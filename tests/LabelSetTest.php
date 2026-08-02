<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(LabelSet::class)]
#[Covers(InvalidArgumentException::class)]
final class LabelSetTest
{
    public function storesAndCanonicalisesLabels(): void
    {
        $set = new LabelSet(['method' => 'GET', 'code' => '200']);

        Assert::same($set->labels, ['code' => '200', 'method' => 'GET']); // sorted
        Assert::same($set->names(), ['code', 'method']);
        Assert::same($set->key(), 'code=200,method=GET');
        Assert::false($set->isEmpty());
    }

    public function emptySetIsEmpty(): void
    {
        $set = new LabelSet();

        Assert::true($set->isEmpty());
        Assert::same($set->key(), '');
    }

    public function equalityIsOrderIndependent(): void
    {
        $a = new LabelSet(['a' => '1', 'b' => '2']);
        $b = new LabelSet(['b' => '2', 'a' => '1']);

        Assert::true($a->equals($b));
        Assert::false($a->equals(new LabelSet(['a' => '1'])));
    }

    public function coercesScalarValuesToStrings(): void
    {
        $set = new LabelSet(['i' => 5, 'f' => 1.5, 'yes' => true, 'no' => false]);

        Assert::same($set->labels['i'], '5');
        Assert::same($set->labels['f'], '1.5');
        Assert::same($set->labels['yes'], 'true');
        Assert::same($set->labels['no'], 'false');
    }

    #[DataProvider('invalidNameProvider')]
    public function rejectsInvalidLabelNames(int|string $name): void
    {
        try {
            new LabelSet([$name => 'x']);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Invalid label name');
        }
    }

    public static function invalidNameProvider(): iterable
    {
        yield 'leading digit' => ['1abc'];
        yield 'hyphen' => ['a-b'];
        yield 'dot' => ['a.b'];
        yield 'empty' => [''];
        yield 'trailing newline' => ["abc\n"];
        yield 'numeric key' => [0];
    }

    #[Property(runs: 200)]
    public function acceptsValidLabelNames(string $name): void
    {
        Assert::same((new LabelSet([$name => 'v']))->names(), [$name]);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function acceptsValidLabelNamesGenerators(): array
    {
        return [
            'name' => Gen::map(
                Gen::nonEmptyArrayOf(Gen::oneOf('a', 'b', 'c', '_', '0', '1', '9')),
                static fn(array $chars): string => 'k' . implode('', $chars),
            ),
        ];
    }
}
