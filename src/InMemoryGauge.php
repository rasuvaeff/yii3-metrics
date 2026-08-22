<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * Single-process gauge for tests and dev. `set()` is an absolute write, so it
 * accepts `±INF` (both backends have a token for it) but not `NAN`, which has no
 * renderable form. `inc()`/`dec()` accumulate, so they reject any non-finite
 * amount.
 *
 * @api
 */
final class InMemoryGauge implements GaugeInterface
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
    public function set(float $value, LabelSet $labels = new LabelSet()): void
    {
        Validation::notNan($value);

        $key = $labels->key();
        $this->values[$key] = $value;
        $this->labelSets[$key] = $labels;
    }

    #[\Override]
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        $this->adjust($amount, $labels);
    }

    #[\Override]
    public function dec(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        $this->adjust(-$amount, $labels);
    }

    public function snapshot(): MetricSnapshot
    {
        $samples = [];

        foreach ($this->values as $key => $value) {
            $samples[] = new MetricSample($this->labelSets[$key], $value);
        }

        return new MetricSnapshot($this->name, MetricKind::Gauge, $this->help, $samples);
    }

    private function adjust(float $delta, LabelSet $labels): void
    {
        Validation::finiteAmount($delta);

        $key = $labels->key();
        $this->values[$key] = ($this->values[$key] ?? 0.0) + $delta;
        $this->labelSets[$key] = $labels;
    }
}
