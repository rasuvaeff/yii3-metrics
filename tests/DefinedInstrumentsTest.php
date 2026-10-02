<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3Metrics\CounterDefinition;
use Rasuvaeff\Yii3Metrics\DefinedCounter;
use Rasuvaeff\Yii3Metrics\DefinedGauge;
use Rasuvaeff\Yii3Metrics\DefinedHistogram;
use Rasuvaeff\Yii3Metrics\DefinedUpDownCounter;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\GaugeDefinition;
use Rasuvaeff\Yii3Metrics\HistogramDefinition;
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\Internal\DefinedLabels;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3Metrics\NullCounter;
use Rasuvaeff\Yii3Metrics\NullMeterProvider;
use Rasuvaeff\Yii3Metrics\UpDownCounterDefinition;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * A metric declared once and recorded through {@see MetricRegistry}: the
 * definition reaches the meter whole, labels arrive as an array or a
 * {@see LabelSet}, and a label set that does not match the declaration fails
 * at the call site, naming the metric.
 */
#[Test]
#[Covers(MetricRegistry::class)]
#[Covers(DefinedCounter::class)]
#[Covers(DefinedGauge::class)]
#[Covers(DefinedUpDownCounter::class)]
#[Covers(DefinedHistogram::class)]
#[Covers(DefinedLabels::class)]
final class DefinedInstrumentsTest
{
    public function everyKindRecordsThroughItsDefinition(): void
    {
        $provider = new InMemoryMeterProvider();
        $registry = new MetricRegistry($provider);
        $labels = ['channel' => 'creator', 'message' => 'refresh'];

        $registry->counter(new CounterDefinition('pushed_total', 'Pushed', ['channel', 'message']))->inc(2.0, $labels);
        $gauge = $registry->gauge(new GaugeDefinition('depth', 'Depth', ['channel', 'message']));
        $gauge->set(5.0, $labels);
        $gauge->inc(2.0, $labels);
        $gauge->dec(1.0, $labels);
        $registry->upDownCounter(new UpDownCounterDefinition('pool', 'Pool', ['channel', 'message']))->add(-3.0, $labels);
        $registry->histogram(new HistogramDefinition('seconds', 'Seconds', ['channel', 'message'], [0.5, 1.0]))
            ->observe(0.7, $labels);

        Assert::same($provider->value('pushed_total', $labels), 2.0);
        Assert::same($provider->value('depth', $labels), 6.0);
        Assert::same($provider->value('pool', $labels), -3.0);
        Assert::same($provider->histogram('seconds', $labels)?->sum, 0.7);
    }

    public function theDefinitionReachesTheMeterWhole(): void
    {
        $provider = new InMemoryMeterProvider(strictNaming: true);
        $registry = new MetricRegistry($provider);

        $registry->counter(new CounterDefinition('jobs_total', 'Jobs', ['queue']))->inc(labels: ['queue' => 'a']);

        // The strict guard saw the definition's help and label names: the same
        // name with different label names is refused.
        try {
            $registry->counter('jobs_total', 'Jobs', ['other']);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('jobs_total');
        }
    }

    public function aLabelSetIsAcceptedToo(): void
    {
        $provider = new InMemoryMeterProvider();

        (new MetricRegistry($provider))->counter(new CounterDefinition('c', labelNames: ['a']))->inc(1.0, new LabelSet(['a' => 'x']));

        Assert::same($provider->value('c', ['a' => 'x']), 1.0);
    }

    public function aDefinitionWithoutLabelsTakesNone(): void
    {
        $provider = new InMemoryMeterProvider();

        (new MetricRegistry($provider))->counter(new CounterDefinition('c'))->inc();

        Assert::same($provider->value('c'), 1.0);
    }

    #[DataProvider('mismatchedLabels')]
    public function labelsThatDoNotMatchTheDeclarationAreRefused(array $labels, string $got): void
    {
        $counter = (new MetricRegistry(new InMemoryMeterProvider()))
            ->counter(new CounterDefinition('queue_total', labelNames: ['message', 'channel']));

        try {
            $counter->inc(labels: $labels);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same($e->getMessage(), \sprintf('Metric "queue_total" takes labels [channel, message], got [%s]', $got));
        }
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function mismatchedLabels(): iterable
    {
        yield 'missing' => [['channel' => 'a'], 'channel'];
        yield 'extra' => [['channel' => 'a', 'message' => 'b', 'route' => 'c'], 'channel, message, route'];
        yield 'renamed' => [['channel' => 'a', 'msg' => 'b'], 'channel, msg'];
        yield 'none' => [[], ''];
    }

    #[DataProvider('competingArguments')]
    public function argumentsBesideADefinitionAreRefused(\Closure $register): void
    {
        $registry = new MetricRegistry(new NullMeterProvider());

        try {
            $register($registry);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same($e->getMessage(), 'Metric "m" is passed as a definition; help, label names and buckets belong in it');
        }
    }

    /**
     * @return iterable<string, array{\Closure(MetricRegistry): mixed}>
     */
    public static function competingArguments(): iterable
    {
        yield 'counter help' => [static fn(MetricRegistry $r): mixed => $r->counter(new CounterDefinition('m'), 'help')];
        yield 'gauge label names' => [static fn(MetricRegistry $r): mixed => $r->gauge(new GaugeDefinition('m'), labelNames: ['a'])];
        yield 'up-down counter help' => [static fn(MetricRegistry $r): mixed => $r->upDownCounter(new UpDownCounterDefinition('m'), 'help')];
        yield 'histogram buckets' => [static fn(MetricRegistry $r): mixed => $r->histogram(new HistogramDefinition('m'), buckets: [1.0])];
    }

    public function theStringFormKeepsReturningTheMeterInstrument(): void
    {
        Assert::instanceOf((new MetricRegistry(new NullMeterProvider()))->counter('c'), NullCounter::class);
    }

    /**
     * An array and a {@see LabelSet} with the same pairs are the same series,
     * whatever the values hold.
     */
    #[Property(runs: 200, auto: true)]
    public function anArrayRecordsTheSameSeriesAsALabelSet(string $channel, string $message): void
    {
        $provider = new InMemoryMeterProvider();
        $counter = (new MetricRegistry($provider))->counter(new CounterDefinition('c', labelNames: ['channel', 'message']));
        $pairs = ['message' => $message, 'channel' => $channel];

        $counter->inc(1.0, $pairs);
        $counter->inc(1.0, new LabelSet($pairs));

        Assert::count($provider->values('c'), 1);
        Assert::same($provider->value('c', $pairs), 2.0);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function anArrayRecordsTheSameSeriesAsALabelSetExamples(): iterable
    {
        yield 'empty values' => ['', ''];
        yield 'separators in values' => ['a,b=c', '1:x'];
    }
}
