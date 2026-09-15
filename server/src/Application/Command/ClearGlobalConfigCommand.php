<?php

namespace Zieren\WYT\Application\Command;

final class ClearGlobalConfigCommand implements Command
{
    public function __construct(public readonly string $key)
    {
    }
}
