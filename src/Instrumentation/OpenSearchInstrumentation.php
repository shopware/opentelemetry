<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenSearch\Client;
use OpenSearch\Endpoints\AbstractEndpoint;
use OpenSearch\Namespaces\IndicesNamespace;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use Throwable;

use function OpenTelemetry\Instrumentation\hook;

class OpenSearchInstrumentation
{
    private const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.shopware.opensearch';
    private const TRACE_ATTRIBUTE_SIZE = 'body.size';

    public static function register(): void
    {
        self::instrumentClient();
        self::instrumentIndices();
    }

    private static function instrumentClient(): void
    {
        $methods = ['create', 'update', 'bulk', 'deleteByQuery', 'search', 'msearch'];
        foreach ($methods as $method) {
            hook(
                class: Client::class,
                function: $method,
                pre: static function (
                    Client $client,
                    array $params,
                    string $class,
                    string $function,
                    ?string $filename,
                    ?int $lineno,
                ) {
                    $index = $params[0]['index'] ?? '';
                    if (\is_array($index)) {
                        $index = implode(',', $index);
                    } elseif (!\is_string($index)) {
                        $index = '<unknown>';
                    }

                    $builder = (new CachedInstrumentation(self::INSTRUMENTATION_NAME))
                        ->tracer()
                        ->spanBuilder(sprintf('OpenSearch::%s index: %s', $function, $index))
                        ->setSpanKind(SpanKind::KIND_CLIENT)
                        ->setAttributes(CodeLocationAttributes::from($class, $function, $filename, $lineno));

                    if (isset($params[0]['body'])) {
                        $builder->setAttribute(self::TRACE_ATTRIBUTE_SIZE, strlen(serialize($params[0]['body'])));
                    }

                    $parent = Context::getCurrent();

                    $span = $builder
                        ->setParent($parent)
                        ->startSpan();

                    Context::storage()->attach($span->storeInContext($parent));
                },
                post: static function (Client $client, array $params, ?array $response, ?Throwable $exception) {
                    self::end($exception);
                },
            );
        }
    }

    private static function end(?Throwable $exception): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());
        if ($exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }

        $span->end();
    }

    private static function instrumentIndices(): void
    {
        hook(
            class: IndicesNamespace::class,
            function: 'performRequest',
            pre: static function (
                IndicesNamespace $indices,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) {
                /** @var AbstractEndpoint $endpoint */
                $endpoint = $params[0];

                $builder = (new CachedInstrumentation(self::INSTRUMENTATION_NAME))
                    ->tracer()
                    ->spanBuilder(sprintf('OpenSearch::%s index: %s', get_class($endpoint), $endpoint->getIndex()))
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttributes(CodeLocationAttributes::from($class, $function, $filename, $lineno));

                $parent = Context::getCurrent();

                $span = $builder
                    ->setParent($parent)
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($parent));

            },
            post: static function (IndicesNamespace $indices, array $params, ?array $response, ?Throwable $exception) {
                self::end($exception);
            },
        );

    }

}
