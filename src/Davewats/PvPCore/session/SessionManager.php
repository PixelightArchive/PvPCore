<?php

declare(strict_types=1);

namespace Davewats\PvPCore\session;

use Davewats\PvPCore\PvPCore;
use pocketmine\player\Player;

class SessionManager
{
    protected PvPCore $plugin;
    /**
     * @var Session[]
     */
    protected array $sessions;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        $this->sessions = [];
    }

    public function createSession(Player $player): bool
    {
        if ($this->hasSession($player->getUniqueId()->toString())) {
            return false;
        }
        $this->sessions[$player->getUniqueId()->toString()] = new Session($this->getPlugin(), $player);
        return true;
    }

    public function hasSession(string $uid): bool
    {
        return $this->getSession($uid) !== null;
    }

    public function getSession(string $uid): ?Session
    {
        return $this->sessions[$uid] ?? null;
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }

    public function removeSession(string $uid): bool
    {
        if (!$this->hasSession($uid)) {
            return false;
        }
        return true;
    }

    /**
     * @return Session[]
     */
    public function getSessions(): array
    {
        return $this->sessions;
    }
}
