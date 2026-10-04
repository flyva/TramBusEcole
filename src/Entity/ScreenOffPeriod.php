<?php

namespace App\Entity;

use App\Repository\ScreenOffPeriodRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One recurring daily time window during which the screen should be off.
 * Several can coexist (e.g. "nuit" 23:00-06:00 and "pause midi" 12:30-13:15).
 */
#[ORM\Entity(repositoryClass: ScreenOffPeriodRepository::class)]
class ScreenOffPeriod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 5)]
    private string $startTime = '22:00';

    #[ORM\Column(length: 5)]
    private string $endTime = '07:00';

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function setStartTime(string $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): string
    {
        return $this->endTime;
    }

    public function setEndTime(string $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Whether `$time` ("HH:MM") falls within this period, handling windows
     * that cross midnight (e.g. 22:00 -> 07:00).
     */
    public function contains(string $time): bool
    {
        if ($this->startTime <= $this->endTime) {
            return $time >= $this->startTime && $time < $this->endTime;
        }

        return $time >= $this->startTime || $time < $this->endTime;
    }
}
