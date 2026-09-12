<?php

namespace Zieren\WYT\Domain\Service;

use Zieren\WYT\Domain\Clock;
use Zieren\WYT\Domain\Exception\InvalidActivityIntervalException;
use Zieren\WYT\Domain\ValueObject\TimeLeft;
use Zieren\WYT\Domain\ValueObject\TimeSlot;

class TimeCalculationService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly SlotParser $slotParser
    ) {
    }

    /**
     * @param array<int, array{limit_id: int, from_ts: int, to_ts: int}> $rows
     * @return array<int, array<string, int>>
     */
    public function computeTimeSpentByLimitAndDate(array $rows): array
    {
        if (!$rows) {
            return [];
        }

        $timestamps = [];
        $limitIds = [];
        $minTs = $rows[0]['from_ts'];
        $maxTs = $rows[0]['to_ts'];
        foreach ($rows as $row) {
            $fromTs = $row['from_ts'];
            $toTs = $row['to_ts'];
            $limitId = $row['limit_id'];
            $timestamps[$fromTs]['starting'][] = $limitId;
            $timestamps[$toTs]['ending'][] = $limitId;
            $maxTs = max($maxTs, $toTs);
            $minTs = min($minTs, $fromTs);
            $limitIds[$limitId] = true;
        }

        $dateTime = $this->clock->now()->setTimestamp($minTs)->setTime(0, 0);
        do {
            $dateString = $dateTime->format('Y-m-d');
            $dateTime = $dateTime->modify('+1 day');
            $ts = $dateTime->getTimestamp();
            $timestamps[$ts]['day'] = $dateString;
        } while ($ts < $maxTs);

        ksort($timestamps);
        $timeByLimitAndDate = [];
        $limitCount = [];
        $limitStart = [];
        $limitTime = [];
        foreach ($timestamps as $ts => $events) {
            foreach ($events['starting'] ?? [] as $limitId) {
                $n = $limitCount[$limitId] ?? 0;
                if ($n === 0) {
                    $limitStart[$limitId] = $ts;
                }
                $limitCount[$limitId] = $n + 1;
            }
            foreach ($events['ending'] ?? [] as $limitId) {
                $n = $limitCount[$limitId] ?? 0;
                if (!$n) {
                    throw new InvalidActivityIntervalException(
                        'Invalid: Limit interval ending before it started'
                    );
                }
                $limitCount[$limitId] = $n - 1;
                if ($limitCount[$limitId] === 0) {
                    $limitTime[$limitId] = ($limitTime[$limitId] ?? 0) + ($ts - $limitStart[$limitId]);
                }
            }
            if (isset($events['day'])) {
                $dateString = $events['day'];
                foreach (array_keys($limitIds) as $limitId) {
                    if (($limitCount[$limitId] ?? 0) > 0) {
                        $limitTime[$limitId] = ($limitTime[$limitId] ?? 0) + ($ts - $limitStart[$limitId]);
                        $limitStart[$limitId] = $ts;
                    }
                    if (isset($limitTime[$limitId])) {
                        $timeByLimitAndDate[$limitId][$dateString] = $limitTime[$limitId];
                        $limitTime[$limitId] = 0;
                    }
                }
            }
        }

        ksort($timeByLimitAndDate);
        return $timeByLimitAndDate;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $overrides
     * @param array<string, int>   $timeSpentByDate
     */
    public function computeTimeLeftToday(array $config, array $overrides, array $timeSpentByDate): TimeLeft
    {
        $now = $this->clock->now();
        $dow = strtolower($now->format('D'));
        $nowString = $now->format('Y-m-d');

        $locked = !empty($config['locked']) && empty($overrides['unlocked']);

        $slotsSpec = $overrides['slots'] ?? $config["times_$dow"] ?? $config['times'] ?? null;
        $minutesToday = $overrides['minutes'] ?? $config["minutes_$dow"] ?? $config['minutes_day'] ?? null;

        if ($minutesToday === null) {
            $secondsLimitToday = $slotsSpec === null ? 0 : PHP_INT_MAX;
        } else {
            $secondsLimitToday = $minutesToday * 60;
        }

        if (!isset($overrides['minutes']) && isset($config['minutes_week']) && $config['minutes_week'] !== '') {
            $secondsLeftInWeek = (int) $config['minutes_week'] * 60 - array_sum($timeSpentByDate);
            $secondsLimitToday = min($secondsLimitToday, $secondsLeftInWeek);
        }

        $tomorrow = $now->setTime(0, 0)->modify('+1 day');
        $secondsLeftInDay = $tomorrow->getTimestamp() - $now->getTimestamp();
        $totalSeconds = min(
            $secondsLimitToday - ($timeSpentByDate[$nowString] ?? 0),
            $secondsLeftInDay
        );

        $timeLeft = new TimeLeft($locked, $totalSeconds, $this->clock);

        if ($slotsSpec !== null) {
            $slots = $this->slotParser->parse($slotsSpec);
            $this->applySlots($slots, $timeLeft);
        }

        return $timeLeft;
    }

    /**
     * @param TimeSlot[] $slots
     */
    private function applySlots(array $slots, TimeLeft $timeLeft): void
    {
        $ts = $this->clock->now()->getTimestamp();
        $slots[] = new TimeSlot(0, 0); // avoids next slot extraction special case
        $currentSeconds = 0;
        $totalSeconds = 0;
        $currentSlot = null;
        $nextSlot = null;

        for ($i = 0; $i < count($slots) - 1; $i++) {
            $slot = $slots[$i];
            if ($slot->from <= $ts && $ts < $slot->to) {
                $totalSeconds = $currentSeconds = $slot->to - $ts;
                $currentSlot = $slot;
                $nextSlot = $slots[$i + 1];
                $i++;
                break;
            }
            if ($ts < $slot->from) {
                $nextSlot = $slot;
                break;
            }
        }

        for (; $i < count($slots) - 1; $i++) {
            $totalSeconds += $slots[$i]->to - $slots[$i]->from;
        }

        $timeLeft->applySlots($currentSlot, $currentSeconds, $totalSeconds, $nextSlot);
    }
}
