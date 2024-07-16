<?php

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Application;
use Throwable;
use Twig\Environment;
use Twig\TemplateWrapper;

use function OpenTelemetry\Instrumentation\hook;

class ShopwareInstrumentation
{
    private const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.shopware';

    public static function register(): void
    {
        hook(
            class: Kernel::class,
            function: 'boot',
            pre: static function (
                Environment $application,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) {
                $span = self::createSpan($class, $function, $filename, $lineno);
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (
                Application $application,
                array $params,
                mixed $ret,
                ?Throwable $exception,
            ) {
                $scope = Context::storage()->scope();
                if (null === $scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());
                self::logException($span, $exception);
                $span->end();
                $scope->detach();
            },
        );
    }

    private static function createSpan(string $class, string $function, ?string $filename, ?int $lineno): Span
    {
        $builder = (new CachedInstrumentation(self::INSTRUMENTATION_NAME))
            ->tracer()
            ->spanBuilder('kernel boot')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
            ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
            ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
            ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

        return $builder->setParent(Context::getCurrent())->startSpan();
    }

    private static function logException(Span $span, ?Throwable $exception): void
    {
        if ($exception !== null) {
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }
    }
}
