<?php

namespace Zieren\WYT\Application\Command;

final class ClearUserConfigCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $key
    ) {
    }
}
