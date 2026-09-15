<?php

namespace Zieren\WYT\Application\Service\Contract;

interface ClassificationManagementServiceInterface
{
    public function addClassification(int $classId, int $priority, string $regex): int;

    public function changeClassification(int $classificationId, string $regex, int $priority): void;

    public function removeClassification(int $classificationId): void;
}
