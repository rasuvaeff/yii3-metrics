<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;

/** @var array $params */

// The RED middleware is web-only. Add it to the application's middleware stack.
// Histogram bounds and excluded paths come from the package params.
return [
    RedMetricsMiddleware::class => [
        'class' => RedMetricsMiddleware::class,
        '__construct()' => [
            'durationBuckets' => (array) ($params['rasuvaeff/yii3-metrics']['red']['duration_buckets'] ?? []),
            'excludedPaths' => (array) ($params['rasuvaeff/yii3-metrics']['red']['excluded_paths'] ?? []),
        ],
    ],
];
