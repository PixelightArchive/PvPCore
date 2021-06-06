<?php

declare(strict_types=1);

namespace Davewats\PvPCore\kit;

use Davewats\PvPCore\PvPCore;
use Davewats\PvPCore\utils\Utils;
use function file_get_contents;
use function json_decode;

class KitManager
{
    protected PvPCore $plugin;
    /**
     * @var Kit[]
     */
    protected array $kits;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        $this->kits = [];
        $kits = json_decode(file_get_contents($plugin->getDataFolder() . "kits.json"), true);
        foreach ($kits as $kit => $contents) {
            $this->addKit($kit, $contents["items"], $contents["armor"]);
        }
    }

    public function addKit(string $kit, array $itemsData, array $armorData): bool
    {
        if ($this->isKitRegistered($kit)) {
            return false;
        }
        $items = [];
        $armor = [];
        foreach ($itemsData as $datum) {
            $items[] = Utils::parseItemFromData($datum);
        }
        foreach ($armorData as $datum) {
            $armor[] = Utils::parseItemFromData($datum);
        }
        $this->kits[$kit] = new Kit($kit, $items, $armor);
        return true;
    }

    public function isKitRegistered(string $kit): bool
    {
        return isset($this->kits[$kit]);
    }

    public function getKit(string $kit): ?Kit
    {
        return $this->kits[$kit] ?? null;
    }

    /**
     * @return Kit[]
     */
    public function getKits(): array
    {
        return $this->kits;
    }
}
