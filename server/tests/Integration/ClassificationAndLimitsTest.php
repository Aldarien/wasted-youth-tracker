<?php

namespace Zieren\WYT\Tests\Integration;

use Zieren\WYT\Application\Service\UserManagementService;
use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Service\ClassificationService;
use Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassificationRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassLimitMappingRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoConfigRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoLimitRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoOverrideRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoUserRepository;

class ClassificationAndLimitsTest extends IntegrationTestCase
{
    private PdoLimitRepository $limitRepository;
    private PdoClassLimitMappingRepository $mappingRepository;
    private PdoClassRepository $classRepository;
    private PdoClassificationRepository $classificationRepository;
    private UserManagementService $userService;
    private ClassificationService $classificationService;
    private int $totalLimitId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limitRepository = new PdoLimitRepository($this->connection);
        $this->mappingRepository = new PdoClassLimitMappingRepository($this->connection);
        $this->classRepository = new PdoClassRepository($this->connection);
        $this->classificationRepository = new PdoClassificationRepository($this->connection);
        $dispatcher = $this->createEventDispatcher();
        $this->userService = new UserManagementService(
            new PdoUserRepository($this->connection),
            $this->limitRepository,
            new \Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager($this->connection),
            new PdoConfigRepository($this->connection),
            $dispatcher
        );
        $this->classificationService = new ClassificationService(
            $this->classificationRepository,
            $this->mappingRepository
        );
        $this->totalLimitId = $this->userService->addUser('u1');
    }

    private function createEventDispatcher(): \Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher
    {
        $dispatcher = new \Zieren\WYT\Infrastructure\Event\InMemoryEventDispatcher();
        $configRepository = new PdoConfigRepository($this->connection);
        $userRepository = new PdoUserRepository($this->connection);

        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\UserCreated::class,
            new \Zieren\WYT\Application\Event\MapNewUserToAllClasses($this->mappingRepository)
        );
        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\UserCreated::class,
            new \Zieren\WYT\Application\Event\ApplyTotalLimitDefaultConfig($configRepository)
        );
        $dispatcher->listen(
            \Zieren\WYT\Domain\Event\ClassCreated::class,
            new \Zieren\WYT\Application\Event\MapNewClassToTotalLimits($userRepository, $this->mappingRepository)
        );
        return $dispatcher;
    }

    public function testSetUpLimitsAndClassify(): void
    {
        $limitId1 = $this->limitRepository->save(new Limit(0, 'u1', 'b1'));
        $limitId2 = $this->limitRepository->save(new Limit(0, 'u1', 'b2'));
        $this->assertSame($limitId2, $limitId1 + 1);

        $classId1 = $this->classRepository->save(new ActivityClass(0, 'c1'));
        $classId2 = $this->classRepository->save(new ActivityClass(0, 'c2'));
        $this->mappingRepository->addMapping($classId1, $this->totalLimitId);
        $this->mappingRepository->addMapping($classId2, $this->totalLimitId);
        $this->assertSame($classId2, $classId1 + 1);

        $classificationId1 = $this->classificationRepository->save(
            new Classification(0, $classId1, 0, '1$')
        );
        $classificationId2 = $this->classificationRepository->save(
            new Classification(0, $classId2, 10, '2$')
        );
        $this->assertSame($classificationId2, $classificationId1 + 1);

        $this->mappingRepository->addMapping($classId1, $limitId1);

        $result = $this->classificationService->classify('u1', ['window 0', 'window 1', 'window 2']);
        $this->assertClassificationResult($result, [
            [Defaults::DEFAULT_CLASS_ID, [$this->totalLimitId]],
            [$classId1, [$this->totalLimitId, $limitId1]],
            [$classId2, [$this->totalLimitId]],
        ]);

        $this->mappingRepository->addMapping($classId1, $limitId2);
        $result = $this->classificationService->classify('u1', ['window 0', 'window 1', 'window 2']);
        $this->assertClassificationResult($result, [
            [Defaults::DEFAULT_CLASS_ID, [$this->totalLimitId]],
            [$classId1, [$this->totalLimitId, $limitId1, $limitId2]],
            [$classId2, [$this->totalLimitId]],
        ]);

        $this->mappingRepository->addMapping(Defaults::DEFAULT_CLASS_ID, $limitId2);
        $result = $this->classificationService->classify('u1', ['window 0', 'window 1', 'window 2']);
        $this->assertClassificationResult($result, [
            [Defaults::DEFAULT_CLASS_ID, [$this->totalLimitId, $limitId2]],
            [$classId1, [$this->totalLimitId, $limitId1, $limitId2]],
            [$classId2, [$this->totalLimitId]],
        ]);

        $result = $this->classificationService->classify('u1', ['window 1']);
        $this->assertClassificationResult($result, [
            [$classId1, [$this->totalLimitId, $limitId1, $limitId2]],
        ]);

        $this->mappingRepository->removeMapping($classId1, $limitId1);
        $result = $this->classificationService->classify('u1', ['window 1']);
        $this->assertClassificationResult($result, [
            [$classId1, [$this->totalLimitId, $limitId2]],
        ]);
    }

    public function testGetAllLimitConfigs(): void
    {
        $configRepository = new PdoConfigRepository($this->connection);
        $all = $configRepository->findAllLimitConfigs('u1');
        $expected = [
            $this->totalLimitId => [
                'name' => Defaults::TOTAL_LIMIT_NAME,
                'is_total' => true,
                'total_limit_minutes_day' => '0',
            ],
        ];
        $this->assertEquals($expected, $all);

        $limitId = $this->limitRepository->save(new Limit(0, 'u1', 'b'));
        $all = $configRepository->findAllLimitConfigs('u1');
        $expected[$limitId] = ['name' => 'b', 'is_total' => false];
        $this->assertEquals($expected, $all);

        $this->mappingRepository->addMapping(Defaults::DEFAULT_CLASS_ID, $limitId);
        $all = $configRepository->findAllLimitConfigs('u1');
        $this->assertEqualsCanonicalizing($expected, $all);

        $configRepository->setLimitConfig($limitId, 'foo', 'bar');
        $all = $configRepository->findAllLimitConfigs('u1');
        $expected[$limitId]['foo'] = 'bar';
        $this->assertEquals($expected, $all);
    }

    public function testAddMappingIsIdempotent(): void
    {
        $classId = $this->classRepository->save(new ActivityClass(0, 'c1'));
        $limitId = $this->limitRepository->save(new Limit(0, 'u1', 'b1'));

        $this->mappingRepository->addMapping($classId, $limitId);
        $this->mappingRepository->addMapping($classId, $limitId);

        $this->assertSame([$limitId], $this->mappingRepository->findLimitIdsByClass($classId));
    }

    /**
     * @param array<int, int[]> $expected
     */
    private function assertClassificationResult(array $results, array $expected): void
    {
        $this->assertCount(count($expected), $results);
        foreach ($expected as $i => [$classId, $limitIds]) {
            $this->assertSame($classId, $results[$i]->classId);
            $this->assertSame($limitIds, $results[$i]->limitIds);
        }
    }
}
