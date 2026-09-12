<?php

namespace Zieren\WYT\Domain\Repository;

use Zieren\WYT\Domain\Entity\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function delete(string $id): void;

    /**
     * @return User[]
     */
    public function findAll(): array;

    public function findById(string $id): ?User;

    public function updateTotalLimit(string $id, int $limitId): void;

    public function updateLastError(string $id, string $error): void;

    public function updateAckedError(string $id, string $ackedError): void;

}
