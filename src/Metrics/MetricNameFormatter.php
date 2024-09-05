<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Metrics;

readonly class MetricNameFormatter
{
    public function __construct(
        private string $namespace,
    ) {}

    public function format(string $metricName): string
    {
        if ($this->namespace === '') {
            return $metricName;
        }
        return \sprintf('%s.%s', $this->namespace, $metricName);
    }
}
