<?php

namespace GAXE;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\item\VanillaItems;
use pocketmine\item\enchantment\StringToEnchantmentParser;

class Main extends PluginBase {

    private Config $cooldowns;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->cooldowns = new Config($this->getDataFolder() . "cooldowns.yml", Config::YAML);
    }

    public function onDisable(): void {
        $this->cooldowns->save();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender instanceof Player) return true;

        if($command->getName() === "gaxe") {

            if(!$sender->hasPermission("gaxe.use")) {
                $sender->sendMessage($this->msg("messages.no-permission"));
                return true;
            }

            $cooldownTime = $this->getConfig()->get("cooldown");
            $name = $sender->getName();
            $time = time();

            if($this->cooldowns->exists($name)) {
                $lastUse = $this->cooldowns->get($name);
                if(($time - $lastUse) < $cooldownTime) {
                    $remaining = $cooldownTime - ($time - $lastUse);
                    $sender->sendMessage(str_replace("{time}", $remaining, $this->msg("messages.cooldown")));
                    return true;
                }
            }

            $this->giveAxe($sender);

            $this->cooldowns->set($name, $time);
            $this->cooldowns->save();

            $sender->sendMessage($this->msg("messages.received"));
        }

        return true;
    }

    private function giveAxe(Player $player): void {

        $config = $this->getConfig()->get("axe");

        $axe = VanillaItems::DIAMOND_AXE();

        // Name
        $axe->setCustomName(TextFormat::colorize($config["name"]));

        // Lore
        $lore = [];
        foreach($config["lore"] as $line) {
            $lore[] = TextFormat::colorize($line);
        }

        // Enchants
        $showEnchants = $config["show-enchants-in-lore"];
        $enchLore = [];

        foreach($config["enchants"] as $enchString) {
            [$enchName, $level] = explode(":", $enchString);

            $enchant = StringToEnchantmentParser::getInstance()->parse($enchName);
            if($enchant !== null) {
                $axe->addEnchantment($enchant->setLevel((int)$level));

                if($showEnchants) {
                    $enchLore[] = TextFormat::GRAY . ucfirst($enchName) . " " . $level;
                }
            }
        }

        if($showEnchants) {
            $lore = array_merge($lore, ["", ...$enchLore]);
        }

        $axe->setLore($lore);

        $player->getInventory()->addItem($axe);
    }

    private function msg(string $path): string {
        return TextFormat::colorize($this->getConfig()->getNested($path));
    }
}
