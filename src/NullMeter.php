<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\RegistrationGuard;
use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * No-op meter. It still validates the metric name and histogram buckets at
 * registration — a bad name is a portability bug that must fail even with metrics
 * disabled — but the returned instruments record nothing. A meter built with
 * `strictNaming` also applies the {@see RegistrationGuard} rules, so a naming or
 * re-registration mistake fails in tests that run with metrics disabled; the
 * shared {@see instance()} stays lenient.
 *
 * @api
 */
final class NullMeter implements MeterInterface
{
    private static ?self $instance = null;

    private readonly ?RegistrationGuard $guard;

    public function __construct(bool $strictNaming = false)
    {
        $this->guard = $strictNaming ? new RegistrationGuard() : null;
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    #[\Override]
    public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface
    {
        Validation::metricName($name);
        $this->guard?->register(kind: MetricKind::Counter, name: $name, help: $help, labelNames: $labelNames);

        return NullCounter::instance();
    }

    #[\Override]
    public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface
    {
        Validation::metricName($name);
        $this->guard?->register(kind: MetricKind::Gauge, name: $name, help: $help, labelNames: $labelNames);

        return NullGauge::instance();
    }

    #[\Override]
    public function upDownCounter(string $name, string $help = '', array $labelNames = []): UpDownCounterInterface
    {
        Validation::metricName($name);
        $this->guard?->register(kind: MetricKind::UpDownCounter, name: $name, help: $help, labelNames: $labelNames);

        return NullUpDownCounter::instance();
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
        $this->guard?->register(
            kind: MetricKind::Histogram,
            name: $name,
            help: $help,
            labelNames: $labelNames,
            buckets: $buckets,
        );

        return NullHistogram::instance();
    }
}
