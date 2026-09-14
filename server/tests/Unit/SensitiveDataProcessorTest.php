<?php

namespace Zieren\WYT\Tests\Unit;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Zieren\WYT\Infrastructure\Logging\SensitiveDataProcessor;

final class SensitiveDataProcessorTest extends TestCase
{
    public function testRedactsSensitiveContextAndExtraValuesRecursively(): void
    {
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'test',
            Level::Info,
            'request',
            [
                'username' => 'user',
                'password' => 'secret',
                'nested' => ['authorization' => 'Bearer secret'],
            ],
            ['db_pass' => 'secret']
        );

        $processed = (new SensitiveDataProcessor())($record);

        $this->assertSame('user', $processed->context['username']);
        $this->assertSame('[REDACTED]', $processed->context['password']);
        $this->assertSame('[REDACTED]', $processed->context['nested']['authorization']);
        $this->assertSame('[REDACTED]', $processed->extra['db_pass']);
    }
}
