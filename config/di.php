<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Metrics\ConstantRouteResolver;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;

// The core binds the facade and the default route resolver. `MeterProviderInterface`
// is the swappable key — owned by exactly one backend (prometheus) or the
// app (`MeterProviderInterface => NullMeterProvider`). Binding it here would
// collide with a backend (`yiisoft/config` "Duplicate key").
//
// The default resolver is deliberately request-independent: a raw-path `route`
// label is attacker-controlled, so it both explodes the series count and copies
// single-use path tokens into `/metrics`. Applications opt into a real route
// label by rebinding this key (CurrentRouteResolver, BoundedRouteResolver around
// PathRouteResolver, or PathRouteResolver itself) — see the README.
return [
    MetricRegistry::class => MetricRegistry::class,
    RouteResolverInterface::class => ConstantRouteResolver::class,
];
