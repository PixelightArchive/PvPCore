<?php

declare(strict_types=1);

namespace Davewats\PvPCore\thread;

use ClassLoader;
use Davewats\PvPCore\task\CallbackTask;
use pocketmine\scheduler\AsyncPool;
use pocketmine\snooze\SleeperHandler;
use ThreadedLogger;

class PvPCoreThreadPool extends AsyncPool
{
    protected static array $callbackQueue = [];
    protected static PvPCoreThreadPool $instance;

    public function __construct(int $size, int $workerMemoryLimit, ClassLoader $classLoader, ThreadedLogger $logger, SleeperHandler $eventLoop)
    {
        parent::__construct($size, $workerMemoryLimit, $classLoader, $logger, $eventLoop);
        PvPCoreThreadPool::$instance = $this;
    }

    public static function getInstance(): PvPCoreThreadPool
    {
        return PvPCoreThreadPool::$instance;
    }

    public function submitCallbackTask(CallbackTask $task, ?callable $callback): void
    {
        $this->submitTask($task);
        if ($callback) {
            PvPCoreThreadPool::$callbackQueue[spl_object_hash($task)] = $callback;
        }
    }

    public function processCallbackTask(CallbackTask $task)
    {
        $callback = PvPCoreThreadPool::$callbackQueue[spl_object_hash($task)] ?? null;
        if ($callback) {
            $callback($task);
            unset(PvPCoreThreadPool::$callbackQueue[spl_object_hash($task)]);
        }
    }
}
