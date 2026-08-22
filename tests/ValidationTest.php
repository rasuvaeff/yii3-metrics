<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\Yii3Metrics\Buckets;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\Internal\Validation;
use Testo\Assert;
use Testo\Assert\ExpectNoAssertions;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(Validation::class)]
#[Covers(Buckets::class)]
#[Covers(InvalidArgumentException::class)]
final class ValidationTest
{
    #[DataProvider('nameProvider')]
    public function validatesMetricName(string $name, bool $valid): void
    {
        $threw = false;

        try {
            Validation::metricName($name);
        } catch (InvalidArgumentException) {
            $threw = true;
        }

        Assert::same($threw, !$valid);
    }

    public static function nameProvider(): iterable
    {
        yield 'simple' => ['http_requests_total', true];
        yield 'with colon' => ['app:requests:total', true];
        yield 'leading underscore' => ['_internal', true];
        yield 'dotted (invalid)' => ['http.requests', false];
        yield 'leading digit' => ['1metric', false];
        yield 'hyphen' => ['a-b', false];
        yield 'empty' => ['', false];
        yield 'trailing newline' => ["http_requests_total\n", false];
    }

    public function defaultsToPrometheusBucketsWhenEmpty(): void
    {
        $bounds = Validation::histogramBuckets([]);

        Assert::same($bounds, [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, INF]);
        Assert::same($bounds, [...Buckets::PROMETHEUS_DEFAULTS, INF]);
    }

    /**
     * The public bucket list is what a backend reads when it has to materialise
     * "no explicit bounds" itself, so its contract is: seconds, finite, strictly
     * increasing, and NO trailing `+Inf` (backends append their own overflow
     * bucket — a second one would be a silently wrong schema).
     */
    public function publicDefaultBucketsAreFiniteAscendingSeconds(): void
    {
        $previous = 0.0;

        foreach (Buckets::PROMETHEUS_DEFAULTS as $bound) {
            Assert::true(is_finite($bound));
            Assert::true($bound > $previous);
            $previous = $bound;
        }

        Assert::same($previous, 10.0);
        Assert::count(Buckets::PROMETHEUS_DEFAULTS, 11);
    }

    #[DataProvider('nonFiniteProvider')]
    public function rejectsNonFiniteAmounts(float $amount): void
    {
        try {
            Validation::finiteAmount($amount);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('must be finite');
        }
    }

    public static function nonFiniteProvider(): iterable
    {
        yield 'nan' => [NAN];
        yield 'positive infinity' => [INF];
        yield 'negative infinity' => [-INF];
    }

    #[DataProvider('finiteProvider')]
    #[ExpectNoAssertions]
    public function acceptsFiniteAmounts(float $amount): void
    {
        Validation::finiteAmount($amount);
    }

    public function notNanRejectsOnlyNan(): void
    {
        // A gauge's absolute set() can carry ±INF (both backends render a token
        // for it) but not NAN, which promphp coerces to an invalid token while
        // raising a PHP warning.
        Validation::notNan(INF);
        Validation::notNan(-INF);
        Validation::notNan(0.0);

        try {
            Validation::notNan(NAN);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('must not be NaN');
        }
    }

    public static function finiteProvider(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-1.5];
        yield 'max float' => [PHP_FLOAT_MAX];
        yield 'min float' => [-PHP_FLOAT_MAX];
    }

    public function appendsInfAndPreservesOrder(): void
    {
        Assert::same(Validation::histogramBuckets([1.0, 2.0, 5.0]), [1.0, 2.0, 5.0, INF]);
    }

    #[DataProvider('badBucketsProvider')]
    public function rejectsInvalidBuckets(array $bounds, string $needle): void
    {
        try {
            Validation::histogramBuckets($bounds);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains($needle);
        }
    }

    public static function badBucketsProvider(): iterable
    {
        yield 'descending' => [[2.0, 1.0], 'strictly increasing'];
        yield 'equal' => [[1.0, 1.0], 'strictly increasing'];
        yield 'infinite input' => [[1.0, INF], 'finite'];
    }
}
