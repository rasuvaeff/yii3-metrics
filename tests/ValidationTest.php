<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\Internal\Validation;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(Validation::class)]
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
