<?php

namespace Zieren\WYT\Infrastructure\Persistence;

use Zieren\WYT\Domain\Repository\TransactionManagerInterface;

class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function run(callable $operation): mixed
    {
        $this->connection->startTransaction();
        try {
            $result = $operation();
            $this->connection->commit();
            return $result;
        } catch (\Throwable $exception) {
            $this->connection->rollback();
            throw $exception;
        }
    }
}
