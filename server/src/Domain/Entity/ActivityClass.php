<?php

namespace Zieren\WYT\Domain\Entity;

final class ActivityClass
{
    public function __construct(
        public readonly int $id,
        public readonly string $name
    ) {
    }
}
