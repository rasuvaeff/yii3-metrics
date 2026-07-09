<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * DI-bound metrics entry point. Resolves the active {@see MeterInterface} from the
 * injected {@see MeterProviderInterface} once and delegates to it, so instruments
 * memoized by name stay consistent across calls.
 *
 * @api
 */
final readonly class MetricRegistry
{
    private MeterInterface $meter;

    public function __construct(MeterProviderInterface $provider)
    {
        $this->meter = $provider->getMeter();
    }

    /**
     * @param list<string> $labelNames
     */
    public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface
    {
        return $this->meter->counter($name, $help, $labelNames);
    }

    /**
     * @param list<string> $labelNames
     */
    public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface
    {
        return $this->meter->gauge($name, $help, $labelNames);
    }

    /**
     * @param list<string> $labelNames
     * @param list<float> $buckets
     */
    public function histogram(
        string $name,
        string $help = '',
        array $labelNames = [],
        array $buckets = [],
    ): HistogramInterface {
        return $this->meter->histogram($name, $help, $labelNames, $buckets);
    }
}
