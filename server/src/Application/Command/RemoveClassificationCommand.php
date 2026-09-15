<?php

namespace Zieren\WYT\Application\Command;

final class RemoveClassificationCommand implements Command
{
    public function __construct(public readonly int $classificationId)
    {
    }
}
