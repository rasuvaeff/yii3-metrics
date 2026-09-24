---
name: rasuvaeff-yii3-metrics
description: >-
  Record application metrics in Yii3 with rasuvaeff/yii3-metrics —
  MetricRegistry facade over counter/gauge/upDownCounter/histogram instruments,
  LabelSet, RedMetricsMiddleware (PSR-15 RED), RouteResolverInterface. Use when
  writing, reviewing or debugging metrics instrumentation, RED middleware
  wiring, backend failures, in-memory testing, or Prometheus storage in a
  project that has this package installed.
---

# rasuvaeff/yii3-metrics

Vendor-neutral metrics core for Yii3: the `MetricRegistry` facade hands out
counter/gauge/upDownCounter/histogram instruments; state lives in a backend.
Namespace `Rasuvaeff\Yii3Metrics`.

## Safety rules — verify these on every change

1. **Metric names are Prometheus-strict** — `^[a-zA-Z_:][a-zA-Z0-9_:]*$`,
   underscores, NO dots. Label names `^[a-zA-Z_]\w*$`. Validation runs even in
   `NullMeter`; a bad name throws `Exception\InvalidArgumentException`.

   ```php
   $registry->counter('orders_total');   // correct
   $registry->counter('orders.total');   // throws — dots are invalid
   ```

2. **Never put user ids or other dynamic values in labels.** Every distinct
   label value mints a new time series (cardinality explosion). Labels are for
   small closed sets: method, route pattern, status.

3. **Core must never bind `MeterProviderInterface` in DI.** Exactly one backend
   (`yii3-metrics-prometheus` / `yii3-metrics-otel`) or the app owns that key;
   two vendor bindings cause a `yiisoft/config` `Duplicate key` error.
   An application-layer override of the backend key is supported (see below). The core's
   `config/di.php` binds only `MetricRegistry` + `RouteResolverInterface`.
   Without a backend, the app binds `MeterProviderInterface => NullMeterProvider`.

4. **Counted values → `upDownCounter`, measured values → `gauge`.**
   `add(±δ)` deltas aggregate correctly across php-fpm workers; gauge
   `inc()`/`dec()` tallies are process-local. Counters reject negative `inc()`.
   Every accumulating write (`inc`/`observe`/`add`, gauge `inc`/`dec`) also
   rejects `NAN`/`±INF` — `NAN` is absorbing and would poison the series total
   permanently. Gauge `set()` is absolute: `±INF` is fine, `NAN` still throws
   (no renderable exposition token).

5. **The RED `route` label is opt-in; the default never reads the request URI.**
   Out of the box it is the constant `(unset)` (`ConstantRouteResolver`) — a raw
   path is attacker-controlled, so as a default it mints a series per scanned
   URL and copies path tokens (`/reset-password/<token>`) into `/metrics`. To get
   a real route breakdown, rebind `RouteResolverInterface` to
   `CurrentRouteResolver` (matched `yiisoft/router` pattern, e.g. `/users/{id}`),
   or to `BoundedRouteResolver` around `PathRouteResolver` when you want raw
   paths capped at N distinct values — the cap is per resolver instance, i.e.
   per process (`limit × workers` on fpm), not a deployment-wide guarantee.
   Keep `RedMetricsMiddleware` BEFORE the
   router middleware, and exclude scrape endpoints via
   `excludedPaths: ['/metrics']`.

## Canonical usage

```php
use Rasuvaeff\Yii3Metrics\{MetricRegistry, LabelSet};

$c = $registry->counter('orders_total', 'help', ['channel']);
$c->inc(1.0, new LabelSet(['channel' => 'web']));

$u = $registry->upDownCounter('inflight_jobs');
$u->add(1.0); $u->add(-1.0);

$h = $registry->histogram('db_seconds', 'help', ['op'], [0.01, 0.1, 1.0]); // finite bounds; +Inf auto
$h->observe(0.023, new LabelSet(['op' => 'select']));
```

Histograms are cumulative (`le`); pass finite, increasing bounds — `+Inf` is
appended implicitly. `counter('x')` twice returns the same accumulating series.

## Failure behaviour (core 2.2+)

Storage failures propagate by default, including RED recording after a successful
request. To drop failed writes on application paths, opt into
`FailOpenMeterProvider($inner, $logger, cooldownSeconds: 30.0)` with a PSR-3 logger.
The cooldown belongs to the provider instance and affects all its instruments;
under php-fpm it normally lasts only for that request. The first failed write is
logged, subsequent writes are dropped until retry.

`InvalidArgumentException` from an attempted write is rethrown. During cooldown,
the entire write is skipped, including its validation. Meter/instrument creation
and logger failures are outside this protection. Do not promise that this wrapper
makes every metrics operation non-throwing.

For `yiisoft/config`, put this override in the
application's DI group (e.g. `config/common/di/metrics.php`). Keep the backend's
`CollectorRegistry` and configured namespace; construct the concrete provider
inside the closure. Injecting `MeterProviderInterface` into its own factory would
create a dependency cycle. The application must also bind `LoggerInterface`.

<!-- smoke:di -->
```php
<?php

declare(strict_types=1);

use Prometheus\CollectorRegistry;
use Psr\Log\LoggerInterface;
use Rasuvaeff\Yii3Metrics\{FailOpenMeterProvider, MeterProviderInterface};
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;

return [
    MeterProviderInterface::class => static fn (
        CollectorRegistry $registry,
        LoggerInterface $logger,
    ): MeterProviderInterface => new FailOpenMeterProvider(
        inner: new PrometheusMeterProvider(
            registry: $registry,
            namespace: (string) ($params['rasuvaeff/yii3-metrics-prometheus']['namespace'] ?? ''),
        ),
        logger: $logger,
        cooldownSeconds: 30.0,
    ),
];
```

## Testing (core 2.2+)

Use `InMemoryMeterProvider` and assert on `value(name, labels)` for a single
counter, gauge or up-down counter series. Labels are an array here, not a
`LabelSet`. Missing metrics/series return `null`, not zero.

<!-- smoke:testing -->
```php
use Rasuvaeff\Yii3Metrics\{InMemoryMeterProvider, LabelSet, MetricRegistry};
use Testo\Assert;

$provider = new InMemoryMeterProvider();
$registry = new MetricRegistry($provider);
$registry->counter('requests_total', labelNames: ['source'])
    ->inc(labels: new LabelSet(['source' => 'tiktok.http']));
Assert::same($provider->value('requests_total', ['source' => 'tiktok.http']), 1.0);
Assert::true($provider->has('requests_total'));

$registry->histogram('latency_seconds', buckets: [0.1, 1.0])->observe(0.25);
$sample = $provider->histogram('latency_seconds'); // MetricSample|null
Assert::same($sample?->value, 1.0); // observation count
Assert::same($sample?->sum, 0.25);

$provider->reset();
$registry = new MetricRegistry($provider); // recreate instruments from this registry
Assert::false($provider->has('requests_total'));
$registry->counter('fresh_total')->inc();
Assert::same($provider->value('fresh_total'), 1.0);
```

Helpers: `value(string $name, array $labels = []): ?float`,
`values(string $name): array<string, float>`,
`histogram(string $name, array $labels = []): ?MetricSample`,
`has(string $name): bool`, `reset(): void`.
`has()` checks registration, not whether a sample was recorded.
`values()` returns keys from `LabelSet::key()`, e.g.
`6:source=11:tiktok.http`, not `source=tiktok.http`; prefer `value()` in tests.
`reset()` detaches the meter: existing registries/instruments retain the old
state. Recreate them after reset, or use a fresh provider per test.

## Prometheus backend

These settings apply when `rasuvaeff/yii3-metrics-prometheus` is installed; Redis
prefix configuration and the endpoint behaviour below require backend 2.1+.

- php-fpm needs shared storage: `apcng`/`apcu` within a shared APCu segment, or
  `redis`/`predis`/`pdo`. `in_memory` is suitable for tests, CLI, or a single
  long-running process; it does not aggregate multiple workers.
- Configure Redis in the application's params group:

  ```php
  return [
      'rasuvaeff/yii3-metrics-prometheus' => [
          'storage' => 'redis', // requires ext-redis; predis requires predis/predis
          'storage_options' => [
              'host' => 'redis',
              'port' => 6379,
              'prefix' => 'checkout:PROMETHEUS_',
              'persistent_connections' => true,
              'timeout' => 0.2,
              'read_timeout' => 0.5,
              'database' => 2,
          ],
      ],
  ];
  ```

  These connection options are for `redis`; use the Predis client's options for
  `predis`. The storage `prefix` is process-global in promphp and separate from
  the metric-name `namespace`. Use one prefix per application process and distinct
  prefixes for applications sharing Redis. Bound connection/read timeouts.
- `MetricsEndpoint` returns `503` and `metrics storage unavailable` when rendering
  raises `Prometheus\Exception\StorageException`; it does not catch every throwable.
  Fail-open writes do not hide scrape failures. Restrict `/metrics` at the edge;
  the handler has no access control. Exclude it from RED recording.
- Scrape each independent storage dataset once. When replicas share the same
  Redis database/prefix, use one logical scrape target for that dataset; scraping
  every replica and summing the results duplicates the same counters. Separate
  APCu segments or isolated prefixes need separate targets.

## Full API

The complete reference — instrument signatures, providers (`Null*`,
`InMemory*` helpers and `snapshots()`, `FailOpenMeterProvider`), `RedMetricsMiddleware` options, resolvers,
`LabelSet` — ships with the package: read
`vendor/rasuvaeff/yii3-metrics/llms.txt` before guessing a method name.
