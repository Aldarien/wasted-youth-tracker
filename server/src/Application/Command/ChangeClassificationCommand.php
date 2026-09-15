<?php

namespace Zieren\WYT\Application\Command;

use Zieren\WYT\Application\ValueObject\WindowTitlePattern;

final class ChangeClassificationCommand implements Command
{
    public function __construct(
        public readonly int $classificationId,
        public readonly WindowTitlePattern $regex,
        public readonly int $priority
    ) {
    }
}
