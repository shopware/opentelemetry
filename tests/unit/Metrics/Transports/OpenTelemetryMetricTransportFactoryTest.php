<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Unit\Metrics\Transports;

use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\OpenTelemetry\Feature;
use Shopware\OpenTelemetry\Metrics\MetricNameFormatter;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransportFactory;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMeterProviderFactory;

#[CoversClass(OpenTelemetryMetricTransportFactory::class)]
#[UsesClass(Feature::class)]
#[UsesClass(MetricNameFormatter::class)]
#[UsesClass(OpenTelemetryMetricTransport::class)]
class OpenTelemetryMetricTransportFactoryTest extends TestCase
{
    /**
     * @var OpenTelemetryMeterProviderFactory&MockObject
     */
    private OpenTelemetryMeterProviderFactory $meterProviderFactory;

    /**
     * @var MeterProviderInterface&MockObject
     */
    private MeterProviderInterface $meterProvider;

    public function setUp(): void
    {
        if (!Feature::metricsSupported()) {
            static::markTestSkipped('Installed version of shopware/core does not support metrics');
        }

        $this->meterProviderFactory = $this->createMock(OpenTelemetryMeterProviderFactory::class);
        $this->meterProvider = $this->createMock(MeterProviderInterface::class);
    }

    public function testCreatePassesPrefixedBucketsOfEnabledHistogramsOnly(): void
    {
        $this->skipIfCoreNamespaceUnsupported();

        $transportConfig = $this->createTransportConfig([
            new MetricConfig(
                name: 'testHistogram',
                description: 'testDescription',
                type: Type::HISTOGRAM,
                enabled: true,
                parameters: ['buckets' => [1, 2, 3]],
            ),
            new MetricConfig(
                name: 'disabledHistogram',
                description: 'testDescription',
                type: Type::HISTOGRAM,
                enabled: false,
                parameters: ['buckets' => [1, 2, 3]],
            ),
            new MetricConfig(
                name: 'testCounter',
                description: 'testDescription',
                type: Type::COUNTER,
                enabled: true,
            ),
        ], 'core.namespace');

        $this->expectMeterProvider(['core.namespace.testHistogram' => [1, 2, 3]]);

        $transport = (new OpenTelemetryMetricTransportFactory($this->meterProviderFactory))->create($transportConfig);

        $this->assertInstanceOf(OpenTelemetryMetricTransport::class, $transport);
    }

    public function testNullCoreNamespaceMeansNoPrefix(): void
    {
        $this->skipIfCoreNamespaceUnsupported();
        $this->expectMeterProvider(['testHistogram' => [1]]);

        (new OpenTelemetryMetricTransportFactory($this->meterProviderFactory))
            ->create($this->createTransportConfig([$this->histogram()], null));
    }

    public function testLegacyNamespaceIsUsedWhenCoreDoesNotPassOne(): void
    {
        if (Feature::metricNamespaceSupported(new TransportConfig([]))) {
            static::markTestSkipped('Installed version of shopware/core passes a namespace to transports');
        }

        $this->expectMeterProvider([OpenTelemetryMetricTransportFactory::LEGACY_NAMESPACE . '.testHistogram' => [1]]);

        (new OpenTelemetryMetricTransportFactory($this->meterProviderFactory))
            ->create(new TransportConfig([$this->histogram()]));
    }

    /**
     * @param array<string, array<int>> $expectedBuckets
     */
    private function expectMeterProvider(array $expectedBuckets): void
    {
        $this->meterProviderFactory->expects($this->once())
            ->method('createMeterProvider')
            ->with($expectedBuckets)
            ->willReturn($this->meterProvider);

        $this->meterProvider->expects($this->once())
            ->method('getMeter')
            ->with(OpenTelemetryMetricTransportFactory::INSTRUMENTATION_SCOPE)
            ->willReturn($this->createMock(MeterInterface::class));
    }

    private function histogram(): MetricConfig
    {
        return new MetricConfig(
            name: 'testHistogram',
            description: 'testDescription',
            type: Type::HISTOGRAM,
            enabled: true,
            parameters: ['buckets' => [1]],
        );
    }

    /**
     * TransportConfig::$namespace exists since shopware/core 6.7.12, so the object is built via reflection
     * to keep this test file loadable (and analysable) with older core versions.
     *
     * @param array<MetricConfig> $metricsConfig
     */
    private function createTransportConfig(array $metricsConfig, ?string $coreNamespace): TransportConfig
    {
        return (new \ReflectionClass(TransportConfig::class))->newInstanceArgs([
            'metricsConfig' => $metricsConfig,
            'namespace' => $coreNamespace,
        ]);
    }

    private function skipIfCoreNamespaceUnsupported(): void
    {
        if (!Feature::metricNamespaceSupported(new TransportConfig([]))) {
            static::markTestSkipped('Installed version of shopware/core does not pass a namespace to transports');
        }
    }
}
