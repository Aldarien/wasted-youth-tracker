<?php

namespace Zieren\WYT\Application\Command;

use Zieren\WYT\Application\ValueObject\WindowTitlePattern;

final class AddClassificationCommand implements Command
{
    public function __construct(
        public readonly int $classId,
        public readonly int $priority,
        public readonly WindowTitlePattern $regex
    ) {
    }
}
