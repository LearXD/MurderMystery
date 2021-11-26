<?php


namespace murder\scheduler;


use murder\Main;

class ArenaUpdater extends \pocketmine\scheduler\PluginTask
{

    public function __construct(Main $owner)
    {
        parent::__construct($owner);
    }

    /**
     * @inheritDoc
     */
    public function onRun($currentTick)
    {
        $this->owner->updateArenas();
    }
}