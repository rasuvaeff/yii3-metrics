<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * A gauge declared once — name, help text and label names — and
 * passed to {@see MetricRegistry::gauge()} wherever the metric is recorded,
 * so call sites cannot drift apart. Validated on construction.
 *
 * @api
 */
final readonly class GaugeDefinition
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
