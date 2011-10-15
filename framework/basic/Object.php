<?php
    class Object
    {
        private static $__mixin_included = null;

        public static function __include($className)
        {
            if (Object::$__mixin_included === null)
                Object::$__mixin_included = new stdClass();

            $baseClass = get_called_class();

            if (!isset(Object::$__mixin_included->{$baseClass}))
            {
                $params = new stdClass();
                $params->classes = array();
                $params->methods = array();
                $params->vars = array();
                Object::$__mixin_included->{$baseClass} = $params;
            } else
                $params = Object::$__mixin_included->{$baseClass};

            if (array_search($className, $params->classes) !== false)
                return false;

            $params->classes[] = $className;

            $class = new ReflectionClass($className);

            $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach($methods as $method)
                if (($method->name[0] != '_') && ($method->getDeclaringClass()->name == $className))
                    $params->methods[$method->name] = $className;

            $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC);
            $vars = get_class_vars($className);

            foreach($properties as $property)
                if ($property->getDeclaringClass()->name == $className)
                    $params->vars[$property->name] = $vars[$property->name];

            return true;
        }

        public function __construct()
        {
            $baseName = get_class($this);

            while ($baseName)
            {
                if ((Object::$__mixin_included !== null) && isset(Object::$__mixin_included->{$baseName}))
                {
                    foreach (Object::$__mixin_included->{$baseName}->vars as $varName => $varValue)
                        $this->{$varName} = $varValue;

                    foreach (Object::$__mixin_included->{$baseName}->classes as $class)
                    {
                        if (array_search ('__included', get_class_methods ($class)) !== false)
                            eval("$class::__included(\$this);");
                    }
                }

                $baseName = get_parent_class($baseName);
            }
        }

        public function __call($name, $arguments)
        {
            $baseName = get_class($this);

            do {
                if ((Object::$__mixin_included !== null)
                        && isset(Object::$__mixin_included->{$baseName})
                        && isset(Object::$__mixin_included->{$baseName}->methods[$name]))
                {
                    $className = Object::$__mixin_included->{$baseName}->methods[$name];

                    if (empty($arguments))
                        $methodCall = "return $className::$name();";
                    else
                        $methodCall = "return $className::$name(\$arguments[".implode('], $arguments[', array_keys($arguments)).']);';

                    return eval($methodCall);
                }
            } while (($baseName = get_parent_class($baseName)));

            throw new BaseError(sprintf('No method defined for: %s->%s', $baseName, $name));
        }

        public function __toString()
        {
            $str = sprintf('<%s ', get_class($this));
            foreach ($this as $k=>$v)
                $str .= sprintf('%s: %s; ', $k, var_export($v, true));

            return substr($str, 0, -2).'>';
        }

        public function __toJSONRepresentable()
        {
            $result = array();

            foreach ($this as $k=>$v)
            {
                if (is_object($v) && ($v instanceof Object))
                {
                    if (!($v instanceof YPFNoJsonable))
                        $result[$k] = $v->__toJSONRepresentable();
                } else
                    $result[$k] = $v;
            }

            return $result;
        }

        public function __toJSON()
        {
            return json_encode($this->__toJSONRepresentable());
        }

        public function __toXML($xmlParent = null)
        {
            if ($xmlParent === null)
                $root = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><root />');
            else
                $root = $xmlParent;

            foreach($this as $key=>$val)
            {
                if (is_scalar($val) or ($val == null))
                    $root->addChild ($key, $val);
                elseif (is_object($val))
                    $this->__toXML($root->addChild($key));
            }

            if ($xmlParent === null)
                return $root->asXML();
        }
    }
?>