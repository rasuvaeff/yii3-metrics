<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\DefinedLabels;

/**
 * A histogram returned by {@see MetricRegistry} for a {@see HistogramDefinition}. Takes labels
 * as a plain `array<string, scalar>` as well as a {@see LabelSet}, and checks
 * their names against the definition before recording — a missing or extra
 * label fails here, naming the metric, rather than deep in a backend.
 *
 * @api
 */
final readonly class DefinedHistogram implements HistogramInterface
{
    public function __construct(
        private HistogramInterface $instrument,
        private HistogramDefinition $definition,
    ) {}

    /**
     * @param LabelSet|array<string, scalar> $labels
     */
    #[\Override]
    public function observe(float $value, array|LabelSet $labels = new LabelSet()): void
    {
        $this->instrument->observe($value, DefinedLabels::resolve($this->definition->name, $this->definition->labelNames, $labels));
    }

}
