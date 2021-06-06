<?php

declare(strict_types=1);

namespace Davewats\PvPCore\task;

use Davewats\PvPCore\PvPCore;
use pocketmine\scheduler\Task;

class ParkourUpdateTask extends Task
{
    protected PvPCore $plugin;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(): void
    {
        foreach ($this->getPlugin()->getParkourManager()->getSessions() as $session) {
            $session->tick();
        }
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }
}
