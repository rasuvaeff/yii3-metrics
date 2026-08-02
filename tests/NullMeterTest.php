<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\NullCounter;
use Rasuvaeff\Yii3Metrics\NullGauge;
use Rasuvaeff\Yii3Metrics\NullHistogram;
use Rasuvaeff\Yii3Metrics\NullMeter;
use Rasuvaeff\Yii3Metrics\NullMeterProvider;
use Rasuvaeff\Yii3Metrics\NullUpDownCounter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(NullMeter::class)]
#[Covers(NullCounter::class)]
#[Covers(NullGauge::class)]
#[Covers(NullUpDownCounter::class)]
#[Covers(NullHistogram::class)]
#[Covers(NullMeterProvider::class)]
#[Covers(InvalidArgumentException::class)]
final class NullMeterTest
{
    public function instrumentsAreInertSingletons(): void
    {
        $meter = (new NullMeterProvider())->getMeter();

        $counter = $meter->counter('c');
        $gauge = $meter->gauge('g');
        $upDown = $meter->upDownCounter('u');
        $histogram = $meter->histogram('h');

        Assert::instanceOf($counter, NullCounter::class);
        Assert::instanceOf($gauge, NullGauge::class);
        Assert::instanceOf($upDown, NullUpDownCounter::class);
        Assert::instanceOf($histogram, NullHistogram::class);

        // No-ops — nothing to observe, must not throw.
        $counter->inc(5.0, new LabelSet(['a' => '1']));
        $gauge->set(3.0);
        $gauge->inc();
        $gauge->dec();
        $upDown->add(-1.0);
        $histogram->observe(0.2);

        Assert::same(NullCounter::instance(), $counter);
    }

    public function nullInstrumentsAreStableSingletons(): void
    {
        Assert::same(NullCounter::instance(), NullCounter::instance());
        Assert::same(NullGauge::instance(), NullGauge::instance());
        Assert::same(NullHistogram::instance(), NullHistogram::instance());
        Assert::same(NullUpDownCounter::instance(), NullUpDownCounter::instance());
        Assert::same(NullMeter::instance(), NullMeter::instance());
    }

    public function nullCounterAllowsNegativeIncrement(): void
    {
        // Null is a no-op on recording — the negative-inc guard is a recording-impl
        // concern, so this must NOT throw.
        NullCounter::instance()->inc(-1.0);

        Assert::true(true);
    }

    public function stillValidatesMetricNameAndBuckets(): void
    {
        $meter = NullMeter::instance();

        try {
            $meter->counter('bad.name');
            Assert::fail('expected an InvalidArgumentException for the metric name');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid metric name');
        }

        try {
            $meter->upDownCounter('bad.name');
            Assert::fail('expected an InvalidArgumentException for the metric name');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid metric name');
        }

        try {
            $meter->gauge('bad.name');
            Assert::fail('expected an InvalidArgumentException for the metric name');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid metric name');
        }

        try {
            $meter->histogram('bad.name');
            Assert::fail('expected an InvalidArgumentException for the metric name');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid metric name');
        }

        try {
            $meter->histogram('h', buckets: [2.0, 1.0]);
            Assert::fail('expected an InvalidArgumentException for buckets');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('strictly increasing');
        }
    }
}
