<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface;

class MeekroClassificationRepository implements ClassificationRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function save(Classification $classification): int
    {
        $this->validateRegex($classification->regex);
        $this->connection->insert('classification', [
            'class_id' => $classification->classId,
            'priority' => $classification->priority,
            're' => $classification->regex,
        ]);
        return $this->connection->insertId();
    }

    public function delete(int $id): void
    {
        $this->connection->delete('classification', 'id = %i', $id);
    }

    public function update(Classification $classification): void
    {
        $this->validateRegex($classification->regex);
        $this->connection->update(
            'classification',
            ['class_id' => $classification->classId, 'priority' => $classification->priority, 're' => $classification->regex],
            'id = %s',
            $classification->id
        );
    }

    public function findBestMatch(string $title): ?Classification
    {
        $row = $this->connection->queryFirstRow(
            'SELECT classification.id, class_id, priority, re FROM classification '
            . 'JOIN classes ON classification.class_id = classes.id '
            . 'WHERE %s REGEXP re ORDER BY priority DESC LIMIT 1',
            $title
        );
        if (!$row) {
            return null;
        }
        return new Classification((int) $row['id'], (int) $row['class_id'], (int) $row['priority'], $row['re']);
    }

    public function findByClassId(int $classId): array
    {
        $rows = $this->connection->query(
            'SELECT id, class_id, priority, re FROM classification WHERE class_id = %i ORDER BY priority DESC',
            $classId
        );
        return array_map(
            fn ($row) => new Classification((int) $row['id'], (int) $row['class_id'], (int) $row['priority'], $row['re']),
            $rows
        );
    }

    public function findById(int $id): ?Classification
    {
        $row = $this->connection->queryFirstRow(
            'SELECT id, class_id, priority, re FROM classification WHERE id = %i',
            $id
        );
        if (!$row) {
            return null;
        }
        return new Classification((int) $row['id'], (int) $row['class_id'], (int) $row['priority'], $row['re']);
    }

    public function findAll(): array
    {
        $rows = $this->connection->query(
            'SELECT id, class_id, priority, re FROM classification ORDER BY class_id, priority DESC'
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = new Classification(
                (int) $row['id'],
                (int) $row['class_id'],
                (int) $row['priority'],
                $row['re']
            );
        }
        return $result;
    }

    private function validateRegex(string $regex): void
    {
        $this->connection->query("SELECT 'test' REGEXP %s", $regex);
    }
}
