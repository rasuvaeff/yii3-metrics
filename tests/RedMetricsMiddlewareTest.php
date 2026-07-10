<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\MetricKind;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3Metrics\MetricSnapshot;
use Rasuvaeff\Yii3Metrics\PathRouteResolver;
use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(RedMetricsMiddleware::class)]
#[Covers(PathRouteResolver::class)]
final class RedMetricsMiddlewareTest
{
    private InMemoryMeterProvider $provider;
    private MetricRegistry $registry;
    private Psr17Factory $factory;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->provider = new InMemoryMeterProvider();
        $this->registry = new MetricRegistry($this->provider);
        $this->factory = new Psr17Factory();
    }

    public function recordsRequestCountAndDuration(): void
    {
        $middleware = new RedMetricsMiddleware($this->registry);

        $middleware->process($this->factory->createServerRequest('GET', 'https://x/users'), $this->handler(200));

        $requests = $this->snapshot('http_server_requests_total');
        Assert::same($requests->kind, MetricKind::Counter);
        Assert::same($requests->samples[0]->value, 1.0);
        Assert::same($requests->samples[0]->labels->labels, [
            'method' => 'GET',
            'route' => '/users',
            'status' => '200',
        ]);

        $duration = $this->snapshot('http_server_request_duration_seconds');
        Assert::same($duration->kind, MetricKind::Histogram);
        Assert::same($duration->samples[0]->value, 1.0); // one observation
        // A plausible sub-second duration — guards the hrtime delta calculation.
        $sum = $duration->samples[0]->sum ?? -1.0;
        Assert::true($sum >= 0.0 && $sum < 1.0);
    }

    public function accumulatesRepeatedRequestsOnOneSeries(): void
    {
        $middleware = new RedMetricsMiddleware($this->registry);

        $middleware->process($this->factory->createServerRequest('GET', 'https://x/users'), $this->handler(200));
        $middleware->process($this->factory->createServerRequest('GET', 'https://x/users'), $this->handler(200));

        Assert::same($this->snapshot('http_server_requests_total')->samples[0]->value, 2.0);
    }

    public function recordsStatus500WhenTheHandlerThrows(): void
    {
        $middleware = new RedMetricsMiddleware($this->registry);

        try {
            $middleware->process($this->factory->createServerRequest('POST', 'https://x/orders'), $this->throwingHandler());
            Assert::fail('expected the handler exception to propagate');
        } catch (\RuntimeException $e) {
            Assert::same($e->getMessage(), 'boom');
        }

        Assert::same($this->snapshot('http_server_requests_total')->samples[0]->labels->labels['status'], '500');
    }

    public function usesTheInjectedRouteResolver(): void
    {
        $resolver = new class implements RouteResolverInterface {
            #[\Override]
            public function resolve(ServerRequestInterface $request): string
            {
                return '/users/{id}';
            }
        };
        $middleware = new RedMetricsMiddleware($this->registry, $resolver);

        $middleware->process($this->factory->createServerRequest('GET', 'https://x/users/123'), $this->handler(200));

        Assert::same($this->snapshot('http_server_requests_total')->samples[0]->labels->labels['route'], '/users/{id}');
    }

    public function customDurationBucketsReachTheHistogram(): void
    {
        $middleware = new RedMetricsMiddleware(
            $this->registry,
            durationBuckets: [1.0, 30.0, 120.0],
        );

        $middleware->process($this->factory->createServerRequest('GET', 'https://x/slow'), $this->handler(200));

        $duration = $this->snapshot('http_server_request_duration_seconds');
        // PHP casts numeric string array keys to int.
        Assert::same(array_keys($duration->samples[0]->buckets), [1, 30, 120, '+Inf']);
    }

    private function snapshot(string $name): MetricSnapshot
    {
        foreach ($this->provider->snapshots() as $snapshot) {
            if ($snapshot->name === $name) {
                return $snapshot;
            }
        }

        Assert::fail(\sprintf('no snapshot named "%s"', $name));
    }

    private function handler(int $status): RequestHandlerInterface
    {
        return new readonly class ($status) implements RequestHandlerInterface {
            public function __construct(private int $status) {}

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response($this->status);
            }
        };
    }

    private function throwingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('boom');
            }
        };
    }
}
