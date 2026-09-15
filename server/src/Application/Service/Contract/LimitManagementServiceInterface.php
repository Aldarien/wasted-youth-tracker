<?php

namespace Zieren\WYT\Application\Service\Contract;

interface LimitManagementServiceInterface
{
    public function addLimit(string $userId, string $name): int;

    public function renameLimit(string $userId, int $limitId, string $name): void;

    public function removeLimit(string $userId, int $limitId): void;

    public function setLimitConfig(string $userId, int $limitId, string $key, string $value): void;

    public function clearLimitConfig(string $userId, int $limitId, string $key): void;
}
