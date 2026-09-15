<?php

namespace Zieren\WYT\Domain\Repository;

interface ConfigRepositoryInterface
{
    public function getGlobalConfig(): array;

    public function getGlobalConfigValue(string $key): ?string;

    public function getGlobalString(string $key): ?string;

    public function getGlobalInt(string $key): ?int;

    public function setGlobalConfig(string $key, string $value): void;

    public function clearGlobalConfig(string $key): void;

    public function getUserConfig(string $userId): array;

    public function getUserString(string $userId, string $key): ?string;

    public function getUserInt(string $userId, string $key): ?int;

    public function setUserConfig(string $userId, string $key, string $value): void;

    public function clearUserConfig(string $userId, string $key): void;

    public function getLimitConfig(int $limitId): array;

    public function getLimitString(int $limitId, string $key): ?string;

    public function getLimitInt(int $limitId, string $key): ?int;

    public function setLimitConfig(int $limitId, string $key, string $value): void;

    public function clearLimitConfig(int $limitId, string $key): void;

    /**
     * Global config merged with user-specific config.
     */
    public function getClientConfig(string $userId): array;

    public function getClientString(string $userId, string $key): ?string;

    public function getClientInt(string $userId, string $key): ?int;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllLimitConfigs(string $userId): array;

    /**
     * @return array<string, array<string, string>>
     */
    public function findAllUserConfigs(): array;

    /**
     * @param string[] $userIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function findAllLimitConfigsForUsers(array $userIds): array;
}
