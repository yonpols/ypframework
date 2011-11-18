<?php
    class InstallDbCommand extends YPFCommand {
        public function getDescription() {
            return 'synchronizes database schema with current models schemas';
        }

        public function help($parameters) {
            echo "ypf sync.db.schema\n".
                 "Synchronizes database schema with current models schemas\n";

            return YPFCommand::RESULT_OK;
        }

        public function run($parameters) {
            if (!empty($parameters)) {
                $this->help($parameters);
                $this->exitNow(YPFCommand::RESULT_INVALID_PARAMETERS);
            }

            $this->requirePackage('application');

            $models = YPFramework::getComponentPath('*.php', 'models', true, true);
            foreach ($models as $modelFileName) {
                $fileName = substr(basename($modelFileName), 0, -4);
                $modelClassName = YPFramework::camelize($fileName);
                $modelClassName::install();
                printf("%s synchronized\n", $modelClassName);
            }

            printf("database synchronized successfully\n");
            return YPFCommand::RESULT_OK;
        }
    }
?>
