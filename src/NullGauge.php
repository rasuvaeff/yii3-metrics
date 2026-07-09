<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * No-op gauge used when metrics are disabled.
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
    public function set(float $value, LabelSet $labels = new LabelSet()): void {}

    #[\Override]
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void {}

    #[\Override]
    public function dec(float $amount = 1.0, LabelSet $labels = new LabelSet()): void {}
}
