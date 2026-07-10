<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Single-process up-down counter for tests and dev.
 *
 * @api
 */
final class InMemoryUpDownCounter implements UpDownCounterInterface
{
    /** @var array<string, float> */
    private array $values = [];

    /** @var array<string, LabelSet> */
    private array $labelSets = [];

    public function __construct(
        private readonly string $name,
        private readonly string $help,
    ) {}

    #[\Override]
    public function add(float $delta, LabelSet $labels = new LabelSet()): void
    {
        $key = $labels->key();
        $this->values[$key] = ($this->values[$key] ?? 0.0) + $delta;
        $this->labelSets[$key] = $labels;
    }

    public function snapshot(): MetricSnapshot
    {
        $samples = [];

        foreach ($this->values as $key => $value) {
            $samples[] = new MetricSample($this->labelSets[$key], $value);
        }

        return new MetricSnapshot($this->name, MetricKind::UpDownCounter, $this->help, $samples);
    }
}
