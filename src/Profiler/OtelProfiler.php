<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Profiler;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use Shopware\Core\Profiling\Integration\ProfilerInterface;

class OtelProfiler implements ProfilerInterface
{
    /**
     * @var array<string, SpanInterface[]>
     */
    private array $spans = [];

    /**
     * @var array<string, ScopeInterface[]>
     */
    private array $scopes = [];

    /**
     * @param non-empty-string $title
     * @param array<string, string> $tags
     */
    public function start(string $title, string $category, array $tags): void
    {
        $tracer = $this->getTracer();

        $builder = $tracer->spanBuilder($title)
            ->setAttribute('category', $category);

        $parent = Context::getCurrent();
        $builder = $builder->setParent($parent);

        foreach ($tags as $k => $v) {
            if (!is_string($k)) {
                continue;
            }

            $builder = $builder->setAttribute($k, $v);
        }

        $span = $builder->startSpan();
        $scope = Context::storage()->attach($span->storeInContext($parent));

        $this->spans[$title][] = $span;
        $this->scopes[$title][] = $scope;
    }

    public function stop(string $title): void
    {
        if (!isset($this->spans[$title]) || !isset($this->scopes[$title])) {
            return;
        }

        // Pop scope
        $scope = array_pop($this->scopes[$title]);
        if ($scope instanceof ScopeInterface) {
            $scope->detach();
        }

        // Pop span
        $span = array_pop($this->spans[$title]);
        if ($span instanceof SpanInterface) {
            $span->end();
        }

        // Clean up empty stacks
        if (empty($this->spans[$title])) {
            unset($this->spans[$title]);
        }
        if (empty($this->scopes[$title])) {
            unset($this->scopes[$title]);
        }
    }

    public function getTracer(): TracerInterface
    {
        $tracer = Globals::tracerProvider();

        return $tracer->getTracer('shopware');
    }
}
