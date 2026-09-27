<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\RegistrationGuard;
use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * Single-process meter for tests and dev. Instruments are memoized by name, so a
 * repeated `counter('x')` returns the same accumulating instrument. With
 * `strictNaming` it also enforces suffix conventions and rejects a conflicting
 * re-registration (see {@see RegistrationGuard}).
 *
 * @api
 */
final class InMemoryMeter implements MeterInterface
{
    /** @var array<string, InMemoryCounter> */
    private array $counters = [];

    /** @var array<string, InMemoryGauge> */
    private array $gauges = [];

    /** @var array<string, InMemoryUpDownCounter> */
    private array $upDownCounters = [];

    /** @var array<string, InMemoryHistogram> */
    private array $histograms = [];

    private readonly ?RegistrationGuard $guard;

    public function __construct(bool $strictNaming = false)
    {
        $this->guard = $strictNaming ? new RegistrationGuard() : null;
    }

    #[\Override]
    public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface
    {
        Validation::metricName($name);
        $this->guard?->register(kind: MetricKind::Counter, name: $name, help: $help, labelNames: $labelNames);

        return $this->counters[$name] ??= new InMemoryCounter($name, $help);
    }

    #[\Override]
    public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface
    {
        Validation::metricName($name);
        $this->guard?->register(kind: MetricKind::Gauge, name: $name, help: $help, labelNames: $labelNames);

        return $this->gauges[$name] ??= new InMemoryGauge($name, $help);
    }

    #[\Override]
    public function upDownCounter(string $name, string $help = '', array $labelNames = []): UpDownCounterInterface
    {
        Validation::metricName($name);
        $this->guard?->register(kind: MetricKind::UpDownCounter, name: $name, help: $help, labelNames: $labelNames);

        return $this->upDownCounters[$name] ??= new InMemoryUpDownCounter($name, $help);
    }

    #[\Override]
    public function histogram(
        string $name,
        string $help = '',
        array $labelNames = [],
        array $buckets = [],
    ): HistogramInterface {
        Validation::metricName($name);
        $this->guard?->register(
            kind: MetricKind::Histogram,
            name: $name,
            help: $help,
            labelNames: $labelNames,
            buckets: $buckets,
        );

        return $this->histograms[$name] ??= new InMemoryHistogram($name, $help, Validation::histogramBuckets($buckets));
    }

    /**
     * @return list<MetricSnapshot>
     */
    public function snapshots(): array
    {
        $snapshots = [];

        foreach ($this->counters as $counter) {
            $snapshots[] = $counter->snapshot();
        }

        foreach ($this->gauges as $gauge) {
            $snapshots[] = $gauge->snapshot();
        }

        foreach ($this->upDownCounters as $upDownCounter) {
            $snapshots[] = $upDownCounter->snapshot();
        }

        foreach ($this->histograms as $histogram) {
            $snapshots[] = $histogram->snapshot();
        }

        return $snapshots;
    }
}
