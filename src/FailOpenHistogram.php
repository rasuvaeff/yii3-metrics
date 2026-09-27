<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * @internal
 */
final readonly class FailOpenHistogram implements HistogramInterface
{
    public function __construct(
        private string $name,
        private HistogramInterface $inner,
        private FailOpenMeterProvider $provider,
    ) {}

    #[\Override]
    public function observe(float $value, LabelSet $labels = new LabelSet()): void
    {
        $this->provider->run($this->name, fn(): mixed => $this->inner->observe($value, $labels));
    }
}
