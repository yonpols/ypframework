<?php
    /**
     * Abstract class to implement command line options on ypf cli command.
     */
    abstract class YPFCommand extends YPFObject {
        const RESULT_OK = 0;
        const RESULT_INVALID_PARAMETERS = 1;
        const RESULT_FILESYSTEM_ERROR = 2;
        const RESULT_FILES_ERROR = 3;
        const RESULT_COMMAND_NOT_FOUND = 4;
        const RESULT_BAD_ENVIRONMENT = 5;

        /**
         * Get a command instance for the command name passed
         * @param string $commandName
         * @return YPFCommand command instance
         */
        public static function get($commandName) {
            $className = YPFramework::camelize(str_replace('.', '_', $commandName).'_command');
            return new $className;
        }

        /**
         * Method that a command class must implement. This method
         * is called when the command is run. It must return YPFCommand::RESULT_OK
         * if finished succesfully.
         *
         * $parameters is an array with parameters passed to the command. This array
         * does not contain parameters passed to ypf
         */
        public abstract function run($parameters);

        /**
         * Method that a command class must implement. This method
         * is called when the help command is called. It's intended to
         * print some help about the command on the screen.
         *
         * $parameters is an array with parameters passed to the command. This array
         * does not contain parameters passed to ypf
         */
        public abstract function help($parameters);

        /**
         * Method that a command class must implement. This method
         * must return a breif description of the command that will be
         * printed when the command list is printed on scren.
         */
        public abstract function getDescription();

        /**
         * Require that the command is called in a directory with a YPI package
         * of type $types or other. This function can be called with more than
         * one parameter to require a package of one type or other.
         * @param string $types
         * @return boolean
         */
        protected function requirePackage($types) {
            $found = false;
            $types = func_get_args();
            $package_type = YPFramework::getPackage()->type;

            foreach($types as $type)
                if ($package_type == $type) {
                    $found = $type;
                    break;
                }

            if ($found === false)
                $this->exitNow (self::RESULT_BAD_ENVIRONMENT, sprintf ('can\'t find a valid %s on this directory', implode(' or ', $types)));

            return $found;
        }

        /**
         * Terminate command execution with an exit code and a text.
         * @param integer $code
         * @param string $text
         */
        protected function exitNow($code = YPFCommand::RESULT_OK, $text = '') {
            if ($text != '')
                echo $text."\n";
            exit($code);
        }

        /**
         * Proccess a simple template file replacing every ocurrence of {{key}} by the value
         * passed on $data['key']. Output can be written to a file or returned by the function.
         * @param string $fileName
         * @param array $data
         * @param string $destFileName
         * @return mixed returns true if written to a file, false if error or a string with the template processed
         */
        protected function getProcessedTemplate($fileName, $data, $destFileName = null) {
            $skeleton = file_get_contents($fileName);
            if ($skeleton === false)
                $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('can\'t read skeleton file %s', $fileName));

            foreach ($data as $key => $val)
                $skeleton = str_replace (sprintf('{{%s}}', $key), $val, $skeleton);

            if ($destFileName === null)
                return $skeleton;
            else
                if (file_put_contents($destFileName, $skeleton) === false)
                    $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('can\'t write file %s', $destFileName));

            return true;
        }

        /**
         * Read configurations of a YAML file
         * @param string $configFileName
         * @return mixed
         */
        protected function getConfig($configFileName) {
            $yaml = new sfYamlParser();
            try
            {
                $config = $yaml->parse(file_get_contents($configFileName));
                return $config;
            }
            catch (InvalidArgumentException $e) {
                $this->exitNow(self::RESULT_FILES_ERROR, sprintf('%s config file corrupted', $configFileName));
            }
        }

        /**
         * Write configurations to a YAML file
         * @param string $configFileName
         * @param mixed $config
         */
        protected function setConfig($configFileName, $config) {
            $yaml = new sfYamlDumper();
            if (!file_put_contents($configFileName, $yaml->dump($config, 4)))
                $this->exitNow(self::RESULT_FILES_ERROR, sprintf('can\'t write %s config file', $configFileName));
        }
    }
?>
