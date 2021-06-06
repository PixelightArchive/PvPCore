<?php

declare(strict_types=1);

namespace Davewats\PvPCore\task;

use Davewats\PvPCore\PvPCore;
use pocketmine\scheduler\Task;

class DuelHeartbeatTask extends Task
{
    protected PvPCore $plugin;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(): void
    {
        foreach ($this->getPlugin()->getDuelManager()->getDuels() as $duel) {
            $duel->heartbeat();
        }
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }
}
