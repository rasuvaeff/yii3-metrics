<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;

// The RED middleware is web-only. Add it to the application's middleware stack.
return [
    RedMetricsMiddleware::class => RedMetricsMiddleware::class,
];
