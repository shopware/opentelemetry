<?php

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function OpenTelemetry\Instrumentation\hook;

class ShopwareInstrumentation
{
    public const NAME = 'shopware';

    private const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.shopware';

    public static function register(): void
    {
        self::instrumentKernelHandle();
        self::instrumentKernelBoot();
        self::instrumentAppScripts();
    }

    /**
     * @return void
     */
    public static function instrumentKernelBoot(): void
    {
        hook(
            class: Kernel::class,
            function: 'boot',
            pre: static function (
                Kernel $application,
                array       $params,
                string      $class,
                string      $function,
                ?string     $filename,
                ?int        $lineno,
            ) {

                $builder = (new CachedInstrumentation(self::INSTRUMENTATION_NAME))
                    ->tracer()
                    ->spanBuilder('Kernel::boot')
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();

                $span = $builder->setParent($parent)->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                Kernel $application,
                array       $params,
                mixed       $ret,
                ?Throwable  $exception,
            ) {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                    $span->recordException($exception);
                }
                $span->end();
                $scope->detach();
            },
        );
    }

    /**
     * @return void
     */
    public static function instrumentAppScripts(): void
    {
        hook(
            class: ScriptExecutor::class,
            function: 'render',
            pre: static function (
                ScriptExecutor $executor,
                array          $params,
                string         $class,
                string         $function,
                ?string        $filename,
                ?int           $lineno,
            ) {
                $hook = $params[0];
                $script = $params[1];

                $builder = (new CachedInstrumentation(self::INSTRUMENTATION_NAME))
                    ->tracer()
                    ->spanBuilder(sprintf('script.render: %s', $script->getName()))
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno)
                    ->setAttribute('script.name', $script->getName())
                    ->setAttribute('hook.name', $hook->getName());

                $span = $builder->setParent(Context::getCurrent())->startSpan();
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (
                ScriptExecutor $executor,
                array          $params,
                mixed          $ret,
                ?Throwable     $exception,
            ) {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                    $span->recordException($exception);
                }
                $span->end();
                $scope->detach();
            },
        );
    }

    /**
     * @return void
     */
    public static function instrumentKernelHandle(): void
    {
        hook(
            class: Kernel::class,
            function: 'handle',
            pre: static function (
                Kernel  $kernel,
                array   $params,
                string  $class,
                string  $function,
                ?string $filename,
                ?int    $lineno,
            ) {
                $request = ($params[0] instanceof Request) ? $params[0] : null;
                $type = $params[1] ?? HttpKernelInterface::MAIN_REQUEST;
                $method = $request?->getMethod() ?? 'unknown';
                $name = ($type === HttpKernelInterface::SUB_REQUEST)
                    ? sprintf('%s %s', $method, $request?->attributes?->get('_controller') ?? 'sub-request')
                    : sprintf('%s %s', $method, $request->getPathInfo());

                $builder = (new CachedInstrumentation(self::INSTRUMENTATION_NAME))
                    ->tracer()
                    ->spanBuilder($name)
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                Kernel     $kernel,
                array      $params,
                mixed      $ret,
                ?Throwable $exception,
            ) {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                    $span->recordException($exception);
                }
                $span->end();
                $scope->detach();
            },
        );
    }
}
