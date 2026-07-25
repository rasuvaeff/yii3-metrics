# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
