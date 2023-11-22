<?php

namespace Frosh\OpenTelemetry;

use Frosh\OpenTelemetry\Logging\OpenTelemetryLoggerFactory;
use Frosh\OpenTelemetry\Profiler\OtelProfiler;
use OpenTelemetry\API\Globals;
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

        $container
            ->register('monolog.handler.open_telemetry', Handler::class)
            ->setFactory([OpenTelemetryLoggerFactory::class, 'build']);
    }
}
