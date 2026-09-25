<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * @internal
 */
final readonly class FailOpenUpDownCounter implements UpDownCounterInterface
{
    public function __construct(
        private string $name,
        private UpDownCounterInterface $inner,
        private FailOpenMeterProvider $provider,
    ) {}

    #[\Override]
    public function add(float $delta, LabelSet $labels = new LabelSet()): void
    {
        $this->provider->run($this->name, fn(): mixed => $this->inner->add($delta, $labels));
    }
}
