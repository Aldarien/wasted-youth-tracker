<?php

namespace Zieren\WYT\Domain\ValueObject;

use Zieren\WYT\Domain\Clock;

final class TimeLeft
{
    public function __construct(
        private readonly bool $locked,
        int $totalSeconds,
        private readonly Clock $clock,
        ?TimeSlot $currentSlot = null,
        ?TimeSlot $nextSlot = null,
        ?int $currentSeconds = null
    ) {
        $this->currentSeconds = $currentSeconds ?? ($locked ? 0 : $totalSeconds);
        $this->totalSeconds = $totalSeconds;
        $this->currentSlot = $currentSlot;
        $this->nextSlot = $nextSlot;
    }

    private readonly int $currentSeconds;
    private readonly int $totalSeconds;
    private readonly ?TimeSlot $currentSlot;
    private readonly ?TimeSlot $nextSlot;

    public function withSlots(
        ?TimeSlot $currentSlot,
        int $currentSeconds,
        int $totalSeconds,
        ?TimeSlot $nextSlot
    ): self {
        return new self(
            $this->locked,
            min($this->totalSeconds, $totalSeconds),
            $this->clock,
            $currentSlot,
            $nextSlot,
            min($this->currentSeconds, $currentSeconds)
        );
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

}
