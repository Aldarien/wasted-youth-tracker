<?php

namespace Zieren\WYT\Domain\Event;

final class UserCreated implements Event
{
    public function __construct(
        public readonly string $userId,
        public readonly int $totalLimitId
    ) {
    }
}
