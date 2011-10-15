<?php
    class ModelQuery extends Object implements Iterator, IModelQuery
    {
        private static $modelCaches = array();

        protected $database;

        protected $modelName;
        protected $modelParams;
        protected $tableName;
        protected $aliasName = '';

        protected $sqlFields = array();
        protected $sqlJoins = array();
        protected $sqlConditions = array();
        protected $sqlGrouping = array();
        protected $sqlOrdering = array();
        protected $sqlLimit = array();

        protected $customQueries;

        private $_count = null;
        private $_query = null;

        public function __construct($database, $modelName = null)
        {
            if (!($database instanceof DataBase))
                throw new ErrorException('Database not supplied to query');
            if ($database == '')
                throw new ErrorException('Tablename not supplied to query');

            $this->database = $database;
            $this->modelName = $modelName;
            $this->modelParams = Model::getModelParams($modelName);

            $this->tableName = $this->modelParams->tableName;
            $this->aliasName = $this->modelParams->aliasName;
            $this->sqlFields = $this->modelParams->sqlFields;
            $this->sqlConditions = $this->modelParams->sqlConditions;
            $this->sqlGrouping = $this->modelParams->sqlGrouping;
            $this->sqlJoins = $this->modelParams->sqlJoins;
            $this->sqlOrdering = $this->modelParams->sqlOrdering;
            $this->sqlLimit = $this->modelParams->sqlLimit;
            $this->customQueries = $this->modelParams->customQueries;

            if (!isset(self::$modelCaches[$modelName]))
                self::$modelCaches[$modelName] = array();
        }

        public function fields($fields)
        {
            if (is_string($fields))
                $fields = array($fields);

            $query = $this->copy();
            $query->sqlFields = $fields;
            return $query;
        }

        public function count()
        {
            if ($this->_count === null)
                $this->_count = $this->database->value($this->sql('COUNT(*)'));

            return $this->_count;
        }

        public function first()
        {
            $query = $this->limit(1)->query();
            $row = $query->getNext();
            return $this->getModelInstance($row);
        }

        public function last()
        {
            $count = $this->count();
            $query = $this->limit(array($count-1, 1))->query();
            $row = $query->getNext();
            return $this->getModelInstance($row);
        }

        public function toArray()
        {
            $result = array();
            foreach ($this as $instance)
                $result[] = $instance;

            return $result;
        }

        //Devuelven listado de valores
        public function all()
        {
            $this->_query = null;
            return $this;
        }

        public function where($sqlConditions)
        {
            if (is_string($sqlConditions))
                $sqlConditions = array($sqlConditions);

            $query = $this->copy();
            $query->sqlConditions = array_merge($this->sqlConditions, $sqlConditions);
            return $query;
        }

        public function join($table, $conditions)
        {
            if (is_string($conditions))
                $conditions = array($conditions);

            $query = $this->copy();
            $query->sqlJoins[] = sprintf(' JOIN %s ON %s', $table, implode(' AND ', $conditions));
            return $query;
        }

        public function alias($alias) {
            $query = $this->copy();
            $query->aliasName = $alias;
            return $query;
        }

        public function orderBy($sqlOrdering)
        {
            if (is_string($sqlOrdering))
                $sqlOrdering = array($sqlOrdering);

            $query = $this->copy();
            $query->sqlOrdering = array_merge($this->sqlOrdering, $sqlOrdering);
            return $query;
        }

        public function groupBy($sqlGrouping)
        {
            if (is_string($sqlGrouping))
                $sqlGrouping = array($sqlGrouping);

            $query = $this->copy();
            $query->sqlGrouping = array_merge($this->sqlGrouping, $sqlGrouping);
            return $query;
        }

        public function limit($sqlLimit)
        {
            $query = $this->copy();
            $query->sqlLimit = $sqlLimit;
            return $query;
        }

        public function __get($name)
        {
            if (isset($this->customQueries[$name]))
                return $this->processCustomQuery($name);
        }

        public function __clone() {
            $this->_count = null;
            $this->_query = null;
        }

        public function setCustomQueries($customQueries)
        {
            $this->customQueries = $customQueries;
        }

        protected function getModelInstance($row)
        {
            if ($row == false)
                return null;

            $key = array();
            foreach ($this->modelParams->keyFields as $k)
            {
                $v = $row[$k];
                $key[] = Model::encodeKey($v);
            }
            $key = implode('|', $key);

            if (!isset(self::$modelCaches[$this->modelName][$key]))
                self::$modelCaches[$this->modelName][$key] = eval(sprintf('return new %s();', $this->modelName));

            $instance = self::$modelCaches[$this->modelName][$key];
            $instance->loadFromRecord($row);

            return $instance;
        }

        private function processCustomQuery($name)
        {
            $query = $this->customQueries[$name];
            $others = array_diff($this->customQueries, array($name=>$query));

            $new = $this->copy();
            $new->customQueries = $others;

            if (isset($query['sqlConditions']))
                $new = $new->where($query['sqlConditions']);

            if (isset($query['sqlGrouping']))
                $new = $new->groupBy($query['sqlGrouping']);

            if (isset($query['sqlOrdering']))
                $new = $new->orderBy($query['sqlOrdering']);

            if (isset($query['sqlJoins']))
                $new->sqlJoins = array_merge($new->sqlJoins, $query['sqlJoins']);

            if (isset($query['sqlLimit']))
                $new = $new->limit($query['sqlLimit']);

            if (isset($query['sqlFields']))
                $new = $new->fields($query['sqlFields']);

            if (isset($query['action']))
            {
                switch($query['action'])
                {
                    case 'first':
                        return $new->first();
                    case 'last':
                        return $new->last();
                    case 'count':
                        return $new->count();
                }
            } else
                return $new;
        }

        private function copy()
        {
            $copy = clone $this;
            return $copy;
        }

        public function sql($fields = null)
        {
            if ($fields === null)
                $fields = implode(', ', $this->sqlFields);
            elseif (is_array($fields))
                $fields = implode(', ', $this->fields);

            $table = $this->tableName;
            if ($this->aliasName != '')
                $table = sprintf('%s AS %s', $table, $this->aliasName);

            if ($fields == '')
                $fields = sprintf('%s.*', ($this->aliasName != '')? $this->aliasName: $this->tableName);

            $where = (count($this->sqlConditions) > 0)? ' WHERE '.implode(' AND ', $this->sqlConditions): '';
            $joins = implode(' ', $this->sqlJoins);
            $group = (count($this->sqlGrouping) > 0)? ' GROUP BY '.implode(', ', $this->sqlGrouping): '';
            $order = (count($this->sqlOrdering) > 0)? ' ORDER BY '.implode(', ', $this->sqlOrdering): '';
            $limit = ($this->sqlLimit !== null)? ' LIMIT '.(is_array($this->sqlLimit)? implode(',', array_slice($this->sqlLimit, 0, 2)): $this->sqlLimit): '';

            return "SELECT $fields FROM $table$joins$where$group$order$limit";
        }

        private function query()
        {
            $sql = $this->sql();
            $query = $this->database->query($sql);
            if ($query === false)
                throw new ErrorDataModel(null, 'You have an error in your query: '.$sql);

            if (!isset($this->modelParams->tableMetaData))
                $this->modelParams->tableMetaData = $query->getFieldsInfo();

            return $query;
        }
        // ----------- Iterator Implementation --------------------------------
        private $_iteratorCurrentInstance = null;
        private $_iteratorCurrentIndex = null;
        private $_iteratorKeys = null;

        public function current()
        {
            if ($this->_query == null)
                $this->next();

            return $this->_iteratorCurrentInstance;
        }

        public function key()
        {
            if ($this->_query == null)
                $this->next();

            return $this->_iteratorCurrentIndex;
        }

        public function next()
        {
            if ($this->_query === null)
            {
                $this->_query = $this->query();
                $this->_iteratorCurrentIndex = -1;
            }

            if (($row = $this->_query->getNext()))
            {
                $this->_iteratorCurrentIndex++;
                $this->_iteratorCurrentInstance = $this->getModelInstance($row);
            } else
                $this->_iteratorCurrentIndex = -1;
        }

        public function rewind()
        {
            $this->_query = null;
            $this->_iteratorCurrentIndex = null;
        }

        public function valid()
        {
            if ($this->_iteratorCurrentIndex === null)
                $this->next ();

            return ($this->_iteratorCurrentIndex !== -1);
        }
    }
?>
