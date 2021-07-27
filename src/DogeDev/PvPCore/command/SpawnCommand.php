<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\command;

use DogeDev\PvPCore\PvPCore;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class SpawnCommand extends Command
{
    protected PvPCore $plugin;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("spawn", "Teleport to the spawn", null, []);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Run this command in game.");
            return;
        }
        $sender->teleport($this->getPlugin()->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }
}
