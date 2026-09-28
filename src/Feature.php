<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry;

use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;

class Feature
{
    public static function metricsSupported(): bool
    {
        return class_exists('Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric');
    }

    /**
     * shopware/core >= 6.7.12 passes `shopware.telemetry.metrics.namespace` to transports via TransportConfig.
     * The assertion tells PHPStan that the property exists when analysing against an older core.
     *
     * @phpstan-assert-if-true TransportConfig&object{namespace: string|null} $transportConfig
     */
    public static function metricNamespaceSupported(TransportConfig $transportConfig): bool
    {
        return property_exists($transportConfig, 'namespace');
    }
}
