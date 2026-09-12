<?php

namespace Zieren\WYT\Application\Service;

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
        private readonly ConfigRepositoryInterface $configRepository
    ) {
    }

    /**
     * @return array{
     *     users: list<\Zieren\WYT\Domain\Entity\User>,
     *     classes: list<\Zieren\WYT\Domain\Entity\ActivityClass>,
     *     classifications: array<int, list<\Zieren\WYT\Domain\Entity\Classification>>,
     *     globalConfig: array<string, string>,
     *     userConfig: array<string, array<string, string>>,
     *     limits: array<string, array<int, array<string, mixed>>>
     * }
     */
    public function getPageData(): array
    {
        $users = $this->userRepository->findAll();
        $classes = $this->classRepository->findAll();
        $classifications = $this->classificationRepository->findAllGroupedByClassId();
        $userIds = array_map(fn (User $user): string => $user->id, $users);
        $userConfig = $this->configRepository->findAllUserConfigs();
        $limits = $this->configRepository->findAllLimitConfigsForUsers($userIds);

        return [
            'users' => $users,
            'classes' => $classes,
            'classifications' => $classifications,
            'globalConfig' => $this->configRepository->getGlobalConfig(),
            'userConfig' => $userConfig,
            'limits' => $limits,
        ];
    }
}
