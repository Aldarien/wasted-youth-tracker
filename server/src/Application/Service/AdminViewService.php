<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Application\Query\DashboardQueryService;
use Zieren\WYT\Application\ViewModel\AdminPageView;
use Zieren\WYT\Domain\Entity\User;
use Zieren\WYT\Domain\Repository\ClassRepositoryInterface;
use Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface;
use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;

class AdminViewService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ClassRepositoryInterface $classRepository,
        private readonly ClassificationRepositoryInterface $classificationRepository,
        private readonly ConfigRepositoryInterface $configRepository,
        private readonly DashboardQueryService $dashboardQueryService
    ) {
    }

    public function getPageData(): AdminPageView
    {
        $users = $this->userRepository->findAll();
        $classes = $this->classRepository->findAll();
        $classifications = $this->classificationRepository->findAllGroupedByClassId();
        $userIds = array_map(fn (User $user): string => $user->id, $users);
        $userConfig = $this->configRepository->findAllUserConfigs();
        $limits = $this->configRepository->findAllLimitConfigsForUsers($userIds);

        return new AdminPageView(
            $users,
            $classes,
            $classifications,
            $this->configRepository->getGlobalConfig(),
            $userConfig,
            $limits,
            $this->dashboardQueryService->getView($users)
        );
    }
}
