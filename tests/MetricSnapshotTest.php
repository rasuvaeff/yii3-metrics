<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricKind;
use Rasuvaeff\Yii3Metrics\MetricSample;
use Rasuvaeff\Yii3Metrics\MetricSnapshot;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(MetricSnapshot::class)]
#[Covers(MetricSample::class)]
final class MetricSnapshotTest
{
    public function sampleEqualityComparesEveryField(): void
    {
        $base = new MetricSample(new LabelSet(['x' => '1']), 2.0, ['+Inf' => 2.0], 5.0);

        Assert::true($base->equals(new MetricSample(new LabelSet(['x' => '1']), 2.0, ['+Inf' => 2.0], 5.0)));
        Assert::false($base->equals(new MetricSample(new LabelSet(['x' => '2']), 2.0, ['+Inf' => 2.0], 5.0)));
        Assert::false($base->equals(new MetricSample(new LabelSet(['x' => '1']), 3.0, ['+Inf' => 2.0], 5.0)));
        Assert::false($base->equals(new MetricSample(new LabelSet(['x' => '1']), 2.0, ['+Inf' => 3.0], 5.0)));
        Assert::false($base->equals(new MetricSample(new LabelSet(['x' => '1']), 2.0, ['+Inf' => 2.0], 6.0)));
    }

    public function snapshotEqualityComparesHeaderAndSamples(): void
    {
        $sample = new MetricSample(new LabelSet(['a' => '1']), 1.0);
        $base = new MetricSnapshot('m', MetricKind::Counter, 'help', [$sample]);

        Assert::true($base->equals(new MetricSnapshot('m', MetricKind::Counter, 'help', [$sample])));
        Assert::false($base->equals(new MetricSnapshot('other', MetricKind::Counter, 'help', [$sample])));
        Assert::false($base->equals(new MetricSnapshot('m', MetricKind::Gauge, 'help', [$sample])));
        Assert::false($base->equals(new MetricSnapshot('m', MetricKind::Counter, 'other', [$sample])));
        Assert::false($base->equals(new MetricSnapshot('m', MetricKind::Counter, 'help', [])));
        Assert::false($base->equals(new MetricSnapshot(
            'm',
            MetricKind::Counter,
            'help',
            [new MetricSample(new LabelSet(['a' => '2']), 1.0)],
        )));
    }

    public function snapshotEqualityIsSampleOrderIndependent(): void
    {
        $first = new MetricSample(new LabelSet(['a' => '1']), 1.0);
        $second = new MetricSample(new LabelSet(['b' => '2']), 2.0);

        $a = new MetricSnapshot('m', MetricKind::Counter, 'h', [$first, $second]);
        $b = new MetricSnapshot('m', MetricKind::Counter, 'h', [$second, $first]);

        Assert::true($a->equals($b));
    }

    public function snapshotEqualityDetectsMismatchBeyondTheFirstSortedSample(): void
    {
        $shared = new MetricSample(new LabelSet(['a' => '1']), 1.0);

        $a = new MetricSnapshot('m', MetricKind::Counter, 'h', [
            $shared,
            new MetricSample(new LabelSet(['b' => '2']), 2.0),
        ]);
        $b = new MetricSnapshot('m', MetricKind::Counter, 'h', [
            $shared,
            new MetricSample(new LabelSet(['b' => '2']), 99.0),
        ]);

        Assert::false($a->equals($b));
    }
}
