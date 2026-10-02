<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Metrics;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;

/**
 * DI-bound metrics entry point. Resolves the active {@see MeterInterface} from the
 * injected {@see MeterProviderInterface} once and delegates to it, so instruments
 * memoized by name stay consistent across calls.
 *
 * Every method takes either the name with its help text and label names, or a
 * definition ({@see CounterDefinition} and siblings) declared once. Given a
 * definition, it returns a `Defined*` instrument that also accepts labels as a
 * plain array and checks their names against the definition.
 *
 * @api
 */
final readonly class MetricRegistry
{
    private MeterInterface $meter;

    public function __construct(MeterProviderInterface $provider)
    {
        $this->meter = $provider->getMeter();
    }

    /**
     * @param CounterDefinition|string $name the metric name, or its whole definition
     * @param list<string> $labelNames
     *
     * @return ($name is CounterDefinition ? DefinedCounter : CounterInterface)
     */
    public function counter(CounterDefinition|string $name, string $help = '', array $labelNames = []): CounterInterface
    {
        if (\is_string($name)) {
            return $this->meter->counter($name, $help, $labelNames);
        }

        self::onlyTheDefinition($name->name, $help, $labelNames);

        return new DefinedCounter($this->meter->counter($name->name, $name->help, $name->labelNames), $name);
    }

    /**
     * @param GaugeDefinition|string $name the metric name, or its whole definition
     * @param list<string> $labelNames
     *
     * @return ($name is GaugeDefinition ? DefinedGauge : GaugeInterface)
     */
    public function gauge(GaugeDefinition|string $name, string $help = '', array $labelNames = []): GaugeInterface
    {
        if (\is_string($name)) {
            return $this->meter->gauge($name, $help, $labelNames);
        }

        self::onlyTheDefinition($name->name, $help, $labelNames);

        return new DefinedGauge($this->meter->gauge($name->name, $name->help, $name->labelNames), $name);
    }

    /**
     * @param UpDownCounterDefinition|string $name the metric name, or its whole definition
     * @param list<string> $labelNames
     *
     * @return ($name is UpDownCounterDefinition ? DefinedUpDownCounter : UpDownCounterInterface)
     */
    public function upDownCounter(UpDownCounterDefinition|string $name, string $help = '', array $labelNames = []): UpDownCounterInterface
    {
        if (\is_string($name)) {
            return $this->meter->upDownCounter($name, $help, $labelNames);
        }

        self::onlyTheDefinition($name->name, $help, $labelNames);

        return new DefinedUpDownCounter($this->meter->upDownCounter($name->name, $name->help, $name->labelNames), $name);
    }

    /**
     * @param HistogramDefinition|string $name the metric name, or its whole definition
     * @param list<string> $labelNames
     * @param list<float> $buckets
     *
     * @return ($name is HistogramDefinition ? DefinedHistogram : HistogramInterface)
     */
    public function histogram(
        HistogramDefinition|string $name,
        string $help = '',
        array $labelNames = [],
        array $buckets = [],
    ): HistogramInterface {
        if (\is_string($name)) {
            return $this->meter->histogram($name, $help, $labelNames, $buckets);
        }

        self::onlyTheDefinition($name->name, $help, $labelNames, $buckets);

        return new DefinedHistogram(
            $this->meter->histogram($name->name, $name->help, $name->labelNames, $name->buckets),
            $name,
        );
    }

    /**
     * A definition is the whole declaration: arguments beside it would be a
     * second, competing one, silently ignored.
     *
     * @param list<string> $labelNames
     * @param list<float> $buckets
     */
    private static function onlyTheDefinition(string $metric, string $help, array $labelNames, array $buckets = []): void
    {
        if ($help !== '' || $labelNames !== [] || $buckets !== []) {
            throw new InvalidArgumentException(\sprintf(
                'Metric "%s" is passed as a definition; help, label names and buckets belong in it',
                $metric,
            ));
        }
    }
}
