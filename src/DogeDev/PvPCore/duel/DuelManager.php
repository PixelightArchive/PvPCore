<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\duel;

use DogeDev\PvPCore\PvPCore;
use DogeDev\PvPCore\session\Session;
use pocketmine\utils\TextFormat;

class DuelManager
{
    protected PvPCore $plugin;
    /**
     * @var Duel[]
     */
    protected array $duels;
    protected int $generatedDuels;
    protected array $arenas;
    protected array $modes;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        $this->duels = [];
        $this->generatedDuels = 0;
        $this->loadArenas();
        $this->loadModes();
    }

    public function loadArenas(): void
    {
        $this->arenas = [];
        foreach (scandir($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR) as $arena) {
            if ($arena === "." || $arena === ".." || is_file($arena)) {
                continue;
            }
            $data = json_decode(file_get_contents($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $arena . DIRECTORY_SEPARATOR . "data.json"), true);
            $this->addArena($data["name"], $data);
        }
    }

    public function addArena(string $arena, array $data): bool
    {
        if ($this->isArenaExists($arena)) {
            return false;
        }
        $this->arenas[$arena] = $data;
        return true;
    }

    public function isArenaExists(string $name): bool
    {
        return isset($this->arenas[$name]);
    }

    public function loadModes(): void
    {
        $this->modes = [];
        $modes = json_decode(file_get_contents($this->plugin->getDataFolder() . "modes.json"), true);
        foreach ($modes as $name => $data) {
            $this->modes[$name] = $data;
        }
    }

    public function reloadArena(string $arena): bool
    {
        if (!isset($this->arenas[$arena])) {
            return false;
        }
        $this->removeArena($arena);
        $data = json_decode(file_get_contents($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $arena . DIRECTORY_SEPARATOR . "data.json"), true);
        $this->addArena($arena, $data);
        return true;
    }

    public function removeArena(string $arena): bool
    {
        if (!$this->isArenaExists($arena)) {
            return false;
        }
        unset($this->arenas[$arena]);
        return true;
    }

    /**
     * @param Session[] $queues
     * @param string|null $map
     * @param string|null $mode
     * @param bool $create
     */
    public function queueToDuel(array $queues, ?string $map = null, ?string $mode = null, bool $create = false): void
    {
        $availableDuels = [];
        foreach ($this->duels as $index => $duel) {
            if (
                $map !== null && $duel->getMap() !== $map ||
                $mode !== null && $duel->getMode() !== $mode ||
                !$duel->isDuelFree()
            ) {
                continue;
            }
            $availableDuels[$index] = $duel;
        }
        if (count($availableDuels) >= 1 && count($queues) >= 1) {
            $game = $this->duels[array_rand($availableDuels)];
            foreach ($queues as $queue) {
                $game->addToDuel($queue);
            }
            return;
        }
        if (!$create || count($this->arenas) < 1 || $map && !isset($this->arenas[$map])) {
            foreach ($queues as $queue) {
                $queue->getPlayer()->sendMessage(TextFormat::RED . "Couldn't find a duel, retry later.");
            }
            return;
        }
        $arena = $this->arenas[$map ?? array_rand($this->arenas)];
        $mode = $this->getMode($arena["mode"]);
        $kit = $this->plugin->getKitManager()->getKit($mode["kit"] ?? "");
        if (!$mode || !$kit) {
            foreach ($queues as $queue) {
                $queue->getPlayer()->sendMessage(TextFormat::RED . "Couldn't find a duel, retry later.");
            }
            return;
        }
        $duel = new Duel($this->plugin, $this->generatedDuels, $arena, $kit);
        $this->duels[$this->generatedDuels] = $duel;
        $this->generatedDuels++;
        foreach ($queues as $queue) {
            $duel->addToDuel($queue);
        }
    }

    public function getMode(string $mode): ?array
    {
        return $this->modes[$mode] ?? null;
    }

    public function removeDuel(int $id): bool
    {
        if ($this->isDuelExists($id)) {
            return false;
        }
        unset($this->duels[$id]);
        return true;
    }

    public function isDuelExists(int $id): bool
    {
        return isset($this->games[$id]);
    }

    public function getDuel(int $id): ?Duel
    {
        return $this->duels[$id] ?? null;
    }

    public function getDuels(): array
    {
        return $this->duels;
    }

    public function getArena(string $arena): ?array
    {
        return $this->arenas[$arena] ?? null;
    }

    public function getArenas(): array
    {
        return $this->arenas;
    }


    public function getModes(): array
    {
        return $this->modes;
    }
}
