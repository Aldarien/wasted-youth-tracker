<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Entity\User;
use Zieren\WYT\Domain\Repository\TotalLimitMappingRepositoryInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;

class PdoUserRepository implements UserRepositoryInterface, TotalLimitMappingRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function save(User $user): void
    {
        $this->connection->insert('users', ['id' => $user->id]);
    }

    public function delete(string $id): void
    {
        $this->connection->delete('users', 'id = %s', $id);
    }

    public function findAll(): array
    {
        $rows = $this->connection->query('SELECT id, total_limit_id, last_error, acked_error FROM users ORDER BY id');
        return array_map(fn ($row) => new User(
            $row['id'],
            $row['total_limit_id'] ? (int) $row['total_limit_id'] : null,
            $row['last_error'] ?? '',
            $row['acked_error'] ?? ''
        ), $rows);
    }

    public function findById(string $id): ?User
    {
        $row = $this->connection->queryFirstRow(
            'SELECT id, total_limit_id, last_error, acked_error FROM users WHERE id = %s',
            $id
        );
        if (!$row) {
            return null;
        }
        return new User(
            $row['id'],
            $row['total_limit_id'] ? (int) $row['total_limit_id'] : null,
            $row['last_error'] ?? '',
            $row['acked_error'] ?? ''
        );
    }

    public function updateTotalLimit(string $id, int $limitId): void
    {
        $this->connection->update('users', ['total_limit_id' => $limitId], 'id = %s', $id);
    }

    public function updateLastError(string $id, string $error): void
    {
        $this->connection->update('users', ['last_error' => $error], 'id = %s', $id);
    }

    public function updateAckedError(string $id, string $ackedError): void
    {
        $this->connection->update('users', ['acked_error' => $ackedError], 'id = %s', $id);
    }

    public function mapAllClassesToLimit(int $limitId): void
    {
        $this->connection->rawQuery('
            INSERT IGNORE INTO mappings (limit_id, class_id)
            SELECT %i AS limit_id, id AS class_id FROM classes',
            $limitId
        );
    }

}
