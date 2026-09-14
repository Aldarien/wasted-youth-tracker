<?php

namespace Zieren\WYT\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class SensitiveDataProcessor implements ProcessorInterface
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'password',
        'secret',
        'token',
        'api_key',
        'db_pass',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra)
        );
    }

    /**
     * @param array<mixed> $values
     * @return array<mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_contains($normalized, '_' . $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
