<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Single-process histogram for tests and dev. Buckets are cumulative (`le`): an
 * observation increments every bucket whose bound is >= the value, plus the
 * running count and sum.
 *
 * @api
 */
final class InMemoryHistogram implements HistogramInterface
{
    /** @var array<string, array{count: float, sum: float, buckets: array<int, float>}> */
    private array $data = [];

    /** @var array<string, LabelSet> */
    private array $labelSets = [];

    /**
     * @param list<float> $bounds ascending `le` bounds ending in `INF`
     */
    public function __construct(
        private readonly string $name,
        private readonly string $help,
        private readonly array $bounds,
    ) {}

    #[\Override]
    public function observe(float $value, LabelSet $labels = new LabelSet()): void
    {
        $key = $labels->key();

        if (!isset($this->data[$key])) {
            $this->data[$key] = [
                'count' => 0.0,
                'sum' => 0.0,
                'buckets' => array_fill(0, \count($this->bounds), 0.0),
            ];
            $this->labelSets[$key] = $labels;
        }

        $this->data[$key]['count'] += 1.0;
        $this->data[$key]['sum'] += $value;

        foreach ($this->bounds as $index => $bound) {
            if ($value <= $bound) {
                $this->data[$key]['buckets'][$index] += 1.0;
            }
        }
    }

    public function snapshot(): MetricSnapshot
    {
        $samples = [];

        foreach ($this->data as $key => $entry) {
            $buckets = [];

            foreach ($this->bounds as $index => $bound) {
                $buckets[$this->formatBound($bound)] = $entry['buckets'][$index];
            }

            $samples[] = new MetricSample($this->labelSets[$key], $entry['count'], $buckets, $entry['sum']);
        }

        return new MetricSnapshot($this->name, MetricKind::Histogram, $this->help, $samples);
    }

    private function formatBound(float $bound): string
    {
        return is_infinite($bound) ? '+Inf' : (string) $bound;
    }
}
