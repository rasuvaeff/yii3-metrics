<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware recording the RED signals (Rate, Errors, Duration) for every
 * request: a `http_server_requests_total` counter and a
 * `http_server_request_duration_seconds` histogram, both labelled by method,
 * route, and status.
 *
 * The `route` label defaults to the request path — wire a router-aware
 * {@see RouteResolverInterface} to avoid a series per URL id (cardinality).
 *
 * @api
 */
final readonly class RedMetricsMiddleware implements MiddlewareInterface
{
    private const string REQUESTS = 'http_server_requests_total';
    private const string DURATION = 'http_server_request_duration_seconds';
    private const array LABELS = ['method', 'route', 'status'];
    private const string STATUS_ON_THROW = '500';

    private CounterInterface $requests;
    private HistogramInterface $duration;

    public function __construct(
        MetricRegistry $registry,
        private RouteResolverInterface $routes = new PathRouteResolver(),
    ) {
        $this->requests = $registry->counter(self::REQUESTS, 'Total HTTP requests handled', self::LABELS);
        $this->duration = $registry->histogram(self::DURATION, 'HTTP request duration in seconds', self::LABELS);
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = hrtime(true);
        $status = self::STATUS_ON_THROW;

        try {
            $response = $handler->handle($request);
            $status = (string) $response->getStatusCode();

            return $response;
        } finally {
            $labels = new LabelSet([
                'method' => $request->getMethod(),
                'route' => $this->routes->resolve($request),
                'status' => $status,
            ]);

            $this->requests->inc(1.0, $labels);
            $this->duration->observe((float) (hrtime(true) - $start) / 1e9, $labels);
        }
    }
}
