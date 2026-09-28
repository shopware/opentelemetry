<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Tests\Unit\Instrumentation;

use OpenTelemetry\SemConv\Incubating\Attributes\HttpIncubatingAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\OpenTelemetry\Instrumentation\ShopwareAttributes;

#[CoversClass(ShopwareAttributes::class)]
class ShopwareAttributesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function providePinnedIncubatingAttributes(): iterable
    {
        yield 'http.request.body.size' => ['HTTP_REQUEST_BODY_SIZE', ShopwareAttributes::HTTP_REQUEST_BODY_SIZE];
        yield 'http.response.body.size' => ['HTTP_RESPONSE_BODY_SIZE', ShopwareAttributes::HTTP_RESPONSE_BODY_SIZE];
    }

    /**
     * The pinned attribute names are copies of incubating semantic conventions. This test fails, on purpose,
     * when the installed sem-conv release renames, removes or changes the incubating constant.
     */
    #[DataProvider('providePinnedIncubatingAttributes')]
    public function testPinnedAttributeMatchesIncubatingConvention(string $constant, string $pinned): void
    {
        $incubating = new \ReflectionClass(HttpIncubatingAttributes::class);

        $this->assertTrue(
            $incubating->hasConstant($constant),
            sprintf('HttpIncubatingAttributes::%s no longer exists, check the semantic conventions for its replacement', $constant),
        );
        $this->assertSame($pinned, $incubating->getConstant($constant));
    }
}
