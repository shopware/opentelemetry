<?php

declare(strict_types=1);

use Shopware\OpenTelemetry\Instrumentation\ConnectionInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\DALInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\HttpClientInstrumentation;
use Shopware\OpenTelemetry\Instrumentation\SymfonyInstrumentation;
use OpenTelemetry\SDK\Sdk;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(SymfonyInstrumentation::NAME) === true) {
    return;
}

if (str_contains($_SERVER['SCRIPT_NAME'], 'phpstan')) {
    return;
}

if (!extension_loaded('opentelemetry')) {
    return;
}

SymfonyInstrumentation::register();
HttpClientInstrumentation::register();
DALInstrumentation::register();
ConnectionInstrumentation::register();
