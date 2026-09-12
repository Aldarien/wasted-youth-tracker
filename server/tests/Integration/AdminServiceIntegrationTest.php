<?php

namespace Zieren\WYT\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Zieren\WYT\Application\Service\ClassManagementService;
use Zieren\WYT\Application\Service\ClassificationManagementService;
use Zieren\WYT\Application\Service\ConfigManagementService;
use Zieren\WYT\Application\Service\LimitManagementService;
use Zieren\WYT\Application\Service\MappingManagementService;
use Zieren\WYT\Application\Service\OverrideManagementService;
use Zieren\WYT\Application\Service\PruningService;
use Zieren\WYT\Application\Service\UserManagementService;
use Zieren\WYT\Domain\Exception\InvalidSlotException;
use Zieren\WYT\Domain\Defaults;
use Zieren\WYT\Domain\Exception\CannotModifyDefaultClassException;
use Zieren\WYT\Domain\Exception\CannotModifyDefaultClassificationException;
use Zieren\WYT\Domain\Service\ActivityReclassificationService;
use Zieren\WYT\Domain\Service\ClassificationService;
use Zieren\WYT\Domain\Service\SlotParser;
use Zieren\WYT\Infrastructure\Clock\FrozenClock;
use Zieren\WYT\Infrastructure\Persistence\DatabaseInitializer;
use Zieren\WYT\Infrastructure\Persistence\PdoActivityRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoClassificationRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoConfigRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoConnection;
use Zieren\WYT\Infrastructure\Persistence\PdoLimitRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoOverrideRepository;
use Zieren\WYT\Infrastructure\Persistence\PdoUserRepository;

class AdminServiceIntegrationTest extends TestCase
{
    private PdoConnection $connection;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new \Psr\Log\NullLogger();
        $this->connection = new PdoConnection(
            $logger,
            TEST_DB_NAME,
            TEST_DB_USER,
            TEST_DB_PASS,
            'latin1',
            TEST_DB_HOST
        );
        $this->connection->rawQuery('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->connection->query(
            'SELECT TABLE_NAME AS table_name FROM information_schema.tables WHERE table_schema = %s',
            TEST_DB_NAME
        ) as $row) {
            $this->connection->rawQuery('DROP TABLE `' . $row['table_name'] . '`');
        }
        $this->connection->rawQuery('SET FOREIGN_KEY_CHECKS = 1');

        (new DatabaseInitializer($this->connection))->initialize();
        $this->clock = new FrozenClock(new \DateTimeImmutable('@1000'));
    }

    public function testLimitLifecycle(): void
    {
        $service = $this->createLimitManagementService();
        $userService = $this->createUserManagementService();
        $userService->addUser('u1');

        $limitId = $service->addLimit('u1', 'Games');
        $this->assertGreaterThan(0, $limitId);

        $limits = (new PdoLimitRepository($this->connection))->findByUser('u1');
        $this->assertCount(2, $limits);
        $limitById = (new PdoLimitRepository($this->connection))->findById($limitId);
        $this->assertNotNull($limitById);
        $this->assertSame('Games', $limitById->name);

        $service->renameLimit('u1', $limitId, 'Videos');
        $limitById = (new PdoLimitRepository($this->connection))->findById($limitId);
        $this->assertSame('Videos', $limitById->name);

        $service->removeLimit('u1', $limitId);
        $limits = (new PdoLimitRepository($this->connection))->findByUser('u1');
        $this->assertCount(1, $limits);
    }

    public function testTotalLimitCannotBeRenamedOrRemoved(): void
    {
        $service = $this->createLimitManagementService();
        $userService = $this->createUserManagementService();
        $totalId = $userService->addUser('u1');

        $service->renameLimit('u1', $totalId, 'Hacked');
        $service->removeLimit('u1', $totalId);

        $limit = (new PdoLimitRepository($this->connection))->findById($totalId);
        $this->assertNotNull($limit);
        $this->assertSame('Total', $limit->name);
    }

    public function testClassLifecycle(): void
    {
        $service = $this->createClassManagementService();
        $classRepository = new PdoClassRepository($this->connection);
        $totalLimitId = $this->createUserManagementService()->addUser('u1');

        $classId = $service->addClass('game');
        $this->assertGreaterThan(1, $classId);
        $classes = $classRepository->findAll();
        $this->assertSame('game', $classes[$classId]->name);
        $this->assertContains(
            $totalLimitId,
            (new PdoLimitRepository($this->connection))->findLimitIdsByClass($classId)
        );

        $service->renameClass($classId, 'video');
        $classes = $classRepository->findAll();
        $this->assertSame('video', $classes[$classId]->name);

        $service->removeClass($classId);
        $classes = $classRepository->findAll();
        $this->assertArrayNotHasKey($classId, $classes);
    }

    public function testLegacyTotalLimitTriggersAreRemoved(): void
    {
        $this->createUserManagementService()->addUser('legacy_user');
        $this->connection->rawQuery('
            CREATE TRIGGER `total_limit_legacy_user` AFTER INSERT ON classes
            FOR EACH ROW INSERT INTO mappings (limit_id, class_id) VALUES (1, NEW.id)
        ');

        (new DatabaseInitializer($this->connection))->initialize();

        $triggers = $this->connection->query(
            'SELECT TRIGGER_NAME FROM information_schema.triggers '
            . 'WHERE trigger_schema = %s AND trigger_name = %s',
            TEST_DB_NAME,
            'total_limit_legacy_user'
        );
        $this->assertCount(0, $triggers);
    }

    public function testOrphanedLegacyTotalLimitTriggersAreRemoved(): void
    {
        $this->connection->rawQuery('
            CREATE TRIGGER `total_limit_deleted_user` AFTER INSERT ON classes
            FOR EACH ROW INSERT INTO mappings (limit_id, class_id) VALUES (1, NEW.id)
        ');

        (new DatabaseInitializer($this->connection))->initialize();

        $triggers = $this->connection->query(
            'SELECT TRIGGER_NAME FROM information_schema.triggers '
            . 'WHERE trigger_schema = %s AND trigger_name = %s',
            TEST_DB_NAME,
            'total_limit_deleted_user'
        );
        $this->assertCount(0, $triggers);
    }

    public function testDefaultClassCannotBeRemoved(): void
    {
        $service = $this->createClassManagementService();

        $this->expectException(CannotModifyDefaultClassException::class);
        $service->removeClass(Defaults::DEFAULT_CLASS_ID);
    }

    public function testClassificationLifecycle(): void
    {
        $service = $this->createClassificationManagementService();
        $classificationRepository = new PdoClassificationRepository($this->connection);

        $classId = $this->createClassManagementService()->addClass('game');
        $classificationId = $service->addClassification($classId, 10, 'foo$');
        $this->assertGreaterThan(0, $classificationId);

        $classifications = $classificationRepository->findByClassId($classId);
        $this->assertCount(1, $classifications);
        $this->assertSame('foo$', $classifications[0]->regex);

        $service->changeClassification($classificationId, 'bar$', 20);
        $classifications = $classificationRepository->findByClassId($classId);
        $this->assertSame('bar$', $classifications[0]->regex);
        $this->assertSame(20, $classifications[0]->priority);

        $service->removeClassification($classificationId);
        $this->assertCount(0, $classificationRepository->findByClassId($classId));
    }

    public function testReclassifyUpdatesRecentActivity(): void
    {
        $userService = $this->createUserManagementService();
        $userService->addUser('u1');
        $classService = $this->createClassManagementService();
        $classificationService = $this->createClassificationManagementService();
        $classId = $classService->addClass('game');
        $classificationService->addClassification($classId, 10, 'game$');

        $now = $this->clock->now()->getTimestamp();
        (new PdoActivityRepository($this->connection))->save(new \Zieren\WYT\Domain\Entity\ActivityRecord(
            'u1',
            0,
            $now - 100,
            $now - 50,
            Defaults::DEFAULT_CLASS_ID,
            'game'
        ));

        $classService->reclassify($this->clock->now()->modify('-1 day'));

        $activity = $this->connection->queryFirstRow(
            'SELECT class_id FROM activity WHERE user = %s AND title = %s',
            'u1',
            'game'
        );
        $this->assertSame($classId, (int) $activity['class_id']);
    }

    public function testDefaultClassificationCannotBeRemoved(): void
    {
        $service = $this->createClassificationManagementService();

        $this->expectException(CannotModifyDefaultClassificationException::class);
        $service->removeClassification(Defaults::DEFAULT_CLASSIFICATION_ID);
    }

    public function testMappingLifecycle(): void
    {
        $limitService = $this->createLimitManagementService();
        $classService = $this->createClassManagementService();
        $mappingService = $this->createMappingManagementService();
        $userService = $this->createUserManagementService();
        $userService->addUser('u1');

        $limitId = $limitService->addLimit('u1', 'Games');
        $classId = $classService->addClass('game');

        $mappingService->addMapping($classId, $limitId);
        $limits = (new PdoLimitRepository($this->connection))->findLimitIdsByClass($classId);
        $this->assertContains($limitId, $limits);

        $mappingService->removeMapping($classId, $limitId);
        $limits = (new PdoLimitRepository($this->connection))->findLimitIdsByClass($classId);
        $this->assertNotContains($limitId, $limits);
    }

    public function testLimitConfigWithSlots(): void
    {
        $service = $this->createLimitManagementService();
        $userService = $this->createUserManagementService();
        $userService->addUser('u1');
        $limitId = $service->addLimit('u1', 'Games');

        $service->setLimitConfig('u1', $limitId, 'times', '10:00-12:00,14:00-16:00');
        $config = (new PdoConfigRepository($this->connection))->getLimitConfig($limitId);
        $this->assertSame('10:00-12:00,14:00-16:00', $config['times']);

        $service->clearLimitConfig('u1', $limitId, 'times');
        $config = (new PdoConfigRepository($this->connection))->getLimitConfig($limitId);
        $this->assertArrayNotHasKey('times', $config);
    }

    public function testConfigManagement(): void
    {
        $service = $this->createConfigManagementService();
        $this->createUserManagementService()->addUser('u1');

        $service->setGlobalConfig('theme', 'dark');
        $service->setUserConfig('u1', 'theme', 'light');

        $configRepository = new PdoConfigRepository($this->connection);
        $this->assertSame(['theme' => 'dark'], $configRepository->getGlobalConfig());
        $this->assertSame(['theme' => 'light'], $configRepository->getUserConfig('u1'));

        $service->clearUserConfig('u1', 'theme');
        $service->clearGlobalConfig('theme');
        $this->assertSame([], $configRepository->getGlobalConfig());
        $this->assertSame([], $configRepository->getUserConfig('u1'));
    }

    public function testInvalidSlotConfigThrows(): void
    {
        $service = $this->createLimitManagementService();
        $userService = $this->createUserManagementService();
        $userService->addUser('u1');
        $limitId = $service->addLimit('u1', 'Games');

        $this->expectException(InvalidSlotException::class);
        $service->setLimitConfig('u1', $limitId, 'times', '10:00');
    }

    public function testOverrideManagement(): void
    {
        $limitService = $this->createLimitManagementService();
        $classService = $this->createClassManagementService();
        $mappingService = $this->createMappingManagementService();
        $overrideService = $this->createOverrideManagementService();
        $userService = $this->createUserManagementService();
        $userService->addUser('u1');

        $limitId = $limitService->addLimit('u1', 'Games');
        $classId = $classService->addClass('game');
        $mappingService->addMapping($classId, $limitId);

        $date = $this->clock->now()->format('Y-m-d');
        $further = $overrideService->setMinutes('u1', $date, $limitId, 60);
        $this->assertSame([], $further);

        $overrides = (new PdoOverrideRepository($this->connection))->findByUserForDate('u1', $date);
        $this->assertSame(60, $overrides[$limitId]['minutes']);

        $overrideService->clearOverrides('u1', $date, $limitId);
        $overrides = (new PdoOverrideRepository($this->connection))->findByUserForDate('u1', $date);
        $this->assertArrayNotHasKey($limitId, $overrides);
    }

    public function testLimitMutationsRequireTheOwningUser(): void
    {
        $userService = $this->createUserManagementService();
        $userService->addUser('u1');
        $userService->addUser('u2');
        $limitId = $this->createLimitManagementService()->addLimit('u2', 'Games');

        $this->expectException(\Zieren\WYT\Domain\Exception\LimitNotOwnedByUserException::class);
        $this->createLimitManagementService()->renameLimit('u1', $limitId, 'Hacked');
    }

    public function testOverrideMutationsRequireTheOwningUser(): void
    {
        $userService = $this->createUserManagementService();
        $userService->addUser('u1');
        $userService->addUser('u2');
        $limitId = $this->createLimitManagementService()->addLimit('u2', 'Games');

        $this->expectException(\Zieren\WYT\Domain\Exception\LimitNotOwnedByUserException::class);
        $this->createOverrideManagementService()->setMinutes('u1', '2024-01-01', $limitId, 60);
    }

    public function testPruningRemovesOldActivity(): void
    {
        $userService = $this->createUserManagementService();
        $limitService = $this->createLimitManagementService();
        $classService = $this->createClassManagementService();
        $mappingService = $this->createMappingManagementService();
        $userService->addUser('u1');
        $limitId = $limitService->addLimit('u1', 'Games');
        $mappingService->addMapping(Defaults::DEFAULT_CLASS_ID, $limitId);

        $classId = $classService->addClass('game');
        $this->clock->advance(1);
        $recordService = new \Zieren\WYT\Application\Service\RecordActivityService(
            $this->clock,
            new ClassificationService(
                new PdoClassificationRepository($this->connection),
                new PdoLimitRepository($this->connection)
            ),
            new PdoActivityRepository($this->connection),
            new PdoUserRepository($this->connection),
            new PdoConfigRepository($this->connection),
            new \Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager($this->connection)
        );
        $recordService->execute('u1', '', ['title']);
        $this->assertCount(1, $this->connection->query('SELECT * FROM activity'));

        $pruneService = new PruningService(
            new PdoActivityRepository($this->connection),
            new \Psr\Log\NullLogger(),
            new \Zieren\WYT\Infrastructure\Logging\LogPruner(new \Psr\Log\NullLogger())
        );
        $pruneService->prune($this->clock->now()->modify('+1 day'));
        $this->assertCount(0, $this->connection->query('SELECT * FROM activity'));
    }

    private function createLimitManagementService(): LimitManagementService
    {
        return new LimitManagementService(
            new PdoLimitRepository($this->connection),
            new PdoUserRepository($this->connection),
            new PdoConfigRepository($this->connection),
            new SlotParser($this->clock)
        );
    }

    private function createConfigManagementService(): ConfigManagementService
    {
        return new ConfigManagementService(new PdoConfigRepository($this->connection));
    }

    private function createClassManagementService(): ClassManagementService
    {
        return new ClassManagementService(
            new PdoClassRepository($this->connection),
            new PdoUserRepository($this->connection),
            new PdoLimitRepository($this->connection),
            new \Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager($this->connection),
            new ActivityReclassificationService(
                new PdoActivityRepository($this->connection),
                new PdoClassificationRepository($this->connection)
            )
        );
    }

    private function createClassificationManagementService(): ClassificationManagementService
    {
        return new ClassificationManagementService(
            new PdoClassificationRepository($this->connection)
        );
    }

    private function createMappingManagementService(): MappingManagementService
    {
        return new MappingManagementService(new PdoLimitRepository($this->connection));
    }

    private function createOverrideManagementService(): OverrideManagementService
    {
        return new OverrideManagementService(
            new PdoOverrideRepository($this->connection),
            new PdoLimitRepository($this->connection),
            new SlotParser($this->clock)
        );
    }

    private function createUserManagementService(): UserManagementService
    {
        return new UserManagementService(
            new PdoUserRepository($this->connection),
            new PdoLimitRepository($this->connection),
            new \Zieren\WYT\Infrastructure\Persistence\PdoUserRepository($this->connection),
            new \Zieren\WYT\Infrastructure\Persistence\PdoTransactionManager($this->connection)
        );
    }
}
