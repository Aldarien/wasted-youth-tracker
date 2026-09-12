<?php

namespace Zieren\WYT\Domain\Entity;

final class User
{
    public function __construct(
        public readonly string $id,
        public readonly ?int $totalLimitId = null,
        public readonly string $lastError = '',
        public readonly string $ackedError = ''
    ) {
    }
}
