<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\InMemoryCounter;
use Rasuvaeff\Yii3Metrics\InMemoryGauge;
use Rasuvaeff\Yii3Metrics\InMemoryHistogram;
use Rasuvaeff\Yii3Metrics\InMemoryMeter;
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\InMemoryUpDownCounter;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricKind;
use Rasuvaeff\Yii3Metrics\MetricSample;
use Rasuvaeff\Yii3Metrics\MetricSnapshot;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(InMemoryMeter::class)]
#[Covers(InMemoryCounter::class)]
#[Covers(InMemoryGauge::class)]
#[Covers(InMemoryUpDownCounter::class)]
#[Covers(InMemoryHistogram::class)]
#[Covers(InMemoryMeterProvider::class)]
#[Covers(MetricSnapshot::class)]
#[Covers(MetricSample::class)]
#[Covers(InvalidArgumentException::class)]
final class InMemoryMeterTest
{
    public function memoizesInstrumentsByName(): void
    {
        $meter = new InMemoryMeter();

        $a = $meter->counter('http_requests_total');
        $b = $meter->counter('http_requests_total');

        Assert::same($a, $b);

        $a->inc();
        $b->inc();

        Assert::same($meter->snapshots()[0]->samples[0]->value, 2.0);
    }

    public function upDownCounterAccumulatesSignedDeltas(): void
    {
        $meter = new InMemoryMeter();

        $a = $meter->upDownCounter('inflight_requests');
        $b = $meter->upDownCounter('inflight_requests');
        Assert::same($a, $b);
        Assert::instanceOf($a, InMemoryUpDownCounter::class);

        $a->add(5.0);
        $b->add(-2.0);

        $snapshot = $meter->snapshots()[0];
        Assert::same($snapshot->kind, MetricKind::UpDownCounter);
        Assert::same($snapshot->samples[0]->value, 3.0);
    }

    public function counterRejectsNegativeIncrement(): void
    {
        $counter = (new InMemoryMeter())->counter('c');

        try {
            $counter->inc(-1.0);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('cannot be decremented');
        }
    }

    public function gaugeSupportsSetIncDec(): void
    {
        $gauge = (new InMemoryMeter())->gauge('g');

        $gauge->set(10.0);
        $gauge->inc(5.0);
        $gauge->dec(3.0);

        Assert::same($this->onlySample($gauge)->value, 12.0);
    }

    public function gaugeIncFromFreshStateStartsAtZero(): void
    {
        $gauge = (new InMemoryMeter())->gauge('g');

        $gauge->inc(5.0);

        Assert::same($this->onlySample($gauge)->value, 5.0);
    }

    public function gaugeIsMemoizedByName(): void
    {
        $meter = new InMemoryMeter();

        $a = $meter->gauge('g');
        $b = $meter->gauge('g');

        Assert::same($a, $b);
    }

    public function histogramIsMemoizedByName(): void
    {
        $meter = new InMemoryMeter();

        $a = $meter->histogram('h');
        $b = $meter->histogram('h');

        Assert::same($a, $b);
    }

    public function factoriesValidateTheMetricName(): void
    {
        $meter = new InMemoryMeter();

        try {
            $meter->counter('bad.name');
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid metric name');
        }

        try {
            $meter->gauge('bad.name');
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid metric name');
        }

        try {
            $meter->upDownCounter('bad.name');
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid metric name');
        }

        try {
            $meter->histogram('bad.name');
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid metric name');
        }
    }

    public function histogramAggregatesCountAndSum(): void
    {
        $histogram = (new InMemoryMeter())->histogram('h', buckets: [0.1, 1.0]);

        $histogram->observe(0.05);
        $histogram->observe(0.5);
        $histogram->observe(2.0);

        $sample = $this->onlySample($histogram);
        Assert::same($sample->value, 3.0); // count
        Assert::same($sample->sum, 2.55);
        Assert::same($sample->buckets['0.1'], 1.0); // 0.05
        Assert::same($sample->buckets['1'], 2.0);   // 0.05, 0.5
        Assert::same($sample->buckets['+Inf'], 3.0);
    }

    public function histogramCountsValuesEqualToTheBucketBound(): void
    {
        $histogram = (new InMemoryMeter())->histogram('h', buckets: [0.1]);

        $histogram->observe(0.1); // 0.1 <= 0.1 is inclusive

        Assert::same($this->onlySample($histogram)->buckets['0.1'], 1.0);
    }

    public function providerReturnsTheSameMeter(): void
    {
        $provider = new InMemoryMeterProvider();

        Assert::same($provider->getMeter(), $provider->getMeter());
    }

    public function snapshotIsStableAcrossCalls(): void
    {
        $meter = new InMemoryMeter();
        $meter->counter('c')->inc(3.0, new LabelSet(['a' => '1']));
        $meter->histogram('h')->observe(0.2);

        $first = $meter->snapshots();
        $second = $meter->snapshots();

        Assert::count($first, 2);

        foreach ($first as $index => $snapshot) {
            Assert::true($snapshot->equals($second[$index]));
        }
    }

    public function providerExposesSnapshotsFromEveryInstrument(): void
    {
        $provider = new InMemoryMeterProvider();
        $meter = $provider->getMeter();

        $meter->counter('c')->inc();
        $meter->gauge('g')->set(1.0);
        $meter->histogram('h')->observe(0.1);

        Assert::count($provider->snapshots(), 3);
    }

    public function freshProviderHasNoSnapshots(): void
    {
        Assert::same((new InMemoryMeterProvider())->snapshots(), []);
    }

    #[Property(runs: 200)]
    public function counterAccumulatesToTheSumOfIncrements(array $amounts): void
    {
        $meter = new InMemoryMeter();
        $counter = $meter->counter('c');

        foreach ($amounts as $amount) {
            $counter->inc($amount);
        }

        Assert::same($meter->snapshots()[0]->samples[0]->value, array_sum($amounts));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function counterAccumulatesToTheSumOfIncrementsGenerators(): array
    {
        return [
            'amounts' => Gen::nonEmptyArrayOf(Gen::floatBetween(0.0, 1000.0)),
        ];
    }

    #[Property(runs: 200)]
    public function histogramBucketsAreCumulative(float $value): void
    {
        $meter = new InMemoryMeter();
        $histogram = $meter->histogram('h', buckets: [0.1, 0.5, 1.0, 5.0]);

        $histogram->observe($value);

        // Every bucket has to be the one a value lands in at least sometimes:
        // a run whose values all fell past 5.0 would only ever check the +Inf
        // bucket and say nothing about the cumulative counts below it.
        // Floors are under half the share the range implies: over
        // [-1.0, 10.0] the first bucket is ~10% of draws, the middle two
        // ~8%, and everything past 5.0 ~45%.
        Classify::cover($value <= 0.1, 'lands in the first bucket', 4.0);
        Classify::cover($value > 0.1 && $value <= 1.0, 'lands mid-range', 3.0);
        Classify::cover($value > 5.0, 'past the last finite bound', 20.0);

        $sample = $meter->snapshots()[0]->samples[0];
        Assert::same($sample->value, 1.0);
        Assert::same($sample->sum, $value);

        foreach ([0.1, 0.5, 1.0, 5.0, INF] as $bound) {
            $key = is_infinite($bound) ? '+Inf' : (string) $bound;
            Assert::same($sample->buckets[$key], $value <= $bound ? 1.0 : 0.0);
        }
    }

    /** @return array<string, ArbitraryInterface> */
    public static function histogramBucketsAreCumulativeGenerators(): array
    {
        return [
            'value' => Gen::floatBetween(-1.0, 10.0),
        ];
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function histogramBucketsAreCumulativeExamples(): iterable
    {
        // Every bound exactly: Prometheus histogram buckets are `le` — less
        // than or equal — and a value sitting on a bound belongs to it.
        yield 'exactly the first bound' => [0.1];
        yield 'exactly the second bound' => [0.5];
        yield 'exactly the third bound' => [1.0];
        yield 'exactly the last finite bound' => [5.0];
        yield 'zero' => [0.0];
        yield 'negative' => [-1.0];
        yield 'past every finite bound' => [10.0];
    }

    #[Property(runs: 200)]
    public function counterNeverGoesBackwards(array $amounts): void
    {
        $meter = new InMemoryMeter();
        $counter = $meter->counter('c');
        $previous = 0.0;

        Classify::cover(\count($amounts) > 3, 'several increments', 40.0);
        Classify::when(\in_array(0.0, $amounts, strict: true), 'a zero increment');

        foreach ($amounts as $amount) {
            $counter->inc($amount);
            $current = $meter->snapshots()[0]->samples[0]->value;

            // A counter is the one instrument a scrape may difference across
            // two reads; a single decrease turns that difference into a
            // negative rate, which downstream reads as a counter reset.
            Assert::true($current >= $previous);
            $previous = $current;
        }
    }

    /** @return array<string, ArbitraryInterface> */
    public static function counterNeverGoesBackwardsGenerators(): array
    {
        return [
            'amounts' => Gen::nonEmptyArrayOf(Gen::floatBetween(0.0, 1000.0), 8),
        ];
    }

    /**
     * @return iterable<string, array{list<float>}>
     */
    public static function counterAccumulatesToTheSumOfIncrementsExamples(): iterable
    {
        yield 'a single zero' => [[0.0]];
        yield 'zeros only' => [[0.0, 0.0, 0.0]];
        yield 'a large value followed by a tiny one' => [[1000.0, 0.000001]];
    }

    private function onlySample(InMemoryGauge|InMemoryHistogram $instrument): MetricSample
    {
        $samples = $instrument->snapshot()->samples;
        Assert::count($samples, 1);

        return $samples[0];
    }
}
