<?php

namespace Zieren\WYT\Application\Service;

use DateTimeImmutable;
use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Exception\CannotModifyDefaultClassException;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;
use Zieren\WYT\Domain\Repository\ClassRepositoryInterface;
use Zieren\WYT\Domain\Repository\TransactionManagerInterface;
use Zieren\WYT\Domain\Repository\UserRepositoryInterface;
use Zieren\WYT\Domain\Service\ActivityReclassificationService;
use Zieren\WYT\Domain\Defaults;

class ClassManagementService
{
    public function __construct(
        private readonly ClassRepositoryInterface $classRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LimitRepositoryInterface $limitRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ActivityReclassificationService $activityReclassificationService
    ) {
    }

    public function addClass(string $name): int
    {
        return $this->transactionManager->run(function () use ($name): int {
            $classId = $this->classRepository->save(new ActivityClass(0, $name));
            $mappedLimitIds = $this->limitRepository->findLimitIdsByClass($classId);
            foreach ($this->userRepository->findAll() as $user) {
                if ($user->totalLimitId !== null
                    && !in_array($user->totalLimitId, $mappedLimitIds, true)
                ) {
                    $this->limitRepository->addMapping($classId, $user->totalLimitId);
                }
            }
            return $classId;
        });
    }

    public function renameClass(int $classId, string $name): void
    {
        if ($classId === Defaults::DEFAULT_CLASS_ID) {
            throw new CannotModifyDefaultClassException(
                'Cannot rename default class "' . Defaults::DEFAULT_CLASS_NAME . '"'
            );
        }
        $this->classRepository->rename($classId, $name);
    }

    public function removeClass(int $classId): void
    {
        if ($classId === Defaults::DEFAULT_CLASS_ID) {
            throw new CannotModifyDefaultClassException(
                'Cannot delete default class "' . Defaults::DEFAULT_CLASS_NAME . '"'
            );
        }
        $this->transactionManager->run(function () use ($classId): void {
            $this->activityReclassificationService->reclassifyForRemoval($classId);
            $this->classRepository->delete($classId);
        });
    }

    public function reclassify(DateTimeImmutable $fromTime): void
    {
        $this->activityReclassificationService->reclassify($fromTime);
    }
}
