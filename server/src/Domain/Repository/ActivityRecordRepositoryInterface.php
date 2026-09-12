<?php

namespace Zieren\WYT\Domain\Repository;

use Zieren\WYT\Domain\Entity\ActivityRecord;

interface ActivityRecordRepositoryInterface
{
    public function save(ActivityRecord $record): void;

    /**
     * @param ActivityRecord[] $records
     */
    public function saveBatch(array $records): void;

    public function getMaxSequence(string $userId): ?int;

    /**
     * @param string[] $titles
     * @return ActivityRecord[]
     */
    public function findRecentByTitles(string $userId, int $previousSeq, int $sinceTs, array $titles): array;

    public function concludePreviousRecords(string $userId, int $previousSeq, int $timestamp, int $sinceTs): void;
}
