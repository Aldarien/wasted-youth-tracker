<?php

namespace Zieren\WYT\Domain\Repository;

interface LimitOverlapQueryInterface
{
    /**
     * @return string[]
     */
    public function findOverlappingLimitNames(int $limitId, ?string $dateForUnlock = null): array;
}
