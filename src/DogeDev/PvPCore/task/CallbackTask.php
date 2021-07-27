<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\task;

use DogeDev\PvPCore\thread\PvPCoreThreadPool;
use pocketmine\scheduler\AsyncTask;

abstract class CallbackTask extends AsyncTask
{
    final public function onCompletion(): void
    {
        PvPCoreThreadPool::getInstance()->processCallbackTask($this);
        $this->onFinish();
    }

    public function onFinish(): void
    {
    }
}
