<?php

namespace Zieren\WYT\Tests\Integration;

use Zieren\WYT\Application\Service\UserManagementService;
use Zieren\WYT\Domain\Entity\ActivityClass;
use Zieren\WYT\Domain\Entity\Classification;
use Zieren\WYT\Domain\Entity\Limit;
use Zieren\WYT\Domain\Service\ClassificationService;
use Zieren\WYT\Domain\Wasted;
use Zieren\WYT\Infrastructure\Persistence\MeekroActivityRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroClassRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroClassificationRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroConfigRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroLimitRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroOverrideRepository;
use Zieren\WYT\Infrastructure\Persistence\MeekroUserRepository;

class ClassificationAndLimitsTest extends IntegrationTestCase
{
    private MeekroLimitRepository $limitRepository;
    private MeekroClassRepository $classRepository;
    private MeekroClassificationRepository $classificationRepository;
    private UserManagementService $userService;
    private ClassificationService $classificationService;
    private int $totalLimitId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limitRepository = new MeekroLimitRepository($this->connection);
        $this->classRepository = new MeekroClassRepository($this->connection);
        $this->classificationRepository = new MeekroClassificationRepository($this->connection);
        $this->userService = new UserManagementService(
            new MeekroUserRepository($this->connection),
            $this->limitRepository,
            new MeekroUserRepository($this->connection),
            new \Zieren\WYT\Infrastructure\Persistence\MeekroTransactionManager($this->connection)
        );
        $this->classificationService = new ClassificationService(
            $this->classificationRepository,
            $this->limitRepository
        );
        $this->totalLimitId = $this->userService->addUser('u1');
    }

    public function testSetUpLimitsAndClassify(): void
    {
        $limitId1 = $this->limitRepository->save(new Limit(0, 'u1', 'b1'));
        $limitId2 = $this->limitRepository->save(new Limit(0, 'u1', 'b2'));
        $this->assertSame($limitId2, $limitId1 + 1);

        $classId1 = $this->classRepository->save(new ActivityClass(0, 'c1'));
        $classId2 = $this->classRepository->save(new ActivityClass(0, 'c2'));
        $this->limitRepository->addMapping($classId1, $this->totalLimitId);
        $this->limitRepository->addMapping($classId2, $this->totalLimitId);
        $this->assertSame($classId2, $classId1 + 1);

        $classificationId1 = $this->classificationRepository->save(
            new Classification(0, $classId1, 0, '1$')
        );
        $classificationId2 = $this->classificationRepository->save(
            new Classification(0, $classId2, 10, '2$')
        );
        $this->assertSame($classificationId2, $classificationId1 + 1);

        $this->limitRepository->addMapping($classId1, $limitId1);

        $result = $this->classificationService->classify('u1', ['window 0', 'window 1', 'window 2']);
        $this->assertClassificationResult($result, [
            [Wasted::DEFAULT_CLASS_ID, [$this->totalLimitId]],
            [$classId1, [$this->totalLimitId, $limitId1]],
            [$classId2, [$this->totalLimitId]],
        ]);

        $this->limitRepository->addMapping($classId1, $limitId2);
        $result = $this->classificationService->classify('u1', ['window 0', 'window 1', 'window 2']);
        $this->assertClassificationResult($result, [
            [Wasted::DEFAULT_CLASS_ID, [$this->totalLimitId]],
            [$classId1, [$this->totalLimitId, $limitId1, $limitId2]],
            [$classId2, [$this->totalLimitId]],
        ]);

        $this->limitRepository->addMapping(Wasted::DEFAULT_CLASS_ID, $limitId2);
        $result = $this->classificationService->classify('u1', ['window 0', 'window 1', 'window 2']);
        $this->assertClassificationResult($result, [
            [Wasted::DEFAULT_CLASS_ID, [$this->totalLimitId, $limitId2]],
            [$classId1, [$this->totalLimitId, $limitId1, $limitId2]],
            [$classId2, [$this->totalLimitId]],
        ]);

        $result = $this->classificationService->classify('u1', ['window 1']);
        $this->assertClassificationResult($result, [
            [$classId1, [$this->totalLimitId, $limitId1, $limitId2]],
        ]);

        $this->limitRepository->removeMapping($classId1, $limitId1);
        $result = $this->classificationService->classify('u1', ['window 1']);
        $this->assertClassificationResult($result, [
            [$classId1, [$this->totalLimitId, $limitId2]],
        ]);
    }

    public function testGetAllLimitConfigs(): void
    {
        $configRepository = new MeekroConfigRepository($this->connection);
        $all = $configRepository->findAllLimitConfigs('u1');
        $expected = [$this->totalLimitId => ['name' => Wasted::TOTAL_LIMIT_NAME, 'is_total' => true]];
        $this->assertEquals($expected, $all);

        $limitId = $this->limitRepository->save(new Limit(0, 'u1', 'b'));
        $all = $configRepository->findAllLimitConfigs('u1');
        $expected[$limitId] = ['name' => 'b', 'is_total' => false];
        $this->assertEquals($expected, $all);

        $this->limitRepository->addMapping(Wasted::DEFAULT_CLASS_ID, $limitId);
        $all = $configRepository->findAllLimitConfigs('u1');
        $this->assertEqualsCanonicalizing($expected, $all);

        $configRepository->setLimitConfig($limitId, 'foo', 'bar');
        $all = $configRepository->findAllLimitConfigs('u1');
        $expected[$limitId]['foo'] = 'bar';
        $this->assertEquals($expected, $all);
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
