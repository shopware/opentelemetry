<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\OpenTelemetry\Feature;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransportFactory;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Shopware\OpenTelemetry\OpenTelemetryShopwareBundle;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @phpstan-import-type Config from OpenTelemetryShopwareBundle
 */
#[CoversClass(OpenTelemetryShopwareBundle::class)]
#[UsesClass(Feature::class)]
class OpenTelemetryShopwareBundleTest extends TestCase
{
    public function testServicesLoadedWhenMetricsEnabled(): void
    {
        $config = ['metrics' => ['enabled' => true]];
        $container = $this->loadBundleWithConfig($config);

        if (Feature::metricsSupported()) {
            $this->assertTrue($container->has(OpenTelemetryMetricTransportFactory::class));
            $definition = $container->getDefinition(OpenTelemetryMetricTransportFactory::class);
            $this->assertTrue($definition->hasTag('shopware.metric_transport_factory'));
        } else {
            $this->assertFalse($container->has(OpenTelemetryMetricTransportFactory::class));
        }
    }

    public function testMetricsAreDisabledByDefault(): void
    {
        $config = $this->processConfiguration([]);

        $this->assertSame(['metrics' => ['enabled' => false]], $config);
    }

    public function testNamespaceOptionIsDeprecatedAndIgnored(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $config = $this->processConfiguration([['metrics' => ['enabled' => true, 'namespace' => 'my.namespace']]]);
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('"open_telemetry_shopware.metrics.namespace" option is ignored', $deprecations[0]);

        // the option is accepted, so existing configuration files keep working
        $this->assertSame(['metrics' => ['enabled' => true, 'namespace' => 'my.namespace']], $config);

        if (Feature::metricsSupported()) {
            $container = $this->loadBundleWithConfig(['metrics' => ['enabled' => true, 'namespace' => 'my.namespace']]);
            $definition = $container->getDefinition(OpenTelemetryMetricTransportFactory::class);
            $this->assertArrayNotHasKey('$namespace', $definition->getArguments());
        }
    }

    /**
     * @param array<array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function processConfiguration(array $configs): array
    {
        $bundle = new OpenTelemetryShopwareBundle();
        $extension = $bundle->getContainerExtension();
        $this->assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        $this->assertNotNull($configuration);

        return (new Processor())->processConfiguration($configuration, $configs);
    }

    public function testServicesNotLoadedWhenMetricsDisabled(): void
    {
        $config = ['metrics' => ['enabled' => false]];
        $container = $this->loadBundleWithConfig($config);

        $this->assertFalse($container->has(OpenTelemetryMetricTransportFactory::class));
    }

    /**
     * @param Config $config
     */
    private function loadBundleWithConfig(array $config): ContainerBuilder
    {
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
