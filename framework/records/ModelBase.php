<?php
    class ModelBase extends Object implements Iterator, Initializable
    {
        //Variables de ModelBase, no pueden redefinirse.
        private static $modelParams = null;

        //Each model on initialization can set up a different database.
        //If this is the case, relations doesn't work.
        protected static $database;

        //Variables de instancia
        protected $_modelData = array();
        protected $_modelFieldModified = array();
        protected $_modelModified = false;
        protected $_modelErrors = array();
        protected $_modelName = null;
        protected $_modelParams = null;
        //----------------------------------------------------------------------

        //Métodos de ModelBase
        public static function getModelParams($model)
        {
            if (!isset(ModelBase::$modelParams->{$model}))
                $model::initialize();
            return (isset(ModelBase::$modelParams->{$model})? ModelBase::$modelParams->{$model}: null);
        }

        public static function fromData($data) {
            $modelName = get_called_class();
            $instance = eval(sprintf('return new %s();', $modelName));
            $instance->loadFromRecord($row);
        }

        public static function getDB()
        {
            $modelName = get_called_class();
            $modelParams = Model::getModelParams($modelName);
            return $modelName::$database;
        }

        public static function find($id, $instance = null, $rawId = false)
        {
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

            /*
            if (isset(self::$__cache->{$modelName}) && array_key_exists($str_key, self::$__cache->{$modelName}))
                return self::$__cache->{$modelName}[$str_key];
            else
                self::$__cache->{$modelName} = array();
             *
             */

            $id = Model::decodeKey($id, $modelParams);
            $aliasPrefix = ($modelParams->aliasName!='')? $modelParams->aliasName.'.': '';

            //Preparar condiciones
            $conditions = $modelParams->sqlConditions;
            if (is_array($id))
            {
                foreach ($id as $key=>$value)
                    if (array_search ($key, $modelParams->keyFields) !== false)
                        $conditions[] = sprintf('(%s%s = %s)', $aliasPrefix, $key, Model::getFieldSQLRepresentation($key, $value, $modelParams, $rawId));
            } elseif (count($modelParams->keyFields) == 1)
                $conditions[] = sprintf('(%s%s = %s)', $aliasPrefix, $modelParams->keyFields[0], Model::getFieldSQLRepresentation($modelParams->keyFields[0], $id, $modelParams), $rawId);
            else
                throw new ErrorDataModel ($modelName, 'find(): invalid number of key values');

            return $modelParams->modelQuery->where($conditions)->limit(1)->first();
        }

        // ----------- ModelQuery Implementation--------------------------------
        public static function fields($fields)
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->fields($fields);
        }

        public static function all()
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->all();
        }

        public static function count($sqlConditions = null, $sqlGrouping = null)
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->count($sqlConditions, $sqlGrouping);
        }

        public static function first()
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->first();
        }

        public static function groupBy($sqlGrouping)
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->groupBy($sqlGrouping);
        }

        public static function last()
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->last();
        }

        public static function limit($limit)
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->limit($limit);
        }

        public static function orderBy($sqlOrdering)
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->orderBy($sqlOrdering);
        }

        public static function select($sqlConditions, $sqlGrouping = array(), $sqlOrdering = array(), $sqlLimit = null)
        {
            throw new Exception('Deprecated: select');
        }

        public static function where($sqlConditions)
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->where($sqlConditions);
        }

        public static function join($table, $conditions)
        {
            $modelParams = Model::getModelParams(get_called_class());
            return $modelParams->modelQuery->join($table, $conditions);
        }

        //Métodos de Instancia
        public function __construct($id=null)
        {
            $this->_modelName = get_class($this);

            $this->_modelParams = Model::getModelParams($this->_modelName);

            if ($id !== null)
            {
                throw new ErrorDataModel($this->_modelName, sprintf('Couldn\'t load intance with id "%s"', $id));
            } else {
                $this->_modelModified = false;
                $this->_modelFieldModified = array_fill_keys(array_keys($this->_modelParams->tableMetaData), false);
                $this->_modelData = array();

                foreach($this->_modelParams->tableMetaData as $field=>$metadata)
                $this->_modelData[$field] = $metadata->Default;
                foreach($this->_modelParams->transientFields as $field=>$default)
                $this->_modelData[$field] = $default;
            }
        }

        public function __get($name)
        {
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

        public function __set($name, $value)
        {
            if (isset($this->_modelParams->tableMetaData[$name]))
            {
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
        }

        public function __isset($name)
        {
            return (isset($this->_modelParams->tableMetaData[$name]) |
                    isset($this->_modelParams->relations[$name]) |
                    array_key_exists($name, $this->_modelParams->transientFields));
        }

        public function __unset($name)
        {
            if (isset($this->_modelParams->tableMetaData[$name]) | array_key_exists($name, $this->_modelParams->transientFields))
            {
                $this->_modelData[$name] = $this->_modelParams->tableMetaData[$name]->Default;
                $this->_modelModified = true;
                $this->_modelFieldModified[$name] = true;
            }
        }

        public function getSerializedKey($stringify=true)
        {
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

        public function getAttributes()
        {
            return $this->_modelData;
        }

        public function setAttributes($attributes)
        {
            foreach ($attributes as $key=>$value)
            {
                if (isset($this->_modelParams->relations[$key]) && (!is_object($value)) && (!is_array($value)))
                    continue;
                $this->__set($key, $value);
            }
        }

        public function getError($field=null)
        {
            if ($field === null)
                return $this->_modelErrors;
            elseif (isset($this->_modelErrors[$field]))
                return $this->_modelErrors[$field];
            else
                return null;
        }

        public function clearErrors()
        {
            $this->_modelErrors = array();
        }

        public function save()
        {
            if (!$this->_modelModified)
                return false;

            $model = $this->_modelName;
            $result = true;
            foreach($this->_modelParams->beforeSave as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);
            if (!$result)
                return false;

            if (!$this->isValid())
                return false;

            //Start transaction
            $model::$database->begin();

            $fieldNames = array_keys($this->_modelParams->tableMetaData);
            if ($this->isNew())
            {
                $result = true;
                foreach($this->_modelParams->beforeCreate as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else {
                        $model::$database->rollback();
                        throw new ErrorNoCallback(get_class($this), $function);
                    }


                if (!$result) {
                    $model::$database->rollback();
                    return false;
                }

                $fieldValues = array();

                foreach ($fieldNames as $field)
                    $fieldValues[] = Model::getFieldSQLRepresentation($field, $this->__get($field), $this->_modelParams);

                $sql = sprintf("INSERT INTO %s (%s) VALUES(%s)", $this->_modelParams->tableName,
                    implode(', ', $fieldNames), implode(', ', $fieldValues));

                $result = $model::$database->command($sql);

                if (is_int($result) && (count($this->_modelParams->keyFields)==1))
                {
                    $this->_modelData[$this->_modelParams->keyFields[0]] = $result;
                    $result = true;
                }

                if (!$result) {
                    $model::$database->rollback();
                    return false;
                }
                foreach($this->_modelParams->afterCreate as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else {
                        $model::$database->rollback();
                        throw new ErrorNoCallback(get_class($this), $function);
                    }
            } else
            {
                $result = true;
                foreach($this->_modelParams->beforeUpdate as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else {
                        $model::$database->rollback();
                        throw new ErrorNoCallback(get_class($this), $function);
                    }


                if (!$result) {
                    $model::$database->rollback();
                    return false;
                }

                $fieldAssigns = array();

                foreach ($fieldNames as $field)
                    if ($this->_modelFieldModified[$field])
                        $fieldAssigns[] = sprintf("%s = %s", $field, Model::getFieldSQLRepresentation($field, $this->__get($field), $this->_modelParams));

                $sql = sprintf("UPDATE %s SET %s WHERE %s", $this->_modelParams->tableName,
                    implode(', ', $fieldAssigns), implode(' AND ', $this->getSQlIdConditions(false)));

                $result = $model::$database->command($sql);

                if (!$result) {
                    $model::$database->rollback();
                    return false;
                }
                foreach($this->_modelParams->afterUpdate as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else {
                        $model::$database->rollback();
                        throw new ErrorNoCallback(get_class($this), $function);
                    }
            }

            if ($result === false) {
                $model::$database->rollback();
                return false;
            } else {
                $this->_modelModified = false;
                $this->_modelFieldModified = array_fill_keys($fieldNames, false);

                foreach ($this->_modelParams->relations as $name=>$relation) {
                    $relation = $this->getRelationObject($name);

                    if ($relation instanceof HasManyRelation) {
                        $relation = $relation->get($this);
                        if (!$relation->save()) {
                            $model::$database->rollback();
                            return false;
                        }
                    }
                }

                $result = true;
                foreach($this->_modelParams->afterSave as $function)
                    if (is_callable (array($this, $function)))
                    {
                        if (call_user_func(array($this, $function)) === false)
                            $result = false;
                    }
                    else {
                        $model::$database->rollback();
                        throw new ErrorNoCallback(get_class($this), $function);
                    }


                if (!$result) {
                    $model::$database->rollback();
                    return false;
                }
            }

            $model::$database->commit();
            return true;
        }

        public function delete()
        {
            $result = true;
            foreach($this->_modelParams->beforeDelete as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            if (!$result)
                return false;

            $model = $this->_modelName;
            $model::$database->begin();
            $sql = sprintf("DELETE FROM %s WHERE %s",
                $this->_modelParams->tableName, implode(' AND ', $this->getSQlIdConditions(false)));

            $result = $model::$database->command($sql);

            foreach($this->_modelParams->afterDelete as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else {
                    $model::$database->rollback();
                    throw new ErrorNoCallback(get_class($this), $function);
                }

            $model::$database->commit();
            return $result;
        }

        public function isNew()
        {
            $sql = sprintf("SELECT COUNT(*) FROM %s WHERE %s",
                $this->_modelParams->tableName, implode(' AND ', $this->getSQlIdConditions()));

            $model = $this->_modelName;
            return ($model::$database->value($sql) == 0);
        }

        public function isValid()
        {
            $total_valid = true;
            $this->_modelErrors = array();

            foreach ($this->_modelParams->validations as $field => $validations)
                foreach($validations as $validation => $parameters)
                {
                    $validator = "validate_".$validation;
                    $valid = $this->$validator($field, $parameters);
                    $total_valid = $total_valid && $valid;
                }

            return $total_valid;
        }

        public function loadFromRecord($record)
        {
            $result = true;
            foreach($this->_modelParams->beforeLoad as $function)
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
                            $this->_modelData[$field] = $value*1;
                            break;

                        case 'double':
                        case 'float':
                        case 'real':
                            $this->_modelData[$field] = $value*1.0;
                            break;

                        case 'date':
                            $this->_modelData[$field] = $model::$database->sqlDateToLocalDate($value);
                            break;

                        case 'time':
                            $this->_modelData[$field] = $model::$database->sqlDateToLocalDate($value);
                            break;

                        case 'datetime':
                            $this->_modelData[$field] = $model::$database->sqlDateTimeToLocalDateTime($value);
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
            foreach($this->_modelParams->afterLoad as $function)
                if (is_callable (array($this, $function)))
                {
                    if (call_user_func(array($this, $function)) === false)
                        $result = false;
                }
                else
                    throw new ErrorNoCallback(get_class($this), $function);

            return $result;
        }

        public function getSQlIdConditions($withAlias=true)
        {
            $conditions = array();

            if (is_string($withAlias) && (strlen($withAlias) > 0))
                $aliasPrefix = $withAlias.'.';
            elseif ($withAlias === false)
                $aliasPrefix = '';
            elseif($this->_modelParams->aliasName != '')
                $aliasPrefix = $this->_modelParams->aliasName.'.';
            else
                $aliasPrefix = '';

            foreach($this->_modelParams->keyFields as $field)
            {
                $conditions[] = sprintf('(%s%s = %s)', $aliasPrefix, $field,
                    Model::getFieldSQLRepresentation ($field, $this->__get($field), $this->_modelParams));
            }

            return $conditions;
        }

        public function getRelationObject($name)
        {
            if (isset($this->_modelParams->relations[$name]))
            {
                if (!isset($this->_modelParams->relationObjects->{$name}))
                {
                    $model = $this->_modelName;
                    $this->_modelParams->relationObjects->{$name} = ModelBaseRelation::getFor($this->_modelName, $name, $this->_modelParams->relations[$name]);
                }

                return $this->_modelParams->relationObjects->{$name};
            }

            return null;
        }

        public static function getFieldSQLRepresentation($field, $value, $modelParams, $rawData = false)
        {
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
                    return sprintf("%d", $value);

                case 'double':
                case 'float':
                case 'real':
                    return sprintf("%F", $value);

                case 'date':
                    $model = $modelParams->modelName;
                    if ($rawData) return sprintf("'%s'", $model::$database->sqlEscaped($value));
                    return sprintf("'%s'", $model::$database->sqlEscaped($model::$database->localDateToSqlDate($value)));
                case 'time':
                    $model = $modelParams->modelName;
                    if ($rawData) return sprintf("'%s'", $model::$database->sqlEscaped($value));
                    return sprintf("'%s'", $model::$database->sqlEscaped($model::$database->localTimeToSqlTime($value)));
                case 'datetime':
                    $model = $modelParams->modelName;
                    if ($rawData) return sprintf("'%s'", $model::$database->sqlEscaped($value));
                    return sprintf("'%s'", $model::$database->sqlEscaped($model::$database->localDateTimeToSqlDateTime($value)));

                case 'varchar':
                case 'mediumtext':
                case 'text':
                case 'string':
                default:
                    $model = $modelParams->modelName;
                    if ($modelParams->tableCharset != 'utf-8' && !$rawData)
                        return sprintf("'%s'", $model::$database->sqlEscaped(iconv('utf-8', $modelParams->tableCharset, $value)));
                    else
                        return sprintf("'%s'", $model::$database->sqlEscaped($value));
            }
        }

        public static function encodeKey($key)
        {
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

        protected static function decodeKey($key, $modelParams)
        {
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

        protected function validate_relation($field, $parameters)
        {
            $value = $this->__get($field);
            $valid = true;

            if (is_null($value))
                return true;
            elseif ($value instanceof Iterator)
                foreach ($value as $inst)
                    $valid = $valid && $inst->isValid();
            else
                $valid = $value->isValid();

            return $valid;
        }

        protected function validate_presence($field, $parameters)
        {
            $message = isset($parameters['message'])? $parameters['message']: 'se espearaba un valor';
            $value = $this->__get($field);
            $valid = ($value != '') && ($value !== NULL);
            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        protected function validate_confirmation($field, $parameters)
        {
            $message = isset($parameters['message'])? $parameters['message']: 'no coinciden los valores';
            $sufix = isset($parameters['sufix'])? $parameters['sufix']: '_confirmation';
            $value = $this->__get($field.$sufix);
            $valid = ($value === $this->__get($field));

            if (!$valid)
            {
                $field .= $sufix;

                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        protected function validate_exclusion_in($field, $parameters)
        {
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

        protected function validate_inclusion_in($field, $parameters)
        {
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

        protected function validate_format($field, $parameters)
        {
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

        protected function validate_length($field, $parameters)
        {
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

        protected function validate_with($field, $parameters)
        {
            $message = isset($parameters['message'])? $parameters['message']: 'longitud incorrecta';
            $value = $this->__get($field);
            $function = $parameters['function'];
            $valid = $function($value);

            if (!$valid)
            {
                if (!isset($this->_modelErrors[$field]))
                    $this->_modelErrors[$field] = array();

                $this->_modelErrors[$field][] = $message;
            }

            return $valid;
        }

        //Object redefinition
        public function __toString()
        {
            $values = array();

            foreach($this->_modelData as $key=>$value)
                $values[] = sprintf('%s: %s', $key, $value);

            return sprintf('<#%s %s>', get_class($this), implode(', ', $values));
        }

        public function __toJSONRepresentable()
        {
            return $this->_modelData;
        }

        // ----------- Iterator Implementation --------------------------------
        private $_iteratorCurrentKey = null;
        private $_iteratorCurrentIndex = null;
        private $_iteratorKeys = null;

        public function current()
        {
            if ($this->_iteratorCurrentIndex == null)
                $this->next();

            return $this->__get($this->_iteratorCurrentKey);
        }

        public function key()
        {
            if ($this->_iteratorCurrentIndex == null)
                $this->next();

            return $this->_iteratorCurrentKey;
        }

        public function next()
        {
            $this->_iteratorCurrentIndex++;

            if ($this->_iteratorCurrentIndex < count($this->_modelParams->tableMetaData))
            {
                if ($this->_iteratorKeys === null)
                    $this->_iteratorKeys = array_keys($this->_modelParams->tableMetaData);

                $this->_iteratorCurrentKey = $this->_iteratorKeys[$this->_iteratorCurrentIndex];
            }
        }

        public function rewind()
        {
            $this->_iteratorCurrentIndex = null;
        }

        public function valid()
        {
            return ($this->_iteratorCurrentIndex < count($this->_modelParams->tableMetaData));
        }

        public static function finalize() { }

        public static function initialize()
        {
            $model = get_called_class();

            if ($model::$database === null)
                $model::$database = YPFramework::getDatabase();

            if (ModelBase::$modelParams === null)
                ModelBase::$modelParams = new Object();

            if (isset(ModelBase::$modelParams->{$model}))
                return;

            $settings = get_class_vars($model);
            $params = new Object();
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
                $params->tableName =        (isset($settings['_tableName'])? $settings['_tableName']: strtolower($model.'s'));
                $params->keyFields =        (isset($settings['_keyFields'])? arraize($settings['_keyFields']): array('id'));
            }

            $params->aliasName =        (isset($settings['_aliasName'])? $settings['_aliasName']: null);
            $params->transientFields =  (isset($settings['_transientFields'])? $settings['_transientFields']: array());
            $params->tableCharset =     (isset($settings['_tableCharset'])? strtolower($settings['_tableCharset']): 'utf-8');

            $params->sqlFields =        (isset($settings['_sqlFields'])? $settings['_sqlFields']: array());
            $params->sqlJoins =         (isset($settings['_sqlJoins'])? $settings['_sqlJoins']: array());
            $params->sqlConditions =    (isset($settings['_sqlConditions'])? $settings['_sqlConditions']: array());
            $params->sqlGrouping =      (isset($settings['_sqlGrouping'])? $settings['_sqlGrouping']: array());
            $params->sqlOrdering =      (isset($settings['_sqlOrdering'])? $settings['_sqlOrdering']: array());
            $params->sqlLimit =         (isset($settings['_sqlLimit'])? $settings['_sqlLimit']: null);
            $params->customQueries =    (isset($settings['_queries'])? $settings['_queries']: array());

            $params->relations =        (isset($settings['_relations'])? $settings['_relations']: array());

            $params->validations =      (isset($settings['_validations'])? $settings['_validations']: array());
            foreach ($params->validations as $field)
                foreach ($field as $i=>$validation)
                    if (!is_array($validation))
                        $field[$i] = array('value' => $validation);

            //Callbacks
            $params->beforeLoad =       (isset($settings['_beforeLoad'])? $settings['_beforeLoad']: array());
            $params->beforeDelete =     (isset($settings['_beforeDelete'])? $settings['_beforeDelete']: array());
            $params->beforeSave =       (isset($settings['_beforeSave'])? $settings['_beforeSave']: array());
            $params->beforeCreate =     (isset($settings['_beforeCreate'])? $settings['_beforeCreate']: array());
            $params->beforeUpdate =     (isset($settings['_beforeUpdate'])? $settings['_beforeUpdate']: array());

            $params->afterLoad =        (isset($settings['_afterLoad'])? $settings['_afterLoad']: array());
            $params->afterDelete =      (isset($settings['_afterDelete'])? $settings['_afterDelete']: array());
            $params->afterSave =        (isset($settings['_afterSave'])? $settings['_afterSave']: array());
            $params->afterCreate =     (isset($settings['_afterCreate'])? $settings['_afterCreate']: array());
            $params->afterUpdate =     (isset($settings['_afterUpdate'])? $settings['_afterUpdate']: array());

            $params->relationObjects =  new Object();
            $params->tableMetaData = $model::$database->getTableFields($params->tableName);

            ModelBase::$modelParams->{$model} = $params;
            $params->modelQuery = new ModelQuery($model::$database, $model);
        }

        public static function install()
        {
            $model = get_called_class();

            if ($model::$database === null)
                $model::$database = YPFramework::getDatabase();

            $settings = get_class_vars($model);

            if (isset($settings['_schema']))
                $model::$database->install($settings['_schema']);
        }
    }
?>
