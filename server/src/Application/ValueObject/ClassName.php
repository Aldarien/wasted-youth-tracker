<?php

namespace Zieren\WYT\Application\ValueObject;

use InvalidArgumentException;

final class ClassName
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Class name cannot be empty.');
        }
        $this->value = $trimmed;
    }
}
