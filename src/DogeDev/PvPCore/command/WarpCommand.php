<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\command;

use DogeDev\PvPCore\form\CustomForm;
use DogeDev\PvPCore\form\NormalForm;
use DogeDev\PvPCore\language\Language;
use DogeDev\PvPCore\PvPCore;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class WarpCommand extends Command
{
    protected PvPCore $plugin;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("warp", "Warps", null, ["warps"]);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Run this command in game.");
            return;
        }
        switch ($args[0] ?? "join") {
            case "create":
                if (!$sender->hasPermission("pvpcore.admin")) {
                    $sender->sendMessage(TextFormat::RED . "You don't have enough permissions to perform this command.");
                    return;
                }
                $callback = function (Player $player, ?array $data): void {
                    if (!$data) {
                        return;
                    }
                    $world = $data[0] ?? null;
                    $kit = $data[1] ?? null;
                    $name = $data[2] !== "" ? $data[2] : null;
                    $icon = $data[3] !== "" ? $data[3] : null;
                    if (!$world || !$kit || !$name) {
                        $player->sendMessage(TextFormat::RED . "Please fill all the data fields.");
                        return;
                    }
                    $config = new Config($this->getPlugin()->getDataFolder() . "warps.json" . Config::JSON);
                    $config->set($name, json_encode(["world" => $world, "kit" => $kit]));
                    $config->save();
                    $this->getPlugin()->getWarpManager()->addWarp($name, $this->getPlugin()->getServer()->getWorldManager()->getWorldByName($world), $this->getPlugin()->getKitManager()->getKit($kit), $icon);
                    $player->sendMessage(TextFormat::GREEN . "Successfully created the " . $name . " warp with the " . $kit . " kit.");
                };
                $worlds = [];
                $defaultWorld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getFolderName();
                foreach (scandir($this->plugin->getServer()->getDataPath() . DIRECTORY_SEPARATOR . "worlds") as $world) {
                    if ($world === "." || $world === ".." || $defaultWorld === $world) {
                        continue;
                    }
                    $worlds[] = $world;
                }
                $kits = array_keys($this->getPlugin()->getKitManager()->getKits());
                $form = new CustomForm();
                $form->addDropdown("World", $worlds);
                $form->addDropdown("Kit", $kits);
                $form->addInput("Name");
                $form->addInput("Icon");
                $form->setCallback($callback);
                $sender->sendForm($form);
                break;
            case "delete":
                if (!$sender->hasPermission("pvpcore.admin")) {
                    $sender->sendMessage(TextFormat::RED . "You don't have enough permissions to perform this command.");
                    return;
                }
                $callback = function (Player $player, ?array $data): void {
                    if (!$data) {
                        return;
                    }
                    $warp = $data[0] ?? null;
                    if (!$warp) {
                        $player->sendMessage(TextFormat::RED . "Please select a warp to delete.");
                        return;
                    }
                    $config = new Config($this->getPlugin()->getDataFolder() . "warps.json" . Config::JSON);
                    $config->remove($warp);
                    $config->save();
                    $this->getPlugin()->getWarpManager()->removeWarp($warp);
                    $player->sendMessage(TextFormat::GREEN . "Successfully deleted the " . $warp . ".");
                };
                $warps = array_keys($this->getPlugin()->getWarpManager()->getWarps());
                $form = new CustomForm();
                $form->setTitle("Delete a Warp");
                $form->addDropdown("Warp", $warps);
                $form->setCallback($callback);
                $sender->sendForm($form);
                break;
            case "edit":
                if (!$sender->hasPermission("pvpcore.admin")) {
                    $sender->sendMessage(TextFormat::RED . "You don't have enough permissions to perform this command.");
                    return;
                }
                $warps = array_keys($this->getPlugin()->getWarpManager()->getWarps());
                $callback = function (Player $player, ?array $data) use ($warps): void {
                    $warp = $data[0] ?? null;
                    if (!$warp) {
                        $player->sendMessage(TextFormat::RED . "Please select a warp to edit.");
                        return;
                    }
                    $warp = $warps[$warp];
                    $config = new Config($this->getPlugin()->getDataFolder() . "warps.json" . Config::JSON);
                    $world = $data[1] ?? $config->get($warp)["world"];
                    $kit = $data[2] ?? $config->get($warp)["kit"];
                    $name = $data[3] !== "" ? $data[2] : $warp;
                    $icon = $data[4] !== "" ? $data[3] : $config->get($warp)["icon"];
                    if (strtolower($name) !== strtolower($warp)) {
                        $config->remove($warp);
                    }
                    $config->set($name, json_encode(["world" => $world, "kit" => $kit, "icon" => $icon]));
                    $config->save();
                    $this->getPlugin()->getWarpManager()->removeWarp($warp);
                    $this->getPlugin()->getWarpManager()->addWarp($name, $this->getPlugin()->getServer()->getWorldManager()->getWorldByName($world), $this->getPlugin()->getKitManager()->getKit($kit), $icon);
                    $player->sendMessage(TextFormat::GREEN . "Successfully edited the " . $name . " warp.");
                };
                $worlds = [];
                $defaultWorld = $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getFolderName();
                foreach (scandir($this->plugin->getServer()->getDataPath() . DIRECTORY_SEPARATOR . "worlds") as $world) {
                    if ($world === "." || $world === ".." || $defaultWorld === $world) {
                        continue;
                    }
                    $worlds[] = $world;
                }
                $kits = array_keys($this->getPlugin()->getKitManager()->getKits());
                $form = new CustomForm();
                $form->setTitle("Edit a Warp");
                $form->addDropdown("Warp", $warps);
                $form->addDropdown("World", $worlds);
                $form->addDropdown("Kit", $kits);
                $form->addInput("Name");
                $form->addInput("Icon");
                $form->setCallback($callback);
                $sender->sendForm($form);
                break;
            case "join":
                $callback = function (Player $player, ?string $data): void {
                    if (!$data || $data === "Exit") {
                        return;
                    }
                    $warp = $this->getPlugin()->getWarpManager()->getWarp($data);
                    if (!$warp) {
                        $player->sendMessage(Language::getMessage("playerWarpFailMessage", ["{WARP}" => ucwords($data)]));
                        return;
                    }
                    $warp->warpTo($player);
                };
                $form = new NormalForm();
                $form->setTitle("Warps");
                $form->setTitle("Select a warp:");
                foreach ($this->getPlugin()->getWarpManager()->getWarps() as $warp) {
                    if ($warp->getIcon() !== null) {
                        $form->addButton(ucwords($warp->getName()), strpos("http", $warp->getIcon()) === 0 ? NormalForm::IMAGE_TYPE_URL : NormalForm::IMAGE_TYPE_PATH, $warp->getIcon());
                        continue;
                    }
                    $form->addButton(ucwords($warp->getName()));
                }
                $form->addButton(TextFormat::RED . TextFormat::BOLD . "Exit", NormalForm::IMAGE_TYPE_PATH, "textures/blocks/barrier");
                $form->setCallback($callback);
                $sender->sendForm($form);
        }
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }
}
