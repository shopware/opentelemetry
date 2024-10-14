<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Unit\Metrics\Transports;

use OpenTelemetry\API\Metrics\MeterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\OpenTelemetry\Feature;
use Shopware\OpenTelemetry\Metrics\MetricNameFormatter;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransportFactory;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMeterProviderFactory;
use OpenTelemetry\API\Metrics\MeterProviderInterface;

#[CoversClass(OpenTelemetryMetricTransportFactory::class)]
#[UsesClass(Feature::class)]
#[UsesClass(OpenTelemetryMetricTransport::class)]
class OpenTelemetryMetricTransportFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        if (!Feature::metricsSupported()) {
            static::markTestSkipped('Installed version of shopware/core does not support metrics');
        }

        $namespace = 'testNamespace';
        $formatter = $this->createMock(MetricNameFormatter::class);
        $meterProviderFactory = $this->createMock(OpenTelemetryMeterProviderFactory::class);
        $meterProvider = $this->createMock(MeterProviderInterface::class);

        $transportConfig = new TransportConfig([
            new MetricConfig(
                name: 'testHistogram',
                description: 'testDescription',
                type: Type::HISTOGRAM,
                enabled: true,
                parameters: ['buckets' => [1, 2, 3]],
            ),
        ]);

        $formatter->expects($this->once())
            ->method('format')
            ->with('testHistogram')
            ->willReturn('testNamespace.testHistogram');

        // checking if buckets are provided to the meterProviderFactory
        $meterProviderFactory->expects($this->once())
            ->method('createMeterProvider')
            ->with(['testNamespace.testHistogram' => [1, 2, 3]])
            ->willReturn($meterProvider);

        $meterProvider->expects($this->once())
            ->method('getMeter')
            ->with($namespace)
            ->willReturn($this->createMock(MeterInterface::class));


        $factory = new OpenTelemetryMetricTransportFactory($namespace, $formatter, $meterProviderFactory);
        $transport = $factory->create($transportConfig);

        $this->assertInstanceOf(OpenTelemetryMetricTransport::class, $transport);
    }
}
