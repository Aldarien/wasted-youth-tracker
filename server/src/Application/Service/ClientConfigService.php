<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;

class ClientConfigService
{
    public function __construct(
        private readonly ConfigRepositoryInterface $configRepository
    ) {
    }

    public function getConfig(string $userId): string
    {
        $config = $this->configRepository->getClientConfig($userId);
        $response = [];
        foreach ($config as $k => $v) {
            $response[] = $k;
            $response[] = $v;
        }
        return implode("\n", $response);
    }
}
