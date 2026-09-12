<?php

namespace Zieren\WYT\Domain\Repository;

use Zieren\WYT\Domain\Entity\Limit;

interface LimitRepositoryInterface
{
    public function save(Limit $limit): int;

    public function delete(int $id): void;

    public function rename(int $id, string $name): void;

    /**
     * @return Limit[]
     */
    public function findByUser(string $userId): array;

    public function findById(int $id): ?Limit;

    public function findTotalLimitForUser(string $userId): ?Limit;
}
