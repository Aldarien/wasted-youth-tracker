<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Repository\LimitOverlapQueryInterface;

class PdoLimitOverlapQuery implements LimitOverlapQueryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function findOverlappingLimitNames(int $limitId, ?string $dateForUnlock = null): array
    {
        $configJoin = $dateForUnlock !== null
            ? 'JOIN limit_config ON id = limit_config.limit_id'
            : '';
        $unlockCondition = $dateForUnlock !== null
            ? 'AND k = "locked" AND v '
              . 'AND id NOT IN ( '
              . 'SELECT limit_id FROM overrides '
              . 'WHERE user = (SELECT user FROM limits WHERE id = %i0) '
              . 'AND date = %s1 AND unlocked)'
            : '';

        $sql = "
            SELECT name, id FROM (
              SELECT DISTINCT limit_id FROM (
                SELECT class_id FROM limits
                JOIN mappings ON id = limit_id
                WHERE id = %i0
              ) AS affected_classes
              JOIN mappings ON affected_classes.class_id = mappings.class_id
              WHERE limit_id NOT IN (
                %i0,
                (SELECT total_limit_id FROM users WHERE id =
                  (SELECT user FROM limits WHERE id = %i0))
              )
            ) AS overlapping_limits
            JOIN limits ON id = limit_id
            $configJoin
            WHERE user = (SELECT user FROM limits WHERE id = %i0)
            $unlockCondition
            ORDER BY name";

        $rows = $dateForUnlock !== null
            ? $this->connection->query($sql, $limitId, $dateForUnlock)
            : $this->connection->query($sql, $limitId);

        return array_map(fn ($row) => $row['name'], $rows);
    }
}
