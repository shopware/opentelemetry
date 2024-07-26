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
                $backtrace = self::getBacktrace();

                if ($backtrace) {
                    $class = $backtrace['class'];
                    $function = $backtrace['function'];
                    $filename = $backtrace['file'];
                    $lineno = $backtrace['line'];
                }

                $query = trim($getter($stmt)->queryString);

                $spanTitle = 'sql.query.';

                if ($query[0] === '#') {
                    $part = substr(explode("\n", $query)[0], 2);
                } else {
                    $part = strtoupper(explode(' ', $query, 2)[0]);
                }

                $spanTitle .= $part;

                if ($part === 'SELECT' && preg_match('/FROM\s*`?(\w*)`?/m', $query, $matches)) {
                    $spanTitle .= '.' . $matches[1];
                } elseif ($part === 'INSERT' && preg_match('/INTO\s*`?(\w*)`?/m', $query, $matches)) {
                    $spanTitle .= '.' . $matches[1];
                } elseif ($part === 'UPDATE' && preg_match('/UPDATE\s*`?(\w*)`?/m', $query, $matches)) {
                    $spanTitle .= '.' . $matches[1];
                } elseif ($part === 'DELETE' && preg_match('/FROM\s*`?(\w*)`?/m', $query, $matches)) {
                    $spanTitle .= '.' . $matches[1];
                }

                $builder = (new CachedInstrumentation('io.opentelemetry.contrib.php.shopware'))
                    ->tracer()
                    ->spanBuilder($spanTitle)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::DB_STATEMENT, $query)
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
                Statement $repository,
                array $params,
                mixed $ret,
                ?Throwable $exception,
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
            },
        );
    }

    private static function getBacktrace(): ?array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($backtrace as $trace) {
            if (isset($trace['class']) && (
                str_starts_with($trace['class'], 'Doctrine\\DBAL') ||
                    str_starts_with($trace['class'], 'Shopware\\OpenTelemetry\\') ||
                    str_starts_with($trace['class'], 'Shopware\Core\Framework\DataAbstractionLayer')
            )) {
                continue;
            }

            return $trace;
        }

        return null;
    }
}
