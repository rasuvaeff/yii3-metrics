<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\Yii3Metrics\MetricKind;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(MetricKind::class)]
final class MetricKindTest
{
    public function hasThreeKinds(): void
    {
        Assert::count(MetricKind::cases(), 3);
    }

    public function backingValues(): void
    {
        Assert::same(MetricKind::Counter->value, 'counter');
        Assert::same(MetricKind::Gauge->value, 'gauge');
        Assert::same(MetricKind::Histogram->value, 'histogram');
    }
}
