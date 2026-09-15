<?php

namespace Zieren\WYT\Application\Command;

final class RemoveUserCommand implements Command
{
    public function __construct(public readonly string $userId)
    {
    }
}
