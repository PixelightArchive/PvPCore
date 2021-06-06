<?php

declare(strict_types=1);

namespace Davewats\PvPCore\parkour;

use Davewats\PvPCore\language\Language;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class ParkourSession
{
    protected Player $player;
    protected Parkour $parkour;
    protected int $current;
    protected int $elapsedTime;
    protected int $lastCheckpoint;
    /**
     * @var Position[]
     */
    protected array $reachedCheckpoints;
    protected array $inventoryContents;

    public function __construct(Player $player, Parkour $parkour)
    {
        $this->player = $player;
        $this->parkour = $parkour;
        $this->current = 0;
        $this->lastCheckpoint = 0;
        $this->elapsedTime = 0;
        $this->reachedCheckpoints = [];
        $this->inventoryContents = $player->getInventory()->getContents();
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getInventory()->setItem(0, ItemFactory::getInstance()->get(ItemIds::BLAZE_ROD)->setCustomName(TextFormat::RESET . TextFormat::GREEN . "Last Checkpoint"));
        $player->getInventory()->setItem(2, ItemFactory::getInstance()->get(ItemIds::ARROW)->setCustomName(TextFormat::RESET . TextFormat::GRAY . "Next Checkpoint"));
        $player->getInventory()->setItem(4, ItemFactory::getInstance()->get(ItemIds::ARROW)->setCustomName(TextFormat::RESET . TextFormat::GRAY . "Previous Checkpoint"));
        $player->getInventory()->setItem(8, ItemFactory::getInstance()->get(ItemIds::BED, 14)->setCustomName(TextFormat::RESET . TextFormat::RED . "Back to Lobby"));
        $player->sendMessage(Language::getMessage("parkourJoinMessage"));
    }

    public function goToPrevious(): void
    {
        if ($this->current === 0 || $this->current - 1 === 0) {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourNoCheckpoints"));
            return;
        }
        if (count($this->getReachedCheckpoints()) <= 0) {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourNoCheckpoints"));
            return;
        }
        if ($this->getReachedCheckpointByIndex($this->current - 1) === null) {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourNotReached", ["{CHECKPOINT}" => $this->current - 1]));
            return;
        }
        $this->current--;
        $this->teleportToCheckpoint($this->getCurrent());
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getReachedCheckpoints(): array
    {
        return $this->reachedCheckpoints;
    }

    public function getReachedCheckpointByIndex(int $index): ?Position
    {
        return $this->reachedCheckpoints[$index] ?? null;
    }

    public function teleportToCheckpoint(int $checkpoint): void
    {
        $this->current = $checkpoint;
        $this->getPlayer()->teleport($this->getReachedCheckpointByIndex($checkpoint));
        $this->getPlayer()->sendMessage(Language::getMessage("parkourTeleportCheckpoint", ["{CHECKPOINT}" => $this->current, "{TOTAL_CHECKPOINTS}" => count($this->getParkour()->getCheckpoints())]));
    }

    public function getParkour(): Parkour
    {
        return $this->parkour;
    }

    public function getCurrent(): int
    {
        return $this->current;
    }

    public function goToNext(): void
    {
        if (count($this->getReachedCheckpoints()) <= 0) {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourNoCheckpoints"));
            return;
        }
        if ($this->getReachedCheckpointByIndex($this->current + 1) === null) {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourNotReached", ["{CHECKPOINT}" => $this->current + 1]));
            return;
        }
        if ($this->getCurrent() + 1 >= count($this->getParkour()->getCheckpoints())) {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourReachedAllCheckpoints"));
            return;
        }
        $this->current++;
        var_dump($this->current);
        $this->teleportToCheckpoint($this->getCurrent());
    }

    public function goToLast(): void
    {
        if ($this->current === 0) {
            $this->getPlayer()->teleport($this->getParkour()->getStart());
            $this->getPlayer()->sendMessage(Language::getMessage("parkourTeleportStart"));
            return;
        }
        $this->teleportToCheckpoint($this->getLastCheckpoint());
    }

    public function getLastCheckpoint(): int
    {
        return $this->lastCheckpoint;
    }

    public function addCheckpoint(int $checkpoint): void
    {
        if (isset($this->reachedCheckpoints[$checkpoint])) {
            return;
        }
        $this->reachedCheckpoints[$checkpoint] = $this->getParkour()->getCheckpointByIndex($checkpoint);
        $this->lastCheckpoint = $checkpoint;
        $this->current = $checkpoint;
        $this->getPlayer()->sendMessage(Language::getMessage("parkourReachCheckpoint", ["{CHECKPOINT}" => $checkpoint, "{TOTAL_CHECKPOINTS}" => count($this->getParkour()->getCheckpoints())]));
    }

    public function getElapsedTime(): int
    {
        return $this->elapsedTime;
    }

    public function finishParkour(): bool
    {
        if (count($this->getReachedCheckpoints()) < count($this->getParkour()->getCheckpoints())) {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourFinishError"));
            return false;
        }
        $this->quit(true);
        return true;
    }

    public function quit(bool $finished = false): void
    {
        if ($finished) {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourFinishMessage", ["{ELAPSED_TIME}" => gmdate("H:i:s", $this->elapsedTime)]));
        } else {
            $this->getPlayer()->sendMessage(Language::getMessage("parkourQuitMessage"));
        }
        $this->getPlayer()->getInventory()->clearAll();
        $this->getPlayer()->getArmorInventory()->clearAll();
        $this->getPlayer()->getCursorInventory()->clearAll();
        $this->getPlayer()->getInventory()->setContents($this->inventoryContents);
    }


    public function tick(): void
    {
        $this->elapsedTime++;
    }
}
