<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Application\Service\Contract\ConfigManagementServiceInterface;
use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;

class ConfigManagementService implements ConfigManagementServiceInterface
{
    public function __construct(
        private readonly ConfigRepositoryInterface $configRepository
    ) {
    }

    public function setUserConfig(string $userId, string $key, string $value): void
    {
        $this->configRepository->setUserConfig($userId, $key, $value);
    }

    public function clearUserConfig(string $userId, string $key): void
    {
        $this->configRepository->clearUserConfig($userId, $key);
    }

    public function setGlobalConfig(string $key, string $value): void
    {
        $this->configRepository->setGlobalConfig($key, $value);
    }

    public function clearGlobalConfig(string $key): void
    {
        $this->configRepository->clearGlobalConfig($key);
    }
}
