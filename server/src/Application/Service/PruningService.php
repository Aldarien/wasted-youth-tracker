<?php

namespace Zieren\WYT\Application\Service;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Zieren\WYT\Domain\Repository\ActivityMaintenanceRepositoryInterface;
use Zieren\WYT\Domain\Repository\LogPruningInterface;

class PruningService
{
    public function __construct(
        private readonly ActivityMaintenanceRepositoryInterface $activityRepository,
        private readonly LoggerInterface $logger,
        private readonly LogPruningInterface $logPruner
    ) {
    }

    public function prune(DateTimeImmutable $before): void
    {
        $this->logger->notice('prune timestamp: ' . $before->getTimestamp());
        $this->activityRepository->prune($before);
        $this->logger->notice('tables pruned up to ' . $before->format(DateTimeImmutable::ATOM));

        $this->logPruner->prune($before);
    }
}
