<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Metrics\Transports;

use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\OpenTelemetry\Metrics\MetricNameFormatter;

class OpenTelemetryMetricTransportFactory implements MetricTransportFactoryInterface
{
    public function __construct(
        private readonly string $namespace,
        private MetricNameFormatter $formatter,
        private OpenTelemetryMeterProviderFactory $meterProviderFactory,
    ) {}
    public function create(TransportConfig $transportConfig): MetricTransportInterface
    {
        $buckets = [];
        foreach ($transportConfig->metricsConfig as $metric) {
            if (!$metric->enabled) {
                continue;
            }

            if ($metric->type === Type::HISTOGRAM
                && isset($metric->parameters['buckets'])
                && \is_array($metric->parameters['buckets'])
                && \count($metric->parameters['buckets']) > 0
            ) {
                $metricName = $this->formatter->format($metric->name);

                assert(!empty($metricName));
                $buckets[$metricName] = $metric->parameters['buckets'];
            }
        }

        $meterProvider = $this->meterProviderFactory->createMeterProvider($buckets);

        return new OpenTelemetryMetricTransport($meterProvider, $this->formatter, $this->namespace);
    }
}
