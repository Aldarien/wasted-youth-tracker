<?php

namespace Zieren\WYT\Application\Service\Contract;

interface RuleFromActivityServiceInterface
{
    public function createRuleFromTitle(string $className, int $priority, string $title, ?int $limitId = null): int;
}
