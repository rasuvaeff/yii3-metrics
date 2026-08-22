---
name: rasuvaeff-yii3-metrics
description: >-
  Record application metrics in Yii3 with rasuvaeff/yii3-metrics —
  MetricRegistry facade over counter/gauge/upDownCounter/histogram instruments,
  LabelSet, RedMetricsMiddleware (PSR-15 RED), RouteResolverInterface. Use when
  writing, reviewing or debugging metrics instrumentation, RED middleware
  wiring, or label/metric naming in a project that has this package installed.
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
   a second binding is a `yiisoft/config` `Duplicate key` error. The core's
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
   paths capped at N distinct values. Keep `RedMetricsMiddleware` BEFORE the
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

## Full API

The complete reference — instrument signatures, providers (`Null*`,
`InMemory*` with `snapshots()`), `RedMetricsMiddleware` options, resolvers,
`LabelSet` — ships with the package: read
`vendor/rasuvaeff/yii3-metrics/llms.txt` before guessing a method name.
