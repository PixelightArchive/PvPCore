<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\task;

use DogeDev\PvPCore\database\MySQLQueryTask;
use mysqli;

class PlayerDataRegisterTask extends MySQLQueryTask
{
    const DATA_REGISTER_QUERY = "INSERT IGNORE INTO pvp_players (xuid, name) VALUES (?)";
    protected string $xuid;
    protected string $name;

    public function __construct(string $xuid, string $name)
    {
        $this->xuid = $xuid;
        $this->name = $name;
    }

    public function query(mysqli $database): void
    {
        $statement = $database->prepare(PlayerDataRegisterTask::DATA_REGISTER_QUERY);
        $xuid = $this->getXuid();
        $name = $this->getXuid();
        $statement->bind_param("ss", $xuid, $name);
        $statement->execute();
        $statement->close();
    }

	public function getXuid(): string
	{
		return $this->xuid;
	}

	public function getName(): string
	{
		return $this->name;
	}
}
