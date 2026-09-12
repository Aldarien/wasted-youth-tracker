<?php

namespace Zieren\WYT\Domain\Service;

use Zieren\WYT\Domain\Exception\DomainException;
use Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface;
use Zieren\WYT\Domain\Repository\LimitRepositoryInterface;
use Zieren\WYT\Domain\ValueObject\ClassificationResult;

class ClassificationService
{
    public function __construct(
        private readonly ClassificationRepositoryInterface $classificationRepository,
        private readonly LimitRepositoryInterface $limitRepository
    ) {
    }

    /**
     * @param string[] $titles
     * @return ClassificationResult[]
     */
    public function classify(string $userId, array $titles): array
    {
        $results = [];
        foreach ($titles as $title) {
            $classification = $this->classificationRepository->findBestMatch($title);
            if ($classification === null) {
                throw new DomainException("Failed to classify '$title' (default class missing?)");
            }
            $results[] = new ClassificationResult(
                $classification->classId,
                $this->limitRepository->findLimitIdsByClassAndUser($classification->classId, $userId)
            );
        }
        return $results;
    }
}
