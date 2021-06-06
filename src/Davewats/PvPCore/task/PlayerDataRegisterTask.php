<?php

declare(strict_types=1);

namespace Davewats\PvPCore\task;

use Davewats\PvPCore\database\MySQLQueryTask;
use mysqli;

class PlayerDataRegisterTask extends MySQLQueryTask
{
    const DATA_REGISTER_QUERY = "INSERT IGNORE INTO pvp_players (xuid) VALUES (?)";
    protected string $xuid;

    public function __construct(string $xuid)
    {
        $this->xuid = $xuid;
    }

    public function query(mysqli $database): void
    {
        $statement = $database->prepare(PlayerDataRegisterTask::DATA_REGISTER_QUERY);
        $xuid = $this->getXuid();
        $statement->bind_param("s", $xuid);
        $statement->execute();
    }

    public function getXuid(): string
    {
        return $this->xuid;
    }
}
