<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;

class MappingManagementService
{
    public function __construct(
        private readonly LimitRepositoryInterface $limitRepository
    ) {
    }

    public function addMapping(int $classId, int $limitId): void
    {
        $this->limitRepository->addMapping($classId, $limitId);
    }

    public function removeMapping(int $classId, int $limitId): void
    {
        $this->limitRepository->removeMapping($classId, $limitId);
    }
}
