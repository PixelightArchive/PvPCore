<?php

declare(strict_types=1);

namespace DogeDev\PvPCore;

use DogeDev\PvPCore\command\DuelCommand;
use DogeDev\PvPCore\command\ParkourCommand;
use DogeDev\PvPCore\command\SpawnCommand;
use DogeDev\PvPCore\command\WarpCommand;
use DogeDev\PvPCore\database\Database;
use DogeDev\PvPCore\duel\DuelManager;
use DogeDev\PvPCore\kit\KitManager;
use DogeDev\PvPCore\language\Language;
use DogeDev\PvPCore\listener\PvPCoreListener;
use DogeDev\PvPCore\parkour\ParkourManager;
use DogeDev\PvPCore\session\SessionManager;
use DogeDev\PvPCore\task\DuelHeartbeatTask;
use DogeDev\PvPCore\task\ParkourUpdateTask;
use DogeDev\PvPCore\task\RecursiveDeletionTask;
use DogeDev\PvPCore\task\ScoreboardUpdateTask;
use DogeDev\PvPCore\task\ThreadCollectionTask;
use DogeDev\PvPCore\thread\PvPCoreThreadPool;
use DogeDev\PvPCore\warp\WarpManager;
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
