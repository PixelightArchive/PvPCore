<?php

declare(strict_types=1);

namespace Davewats\PvPCore\task;

use Davewats\PvPCore\language\Language;
use Davewats\PvPCore\PvPCore;
use Davewats\PvPCore\scoreboard\Scoreboard;
use pocketmine\scheduler\Task;

class ScoreboardUpdateTask extends Task
{
    protected PvPCore $plugin;
    protected array $scoreboard;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        $this->scoreboard = json_decode(file_get_contents($plugin->getDataFolder() . "scoreboards.json"), true)["main"] ?? [];
    }

    public function onRun(): void
    {
        foreach ($this->getPlugin()->getSessionManager()->getSessions() as $session) {
            if ($session->isInDuel() || !$session->getPlayer()->isConnected() || !$session->getDataCache()->isLoaded()) {
                continue;
            }
            $lines = [];
            foreach ($this->scoreboard as $index => $line) {
                $lines[$index] = Language::parseStringVariables($line, ["{PING}" => $session->getPlayer()->getNetworkSession()->getPing(), "{KILLS}" => $session->getDataCache()->getKills(), "{DEATHS}" => $session->getDataCache()->getDeaths(), "{STREAK}" => $session->getDataCache()->getStreak(), "{KDR}" => $session->getDataCache()->getKDR(), "{WINS}" => $session->getDataCache()->getWins(), "{LOSES}" => $session->getDataCache()->getLoses(), "{WINS_STREAK}" => $session->getDataCache()->getDuelStreak(), "{WLR}" => $session->getDataCache()->getWLR()]);
            }
            Scoreboard::create($session->getPlayer(), $this->plugin->getConfig()->get("scoreboard")["main"]);
            Scoreboard::setLines($session->getPlayer(), $lines);
        }
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }
}
