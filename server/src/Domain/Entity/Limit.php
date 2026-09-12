<?php

namespace Zieren\WYT\Domain\Entity;

final class Limit
{
    public function __construct(
        public readonly int $id,
        public readonly string $userId,
        public readonly string $name
    ) {
    }
}
