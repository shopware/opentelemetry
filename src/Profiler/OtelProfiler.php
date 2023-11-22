<?php

namespace Frosh\OpenTelemetry\Profiler;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;
use Shopware\Core\Profiling\Integration\ProfilerInterface;

class OtelProfiler implements ProfilerInterface
{
    private array $spans = [];

    public function start(string $title, string $category, array $tags): void
    {
        $tracer = $this->getTracer();

        $span = $tracer->spanBuilder($title)
            ->setAttribute('category', $category);

        foreach ($tags as $k => $v) {
            if (!is_string($k)) {
                continue;
            }

            $span = $span->setAttribute($k, $v);
        }

        $this->spans[$title] = $span->startSpan();
    }

    public function stop(string $title): void
    {
        $span = $this->spans[$title] ?? null;

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
