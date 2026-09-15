<?php

namespace Zieren\WYT\Application\Event;

use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Event\UserCreated;
use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;

final class ApplyTotalLimitDefaultConfig
{
    public function __construct(
        private readonly ConfigRepositoryInterface $configRepository
    ) {
    }

    public function __invoke(UserCreated $event): void
    {
        $value = $this->configRepository->getGlobalConfigValue(
            Defaults::TOTAL_LIMIT_MINUTES_DAY_CONFIG_KEY
        );
        if ($value !== null) {
            $this->configRepository->setLimitConfig(
                $event->totalLimitId,
                Defaults::TOTAL_LIMIT_MINUTES_DAY_CONFIG_KEY,
                $value
            );
        }
    }
}
