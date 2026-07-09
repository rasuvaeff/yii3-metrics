<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Samples observations into cumulative (`le`) buckets, plus a running sum and
 * count — e.g. request durations.
 *
 * @api
 */
interface HistogramInterface
{
    public function observe(float $value, LabelSet $labels = new LabelSet()): void;
}
