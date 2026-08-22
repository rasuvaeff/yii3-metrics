<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Opt-in route resolver: the raw request path.
 *
 * NOT the default — see {@see ConstantRouteResolver} for why. The path is
 * attacker-controlled, so this resolver is only safe where the path space is
 * known to be small and free of secrets:
 *
 * - cardinality: a path with ids (`/users/123`) creates a series per id, and
 *   scanner traffic creates one per scanned URL. In a shared promphp storage
 *   those series live until a flush. Wrap this in a {@see BoundedRouteResolver}
 *   to cap them.
 * - disclosure: `/reset-password/<token>` reaches `/metrics` verbatim, so anyone
 *   who can scrape the endpoint reads the token. Only
 *   {@see CurrentRouteResolver} avoids this class of leak entirely.
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
