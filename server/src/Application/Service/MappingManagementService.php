<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;

class MappingManagementService
{
    public function __construct(
        private readonly ClassLimitMappingRepositoryInterface $mappingRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function addMapping(int $classId, int $limitId): void
    {
        $this->mappingRepository->addMapping($classId, $limitId);
    }

    public function removeMapping(int $classId, int $limitId): void
    {
        foreach ($this->userRepository->findAll() as $user) {
            if ($user->totalLimitId === $limitId) {
                return;
            }
        }
        $this->mappingRepository->removeMapping($classId, $limitId);
    }
}
