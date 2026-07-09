<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3Metrics\CurrentRouteResolver;
use Rasuvaeff\Yii3Metrics\PathRouteResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\Route;

#[Test]
#[Covers(CurrentRouteResolver::class)]
final class CurrentRouteResolverTest
{
    private Psr17Factory $factory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function resolvesMatchedRouteToPattern(): void
    {
        $currentRoute = new CurrentRoute();
        $currentRoute->setRouteWithArguments(Route::get('/users/{id}'), ['id' => '123']);
        $resolver = new CurrentRouteResolver($currentRoute);

        $route = $resolver->resolve($this->factory->createServerRequest('GET', 'https://x/users/123'));

        Assert::same($route, '/users/{id}');
    }

    public function unmatchedRequestCollapsesToConstant(): void
    {
        $resolver = new CurrentRouteResolver(new CurrentRoute());

        $route = $resolver->resolve($this->factory->createServerRequest('GET', 'https://x/wp-admin/setup.php'));

        Assert::same($route, '(unmatched)');
    }

    public function unmatchedRequestUsesInjectedFallback(): void
    {
        $resolver = new CurrentRouteResolver(new CurrentRoute(), fallback: new PathRouteResolver());

        $route = $resolver->resolve($this->factory->createServerRequest('GET', 'https://x/legacy/page'));

        Assert::same($route, '/legacy/page');
    }

    public function matchedRouteIgnoresFallback(): void
    {
        $currentRoute = new CurrentRoute();
        $currentRoute->setRouteWithArguments(Route::get('/orders/{id}'), ['id' => '7']);
        $resolver = new CurrentRouteResolver($currentRoute, fallback: new PathRouteResolver());

        $route = $resolver->resolve($this->factory->createServerRequest('GET', 'https://x/orders/7'));

        Assert::same($route, '/orders/{id}');
    }
}
