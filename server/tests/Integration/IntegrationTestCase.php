<?php

namespace Zieren\WYT\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zieren\WYT\Infrastructure\Persistence\Connection;
use Zieren\WYT\Infrastructure\Persistence\DatabaseInitializer;
use Zieren\WYT\Infrastructure\Persistence\MeekroConnection;

$testConfig = __DIR__ . '/../config_tests.php';
if (!is_file($testConfig)) {
    $testConfig = __DIR__ . '/../config_tests-sample.php';
}
require_once $testConfig;

abstract class IntegrationTestCase extends TestCase
{
    protected Connection $connection;
    protected DatabaseInitializer $initializer;

    protected function setUp(): void
    {
        $this->connection = new MeekroConnection(
            new NullLogger(),
            TEST_DB_NAME,
            TEST_DB_USER,
            TEST_DB_PASS,
            'latin1',
            TEST_DB_HOST
        );
        $this->initializer = new DatabaseInitializer($this->connection);
        $this->resetDatabase();
        $this->initializer->initialize();
    }

    private function resetDatabase(): void
    {
        $this->connection->rawQuery('SET FOREIGN_KEY_CHECKS = 0');
        $rows = $this->connection->query(
            'SELECT TABLE_NAME AS table_name FROM information_schema.tables WHERE table_schema = %s',
            TEST_DB_NAME
        );
        foreach ($rows as $row) {
            $this->connection->rawQuery('DROP TABLE `' . $row['table_name'] . '`');
        }
        $this->connection->rawQuery('SET FOREIGN_KEY_CHECKS = 1');
    }
}
