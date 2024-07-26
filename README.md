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

## Transport metrics to OpenTelemetry

You can enable OpenTelemetry metrics transport with the following configuration:

```yaml
# config/packages/opentelemetry.yaml
open_telemetry_shopware:
  metrics:
    enabled: true
    namespace: 'io.opentelemetry.contrib.php.shopware' # or your custom namespace
```

Please note that OpenTelemetry SDK has to be configured to send metrics to the collector.
It is configured using the same environment variables. Example configuration could look like this:

```bash
OTEL_SERVICE_NAME=shopware
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_METRICS_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4317
OTEL_EXPORTER_OTLP_METRICS_TEMPORALITY_PREFERENCE=delta
```

### Temporality configuration
OpenTelemetry PHP SDK does not support storage for accumulation of metrics. As PHP processes are short-lived, 
it's best to emit metrics with delta temporality and aggregate them on receiving side. Unfortunately at the moment of writing this
OpenTelemetry Collector does not support transforming delta temporality metrics to cumulative. The feature is in the
development, the progress can be tracked [here](https://github.com/open-telemetry/opentelemetry-collector-contrib/issues/30705).

Meanwhile the issue can be handled either by implementing aggregation manually or use metrics backend that
can work with delta temporality. We've successfully tested this with DataDog.

Some links:
- [OpenTelemetry Metrics Data Model](https://opentelemetry.io/docs/specs/otel/metrics/data-model/#metric-points)
- [Temporality easily explained](https://grafana.com/blog/2023/09/26/opentelemetry-metrics-a-guide-to-delta-vs.-cumulative-temporality-trade-offs/)
