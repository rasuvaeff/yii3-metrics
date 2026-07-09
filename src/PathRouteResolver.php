<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Default route resolver: the request path. Beware cardinality — a path with ids
 * (`/users/123`) creates a series per id. Provide a router-aware resolver for
 * production.
 *
 * @api
 */
final readonly class PathRouteResolver implements RouteResolverInterface
{
    #[\Override]
    public function resolve(ServerRequestInterface $request): string
    {
        return $request->getUri()->getPath();
    }
}
