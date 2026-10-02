<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Internal;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\LabelSet;

/**
 * Turns the labels a defined instrument was handed into a {@see LabelSet} and
 * checks their names against the definition.
 *
 * @internal
 */
final class DefinedLabels
{
    private function __construct() {}

    /**
     * @param list<string> $declared
     * @param LabelSet|array<array-key, scalar> $labels
     */
    public static function resolve(string $metric, array $declared, array|LabelSet $labels): LabelSet
    {
        $set = $labels instanceof LabelSet ? $labels : new LabelSet($labels);
        $expected = $declared;
        sort($expected);

        if ($set->names() !== $expected) {
            throw new InvalidArgumentException(\sprintf(
                'Metric "%s" takes labels [%s], got [%s]',
                $metric,
                implode(', ', $expected),
                implode(', ', $set->names()),
            ));
        }

        return $set;
    }
}
