# OpenTelemetry for Shopware 6

This is not an official OpenTelemetry project. This repository contains a Shopware-specific implementation using OpenTelemetry standards.

## Requirements

- `ext-opentelemetry` PHP extension
- Optional: `ext-grpc` when using the gRPC exporter

## Installation

```bash
composer require shopware/opentelemetry
```

You also need to install the OpenTelemetry SDK and selected exporter and transport. Below is an example with OTLP over gRPC:

```bash
composer require open-telemetry/sdk
composer require open-telemetry/exporter-otlp
composer require open-telemetry/transport-grpc
```

## Configuration

Enable OpenTelemetry SDK with the following environment variables:

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

Shopware core collects metrics through its telemetry abstraction. This bundle
registers a transport that sends those metrics to OpenTelemetry.

Enable the transport with the following configuration:

```yaml
# config/packages/opentelemetry.yaml
open_telemetry_shopware:
    metrics:
        enabled: true
```

The OpenTelemetry SDK must be configured to send metrics to the collector. It
uses the same environment variables as tracing. Example:

```bash
OTEL_SERVICE_NAME=shopware
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_METRICS_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4317
OTEL_EXPORTER_OTLP_METRICS_TEMPORALITY_PREFERENCE=delta
```

Metrics are only emitted when the `TELEMETRY_METRICS` feature flag is enabled in
Shopware. Since Shopware 6.7.12, `shopware.telemetry.metrics.enabled` must be
`true` as well.

### Metric names

Metric names are prefixed with the namespace configured in Shopware core (`shopware.telemetry.metrics.namespace`). 
Shopware core passes it to transports since version 6.7.12. On older versions the prefix
`io.opentelemetry.contrib.php.shopware` is used.

This bundle has no namespace option of its own. The option `open_telemetry_shopware.metrics.namespace`
from `0.2.0-alpha` is deprecated and ignored. It will be removed in 1.0.

### Flushing

Since Shopware 6.7.12, core calls `flush()` on every transport at the end of each request
and console command, and about every 60 seconds inside a running Messenger worker. This transport
then exports the collected metrics immediately, so metrics from long-running workers
do not wait until the worker stops.

On older Shopware versions the metrics are exported once, when the PHP process shuts down.

The method `OpenTelemetryMetricTransport::forceFlush()` is deprecated. Use `flush()` instead.

### Shopware compatibility

| Shopware version   | Behavior                                                                 |
|--------------------|--------------------------------------------------------------------------|
| below 6.6.7        | No metrics support in core. Tracing and log forwarding still work.        |
| 6.6.7 to 6.7.11    | Metrics are exported when the PHP process shuts down. Metric names are prefixed with `io.opentelemetry.contrib.php.shopware`. |
| 6.7.12 and later   | Metrics are flushed by core (see above). Metric names are prefixed with the namespace from Shopware core. |

The telemetry abstraction in Shopware core is experimental until Shopware 6.8.
This bundle stays at version `0.x` until then.

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
