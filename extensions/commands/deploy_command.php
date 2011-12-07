<?php
    class DeployCommand extends YPFCommand {
        public function getDescription() {
            return 'prepares the application for a packed deployment on a website';
        }

        public function help($parameters) {
            echo "ypf deploy url\n".
                 "Prepares a self contained copy of the application with YPF for a website deployment\n".
                 "   controller-name:  name of the controller\n".
                 "   -rest model-name: (optional) name of the model to create rest actions\n".
                 "                     and routes\n";

            return YPFCommand::RESULT_OK;
        }

        public function run($parameters) {
            if (empty($parameters)) {
                $this->help($parameters);
                $this->exitNow(YPFCommand::RESULT_INVALID_PARAMETERS);
            }

            $this->requirePackage('application', 'plugin');

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $parameters[0]))
                $this->exitNow (YPFCommand::RESULT_INVALID_PARAMETERS, sprintf('%s is not a valid controller name', $parameters[0]));

            $controllerClassName = YPFramework::camelize($parameters[0]).'Controller';
            $controllerFileName = YPFramework::getFileName(getcwd(), 'controllers', YPFramework::underscore(($parameters[0])).'_controller.php');

            if (file_exists($controllerFileName))
                $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('controller %s already exists', $controllerClassName));

            if (isset($parameters[1]) && $parameters[1] == '-rest') {
                if (count($parameters) != 3) {
                    $this->help($parameters);
                    $this->exitNow(YPFCommand::RESULT_INVALID_PARAMETERS);
                }

                $modelClassName = YPFramework::camelize($parameters[2]);
                $params = YPFModelBase::getModelParams($modelClassName);

                $skeletonFileName = realpath(YPFramework::getFileName(dirname(__FILE__), 'skeletons/controller_rest.skeleton'));

                $list_name = $params->tableName;
                $item_name = YPFramework::underscore($modelClassName);
                $controller = YPFramework::underscore($parameters[0]);
                $data = array(
                    'list_name' => $list_name,
                    'item_name' => $item_name,
                    'model_name' => $modelClassName
                );

                $controller_actions = $this->getProcessedTemplate($skeletonFileName, $data);

                //Set routes
                $configFileName = YPFramework::getFileName(getcwd(), 'config.yml');
                $config = $this->getConfig($configFileName);
                $config['routes'][$list_name] = array(
                    'match' => '/'.$list_name.'(.:format)',
                    'controller' => $controller,
                    'action' => 'index',
                    'method' => 'get'
                );
                $config['routes']['new_'.$item_name] = array(
                    'match' => '/'.$list_name.'/new(.:format)',
                    'controller' => $controller,
                    'action' => 'new_item',
                    'method' => 'get'
                );
                $config['routes'][$item_name] = array(
                    'match' => '/'.$list_name.'/:id(.:format)',
                    'controller' => $controller,
                    'action' => 'show',
                    'method' => 'get'
                );
                $config['routes']['save_'.$item_name] = array(
                    'match' => '/'.$list_name.'/:id/edit(.:format)',
                    'controller' => $controller,
                    'action' => 'edit',
                    'method' => 'get'
                );
                $config['routes']['save_'.$item_name] = array(
                    'match' => '/'.$list_name.'(/:id)(.:format)',
                    'controller' => $controller,
                    'action' => 'save',
                    'method' => 'post'
                );
                $config['routes']['delete_'.$item_name] = array(
                    'match' => '/'.$list_name.'/:id(.:format)',
                    'controller' => $controller,
                    'action' => 'delete',
                    'method' => 'delete'
                );
                $this->setConfig($configFileName, $config);
            } else
                $controller_actions = '';

            $skeletonFileName = realpath(YPFramework::getFileName(dirname(__FILE__), 'skeletons/controller.skeleton'));
            $data = array('controller_name' => $controllerClassName, 'controller_actions' => $controller_actions);
            $this->getProcessedTemplate($skeletonFileName, $data, $controllerFileName);

            printf("controller %s created successfully\n", $controllerClassName);
            return YPFCommand::RESULT_OK;
        }
    }
?>
