<?php

namespace Shopware\OpenTelemetry\Tests\Integration;

use OpenTelemetry\API\Metrics\MeterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\OpenTelemetry\Metrics\Transports\OpenTelemetryMeterFactory;

#[CoversClass(OpenTelemetryMeterFactory::class)]
class OpenTelemetryMeterFactoryTest extends TestCase
{
    public function testCreateMeter(): void
    {
        $meter = OpenTelemetryMeterFactory::createMeter('test.namespace');
        $this->assertInstanceOf(MeterInterface::class, $meter);
    }
}
