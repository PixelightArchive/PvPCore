<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\kit;

use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\player\Player;

class Kit
{
    protected string $name;
    /**
     * @var Item[]
     */
    protected array $items;
    /**
     * @var Armor[]
     */
    protected array $armor;

    public function __construct(string $name, array $items, array $armor)
    {
        $this->name = $name;
        $this->items = $items;
        $this->armor = $armor;
    }

    public function sendContents(Player $player): void
    {
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEffects()->clear();
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood(20.00);
        $player->getInventory()->setContents($this->items);
        $player->getArmorInventory()->setContents($this->armor);
    }

    public function getArmor(): array
    {
        return $this->armor;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
