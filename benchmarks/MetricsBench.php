<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Benchmarks;

use Rasuvaeff\Yii3Metrics\InMemoryMeter;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Testo\Bench;

final class MetricsBench
{
    #[Bench(
        callables: [
            'observe' => [self::class, 'observe'],
        ],
        calls: 2_000,
        iterations: 5,
    )]
    public static function increment(): int
    {
        $counter = (new InMemoryMeter())->counter('http_requests_total');
        $labels = new LabelSet(['method' => 'GET', 'status' => '200']);

        $i = 0;

        for (; $i < 100; ++$i) {
            $counter->inc(1.0, $labels);
        }

        return $i;
    }

    public static function observe(): int
    {
        $histogram = (new InMemoryMeter())->histogram('request_seconds');
        $labels = new LabelSet(['route' => '/x']);

        $i = 0;

        for (; $i < 100; ++$i) {
            $histogram->observe(0.05, $labels);
        }

        return $i;
    }
}
