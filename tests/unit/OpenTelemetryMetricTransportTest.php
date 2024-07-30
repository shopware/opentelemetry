<?php

namespace Shopware\OpenTelemetry\Tests\Unit;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use OpenTelemetry\Context\ContextInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Counter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Gauge;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Histogram;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\UpDownCounter;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;

class OpenTelemetryMetricTransportTest extends TestCase
{

    public static function getMetrics()
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

    public function testEmitUnsupported()
    {
        $this->expectException(\RuntimeException::class);
        $transport = new OpenTelemetryMetricTransport('test.namespace', $this->createMock(MeterInterface::class));
        $transport->emit($this->createMock(MetricInterface::class));
    }

    #[DataProvider('getMetrics')]
    public function testEmit(MetricInterface $metric, string $name, int|float $value, ?string $description, ?string $unit)
    {
        $namespace = 'com.shopware.opentelemetry';
        [$transport, $meter] = $this->mockTransport($namespace);

        match (true) {
            $metric instanceof UpDownCounter => $this->expectsUpDownCounter($meter, null, $namespace, $name, $value, $description, $unit, []),
            $metric instanceof Counter => $this->expectsCounter($meter, null, $namespace, $name, $value, $description, $unit, []),
            $metric instanceof Histogram => $this->expectsHistogram($meter, null, $namespace, $name, $value, $description, $unit, []),
            $metric instanceof Gauge => $this->expectsGauge($meter, null, $namespace, $name, $value, $description, $unit, []),
        };
        $transport->emit($metric);
    }

    public function testHandleCounter()
    {
        $attributes = ['attribute' => 'value'];
        [$transport, $meter, $context] = $this->mockTransport('test.namespace');
        $this->expectsCounter($meter, $context, 'test.namespace', 'test_counter', 3, 'description', 'unit', $attributes);

        $metric = new Counter('test_counter', 3, 'description', 'unit');
        $transport->handleCounter($metric, $attributes, $context);
    }

    public function testHandleUpDownCounter()
    {
        $attributes = ['hostname' => 'api'];
        [$transport, $meter, $context] = $this->mockTransport('other.namespace');
        $this->expectsUpDownCounter($meter, $context, 'other.namespace', 'test_up_down_counter', -5, 'up down counter with negative value', 'meters', $attributes);

        $metric = new UpDownCounter('test_up_down_counter', -5, 'up down counter with negative value', 'meters');
        $transport->handleUpDownCounter($metric, $attributes, $context);
    }

    public function testHandleHistogram()
    {
        $attributes = [];
        [$transport, $meter, $context] = $this->mockTransport('other.namespace');
        $this->expectsHistogram($meter, $context, 'other.namespace', 'test_histogram', 300, null, null, $attributes);

        $metric = new Histogram('test_histogram', 300);
        $transport->handleHistogram($metric, $attributes, $context);
    }

    public function testHandleGauge()
    {
        $attributes = ['attribute' => 'value'];
        [$transport, $meter, $context] = $this->mockTransport('my.gauge.namespace');
        $this->expectsGauge($meter, $context, 'my.gauge.namespace', 'test_gauge', 42, 'Number of megabytes', 'MB', $attributes);

        $transport->handleGauge(new Gauge('test_gauge', 42, 'Number of megabytes', 'MB'), $attributes, $context);
    }

    private function mockTransport(string $namespace) {
        $meter = $this->createMock(MeterInterface::class);
        $context = $this->createMock(ContextInterface::class);
        $transport = new OpenTelemetryMetricTransport($namespace, $meter);
        return [$transport, $meter, $context];
    }

    private function expectsCounter($meter, $context, $namespace, $metricName, $value, $description, $unit, $attributes)
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

    private function expectsUpDownCounter($meter, $context, $namespace, $metricName, $value, $description, $unit, $attributes)
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

    private function expectsHistogram($meter, $context, $namespace, $metricName, $value, $description, $unit, $attributes)
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

    private function expectsGauge($meter, $context, $namespace, $metricName, $value, $description, $unit, $attributes)
    {
        $gauge = $this->createMock(ObservableGaugeInterface::class);

        $meter->expects(static::once())
            ->method('createObservableGauge')
            ->with(\sprintf("%s.%s", $namespace, $metricName), $unit, $description)
            ->willReturn($gauge);

        $observer = $this->createMock(ObserverInterface::class);

        $gauge->expects(static::once())
            ->method('observe')
            ->with(static::callback(function($callback) use ($observer) {
                $callback($observer);
                return true;
            }));

        $observer->expects(static::once())
            ->method('observe')
            ->with($value, $attributes);
    }
}