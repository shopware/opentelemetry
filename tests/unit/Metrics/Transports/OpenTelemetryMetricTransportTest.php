<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Unit\Metrics\Transports;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopware\OpenTelemetry\Feature;
use Shopware\OpenTelemetry\Metrics\MetricNameFormatter;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;

/**
 * @phpstan-type Attributes iterable<non-empty-string, string|bool|float|int>
 */
#[CoversClass(OpenTelemetryMetricTransport::class)]
#[UsesClass(Feature::class)]
#[UsesClass(MetricNameFormatter::class)]
class OpenTelemetryMetricTransportTest extends TestCase
{
    /**
     * @var MeterInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $meterMock;

    /**
     * @var MeterProviderInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $meterProviderMock;

    public function setUp(): void
    {
        if (!Feature::metricsSupported()) {
            static::markTestSkipped('Installed version of shopware/core does not support metrics');
        }

        $this->meterMock = $this->createMock(MeterInterface::class);
        $this->meterProviderMock = $this->createMock(MeterProviderInterface::class);
        $this->meterProviderMock
            ->method('getMeter')
            ->willReturn($this->meterMock);
    }

    private function createTransport(string $namespace): OpenTelemetryMetricTransport
    {
        return new OpenTelemetryMetricTransport($this->meterProviderMock, new MetricNameFormatter($namespace), $namespace);
    }

    /**
     * @return array{0: array{}}|array<string, array{metric: Metric, name: string, value: int|float, description: string|null, unit: string|null, labels: array<non-empty-string, bool|float|int|string>}>
     */
    public static function getMetrics(): array
    {
        if (!Feature::metricsSupported()) {
            return [[]];
        }

        return [
            'Counter' => [
                'metric' => Metric::fromArray([
                    'name' => 'cnt',
                    'type' => Type::COUNTER,
                    'value' => 1,
                    'description' => 'desc',
                    'unit' => 'count',
                ]),
                'name' => 'cnt',
                'value' => 1,
                'description' => 'desc',
                'unit' => 'count',
                'labels' => [],
            ],
            'CounterWithLabels' => [
                'metric' => Metric::fromArray([
                    'name' => 'cnt',
                    'type' => Type::COUNTER,
                    'value' => 1,
                    'description' => 'desc',
                    'unit' => 'count',
                    'labels' => ['myLabel' => 'myValue'],
                ]),
                'name' => 'cnt',
                'value' => 1,
                'description' => 'desc',
                'unit' => 'count',
                'labels' => ['myLabel' => 'myValue'],
            ],
            'UpDowncounter' => [
                'metric' => Metric::fromArray([
                    'name' => 'udc',
                    'type' => Type::UPDOWN_COUNTER,
                    'value' => 10,
                    'description' => 'ud cnt',
                ]),
                'name' => 'udc',
                'value' => 10,
                'description' => 'ud cnt',
                'unit' => null,
                'labels' => [],
            ],
            'Histogram' => [
                'metric' => Metric::fromArray([
                    'name' => 'hist',
                    'type' => Type::HISTOGRAM,
                    'value' => 100,
                    'description' => 'histogram',
                    'unit' => 'ms',
                ]),
                'name' => 'hist',
                'value' => 100,
                'description' => 'histogram',
                'unit' => 'ms',
                'labels' => [],
            ],
            'Gauge' => [
                'metric' => Metric::fromArray([
                    'name' => 'gauge',
                    'type' => Type::GAUGE,
                    'value' => 11,
                    'description' => 'Number of megabytes',
                    'unit' => 'MB',
                    'labels' => [],
                ]),
                'name' => 'gauge',
                'value' => 11,
                'description' => 'Number of megabytes',
                'unit' => 'MB',
                'labels' => [],
            ],
        ];
    }

    /**
     * @param array<non-empty-string, bool|float|int|string> $labels
     */
    #[DataProvider('getMetrics')]
    public function testEmit(Metric $metric, string $name, int|float $value, ?string $description, ?string $unit, array $labels): void
    {
        $namespace = 'com.shopware.opentelemetry';

        match ($metric->type) {
            Type::UPDOWN_COUNTER => $this->expectsUpDownCounter($this->meterMock, null, $namespace, $name, $value, $description, $unit, $labels),
            Type::COUNTER => $this->expectsCounter($this->meterMock, null, $namespace, $name, $value, $description, $unit, $labels),
            Type::HISTOGRAM => $this->expectsHistogram($this->meterMock, null, $namespace, $name, $value, $description, $unit, $labels),
            Type::GAUGE => $this->expectsGauge($this->meterMock, null, $namespace, $name, $value, $description, $unit, $labels),
        };

        $this->createTransport($namespace)->emit($metric);
    }

    public function testForceFlush(): void
    {
        $meterProviderMock = $this->createMock(\OpenTelemetry\SDK\Metrics\MeterProviderInterface::class);
        $meterProviderMock->expects(static::once())
            ->method('forceFlush')->willReturn(true);
        $transport = (new OpenTelemetryMetricTransport($meterProviderMock, new MetricNameFormatter('namespace'), 'namespace'));
        static::assertTrue($transport->forceFlush());
    }

    /**
     * @param Attributes $attributes
     */
    private function expectsCounter(MockObject $meter, ?MockObject $context, string $namespace, string $metricName, float|int $value, ?string $description, ?string $unit, iterable $attributes): void
    {
        $counter = $this->createMock(CounterInterface::class);

        $meter->expects(static::once())
            ->method('createCounter')
            ->with(\sprintf("%s.%s", $namespace, $metricName), $unit, $description)
            ->willReturn($counter);

        $counter->expects(static::once())
            ->method('add')
            ->with($value, $attributes, $context);
    }

    /**
     * @param Attributes $attributes
     */
    private function expectsUpDownCounter(MockObject $meter, ?MockObject $context, string $namespace, string $metricName, float|int $value, ?string $description, ?string $unit, iterable $attributes): void
    {
        $upDownCounter = $this->createMock(UpDownCounterInterface::class);

        $meter->expects(static::once())
            ->method('createUpDownCounter')
            ->with(\sprintf("%s.%s", $namespace, $metricName), $unit, $description)
            ->willReturn($upDownCounter);

        $upDownCounter->expects(static::once())
            ->method('add')
            ->with($value, $attributes, $context);

    }

    /**
     * @param Attributes $attributes
     */
    private function expectsHistogram(MockObject $meter, ?MockObject $context, string $namespace, string $metricName, float|int $value, ?string $description, ?string $unit, iterable $attributes): void
    {
        $histogram = $this->createMock(HistogramInterface::class);

        $meter->expects(static::once())
            ->method('createHistogram')
            ->with(\sprintf("%s.%s", $namespace, $metricName), $unit, $description)
            ->willReturn($histogram);

        $histogram->expects(static::once())
            ->method('record')
            ->with($value, $attributes, $context);

    }

    /**
     * @param Attributes $attributes
     */
    private function expectsGauge(MockObject $meter, ?MockObject $context, string $namespace, string $metricName, float|int $value, ?string $description, ?string $unit, iterable $attributes): void
    {
        $gauge = $this->createMock(GaugeInterface::class);

        $meter->expects(static::once())
            ->method('createGauge')
            ->with(\sprintf("%s.%s", $namespace, $metricName), $unit, $description)
            ->willReturn($gauge);

        $gauge->expects(static::once())
            ->method('record')
            ->with($value, $attributes, $context);

    }
}
