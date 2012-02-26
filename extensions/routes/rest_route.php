<?php
    class RestRoute extends YPFRoute {
        protected function __construct($name, $config, $baseUrl) {
            $new_config = clone $config;

            if (!isset($config->model))
                throw new BaseError (sprintf('%s rest route does not define model', $name));

            unset($new_config->type);
            unset($new_config->model);

            $model_params = YPFModelBase::getModelParams(YPFramework::camelize($config->model));

            if (!isset($config->root))
                $root = $model_params->tableName;
            else {
                $root = $config->root;
                unset($new_config->root);
            }

            if (!isset ($config->controller))
                $new_config->controller = $model_params->tableName;
            else
                $new_config->controller = $config->controller;

            if (isset($config->actions)) {
                $actions = $config->actions;
                unset($new_config->actions);
            } else
                $actions = array('index', 'show', 'edit', 'create', 'save', 'delete');

            if ($root[0] != '/')
                $root = '/'.$root;

            if (isset($config->collections)) {
                $collections = $config->collections;
                unset($new_config->collections);

                if (isset($config->prefix))
                    $prefix = YPFramework::getFileName ($config->prefix, substr($root, 1), ':'.YPFramework::underscore($config->model).'_id');
                else
                    $prefix = YPFramework::getFileName ($root, ':'.YPFramework::underscore($config->model).'_id');
            } else
                $collections = array();

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

            foreach ($collections as $name_col=>$collection) {
                $collection->prefix = $prefix;
                YPFRouter::register($name_col.'_'.$name, YPFRoute::get($name_col.'_'.$name, $collection, $baseUrl));
            }
        }
    }
?>
