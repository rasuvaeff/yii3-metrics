# rasuvaeff/yii3-metrics

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-metrics.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-metrics.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-metrics/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-metrics)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-metrics/php)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-metrics.svg)](https://github.com/rasuvaeff/yii3-metrics/blob/master/LICENSE.md)
[Русская версия](README.ru.md)

Vendor-neutral metrics for Yii3: a `MetricRegistry` facade over counters, gauges,
and histograms, plus a PSR-15 RED middleware. The exporter is a swappable backend
(Prometheus today; the swappable provider key leaves room for others).

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference
> you can pass as context.
> Projects using the [llm/skills](https://github.com/roxblnfk/skills) Composer
> plugin also get this package's agent skill synced into `.agents/skills/`
> automatically on install.

## Requirements

- PHP 8.3+
- PSR-7/PSR-15 interfaces (for the RED middleware)

## Installation

```bash
composer require rasuvaeff/yii3-metrics
```

For real export, add the backend: `rasuvaeff/yii3-metrics-prometheus`.
Without one, bind
`MeterProviderInterface => NullMeterProvider` (see [Wiring](#wiring-yiisoftconfig)).

## Usage

### Record metrics

```php
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;

/** @var MetricRegistry $registry (injected) */
$orders = $registry->counter('orders_total', 'Orders placed', ['channel']);
$orders->inc(1.0, new LabelSet(['channel' => 'web']));

$inflight = $registry->upDownCounter('inflight_jobs', 'Jobs in flight');
$inflight->add(1.0);   // job started
$inflight->add(-1.0);  // job finished

$temperature = $registry->gauge('room_temperature', 'Measured value');
$temperature->set(21.5);

$latency = $registry->histogram('db_query_seconds', 'Query time', ['op'], [0.001, 0.01, 0.1]);
$latency->observe(0.023, new LabelSet(['op' => 'select']));
```

Instruments record into per-name accumulating state — asking for
`counter('orders_total')` again returns an instrument over the same series. A
counter rejects a negative increment.

**Gauge vs up-down counter.** A gauge is for a *measured absolute value*
(`set()` — temperature, disk usage); an up-down counter is for *counted ups and
downs* (`add(±δ)` — in-flight requests, pool size). Prefer the up-down counter
for counted values: each process contributes deltas, so it aggregates correctly
across short-lived php-fpm workers, where a gauge's `inc()`/`dec()` (kept for
single-process convenience) would restart from the process-local value.

### Naming & labels

- Metric names follow the **Prometheus** grammar `^[a-zA-Z_:][a-zA-Z0-9_:]*$`
  (underscores, no dots) — the lowest common denominator both backends render.
- `LabelSet` validates label names (`^[a-zA-Z_]\w*$`) and stores them in canonical
  order, so equality is order-independent.
- `LabelSet::key()` is the aggregation key. Every name and value is
  length-prefixed (`<len>:<bytes>`), so distinct label sets always get distinct
  keys even when values contain `=` or `,`. The exact string is an internal
  detail — compare label sets with `equals()`, not with `key()` strings you
  stored elsewhere.
- **Recorded amounts must be finite.** `counter->inc()`, `histogram->observe()`,
  `upDownCounter->add()` and `gauge->inc()/dec()` reject `NAN` and `±INF` with
  `Exception\InvalidArgumentException`: `NAN` is absorbing, so one such recording
  would poison a series for as long as the backend storage lives. `gauge->set()`
  is an absolute write, so it accepts `±INF` (the exposition has `+Inf`/`-Inf`
  tokens) but still rejects `NAN`, which promphp coerces to an invalid token
  while raising a PHP warning.

### RED middleware

`RedMetricsMiddleware` (PSR-15) records, for every request, a
`http_server_requests_total` counter and a `http_server_request_duration_seconds`
histogram, labelled by `method`, `route`, and `status` (`500` when the handler
throws).

```php
use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;

$middleware = new RedMetricsMiddleware($registry); // add to your PSR-15 stack

// Latency profile doesn't fit the Prometheus defaults (0.005s…10s)?
// Override the histogram bounds (seconds, strictly increasing; +Inf appended):
$middleware = new RedMetricsMiddleware($registry, durationBuckets: [0.1, 1.0, 10.0, 60.0]);

// Skip scrape/probe endpoints (exact paths) — their self-traffic is noise:
$middleware = new RedMetricsMiddleware($registry, excludedPaths: ['/metrics', '/health']);
```

With `yiisoft/config` wiring, both come from the package params instead:

```php
// config/common/params.php (app override)
'rasuvaeff/yii3-metrics' => [
    'red' => [
        'duration_buckets' => [0.1, 1.0, 10.0],
        'excluded_paths' => ['/metrics', '/health'],
    ],
],
```

#### The `route` label is opt-in

> **The shipped default never reads the request URI.** Without configuration the
> `route` label is the constant `(unset)` (`ConstantRouteResolver`). Rate, errors
> and duration are still broken down by `method` and `status`; only the per-route
> breakdown has to be chosen.

A raw path cannot be a safe default, because it is attacker-controlled:

| Risk | What happens with a raw-path `route` |
|---|---|
| Cardinality | one series per scanned URL (`/wp-admin/...`, `/.env`, `/users/123`). In a shared promphp storage those series live until a flush: the APCu segment fills, Redis memory and scrape time grow. |
| Disclosure | `/reset-password/<token>` reaches `/metrics` verbatim, so everyone who can scrape the endpoint reads the token. |

Pick one of three resolvers, most to least safe:

```php
use Rasuvaeff\Yii3Metrics\{BoundedRouteResolver, CurrentRouteResolver, PathRouteResolver, RouteResolverInterface};

// 1. Matched router pattern ('/users/{id}'), low-cardinality by construction.
//    Unmatched requests (404, scanners) collapse to '(unmatched)'. Preferred.
RouteResolverInterface::class => CurrentRouteResolver::class,

// 2. Raw paths with a hard cap: the first N distinct values pass, the rest
//    become '(other)'. Bounds the series count; does NOT hide path tokens.
RouteResolverInterface::class => static fn (): RouteResolverInterface
    => new BoundedRouteResolver(new PathRouteResolver(), limit: 100),

// 3. Raw paths, unbounded — only where the path space is small and secret-free.
RouteResolverInterface::class => PathRouteResolver::class,
```

The Prometheus backend additionally ships `SanitizingRouteResolver`, which
collapses numeric ids and UUIDs in a raw path. It narrows the id case only —
arbitrary scanner paths and non-UUID tokens stay unique, so treat it as a
refinement of option 3, not a replacement for options 1–2.

`CurrentRouteResolver` needs `yiisoft/router`. Place `RedMetricsMiddleware`
**before** the router middleware — the label is resolved after the handler ran,
when `CurrentRoute` is populated.

### Inspecting metrics in tests

```php
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\MetricRegistry;

$provider = new InMemoryMeterProvider();
$registry = new MetricRegistry($provider);
$registry->counter('c')->inc();

$snapshots = $provider->snapshots(); // list<MetricSnapshot>, no timestamp
```

### API surface

| Type | Role |
|---|---|
| `MetricRegistry` | facade: `counter/gauge/upDownCounter/histogram(name, help, labelNames, buckets)` |
| `MeterProviderInterface` / `MeterInterface` | swappable backend entry point; a meter creates and memoizes instruments |
| `CounterInterface` / `GaugeInterface` / `UpDownCounterInterface` / `HistogramInterface` | instrument contracts |
| `LabelSet` / `MetricKind` | validated label pairs / instrument kind enum (`Counter`, `Gauge`, `UpDownCounter`, `Histogram`) |
| `MetricSnapshot` / `MetricSample` | collected state: a metric (name, kind, help) and its per-label-set samples |
| `NullMeterProvider`, `NullMeter`, `NullCounter`, `NullGauge`, `NullUpDownCounter`, `NullHistogram` | no-op backend (config-only default; still validates structure) |
| `InMemoryMeterProvider`, `InMemoryMeter`, `InMemoryCounter`, `InMemoryGauge`, `InMemoryUpDownCounter`, `InMemoryHistogram` | single-process dev/test backend with `snapshots()` |
| `RedMetricsMiddleware`, `RouteResolverInterface` | PSR-15 RED instrumentation |
| `ConstantRouteResolver` | safe default `route` label: a constant, never derived from the request |
| `PathRouteResolver`, `BoundedRouteResolver` | opt-in raw-path label; the bounded decorator caps how many distinct values are ever emitted |
| `CurrentRouteResolver` | route label from the matched `yiisoft/router` pattern (optional dep) |
| `Buckets` | shared histogram bucket layouts (`Buckets::PROMETHEUS_DEFAULTS`, seconds, no trailing `+Inf`) |

## Wiring (`yiisoft/config`)

The core `config/di.php` binds the facade (`MetricRegistry`) and the default
`RouteResolverInterface` (`ConstantRouteResolver` — see "The `route` label is
opt-in"). It never binds `MeterProviderInterface` — that swappable
key is owned by exactly one source:

```php
// config/common/di.php — with no backend installed
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3Metrics\NullMeterProvider;

return [
    MeterProviderInterface::class => NullMeterProvider::class,
];
```

Installing a backend provides the real binding — binding it in two vendor packages
is a deliberate `yiisoft/config` `Duplicate key` error.

## Security

- Label names are validated; label **values** are arbitrary — keep
  high-cardinality or sensitive values (ids, tokens) out of labels.
- The RED `route` label is **opt-in**: the shipped default is the constant
  `(unset)`, precisely so an attacker-controlled path cannot mint series or carry
  a single-use token into `/metrics`. See "The `route` label is opt-in" before
  enabling a path-derived label.
- The exposition endpoint has no access control of its own — close `/metrics` at
  the edge/router.

## Examples

Runnable, server-independent scripts in [`examples/`](examples/). See
[`examples/README.md`](examples/README.md).

## Development

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Runs validate → normalize → require-checker → cs → psalm → tests (incl. property
tests). See [AGENTS.md](AGENTS.md).

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
