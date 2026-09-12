<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;

class MeekroLimitRepository implements LimitRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function save(Limit $limit): int
    {
        $this->connection->insert('limits', ['user' => $limit->userId, 'name' => $limit->name]);
        return $this->connection->insertId();
    }

    public function delete(int $id): void
    {
        $this->connection->delete(
            'limits',
            'id = %i AND id NOT IN (SELECT total_limit_id FROM users)',
            $id
        );
    }

    public function rename(int $id, string $name): void
    {
        $this->connection->update(
            'limits',
            ['name' => $name],
            'id = %s AND id NOT IN (SELECT total_limit_id FROM users)',
            $id
        );
    }

    public function findByUser(string $userId): array
    {
        $rows = $this->connection->query('SELECT id, user, name FROM limits WHERE user = %s ORDER BY id', $userId);
        return array_map(fn ($row) => new Limit((int) $row['id'], $row['user'], $row['name']), $rows);
    }

    public function findById(int $id): ?Limit
    {
        $row = $this->connection->queryFirstRow('SELECT id, user, name FROM limits WHERE id = %i', $id);
        if (!$row) {
            return null;
        }
        return new Limit((int) $row['id'], $row['user'], $row['name']);
    }

    public function findTotalLimitForUser(string $userId): ?Limit
    {
        $row = $this->connection->queryFirstRow(
            'SELECT limits.id, limits.user, limits.name FROM limits '
            . 'JOIN users ON users.total_limit_id = limits.id WHERE users.id = %s',
            $userId
        );
        if (!$row) {
            return null;
        }
        return new Limit((int) $row['id'], $row['user'], $row['name']);
    }

    public function addMapping(int $classId, int $limitId): void
    {
        $this->connection->insert('mappings', ['class_id' => $classId, 'limit_id' => $limitId]);
    }

    public function removeMapping(int $classId, int $limitId): void
    {
        $this->connection->delete(
            'mappings',
            'class_id = %i AND limit_id = %i AND limit_id NOT IN (SELECT total_limit_id FROM users)',
            $classId,
            $limitId
        );
    }

    public function findLimitIdsByClass(int $classId): array
    {
        $rows = $this->connection->query(
            'SELECT limit_id FROM mappings WHERE class_id = %i ORDER BY limit_id',
            $classId
        );
        return array_map(fn ($row) => (int) $row['limit_id'], $rows);
    }

    public function findLimitIdsByClassAndUser(int $classId, string $userId): array
    {
        $rows = $this->connection->query(
            'SELECT mappings.limit_id FROM mappings '
            . 'JOIN limits ON limits.id = mappings.limit_id '
            . 'WHERE mappings.class_id = %i AND limits.user = %s '
            . 'ORDER BY mappings.limit_id',
            $classId,
            $userId
        );
        return array_map(fn ($row) => (int) $row['limit_id'], $rows);
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
