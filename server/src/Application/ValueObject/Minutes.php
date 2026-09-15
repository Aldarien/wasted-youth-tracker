<?php

namespace Zieren\WYT\Application\ValueObject;

use InvalidArgumentException;

final class Minutes
{
    public readonly int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Minutes must be zero or positive.');
        }
        $this->value = $value;
    }
}
