<?php

namespace Zieren\WYT\Application\Event;

use Zieren\WYT\Domain\Event\ClassCreated;
use Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;

final class MapNewClassToTotalLimits
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ClassLimitMappingRepositoryInterface $classLimitMappingRepository
    ) {
    }

    public function __invoke(ClassCreated $event): void
    {
        foreach ($this->userRepository->findAll() as $user) {
            if ($user->totalLimitId !== null) {
                $mappedLimitIds = $this->classLimitMappingRepository->findLimitIdsByClass($event->classId);
                if (!in_array($user->totalLimitId, $mappedLimitIds, true)) {
                    $this->classLimitMappingRepository->addMapping($event->classId, $user->totalLimitId);
                }
            }
        }
    }
}
