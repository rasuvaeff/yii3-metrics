<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\Internal\RegistrationGuard;
use Rasuvaeff\Yii3Metrics\MetricKind;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(RegistrationGuard::class)]
final class RegistrationGuardTest
{
    #[DataProvider('suffixViolationProvider')]
    public function rejectsSuffixViolations(MetricKind $kind, string $name, string $message): void
    {
        try {
            (new RegistrationGuard())->register(kind: $kind, name: $name);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same($e->getMessage(), $message);
        }
    }

    public static function suffixViolationProvider(): iterable
    {
        yield 'counter without _total' => [
            MetricKind::Counter,
            'requests',
            'Counter name "requests" must end with "_total"',
        ];

        yield 'gauge with _total' => [
            MetricKind::Gauge,
            'creator_platforms_total',
            'Metric "creator_platforms_total" of kind gauge must not end with "_total", which is reserved for counters',
        ];

        yield 'up-down counter with _total' => [
            MetricKind::UpDownCounter,
            'inflight_total',
            'Metric "inflight_total" of kind up_down_counter must not end with "_total", which is reserved for counters',
        ];

        yield 'histogram with _total' => [
            MetricKind::Histogram,
            'latency_total',
            'Metric "latency_total" of kind histogram must not end with "_total", which is reserved for counters',
        ];

        yield 'histogram with _bucket' => [
            MetricKind::Histogram,
            'latency_bucket',
            'Histogram name "latency_bucket" must not end with "_bucket", which collides with its own series',
        ];

        yield 'histogram with _sum' => [
            MetricKind::Histogram,
            'latency_sum',
            'Histogram name "latency_sum" must not end with "_sum", which collides with its own series',
        ];

        yield 'histogram with _count' => [
            MetricKind::Histogram,
            'latency_count',
            'Histogram name "latency_count" must not end with "_count", which collides with its own series',
        ];
    }

    #[DataProvider('conformingNameProvider')]
    public function acceptsConformingNames(MetricKind $kind, string $name): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: $kind, name: $name);
        $guard->register(kind: $kind, name: $name);

        Assert::true(actual: true);
    }

    public static function conformingNameProvider(): iterable
    {
        yield 'counter' => [MetricKind::Counter, 'http_requests_total'];

        yield 'gauge' => [MetricKind::Gauge, 'queue_depth'];

        yield 'gauge with a series-like suffix' => [MetricKind::Gauge, 'items_count'];

        yield 'up-down counter' => [MetricKind::UpDownCounter, 'inflight_requests'];

        yield 'histogram' => [MetricKind::Histogram, 'request_duration_seconds'];
    }

    public function rejectsDifferentLabelNamesAtRegistration(): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Counter, name: 'a_total', labelNames: ['x']);

        try {
            $guard->register(kind: MetricKind::Counter, name: 'a_total', labelNames: ['y']);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same(
                $e->getMessage(),
                'Metric "a_total" is already registered as counter{labels=[x]}, cannot register it as counter{labels=[y]}',
            );
        }
    }

    public function rejectsADifferentKind(): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Gauge, name: 'depth', help: 'Queue depth');

        try {
            $guard->register(kind: MetricKind::UpDownCounter, name: 'depth');
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same(
                $e->getMessage(),
                'Metric "depth" is already registered as gauge{labels=[], help="Queue depth"}, cannot register it as up_down_counter{labels=[]}',
            );
        }
    }

    public function rejectsDifferentBuckets(): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Histogram, name: 'h', buckets: [0.5, 1.0]);

        try {
            $guard->register(kind: MetricKind::Histogram, name: 'h', buckets: [0.5, 2.0]);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same(
                $e->getMessage(),
                'Metric "h" is already registered as histogram{labels=[], buckets=[0.5, 1, +Inf]}, cannot register it as histogram{labels=[], buckets=[0.5, 2, +Inf]}',
            );
        }
    }

    public function treatsOmittedBucketsAsTheDefaultLayout(): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Histogram, name: 'h');
        $guard->register(
            kind: MetricKind::Histogram,
            name: 'h',
            buckets: [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0],
        );

        Assert::true(actual: true);
    }

    public function rejectsInvalidBucketsEvenOnFirstRegistration(): void
    {
        try {
            (new RegistrationGuard())->register(kind: MetricKind::Histogram, name: 'h', buckets: [2.0, 1.0]);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('strictly increasing');
        }
    }

    public function rejectsTwoDifferentNonEmptyHelpTexts(): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Counter, name: 'a_total', help: 'First');

        try {
            $guard->register(kind: MetricKind::Counter, name: 'a_total', help: 'Second');
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same(
                $e->getMessage(),
                'Metric "a_total" is already registered as counter{labels=[], help="First"}, cannot register it as counter{labels=[], help="Second"}',
            );
        }
    }

    public function anEmptyHelpMatchesAnyHelp(): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Counter, name: 'a_total', help: 'Described');
        $guard->register(kind: MetricKind::Counter, name: 'a_total');
        $guard->register(kind: MetricKind::Counter, name: 'a_total', help: 'Described');

        Assert::true(actual: true);
    }

    public function theFirstNonEmptyHelpIsTheOneLaterRegistrationsMustMatch(): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Counter, name: 'a_total');
        $guard->register(kind: MetricKind::Counter, name: 'a_total', help: 'First');

        try {
            $guard->register(kind: MetricKind::Counter, name: 'a_total', help: 'Second');
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('help="First"');
        }
    }

    #[DataProvider('seriesCollisionProvider')]
    public function rejectsTwoMetricsExposingTheSameSeries(
        MetricKind $firstKind,
        string $firstName,
        MetricKind $secondKind,
        string $secondName,
        string $message,
    ): void {
        $guard = new RegistrationGuard();
        $guard->register(kind: $firstKind, name: $firstName);

        try {
            $guard->register(kind: $secondKind, name: $secondName);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same($e->getMessage(), $message);
        }
    }

    public static function seriesCollisionProvider(): iterable
    {
        yield 'gauge after histogram' => [
            MetricKind::Histogram,
            'latency',
            MetricKind::Gauge,
            'latency_count',
            'Metric "latency_count" and already registered histogram "latency" both expose the series "latency_count"',
        ];

        yield 'histogram after gauge' => [
            MetricKind::Gauge,
            'latency_sum',
            MetricKind::Histogram,
            'latency',
            'Metric "latency" and already registered gauge "latency_sum" both expose the series "latency_sum"',
        ];
    }

    public function aRejectedMetricClaimsNoSeries(): void
    {
        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Gauge, name: 'latency_count');

        try {
            $guard->register(kind: MetricKind::Histogram, name: 'latency');
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException) {
        }

        $guard->register(kind: MetricKind::Gauge, name: 'latency_sum');

        Assert::true(actual: true);
    }

    /**
     * The suffix rule is a pure function of kind and name: accepted exactly
     * when a counter ends with `_total`, anything else does not, and a
     * histogram also avoids its own series suffixes.
     */
    #[Property(runs: 300)]
    public function suffixRuleAcceptsExactlyTheConformingNames(MetricKind $kind, string $stem, string $suffix): void
    {
        $name = $stem . $suffix;
        $endsWithTotal = str_ends_with($name, '_total');
        $expected = $kind === MetricKind::Counter
            ? $endsWithTotal
            : !$endsWithTotal && ($kind !== MetricKind::Histogram || preg_match('/_(bucket|sum|count)\z/', $name) !== 1);

        try {
            (new RegistrationGuard())->register(kind: $kind, name: $name);
            $accepted = true;
        } catch (InvalidArgumentException) {
            $accepted = false;
        }

        Classify::cover($accepted, 'accepted', 20.0);
        Classify::cover(!$accepted, 'rejected', 20.0);
        Classify::label($kind->value);

        Assert::same($accepted, $expected);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function suffixRuleAcceptsExactlyTheConformingNamesGenerators(): array
    {
        return [
            'kind' => Gen::enum(MetricKind::class),
            'stem' => Gen::regex('[a-z][a-z0-9_]{0,8}'),
            'suffix' => Gen::elements(['', '_total', '_bucket', '_sum', '_count']),
        ];
    }

    public static function suffixRuleAcceptsExactlyTheConformingNamesExamples(): iterable
    {
        yield 'counter without suffix' => [MetricKind::Counter, 'requests', ''];

        yield 'gauge named like a counter' => [MetricKind::Gauge, 'creator_ai_tags', '_total'];

        yield 'gauge with a histogram suffix' => [MetricKind::Gauge, 'jobs', '_count'];

        yield 'histogram with its own suffix' => [MetricKind::Histogram, 'latency', '_bucket'];
    }

    /**
     * Label names are a set: re-registering them in another order is the same
     * definition, not a conflict.
     *
     * @param list<string> $labelNames
     */
    #[Property(runs: 200)]
    public function labelNameOrderDoesNotMatter(array $labelNames, int $shift): void
    {
        $rotated = [...\array_slice($labelNames, $shift % \count($labelNames)), ...\array_slice($labelNames, 0, $shift % \count($labelNames))];

        $guard = new RegistrationGuard();
        $guard->register(kind: MetricKind::Counter, name: 'a_total', labelNames: $labelNames);
        $guard->register(kind: MetricKind::Counter, name: 'a_total', labelNames: $rotated);

        Classify::cover($rotated !== $labelNames, 'reordered', 30.0);

        Assert::true(actual: true);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function labelNameOrderDoesNotMatterGenerators(): array
    {
        return [
            'labelNames' => Gen::uniqueArrayOf(Gen::regex('[a-z]{1,6}'), 1, 5),
            'shift' => Gen::intBetween(0, 10),
        ];
    }
}
