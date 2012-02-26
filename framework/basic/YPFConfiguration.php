<?php
    /**
     * Configuration settings manager. It supports YAML or PHP files. If a PHP
     * file is passed it must return an array with the settings.
     *
     * <h2>Examples</h2>
     *
     * <h3>YAML</h3>
     * <code>
     * package:
     *   name: 'Name of the package'
     *   type: application
     *   version: '1.0.0'
     *
     * $include: 'environments.yml'
     * </code>
     *
     * <h3>PHP</h3>
     * <code>
     * <?php
     *   return array(
     *     'package' => array(
     *       'name' => 'Name of the package',
     *       'type' => 'application'
     *       'version' => '1.0.0'
     *     ),
     *     include('environments.php')
     *   );
     * ?>
     * </code>
     *
     * <h2>Special Features</h2>
     *
     * <h3>Inheritance</h3>
     * In order to avoid repeating settings you can inherit settings from a previous
     * declared section at the same level of the new one and overwrite only those settings
     * you must change. To do this you must specify the new sections name followed by a colon
     * and the section you want to inherit
     *
     * <code>
     * environments:
     *   development:
     *     databases:
     *       main:
     *         type: 'MySQL'
     *         host: '127.0.0.1'
     *         user: 'test_app'
     *         name: 'test_app'
     *         pass: '42534534fd
     *
     *   production:development:
     *     databases:
     *       main:
     *         host: 'db.test_app.com'
     * </code>
     * As you can imagine all settings of production mode will be the same as development except
     * for the host where the database is located.
     *
     * <h3>File inclusion</h3>
     * If you want to mantain your configuration files small and easy to read and modify you can
     * split settings in several files and include them as you need. You can see an example of this
     * feature at the first code of this page. In YAML files you must use the section name keyword
     * '$include' with a path to the file, relative to the path of the configuration file where the
     * keyword is present. In PHP files you can use the include php keyword.
     *
     */
    class YPFConfiguration {
        protected $root;
        protected $changed = false;
        protected $base_path = null;

        /**
         * Create and load a new configuration file. It can be a text file in YAML format or
         * an php file that returns an array.
         *
         * @param string $configFileName Path to he configuration file to load
         * @param mixed $root Optional root object instance in which configuration settings will be loaded. If none is present an empty object will be used.
         */
        public function __construct($configFileName, $root=null) {
            if ($root === null)
                $this->root = new YPFObject();
            else
                $this->root = $root;

            $this->base_path = dirname($configFileName);
            $config = $this->loadConfigurationFile ($configFileName);

            foreach ($config as $name=>$value)
                $this->mergeConfig ($this->root, $name, $this->objetize($value));
        }

        public function __isset($name) {
            return isset ($this->root->{$name});
        }

        public function __get($name) {
            if ($this->changed) {
                $this->replace($this->root);
                $this->changed = false;

            }

            if (isset ($this->root->{$name}))
                return $this->root->{$name};
        }

        public function __set($name, $value) {
            $this->root->{$name} = $value;
            $this->changed = true;
        }

        /**
         * Append configuration settings to this configuration object.
         * @param mixed $config It can be either a string with the path to the file or an instance of YPFConfiguration.
         */
        public function append($config) {
            if (is_string($config) && is_readable($config))
                $config = new YPFConfiguration ($config);

            if ($config instanceof YPFConfiguration) {
                foreach ($config->getRoot() as $name=>$value)
                    $this->mergeConfig ($this->root, $name, $this->objetize($value));
            } else
                throw new BaseError ('Bad configuration data to append');

            $this->changed = true;
        }

        /**
         * Returns the configuration settings root.
         * @return object
         */
        public function getRoot() {
            if ($this->changed)
            {
                $this->replace($this->root);
                $this->changed = false;
            }

            return $this->root;
        }

        private function loadConfigurationFile($configFileName) {
            if (!file_exists($configFileName))
                throw new ErrorComponentNotFound('configuration_file', $configFileName);

            if (strtolower(substr($configFileName, -4)) == '.php')
                $config = require $configFileName;
            else {
                $yaml = new sfYamlParser();

                try {
                    $config = $yaml->parse(file_get_contents($configFileName));
                }
                catch (InvalidArgumentException $e) {
                    $config = null;
                }
            }

            if (!$config)
                throw new ErrorCorruptFile($configFileName, $e->getMessage());

            return $config;
        }

        private function mergeConfig($parent, $name, $config, $path = '') {
            if (!isset($parent->{$name}))
                $parent->{$name} = $config;
            elseif ($name != 'package')
            {
                if (is_object($config) && is_object($parent->{$name}))
                {
                    foreach($config as $key => $value)
                        $this->mergeConfig ($parent->{$name}, $key, $value, $path.$name.'.');
                }
            }

            $this->changed = true;
        }

        private function objetize($config) {
            if (is_array($config)) {
                $key = array_keys($config);

                if (empty($key) or (is_numeric($key[0]))) {
                    $object = array();
                    foreach ($config as $val)
                        $object[] = $this->objetize ($val);
                    return $object;
                } else {
                    $object = new YPFObject();
                    foreach ($config as $key=>$val) {
                        if ($key == '$include') {
                            $included = $this->loadConfigurationFile(YPFramework::getFileName($this->base_path, $val));
                            foreach ($included as $inc_key => $inc_val)
                                $this->mergeConfig ($object, $inc_key, $this->objetize ($inc_val));
                        } elseif (($pos = strrpos($key, ':')) !== false) {
                            $copy = substr($key, $pos+1);
                            $key = substr($key, 0, $pos);
                            if (!isset($object->{$copy}))
                                throw new BaseError (sprintf('Configuration section %s is not available to be inherited by section %s', $copy, $key));
                            $object->{$key} = $this->objetize(array_merge_deep(get_object_vars($object->{$copy}), $val));
                        } else
                            $object->{$key} = $this->objetize ($val);
                    }
                    return $object;
                }
            } else
                return $config;
        }

        private function replace(&$object) {
            if (is_object($object))
                foreach($object as $key=>$val)
                    $this->replace($object->{$key});

            elseif (is_array($object))
                foreach($object as $key=>$val)
                    $this->replace($object[$key]);

            elseif (is_string($object))
                $object = $this->processString ($object);
        }

        private function processString($value) {
            while (preg_match('/\\{%([a-z][a-zA-Z0-9_\\.]+)\\}/', $value, $matches, PREG_OFFSET_CAPTURE))
            {
                $replaced = '$this->root->'.str_replace('.', '->', $matches[1][0]);
                $replaced = eval("if (isset($replaced)) return $replaced; else return null;");
                $value = substr($value, 0, $matches[0][1]).$replaced.substr($value, $matches[0][1]+strlen($matches[0][0]));
            }

            return $value;
        }
    }
?>
