<?php

namespace Zieren\WYT\Infrastructure\Logging;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Zieren\WYT\Domain\Repository\LogPruningInterface;

class LogPruner implements LogPruningInterface
{
    private const LOG_PATTERN = '/^application-(\d{4}-\d{2}-\d{2})\.log$/';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $logDir = ''
    ) {
    }

    public function prune(DateTimeImmutable $before): void
    {
        if ($this->logDir === '') {
            return;
        }
        $logfiles = is_dir($this->logDir) ? scandir($this->logDir) : false;
        if ($logfiles === false) {
            return;
        }
        foreach ($logfiles as $file) {
            if (preg_match(self::LOG_PATTERN, $file, $matches)) {
                $fileDate = (new DateTimeImmutable())->setTimestamp(strtotime($matches[1]));
                $fileDate = $fileDate->modify('+1 day');
                if ($fileDate->getTimestamp() < $before->getTimestamp()) {
                    if (unlink($this->logDir . '/' . $file)) {
                        $this->logger->notice('log file deleted: ' . $file);
                    } else {
                        $this->logger->error('log file could not be deleted', [
                            'file' => $file,
                        ]);
                    }
                }
            }
        }
    }
}
