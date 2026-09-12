<?php

namespace Zieren\WYT\Domain\Repository;

interface ClassLimitMappingRepositoryInterface
{
    public function addMapping(int $classId, int $limitId): void;

    public function removeMapping(int $classId, int $limitId): void;

    /**
     * @return int[]
     */
    public function findLimitIdsByClass(int $classId): array;

    /**
     * @return int[]
     */
    public function findLimitIdsByClassAndUser(int $classId, string $userId): array;

    public function mapAllClassesToLimit(int $limitId): void;
}
