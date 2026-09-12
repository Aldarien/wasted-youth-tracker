<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Psr\Log\LoggerInterface;

class MeekroConnection implements Connection
{
    public function __construct(
        private readonly LoggerInterface $logger,
        string $dbName,
        string $user,
        string $password,
        string $encoding = 'latin1',
        string $host = 'localhost'
    ) {
        \DB::$dbName = $dbName;
        \DB::$user = $user;
        \DB::$password = $password;
        \DB::$host = $host;
        \DB::$encoding = $encoding;
    }

    public function query(string $sql, ...$args): array
    {
        $this->logger->debug('DB query: ' . preg_replace('/\r\n/', '', $sql));
        return \DB::query($sql, ...$args);
    }

    public function queryFirstRow(string $sql, ...$args): ?array
    {
        $this->logger->debug('DB query first row: ' . preg_replace('/\r\n/', '', $sql));
        return \DB::queryFirstRow($sql, ...$args);
    }

    public function insert(string $table, array $data): void
    {
        \DB::insert($table, $data);
    }

    public function insertIgnore(string $table, array $data): void
    {
        \DB::insertIgnore($table, $data);
    }

    public function insertUpdate(string $table, array $data, ?string $updateFormat = null, ...$args): void
    {
        if ($updateFormat === null) {
            \DB::insertUpdate($table, $data);
        } else {
            \DB::insertUpdate($table, $data, $updateFormat, ...$args);
        }
    }

    public function update(string $table, array $data, string $whereFormat, ...$args): void
    {
        \DB::update($table, $data, $whereFormat, ...$args);
    }

    public function delete(string $table, string $whereFormat, ...$args): void
    {
        \DB::delete($table, $whereFormat, ...$args);
    }

    public function replace(string $table, array $records): void
    {
        \DB::replace($table, $records);
    }

    public function insertId(): int
    {
        return (int) \DB::insertId();
    }

    public function tableList(): array
    {
        return \DB::tableList();
    }

    public function startTransaction(): void
    {
        \DB::startTransaction();
    }

    public function commit(): void
    {
        \DB::commit();
    }

    public function rollback(): void
    {
        \DB::rollback();
    }

    public function rawQuery(string $sql, ...$args): void
    {
        $this->logger->debug('DB raw query: ' . preg_replace('/\r\n/', '', $sql));
        \DB::query($sql, ...$args);
    }
}
