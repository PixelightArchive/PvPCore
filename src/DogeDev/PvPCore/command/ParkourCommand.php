<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\command;

use DogeDev\PvPCore\form\CustomForm;
use DogeDev\PvPCore\PvPCore;
use DogeDev\PvPCore\session\setup\ParkourSetupMode;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class ParkourCommand extends Command
{
    protected PvPCore $plugin;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("parkour", "Parkour", null, []);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$this->testPermission($sender)) {
            $sender->sendMessage(TextFormat::RED . "You don't have enough permissions to perform this command.");
            return;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Run this command in-game.");
            return;
        }
        switch ($args[0] ?? "help") {
            case "create":
                $worlds = [];
                foreach (scandir($this->getPlugin()->getServer()->getDataPath() . DIRECTORY_SEPARATOR . "worlds") as $world) {
                    if ($world === "." || $world === "..") {
                        continue;
                    }
                    $worlds[] = $world;
                }
                $callback = function (Player $player, ?array $data) use ($worlds): void {
                    if (!$data) {
                        return;
                    }
                    $world = $worlds[$data[0]] ?? null;
                    $name = $data[1];
                    if (!$world || $name === "") {
                        $player->sendMessage(TextFormat::RED . "You must fill all the fields.");
                        return;
                    }
                    $config = new Config($this->getPlugin()->getDataFolder() . "parkour.json", Config::JSON);
                    $config->set($name, ["world" => $world]);
                    $config->save();
                    $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
                    $this->getPlugin()->getServer()->getWorldManager()->loadWorld($world);
                    $gameWorld = $this->getPlugin()->getServer()->getWorldManager()->getWorldByName($world);
                    $spawn = $gameWorld->getSpawnLocation();
                    $gameWorld->requestChunkPopulation($spawn->getFloorX() >> 4, $spawn->getFloorZ() >> 4, null)->onCompletion(
                        function () use ($player, $session, $spawn, $name): void {
                            if (!$player->isConnected()) {
                                return;
                            }
                            $setupMode = new ParkourSetupMode($session);
                            $setupMode->setName($name);
                            $session->setSetupMode($setupMode);
                            $player->teleport($spawn);
                        },
                        static function () use ($player): void {
                            $player->sendMessage(TextFormat::RED . "Failed to load the world spawn chunk, please edit the parkour via /parkour edit.");
                        }
                    );
                };
                $form = new CustomForm();
                $form->setCallback($callback);
                $form->setTitle("Create a Parkour");
                $form->addDropdown("World", $worlds);
                $form->addInput("Name");
                $sender->sendForm($form);
                break;
            case "delete":
                $parkour = array_keys($this->getPlugin()->getParkourManager()->getGames());
                $callback = function (Player $player, ?array $data) use ($parkour): void {
                    if (!$data) {
                        return;
                    }
                    $game = $parkour[$data[0]] ?? null;
                    if (!$game) {
                        $player->sendMessage(TextFormat::RED . "You must select a parkour to delete.");
                        return;
                    }
                    $this->getPlugin()->getParkourManager()->removeGame($game);
                    $config = new Config($this->getPlugin()->getDataFolder() . "parkour.json", Config::JSON);
                    $config->remove($game);
                    $config->save();
                    $player->sendMessage(TextFormat::RED . "You deleted the " . $game . " parkour.");
                };
                $form = new CustomForm();
                $form->setCallback($callback);
                $form->setTitle("Delete a Parkour");
                $form->addDropdown("Parkour", $parkour);
                $sender->sendForm($form);
                break;
            case "edit":
                $parkour = array_keys($this->getPlugin()->getParkourManager()->getGames());
                $callback = function (Player $player, ?array $data) use ($parkour): void {
                    if (!$data) {
                        return;
                    }
                    $game = $parkour[$data[0]] ?? null;

                    if (!$game) {
                        $player->sendMessage(TextFormat::RED . "You must select a parkour to edit.");
                        return;
                    }

                    $gameData = $this->getPlugin()->getParkourManager()->getGame($data[0]);

                    $name = $data[1] !== "" ? $data[1] : $gameData->getName();

                    $config = new Config($this->getPlugin()->getDataFolder() . "parkour.json", Config::JSON);
                    $config->remove($gameData->getName());
                    $config->set($name, json_decode("{}"));
                    $config->save();

                    $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
                    $spawn = $gameData->getWorld()->getSpawnLocation();
                    $gameData->getWorld()->requestChunkPopulation($spawn->getFloorX() >> 4, $spawn->getFloorZ() >> 4, null)->onCompletion(
                        function () use ($player, $session, $spawn, $name): void {
                            if (!$player->isConnected()) {
                                return;
                            }
                            $setupMode = new ParkourSetupMode($session);
                            $setupMode->setName($name);
                            $session->setSetupMode($setupMode);
                            $player->teleport($spawn);
                        },
                        static function () use ($player): void {
                            $player->sendMessage(TextFormat::RED . "Failed to load the world spawn chunk, please edit the parkour via /parkour edit.");
                        }
                    );
                };
                $form = new CustomForm();
                $form->setCallback($callback);
                $form->setTitle("Edit a Parkour");
                $form->addDropdown("Parkour", $parkour);
                $form->addInput("Name");
                $sender->sendForm($form);
                break;
            case "help":
                $sender->sendMessage(TextFormat::RED . "Usage /parkour <create|delete|edit>");
        }
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }
}
