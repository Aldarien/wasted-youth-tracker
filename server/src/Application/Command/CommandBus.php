<?php

namespace Zieren\WYT\Application\Command;

use InvalidArgumentException;

class CommandBus
{
    /** @var array<class-string<Command>, callable(Command): void> */
    private array $handlers = [];

    /**
     * @param class-string<Command> $commandClass
     * @param callable(Command): void $handler
     */
    public function register(string $commandClass, callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    public function dispatch(Command $command): void
    {
        $commandClass = $command::class;
        if (!isset($this->handlers[$commandClass])) {
            throw new InvalidArgumentException('No handler registered for command ' . $commandClass);
        }
        $this->handlers[$commandClass]($command);
    }
}
