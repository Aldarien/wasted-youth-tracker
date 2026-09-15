<?php

namespace Zieren\WYT\Application\Service\Contract;

use DateTimeImmutable;

interface ClassManagementServiceInterface
{
    public function addClass(string $name): int;

    public function renameClass(int $classId, string $name): void;

    public function removeClass(int $classId): void;

    public function reclassify(DateTimeImmutable $fromTime): void;
}
