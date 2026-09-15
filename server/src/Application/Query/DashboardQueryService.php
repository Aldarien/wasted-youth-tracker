<?php

namespace Zieren\WYT\Application\Query;

use Zieren\WYT\Application\ViewModel\BudgetStatusView;
use Zieren\WYT\Application\ViewModel\ChildStatusView;
use Zieren\WYT\Application\ViewModel\DashboardView;
use Zieren\WYT\Application\ViewModel\SetupProgressView;
use Zieren\WYT\Domain\Clock;
use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Entity\User;
use Zieren\WYT\Domain\Repository\ActivityQueryRepositoryInterface;
use Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface;
use Zieren\WYT\Domain\Repository\ClassRepositoryInterface;
use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;
use Zieren\WYT\Domain\Repository\OverrideRepositoryInterface;
use Zieren\WYT\Domain\Service\TimeCalculationService;

class DashboardQueryService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly ActivityQueryRepositoryInterface $activityRepository,
        private readonly ClassRepositoryInterface $classRepository,
        private readonly LimitRepositoryInterface $limitRepository,
        private readonly ConfigRepositoryInterface $configRepository,
        private readonly OverrideRepositoryInterface $overrideRepository,
        private readonly ClassLimitMappingRepositoryInterface $mappingRepository,
        private readonly TimeCalculationService $timeCalculationService
    ) {
    }

    /**
     * @param User[] $users
     */
    public function getView(array $users): DashboardView
    {
        $to = $this->clock->now();
        $from = $to->modify('-1 day');
        $todayStart = $to->setTime(0, 0);

        $recentActivity = [];
        $unclassified = [];
        $totalLimitCount = 0;
        $hasBudget = false;
        $hasAppGroup = false;
        $hasMapping = false;
        $children = [];

        $classes = $this->classRepository->findAll();
        foreach ($classes as $class) {
            if ($class->id !== Defaults::DEFAULT_CLASS_ID) {
                $hasAppGroup = true;
                if ($this->mappingRepository->findLimitIdsByClass($class->id)) {
                    $hasMapping = true;
                }
            }
        }

        foreach ($users as $user) {
            $limits = $this->limitRepository->findByUser($user->id);
            $budgets = [];
            $hasUserTotalLimit = false;

            foreach ($limits as $limit) {
                if ($limit->id === $user->totalLimitId) {
                    $hasUserTotalLimit = true;
                } else {
                    $hasBudget = true;
                }
                $budgets[] = $this->buildBudget($user->id, $limit, $todayStart);
            }

            if ($hasUserTotalLimit) {
                $totalLimitCount++;
            }

            $children[$user->id] = new ChildStatusView(
                $this->findLastSeen($user->id, $from, $to),
                $this->getUnackedError($user),
                $budgets
            );

            $rows = $this->activityRepository->queryTitleSequence($user->id, $from, $to);
            foreach (array_slice($rows, 0, 10) as $row) {
                $recentActivity[] = [
                    'user' => $user->id,
                    'from' => $row[0],
                    'to' => $row[1],
                    'class' => $row[2],
                    'title' => $row[3],
                ];
            }

            foreach ($this->activityRepository->queryTopUnclassified($user->id, $from, $to, false, 5) as $row) {
                $unclassified[] = [
                    'user' => $user->id,
                    'seconds' => $row[0],
                    'title' => $row[1],
                    'lastSeen' => $row[2],
                ];
            }
        }

        return new DashboardView(
            new SetupProgressView(
                count($users) > 0,
                $hasBudget,
                $hasAppGroup,
                $hasMapping
            ),
            count($users),
            count($classes),
            $totalLimitCount,
            $recentActivity,
            $unclassified,
            $children
        );
    }

    private function getUnackedError(User $user): string
    {
        if (!$user->lastError) {
            return '';
        }
        if (substr($user->lastError, 0, 15) === $user->ackedError) {
            return '';
        }
        return $user->lastError;
    }

    private function findLastSeen(string $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): ?string
    {
        $rows = $this->activityRepository->queryTitleSequence($userId, $from, $to);
        if (!$rows) {
            return null;
        }
        return $rows[0][1]; // to_ts of the most recent record
    }

    private function buildBudget(string $userId, Limit $limit, \DateTimeImmutable $todayStart): BudgetStatusView
    {
        $overrides = $this->overrideRepository->findByUserForDate(
            $userId,
            $this->clock->now()->format('Y-m-d')
        );
        $rawRows = $this->activityRepository->queryTimeSpentByLimitAndDate(
            $userId,
            $todayStart,
            null
        );
        $timeSpentByLimitAndDate = $this->timeCalculationService->computeTimeSpentByLimitAndDate($rawRows);
        $config = $this->configRepository->getLimitConfig($limit->id);
        $config['name'] = $limit->name;
        $config['is_total'] = false;
        $timeLeft = $this->timeCalculationService->computeTimeLeftToday(
            $config,
            $overrides[$limit->id] ?? [],
            $timeSpentByLimitAndDate[$limit->id] ?? []
        );

        $noLimit = !$timeLeft->isLocked() && $timeLeft->totalSeconds() === PHP_INT_MAX;

        return new BudgetStatusView(
            $limit->name,
            $noLimit ? 0 : $timeLeft->currentSeconds(),
            $timeLeft->isLocked(),
            $noLimit
        );
    }
}
