<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Profiler;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Shopware\Core\Profiling\Integration\ProfilerInterface;

class OtelProfiler implements ProfilerInterface
{
    /**
     * @var array<string, SpanInterface>
     */
    private array $spans = [];

    /**
     * @param non-empty-string $title
     * @param array<string, string> $tags
     */
    public function start(string $title, string $category, array $tags): void
    {
        $tracer = $this->getTracer();

        $span = $tracer->spanBuilder($title)
            ->setAttribute('category', $category);

        $parent = Context::getCurrent();
        $span = $span->setParent($parent);

        foreach ($tags as $k => $v) {
            if (!is_string($k)) {
                continue;
            }

            $span = $span->setAttribute($k, $v);
        }

        $span = $span->startSpan();
        $this->spans[$title] = $span;
        Context::storage()->attach($span->storeInContext($parent));
    }

    public function stop(string $title): void
    {
        $span = $this->spans[$title] ?? null;

        $scope = Context::storage()->scope();
        if (null === $scope) {
            return;
        }
        $scope->detach();

        if ($span) {
            $span->end();
            unset($this->spans[$title]);
        }
    }

    public function getTracer(): TracerInterface
    {
        $tracer = Globals::tracerProvider();

        return $tracer->getTracer('shopware');
    }
}
