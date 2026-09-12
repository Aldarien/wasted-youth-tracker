<?php

namespace Zieren\WYT\Infrastructure\Clock;

use DateTimeImmutable;
use Zieren\WYT\Domain\Clock;

class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
