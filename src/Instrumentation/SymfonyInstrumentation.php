<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use Shopware\Core\Framework\Adapter\Kernel\HttpKernel;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function OpenTelemetry\Instrumentation\hook;
use function sprintf;
use function strlen;

final class SymfonyInstrumentation
{
    public static function register(): void
    {
        self::instrumentKernel();
        self::instrumentEventDispatcher();
    }

    private static function instrumentKernel(): void
    {
        hook(
            HttpKernel::class,
            'handle',
            pre: static function (
                HttpKernel $kernel,
                array      $params,
                string     $class,
                string     $function,
                ?string    $filename,
                ?int       $lineno,
            ) {
                $instrumentation = new CachedInstrumentation('io.opentelemetry.contrib.php.symfony');
                $request = ($params[0] instanceof Request) ? $params[0] : null;
                $type = $params[1] ?? HttpKernelInterface::MAIN_REQUEST;
                $method = $request?->getMethod() ?? '';
                $method = $method === '' ? 'unknown' : $method;

                if ($type === HttpKernelInterface::SUB_REQUEST) {
                    $controller = $request?->attributes?->get('_controller');
                    $path = is_string($controller) && strlen($controller) > 0 ? $controller : 'sub-request';
                    $name = sprintf('%s %s', $method, $path);
                } else {
                    $name = $method;
                }

                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = $instrumentation
                    ->tracer()
                    ->spanBuilder($name)
                    ->setSpanKind(($type === HttpKernelInterface::SUB_REQUEST) ? SpanKind::KIND_INTERNAL : SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();

                $span = $builder->setParent($parent)->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                HttpKernel $kernel,
                array      $params,
                ?Response  $response,
                ?Throwable $exception,
            ): void {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }
                $scope->detach();
                $span = Span::fromContext($scope->context());

                $request = ($params[0] instanceof Request) ? $params[0] : null;
                if (null !== $request) {
                    /** @var string|null $routeName */
                    $routeName = $request->attributes->get('_route', '');

                    if ('' !== $routeName) {
                        /** @psalm-suppress ArgumentTypeCoercion */
                        $span
                            ->updateName(sprintf('%s %s', $request->getMethod(), $routeName))
                            ->setAttribute(TraceAttributes::HTTP_ROUTE, $routeName);
                    }
                }

                if (null !== $exception) {
                    $span->recordException($exception, [
                        TraceAttributes::EXCEPTION_ESCAPED => true,
                    ]);
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                }

                if (null === $response) {
                    $span->end();

                    return;
                }

                if ($response->getStatusCode() >= Response::HTTP_BAD_REQUEST) {
                    $span->setStatus(StatusCode::STATUS_ERROR);
                }
                $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getProtocolVersion());
                $contentLength = $response->headers->get('Content-Length');
                /** @psalm-suppress PossiblyFalseArgument */
                if (null === $contentLength && is_string($response->getContent())) {
                    $contentLength = strlen($response->getContent());
                }

                $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, $contentLength);

                // Propagate server-timing header to response, if ServerTimingPropagator is present
                if (class_exists('OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator')) {
                    $prop = new \OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator();
                    $prop->inject($response, ResponsePropagationSetter::instance(), $scope->context());
                }

                // Propagate traceresponse header to response, if TraceResponsePropagator is present
                if (class_exists('OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator')) {
                    $prop = new \OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator();
                    $prop->inject($response, ResponsePropagationSetter::instance(), $scope->context());
                }

                $span->end();
            },
        );

        hook(
            HttpKernel::class,
            'handleThrowable',
            pre: static function (
                HttpKernel $kernel,
                array      $params,
                string     $class,
                string     $function,
                ?string    $filename,
                ?int       $lineno,
            ): array {
                /** @var \Throwable $throwable */
                $throwable = $params[0];

                Span::getCurrent()
                    ->recordException($throwable, [
                        TraceAttributes::EXCEPTION_ESCAPED => true,
                    ])
                    ->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());

                return $params;
            },
        );
    }

    private static function instrumentEventDispatcher(): void
    {
        hook(
            EventDispatcher::class,
            'dispatch',
            pre: static function (
                EventDispatcher $dispatcher,
                array           $params,
                string          $class,
                string          $function,
                ?string         $filename,
                ?int            $lineno,
            ) {
                $instrumentation = new CachedInstrumentation('io.opentelemetry.contrib.php.symfony');
                $eventClass = $params[0]::class;
                $name = sprintf('EventDispatcher::dispatch(%s)', $eventClass);

                $builder = $instrumentation
                    ->tracer()
                    ->spanBuilder($name)
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno)
                    ->setAttribute(TraceAttributes::EVENT_NAME, $eventClass);

                $parent = Context::getCurrent();
                $span = $builder->setParent($parent)->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                EventDispatcher $dispatcher,
                array           $params,
                object         $return,
                ?\Throwable     $exception,
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
