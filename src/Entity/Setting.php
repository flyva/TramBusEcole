<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Single-row table holding the board's configurable settings
 * (currently just the screen on/off schedule).
 */
#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $scheduleEnabled = false;

    /** Screen turns on at this time each day, format "HH:MM". */
    #[ORM\Column(length: 5, options: ['default' => '07:00'])]
    private string $screenOnTime = '07:00';

    /** Screen turns off at this time each day, format "HH:MM". */
    #[ORM\Column(length: 5, options: ['default' => '22:00'])]
    private string $screenOffTime = '22:00';

    /** Manual "show this preset right now" override, for unplanned days. */
    #[ORM\ManyToOne]
    private ?Preset $overridePreset = null;

    /** The override stops applying automatically after this moment. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $overrideUntil = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isScheduleEnabled(): bool
    {
        return $this->scheduleEnabled;
    }

    public function setScheduleEnabled(bool $scheduleEnabled): static
    {
        $this->scheduleEnabled = $scheduleEnabled;

        return $this;
    }

    public function getScreenOnTime(): string
    {
        return $this->screenOnTime;
    }

    public function setScreenOnTime(string $screenOnTime): static
    {
        $this->screenOnTime = $screenOnTime;

        return $this;
    }

    public function getScreenOffTime(): string
    {
        return $this->screenOffTime;
    }

    public function setScreenOffTime(string $screenOffTime): static
    {
        $this->screenOffTime = $screenOffTime;

        return $this;
    }

    public function getOverridePreset(): ?Preset
    {
        return $this->overridePreset;
    }

    public function setOverridePreset(?Preset $overridePreset): static
    {
        $this->overridePreset = $overridePreset;

        return $this;
    }

    public function getOverrideUntil(): ?\DateTimeImmutable
    {
        return $this->overrideUntil;
    }

    public function setOverrideUntil(?\DateTimeImmutable $overrideUntil): static
    {
        $this->overrideUntil = $overrideUntil;

        return $this;
    }

    /**
     * The currently-active manual override preset, or null if none is set
     * or it has expired.
     */
    public function getActiveOverride(\DateTimeImmutable $now): ?Preset
    {
        if ($this->overridePreset === null || $this->overrideUntil === null) {
            return null;
        }

        return $now < $this->overrideUntil ? $this->overridePreset : null;
    }
}
