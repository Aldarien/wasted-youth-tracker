<?php

namespace Zieren\WYT\Application\Command;

final class SetUserConfigCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $key,
        public readonly string $value
    ) {
    }
}
