<?php

namespace Zieren\WYT\Application\Command;

final class SetGlobalConfigCommand implements Command
{
    public function __construct(
        public readonly string $key,
        public readonly string $value
    ) {
    }
}
