<?php

namespace Zieren\WYT\Tests\Integration;

use Zieren\WYT\Application\Service\RecordActivityService;
use Zieren\WYT\Application\Service\UserManagementService;
use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Service\ClassificationService;
use Zieren\WYT\Domain\Service\ActivityReclassificationService;
use Zieren\WYT\Domain\Service\SlotParser;
use Zieren\WYT\Domain\Service\TimeCalculationService;
use Zieren\WYT\Infrastructure\Clock\FrozenClock;
use Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassificationRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassLimitMappingRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoConfigRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoLimitRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoOverrideRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoUserRepository;

class WastedBehaviorTest extends IntegrationTestCase
{
    private FrozenClock $clock;
    private RecordActivityService $recordActivityService;
    private TimeCalculationService $timeCalculationService;
    private PdoActivityRepository $activityRepository;
    private PdoLimitRepository $limitRepository;
    private PdoClassLimitMappingRepository $mappingRepository;
    private PdoClassRepository $classRepository;
    private PdoClassificationRepository $classificationRepository;
    private PdoConfigRepository $configRepository;
    private PdoOverrideRepository $overrideRepository;
    private UserManagementService $userService;

    private int $totalLimitId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock(new \DateTimeImmutable('@1000'));
        $this->classificationRepository = new PdoClassificationRepository($this->connection);
        $this->activityRepository = new PdoActivityRepository($this->connection);
        $this->limitRepository = new PdoLimitRepository($this->connection);
        $this->mappingRepository = new PdoClassLimitMappingRepository($this->connection);
        $this->classRepository = new PdoClassRepository($this->connection);
        $this->configRepository = new PdoConfigRepository($this->connection);
        $this->overrideRepository = new PdoOverrideRepository($this->connection);
        $userRepository = new PdoUserRepository($this->connection);

        $this->timeCalculationService = new TimeCalculationService(
            $this->clock,
            new SlotParser($this->clock)
        );
        $this->recordActivityService = new RecordActivityService(
            $this->clock,
            new ClassificationService($this->classificationRepository, $this->mappingRepository),
            $this->activityRepository,
            $userRepository,
            $this->configRepository,
            new \Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager($this->connection)
        );
        $dispatcher = $this->createEventDispatcher();
        $this->userService = new UserManagementService(
            $userRepository,
            $this->limitRepository,
            new \Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager($this->connection),
            $this->configRepository,
            $dispatcher
        );

        $this->totalLimitId = $this->userService->addUser('u1');
    }

    private function createEventDispatcher(): \Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher
    {
        $dispatcher = new \Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher();
        $userRepository = new PdoUserRepository($this->connection);
        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\UserCreated::class,
            new \Zieren\WYT\Application\Event\MapNewUserToAllClasses($this->mappingRepository)
        );
        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\UserCreated::class,
            new \Zieren\WYT\Application\Event\ApplyTotalLimitDefaultConfig($this->configRepository)
        );
        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\ClassCreated::class,
            new \Zieren\WYT\Application\Event\MapNewClassToTotalLimits($userRepository, $this->mappingRepository)
        );
        return $dispatcher;
    }

    public function testTotalTimeSingleWindowNoLimit(): void
    {
        $fromTime = $this->clock->now();

        $this->assertEquals([], $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals($this->total(0), $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(5);
        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals($this->total(5), $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(6);
        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals($this->total(11), $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(7);
        $this->insertActivity('u1', ['window 2']);
        $this->assertEquals($this->total(18), $this->queryTimeSpentByLimitAndDate($fromTime));
    }

    public function testTotalTimeSingleObservation(): void
    {
        $fromTime = $this->clock->now();
        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals($this->total(0), $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(5);
        $this->insertActivity('u1', []);
        $this->assertEquals($this->total(5), $this->queryTimeSpentByLimitAndDate($fromTime));
    }

    public function testReplaceEmptyTitle(): void
    {
        $fromTime = $this->clock->now();
        $toTime = $this->clock->now()->modify('+1 day');

        $this->insertActivity('u1', ['window 1']);
        $this->advanceTime(5);
        $this->insertActivity('u1', ['window 1']);
        $this->advanceTime(5);
        $this->insertActivity('u1', ['']);
        $window1LastSeen = $this->dateTimeString();
        $this->advanceTime(5);
        $this->insertActivity('u1', ['']);

        $this->assertEquals($this->total(15), $this->queryTimeSpentByLimitAndDate($fromTime));

        $actual = $this->queryTimeSpentByTitle($fromTime, $toTime);
        $expected = [
            [$this->dateTimeString(), 5, 'Default', '(no title)'],
            [$window1LastSeen, 10, 'Default', 'window 1'],
        ];
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    public function testTotalTimeSingleWindowWithLimits(): void
    {
        $limitId1 = $this->limitRepository->save(new Limit(0, 'u1', 'b1'));
        $classId1 = $this->saveClass('c1');
        $this->classificationRepository->save(new Classification(0, $classId1, 0, '1$'));
        $this->mappingRepository->addMapping($classId1, $limitId1);

        $fromTime = $this->clock->now();

        $this->assertEquals([], $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals($this->limit($limitId1, 0), $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(5);
        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals($this->limit($limitId1, 5), $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(6);
        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals($this->limit($limitId1, 11), $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(7);
        $this->insertActivity('u1', ['window 2']);
        $this->assertEquals([
            $this->totalLimitId => [$this->day() => 18],
            $limitId1 => [$this->day() => 18],
        ], $this->queryTimeSpentByLimitAndDate($fromTime));
    }

    public function testTotalTimeTwoWindowsWithLimits(): void
    {
        $limitId1 = $this->limitRepository->save(new Limit(0, 'u1', 'b1'));
        $limitId2 = $this->limitRepository->save(new Limit(0, 'u1', 'b2'));
        $limitId3 = $this->limitRepository->save(new Limit(0, 'u1', 'b3'));
        $classId1 = $this->saveClass('c1');
        $classId2 = $this->saveClass('c2');
        $classId3 = $this->saveClass('c3');
        $this->classificationRepository->save(new Classification(0, $classId1, 0, '1$'));
        $this->classificationRepository->save(new Classification(0, $classId2, 10, '2$'));
        $this->classificationRepository->save(new Classification(0, $classId3, 20, '3$'));
        $this->mappingRepository->addMapping(1, $limitId1);
        $this->mappingRepository->addMapping($classId1, $limitId1);
        $this->mappingRepository->addMapping($classId2, $limitId2);
        $this->mappingRepository->addMapping($classId2, $limitId3);
        $this->mappingRepository->addMapping($classId3, $limitId3);

        $fromTime = $this->clock->now();

        $this->assertEquals([], $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals($this->limit($limitId1, 0), $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(5);
        $this->insertActivity('u1', ['window 1', 'window 2']);
        $this->assertEquals([
            $this->totalLimitId => [$this->day() => 5],
            $limitId1 => [$this->day() => 5],
            $limitId2 => [$this->day() => 0],
            $limitId3 => [$this->day() => 0],
        ], $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(5);
        $this->insertActivity('u1', ['window 1', 'window 2']);
        $this->assertEquals([
            $this->totalLimitId => [$this->day() => 10],
            $limitId1 => [$this->day() => 10],
            $limitId2 => [$this->day() => 5],
            $limitId3 => [$this->day() => 5],
        ], $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(5);
        $this->insertActivity('u1', ['window 1']);
        $this->assertEquals([
            $this->totalLimitId => [$this->day() => 15],
            $limitId1 => [$this->day() => 15],
            $limitId2 => [$this->day() => 10],
            $limitId3 => [$this->day() => 10],
        ], $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(6);
        $this->insertActivity('u1', ['window 1', 'another window 1']);
        $this->assertEquals([
            $this->totalLimitId => [$this->day() => 21],
            $limitId1 => [$this->day() => 21],
            $limitId2 => [$this->day() => 10],
            $limitId3 => [$this->day() => 10],
        ], $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(7);
        $this->insertActivity('u1', ['window 1', 'window 2']);
        $this->assertEquals([
            $this->totalLimitId => [$this->day() => 28],
            $limitId1 => [$this->day() => 28],
            $limitId2 => [$this->day() => 10],
            $limitId3 => [$this->day() => 10],
        ], $this->queryTimeSpentByLimitAndDate($fromTime));

        $this->advanceTime(8);
        $this->insertActivity('u1', ['window 2']);
        $this->assertEquals([
            $this->totalLimitId => [$this->day() => 36],
            $limitId1 => [$this->day() => 36],
            $limitId2 => [$this->day() => 18],
            $limitId3 => [$this->day() => 18],
        ], $this->queryTimeSpentByLimitAndDate($fromTime));
    }

    public function testWeeklyLimit(): void
    {
        $limitId = $this->limitRepository->save(new Limit(0, 'u1', 'b'));
        $this->mappingRepository->addMapping(1, $limitId);

        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId => 0],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->configRepository->setLimitConfig($limitId, 'minutes_day', '42');
        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId => 42 * 60],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->configRepository->setLimitConfig($limitId, 'minutes_week', '666');
        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId => 42 * 60],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->configRepository->setLimitConfig($limitId, 'minutes_week', '5');
        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId => 5 * 60],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->configRepository->setLimitConfig($limitId, 'minutes_week', '0');
        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId => 0],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->overrideRepository->setMinutes('u1', $this->day(), $limitId, 123);
        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId => 123 * 60],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->overrideRepository->clear('u1', $this->day(), $limitId);
        $this->configRepository->clearLimitConfig($limitId, 'minutes_week');
        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId => 42 * 60],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );
    }

    public function testTimeLeftConsumeTimeAndClassify(): void
    {
        $classId = $this->saveClass('c1');
        $this->classificationRepository->save(new Classification(0, $classId, 42, '1$'));
        $limitId1 = $this->limitRepository->save(new Limit(0, 'u1', 'b1'));

        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId1 => 0],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->mappingRepository->addMapping($classId, $limitId1);
        $this->configRepository->setLimitConfig($limitId1, 'minutes_day', '2');

        $classification1 = ['class_id' => $classId, 'limits' => [$this->totalLimitId, $limitId1]];
        $this->assertEqualsCanonicalizing([$classification1], $this->toArray($this->insertActivity('u1', ['title 1'])));
        $this->assertEquals(
            [$this->totalLimitId => 0, $limitId1 => 120],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->advanceTime(15);
        $this->assertEqualsCanonicalizing([$classification1], $this->toArray($this->insertActivity('u1', ['title 1'])));
        $this->assertEquals(
            [$this->totalLimitId => -15, $limitId1 => 105],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->advanceTime(15);
        $classification2 = ['class_id' => 1, 'limits' => [$this->totalLimitId]];
        $this->assertEqualsCanonicalizing(
            [$classification1, $classification2],
            $this->toArray($this->insertActivity('u1', ['title 1', 'title 2']))
        );
        $this->assertEquals(
            [$this->totalLimitId => -30, $limitId1 => 90],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $this->advanceTime(15);
        $this->assertEqualsCanonicalizing(
            [$classification1, $classification2],
            $this->toArray($this->insertActivity('u1', ['title 1', 'title 2']))
        );
        $this->assertEquals(
            [$this->totalLimitId => -45, $limitId1 => 75],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );

        $limitId2 = $this->limitRepository->save(new Limit(0, 'u1', 'b2'));
        $this->mappingRepository->addMapping($classId, $limitId2);
        $this->configRepository->setLimitConfig($limitId2, 'minutes_day', '1');
        $this->advanceTime(1);
        $classification1['limits'][] = $limitId2;
        $this->assertEqualsCanonicalizing(
            [$classification1, $classification2],
            $this->toArray($this->insertActivity('u1', ['title 1', 'title 2']))
        );
        $this->assertEquals(
            [$this->totalLimitId => -46, $limitId1 => 74, $limitId2 => 14],
            $this->queryTimeLeftTodayAllLimitsOnlyCurrentSeconds()
        );
    }

    public function testReclassify(): void
    {
        $classId = $this->saveClass('c1');
        $this->classificationRepository->save(new Classification(0, $classId, 10, '1$'));

        $fromTime = $this->clock->now();
        $this->advanceTime(1);
        $this->insertActivity('u1', ['foo 1']);
        $this->advanceTime(1);
        $this->insertActivity('u1', ['foo 1']);

        $this->assertEquals(
            [$this->totalLimitId => [$this->day() => 1]],
            $this->queryTimeSpentByLimitAndDate($fromTime)
        );

        $this->classificationRepository->save(new Classification(0, $classId, 20, 'foo'));
        (new ActivityReclassificationService(
            $this->activityRepository,
            $this->classificationRepository
        ))->reclassify($this->clock->now()->modify('-2 days'));

        $this->assertEquals(
            [$this->totalLimitId => [$this->day() => 1]],
            $this->queryTimeSpentByLimitAndDate($fromTime)
        );
    }

    public function testManageLimits(): void
    {
        $configs = $this->configRepository->findAllLimitConfigs('u1');
        $this->assertEquals(
            [
                $this->totalLimitId => [
                    'name' => 'Total',
                    'is_total' => true,
                    'total_limit_minutes_day' => '0',
                ],
            ],
            $configs
        );

        $limitId1 = $this->limitRepository->save(new Limit(0, 'u1', 'b1'));
        $this->assertEquals([], $this->configRepository->findAllLimitConfigs('nobody'));

        $expected = [
            $this->totalLimitId => [
                'name' => 'Total',
                'is_total' => true,
                'total_limit_minutes_day' => '0',
            ],
            $limitId1 => ['name' => 'b1', 'is_total' => false],
        ];
        $this->assertEquals($expected, $this->configRepository->findAllLimitConfigs('u1'));

        $this->mappingRepository->addMapping(1, $limitId1);
        $this->assertEqualsCanonicalizing(
            [['class_id' => 1, 'limits' => [$this->totalLimitId, $limitId1]]],
            $this->toArray($this->insertActivity('u1', ['foo']))
        );

        $this->configRepository->setLimitConfig($limitId1, 'foo', 'bar');
        $expected[$limitId1]['foo'] = 'bar';
        $this->assertEquals($expected, $this->configRepository->findAllLimitConfigs('u1'));

        $this->limitRepository->delete($limitId1);
        unset($expected[$limitId1]);
        $this->assertEquals($expected, $this->configRepository->findAllLimitConfigs('u1'));
        $this->assertEqualsCanonicalizing(
            [['class_id' => 1, 'limits' => [$this->totalLimitId]]],
            $this->toArray($this->insertActivity('u1', ['foo']))
        );
    }

    /**
     * @param \Zieren\WYT\Domain\ValueObject\ClassificationResult[] $results
     * @return array<int, array<string, mixed>>
     */
    private function toArray(array $results): array
    {
        return array_map(fn ($r) => ['class_id' => $r->classId, 'limits' => $r->limitIds], $results);
    }

    private function insertActivity(string $user, array $titles): array
    {
        return $this->recordActivityService->execute($user, '', $titles);
    }

    private function queryTimeSpentByLimitAndDate(\DateTimeImmutable $fromTime): array
    {
        $rows = $this->activityRepository->queryTimeSpentByLimitAndDate('u1', $fromTime, null);
        return $this->timeCalculationService->computeTimeSpentByLimitAndDate($rows);
    }

    private function queryTimeSpentByTitle(\DateTimeImmutable $fromTime, \DateTimeImmutable $toTime): array
    {
        return $this->activityRepository->queryTimeSpentByTitle('u1', $fromTime, $toTime);
    }

    private function queryTimeLeftTodayAllLimitsOnlyCurrentSeconds(): array
    {
        $limits = $this->limitRepository->findByUser('u1');
        $overrides = $this->overrideRepository->findByUserForDate('u1', $this->day());
        $rows = $this->activityRepository->queryTimeSpentByLimitAndDate(
            'u1',
            $this->getWeekStart($this->clock->now()),
            null
        );
        $timeSpent = $this->timeCalculationService->computeTimeSpentByLimitAndDate($rows);

        $result = [];
        foreach ($limits as $limit) {
            $config = $this->configRepository->getLimitConfig($limit->id);
            $config['name'] = $limit->name;
            $config['is_total'] = false;
            $result[$limit->id] = $this->timeCalculationService
                ->computeTimeLeftToday(
                    $config,
                    $overrides[$limit->id] ?? [],
                    $timeSpent[$limit->id] ?? []
                )->currentSeconds();
        }
        ksort($result, SORT_NUMERIC);
        return $result;
    }

    private function getWeekStart(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = ((int) $date->format('w') + 6) % 7;
        return $date->setTime(0, 0)->modify("-{$dayOfWeek} days");
    }

    private function advanceTime(int $seconds): void
    {
        $this->clock->advance($seconds);
    }

    private function day(): string
    {
        return $this->clock->now()->format('Y-m-d');
    }

    private function dateTimeString(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }

    private function total(int $seconds): array
    {
        return [$this->totalLimitId => [$this->day() => $seconds]];
    }

    private function saveClass(string $name): int
    {
        $classId = $this->classRepository->save(new ActivityClass(0, $name));
        $this->mappingRepository->addMapping($classId, $this->totalLimitId);
        return $classId;
    }

    private function limit(int $limitId, int $seconds): array
    {
        return [
            $this->totalLimitId => [$this->day() => $seconds],
            $limitId => [$this->day() => $seconds],
        ];
    }
}
