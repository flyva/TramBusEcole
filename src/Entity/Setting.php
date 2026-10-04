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
