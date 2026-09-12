<?php

namespace Zieren\WYT\Domain\Service;

use DateTimeImmutable;
use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Repository\ActivityReclassificationRepositoryInterface;
use Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface;

class ActivityReclassificationService
{
    public function __construct(
        private readonly ActivityReclassificationRepositoryInterface $activityRepository,
        private readonly ClassificationRepositoryInterface $classificationRepository
    ) {
    }

    public function reclassify(DateTimeImmutable $fromTime): void
    {
        foreach ($this->activityRepository->findTitlesAfter($fromTime) as $title) {
            $classification = $this->classificationRepository->findBestMatch($title);
            if ($classification !== null) {
                $this->activityRepository->updateClassForTitleAfter(
                    $title,
                    $classification->classId,
                    $fromTime
                );
            }
        }
    }

    public function reclassifyForRemoval(int $classId): void
    {
        foreach ($this->activityRepository->findTitlesByClass($classId) as $title) {
            $classification = $this->classificationRepository->findBestMatch($title);
            $newClassId = $classification->classId ?? Defaults::DEFAULT_CLASS_ID;
            $this->activityRepository->updateClassForTitle($title, $classId, $newClassId);
        }
    }
}
