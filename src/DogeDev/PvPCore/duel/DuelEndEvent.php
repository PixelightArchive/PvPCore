<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\duel;

class DuelEndEvent extends DuelEvent
{

    public function getName(): string
    {
        return "Game End";
    }

    public function call(Duel $duel): void
    {
        $duel->destructDuel();
    }
}
