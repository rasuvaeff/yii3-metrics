<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;

require __DIR__ . '/../vendor/autoload.php';

$provider = new InMemoryMeterProvider();
$registry = new MetricRegistry($provider);

$orders = $registry->counter('orders_total', 'Orders placed', ['channel']);
$orders->inc(1.0, new LabelSet(['channel' => 'web']));
$orders->inc(2.0, new LabelSet(['channel' => 'web'])); // same series accumulates

$latency = $registry->histogram('checkout_seconds', 'Checkout duration', [], [0.1, 0.5, 1.0]);
$latency->observe(0.3);
$latency->observe(0.7);

foreach ($provider->snapshots() as $snapshot) {
    echo "{$snapshot->name} ({$snapshot->kind->value})\n";

    foreach ($snapshot->samples as $sample) {
        printf("  labels=%s value=%s\n", $sample->labels->key(), (string) $sample->value);
    }
}
