<?php
    class TestCommand extends YPFCommand {
        public function getDescription() {
            return 'run all tests defined on every component';
        }

        public function help($parameters) {
            echo "ypf run.tests\n".
                 "Run tests on every component\n";

            return YPFCommand::RESULT_OK;
        }

        public function run($parameters) {
            if (!empty($parameters)) {
                $this->help($parameters);
                $this->exitNow(YPFCommand::RESULT_INVALID_PARAMETERS);
            }

            /*$this->requirePackage('application');

            $models = YPFramework::getComponentPath('*_test.php', 'support/tests', true, true);
            foreach ($models as $modelFileName) {
                $fileName = substr(basename($modelFileName), 0, -4);
                $modelClassName = YPFramework::camelize($fileName);
                $modelClassName::install();
                printf("%s synchronized\n", $modelClassName);
            }

            printf("database synchronized successfully\n");*/
            die("Not implemented\n");
            return YPFCommand::RESULT_OK;
        }
    }
?>
