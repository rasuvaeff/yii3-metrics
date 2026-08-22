<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3Metrics\BoundedRouteResolver;
use Rasuvaeff\Yii3Metrics\ConstantRouteResolver;
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
#[Covers(ConstantRouteResolver::class)]
#[Covers(BoundedRouteResolver::class)]
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
            'route' => '(unset)', // the shipped default never reads the URI
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

    /**
     * Regression for the default `route` label. A raw path used to be the shipped
     * default, so a single-use token in the URL reached the exposition and every
     * scanned URL became its own series. Neither may happen without the
     * application choosing a resolver.
     */
    public function defaultRouteLabelNeverEchoesTheRequestPath(): void
    {
        $middleware = new RedMetricsMiddleware($this->registry);

        $middleware->process(
            $this->factory->createServerRequest('GET', 'https://x/reset-password/s3cr3t-token'),
            $this->handler(200),
        );
        $middleware->process($this->factory->createServerRequest('GET', 'https://x/.env'), $this->handler(404));
        $middleware->process($this->factory->createServerRequest('GET', 'https://x/wp-admin'), $this->handler(404));

        $routes = [];

        foreach ($this->snapshot('http_server_requests_total')->samples as $sample) {
            $routes[] = $sample->labels->labels['route'];
        }

        Assert::same(array_values(array_unique($routes)), ['(unset)']);
    }

    public function pathRouteResolverStaysAvailableAsAnExplicitOptIn(): void
    {
        $middleware = new RedMetricsMiddleware($this->registry, new PathRouteResolver());

        $middleware->process($this->factory->createServerRequest('GET', 'https://x/users'), $this->handler(200));

        Assert::same($this->snapshot('http_server_requests_total')->samples[0]->labels->labels['route'], '/users');
    }

    public function boundedResolverCollapsesRoutesPastTheLimit(): void
    {
        $middleware = new RedMetricsMiddleware(
            $this->registry,
            new BoundedRouteResolver(new PathRouteResolver(), limit: 2),
        );

        foreach (['/a', '/b', '/c', '/d', '/a'] as $path) {
            $middleware->process($this->factory->createServerRequest('GET', 'https://x' . $path), $this->handler(200));
        }

        $counts = [];

        foreach ($this->snapshot('http_server_requests_total')->samples as $sample) {
            $counts[$sample->labels->labels['route']] = $sample->value;
        }

        ksort($counts);

        Assert::same($counts, ['(other)' => 2.0, '/a' => 2.0, '/b' => 1.0]);
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

    public function excludedPathIsNotRecorded(): void
    {
        $middleware = new RedMetricsMiddleware($this->registry, excludedPaths: ['/metrics']);

        $response = $middleware->process($this->factory->createServerRequest('GET', 'https://x/metrics'), $this->handler(200));

        Assert::same($response->getStatusCode(), 200);

        // Instruments are registered in the constructor, but nothing was recorded.
        foreach ($this->provider->snapshots() as $snapshot) {
            Assert::count($snapshot->samples, 0);
        }
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
