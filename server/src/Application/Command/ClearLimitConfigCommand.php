<?php

namespace Zieren\WYT\Application\Command;

final class ClearLimitConfigCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly int $limitId,
        public readonly string $key
    ) {
    }
}
