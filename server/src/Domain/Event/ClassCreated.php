<?php

namespace Zieren\WYT\Domain\Event;

final class ClassCreated implements Event
{
    public function __construct(public readonly int $classId)
    {
    }
}
