<?php

namespace Zieren\WYT\Application\Service;

use DateTimeImmutable;
use Zieren\WYT\Domain\Clock;
use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;
use Zieren\WYT\Domain\Repository\OverrideRepositoryInterface;
use Zieren\WYT\Domain\Service\TimeCalculationService;

class RxService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly RecordActivityService $recordActivityService,
        private readonly TimeCalculationService $timeCalculationService,
        private readonly \Zieren\WYT\Domain\Repository\ActivityQueryRepositoryInterface $activityRepository,
        private readonly ConfigRepositoryInterface $configRepository,
        private readonly LimitRepositoryInterface $limitRepository,
        private readonly OverrideRepositoryInterface $overrideRepository
    ) {
    }

    public function handle(string $userId, string $lastError, array $titles): string
    {
        $classifications = $this->recordActivityService->execute($userId, $lastError, $titles);

        $timeLeftByLimit = $this->computeTimeLeftForUser($userId);

        return $this->buildResponse($userId, $classifications, $timeLeftByLimit);
    }

    /**
     * @return array<int, \Zieren\WYT\Domain\ValueObject\TimeLeft>
     */
    private function computeTimeLeftForUser(string $userId): array
    {
        $limits = $this->limitRepository->findByUser($userId);
        $overrides = $this->overrideRepository->findByUserForDate(
            $userId,
            $this->clock->now()->format('Y-m-d')
        );
        $rawRows = $this->activityRepository->queryTimeSpentByLimitAndDate(
            $userId,
            $this->getWeekStart($this->clock->now()),
            null
        );
        $timeSpentByLimitAndDate = $this->timeCalculationService->computeTimeSpentByLimitAndDate($rawRows);

        $timeLeftByLimit = [];
        foreach ($limits as $limit) {
            $config = $this->configRepository->getLimitConfig($limit->id);
            $config['name'] = $limit->name;
            $config['is_total'] = false;
            $timeLeftByLimit[$limit->id] = $this->timeCalculationService->computeTimeLeftToday(
                $config,
                $overrides[$limit->id] ?? [],
                $timeSpentByLimitAndDate[$limit->id] ?? []
            );
        }
        ksort($timeLeftByLimit, SORT_NUMERIC);
        return $timeLeftByLimit;
    }

    private function getWeekStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = ((int) $date->format('w') + 6) % 7;
        return $date->setTime(0, 0)->modify("-{$dayOfWeek} days");
    }

    /**
     * @param \Zieren\WYT\Domain\ValueObject\ClassificationResult[] $classifications
     * @param array<int, \Zieren\WYT\Domain\ValueObject\TimeLeft> $timeLeftByLimit
     */
    private function buildResponse(string $userId, array $classifications, array $timeLeftByLimit): string
    {
        $limits = $this->limitRepository->findByUser($userId);
        $limitNames = [];
        foreach ($limits as $limit) {
            $limitNames[$limit->id] = $limit->name;
        }

        $response = [];
        foreach ($timeLeftByLimit as $limitId => $timeLeft) {
            $response[] = $limitId . ';' . $timeLeft->toClientResponse() . ';' . ($limitNames[$limitId] ?? '');
        }
        $response[] = '';

        foreach ($classifications as $classification) {
            $response[] = implode(',', $classification->limitIds);
        }

        return implode("\n", $response);
    }
}
