<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry;

class Feature
{
    public static function metricsSupported(): bool
    {
        return class_exists('Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric');
    }
}
