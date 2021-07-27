<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\task;

use DogeDev\PvPCore\utils\Utils;

class RecursiveDeletionTask extends CallbackTask
{
    protected string $source;
    protected string $folders;

    public function __construct(string $source, array $folders)
    {
        $this->source = $source;
        $this->folders = serialize($folders);
    }

    public function onRun(): void
    {
        foreach (unserialize($this->folders) as $folder) {
            if ($folder === null || $folder === "." || $folder === "..") {
                continue;
            }
            Utils::recursiveDelete($this->source . DIRECTORY_SEPARATOR . basename($folder));
        }
    }
}
