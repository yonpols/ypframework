<?php
    class RestRoute extends YPFRoute {
        protected function __construct($name, $config, $baseUrl) {
            $new_config = new YPFObject;

            if (!isset($config->model))
                throw new BaseError (sprintf('%s rest route does not define model', $name));

            $model_params = YPFModelBase::getModelParams(YPFramework::camelize($config->model));

            if (!isset($config->root))
                $root = $model_params->tableName;
            else
                $root = $config->root;

            if (!isset ($config->controller))
                $new_config->controller = $model_params->tableName;
            else
                $new_config->controller = $config->controller;

            if (isset($config->actions))
                $actions = $config->actions;
            else
                $actions = array('index', 'show', 'edit', 'create', 'save', 'delete');

            if ($root[0] != '/')
                $root = '/'.$root;

            if (array_search('index', $actions) !== false) {
                $cloned_config = clone $new_config;
                $cloned_config->match = $root.'(.:format)';
                $cloned_config->action = 'index';
                YPFRouter::register($name, YPFRoute::get($name, $cloned_config, $baseUrl));
            }

            if (array_search('show', $actions) !== false) {
                $cloned_config = clone $new_config;
                $cloned_config->match = YPFramework::getFileName($root, ':id(.:format)');
                $cloned_config->action = 'show';
                YPFRouter::register('show_'.$name, YPFRoute::get('show_'.$name, $cloned_config, $baseUrl));
            }

            if (array_search('edit', $actions) !== false) {
                $cloned_config = clone $new_config;
                $cloned_config->match = YPFramework::getFileName($root, ':id/edit');
                $cloned_config->action = 'edit';
                YPFRouter::register('edit_'.$name, YPFRoute::get('edit_'.$name, $cloned_config, $baseUrl));
            }

            if (array_search('create', $actions) !== false) {
                $cloned_config = clone $new_config;
                $cloned_config->match = YPFramework::getFileName($root, 'new(.:format)');
                $cloned_config->action = 'create';
                YPFRouter::register('create_'.$name, YPFRoute::get('create_'.$name, $cloned_config, $baseUrl));
            }

            if (array_search('save', $actions) !== false) {
                $cloned_config = clone $new_config;
                $cloned_config->match = $root.'(/:id)(.:format)';
                $cloned_config->action = 'save';
                $cloned_config->method = 'post';
                YPFRouter::register('save_'.$name, YPFRoute::get('save_'.$name, $cloned_config, $baseUrl));
            }

            if (array_search('delete', $actions) !== false) {
                $cloned_config = clone $new_config;
                $cloned_config->match = YPFramework::getFileName($root, ':id(.:format)');
                $cloned_config->action = 'delete';
                $cloned_config->method = 'delete';
                YPFRouter::register('delete_'.$name, YPFRoute::get('delete_'.$name, $cloned_config, $baseUrl));
            }
        }
    }
?>
