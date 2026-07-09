<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * In-process meter provider for tests and dev. Exposes {@see snapshots()} to
 * inspect recorded state without an export backend.
 *
 * @api
 */
final class InMemoryMeterProvider implements MeterProviderInterface
{
    private ?InMemoryMeter $meter = null;

    #[\Override]
    public function getMeter(?string $name = null): MeterInterface
    {
        return $this->meter ??= new InMemoryMeter();
    }

    /**
     * @return list<MetricSnapshot>
     */
    public function snapshots(): array
    {
        return $this->meter?->snapshots() ?? [];
    }
}
