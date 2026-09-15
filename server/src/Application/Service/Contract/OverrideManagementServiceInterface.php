<?php

namespace Zieren\WYT\Application\Service\Contract;

interface OverrideManagementServiceInterface
{
    /**
     * @return string[]
     */
    public function setMinutes(string $userId, string $date, int $limitId, int $minutes): array;

    /**
     * @return string[]
     */
    public function setSlots(string $userId, string $date, int $limitId, string $slots): array;

    /**
     * @return string[]
     */
    public function unlock(string $userId, string $date, int $limitId): array;

    public function clearOverrides(string $userId, string $date, int $limitId): void;
}
