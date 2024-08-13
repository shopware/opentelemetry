<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Unit;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use OpenTelemetry\Context\ContextInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Gauge;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\UpDownCounter;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;

/**
 * @phpstan-import-type Attributes from OpenTelemetryMetricTransport
 */
#[CoversClass(OpenTelemetryMetricTransport::class)]
class OpenTelemetryMetricTransportTest extends TestCase
{
    /**
     * @var MeterInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $meterMock;

    /**
     * @var ContextInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $contextMock;

    public function setUp(): void
    {
        $this->meterMock = $this->createMock(MeterInterface::class);
        $this->contextMock = $this->createMock(ContextInterface::class);
    }

    /**
     * @return array<string, array{metric: MetricInterface, name: string, value: int|float, description: string|null, unit: string|null}>
     */
    public static function getMetrics(): array
    {
        return [
            'Counter' => [
                'metric' => new Counter('cnt', 1, 'desc', 'count'),
                'name' => 'cnt',
                'value' => 1,
                'description' => 'desc',
                'unit' => 'count',
            ],
            'UpDowncounter' => [
                'metric' => new UpDownCounter('udc', 10, 'ud cnt', null),
                'name' => 'udc',
                'value' => 10,
                'description' => 'ud cnt',
                'unit' => null,
            ],
            'Histogram' => [
                'metric' => new Histogram('hist', 100, 'histogram', 'ms'),
                'name' => 'hist',
                'value' => 100,
                'description' => 'histogram',
                'unit' => 'ms',
            ],
            'Gauge' => [
                'metric' => new Gauge('gauge', 11, 'Number of megabytes', 'MB'),
                'name' => 'gauge',
                'value' => 11,
                'description' => 'Number of megabytes',
                'unit' => 'MB',
            ],
        ];
    }

    public function testEmitUnsupported(): void
    {
        $this->expectException(MetricNotSupportedException::class);
        $transport = new OpenTelemetryMetricTransport('test.namespace', $this->createMock(MeterInterface::class));
        $transport->emit($this->createMock(MetricInterface::class));
    }

    #[DataProvider('getMetrics')]
    public function testEmit(MetricInterface $metric, string $name, int|float $value, ?string $description, ?string $unit): void
    {
        $namespace = 'com.shopware.opentelemetry';

        match (true) {
            $metric instanceof UpDownCounter => $this->expectsUpDownCounter($this->meterMock, null, $namespace, $name, $value, $description, $unit, []),
            $metric instanceof Counter => $this->expectsCounter($this->meterMock, null, $namespace, $name, $value, $description, $unit, []),
            $metric instanceof Histogram => $this->expectsHistogram($this->meterMock, null, $namespace, $name, $value, $description, $unit, []),
            $metric instanceof Gauge => $this->expectsGauge($this->meterMock, null, $namespace, $name, $value, $description, $unit, []),
            default => self::fail('Data provider returned unsupported metric type'),
        };

        (new OpenTelemetryMetricTransport($namespace, $this->meterMock))->emit($metric);
    }

    public function testHandleCounter(): void
    {
        $attributes = ['attribute' => 'value'];

        $this->expectsCounter($this->meterMock, $this->contextMock, 'test.namespace', 'test_counter', 3, 'description', 'unit', $attributes);

        $metric = new Counter('test_counter', 3, 'description', 'unit');
        (new OpenTelemetryMetricTransport('test.namespace', $this->meterMock))->handleCounter($metric, $attributes, $this->contextMock);
    }

    public function testHandleUpDownCounter(): void
    {
        $attributes = ['hostname' => 'api'];
        $this->expectsUpDownCounter($this->meterMock, $this->contextMock, 'other.namespace', 'test_up_down_counter', -5, 'up down counter with negative value', 'meters', $attributes);

        $metric = new UpDownCounter('test_up_down_counter', -5, 'up down counter with negative value', 'meters');
        (new OpenTelemetryMetricTransport('other.namespace', $this->meterMock))->handleUpDownCounter($metric, $attributes, $this->contextMock);
    }

    public function testHandleHistogram(): void
    {
        $attributes = [];
        $this->expectsHistogram($this->meterMock, $this->contextMock, 'other.namespace', 'test_histogram', 300, null, null, $attributes);

        $metric = new Histogram('test_histogram', 300);
        (new OpenTelemetryMetricTransport('other.namespace', $this->meterMock))->handleHistogram($metric, $attributes, $this->contextMock);
    }

    public function testHandleGauge(): void
    {
        $attributes = ['attribute' => 'value'];
        $this->expectsGauge($this->meterMock, $this->contextMock, 'my.gauge.namespace', 'test_gauge', 42, 'Number of megabytes', 'MB', $attributes);

        $metric = new Gauge('test_gauge', 42, 'Number of megabytes', 'MB');
        (new OpenTelemetryMetricTransport('my.gauge.namespace', $this->meterMock))->handleGauge($metric, $attributes, $this->contextMock);
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
        $gauge = $this->createMock(ObservableGaugeInterface::class);

        $meter->expects(static::once())
            ->method('createObservableGauge')
            ->with(\sprintf("%s.%s", $namespace, $metricName), $unit, $description)
            ->willReturn($gauge);

        $observer = $this->createMock(ObserverInterface::class);

        $gauge->expects(static::once())
            ->method('observe')
            ->with(static::callback(function ($callback) use ($observer) {
                $callback($observer);
                return true;
            }));

        $observer->expects(static::once())
            ->method('observe')
            ->with($value, $attributes);
    }
}
