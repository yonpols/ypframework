<?php
    class YPFObject
    {
        private static $__mixin_included = null;
        private static $__object_ids = 0;
        public static $__callbacks = null;
        private static $__loadedClasses = null;

        private $id = null;

        //Mixins================================================================
        /**
         * Include class variables and functions to this class
         * @param string $className
         * @return boolean returns true if the class was included
         */
        public static function __include($className) {
            $baseClass = get_called_class();

            if (!isset(YPFObject::$__mixin_included->{$baseClass}))
            {
                $params = new stdClass();
                $params->classes = array();
                $params->methods = array();
                $params->vars = array();
                YPFObject::$__mixin_included->{$baseClass} = $params;
            } else
                $params = YPFObject::$__mixin_included->{$baseClass};

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

        public function __construct() {
            $baseName = get_class($this);

            while ($baseName)
            {
                if ((YPFObject::$__mixin_included !== null) && isset(YPFObject::$__mixin_included->{$baseName}))
                {
                    foreach (YPFObject::$__mixin_included->{$baseName}->vars as $varName => $varValue)
                        $this->{$varName} = $varValue;

                    foreach (YPFObject::$__mixin_included->{$baseName}->classes as $class)
                    {
                        if (array_search ('__included', get_class_methods ($class)) !== false)
                            eval("$class::__included(\$this);");
                    }
                }

                $baseName = get_parent_class($baseName);
            }
        }

        public function __call($name, $arguments) {
            $baseName = get_class($this);

            do {
                if ((YPFObject::$__mixin_included !== null)
                        && isset(YPFObject::$__mixin_included->{$baseName})
                        && isset(YPFObject::$__mixin_included->{$baseName}->methods[$name]))
                {
                    $className = YPFObject::$__mixin_included->{$baseName}->methods[$name];

                    if (empty($arguments))
                        $methodCall = "return $className::$name();";
                    else
                        $methodCall = "return $className::$name(\$arguments[".implode('], $arguments[', array_keys($arguments)).']);';

                    return eval($methodCall);
                }
            } while (($baseName = get_parent_class($baseName)));

            throw new BaseError(sprintf('No method defined for: %s->%s', $baseName, $name));
        }

        //Object UID============================================================
        public function getObjectId() {
            if ($this->id === null)
                $this->id = sprintf("%x", time()+(self::$__object_ids++));

            return $this->id;
        }

        //Object Event Model====================================================
        public static function initialize() {
            $className = get_called_class();

            if ($className !== 'YPFObject') {
                YPFObject::$__loadedClasses[] = $className;
                return;
            }

            YPFObject::$__mixin_included = new stdClass();
            YPFObject::$__callbacks = new stdClass();
            YPFObject::$__loadedClasses = array();
        }

        public static function finalize() {
            if (get_called_class() !== 'YPFObject')
                return;

            foreach (YPFObject::$__loadedClasses as $className)
                $className::finalize();
        }

        public static final function before($event, $action = null) {
            $className = get_called_class();
            $callbacks = self::__getCallbacks($className);

            if (!isset($callbacks->before->{$event}))
                $callbacks->before->{$event} = array();

            if ($action === null)
                return $callbacks->before->{$event};
            elseif ($action === false)
                $callbacks->before->{$event} = array();
            elseif (array_search($action, $callbacks->before->{$event}) === false)
                $callbacks->before->{$event}[] = $action;
        }

        public static final function after($event, $action = null) {
            $className = get_called_class();
            $callbacks = self::__getCallbacks($className);

            if (!isset($callbacks->after->{$event}))
                $callbacks->after->{$event} = array();

            if ($action === null)
                return $callbacks->after->{$event};
            elseif ($action === false)
                $callbacks->after->{$event} = array();
            elseif (array_search($action, $callbacks->after->{$event}) === false)
                $callbacks->after->{$event}[] = $action;
        }

        public static final function on($event, $action = null) {
            $className = get_called_class();
            $callbacks = self::__getCallbacks($className);

            if (!isset($callbacks->on->{$event}))
                $callbacks->on->{$event} = array();

            if ($action === null)
                return $callbacks->on->{$event};
            elseif ($action === false)
                $callbacks->on->{$event} = array();
            elseif (array_search($action, $callbacks->on->{$event}) === false)
                $callbacks->on->{$event}[] = $action;
        }

        private static function __getCallbacks($className) {
            if (!isset(YPFObject::$__callbacks->{$className})) {
                $callbacks = new stdClass();
                $callbacks->before = new stdClass();
                $callbacks->after = new stdClass();
                $callbacks->on = new stdClass();

                $parentClass = get_parent_class($className);
                if ($parentClass != 'YPFObject') {
                    $parentCallbacks = self::__getCallbacks($parentClass);

                    foreach ($parentCallbacks->before as $key => $vals)
                        $callbacks->before->{$key} = $vals;
                    foreach ($parentCallbacks->after as $key => $vals)
                        $callbacks->after->{$key} = $vals;
                    foreach ($parentCallbacks->on as $key => $vals)
                        $callbacks->on->{$key} = $vals;
                }

                YPFObject::$__callbacks->{$className} = $callbacks;
            } else
                $callbacks = YPFObject::$__callbacks->{$className};

            return $callbacks;
        }

        //Object serialization==================================================
        public function __toString() {
            $str = sprintf('<%s ', get_class($this));
            foreach ($this as $k=>$v)
                $str .= sprintf('%s: %s; ', $k, var_export($v, true));

            return substr($str, 0, -2).'>';
        }

        public function __toJSONRepresentable() {
            $result = array();

            foreach ($this as $k=>$v)
            {
                if (is_object($v) && ($v instanceof YPFObject))
                {
                    if (!($v instanceof YPFNoJsonable))
                        $result[$k] = $v->__toJSONRepresentable();
                } else
                    $result[$k] = $v;
            }

            return $result;
        }

        public function __toJSON() {
            return json_encode($this->__toJSONRepresentable());
        }

        public function __toXML($xmlParent = null) {
            if ($xmlParent === null)
                $root = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><root />');
            else
                $root = $xmlParent;

            foreach($this as $key=>$val)
            {
                if (is_array($val)) {
                    $parent = $root->addChild($key);
                    foreach($val as $item)
                        if (is_object($item) && ($item instanceof YPFObject))
                            $item->__toXML($parent);
                        else
                            $parent->addChild($item);
                } elseif (is_scalar($val) or ($val == null))
                    $root->addChild($key, $val);
                elseif (is_object($val) && ($val instanceof YPFObject))
                    $val->__toXML($root->addChild($key));
            }

            if ($xmlParent === null)
                return $root->asXML();
        }
    }

    YPFObject::initialize();
?>