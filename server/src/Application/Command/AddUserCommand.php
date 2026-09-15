<?php

namespace Zieren\WYT\Application\Command;

final class AddUserCommand implements Command
{
    public function __construct(public readonly string $userId)
    {
    }
}
