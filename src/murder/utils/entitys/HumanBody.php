<?php


namespace murder\utils\entitys;

use murder\utils\arena\Arena;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\format\FullChunk;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\Player;
use pocketmine\item\Item;

class HumanBody extends \pocketmine\entity\Human
{

    protected $game = null;
	
	public function attack($damage, EntityDamageEvent $source)
    {
        if(!$source instanceof EntityDamageByEntityEvent)
            return $source->setCancelled(true);
        $player = $source->getDamager();
        if(!$player instanceof Player)
            return $source->setCancelled(true);
        return $source->setCancelled(true);
    }

    /*
    public function __construct(FullChunk $chunk, CompoundTag $nbt)
    {
        parent::__construct($chunk, $nbt);
        $this->setDataProperty(Player::DATA_PLAYER_BED_POSITION, Player::DATA_TYPE_POS, [$this->getX(), $this->getY(), $this->getZ()]);
    }
    */

    public function setGame(Arena $arena){
	    return $this->game = $arena;
    }

    public function getGame(){
	    return $this->game;
    }

    public function spawnTo(Player $player)
    {
        if(!$this->getGame()){
            $this->close();
            return;
        }

        if (!(isset($this->hasSpawned[$player->getLoaderId()]))) {

            $this->hasSpawned[$player->getLoaderId()] = $player;

            $uuid = $this->getUniqueId();
            $entityId = $this->getId();

            $pk = new AddPlayerPacket();
            $pk->uuid = $uuid;
            $pk->username = uniqid('');
            $pk->eid = $entityId;
            $pk->x = $this->x;
            $pk->y = $this->y;
            $pk->z = $this->z;
            $pk->yaw = 0;
            $pk->pitch = 0;
            $pk->item = Item::get(Item::AIR);
            $pk->metadata = [
                2 => [4, $this->getDataProperty(2)],
                3 => [0, $this->getDataProperty(3)],
                15 => [0, 1],
                17 => [6, [$this->x, $this->y, $this->z]],
                23 => [7, -1],
                24 => [0, 0]
            ];
            $player->dataPacket($pk);


        }
    }

}