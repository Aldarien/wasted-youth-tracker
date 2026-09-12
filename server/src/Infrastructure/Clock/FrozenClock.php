<?php

namespace Zieren\WYT\Infrastructure\Clock;

use DateTimeImmutable;
use Zieren\WYT\Domain\Clock;

class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(DateTimeImmutable $now)
    {
        $this->now = $now;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
