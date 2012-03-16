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

            system(sprintf('cp -r "%s/new_app" "%s"', YPF_PATH, $name), $result);
            if ($result != 0)
                $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('can\'t copy files to %s', $name));

            $configFileName = YPFramework::getFileName($name, 'private/config.yml');
            $data = array(
                'application_name' => basename($name),
                'ypf_version' => YPFramework::getVersion(),
                'application_human_name' => $human_name
            );
            $this->getProcessedTemplate($configFileName, $data, $configFileName);

            if (!symlink(YPF_PATH, YPFramework::getFileName($name, 'private/ypf')))
                $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('can\'t create symlink to YPF on %s/private/ypf', $name));

            if (!symlink(YPFramework::getFileName($name, 'private/support/install.php'), YPFramework::getFileName($name, 'index.php')))
                $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('can\'t create symlink to installation', $name));

            printf("application %s created successfully\n", basename($name));
            return YPFCommand::RESULT_OK;
        }

        public function getDescription() {
            return 'creates a new ypf application with a basic skeleton';
        }
    }
?>
