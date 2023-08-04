<?php

/**
 * Worlds | EventListener
 */

namespace surva\worlds;

use pocketmine\block\Grass;
use pocketmine\block\ItemFrame;
use pocketmine\entity\object\Painting;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\item\PaintingItem;
use pocketmine\item\Potion;
use pocketmine\item\StringToItemParser;
use pocketmine\item\TieredTool;
use pocketmine\player\Player;
use surva\worlds\utils\Flags;

class EventListener implements Listener
{
    private Worlds $worlds;

    public function __construct(Worlds $worlds)
    {
        $this->worlds = $worlds;
    }

    /**
     * Register world in the plugin when loading
     *
     * @param  \pocketmine\event\world\WorldLoadEvent  $event
     */
    public function onWorldLoad(WorldLoadEvent $event): void
    {
        $folderName = $event->getWorld()->getFolderName();

        $this->worlds->registerWorld($folderName);
    }

    /**
     * Apply world options when a player joins the game
     *
     * @param  \pocketmine\event\player\PlayerJoinEvent  $event
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player     = $event->getPlayer();
        $pmWorld    = $player->getWorld();
        $folderName = $pmWorld->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($world->getStringFlag(Flags::FLAG_PERMISSION) !== null) {
            if (!$player->hasPermission($world->getStringFlag(Flags::FLAG_PERMISSION))) {
                $this->worlds->sendMessage($player, "general.permission");

                $defaultWorld = $this->worlds->getServer()->getWorldManager()->getDefaultWorld();

                if ($defaultWorld === null) {
                    return;
                }

                $player->teleport($defaultWorld->getSafeSpawn());
            }
        }

        $this->worlds->applyWorldOptions($world, $player);
    }

    /**
     * Apply world options when a player changes its world on teleportation
     *
     * @param  \pocketmine\event\entity\EntityTeleportEvent  $event
     */
    public function onEntityTeleport(EntityTeleportEvent $event): void
    {
        $player     = $event->getEntity();
        $origin     = $event->getFrom()->getWorld();
        $target     = $event->getTo()->getWorld();
        $folderName = $target->getFolderName();

        if (!($player instanceof Player)) {
            return;
        }

        if ($origin->getId() === $target->getId()) {
            return;
        }

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($world->getStringFlag(Flags::FLAG_PERMISSION) !== null) {
            if (!$player->hasPermission($world->getStringFlag(Flags::FLAG_PERMISSION))) {
                $this->worlds->sendMessage($player, "general.permission");

                $event->cancel();

                return;
            }
        }

        $this->worlds->applyWorldOptions($world, $player);

        if ($world->getBoolFlag(Flags::FLAG_DAYLIGHT_CYCLE) === true) {
            $target->startTime();
        } elseif ($world->getBoolFlag(Flags::FLAG_DAYLIGHT_CYCLE) === false) {
            $target->stopTime();
        }
    }

    /**
     * Check command before execution if command execution is blocked or managed by a control list
     *
     * @param  \pocketmine\event\server\CommandEvent  $event
     *
     * @return void
     */
    public function onCommand(CommandEvent $event): void
    {
        $sender = $event->getSender();
        $commandStr = $event->getCommand();

        if (!($sender instanceof Player)) {
            return;
        }

        $folderName = $sender->getWorld()->getFolderName();
        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        $commandParts = explode(" ", $commandStr);
        $commandName = $commandParts[0];

        if ($commandName === "worlds" || $commandName === "ws") {
            return;
        }

        if ($sender->hasPermission("worlds.admin.command")) {
            return;
        }

        if ($world->checkControlList(Flags::FLAG_COMMAND, $commandName) === false) {
            $this->worlds->sendMessage($sender, "general.command.no_permission");

            $event->cancel();
        }
    }

    /**
     * Prevent breaking blocks if option is set
     *
     * @param  \pocketmine\event\block\BlockBreakEvent  $event
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $player     = $event->getPlayer();
        $blockAliases = StringToItemParser::getInstance()->lookupBlockAliases($event->getBlock());
        $folderName = $player->getWorld()->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($player->hasPermission("worlds.admin.build")) {
            return;
        }

        if ($world->checkControlList(Flags::FLAG_BUILD, $blockAliases) === false) {
            $event->cancel();
        }
    }

    /**
     * Prevent placing blocks if option is set
     *
     * @param  \pocketmine\event\block\BlockPlaceEvent  $event
     */
    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        $player     = $event->getPlayer();
        $folderName = $player->getWorld()->getFolderName();

        $blocks = $event->getTransaction()->getBlocks();
        $aliasesOfPlacedBlocks = [];
        foreach ($blocks as $blockData) {
            $block = $blockData[3];
            $blockAliases = StringToItemParser::getInstance()->lookupBlockAliases($block);
            $aliasesOfPlacedBlocks = array_merge($aliasesOfPlacedBlocks, $blockAliases);
        }

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($player->hasPermission("worlds.admin.build")) {
            return;
        }

        if ($world->checkControlList(Flags::FLAG_BUILD, $aliasesOfPlacedBlocks) === false) {
            $event->cancel();
        }
    }

    /**
     * Prevent using buckets if building is disabled
     *
     * @param  \pocketmine\event\player\PlayerBucketEmptyEvent  $event
     */
    public function onPlayerBucketEmpty(PlayerBucketEmptyEvent $event): void
    {
        $player     = $event->getPlayer();
        $itemAliases = StringToItemParser::getInstance()->lookupAliases($event->getBucket());
        $folderName = $player->getWorld()->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($player->hasPermission("worlds.admin.build")) {
            return;
        }

        if ($world->checkControlList(Flags::FLAG_BUILD, $itemAliases) === false) {
            $event->cancel();
        }
    }

    /**
     * Handle damage options and prevent damage if needed
     *
     * @param  \pocketmine\event\entity\EntityDamageEvent  $event
     */
    public function onEntityDamage(EntityDamageEvent $event): void
    {
        $entity     = $event->getEntity();
        $pmWorld    = $entity->getWorld();
        $folderName = $pmWorld->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($entity instanceof Player) {
            if ($event instanceof EntityDamageByEntityEvent) {
                if ($world->getBoolFlag(Flags::FLAG_PVP) === false) {
                    $event->cancel();
                }
            } elseif ($event->getCause() !== EntityDamageEvent::CAUSE_VOID) {
                if ($world->getBoolFlag(Flags::FLAG_DAMAGE) === false) {
                    $event->cancel();
                }
            }
        } elseif ($entity instanceof Painting) {
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();

                if ($damager instanceof Player) {
                    if (!$damager->hasPermission("worlds.admin.build")) {
                        if ($world->getBoolFlag(Flags::FLAG_BUILD) === false) {
                            $event->cancel();
                        }
                    }
                } elseif ($world->getBoolFlag(Flags::FLAG_BUILD) === false) {
                    $event->cancel();
                }
            } elseif ($world->getBoolFlag(Flags::FLAG_BUILD) === false) {
                $event->cancel();
            }
        }
    }

    /**
     * Prevent explosions if policy is set
     *
     * @param  \pocketmine\event\entity\EntityPreExplodeEvent  $event
     *
     * @return void
     */
    public function onExplosionPrime(EntityPreExplodeEvent $event): void
    {
        $player     = $event->getEntity();
        $folderName = $player->getWorld()->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($world->getBoolFlag(Flags::FLAG_EXPLODE) === false) {
            $event->cancel();
        }
    }

    /**
     * Prevent dropping items if policy is set
     *
     * @param  \pocketmine\event\player\PlayerDropItemEvent  $event
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void
    {
        $player     = $event->getPlayer();
        $itemAliases = StringToItemParser::getInstance()->lookupAliases($event->getItem());
        $folderName = $player->getWorld()->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($world->checkControlList(Flags::FLAG_DROP, $itemAliases) === false) {
            $event->cancel();
        }
    }

    /**
     * Prevent exhaustion if hunger is disabled
     *
     * @param  \pocketmine\event\player\PlayerExhaustEvent  $event
     */
    public function onPlayerExhaust(PlayerExhaustEvent $event): void
    {
        $player     = $event->getPlayer();
        $folderName = $player->getWorld()->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($world->getBoolFlag(Flags::FLAG_HUNGER) === false) {
            $event->cancel();
        }
    }

    /**
     * Prevent interaction if disabled and some block breaking events like painting/grass, ...
     *
     * @param  \pocketmine\event\player\PlayerInteractEvent  $event
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $player     = $event->getPlayer();
        $item       = $event->getItem();
        $block      = $event->getBlock();
        $blockAliases = StringToItemParser::getInstance()->lookupBlockAliases($block);
        $folderName = $player->getWorld()->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if (!$player->hasPermission("worlds.admin.interact")) {
            if ($world->checkControlList(Flags::FLAG_INTERACT, $blockAliases) === false) {
                $event->cancel();
            }
        }

        if (
            $item instanceof PaintingItem or
            $block instanceof ItemFrame or
            ($item instanceof TieredTool and $block instanceof Grass)
        ) {
            if (!$player->hasPermission("worlds.admin.build")) {
                if ($world->checkControlList(Flags::FLAG_BUILD, $blockAliases) === false) {
                    $event->cancel();
                }
            }
        }
    }

    /**
     * Prevent leaves decaying if option is set
     *
     * @param  \pocketmine\event\block\LeavesDecayEvent  $event
     */
    public function onLeavesDecay(LeavesDecayEvent $event): void
    {
        $folderName = $event->getBlock()->getPosition()->getWorld()->getFolderName();

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($world->getBoolFlag(Flags::FLAG_LEAVES_DECAY) === false) {
            $event->cancel();
        }
    }

    /**
     * Prevent consuming potions if disabled
     *
     * @param  \pocketmine\event\player\PlayerItemConsumeEvent  $event
     */
    public function onPlayerItemConsume(PlayerItemConsumeEvent $event): void
    {
        $player     = $event->getPlayer();
        $item       = $event->getItem();
        $itemAliases = StringToItemParser::getInstance()->lookupAliases($item);
        $folderName = $player->getWorld()->getFolderName();

        if (!($item instanceof Potion)) {
            return;
        }

        if (($world = $this->worlds->getWorldByName($folderName)) === null) {
            return;
        }

        if ($world->checkControlList(Flags::FLAG_POTION, $itemAliases) === false) {
            $event->cancel();
        }
    }
}
