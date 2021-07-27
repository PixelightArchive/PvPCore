<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\task;

use DogeDev\PvPCore\database\MySQLQueryTask;
use mysqli;

class PlayerDataUpdateTask extends MySQLQueryTask
{
    const DATA_UPDATE_QUERY = "UPDATE pvp_players SET kills = ?, deaths = ?, streak = ?, duel_wins = ?, duel_loses = ?, duel_streak = ? WHERE xuid = ?";
    protected string $xuid;
    protected int $kills;
    protected int $deaths;
    protected int $streak;
    protected int $duel_wins;
    protected int $duel_loses;
    protected int $duel_streak;

    public function __construct(string $xuid, int $kills, int $deaths, int $streak, int $duel_wins, int $duel_loses, int $duel_streak)
    {
        $this->xuid = $xuid;
        $this->kills = $kills;
        $this->deaths = $deaths;
        $this->streak = $streak;
        $this->duel_wins = $duel_wins;
        $this->duel_loses = $duel_loses;
        $this->duel_streak = $duel_streak;
    }

    public function query(mysqli $database): void
    {
        $xuid = $this->getXuid();
        $kills = $this->getKills();
        $deaths = $this->getDeaths();
        $streak = $this->getStreak();
        $duel_wins = $this->getDuelWins();
        $duel_loses = $this->getDuelLoses();
        $duel_streak = $this->getDuelStreak();
        $statement = $database->prepare(PlayerDataUpdateTask::DATA_UPDATE_QUERY);
        $statement->bind_param("sssssss", $kills, $deaths, $streak, $duel_wins, $duel_loses, $duel_streak, $xuid);
        $statement->execute();
        $statement->close();
    }

    public function getXuid(): string
    {
        return $this->xuid;
    }

    public function getKills(): int
    {
        return $this->kills;
    }

    public function getDeaths(): int
    {
        return $this->deaths;
    }

    public function getStreak(): int
    {
        return $this->streak;
    }

    public function getDuelWins(): int
    {
        return $this->duel_wins;
    }

    public function getDuelLoses(): int
    {
        return $this->duel_loses;
    }

    public function getDuelStreak(): int
    {
        return $this->duel_streak;
    }
}
