<?php

namespace Zieren\WYT\Application\Command;

use Zieren\WYT\Application\ValueObject\ClassName;

final class RenameClassCommand implements Command
{
    public function __construct(
        public readonly int $classId,
        public readonly ClassName $className
    ) {
    }
}
