<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * No-op meter. It still validates the metric name and histogram buckets at
 * registration — a bad name is a portability bug that must fail even with metrics
 * disabled — but the returned instruments record nothing.
 *
 * @api
 */
final class NullMeter implements MeterInterface
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    #[\Override]
    public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface
    {
        Validation::metricName($name);

        return NullCounter::instance();
    }

    #[\Override]
    public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface
    {
        Validation::metricName($name);

        return NullGauge::instance();
    }

    #[\Override]
    public function histogram(
        string $name,
        string $help = '',
        array $labelNames = [],
        array $buckets = [],
    ): HistogramInterface {
        Validation::metricName($name);
        Validation::histogramBuckets($buckets);

        return NullHistogram::instance();
    }
}
