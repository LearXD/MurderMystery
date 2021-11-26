<?php


namespace murder\utils;


use murder\Main;
use murder\utils\arena\Arena;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;

class Utils
{

    public static function searchArena(Player $player)
    {
        if (count(Main::$arenas) <= 0) {
        	$player->sendMessage('§cEste minigame nao possui partidas ainda, aguarde ate o lançamento!');
        	return null;
        }
            

        /** @var Arena $arena */
        foreach (Main::$arenas as $arena) {
            if ($arena->canJoin()) {
                return $arena;
            }
        }

		$player->sendMessage('§cNao encontramos partidas disponiveis, pois todas estao ocupadas!');
        return null;
    }


    public static function dropItem(Position $position, Item $item)
    {

        $item = Entity::createEntity("Item", $position->getLevel()->getChunk($position->getX() >> 4, $position->getZ() >> 4, true), new CompoundTag("", [
            "Pos" => new ListTag("Pos", [
                new DoubleTag("", $position->getX() + 0.5),
                new DoubleTag("", $position->getY()),
                new DoubleTag("", $position->getZ() + 0.5)
            ]),

            "Motion" => new ListTag("Motion", [
                new DoubleTag("", 0),
                new DoubleTag("", 0),
                new DoubleTag("", 0)
            ]),
            "Rotation" => new ListTag("Rotation", [
                new FloatTag("", lcg_value() * 360),
                new FloatTag("", 0)
            ]),
            "Health" => new ShortTag("Health", 5),
            "Item" => new CompoundTag('Item', [
                'id' => new ShortTag('id', $item->getId()),
                'Damage' => new ShortTag('Damage', $item->getDamage()),
                'Count' => new ByteTag('Count', $item->getCount()),
            ]),
            "PickupDelay" => new ShortTag("PickupDelay", bin2hex(1))
        ]));


        $item->spawnToAll();
        return $item;
    }

    public static function createBody(Arena $arena, Position $position, Player $player = null) {

        $body = Entity::createEntity("HumanBody", $position->level->getChunk($position->getX() >> 4, $position->getZ() >> 4), new CompoundTag("", [
            "Pos" => new ListTag('Pos', [
                new DoubleTag('', $position->getX()),
                new DoubleTag('', $position->getY() - 1.3),
                new DoubleTag('', $position->getZ()),
            ]),
            "Rotation" => new ListTag('Rotation', [
                new FloatTag('', 0),
                new FloatTag('', 0)
            ]),
            "Skin" => new CompoundTag('Skin', [
                "Data" => new StringTag('Data', $player ? $player->getSkinData() : ''),
                "Name" => new StringTag('Name', $player ? $player->getSkinId() : '')
            ]),
            "CustomName" => new StringTag("CustomName", self::centralize("§c§lCORPO DO JOGADOR§r\n§f". $player->getName())),
        ]));

        $body->setGame($arena);
        $body->spawnToAll();

        $body->setDataFlag(Player::DATA_PLAYER_FLAGS, Player::DATA_PLAYER_FLAG_SLEEP, true);

        return $body;
    }

    public static function spawnFloatingMessage(Arena $arena, Position $position, string $name) {
        $entity = Entity::createEntity("FloatingMessage", $position->level->getChunk($position->getX() >> 4, $position->getZ() >> 4), new CompoundTag("", [
            "Pos" => new ListTag('Pos', [
                new DoubleTag('', $position->getX()),
                new DoubleTag('', $position->getY()),
                new DoubleTag('', $position->getZ()),
            ]),
            "Rotation" => new ListTag('Rotation', [
                new FloatTag('', 0),
                new FloatTag('', 0)
            ]),
            "Skin" => new CompoundTag('Skin', [
                "Data" => new StringTag('Data',  ''),
                "Name" => new StringTag('Name', '')
            ]),
            "CustomName" => new StringTag("CustomName", self::centralize($name)),
        ]));
        $entity->setGame($arena);
        $entity->spawnToAll();
        return $entity;
    }

    public static function centralize(string $text, int $type = 0)
    {
        $string = "";
        $args = explode("\n", $text);

        if (count($args) <= 1) return $text;

        $bigger = [0, ""];
        $length = [];

        foreach ($args as $i => $line) {
            $diff = abs(strlen(str_replace("§", "", $line)) - strlen($line));
            $diff *= 2;
            $length[$i] = (strlen($line) - 1) - $diff;

            if ($length[$i] > $length[$bigger[0]]) {
                $bigger = [$i, $line];
            }
        }

        foreach ($args as $i => $line) {
            if ($line == $bigger[1]) {
                $string .=  "" . $bigger[1] . "\n";
            } else {
                if ($length[$bigger[0]] <= 1 or $length[$i] <= 1) {
                    $string .= $line . "\n";
                } else {
                    $x = (ceil($length[$bigger[0]] - $length[$i]) / 2);

                    if($bigger[0] > $i){
                        $x = $x - (count($args) - ($i + 1));
                    } else {
                        $x = $x + (count($args) - ($i + 1));
                    }
                    $string .= str_repeat(
                            " ",
                            $x
                        ) . $line . "\n";
                }
            }


        }

        return $string;
    }

}