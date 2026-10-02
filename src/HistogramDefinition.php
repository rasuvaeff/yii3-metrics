<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * A histogram declared once — name, help text and label names and buckets — and
 * passed to {@see MetricRegistry::histogram()} wherever the metric is recorded,
 * so call sites cannot drift apart. Validated on construction.
 *
 * @api
 */
final readonly class HistogramDefinition
{
    /**
     * @param list<string> $labelNames
     * @param list<float> $buckets finite upper bounds; `+Inf` is appended implicitly; empty = {@see Buckets::PROMETHEUS_DEFAULTS}
     */
    public function __construct(
        public string $name,
        public string $help = '',
        public array $labelNames = [],
        public array $buckets = [],
    ) {
        Validation::metricName($name);
        Validation::labelNames($labelNames);
        Validation::histogramBuckets($buckets);
    }
}
