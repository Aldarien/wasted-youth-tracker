<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Defaults;

class DatabaseInitializer
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function initialize(): void
    {
        $this->connection->rawQuery('SET default_storage_engine=INNODB');
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS users ('
            . 'id VARCHAR(32) NOT NULL, '
            . 'total_limit_id INT DEFAULT NULL, '
            . 'last_error VARCHAR(10240) DEFAULT "", '
            . 'acked_error CHAR(15) DEFAULT "", '
            . 'PRIMARY KEY (id) '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS classes ('
            . 'id INT NOT NULL AUTO_INCREMENT, '
            . 'name VARCHAR(256) NOT NULL, '
            . 'PRIMARY KEY (id) '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS activity ('
            . 'user VARCHAR(32) NOT NULL, '
            . 'seq INT UNSIGNED NOT NULL, '
            . 'from_ts BIGINT NOT NULL, '
            . 'to_ts BIGINT NOT NULL, '
            . 'class_id INT NOT NULL, '
            . 'title VARCHAR(256) NOT NULL, '
            . 'PRIMARY KEY (user, title, from_ts), '
            . 'FOREIGN KEY (user) REFERENCES users(id) ON DELETE CASCADE, '
            . 'FOREIGN KEY (class_id) REFERENCES classes(id) '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS classification ('
            . 'id INT NOT NULL AUTO_INCREMENT, '
            . 'class_id INT NOT NULL, '
            . 'priority INT NOT NULL, '
            . 're VARCHAR(1024) NOT NULL, '
            . 'PRIMARY KEY (id), '
            . 'FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS limits ('
            . 'id INT NOT NULL AUTO_INCREMENT, '
            . 'user VARCHAR(32) NOT NULL, '
            . 'name VARCHAR(256) NOT NULL, '
            . 'PRIMARY KEY (id), '
            . 'FOREIGN KEY (user) REFERENCES users(id) ON DELETE CASCADE '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS mappings ('
            . 'class_id INT NOT NULL, '
            . 'limit_id INT NOT NULL, '
            . 'PRIMARY KEY (class_id, limit_id), '
            . 'FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE, '
            . 'FOREIGN KEY (limit_id) REFERENCES limits(id) ON DELETE CASCADE '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS limit_config ('
            . 'limit_id INT NOT NULL, '
            . 'k VARCHAR(100) NOT NULL, '
            . 'v VARCHAR(200) NOT NULL, '
            . 'PRIMARY KEY (limit_id, k), '
            . 'FOREIGN KEY (limit_id) REFERENCES limits(id) ON DELETE CASCADE '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS user_config ('
            . 'user VARCHAR(32) NOT NULL, '
            . 'k VARCHAR(100) NOT NULL, '
            . 'v VARCHAR(200) NOT NULL, '
            . 'PRIMARY KEY (user, k), '
            . 'FOREIGN KEY (user) REFERENCES users(id) ON DELETE CASCADE '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS global_config ('
            . 'k VARCHAR(100) NOT NULL, '
            . 'v VARCHAR(200) NOT NULL, '
            . 'PRIMARY KEY (k) '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->connection->rawQuery(
            'CREATE TABLE IF NOT EXISTS overrides ('
            . 'user VARCHAR(32) NOT NULL, '
            . 'date DATE NOT NULL, '
            . 'limit_id INT NOT NULL, '
            . 'unlocked BOOL, '
            . 'minutes INT, '
            . 'slots VARCHAR(200), '
            . 'PRIMARY KEY (user, date, limit_id), '
            . 'FOREIGN KEY (user) REFERENCES users(id) ON DELETE CASCADE, '
            . 'FOREIGN KEY (limit_id) REFERENCES limits(id) ON DELETE CASCADE '
            . ') '
            . SchemaDefaults::TABLE_SUFFIX);
        $this->removeLegacyTotalLimitTriggers();
        $this->insertDefaultRows();
    }

    private function removeLegacyTotalLimitTriggers(): void
    {
        $triggers = $this->connection->query(
            "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS "
            . "WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME LIKE 'total_limit_%'"
        );
        foreach ($triggers as $row) {
            $triggerName = str_replace('`', '``', $row['TRIGGER_NAME']);
            $this->connection->rawQuery('DROP TRIGGER IF EXISTS `' . $triggerName . '`');
        }
    }

    private function insertDefaultRows(): void
    {
        $this->connection->insertIgnore('classes', [
            'id' => Defaults::DEFAULT_CLASS_ID,
            'name' => Defaults::DEFAULT_CLASS_NAME,
        ]);
        $this->connection->insertIgnore('global_config', [
            'k' => Defaults::TOTAL_LIMIT_NAME_CONFIG_KEY,
            'v' => Defaults::TOTAL_LIMIT_NAME,
        ]);
        $this->connection->insertIgnore('global_config', [
            'k' => Defaults::TOTAL_LIMIT_MINUTES_DAY_CONFIG_KEY,
            'v' => Defaults::DEFAULT_TOTAL_LIMIT_MINUTES_DAY,
        ]);
        $this->connection->insertIgnore('classification', [
            'id' => Defaults::DEFAULT_CLASSIFICATION_ID,
            'class_id' => Defaults::DEFAULT_CLASS_ID,
            'priority' => SchemaDefaults::MIN_SIGNED_INT,
            're' => '()',
        ]);
    }
}
