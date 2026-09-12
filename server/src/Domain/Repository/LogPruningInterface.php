<?php

namespace Zieren\WYT\Domain\Repository;

use DateTimeImmutable;

interface LogPruningInterface
{
    public function prune(DateTimeImmutable $before): void;
}
