<?php

namespace Shopware\OpenTelemetry\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class EnvelopeStamp implements StampInterface
{
    public function __construct(public array $data = [])
    {

    }
}
