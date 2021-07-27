<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\warp;

use DogeDev\PvPCore\kit\Kit;
use DogeDev\PvPCore\language\Language;
use pocketmine\player\Player;
use pocketmine\world\World;

class Warp
{
    protected string $name;
    protected World $world;
    protected Kit $kit;
    protected ?string $icon;

    public function __construct(string $name, World $world, Kit $kit, ?string $icon)
    {
        $this->name = $name;
        $this->world = $world;
        $this->kit = $kit;
        $this->icon = $icon;
    }

    public function warpTo(Player $player): void
    {
        $this->getWorld()->requestChunkPopulation($this->getWorld()->getSpawnLocation()->getFloorX() >> 4, $this->getWorld()->getSpawnLocation()->getFloorZ() >> 4, null)->onCompletion(
            function () use ($player): void {
                if (!$player->isConnected()) {
                    return;
                }
                $player->teleport($this->getWorld()->getSpawnLocation());
                $player->sendMessage(Language::getMessage("playerWarpMessage", ["{WARP}" => $this->getName()]));
            },
            static function () use ($player): void {
                $player->sendMessage(Language::getMessage("playerWarpFailMessage", ["{WARP}" => $this->getName()]));
            }
        );
        $this->getKit()->sendContents($player);
    }

    public function getWorld(): World
    {
        return $this->world;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKit(): Kit
    {
        return $this->kit;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }
}
