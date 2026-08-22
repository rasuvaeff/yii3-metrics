# AGENTS.md — yii3-metrics

Guidance for AI agents working on this package. Read before changing code.

## What this is

The vendor-neutral **metrics core** for Yii3: a `MetricRegistry` facade over
counters, gauges, and histograms, plus a PSR-15 RED middleware. Instruments are
interfaces — state lives in a backend (`yii3-metrics-prometheus`; the OTLP
backend `yii3-metrics-otel` exists in the monorepo but is deliberately
UNPUBLISHED — publish only on a real long-running-runtime / OTLP-pipeline
request); the
core ships only `Null*` (no-op) and `InMemory*` (single-process dev/test) impls.

Namespace: `Rasuvaeff\Yii3Metrics`.

Public API: `MetricRegistry` (facade), `MeterProviderInterface`, `MeterInterface`,
`CounterInterface`, `GaugeInterface`, `UpDownCounterInterface`,
`HistogramInterface`, `LabelSet`,
`MetricKind`, `MetricSnapshot`, `MetricSample`, `Null*` and `InMemory*`
(meter/provider/counter/gauge/histogram), `RedMetricsMiddleware`,
`RouteResolverInterface`, `ConstantRouteResolver`, `PathRouteResolver`,
`BoundedRouteResolver`, `CurrentRouteResolver`, `Buckets`,
`Exception\InvalidArgumentException`.

## DI wiring (core+backend)

`config/di.php` binds the facade (`MetricRegistry`) and the default
`RouteResolverInterface => ConstantRouteResolver`. It must **never** bind
`MeterProviderInterface` — that swappable key is owned by exactly one backend or
the app (`MeterProviderInterface => NullMeterProvider`). Binding it twice is a
`yiisoft/config` `Duplicate key` error, by design. `config/di-web.php` binds
`RedMetricsMiddleware` (web-only). `ConfigWiringTest` guards this.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **A meter MUST keep per-name accumulating state, and validation is split by axis.**
   - Same `(kind, name)` → an instrument recording into the SAME underlying
     state (metrics accumulate; RED increments one counter per request).
     Instance identity is NOT part of the contract: a backend whose SDK already
     aggregates by name may hand out fresh stateless wrappers, but any
     instrument that itself holds state must be memoized.
   - **Structural validation always** (even in `NullMeter`): metric-name regex
     (`^[a-zA-Z_:][a-zA-Z0-9_:]*$`, Prometheus — no dots) and histogram bucket
     monotonicity; `LabelSet` validates label-name format in its constructor.
   - **Recording checks only in recording impls** (`InMemory*`, backends), NOT
     `Null*`: a counter rejects a negative increment, and every accumulating
     write (`inc`/`observe`/`add`/gauge `inc`/`dec`) rejects `NAN`/`±INF` via
     `Validation::finiteAmount()`. Gauge `set()` is absolute, so it allows `±INF`
     but rejects `NAN` (`Validation::notNan()`) — promphp coerces `NAN` to an
     invalid exposition token and raises a PHP warning doing it. Any new backend
     must copy both guards; a backend without them makes `NAN` behave differently
     per backend, which is the bug they were added for.
   - **Gauge vs UpDownCounter**: gauge = measured absolute (`set()`);
     up-down counter = counted deltas (`add(±δ)`, no set) — the fpm-safe choice
     for counted values. Backends map it to promphp gauge (`incBy`) / OTel
     UpDownCounter respectively.
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
- **RED `route` label is opt-in, and the default must stay request-independent.**
  `ConstantRouteResolver` (`(unset)`) is the DI and middleware default: a raw
  path is attacker-controlled, so as a default it both explodes cardinality and
  copies path tokens (`/reset-password/<token>`) into `/metrics`. Never restore a
  URI-reading default. Applications opt in by rebinding
  `RouteResolverInterface` to `CurrentRouteResolver` (matched `yiisoft/router`
  pattern), `BoundedRouteResolver` around `PathRouteResolver` (capped raw paths),
  `PathRouteResolver`, or the Prometheus backend's `SanitizingRouteResolver`.
- **`LabelSet::key()` must stay injective.** Values are untrusted strings that can
  contain the separators, so every name and value is length-prefixed. A plain
  `name=value` join merged distinct series into one; do not "simplify" it back.
- **`Buckets::PROMETHEUS_DEFAULTS` carries NO trailing `+Inf`.** Backends append
  (or model) the overflow bucket themselves; `Validation::histogramBuckets()`
  appends it for the in-memory path. Passing the already-`INF`-terminated list to
  a backend is a silently wrong schema.
- **`CurrentRouteResolver` is an optional-dep class** (`yiisoft/router` in
  `suggest` + `require-dev`, symbol whitelisted in `composer-require-checker.json`
  — do not delete that file). It must NOT be bound in the core `di.php` — the
  container would fatal without `yiisoft/router` installed. `RedMetricsMiddleware`
  sits BEFORE the router middleware: the label resolves in `finally` after the
  handler ran, when `CurrentRoute` is populated. Unmatched requests collapse to
  `(unmatched)` by default (scanner traffic must not mint series); an injected
  fallback resolver overrides that. `yiisoft/dummy-provider` satisfies the
  `yiisoft/router-implementation` virtual package in `require-dev`.
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
