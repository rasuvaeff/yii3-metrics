<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Psr\Http\Message\ServerRequestInterface;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;

/**
 * Caps how many distinct `route` values a resolver may ever emit: the first
 * `$limit` values pass through, everything after them collapses into a single
 * overflow value (`(other)` by default).
 *
 * Wrap {@see PathRouteResolver} with this when you want raw paths but must not
 * let scanner traffic (`/wp-admin/...`, `/.env`, random URLs) grow the series
 * count without bound — in a shared promphp storage those series survive until a
 * flush, so an unbounded label is a memory and scrape-time leak.
 *
 * It does NOT make raw paths private: the first `$limit` distinct paths are still
 * recorded verbatim, so a path carrying a secret can still reach `/metrics`. Use
 * {@see CurrentRouteResolver} when the path may contain tokens.
 *
 * The seen-set is per process. On php-fpm every worker learns its own set, so the
 * global bound is `$limit × workers`; it still converges instead of growing with
 * traffic.
 *
 * Stateful by design (it remembers the values already emitted), hence not
 * `readonly` — bind it as a shared instance, one per process.
 *
 * @api
 */
final class BoundedRouteResolver implements RouteResolverInterface
{
    public const string DEFAULT_OVERFLOW = '(other)';

    /** @var array<array-key, true> */
    private array $seen = [];

    /**
     * `$limit` is the maximum number of distinct values emitted verbatim; it is
     * validated at runtime rather than declared as `int<1, max>`, because it
     * typically arrives from application configuration.
     */
    public function __construct(
        private readonly RouteResolverInterface $inner,
        private readonly int $limit = 100,
        private readonly string $overflow = self::DEFAULT_OVERFLOW,
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException('Route limit must be at least 1');
        }
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): string
    {
        $route = $this->inner->resolve($request);

        if (isset($this->seen[$route])) {
            return $route;
        }

        if (\count($this->seen) >= $this->limit) {
            return $this->overflow;
        }

        $this->seen[$route] = true;

        return $route;
    }
}
