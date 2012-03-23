<?php
    /**
     * Implementation of ActiveRecord pattern.
     *
     * YPFModelBase is the base class of each application Model base class.
     * It contains all the logic useful to store and retrieve rows of a table
     * in an object oriented way.
     */
    class YPFModelBase extends YPFObject implements Iterator {
        private static $modelParams = null;

        protected static $_databaseName = 'main';
        protected static $_database;

        protected $_modelData = array();
        protected $_modelFieldModified = array();
        protected $_modelModified = false;
        protected $_modelErrors = array();
        protected $_modelName = null;
        protected $_modelParams = null;
        //----------------------------------------------------------------------

        //Métodos de ModelBase
        public static function getModelParams($model) {
            if (!isset(YPFModelBase::$modelParams->{$model}))
                $model::loadMetadata();
            return (isset(YPFModelBase::$modelParams->{$model})? YPFModelBase::$modelParams->{$model}: null);
        }

        public static function fromData($data) {
            $modelName = get_called_class();
            $instance = eval(sprintf('return new %s();', $modelName));
            $instance->loadFromRecord($row);
        }

        public static function install() {
            $model = get_called_class();

            if ($model::$_database === null)
                $model::$_database = YPFramework::getDatabase();

            $settings = get_class_vars($model);

            if (isset($settings['_schema']))
                $model::$_database->install($settings['_schema']);
        }

        public static function getDB() {
            $modelName = get_called_class();
            $modelParams = Model::getModelParams($modelName);
            return $modelName::$_database;
        }

        public static function find($id, $instance = null, $rawId = false) {
            $modelName = get_called_class();
            $modelParams = Model::getModelParams($modelName);

            if (is_array($id))
            {
                $null = true;
                $key = array();

                foreach ($modelParams->keyFields as $k)
                {
                    if (!isset($id[$k]))
                        return null;
                    $key[] = Model::encodeKey($id[$k]);
                }

                $str_key = implode('|', $key);
            }
            else
                $str_key = $id;

            $id = Model::decodeKey($id, $modelParams);
            $aliasPrefix = ($modelParams->aliasName!='')? $modelParams->aliasName: $modelParams->tableName;

            //Preparar condiciones
            $conditions = $modelParams->sqlConditions;
            if (is_array($id))
            {
                foreach ($id as $key=>$value)
                    if (array_search ($key, $modelParams->keyFields) !== false)
                        $conditions[] = sprintf('(`%s`.`%s` = %s)', $aliasPrefix, $key, Model::getFieldSQLRepresentation($key, $value, $modelParams, $rawId));
            } elseif (count($modelParams->keyFields) == 1)
                $conditions[] = sprintf('(`%s`.`%s` = %s)', $aliasPrefix, $modelParams->keyFields[0], Model::getFieldSQLRepresentation($modelParams->keyFields[0], $id, $modelParams), $rawId);
            else
                throw new ErrorDataModel ($modelName, 'find(): invalid number of key values');

            return $modelParams->modelQuery->where(implode(' AND ', $conditions))->limit(1)->first();
        }

        public static function transaction($code) {
            $model = get_called_class();
            YPFModelBase::getModelParams($model);

            if ($model::$_database->inTransaction()) {
                return $code();
            } else {
                try {
                    $model::$_database->begin();
                    if ($code()) {
                        $model::$_database->commit();
                        return true;
                    } else
                        $model::$_database->rollback();
                } catch (Exception $e) {
                    $model::$_database->rollback();
                }
                return false;
            }
        }

        //Métodos de Instancia
        public final function __construct($values = null) {
            $this->_modelName = get_class($this);

            $this->_modelParams = Model::getModelParams($this->_modelName);

            foreach ($this->_modelParams->tableMetaData as $name => $meta)
                if ($meta->Type == 'date')
                    $this->_modelData[$name] = YPFDateTime::createFromDB ('date', $this->_modelData[$name] = $meta->Default);
                elseif ($meta->Type == 'datetime')
                    $this->_modelData[$name] = YPFDateTime::createFromDB ('datetime', $this->_modelData[$name] = $meta->Default);
                elseif ($meta->Type == 'time')
                    $this->_modelData[$name] = YPFDateTime::createFromDB ('time', $this->_modelData[$name] = $meta->Default);
                else
                    $this->_modelData[$name] = $meta->Default;
            foreach ($this->_modelParams->transientFields as $name => $default)
                $this->_modelData[$name] = $default;

            if ($values !== null)
                $this->setAttributes ($values);

            $className = $this->_modelName;
            foreach($className::on('initialize') as $function)
                if (is_callable (array($this, $function)))
                    call_user_func(array($this, $function));
                else
                    throw new ErrorNoCallback(get_class($this), $function);
        }

        public final function __get($name) {
            if (isset($this->_modelParams->tableMetaData[$name]))
                return $this->_modelData[$name];
            elseif (isset($this->_modelParams->relations[$name]))
                return $this->getRelationObject($name)->get($this);
            elseif (array_key_exists($name, $this->_modelParams->transientFields))
                return $this->_modelData[$name];
            elseif (isset($this->_modelParams->customQueries[$name]))
                return $this->_modelParams->modelQuery->{$name};
            else
                return null;
        }

        public static final function __callStatic($name, $arguments) {
            $modelParams = self::getModelParams(get_called_class());
            return call_user_func_array(array($modelParams->modelQuery, $name), $arguments);
        }

        public final function __set($name, $value) {
            $className = $this->_modelName;

            $result = true;
            foreach($className::before('set') as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function), $name, $value) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);
            if (!$result)
                return;

            if (isset($this->_modelParams->tableMetaData[$name]))
            {
                $type = $this->_modelParams->tableMetaData[$name]->Type;

                if (array_search($type, array('date', 'time', 'datetime')) !== false)
                    $this->_modelData[$name] = YPFDateTime::createFromLocal($type, $value);
                else
                    $this->_modelData[$name] = $value;
                $this->_modelModified = true;
                $this->_modelFieldModified[$name] = true;
            }
            elseif (isset($this->_modelParams->relations[$name]))
            {
                $relation = $this->getRelationObject($name);
                $relation->set($this, $value);
            }
            elseif (array_key_exists($name, $this->_modelParams->transientFields))
                $this->_modelData[$name] = $value;
            else
                throw new ErrorComponentNotFound ('model property', sprintf('%s::%s', $this->_modelName, $name));

            foreach($className::after('set') as $function)
                if (is_callable (array($this, $function)))
                    call_user_func(array($this, $function), $name, $value);
                else
                    throw new ErrorNoCallback(get_class($this), $function);
        }

        public final function __isset($name) {
            return (isset($this->_modelParams->tableMetaData[$name]) |
                    isset($this->_modelParams->relations[$name]) |
                    array_key_exists($name, $this->_modelParams->transientFields));
        }

        public final function __unset($name) {
            if (isset($this->_modelParams->tableMetaData[$name]) | array_key_exists($name, $this->_modelParams->transientFields))
            {
                $this->_modelData[$name] = $this->_modelParams->tableMetaData[$name]->Default;
                $this->_modelModified = true;
                $this->_modelFieldModified[$name] = true;
            }
        }

        public final function getSerializedKey($stringify=true) {
            $null = true;
            $key = array();

            foreach ($this->_modelParams->keyFields as $k)
            {
                $v = $this->__get($k);
                $null = $null && ($v === null);
                $key[] = Model::encodeKey($v);
            }

            return $null? null: (($stringify)? implode('|', $key): $key);
        }

        public final function getAttributes() {
            return $this->_modelData;
        }

        public final function setAttributes($attributes) {
            foreach ($attributes as $key=>$value)
            {
                if (isset($this->_modelParams->relations[$key]) && (!is_object($value)) && (!is_array($value)))
                    continue;
                $this->__set($key, $value);
            }
        }

        public final function getError($field) {
            if (isset($this->_modelErrors[$field]))
                return $this->_modelErrors[$field];
            else
                return null;
        }

        public final function getErrors() {
            return arraize($this->_modelErrors);
        }

        public final function clearErrors() {
            $this->_modelErrors = array();
        }

        public function save() {
            $model = $this->_modelName;

            if ($model::$_database->inTransaction()) {
                return $this->realSave();
            } else {
                try {
                    $model::$_database->begin();
                    if ($this->realSave()) {
                        $model::$_database->commit();
                        return true;
                    } else
                        $model::$_database->rollback();
                } catch (Exception $e) {
                    $model::$_database->rollback();
                }
                return false;
            }
        }

        public function delete() {
            $model = $this->_modelName;

            if ($model::$_database->inTransaction()) {
                return $this->realDelete();
            } else {
                try {
                    $model::$_database->begin();
                    if ($this->realDelete()) {
                        $model::$_database->commit();
                        return true;
                    } else
                        $model::$_database->rollback();
                } catch (Exception $e) {
                    $model::$_database->rollback();
                }
                return false;
            }
        }

        public function isNew() {
            if ($this->isIdNull())
                return true;

            return ($this->_modelParams->modelQuery->where($this->getSqlIdConditions())->count() == 0);
        }

        public function isValid() {
            $total_valid = true;
            $this->_modelErrors = array();

            $action = $this->isNew()? 'create': 'update';

            foreach ($this->_modelParams->validations as $field => $validations)
                foreach($validations as $validation => $parameters)
                {
                    $validator = "validate_".$validation;
                    if (is_bool($parameters)) {
                        if (!$parameters)
                            continue;

                        $parameters = array();
                    }

                    if (isset($parameters['only']) && ($parameters['only'] != $action))
                        continue;
                    elseif (isset($parameters['except']) && ($parameters['except'] == $action))
                        continue;

                    $valid = $this->$validator($field, $parameters);
                    $total_valid = $total_valid && $valid;
                }

            return $total_valid;
        }

        public final function loadFromRecord($record) {
            $result = true;
            $className = $this->_modelName;

            foreach($className::before('load') as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);
            if (!$result)
                return false;

            $this->_modelModified = false;

            $this->_modelFieldModified = array_fill_keys(array_keys($this->_modelParams->tableMetaData), false);
            $this->_modelData = array_fill_keys(array_keys($this->_modelParams->tableMetaData), null);
            foreach ($this->_modelParams->transientFields as $field=>$default)
                $this->_modelData[$field] = $default;

            $model = $this->_modelName;

            foreach($record as $field => $value)
            {
                //Eliminar prefijo
                $pos = strrpos($field, '.');
                if ($pos !== false)
                    $field = substr($field, $pos+1);

                if (!array_key_exists($field, $this->_modelData))
                    continue;

                if ($value === NULL)
                    $this->_modelData[$field] = $value;
                else
                    switch ($this->_modelParams->tableMetaData[$field]->Type)
                    {
                        case 'integer':
                        case 'int':
                        case 'tinyint':
                            $this->_modelData[$field] = (int)$value;
                            break;

                        case 'double':
                        case 'float':
                        case 'real':
                            $this->_modelData[$field] = (double)$value;
                            break;

                        case 'date':
                        case 'time':
                        case 'datetime':
                            $this->_modelData[$field] = YPFDateTime::createFromDB($this->_modelParams->tableMetaData[$field]->Type, $value);
                            break;

                        case 'varchar':
                        case 'mediumtext':
                        case 'text':
                        case 'string':
                        default:
                            if ($this->_modelParams->tableCharset != 'utf-8')
                                $this->_modelData[$field] = iconv($this->_modelParams->tableCharset, 'utf-8', $value);
                            else
                                $this->_modelData[$field] = $value;
                            break;
                    }
            }

            $result = true;
            foreach($className::after('load') as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            return $result;
        }

        public static function getFieldSQLRepresentation($field, $value, $modelParams, $rawData = false) {
            if (!array_key_exists($field, $modelParams->tableMetaData))
                $type = 'string';
            else
                $type = $modelParams->tableMetaData[$field]->Type;

            if ($value === null)
                return 'NULL';

            switch ($type)
            {
                case 'integer':
                case 'int':
                case 'tinyint':
                    if ($value === '')
                        return 'NULL';
                    return sprintf("%d", $value);

                case 'double':
                case 'float':
                case 'real':
                    if ($value === '')
                        return 'NULL';
                    return sprintf("%F", $value);

                case 'date':
                case 'time':
                case 'datetime':
                    if ($value === '')
                        return 'NULL';
                    $model = $modelParams->modelName;
                    if ($rawData) return sprintf("'%s'", $model::$_database->sqlEscaped($value));
                    return sprintf("'%s'", $value->__toDBValue());

                case 'varchar':
                case 'mediumtext':
                case 'text':
                case 'string':
                default:
                    $model = $modelParams->modelName;
                    if ($modelParams->tableCharset != 'utf-8' && !$rawData)
                        return sprintf("'%s'", $model::$_database->sqlEscaped(iconv('utf-8', $modelParams->tableCharset, $value)));
                    else
                        return sprintf("'%s'", $model::$_database->sqlEscaped($value));
            }
        }

        public static function encodeKey($key) {
            $result = '';
            $key = $key.'';

            for($i = 0; $i < strlen($key); $i++)
            {
                if (preg_match('/[a-zA-Z_0-9\\-]/', $key[$i]) == 0)
                {
                    $result.='%'.sprintf('%02x', ord($key[$i]));
                } else
                    $result.=$key[$i];
            }

            return $result;
        }

        private static function decodeKey($key, $modelParams) {
            if (is_array($key))
                return $key;

            $key = explode('|', $key);

            if (count($modelParams->keyFields) <= count($key))
            {
                $result = array();
                for ($i = 0; $i < count($modelParams->keyFields); $i++)
                    $result[$modelParams->keyFields[$i]] = urldecode ($key[$i]);

                return $result;
            }

            return null;
        }

        private function validate_relation($field, $parameters) {
            $value = $this->__get($field);
            $valid = true;

            if (is_null($value))
                return true;
            elseif ($value instanceof YPFModelQuery) {
                foreach ($value as $inst)
                    $valid = $valid && $inst->isValid();
            } elseif (is_object($value) && $value instanceof YPFModelBase)
                $valid = $value->isValid();

            return $valid;
        }

        private function validate_presence($field, $parameters) {
            $message = isset($parameters['message'])? $parameters['message']: 'se esperaba un valor';
            $value = $this->__get($field);
            $valid = ($value !== '') && ($value !== NULL);
            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        private function validate_confirmation($field, $parameters) {
            $message = isset($parameters['message'])? $parameters['message']: 'no coinciden los valores';
            $sufix = isset($parameters['sufix'])? $parameters['sufix']: '_confirmation';
            $value = $this->__get($field.$sufix);
            $valid = ((!$value) && (!$this->__get($field))) || ($value === $this->__get($field));

            if (!$valid)
            {
                $field .= $sufix;

                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        private function validate_exclusion_in($field, $parameters) {
            $message = isset($parameters['message'])? $parameters['message']: 'valor incorrecto';
            $value = $this->__get($field);
            $valid = (array_search($value, $parameters) === false);

            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        private function validate_inclusion_in($field, $parameters) {
            $message = isset($parameters['message'])? $parameters['message']: 'valor incorrecto';
            $value = $this->__get($field);
            $valid = (array_search($value, $parameters) !== false);

            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        private function validate_format($field, $parameters) {
            $message = isset($parameters['message'])? $parameters['message']: 'formato incorrecto';
            $value = $this->__get($field);
            $valid = preg_match($parameters['with'], $value);
            $valid = $valid || (isset($parameters['allow_blank']) && $parameters['allow_blank']);

            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        private function validate_length($field, $parameters) {
            $message = isset($parameters['message'])? $parameters['message']: 'longitud incorrecta';
            $value = $this->__get($field);
            $valid = true;

            if (isset($parameters['is']))
                $valid = $valid && (strlen($value) == $parameters['is']);
            if (isset($parameters['max']))
                $valid = $valid && (strlen($value) <= $parameters['max']);
            if (isset($parameters['min']))
                $valid = $valid && (strlen($value) >= $parameters['min']);

            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        private function validate_with($field, $parameters) {
            $message = isset($parameters['message'])? $parameters['message']: 'parámetro incorrecto';
            $value = $this->__get($field);
            $function = $parameters['function'];
            $valid = $function($this, $value);

            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        private function validate_uniqueness($field, $parameters) {
            $message = isset($parameters['message'])? $parameters['message']: 'el valor no puede repetirse';
            $value = $this->__get($field);

            $count = $this->_modelParams->modelQuery->where("$field = ?", $value)
                          ->where(sprintf('NOT (%s)', implode(' AND ', $this->getSqlIdConditions())))
                          ->count();

            $valid = ($count == 0);

            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        private function realSave() {
            $model = $this->_modelName;
            $result = true;

            foreach($model::before('save') as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            if (!$result)
                return false;

            if (!$this->_modelModified)
                return false;

            if (!$this->isValid())
                return false;

            $fieldNames = array_keys($this->_modelParams->tableMetaData);
            if ($this->isNew())
            {
                $result = true;
                foreach($model::before('create') as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else
                        throw new ErrorNoCallback(get_class($this), $function);

                if (!$result)
                    return false;

                $fieldValues = array();

                foreach ($fieldNames as $field)
                    $fieldValues[] = Model::getFieldSQLRepresentation($field, $this->__get($field), $this->_modelParams);

                $sql = sprintf("INSERT INTO `%s` (`%s`) VALUES(%s)", $this->_modelParams->tableName,
                    implode('`, `', $fieldNames), implode(', ', $fieldValues));

                $result = $model::$_database->command($sql);

                if (is_int($result) && (count($this->_modelParams->keyFields)==1))
                {
                    $this->_modelData[$this->_modelParams->keyFields[0]] = $result;
                    $result = true;
                }

                if (!$result)
                    return false;
                foreach($model::after('create') as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else
                        throw new ErrorNoCallback(get_class($this), $function);
            } else
            {
                $result = true;
                foreach($model::before('update') as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else
                        throw new ErrorNoCallback(get_class($this), $function);

                if (!$result)
                    return false;

                $fieldAssigns = array();

                foreach ($fieldNames as $field)
                    if (isset($this->_modelFieldModified[$field]) && $this->_modelFieldModified[$field])
                        $fieldAssigns[] = sprintf("`%s` = %s", $field, Model::getFieldSQLRepresentation($field, $this->__get($field), $this->_modelParams));

                $sql = sprintf("UPDATE `%s` SET %s WHERE %s", $this->_modelParams->tableName,
                    implode(', ', $fieldAssigns), implode(' AND ', $this->getSqlIdConditions(false)));

                $result = $model::$_database->command($sql);

                if (!$result)
                    return false;

                foreach($model::after('update') as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else
                        $model::$_database->rollback();
            }

            if ($result === false)
                return false;
            else {
                $this->_modelModified = false;
                $this->_modelFieldModified = array_fill_keys($fieldNames, false);

                foreach ($this->_modelParams->relations as $name=>$relation) {
                    $relation = $this->getRelationObject($name);

                    if ($relation instanceof YPFHasManyRelation) {
                        $relation = $relation->get($this);
                        if (!$relation->save()) {
                            $model::$_database->rollback();
                            return false;
                        }
                    }
                }

                $result = true;
                foreach($model::after('save') as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else
                        throw new ErrorNoCallback(get_class($this), $function);

                if (!$result)
                    return false;
            }

            return true;
        }

        private function realDelete() {
            $result = true;
            $model = $this->_modelName;

            foreach($model::before('delete') as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            if (!$result)
                return false;

            $sql = sprintf("DELETE FROM `%s` WHERE %s",
                $this->_modelParams->tableName, implode(' AND ', $this->getSqlIdConditions(false)));

            $result = $model::$_database->command($sql);

            foreach($model::after('delete') as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            return $result;
        }

        public function getSqlIdConditions($withAlias=true) {
            $conditions = array();

            if (is_string($withAlias) && (strlen($withAlias) > 0))
                $aliasPrefix = $withAlias;
            elseif($this->_modelParams->aliasName != '')
                $aliasPrefix = $this->_modelParams->aliasName;
            else
                $aliasPrefix = $this->_modelParams->tableName;

            foreach($this->_modelParams->keyFields as $field)
            {
                $value = Model::getFieldSQLRepresentation ($field, $this->__get($field), $this->_modelParams);
                $conditions[] = sprintf('(`%s`.`%s` %s %s)', $aliasPrefix, $field,
                    ($value == 'NULL')? 'IS':'=', $value);
            }

            return $conditions;
        }

        public function getRelationObject($name) {
            if (isset($this->_modelParams->relations[$name]))
            {
                if (!isset($this->_modelParams->relationObjects->{$name}))
                {
                    $model = $this->_modelName;
                    $this->_modelParams->relationObjects->{$name} = YPFModelBaseRelation::getFor($this->_modelName, $name, $this->_modelParams->relations[$name]);
                }

                return $this->_modelParams->relationObjects->{$name};
            }

            return null;
        }

        private function isIdNull() {
            $isNull = true;

            foreach($this->_modelParams->keyFields as $field)
                $isNull = $isNull && ($this->__get($field) === null);

            return $isNull;
        }

        //Object redefinition
        //======================================================================
        public function __toString() {
            $values = array();

            foreach($this->_modelData as $key=>$value)
                $values[] = sprintf('%s: %s', $key, $value);

            return sprintf('<#%s %s>', get_class($this), implode(', ', $values));
        }

        public function __toJSONRepresentable() {
            return $this->_modelData;
        }

        //ModelQuery static Implementation
        //======================================================================
        public static function sum($expression) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->sum($expression);
        }

        public static function max($expression) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->max($expression);
        }

        public static function min($expression) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->min($expression);
        }

        public static function value($expression) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->value($expression);
        }

        public static function fields($fields) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->fields($fields);
        }

        public static function count($sqlConditions = null, $sqlGrouping = null) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->count($sqlConditions, $sqlGrouping);
        }

        public static function all() {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->all();
        }

        public static function first() {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->first();
        }

        public static function last() {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->last();
        }

        public static function join($table, $conditions) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->join($table, $conditions);
        }

        public static function where($sqlConditions) {
            $modelParams = Model::getModelParams(get_called_class());
            return call_user_func_array(array($modelParams->modelQuery, 'where'), func_get_args());
        }

        public static function groupBy($sqlGrouping) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->groupBy($sqlGrouping);
        }

        public static function orderBy($sqlOrdering) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->orderBy($sqlOrdering);
        }

        public static function limit($limit) {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->limit($limit);
        }

        //Iterator Implementation
        //======================================================================
        private $_iteratorCurrentKey = null;
        private $_iteratorCurrentIndex = null;
        private $_iteratorKeys = null;

        public function current() {
            if ($this->_iteratorCurrentIndex == null)
                $this->next();

            return $this->__get($this->_iteratorCurrentKey);
        }

        public function key() {
            if ($this->_iteratorCurrentIndex == null)
                $this->next();

            return $this->_iteratorCurrentKey;
        }

        public function next() {
            $this->_iteratorCurrentIndex++;

            if ($this->_iteratorCurrentIndex < count($this->_modelParams->tableMetaData))
            {
                if ($this->_iteratorKeys === null)
                    $this->_iteratorKeys = array_keys($this->_modelParams->tableMetaData);

                $this->_iteratorCurrentKey = $this->_iteratorKeys[$this->_iteratorCurrentIndex];
            }
        }

        public function rewind() {
            $this->_iteratorCurrentIndex = null;
        }

        public function valid() {
            return ($this->_iteratorCurrentIndex < count($this->_modelParams->tableMetaData));
        }

        //Initializable implementation
        //======================================================================
        public static final function loadMetadata() {
            $model = get_called_class();

            if ($model::$_database === null)
                $model::$_database = YPFramework::getDatabase($model::$_databaseName);

            if (!$model::$_database)
                throw new BaseError ('Couldn\'t get a database connection', 'DB');

            if (YPFModelBase::$modelParams === null)
                YPFModelBase::$modelParams = new YPFObject();

            if (isset(YPFModelBase::$modelParams->{$model}))
                return;

            $settings = get_class_vars($model);
            $params = new YPFObject();
            $params->modelName =        $model;

            if (isset($settings['_schema']))
            {
                $params->tableName = $settings['_schema']['name'];
                $params->keyFields = array();

                foreach ($settings['_schema']['columns'] as $column)
                    if ($column['type'] == 'key')
                        $params->keyFields[] = $column['name'];

                if (empty($params->keyFields))
                    $params->keyFields = array_map (function($i){ return $i['name']; }, $settings['_schema']['columns']);
            } else
            {
                $params->tableName =        (isset($settings['_tableName'])? $settings['_tableName']: YPFramework::underscore($model));
                $params->keyFields =        (isset($settings['_keyFields'])? arraize($settings['_keyFields']): array('id'));
            }

            $params->aliasName =        (isset($settings['_aliasName'])? $settings['_aliasName']: null);
            $params->transientFields =  (isset($settings['_transientFields'])? $settings['_transientFields']: array());
            $params->tableCharset =     (isset($settings['_tableCharset'])? strtolower($settings['_tableCharset']): 'utf-8');

            $params->sqlFields =        (isset($settings['_sqlFields'])? $settings['_sqlFields']: array());
            $params->sqlJoins =         (isset($settings['_sqlJoins'])? $settings['_sqlJoins']: array());
            $params->sqlConditions =    (isset($settings['_sqlConditions'])? $settings['_sqlConditions']: array());
            $params->sqlGrouping =      (isset($settings['_sqlGrouping'])? $settings['_sqlGrouping']: array());
            $params->sqlGroupConditions = (isset($settings['_sqlGroupConditions'])? $settings['_sqlGroupConditions']: array());
            $params->sqlOrdering =      (isset($settings['_sqlOrdering'])? $settings['_sqlOrdering']: array());
            $params->sqlLimit =         (isset($settings['_sqlLimit'])? $settings['_sqlLimit']: null);
            $params->customQueries =    (isset($settings['_queries'])? $settings['_queries']: array());

            $params->relations =        (isset($settings['_relations'])? $settings['_relations']: array());

            $params->validations =      (isset($settings['_validations'])? $settings['_validations']: array());
            foreach ($params->validations as $k=>$field)
                foreach ($field as $i=>$validation)
                    if (!is_array($validation))
                        $params->validations[$k][$i] = array('value' => $validation);

            $params->relationObjects =  new YPFObject();
            $params->tableMetaData = $model::$_database->getTableFields($params->tableName);

            if (!$params->tableMetaData)
                throw new ErrorDataModel ($model, sprintf('Couldn\'t load table \'%s\' metadata', $params->tableName));

            YPFModelBase::$modelParams->{$model} = $params;
            $params->modelQuery = new YPFModelQuery($model::$_database, $model);
        }
    }
?>
