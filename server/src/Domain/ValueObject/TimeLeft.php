<?php

namespace Zieren\WYT\Domain\ValueObject;

use Zieren\WYT\Domain\Clock;

class TimeLeft
{
    private bool $locked;
    private int $currentSeconds;
    private int $totalSeconds;
    private ?TimeSlot $currentSlot = null;
    private ?TimeSlot $nextSlot = null;

    public function __construct(
        bool $locked,
        int $totalSeconds,
        private readonly Clock $clock
    )
    {
        $this->locked = $locked;
        $this->currentSeconds = $locked ? 0 : $totalSeconds;
        $this->totalSeconds = $totalSeconds;
    }

    public function applySlots(?TimeSlot $currentSlot, int $currentSeconds, int $totalSeconds, ?TimeSlot $nextSlot): self
    {
        $this->currentSeconds = min($this->currentSeconds, $currentSeconds);
        $this->totalSeconds = min($this->totalSeconds, $totalSeconds);
        $this->currentSlot = $currentSlot;
        $this->nextSlot = $nextSlot;
        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function currentSeconds(): int
    {
        return $this->currentSeconds;
    }

    public function totalSeconds(): int
    {
        return $this->totalSeconds;
    }

    public function currentSlot(): ?TimeSlot
    {
        return $this->currentSlot;
    }

    public function nextSlot(): ?TimeSlot
    {
        return $this->nextSlot;
    }

    public function toClientResponse(): string
    {
        $date = $this->clock->now();
        $response = [
            $this->locked ? 1 : 0,
            $this->currentSeconds,
            $this->totalSeconds,
        ];
        foreach ([$this->currentSlot, $this->nextSlot] as $slot) {
            if ($slot !== null) {
                $response[] = $date->setTimestamp($slot->from)->format('H:i')
                    . '-' . $date->setTimestamp($slot->to)->format('H:i');
            } else {
                $response[] = '';
            }
        }
        return implode(';', $response);
    }

    public static function toCurrentSeconds(self $timeLeft): int
    {
        return $timeLeft->currentSeconds();
    }
}
