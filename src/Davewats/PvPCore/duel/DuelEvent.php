<?php

declare(strict_types=1);

namespace Davewats\PvPCore\duel;

abstract class DuelEvent
{
    protected int $duration;
    protected int $ticks;

    public function __construct(int $duration)
    {
        $this->duration = $duration;
        $this->ticks = $duration;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function getTicks(): int
    {
        return $this->ticks;
    }

    final public function tick(): void
    {
        $this->ticks--;
    }

    abstract public function getName(): string;

    abstract public function call(Duel $duel): void;
}
