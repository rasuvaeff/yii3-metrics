# AGENTS.md — yii3-metrics

Guidance for AI agents working on this package. Read before changing code.

## What this is

The vendor-neutral **metrics core** for Yii3: a `MetricRegistry` facade over
counters, gauges, and histograms, plus a PSR-15 RED middleware. Instruments are
interfaces — state lives in a backend (`yii3-metrics-prometheus` / `-otel`); the
core ships only `Null*` (no-op) and `InMemory*` (single-process dev/test) impls.

Namespace: `Rasuvaeff\Yii3Metrics`.

Public API: `MetricRegistry` (facade), `MeterProviderInterface`, `MeterInterface`,
`CounterInterface`, `GaugeInterface`, `HistogramInterface`, `LabelSet`,
`MetricKind`, `MetricSnapshot`, `MetricSample`, `Null*` and `InMemory*`
(meter/provider/counter/gauge/histogram), `RedMetricsMiddleware`,
`RouteResolverInterface`, `PathRouteResolver`, `Exception\InvalidArgumentException`.

## DI wiring (core+backend)

`config/di.php` binds the facade (`MetricRegistry`) and the default
`RouteResolverInterface => PathRouteResolver`. It must **never** bind
`MeterProviderInterface` — that swappable key is owned by exactly one backend or
the app (`MeterProviderInterface => NullMeterProvider`). Binding it twice is a
`yiisoft/config` `Duplicate key` error, by design. `config/di-web.php` binds
`RedMetricsMiddleware` (web-only). `ConfigWiringTest` guards this.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **A meter MUST memoize instruments by name, and validation is split by axis.**
   - Same `(kind, name)` → the SAME instrument instance (metrics accumulate; RED
     increments one counter per request). A fresh instance would lose state.
   - **Structural validation always** (even in `NullMeter`): metric-name regex
     (`^[a-zA-Z_:][a-zA-Z0-9_:]*$`, Prometheus — no dots) and histogram bucket
     monotonicity; `LabelSet` validates label-name format in its constructor.
   - **Recording checks only in recording impls** (`InMemory*`, backends), NOT
     `Null*`: a counter rejects a negative increment.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`,
`make test-coverage`, `make mutation`, `make release-check`.
`make test-coverage` and `make mutation` bootstrap `pcov` in the container.
`composer.lock` is gitignored (library).

## Invariants & gotchas

- **Metric names are Prometheus-strict (underscores, no dots)** — the lowest
  common denominator both backends render. RED metrics are
  `http_server_requests_total` / `http_server_request_duration_seconds`, NOT the
  dotted names in the plan (which contradict the name regex).
- **RED `route` label is a cardinality footgun.** The default `PathRouteResolver`
  uses the raw path (a series per `/users/123`). The sanitizer/router-template
  resolver belongs in the Prometheus backend; inject a `RouteResolverInterface`
  in production.
- Histograms are cumulative (`le`): an observation increments every bucket whose
  bound >= the value. `+Inf` is appended implicitly; callers pass finite bounds.
- `MetricSnapshot` carries NO timestamp (so two snapshots of unchanged state are
  equal) and is produced only by `InMemory*` for dev/test — backends use their
  own storage, not this.
- RED duration uses `hrtime(true)` directly; this package does NOT depend on
  `rasuvaeff/yii3-telemetry` (the two stacks are independent).
- `property-testing` needs `ext-mbstring` in every CI job (already in the
  workflows); it is a dev/CI concern, not a runtime `require`.
- Code: `declare(strict_types=1)`, `final readonly class` (or `final class` for a
  static/singleton or mutable state), `#[\Override]`, explicit types.
- **CI workflows are SHA-pinned** (`uses:` → 40-char SHA + `# vN`),
  `permissions: { contents: read }`, `persist-credentials: false` on every
  checkout. Verify with `zizmor --persona=auditor .github/`.
- `examples/` is part of the public contract: keep scripts runnable.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; paste the output. For releases also run mutation and
  `release-check`.
