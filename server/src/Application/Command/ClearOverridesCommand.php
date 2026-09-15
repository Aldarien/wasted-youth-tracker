<?php

namespace Zieren\WYT\Application\Command;

final class ClearOverridesCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $date,
        public readonly int $limitId
    ) {
    }
}
