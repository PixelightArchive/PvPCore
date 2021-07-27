<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\database;

use DogeDev\PvPCore\PvPCore;

class Database
{
    protected PvPCore $plugin;
    protected string $username;
    protected string $database;
    protected string $host;
    protected string $password;
    protected int $port;

    public function __construct(PvPCore $plugin, string $username, string $database, string $host, string $password, int $port = 3306)
    {
        $this->plugin = $plugin;
        $this->username = $username;
        $this->database = $database;
        $this->host = $host;
        $this->password = $password;
        $this->port = $port;
    }

    public function submitTask(MySQLQueryTask $task, ?callable $callback = null)
    {
        $task->boundTo($this->getHost(), $this->getUsername(), $this->getPassword(), $this->getDatabase(), $this->getPort());
        $this->plugin->getThreadPool()->submitCallbackTask($task, $callback);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
