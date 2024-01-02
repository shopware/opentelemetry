<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use Symfony\Component\HttpFoundation\Request;

use function assert;

/**
 * @internal
 */
final class RequestPropagationGetter implements PropagationGetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    /** @psalm-suppress InvalidReturnType */
    public function keys($carrier): array
    {
        assert($carrier instanceof Request);

        /** @psalm-suppress InvalidReturnStatement */
        return $carrier->headers->keys();
    }

    public function get($carrier, string $key): ?string
    {
        assert($carrier instanceof Request);

        return $carrier->headers->get($key);
    }
}
