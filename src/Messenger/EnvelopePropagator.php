<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Messenger;

use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;

use function assert;

class EnvelopePropagator implements PropagationSetterInterface, PropagationGetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    /**
     * @param EnvelopeStamp $carrier
     */
    public function set(&$carrier, string $key, string $value): void
    {
        assert($carrier instanceof EnvelopeStamp);

        $carrier->data[$key] = $value;
    }

    /**
     * @param EnvelopeStamp $carrier
     */
    public function keys($carrier): array
    {
        assert($carrier instanceof EnvelopeStamp);

        return array_keys($carrier->data);
    }

    /**
     * @param EnvelopeStamp $carrier
     */
    public function get($carrier, string $key): ?string
    {
        assert($carrier instanceof EnvelopeStamp);

        return $carrier->data[$key] ?? null;
    }
}
