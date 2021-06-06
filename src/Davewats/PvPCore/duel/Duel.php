<?php

declare(strict_types=1);

namespace Davewats\PvPCore\duel;

use Davewats\PvPCore\constants\DuelBroadcastConstants;
use Davewats\PvPCore\constants\DuelStatusConstants;
use Davewats\PvPCore\constants\PlayerStatusConstants;
use Davewats\PvPCore\kit\Kit;
use Davewats\PvPCore\language\Language;
use Davewats\PvPCore\PvPCore;
use Davewats\PvPCore\scoreboard\Scoreboard;
use Davewats\PvPCore\session\Session;
use Davewats\PvPCore\task\RecursiveCloneTask;
use Davewats\PvPCore\task\RecursiveDeletionTask;
use Davewats\PvPCore\utils\Utils;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\player\GameMode;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

class Duel
{
    protected PvPCore $plugin;
    protected int $status;
    protected int $id;
    protected int $time, $countdown;
    protected string $map;
    protected string $mode;
    protected bool $loading;
    protected ?Kit $kit;
    protected ?World $world;
    protected ?Position $spectatorPosition;
    /**
     * @var Position[]|array
     */
    protected array $positions;
    /**
     * @var Position[]
     */
    protected array $usedPositions;
    /**
     * @var Session[]
     */
    protected array $sessions;
    /**
     * @var Session[]
     */
    protected array $queues;
    /**
     * @var array[]
     */
    protected array $scoreboards;
    protected array $data;
    protected ?DuelEvent $event;

    public function __construct(PvPCore $plugin, int $id, array $data, Kit $kit)
    {
        $this->plugin = $plugin;
        $this->id = $id;
        $this->map = $data["name"];
        $this->mode = $data["mode"];
        $this->time = $data["time"] * 60;
        $this->countdown = $data["countdown"];
        $this->kit = $kit;
        $this->world = null;
        $this->spectatorPosition = null;
        $this->positions = [];
        $this->usedPositions = [];
        $this->sessions = [];
        $this->queues = [];
        $this->scoreboards = json_decode(file_get_contents($plugin->getDataFolder() . "scoreboards.json"), true);
        $this->data = $data;
        $this->event = null;
        $this->loading = true;
        $this->loadDuel();
    }

    public function loadDuel(): void
    {
        $this->setStatus(DuelStatusConstants::DUEL_STATUS_LOADING);
        $callback = function () {
            $this->plugin->getServer()->getWorldManager()->loadWorld("duel-" . $this->getId());
            $this->world = $this->plugin->getServer()->getWorldManager()->getWorldByName("duel-" . $this->getId());
            $this->world->setTime(1000);
            $this->world->stopTime();
            $this->spectatorPosition = Position::fromObject(Utils::stringToVector($this->data["spectatorPosition"]), $this->world);
            $positions = $this->data["positions"];
            foreach ($positions as $position) {
                $this->positions[$position] = Position::fromObject(Utils::stringToVector($position), $this->world);
            }
            $this->setLoading(false);
            $this->setStatus(DuelStatusConstants::DUEL_STATUS_WAITING);
            foreach ($this->queues as $queue) {
                if (!$queue->getPlayer()->isConnected()) {
                    return;
                }
                $this->addToDuel($queue);
            }
            $this->queues = [];
        };
        $this->plugin->getThreadPool()->submitCallbackTask(new RecursiveCloneTask($this->plugin->getDataFolder() . "arenas" . DIRECTORY_SEPARATOR . $this->map . DIRECTORY_SEPARATOR . "world", $this->plugin->getServer()->getDataPath() . "worlds" . DIRECTORY_SEPARATOR . "duel-" . $this->getId()), $callback);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function addToDuel(Session $session): bool
    {
        if (!$this->isDuelFree() || $session->isInDuel()) {
            return false;
        }
        if ($this->isLoading()) {
            $this->queues[] = $session;
            $session->setQueued(true);
            return true;
        }
        $position = $this->getRandomPosition();
        $session->getPlayer()->getInventory()->clearAll();
        $session->getPlayer()->getArmorInventory()->clearAll();
        $session->getPlayer()->getEffects()->clear();
        $session->getPlayer()->setHealth($session->getPlayer()->getMaxHealth());
        $session->getPlayer()->getHungerManager()->setFood(20);
        $session->getPlayer()->setGamemode(GameMode::ADVENTURE());
        $session->getPlayer()->setImmobile();
        $session->getPlayer()->getInventory()->setItem(0, ItemFactory::getInstance()->get(ItemIds::BOW)->setCustomName(TextFormat::RESET . TextFormat::GOLD . "Kit Selector"));
        $session->getPlayer()->getInventory()->setItem(8, ItemFactory::getInstance()->get(ItemIds::BED, 14)->setCustomName(TextFormat::RESET . TextFormat::RED . "Back to Lobby"));
        $session->getPlayer()->teleport(Position::fromObject($position->add(0.5, 0, 0.5), $this->getWorld()), 0, 0);
        $session->setDuel($this);
        $session->setQueued(false);
        $session->setStatus(PlayerStatusConstants::PLAYER_STATUS_ALIVE);

        $this->usedPositions[$session->getPlayer()->getUniqueId()->toString()] = Utils::vectorToString($position->asVector3());
        $this->sessions[$session->getPlayer()->getUniqueId()->toString()] = $session;

        $this->broadcastMessage("gamePlayerJoinMessage", ["{PLAYER}" => $session->getPlayer()->getName(), "{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
        $this->broadcastTitle("gamePlayerJoinTitle", "gamePlayerJoinSubtitle", ["{PLAYER}" => $session->getPlayer()->getName(), "{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
        return true;
    }

    public function isDuelFree(): bool
    {
        if (
            $this->getStatus() !== DuelStatusConstants::DUEL_STATUS_WAITING &&
            $this->getStatus() !== DuelStatusConstants::DUEL_STATUS_STARTING &&
            $this->getStatus() !== DuelStatusConstants::DUEL_STATUS_LOADING ||
            count($this->getSessions()) >= 2
        ) {
            return false;
        }
        return true;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return Session[]
     */
    public function getSessions(): array
    {
        return $this->sessions;
    }

    public function isLoading(): bool
    {
        return $this->loading;
    }

    public function setLoading(bool $loading): void
    {
        $this->loading = $loading;
    }

    public function getRandomPosition(): ?Position
    {
        $availablePositions = [];
        foreach ($this->positions as $coordinates => $position) {
            if (in_array($coordinates, $this->usedPositions)) {
                continue;
            }
            $availablePositions[$coordinates] = $position;
        }
        if (count($availablePositions) >= 1) {
            return $this->positions[array_rand($availablePositions)];
        }
        return null;
    }

    public function getWorld(): ?World
    {
        return $this->world;
    }

    public function broadcastMessage(string $message, array $replacement = [], int $mode = DuelBroadcastConstants::DUEL_BROADCAST_ALL): void
    {
        if ($message === "") {
            return;
        }
        switch ($mode) {
            case DuelBroadcastConstants::DUEL_BROADCAST_ALL:
                foreach ($this->getSessions() as $session) {
                    $session->getPlayer()->sendMessage(Language::getMessage($message, $replacement));
                }
                break;
            case DuelBroadcastConstants::DUEL_BROADCAST_ALIVE:
                foreach ($this->getAlivePlayers() as $session) {
                    $session->getPlayer()->sendMessage(Language::getMessage($message, $replacement));
                }
                break;
            case DuelBroadcastConstants::DUEL_BROADCAST_SPECTATOR:
                foreach ($this->getSpectators() as $session) {
                    $session->getPlayer()->sendMessage(Language::getMessage($message, $replacement));
                }
        }
    }

    /**
     * @return Session[]
     */
    public function getAlivePlayers(): array
    {
        $alivePlayers = [];
        foreach ($this->sessions as $session) {
            if ($session->getStatus() === PlayerStatusConstants::PLAYER_STATUS_ALIVE) {
                $alivePlayers[] = $session;
            }
        }
        return $alivePlayers;
    }

    /**
     * @return Session[]
     */
    public function getSpectators(): array
    {
        $spectators = [];
        foreach ($this->sessions as $session) {
            if ($session->getStatus() === PlayerStatusConstants::PLAYER_STATUS_SPECTATOR) {
                $spectators[] = $session;
            }
        }
        return $spectators;
    }

    public function broadcastTitle(string $message, string $subtitle = "", array $replacement = [], int $mode = DuelBroadcastConstants::DUEL_BROADCAST_ALL, int $fadeIn = 5, int $stay = 20, int $fadeOut = 5): void
    {
        switch ($mode) {
            case DuelBroadcastConstants::DUEL_BROADCAST_ALL:
                foreach ($this->getSessions() as $session) {
                    $session->getPlayer()->sendTitle(Language::getMessage($message), Language::getMessage($subtitle, $replacement), $fadeIn, $stay, $fadeOut);
                }
                break;
            case DuelBroadcastConstants::DUEL_BROADCAST_ALIVE:
                foreach ($this->getAlivePlayers() as $session) {
                    $session->getPlayer()->sendTitle(Language::getMessage($message), Language::getMessage($subtitle, $replacement), $fadeIn, $stay, $fadeOut);
                }
                break;
            case DuelBroadcastConstants::DUEL_BROADCAST_SPECTATOR:
                foreach ($this->getSpectators() as $session) {
                    $session->getPlayer()->sendTitle(Language::getMessage($message), Language::getMessage($subtitle, $replacement), $fadeIn, $stay, $fadeOut);
                }
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getMap(): string
    {
        return $this->map;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function getCountdown(): int
    {
        return $this->countdown;
    }

    public function getPositions(): array
    {
        return $this->positions;
    }

    public function isAlive(string $uid): bool
    {
        return $this->sessions[$uid] !== null && $this->sessions[$uid]->getStatus() === PlayerStatusConstants::PLAYER_STATUS_ALIVE;
    }

    public function isSpectator(string $uid): bool
    {
        return $this->sessions[$uid] !== null && $this->sessions[$uid]->getStatus() === PlayerStatusConstants::PLAYER_STATUS_SPECTATOR;
    }

    public function getUsedPositions(): array
    {
        return $this->usedPositions;
    }

    public function getScoreboards()
    {
        return $this->scoreboards;
    }

    public function getQueues(): array
    {
        return $this->queues;
    }

    public function getSpectatorPosition(): ?Position
    {
        return $this->spectatorPosition;
    }

    public function heartbeat(): void
    {
        switch ($this->getStatus()) {
            case DuelStatusConstants::DUEL_STATUS_WAITING:
                if (count($this->sessions) >= 2) {
                    $this->startCountdown();
                    return;
                }
                if (count($this->queues) >= 1) {
                    return;
                }
                if (count($this->sessions) <= 0) {
                    $this->destructDuel();
                }
                $this->sendScoreboards();
                break;
            case DuelStatusConstants::DUEL_STATUS_STARTING:
                if (count($this->sessions) < 2) {
                    $this->stopCountdown();
                    return;
                }
                if ($this->countdown <= 0) {
                    $this->startGame();
                    return;
                }
                if ($this->countdown <= 5) {
                    $this->broadcastMessage("gameCountdownDecrementMessage", ["{MODE}" => $this->mode, "{COUNTDOWN}" => $this->countdown, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
                    $this->broadcastTitle("gameCountdownDecrementTitle", "gameCountdownDecrementSubtitle", ["{COUNTDOWN}" => $this->countdown, "{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
                }
                $this->countdown--;
                $this->sendScoreboards();
                break;
            case DuelStatusConstants::DUEL_STATUS_RUNNING:
                if (count($this->getAlivePlayers()) <= 1) {
                    foreach ($this->getAlivePlayers() as $player) {
                        $this->endGame($player);
                    }
                    return;
                }
                if ($this->time <= 0) {
                    $this->endGame();
                    return;
                }
                if ($this->getEvent() !== null) {
                    if ($this->getEvent()->getTicks() >= 1) {
                        $this->getEvent()->tick();
                    } else {
                        $this->getEvent()->call($this);
                    }
                }
                $this->time--;
                $this->sendScoreboards();
                break;
            case DuelStatusConstants::DUEL_STATUS_ENDING:
                if ($this->getEvent() !== null) {
                    if ($this->getEvent()->getTicks() >= 1) {
                        $this->getEvent()->tick();
                    } else {
                        $this->getEvent()->call($this);
                    }
                }
        }
    }

    public function startCountdown(): void
    {
        $this->setStatus(DuelStatusConstants::DUEL_STATUS_STARTING);
    }

    public function destructDuel(): void
    {
        foreach ($this->sessions as $session) {
            $this->removeFromDuel($session);
        }
        $this->setStatus(DuelStatusConstants::DUEL_STATUS_DESTRUCTING);
        $this->plugin->getDuelManager()->removeDuel($this->getId());
        $this->plugin->getServer()->getWorldManager()->unloadWorld($this->world);
        $this->world = null;
        $this->plugin->getThreadPool()->submitTask(new RecursiveDeletionTask($this->plugin->getServer()->getDataPath() . "worlds", ["duel-" . $this->id]));
    }

    public function removeFromDuel(Session $session, bool $left = false, bool $spectate = false, ?Session $winner = null): bool
    {
        if (!$this->isInDuel($session->getPlayer()->getUniqueId()->toString())) {
            return false;
        }
        if ($this->getStatus() === DuelStatusConstants::DUEL_STATUS_WAITING || $this->getStatus() === DuelStatusConstants::DUEL_STATUS_STARTING && isset($this->usedPositions[$session->getPlayer()->getUniqueId()->toString()])) {
            unset($this->usedPositions[$session->getPlayer()->getUniqueId()->toString()]);
        }
        $session->getPlayer()->getCursorInventory()->clearAll();
        $session->getPlayer()->getInventory()->clearAll();
        $session->getPlayer()->getArmorInventory()->clearAll();
        $session->getPlayer()->getEffects()->clear();
        $session->getPlayer()->setHealth($session->getPlayer()->getMaxHealth());
        $session->getPlayer()->getHungerManager()->setFood(20);
        $session->getPlayer()->setImmobile(false);
        if ($spectate) {
            $session->getPlayer()->setGamemode(GameMode::SPECTATOR());
            $session->getPlayer()->getInventory()->setItem(0, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::GREEN . "Teleporter"));
            $session->getPlayer()->getInventory()->setItem(7, ItemFactory::getInstance()->get(ItemIds::PAPER)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::AQUA . "Play Again"));
            $session->getPlayer()->getInventory()->setItem(8, ItemFactory::getInstance()->get(ItemIds::BED, 14)->setCustomName(TextFormat::RESET . TextFormat::BOLD . TextFormat::RED . "Back to Lobby"));
            if ($winner && $session->getPlayer()->getUniqueId()->toString() === $winner->getPlayer()->getUniqueId()->toString()) {
                $this->broadcastMessage("gameWinMessage", ["{WINNER}" => $winner->getPlayer()->getName(), "{MODE}" => $this->mode, "{MAP}" => $this->map], DuelBroadcastConstants::DUEL_BROADCAST_ALIVE);
                $this->broadcastTitle("gameWinTitle", "gameWinSubtitle", ["{WINNER}" => $winner->getPlayer()->getName(), "{MODE}" => $this->mode, "{MAP}" => $this->map], DuelBroadcastConstants::DUEL_BROADCAST_ALIVE);
            } else {
                $session->getDataCache()->setLoses($session->getDataCache()->getLoses() + 1);
                $session->getDataCache()->setDuelStreak(0);
                if ($winner) {
                    $this->broadcastMessage("gameEndMessage", ["{WINNER}" => $winner->getPlayer()->getName(), "{MODE}" => $this->mode, "{MAP}" => $this->map], DuelBroadcastConstants::DUEL_BROADCAST_SPECTATOR);
                    $this->broadcastTitle("gameEndTitle", "gameEndSubtitle", ["{WINNER}" => $winner->getPlayer()->getName(), "{MODE}" => $this->mode, "{MAP}" => $this->map], DuelBroadcastConstants::DUEL_BROADCAST_SPECTATOR);
                }
            }
            $session->getPlayer()->teleport($this->spectatorPosition);
            $session->setStatus(PlayerStatusConstants::PLAYER_STATUS_SPECTATOR);
        } else {
            $session->getPlayer()->setGamemode($this->plugin->getServer()->getGamemode());
            $session->getPlayer()->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
            $session->setStatus(PlayerStatusConstants::PLAYER_STATUS_UNKNOWN);
            $session->setDuel(null);
            Scoreboard::remove($session->getPlayer());
            unset($this->sessions[$session->getPlayer()->getUniqueId()->toString()]);
        }
        if ($left) {
            $this->broadcastMessage($this->getStatus() === DuelStatusConstants::DUEL_STATUS_RUNNING ? "playerDisconnectMessage" : "gamePlayerQuitMessage", ["{PLAYER}" => $session->getPlayer()->getName(), "{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
            $this->broadcastTitle($this->getStatus() === DuelStatusConstants::DUEL_STATUS_RUNNING ? "playerDisconnectTitle" : "gamePlayerQuitTitle", $this->getStatus() === DuelStatusConstants::DUEL_STATUS_RUNNING ? "playerDisconnectSubtitle" : "gamePlayerQuitSubtitle", ["{PLAYER}" => $session->getPlayer()->getName(), "{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
        }
        return true;
    }

    public function isInDuel(string $uid): bool
    {
        return $this->sessions[$uid] !== null;
    }

    public function sendScoreboards(): void
    {
        $lines = null;
        switch ($this->getStatus()) {
            case DuelStatusConstants::DUEL_STATUS_WAITING:
                $lines = $this->scoreboards["waiting"];
                break;
            case DuelStatusConstants::DUEL_STATUS_STARTING:
                $lines = $this->scoreboards["starting"];
                break;
            case DuelStatusConstants::DUEL_STATUS_RUNNING:
                $lines = $this->scoreboards["running"];
                break;
            case DuelStatusConstants::DUEL_STATUS_ENDING:
                $lines = $this->scoreboards["running"];
        }
        if ($lines) {
            foreach ($this->getSessions() as $session) {
                $opponent = $this->getOpponent($session);
                foreach ($lines as $index => $line) {
                    $lines[$index] = Language::parseStringVariables($line, ["{TIME}" => gmdate("i:s", $this->time), "{PING}" => $session->getPlayer()->getNetworkSession()->getPing(), "{OPPONENT}" => $opponent ? $opponent->getPlayer()->getName() : "", "{OPPONENT_PING}" => $opponent ? $opponent->getPlayer()->getNetworkSession()->getPing() : "", "{OPPONENT_HEALTH}" => $opponent ? round($opponent->getPlayer()->getHealth()) : "", "{COUNTDOWN}" => $this->countdown, "{MODE}" => $this->plugin->getDuelManager()->getMode($this->mode)["prefix"] ?? "", "{MAP}" => $this->map]);
                }
                Scoreboard::create($session->getPlayer(), $this->plugin->getConfig()->get("scoreboard")["duel"]);
                Scoreboard::setLines($session->getPlayer(), $lines);
            }
        }
    }

    public function getOpponent(Session $session): ?Session
    {
        foreach ($this->getAlivePlayers() as $opponents) {
            if ($session->getPlayer()->getUniqueId()->toString() === $opponents->getPlayer()->getUniqueId()->toString()) {
                continue;
            }
            return $opponents;
        }
        return null;
    }

    public function stopCountdown(): void
    {
        $this->setStatus(DuelStatusConstants::DUEL_STATUS_WAITING);
        $this->broadcastMessage("gameCountdownStopMessage", ["{COUNTDOWN}" => $this->countdown, "{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
        $this->broadcastTitle("gameCountdownStopTitle", "gameCountdownStopSubtitle", ["{COUNTDOWN}" => $this->countdown, "{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
    }

    public function startGame(): void
    {
        $this->setStatus(DuelStatusConstants::DUEL_STATUS_RUNNING);
        $this->broadcastMessage("gameStartMessage", ["{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
        $this->broadcastTitle("gameStartTitle", "gameStartSubtitle", ["{MODE}" => $this->mode, "{MAP}" => $this->map, "{PLAYERS}" => count($this->getAlivePlayers())]);
        foreach ($this->sessions as $session) {
            $session->getPlayer()->setImmobile(false);
            $session->getPlayer()->getInventory()->remove(ItemFactory::getInstance()->get(ItemIds::BOW)->setCustomName(TextFormat::RESET . TextFormat::GOLD . "Kit Selector"));
            $session->getPlayer()->getInventory()->remove(ItemFactory::getInstance()->get(ItemIds::BED, 14)->setCustomName(TextFormat::RESET . TextFormat::RED . "Back to Lobby"));
            $session->getPlayer()->getCursorInventory()->clearAll();
            $session->getPlayer()->getInventory()->clearAll();
            $session->getPlayer()->getArmorInventory()->clearAll();
            if (isset($this->usedPositions[$session->getPlayer()->getUniqueId()->toString()])) {
                unset($this->usedPositions[$session->getPlayer()->getUniqueId()->toString()]);
            }
            if ($this->kit) {
                $this->kit->sendContents($session->getPlayer());
            }
        }
    }

    public function endGame(?Session $winner = null): void
    {
        $this->setStatus(DuelStatusConstants::DUEL_STATUS_ENDING);
        $this->setEvent(new DuelEndEvent((int)$this->data["end"]));
        if ($winner) {
            $winner->getDataCache()->setWins($winner->getDataCache()->getWins() + 1);
            $winner->getDataCache()->setDuelStreak($winner->getDataCache()->getDuelStreak() + 1);
            $this->removeFromDuel($winner, false, true, $winner);
        } else {
            foreach ($this->getAlivePlayers() as $alivePlayer) {
                $this->removeFromDuel($alivePlayer, false, true);
            }
            $this->broadcastMessage("gameTimeEndMessage", ["{MODE}" => $this->mode, "{MAP}" => $this->map]);
            $this->broadcastTitle("gameTimeEndTitle", "gameTimeEndSubtitle", ["{MODE}" => $this->mode, "{MAP}" => $this->map]);
        }
    }

    public function getEvent(): ?DuelEvent
    {
        return $this->event;
    }

    public function setEvent(?DuelEvent $event): void
    {
        $this->event = $event;
    }
}
