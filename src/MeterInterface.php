<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Creates instruments. A repeated `(kind, name)` MUST yield an instrument
 * recording into the SAME underlying accumulating state — metrics accumulate
 * across the process, so per-call state would be lost. Instance identity is not
 * guaranteed: a backend may return a fresh stateless wrapper when its SDK
 * already aggregates by name; an instrument that itself holds state MUST be
 * memoized.
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
     */
    public function upDownCounter(string $name, string $help = '', array $labelNames = []): UpDownCounterInterface;

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
