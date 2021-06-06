<?php

declare(strict_types=1);

namespace Davewats\PvPCore\task;

use Davewats\PvPCore\PvPCore;
use Exception;
use pocketmine\scheduler\Task;

class ThreadCollectionTask extends Task
{
    protected PvPCore $plugin;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(): void
    {
        try {
            $this->getPlugin()->getThreadPool()->collectTasks();
        } catch (Exception $exception) {
            $this->getPlugin()->getLogger()->debug($exception->getMessage());
        }
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }
}
