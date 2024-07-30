<?php

namespace Shopware\OpenTelemetry\Tests\Integration;

use OpenTelemetry\API\Metrics\MeterInterface;
use PHPUnit\Framework\TestCase;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Shopware\OpenTelemetry\OpenTelemetryShopwareBundle;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class OpenTelemetryShopwareBundleTest extends TestCase
{
    public function testServicesLoadedWhenMetricsEnabled()
    {
        $config = ['metrics' => ['enabled' => true, 'namespace' => 'io.opentelemetry.contrib.php.shopware']];
        $container = $this->loadBundleWithConfig($config);

        $this->assertTrue($container->has(MeterInterface::class));
        $this->assertTrue($container->has(OpenTelemetryMetricTransport::class));
    }

    public function testServicesNotLoadedWhenMetricsDisabled()
    {
        $config = ['metrics' => ['enabled' => false, 'namespace' => 'io.opentelemetry.contrib.php.shopware']];
        $container = $this->loadBundleWithConfig($config);

        $this->assertFalse($container->has(MeterInterface::class));
        $this->assertFalse($container->has(OpenTelemetryMetricTransport::class));
    }

    private function loadBundleWithConfig(array $config): ContainerBuilder {
        $bundle = new OpenTelemetryShopwareBundle();
        $builder = new ContainerBuilder();
        $fileLocator = new FileLocator(__DIR__);
        $phpFileLoader = new PhpFileLoader($builder, $fileLocator);
        $instanceof = [];
        $configurator = new ContainerConfigurator($builder, $phpFileLoader, $instanceof, __DIR__, __DIR__);

        $bundle->loadExtension($config, $configurator, $builder);

        return $builder;
    }
}