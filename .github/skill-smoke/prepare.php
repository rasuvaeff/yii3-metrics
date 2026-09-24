<?php

declare(strict_types=1);

// Execute the published recipe itself, so edits to the skill exercise real DI.
$skill = file_get_contents(__DIR__ . '/../../resources/skills/rasuvaeff-yii3-metrics/SKILL.md');
foreach (['di', 'testing'] as $name) {
    if (preg_match('/<!-- smoke:' . $name . ' -->\s*```php\n(.*?)\n```/s', $skill, $matches) !== 1) {
        throw new RuntimeException('Missing skill example: ' . $name);
    }
    $examples[$name] = $matches[1];
}

if (!is_dir(__DIR__ . '/config')) {
    mkdir(__DIR__ . '/config');
}
file_put_contents(__DIR__ . '/config/metrics.php', $examples['di']);
file_put_contents(__DIR__ . '/testing.php', "<?php\n\ndeclare(strict_types=1);\n\n" . $examples['testing']);
file_put_contents(__DIR__ . '/config/logger.php', <<<'PHP'
    <?php

    return [\Psr\Log\LoggerInterface::class => \Psr\Log\NullLogger::class];
    PHP);
file_put_contents(__DIR__ . '/config/params.php', <<<'PHP'
    <?php

    return [
        'rasuvaeff/yii3-metrics-prometheus' => [
            'storage' => 'in_memory',
            'namespace' => 'skill',
        ],
    ];
    PHP);
