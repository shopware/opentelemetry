<?php

declare(strict_types=1);

use Frosh\OpenTelemetry\Instrumentation\DALInstrumentation;
use Frosh\OpenTelemetry\Instrumentation\HttpClientInstrumentation;
use Frosh\OpenTelemetry\Instrumentation\SymfonyInstrumentation;
use OpenTelemetry\SDK\Sdk;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(SymfonyInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Symfony auto-instrumentation', E_USER_WARNING);

    return;
}

SymfonyInstrumentation::register();
HttpClientInstrumentation::register();
DALInstrumentation::register();
