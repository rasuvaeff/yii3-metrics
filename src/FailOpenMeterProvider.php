<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Psr\Log\LoggerInterface;

/**
 * Drops backend failures for a short cooldown so optional metrics cannot break
 * the application path. Validation failures from the core are always rethrown.
 *
 * @api
 */
final class FailOpenMeterProvider implements MeterProviderInterface
{
    private float $retryAt = 0.0;

    public function __construct(
        private readonly MeterProviderInterface $inner,
        private readonly LoggerInterface $logger,
        private readonly float $cooldownSeconds = 30.0,
    ) {
        if ($cooldownSeconds < 0.0 || !is_finite($cooldownSeconds)) {
            throw new Exception\InvalidArgumentException('Cooldown must be finite and non-negative');
        }
    }

    #[\Override]
    public function getMeter(?string $name = null): MeterInterface
    {
        return new FailOpenMeter($this->inner->getMeter($name), $this);
    }

    public function run(string $metric, callable $write): void
    {
        $now = microtime(as_float: true);

        if ($now < $this->retryAt) {
            return;
        }

        try {
            $write();
        } catch (\InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->retryAt = $now + $this->cooldownSeconds;

            $this->logger->warning('Metrics backend failed; writes are temporarily dropped', [
                'metric' => $metric,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
