<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Instrumentation;

use OpenTelemetry\SemConv\Attributes\CodeAttributes;

/**
 * Builds the `code.*` span attributes from the parameters the OpenTelemetry hook passes to a pre-hook.
 *
 * @internal
 */
final class CodeLocationAttributes
{
    /**
     * @return array<string, string|int|null>
     */
    public static function from(string $class, string $function, ?string $filename, ?int $lineno): array
    {
        return [
            CodeAttributes::CODE_FUNCTION_NAME => $class . '::' . $function,
            CodeAttributes::CODE_FILE_PATH => $filename,
            CodeAttributes::CODE_LINE_NUMBER => $lineno,
        ];
    }

    private function __construct() {}
}
