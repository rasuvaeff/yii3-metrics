<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * One labelled data point of a metric. For a counter/gauge only {@see $value} is
 * set; for a histogram {@see $value} is the observation count, {@see $buckets}
 * maps each `le` bound to its cumulative count, and {@see $sum} is the running
 * total.
 *
 * @api
 */
final readonly class MetricSample
{
    /**
     * @param array<string, float> $buckets `le` bound (as string) => cumulative count
     */
    public function __construct(
        public LabelSet $labels,
        public float $value,
        public array $buckets = [],
        public ?float $sum = null,
    ) {}

    public function equals(self $other): bool
    {
        return $this->labels->equals($other->labels)
            && $this->value === $other->value
            && $this->buckets === $other->buckets
            && $this->sum === $other->sum;
    }
}
