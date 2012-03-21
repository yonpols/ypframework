<?php
    class YPFModelQuery extends YPFObject implements Iterator, IYPFModelQuery
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
        protected $sqlGroupContions = array();
        protected $sqlOrdering = array();
        protected $sqlLimit = array();

        protected $conditionsJoiner = ' AND ';

        protected $customQueries;

        private $_query = null;

        public function __construct($database, $modelName = null)
        {
            if (!($database instanceof YPFDataBase))
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
            $this->sqlGroupConditions = $this->modelParams->sqlGroupConditions;
            $this->sqlOrdering = $this->modelParams->sqlOrdering;
            $this->sqlLimit = $this->modelParams->sqlLimit;
            $this->customQueries = $this->modelParams->customQueries;

            if (!isset(self::$modelCaches[$modelName]))
                self::$modelCaches[$modelName] = array();
        }

        public function getAliasName() {
            return $this->aliasName;
        }

        public function getSqlFields() {
            return $this->sqlFields;
        }

        public function getSqlJoins() {
            return $this->sqlJoins;
        }

        public function getSqlConditions() {
            return $this->sqlConditions;
        }

        public function getSqlGrouping() {
            return $this->sqlGrouping;
        }

        public function getSqlOrdering() {
            return $this->sqlOrdering;
        }

        public function getSqlGroupContions() {
            return $this->sqlGroupContions;
        }

        public function getSqlLimit() {
            return $this->sqlLimit;
        }

        public function getModelName() {
            return $this->modelName;
        }

        public function getModelParams() {
            return $this->modelParams;
        }

        public function useOr() {
            $this->conditionsJoiner = ' OR ';
        }

        public function fields($fields) {
            if (is_string($fields))
                $fields = array($fields);

            $query = $this->copy();
            $query->sqlFields = $fields;
            return $query;
        }

        public function count() {
            return $this->database->value($this->sql('COUNT(*)'));
        }

        public function sum($expression) {
            return $this->value(sprintf('SUM(%s)', $expression));
        }

        public function max($expression) {
            return $this->value(sprintf('MAX(%s)', $expression));
        }

        public function min($expression) {
            return $this->value(sprintf('MIN(%s)', $expression));
        }

        public function value($expression) {
            $sql = $this->sql($expression);
            return $this->database->value($sql);
        }

        public function first() {
            $query = $this->limit(1)->query();
            $row = $query->getNext();
            return $this->getModelInstance($row);
        }

        public function last() {
            $count = $this->count();
            $query = $this->limit(array($count-1, 1))->query();
            $row = $query->getNext();
            return $this->getModelInstance($row);
        }

        public function delete() {
            return $this->database->command($this->sql(null, true));
        }

        public function toArray() {
            $result = array();
            foreach ($this as $instance)
                $result[] = $instance;

            return $result;
        }

        //Devuelven listado de valores
        public function all() {
            $this->_query = null;
            return $this;
        }

        public function where($sqlConditions) {
            $query = $this->copy();
            $params = func_num_args();
            if ($params > 1) {
                $pos = 0;
                $param = 0;
                $finalSql = $sqlConditions;
                while (($pos = stripos($finalSql, '?', $pos)) !== false) {
                    $param++;
                    if ($param >= $params)
                        throw new ErrorDataModel ($this->modelName, sprintf('Query: %s has more parameters than specified', $sqlConditions));

                    $value = $this->getSqlRepresentation(func_get_arg($param));
                    $finalSql = substr($finalSql, 0, $pos).$value.substr($finalSql, $pos+1);
                    $pos += strlen($value);
                }
                $query->sqlConditions[] = $finalSql;
            } else {
                if (is_string($sqlConditions))
                    $query->sqlConditions[] = $sqlConditions;
                elseif (is_array($sqlConditions) && !empty($sqlConditions)) {
                    $conditions = array();
                    foreach ($sqlConditions as $field => $value)
                        if (is_int($field))
                            $conditions[] = $value;
                        else
                            $conditions[] = sprintf('(%s = %s)', $field, $this->getSqlRepresentation($value));


                    $query->sqlConditions[] = implode(' AND ', $conditions);
                }
            }

            return $query;
        }

        public function join($table, $conditions) {
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

        public function orderBy($sqlOrdering) {
            if (is_string($sqlOrdering))
                $sqlOrdering = array($sqlOrdering);

            $query = $this->copy();
            $query->sqlOrdering = array_merge($this->sqlOrdering, $sqlOrdering);
            return $query;
        }

        public function groupBy($sqlGrouping) {
            if (is_string($sqlGrouping))
                $sqlGrouping = array($sqlGrouping);

            $query = $this->copy();
            $query->sqlGrouping = array_merge($this->sqlGrouping, $sqlGrouping);
            return $query;
        }

        public function having($sqlGroupContions) {
            $query = $this->copy();

            $params = func_num_args();
            if ($params > 1) {
                $pos = 0;
                $param = 0;
                $finalSql = $sqlGroupContions;
                while (($pos = stripos($finalSql, '?', $pos)) !== false) {
                    $param++;
                    if ($param >= $params)
                        throw new ErrorDataModel ($this->modelName, sprintf('Query: %s has more parameters than specified', $sqlGroupContions));

                    $value = $this->getSqlRepresentation(func_get_arg($param));
                    $finalSql = substr($finalSql, 0, $pos).$value.substr($finalSql, $pos+1);
                    $pos += strlen($value);
                }
                $query->sqlGroupContions[] = $finalSql;
            } else {
                if (is_string($sqlGroupContions))
                    $query->sqlGroupContions[] = $sqlGroupContions;
                elseif (is_array($sqlGroupContions) && !empty($sqlGroupContions)) {
                    $conditions = array();
                    foreach ($sqlGroupContions as $field => $value)
                        if (is_int($field))
                            $conditions[] = $value;
                        else
                            $conditions[] = sprintf('(%s = %s)', $field, $this->getSqlRepresentation($value));


                    $query->sqlGroupContions[] = implode(' AND ', $conditions);
                }
            }

            return $query;
        }

        public function limit($sqlLimit) {
            $query = $this->copy();
            $query->sqlLimit = $sqlLimit;
            return $query;
        }

        public function __get($name) {
            if (isset($this->customQueries[$name]))
                return $this->processCustomQuery($name);
        }

        public function __clone() {
            $this->_count = null;
            $this->_query = null;
        }

        public function setCustomQueries($customQueries) {
            $this->customQueries = $customQueries;
        }

        protected function getModelInstance($row) {
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

        private function getSqlRepresentation($value) {
            if (is_null($value))
                return 'NULL';
            elseif (is_bool($value))
                return ($value)? '1': '0';
            elseif (is_double($value))
                return sprintf("%.F", $value);
            elseif (is_int($value))
                return sprintf("%d", $value);
            elseif (is_numeric($value))
            {
                if (floor($value) == $value)
                    return sprintf("%d", $value);
                else
                    return sprintf("%.F", $value);
            }
            elseif (is_object($value)) {
                if ($value instanceof Model)
                    return sprintf("'%s'", $this->database->sqlEscaped($value->getSerializedKey()));
                elseif ($value instanceof YPFDateTime)
                    return sprintf("'%s'", $this->database->sqlEscaped($value->__toDBValue()));
                else
                    return sprintf("'%s'", $this->database->sqlEscaped($value->__toString()));
            } else
                return sprintf("'%s'", $this->database->sqlEscaped($value));
        }

        private function processCustomQuery($name) {
            $query = $this->customQueries[$name];

            $new = $this->copy();
            unset($new->customQueries[$name]);

            if (isset($query['sqlConditions']))
                $new = $new->where($query['sqlConditions']);

            if (isset($query['sqlGrouping']))
                $new = $new->groupBy($query['sqlGrouping']);

            if (isset($query['sqlOrdering']))
                $new = $new->orderBy($query['sqlOrdering']);

            if (isset($query['sqlGroupConditions']))
                $new = $new->having($query['sqlGroupConditions']);

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

        private function copy() {
            $copy = clone $this;
            return $copy;
        }

        public function sql($fields = null, $delete = false) {
            $table = $this->tableName;
            if ($this->aliasName != '')
                $table = sprintf('%s AS %s', $table, $this->aliasName);


            if (!$delete) {
                if ($fields === null)
                    $fields = implode(', ', $this->sqlFields);
                elseif (is_array($fields))
                    $fields = implode(', ', $this->fields);

                if ($fields == '')
                    $fields = sprintf('%s.*', ($this->aliasName != '')? $this->aliasName: $this->tableName);

                $joins = implode(' ', $this->sqlJoins);
                $group = (count($this->sqlGrouping) > 0)? ' GROUP BY '.implode(', ', $this->sqlGrouping): '';
                $having = (count($this->sqlGroupContions) > 0)? ' HAVING '.implode(' AND ', $this->sqlGroupContions): '';
                $command = 'SELECT ';
            } else {
                $fields = '';
                $group = '';
                $having = '';
                $command = 'DELETE ';
            }

            $where = (count($this->sqlConditions) > 0)? ' WHERE '.implode($this->conditionsJoiner, $this->sqlConditions): '';
            $order = (count($this->sqlOrdering) > 0)? ' ORDER BY '.implode(', ', $this->sqlOrdering): '';
            $limit = ($this->sqlLimit !== null)? ' LIMIT '.(is_array($this->sqlLimit)? implode(',', array_slice($this->sqlLimit, 0, 2)): $this->sqlLimit): '';

            return "$command$fields FROM $table$joins$where$group$having$order$limit";
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
