<?php


namespace murder\scheduler;


use murder\Main;
use murder\utils\arena\Arena;
use pocketmine\item\Item;
use pocketmine\level\sound\ExpPickupSound;
use pocketmine\level\sound\LaunchSound;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

class ShootCountdown extends \pocketmine\scheduler\PluginTask
{

    private $giveback;

    /** @var $arena Arena  */
    private $arena;

    /* @var Player */
    private $player;

    public function __construct(Main $owner, Player $player)
    {
        parent::__construct($owner);
        $this->arena = Main::getPlayerArena($player);
        $this->player = $player;
        $this->giveback = Main::$config['give-back-arrow-countdown'];
    }

    /**
     * @inheritDoc
     */
    public function onRun($currentTick)
    {
        if($this->arena->isAlive($this->player) and $this->arena->getStats() == $this->arena::STATS_RUNNING) {
            if ($this->giveback <= 0) {
                if ($this->arena->getStats() <= 3) {
                    $this->player->getLevel()->addSound(new LaunchSound($this->player), [$this->player]);
                    $this->player->getInventory()->addItem(Item::get(262));
                    $this->owner->getServer()->getScheduler()->cancelTask($this->getTaskId());
                }
            }
            $this->player->sendTip("§cRecebendo arco em: §f" . $this->giveback . "§r\n§r\n§r\n§r\n§r\n§r");
            $this->player->setXpLevel($this->giveback);
            $this->giveback--;
        } else {
            $this->owner->getServer()->getScheduler()->cancelTask($this->getTaskId());
        }
    }
}