<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3Metrics\PathRouteResolver;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;

// The core binds the facade and the default route resolver. `MeterProviderInterface`
// is the swappable key — owned by exactly one backend (prometheus) or the
// app (`MeterProviderInterface => NullMeterProvider`). Binding it here would
// collide with a backend (`yiisoft/config` "Duplicate key").
return [
    MetricRegistry::class => MetricRegistry::class,
    RouteResolverInterface::class => PathRouteResolver::class,
];
