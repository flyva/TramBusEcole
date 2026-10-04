<?php

namespace App\Entity;

use App\Repository\CalendarDayRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Assigns a Preset to one specific calendar date (e.g. "6 octobre 2026 =
 * École"). This is what actually drives the board - Preset only carries
 * the address and time window, not which days it applies to.
 *
 * A date can have several of these (e.g. École in the morning, Travail in
 * the afternoon) - PresetRepository::findScheduledForNow() picks whichever
 * one's own time window matches the current time.
 */
#[ORM\Entity(repositoryClass: CalendarDayRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_calendar_day_date_preset', columns: ['date', 'preset_id'])]
class CalendarDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(targetEntity: Preset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Preset $preset = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getPreset(): ?Preset
    {
        return $this->preset;
    }

    public function setPreset(?Preset $preset): static
    {
        $this->preset = $preset;

        return $this;
    }
}
