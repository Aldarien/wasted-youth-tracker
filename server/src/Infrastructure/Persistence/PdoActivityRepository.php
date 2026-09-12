<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use DateTimeImmutable;
use Zieren\WYT\Domain\Entity\ActivityRecord;
use Zieren\WYT\Domain\Repository\ActivityPruningRepositoryInterface;
use Zieren\WYT\Domain\Repository\ActivityReclassificationRepositoryInterface;
use Zieren\WYT\Domain\Repository\ActivityQueryRepositoryInterface;
use Zieren\WYT\Domain\Repository\ActivityRecordRepositoryInterface;

class PdoActivityRepository implements
    ActivityRecordRepositoryInterface,
    ActivityQueryRepositoryInterface,
    ActivityPruningRepositoryInterface,
    ActivityReclassificationRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function save(ActivityRecord $record): void
    {
        $this->connection->replace('activity', [[
            'user' => $record->userId,
            'seq' => $record->seq,
            'from_ts' => $record->fromTs,
            'to_ts' => $record->toTs,
            'class_id' => $record->classId,
            'title' => $record->title,
        ]]);
    }

    public function saveBatch(array $records): void
    {
        $data = [];
        foreach ($records as $record) {
            $data[] = [
                'user' => $record->userId,
                'seq' => $record->seq,
                'from_ts' => $record->fromTs,
                'to_ts' => $record->toTs,
                'class_id' => $record->classId,
                'title' => $record->title,
            ];
        }
        if ($data) {
            $this->connection->replace('activity', $data);
        }
    }

    public function getMaxSequence(string $userId): ?int
    {
        $row = $this->connection->queryFirstRow(
            'SELECT MAX(seq) AS seq FROM activity WHERE user = %s',
            $userId
        );
        if (!$row || $row['seq'] === null) {
            return null;
        }
        return (int) $row['seq'];
    }

    public function findRecentByTitles(string $userId, int $previousSeq, int $sinceTs, array $titles): array
    {
        if (!$titles) {
            return [];
        }
        $rows = $this->connection->query('
            SELECT title, from_ts
            FROM activity
            WHERE user = %s
            AND seq = %i
            AND to_ts >= %i
            AND title IN %ls',
            $userId,
            $previousSeq,
            $sinceTs,
            $titles
        );
        return array_map(fn ($row) => ['title' => $row['title'], 'from_ts' => (int) $row['from_ts']], $rows);
    }

    public function concludePreviousRecords(string $userId, int $previousSeq, int $timestamp, int $sinceTs): void
    {
        $this->connection->rawQuery('
            UPDATE activity
            SET to_ts = %i
            WHERE user = %s
            AND seq = %i
            AND to_ts >= %i',
            $timestamp,
            $userId,
            $previousSeq,
            $sinceTs
        );
    }

    public function queryTimeSpentByLimitAndDate(string $userId, DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        $fromTs = $from->getTimestamp();
        $toTs = $to ? $to->getTimestamp() : PHP_INT_MAX;
        return $this->connection->query('
            SELECT
                limit_id,
                GREATEST(%i1, from_ts) AS from_ts,
                LEAST(%i2, to_ts) AS to_ts
            FROM activity
            LEFT JOIN (
                SELECT class_id, limit_id
                FROM mappings
                JOIN limits ON mappings.limit_id = limits.id
                WHERE user = %s0
            ) user_mappings
            ON activity.class_id = user_mappings.class_id
            WHERE user = %s0
            AND title != ""
            AND (
                (%i1 <= from_ts AND from_ts < %i2)
                OR
                (%i1 < to_ts AND to_ts <= %i2)
                OR
                (from_ts < %i1 AND %i2 <= to_ts)
            )
            ORDER BY from_ts, to_ts',
            $userId,
            $fromTs,
            $toTs
        );
    }

    public function queryTimeSpentByTitle(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $fromTs = $from->getTimestamp();
        $toTs = $to->getTimestamp();
        $rows = $this->connection->query('
            SELECT
                title,
                name,
                SUM(LEAST(%i2, to_ts) - GREATEST(%i1, from_ts)) AS sum_s,
                MAX(LEAST(%i2, to_ts)) AS ts_last_seen
            FROM activity
            JOIN classes ON class_id = id
            WHERE user = %s0
            AND title != ""
            AND (
                (%i1 <= from_ts AND from_ts < %i2)
                OR
                (%i1 < to_ts AND to_ts <= %i2)
                OR
                (from_ts < %i1 AND %i2 <= to_ts)
            )
            GROUP BY title, name
            ORDER BY ts_last_seen DESC, sum_s DESC, title',
            $userId,
            $fromTs,
            $toTs
        );
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                date('Y-m-d H:i:s', (int) $row['ts_last_seen']),
                (int) $row['sum_s'],
                $row['name'],
                $row['title'],
            ];
        }
        return $result;
    }

    public function queryTitleSequence(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $fromTs = $from->getTimestamp();
        $toTs = $to->getTimestamp();
        $rows = $this->connection->query('
            SELECT from_ts, to_ts, name, title
            FROM activity
            JOIN classes ON class_id = id
            WHERE user = %s0
            AND title != ""
            AND (
                (%i1 <= from_ts AND from_ts < %i2)
                OR
                (%i1 < to_ts AND to_ts <= %i2)
                OR
                (from_ts < %i1 AND %i2 <= to_ts)
            )
            ORDER BY to_ts DESC, from_ts, title',
            $userId,
            $fromTs,
            $toTs
        );
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                date('Y-m-d H:i:s', (int) $row['from_ts']),
                date('Y-m-d H:i:s', (int) $row['to_ts']),
                $row['name'],
                $row['title'],
            ];
        }
        return $result;
    }

    public function queryTopUnclassified(string $userId, DateTimeImmutable $from, DateTimeImmutable $to, bool $orderBySum, int $num): array
    {
        $fromTs = $from->getTimestamp();
        $toTs = $to->getTimestamp();
        $orderBy = $orderBySum
            ? 'ORDER BY sum_s DESC, ts_last_seen DESC, title'
            : 'ORDER BY ts_last_seen DESC, sum_s DESC, title';
        $rows = $this->connection->query("
            SELECT
                title,
                name,
                SUM(LEAST(%i2, to_ts) - GREATEST(%i1, from_ts)) AS sum_s,
                MAX(LEAST(%i2, to_ts)) AS ts_last_seen
            FROM activity
            JOIN classes ON class_id = id
            WHERE user = %s0
            AND title != ''
            AND class_id = 1
            AND (
                (%i1 <= from_ts AND from_ts < %i2)
                OR
                (%i1 < to_ts AND to_ts <= %i2)
                OR
                (from_ts < %i1 AND %i2 <= to_ts)
            )
            GROUP BY title, name
            $orderBy
            LIMIT %i",
            $userId,
            $fromTs,
            $toTs,
            $num
        );
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                (int) $row['sum_s'],
                $row['title'],
                date('Y-m-d H:i:s', (int) $row['ts_last_seen']),
            ];
        }
        return $result;
    }

    public function prune(DateTimeImmutable $before): void
    {
        $this->connection->delete('activity', 'to_ts < %i', $before->getTimestamp());
    }

    public function findTitlesAfter(DateTimeImmutable $fromTime): array
    {
        $rows = $this->connection->query(
            'SELECT DISTINCT title FROM activity WHERE title != "" AND to_ts > %i',
            $fromTime->getTimestamp()
        );
        return array_column($rows, 'title');
    }

    public function findTitlesByClass(int $classId): array
    {
        $rows = $this->connection->query(
            'SELECT DISTINCT title FROM activity WHERE class_id = %i',
            $classId
        );
        return array_column($rows, 'title');
    }

    public function updateClassForTitleAfter(
        string $title,
        int $classId,
        DateTimeImmutable $fromTime
    ): void {
        $this->connection->update(
            'activity',
            ['class_id' => $classId],
            'title = %s AND to_ts > %i',
            $title,
            $fromTime->getTimestamp()
        );
    }

    public function updateClassForTitle(string $title, int $fromClassId, int $toClassId): void
    {
        $this->connection->update(
            'activity',
            ['class_id' => $toClassId],
            'title = %s AND class_id = %i',
            $title,
            $fromClassId
        );
    }
}
