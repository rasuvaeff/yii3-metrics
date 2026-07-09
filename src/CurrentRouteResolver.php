<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\CurrentRoute;

/**
 * Router-aware `route` label: resolves to the matched `yiisoft/router` route
 * pattern (`/users/{id}`), which is low-cardinality by construction.
 *
 * Works with {@see RedMetricsMiddleware} placed before the router middleware —
 * the label is resolved after the handler ran, when {@see CurrentRoute} is
 * populated. An unmatched request (404, no route) resolves to `(unmatched)` by
 * default so scanner traffic cannot explode the series count; pass a fallback
 * resolver to keep the raw path instead.
 *
 * @api
 */
final readonly class CurrentRouteResolver implements RouteResolverInterface
{
    private const string UNMATCHED = '(unmatched)';

    public function __construct(
        private CurrentRoute $currentRoute,
        private ?RouteResolverInterface $fallback = null,
    ) {}

    #[\Override]
    public function resolve(ServerRequestInterface $request): string
    {
        $pattern = $this->currentRoute->getPattern();

        if ($pattern !== null) {
            return $pattern;
        }

        if ($this->fallback instanceof RouteResolverInterface) {
            return $this->fallback->resolve($request);
        }

        return self::UNMATCHED;
    }
}
