# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — Unreleased

- Vendor-neutral metrics core: `MetricRegistry` facade over `CounterInterface`,
  `GaugeInterface`, `HistogramInterface`; `MeterInterface` / `MeterProviderInterface`.
- Instruments are interfaces; the core ships `Null*` (no-op) and `InMemory*`
  (single-process dev/test with `snapshots()`) implementations.
- `LabelSet` (validated names, canonical order), `MetricKind`, `MetricSnapshot`,
  `MetricSample`.
- Meters memoize instruments by name; counters reject a negative increment;
  histograms are cumulative (`le`) with an implicit `+Inf` bucket.
- `RedMetricsMiddleware` (PSR-15): `http_server_requests_total` counter and
  `http_server_request_duration_seconds` histogram, with an injectable
  `RouteResolverInterface` (default `PathRouteResolver`).
- `yiisoft/config` wiring: the core binds only the facade and the route resolver;
  the backend or app owns `MeterProviderInterface`.
- Property tests (counter monotonicity, histogram bucket containment, label-name
  validation, snapshot identity) and a `ConfigWiringTest`.
- `CurrentRouteResolver` — RED `route` label from the matched `yiisoft/router`
  pattern (`/users/{id}`); unmatched requests collapse to `(unmatched)` or an
  injected fallback resolver. `yiisoft/router` is optional (`suggest`).
