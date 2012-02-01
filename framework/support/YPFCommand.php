<?php
    abstract class YPFCommand extends YPFObject {
        const RESULT_OK = 0;
        const RESULT_INVALID_PARAMETERS = 1;
        const RESULT_FILESYSTEM_ERROR = 2;
        const RESULT_FILES_ERROR = 3;
        const RESULT_COMMAND_NOT_FOUND = 4;
        const RESULT_BAD_ENVIRONMENT = 5;

        public static function get($commandName) {
            $className = YPFramework::camelize(str_replace('.', '_', $commandName).'_command');
            $classFileName = YPFramework::getClassPath($className, '', 'extensions/commands');

            if ($classFileName === false)
                return false;

            require_once $classFileName;
            return new $className;
        }

        public abstract function run($parameters);
        public abstract function help($parameters);
        public abstract function getDescription();

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

        protected function exitNow($code = YPFCommand::RESULT_OK, $text = '') {
            if ($text != '')
                echo $text."\n";
            exit($code);
        }

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

        protected function setConfig($configFileName, $config) {
            $yaml = new sfYamlDumper();
            if (!file_put_contents($configFileName, $yaml->dump($config, 4)))
                $this->exitNow(self::RESULT_FILES_ERROR, sprintf('can\'t write %s config file', $configFileName));
        }
    }
?>
