<?php

declare(strict_types=1);

namespace Davewats\PvPCore\parkour;

use Davewats\PvPCore\PvPCore;
use Davewats\PvPCore\utils\Utils;
use pocketmine\player\Player;
use pocketmine\world\Position;

class ParkourManager
{
    protected PvPCore $plugin;
    /**
     * @var ParkourSession[]
     */
    protected array $sessions;
    /**
     * @var Parkour[]
     */
    protected array $games;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        $this->sessions = [];
        $this->games = [];
        $parkourData = json_decode(file_get_contents($plugin->getDataFolder() . "parkour.json"), true);
        foreach ($parkourData as $parkour => $data) {
            if (count($data) <= 0) {
                continue;
            }
            $this->createGame($parkour, $data);
        }
    }

    public function createGame(string $game, array $data): bool
    {
        if ($this->isGameExists($game)) {
            return false;
        }
        $this->getPlugin()->getServer()->getWorldManager()->loadWorld($data["world"]);
        $world = $this->getPlugin()->getServer()->getWorldManager()->getWorldByName($data["world"]);
        $start = Position::fromObject(Utils::stringToVector($data["start"]), $world);
        $end = Position::fromObject(Utils::stringToVector($data["end"]), $world);
        $checkpoints = [];
        foreach ($data["checkpoints"] as $index => $checkpoint) {
            $checkpoints[$index + 1] = Position::fromObject(Utils::stringToVector($checkpoint), $world);
        }
        $this->games[$game] = new Parkour($game, $world, $start, $end, $checkpoints);
        return true;
    }

    public function isGameExists(string $map): bool
    {
        return $this->getGame($map) !== null;
    }

    public function getGame(string $map): ?Parkour
    {
        return $this->games[$map] ?? null;
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }

    public function createSession(Player $player, Parkour $parkour): bool
    {
        if ($this->hasSession($player->getUniqueId()->toString())) {
            return false;
        }
        $this->sessions[$player->getUniqueId()->toString()] = new ParkourSession($player, $parkour);
        return true;
    }

    public function hasSession(string $uid): bool
    {
        return $this->getSession($uid) !== null;
    }

    public function getSession(string $uid): ?ParkourSession
    {
        return $this->sessions[$uid] ?? null;
    }

    public function removeSession(string $uid): bool
    {
        if (!$this->hasSession($uid)) {
            return false;
        }
        unset($this->sessions[$uid]);
        return true;
    }

    public function removeGame(string $game): bool
    {
        if (!$this->isGameExists($game)) {
            return false;
        }
        unset($this->games[$game]);
        return true;
    }

    /**
     * @return ParkourSession[]
     */
    public function getSessions(): array
    {
        return $this->sessions;
    }

    /**
     * @return Parkour[]
     */
    public function getGames(): array
    {
        return $this->games;
    }
}
