<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/** @internal */
final readonly class FailOpenCounter implements CounterInterface
{
    public function __construct(
        private string $name,
        private CounterInterface $inner,
        private FailOpenMeterProvider $provider,
    ) {}

    #[\Override]
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        $this->provider->run($this->name, fn(): mixed => $this->inner->inc($amount, $labels));
    }
}
