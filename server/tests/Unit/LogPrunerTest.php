<?php

namespace Zieren\WYT\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zieren\WYT\Infrastructure\Logging\LogPruner;

final class LogPrunerTest extends TestCase
{
    public function testDeletesOnlyMatchingFilesOlderThanCutoff(): void
    {
        $directory = $this->temporaryDirectory();
        file_put_contents($directory . '/application-2024-01-01.log', '{}');
        file_put_contents($directory . '/application-2024-01-02.log', '{}');
        file_put_contents($directory . '/application-2024-01-03.log', '{}');
        file_put_contents($directory . '/application-current.log', '{}');
        file_put_contents($directory . '/unrelated.txt', '{}');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('notice')
            ->with(
                $this->stringStartsWith('log file deleted: application-')
            );

        (new LogPruner($logger, $directory))->prune(
            new DateTimeImmutable('2024-01-04T00:00:00+00:00')
        );

        $this->assertFileDoesNotExist($directory . '/application-2024-01-01.log');
        $this->assertFileDoesNotExist($directory . '/application-2024-01-02.log');
        $this->assertFileExists($directory . '/application-2024-01-03.log');
        $this->assertFileExists($directory . '/application-current.log');
        $this->assertFileExists($directory . '/unrelated.txt');

        $this->removeDirectory($directory);
    }

    public function testDoesNothingWhenDirectoryDoesNotExist(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('notice');

        (new LogPruner($logger, sys_get_temp_dir() . '/missing-wyt-log-directory'))
            ->prune(new DateTimeImmutable());

        $this->addToAssertionCount(1);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wyt-pruner-' . bin2hex(random_bytes(6));
        mkdir($directory, 0750, true);
        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
