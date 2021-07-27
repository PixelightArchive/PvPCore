<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\database;

use DogeDev\PvPCore\task\CallbackTask;
use mysqli;

abstract class MySQLQueryTask extends CallbackTask
{
    protected string $username;
    protected string $database;
    protected string $host;
    protected string $password;
    protected int $port;

    public function boundTo(string $host, string $username, string $password, string $database, int $port = 3306): void
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;
    }

    final public function onRun(): void
    {

        $database = new mysqli($this->host, $this->username, $this->password, $this->database, $this->port);
        $this->query($database);
        $database->close();
    }

    abstract public function query(mysqli $database): void;
}
