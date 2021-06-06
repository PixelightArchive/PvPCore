<?php

declare(strict_types=1);

namespace Davewats\PvPCore\language;

use Davewats\PvPCore\PvPCore;
use pocketmine\utils\TextFormat;
use function array_keys;
use function array_values;
use function file_get_contents;
use function json_decode;

class Language
{
    protected static array $messages;

    public static function loadLanguage(PvPCore $plugin): void
    {
        $messages = json_decode(file_get_contents($plugin->getDataFolder() . "language.json"), true);
        if (!$messages) {
            $plugin->getLogger()->alert("Couldn't find the language file. Please make sure your configuration files are up-to date.");
            $plugin->getServer()->getPluginManager()->disablePlugin($plugin);
            return;
        }
        Language::$messages = $messages;
    }

    public static function getMessage(string $message, array $replacement = [], bool $randomized = false): ?string
    {
        return
            isset(Language::$messages[$message]) ?
                TextFormat::colorize(Language::parseStringVariables($randomized ? Language::$messages[$message][array_rand(Language::$messages[$message])] : Language::$messages[$message], $replacement)) : "";
    }

    public static function parseStringVariables(string $message, array $replacement = []): string
    {
        return str_replace(array_keys($replacement), array_values($replacement), $message);
    }
}
