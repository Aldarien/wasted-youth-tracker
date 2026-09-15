<?php

namespace Zieren\WYT\Application\Service\Contract;

interface UserManagementServiceInterface
{
    public function addUser(string $id): int;

    public function removeUser(string $id): void;

    public function ackError(string $id, string $error): void;

    /**
     * @return \Zieren\WYT\Domain\Entity\User[]
     */
    public function getUsers(): array;

    public function getUnackedError(string $id): string;
}
