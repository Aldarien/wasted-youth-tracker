<?php

namespace Zieren\WYT\Domain\Repository;

use Zieren\WYT\Domain\Entity\Classification;

interface ClassificationRepositoryInterface
{
    public function save(Classification $classification): int;

    public function delete(int $id): void;

    public function update(Classification $classification): void;

    public function findBestMatch(string $title): ?Classification;

    /**
     * @return Classification[]
     */
    public function findByClassId(int $classId): array;

    public function findById(int $id): ?Classification;

    /**
     * @return Classification[]
     */
    public function findAll(): array;
}
