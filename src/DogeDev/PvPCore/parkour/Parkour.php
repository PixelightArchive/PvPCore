<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\parkour;

use pocketmine\world\Position;
use pocketmine\world\World;

class Parkour
{
    protected string $name;
    protected World $world;
    protected Position $start;
    protected Position $end;
    /**
     * @var Position[]
     */
    protected array $checkpoints;

    public function __construct(string $name, World $world, Position $start, Position $end, array $checkpoints)
    {
        $this->name = $name;
        $this->world = $world;
        $this->start = $start;
        $this->end = $end;
        $this->checkpoints = $checkpoints;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getWorld(): World
    {
        return $this->world;
    }

    public function getEnd(): Position
    {
        return $this->end;
    }

    public function getStart(): Position
    {
        return $this->start;
    }

    public function getCheckpointByIndex(int $index): ?Position
    {
        return $this->checkpoints[$index] ?? null;
    }

    public function getCheckpointByPosition(Position $position): ?int
    {
        foreach ($this->getCheckpoints() as $index => $checkpoint) {
            if ($checkpoint->equals($position)) {
                return $index;
            }
        }
        return null;
    }

    /**
     * @return Position[]
     */
    public function getCheckpoints(): array
    {
        return $this->checkpoints;
    }

    public function isCheckpointExists(Position $position): bool
    {
        foreach ($this->getCheckpoints() as $checkpoint) {
            if ($checkpoint->equals($position)) {
                return true;
            }
        }
        return false;
    }
}
