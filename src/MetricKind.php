<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * @api
 */
enum MetricKind: string
{
    case Counter = 'counter';
    case Gauge = 'gauge';
    case Histogram = 'histogram';
}
