<?php

use function DI\create;
use function DI\get;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

return [
    Twig::class => static function (): Twig {
        return Twig::create(__DIR__ . '/../../resources/views', [
            'cache' => false,
        ]);
    },

    LoggerInterface::class => static function (): LoggerInterface {
        $logDirectory = __DIR__ . '/../../logs';
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }
        $logger = new Logger('wasted-youth-tracker');
        $logger->pushHandler(new RotatingFileHandler(
            $logDirectory . '/application.log',
            0,
            Level::Debug
        ));
        return $logger;
    },

    \Zieren\WYT\Domain\Clock::class => create(\Zieren\WYT\Infrastructure\Clock\SystemClock::class),

    'db.host' => getenv('DB_HOST') ?: 'localhost',
    'db.name' => getenv('DB_NAME') ?: '',
    'db.user' => getenv('DB_USER') ?: '',
    'db.password' => getenv('DB_PASS') ?: '',
    'db.encoding' => 'latin1',

    \Zieren\WYT\Infrastructure\Persistence\Connection::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroConnection::class)
            ->constructor(
                get(\Psr\Log\LoggerInterface::class),
                get('db.name'),
                get('db.user'),
                get('db.password'),
                get('db.encoding'),
                get('db.host')
            ),

    \Zieren\WYT\Domain\Repository\UserRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroUserRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\TotalLimitMappingRepositoryInterface::class =>
        get(\Zieren\WYT\Domain\Repository\UserRepositoryInterface::class),

    \Zieren\WYT\Domain\Repository\LimitRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroLimitRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ClassRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroClassRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroClassificationRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroConfigRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\OverrideRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroOverrideRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Infrastructure\Persistence\MeekroActivityRepository::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroActivityRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ActivityRecordRepositoryInterface::class =>
        get(\Zieren\WYT\Infrastructure\Persistence\MeekroActivityRepository::class),

    \Zieren\WYT\Domain\Repository\ActivityQueryRepositoryInterface::class =>
        get(\Zieren\WYT\Infrastructure\Persistence\MeekroActivityRepository::class),

    \Zieren\WYT\Domain\Repository\ActivityMaintenanceRepositoryInterface::class =>
        get(\Zieren\WYT\Infrastructure\Persistence\MeekroActivityRepository::class),

    \Zieren\WYT\Domain\Repository\TransactionManagerInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\MeekroTransactionManager::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\LogPruningInterface::class =>
        create(\Zieren\WYT\Infrastructure\Logging\LogPruner::class)
            ->constructor(get(LoggerInterface::class), __DIR__ . '/../../logs'),

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
                get(\Zieren\WYT\Domain\Repository\ActivityMaintenanceRepositoryInterface::class),
                get(\Psr\Log\LoggerInterface::class),
                get(\Zieren\WYT\Domain\Repository\LogPruningInterface::class)
            ),
];
