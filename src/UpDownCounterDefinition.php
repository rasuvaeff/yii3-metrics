<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * An up-down counter declared once — name, help text and label names — and
 * passed to {@see MetricRegistry::upDownCounter()} wherever the metric is recorded,
 * so call sites cannot drift apart. Validated on construction.
 *
 * @api
 */
final readonly class UpDownCounterDefinition
{
    /**
     * @param list<string> $labelNames
     */
    public function __construct(
        public string $name,
        public string $help = '',
        public array $labelNames = [],
    ) {
        Validation::metricName($name);
        Validation::labelNames($labelNames);
    }
}
