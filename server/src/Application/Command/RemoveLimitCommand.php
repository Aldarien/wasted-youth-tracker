<?php

namespace Zieren\WYT\Application\Command;

final class RemoveLimitCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly int $limitId
    ) {
    }
}
