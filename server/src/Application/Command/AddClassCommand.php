<?php

namespace Zieren\WYT\Application\Command;

use Zieren\WYT\Application\ValueObject\ClassName;

final class AddClassCommand implements Command
{
    public function __construct(public readonly ClassName $name)
    {
    }
}
