<?php

declare(strict_types=1);

namespace Davewats\PvPCore\session;

class SessionDataCache
{
    protected Session $session;
    protected int $kills;
    protected int $deaths;
    protected int $streak;
    protected int $wins;
    protected int $loses;
    protected int $duel_streak;
    protected bool $loaded;

    public function __construct(Session $session)
    {
        $this->session = $session;
        $this->loaded = false;
    }

    public function getStreak(): int
    {
        return $this->streak;
    }

    public function setStreak(int $streak): void
    {
        $this->streak = $streak;
    }

    public function getDuelStreak(): int
    {
        return $this->duel_streak;
    }

    public function setDuelStreak(int $duel_streak): void
    {
        $this->duel_streak = $duel_streak;
    }

    public function getKDR(): float
    {
        $kdr = 0.0;
        try {
            $kdr = $this->getKills() / $this->getDeaths();
        } catch (\Exception $exception) {
            $kdr = 0.0;
        }
        return $kdr;
    }

    public function getKills(): int
    {
        return $this->kills;
    }

    public function setKills(int $kills): void
    {
        $this->kills = $kills;
    }

    public function getDeaths(): int
    {
        return $this->deaths;
    }

    public function setDeaths(int $deaths): void
    {
        $this->deaths = $deaths;
    }

    public function getWLR(): float
    {
        if ($this->getWins() <= 0 || $this->getLoses()) {
            return 0.0;
        }
        return $this->getWins() / $this->getLoses();
    }

    public function getWins(): int
    {
        return $this->wins;
    }

    public function setWins(int $wins): void
    {
        $this->wins = $wins;
    }

    public function getLoses(): int
    {
        return $this->loses;
    }

    public function setLoses(int $loses): void
    {
        $this->loses = $loses;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function setLoaded(bool $loaded): void
    {
        $this->loaded = $loaded;
    }
}
