<?php

namespace Zieren\WYT\Application\Command;

use Zieren\WYT\Application\ValueObject\Minutes;

final class SetOverrideMinutesCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $date,
        public readonly int $limitId,
        public readonly Minutes $minutes
    ) {
    }
}
