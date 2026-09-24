<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Psr\Log\AbstractLogger;
use Rasuvaeff\Yii3Metrics\CounterInterface;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\FailOpenMeterProvider;
use Rasuvaeff\Yii3Metrics\GaugeInterface;
use Rasuvaeff\Yii3Metrics\HistogramInterface;
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
