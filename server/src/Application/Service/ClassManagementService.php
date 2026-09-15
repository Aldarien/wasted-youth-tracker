<?php

namespace Zieren\WYT\Application\Service;

use DateTimeImmutable;
use Zieren\WYT\Application\Event\EventDispatcher;
use Zieren\WYT\Application\Service\Contract\ClassManagementServiceInterface;
use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Event\ClassCreated;
use Zieren\WYT\Domain\Exception\CannotModifyDefaultClassException;
use Zieren\WYT\Domain\Repository\ClassRepositoryInterface;
use Zieren\WYT\Domain\Repository\TransactionManagerInterface;
use Zieren\WYT\Domain\Service\ActivityReclassificationService;

class ClassManagementService implements ClassManagementServiceInterface
{
    public function __construct(
        private readonly ClassRepositoryInterface $classRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ActivityReclassificationService $activityReclassificationService,
        private readonly EventDispatcher $eventDispatcher
    ) {
    }

    public function addClass(string $name): int
    {
        return $this->transactionManager->run(function () use ($name): int {
            $classId = $this->classRepository->save(new ActivityClass(0, $name));
            $this->eventDispatcher->dispatch(new ClassCreated($classId));
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
