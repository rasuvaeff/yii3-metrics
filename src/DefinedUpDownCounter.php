<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\DefinedLabels;

/**
 * An up-down counter returned by {@see MetricRegistry} for a {@see UpDownCounterDefinition}. Takes labels
 * as a plain `array<string, scalar>` as well as a {@see LabelSet}, and checks
 * their names against the definition before recording — a missing or extra
 * label fails here, naming the metric, rather than deep in a backend.
 *
 * @api
 */
final readonly class DefinedUpDownCounter implements UpDownCounterInterface
{
    public function __construct(
        private UpDownCounterInterface $instrument,
        private UpDownCounterDefinition $definition,
    ) {}

    /**
     * @param LabelSet|array<string, scalar> $labels
     */
    #[\Override]
    public function add(float $delta, array|LabelSet $labels = new LabelSet()): void
    {
        $this->instrument->add($delta, DefinedLabels::resolve($this->definition->name, $this->definition->labelNames, $labels));
    }

}
