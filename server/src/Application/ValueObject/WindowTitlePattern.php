<?php

namespace Zieren\WYT\Application\ValueObject;

use InvalidArgumentException;

final class WindowTitlePattern
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Title pattern cannot be empty.');
        }
        $this->value = $trimmed;
    }
}
