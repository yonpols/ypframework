<?php
    class NewCommand extends YPFCommand {
        public function help($parameters) {
            echo "ypf new <app-name>\n".
                 "Creates a new directory with the name specified and populates it with a basic YPFramework application structure\n";

            return YPFCommand::RESULT_OK;
        }

        public function run($parameters) {
            if (count($parameters) != 1) {
                $this->help($parameters);
                $this->exitNow(YPFCommand::RESULT_INVALID_PARAMETERS);
            }

            $human_name = str_replace('_', ' ', YPFramework::underscore($parameters[0]));
            $human_name = strtoupper($human_name[0]).substr($human_name, 1);

            $name = YPFramework::normalize($parameters[0]);
            $name = YPFramework::getFileName(getcwd(), $name);

            if (file_exists($name))
                $this->exitNow (YPFCommand::RESULT_FILESYSTEM_ERROR, sprintf('%s already exists', basename ($name)));

            if (!@mkdir($name))
                $this->exitNow (YPFCommand::RESULT_FILESYSTEM_ERROR, sprintf('can\'t create directory %s', basename ($name)));

            system(sprintf('cp -r "%s/new_app/" "%s"', YPF_PATH, $name), $result);
            if ($result != 0)
                $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('can\'t copy files to %s', $name));

            chmod(YPFramework::getFileName($name, 'support/tmp'), 01775);
            chmod(YPFramework::getFileName($name, 'support/db'), 01775);
            chmod(YPFramework::getFileName($name, 'support/log'), 01775);

            $configFileName = YPFramework::getFileName($name, 'config.yml');
            $data = array(
                'application_name' => basename($name),
                'ypf_version' => YPF_VERSION,
                'application_human_name' => $human_name
            );
            $this->getProcessedTemplate($configFileName, $data, $configFileName);

            $indexFileName = YPFramework::getFileName($name, 'www/index.php');
            $this->getProcessedTemplate($indexFileName, array('ypf_path' => YPF_PATH), $indexFileName);

            printf("application %s created successfully\n", basename($name));
            return YPFCommand::RESULT_OK;
        }

        public function getDescription() {
            return 'creates a new ypf application with a basic skeleton';
        }
    }
?>
