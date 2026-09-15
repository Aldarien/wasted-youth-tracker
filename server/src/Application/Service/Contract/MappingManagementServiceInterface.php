<?php

namespace Zieren\WYT\Application\Service\Contract;

interface MappingManagementServiceInterface
{
    public function addMapping(int $classId, int $limitId): void;

    public function removeMapping(int $classId, int $limitId): void;
}
