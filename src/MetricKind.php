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
    case UpDownCounter = 'up_down_counter';
    case Histogram = 'histogram';
}
