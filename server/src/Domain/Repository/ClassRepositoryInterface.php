<?php

namespace Zieren\WYT\Domain\Repository;

use Zieren\WYT\Domain\Entity\ActivityClass;

interface ClassRepositoryInterface
{
    public function save(ActivityClass $class): int;

    public function delete(int $id): void;

    public function rename(int $id, string $name): void;

    /**
     * @return ActivityClass[]
     */
    public function findAll(): array;

    public function findById(int $id): ?ActivityClass;
}
