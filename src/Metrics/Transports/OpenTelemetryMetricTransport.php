<?php declare(strict_types=1);

namespace Shopware\OpenTelemetry\Metrics\Transports;

use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\Context\ContextInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Gauge;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\UpDownCounter;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

class OpenTelemetryMetricTransport implements MetricTransportInterface
{
    public function __construct(
        private string $namespace,
        private MeterInterface $meter,
    ) {
    }

    public function emit(MetricInterface $metric): void
    {
        $attributes = $this->prepareAttributes();
        $context = $this->prepareContext();

        match (true) {
            $metric instanceof UpDownCounter => $this->handleUpDownCounter($metric, $attributes, $context),
            $metric instanceof Counter => $this->handleCounter($metric, $attributes, $context),
            $metric instanceof Histogram => $this->handleHistogram($metric, $attributes, $context),
            $metric instanceof Gauge => $this->handleGauge($metric, $attributes, $context),
            default => throw new \RuntimeException('Unsupported metric type'), // todo: replace with logging
        };
    }

    /**
     * @return iterable<non-empty-string, string|bool|float|int>
     */
    private function prepareAttributes(): iterable
    {
        return [];
    }

    private function prepareContext(): ?ContextInterface
    {
        return null; // can use Context::getCurrent() if needed
    }

    private function formatName(string $name): string
    {
        return sprintf('%s.%s', $this->namespace, $name);
    }

    private function getMeter(): MeterInterface
    {
        return $this->meter;
    }

    /**
     * @param iterable<non-empty-string, string|bool|float|int> $attributes
     */
    public function handleCounter(Counter $metric, iterable $attributes, ?ContextInterface $context): void
    {
        $this
            ->getMeter()
            ->createCounter(
                $this->formatName($metric->name),
                $metric->unit,
                $metric->description,
            )->add($metric->value, $attributes, $context);
    }

    /**
     * @param iterable<non-empty-string, string|bool|float|int> $attributes
     */
    public function handleUpDownCounter(UpDownCounter $metric, iterable $attributes, ?ContextInterface $context): void
    {
        $this
            ->getMeter()
            ->createUpDownCounter(
                $this->formatName($metric->name),
                $metric->unit,
                $metric->description,
            )->add($metric->value, $attributes, $context);
    }

    /**
     * @param iterable<non-empty-string, string|bool|float|int> $attributes
     */
    public function handleHistogram(Histogram $metric, iterable $attributes, ?ContextInterface $context): void
    {
        $this
            ->getMeter()
            ->createHistogram(
                $this->formatName($metric->name),
                $metric->unit,
                $metric->description,
            )->record($metric->value, $attributes, $context);
    }

    /**
     * @param iterable<non-empty-string, string|bool|float|int> $attributes
     */
    public function handleGauge(Gauge $metric, iterable $attributes, ?ContextInterface $context): void
    {
        // todo: replace implementation with sync gauge as soon as it's released in SDK
        $this
            ->getMeter()
            ->createObservableGauge(
                $this->formatName($metric->name),
                $metric->unit,
                $metric->description,
            )->observe(function (ObserverInterface $observer) use ($metric, $attributes): void {
                $observer->observe($metric->value, $attributes);
            });
    }
}
