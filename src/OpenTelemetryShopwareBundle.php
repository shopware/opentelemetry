<?php

namespace Shopware\OpenTelemetry;

use Shopware\OpenTelemetry\Logging\OpenTelemetryLoggerFactory;
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
            ->addTag('shopware.profiler', ['integration' => 'Otel']);

        if (ContainerBuilder::willBeAvailable('open-telemetry/opentelemetry-logger-monolog', Handler::class, [])) {
            $container
                ->register('monolog.handler.open_telemetry', Handler::class)
                ->setFactory([OpenTelemetryLoggerFactory::class, 'build']);
        }
    }
}
