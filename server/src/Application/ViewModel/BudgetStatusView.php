<?php

namespace Zieren\WYT\Application\ViewModel;

final class BudgetStatusView
{
    public function __construct(
        public readonly string $name,
        public readonly int $remainingSeconds,
        public readonly bool $locked,
        public readonly bool $noLimit
    ) {
    }
}
