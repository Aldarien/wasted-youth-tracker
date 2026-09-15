<?php

namespace Zieren\WYT\Application\Command;

final class UnlockOverrideCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $date,
        public readonly int $limitId
    ) {
    }
}
