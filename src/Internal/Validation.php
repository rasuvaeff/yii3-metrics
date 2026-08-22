<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Internal;

use Rasuvaeff\Yii3Metrics\Buckets;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;

/**
 * Shared structural validation for metric registration and recording — applied by
 * every meter (including the no-op one) so a bad name, bucket layout or
 * non-finite amount fails fast rather than only when a recording backend is
 * enabled.
 *
 * @internal
 */
final class Validation
{
    /** Prometheus metric-name grammar (allows `:`, no dots). */
    private const string NAME_PATTERN = '/^[a-zA-Z_:][a-zA-Z0-9_:]*\z/';

    private function __construct() {}

    public static function metricName(string $name): void
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(\sprintf('Invalid metric name "%s"', $name));
        }
    }

    /**
     * Rejects `NAN` and `±INF` before they reach an accumulator. `NAN` is
     * absorbing (`NAN + x === NAN`), so a single non-finite recording poisons a
     * series for as long as the backend storage lives; `NAN` also fails every
     * bucket comparison, breaking the `count == bucket{le="+Inf"}` invariant.
     */
    public static function finiteAmount(float $amount): void
    {
        if (!is_finite($amount)) {
            throw new InvalidArgumentException('Metric amount must be finite');
        }
    }

    /**
     * Rejects `NAN` where `±INF` is still representable — a gauge's absolute
     * `set()`. `NAN` is not: promphp coerces it to the token `NAN` (the exposition
     * format spells it `NaN`) and PHP raises a warning while doing so, which under
     * `yiisoft/error-handler` turns the `/metrics` render into a 500. `±INF` has a
     * defined token (`+Inf` / `-Inf`) on both backends.
     */
    public static function notNan(float $value): void
    {
        if (is_nan($value)) {
            throw new InvalidArgumentException('Metric value must not be NaN');
        }
    }

    /**
     * Validates finite bounds are strictly increasing and appends the implicit
     * `+Inf` bucket. Empty input falls back to {@see Buckets::PROMETHEUS_DEFAULTS}.
     *
     * @param list<float> $bounds
     *
     * @return list<float> the validated bounds followed by `INF`
     */
    public static function histogramBuckets(array $bounds): array
    {
        if ($bounds === []) {
            $bounds = Buckets::PROMETHEUS_DEFAULTS;
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
