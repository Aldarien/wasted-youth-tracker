<?php

namespace Zieren\WYT\Application\Command;

use Zieren\WYT\Application\ValueObject\SlotExpression;

final class SetOverrideSlotsCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $date,
        public readonly int $limitId,
        public readonly SlotExpression $slots
    ) {
    }
}
