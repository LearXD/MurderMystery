<?php


namespace murder\utils\entitys;

use murder\utils\arena\Arena;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\Player;

class FloatingMessage extends \pocketmine\entity\Human
{

    protected $game = null;
    protected $along = null;

    protected $tick = 0;
	
	public function attack($damage, EntityDamageEvent $source)
    {
        $source->setCancelled(true);
    }

    public function onUpdate($tick)
    {
        $this->tick++;
        if($this->tick % 5 != 0) return;
        $this->tick = 0;

        if(!$this->getGame() or $this->getAlong()->closed){
            $this->close();
        }

    }

    public function setAlong(\pocketmine\entity\Entity $entity){
        return $this->along = $entity;
    }

    /**
     * @return Entity
     */
    public function getAlong(){
        return $this->along;
    }

    public function setGame(Arena $arena){
        return $this->game = $arena;
    }

    public function getGame(){
        return $this->game;
    }

    public function spawnTo(Player $player)
    {


        if (!(isset($this->hasSpawned[$player->getLoaderId()]))) {
            $this->hasSpawned[$player->getLoaderId()] = $player;

            $pk = new AddPlayerPacket();
            $pk->eid = $this->getId();
            $pk->uuid = $this->getUniqueId();
            $pk->x = $this->x;
            $pk->y = $this->y;
            $pk->z = $this->z;
            $pk->speedX = 0;
            $pk->speedY = 0;
            $pk->speedZ = 0;
            $pk->yaw = 0;
            $pk->pitch = 0;
            $pk->item = Item::get(0);
            $pk->metadata = [
                Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 1 << Entity::DATA_FLAG_INVISIBLE],
                Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $this->getDataProperty(2)],
                Entity::DATA_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1],
                Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1],
                Entity::DATA_LEAD_HOLDER => [Entity::DATA_TYPE_LONG, -1],
                Entity::DATA_LEAD => [Entity::DATA_TYPE_BYTE, 0]
            ];

        $player->dataPacket($pk);
        }
    }

}