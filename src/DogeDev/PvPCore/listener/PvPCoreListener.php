<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\listener;

use DogeDev\PvPCore\constants\DuelStatusConstants;
use DogeDev\PvPCore\constants\PlayerStatusConstants;
use DogeDev\PvPCore\form\ModalForm;
use DogeDev\PvPCore\form\NormalForm;
use DogeDev\PvPCore\language\Language;
use DogeDev\PvPCore\PvPCore;
use DogeDev\PvPCore\session\setup\DuelSetupMode;
use DogeDev\PvPCore\session\setup\ParkourSetupMode;
use DogeDev\PvPCore\task\RecursiveDeletionTask;
use DogeDev\PvPCore\utils\Utils;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class PvPCoreListener implements Listener
{
    protected PvPCore $plugin;

    public function __construct(PvPCore $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onLogin(PlayerLoginEvent $event): void
    {
        $player = $event->getPlayer();
        $this->getPlugin()->getSessionManager()->createSession($player);
    }

    public function getPlugin(): PvPCore
    {
        return $this->plugin;
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        $event->setQuitMessage(Language::getMessage("playerQuitMessage", ["{PLAYER}" => $player->getName()]));
        $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
        if ($session->isInDuel()) {
            $session->getDuel()->removeFromDuel($session, $session->getDuel()->isAlive($player->getUniqueId()->toString()));
        }
        $session->save();
        $this->getPlugin()->getSessionManager()->removeSession($player->getUniqueId()->toString());
        if ($this->getPlugin()->getParkourManager()->hasSession($player->getUniqueId()->toString())) {
            $this->getPlugin()->getParkourManager()->removeSession($player->getUniqueId()->toString());
        }
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $event->setJoinMessage(Language::getMessage("playerQuitMessage", ["{PLAYER}" => $player->getName()]));
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->sendTitle(Language::getMessage("playerJoinTitle"), Language::getMessage("playerJoinSubtitle"));
        $player->sendTitle(Language::getMessage("playerJoinMessage"));
        $player->getInventory()->setItem(2, ItemFactory::getInstance()->get(ItemIds::POPPY)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . "Cosmetics"));
        $player->getInventory()->setItem(4, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . "Warps"));
        $player->getInventory()->setItem(6, ItemFactory::getInstance()->get(ItemIds::DIAMOND_SWORD)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . "Duels"));
    }

    public function onDrop(PlayerDropItemEvent $event): void
    {
        $event->cancel();
    }

    public function onBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
        if ($session->isInDuel()) {
            return;
        }
        if ($player->hasPermission("pvpcore.admin") && $player->isCreative()) {
            return;
        }
        $event->cancel();
    }

    public function onTeleport(EntityTeleportEvent $event): void
    {
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }
        if ($event->getTo()->getWorld()->getFolderName() === $this->getPlugin()->getServer()->getWorldManager()->getDefaultWorld()->getFolderName()) {
//        $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
//            if ($session->isInDuel()) {
//                $session->getDuel()->removeFromDuel($session, $session->getDuel()->isAlive($session->getPlayer()->getUniqueId()->toString()));
//            }
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getCursorInventory()->clearAll();
            $player->getInventory()->setItem(2, ItemFactory::getInstance()->get(ItemIds::POPPY)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . "Cosmetics"));
            $player->getInventory()->setItem(4, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . "Warps"));
            $player->getInventory()->setItem(6, ItemFactory::getInstance()->get(ItemIds::DIAMOND_SWORD)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . "Duels"));
        }
    }


    public function onPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem()->getCustomName();
        $block = $event->getBlock()->getPos()->asVector3();
        $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
        if (!$player->hasPermission("pvpcore.admin") && !$player->isCreative()) {
            $event->cancel();
            return;
        }
        $setupMode = $session->getSetupMode();
        if (!$setupMode instanceof ParkourSetupMode) {
            return;
        }
        $config = new Config($this->getPlugin()->getDataFolder() . "parkour.json", Config::JSON);
        $data = $config->get($setupMode->getName());
        switch ($item) {
            case TextFormat::RESET . TextFormat::GRAY . "Add Checkpoint":
                $checkpoints = $data["checkpoints"] ?? [];
                $checkpoints[] = Utils::vectorToString($block);
                $config->set($setupMode->getName(), ["start" => $data["start"] ?? "", "end" => $data["end"] ?? "", "checkpoints" => $checkpoints, "world" => $data["world"]]);
                $config->save();
                $player->sendMessage(TextFormat::GREEN . "Successfully added a new checkpoint #" . count($checkpoints));
                break;
            case TextFormat::RESET . TextFormat::AQUA . "Set Starting point":
                $config->set($setupMode->getName(), ["start" => Utils::vectorToString($block), "end" => $data["end"] ?? "", "checkpoints" => $data["checkpoints"] ?? [], "world" => $data["world"]]);
                $config->save();
                $player->sendMessage(TextFormat::GREEN . "Successfully set the starting point.");
                break;
            case TextFormat::RESET . TextFormat::GOLD . "Set Endpoint":
                $config->set($setupMode->getName(), ["start" => $data["start"] ?? "", "end" => Utils::vectorToString($block), "checkpoints" => $data["checkpoints"] ?? [], "world" => $data["world"]]);
                $config->save();
                $player->sendMessage(TextFormat::GREEN . "Successfully set the endpoint.");
        }
    }

    /**
     * @param PlayerChatEvent $event
     * @priority HIGHEST
     */
    public function onChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
        $duel = $session->getDuel();
        if ($session->isInDuel()) {
            $duel->broadcastMessage($this->plugin->getConfig()->get("gameChat"), ["{PLAYER}" => $session->getStatus() === PlayerStatusConstants::PLAYER_STATUS_SPECTATOR]);
            $event->cancel();
        }
    }

    public function onDeath(PlayerDeathEvent $event): void
    {
        $event->setDeathMessage(null);
        $event->setDrops([]);
    }

    public function onInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem()->getCustomName();
        $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
        $setupMode = $session->getSetupMode();
        $position = Utils::vectorToString($event->getBlock()->getPos()->asVector3());
        if (!$setupMode instanceof DuelSetupMode) {
            return;
        }
        if (!$player->hasPermission("pvpcore.admin")) {
            $player->sendMessage("You don't have enough permissions to perform this action.");
            return;
        }
        $config = new Config($this->getPlugin()->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $setupMode->getArena() . DIRECTORY_SEPARATOR . "data.json", Config::JSON);
        switch ($item) {
            case TextFormat::RESET . TextFormat::GREEN . "Add Position":
                $positions = (array)$config->get("positions");
                $positions[] = $position;
                $config->set("positions", $positions);
                $config->save();
                $player->sendMessage(TextFormat::GREEN . "Successfully added position ($position).");
                break;
            case TextFormat::RESET . TextFormat::AQUA . "Set Spectator Position":
                $config->set("spectatorPosition", $position);
                $config->save();
                $player->sendMessage(TextFormat::GREEN . "Successfully set spectator position ($position).");
        }
    }

    public function onMove(PlayerMoveEvent $event): void
    {
        $player = $event->getPlayer();
        $block = $player->getWorld()->getBlock($player->getPosition());
        if ($event->getFrom()->floor()->equals($event->getTo()->floor())) {
            return;
        }
        if ($block->getId() === ItemIds::LIGHT_WEIGHTED_PRESSURE_PLATE) {
            foreach ($this->getPlugin()->getParkourManager()->getGames() as $game) {
                if ($player->getWorld()->getFolderName() === $game->getWorld()->getFolderName() && $game->getStart()->equals($block->getPos())) {
                    $this->getPlugin()->getParkourManager()->createSession($player, $game);
                    return;
                }
            }
        }
        $session = $this->getPlugin()->getParkourManager()->getSession($player->getUniqueId()->toString());
        if (!$session) {
            return;
        }
        if ($block->getId() === ItemIds::HEAVY_WEIGHTED_PRESSURE_PLATE && $session->getParkour()->isCheckpointExists($block->getPos())) {
            $session->addCheckpoint($session->getParkour()->getCheckpointByPosition($block->getPos()));
            return;
        }
        if ($block->getId() === ItemIds::LIGHT_WEIGHTED_PRESSURE_PLATE && $session->getParkour()->getEnd()->equals($block->getPos())) {
            if ($session->finishParkour()) {
                $this->getPlugin()->getParkourManager()->removeSession($player->getUniqueId()->toString());
            }
        }
    }

    public function onUse(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem()->getCustomName();
        $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
        switch ($item) {
            case TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . "Warps":
                $this->getPlugin()->getServer()->dispatchCommand($player, "warp join");
                break;
            case TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . "Duels":
                $this->getPlugin()->getServer()->dispatchCommand($player, "duel join");
                break;
            case TextFormat::RESET . TextFormat::RED . "Back to Lobby":
                if ($session->isInDuel()) {
                    $session->getDuel()->removeFromDuel($session, true);
                    return;
                }
                if ($this->getPlugin()->getParkourManager()->hasSession($player->getUniqueId()->toString())) {
                    $this->getPlugin()->getParkourManager()->getSession($player->getUniqueId()->toString())->quit();
                    $this->getPlugin()->getParkourManager()->removeSession($player->getUniqueId()->toString());
                    return;
                }
                break;
            case TextFormat::RESET . TextFormat::GREEN . "Last Checkpoint":
                if ($this->getPlugin()->getParkourManager()->hasSession($player->getUniqueId()->toString())) {
                    $this->getPlugin()->getParkourManager()->getSession($player->getUniqueId()->toString())->goToLast();
                    return;
                }
                break;
            case TextFormat::RESET . TextFormat::GRAY . "Next Checkpoint":
                if ($this->getPlugin()->getParkourManager()->hasSession($player->getUniqueId()->toString())) {
                    $this->getPlugin()->getParkourManager()->getSession($player->getUniqueId()->toString())->goToNext();
                    return;
                }
                break;
            case TextFormat::RESET . TextFormat::GRAY . "Previous Checkpoint":
                if ($this->getPlugin()->getParkourManager()->hasSession($player->getUniqueId()->toString())) {
                    $this->getPlugin()->getParkourManager()->getSession($player->getUniqueId()->toString())->goToPrevious();
                    return;
                }
                break;
            case TextFormat::RESET . TextFormat::RED . "Quit Setup Mode":
                if (!$session->getSetupMode()->isActivated()) {
                    $player->sendMessage("You can only perform this action in the setup mode.");
                    return;
                }
                $setupMode = $session->getSetupMode();
                if ($setupMode instanceof DuelSetupMode) {
                    $this->getPlugin()->getDuelManager()->reloadArena($setupMode->getArena());
                }
                $player->teleport($this->getPlugin()->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
                $this->getPlugin()->getServer()->getWorldManager()->unloadWorld($this->getPlugin()->getServer()->getWorldManager()->getWorldByName($setupMode->getClonedWorld()));
                $this->getPlugin()->getThreadPool()->submitTask(new RecursiveDeletionTask($this->getPlugin()->getServer()->getDataPath() . "worlds", [$setupMode->getClonedWorld()]));
                $player->sendMessage(TextFormat::GREEN . "You've successfully quit the setup mode.");
                $player->setGamemode($this->getPlugin()->getServer()->getGamemode());
                $setupMode->deactivate();
                $session->setSetupMode(null);
        }
    }

    public function onExhaust(PlayerExhaustEvent $event): void
    {
        $event->cancel();
    }

    public function onDamage(EntityDamageEvent $event): void
    {
        $player = $event->getEntity();
        $cause = $event->getCause();
        if (!$player instanceof Player) {
            return;
        }
        if ($event instanceof EntityDamageByEntityEvent) {
            $attacker = $event->getDamager();
            if ($attacker instanceof Player && $attacker->getInventory()->getItemInHand()->getCustomName() === TextFormat::RESET . TextFormat::BOLD . TextFormat::YELLOW . "Duels") {
                $callback = function (Player $sender, ?string $mode) use ($player): void {
                    if (!$mode) {
                        return;
                    }
                    $callback = function (Player $receiver, ?array $data) use ($sender, $mode): void {
                        if (!$data) {
                            $sender->sendMessage(Language::getMessage("duelDeclineMessage", ["{PLAYER}" => $receiver->getName()]));
                            return;
                        }
                        if ($data[0]) {
                            $receiver = $this->getPlugin()->getSessionManager()->getSession($receiver->getUniqueId()->toString());
                            $sender = $this->getPlugin()->getSessionManager()->getSession($sender->getUniqueId()->toString());
                            $this->getPlugin()->getDuelManager()->queueToDuel([$receiver, $sender], $mode);
                        } else {
                            $sender->sendMessage(Language::getMessage("duelDeclineMessage", ["{PLAYER}" => $receiver->getName()]));
                        }
                    };
                    $form = new ModalForm($mode . " Duel", "Do you want to duel " . $sender->getName() . " ");
                    $form->setCallback($callback);
                    $form->setTitle($sender->getName());
                    $form->setFirstButton(TextFormat::GREEN . "Accept");
                    $form->setSecondButton(TextFormat::RED . "Decline");
                    $form->setCallback($callback);
                    $player->sendForm($form);
                };
                $form = new NormalForm();
                $form->setTitle("Duels");
                $form->setContent("Select a mode:");
                foreach ($this->plugin->getDuelManager()->getModes() as $mode => $data) {
                    if (isset($data["icon"]) && $data["icon"] !== "") {
                        $form->addButton(ucwords($mode), strpos("http", $data["icon"]) === 0 ? NormalForm::IMAGE_TYPE_URL : NormalForm::IMAGE_TYPE_PATH, $data["icon"]);
                        continue;
                    }
                    $form->addButton(ucwords($mode));
                }
                $form->setCallback($callback);
                $attacker->sendForm($form);
                $event->cancel();
                return;
            }
        }
        $session = $this->getPlugin()->getSessionManager()->getSession($player->getUniqueId()->toString());
        $duel = $session->getDuel();
        if (!$session->isInDuel()) {
            if ($player->getWorld()->getFolderName() === $this->getPlugin()->getServer()->getWorldManager()->getDefaultWorld()->getFolderName()) {
                $event->cancel();
                return;
            }
            if ($player->getHealth() - $event->getBaseDamage() <= 0) {
                $session->getDataCache()->setDeaths($session->getDataCache()->getDeaths() + 1);
                $session->getDataCache()->setStreak(0);
                if ($event instanceof EntityDamageByEntityEvent) {
                    $causer = $event->getDamager();
                    if ($causer instanceof Player) {
                        $causerSession = $this->getPlugin()->getSessionManager()->getSession($causer->getUniqueId()->toString());
                        $causerSession->getDataCache()->setKills($causerSession->getDataCache()->getKills() + 1);
                        $causerSession->getDataCache()->setStreak($causerSession->getDataCache()->getStreak() + 1);
                        foreach ($this->getPlugin()->getSessionManager()->getSessions() as $broadcastReceiver) {
                            if ($broadcastReceiver->isInDuel()) {
                                continue;
                            }
                            $broadcastReceiver->getPlayer()->sendMessage(Language::getMessage("ffaDeathMessages", ["{PLAYER}" => $session->getPlayer()->getName(), "{KILLER}" => $causer->getName()], true));
                        }
                    }
                }
            }
        }
        if (!$session->isInDuel()) {
            return;
        }
        if ($duel->getStatus() !== DuelStatusConstants::DUEL_STATUS_RUNNING) {
            $event->cancel();
            return;
        }
        // Abuse check.
        if ($event instanceof EntityDamageByEntityEvent) {
            $causer = $event->getDamager();
            if ($causer instanceof Player) {
                $causerSession = $this->getPlugin()->getSessionManager()->getSession($causer->getUniqueId()->toString());
                if (!$causerSession->isInDuel() || !$duel->isInDuel($causerSession->getPlayer()->getUniqueId()->toString())) {
                    $event->cancel();
                    return;
                }
            }
        }
        if ($player->getHealth() - $event->getBaseDamage() <= 0) {
            $player->sendTitle(Language::getMessage("playerDeathTitle"), Language::getMessage("playerDeathSubtitle"));
            $player->sendMessage(Language::getMessage("playerDeathMessage"));
            $duel->removeFromDuel($session, false, true);
            $event->cancel();
            switch ($cause) {
                case EntityDamageEvent::CAUSE_PROJECTILE:
                case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                    break;
                case EntityDamageEvent::CAUSE_BLOCK_EXPLOSION:
                    $duel->broadcastMessage("playerDeathByExplosion", ["{PLAYER}" => $player->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                    break;
                case EntityDamageEvent::CAUSE_DROWNING:
                    $duel->broadcastMessage("playerDeathByDrowning", ["{PLAYER}" => $player->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                    break;
                case EntityDamageEvent::CAUSE_FIRE:
                    $duel->broadcastMessage("playerDeathByBurning", ["{PLAYER}" => $player->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                    break;
                case EntityDamageEvent::CAUSE_LAVA:
                    $duel->broadcastMessage("playerDeathByLava", ["{PLAYER}" => $player->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                    break;
                case EntityDamageEvent::CAUSE_FALL:
                    $duel->broadcastMessage("playerDeathByFall", ["{PLAYER}" => $player->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                    break;
                case EntityDamageEvent::CAUSE_VOID:
                    $duel->broadcastMessage("playerDeathByVoid", ["{PLAYER}" => $player->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                    break;
                default:
                    $duel->broadcastMessage("playerDeathDefault", ["{PLAYER}" => $player->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
            }
            if ($event instanceof EntityDamageByEntityEvent) {
                $causer = $event->getDamager();
                if (!$causer instanceof Player) {
                    $duel->broadcastMessage("playerDeathDefault", ["{PLAYER}" => $player->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                    return;
                }
//                $causerSession = $this->getPlugin()->getSessionManager()->getSession($causer->getUniqueId()->toString());
                switch ($cause) {
                    case EntityDamageEvent::CAUSE_PROJECTILE:
                        $duel->broadcastMessage("playerDeathByProjectile", ["{PLAYER}" => $player->getName(), "{KILLER}" => $causer->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                        break;
                    case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                        $duel->broadcastMessage("playerDeathByAttack", ["{PLAYER}" => $player->getName(), "{KILLER}" => $causer->getName(), "{MODE}" => $duel->getMode(), "{MAP}" => $duel->getMap(), "{PLAYERS}" => count($duel->getAlivePlayers())]);
                }
            }
        }
    }
}
