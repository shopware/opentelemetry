<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Logging;

use Monolog\Level;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;

class OpenTelemetryLoggerFactory
{
    public static function build(): Handler
    {
        $loggerProvider = Globals::loggerProvider();

        return new Handler($loggerProvider, Level::Debug);
    }
}
