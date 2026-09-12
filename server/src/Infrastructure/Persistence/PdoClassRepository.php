<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Repository\ClassRepositoryInterface;

class PdoClassRepository implements ClassRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function save(ActivityClass $class): int
    {
        $this->connection->insert('classes', ['name' => $class->name]);
        return $this->connection->insertId();
    }

    public function delete(int $id): void
    {
        $this->connection->delete('classes', 'id = %i', $id);
    }

    public function rename(int $id, string $name): void
    {
        $this->connection->update('classes', ['name' => $name], 'id = %s', $id);
    }

    public function findAll(): array
    {
        $rows = $this->connection->query('SELECT id, name FROM classes ORDER BY name');
        $classes = [];
        foreach ($rows as $row) {
            $classes[(int) $row['id']] = new ActivityClass((int) $row['id'], $row['name']);
        }
        return $classes;
    }

    public function findById(int $id): ?ActivityClass
    {
        $row = $this->connection->queryFirstRow('SELECT id, name FROM classes WHERE id = %i', $id);
        if (!$row) {
            return null;
        }
        return new ActivityClass((int) $row['id'], $row['name']);
    }
}
