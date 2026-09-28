<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Instrumentation;

/**
 * Span attribute names owned by this bundle. Attributes defined by the OpenTelemetry semantic conventions
 * are taken from the stable `OpenTelemetry\SemConv\Attributes\*` classes directly.
 *
 * @internal
 */
final class ShopwareAttributes
{
    /**
     * Not part of the semantic conventions: attributes specific to Shopware and Symfony.
     */
    public const SCRIPT_NAME = 'script.name';
    public const HOOK_NAME = 'hook.name';
    public const PROFILER_CATEGORY = 'category';
    public const SYMFONY_EVENT_CLASS = 'symfony.event.class';
    public const SYMFONY_MESSENGER_MESSAGE_CLASS = 'symfony.messenger.message.class';

    /**
     * Still incubating in the semantic conventions (`HttpIncubatingAttributes`), so the names are pinned here
     * to keep this bundle unaffected by changes to the incubating classes between sem-conv releases.
     *
     * @todo Replace with `HttpAttributes::HTTP_REQUEST_BODY_SIZE` / `HTTP_RESPONSE_BODY_SIZE` once they are stable.
     *       ShopwareAttributesTest fails when the incubating constants are renamed, removed or changed.
     */
    public const HTTP_REQUEST_BODY_SIZE = 'http.request.body.size';
    public const HTTP_RESPONSE_BODY_SIZE = 'http.response.body.size';

    private function __construct() {}
}
