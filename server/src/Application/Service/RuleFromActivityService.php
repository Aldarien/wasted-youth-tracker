<?php

namespace Zieren\WYT\Application\Service;

use Zieren\WYT\Application\Service\Contract\ClassManagementServiceInterface;
use Zieren\WYT\Application\Service\Contract\ClassificationManagementServiceInterface;
use Zieren\WYT\Application\Service\Contract\MappingManagementServiceInterface;
use Zieren\WYT\Application\Service\Contract\RuleFromActivityServiceInterface;

class RuleFromActivityService implements RuleFromActivityServiceInterface
{
    public function __construct(
        private readonly ClassManagementServiceInterface $classManagementService,
        private readonly ClassificationManagementServiceInterface $classificationManagementService,
        private readonly MappingManagementServiceInterface $mappingManagementService
    ) {
    }

    /**
     * Create a new app group and a window-title rule that matches the given title exactly.
     * If a limit ID is provided, the new group is also mapped to that budget.
     */
    public function createRuleFromTitle(string $className, int $priority, string $title, ?int $limitId = null): int
    {
        $classId = $this->classManagementService->addClass($className);
        $this->classificationManagementService->addClassification(
            $classId,
            $priority,
            preg_quote($title, '/')
        );
        if ($limitId !== null) {
            $this->mappingManagementService->addMapping($classId, $limitId);
        }
        return $classId;
    }
}
