<?php

declare(strict_types=1);

namespace Davewats\PvPCore\task;

use Davewats\PvPCore\database\MySQLQueryTask;
use mysqli;

class PlayerDataRetrieveTask extends MySQLQueryTask
{
    const DATA_RETRIEVE_QUERY = "SELECT * FROM pvp_players WHERE xuid = ?";
    protected string $xuid;

    public function __construct(string $xuid)
    {
        $this->xuid = $xuid;
    }

    public function query(mysqli $database): void
    {
        $statement = $database->prepare(PlayerDataRetrieveTask::DATA_RETRIEVE_QUERY);
        $xuid = $this->getXuid();
        $statement->bind_param("s", $xuid);
        $statement->execute();
        $this->setResult($statement->get_result()->fetch_assoc());
        $statement->close();
    }

    public function getXuid(): string
    {
        return $this->xuid;
    }
}
