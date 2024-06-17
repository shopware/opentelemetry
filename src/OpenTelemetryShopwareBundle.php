<?php

namespace Shopware\OpenTelemetry;

use OpenTelemetry\API\Globals;
use Shopware\OpenTelemetry\Logging\OpenTelemetryLoggerFactory;
use Shopware\OpenTelemetry\Messenger\MessageBusSubscriber;
use Shopware\OpenTelemetry\Profiler\OtelProfiler;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class OpenTelemetryShopwareBundle extends Bundle
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
}
