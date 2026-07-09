<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * A value that can go up and down (e.g. in-flight requests, queue depth).
 *
 * @api
 */
interface GaugeInterface
{
    public function set(float $value, LabelSet $labels = new LabelSet()): void;

    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void;

    public function dec(float $amount = 1.0, LabelSet $labels = new LabelSet()): void;
}
