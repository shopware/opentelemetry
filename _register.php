<?php

declare(strict_types=1);

use Shopware\OpenTelemetry\Instrumentation\CommandInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\ConnectionInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\DALInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\HttpClientInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\OpenSearchInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\ShopwareInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\SymfonyInstrumentation;
use OpenTelemetry\SDK\Sdk;
use Shopware\OpenTelemetry\Instrumentation\TwigInstrumentation;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(ShopwareInstrumentation::NAME) === true) {
    return;
}

if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'phpstan') || str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'phpunit')) {
    return;
}

if (!\extension_loaded('opentelemetry')) {
    return;
}

ShopwareInstrumentation::register();
SymfonyInstrumentation::register();
HttpClientInstrumentation::register();
DALInstrumentation::register();
ConnectionInstrumentation::register();
CommandInstrumentation::register();
TwigInstrumentation::register();
if (class_exists('OpenSearch\Client')) {
    OpenSearchInstrumentation::register();
}
