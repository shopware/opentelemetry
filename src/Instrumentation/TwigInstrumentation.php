<?php

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;
use Twig\Environment;
use Twig\TemplateWrapper;

use function OpenTelemetry\Instrumentation\hook;

class TwigInstrumentation
{
    private const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.shopware.twig';

    public static function register(): void
    {
        hook(
            class: Environment::class,
            function: 'render',
            pre: static function (
                Environment $application,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno,
            ) {
                $templateName = self::extractTemplateName($params);
                $span = self::createSpan($templateName, $class, $function, $filename, $lineno);
                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function (
                Environment $application,
                array $params,
                mixed $ret,
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

    private static function extractTemplateName(array $params): string
    {
        return $params[0] instanceof TemplateWrapper ? $params[0]->getTemplateName() : $params[0];
    }

    private static function createSpan(string $templateName, string $class, string $function, ?string $filename, ?int $lineno): Span
    {
        $builder = (new CachedInstrumentation(self::INSTRUMENTATION_NAME))
            ->tracer()
            ->spanBuilder(sprintf('twig.render template: %s', $templateName))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
            ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
            ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
            ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

        return $builder->setParent(Context::getCurrent())->startSpan();
    }
}
