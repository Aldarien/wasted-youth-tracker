<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Domain\Exception\CannotModifyDefaultClassificationException;
use Zieren\WYT\Domain\Exception\ClassificationNotFoundException;
use Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface;

class ClassificationManagementService
{
    public function __construct(
        private readonly ClassificationRepositoryInterface $classificationRepository
    ) {
    }

    public function addClassification(int $classId, int $priority, string $regex): int
    {
        return $this->classificationRepository->save(new Classification(0, $classId, $priority, $regex));
    }

    public function changeClassification(int $classificationId, string $regex, int $priority): void
    {
        if ($classificationId === Defaults::DEFAULT_CLASSIFICATION_ID) {
            throw new CannotModifyDefaultClassificationException('Cannot change default classification');
        }
        $classification = $this->classificationRepository->findById($classificationId);
        if (!$classification) {
            throw new ClassificationNotFoundException('Classification not found');
        }
        $this->classificationRepository->update(
            new Classification($classificationId, $classification->classId, $priority, $regex)
        );
    }

    public function removeClassification(int $classificationId): void
    {
        if ($classificationId === Defaults::DEFAULT_CLASSIFICATION_ID) {
            throw new CannotModifyDefaultClassificationException('Cannot delete default classification');
        }
        $this->classificationRepository->delete($classificationId);
    }
}
