<?php

namespace Zieren\WYT\Application\Command;

final class RemoveClassCommand implements Command
{
    public function __construct(public readonly int $classId)
    {
    }
}
