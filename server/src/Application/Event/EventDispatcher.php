<?php

namespace Zieren\WYT\Application\Event;

use Zieren\WYT\Domain\Event\Event;

interface EventDispatcher
{
    public function dispatch(Event $event): void;
}
