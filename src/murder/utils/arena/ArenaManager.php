<?php

namespace murder\utils\arena;

use murder\Main;

use murder\scheduler\ShootCountdown;

use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\Player;
use pocketmine\Server;

class ArenaManager implements Listener {

    protected $owner;

    public function __construct(Main $main)
    {
        $this->owner = $main;
    }

    public function onDamage(EntityDamageEvent $event){

        /** @var Player $player */
        $player = $event->getEntity();

        if (!($player instanceof Player))
            return false;

        /** @var Arena $arena */
        if(!$arena = $this->owner->getPlayerArena($player))
            return false;

        $event->setCancelled(true);


        if($event instanceof EntityDamageByChildEntityEvent) {

            /** @var Player $damager */
            $damager = $event->getDamager();

            if($arena->isMurder($player)){
                $damager->sendMessage('§cVocê matou o murder! Parabens!!!');

                foreach (array_merge($arena->players, $arena->spectators) as $name) {
                    $arena->broadcast($name, "§r\n§aO §c§lMurder§r " . $player->getName() . "§a foi morto por §f" . $damager->getName() . "§a!\n§r");
                }

                $arena->killPlayer($player);

            } else {

                $arena->killPlayer($player);
                if(!$arena->isMurder($damager)) {
                    $damager->sendMessage('§cOops, voce matou um jogador inocente e morreu também!');
                    $arena->killPlayer($damager);
                }

            }

        } elseif($event instanceof EntityDamageByEntityEvent) {

            /** @var Player $damager */
            $damager = $event->getDamager();

            if ($arena->isMurder($damager)) {
                list($id, $damage) = explode(":", Main::$items['murder']['items']['weapon']['item']);
                if (
                    $damager->getInventory()->getItemInHand()->getId() == $id
                    and $damager->getInventory()->getItemInHand()->getDamage() == $damage
                ) {
                    $damager->sendMessage('§a* Voce matou o jogador §f' . $player->getName() . '§c!');
                    $arena->killPlayer($player);
                }

            }

        }
        return true;
    }

    public function colide(ProjectileHitEvent $event){
        $projectile = $event->getEntity();

        /** @var $player Player */
        $player = $projectile->shootingEntity;

        if(!$player instanceof Player)
            return false;

        if(!Main::getPlayerArena($player))
            return false;

        $projectile->close();
        $this->owner->getServer()->getScheduler()->scheduleRepeatingTask(new ShootCountdown($this->owner, $player), 20);
        return true;
    }

    public function quit(PlayerQuitEvent $event){
        $player = $event->getPlayer();

        if(!($arena = Main::getPlayerArena($player)))
            return false;

        $arena->quit($player);
        return true;
    }

    public function dropItem(PlayerDropItemEvent $event){
        $player = $event->getPlayer();

        if(!$this->owner->getPlayerArena($player))
            return false;

        $player->getInventory()->addItem($event->getItem());
        $event->setCancelled(true);
        return true;
    }

    public function pickupItem(InventoryPickupItemEvent $event): bool
    {

        /** @var Player $player */
        $player = $event->getInventory()->getHolder();

        if(!($arena = Main::getPlayerArena($player)))
            return false;


        if($event->getItem()->getItem()->getId() == Item::GOLD_INGOT) {
        	
            if ($arena->isInnocent($player) or $arena->isMurder($player)) {
            	
                if (
                    $event->getInventory()->contains(Item::get(Item::ARROW)) or
                    $arena->isMurder($player) or
                    $arena->isDetective($player)
                ) {
                    $event->setCancelled(true);
                } else {
                	
                    if ($event->getInventory()->contains(Item::get(266, 0, (Main::$config['amount-for-exchange'] - 1)))) {

                        $player->getInventory()->remove(Item::get(266, 0, Main::$config['amount-for-exchange']));

                        if(!$player->getInventory()->contains(Item::get(Item::BOW))) {
                            $player->getInventory()->addItem(Item::get(Item::BOW));
                        }
                        $player->getInventory()->addItem(Item::get(Item::ARROW));

                        $player->sendMessage('§a* Voce recebeu uma arma para se tornar um heroi!');
                    } else {
                    	unset($arena->spawnedOres[$event->getItem()->x . ':' . $event->getItem()->y . ':' . $event->getItem()->z]);
                        $player->sendMessage('§aVocê coletou 1 ouro...');
                    }
                    
                }
                
            } else {
                $event->setCancelled(true);
            }
            
        } elseif ($event->getItem()->getItem()->getId() == 261){

            if($arena->isInnocent($player)) {
                foreach (array_merge($arena->players) as $name) {
                    $arena->broadcast($name, '§a* O arco do detetive foi pego por alguem!');
                }
                $player->sendMessage('§a* Você pegou o arco do Detetive!');
                $player->getInventory()->addItem(Item::get(Item::ARROW));
            } else {
                $event->setCancelled(true);
            }

        } else {
            $event->setCancelled(true);
        }
        return true;
    }

    public function interact(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $item = $event->getItem();

        if(!($arena = Main::getPlayerArena($player)))
            return false;

        switch ($item->getCustomName()){
            case Main::$items['murder']['items']['compass']['customName']:
                if($arena->getStats() == $arena::STATS_RUNNING){
                    $distance = -1;
                    foreach ($arena->players as $name){
                        $p = Server::getInstance()->getPlayerExact($name);
                        if($p !== $player and $p !== null){
                            if($player->distance($p) < $distance or $distance == -1){
                                $distance = $player->distance($p);
                            }
                        }
                    }

                    if($distance == -1){
                        $player->sendTip("§cNenhum jogador próximo encontrado!");
                    } else {
                        $player->sendTip("§aJogador mais proximo encontrado há §f" . round($distance) . "§a blocos!");
                    }

                }
                break;
            case Main::$items['murder']['items']['gadget']['customName']:
                $player->getInventory()->removeItem($item);
                $player->addEffect(Effect::getEffect(Effect::SPEED)->setAmplifier(2)->setDuration(20 * 8)->setVisible(false));
                $player->sendMessage("§a* Adrenalina aplicada com sucesso, tudo está ficando lento...");
                break;
        }
    }

    public function preCmd(PlayerCommandPreprocessEvent $event){
        $player = $event->getPlayer();

        $message = $event->getMessage();
        $args = explode(" ", $message);


        if ($arena = Main::getPlayerArena($player)) {
        	
            if ($args[0] != "/mdr" and $args[0] != "/murder") {
                $event->setCancelled(true);
                if(substr($message, 0, 1) != "/" and $arena->isAlive($player)){
                	foreach($arena->getInArena() as $name){
                 	   $arena->broadcast($name, "§c[Murder-Chat] " . $player->getName() . " §7" . $message);
                	}
                } else {
                    $player->sendMessage("§cVocê não pode escrever no chat enquanto estiver jogando Murder! Use: /mdr sair!");
                }
            }
        }
        
    }
}