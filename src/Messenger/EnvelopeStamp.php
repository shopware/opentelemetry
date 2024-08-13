<?php

declare(strict_types=1);

namespace Shopware\OpenTelemetry\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class EnvelopeStamp implements StampInterface
{
    public function __construct(public array $data = []) {}
}
