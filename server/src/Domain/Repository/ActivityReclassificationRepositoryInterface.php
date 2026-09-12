<?php

namespace Zieren\WYT\Domain\Repository;

use DateTimeImmutable;

interface ActivityReclassificationRepositoryInterface
{
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
