<?php
    class TestCommand extends YPFCommand {
        const RESULT_TEST_ERROR = 0x1000;

        private $testFiles = array();

        public function getDescription() {
            return 'run all tests defined on every component';
        }

        public function help($parameters) {
            echo "ypf test [test_case1 [test_case2 ...]]\n".
                 "Run tests on every component if no parameter present or run the test cases passed by\n";

            return YPFCommand::RESULT_OK;
        }

        public function run($parameters) {
            $this->requirePackage('application');

            require 'simpletest/arguments.php';
            require 'simpletest/authentication.php';
            require 'simpletest/browser.php';
            require 'simpletest/collector.php';
            require 'simpletest/detached.php';
            require 'simpletest/mock_objects.php';
            require 'simpletest/recorder.php';
            require 'simpletest/remote.php';
            require 'simpletest/unit_tester.php';
            require 'simpletest/web_tester.php';

            if (empty($parameters))
                $this->searchFiles();
            else
                foreach($parameters as $testCase) {
                    $file = YPFramework::getComponentPath($testCase.'_test.php', 'support/tests/', true, false);
                    if (!$file)
                        $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('couldn\'t find %s test case', $testCase));
                    elseif (is_array($file))
                        $this->testFiles = array_merge ($this->testFiles, $file);
                    else
                        $this->testFiles[] = $file;
                }


            $result = true;
            foreach($this->testFiles as $file) {
                require $file;
                $className = basename($file);
                $className = YPFramework::camelize(substr($className, 0, -4));


                $test = new $className;
                $result = $test->run(new TextReporter) && $result;
            }

            if ($result)
                return YPFCommand::RESULT_OK;
            else
                return self::RESULT_TEST_ERROR;
        }

        private function searchFiles($parent = null) {
            $files = \YPFramework::getComponentPath('*', 'support/tests', true, true);

            if (!$files)
                return;

            foreach ($files as $file) {
                if (is_dir($file)) {
                    $basename = basename($file);
                    $this->searchFiles(\YPFramework::getFileName($parent, $basename));
                } elseif (substr($file, -9) == '_test.php') {
                    $this->testFiles[] = $file;
                }
            }
        }
    }
?>
