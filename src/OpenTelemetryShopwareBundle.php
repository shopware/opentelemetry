<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry;

use OpenTelemetry\API\Metrics\MeterInterface;
use Shopware\OpenTelemetry\Logging\OpenTelemetryLoggerFactory;
use Shopware\OpenTelemetry\Messenger\MessageBusSubscriber;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMeterFactory;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMetricTransport;
use Shopware\OpenTelemetry\Profiler\OtelProfiler;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @phpstan-type Config array{metrics: array{enabled: bool, namespace: string}}
 */
class OpenTelemetryShopwareBundle extends AbstractBundle
{
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
        $rootNode = $definition->rootNode();
        \assert($rootNode instanceof ArrayNodeDefinition);

        $rootNode
            ->children()
                ->arrayNode('metrics')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('namespace')
                            ->defaultValue('io.opentelemetry.contrib.php.shopware')
                        ->end()
                    ->end()
                ->end()
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
        $metricsConfig = $config['metrics'];
        $namespace = $metricsConfig['namespace'];

        if ($metricsConfig['enabled']) {
            $container->services()
                ->set(MeterInterface::class)
                ->factory([OpenTelemetryMeterFactory::class, 'createMeter'])
                ->arg('$namespace', $namespace);

            $container->services()
                ->set(OpenTelemetryMetricTransport::class)
                ->arg('$meter', service(MeterInterface::class))
                ->arg('$namespace', $namespace)
                ->tag('shopware.metric_transport');
        }
    }
}
