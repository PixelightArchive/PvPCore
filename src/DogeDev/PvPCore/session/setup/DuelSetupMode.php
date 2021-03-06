<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\session\setup;

use DogeDev\PvPCore\session\Session;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\utils\TextFormat;

class DuelSetupMode extends SetupMode
{
    protected Session $session;
    protected bool $activated;
    protected ?string $arena;
    protected ?string $clonedWorld;

    public function getArena(): ?string
    {
        return $this->arena;
    }

    public function setArena(?string $arena): void
    {
        $this->arena = $arena;
    }

    public function getClonedWorld(): ?string
    {
        return $this->clonedWorld;
    }

    public function setClonedWorld(?string $clonedWorld): void
    {
        $this->clonedWorld = $clonedWorld;
    }

    protected function onActivate(): void
    {
        $this->session->getPlayer()->sendMessage(TextFormat::GREEN . "You've entered the setup mode, use the items in your inventory to perform actions.");
        $this->session->getPlayer()->getInventory()->setItem(3, ItemFactory::getInstance()->get(ItemIds::STICK)->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Add Position"));
        $this->session->getPlayer()->getInventory()->setItem(6, ItemFactory::getInstance()->get(ItemIds::BLAZE_ROD)->setCustomName(TextFormat::RESET . TextFormat::AQUA . "Set Spectator Position"));
    }

    protected function onDeactivate(): void
    {
        $this->setArena(null);
        $this->setClonedWorld(null);
    }
}
