<?php

namespace Zieren\WYT\Domain\Repository;

interface TotalLimitMappingRepositoryInterface
{
    public function mapAllClassesToLimit(int $limitId): void;
}
