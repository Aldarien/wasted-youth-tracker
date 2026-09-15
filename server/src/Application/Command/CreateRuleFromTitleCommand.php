<?php

namespace Zieren\WYT\Application\Command;

final class CreateRuleFromTitleCommand implements Command
{
    public function __construct(
        public readonly string $className,
        public readonly int $priority,
        public readonly string $title,
        public readonly ?int $limitId = null
    ) {
    }
}
