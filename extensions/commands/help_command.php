<?php
    class HelpCommand extends YPFCommand {
        public function help($parameters) {
            echo "ypf help [command-name]\n".
                 "Shows help about a command. If command-name is ommited, shows a list of all available commands\n";

            return YPFCommand::RESULT_OK;
        }

        public function run($parameters) {
            if (empty ($parameters)) {
                echo "YPFramework v".YPFramework::getVersion().": ypf command-name [options]\n\n";
                echo "List of available commands:\n";

                $this->showAllCommands();
            }
            else {
                $commandName = array_shift($parameters);
                $command = YPFCommand::get($commandName);

                if ($command === false)
                    $this->exitNow (YPFCommand::RESULT_COMMAND_NOT_FOUND, sprintf('command %s not found', $commandName));

                return $command->help($parameters);
            }
        }

        public function getDescription() {
            return 'show help about a command or lists all available commands';
        }

        private function showAllCommands($parent = '') {
            $commands = YPFramework::getComponentPath('*_command.php', YPFramework::getFileName('extensions/commands', $parent), true, true);

            if ($commands) {
                uasort($commands, function($a, $b) {
                    $a = basename($a);
                    $b = basename($b);

                    $canta = preg_match_all('/_/', $a, $t);
                    $cantb = preg_match_all('/_/', $b, $t);

                    if ($canta < $cantb)
                        return -1;
                    elseif ($cantb < $canta)
                        return 1;
                    else
                        return strcasecmp($a, $b);
                });

                foreach($commands as $commandFile) {
                    $commandName = basename($commandFile);
                    $commandClass = YPFramework::camelize(str_replace('.', '_', substr($commandName, 0, -4)));
                    $commandName = substr($commandName, 0, -12);

                    require_once $commandFile;
                    $command = new $commandClass;
                    printf("    %-40s%s\n", str_replace('_', '.',
                        YPFramework::getFileName($parent, $commandName)), $command->getDescription());
                }
            }
        }
    }
?>
