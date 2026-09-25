<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * @internal
 */
final readonly class FailOpenMeter implements MeterInterface
{
    public function __construct(
        private MeterInterface $inner,
        private FailOpenMeterProvider $provider,
    ) {}

    #[\Override]
    public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface
    {
        return new FailOpenCounter($name, $this->inner->counter($name, $help, $labelNames), $this->provider);
    }

    #[\Override]
    public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface
    {
        return new FailOpenGauge($name, $this->inner->gauge($name, $help, $labelNames), $this->provider);
    }

    #[\Override]
    public function upDownCounter(string $name, string $help = '', array $labelNames = []): UpDownCounterInterface
    {
        return new FailOpenUpDownCounter($name, $this->inner->upDownCounter($name, $help, $labelNames), $this->provider);
    }

    #[\Override]
    public function histogram(
        string $name,
        string $help = '',
        array $labelNames = [],
        array $buckets = [],
    ): HistogramInterface {
        return new FailOpenHistogram($name, $this->inner->histogram($name, $help, $labelNames, $buckets), $this->provider);
    }
}
