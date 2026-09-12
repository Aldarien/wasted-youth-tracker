<?php

namespace Zieren\WYT\Application\Service;

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
        $classifications = [];
        foreach ($classes as $class) {
            $classifications[$class->id] = $this->classificationRepository->findByClassId($class->id);
        }

        $userConfig = [];
        $limits = [];
        foreach ($users as $user) {
            $userConfig[$user->id] = $this->configRepository->getUserConfig($user->id);
            $limits[$user->id] = $this->configRepository->findAllLimitConfigs($user->id);
        }

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
