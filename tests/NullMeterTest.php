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
use Testo\Data\DataProvider;
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

        Assert::true(actual: true);
    }

    /**
     * Whether metrics are enabled or disabled must not change what input is
     * accepted: a disabled stack that silently swallowed a `NAN` the enabled
     * one rejects would hide the failure until the backend is switched on.
     */
    #[DataProvider('nonFiniteRecordingCallsProvider')]
    public function nullRecordingStillRejectsNonFiniteAmounts(object $instrument, string $method, float $amount): void
    {
        try {
            $instrument->{$method}($amount);
            Assert::fail('expected an InvalidArgumentException for a non-finite amount');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('finite');
        }
    }

    public static function nonFiniteRecordingCallsProvider(): iterable
    {
        yield 'counter inc NAN' => [NullCounter::instance(), 'inc', NAN];

        yield 'counter inc INF' => [NullCounter::instance(), 'inc', INF];

        yield 'gauge inc NAN' => [NullGauge::instance(), 'inc', NAN];

        yield 'gauge dec INF' => [NullGauge::instance(), 'dec', INF];

        yield 'up-down add NAN' => [NullUpDownCounter::instance(), 'add', NAN];

        yield 'histogram observe INF' => [NullHistogram::instance(), 'observe', INF];
    }

    public function nullGaugeSetAllowsInfinityAndRejectsNan(): void
    {
        NullGauge::instance()->set(INF);
        NullGauge::instance()->set(-INF);

        try {
            NullGauge::instance()->set(NAN);
            Assert::fail('expected an InvalidArgumentException for NaN');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('must not be NaN');
        }
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

    public function defaultProviderSharesTheLenientSingleton(): void
    {
        $meter = (new NullMeterProvider())->getMeter();

        Assert::same($meter, NullMeter::instance());

        $meter->gauge('tags_total');
        $meter->counter('a_total', labelNames: ['x']);
        $meter->counter('a_total', labelNames: ['y']);
    }

    public function strictProviderReturnsOneStrictMeter(): void
    {
        $provider = new NullMeterProvider(strictNaming: true);
        $meter = $provider->getMeter();

        Assert::same($provider->getMeter('other'), $meter);
        Assert::false($meter === NullMeter::instance());

        $meter->counter('a_total', labelNames: ['x']);

        try {
            $meter->counter('a_total', labelNames: ['y']);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('is already registered');
        }
    }

    #[DataProvider('strictViolationProvider')]
    public function strictNullMeterRejectsWhatStrictRecordingMetersReject(\Closure $register, string $message): void
    {
        try {
            $register(new NullMeter(strictNaming: true));
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains($message);
        }
    }

    public static function strictViolationProvider(): iterable
    {
        yield 'counter without _total' => [static fn(NullMeter $m) => $m->counter('requests'), 'must end with "_total"'];

        yield 'gauge with _total' => [static fn(NullMeter $m) => $m->gauge('tags_total'), 'kind gauge must not end'];

        yield 'up-down counter with _total' => [
            static fn(NullMeter $m) => $m->upDownCounter('inflight_total'),
            'kind up_down_counter must not end',
        ];

        yield 'histogram with _total' => [static fn(NullMeter $m) => $m->histogram('latency_total'), 'kind histogram must not end'];

        yield 'counter re-registered with other help' => [static function (NullMeter $m): void {
            $m->counter('a_total', 'A');
            $m->counter('a_total', 'B');
        }, 'is already registered'];

        yield 'gauge re-registered with other labels' => [static function (NullMeter $m): void {
            $m->gauge('g', labelNames: ['x']);
            $m->gauge('g', labelNames: ['y']);
        }, 'is already registered'];

        yield 'up-down counter re-registered with other labels' => [static function (NullMeter $m): void {
            $m->upDownCounter('u', labelNames: ['x']);
            $m->upDownCounter('u', labelNames: ['y']);
        }, 'is already registered'];

        yield 'histogram re-registered with other buckets' => [static function (NullMeter $m): void {
            $m->histogram('h', labelNames: ['x'], buckets: [1.0]);
            $m->histogram('h', labelNames: ['x'], buckets: [2.0]);
        }, 'is already registered'];

        yield 'histogram re-registered with other help' => [static function (NullMeter $m): void {
            $m->histogram('h', 'A');
            $m->histogram('h', 'B');
        }, 'is already registered'];
    }
}
