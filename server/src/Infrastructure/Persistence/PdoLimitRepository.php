<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;

class PdoLimitRepository implements LimitRepositoryInterface
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

    public function isTotalLimit(int $id): bool
    {
        $row = $this->connection->queryFirstRow(
            'SELECT 1 FROM users WHERE total_limit_id = %i LIMIT 1',
            $id
        );
        return $row !== null;
    }

}
