<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Metrics\Transports;

use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\OpenTelemetry\Feature;
use Shopware\OpenTelemetry\Metrics\MetricNameFormatter;

class OpenTelemetryMetricTransportFactory implements MetricTransportFactoryInterface
{
    /**
     * Name of the OpenTelemetry instrumentation scope that owns the metrics.
     * Same convention as the tracing instrumentations shipped in this bundle.
     */
    public const INSTRUMENTATION_SCOPE = 'io.opentelemetry.contrib.php.shopware';

    /**
     * Metric name prefix used with shopware/core < 6.7.12, where core does not pass a namespace to transports.
     * Intentionally equal to the old default prefix name to limit prefix changes on updates at least for
     * installations with default values.
     */
    public const LEGACY_NAMESPACE = 'io.opentelemetry.contrib.php.shopware';

    public function __construct(
        private readonly OpenTelemetryMeterProviderFactory $meterProviderFactory,
    ) {}

    public function create(TransportConfig $transportConfig): MetricTransportInterface
    {
        $formatter = new MetricNameFormatter($this->resolveNamespace($transportConfig));

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
                $metricName = $formatter->format($metric->name);

                assert(!empty($metricName));
                $buckets[$metricName] = $metric->parameters['buckets'];
            }
        }

        $meterProvider = $this->meterProviderFactory->createMeterProvider($buckets);

        return new OpenTelemetryMetricTransport($meterProvider, $formatter, self::INSTRUMENTATION_SCOPE);
    }

    /**
     * The metric name prefix is `shopware.telemetry.metrics.namespace`, passed by Shopware core since 6.7.12.
     * A null value from core means "no prefix". Older core versions do not pass a namespace at all,
     * they get self::LEGACY_NAMESPACE.
     */
    private function resolveNamespace(TransportConfig $transportConfig): string
    {
        if (!Feature::metricNamespaceSupported($transportConfig)) {
            return self::LEGACY_NAMESPACE;
        }

        return \is_string($transportConfig->namespace) ? $transportConfig->namespace : '';
    }
}
