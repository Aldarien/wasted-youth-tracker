<?php

use function DI\create;
use function DI\get;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Zieren\WYT\Infrastructure\Config\EnvironmentConfig;
use Zieren\WYT\Infrastructure\Logging\SensitiveDataProcessor;

return [
    EnvironmentConfig::class => create(EnvironmentConfig::class),

    \Zieren\WYT\Infrastructure\Health\StartupHealthCheck::class =>
        create(\Zieren\WYT\Infrastructure\Health\StartupHealthCheck::class)
            ->constructor(
                get(EnvironmentConfig::class),
                get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)
            ),

    Twig::class => static function (): Twig {
        return Twig::create(__DIR__ . '/../../resources/views', [
            'cache' => false,
        ]);
    },

    'log.directory' => static function (EnvironmentConfig $config): string {
        return $config->getString('LOG_DIRECTORY') ?? __DIR__ . '/../../logs';
    },

    'http.slow_request_ms' => static function (EnvironmentConfig $config): int {
        return $config->getPositiveInt('LOG_SLOW_REQUEST_MS', 1000);
    },

    LoggerInterface::class => static function (EnvironmentConfig $config): LoggerInterface {
        $logDirectory = $config->getString('LOG_DIRECTORY') ?? __DIR__ . '/../../logs';
        if (!is_dir($logDirectory)) {
            if (!mkdir($logDirectory, 0750, true) && !is_dir($logDirectory)) {
                throw new \RuntimeException('Unable to create log directory: ' . $logDirectory);
            }
        }

        $logLevelName = strtoupper($config->getString('LOG_LEVEL') ?? 'INFO');
        try {
            $logLevel = Level::fromName($logLevelName);
        } catch (\ValueError|\UnhandledMatchError $exception) {
            throw new \RuntimeException('Invalid LOG_LEVEL: ' . $logLevelName, 0, $exception);
        }

        $maxFiles = $config->getPositiveInt('LOG_MAX_FILES', 14);
        $logToStderr = $config->getBool('LOG_STDERR', true);

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
            null,
            true
        );
        $fileHandler->setFormatter($formatter);
        $logger->pushHandler(new WhatFailureGroupHandler([$fileHandler]));

        if ($logToStderr) {
            $stderrHandler = new StreamHandler('php://stderr', $logLevel);
            $stderrHandler->setFormatter($formatter);
            $logger->pushHandler($stderrHandler);
        }

        return $logger;
    },

    \Zieren\WYT\Domain\Clock::class => create(\Zieren\WYT\Infrastructure\Clock\SystemClock::class),

    'db.host' => static fn (EnvironmentConfig $config) => $config->getString('DB_HOST') ?? 'localhost',
    'db.name' => static fn (EnvironmentConfig $config) => $config->getString('DB_NAME') ?? '',
    'db.user' => static fn (EnvironmentConfig $config) => $config->getString('DB_USER') ?? '',
    'db.password' => static fn (EnvironmentConfig $config) => $config->getString('DB_PASS') ?? '',
    'db.encoding' => 'latin1',

    \Zieren\WYT\Infrastructure\Persistence\Connection::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoConnection::class)
            ->constructor(
                get(LoggerInterface::class),
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

    \Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoConfigRepository::class)
            ->constructor(get(\Zieren\WYT\Infrastructure\Persistence\Connection::class)),

    \Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface::class =>
        create(\Zieren\WYT\Infrastructure\Persistence\PdoClassificationRepository::class)
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

    \Zieren\WYT\Domain\Service\SlotParser::class =>
        create(\Zieren\WYT\Domain\Service\SlotParser::class)
            ->constructor(get(\Zieren\WYT\Domain\Clock::class)),

    \Zieren\WYT\Domain\Service\ClassificationService::class =>
        create(\Zieren\WYT\Domain\Service\ClassificationService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface::class)
            ),

    \Zieren\WYT\Domain\Service\TimeCalculationService::class =>
        create(\Zieren\WYT\Domain\Service\TimeCalculationService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Clock::class),
                get(\Zieren\WYT\Domain\Service\SlotParser::class)
            ),

    \Zieren\WYT\Domain\Service\ActivityReclassificationService::class =>
        create(\Zieren\WYT\Domain\Service\ActivityReclassificationService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\ActivityReclassificationRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface::class)
            ),

    \Zieren\WYT\Application\Service\RecordActivityService::class =>
        create(\Zieren\WYT\Application\Service\RecordActivityService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Clock::class),
                get(\Zieren\WYT\Domain\Service\ClassificationService::class),
                get(\Zieren\WYT\Domain\Repository\ActivityRecordRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\UserRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\TransactionManagerInterface::class)
            ),

    \Zieren\WYT\Application\Service\RxService::class =>
        create(\Zieren\WYT\Application\Service\RxService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Clock::class),
                get(\Zieren\WYT\Application\Service\RecordActivityService::class),
                get(\Zieren\WYT\Domain\Service\TimeCalculationService::class),
                get(\Zieren\WYT\Domain\Repository\ActivityQueryRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\LimitRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\OverrideRepositoryInterface::class)
            ),

    \Zieren\WYT\Application\Service\ClientConfigService::class =>
        create(\Zieren\WYT\Application\Service\ClientConfigService::class)
            ->constructor(get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class)),

    \Zieren\WYT\Application\Service\ConfigManagementService::class =>
        create(\Zieren\WYT\Application\Service\ConfigManagementService::class)
            ->constructor(get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class)),

    \Zieren\WYT\Application\Service\RuleFromActivityService::class =>
        create(\Zieren\WYT\Application\Service\RuleFromActivityService::class)
            ->constructor(
                get(\Zieren\WYT\Application\Service\Contract\ClassManagementServiceInterface::class),
                get(\Zieren\WYT\Application\Service\Contract\ClassificationManagementServiceInterface::class),
                get(\Zieren\WYT\Application\Service\Contract\MappingManagementServiceInterface::class)
            ),

    \Zieren\WYT\Application\Query\DashboardQueryService::class =>
        create(\Zieren\WYT\Application\Query\DashboardQueryService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Clock::class),
                get(\Zieren\WYT\Domain\Repository\ActivityQueryRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ClassRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\LimitRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\OverrideRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Service\TimeCalculationService::class)
            ),

    \Zieren\WYT\Application\Service\AdminViewService::class =>
        create(\Zieren\WYT\Application\Service\AdminViewService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\UserRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ClassRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class),
                get(\Zieren\WYT\Application\Query\DashboardQueryService::class)
            ),

    \Zieren\WYT\Application\Event\EventDispatcher::class =>
        get(\Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher::class),

    \Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher::class =>
        \DI\factory(static function (
            \Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface $classLimitMappingRepository,
            \Zieren\WYT\Domain\Repository\ConfigRepositoryInterface $configRepository,
            \Zieren\WYT\Domain\Repository\UserRepositoryInterface $userRepository
        ): \Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher {
            $dispatcher = new \Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher();
            $dispatcher->listen(
                \Zieren\WYT\Domain\Event\UserCreated::class,
                new \Zieren\WYT\Application\Event\MapNewUserToAllClasses($classLimitMappingRepository)
            );
            $dispatcher->listen(
                \Zieren\WYT\Domain\Event\UserCreated::class,
                new \Zieren\WYT\Application\Event\ApplyTotalLimitDefaultConfig($configRepository)
            );
            $dispatcher->listen(
                \Zieren\WYT\Domain\Event\ClassCreated::class,
                new \Zieren\WYT\Application\Event\MapNewClassToTotalLimits($userRepository, $classLimitMappingRepository)
            );
            return $dispatcher;
        }),

    \Zieren\WYT\Application\Service\UserManagementService::class =>
        create(\Zieren\WYT\Application\Service\UserManagementService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\UserRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\LimitRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\TransactionManagerInterface::class),
                get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class),
                get(\Zieren\WYT\Application\Event\EventDispatcher::class)
            ),

    \Zieren\WYT\Application\Service\LimitManagementService::class =>
        create(\Zieren\WYT\Application\Service\LimitManagementService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\LimitRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\ConfigRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Service\SlotParser::class)
            ),

    \Zieren\WYT\Application\Service\ClassManagementService::class =>
        create(\Zieren\WYT\Application\Service\ClassManagementService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\ClassRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\TransactionManagerInterface::class),
                get(\Zieren\WYT\Domain\Service\ActivityReclassificationService::class),
                get(\Zieren\WYT\Application\Event\EventDispatcher::class)
            ),

    \Zieren\WYT\Application\Service\ClassificationManagementService::class =>
        create(\Zieren\WYT\Application\Service\ClassificationManagementService::class)
            ->constructor(get(\Zieren\WYT\Domain\Repository\ClassificationRepositoryInterface::class)),

    \Zieren\WYT\Application\Service\MappingManagementService::class =>
        create(\Zieren\WYT\Application\Service\MappingManagementService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\ClassLimitMappingRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\UserRepositoryInterface::class)
            ),

    \Zieren\WYT\Application\Service\OverrideManagementService::class =>
        create(\Zieren\WYT\Application\Service\OverrideManagementService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\OverrideRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\LimitRepositoryInterface::class),
                get(\Zieren\WYT\Domain\Repository\LimitOverlapQueryInterface::class),
                get(\Zieren\WYT\Domain\Service\SlotParser::class)
            ),

    \Zieren\WYT\Application\Service\PruningService::class =>
        create(\Zieren\WYT\Application\Service\PruningService::class)
            ->constructor(
                get(\Zieren\WYT\Domain\Repository\ActivityPruningRepositoryInterface::class),
                get(\Psr\Log\LoggerInterface::class),
                get(\Zieren\WYT\Domain\Repository\LogPruningInterface::class)
            ),

    \Zieren\WYT\Application\Service\Contract\ClassManagementServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\ClassManagementService::class),
    \Zieren\WYT\Application\Service\Contract\ClassificationManagementServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\ClassificationManagementService::class),
    \Zieren\WYT\Application\Service\Contract\LimitManagementServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\LimitManagementService::class),
    \Zieren\WYT\Application\Service\Contract\MappingManagementServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\MappingManagementService::class),
    \Zieren\WYT\Application\Service\Contract\OverrideManagementServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\OverrideManagementService::class),
    \Zieren\WYT\Application\Service\Contract\ConfigManagementServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\ConfigManagementService::class),
    \Zieren\WYT\Application\Service\Contract\UserManagementServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\UserManagementService::class),
    \Zieren\WYT\Application\Service\Contract\RuleFromActivityServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\RuleFromActivityService::class),
    \Zieren\WYT\Application\Service\Contract\PruningServiceInterface::class =>
        get(\Zieren\WYT\Application\Service\PruningService::class),

    \Zieren\WYT\Application\Command\CommandBus::class =>
        \DI\factory(static function (
            \Zieren\WYT\Application\Service\Contract\ClassManagementServiceInterface $classManagementService,
            \Zieren\WYT\Application\Service\Contract\ClassificationManagementServiceInterface $classificationManagementService,
            \Zieren\WYT\Application\Service\Contract\LimitManagementServiceInterface $limitManagementService,
            \Zieren\WYT\Application\Service\Contract\MappingManagementServiceInterface $mappingManagementService,
            \Zieren\WYT\Application\Service\Contract\OverrideManagementServiceInterface $overrideManagementService,
            \Zieren\WYT\Application\Service\Contract\ConfigManagementServiceInterface $configManagementService,
            \Zieren\WYT\Application\Service\Contract\UserManagementServiceInterface $userManagementService,
            \Zieren\WYT\Application\Service\Contract\RuleFromActivityServiceInterface $ruleFromActivityService,
            \Zieren\WYT\Application\Service\Contract\PruningServiceInterface $pruningService
        ): \Zieren\WYT\Application\Command\CommandBus {
            $bus = new \Zieren\WYT\Application\Command\CommandBus();

            $bus->register(\Zieren\WYT\Application\Command\AddClassCommand::class, static function (\Zieren\WYT\Application\Command\AddClassCommand $command) use ($classManagementService): void {
                $classManagementService->addClass($command->name->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\RenameClassCommand::class, static function (\Zieren\WYT\Application\Command\RenameClassCommand $command) use ($classManagementService): void {
                $classManagementService->renameClass($command->classId, $command->className->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\RemoveClassCommand::class, static function (\Zieren\WYT\Application\Command\RemoveClassCommand $command) use ($classManagementService): void {
                $classManagementService->removeClass($command->classId);
            });
            $bus->register(\Zieren\WYT\Application\Command\ReclassifyCommand::class, static function (\Zieren\WYT\Application\Command\ReclassifyCommand $command) use ($classManagementService): void {
                $classManagementService->reclassify($command->from);
            });

            $bus->register(\Zieren\WYT\Application\Command\AddClassificationCommand::class, static function (\Zieren\WYT\Application\Command\AddClassificationCommand $command) use ($classificationManagementService): void {
                $classificationManagementService->addClassification($command->classId, $command->priority, $command->regex->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\ChangeClassificationCommand::class, static function (\Zieren\WYT\Application\Command\ChangeClassificationCommand $command) use ($classificationManagementService): void {
                $classificationManagementService->changeClassification($command->classificationId, $command->regex->value, $command->priority);
            });
            $bus->register(\Zieren\WYT\Application\Command\RemoveClassificationCommand::class, static function (\Zieren\WYT\Application\Command\RemoveClassificationCommand $command) use ($classificationManagementService): void {
                $classificationManagementService->removeClassification($command->classificationId);
            });

            $bus->register(\Zieren\WYT\Application\Command\AddLimitCommand::class, static function (\Zieren\WYT\Application\Command\AddLimitCommand $command) use ($limitManagementService): void {
                $limitManagementService->addLimit($command->userId, $command->limitName->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\RenameLimitCommand::class, static function (\Zieren\WYT\Application\Command\RenameLimitCommand $command) use ($limitManagementService): void {
                $limitManagementService->renameLimit($command->userId, $command->limitId, $command->limitName->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\RemoveLimitCommand::class, static function (\Zieren\WYT\Application\Command\RemoveLimitCommand $command) use ($limitManagementService): void {
                $limitManagementService->removeLimit($command->userId, $command->limitId);
            });
            $bus->register(\Zieren\WYT\Application\Command\SetLimitConfigCommand::class, static function (\Zieren\WYT\Application\Command\SetLimitConfigCommand $command) use ($limitManagementService): void {
                $limitManagementService->setLimitConfig($command->userId, $command->limitId, $command->key, $command->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\ClearLimitConfigCommand::class, static function (\Zieren\WYT\Application\Command\ClearLimitConfigCommand $command) use ($limitManagementService): void {
                $limitManagementService->clearLimitConfig($command->userId, $command->limitId, $command->key);
            });

            $bus->register(\Zieren\WYT\Application\Command\AddMappingCommand::class, static function (\Zieren\WYT\Application\Command\AddMappingCommand $command) use ($mappingManagementService): void {
                $mappingManagementService->addMapping($command->classId, $command->limitId);
            });
            $bus->register(\Zieren\WYT\Application\Command\RemoveMappingCommand::class, static function (\Zieren\WYT\Application\Command\RemoveMappingCommand $command) use ($mappingManagementService): void {
                $mappingManagementService->removeMapping($command->classId, $command->limitId);
            });

            $bus->register(\Zieren\WYT\Application\Command\SetOverrideMinutesCommand::class, static function (\Zieren\WYT\Application\Command\SetOverrideMinutesCommand $command) use ($overrideManagementService): void {
                $overrideManagementService->setMinutes($command->userId, $command->date, $command->limitId, $command->minutes->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\SetOverrideSlotsCommand::class, static function (\Zieren\WYT\Application\Command\SetOverrideSlotsCommand $command) use ($overrideManagementService): void {
                $overrideManagementService->setSlots($command->userId, $command->date, $command->limitId, $command->slots->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\UnlockOverrideCommand::class, static function (\Zieren\WYT\Application\Command\UnlockOverrideCommand $command) use ($overrideManagementService): void {
                $overrideManagementService->unlock($command->userId, $command->date, $command->limitId);
            });
            $bus->register(\Zieren\WYT\Application\Command\ClearOverridesCommand::class, static function (\Zieren\WYT\Application\Command\ClearOverridesCommand $command) use ($overrideManagementService): void {
                $overrideManagementService->clearOverrides($command->userId, $command->date, $command->limitId);
            });

            $bus->register(\Zieren\WYT\Application\Command\SetUserConfigCommand::class, static function (\Zieren\WYT\Application\Command\SetUserConfigCommand $command) use ($configManagementService): void {
                $configManagementService->setUserConfig($command->userId, $command->key, $command->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\ClearUserConfigCommand::class, static function (\Zieren\WYT\Application\Command\ClearUserConfigCommand $command) use ($configManagementService): void {
                $configManagementService->clearUserConfig($command->userId, $command->key);
            });
            $bus->register(\Zieren\WYT\Application\Command\SetGlobalConfigCommand::class, static function (\Zieren\WYT\Application\Command\SetGlobalConfigCommand $command) use ($configManagementService): void {
                $configManagementService->setGlobalConfig($command->key, $command->value);
            });
            $bus->register(\Zieren\WYT\Application\Command\ClearGlobalConfigCommand::class, static function (\Zieren\WYT\Application\Command\ClearGlobalConfigCommand $command) use ($configManagementService): void {
                $configManagementService->clearGlobalConfig($command->key);
            });

            $bus->register(\Zieren\WYT\Application\Command\AddUserCommand::class, static function (\Zieren\WYT\Application\Command\AddUserCommand $command) use ($userManagementService): void {
                $userManagementService->addUser($command->userId);
            });
            $bus->register(\Zieren\WYT\Application\Command\RemoveUserCommand::class, static function (\Zieren\WYT\Application\Command\RemoveUserCommand $command) use ($userManagementService): void {
                $userManagementService->removeUser($command->userId);
            });

            $bus->register(\Zieren\WYT\Application\Command\CreateRuleFromTitleCommand::class, static function (\Zieren\WYT\Application\Command\CreateRuleFromTitleCommand $command) use ($ruleFromActivityService): void {
                $ruleFromActivityService->createRuleFromTitle($command->className, $command->priority, $command->title, $command->limitId);
            });

            $bus->register(\Zieren\WYT\Application\Command\PruneCommand::class, static function (\Zieren\WYT\Application\Command\PruneCommand $command) use ($pruningService): void {
                $pruningService->prune($command->date);
            });

            return $bus;
        }),

    \Zieren\WYT\Application\Command\FormCommandFactory::class =>
        create(\Zieren\WYT\Application\Command\FormCommandFactory::class),
];
