# rasuvaeff/yii3-metrics

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-metrics.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-metrics.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-metrics/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-metrics)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-metrics/php)](https://packagist.org/packages/rasuvaeff/yii3-metrics)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-metrics.svg)](https://github.com/rasuvaeff/yii3-metrics/blob/master/LICENSE.md)

Vendor-neutral metrics for Yii3: a `MetricRegistry` facade over counters, gauges,
and histograms, plus a PSR-15 RED middleware. The exporter is a swappable backend
(Prometheus or OTLP).

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference
> you can pass as context.

## Requirements

- PHP 8.3+
- PSR-7/PSR-15 interfaces (for the RED middleware)

## Installation

```bash
composer require rasuvaeff/yii3-metrics
```

For real export, add a backend (later sprints): `rasuvaeff/yii3-metrics-prometheus`
or `rasuvaeff/yii3-metrics-otel`. Without one, bind
`MeterProviderInterface => NullMeterProvider` (see [Wiring](#wiring-yiisoftconfig)).

## Usage

### Record metrics

```php
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;

/** @var MetricRegistry $registry (injected) */
$orders = $registry->counter('orders_total', 'Orders placed', ['channel']);
$orders->inc(1.0, new LabelSet(['channel' => 'web']));

$inflight = $registry->gauge('inflight_jobs', 'Jobs in flight');
$inflight->inc();
$inflight->dec();

$latency = $registry->histogram('db_query_seconds', 'Query time', ['op'], [0.001, 0.01, 0.1]);
$latency->observe(0.023, new LabelSet(['op' => 'select']));
```

Instruments are memoized by name — asking for `counter('orders_total')` again
returns the same accumulating instrument. A counter rejects a negative increment
(use a gauge for values that go down).

### Naming & labels

- Metric names follow the **Prometheus** grammar `^[a-zA-Z_:][a-zA-Z0-9_:]*$`
  (underscores, no dots) — the lowest common denominator both backends render.
- `LabelSet` validates label names (`^[a-zA-Z_]\w*$`) and stores them in canonical
  order, so equality is order-independent.

### RED middleware

`RedMetricsMiddleware` (PSR-15) records, for every request, a
`http_server_requests_total` counter and a `http_server_request_duration_seconds`
histogram, labelled by `method`, `route`, and `status` (`500` when the handler
throws).

```php
use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;

$middleware = new RedMetricsMiddleware($registry); // add to your PSR-15 stack
```

> **Cardinality:** the `route` label defaults to the raw path — a new time series
> per `/users/123`. Inject a router-aware `RouteResolverInterface` (mapping to a
> template like `/users/{id}`) in production.

### Inspecting metrics in tests

```php
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\MetricRegistry;

$provider = new InMemoryMeterProvider();
$registry = new MetricRegistry($provider);
$registry->counter('c')->inc();

$snapshots = $provider->snapshots(); // list<MetricSnapshot>, no timestamp
```

## Wiring (`yiisoft/config`)

The core `config/di.php` binds the facade (`MetricRegistry`) and the default
`RouteResolverInterface`. It never binds `MeterProviderInterface` — that swappable
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
- The RED `route` label defaults to the path; sanitize it in production.

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
