<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Application\Event\EventDispatcher;
use Zieren\WYT\Application\Service\Contract\UserManagementServiceInterface;
use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Entity\User;
use Zieren\WYT\Domain\Event\UserCreated;
use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;
use Zieren\WYT\Domain\Repository\TransactionManagerInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;

class UserManagementService implements UserManagementServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LimitRepositoryInterface $limitRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ConfigRepositoryInterface $configRepository,
        private readonly EventDispatcher $eventDispatcher
    ) {
    }

    public function addUser(string $id): int
    {
        $limitId = $this->transactionManager->run(function () use ($id): int {
            $totalLimitName = $this->configRepository->getGlobalConfigValue(
                Defaults::TOTAL_LIMIT_NAME_CONFIG_KEY
            ) ?? Defaults::TOTAL_LIMIT_NAME;

            $this->userRepository->save(new User($id));
            $limitId = $this->limitRepository->save(
                new Limit(0, $id, $totalLimitName)
            );
            $this->userRepository->updateTotalLimit($id, $limitId);

            $this->eventDispatcher->dispatch(new UserCreated($id, $limitId));

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
