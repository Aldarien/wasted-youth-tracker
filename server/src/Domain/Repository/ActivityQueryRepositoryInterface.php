<?php

namespace Zieren\WYT\Domain\Repository;

use DateTimeImmutable;

interface ActivityQueryRepositoryInterface
{
    /**
     * @return array<int, array<string, int>>
     */
    public function queryTimeSpentByLimitAndDate(string $userId, DateTimeImmutable $from, ?DateTimeImmutable $to): array;

    /**
     * @return array<int, array{0: string, 1: int, 2: string, 3: string}>
     */
    public function queryTimeSpentByTitle(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public function queryTitleSequence(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @return array<int, array{0: int, 1: string, 2: string}>
     */
    public function queryTopUnclassified(
        string $userId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        bool $orderBySum,
        int $num
    ): array;
}
