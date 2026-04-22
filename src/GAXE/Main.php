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
use pocketmine\item\enchantment\EnchantmentInstance;

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

            // Permission check
            if(!$sender->hasPermission("gaxe.use")) {
                $sender->sendMessage($this->msg("messages.no-permission"));
                return true;
            }

            $cooldownTime = (int)$this->getConfig()->get("cooldown");
            $name = strtolower($sender->getName());
            $time = time();

            // Cooldown check
            if($this->cooldowns->exists($name)) {
                $lastUse = (int)$this->cooldowns->get($name);

                if(($time - $lastUse) < $cooldownTime) {
                    $remaining = $cooldownTime - ($time - $lastUse);
                    $sender->sendMessage(str_replace("{time}", $remaining, $this->msg("messages.cooldown")));
                    return true;
                }
            }

            // Give axe
            $this->giveAxe($sender);

            // Save cooldown
            $this->cooldowns->set($name, $time);
            $this->cooldowns->save();

            $sender->sendMessage($this->msg("messages.received"));
        }

        return true;
    }

    private function giveAxe(Player $player): void {

        $config = $this->getConfig()->get("axe");

        $axe = VanillaItems::DIAMOND_AXE();

        // Set custom name
        $axe->setCustomName(TextFormat::colorize($config["name"] ?? "&bGAXE"));

        // Base lore
        $lore = [];
        if(isset($config["lore"]) && is_array($config["lore"])) {
            foreach($config["lore"] as $line) {
                $lore[] = TextFormat::colorize($line);
            }
        }

        $showEnchants = $config["show-enchants-in-lore"] ?? true;
        $enchLore = [];

        // Apply enchants
        if(isset($config["enchants"]) && is_array($config["enchants"])) {
            foreach($config["enchants"] as $enchString) {

                if(!str_contains($enchString, ":")) continue;

                [$enchName, $level] = explode(":", $enchString);

                $enchant = StringToEnchantmentParser::getInstance()->parse($enchName);

                if($enchant !== null) {
                    $axe->addEnchantment(new EnchantmentInstance($enchant, (int)$level));

                    if($showEnchants) {
                        $enchLore[] = TextFormat::GRAY . ucfirst($enchName) . " " . $level;
                    }
                }
            }
        }

        // Add enchant lore if enabled
        if($showEnchants && count($enchLore) > 0) {
            $lore[] = ""; // spacer
            foreach($enchLore as $line) {
                $lore[] = $line;
            }
        }

        $axe->setLore($lore);

        $player->getInventory()->addItem($axe);
    }

    private function msg(string $path): string {
        return TextFormat::colorize($this->getConfig()->getNested($path) ?? "Config error");
    }
}
