<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Internal;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;

/**
 * Shared structural validation for metric registration — applied by every meter
 * (including the no-op one) so a bad name or bucket layout fails fast rather than
 * only when a recording backend is enabled.
 *
 * @internal
 */
final class Validation
{
    /** Prometheus metric-name grammar (allows `:`, no dots). */
    private const string NAME_PATTERN = '/^[a-zA-Z_:][a-zA-Z0-9_:]*\z/';

    /** @var list<float> Prometheus default histogram bounds (seconds). */
    public const array DEFAULT_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    private function __construct() {}

    public static function metricName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(\sprintf('Invalid metric name "%s"', $name));
        }
    }

    /**
     * Validates finite bounds are strictly increasing and appends the implicit
     * `+Inf` bucket. Empty input falls back to {@see DEFAULT_BUCKETS}.
     *
     * @param list<float> $bounds
     *
     * @return list<float> the validated bounds followed by `INF`
     */
    public static function histogramBuckets(array $bounds): array
    {
        if ($bounds === []) {
            $bounds = self::DEFAULT_BUCKETS;
        }

        $previous = -INF;

        foreach ($bounds as $bound) {
            if (is_infinite($bound) || is_nan($bound)) {
                throw new InvalidArgumentException('Histogram bounds must be finite');
            }

            if ($bound <= $previous) {
                throw new InvalidArgumentException('Histogram bounds must be strictly increasing');
            }

            $previous = $bound;
        }

        $bounds[] = INF;

        return $bounds;
    }
}
