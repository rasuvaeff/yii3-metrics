<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the `route` label for {@see RedMetricsMiddleware}. Implement it to map
 * a request to a low-cardinality route template (e.g. `/users/{id}`) instead of a
 * raw path — see the cardinality note on the middleware.
 *
 * @api
 */
interface RouteResolverInterface
{
    public function resolve(ServerRequestInterface $request): string;
}
