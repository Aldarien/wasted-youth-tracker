<?php

namespace Zieren\WYT\Application\ViewModel;

final class ChildStatusView
{
    /**
     * @param BudgetStatusView[] $budgets
     */
    public function __construct(
        public readonly ?string $lastSeen,
        public readonly string $unackedError,
        public readonly array $budgets
    ) {
    }
}
