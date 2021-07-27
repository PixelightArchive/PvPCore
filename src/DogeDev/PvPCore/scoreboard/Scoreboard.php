<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\scoreboard;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class Scoreboard
{
    /**
     * @var string[]
     */
    protected static array $scoreboards;

    public static function create(Player $player, string $displayName): void
    {
        if (Scoreboard::hasObjective($player)) {
            Scoreboard::remove($player);
        }
        $pk = new SetDisplayObjectivePacket();
        $pk->displaySlot = "sidebar";
        $pk->objectiveName = $displayName;
        $pk->displayName = TextFormat::colorize($displayName);
        $pk->criteriaName = "dummy";
        $pk->sortOrder = 1;
        $player->getNetworkSession()->sendDataPacket($pk);
        Scoreboard::$scoreboards[$player->getUniqueId()->toString()] = $displayName;
    }

    public static function hasObjective(Player $player): bool
    {
        return isset(Scoreboard::$scoreboards[$player->getUniqueId()->toString()]);
    }

    public static function remove(Player $player): void
    {
        if (Scoreboard::hasObjective($player)) {
            $objectiveName = Scoreboard::getObjectiveName($player);
            $pk = new RemoveObjectivePacket();
            $pk->objectiveName = $objectiveName;
            $player->getNetworkSession()->sendDataPacket($pk);
            unset(Scoreboard::$scoreboards[$player->getUniqueId()->toString()]);
        }
    }

    public static function getObjectiveName(Player $player): ?string
    {
        return Scoreboard::$scoreboards[$player->getUniqueId()->toString()] ?? null;
    }

    public static function setLines(Player $player, array $lines): void
    {
        if (!isset(Scoreboard::$scoreboards[$player->getUniqueId()->toString()]) || count($lines) >= 15) {
            return;
        }
        foreach (Scoreboard::formatLines(array_reverse($lines)) as $index => $line) {
            $index++;
            $objectiveName = Scoreboard::getObjectiveName($player);
            $entry = new ScorePacketEntry();
            $entry->objectiveName = $objectiveName;
            $entry->type = $entry::TYPE_FAKE_PLAYER;
            $entry->customName = TextFormat::colorize($line);
            $entry->score = $index;
            $entry->scoreboardId = $index;
            $pk = new SetScorePacket();
            $pk->type = $pk::TYPE_CHANGE;
            $pk->entries[] = $entry;
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    public static function formatLines(array $lines): array
    {
        $i = 0;
        foreach ($lines as $index => $line) {
            if ($line !== "") {
                continue;
            }
            $lines[$index] = str_repeat(" ", $i);
            $i++;
        }
        return $lines;
    }
}
