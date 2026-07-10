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
    public function getMeter(?string $name = null): MeterInterface;
}
