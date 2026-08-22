<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * No-op up-down counter used when metrics are disabled.
 *
 * Recording guards still run, so invalid input fails identically whether
 * metrics are enabled or disabled — the contract must not depend on the
 * provider.
 *
 * @api
 */
final class NullUpDownCounter implements UpDownCounterInterface
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    #[\Override]
    public function add(float $delta, LabelSet $labels = new LabelSet()): void
    {
        Validation::finiteAmount($delta);
    }
}
