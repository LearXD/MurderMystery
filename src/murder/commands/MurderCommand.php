<?php


namespace murder\commands;


use murder\Main;
use murder\utils\arena\Arena;
use murder\utils\Utils;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class MurderCommand extends \pocketmine\command\Command
{

    protected $owner;

    protected static $data = [];

    /**
     * @inheritDoc
     */
    public function __construct(Main $main)
    {
        $this->owner = $main;
        parent::__construct('murder', 'Use para ver os comandos do Murder!', '', ['mdr']);
    }

    public function execute(CommandSender $sender, $commandLabel, array $args)
    {
        if(empty($args[0]))
            return $sender->sendMessage(implode("§r\n§r", $sender->isOp() ? [
                '§c§lLista de ajuda do Murder §r(1/1):',
                '§cUse: §f/murder entrar [ Para entrar em uma partida ]',
                '§cUse: §f/murder sair [ Para sair da partida Murder ]',
                '§cUse: §f/murder setspawn [ Para adicionar um possivel spawn ]',
                '§cUse: §f/murder setlobby [ Para setar o lobby de espera ]',
                '§cUse: §f/murder setgenerator [ Para adiocinar um local de geraçao de moedas(ouro) ]',
                '§cUse: §f/murder criar (nome) [ Para criar uma partida Murder ]'
            ] : [
                '§c§lLista de ajuda do Murder §r(1/1):',
                '§cUse: §f/murder entrar [ Para entrar em uma partida ]',
                '§cUse: §f/murder sair [ Para sair da partida Murder ]'
            ]));

        if(!$sender instanceof Player)
            return $sender->sendMessage('§cEsse comando só pode ser utilizado no jogo!');

        $x = (int) $sender->getX();
        $y = (int) $sender->getY();
        $z = (int) $sender->getZ();

        switch (strtolower($args[0])){
            case 'entrar':
            case 'join':

                if(Main::getPlayerArena($sender)) {
                    return $sender->sendMessage('§cVoce ja esta jogando Murder!');
                } else {
                    if($arena = Utils::searchArena($sender)) {
                        $sender->sendMessage('§aConectando a §f'. $arena->getName() .'§a...');
                        Main::$playing[$sender->getName()] = $arena;
                        $arena->join($sender);
                    }

                }
                break;

            case 'sair':
            case 'leave':
                if($arena = Main::getPlayerArena($sender)){
                    $sender->sendMessage("§cSaindo...");
                    $arena->quit($sender);
                } else {
                    return $sender->sendMessage('§cVoce nao esta jogando em nenhuma arena de Murder!');
                }
                break;

            case 'setspawn':
                if(!$sender->isOp())
                    return $sender->sendMessage('§cVoce nao tem permissao para isso!');

                self::$data[$sender->getName()]['spawns'][] = array($x, $y, $z);
                $sender->sendMessage('§aSpawn adicionado em X:§f ' . $x . ' §aY:§f ' . $y . ' §aZ:§f ' . $z . ' §a! Total de spawns: §f'. count(self::$data[$sender->getName()]['spawns']));
                break;
            case 'setlobby':
                if(!$sender->isOp())
                    return $sender->sendMessage('§cVoce nao tem permissao para isso!');

                self::$data[$sender->getName()]['lobby'] = array($x, $y, $z);
                $sender->sendMessage('§aLobby setado em X:§f ' . $x . ' §aY: §f' . $y . ' §aZ:§f ' . $z . ' §a!');
                break;

            case 'setgenerator':
                if(!$sender->isOp())
                    return $sender->sendMessage('§cVoce nao tem permissao para isso!');

                self::$data[$sender->getName()]['generators'][] = array($x, $y, $z);
                $sender->sendMessage('§aGerador de Ouro adicionado em X:§f ' . $x . ' §aY:§f ' . $y . ' §aZ:§f ' . $z . ' §a! Total de geradores: §f'. count(self::$data[$sender->getName()]['generators']));
                break;

            case 'criar':
            case 'create':

                if(!$sender->isOp())
                    return $sender->sendMessage('§cVoce nao tem permissao para isso!');

                if(empty($args[1]))
                    return $sender->sendMessage('§cDefina o nome da Arena que deseja criar!');

                if(empty(self::$data[$sender->getName()]['lobby']))
                    return $sender->sendMessage('§cDefina o lobby do Murder!');

                if(empty(self::$data[$sender->getName()]['generators']))
                    return $sender->sendMessage('§cDefina os geradores do Murder!');

                if(empty(self::$data[$sender->getName()]['spawns']))
                    return $sender->sendMessage('§cDefina os spawns do Murder!');

                Main::$arena->set($args[1], [
                    'lobby' => self::$data[$sender->getName()]['lobby'],
                    'spawns' => self::$data[$sender->getName()]['spawns'],
                    'generators' => self::$data[$sender->getName()]['generators'],
                    'level' => $sender->getLevel()->getName()
                ]);

                Main::$arena->save();
                unset(self::$data[$sender->getName()]);

                Main::$arenas[$args[1]] = new Arena($this->owner, $args[1], Main::$arena->getAll()[$args[1]]);

                $sender->sendMessage('§aA arena §f' . $args[1] . '§a foi criada com sucesso!');
                break;
            default: $sender->sendMessage("§cUse: §f/" . $commandLabel . " help");
        }

        return true;
    }

}