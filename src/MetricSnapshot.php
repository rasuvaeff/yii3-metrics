<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Point-in-time state of one metric, produced by {@see InMemoryMeterProvider} for
 * dev/test inspection. It carries NO timestamp, so two snapshots of unchanged
 * state are equal.
 *
 * @api
 */
final readonly class MetricSnapshot
{
    /**
     * @param list<MetricSample> $samples
     */
    public function __construct(
        public string $name,
        public MetricKind $kind,
        public string $help,
        public array $samples,
    ) {}

    public function equals(self $other): bool
    {
        if ($this->name !== $other->name || $this->kind !== $other->kind || $this->help !== $other->help) {
            return false;
        }

        $mine = $this->sortedSamples();
        $theirs = $other->sortedSamples();

        if (\count($mine) !== \count($theirs)) {
            return false;
        }

        foreach ($mine as $index => $sample) {
            if (!$sample->equals($theirs[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<MetricSample>
     */
    private function sortedSamples(): array
    {
        $samples = $this->samples;

        usort($samples, static fn(MetricSample $a, MetricSample $b): int => $a->labels->key() <=> $b->labels->key());

        return $samples;
    }
}
