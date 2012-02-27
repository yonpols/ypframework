<?php
    class RoutesCommand extends YPFCommand {
        const RESULT_TEST_ERROR = 0x1000;

        private $testFiles = array();

        public function getDescription() {
            return 'list all registered routes';
        }

        public function help($parameters) {
            echo "ypf routes\n".
                 "List all routes\n";

            return YPFCommand::RESULT_OK;
        }

        public function run($parameters) {
            $this->requirePackage('application');

            $routes = YPFramework::getSetting('routes');
            foreach($routes as $name => $data)
                YPFRoute::get($name, $data, '');

            $routes = YPFRouter::get();

            foreach ($routes->all() as $route)
                printf("%s\n", $route->getInfo());

            return YPFCommand::RESULT_OK;
        }
    }
?>
