<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * A monotonically increasing counter. Use a {@see GaugeInterface} for values that
 * can decrease — a recording counter rejects a negative increment.
 *
 * @api
 */
interface CounterInterface
{
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void;
}
