<?php

namespace Shopware\OpenTelemetry\Instrumentation;

use Closure;
use Doctrine\DBAL\Driver\PDO\Statement;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Throwable;

use function OpenTelemetry\Instrumentation\hook;

final class ConnectionInstrumentation
{
    public static function register(): void
    {
        $getter = Closure::bind(static function (Statement $stmt) {
            return $stmt->stmt;
        }, null, Statement::class);

        hook(
            Statement::class,
            'execute',
            pre: static function (
                Statement $stmt,
                array     $params,
                string    $class,
                string    $function,
                ?string   $filename,
                ?int      $lineno,
            ) use ($getter) {
                $query = trim($getter($stmt)->queryString);

                $spanTitle = 'sql.query.';

                if ($query[0] === '#') {
                    $spanTitle .= substr(explode("\n", $query)[0], 2);
                } else {
                    $spanTitle .= explode(' ', $query, 2)[0];
                }

                $parent = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
                $builder = (new CachedInstrumentation('io.opentelemetry.contrib.php.shopware'))
                    ->tracer()
                    ->spanBuilder($spanTitle)
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::DB_STATEMENT, $query)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $parent[5]['function'])
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $parent[5]['class'])
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $parent[5]['file'])
                    ->setAttribute(TraceAttributes::CODE_LINENO, $parent[5]['line']);

                $parent = Context::getCurrent();

                $span = $builder
                    ->setParent($parent)
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (
                Statement $repository,
                array $params,
                mixed $ret,
                ?Throwable $exception
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
