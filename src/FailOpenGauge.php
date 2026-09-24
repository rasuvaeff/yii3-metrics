<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/** @internal */
final readonly class FailOpenGauge implements GaugeInterface
{
    public function __construct(
        private string $name,
        private GaugeInterface $inner,
        private FailOpenMeterProvider $provider,
    ) {}

    #[\Override]
    public function set(float $value, LabelSet $labels = new LabelSet()): void
    {
        $this->provider->run($this->name, fn(): mixed => $this->inner->set($value, $labels));
    }

    #[\Override]
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        $this->provider->run($this->name, fn(): mixed => $this->inner->inc($amount, $labels));
    }

    #[\Override]
    public function dec(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        $this->provider->run($this->name, fn(): mixed => $this->inner->dec($amount, $labels));
    }
}
