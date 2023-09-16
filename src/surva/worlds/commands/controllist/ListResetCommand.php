<?php

/**
 * Worlds | control list reset command
 */

namespace surva\worlds\commands\controllist;

use InvalidArgumentException;
use pocketmine\command\CommandSender;
use surva\worlds\types\exception\ConfigSaveException;

class ListResetCommand extends ControlListCommand
{
    /**
     * @inheritDoc
     */
    public function do(CommandSender $sender, array $args): bool
    {
        if (count($args) !== 0) {
            return false;
        }

        $flag = $this->getFlagName();

        $controlList = $this->getWorld()->getControlListContent($flag);

        if ($controlList === null) {
            return false;
        }

        $controlList->reset();
        try {
            $this->getWorld()->saveControlList($flag);
        } catch (ConfigSaveException | InvalidArgumentException $e) {
            $this->getWorlds()->sendMessage($sender, "general.config.save_error");

            return true;
        }

        $this->getWorlds()->sendMessage(
            $sender,
            "controllist.reset.success",
            ["key" => $flag]
        );

        return true;
    }
}
