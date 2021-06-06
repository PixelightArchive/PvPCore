<?php

declare(strict_types=1);

namespace Davewats\PvPCore;

use Davewats\PvPCore\command\DuelCommand;
use Davewats\PvPCore\command\ParkourCommand;
use Davewats\PvPCore\command\SpawnCommand;
use Davewats\PvPCore\command\WarpCommand;
use Davewats\PvPCore\database\Database;
use Davewats\PvPCore\duel\DuelManager;
use Davewats\PvPCore\kit\KitManager;
use Davewats\PvPCore\language\Language;
use Davewats\PvPCore\listener\PvPCoreListener;
use Davewats\PvPCore\parkour\ParkourManager;
use Davewats\PvPCore\session\SessionManager;
use Davewats\PvPCore\task\DuelHeartbeatTask;
use Davewats\PvPCore\task\ParkourUpdateTask;
use Davewats\PvPCore\task\RecursiveDeletionTask;
use Davewats\PvPCore\task\ScoreboardUpdateTask;
use Davewats\PvPCore\task\ThreadCollectionTask;
use Davewats\PvPCore\thread\PvPCoreThreadPool;
use Davewats\PvPCore\warp\WarpManager;
use pocketmine\plugin\PluginBase;

class PvPCore extends PluginBase
{
    protected PvPCoreThreadPool $threadPool;
    protected SessionManager $sessionManager;
    protected DuelManager $duelManager;
    protected KitManager $kitManager;
    protected WarpManager $warpManager;
    protected ParkourManager $parkourManager;
    protected Database $database;

    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }

    public function getDuelManager(): DuelManager
    {
        return $this->duelManager;
    }

    public function getKitManager(): KitManager
    {
        return $this->kitManager;
    }

    public function getWarpManager(): WarpManager
    {
        return $this->warpManager;
    }

    public function getParkourManager(): ParkourManager
    {
        return $this->parkourManager;
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getThreadPool(): PvPCoreThreadPool
    {
        return $this->threadPool;
    }

    protected function onEnable(): void
    {
        @mkdir($this->getDataFolder() . "arenas");
        foreach ($this->getResources() as $resource) {
            $this->saveResource($resource->getFilename());
        }
        $this->threadPool = new PvPCoreThreadPool($this->getConfig()->get("thread")["workers"], $this->getConfig()->get("thread")["worker-limit"], $this->getServer()->getLoader(), $this->getServer()->getLogger(), $this->getServer()->getTickSleeper());
        $this->sessionManager = new SessionManager($this);
        $this->duelManager = new DuelManager($this);
        $this->kitManager = new KitManager($this);
        $this->warpManager = new WarpManager($this);
        $this->parkourManager = new ParkourManager($this);
        $this->database = new Database($this, $this->getConfig()->get("mysql")["username"], $this->getConfig()->get("mysql")["database"], $this->getConfig()->get("mysql")["host"], $this->getConfig()->get("mysql")["password"], $this->getConfig()->get("mysql")["port"]);
        $this->getScheduler()->scheduleRepeatingTask(new DuelHeartbeatTask($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new ParkourUpdateTask($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new ScoreboardUpdateTask($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new ThreadCollectionTask($this), $this->getConfig()->get("thread")["collection-period"]);
        $this->getServer()->getPluginManager()->registerEvents(new PvPCoreListener($this), $this);
        $this->getServer()->getCommandMap()->registerAll("pvpcore", [
            new DuelCommand($this),
            new SpawnCommand($this),
            new WarpCommand($this),
            new ParkourCommand($this),
        ]);
        $this->threadPool->submitTask(new RecursiveDeletionTask($this->getServer()->getDataPath() . "worlds", glob($this->getServer()->getDataPath() . DIRECTORY_SEPARATOR . "worlds" . DIRECTORY_SEPARATOR . "duel-*")));
        Language::loadLanguage($this);
    }
}
