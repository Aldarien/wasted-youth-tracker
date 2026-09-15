<?php

namespace Zieren\WYT\Application\Command;

use DateTimeImmutable;

final class PruneCommand implements Command
{
    public function __construct(public readonly DateTimeImmutable $date)
    {
    }
}
