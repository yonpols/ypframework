<?php
    class YPFConfiguration
    {
        protected $root;
        protected $changed = false;

        public function __construct($configFileName=null, $root=null)
        {
            if ($root === null)
                $root = new YPFObject();
            $this->root = $root;

            if ($configFileName !== null)
                $this->addConfigFile ($configFileName);
        }

        public function addConfigFile($configFileName)
        {
            if (defined('YPF_CMD') && !file_exists($configFileName))
                return;

            $yaml = new sfYamlParser();

            try
            {
                $config = $yaml->parse(file_get_contents($configFileName));

                foreach ($config as $name=>$value)
                    $this->mergeConfig ($this->root, $name, $this->objetize($value));

                $this->changed = true;
            }
            catch (InvalidArgumentException $e)
            {
                throw new ErrorCorruptFile($configFileName, $e->getMessage());
            }

        }

        public function getRoot()
        {
            if ($this->changed)
            {
                $this->replace($this->root);
                $this->changed = false;
            }

            return $this->root;
        }

        public function isValid()
        {
            $root = $this->getRoot();

            if (!isset($root->application))
                return false;

            if (!isset($root->routes))
                return false;

            return true;
        }

        private function mergeConfig($parent, $name, $config, $path = '')
        {
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
        }

        private function objetize($config)
        {
            if (is_array($config))
            {
                $key = array_keys($config);

                if (empty($key) | (is_numeric($key[0])))
                {
                    $object = array();
                    foreach ($config as $val)
                        $object[] = $this->objetize ($val);
                    return $object;
                } else
                {
                    $object = new YPFObject();
                    foreach ($config as $key=>$val)
                        $object->{$key} = $this->objetize ($val);
                    return $object;
                }
            } else
                return $config;
        }

        private function replace(&$object)
        {
            if (is_object($object))
                foreach($object as $key=>$val)
                    $this->replace($object->{$key});

            elseif (is_array($object))
                foreach($object as $key=>$val)
                    $this->replace($object[$key]);

            elseif (is_string($object))
                $object = $this->processString ($object);
        }

        private function processString($value)
        {
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
