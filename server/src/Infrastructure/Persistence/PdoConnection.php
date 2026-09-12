<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use PDO;
use Psr\Log\LoggerInterface;

final class PdoConnection implements Connection
{
    private PDO $pdo;

    public function __construct(
        private readonly LoggerInterface $logger,
        string $dbName,
        string $user,
        string $password,
        string $encoding = 'latin1',
        string $host = 'localhost'
    ) {
        $this->pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=' . $encoding,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public function rawQuery(string $sql, ...$args): void
    {
        $this->execute($sql, $args);
    }

    public function query(string $sql, ...$args): array
    {
        $statement = $this->execute($sql, $args);
        return $statement->fetchAll();
    }

    public function queryFirstRow(string $sql, ...$args): ?array
    {
        $statement = $this->execute($sql, $args);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function insert(string $table, array $data): void
    {
        $this->insertRows('INSERT INTO', $table, [$data]);
    }

    public function insertIgnore(string $table, array $data): void
    {
        $this->insertRows('INSERT IGNORE INTO', $table, [$data]);
    }

    public function insertUpdate(string $table, array $data, ?string $updateFormat = null, ...$args): void
    {
        $columns = array_keys($data);
        $updates = implode(', ', array_map(
            static fn (string $column): string => self::identifier($column) . ' = VALUES(' . self::identifier($column) . ')',
            $columns
        ));
        $this->insertRows('INSERT INTO', $table, [$data], ' ON DUPLICATE KEY UPDATE ' . $updates);
    }

    public function update(string $table, array $data, string $whereFormat, ...$args): void
    {
        $assignments = [];
        $values = [];
        foreach ($data as $column => $value) {
            $assignments[] = self::identifier($column) . ' = ?';
            $values[] = $value;
        }
        $this->execute(
            'UPDATE ' . self::identifier($table) . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $whereFormat,
            array_merge($values, $args)
        );
    }

    public function delete(string $table, string $whereFormat, ...$args): void
    {
        $this->execute(
            'DELETE FROM ' . self::identifier($table) . ' WHERE ' . $whereFormat,
            $args
        );
    }

    public function replace(string $table, array $records): void
    {
        if ($records) {
            $this->insertRows('REPLACE INTO', $table, $records);
        }
    }

    public function insertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function tableList(): array
    {
        return array_map(
            static fn (array $row): string => (string) array_values($row)[0],
            $this->query('SHOW TABLES')
        );
    }

    public function startTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function insertRows(string $verb, string $table, array $records, string $suffix = ''): void
    {
        $columns = array_keys($records[0]);
        $rowPlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $values = [];
        foreach ($records as $record) {
            foreach ($columns as $column) {
                $values[] = $record[$column];
            }
        }
        $sql = $verb . ' ' . self::identifier($table)
            . ' (' . implode(', ', array_map(self::identifier(...), $columns)) . ') VALUES '
            . implode(', ', array_fill(0, count($records), $rowPlaceholders))
            . $suffix;
        $this->execute($sql, $values);
    }

    /**
     * @param list<mixed> $args
     */
    private function execute(string $sql, array $args): \PDOStatement
    {
        if (preg_match('/%l?[si]\d*/', $sql) === 1) {
            [$sql, $parameters] = $this->prepareSql($sql, $args);
        } else {
            $parameters = $args;
        }
        $this->logger->debug('DB query: ' . preg_replace('/\r\n/', '', $sql));
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $index => $parameter) {
            $type = match (true) {
                is_int($parameter) => PDO::PARAM_INT,
                is_bool($parameter) => PDO::PARAM_BOOL,
                $parameter === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($index + 1, $parameter, $type);
        }
        $statement->execute();
        return $statement;
    }

    /**
     * @param list<mixed> $args
     * @return array{0: string, 1: list<mixed>}
     */
    private function prepareSql(string $sql, array $args): array
    {
        $next = 0;
        $parameters = [];
        $directParameterCount = substr_count($sql, '?');
        $sql = (string) preg_replace_callback(
            '/%l([si])(\d*)|%([si])(\d*)/',
            static function (array $match) use (
                $args,
                &$next,
                &$parameters,
                $directParameterCount
            ): string {
                $isList = $match[1] !== '';
                $explicitIndex = $isList ? $match[2] : $match[4];
                $index = $explicitIndex === ''
                    ? $directParameterCount + $next++
                    : (int) $explicitIndex;
                if (!array_key_exists($index, $args)) {
                    throw new \InvalidArgumentException('Missing SQL parameter at index ' . $index);
                }
                if (!$isList) {
                    $parameters[] = $args[$index];
                    return '?';
                }
                if (!is_array($args[$index]) || !$args[$index]) {
                    throw new \InvalidArgumentException('SQL list parameter must be a non-empty array');
                }
                foreach ($args[$index] as $value) {
                    $parameters[] = $value;
                }
                return '(' . implode(', ', array_fill(0, count($args[$index]), '?')) . ')';
            },
            $sql
        );
        return [$sql, array_merge(array_slice($args, 0, $directParameterCount), $parameters)];
    }

    private static function identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
