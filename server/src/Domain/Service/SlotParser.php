<?php

namespace Zieren\WYT\Domain\Service;

use Zieren\WYT\Domain\Clock;
use Zieren\WYT\Domain\Exception\InvalidSlotException;
use Zieren\WYT\Domain\ValueObject\TimeSlot;

class SlotParser
{
    /** Compiling and matching this pattern once takes about 20 microseconds on my server. */
    private const TIME_OF_DAY_PATTERN =
        '/(?:^|-) *((?:[01]?[0-9])|(?:2[0-4]))(?::([0-5]?[0-9]))?(?: *(a|p)[.m]*)? *(?=-|$)/i';

    public function __construct(
        private readonly Clock $clock
    ) {
    }

    /**
     * @return TimeSlot[]
     * @throws InvalidSlotException
     */
    public function parse(string $slotsSpec): array
    {
        $slotsStrings = explode(',', $slotsSpec);
        $slots = [];
        foreach ($slotsStrings as $slotString) {
            $slotString = trim($slotString);
            if (!$slotString) {
                continue;
            }
            $m = [];
            $valid = false;
            if (preg_match_all(self::TIME_OF_DAY_PATTERN, $slotString, $m) && count($m[0]) == 2) {
                $fromHour = $this->adjust12hFormat(intval($m[1][0]), strtolower($m[3][0]));
                $fromMinute = $m[2][0] ? intval($m[2][0]) : 0;
                $toHour = $this->adjust12hFormat(intval($m[1][1]), strtolower($m[3][1]));
                $toMinute = $m[2][1] ? intval($m[2][1]) : 0;

                // Special case: The 24h format has hours 0, 12 and 24:00, whereas the 12h format only has
                // 12am (i.e. 0) and 12pm (i.e. 12). If 12am is specified as the end time, interpret it as
                // 24:00 (which DateTime handles correctly).
                if ($toHour == 0 && $toMinute == 0) {
                    $toHour = 24;
                }

                $valid = ($fromHour != 24 || $fromMinute == 0) && ($toHour != 24 || $toMinute == 0);
            }
            if ($valid) {
                $d = $this->clock->now();
                $fromTimestamp = $d->setTime($fromHour, $fromMinute)->getTimestamp();
                $toTimestamp = $d->setTime($toHour, $toMinute)->getTimestamp();
                $valid = $fromTimestamp <= $toTimestamp;
            }
            if (!$valid) {
                throw new InvalidSlotException("Invalid time slot: '$slotString'");
            }
            $slots[] = new TimeSlot($fromTimestamp, $toTimestamp);
        }

        usort($slots, fn (TimeSlot $a, TimeSlot $b) => $a->from - $b->from);
        for ($i = 1; $i < count($slots); $i++) {
            if ($slots[$i]->from < $slots[$i - 1]->to) {
                throw new InvalidSlotException("Time slots overlap: '$slotsSpec'");
            }
        }
        return $slots;
    }

    private function adjust12hFormat(int $h, ?string $amOrPm): int
    {
        if ($amOrPm === 'a') {
            return $h == 12 ? 0 : $h;
        }
        if ($amOrPm === 'p') {
            return $h < 12 ? $h + 12 : $h;
        }
        return $h;
    }
}
