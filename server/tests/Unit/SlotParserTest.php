<?php

namespace Zieren\WYT\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Zieren\WYT\Domain\Exception\InvalidSlotException;
use Zieren\WYT\Domain\Service\SlotParser;
use Zieren\WYT\Infrastructure\Clock\FrozenClock;

class SlotParserTest extends TestCase
{
    public function testParses24HourSlot(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-11 12:00:00'));
        $parser = new SlotParser($clock);
        $slots = $parser->parse('08:00-12:30,14:00-18:00');

        $this->assertCount(2, $slots);
        $this->assertEquals($clock->now()->setTime(8, 0)->getTimestamp(), $slots[0]->from);
        $this->assertEquals($clock->now()->setTime(12, 30)->getTimestamp(), $slots[0]->to);
    }

    public function testParses12HourSlot(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-11 12:00:00'));
        $parser = new SlotParser($clock);
        $slots = $parser->parse('8am-12pm');

        $this->assertCount(1, $slots);
        $this->assertEquals($clock->now()->setTime(8, 0)->getTimestamp(), $slots[0]->from);
        $this->assertEquals($clock->now()->setTime(12, 0)->getTimestamp(), $slots[0]->to);
    }

    public function testEndOfDay24IsNextDay(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-11 12:00:00'));
        $parser = new SlotParser($clock);
        $slots = $parser->parse('22:00-24:00');

        $this->assertEquals($clock->now()->setTime(22, 0)->getTimestamp(), $slots[0]->from);
        $this->assertEquals($clock->now()->setTime(24, 0)->getTimestamp(), $slots[0]->to);
    }

    public function testThrowsOnInvalidSlot(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-11 12:00:00'));
        $parser = new SlotParser($clock);

        $this->expectException(InvalidSlotException::class);
        $parser->parse('not-a-slot');
    }

    public function testThrowsOnOverlappingSlots(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-11 12:00:00'));
        $parser = new SlotParser($clock);

        $this->expectException(InvalidSlotException::class);
        $parser->parse('08:00-12:00,09:00-13:00');
    }
}
