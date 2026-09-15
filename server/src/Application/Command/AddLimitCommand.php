<?php

namespace Zieren\WYT\Application\Command;

use Zieren\WYT\Application\ValueObject\BudgetName;

final class AddLimitCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly BudgetName $limitName
    ) {
    }
}
