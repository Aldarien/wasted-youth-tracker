<?php

namespace Zieren\WYT\Application\Command;

use Zieren\WYT\Application\ValueObject\BudgetName;

final class RenameLimitCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly int $limitId,
        public readonly BudgetName $limitName
    ) {
    }
}
