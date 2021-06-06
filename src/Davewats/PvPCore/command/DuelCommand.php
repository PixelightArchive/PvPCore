<?php

declare(strict_types=1);

namespace Davewats\PvPCore\command;

use Davewats\PvPCore\form\CustomForm;
use Davewats\PvPCore\form\NormalForm;
use Davewats\PvPCore\PvPCore;
use Davewats\PvPCore\session\setup\DuelSetupMode;
use Davewats\PvPCore\task\RecursiveCloneTask;
use Davewats\PvPCore\task\RecursiveDeletionTask;
use Davewats\PvPCore\utils\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class DuelCommand extends Command
{
    protected PvPCore $plugin;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("duel", "Duel command", null, ["duels"]);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Run the command in game.");
            return;
        }
        switch ($args[0] ?? "join") {
            case "create":
                if (!$sender->hasPermission("pvpcore.admin")) {
                    $sender->sendMessage(TextFormat::RED . "You don't have enough permissions to perform this command.");
                    return;
                }
                $form = new CustomForm();
                $modes = array_keys($this->plugin->getDuelManager()->getModes());
                $worlds = [];
                $defaultWorld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getFolderName();
                foreach (scandir($this->plugin->getServer()->getDataPath() . DIRECTORY_SEPARATOR . "worlds") as $world) {
                    if ($world === "." || $world === ".." || $defaultWorld === $world) {
                        continue;
                    }
                    $worlds[] = $world;
                }
                $form->setCallback(function (Player $player, $data) use ($worlds, $modes) {
                    if ($data === null) {
                        return;
                    }
                    $world = $worlds[$data[0]] ?? null;
                    $arena = $data[1];
                    $mode = strtolower($modes[$data[2]]) ?? null;
                    $time = $data[3];
                    $countdown = $data[4];
                    $end = $data[5];
                    if ($world === null || $arena === "" || $mode === null) {
                        $player->sendMessage(TextFormat::RED . "Please fill in valid data, check your (world, arena, mode) if they are valid.");
                        return;
                    }
                    if ($this->plugin->getDuelManager()->isArenaExists($arena)) {
                        $player->sendMessage(TextFormat::RED . "An arena already exists with that name.");
                        return;
                    }
                    $player->sendMessage(TextFormat::GREEN . "Successfully created the $arena arena, setup the positions.");
                    $data = ["name" => $arena, "mode" => $mode, "time" => $time, "countdown" => $countdown, "end" => $end, "positions" => [], "spectatorPosition" => ""];
                    $this->plugin->getDuelManager()->addArena($arena, $data);
                    @mkdir($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $arena);
                    Utils::recursiveCopy($this->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . $world, $this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $arena . DIRECTORY_SEPARATOR . "world");
                    $config = new Config($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $arena . DIRECTORY_SEPARATOR . "data.json", Config::JSON);
                    $config->setAll($data);
                    $config->save();
                    $this->plugin->getDuelManager()->addArena($arena, $data);
                    $this->plugin->getServer()->getWorldManager()->loadWorld($world);
                    if ($this->plugin->getServer()->getWorldManager()->isWorldLoaded($world)) {
                        $randomId = mt_rand(1, 1000);
                        $this->plugin->getThreadPool()->submitCallbackTask(new RecursiveCloneTask($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $arena . DIRECTORY_SEPARATOR . "world", $this->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . "duel-setup-" . $randomId), function () use ($player, $arena, $randomId) {
                            if (!$this->plugin->getServer()->getWorldManager()->loadWorld("duel-setup-" . $randomId)) {
                                $player->sendMessage(TextFormat::RED . "Failed to load the world, please retry later.");
                                $this->plugin->getThreadPool()->submitTask(new RecursiveDeletionTask($this->plugin->getServer()->getDataPath() . "worlds", ["duel-setup-" . $randomId]));
                                return;
                            }
                            $gameWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName("duel-setup-" . $randomId);
                            $session = $this->plugin->getSessionManager()->getSession($player->getUniqueId()->toString());
                            $spawn = $gameWorld->getSpawnLocation();
                            $gameWorld->requestChunkPopulation($spawn->getFloorX() >> 4, $spawn->getFloorZ() >> 4, null)->onCompletion(
                                function () use ($player, $session, $spawn, $arena, $randomId): void {
                                    if (!$player->isConnected()) {
                                        return;
                                    }
                                    $setupMode = new DuelSetupMode($session);
                                    $setupMode->setArena($arena);
                                    $setupMode->setClonedWorld("duel-setup-" . $randomId);
                                    $session->setSetupMode($setupMode);
                                    $player->teleport($spawn);
                                },
                                static function () use ($player, $randomId): void {
                                    $this->plugin->getThreadPool()->submitTask(new RecursiveDeletionTask($this->plugin->getServer()->getDataPath() . "worlds", ["duel-setup-" . $randomId]));
                                    $player->sendMessage(TextFormat::RED . "Failed to load the world spawn chunk, please edit the arena via /duel edit.");
                                }
                            );
                        });
                    }
                });
                $form->setTitle("Create an Arena");
                $form->addDropdown("World", $worlds);
                $form->addInput("Name");
                $form->addDropdown("Mode", $modes);
                $form->addSlider("Duel time (minutes)", 1, 100);
                $form->addSlider("Countdown time (seconds)", 5, 100);
                $form->addSlider("End time (seconds)", 5, 100);
                $sender->sendForm($form);
                break;
            case "delete":
                if (!$sender->hasPermission("pvpcore.admin")) {
                    $sender->sendMessage(TextFormat::RED . "You don't have enough permissions to perform this command.");
                    return;
                }
                $arenas = array_keys($this->plugin->getDuelManager()->getArenas());
                if (count($arenas) <= 0) {
                    $sender->sendMessage(TextFormat::RED . "Couldn't find arenas, try using /duel create to create one.");
                    return;
                }
                $form = new CustomForm();
                $form->setCallback(function (Player $player, $data) use ($arenas) {
                    if (!$data) {
                        return;
                    }
                    $arena = $arenas[$data[0]] ?? null;
                    if ($arena === null) {
                        $player->sendMessage(TextFormat::RED . "You don't have any arenas to delete.");
                        return;
                    }
                    $this->plugin->getDuelManager()->removeArena($arena);
                    $this->plugin->getThreadPool()->submitTask(new RecursiveDeletionTask($this->plugin->getDataFolder() . DIRECTORY_SEPARATOR . "arenas" . DIRECTORY_SEPARATOR, [$arena]));
                    $player->sendMessage(TextFormat::RED . "Successfully deleted the $arena arena.");
                });
                $form->setTitle("Delete an Arena");
                $form->addDropdown("Arena", $arenas);
                $sender->sendForm($form);
                break;
            case "edit":
                if (!$sender->hasPermission("pvpcore.admin")) {
                    $sender->sendMessage(TextFormat::RED . "You don't have enough permissions to perform this command.");
                    return;
                }
                $arenas = array_keys($this->plugin->getDuelManager()->getArenas());
                if (count($arenas) <= 0) {
                    $sender->sendMessage(TextFormat::RED . "Couldn't find arenas, try using /duel create to create one.");
                    return;
                }
                $modes = array_keys($this->plugin->getDuelManager()->getModes());
                $form = new CustomForm();
                $form->setCallback(function (Player $player, $data) use ($arenas, $modes) {
                    if (!$data) {
                        return;
                    }
                    if ($data[0] === null) {
                        $player->sendMessage(TextFormat::RED . "You must select an arena to edit.");
                    }
                    $arena = $arenas[$data[0]];
                    $oldData = $this->plugin->getDuelManager()->getArena($arena);
                    if (!$oldData) {
                        $player->sendMessage(TextFormat::RED . "That arena no longer exists.");
                    }
                    $name = $data[1] !== "" ? $data[1] : $oldData["name"];
                    $mode = strtolower($modes[$data[2]]) ?? $oldData["mode"];
                    $editPositions = $data[3];
                    $time = (int)$data[4];
                    $countdown = (int)$data[5];
                    $end = (int)$data[6];
                    $positions = $editPositions ? [] : $oldData["positions"];
                    $spectatorPosition = $editPositions ? "" : $oldData["spectatorPosition"];
                    $data = ["name" => $name, "mode" => $mode, "time" => $time, "countdown" => $countdown, "end" => $end, "positions" => $positions, "spectatorPosition" => $spectatorPosition];
                    if ($name !== $arena) {
                        rename($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $arena, $this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name);
                    }
                    $config = new Config($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . "data.json", Config::JSON);
                    $config->setAll($data);
                    $config->save();
                    // Can't reload if the name has changed.
                    $this->plugin->getDuelManager()->removeArena($arena);
                    $this->plugin->getDuelManager()->addArena($name, $data);
                    $player->sendMessage(TextFormat::YELLOW . "Successfully edited the $arena arena.");
                    if ($editPositions) {
                        $randomId = mt_rand(1, 1000);
                        $this->plugin->getThreadPool()->submitCallbackTask(new RecursiveCloneTask($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . "world", $this->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . "duel-setup-" . $randomId), function () use ($player, $name, $randomId) {
                            if (!$this->plugin->getServer()->getWorldManager()->loadWorld("duel-setup-" . $randomId)) {
                                $player->sendMessage(TextFormat::RED . "Failed to load the world, please retry later.");
                                $this->plugin->getThreadPool()->submitTask(new RecursiveDeletionTask($this->plugin->getServer()->getDataPath() . "worlds", ["duel-setup-" . $randomId]));
                                return;
                            }
                            $gameWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName("duel-setup-" . $randomId);
                            $session = $this->plugin->getSessionManager()->getSession($player->getUniqueId()->toString());
                            $spawn = $gameWorld->getSpawnLocation();
                            $gameWorld->requestChunkPopulation($spawn->getFloorX() >> 4, $spawn->getFloorZ() >> 4, null)->onCompletion(
                                function () use ($player, $session, $spawn, $name, $randomId): void {
                                    if (!$player->isConnected()) {
                                        return;
                                    }
                                    $setupMode = new DuelSetupMode($session);
                                    $setupMode->setArena($name);
                                    $setupMode->setClonedWorld("duel-setup-" . $randomId);
                                    $session->setSetupMode($setupMode);
                                    $player->teleport($spawn);
                                },
                                static function () use ($player, $randomId): void {
                                    $player->sendMessage(TextFormat::RED . "Failed to load the world spawn chunk, please retry later.");
                                    $this->plugin->getThreadPool()->submitTask(new RecursiveDeletionTask($this->plugin->getServer()->getDataPath() . "worlds", ["duel-setup-" . $randomId]));
                                }
                            );
                        });
                    }
                });
                $form->setTitle("Edit an Arena");
                $form->addDropdown("Arena", $arenas);
                $form->addInput("Name");
                $form->addDropdown("Mode", $modes);
                $form->addToggle("Edit Positions");
                $form->addSlider("Duel time (minutes)", 1, 100);
                $form->addSlider("Countdown time (seconds)", 5, 100);
                $form->addSlider("End time (seconds)", 5, 100);
                $sender->sendForm($form);
                break;
            case "help":
                if (!$sender->hasPermission("pvpcore.admin")) {
                    $sender->sendMessage(TextFormat::RED . "Usage /duel <join|random|settings|stats>");
                    return;
                }
                $sender->sendMessage(TextFormat::RED . "Usage /duel <create|delete|edit|join|random|stats>");
                break;
            case "join":
                $session = $this->plugin->getSessionManager()->getSession($sender->getUniqueId()->toString());
                if ($session->isInDuel()) {
                    $sender->sendMessage(TextFormat::RED . "You're already in a duel.");
                    return;
                }
                if ($session->isQueued()) {
                    $sender->sendMessage(TextFormat::RED . "You're already in a queue.");
                    return;
                }
                $form = new NormalForm();
                $form->setTitle("Duels");
                $form->setContent("Select a mode:");
                $form->setCallback(function (Player $player, ?string $mode) use ($session) {
                    if (!$mode) {
                        return;
                    }
                    $this->plugin->getDuelManager()->queueToDuel([$session], null, $mode, true);
                });
                foreach ($this->plugin->getDuelManager()->getModes() as $mode => $data) {
                    if ($data["icon"] !== null) {
                        $form->addButton(ucwords($mode), strpos("http", $data["icon"]) === 0 ? NormalForm::IMAGE_TYPE_URL : NormalForm::IMAGE_TYPE_PATH, $data["icon"]);
                        continue;
                    }
                    $form->addButton(ucwords($mode));
                }
                $form->addButton(TextFormat::RED . TextFormat::BOLD . "Exit", NormalForm::IMAGE_TYPE_PATH, "textures/blocks/barrier");
                $sender->sendForm($form);
                break;
            case "random":
                $session = $this->plugin->getSessionManager()->getSession($sender->getUniqueId()->toString());
                if ($session->isInDuel()) {
                    $sender->sendMessage(TextFormat::RED . "You're already in a .");
                    return;
                }
                if ($session->isQueued()) {
                    $sender->sendMessage(TextFormat::RED . "You're already in a queue.");
                    return;
                }
                $this->plugin->getDuelManager()->queueToDuel([$session], null, null, true);
                break;
            case "quit":
                $session = $this->plugin->getSessionManager()->getSession($sender->getUniqueId()->toString());
                $duel = $session->getDuel();
                if ($session->isInDuel()) {
                    $duel->removeFromDuel($session, $duel->isAlive($sender->getUniqueId()->toString()));
                } else {
                    $sender->sendMessage(TextFormat::RED . "You're not in a duel.");
                }
        }
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }
}
