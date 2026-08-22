<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
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
        Assert::same($set->key(), '4:code=3:200,6:method=3:GET');
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

    /**
     * The exact pair from the review: with the old `name=value,…` join both sets
     * rendered as `a=1,b=2,b=3`, so every consumer that aggregates by `key()`
     * merged two unrelated series into one.
     */
    public function keySeparatesSetsWhoseValuesContainTheDelimiters(): void
    {
        $left = new LabelSet(['a' => '1,b=2', 'b' => '3']);
        $right = new LabelSet(['a' => '1', 'b' => '2,b=3']);

        Assert::false($left->equals($right));
        Assert::same($left->key(), '1:a=5:1,b=2,1:b=1:3');
        Assert::same($right->key(), '1:a=1:1,1:b=5:2,b=3');
    }

    /**
     * Injectivity in both directions: equal keys iff equal label sets. The value
     * alphabet deliberately contains `=` and `,`, the two characters the old key
     * format could not distinguish from its own separators.
     *
     * @param array<string, string> $left
     * @param array<string, string> $right
     */
    #[Property(runs: 400)]
    public function keyIsInjective(array $left, array $right): void
    {
        $a = new LabelSet($left);
        $b = new LabelSet($right);

        $equal = $a->equals($b);

        Classify::cover($equal, 'equal label sets', 2.0);
        Classify::cover(!$equal, 'distinct label sets', 50.0);
        Classify::when($a->isEmpty() || $b->isEmpty(), 'empty operand');

        Assert::same($a->key() === $b->key(), $equal);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function keyIsInjectiveGenerators(): array
    {
        $set = Gen::dictOf(Gen::elements(['a', 'b']), Gen::stringFrom('1,=', 0, 2), 0, 2);

        return ['left' => $set, 'right' => $set];
    }

    /** @return iterable<string, array{array<string, string>, array<string, string>}> */
    public static function keyIsInjectiveExamples(): iterable
    {
        yield 'review collision pair' => [['a' => '1,b=2', 'b' => '3'], ['a' => '1', 'b' => '2,b=3']];
        yield 'value is a whole pair' => [['a' => '', 'b' => ''], ['a' => '1:b=']];
        yield 'delimiter-only values' => [['a' => ','], ['a' => '=']];
        yield 'length prefix inside the value' => [['a' => '1:b'], ['a' => '1', 'b' => '']];
        yield 'both empty' => [[], []];
        yield 'identical non-empty' => [['a' => 'x=y,z'], ['a' => 'x=y,z']];
    }
}
