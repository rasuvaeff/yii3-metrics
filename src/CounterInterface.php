<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * A monotonically increasing counter. Use a {@see GaugeInterface} for values that
 * can decrease — a recording counter rejects a negative increment.
 *
 * `$amount` must be finite: a recording implementation rejects `NAN` and `±INF`
 * with an `Exception\InvalidArgumentException`, because `NAN` is absorbing and
 * would poison the series total for as long as the backend storage lives.
 *
 * @api
 */
interface CounterInterface
{
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void;
}
