<?php

declare(strict_types=1);

namespace Davewats\PvPCore\task;

use Davewats\PvPCore\utils\Utils;

class RecursiveCloneTask extends CallbackTask
{
    protected string $source;
    protected string $target;

    public function __construct(string $source, string $target)
    {
        $this->source = $source;
        $this->target = $target;
    }

    public function onRun(): void
    {
        Utils::recursiveCopy($this->source, $this->target);
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
