<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

use function OpenTelemetry\Instrumentation\hook;

final class DALInstrumentation
{
    public static function register(): void
    {
        $methods = [
            'search',
            'aggregate',
            'searchIds',
            'update',
            'upsert',
            'create',
            'delete',
            'createVersion',
            'merge',
            'clone',
        ];

        foreach ($methods as $method) {
            hook(
                EntityRepository::class,
                $method,
                pre: self::pre(),
                post: self::post(),
            );
        }
    }

    /**
     * @return \Closure
     */
    public static function post(): \Closure
    {
        return static function (
            EntityRepository    $repository,
            array               $params,
            mixed               $return,
            ?\Throwable         $exception,
        ) {
            $scope = Context::storage()->scope();
            if (null === $scope) {
                return;
            }
            $scope->detach();
            $span = Span::fromContext($scope->context());

            if ($exception !== null) {
                $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                $span->recordException($exception);
            }

            $span->end();
        };
    }

    /**
     * @return \Closure
     */
    public static function pre(): \Closure
    {
        return static function (
            EntityRepository $repository,
            array            $params,
            string           $class,
            string           $function,
            ?string          $filename,
            ?int             $lineno,
        ) {
            $builder = (new CachedInstrumentation('io.opentelemetry.contrib.php.shopware.dal'))
                ->tracer()
                ->spanBuilder(sprintf('%s::%s', $repository->getDefinition()->getEntityName(), $function))
                ->setSpanKind(SpanKind::KIND_INTERNAL)
                ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

            $parent = Context::getCurrent();

            $span = $builder
                ->setParent($parent)
                ->startSpan();

            Context::storage()->attach($span->storeInContext($parent));
        };
    }
}
