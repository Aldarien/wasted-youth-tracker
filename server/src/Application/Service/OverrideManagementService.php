<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Domain\Exception\LimitNotOwnedByUserException;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;
use Zieren\WYT\Domain\Repository\LimitOverlapQueryInterface;
use Zieren\WYT\Domain\Repository\OverrideRepositoryInterface;
use Zieren\WYT\Domain\Service\SlotParser;

class OverrideManagementService
{
    public function __construct(
        private readonly OverrideRepositoryInterface $overrideRepository,
        private readonly LimitRepositoryInterface $limitRepository,
        private readonly LimitOverlapQueryInterface $overlapQuery,
        private readonly SlotParser $slotParser
    ) {
    }

    /**
     * @return string[]
     */
    public function setMinutes(string $userId, string $date, int $limitId, int $minutes): array
    {
        $this->assertLimitBelongsToUser($userId, $limitId);
        $this->overrideRepository->setMinutes($userId, $date, $limitId, $minutes);
        return $this->overlapQuery->findOverlappingLimitNames($limitId);
    }

    /**
     * @return string[]
     */
    public function setSlots(string $userId, string $date, int $limitId, string $slots): array
    {
        $this->assertLimitBelongsToUser($userId, $limitId);
        $this->slotParser->parse($slots);
        $this->overrideRepository->setSlots($userId, $date, $limitId, $slots);
        return $this->overlapQuery->findOverlappingLimitNames($limitId);
    }

    /**
     * @return string[]
     */
    public function unlock(string $userId, string $date, int $limitId): array
    {
        $this->assertLimitBelongsToUser($userId, $limitId);
        $this->overrideRepository->setUnlock($userId, $date, $limitId);
        return $this->overlapQuery->findOverlappingLimitNames($limitId, $date);
    }

    public function clearOverrides(string $userId, string $date, int $limitId): void
    {
        $this->assertLimitBelongsToUser($userId, $limitId);
        $this->overrideRepository->clear($userId, $date, $limitId);
    }

    private function assertLimitBelongsToUser(string $userId, int $limitId): void
    {
        $limit = $this->limitRepository->findById($limitId);
        if ($limit === null || $limit->userId !== $userId) {
            throw new LimitNotOwnedByUserException('Limit does not belong to user');
        }
    }
}
