<?php

declare(strict_types=1);

namespace DogeDev\PvPCore\utils;

use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use function basename;
use function closedir;
use function copy;
use function is_dir;
use function is_file;
use function mkdir;
use function opendir;
use function readdir;
use function rmdir;
use function unlink;
use const DIRECTORY_SEPARATOR;

class Utils
{
    public static function recursiveCopy(string $source, string $target): void
    {
        $dir = opendir($source);
        @mkdir($target);
        while ($file = readdir($dir)) {
            if ($file === "." || $file === "..") {
                continue;
            }
            if (is_dir($source . DIRECTORY_SEPARATOR . $file)) {
                Utils::recursiveCopy($source . DIRECTORY_SEPARATOR . $file, $target . DIRECTORY_SEPARATOR . $file);
            } else {
                copy($source . DIRECTORY_SEPARATOR . $file, $target . DIRECTORY_SEPARATOR . $file);
            }
        }
        closedir($dir);
    }

    public static function recursiveDelete(string $path): void
    {
        if (basename($path) === "." or basename($path) === "..") {
            return;
        }
        foreach (scandir($path) as $item) {
            if ($item === "." or $item === "..") {
                continue;
            }
            if (is_dir($path . DIRECTORY_SEPARATOR . $item)) {
                Utils::recursiveDelete($path . DIRECTORY_SEPARATOR . $item);
            }
            if (is_file($path . DIRECTORY_SEPARATOR . $item)) {
                unlink($path . DIRECTORY_SEPARATOR . $item);
            }
        }
        rmdir($path);
    }

    public static function stringToVector(string $pos): Vector3
    {
        $positions = explode(":", $pos);
        return new Vector3((int)$positions[0], (int)$positions[1], (int)$positions[2]);
    }

    public static function vectorToString(Vector3 $pos): string
    {
        return "$pos->x:$pos->y:$pos->z";
    }

    public static function parseItemFromData(array $data): ?Item
    {
        $item = ItemFactory::getInstance()->get((int)$data["id"], $data["meta"] ?? 0, $data["amount"] ?? 1);
        $enchantments = $data["enchantments"] ?? [];
        $customName = $data["customName"] ?? null;
        if ($customName) {
            $item->setCustomName(TextFormat::colorize($customName));
        }
        foreach ($enchantments as $enchantment) {
            if ($enchantment === "") {
                continue;
            }
            $enchantment = explode(":", $enchantment);
            $name = $enchantment[0];
            $level = (int)$enchantment[1] ?? 1;
            $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::fromString($name), $level));
            $items[] = $item;
        }
        return $item;
    }
}
