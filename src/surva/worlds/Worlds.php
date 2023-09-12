<?php

/**
 * Worlds | plugin main class
 */

namespace surva\worlds;

use DirectoryIterator;
use JsonException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use surva\worlds\commands\CopyCommand;
use surva\worlds\commands\CreateCommand;
use surva\worlds\commands\CustomCommand;
use surva\worlds\commands\DefaultsCommand;
use surva\worlds\commands\ListCommand;
use surva\worlds\commands\LoadCommand;
use surva\worlds\commands\RemoveCommand;
use surva\worlds\commands\RenameCommand;
use surva\worlds\commands\SetCommand;
use surva\worlds\commands\TeleportCommand;
use surva\worlds\commands\UnloadCommand;
use surva\worlds\commands\UnsetCommand;
use surva\worlds\types\Defaults;
use surva\worlds\types\World;
use surva\worlds\utils\Flags;
use surva\worlds\utils\Messages;
use Symfony\Component\Filesystem\Path;

class Worlds extends PluginBase
{
    /**
     * @var \surva\worlds\types\Defaults default world options
     */
    private Defaults $defaults;

    /**
     * @var array loaded worlds array
     */
    private array $worlds;

    /**
     * @var \pocketmine\utils\Config default language config
     */
    private Config $defaultMessages;

    /**
     * @var array available language configs
     */
    private array $translationMessages;

    /**
     * Initialize plugin, config, languages
     */
    protected function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->saveDefaultConfig();

        $this->saveResource(Path::join("languages", "en.yml"), true);
        $this->defaultMessages = new Config(Path::join($this->getDataFolder(), "languages", "en.yml"));
        $this->loadLanguageFiles();

        $this->defaults = new Defaults(
            $this,
            $this->getCustomConfig(Path::join($this->getDataFolder(), "defaults.yml")),
            "defaults"
        );

        $this->worlds = [];

        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            $this->registerWorld($world->getFolderName());
        }
    }

    /**
     * Main command (/worlds) execution, calling sub commands
     *
     * @param  \pocketmine\command\CommandSender  $sender
     * @param  \pocketmine\command\Command  $command
     * @param  string  $label
     * @param  array  $args
     *
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        $name = $command->getName();

        if (strtolower($name) === "worlds") {
            if (count($args) > 0) {
                if ($customCommand = $this->getCustomCommand($args[0])) {
                    if ($customCommand instanceof CustomCommand) {
                        return $customCommand->execute($sender, $name, $args);
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get a custom command by its name or alias
     *
     * @param  string  $name
     *
     * @return \surva\worlds\commands\CustomCommand|null
     */
    public function getCustomCommand(string $name): ?CustomCommand
    {
        return match ($name) {
            "list", "ls" => new ListCommand($this, "list", "worlds.list"),
            "create", "cr" => new CreateCommand($this, "create", "worlds.admin.create"),
            "remove", "rm" => new RemoveCommand($this, "remove", "worlds.admin.remove"),
            "copy", "cp" => new CopyCommand($this, "copy", "worlds.admin.copy"),
            "rename", "rn" => new RenameCommand($this, "rename", "worlds.admin.rename"),
            "load", "ld" => new LoadCommand($this, "load", "worlds.admin.load"),
            "unload", "uld" => new UnloadCommand($this, "unload", "worlds.admin.unload"),
            "teleport", "tp" => new TeleportCommand($this, "teleport", "worlds.teleport.general"),
            "set", "st" => new SetCommand($this, "set", "worlds.admin.set"),
            "unset", "ust" => new UnsetCommand($this, "unset", "worlds.admin.unset"),
            "defaults", "df" => new DefaultsCommand($this, "defaults", "worlds.admin.defaults"),
            default => null,
        };
    }

    /**
     * Get a world by its name
     *
     * @param  string  $name
     *
     * @return \surva\worlds\types\World|null
     */
    public function getWorldByName(string $name): ?World
    {
        if (!isset($this->worlds[$name])) {
            return null;
        }

        return $this->worlds[$name];
    }

    /**
     * Register a new server world
     *
     * @param  string  $folderName
     */
    public function registerWorld(string $folderName): void
    {
        $settingsFile = $this->getWorldSettingsFilePath($folderName);
        $worldConfig  = $this->getCustomConfig($settingsFile);

        $this->worlds[$folderName] = new World($this, $worldConfig, $folderName);
    }

    /**
     * Unregister a world, e.g. if it's unloaded
     *
     * @param  string  $folderName
     *
     * @return bool
     */
    public function unregisterWorld(string $folderName): bool
    {
        if (!isset($this->worlds[$folderName])) {
            return false;
        }

        unset($this->worlds[$folderName]);

        return true;
    }

    /**
     * Get the path to the worlds.yml file of a world
     *
     * @param  string  $folderName
     *
     * @return string
     */
    public function getWorldSettingsFilePath(string $folderName): string
    {
        $legacyFilePath = Path::join($this->getServer()->getDataPath(), "worlds", $folderName, "worlds.yml");

        $dirPath = Path::join($this->getDataFolder(), "worlds", $folderName);
        @mkdir($dirPath, 0777, true);
        $filePath = Path::join($dirPath, "worlds.yml");

        if (file_exists($legacyFilePath)) {
            rename($legacyFilePath, $filePath);
        }

        return $filePath;
    }

    /**
     * Apply options of a world to a player
     *
     * @param  \surva\worlds\types\World  $world
     * @param  \pocketmine\player\Player  $pl
     */
    public function applyWorldOptions(World $world, Player $pl): void
    {
        if ($world->getIntFlag(Flags::FLAG_GAME_MODE) !== null) {
            if (!$pl->hasPermission("worlds.special.gamemode")) {
                $pl->setGamemode(GameMode::fromString((string) $world->getIntFlag(Flags::FLAG_GAME_MODE)));
            }
        }

        if ($world->getBoolFlag(Flags::FLAG_FLY) === true or $pl->hasPermission("worlds.special.fly")) {
            $pl->setAllowFlight(true);
        } elseif ($world->getBoolFlag(Flags::FLAG_FLY) === false) {
            $pl->setAllowFlight(false);
        }
    }

    /**
     * Get a custom world settings config file or create if it doesn't exist
     *
     * @param  string  $file
     *
     * @return \pocketmine\utils\Config
     */
    public function getCustomConfig(string $file): Config
    {
        $config = new Config($file);

        if (!file_exists($file)) {
            try {
                $config->save();
            } catch (JsonException $e) {
                // do nothing for now
            }
        }

        return $config;
    }

    /**
     * Shorthand to send a translated message to a command sender
     *
     * @param  \pocketmine\command\CommandSender  $sender
     * @param  string  $key
     * @param  array  $replaces
     *
     * @return void
     */
    public function sendMessage(CommandSender $sender, string $key, array $replaces = []): void
    {
        $messages = new Messages($this, $sender);

        $sender->sendMessage($messages->getMessage($key, $replaces));
    }

    /**
     * Load all available language files
     *
     * @return void
     */
    private function loadLanguageFiles(): void
    {
        $resources = $this->getResources();
        $this->translationMessages = [];

        foreach ($resources as $resource) {
            $normalizedPath = Path::normalize($resource->getPathname());
            if (!preg_match("/languages\/[a-z]{2}.yml$/", $normalizedPath)) {
                continue;
            }

            preg_match("/^[a-z][a-z]/", $resource->getFilename(), $fileNameRes);

            if (!isset($fileNameRes[0])) {
                continue;
            }

            $langId = $fileNameRes[0];

            $this->saveResource(Path::join("languages", $langId . ".yml"), true);
            $this->translationMessages[$langId] = new Config(
                Path::join($this->getDataFolder(), "languages", $langId . ".yml")
            );
        }
    }

    /**
     * @return array
     */
    public function getTranslationMessages(): array
    {
        return $this->translationMessages;
    }

    /**
     * @return \pocketmine\utils\Config
     */
    public function getDefaultMessages(): Config
    {
        return $this->defaultMessages;
    }

    /**
     * @return \surva\worlds\types\Defaults
     */
    public function getDefaults(): Defaults
    {
        return $this->defaults;
    }
}
