<?php

declare(strict_types=1);

namespace Davewats\PvPCore\task;

use Davewats\PvPCore\database\MySQLQueryTask;
use mysqli;

class PlayerDataRetrieveTask extends MySQLQueryTask
{
    const DATA_RETRIEVE_QUERY = "SELECT * FROM pvp_players WHERE xuid = ?";
    protected string $xuid;
    protected int $kills;
    protected int $deaths;
    protected int $streak;
    protected int $duel_wins;
    protected int $duel_loses;
    protected int $duel_streak;

    public function __construct(string $xuid)
    {
        $this->xuid = $xuid;
    }

    public function getDeaths(): int
    {
        return $this->deaths;
    }

    public function getStreak(): int
    {
        return $this->streak;
    }

    public function getDuelLoses(): int
    {
        return $this->duel_loses;
    }

    public function getKills(): int
    {
        return $this->kills;
    }

    public function getDuelStreak(): int
    {
        return $this->duel_streak;
    }

    public function getDuelWins(): int
    {
        return $this->duel_wins;
    }

    public function query(mysqli $database): void
    {
        $statement = $database->prepare(PlayerDataRetrieveTask::DATA_RETRIEVE_QUERY);
        $xuid = $this->getXuid();
        $statement->bind_param("s", $xuid);
        $statement->execute();
        $this->setResult($statement->get_result()->fetch_assoc());
    }

    public function getXuid(): string
    {
        return $this->xuid;
    }
}
