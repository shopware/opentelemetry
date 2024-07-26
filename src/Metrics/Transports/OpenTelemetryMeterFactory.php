<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Metrics\Transports;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\MeterInterface;

/**
 * @internal
 */
class OpenTelemetryMeterFactory
{
    public static function createMeter(string $namespace): MeterInterface
    {
        return Globals::meterProvider()->getMeter($namespace);
    }
}
