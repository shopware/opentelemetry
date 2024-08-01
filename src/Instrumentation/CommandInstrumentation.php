<?php

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use Opentelemetry\Proto\Trace\V1\Span\SpanKind;
use Opentelemetry\Proto\Trace\V1\Status\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\Console\Application;
use Throwable;

use function OpenTelemetry\Instrumentation\hook;

class CommandInstrumentation
{
    public static function register()
    {
        hook(
            class: Application::class,
            function: 'doRunCommand',
            pre: static function (
                Application $application,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {
                $builder = (new CachedInstrumentation('io.opentelemetry.contrib.php.shopware'))
                    ->tracer()
                    ->spanBuilder(sprintf('bin/console %s', $params[0]->getName()))
                    ->setSpanKind(SpanKind::SPAN_KIND_INTERNAL)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();

                $span = $builder
                    ->setParent($parent)
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                Application $application,
                array       $params,
                mixed       $ret,
                ?Throwable  $exception,
            ) {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }
                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->setStatus(StatusCode::STATUS_CODE_ERROR, $exception->getMessage());
                }
                $span->end();
            },
        );
    }
}
