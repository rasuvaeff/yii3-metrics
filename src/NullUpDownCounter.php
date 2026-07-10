<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * No-op up-down counter used when metrics are disabled.
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
    public function add(float $delta, LabelSet $labels = new LabelSet()): void {}
}
