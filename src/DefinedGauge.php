<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\DefinedLabels;

/**
 * A gauge returned by {@see MetricRegistry} for a {@see GaugeDefinition}. Takes labels
 * as a plain `array<string, scalar>` as well as a {@see LabelSet}, and checks
 * their names against the definition before recording — a missing or extra
 * label fails here, naming the metric, rather than deep in a backend.
 *
 * @api
 */
final readonly class DefinedGauge implements GaugeInterface
{
    public function __construct(
        private GaugeInterface $instrument,
        private GaugeDefinition $definition,
    ) {}

    /**
     * @param LabelSet|array<string, scalar> $labels
     */
    #[\Override]
    public function set(float $value, array|LabelSet $labels = new LabelSet()): void
    {
        $this->instrument->set($value, DefinedLabels::resolve($this->definition->name, $this->definition->labelNames, $labels));
    }

    /**
     * @param LabelSet|array<string, scalar> $labels
     */
    #[\Override]
    public function inc(float $amount = 1.0, array|LabelSet $labels = new LabelSet()): void
    {
        $this->instrument->inc($amount, DefinedLabels::resolve($this->definition->name, $this->definition->labelNames, $labels));
    }

    /**
     * @param LabelSet|array<string, scalar> $labels
     */
    #[\Override]
    public function dec(float $amount = 1.0, array|LabelSet $labels = new LabelSet()): void
    {
        $this->instrument->dec($amount, DefinedLabels::resolve($this->definition->name, $this->definition->labelNames, $labels));
    }

}
