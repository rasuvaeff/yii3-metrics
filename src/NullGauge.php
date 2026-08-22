<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Internal\Validation;

/**
 * No-op gauge used when metrics are disabled.
 *
 * Recording guards still run, so invalid input fails identically whether
 * metrics are enabled or disabled — the contract must not depend on the
 * provider.
 *
 * @api
 */
final class NullGauge implements GaugeInterface
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    #[\Override]
    public function set(float $value, LabelSet $labels = new LabelSet()): void
    {
        Validation::notNan($value);
    }

    #[\Override]
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        Validation::finiteAmount($amount);
    }

    #[\Override]
    public function dec(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        Validation::finiteAmount($amount);
    }
}
