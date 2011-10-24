<?php
    class Route
    {
        private $name;
        private $match;
        private $method;
        private $controller;
        private $action;
        private $format;

        private $config;

        private $replaces;
        private $pattern;
        private $optionals;
        private $baseUrl;

        public function __construct($name, $config, $baseUrl)
        {
            if (!isset($config->match))
                throw new BaseError("No 'match' rule found in route: $name");

            $this->name = $name;
            $this->match = $config->match;
            $this->method = null;
            $this->baseUrl = $baseUrl;

            if (isset($config->method))
            {
                if (is_array($config->method))
                {
                    $this->method = array();
                    foreach($config->method as $m)
                        $this->method[] = strtoupper ($m);
                } else
                    $this->method = array(strtoupper($config->method));
            }

            $this->controller = isset($config->controller)? $config->controller: null;
            $this->action = isset($config->action)? $config->action: null;
            $this->format = isset($config->format)? $config->format: 'html';
            $this->optionals = array();

            if ($this->match[0] == '/')
                $rule = substr($this->match, 1);
            else
                $rule = $this->match;

            //Escapamos caracteres
            $rule = preg_replace(array('/\\//', '/\\)/', '/\\./'), array('\\\\/', ')?', '\\\\.'), $rule);
            //Encerramos parámetros
            $rule = preg_replace('/(:[a-zA-Z][a-zA-Z0-9_]*)/', '($1)', $rule);
            $rule = preg_replace('/(\\$[a-zA-Z][a-zA-Z0-9_]*)/', '($1)', $rule);

            $position = 0;
            $this->replaces = array();

            for ($i = 0; $i < strlen($rule); $i++)
            {
                if ($rule[$i] == '(')
                {
                    $position++;

                    if (preg_match('/\\(:[a-zA-Z][a-zA-Z0-9_]*\\)/', $rule, $matches, PREG_OFFSET_CAPTURE, $i))
                    {
                        if ($matches[0][1] == $i)
                            $this->replaces[$position] = $matches[0][0];
                    }

                    if (preg_match('/\\(\\$[a-zA-Z][a-zA-Z0-9_]*\\)/', $rule, $matches, PREG_OFFSET_CAPTURE, $i))
                    {
                        if ($matches[0][1] == $i)
                            $this->replaces[$position] = $matches[0][0];
                    }
                }
            }

            foreach ($this->replaces as $level=>$replace) {
                if ($replace[1] == ':')
                    $rule = str_replace ($replace, '([^(\\/\\.)]+)', $rule);
                elseif ($replace[1] == '$')
                    $rule = str_replace ($replace, '(.+)', $rule);
            }
            $this->pattern = '/^'.$rule.'$/';
            $this->config = $config;
            $this->config->name = $name;
        }

        public function matches($action)
        {
            if (preg_match($this->pattern, $action, $matches, PREG_OFFSET_CAPTURE))
            {
                if (($this->method === null) || (array_search($_SERVER['REQUEST_METHOD'], $this->method) !== false))
                {
                    Logger::framework('ROUTE:MATCH', sprintf('%s: %s', $this->name, $action));

                    $result = array(
                        'controller' => $this->controller,
                        'action' => $this->action,
                        'format' => $this->format,
                        'route' => $this
                    );
                    foreach($this->replaces as $index=>$name)
                    {
                        if (isset($matches[$index]))
                            $result[substr($name, 2, -1)] = $matches[$index][0];
                    }

                    return $result;
                }
            }

            return false;
        }

        public function path($params = array())
        {
            if (is_object($params))
                $params = array('id' => $params);

            $path = $this->match;
            $usedparams = array();

            $start = 0;
            while (preg_match('/:[a-zA-Z][a-zA-Z0-9_]*/', $path, $matches, PREG_OFFSET_CAPTURE, $start))
            {
                $key = substr($matches[0][0], 1);
                if (!array_key_exists($key, $params))
                {
                    $start = $matches[0][1]+strlen($matches[0][0]);
                    continue;
                }
                $usedparams[] = $key;
                $replace = is_object($params[$key])? (($params[$key] instanceof ModelBase)? $params[$key]->getSerializedKey(): $params[$key]->__toString()): $params[$key];
                if ($replace === null)
                {
                    $start = $matches[0][1]+strlen($matches[0][0]);
                    continue;
                }

                $start = $matches[0][1]+strlen($replace);
                $path = substr($path, 0, $matches[0][1]).$replace.substr($path, $matches[0][1]+strlen($matches[0][0]));
            }

            $optionals = array();
            $level = 0;

            for($i = 0; $i < strlen($path); $i++)
            {
                if ($path[$i] == '(')
                {
                    $level++;
                    $this->optionals[$level] = $i;
                } elseif ($path[$i] == ')')
                {
                    if (preg_match('/:[a-zA-Z][a-zA-Z0-9_]*/', $path, $matches, PREG_OFFSET_CAPTURE, $this->optionals[$level]) && ($matches[0][1] < $i))
                    {
                        $path = substr($path, 0, $this->optionals[$level]).substr($path, $i+1);
                        $i = $this->optionals[$level];
                    }
                    $level--;
                }
            }

            foreach($usedparams as $p)
                unset($params[$p]);

            $path = str_replace(array('(', ')'), '', $path);

            if (count($params))
            {
                $query = array();
                foreach ($params as $k=>$v)
                {
                    $replace = is_object($v)? (($v instanceof ModelBase)? $v->getSerializedKey(): $v->__toString()): $v;
                    $query[] = sprintf('%s=%s', urlencode ($k), urlencode ($replace));
                }

                $path .= '?'.implode('&', $query);
            }

            if ($path[0] == '/')
                $path = substr($path, 1);

            return YPFramework::getFileName($this->baseUrl, $path);
        }

        public function getName() {
            return $this->name;
        }

        public function __get($name) {
            if (isset($this->config->{$name}))
                return $this->config->{$name};
            else
                return null;
        }
    }
?>