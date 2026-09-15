<?php

namespace Zieren\WYT\Infrastructure\Config;

use RuntimeException;

class EnvironmentConfig
{
    public function has(string $name): bool
    {
        return array_key_exists($name, $_ENV) || getenv($name) !== false;
    }

    public function getString(string $name, ?string $default = null): ?string
    {
        if (array_key_exists($name, $_ENV)) {
            $value = $_ENV[$name];
            return $value === '' && $default !== null ? $default : (string) $value;
        }

        $value = getenv($name);
        if ($value === false) {
            return $default;
        }
        return $value === '' && $default !== null ? $default : $value;
    }

    public function getStringOrFail(string $name): string
    {
        $value = $this->getString($name);
        if ($value === null) {
            throw new RuntimeException("Missing required environment variable: {$name}");
        }
        return $value;
    }

    public function getInt(string $name, ?int $default = null): ?int
    {
        $value = $this->getString($name);
        if ($value === null) {
            return $default;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException("Environment variable {$name} must be an integer, got: {$value}");
        }
        return (int) $value;
    }

    public function getPositiveInt(string $name, ?int $default = null): int
    {
        $value = $this->getInt($name, $default);
        if ($value === null) {
            throw new RuntimeException("Missing required positive integer environment variable: {$name}");
        }
        if ($value <= 0) {
            throw new RuntimeException("Environment variable {$name} must be a positive integer, got: {$value}");
        }
        return $value;
    }

    public function getBool(string $name, bool $default = false): bool
    {
        $value = $this->getString($name);
        if ($value === null) {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new RuntimeException("Environment variable {$name} must be a boolean, got: {$value}");
        }
        return $parsed;
    }
}
