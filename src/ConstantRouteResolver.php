<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Safe default `route` label: one constant value, never derived from the request.
 *
 * This is what {@see RedMetricsMiddleware} and the package DI bind when the
 * application has not chosen a resolver. A resolver that reads the URI cannot be
 * a safe default: the raw path is attacker-controlled, so it both explodes the
 * series count (one series per scanned URL) and copies single-use path tokens
 * (`/reset-password/<token>`) into `/metrics`, where everyone who can scrape the
 * endpoint reads them.
 *
 * With this resolver the RED metrics still carry rate, errors and duration broken
 * down by `method` and `status`; only the per-route breakdown is opt-in. Choose
 * one deliberately:
 *
 * - {@see CurrentRouteResolver} — the matched `yiisoft/router` pattern
 *   (`/users/{id}`), low-cardinality by construction. Preferred.
 * - {@see BoundedRouteResolver} around {@see PathRouteResolver} — raw paths with a
 *   hard cap on how many distinct values may ever be emitted.
 * - {@see PathRouteResolver} — raw paths, only where the path space is known to be
 *   small and free of secrets.
 *
 * @api
 */
final readonly class ConstantRouteResolver implements RouteResolverInterface
{
    public const string DEFAULT_VALUE = '(unset)';

    public function __construct(
        private string $value = self::DEFAULT_VALUE,
    ) {}

    #[\Override]
    public function resolve(ServerRequestInterface $request): string
    {
        return $this->value;
    }
}
