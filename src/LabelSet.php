<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;

/**
 * Immutable set of metric label name/value pairs. Names are validated against the
 * Prometheus label-name grammar; values are arbitrary strings. Names are stored
 * in canonical (sorted) order so two sets with the same pairs are equal
 * regardless of insertion order.
 *
 * @api
 */
final readonly class LabelSet
{
    private const string NAME_PATTERN = '/^[a-zA-Z_]\w*\z/';

    /** @var array<non-empty-string, string> */
    public array $labels;

    /**
     * @param array<array-key, scalar> $labels
     */
    public function __construct(array $labels = [])
    {
        $validated = [];

        foreach ($labels as $name => $value) {
            if (!\is_string($name) || $name === '' || preg_match(self::NAME_PATTERN, $name) !== 1) {
                throw new InvalidArgumentException(\sprintf('Invalid label name "%s"', (string) $name));
            }

            $validated[$name] = match (true) {
                \is_bool($value) => $value ? 'true' : 'false',
                \is_string($value) => $value,
                default => (string) $value,
            };
        }

        ksort($validated);

        $this->labels = $validated;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->labels);
    }

    public function isEmpty(): bool
    {
        return $this->labels === [];
    }

    public function equals(self $other): bool
    {
        return $this->labels === $other->labels;
    }

    /**
     * Canonical string key for storing values per label set.
     *
     * Every name and value is length-prefixed (`<len>:<bytes>`), which makes the
     * key injective: distinct label sets always produce distinct keys, and the
     * key can be parsed back into the exact pairs. A plain `name=value,…` join
     * is ambiguous, because label values are arbitrary untrusted strings that may
     * themselves contain `=` and `,` — `['a' => '1,b=2', 'b' => '3']` and
     * `['a' => '1', 'b' => '2,b=3']` both rendered as `a=1,b=2,b=3` and were
     * merged into one series wherever this key aggregates values.
     */
    public function key(): string
    {
        $parts = [];

        foreach ($this->labels as $name => $value) {
            $parts[] = \strlen($name) . ':' . $name . '=' . \strlen($value) . ':' . $value;
        }

        return implode(',', $parts);
    }
}
