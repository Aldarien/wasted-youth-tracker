<?php

namespace Zieren\WYT\Domain\Entity;

final class ActivityRecord
{
    public function __construct(
        public readonly string $userId,
        public readonly int $seq,
        public readonly int $fromTs,
        public readonly int $toTs,
        public readonly int $classId,
        public readonly string $title
    ) {
    }
}
