<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Creates instruments. Implementations MUST return the SAME instrument instance
 * for a repeated `(kind, name)` — metrics accumulate across the process, so a new
 * instance per call would lose state (and clash in the Prometheus backend).
 *
 * @api
 */
interface MeterInterface
{
    /**
     * @param list<string> $labelNames declared label names for this metric
     */
    public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface;

    /**
     * @param list<string> $labelNames
     */
    public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface;

    /**
     * @param list<string> $labelNames
     * @param list<float> $buckets finite upper bounds; `+Inf` is appended implicitly
     */
    public function histogram(
        string $name,
        string $help = '',
        array $labelNames = [],
        array $buckets = [],
    ): HistogramInterface;
}
