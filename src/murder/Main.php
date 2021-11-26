<?php

namespace murder;

use murder\commands\MurderCommand;
use murder\scheduler\ArenaUpdater;
use murder\utils\arena\Arena;
use murder\utils\arena\ArenaManager;
use murder\utils\entitys\{
	HumanBody,
	FloatingMessage
};

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\entity\Entity;
use pocketmine\Player;

class Main extends PluginBase implements Listener {


    protected static $instance;

    /* @var \pocketmine\utils\Config */
    public static $arena = null;

    public static $config = [];
    public static $items = [];

    public static $arenas = [];
    public static $playing = [];

    public static function get() {
        return self::$instance;
    }

    public function onEnable()
    {

        self::$instance = $this;

        @mkdir($folder = $this->getDataFolder());

        $this->saveResource('config.yml', false);
        $this->saveResource('items.yml', false);

        self::$config = @yaml_parse_file($folder . 'config.yml');
        self::$items = @yaml_parse_file($folder . 'items.yml');

        self::$arena = new Config($folder . 'arenas.yml', 2);

        $this->registerEvents();
        $this->loadArenas();
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new ArenaUpdater($this), 20);

        $this->getServer()->getLogger()->info('§c[Murder] WORKING');
    }


    public function registerEvents(){
        Entity::registerEntity(HumanBody::class, true);
        Entity::registerEntity(FloatingMessage::class, true);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new ArenaManager($this), $this);

        $this->getServer()->getCommandMap()->register('murder', new MurderCommand($this));
    }


    public function loadArenas() {
        foreach (self::$arena->getAll() as $name => $config){
            self::$arenas[$name] = new Arena($this, $name, $config);
        }
    }

    public function updateArenas(){
        foreach (self::$arenas as $arena){
            $arena->updateArena();
        }
    }

    /**
     * @param Player $player
     * @return Arena|null
     */
    public static function getPlayerArena(Player $player){
        return self::$playing[$player->getName()] ?? null;
    }
}