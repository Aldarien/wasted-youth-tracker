<?php

namespace Zieren\WYT\Application\Service\Contract;

interface ConfigManagementServiceInterface
{
    public function setUserConfig(string $userId, string $key, string $value): void;

    public function clearUserConfig(string $userId, string $key): void;

    public function setGlobalConfig(string $key, string $value): void;

    public function clearGlobalConfig(string $key): void;
}
