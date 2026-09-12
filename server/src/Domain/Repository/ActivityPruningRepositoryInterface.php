<?php

namespace Zieren\WYT\Domain\Repository;

use DateTimeImmutable;

interface ActivityPruningRepositoryInterface
{
    public function prune(DateTimeImmutable $before): void;
}
