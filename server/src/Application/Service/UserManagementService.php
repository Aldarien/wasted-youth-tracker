<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Entity\User;
use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;
use Zieren\WYT\Domain\Repository\TransactionManagerInterface;
use Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;

class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LimitRepositoryInterface $limitRepository,
        private readonly ClassLimitMappingRepositoryInterface $classLimitMappingRepository,
        private readonly TransactionManagerInterface $transactionManager
    ) {
    }

    public function addUser(string $id): int
    {
        $limitId = $this->transactionManager->run(function () use ($id): int {
            $this->userRepository->save(new User($id));
            $limitId = $this->limitRepository->save(
                new Limit(0, $id, Defaults::TOTAL_LIMIT_NAME)
            );
            $this->userRepository->updateTotalLimit($id, $limitId);
            $this->classLimitMappingRepository->mapAllClassesToLimit($limitId);
            return $limitId;
        });
        return $limitId;
    }

    public function removeUser(string $id): void
    {
        $this->transactionManager->run(function () use ($id): void {
            $this->userRepository->delete($id);
        });
    }

    public function ackError(string $id, string $error): void
    {
        $this->userRepository->updateAckedError($id, substr($error, 0, 15));
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->userRepository->findAll();
    }

    public function getUnackedError(string $id): string
    {
        $user = $this->userRepository->findById($id);
        if (!$user || !$user->lastError) {
            return '';
        }
        if (substr($user->lastError, 0, 15) === $user->ackedError) {
            return '';
        }
        return $user->lastError;
    }
}
