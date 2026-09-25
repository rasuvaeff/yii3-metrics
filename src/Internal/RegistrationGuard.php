<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Internal;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\MetricKind;

/**
 * Strict-naming checks a meter runs at registration, so mistakes surface where
 * the metric is declared rather than at the first write or in a dashboard:
 *
 * - suffix conventions: a counter ends with `_total`, nothing else does, and a
 *   histogram does not end with its own series suffixes;
 * - a repeated name must repeat the definition — same kind, label names and
 *   bucket layout; help texts may differ only when one of them is empty;
 * - two metrics must not expose the same series name (a gauge `h_count` next to
 *   a histogram `h`).
 *
 * One guard per meter: it holds the definitions seen so far. Stable API for the
 * backend family, like {@see Validation}.
 *
 * @api
 */
final class RegistrationGuard
{
    private const string TOTAL_SUFFIX = '_total';

    private const array HISTOGRAM_SUFFIXES = ['_bucket', '_sum', '_count'];

    /** @var array<string, array{kind: MetricKind, help: string, labelNames: list<string>, buckets: list<float>}> */
    private array $definitions = [];

    /** @var array<string, string> exposed series name => owning metric name */
    private array $exposedNames = [];

    /**
     * @param list<string> $labelNames
     * @param list<float> $buckets histogram bounds as passed to the meter; ignored for other kinds
     *
     * @throws InvalidArgumentException
     */
    public function register(
        MetricKind $kind,
        string $name,
        string $help = '',
        array $labelNames = [],
        array $buckets = [],
    ): void {
        $this->checkSuffix($kind, $name);

        sort($labelNames);

        $definition = [
            'kind' => $kind,
            'help' => $help,
            'labelNames' => $labelNames,
            'buckets' => $kind === MetricKind::Histogram ? Validation::histogramBuckets($buckets) : [],
        ];

        $existing = $this->definitions[$name] ?? null;

        if ($existing === null) {
            $this->claimExposedNames($kind, $name);
            $this->definitions[$name] = $definition;

            return;
        }

        if (
            $existing['kind'] !== $kind
            || $existing['labelNames'] !== $labelNames
            || $existing['buckets'] !== $definition['buckets']
            || ($existing['help'] !== '' && $help !== '' && $existing['help'] !== $help)
        ) {
            throw new InvalidArgumentException(\sprintf(
                'Metric "%s" is already registered as %s, cannot register it as %s',
                $name,
                $this->describe($existing),
                $this->describe($definition),
            ));
        }

        if ($existing['help'] === '') {
            $this->definitions[$name]['help'] = $help;
        }
    }

    private function checkSuffix(MetricKind $kind, string $name): void
    {
        $endsWithTotal = str_ends_with($name, self::TOTAL_SUFFIX);

        if ($kind === MetricKind::Counter && !$endsWithTotal) {
            throw new InvalidArgumentException(\sprintf('Counter name "%s" must end with "_total"', $name));
        }

        if ($kind !== MetricKind::Counter && $endsWithTotal) {
            throw new InvalidArgumentException(\sprintf(
                'Metric "%s" of kind %s must not end with "_total", which is reserved for counters',
                $name,
                $kind->value,
            ));
        }

        if ($kind !== MetricKind::Histogram) {
            return;
        }

        foreach (self::HISTOGRAM_SUFFIXES as $suffix) {
            if (str_ends_with($name, $suffix)) {
                throw new InvalidArgumentException(\sprintf(
                    'Histogram name "%s" must not end with "%s", which collides with its own series',
                    $name,
                    $suffix,
                ));
            }
        }
    }

    private function claimExposedNames(MetricKind $kind, string $name): void
    {
        $exposed = [$name];

        if ($kind === MetricKind::Histogram) {
            foreach (self::HISTOGRAM_SUFFIXES as $suffix) {
                $exposed[] = $name . $suffix;
            }
        }

        foreach ($exposed as $series) {
            $owner = $this->exposedNames[$series] ?? null;

            if ($owner !== null) {
                throw new InvalidArgumentException(\sprintf(
                    'Metric "%s" and already registered %s "%s" both expose the series "%s"',
                    $name,
                    $this->definitions[$owner]['kind']->value,
                    $owner,
                    $series,
                ));
            }
        }

        foreach ($exposed as $series) {
            $this->exposedNames[$series] = $name;
        }
    }

    /**
     * @param array{kind: MetricKind, help: string, labelNames: list<string>, buckets: list<float>} $definition
     */
    private function describe(array $definition): string
    {
        $parts = [\sprintf('labels=[%s]', implode(', ', $definition['labelNames']))];

        if ($definition['kind'] === MetricKind::Histogram) {
            $parts[] = \sprintf('buckets=[%s]', implode(', ', array_map(
                static fn(float $bound): string => is_infinite($bound) ? '+Inf' : (string) $bound,
                $definition['buckets'],
            )));
        }

        if ($definition['help'] !== '') {
            $parts[] = \sprintf('help="%s"', $definition['help']);
        }

        return \sprintf('%s{%s}', $definition['kind']->value, implode(', ', $parts));
    }

}
