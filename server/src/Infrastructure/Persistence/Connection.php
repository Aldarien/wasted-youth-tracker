<?php

namespace Zieren\WYT\Infrastructure\Persistence;

interface Connection
{
    public function rawQuery(string $sql, ...$args): void;

    public function query(string $sql, ...$args): array;

    public function queryFirstRow(string $sql, ...$args): ?array;

    public function insert(string $table, array $data): void;

    public function insertIgnore(string $table, array $data): void;

    public function insertUpdate(string $table, array $data, ?string $updateFormat = null, ...$args): void;

    public function update(string $table, array $data, string $whereFormat, ...$args): void;

    public function delete(string $table, string $whereFormat, ...$args): void;

    public function replace(string $table, array $records): void;

    public function insertId(): int;

    /**
     * @return string[]
     */
    public function tableList(): array;

    public function startTransaction(): void;

    public function commit(): void;

    public function rollback(): void;
}
