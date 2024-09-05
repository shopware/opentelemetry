<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Integration\Metrics\Transports;

use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMeterProviderFactory;
use Zalas\PHPUnit\Globals\Attribute\Putenv;

#[CoversClass(OpenTelemetryMeterProviderFactory::class)]
class OpenTelemetryMeterProviderFactoryTest extends TestCase
{
    #[Putenv('OTEL_SDK_DISABLED', 'true')]
    public function testSdkDisabledNoOpMeter(): void
    {
        $meterProvider = (new OpenTelemetryMeterProviderFactory())->createMeterProvider([]);
        $this->assertInstanceOf(NoopMeterProvider::class, $meterProvider);
    }

    #[Putenv('OTEL_SDK_DISABLED', 'false')]
    #[Putenv('OTEL_METRICS_EXPORTER', 'memory')]
    public function testCreateMeterProvider(): void
    {
        $meterProvider = (new OpenTelemetryMeterProviderFactory())->createMeterProvider([]);
        $this->assertInstanceOf(MeterProvider::class, $meterProvider);
    }
}
