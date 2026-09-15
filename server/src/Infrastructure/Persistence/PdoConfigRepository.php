<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Repository\ConfigRepositoryInterface;

class PdoConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function getGlobalConfig(): array
    {
        return $this->parseRows($this->connection->query('SELECT k, v FROM global_config'));
    }

    public function getGlobalConfigValue(string $key): ?string
    {
        return $this->getGlobalString($key);
    }

    public function getGlobalString(string $key): ?string
    {
        $row = $this->connection->queryFirstRow('SELECT v FROM global_config WHERE k = %s', $key);
        return $row ? (string) $row['v'] : null;
    }

    public function getGlobalInt(string $key): ?int
    {
        $value = $this->getGlobalString($key);
        if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        return (int) $value;
    }

    public function setGlobalConfig(string $key, string $value): void
    {
        $this->connection->insertUpdate('global_config', ['k' => $key, 'v' => $value]);
    }

    public function clearGlobalConfig(string $key): void
    {
        $this->connection->delete('global_config', 'k = %s', $key);
    }

    public function getUserConfig(string $userId): array
    {
        return $this->parseRows(
            $this->connection->query('SELECT k, v FROM user_config WHERE user = %s', $userId)
        );
    }

    public function getUserString(string $userId, string $key): ?string
    {
        $row = $this->connection->queryFirstRow(
            'SELECT v FROM user_config WHERE user = %s AND k = %s',
            $userId,
            $key
        );
        return $row ? (string) $row['v'] : null;
    }

    public function getUserInt(string $userId, string $key): ?int
    {
        $value = $this->getUserString($userId, $key);
        if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        return (int) $value;
    }

    public function setUserConfig(string $userId, string $key, string $value): void
    {
        $this->connection->insertUpdate(
            'user_config',
            ['user' => $userId, 'k' => $key, 'v' => $value]
        );
    }

    public function clearUserConfig(string $userId, string $key): void
    {
        $this->connection->delete('user_config', 'user = %s AND k = %s', $userId, $key);
    }

    public function getLimitConfig(int $limitId): array
    {
        return $this->parseRows(
            $this->connection->query('SELECT k, v FROM limit_config WHERE limit_id = %i', $limitId)
        );
    }

    public function getLimitString(int $limitId, string $key): ?string
    {
        $row = $this->connection->queryFirstRow(
            'SELECT v FROM limit_config WHERE limit_id = %i AND k = %s',
            $limitId,
            $key
        );
        return $row ? (string) $row['v'] : null;
    }

    public function getLimitInt(int $limitId, string $key): ?int
    {
        $value = $this->getLimitString($limitId, $key);
        if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        return (int) $value;
    }

    public function setLimitConfig(int $limitId, string $key, string $value): void
    {
        $this->connection->insertUpdate(
            'limit_config',
            ['limit_id' => $limitId, 'k' => $key, 'v' => $value]
        );
    }

    public function clearLimitConfig(int $limitId, string $key): void
    {
        $this->connection->delete('limit_config', 'limit_id = %s AND k = %s', $limitId, $key);
    }

    public function getClientConfig(string $userId): array
    {
        return $this->parseRows($this->connection->query('
            SELECT k, v FROM global_config
            WHERE k NOT IN (SELECT k FROM user_config WHERE user = %s0)
            UNION
            SELECT k, v FROM user_config WHERE user = %s0',
            $userId));
    }

    public function getClientString(string $userId, string $key): ?string
    {
        $value = $this->getUserString($userId, $key);
        if ($value !== null) {
            return $value;
        }
        return $this->getGlobalString($key);
    }

    public function getClientInt(string $userId, string $key): ?int
    {
        $value = $this->getClientString($userId, $key);
        if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        return (int) $value;
    }

    public function findAllLimitConfigs(string $userId): array
    {
        $rows = $this->connection->query('
            SELECT limits.id, name, k, v, total_limit_id
              FROM limit_config
              RIGHT JOIN limits ON limit_config.limit_id = limits.id
              LEFT JOIN users ON users.total_limit_id = limits.id
              WHERE limits.user = %s
              ORDER BY id, k',
            $userId);
        $configs = [];
        foreach ($rows as $row) {
            $limitId = (int) $row['id'];
            if (!isset($configs[$limitId])) {
                $configs[$limitId] = [];
            }
            if ($row['k']) {
                $configs[$limitId][$row['k']] = $row['v'];
            }
            $configs[$limitId]['name'] = $row['name'];
            $configs[$limitId]['is_total'] = $row['total_limit_id'] !== null;
        }
        ksort($configs);
        return $configs;
    }

    public function findAllUserConfigs(): array
    {
        $rows = $this->connection->query('SELECT user, k, v FROM user_config ORDER BY user, k');
        $configs = [];
        foreach ($rows as $row) {
            $configs[$row['user']][$row['k']] = $row['v'];
        }
        return $configs;
    }

    public function findAllLimitConfigsForUsers(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }
        $rows = $this->connection->query('
            SELECT limits.user, limits.id, limits.name, k, v, total_limit_id
              FROM limit_config
              RIGHT JOIN limits ON limit_config.limit_id = limits.id
              LEFT JOIN users ON users.total_limit_id = limits.id
              WHERE limits.user IN %ls
              ORDER BY limits.user, limits.id, k',
            $userIds
        );
        $configs = [];
        foreach ($rows as $row) {
            $userId = $row['user'];
            $limitId = (int) $row['id'];
            if (!isset($configs[$userId][$limitId])) {
                $configs[$userId][$limitId] = [
                    'name' => $row['name'],
                    'is_total' => $row['total_limit_id'] !== null,
                ];
            }
            if ($row['k']) {
                $configs[$userId][$limitId][$row['k']] = $row['v'];
            }
        }
        foreach ($configs as &$userConfigs) {
            ksort($userConfigs);
        }
        return $configs;
    }

    private function parseRows(array $rows): array
    {
        $config = [];
        foreach ($rows as $row) {
            $config[$row['k']] = $row['v'];
        }
        return $config;
    }
}
