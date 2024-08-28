# Shopware OpenTelemetry configuration

This document explains how to install and configure OpenTelemetry for Shopware to collect and transport traces, metrics, and logs. We will use OTLP over gRPC as the transport protocol.

## Installation

1. Install dependencies

    ```shell
    composer require open-telemetry/sdk open-telemetry/exporter-otlp open-telemetry/transport-grpc
    composer require open-telemetry/opentelemetry-logger-monolog # if logs forwarding is needed
    composer require shopware/opentelemetry
    ```

   To use the latest version of the shopware/opentelemetry package, you can modify the corresponding line in composer.json to:
    ```json
    "shopware/opentelemetry": "dev-main as 1.0.0"
    ```

2. Create the config file config/packages/opentelemetry.yaml with the following content:

    ```yaml
    # metrics
    open_telemetry_shopware:
        metrics:
            enabled: true
            namespace: 'my.prefix.for.metrics'

    # traces
    shopware:
        profiler:
            integrations:
                - OpenTelemetry

    # logs forwarding
    monolog:
        handlers:
            main:
                type: service
                id: monolog.handler.open_telemetry
            elasticsearch:
                type: service
                id: monolog.handler.open_telemetry
    ```

3. Enable OpenTelemetry bundle
    Add the following line to the list of bundles in the config/bundles.php file:
    ```php
    Shopware\OpenTelemetry\OpenTelemetryShopwareBundle::class => ['all' => true],
    ```

4. Set environment variables:

    ```shell
    # OpenTelemetry
    OTEL_PHP_AUTOLOAD_ENABLED="true"

    # Export configuration
    OTEL_TRACES_EXPORTER="otlp"
    OTEL_METRICS_EXPORTER="otlp"
    OTEL_LOGS_EXPORTER="otlp"
    OTEL_EXPORTER_OTLP_PROTOCOL="grpc"
    OTEL_EXPORTER_OTLP_ENDPOINT="http://localhost:4317" # otlp receiver endpoint
    OTEL_EXPORTER_OTLP_METRICS_TEMPORALITY_PREFERENCE="delta"

    # Attributes detectors
    OTEL_PHP_DETECTORS="env"
    OTEL_SERVICE_NAME="shopware" # or any other name

    # Shopware feature toggle
    TELEMETRY_METRICS="true"
    ```

4. Configure OpenTelemetry receiver

    This highly depends on the backend you plan to use. Example configurations for the OpenTelemetry Collector or Datadog agent can be found in `docker/` subfolder of this repository.
    We recommend using the OpenTelemetry Collector for greater flexibility, as it supports telemetry processing and integration with many backends.

## Advanced configuration

Here some non-obvious configuration options will be explained.

### Attributes (labels)
OpenTelemetry SDK has a number of classes in `OpenTelemetry\SDK\Resource\Detectors` namespace, that allow automatically detect attributes and attach them to collected telemetry.

They can be enabled/disabled using environment variable `OTEL_PHP_DETECTORS`

At the moment of writing next values are supported (check [SDK KnownValues interface](https://github.com/open-telemetry/opentelemetry-php/blob/main/src/SDK/Common/Configuration/KnownValues.php)):
- `env`
- `host`
- `os`
- `process`
- `process_runtime`
- `sdk`
- `sdk_provided`
- `composer`
- `none`
- `all`

It's also possible to specify a list of needed collectors separated by comma:
```
OTEL_PHP_DETECTORS=env,host
```
Detector `env`, when enabled, allows to provide any custom attributes using environment variable:
```shell
OTEL_RESOURCE_ATTRIBUTES=foo=valuefoo,bar=valuebar
```

Be cautious when enabling all detectors, as in large setups, they can significantly increase metric cardinality and associated costs.

## Integrations

Configuring OpenTelemetry with different backends can be non-obvious. In this section we will a few hopefully useful tips.

### Datadog

It's possible to export telemetry directly to DataDog or through OpenTelemetry Collector using datadogexporter. Quite often configuration options are the same for both of this approaches, see [Datatadog Agent configuration options list](https://github.com/DataDog/datadog-agent/blob/main/pkg/config/config_template.yaml)

#### Metric labels
By default, Datadog allows only a limited number of resource attributes to be used as metric tags and automatically remaps them: https://docs.datadoghq.com/opentelemetry/schema_semantics/semantic_mapping/?tab=datadogagent

To allow all resource attributes to be converted to metrics tags:

For datadog agent - set environment variable:
```
DD_OTLP_CONFIG_METRICS_RESOURCE_ATTRIBUTES_AS_TAGS=true
```

For the [OpenTelemetry Collector](https://github.com/open-telemetry/opentelemetry-collector-contrib/blob/ee7be8bbf1b8b2820d28468cdaaff768a0579b08/exporter/datadogexporter/examples/collector.yaml#L267C9-L267C36), add the following lines to the configuration:
    
```yaml
exporters:
  datadog:
    metrics:
      resource_attributes_as_tags: true
```


#### OpenTelemetry histograms as heatmaps
To be able to render OpenTelemetry histograms as heatmaps in datagog follow this [tutorial](https://docs.datadoghq.com/opentelemetry/guide/otlp_histogram_heatmaps/?tab=datadogagent#datadog-exporter-or-datadog-agent-configuration).
