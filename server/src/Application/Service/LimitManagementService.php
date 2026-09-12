<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Exception\LimitNotOwnedByUserException;
use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;
use Zieren\WYT\Domain\Service\SlotParser;

class LimitManagementService
{
    public function __construct(
        private readonly LimitRepositoryInterface $limitRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ConfigRepositoryInterface $configRepository,
        private readonly SlotParser $slotParser
    ) {
    }

    public function addLimit(string $userId, string $name): int
    {
        return $this->limitRepository->save(new Limit(0, $userId, $name));
    }

    public function renameLimit(string $userId, int $limitId, string $name): void
    {
        $this->assertLimitBelongsToUser($userId, $limitId);
        if ($this->isTotalLimit($limitId)) {
            return;
        }
        $this->limitRepository->rename($limitId, $name);
    }

    public function removeLimit(string $userId, int $limitId): void
    {
        $this->assertLimitBelongsToUser($userId, $limitId);
        if ($this->isTotalLimit($limitId)) {
            return;
        }
        $this->limitRepository->delete($limitId);
    }

    public function setLimitConfig(string $userId, int $limitId, string $key, string $value): void
    {
        $this->assertLimitBelongsToUser($userId, $limitId);
        if (preg_match('/^times(_(mon|tue|wed|thu|fri|sat|sun))?$/', $key)) {
            $this->slotParser->parse($value);
        }
        $this->configRepository->setLimitConfig($limitId, $key, $value);
    }

    public function clearLimitConfig(string $userId, int $limitId, string $key): void
    {
        $this->assertLimitBelongsToUser($userId, $limitId);
        $this->configRepository->clearLimitConfig($limitId, $key);
    }

    private function assertLimitBelongsToUser(string $userId, int $limitId): void
    {
        $limit = $this->limitRepository->findById($limitId);
        if ($limit === null || $limit->userId !== $userId) {
            throw new LimitNotOwnedByUserException('Limit does not belong to user');
        }
    }

    private function isTotalLimit(int $limitId): bool
    {
        foreach ($this->userRepository->findAll() as $user) {
            if ($user->totalLimitId === $limitId) {
                return true;
            }
        }
        return false;
    }
}
