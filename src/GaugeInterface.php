<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * A value that can go up and down (e.g. in-flight requests, queue depth).
 *
 * `set()` is an absolute write: a recording implementation accepts `±INF` (the
 * exposition has a token for it) but rejects `NAN`, which has none — promphp
 * coerces it to an invalid token and raises a PHP warning while rendering.
 * `inc()`/`dec()` accumulate, so they reject any non-finite amount. Both throw
 * `Exception\InvalidArgumentException`.
 *
 * @api
 */
interface GaugeInterface
{
    public function set(float $value, LabelSet $labels = new LabelSet()): void;

    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void;

    public function dec(float $amount = 1.0, LabelSet $labels = new LabelSet()): void;
}
