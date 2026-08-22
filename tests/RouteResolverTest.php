<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3Metrics\BoundedRouteResolver;
use Rasuvaeff\Yii3Metrics\ConstantRouteResolver;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\PathRouteResolver;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(ConstantRouteResolver::class)]
#[Covers(BoundedRouteResolver::class)]
#[Covers(PathRouteResolver::class)]
#[Covers(InvalidArgumentException::class)]
final class RouteResolverTest
{
    private Psr17Factory $factory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function constantResolverIgnoresTheRequest(): void
    {
        $resolver = new ConstantRouteResolver();

        Assert::same($resolver->resolve($this->request('/users/1')), '(unset)');
        Assert::same($resolver->resolve($this->request('/reset-password/tok')), '(unset)');
        Assert::same(ConstantRouteResolver::DEFAULT_VALUE, '(unset)');
    }

    public function constantResolverValueIsConfigurable(): void
    {
        Assert::same((new ConstantRouteResolver('app'))->resolve($this->request('/x')), 'app');
    }

    /**
     * The whole point of the safe default: whatever the attacker puts in the URI,
     * it must not come back out as a label value.
     */
    #[Property(runs: 200)]
    public function constantResolverNeverEchoesAnyPathSegment(string $path): void
    {
        Classify::when($path === '', 'empty path');

        Assert::same((new ConstantRouteResolver())->resolve($this->request('/' . $path)), '(unset)');
    }

    /** @return array<string, ArbitraryInterface> */
    public static function constantResolverNeverEchoesAnyPathSegmentGenerators(): array
    {
        return ['path' => Gen::stringFrom('abz09-_.~', 0, 24)];
    }

    /** @return iterable<string, array{string}> */
    public static function constantResolverNeverEchoesAnyPathSegmentExamples(): iterable
    {
        yield 'reset token' => ['reset-password/s3cr3t'];
        yield 'dotfile probe' => ['.env'];
        yield 'root' => [''];
    }

    public function boundedResolverPassesThroughUpToTheLimit(): void
    {
        $resolver = new BoundedRouteResolver(new PathRouteResolver(), limit: 3);

        Assert::same($resolver->resolve($this->request('/a')), '/a');
        Assert::same($resolver->resolve($this->request('/b')), '/b');
        Assert::same($resolver->resolve($this->request('/c')), '/c');
        Assert::same($resolver->resolve($this->request('/d')), '(other)');
        // Values already admitted keep passing after the cap is reached.
        Assert::same($resolver->resolve($this->request('/b')), '/b');
        Assert::same(BoundedRouteResolver::DEFAULT_OVERFLOW, '(other)');
    }

    public function boundedResolverOverflowValueIsConfigurable(): void
    {
        $resolver = new BoundedRouteResolver(new PathRouteResolver(), limit: 1, overflow: '(capped)');

        Assert::same($resolver->resolve($this->request('/a')), '/a');
        Assert::same($resolver->resolve($this->request('/b')), '(capped)');
    }

    #[DataProvider('invalidLimitProvider')]
    public function boundedResolverRejectsANonPositiveLimit(int $limit): void
    {
        try {
            new BoundedRouteResolver(new PathRouteResolver(), $limit);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('at least 1');
        }
    }

    public static function invalidLimitProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
    }

    /**
     * The bound is what makes raw paths survivable under scanner traffic: however
     * many distinct paths arrive, the number of distinct label values emitted can
     * never exceed `limit + 1` (the admitted values plus the overflow bucket).
     *
     * @param list<string> $paths
     * @param int<1, 8> $limit
     */
    #[Property(runs: 300)]
    public function boundedResolverEmitsAtMostLimitPlusOneDistinctValues(array $paths, int $limit): void
    {
        $resolver = new BoundedRouteResolver(new PathRouteResolver(), $limit);
        $emitted = [];

        foreach ($paths as $path) {
            $emitted[$resolver->resolve($this->request('/' . $path))] = true;
        }

        $distinctInput = \count(array_unique($paths));

        // Measured at runs=300: ~83% reach the cap, ~17% stay below it.
        Classify::cover($distinctInput > $limit, 'cap reached', 40.0);
        Classify::cover($distinctInput <= $limit, 'below the cap', 10.0);

        Assert::true(\count($emitted) <= $limit + 1);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function boundedResolverEmitsAtMostLimitPlusOneDistinctValuesGenerators(): array
    {
        return [
            'paths' => Gen::arrayOf(Gen::stringFrom('abcde', 1, 2), 0, 30),
            'limit' => Gen::intBetween(1, 8),
        ];
    }

    /** @return iterable<string, array{list<string>, int}> */
    public static function boundedResolverEmitsAtMostLimitPlusOneDistinctValuesExamples(): iterable
    {
        yield 'no traffic' => [[], 1];
        yield 'scanner flood on a cap of one' => [['a', 'b', 'c', 'd', 'e', 'a'], 1];
        yield 'every path fits' => [['a', 'b'], 8];
    }

    public function boundedResolverDelegatesToTheInnerResolver(): void
    {
        $inner = new class implements RouteResolverInterface {
            #[\Override]
            public function resolve(ServerRequestInterface $request): string
            {
                return '/users/{id}';
            }
        };

        Assert::same((new BoundedRouteResolver($inner))->resolve($this->request('/users/9')), '/users/{id}');
    }

    private function request(string $path): ServerRequestInterface
    {
        return $this->factory->createServerRequest('GET', 'https://example.test' . $path);
    }
}
