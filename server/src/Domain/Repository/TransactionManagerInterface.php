<?php

namespace Zieren\WYT\Domain\Repository;

interface TransactionManagerInterface
{
    public function run(callable $operation): mixed;
}
