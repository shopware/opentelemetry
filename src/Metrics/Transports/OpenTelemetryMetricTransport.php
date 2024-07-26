<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Metrics\Transports;

use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\Context\ContextInterface;
use Shopware\Core\Framework\Telemetry\TelemetryException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Gauge;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\UpDownCounter;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @phpstan-type Attributes iterable<non-empty-string, string|bool|float|int>
 */
class OpenTelemetryMetricTransport implements MetricTransportInterface
{
    public function __construct(
        private string $namespace,
        private MeterInterface $meter,
    ) {}

    public function emit(MetricInterface $metric): void
    {
        $attributes = [];
        $context = null;

        match (true) {
            $metric instanceof UpDownCounter => $this->handleUpDownCounter($metric, $attributes, $context),
            $metric instanceof Counter => $this->handleCounter($metric, $attributes, $context),
            $metric instanceof Histogram => $this->handleHistogram($metric, $attributes, $context),
            $metric instanceof Gauge => $this->handleGauge($metric, $attributes, $context),
            default => throw TelemetryException::metricNotSupported($metric, $this),
        };
    }

    private function formatName(string $name): string
    {
        return sprintf('%s.%s', $this->namespace, $name);
    }

    /**
     * @param Attributes $attributes
     */
    public function handleCounter(Counter $metric, iterable $attributes, ?ContextInterface $context): void
    {
        $this->meter
            ->createCounter(
                $this->formatName($metric->name),
                $metric->unit,
                $metric->description,
            )->add($metric->value, $attributes, $context);
    }

    /**
     * @param Attributes $attributes
     */
    public function handleUpDownCounter(UpDownCounter $metric, iterable $attributes, ?ContextInterface $context): void
    {
        $this->meter
            ->createUpDownCounter(
                $this->formatName($metric->name),
                $metric->unit,
                $metric->description,
            )->add($metric->value, $attributes, $context);
    }

    /**
     * @param Attributes $attributes
     */
    public function handleHistogram(Histogram $metric, iterable $attributes, ?ContextInterface $context): void
    {
        $this->meter
            ->createHistogram(
                $this->formatName($metric->name),
                $metric->unit,
                $metric->description,
            )->record($metric->value, $attributes, $context);
    }

    /**
     * @param Attributes $attributes
     */
    public function handleGauge(Gauge $metric, iterable $attributes, ?ContextInterface $context): void
    {
        // todo: replace implementation with sync gauge as soon as it's released in SDK
        $this->meter
            ->createObservableGauge(
                $this->formatName($metric->name),
                $metric->unit,
                $metric->description,
            )->observe(
                fn(ObserverInterface $observer) => $observer->observe($metric->value, $attributes),
            );
    }
}
