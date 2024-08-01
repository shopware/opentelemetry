# OpenTelemetry for Shopware 6

## Requirements

- `ext-opentelemetry` PHP extension
- Optional: `ext-grpc` when using the gRPC exporter

## Installation

```bash
composer require shopware/opentelemetry
```

## Configuration

Enable open telemetry with the following environment variables:

```bash
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=shopware # or any other name
```

This extension can be disabled via:
```bash
OTEL_PHP_DISABLED_INSTRUMENTATIONS=shopware
```

You will need to configure the exporter to send the data to a collector. 

Here is an example with OTLP over gRPC:

```bash
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4317
```

You will need to install also composer packages: `open-telemetry/transport-grpc` and `open-telemetry/exporter-otlp`.

### Enabling Shopware custom tracing

To enable tracing for Shopware, you need to add the following config:

```yaml
# config/packages/opentelemetry.yaml

shopware:
    profiler:
        integrations:
            - OpenTelemetry
```

## Adding custom spans

**This spans are working with all profilers (Symfony Profiler bar, Tideways, ...) and are not exclusive to OpenTelemetry.**

```php
use Shopware\Core\Profiling\Profiler;

$value = Profiler::trace('<name>', function () {
    return $myFunction();
});
```

## Forward logs to OpenTelemetry

You can forward logs to OpenTelemetry with the following configuration:

```yaml
# config/packages/opentelemetry.yaml

monolog:
    handlers:
        main:
            type: service
            id: monolog.handler.open_telemetry
        elasticsearch:
            type: service
            id: monolog.handler.open_telemetry
```
