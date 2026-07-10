<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * A counter that can go up and down by deltas (in-flight requests, pool size,
 * queue depth). Unlike {@see GaugeInterface} it has no absolute `set()` — every
 * process contributes increments, so the backend aggregates correctly across
 * short-lived workers (php-fpm), where a gauge's `inc`/`dec` tally would reset
 * per request.
 *
 * Rule of thumb: measured absolute value → gauge `set()`; counted ups and downs
 * → up-down counter `add()`.
 *
 * @api
 */
interface UpDownCounterInterface
{
    public function add(float $delta, LabelSet $labels = new LabelSet()): void;
}
