<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * Single-process counter for tests and dev. A recording counter rejects a
 * negative increment (use a {@see GaugeInterface} instead) and a non-finite one.
 *
 * @api
 */
final class InMemoryCounter implements CounterInterface
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
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        Validation::finiteAmount($amount);

        if ($amount < 0) {
            throw new InvalidArgumentException('Counter cannot be decremented; use a gauge');
        }

        $key = $labels->key();
        $this->values[$key] = ($this->values[$key] ?? 0.0) + $amount;
        $this->labelSets[$key] = $labels;
    }

    public function snapshot(): MetricSnapshot
    {
        $samples = [];

        foreach ($this->values as $key => $value) {
            $samples[] = new MetricSample($this->labelSets[$key], $value);
        }

        return new MetricSnapshot($this->name, MetricKind::Counter, $this->help, $samples);
    }
}
