<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Psr\Log\AbstractLogger;
use Rasuvaeff\Yii3Metrics\CounterInterface;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\FailOpenCounter;
use Rasuvaeff\Yii3Metrics\FailOpenGauge;
use Rasuvaeff\Yii3Metrics\FailOpenHistogram;
use Rasuvaeff\Yii3Metrics\FailOpenMeter;
use Rasuvaeff\Yii3Metrics\FailOpenMeterProvider;
use Rasuvaeff\Yii3Metrics\FailOpenUpDownCounter;
use Rasuvaeff\Yii3Metrics\GaugeInterface;
use Rasuvaeff\Yii3Metrics\HistogramInterface;
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MeterInterface;
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3Metrics\NullGauge;
use Rasuvaeff\Yii3Metrics\NullHistogram;
use Rasuvaeff\Yii3Metrics\NullUpDownCounter;
use Rasuvaeff\Yii3Metrics\UpDownCounterInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(FailOpenMeterProvider::class)]
#[Covers(FailOpenMeter::class)]
#[Covers(FailOpenCounter::class)]
#[Covers(FailOpenGauge::class)]
#[Covers(FailOpenUpDownCounter::class)]
#[Covers(FailOpenHistogram::class)]
final class FailOpenMeterProviderTest
{
    public function dropsBackendFailuresDuringCooldownAndLogsOnce(): void
    {
        $logger = new RecordingLogger();
        $provider = new FailOpenMeterProvider(new FailingProvider(), $logger, cooldownSeconds: 60.0);
        $calls = 0;

        $write = static function () use (&$calls): void {
            ++$calls;

            throw new \RuntimeException('backend unavailable');
        };
        $provider->run('jobs_total', $write);
        $provider->run('jobs_total', $write);

        Assert::same($calls, 1);
        Assert::count($logger->records, 1);
        Assert::same($logger->records[0]['context'], [
            'metric' => 'jobs_total',
            'exception' => 'backend unavailable',
        ]);
    }

    public function everyInstrumentWritesThroughToTheWrappedProvider(): void
    {
        $inner = new InMemoryMeterProvider();
        $meter = (new FailOpenMeterProvider($inner, new RecordingLogger()))->getMeter();

        $meter->counter('jobs_total')->inc(2.0);
        $gauge = $meter->gauge('depth');
        $gauge->set(10.0);
        $gauge->inc(3.0);
        $gauge->dec(1.0);
        $meter->upDownCounter('open')->add(-4.0);
        $meter->histogram('latency_seconds')->observe(0.5);

        Assert::same($inner->value('jobs_total'), 2.0);
        Assert::same($inner->value('depth'), 12.0);
        Assert::same($inner->value('open'), -4.0);
        Assert::same($inner->histogram('latency_seconds')?->sum, 0.5);
    }

    public function retriesAndLogsAgainWhenCooldownIsZero(): void
    {
        $logger = new RecordingLogger();
        $provider = new FailOpenMeterProvider(new FailingProvider(), $logger, cooldownSeconds: 0.0);
        $counter = $provider->getMeter()->counter('jobs_total');

        $counter->inc();
        $counter->inc();

        Assert::count($logger->records, 2);
    }

    public function rejectsNegativeOrNonFiniteCooldown(): void
    {
        foreach ([-1.0, INF, NAN] as $cooldown) {
            try {
                new FailOpenMeterProvider(new FailingProvider(), new RecordingLogger(), $cooldown);
                Assert::fail('expected an InvalidArgumentException');
            } catch (InvalidArgumentException $exception) {
                Assert::string($exception->getMessage())->contains('Cooldown');
            }
        }
    }

    public function preservesValidationFailures(): void
    {
        $provider = new FailOpenMeterProvider(new InvalidProvider(), new RecordingLogger());
        $counter = $provider->getMeter()->counter('jobs_total');

        try {
            $counter->inc();
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            Assert::string($exception->getMessage())->contains('invalid');
        }
    }
}

/** @internal */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{message: string, context: array<string, mixed>}> */
    public array $records = [];

    #[\Override]
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['message' => (string) $message, 'context' => $context];
    }
}

/** @internal */
final class FailingProvider implements MeterProviderInterface
{
    #[\Override]
    public function getMeter(?string $name = null): MeterInterface
    {
        return new class implements MeterInterface {
            #[\Override]
            public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface
            {
                return new class implements CounterInterface {
                    #[\Override]
                    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
                    {
                        throw new \RuntimeException('backend unavailable');
                    }
                };
            }

            #[\Override]
            public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface
            {
                return NullGauge::instance();
            }

            #[\Override]
            public function upDownCounter(string $name, string $help = '', array $labelNames = []): UpDownCounterInterface
            {
                return NullUpDownCounter::instance();
            }

            #[\Override]
            public function histogram(string $name, string $help = '', array $labelNames = [], array $buckets = []): HistogramInterface
            {
                return NullHistogram::instance();
            }
        };
    }
}

/** @internal */
final class InvalidProvider implements MeterProviderInterface
{
    #[\Override]
    public function getMeter(?string $name = null): MeterInterface
    {
        return new class implements MeterInterface {
            #[\Override]
            public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface
            {
                return new class implements CounterInterface {
                    #[\Override]
                    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
                    {
                        throw new InvalidArgumentException('invalid amount');
                    }
                };
            }

            #[\Override]
            public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface
            {
                return NullGauge::instance();
            }

            #[\Override]
            public function upDownCounter(string $name, string $help = '', array $labelNames = []): UpDownCounterInterface
            {
                return NullUpDownCounter::instance();
            }

            #[\Override]
            public function histogram(string $name, string $help = '', array $labelNames = [], array $buckets = []): HistogramInterface
            {
                return NullHistogram::instance();
            }
        };
    }
}
