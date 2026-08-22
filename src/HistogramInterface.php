<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Samples observations into cumulative (`le`) buckets, plus a running sum and
 * count — e.g. request durations.
 *
 * `$value` must be finite: a recording implementation rejects `NAN` and `±INF`
 * with an `Exception\InvalidArgumentException`. `NAN` fails every bucket
 * comparison (`NAN <= INF` included), so it would leave `count` and the `+Inf`
 * bucket out of sync and make `histogram_quantile` meaningless.
 *
 * @api
 */
interface HistogramInterface
{
    public function observe(float $value, LabelSet $labels = new LabelSet()): void;
}
