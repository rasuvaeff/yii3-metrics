<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

/**
 * Shared histogram bucket layouts. The core, and every backend that has to
 * materialise "no explicit bounds" into a concrete list, reads the defaults from
 * here so the same code produces the same bucket schema on every backend.
 *
 * @api
 */
final class Buckets
{
    /**
     * Prometheus' default histogram bounds, in SECONDS. Finite and strictly
     * increasing — the implicit `+Inf` bucket is NOT part of the list, because
     * backends append (or model) the overflow bucket themselves.
     *
     * @var list<float>
     */
    public const array PROMETHEUS_DEFAULTS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    private function __construct() {}
}
