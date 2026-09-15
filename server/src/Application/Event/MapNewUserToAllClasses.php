<?php

namespace Zieren\WYT\Application\Event;

use Zieren\WYT\Domain\Event\UserCreated;
use Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface;

final class MapNewUserToAllClasses
{
    public function __construct(
        private readonly ClassLimitMappingRepositoryInterface $classLimitMappingRepository
    ) {
    }

    public function __invoke(UserCreated $event): void
    {
        $this->classLimitMappingRepository->mapAllClassesToLimit($event->totalLimitId);
    }
}
