<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Supplies the active {@see MeterInterface}. Exactly one binding owns this
 * interface — a backend (e.g. `yii3-metrics-prometheus`) or the application
 * (config-only `NullMeterProvider`). The core never binds it.
 *
 * @api
 */
interface MeterProviderInterface
{
    /**
     * `$name` identifies the caller (an instrumentation scope) for diagnostics
     * only: metric state is global per `(kind, metric name)`, so a provider MAY
     * return the same meter instance for every name. Two libraries asking for
     * their own meters still record into the same underlying series.
     */
    public function getMeter(?string $name = null): MeterInterface;
}
