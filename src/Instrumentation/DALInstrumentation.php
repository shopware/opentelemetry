<?php

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use function OpenTelemetry\Instrumentation\hook;

final class DALInstrumentation
{
    public static function register(): void
    {
        hook(
            EntityRepository::class,
            'search',
            pre: static function (
                EntityRepository $repository,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) {
                $parent = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                $builder = (new CachedInstrumentation('io.opentelemetry.contrib.php.shopware'))
                    ->tracer()
                    ->spanBuilder($repository->getDefinition()->getEntityName() . '::search')
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $parent[2]['function'])
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $parent[2]['class'])
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $parent[2]['file'])
                    ->setAttribute(TraceAttributes::CODE_LINENO, $parent[2]['line']);

                $parent = Context::getCurrent();

                $span = $builder
                    ->setParent($parent)
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                EntityRepository $repository,
                array $params,
                ?EntitySearchResult $response,
                ?\Throwable $exception
            ) {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }
                $scope->detach();
                $span = Span::fromContext($scope->context());

                if ($exception) {
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                }

                $span->end();
            }
        );

        hook(
            EntityRepository::class,
            'aggregate',
            pre: static function (
                EntityRepository $repository,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) use($instrumentation) {
                $parent = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                $builder = $instrumentation
                    ->tracer()
                    ->spanBuilder($repository->getDefinition()->getEntityName() . '::aggregate')
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $parent[2]['function'])
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $parent[2]['class'])
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $parent[2]['file'])
                    ->setAttribute(TraceAttributes::CODE_LINENO, $parent[2]['line']);

                $parent = Context::getCurrent();

                $span = $builder
                    ->setParent($parent)
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                EntityRepository $repository,
                array $params,
                ?AggregationResultCollection $response,
                ?\Throwable $exception
            ) {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }
                $scope->detach();
                $span = Span::fromContext($scope->context());

                if ($exception) {
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                }

                $span->end();
            }
        );
    }
}
