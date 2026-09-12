<?php

namespace Zieren\WYT\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Zieren\WYT\Domain\Service\SlotParser;
use Zieren\WYT\Domain\Service\TimeCalculationService;
use Zieren\WYT\Infrastructure\Clock\FrozenClock;

class TimeCalculationServiceTest extends TestCase
{
    public function testTimeSpentByLimitAndDateEmpty(): void
    {
        $service = $this->createService();
        $this->assertSame([], $service->computeTimeSpentByLimitAndDate([]));
    }

    public function testTimeSpentSingleIntervalSameDay(): void
    {
        $service = $this->createService('2026-09-11 10:00:00');
        $from = (new DateTimeImmutable('2026-09-11 10:00:00'))->getTimestamp();
        $to = (new DateTimeImmutable('2026-09-11 10:05:00'))->getTimestamp();
        $rows = [
            ['limit_id' => 1, 'from_ts' => $from, 'to_ts' => $to],
        ];
        $this->assertSame([1 => ['2026-09-11' => 300]], $service->computeTimeSpentByLimitAndDate($rows));
    }

    public function testTimeSpentCrossDay(): void
    {
        $service = $this->createService('2026-09-11 23:50:00');
        $from = (new DateTimeImmutable('2026-09-11 23:50:00'))->getTimestamp();
        $to = (new DateTimeImmutable('2026-09-12 00:10:00'))->getTimestamp();
        $rows = [
            ['limit_id' => 1, 'from_ts' => $from, 'to_ts' => $to],
        ];
        $this->assertSame([
            1 => ['2026-09-11' => 600, '2026-09-12' => 600],
        ], $service->computeTimeSpentByLimitAndDate($rows));
    }

    public function testTimeLeftNoConfig(): void
    {
        $service = $this->createService('2026-09-11 10:00:00');
        $timeLeft = $service->computeTimeLeftToday([], [], []);
        $this->assertFalse($timeLeft->isLocked());
        $this->assertSame(0, $timeLeft->currentSeconds());
        $this->assertSame(0, $timeLeft->totalSeconds());
    }

    public function testTimeLeftWithDailyMinutes(): void
    {
        $service = $this->createService('2026-09-11 10:00:00');
        $timeLeft = $service->computeTimeLeftToday(['minutes_day' => 120], [], []);
        // 2h limit, time left in day is larger.
        $this->assertSame(2 * 3600, $timeLeft->currentSeconds());
        $this->assertSame(2 * 3600, $timeLeft->totalSeconds());
    }

    public function testTimeLeftWithSlots(): void
    {
        $service = $this->createService('2026-09-11 10:00:00');
        $timeLeft = $service->computeTimeLeftToday(['times' => '09:00-12:00,14:00-18:00'], [], []);
        // Inside 09:00-12:00: 2h remaining in current slot + 4h in later slot = 6h total.
        $this->assertSame(2 * 3600, $timeLeft->currentSeconds());
        $this->assertSame(6 * 3600, $timeLeft->totalSeconds());
        $this->assertNotNull($timeLeft->currentSlot());
    }

    private function createService(string $now = '2026-09-11 12:00:00'): TimeCalculationService
    {
        $clock = new FrozenClock(new DateTimeImmutable($now));
        return new TimeCalculationService($clock, new SlotParser($clock));
    }
}
