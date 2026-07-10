<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-metrics' => [
        'red' => [
            // Histogram bounds in seconds (empty = Prometheus defaults) and
            // exact request paths the RED middleware skips (scrape/probe
            // endpoints whose self-traffic is noise).
            'duration_buckets' => [],
            'excluded_paths' => [],
        ],
    ],
];
