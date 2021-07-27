<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\session\setup;

use DogeDev\PvPCore\session\Session;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\utils\TextFormat;

class ParkourSetupMode extends SetupMode
{
    protected Session $session;
    protected ?string $name;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    protected function onActivate(): void
    {
        if ($this->getSession()->getPlayer()->isConnected()) {
            $this->getSession()->getPlayer()->sendMessage(TextFormat::GREEN . "You've entered the setup mode, use the items in your inventory to set the locations.");
            $this->getSession()->getPlayer()->getInventory()->setItem(2, ItemFactory::getInstance()->get(ItemIds::HEAVY_WEIGHTED_PRESSURE_PLATE)->setCustomName(TextFormat::RESET . TextFormat::GRAY . "Add Checkpoint"));
            $this->getSession()->getPlayer()->getInventory()->setItem(4, ItemFactory::getInstance()->get(ItemIds::LIGHT_WEIGHTED_PRESSURE_PLATE)->setCustomName(TextFormat::RESET . TextFormat::AQUA . "Set Starting point"));
            $this->getSession()->getPlayer()->getInventory()->setItem(6, ItemFactory::getInstance()->get(ItemIds::LIGHT_WEIGHTED_PRESSURE_PLATE)->setCustomName(TextFormat::RESET . TextFormat::GOLD . "Set Endpoint"));
        }
    }
}
