<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
                $method = $request?->getMethod() ?? 'unknown_method';
                if ($type === HttpKernelInterface::SUB_REQUEST) {
                    $controller = $request?->attributes?->get('_controller');
                    $path = is_string($controller) && strlen($controller) > 0 ? $controller : 'sub-request';
                } else {
                    $path = $request?->getPathInfo();
                }

                $builder = (new CachedInstrumentation(self::INSTRUMENTATION_NAME))
                    ->tracer()
                    ->spanBuilder(sprintf('%s %s', $method, $path))
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();
                if ($request) {
                    $parent = Globals::propagator()->extract($request, RequestPropagationGetter::instance());
                    $span = $builder
                        ->setParent($parent)
                        ->setAttribute(TraceAttributes::URL_FULL, $request->getUri())
                        ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
                        ->setAttribute(TraceAttributes::HTTP_REQUEST_BODY_SIZE, $request->headers->get('Content-Length'))
                        ->setAttribute(TraceAttributes::URL_SCHEME, $request->getScheme())
                        ->setAttribute(TraceAttributes::URL_PATH, $request->getPathInfo())
                        ->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->headers->get('User-Agent'))
                        ->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getHost())
                        ->setAttribute(TraceAttributes::SERVER_PORT, $request->getPort())
                        ->startSpan();
                    $request->attributes->set(SpanInterface::class, $span);
                } else {
                    $span = $builder->startSpan();
                }

                Context::storage()->attach($span->storeInContext($parent));
                return [$request];
            },
            post: static function (
                Kernel     $kernel,
                array $params,
                ?Response $response,
                ?Throwable $exception,
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
            },
        );
    }
}
