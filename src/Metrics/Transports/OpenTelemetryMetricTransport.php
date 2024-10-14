<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Metrics\Transports;

use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\TelemetryException;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\OpenTelemetry\Metrics\MetricNameFormatter;

readonly class OpenTelemetryMetricTransport implements MetricTransportInterface
{
    private MeterInterface $meter;

    public function __construct(
        // careful: if link to meterProvider is removed, SDK will not transmit metrics (it uses weak references)
        private MeterProviderInterface $meterProvider,
        private MetricNameFormatter $formatter,
        private string $namespace,
    ) {
        $this->meter = $this->meterProvider->getMeter($this->namespace);
    }


    public function emit(Metric $metric): void
    {
        $attributes = $metric->labels;
        $context = null;
        $name = $this->formatter->format($metric->name);

        switch ($metric->type) {
            case Type::UPDOWN_COUNTER:
                $counter = $this->meter->createUpDownCounter($name, $metric->unit, $metric->description);
                $counter->add($metric->value, $attributes, $context);
                break;
            case Type::COUNTER:
                $upDownCounter = $this->meter->createCounter($name, $metric->unit, $metric->description);
                $upDownCounter->add($metric->value, $attributes, $context);
                break;
            case Type::HISTOGRAM:
                $histogram = $this->meter->createHistogram($name, $metric->unit, $metric->description);
                $histogram->record($metric->value, $attributes, $context);
                break;
            case Type::GAUGE:
                $gauge = $this->meter->createGauge($name, $metric->unit, $metric->description);
                $gauge->record($metric->value, $attributes, $context);
                break;
            default:
                throw TelemetryException::metricNotSupported($metric, $this);
        }
    }

    public function forceFlush(): bool
    {
        if ($this->meterProvider instanceof \OpenTelemetry\SDK\Metrics\MeterProviderInterface) {
            return $this->meterProvider->forceFlush();
        }

        return false;
    }
}
