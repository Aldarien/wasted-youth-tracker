<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Repository\OverrideRepositoryInterface;

class PdoOverrideRepository implements OverrideRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function setMinutes(string $userId, string $date, int $limitId, int $minutes): void
    {
        $this->connection->insertUpdate(
            'overrides',
            ['user' => $userId, 'date' => $date, 'limit_id' => $limitId, 'minutes' => $minutes]
        );
    }

    public function setSlots(string $userId, string $date, int $limitId, string $slots): void
    {
        $this->connection->insertUpdate(
            'overrides',
            ['user' => $userId, 'date' => $date, 'limit_id' => $limitId, 'slots' => $slots]
        );
    }

    public function setUnlock(string $userId, string $date, int $limitId): void
    {
        $this->connection->insertUpdate(
            'overrides',
            ['user' => $userId, 'date' => $date, 'limit_id' => $limitId, 'unlocked' => 1]
        );
    }

    public function clear(string $userId, string $date, int $limitId): void
    {
        $this->connection->delete(
            'overrides',
            'user = %s AND date = %s AND limit_id = %i',
            $userId,
            $date,
            $limitId
        );
    }

    public function findByUserAndDate(string $userId, string $fromDate, string $toDate): array
    {
        $rows = $this->connection->query('
            SELECT
                date,
                name,
                CASE WHEN minutes IS NOT NULL THEN minutes ELSE "default" END AS minutes,
                CASE WHEN slots IS NOT NULL THEN slots ELSE "default" END AS slots,
                CASE WHEN unlocked = 1 THEN "unlocked" ELSE "default" END AS unlocked
            FROM overrides
            JOIN limits ON limit_id = id
            WHERE overrides.user = %s
            AND date >= %s
            AND date <= %s
            ORDER BY date DESC, name',
            $userId,
            $fromDate,
            $toDate
        );
        return $rows;
    }

    public function findByUserForDate(string $userId, string $date): array
    {
        $rows = $this->connection->query(
            'SELECT limit_id, unlocked, minutes, slots FROM overrides WHERE user = %s AND date = %s',
            $userId,
            $date
        );
        $overridesByLimit = [];
        foreach ($rows as $row) {
            $data = [];
            if ($row['minutes'] !== null) {
                $data['minutes'] = (int) $row['minutes'];
            }
            if ($row['unlocked'] !== null) {
                $data['unlocked'] = (bool) $row['unlocked'];
            }
            if ($row['slots'] !== null) {
                $data['slots'] = $row['slots'];
            }
            $overridesByLimit[(int) $row['limit_id']] = $data;
        }
        return $overridesByLimit;
    }
}
