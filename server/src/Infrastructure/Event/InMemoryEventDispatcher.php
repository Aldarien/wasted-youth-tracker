<?php

namespace Zieren\WYT\Infrastructure\Event;

use Zieren\WYT\Application\Event\EventDispatcher;
use Zieren\WYT\Domain\Event\Event;

class InMemoryEventDispatcher implements EventDispatcher
{
    /** @var array<class-string<Event>, list<callable(Event): void>> */
    private array $listeners = [];

    /**
     * @param class-string<Event> $eventClass
     * @param callable(Event): void $listener
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(Event $event): void
    {
        foreach ($this->listeners[get_class($event)] ?? [] as $listener) {
            $listener($event);
        }
    }
}
