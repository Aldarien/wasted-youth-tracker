<?php

namespace Zieren\WYT\Domain\ValueObject;

final class TimeSlot
{
    public function __construct(
        public readonly int $from,
        public readonly int $to
    ) {
    }

    public function isCurrentAt(int $timestamp): bool
    {
        return $this->from <= $timestamp && $timestamp < $this->to;
    }
}
