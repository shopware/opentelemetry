<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Messenger;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use Opentelemetry\Proto\Trace\V1\Span\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;

class MessageBusSubscriber implements EventSubscriberInterface
{
    public function onSend(SendMessageToTransportsEvent $event): void
    {
        $propagator = Globals::propagator();

        $envelope = $event->getEnvelope();

        $stamp = new EnvelopeStamp([]);

        $event->setEnvelope($envelope->with($stamp));

        $propagator->inject($stamp, EnvelopePropagator::instance(), Context::getCurrent());
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $stamps = $event->getEnvelope()->all(EnvelopeStamp::class);

        if (empty($stamps)) {
            return;
        }

        $newContext = Globals::propagator()->extract($stamps[0], EnvelopePropagator::instance());

        $instrumentation = new CachedInstrumentation('io.opentelemetry.contrib.php.message', schemaUrl: TraceAttributes::SCHEMA_URL);
        $span = $instrumentation->tracer()->spanBuilder('Handle message: ' . get_class($event->getEnvelope()->getMessage()))
            ->setParent($newContext)
            ->setSpanKind(SpanKind::SPAN_KIND_CONSUMER)
            ->setAttribute(TraceAttributes::MESSAGE_TYPE, get_class($event->getEnvelope()->getMessage()))
            ->startSpan();

        Context::storage()->attach($span->storeInContext($newContext));
    }

    public function onHandled($event): void
    {
        $scope = Context::storage()->scope();
        if (null === $scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());

        if ($event instanceof WorkerMessageFailedEvent) {
            $span->setStatus(StatusCode::STATUS_ERROR, $event->getThrowable()->getMessage());
        }

        $span->end();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => ['onSend'],
            WorkerMessageReceivedEvent::class => ['onReceived'],
            WorkerMessageHandledEvent::class => ['onHandled'],
            WorkerMessageRetriedEvent::class => ['onHandled'],
            WorkerMessageFailedEvent::class => ['onHandled'],
        ];
    }
}
