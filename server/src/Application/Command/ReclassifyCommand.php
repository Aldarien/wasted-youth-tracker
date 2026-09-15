<?php

namespace Zieren\WYT\Application\Command;

use DateTimeImmutable;

final class ReclassifyCommand implements Command
{
    public function __construct(public readonly DateTimeImmutable $from)
    {
    }
}
