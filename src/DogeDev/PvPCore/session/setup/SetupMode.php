<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\session\setup;

use DogeDev\PvPCore\session\Session;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\player\GameMode;
use pocketmine\utils\TextFormat;

abstract class SetupMode
{
    protected Session $session;
    protected bool $activated;

    final public function __construct(Session $session)
    {
        $this->session = $session;
    }

    final public function isActivated(): bool
    {
        return $this->activated;
    }

    final public function activate(): void
    {
        if ($this->getSession()->getPlayer()->isConnected()) {
            $this->getSession()->getPlayer()->getInventory()->clearAll();
            $this->getSession()->getPlayer()->getArmorInventory()->clearAll();
            $this->getSession()->getPlayer()->getCursorInventory()->clearAll();
            $this->getSession()->getPlayer()->setGamemode(GameMode::CREATIVE());
            $this->getSession()->getPlayer()->getInventory()->setItem(8, ItemFactory::getInstance()->get(ItemIds::BED, 14)->setCustomName(TextFormat::RESET . TextFormat::RED . "Quit Setup Mode"));
            $this->activated = true;
            $this->onActivate();
        }
    }

    final protected function getSession(): Session
    {
        return $this->session;
    }

    protected function onActivate(): void
    {
    }

    final public function deactivate(): void
    {
        $this->activated = false;
        $this->getSession()->getPlayer()->getInventory()->clearAll();
        $this->getSession()->getPlayer()->getArmorInventory()->clearAll();
        $this->getSession()->getPlayer()->getCursorInventory()->clearAll();
        $this->onDeactivate();
    }

    protected function onDeactivate(): void
    {
    }
}
