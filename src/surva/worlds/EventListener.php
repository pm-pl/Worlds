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
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\level\LevelLoadEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\Listener;
use pocketmine\item\PaintingItem;
use pocketmine\item\TieredTool;
use pocketmine\Player;

class EventListener implements Listener {
    /* @var Worlds */
    private $worlds;

    public function __construct(Worlds $worlds) {
        $this->worlds = $worlds;
    }

    /**
     * @param LevelLoadEvent $event
     */
    public function onLevelLoad(LevelLoadEvent $event): void {
        $foldername = $event->getLevel()->getFolderName();

        $this->getWorlds()->loadWorld($foldername);
        $world = $this->getWorlds()->getWorldByName($foldername);
        $level = $event->getLevel();

        // DaylightCycle
        $level->setTime(0);
        $level->stopTime = !$world->getDaylightCycle();
    }

    /**
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $foldername = $player->getLevel()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if($world->getPermission() !== null) {
                if(!$player->hasPermission($world->getPermission())) {
                    $player->sendMessage($this->getWorlds()->getMessage("general.permission"));

                    $player->teleport($this->getWorlds()->getServer()->getDefaultLevel()->getSafeSpawn());
                }
            }

            if($world->getGamemode() !== null) {
                if(!$player->hasPermission("worlds.special.gamemode")) {
                    $player->setGamemode($world->getGamemode());
                }
            }

            if($world->getFly() === true OR $player->hasPermission("worlds.special.fly")) {
                $player->setAllowFlight(true);
            } elseif($world->getFly() === false) {
                $player->setAllowFlight(false);
            }
        }
    }

    /**
     * @param EntityLevelChangeEvent $event
     */
    public function onEntityLevelChange(EntityLevelChangeEvent $event): void {
        $player = $event->getEntity();
        $foldername = $event->getTarget()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if($player instanceof Player) {
                if($world->getPermission() !== null) {
                    if(!$player->hasPermission($world->getPermission())) {
                        $player->sendMessage($this->getWorlds()->getMessage("general.permission"));

                        $event->setCancelled();

                        return;
                    }
                }

                if($world->getGamemode() !== null) {
                    if(!$player->hasPermission("worlds.special.gamemode")) {
                        $player->setGamemode($world->getGamemode());
                    }
                }

                if($world->getFly() === true OR $player->hasPermission("worlds.special.fly")) {
                    $player->setAllowFlight(true);
                } elseif($world->getFly() === false) {
                    $player->setAllowFlight(false);
                }
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $foldername = $player->getLevel()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if(!$player->hasPermission("worlds.admin.build")) {
                if($world->getBuild() === false) {
                    $event->setCancelled();
                }
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $foldername = $player->getLevel()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if(!$player->hasPermission("worlds.admin.build")) {
                if($world->getBuild() === false) {
                    $event->setCancelled();
                }
            }
        }
    }

    /**
     * @param PlayerBucketEmptyEvent $event
     */
    public function onPlayerBucketEmpty(PlayerBucketEmptyEvent $event) {
        $player = $event->getPlayer();
        $foldername = $player->getLevel()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if(!$player->hasPermission("worlds.admin.build")) {
                if($world->getBuild() === false) {
                    $event->setCancelled();
                }
            }
        }
    }

    /**
     * @param EntityDamageEvent $event
     */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        $level = $entity->getLevel();

        if($level === null) {
            return;
        }

        $foldername = $level->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if($entity instanceof Player) {
                if($event instanceof EntityDamageByEntityEvent) {
                    if($world->getPvp() === false) {
                        $event->setCancelled();
                    }
                } else {
                    if($event->getCause() !== EntityDamageEvent::CAUSE_VOID) {
                        if($world->getDamage() === false) {
                            $event->setCancelled();
                        }
                    }
                }
            } elseif($entity instanceof Painting) {
                if($event instanceof EntityDamageByEntityEvent) {
                    $damager = $event->getDamager();

                    if($damager instanceof Player) {
                        if(!$damager->hasPermission("worlds.admin.build")) {
                            if($world->getBuild() === false) {
                                $event->setCancelled();
                            }
                        }
                    } else {
                        if($world->getBuild() === false) {
                            $event->setCancelled();
                        }
                    }
                } else {
                    if($world->getBuild() === false) {
                        $event->setCancelled();
                    }
                }
            }
        }
    }

    /**
     * @param ExplosionPrimeEvent $event
     */
    public function onExplosionPrime(ExplosionPrimeEvent $event): void {
        $player = $event->getEntity();
        $foldername = $player->getLevel()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if($world->getExplode() === false) {
                $event->setCancelled();
            }
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $player = $event->getPlayer();
        $foldername = $player->getLevel()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if($world->getDrop() === false) {
                $event->setCancelled();
            }
        }
    }

    /**
     * @param PlayerExhaustEvent $event
     */
    public function onPlayerExhaust(PlayerExhaustEvent $event): void {
        $player = $event->getPlayer();
        $foldername = $player->getLevel()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if($world->getHunger() === false) {
                $event->setCancelled();
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();

        $foldername = $player->getLevel()->getFolderName();

        if($world = $this->getWorlds()->getWorldByName($foldername)) {
            if(!$player->hasPermission("worlds.admin.interact")) {
                if($world->getInteract() === false) {
                    $event->setCancelled();
                }
            }

            if(
                $item instanceof PaintingItem OR
                $block instanceof ItemFrame OR
                ($item instanceof TieredTool AND $block instanceof Grass)
            ) {
                if(!$player->hasPermission("worlds.admin.build")) {
                    if($world->getBuild() === false) {
                        $event->setCancelled();
                    }
                }
            }
        }
    }

    /**
     * @return Worlds
     */
    public function getWorlds(): Worlds {
        return $this->worlds;
    }
}
