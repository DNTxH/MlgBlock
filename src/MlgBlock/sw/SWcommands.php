<?php

/*
 *                _   _
 *  ___  __   __ (_) | |   ___
 * / __| \ \ / / | | | |  / _ \
 * \__ \  \ / /  | | | | |  __/
 * |___/   \_/   |_| |_|  \___|
 *
 * SkyWars plugin for PocketMine-MP & forks
 *
 * @Author: MlgBlock
 * @Kik: _MlgBlock_
 * @Telegram_Group: https://telegram.me/SKYMLG
 * @E-mail: thesville@gmail.com
 * @Github: https://github.com/SKYMLGx/SkyWars-PocketMine
 *
 * Copyright (C) 2016 MlgBlock
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * DONORS LIST :
 * - Ahmet
 * - Jinsong Liu
 * - no one
 *
 */

namespace MlgBlock\sw;

use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\command\Command
use pocketmine\command\PluginCommand;
use pocketmine\world\world;
use pocketmine\math\Vector3;
use pocketmine\player\Player
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class SWcommands extends PluginCommand {

    private function formatUsageMessage(string $message) : string
    {
        //The widely-accepted rule is <> brackets for mandatory fields and [] for optional.
        //Highlights message in red, <> in GREEN and [] in gray

        return TextFormat::RED . strtr($message, [
                "[" => TextFormat::GRAY . "[",
                "]" => "]" . TextFormat::RED,
                "<" => TextFormat::GREEN . "<",
                ">" => ">" . TextFormat::RED
            ]);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool
    {
        switch ($cmd = strtolower(array_shift($args) ?? "help")) {
            case "join":
                if (count($args) > 2 || (!($sender instanceof Player) && !isset($args[1]))) {
                    $sender->sendMessage($this->formatUsageMessage("Usage: /MlgBlock" . $commandLabel . " join <arena> [PlayerName=YOU]"));
                    return false;
                }

                if (!isset($args[0])) {
                    if ($sender instanceof Player) {
                        foreach ($this->getPlugin()->arenas as $arena) {
                            if ($arena->join($sender, false)) {
                                return true;
                            }
                        }
                        $sender->sendMessage(TextFormat::RED . "No games, retry later");
                    }
                    return false;
                }

                //SW NAME
                $arena = $args[0];
                if (!isset($this->getPlugin()->arenas[$arena])) {
                    $sender->sendMessage(TextFormat::RED . "Arena with name: " . TextFormat::WHITE . $arena . TextFormat::RED . " doesn't exist.");
                    return false;
                }

                if ($sender->isOp() && isset($args[1])) {
                    $p = $sender->getServer()->getPlayer($args[1]);
                    if ($p !== null) {
                        if ($this->getPlugin()->inArena($p)) {
                            $sender->sendMessage(TextFormat::RED . $p->getName() . " is already inside an arena.");
                            return false;
                        }

                        $this->getPlugin()->arenas[$arena]->join($p);
                        $sender->sendMessage(TextFormat::GREEN . $p->getName() . " has been sent to " . TextFormat::GREEN . $arena . TextFormat::GREEN . "arena.");
                        return true;
                    }
                    $sender->sendMessage(TextFormat::RED . "Player not found!");
                    return false;
                }

                if ($sender instanceof Player) {
                    if ($this->getPlugin()->inArena($sender)) {
                        $sender->sendMessage(TextFormat::RED . "You are already inside an arena.");
                        return false;
                    }

                    $this->getPlugin()->arenas[$arena]->join($sender);
                    return true;
                }

                $sender->sendMessage(TextFormat::RED . "Player not found!");
                return false;
            case "quit":
                if ($sender instanceof Player) {
                    foreach ($this->getPlugin()->arenas as $arena) {
                        if ($arena->closePlayer($sender, true)) {
                            return true;
                        }
						$sender->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
                    }

                    $sender->sendMessage(TextFormat::RED . "You are not in an arena.");
                    return false;
                }

                $sender->sendMessage(TextFormat::RED . "This command is only avaible in game.");
                return false;
            case ($cmd === "create" && $sender->isOp()):
                $args_c = count($args);
                if ($args_c < 2 || $args_c > 5) {
                    $sender->sendMessage($this->formatUsageMessage("Usage: /" . $commandLabel . " create <SWname> <slots> [countdown=30] [maxGameTime=600]"));
                    return false;
                }

                $arena = $args[0];
                if (isset($this->getPlugin()->arenas[$arena])) {
                    $sender->sendMessage(TextFormat::RED . "An arena with this name already exists.");
                    return false;
                }

                $len = strlen($arena);
                if ($len < 3 || $len > 15 || !ctype_alnum($arena)) {//TODO: Figure out the reason behind this.
                    $sender->sendMessage(TextFormat::RED . "Arena name must contain 3-15 digits and must be alpha-numeric.");
                    return false;
                }

                $world = $sender->getLevel();
                $world_name = $world->getFolderName();

                if ($this->getPlugin()->getServer()->getDefaultLevel() === $world) {//TODO: Figure out the reason behind this.
                    $sender->sendMessage(TextFormat::RED . "You can't create an arena in the default world.");
                    return false;
                }

                //Checks if there is already an arena in the world
                foreach ($this->getPlugin()->arenas as $aname => $arena_instance) {
                    if ($arena_instance->getWorld() === $world_name) {
                        $sender->sendMessage(
                            TextFormat::RED . "You can't create multiple arenas in the same world. Try:" . TextFormat::EOL .
                            TextFormat::GOLD . "/" . $commandLabel . " list " . TextFormat::RED . "for a list of arenas." . TextFormat::EOL .
                            TextFormat::GOLD . "/" . $commandLabel . " delete " . TextFormat::RED . "to delete an arena."
                        );
                        return false;
                    }
                }

                //Checks if there is already a join sign in the world
                foreach ($this->getPlugin()->signs as $loc => $name) {
                    $xyzworld = explode(":", $loc);
                    if ($xyzworld[3] === $level_name) {
                        $sender->sendMessage(
                            TextFormat::RED . "You can't create an arena in the same world of a join sign:" . TextFormat::EOL .
                            TextFormat::GREEN . "Remove the sign at (X=" . $xyzworld[0] . ", Y=" . $xyzworld[1] . ", Z=" . $xyzworld[2] . ")" . TextFormat::EOL .
                            TextFormat::RED . "Use " . TextFormat::GREEN . "/" . $commandLabel . " signdelete " . TextFormat::RED . "to delete signs."
                        );
                        return false;
                    }
                }

                $maxslots = $args[1];
                if (!is_numeric($maxslots) || strpos($maxslots, ".") !== false || $maxslots < 1) {
                    $sender->sendMessage(TextFormat::RED . "Invalid maxslots value '" . $maxslots . "', maxslots must be an integer > 0.");
                    return false;
                }

                $maxslots = (int) $maxslots;

                if (isset($args[2])) {
                    $countdown = $args[2];
                    if (!is_numeric($countdown) || strpos($countdown, ".") !== false || $countdown < 1) {
                        $sender->sendMessage(TextFormat::RED . "Invalid countdown value '" . $countdown . "', countdown must be an integer > 0.");
                        return false;
                    }

                    $countdown = (int) $countdown;
                } else {
                    $countdown = 30;
                }

                if (isset($args[3])) {
                    $maxtime = $args[3];
                    if (!is_numeric($maxtime) || strpos($maxtime, ".") !== false || $maxtime < 1) {
                        $sender->sendMessage(TextFormat::RED . "Invalid maxGameTime value '" . $maxtime . "', maxGameTime must be an integer > 0.");
                        return false;
                    }
                } else {
                    $maxtime = 600;
                }

                $provider = $sender->getLevel()->getProvider();
                if ($this->getPlugin()->configs["world.generator.air"]) {
                    $world_data = $provider->getLevelData();
                    $world_data->setString("generatorName", "flat");
                    $world_data->setString("generatorOptions", "0;0;0");
                    $provider->saveLevelData();
                }

                $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Calculating minimum void in world '" . $level_name . "'...");

                //This is the "fake void"
                $void_y = Level::Y_MAX;
                foreach ($level->getChunks() as $chunk) {
                    for ($x = 0; $x < 16; ++$x) {
                        for ($z = 0; $z < 16; ++$z) {
                            for ($y = 0; $y < $void_y; ++$y) {
                                $block = $chunk->getBlockId($x, $y, $z);
                                if ($block !== Block::AIR) {
                                    $void_y = $y;
                                    break;
                                }
                            }
                        }
                    }
                }

                --$void_y;
                $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Minimum void set to: " . $void_y);

                $server = $sender->getServer();

                $sender->teleport($server->getDefaultLevel()->getSpawnLocation());
                $server->unloadWorld($world);
                unset($world);

                $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Creating backup of world '" . $world_name . "'...");

                @mkdir($this->getPlugin()->getDataFolder() . "arenas/" . $arena, 0755);

                $tar = new \PharData($this->getPlugin()->getDataFolder() . "arenas/" . $arena . "/" . $world_name . ".tar");
                $tar->startBuffering();
                $tar->buildFromDirectory(realpath($sender->getServer()->getDataPath() . "worlds/" . $world_name));

                if ($this->getPlugin()->configs["world.compress.tar"]) {
                    $sender->sendMessage(TextFormat::ITALIC . TextFormat::GRAY . "Compressing world (tar-gz)...");
                    $tar->compress(\Phar::GZ);
                    $sender->sendMessage(TextFormat::ITALIC . TextFormat::GRAY . "World compressed.");
                }

                $tar->stopBuffering();
                $sender->sendMessage(TextFormat::LIGHT_PURPLE . "Backup of world '" . $world_name . "' created.");

                if ($this->getPlugin()->configs["world.compress.tar"]) {
                    $tar = null;
                    @unlink($this->getPlugin()->getDataFolder() . "arenas/" . $arena . "/" . $world_name . ".tar");
                }

                $sender->getServer()->loadLevel($level_name);
                $this->getPlugin()->arenas[$arena] = new SWarena($this->getPlugin(), $arena, $maxslots, $world_name, $countdown, $maxtime, $void_y);

                $sender->sendMessage(
                    TextFormat::GREEN . "Arena " . TextFormat::DARK_GREEN . $arena . TextFormat::GREEN . " created successfully!" . TextFormat::EOL .
                    TextFormat::GREEN . "Use " . TextFormat::GREEN . "/" . $commandLabel . " setspawn <slot#> " . TextFormat::GREEN . "to set spawnpoints for " . TextFormat::GREEN . $arena . TextFormat::GREEN . "."
                );

                $sender->teleport($sender->getServer()->getLevelByName($level_name)->getSpawnLocation());
                return true;
            case ($cmd === "setspawn" && $sender->isOp()):
                if (count($args) !== 1) {
                    $sender->sendMessage($this->formatUsageMessage("Usage: /" . $commandLabel . " setspawn <slot>"));
                    return false;
                }

                $world_name = $sender->getWorld()->getFolderName();

                foreach ($this->getPlugin()->arenas as $name => $arena_instance) {
                    if ($arena_instance->getWorld() === $level_name) {
                        $arena = $arena_instance;
                        break;
                    }
                }

                if (!isset($arena)) {
                    $sender->sendMessage(TextFormat::RED . "Arena not found here, try " . TextFormat::GREEN . "/" . $commandLabel . " create");
                    return false;
                }

                $slot = $args[0];
                if (!is_numeric($slot) || strpos($slot, ".") !== false || $slot < 1 || $slot > $arena->getSlot()) {
                    $sender->sendMessage(TextFormat::RED . "Invalid slot value '" . $slot . "', slot must be an integer > 0 and < " . $arena->getSlot() . ".");
                    return false;
                }

                $slot = (int) $slot;

                if ($arena->setSpawn($sender, $slot)) {
                    $sender->sendMessage(TextFormat::GREEN . "Set spawn #" . TextFormat::GREEN . $slot . TextFormat::GREEN . " in arena " . TextFormat::GREEN . $arena->getName() . TextFormat::GREEN . ".");
                    if ($arena->checkSpawns()) {
                        $sender->sendMessage(TextFormat::GREEN . $arena->getName() . TextFormat::GREEN . "'s spawn points have been set. You can now proceed to create a join sign.");
                    }
                }
                return true;
            case ($cmd === "list" && $sender->isOp()):
                if (!empty($this->getPlugin()->arenas)) {
                    $sender->sendMessage(TextFormat::GREEN . "Loaded arenas:");
                    foreach ($this->getPlugin()->arenas as $key => $val) {
                        $sender->sendMessage(TextFormat::GREEN . $key . TextFormat::AQUA . " [" . $val->getSlot(true) . "/" . $val->getSlot() . "]" . TextFormat::DARK_GRAY . " => " . TextFormat::GREEN . $val->getWorld());
                    }
                    return true;
                }

                $sender->sendMessage(TextFormat::RED . "You haven't configured any arenas, create one using " . TextFormat::GREEN . "/" . $commandLabel . " create");
                return false;
            case ($cmd === "delete" && $sender->isOp()):
                if (count($args) !== 1) {
                    $sender->sendMessage($this->formatUsageMessage("Usage: /" . $commandLabel . " delete <arena>"));
                    return false;
                }

                $arena = $args[0];

                if (!isset($this->getPlugin()->arenas[$arena])) {
                    $sender->sendMessage(TextFormat::RED . "Arena: " . TextFormat::GREEN . $arena . TextFormat::RED . " doesn't exist.");
                    return false;
                }

                $sender->sendMessage(TextFormat::GREEN . "Deleting arena '" . $arena . "'...");

                $this->getPlugin()->arenas[$arena]->stop(true);
                $this->getPlugin()->deleteAllSigns($arena);
                unset($this->getPlugin()->arenas[$arena]);

                foreach (scandir($this->getPlugin()->getDataFolder() . "arenas/" . $arena) as $file) {
                    $file_path = $this->getPlugin()->getDataFolder() . "arenas/" . $arena . "/" . $file;
                    if ($file !== "." && $file !== ".." && is_file($file)) {
                        @unlink($file_path);
                    }
                }

                @rmdir($this->getPlugin()->getDataFolder() . "arenas/" . $arena);
                $sender->sendMessage(TextFormat::GREEN . "Arena: '" . TextFormat::DARK_GREEN . $arena . TextFormat::GREEN . "' deleted!");
                return true;
            case ($cmd === "signdelete" && $sender->isOp()):
                if (count($args) !== 1) {
                    $sender->sendMessage($this->formatUsageMessage("Usage: /" . $commandLabel . " signdelete <arena|all>"));
                    return false;
                }

                $arena = $args[0];

                if (isset($this->getPlugin()->arenas[$arena])) {
                    $count = $this->getPlugin()->deleteAllSigns($arena);
                } elseif ($arena === "all") {
                    $count = $this->getPlugin()->deleteAllSigns();
                } else {
                    $sender->sendMessage(TextFormat::RED . "Arena '" . TextFormat::GREEN . $arena . TextFormat::RED . " does not exist!");
                    return false;
                }

                $sender->sendMessage(TextFormat::GREEN . "Deleted " . $count . " signs from " . ($arena === "all" ? "ALL arenas" : "'" . $arena . "' Arena") . ".");
                return true;
            case "help":
            default:
                if ($sender->isOp()) {
                    $sender->sendMessage($this->formatUsageMessage("Usage: /mb [create|delete|setspawn|list|signdelete|join|quit|]"));
                } else {
                    $sender->sendMessage($this->formatUsageMessage("Usage: /" . $commandLabel . " <join|quit>"));
                }
                return false;
        }

        return true;
    }
}