<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Metrics\Transports;

use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\KnownValues;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Metrics\Aggregation\ExplicitBucketHistogramAggregation;
use OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilter\AllExemplarFilter;
use OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilter\NoneExemplarFilter;
use OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilter\WithSampledTraceExemplarFilter;
use OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilterInterface;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricExporter\NoopMetricExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Metrics\StalenessHandler\NoopStalenessHandlerFactory;
use OpenTelemetry\SDK\Metrics\View\CriteriaViewRegistry;
use OpenTelemetry\SDK\Metrics\View\SelectionCriteria\InstrumentNameCriteria;
use OpenTelemetry\SDK\Metrics\View\ViewTemplate;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;

/**
 * @internal
 */
class OpenTelemetryMeterProviderFactory
{
    use LogsMessagesTrait;

    /**
     * This method is required to be able to configure custom buckets for histograms (or other adjustments if needed).
     * It is implemented to the metric provider initialization during OpenTelemetry SDK autoloading.
     *
     * It may be required to update this method when the OpenTelemetry SDK is updated.
     *
     * References:
     *   - https://github.com/open-telemetry/opentelemetry-php/blob/main/src/SDK/SdkAutoloader.php
     *   - https://github.com/open-telemetry/opentelemetry-php/blob/main/src/SDK/Metrics/MeterProviderBuilder.php
     *   - https://github.com/open-telemetry/opentelemetry-php/blob/main/examples/metrics/basic.php
     *
     * @param array<non-empty-string,array<float|int>> $buckets
     */
    public function createMeterProvider(array $buckets): MeterProviderInterface
    {
        if (Sdk::isDisabled()) {
            return new NoopMeterProvider();
        }

        $exporters = Configuration::getList(Variables::OTEL_METRICS_EXPORTER);
        // todo: check sdk update for "The SDK MAY accept a comma-separated list to enable setting multiple exporters"
        if (count($exporters) !== 1) {
            self::logWarning(sprintf('Configuration %s supports exactly 1 exporter, using first from the list', Variables::OTEL_METRICS_EXPORTER));
        }
        $exporterName = $exporters[0];

        try {
            $factory = Registry::metricExporterFactory($exporterName);
            $exporter = $factory->create();
        } catch (\Throwable $t) {
            self::logWarning(sprintf('Unable to create %s meter provider: %s', $exporterName, $t->getMessage()));
            $exporter = new NoopMetricExporter();
        }

        // todo: check sdk update for "The exporter MUST be paired with a periodic exporting MetricReader"
        $reader = new ExportingReader($exporter);

        $resource = ResourceInfoFactory::defaultResource();

        $exemplarFilter = $this->createExemplarFilter(Configuration::getEnum(Variables::OTEL_METRICS_EXEMPLAR_FILTER));

        $views = new CriteriaViewRegistry();
        $this->registerBucketViews($buckets, $views);

        $meterProvider = new MeterProvider(
            null,
            $resource,
            ClockFactory::getDefault(),
            Attributes::factory(),
            new InstrumentationScopeFactory(Attributes::factory()),
            [$reader],
            $views,
            $exemplarFilter,
            new NoopStalenessHandlerFactory(),
        );

        ShutdownHandler::register([$meterProvider, 'shutdown']);

        return $meterProvider;
    }

    /**
     * @param array<non-empty-string, array<float|int>> $buckets
     */
    private function registerBucketViews(array $buckets, CriteriaViewRegistry $registry): void
    {
        foreach ($buckets as $metricName => $customBuckets) {
            $registry->register(
                new InstrumentNameCriteria($metricName),
                ViewTemplate::create()
                    ->withAggregation(new ExplicitBucketHistogramAggregation($customBuckets)),
            );
        }
    }

    private function createExemplarFilter(string $name): ExemplarFilterInterface
    {
        switch ($name) {
            case KnownValues::VALUE_WITH_SAMPLED_TRACE:
                return new WithSampledTraceExemplarFilter();
            case KnownValues::VALUE_ALL:
                return new AllExemplarFilter();
            case KnownValues::VALUE_NONE:
                return new NoneExemplarFilter();
            default:
                self::logWarning('Unknown exemplar filter: ' . $name);

                return new NoneExemplarFilter();
        }
    }
}
