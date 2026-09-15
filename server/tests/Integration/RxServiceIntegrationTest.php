<?php

namespace Zieren\WYT\Tests\Integration;

use DateTimeImmutable;
use Zieren\WYT\Application\Service\RecordActivityService;
use Zieren\WYT\Application\Service\RxService;
use Zieren\WYT\Application\Service\UserManagementService;
use Zieren\WYT\Domain\Service\ClassificationService;
use Zieren\WYT\Domain\Service\SlotParser;
use Zieren\WYT\Domain\Service\TimeCalculationService;
use Zieren\WYT\Infrastructure\Clock\FrozenClock;
use Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassificationRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassLimitMappingRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoConfigRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoLimitRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoOverrideRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoUserRepository;

class RxServiceIntegrationTest extends IntegrationTestCase
{
    public function testRecordsActivityAndReturnsLimits(): void
    {
        $now = new DateTimeImmutable('2026-09-11 12:00:00');
        $service = $this->createRxService($now);
        $this->addUser();

        $response = $service->handle('u1', '', ['window 1']);

        $this->assertStringContainsString('Total', $response);
        $this->assertStringContainsString("\n\n", $response);
    }

    public function testTimeLeftDecreasesAsTimePasses(): void
    {
        $now = new DateTimeImmutable('2026-09-11 12:00:00');
        $clock = new FrozenClock($now);
        $service = $this->createRxServiceWithClock($clock);
        $this->addUser();

        $first = $service->handle('u1', '', ['window 1']);
        $firstCurrentSeconds = $this->extractCurrentSeconds($first);

        $clock->advance(5);
        $second = $service->handle('u1', '', ['window 1']);
        $secondCurrentSeconds = $this->extractCurrentSeconds($second);

        $this->assertSame($firstCurrentSeconds - 5, $secondCurrentSeconds);
    }

    private function createRxService(DateTimeImmutable $now): RxService
    {
        $clock = new FrozenClock($now);
        return $this->createRxServiceWithClock($clock);
    }

    private function createRxServiceWithClock(FrozenClock $clock): RxService
    {
        $slotParser = new SlotParser($clock);
        $timeCalculationService = new TimeCalculationService($clock, $slotParser);
        $classificationRepository = new PdoClassificationRepository($this->connection);
        $classificationService = new ClassificationService(
            $classificationRepository,
            new PdoClassLimitMappingRepository($this->connection)
        );
        $activityRepository = new PdoActivityRepository($this->connection);
        $configRepository = new PdoConfigRepository($this->connection);
        $userRepository = new PdoUserRepository($this->connection);
        $limitRepository = new PdoLimitRepository($this->connection);

        $recordActivityService = new RecordActivityService(
            $clock,
            $classificationService,
            $activityRepository,
            $userRepository,
            $configRepository,
            new \Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager($this->connection)
        );

        return new RxService(
            $clock,
            $recordActivityService,
            $timeCalculationService,
            $activityRepository,
            $configRepository,
            $limitRepository,
            new PdoOverrideRepository($this->connection)
        );
    }

    private function addUser(): void
    {
        $configRepository = new PdoConfigRepository($this->connection);
        $mappingRepository = new PdoClassLimitMappingRepository($this->connection);
        $userRepository = new PdoUserRepository($this->connection);

        $dispatcher = new \Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher();
        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\UserCreated::class,
            new \Zieren\WYT\Application\Event\MapNewUserToAllClasses($mappingRepository)
        );
        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\UserCreated::class,
            new \Zieren\WYT\Application\Event\ApplyTotalLimitDefaultConfig($configRepository)
        );
        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\ClassCreated::class,
            new \Zieren\WYT\Application\Event\MapNewClassToTotalLimits($userRepository, $mappingRepository)
        );

        $userService = new UserManagementService(
            $userRepository,
            new PdoLimitRepository($this->connection),
            new \Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager($this->connection),
            $configRepository,
            $dispatcher
        );

        $limitId = $userService->addUser('u1');
        $configRepository->setLimitConfig($limitId, 'minutes_day', '60');
        $configRepository->setGlobalConfig('minutes_day', '60');
    }

    private function extractCurrentSeconds(string $response): int
    {
        $lines = explode("\n", $response);
        $parts = explode(';', $lines[0]);
        return (int) $parts[2];
    }
}
