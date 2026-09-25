<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Config-only default provider. Bind `MeterProviderInterface => NullMeterProvider`
 * in the application when no metrics backend is installed, and the
 * {@see MetricRegistry} facade becomes a fully no-op registry.
 *
 * @api
 */
final readonly class NullMeterProvider implements MeterProviderInterface
{
    private NullMeter $meter;

    /**
     * @param bool $strictNaming enforce suffix conventions and reject conflicting re-registration
     */
    public function __construct(bool $strictNaming = false)
    {
        $this->meter = $strictNaming ? new NullMeter(strictNaming: true) : NullMeter::instance();
    }

    #[\Override]
    public function getMeter(?string $name = null): MeterInterface
    {
        return $this->meter;
    }
}
