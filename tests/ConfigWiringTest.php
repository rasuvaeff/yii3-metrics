<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Metrics\BoundedRouteResolver;
use Rasuvaeff\Yii3Metrics\ConstantRouteResolver;
use Rasuvaeff\Yii3Metrics\CurrentRouteResolver;
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3Metrics\NullCounter;
use Rasuvaeff\Yii3Metrics\NullMeterProvider;
use Rasuvaeff\Yii3Metrics\PathRouteResolver;
use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Yiisoft\Router\CurrentRoute;

/**
 * `config/*.php` are outside the cs/psalm/testo gate — this guards the wiring
 * contract: the core binds the facade + route resolver, never the swappable
 * `MeterProviderInterface`.
 */
#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    public function coreBindsTheFacadeAndRouteResolver(): void
    {
        Assert::array($this->di())->hasKeys(MetricRegistry::class, RouteResolverInterface::class);
    }

    /**
     * The shipped default must not be a resolver that reads the request: a
     * raw-path `route` label is attacker-controlled, so it both explodes the
     * series count and copies path tokens into `/metrics`.
     */
    public function defaultRouteResolverIsRequestIndependent(): void
    {
        Assert::same($this->di()[RouteResolverInterface::class], ConstantRouteResolver::class);
    }

    public function coreDoesNotBindTheSwappableProviderInterface(): void
    {
        Assert::array($this->di())->doesNotHaveKeys(MeterProviderInterface::class);
    }

    /**
     * The README documents three ways to opt into a route breakdown by rebinding
     * `RouteResolverInterface`. A merge harness cannot see them — it merges the
     * vendor layer, while the recipe lives in the application layer — so the
     * recipe is executed here verbatim instead.
     */
    public function everyDocumentedRouteResolverOptInResolves(): void
    {
        $request = new ServerRequest('GET', '/users/42');

        $current = new CurrentRouteResolver(new CurrentRoute());
        $bounded = (static fn(): RouteResolverInterface
            => new BoundedRouteResolver(new PathRouteResolver(), limit: 100))();
        $path = new PathRouteResolver();

        foreach ([$current, $bounded, $path] as $resolver) {
            Assert::instanceOf($resolver, RouteResolverInterface::class);
        }

        Assert::same($current->resolve($request), '(unmatched)');
        Assert::same($bounded->resolve($request), '/users/42');
        Assert::same($path->resolve($request), '/users/42');
    }

    public function webConfigBindsTheRedMiddlewareWithParams(): void
    {
        /** @var array<string, mixed> $params */
        $params = require dirname(__DIR__) . '/config/params.php';
        $params['rasuvaeff/yii3-metrics']['red'] = [
            'duration_buckets' => [0.1, 1.0],
            'excluded_paths' => ['/metrics'],
        ];

        /** @var array<string, mixed> $di */
        $di = (static fn(array $params): array => require dirname(__DIR__) . '/config/di-web.php')($params);

        Assert::array($di)->hasKeys(RedMetricsMiddleware::class);

        /** @var array{__construct(): array{durationBuckets: mixed, excludedPaths: mixed}} $definition */
        $definition = $di[RedMetricsMiddleware::class];
        Assert::same($definition['__construct()']['durationBuckets'], [0.1, 1.0]);
        Assert::same($definition['__construct()']['excludedPaths'], ['/metrics']);
    }

    public function facadeBecomesNoOpWithTheNullProvider(): void
    {
        $registry = new MetricRegistry(new NullMeterProvider());

        $registry->counter('c')->inc();

        Assert::instanceOf($registry->counter('c'), NullCounter::class);
    }

    public function paramsAreNamespaced(): void
    {
        /** @var array<string, mixed> $params */
        $params = require dirname(__DIR__) . '/config/params.php';

        Assert::array($params)->hasKeys('rasuvaeff/yii3-metrics');
        Assert::array($params['rasuvaeff/yii3-metrics']['red'])->hasKeys('duration_buckets', 'excluded_paths');
    }

    /**
     * @return array<string, mixed>
     */
    private function di(): array
    {
        /** @var array<string, mixed> $di */
        $di = require dirname(__DIR__) . '/config/di.php';

        return $di;
    }
}
