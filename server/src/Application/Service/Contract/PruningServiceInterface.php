<?php

namespace Zieren\WYT\Application\Service\Contract;

use DateTimeImmutable;

interface PruningServiceInterface
{
    public function prune(DateTimeImmutable $before): void;
}
