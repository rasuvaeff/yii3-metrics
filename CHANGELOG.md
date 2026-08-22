# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0 — 2026-08-22

- **Behaviour change — the RED `route` label no longer defaults to the request
  path.** `config/di.php` and the `RedMetricsMiddleware` constructor now default
  to the new `ConstantRouteResolver`, which returns the constant `(unset)` and
  never reads the request URI. The previous default (`PathRouteResolver`) wrote
  the raw path into a label for every request, including unmatched ones, so
  scanner traffic minted one series per scanned URL (they survive until the
  backend storage is flushed) and paths such as `/reset-password/<token>` were
  copied verbatim into `/metrics`. Rate, errors and duration are unchanged — they
  are still broken down by `method` and `status`. Applications that want a route
  breakdown opt in by rebinding `RouteResolverInterface`: `CurrentRouteResolver`
  (matched router pattern, recommended), the new `BoundedRouteResolver` around
  `PathRouteResolver` (raw paths, capped), or `PathRouteResolver` itself.
- Add `BoundedRouteResolver`: caps how many distinct `route` values a resolver may
  ever emit; everything past the limit collapses into `(other)`.
- Add `ConstantRouteResolver`: a request-independent `route` label.
- Fix `LabelSet::key()` producing the same key for different label sets. The key
  is now length-prefixed (`<len>:<bytes>` per name and value), so it is injective
  even when values contain `=` or `,`. Previously `['a' => '1,b=2', 'b' => '3']`
  and `['a' => '1', 'b' => '2,b=3']` shared the key `a=1,b=2,b=3`, which merged
  two unrelated series wherever the key is used to aggregate — the four
  `InMemory*` instruments and the OTLP backend's gauge tally.
- Reject non-finite recorded amounts: `InMemoryCounter::inc()`,
  `InMemoryHistogram::observe()`, `InMemoryUpDownCounter::add()` and
  `InMemoryGauge::inc()`/`dec()` now throw `InvalidArgumentException` on `NAN` and
  `±INF`. `NAN` passed the `$amount < 0` guard (every comparison with `NAN` is
  false) and is absorbing, so one such recording poisoned a series' total for
  good; in a histogram it also incremented `count` without touching any bucket
  (`NAN <= INF` is false), breaking the `count == bucket{le="+Inf"}` invariant.
  `InMemoryGauge::set()` is an absolute write, so it keeps accepting `±INF` but
  now rejects `NAN`: the exposition has no `NAN` token, and promphp coerces it to
  an invalid one while raising a PHP warning that `yiisoft/error-handler` turns
  into a 500 on `/metrics`.
- Add `Buckets::PROMETHEUS_DEFAULTS`, the default histogram bounds as public API
  (seconds, finite, no trailing `+Inf`), so backends that have to materialise
  "no explicit bounds" read one shared list instead of inventing their own scale.
- Bump the `rasuvaeff/property-testing-testo` dev dependency to `^0.6`.

## 1.1.1 — 2026-07-25

- Reject trailing newlines in metric and label names: anchor the validation
  patterns with `\z` instead of `$` in `Internal\Validation::NAME_PATTERN`
  (metric name) and `LabelSet::NAME_PATTERN` (label name). PCRE `$` matches
  before a trailing `\n`, which let `"<name>\n"` slip through and reach the
  exporter.

## 1.1.0 — 2026-07-25

- Ship an AI agent skill (`resources/skills/rasuvaeff-yii3-metrics/SKILL.md` +
  `extra.skills` in composer.json): projects using the `llm/skills` Composer
  plugin get the skill synced into `.agents/skills/` automatically on install.
- Bump `rasuvaeff/property-testing` dev dependency to `^2.6`.
- Make property-generator methods `public static` (guards against rector
  `RemoveUnusedPrivateMethodRector` deleting reflection-only methods).

## 1.0.0 — 2026-07-10

- Vendor-neutral metrics core: `MetricRegistry` facade over `CounterInterface`,
  `GaugeInterface`, `HistogramInterface`; `MeterInterface` / `MeterProviderInterface`.
- Instruments are interfaces; the core ships `Null*` (no-op) and `InMemory*`
  (single-process dev/test with `snapshots()`) implementations.
- `UpDownCounterInterface` (`add(±δ)`) — counted ups and downs (in-flight
  requests, pool size); unlike a gauge it aggregates correctly across
  short-lived php-fpm workers. `Null*`/`InMemory*` implementations,
  `MetricKind::UpDownCounter`.
- `LabelSet` (validated names, canonical order), `MetricKind`, `MetricSnapshot`,
  `MetricSample`.
- Meters memoize instruments by name; counters reject a negative increment;
  histograms are cumulative (`le`) with an implicit `+Inf` bucket.
- `RedMetricsMiddleware` (PSR-15): `http_server_requests_total` counter and
  `http_server_request_duration_seconds` histogram, with an injectable
  `RouteResolverInterface` (default `PathRouteResolver`), custom histogram
  bounds via `durationBuckets`, and `excludedPaths` for scrape/probe endpoints.
  Both configurable via package params (`red.duration_buckets`,
  `red.excluded_paths`).
- `yiisoft/config` wiring: the core binds only the facade and the route resolver;
  the backend or app owns `MeterProviderInterface`.
- Property tests (counter monotonicity, histogram bucket containment, label-name
  validation, snapshot identity) and a `ConfigWiringTest`.
- `CurrentRouteResolver` — RED `route` label from the matched `yiisoft/router`
  pattern (`/users/{id}`); unmatched requests collapse to `(unmatched)` or an
  injected fallback resolver. `yiisoft/router` is optional (`suggest`).
