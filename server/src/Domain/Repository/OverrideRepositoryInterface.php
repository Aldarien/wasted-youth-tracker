<?php

namespace Zieren\WYT\Domain\Repository;

interface OverrideRepositoryInterface
{
    public function setMinutes(string $userId, string $date, int $limitId, int $minutes): void;

    public function setSlots(string $userId, string $date, int $limitId, string $slots): void;

    public function setUnlock(string $userId, string $date, int $limitId): void;

    public function clear(string $userId, string $date, int $limitId): void;

    /**
     * @return array<int, array{minutes?: int, slots?: string, unlocked?: bool}>
     */
    public function findByUserAndDate(string $userId, string $fromDate, string $toDate): array;

    /**
     * @return array<int, array{minutes?: int, slots?: string, unlocked?: bool}>
     */
    public function findByUserForDate(string $userId, string $date): array;
}
