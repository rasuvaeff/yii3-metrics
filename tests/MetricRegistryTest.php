<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3Metrics\NullCounter;
use Rasuvaeff\Yii3Metrics\NullMeterProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(MetricRegistry::class)]
final class MetricRegistryTest
{
    public function delegatesEachInstrumentToTheProviderMeter(): void
    {
        $provider = new InMemoryMeterProvider();
        $registry = new MetricRegistry($provider);

        $registry->counter('http_requests_total')->inc(2.0);
        $registry->gauge('inflight_requests')->set(3.0);
        $registry->upDownCounter('db_pool_size')->add(1.0);
        $registry->histogram('request_seconds')->observe(0.1);

        Assert::count($provider->snapshots(), 4);
    }

    public function memoizesInstrumentsAcrossCalls(): void
    {
        $provider = new InMemoryMeterProvider();
        $registry = new MetricRegistry($provider);

        $registry->counter('c')->inc();
        $registry->counter('c')->inc();

        Assert::same($provider->snapshots()[0]->samples[0]->value, 2.0);
    }

    public function isNoOpWithTheNullProvider(): void
    {
        $registry = new MetricRegistry(new NullMeterProvider());

        $registry->counter('c')->inc();

        Assert::instanceOf($registry->counter('c'), NullCounter::class);
    }
}
