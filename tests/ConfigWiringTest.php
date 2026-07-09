<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3Metrics\NullCounter;
use Rasuvaeff\Yii3Metrics\NullMeterProvider;
use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

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

    public function coreDoesNotBindTheSwappableProviderInterface(): void
    {
        Assert::array($this->di())->doesNotHaveKeys(MeterProviderInterface::class);
    }

    public function webConfigBindsTheRedMiddleware(): void
    {
        /** @var array<string, mixed> $di */
        $di = require dirname(__DIR__) . '/config/di-web.php';

        Assert::array($di)->hasKeys(RedMetricsMiddleware::class);
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
