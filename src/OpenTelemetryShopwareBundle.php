<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry;

use Shopware\OpenTelemetry\Logging\OpenTelemetryLoggerFactory;
use Shopware\OpenTelemetry\Messenger\MessageBusSubscriber;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMeterProviderFactory;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransportFactory;
use Shopware\OpenTelemetry\Profiler\OtelProfiler;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @phpstan-type Config array{metrics: array{enabled: bool, namespace?: mixed}}
 */
class OpenTelemetryShopwareBundle extends AbstractBundle
{
    protected string $extensionAlias = 'open_telemetry_shopware';

    public function build(ContainerBuilder $container)
    {
        $container
            ->register(OtelProfiler::class)
            ->addTag('shopware.profiler', ['integration' => 'OpenTelemetry']);

        $container
            ->register(MessageBusSubscriber::class)
            ->addTag('kernel.event_subscriber');

        if (ContainerBuilder::willBeAvailable('open-telemetry/opentelemetry-logger-monolog', Handler::class, [])) {
            $container
                ->register('monolog.handler.open_telemetry', Handler::class)
                ->setFactory([OpenTelemetryLoggerFactory::class, 'build']);
        }

    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode() // @phpstan-ignore class.notFound
            ->children()
                ->arrayNode('metrics')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                        ->end()
                        // kept without a default so that only installations that still set it get the deprecation
                        ->scalarNode('namespace')
                            ->setDeprecated(
                                'shopware/opentelemetry',
                                '0.2.0',
                                'The "%path%.%node%" option is ignored and will be removed in 1.0. Replace by "shopware.telemetry.metrics.namespace" instead (Shopware 6.7.12+).',
                            )
                        ->end()
                    ->end()
                ->end() // metrics
            ->end()
        ;
    }

    /**
     * @param Config $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!Feature::metricsSupported()) {
            return;
        }
        if ($config['metrics']['enabled']) {
            $container->services()
                ->set(OpenTelemetryMeterProviderFactory::class);

            $container->services()
                ->set(OpenTelemetryMetricTransportFactory::class)
                ->arg('$meterProviderFactory', service(OpenTelemetryMeterProviderFactory::class))
                ->tag('shopware.metric_transport_factory');
        }
    }
}
