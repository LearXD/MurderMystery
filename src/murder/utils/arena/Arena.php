<?php


namespace murder\utils\arena;


use murder\Main;
use murder\utils\Utils;
use pocketmine\entity\Effect;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;

use pocketmine\Server;

class Arena
{

    const STATS_WAITING = 0;
    const STATS_STARTING = 1;
    const STATS_CHOOSING = 2;
    const STATS_RUNNING = 3;
    const STATS_FINISHING = 4;

    const MESSAGE_TEXT = 0;
    const MESSAGE_TIP = 1;
    const MESSAGE_POPUP = 2;


    /*+ @var Main */
    protected $owner;

    /** @var array */
    public $players = [];
    public $spectators = [];

    /** @var array */
    protected $data = [];

    /** @var array */
    protected $generators = [];

    /** @var string */
    public $name = "";

    /** @var Level */
    public $level = null;

    /** @var int */
    public $stats = self::STATS_WAITING;

    /** @var int */
    protected $time = [
        self::STATS_WAITING => 0,
        self::STATS_STARTING => 10,
        self::STATS_CHOOSING => 10,
        self::STATS_RUNNING => 60 * 4,
        self::STATS_FINISHING => 5
    ];

    public $murder, $detective;

    public $spawnedOres = [];
    public $spawnedEntities = [];


    public function __construct(Main $main, string $name, array $data)
    {
        $this->owner = $main;

        $this->name = $name;
        $this->data = $data;
        $this->init();
    }

    public function init()
    {
        $this->owner->getServer()->loadLevel($this->data['level']);
        $this->level = $this->owner->getServer()->getLevelByName($this->data['level']);

        foreach ($this->data['generators'] as $pos) {
            $this->generators[] = array($pos[0], $pos[1], $pos[2]);
        }
    }

    public function restartArena()
    {
        if (count($this->players) > 0) {
            foreach ($this->players as $name) {
                $player = Server::getInstance()->getPlayerExact($name);
                if ($player) {
                    $player->getInventory()->clearAll();
                    $player->setGamemode(Player::SURVIVAL);
                    $player->setNameTagVisible(true);
                    $player->teleport($this->owner->getServer()->getDefaultLevel()->getSafeSpawn());
                }
                unset(Main::$playing[$name]);
            }
        }

        if (count($this->spectators) > 0) {
            foreach ($this->spectators as $name) {
                $player = Server::getInstance()->getPlayerExact($name);
                if ($player) {
                    $player->getInventory()->clearAll();
                    $player->setGamemode(Player::SURVIVAL);
                    $player->setNameTagVisible(true);
                    $player->teleport($this->owner->getServer()->getDefaultLevel()->getSafeSpawn());
                }
                unset(Main::$playing[$name]);
            }
        }


        foreach (array_merge($this->spawnedEntities, $this->spawnedOres) as $entity) {
            if (!$entity->closed) {
                $entity->close();
            }
        }

        $this->spawnedEntities = [];
        $this->spawnedOres = [];

        $this->stats = self::STATS_WAITING;

        $this->time = [
            self::STATS_WAITING => 0,
            self::STATS_STARTING => 10,
            self::STATS_CHOOSING => 10,
            self::STATS_RUNNING => 60 * 4,
            self::STATS_FINISHING => 5
        ];

        $this->murder = null;
        $this->detective = null;

        $this->spectators = [];
        $this->players = [];

        return true;
    }

    /**
     * @return array
     */
    public
    function getData(): array
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public
    function getStats()
    {
        return $this->stats;
    }

    public
    function getName()
    {
        return $this->name;
    }

    public
    function canJoin()
    {
        return $this->getStats() < self::STATS_CHOOSING and
            count($this->players) <= Main::$config['max-players-per-match'];
    }

    public function getInArena(){
        return array_merge($this->players, $this->spectators);
    }

    public
    function isMurder(Player $player)
    {
        if ($this->murder == $player->getName()) {
            return true;
        }
        return false;
    }

    public
    function isDetective(Player $player)
    {
        if ($this->detective == $player->getName()) {
            return true;
        }
        return false;
    }

    public
    function isInnocent(Player $player)
    {
        if ($this->isMurder($player)) {
            return false;
        }
        if ($this->isDetective($player)) {
            return false;
        }
        return true;
    }

    public
    function isAlive(Player $player)
    {
        if (
            in_array($player->getName(), $this->players) and
            !in_array($player->getName(), $this->spectators)
        ) {
            return true;
        }
        return false;
    }

    public
    function getRandomSpawn()
    {
        $pos = $this->data['spawns'][array_rand($this->data['spawns'])];
        return new Position($pos[0], $pos[1], $pos[2], $this->level);
    }

    public
    function killPlayer(Player $victim)
    {

        if ($this->getStats() != self::STATS_RUNNING)
            return;

        if ($this->isDetective($victim)) {

            $item = Utils::dropItem($victim, Item::get(261));
            $floating = Utils::spawnFloatingMessage($this, $victim, "§l§aArco do Detetive!§r\n§7(Pegue e seja o novo detetive da partida)");
            $floating->setAlong($item);

            $this->spawnedEntities[] = $item;
            $this->spawnedEntities[] = $floating;

            foreach (array_merge($this->players, $this->spectators) as $name) {
                $this->broadcast($name, '§c* O detetive morreu! Seu arco foi dropado no local da morte!');
            }
        }

        $this->spectators[] = $victim->getName();
        unset($this->players[array_search($victim->getName(), $this->players)]);

        $body = Utils::createBody($this, $victim, $victim);
        $this->spawnedEntities[] = $body;

        $victim->setGamemode(Player::SPECTATOR);
        $victim->getInventory()->clearAll();

        $victim->addEffect(Effect::getEffect(Effect::BLINDNESS)->setAmplifier(2)->setDuration(20 * 5)->setVisible(false));
        $victim->sendMessage('§c* Você morreu, agora seu trabalho e espectar!');

    }

    public
    function join(Player $player)
    {
        if (in_array($player->getName(), $this->players) or in_array($player->getName(), $this->spectators))
            return $player->sendMessage('§cVocê ja esta nessa arena!');

        $this->players[] = $player->getName();

        $player->teleport(new Position($this->data['lobby'][0], $this->data['lobby'][1], $this->data['lobby'][2], $this->level));

        $player->setGamemode(Player::ADVENTURE);
        $player->getInventory()->clearAll();
        $player->getFloatingInventory()->clearAll();

        $player->sendMessage('§aVocê entrou na arena §' . $this->name . '§a com sucesso!');

        foreach ($this->players as $name) {
            $this->broadcast($name, '§aO jogador §f' . $player->getName() . '§a entou na partida §f(' . count($this->players) . '/' . Main::$config['max-players-per-match'] . ')');
        }

        return true;
    }

    public
    function quit(Player $player)
    {
        if (!in_array($player->getName(), $this->players) and !in_array($player->getName(), $this->spectators))
            return $player->sendMessage('§cVocê nao esta jogando na arena §f' . $this->name . '§c!');

        if (in_array($player->getName(), $this->players)) {
            unset($this->players[array_search($player->getName(), $this->players)]);
        } elseif (in_array($player->getName(), $this->spectators)) {
            unset($this->spectators[array_search($player->getName(), $this->spectators)]);
        }

        $player->setNameTagVisible(true);
        unset(Main::$playing[$player->getName()]);

        $player->sendMessage('§aVocê saiu da arena §f' . $this->name . '§a com sucesso!');
        $player->teleport($this->owner->getServer()->getDefaultLevel()->getSafeSpawn());
        $player->setGamemode(Player::SURVIVAL);

        foreach (array_merge($this->players, $this->spectators) as $name) {
            $this->broadcast($name, '§cO jogador §f' . $player->getName() . '§c saiu na partida §f(' . count($this->players) . '/' . Main::$config['max-players-per-match'] . ')');
        }
    }

    public
    function generatorsUpdate()
    {
        if (mt_rand(0, 100) <= 10) {
            $rand = array_rand($this->generators);
            $position = new Position((int)$this->generators[$rand][0], (int)$this->generators[$rand][1], (int)$this->generators[$rand][2], $this->level);
            if (empty($this->spawnedOres[$position->x . ':' . $position->y . ':' . $position->z])) {
                $this->spawnedOres[$position->x . ':' . $position->y . ':' . $position->z] = Utils::dropItem($position, Item::get(266));
            }
        }
    }

    public function updateArena()
    {

        $time = $this->time[$this->getStats()];

        switch ($this->getStats()) {
            case self::STATS_WAITING:
                if (count($this->players) >= Main::$config['min-players-per-match']) {
                    $this->stats = self::STATS_STARTING;

                } elseif (count($this->players) > 0) {
                    foreach ($this->players as $player) {
                        $this->broadcast($player, '§aFaltam mais §f' . (Main::$config['min-players-per-match'] - count($this->players)) . '§a jogadores para começar!', self::MESSAGE_POPUP);
                    }
                }
                break;

            case self::STATS_STARTING:
                if (count($this->players) >= Main::$config['min-players-per-match']) {
                    if ($time <= 0) {
                        $this->stats = self::STATS_CHOOSING;
                    } else {
                        foreach ($this->players as $player) {
                            $this->owner->getServer()->getPlayer($player)->sendPopup('§aPartida começa em §f' . $time . '§a segundos!');
                        }
                        $this->time[$this->getStats()]--;
                    }
                } else {
                    $this->time[$this->getStats()] = 15;
                    $this->stats = self::STATS_WAITING;
                }
                break;
            case self::STATS_CHOOSING:
                if (count($this->players) >= Main::$config['min-players-per-match']) {
                    if ($time <= 0) {

                        $murder = $this->owner->getServer()->getPlayerExact($this->murder);
                        $detective = $this->owner->getServer()->getPlayerExact($this->detective);

                        $slot = 0;
                        foreach (Main::$items['murder']['items'] as $type => $data) {
                            list($id, $damage, $count) = explode(":", $data['item']);
                            $item = Item::get($id, $damage, $count);

                            if (isset($data['customName'])) {
                                $item->setCustomName($data['customName']);
                            }

                            if (isset($data['enchantments'])) {
                                foreach ($data['enchantments'] as $enchantment => $level) {
                                    $item->addEnchantment(Enchantment::getEnchantment($enchantment)->setLevel($level));
                                }
                            }
                            $murder->getInventory()->setItem(++$slot, $item);
                        }
                        $slot = 0;

                        foreach (Main::$items['detective']['items'] as $type => $data) {

                            list($id, $damage, $count) = explode(":", $data['item']);
                            $item = Item::get($id, $damage, $count);

                            if (isset($data['customName'])) {
                                $item->setCustomName($data['customName']);
                            }

                            if (isset($data['enchantments'])) {
                                foreach ($data['enchantments'] as $enchantment => $level) {
                                    $item->addEnchantment(Enchantment::getEnchantment($enchantment)->setLevel($level));
                                }
                            }

                            $detective->getInventory()->setItem(++$slot, $item);
                        }

                        $this->stats = self::STATS_RUNNING;

                    } else if ($time == 5) {

                        $offsets = array_rand($this->players, 2);

                        $this->murder = $this->players[$offsets[0]];
                        $this->detective = $this->players[$offsets[1]];

                        foreach ($this->players as $player) {
                            $player = Server::getInstance()->getPlayerExact($player);
                            if ($this->players[$offsets[0]] == $player->getName()) {
                                $player->sendTip(" §l§cAssasino§r\n§7(Mate todos)\n§r\n§r\n§r");
                                $player->sendMessage("§a* Você sera o §c§lAssasino§r§a!\n§aObjetivo: §fMatar todos os jogadores, tomar cuidado com o detetive, tudo isso antes o tempo acabar.");
                            } elseif ($this->players[$offsets[1]] == $player->getName()) {
                                $player->sendTip("  §l§dDetetive§r\n§7(Elimine o Assasino!)\n§r\n§r\n§r");
                                $player->sendMessage("§a* Você sera o §c§dDetetive§r§a!\n§aObjetivo: §fEliminar o Assasino antes que ele mate todos, ou manter a protecao dos inocentes ate o final da partida.");
                            } else {
                                $player->sendTip(" §l§aInocente§r\n§7(Tente sobreviver!)\n§r\n§r\n§r");
                                $player->sendMessage("§a* Você sera o §c§aInocente§r§a!\n§aObjetivo: §fEm geral, ficar vivo, mas você pode coletar ouros e conseguir o arco para ajudar o detetive, alem de poder pegar o arco do detetive quando ele morrer.");
                            }
                        }

                        foreach ($this->players as $name) {
                            $player = Server::getInstance()->getPlayerExact($name);
                            $player->teleport($this->getRandomSpawn());
                            $player->setNameTagVisible(false);
                        }

                    } else if ($time >= 6) {
                        $professions = array(
                            '§cAssasino',
                            '§bDetetive',
                            '§aInocente',
                        );


                        foreach ($this->players as $player) {
                            for ($i = 0; $i <= 10; $i++) {
                                $this->owner->getServer()->getPlayer($player)->sendPopup("§aEscolhendo jogadores...\n     " . $professions[array_rand($professions)] . "\n§r\n§r\n§r\n§r\n§r");
                            }
                        }

                    } else {

                        foreach ($this->players as $name) {
                            $this->broadcast($name, "§aO Assasino e o Detetive irao receber seus itens em §f" . $time . "§a segundos!", self::MESSAGE_POPUP);
                        }

                    }
                    $this->time[$this->getStats()]--;
                } else {
                    $this->time[$this->getStats()] = 15;
                    $this->stats = self::STATS_WAITING;
                }
                break;

            case self::STATS_RUNNING:

                if ($time <= 0) {
                    foreach (array_merge($this->players, $this->spectators) as $name) {
                        $this->broadcast($name, "§r\n§c* A partida acabou! Os inocentes venceram por nao serem eliminados...\n§r");
                    }
                    $this->stats = self::STATS_FINISHING;
                    return true;
                }

                if (in_array($this->murder, $this->players)) {
                    if (count($this->players) <= 1) {
                        foreach ($this->spectators as $dead) {
                            $dead = Server::getInstance()->getPlayerExact($dead);
                            if ($dead) {
                                $this->broadcast($dead, "§r\n§c* O Assasino ganhou a partida!\n§r");
                                //$dead->setGamemode(Player::ADVENTURE);
                            }
                        }
                        $murder = Server::getInstance()->getPlayerExact($this->murder);
                        $murder->sendMessage("§r\n§a* Parabens! Você matou todos os jogadores sem ser descoberto!\n§r");
                        $this->stats = self::STATS_FINISHING;

                    } else {
                        foreach (array_merge($this->players, $this->spectators) as $name) {
                            $this->broadcast($name, "§aMurder: §f" . ($this->murder == null ? '§cMorto§f' : '§cVivo§f') . " §aDetetive: §f" . ($this->detective == null ? "§cMorto§f" : "§cVivo§f") . "\n§aPartida acaba em §f" . date('i:s', $time) . "§a segundos!", self::MESSAGE_POPUP);
                        }
                        $this->generatorsUpdate();

                        $this->time[$this->getStats()]--;
                    }
                } else {
                    foreach (array_merge($this->players, $this->spectators) as $name) {
                        $this->broadcast($name, "§r\n§a* Os inocentes venceram a partida!\n§r");
                    }
                    $this->stats = self::STATS_FINISHING;
                }
                break;
            case self::STATS_FINISHING:
                if ($time <= 0) return $this->restartArena();

                foreach (array_merge($this->players, $this->spectators) as $name) {
                    $this->broadcast($name, '§cA partida ira rezetar em §f' . $time . '§c segundos!', self::MESSAGE_POPUP);
                }

                $this->time[$this->getStats()]--;
                break;
        }
    }

    public
    function broadcast($player, string $message, int $type = self::MESSAGE_TEXT)
    {
        if (!$player instanceof Player) {
            if (!($player = Server::getInstance()->getPlayerExact($player))) {
                return false;
            }
        }

        switch ($type) {
            case self::MESSAGE_TEXT:
                $player->sendMessage($message);
                return true;
            case self::MESSAGE_TIP:
                $player->sendTip($message);
                return true;
            case self::MESSAGE_POPUP:
                $player->sendPopup($message);
                return true;
        }
        return false;
    }

}