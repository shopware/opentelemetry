<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Unit\Metrics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\OpenTelemetry\Metrics\MetricNameFormatter;

#[CoversClass(MetricNameFormatter::class)]
class MetricNameFormatterTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function provideMetricNames(): array
    {
        return [
            'with namespace' => ['testNamespace', 'testMetric', 'testNamespace.testMetric'],
            'empty namespace' => ['', 'testMetric', 'testMetric'],
        ];
    }

    #[DataProvider('provideMetricNames')]
    public function testFormat(string $namespace, string $metricName, string $expectedFormattedName): void
    {
        $formatter = new MetricNameFormatter($namespace);
        $formattedName = $formatter->format($metricName);

        $this->assertSame($expectedFormattedName, $formattedName);
    }
}
