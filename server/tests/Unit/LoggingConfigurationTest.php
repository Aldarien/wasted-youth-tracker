<?php

namespace Zieren\WYT\Tests\Unit;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class LoggingConfigurationTest extends TestCase
{
    private array $environment = [];

    protected function tearDown(): void
    {
        foreach ($this->environment as $name => $value) {
            if ($value === false) {
                putenv($name);
                unset($_ENV[$name]);
                continue;
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }

    public function testCreatesJsonLoggerWithFalseStderrFlag(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wyt-logs-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        $this->setEnvironment([
            'LOG_DIRECTORY' => $directory,
            'LOG_LEVEL' => 'INFO',
            'LOG_MAX_FILES' => '2',
            'LOG_STDERR' => '0',
        ]);

        $logger = $this->logger();
        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertCount(1, $logger->getHandlers());

        $logger->info('configuration test', [
            'test' => true,
            'password' => 'secret',
        ]);
        $files = glob($directory . DIRECTORY_SEPARATOR . 'application-*.log');
        $this->assertCount(1, $files);
        $record = json_decode((string) file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('configuration test', $record['message']);
        $this->assertSame(true, $record['context']['test']);
        $this->assertSame('[REDACTED]', $record['context']['password']);

        unlink($files[0]);
        rmdir($directory);
    }

    public function testRejectsInvalidLogLevel(): void
    {
        $this->setEnvironment(['LOG_LEVEL' => 'not-a-level']);

        $this->expectException(RuntimeException::class);
        ($this->definitions()[LoggerInterface::class])();
    }

    public function testRejectsInvalidMaximumFileCount(): void
    {
        $this->setEnvironment(['LOG_MAX_FILES' => '0']);

        $this->expectException(RuntimeException::class);
        ($this->definitions()[LoggerInterface::class])();
    }

    public function testRejectsInvalidStderrFlag(): void
    {
        $this->setEnvironment(['LOG_STDERR' => 'sometimes']);

        $this->expectException(RuntimeException::class);
        ($this->definitions()[LoggerInterface::class])();
    }

    public function testRejectsInvalidSlowRequestThreshold(): void
    {
        $this->setEnvironment(['LOG_SLOW_REQUEST_MS' => '0']);

        $this->expectException(RuntimeException::class);
        ($this->definitions()['http.slow_request_ms'])();
    }

    private function logger(): LoggerInterface
    {
        return ($this->definitions()[LoggerInterface::class])();
    }

    /**
     * @return array<string, mixed>
     */
    private function definitions(): array
    {
        return require dirname(__DIR__, 2) . '/bootstrap/setups/services.php';
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnvironment(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->environment[$name] ??= $_ENV[$name] ?? getenv($name);
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}
