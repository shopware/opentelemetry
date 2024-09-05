<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Integration\Metrics\Transports;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\OpenTelemetry\Feature;
use Shopware\OpenTelemetry\Metrics\MetricNameFormatter;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMeterProviderFactory;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransportFactory;
use Zalas\PHPUnit\Globals\Attribute\Putenv;

#[CoversClass(OpenTelemetryMeterProviderFactory::class)]
#[CoversClass(OpenTelemetryMetricTransportFactory::class)]
#[CoversClass(OpenTelemetryMetricTransport::class)]
class OpenTelemetryTransportWorkflowTest extends TestCase
{
    #[Putenv('OTEL_SDK_DISABLED', 'false')]
    #[Putenv('OTEL_METRICS_EXPORTER', 'console')]
    public function testEmit(): void
    {
        if (!Feature::metricsSupported()) {
            static::markTestSkipped('Installed version of shopware/core does not support metrics');
        }

        $namespace = 'test.namespace';

        $nameFormatter = new MetricNameFormatter($namespace);
        $providerFactory = new OpenTelemetryMeterProviderFactory();

        $transportFactory = new OpenTelemetryMetricTransportFactory($namespace, $nameFormatter, $providerFactory);
        $transportConfig = new TransportConfig([
            new MetricConfig(
                name: 'testHistogram',
                description: 'test histogram description',
                type: Type::HISTOGRAM,
                enabled: true,
                parameters: ['buckets' => [5, 10]],
            ),
        ]);
        $transport = $transportFactory->create($transportConfig);
        $this->assertInstanceOf(OpenTelemetryMetricTransport::class, $transport);

        $transport->emit(
            new Metric(
                new ConfiguredMetric('testHistogram', 7, ['myLabel' => 'label']),
                Type::HISTOGRAM,
                'description',
                'unit',
            ),
        );


        $transport->forceFlush();

        $output = $this->getActualOutputForAssertion();
        $data = json_decode($output, true);
        $this->assertIsArray($data);

        // Validate scope.name
        $this->assertArrayHasKey('name', $data['scope']);
        $this->assertEquals('test.namespace', $data['scope']['name']);

        // Validate metrics[0].name, description, and unit
        $this->assertArrayHasKey('metrics', $data['scope']);
        $this->assertArrayHasKey(0, $data['scope']['metrics']);
        $this->assertArrayHasKey('name', $data['scope']['metrics'][0]);
        $this->assertEquals('test.namespace.testHistogram', $data['scope']['metrics'][0]['name']);
        $this->assertArrayHasKey('description', $data['scope']['metrics'][0]);
        $this->assertEquals('description', $data['scope']['metrics'][0]['description']);
        $this->assertArrayHasKey('unit', $data['scope']['metrics'][0]);
        $this->assertEquals('unit', $data['scope']['metrics'][0]['unit']);

        // Validate metrics[0].data.dataPoints[0].bucketCounts and explicitBounds
        $this->assertArrayHasKey('data', $data['scope']['metrics'][0]);
        $this->assertArrayHasKey('dataPoints', $data['scope']['metrics'][0]['data']);
        $this->assertArrayHasKey(0, $data['scope']['metrics'][0]['data']['dataPoints']);
        $this->assertArrayHasKey('bucketCounts', $data['scope']['metrics'][0]['data']['dataPoints'][0]);
        $this->assertEquals([0, 1, 0], $data['scope']['metrics'][0]['data']['dataPoints'][0]['bucketCounts']);
        $this->assertArrayHasKey('explicitBounds', $data['scope']['metrics'][0]['data']['dataPoints'][0]);
        $this->assertEquals([5, 10], $data['scope']['metrics'][0]['data']['dataPoints'][0]['explicitBounds']);
    }
}
