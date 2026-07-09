<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * No-op histogram used when metrics are disabled.
 *
 * @api
 */
final class NullHistogram implements HistogramInterface
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    #[\Override]
    public function observe(float $value, LabelSet $labels = new LabelSet()): void {}
}
