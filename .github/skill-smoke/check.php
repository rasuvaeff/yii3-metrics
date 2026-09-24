<?php

declare(strict_types=1);

use Prometheus\CollectorRegistry;
use Rasuvaeff\Yii3Metrics\FailOpenMeterProvider;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusRenderer;
use Testo\Assert;
use Yiisoft\Config\Config;
use Yiisoft\Config\ConfigPaths;
use Yiisoft\Config\Modifier\RecursiveMerge;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

require __DIR__ . '/vendor/autoload.php';

// Both packages live in the vendor layer. Composer generates the merge plan;
// yiisoft/config applies the application's override with the runner's modifier.
$config = new Config(
    new ConfigPaths(__DIR__, 'config'),
    null,
    [RecursiveMerge::groups('params')],
);
$container = new Container(ContainerConfig::create()->withDefinitions($config->get('di')));
Assert::instanceOf($container->get(MeterProviderInterface::class), FailOpenMeterProvider::class);
$registry = $container->get(MetricRegistry::class);
$registry->counter('requests_total', labelNames: ['source'])
    ->inc(labels: new LabelSet(['source' => 'tiktok.http']));
$body = (new PrometheusRenderer())->render($container->get(CollectorRegistry::class));
Assert::string($body)->contains('skill_requests_total{source="tiktok.http"} 1');

require __DIR__ . '/testing.php';

// The copied package must actually ship the skill to Composer consumers.
Assert::same(
    file_get_contents(__DIR__ . '/vendor/rasuvaeff/yii3-metrics/resources/skills/rasuvaeff-yii3-metrics/SKILL.md'),
    file_get_contents(__DIR__ . '/../../resources/skills/rasuvaeff-yii3-metrics/SKILL.md'),
);
echo "Skill consumer smoke passed: application DI override, namespace, recording, test helpers, packaged skill.\n";
