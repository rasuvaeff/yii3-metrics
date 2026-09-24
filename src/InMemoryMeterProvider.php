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

    /**
     * Return one scalar sample by metric name and labels, or null when absent.
     *
     * @param array<array-key, scalar> $labels
     */
    public function value(string $name, array $labels = []): ?float
    {
        $sample = $this->sample($name, new LabelSet($labels));

        return $sample?->value;
    }

    /**
     * @return array<string, float>
     */
    public function values(string $name): array
    {
        foreach ($this->snapshots() as $snapshot) {
            if ($snapshot->name !== $name) {
                continue;
            }

            $values = [];

            foreach ($snapshot->samples as $sample) {
                $values[$sample->labels->key()] = $sample->value;
            }

            return $values;
        }

        return [];
    }

    /**
     * @param array<array-key, scalar> $labels
     */
    public function histogram(string $name, array $labels = []): ?MetricSample
    {
        return $this->sample($name, new LabelSet($labels));
    }

    public function has(string $name): bool
    {
        foreach ($this->snapshots() as $snapshot) {
            if ($snapshot->name === $name) {
                return true;
            }
        }

        return false;
    }

    public function reset(): void
    {
        $this->meter = null;
    }

    private function sample(string $name, LabelSet $labels): ?MetricSample
    {
        foreach ($this->snapshots() as $snapshot) {
            if ($snapshot->name !== $name) {
                continue;
            }

            foreach ($snapshot->samples as $sample) {
                if ($sample->labels->equals($labels)) {
                    return $sample;
                }
            }
        }

        return null;
    }
}
