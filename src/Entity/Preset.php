<?php

namespace App\Entity;

use App\Repository\PresetRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A destination the board can show a live itinerary for (École, Travail,
 * or a one-off address). When it should be shown is driven by the calendar
 * (CalendarDay rows), not by this entity - it only carries the time window
 * used whichever day it's scheduled for.
 */
#[ORM\Entity(repositoryClass: PresetRepository::class)]
class Preset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column]
    private ?float $lat = null;

    #[ORM\Column]
    private ?float $lng = null;

    /** Format "HH:MM", the board switches to this preset from this time... */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $startTime = null;

    /** ...until this time, format "HH:MM". */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $endTime = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(float $lat): static
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLng(): ?float
    {
        return $this->lng;
    }

    public function setLng(float $lng): static
    {
        $this->lng = $lng;

        return $this;
    }

    public function getStartTime(): ?string
    {
        return $this->startTime;
    }

    public function setStartTime(?string $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?string
    {
        return $this->endTime;
    }

    public function setEndTime(?string $endTime): static
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Whether the given moment falls within this preset's time window
     * (the day itself is decided by the calendar, not checked here).
     */
    public function matchesTimeWindow(\DateTimeImmutable $now): bool
    {
        if (!$this->enabled || $this->startTime === null || $this->endTime === null) {
            return false;
        }

        $time = $now->format('H:i');

        return $time >= $this->startTime && $time < $this->endTime;
    }
}
