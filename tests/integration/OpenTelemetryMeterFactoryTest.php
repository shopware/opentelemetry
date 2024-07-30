<?php

namespace Shopware\OpenTelemetry\Tests\Integration;

use OpenTelemetry\API\Metrics\MeterInterface;
use PHPUnit\Framework\TestCase;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMeterFactory;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Shopware\OpenTelemetry\OpenTelemetryShopwareBundle;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class OpenTelemetryMeterFactoryTest extends TestCase
{
    public function testCreateMeter()
    {
        $meter = OpenTelemetryMeterFactory::createMeter('test.namespace');
        $this->assertInstanceOf(MeterInterface::class, $meter);
    }
}