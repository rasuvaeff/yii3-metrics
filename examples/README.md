# Examples

Runnable examples for `rasuvaeff/yii3-metrics`. Each is self-contained and needs
no external services.

| Script | Shows | Needs server? |
|---|---|---|
| `01_in_memory.php` | Counter/gauge/histogram via `InMemoryMeterProvider`, snapshot inspection | no |
| `02_red_middleware.php` | `RedMetricsMiddleware` recording RED metrics for two requests | no |

## Running

```bash
composer install
php examples/01_in_memory.php
php examples/02_red_middleware.php
```

Or via Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/01_in_memory.php
```

In production, swap `InMemoryMeterProvider` for a real backend
(`rasuvaeff/yii3-metrics-prometheus` or `-otel`).
