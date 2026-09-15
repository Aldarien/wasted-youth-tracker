<?php

namespace Zieren\WYT\Infrastructure\Health;

use RuntimeException;
use Zieren\WYT\Infrastructure\Config\EnvironmentConfig;
use Zieren\WYT\Infrastructure\Persistence\Connection;

class StartupHealthCheck
{
    /** @var string[] */
    private const REQUIRED_ENV_VARS = [
        'DB_NAME',
        'DB_USER',
        'DB_HOST',
    ];

    public function __construct(
        private readonly EnvironmentConfig $environmentConfig,
        private readonly Connection $connection
    ) {
    }

    public function validate(): void
    {
        $unmet = [];
        foreach (self::REQUIRED_ENV_VARS as $name) {
            $value = $this->environmentConfig->getString($name);
            if ($value === null || $value === '') {
                $unmet[] = "The environment variable {$name} is missing or empty.";
            }
        }

        if ($unmet) {
            throw new RuntimeException($this->format($unmet));
        }

        try {
            $this->connection->query('SELECT 1');
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Unable to connect to the database: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * @param string[] $messages
     */
    private function format(array $messages): string
    {
        return '<p><b>The following requirements are not met:</b></p>'
            . '<ul><li>' . implode('</li><li>', $messages) . '</li></ul><hr />';
    }
}
