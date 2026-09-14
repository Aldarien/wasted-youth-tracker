<?php

use function DI\create;
use function DI\get;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Zieren\WYT\Infrastructure\Logging\SensitiveDataProcessor;

$env = static function (string $name): ?string {
    if (array_key_exists($name, $_ENV)) {
        return (string) $_ENV[$name];
    }

    $value = getenv($name);
    return $value === false ? null : $value;
};

return [
    Twig::class => static function (): Twig {
        return Twig::create(__DIR__ . '/../../resources/views', [
            'cache' => false,
        ]);
    },

    'log.directory' => static function () use ($env): string {
        return $env('LOG_DIRECTORY') ?: __DIR__ . '/../../logs';
    },

    'http.slow_request_ms' => static function () use ($env): int {
        $value = $env('LOG_SLOW_REQUEST_MS') ?? '1000';
        if (preg_match('/^[1-9]\d*$/', $value) !== 1) {
            throw new \RuntimeException('LOG_SLOW_REQUEST_MS must be a positive integer.');
        }
        return (int) $value;
    },

    LoggerInterface::class => static function () use ($env): LoggerInterface {
        $logDirectory = $env('LOG_DIRECTORY') ?: __DIR__ . '/../../logs';
        if (!is_dir($logDirectory)) {
            if (!mkdir($logDirectory, 0750, true) && !is_dir($logDirectory)) {
                throw new \RuntimeException('Unable to create log directory: ' . $logDirectory);
            }
        }
        $logLevelName = strtoupper($env('LOG_LEVEL') ?: 'INFO');
        try {
            $logLevel = Level::fromName($logLevelName);
        } catch (\ValueError|\UnhandledMatchError $exception) {
            throw new \RuntimeException('Invalid LOG_LEVEL: ' . $logLevelName, 0, $exception);
        }

        $maxFilesValue = $env('LOG_MAX_FILES') ?? '14';
        if (preg_match('/^[1-9]\d*$/', $maxFilesValue) !== 1) {
            throw new \RuntimeException('LOG_MAX_FILES must be a positive integer.');
        }
        $maxFiles = (int) $maxFilesValue;

        $logToStderr = filter_var(
            $env('LOG_STDERR') ?? 'true',
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        );
        if ($logToStderr === null) {
            throw new \RuntimeException('LOG_STDERR must be true or false.');
        }

        $formatter = new JsonFormatter(
            JsonFormatter::BATCH_MODE_NEWLINES,
            true,
            false,
            false
        );
        $logger = new Logger('wasted-youth-tracker');
        $logger->pushProcessor(new SensitiveDataProcessor());
        $fileHandler = new RotatingFileHandler(
            $logDirectory . '/application.log',
            $maxFiles,
            $logLevel,
            true,
            0640,
            true
        );
        $fileHandler->setFormatter($formatter);
        $logger->pushHandler($fileHandler);

        if ($logToStderr) {
            $stderrHandler = new StreamHandler('php://stderr', $logLevel);
            $stderrHandler->setFormatter($formatter);
            $logger->pushHandler($stderrHandler);
        }

        return $logger;
    },

    \Zieren\WYT\Domain\Clock::class => create(\Zieren\WYT\Infrastructure\Clock\SystemClock::class),

    'db.host' => $env('DB_HOST') ?: 'localhost',
    'db.name' => $env('DB_NAME') ?: '',
    'db.user' => $env('DB_USER') ?: '',
    'db.password' => $env('DB_PASS') ?: '',
    'db.encoding' => 'latin1',

    \Zieren\WYT\Infrastructure\Persistence\Connection::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoConnection::class)
            ->constructor(
                get(\Psr\Log\LoggerInterface::class),
                get('db.name'),
                get('db.user'),
                get('db.password'),
                get('db.encoding'),
                get('db.host')
            ),

    \Zieren\WYT\Domain\Repository\UserRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoUserRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\LimitRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoLimitRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoClassLimitMappingRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\LimitOverlapQueryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoLimitOverlapQuery::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ClassRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoClassRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoClassificationRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoConfigRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\OverrideRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoOverrideRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ActivityRecordRepositoryInterface::class =>
        get(\Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository::class),

    \Zieren\WYT\Domain\Repository\ActivityQueryRepositoryInterface::class =>
        get(\Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository::class),

    \Zieren\WYT\Domain\Repository\ActivityPruningRepositoryInterface::class =>
        get(\Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository::class),

    \Zieren\WYT\Domain\Repository\ActivityReclassificationRepositoryInterface::class =>
        get(\Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository::class),

    \Zieren\WYT\Domain\Repository\TransactionManagerInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\LogPruningInterface::class =>
        create(\Zieren\WYT\Infrastructure\Logging\LogPruner::class)
            ->constructor(get(LoggerInterface::class), get('log.directory')),

    \Zieren\WYT\Infrastructure\Persistence\DatabaseInitializer::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\DatabaseInitializer::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Application\Service\RecordActivityService::class =>
        create(\Zieren\WYT\Application\Service\RecordActivityService::class),

    \Zieren\WYT\Domain\Service\ActivityReclassificationService::class =>
        create(\Zieren\WYT\Domain\Service\ActivityReclassificationService::class),

    \Zieren\WYT\Application\Service\RxService::class =>
        create(\Zieren\WYT\Application\Service\RxService::class),

    \Zieren\WYT\Application\Service\ClientConfigService::class =>
        create(\Zieren\WYT\Application\Service\ClientConfigService::class),

    \Zieren\WYT\Application\Service\ConfigManagementService::class =>
        create(\Zieren\WYT\Application\Service\ConfigManagementService::class),

    \Zieren\WYT\Application\Service\AdminViewService::class =>
        create(\Zieren\WYT\Application\Service\AdminViewService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\UserRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ClassRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class)
            ),

    \Zieren\WYT\Application\Service\UserManagementService::class =>
        create(\Zieren\WYT\Application\Service\UserManagementService::class),

    \Zieren\WYT\Application\Service\LimitManagementService::class =>
        create(\Zieren\WYT\Application\Service\LimitManagementService::class),

    \Zieren\WYT\Application\Service\ClassManagementService::class =>
        create(\Zieren\WYT\Application\Service\ClassManagementService::class),

    \Zieren\WYT\Application\Service\ClassificationManagementService::class =>
        create(\Zieren\WYT\Application\Service\ClassificationManagementService::class),

    \Zieren\WYT\Application\Service\MappingManagementService::class =>
        create(\Zieren\WYT\Application\Service\MappingManagementService::class),

    \Zieren\WYT\Application\Service\OverrideManagementService::class =>
        create(\Zieren\WYT\Application\Service\OverrideManagementService::class),

    \Zieren\WYT\Application\Service\PruningService::class =>
        create(\Zieren\WYT\Application\Service\PruningService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\ActivityPruningRepositoryInterface::class),
                get(\Psr\Log\LoggerInterface::class),
                get(\Zieren\WYT\Domain\Repository\LogPruningInterface::class)
            ),
];
