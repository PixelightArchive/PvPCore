<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\warp;

use DogeDev\PvPCore\kit\Kit;
use DogeDev\PvPCore\PvPCore;
use pocketmine\world\World;

class WarpManager
{
    protected PvPCore $plugin;
    /**
     * @var Warp[]
     */
    protected array $warps;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        $this->warps = [];
        $warps = json_decode(file_get_contents($plugin->getDataFolder() . "warps.json"), true);
        foreach ($warps as $warp => $data) {
            $this->getPlugin()->getServer()->getWorldManager()->loadWorld($data["world"]);
            $kit = $this->getPlugin()->getKitManager()->getKit($data["kit"]);
            $world = $this->getPlugin()->getServer()->getWorldManager()->getWorldByName($data["world"]);
            if (!$kit || !$world) {
                continue;
            }
            $this->addWarp($warp, $world, $kit, $data["icon"] ?? null);
        }
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }

    public function addWarp(string $warp, World $world, Kit $kit, ?string $icon): bool
    {
        if ($this->isWarpRegistered($warp)) {
            return false;
        }
        $this->warps[strtolower($warp)] = new Warp($warp, $world, $kit, $icon);
        return true;
    }

    public function isWarpRegistered(string $warp): bool
    {
        return $this->getWarp($warp) !== null;
    }

    public function getWarp(string $warp): ?Warp
    {
        return $this->warps[strtolower($warp)] ?? null;
    }

    public function removeWarp(string $warp): bool
    {
        if (!$this->isWarpRegistered($warp)) {
            return false;
        }
        unset($this->warps[strtolower($warp)]);
        return true;
    }

    /**
     * @return Warp[]
     */
    public function getWarps(): array
    {
        return $this->warps;
    }
}
