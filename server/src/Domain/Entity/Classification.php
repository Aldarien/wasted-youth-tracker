<?php

namespace Zieren\WYT\Domain\Entity;

final class Classification
{
    public function __construct(
        public readonly int $id,
        public readonly int $classId,
        public readonly int $priority,
        public readonly string $regex
    ) {
    }
}
