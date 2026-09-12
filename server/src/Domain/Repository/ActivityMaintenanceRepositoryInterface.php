<?php

namespace Zieren\WYT\Domain\Repository;

use DateTimeImmutable;

interface ActivityMaintenanceRepositoryInterface
{
    public function prune(DateTimeImmutable $before): void;

    /**
     * @return string[]
     */
    public function findTitlesAfter(DateTimeImmutable $fromTime): array;

    /**
     * @return string[]
     */
    public function findTitlesByClass(int $classId): array;

    public function updateClassForTitleAfter(
        string $title,
        int $classId,
        DateTimeImmutable $fromTime
    ): void;

    public function updateClassForTitle(string $title, int $fromClassId, int $toClassId): void;
}
