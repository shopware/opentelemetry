<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use Symfony\Component\HttpFoundation\Response;

use function assert;

/**
 * @internal
 */
final class ResponsePropagationSetter implements PropagationSetterInterface
{
    public static function instance(): self
    {
        static $instance;

        return $instance ??= new self();
    }

    /**
     * @param Response $carrier
     * @return array<string>
     */
    public function keys(Response $carrier): array
    {
        return $carrier->headers->keys();
    }

    /**
     * @param Response $carrier
     */
    public function set(&$carrier, string $key, string $value): void
    {
        assert($carrier instanceof Response);

        $carrier->headers->set($key, $value);
    }
}
