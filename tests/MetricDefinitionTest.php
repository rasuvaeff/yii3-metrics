<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\Yii3Metrics\CounterDefinition;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\GaugeDefinition;
use Rasuvaeff\Yii3Metrics\HistogramDefinition;
use Rasuvaeff\Yii3Metrics\Internal\Validation;
use Rasuvaeff\Yii3Metrics\UpDownCounterDefinition;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(CounterDefinition::class)]
#[Covers(GaugeDefinition::class)]
#[Covers(UpDownCounterDefinition::class)]
#[Covers(HistogramDefinition::class)]
#[Covers(Validation::class)]
final class MetricDefinitionTest
{
    public function keepsWhatItDeclares(): void
    {
        $definition = new HistogramDefinition(
            name: 'request_seconds',
            help: 'Request duration',
            labelNames: ['route', 'method'],
            buckets: [0.1, 1.0],
        );

        Assert::same($definition->name, 'request_seconds');
        Assert::same($definition->help, 'Request duration');
        Assert::same($definition->labelNames, ['route', 'method']);
        Assert::same($definition->buckets, [0.1, 1.0]);
    }

    #[DataProvider('invalidDefinitions')]
    public function rejectsAnInvalidDeclarationOnConstruction(\Closure $construct, string $message): void
    {
        try {
            $construct();
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::same($e->getMessage(), $message);
        }
    }

    /**
     * @return iterable<string, array{\Closure(): object, string}>
     */
    public static function invalidDefinitions(): iterable
    {
        yield 'counter name' => [static fn(): object => new CounterDefinition('bad-name'), 'Invalid metric name "bad-name"'];
        yield 'gauge label name' => [static fn(): object => new GaugeDefinition('g', labelNames: ['1st']), 'Invalid label name "1st"'];
        yield 'up-down counter duplicate label' => [
            static fn(): object => new UpDownCounterDefinition('u', labelNames: ['a', 'a']),
            'Duplicate label name in [a, a]',
        ];
        yield 'histogram buckets' => [
            static fn(): object => new HistogramDefinition('h', buckets: [1.0, 0.5]),
            'Histogram bounds must be strictly increasing',
        ];
        yield 'label name with a trailing newline' => [
            static fn(): object => new CounterDefinition('c', labelNames: ["a\n"]),
            "Invalid label name \"a\n\"",
        ];
    }
}
