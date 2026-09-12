<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface;

class PdoClassLimitMappingRepository implements ClassLimitMappingRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function addMapping(int $classId, int $limitId): void
    {
        $this->connection->insert('mappings', ['class_id' => $classId, 'limit_id' => $limitId]);
    }

    public function removeMapping(int $classId, int $limitId): void
    {
        $this->connection->delete('mappings', 'class_id = %i AND limit_id = %i', $classId, $limitId);
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

    public function mapAllClassesToLimit(int $limitId): void
    {
        $this->connection->rawQuery(
            'INSERT IGNORE INTO mappings (limit_id, class_id) '
            . 'SELECT %i AS limit_id, id AS class_id FROM classes',
            $limitId
        );
    }
}
