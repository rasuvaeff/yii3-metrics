<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3Metrics\InMemoryMeterProvider;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3Metrics\RedMetricsMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$provider = new InMemoryMeterProvider();
$middleware = new RedMetricsMiddleware(new MetricRegistry($provider));

$handler = new readonly class implements RequestHandlerInterface {
    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200);
    }
};

$factory = new Psr17Factory();
$middleware->process($factory->createServerRequest('GET', 'https://demo/users'), $handler);
$middleware->process($factory->createServerRequest('GET', 'https://demo/users'), $handler);

foreach ($provider->snapshots() as $snapshot) {
    echo "{$snapshot->name} ({$snapshot->kind->value})\n";

    foreach ($snapshot->samples as $sample) {
        printf("  %s -> %s\n", $sample->labels->key(), (string) $sample->value);
    }
}
