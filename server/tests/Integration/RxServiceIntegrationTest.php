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
use Zieren\WYT\Infrastructure\Persistence\MeekroActivityRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroClassificationRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroConfigRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroLimitRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroOverrideRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroUserRepository;

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
        $classificationRepository = new MeekroClassificationRepository($this->connection);
        $classificationService = new ClassificationService(
            $classificationRepository,
            new MeekroLimitRepository($this->connection)
        );
        $activityRepository = new MeekroActivityRepository($this->connection);
        $configRepository = new MeekroConfigRepository($this->connection);
        $userRepository = new MeekroUserRepository($this->connection);
        $limitRepository = new MeekroLimitRepository($this->connection);

        $recordActivityService = new RecordActivityService(
            $clock,
            $classificationService,
            $activityRepository,
            $userRepository,
            $configRepository,
            new \Zieren\WYT\Infrastructure\Persistence\MeekroTransactionManager($this->connection)
        );

        return new RxService(
            $clock,
            $recordActivityService,
            $timeCalculationService,
            $activityRepository,
            $configRepository,
            $limitRepository,
            new MeekroOverrideRepository($this->connection)
        );
    }

    private function addUser(): void
    {
        $configRepository = new MeekroConfigRepository($this->connection);
        $userService = new UserManagementService(
            new MeekroUserRepository($this->connection),
            new MeekroLimitRepository($this->connection),
            new MeekroUserRepository($this->connection),
            new \Zieren\WYT\Infrastructure\Persistence\MeekroTransactionManager($this->connection)
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
