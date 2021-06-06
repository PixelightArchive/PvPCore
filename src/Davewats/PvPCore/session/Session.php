<?php

declare(strict_types=1);

namespace Davewats\PvPCore\session;

use Davewats\PvPCore\constants\PlayerStatusConstants;
use Davewats\PvPCore\duel\Duel;
use Davewats\PvPCore\PvPCore;
use Davewats\PvPCore\session\setup\SetupMode;
use Davewats\PvPCore\task\PlayerDataRegisterTask;
use Davewats\PvPCore\task\PlayerDataRetrieveTask;
use Davewats\PvPCore\task\PlayerDataUpdateTask;
use pocketmine\player\Player;

class Session
{
    protected PvPCore $plugin;
    protected Player $player;
    protected ?Duel $duel;
    protected ?SetupMode $setupMode;
    protected SessionDataCache $dataCache;
    protected bool $queued;
    protected int $status;

    public function __construct(PvPCore $plugin, Player $player)
    {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->duel = null;
        $this->setupMode = null;
        $this->queued = false;
        $this->status = PlayerStatusConstants::PLAYER_STATUS_UNKNOWN;
        $this->dataCache = new SessionDataCache($this);
        $plugin->getDatabase()->submitTask(new PlayerDataRetrieveTask($player->getXuid()), function (PlayerDataRetrieveTask $task): void {
            $this->getDataCache()->setKills($task->getResult()["kills"] ?? 0);
            $this->getDataCache()->setDeaths($task->getResult()["deaths"] ?? 0);
            $this->getDataCache()->setStreak($task->getResult()["streak"] ?? 0);
            $this->getDataCache()->setWins($task->getResult()["wins"] ?? 0);
            $this->getDataCache()->setLoses($task->getResult()["duel_loses"] ?? 0);
            $this->getDataCache()->setDuelStreak($task->getResult()["duel_streak"] ?? 0);
            $this->getDataCache()->setLoaded(true);
        });
        $plugin->getDatabase()->submitTask(new PlayerDataRegisterTask($player->getXuid()));
    }

    public function getDataCache(): SessionDataCache
    {
        return $this->dataCache;
    }

    public function getDuel(): ?Duel
    {
        return $this->duel;
    }

    public function setDuel(?Duel $duel): void
    {
        $this->duel = $duel;
    }

    public function isQueued(): bool
    {
        return $this->queued;
    }

    public function setQueued(bool $queued): void
    {
        $this->queued = $queued;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getSetupMode(): ?SetupMode
    {
        return $this->setupMode;
    }

    public function setSetupMode(?SetupMode $setupMode): void
    {
        if ($this->setupMode) {
            $this->getSetupMode()->deactivate();
        }
        $this->setupMode = $setupMode;
        if ($setupMode) {
            $this->setupMode->activate();
        }
    }

    public function isInDuel(): bool
    {
        return $this->duel !== null;
    }

    public function save(): void
    {
        $this->getPlugin()->getDatabase()->submitTask(new PlayerDataUpdateTask($this->getPlayer()->getXuid(), $this->getDataCache()->getKills(), $this->getDataCache()->getDeaths(), $this->getDataCache()->getStreak(), $this->getDataCache()->getWins(), $this->getDataCache()->getLoses(), $this->getDataCache()->getDuelStreak()));
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }
}
