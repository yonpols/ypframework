<?php
    class ModelBaseRelation implements IModelQuery, Iterator
    {
        protected static $_temporalAlias = 0;

        protected $relationName;
        protected $relationType;
        protected $relationSingle;
        protected $relationParams;

        protected $relatorModelName;
        protected $relatedModelName;
        protected $relatorModelParams;
        protected $relatedModelParams;

        protected $customQueries;

        protected $tempAliasRelator;
        protected $tempAliasRelated;

        protected $baseModelQuery;
        protected $modelQuery;
        protected $relatorInstance;
        protected $iterationQuery = null;
        protected $cache = array();

        public function __construct($relatorModelName, $relationName, $relationParams)
        {
            $this->relatorModelName = $relatorModelName;
            $this->relationName = $relationName;
            $this->relationParams = $relationParams;

            $sqlConditions = isset($relationParams['sqlConditions'])? arraize($relationParams['sqlConditions']): array();
            $sqlGrouping = isset($relationParams['sqlGrouping'])? arraize($relationParams['sqlGrouping']): array();
            $sqlOrdering = isset($relationParams['sqlOrdering'])? arraize($relationParams['sqlOrdering']): array();
            $this->customQueries = isset($relationParams['queries'])? arraize($relationParams['queries']): array();

            if (isset($this->relationParams['belongs_to']))
            {
                $this->relationType = 'belongs_to';
                $this->relationSingle = true;
                $this->relatedModelName = $this->relationParams['belongs_to'];
                $this->relatorModelParams = ModelBase::getModelParams($relatorModelName);
                $this->relatedModelParams = ModelBase::getModelParams($this->relatedModelName);

                if ($this->relatedModelParams === null)
                    throw new ErrorDataModel ($this->relatedModelName, 'Model not defined.');

                $model = $this->relatedModelName;

                $joinConditions = array();
                $this->tempAliasRelator = ($this->relatorModelParams->aliasName!=null)? $this->relatorModelParams->aliasName: $this->relationName.'_table'.(self::$_temporalAlias++);
                $this->tempAliasRelated = ($this->relatedModelParams->aliasName!=null)? $this->relatedModelParams->aliasName: $this->relationName;
                $this->baseModelQuery = $model::all()->alias($this->tempAliasRelated);

                foreach($this->relationParams['keys'] as $index=>$key)
                    $joinConditions[] = sprintf('(%s.%s = %s.%s)',
                        $this->tempAliasRelated, $this->relatedModelParams->keyFields[$index],
                        $this->tempAliasRelator, $key);

                $this->baseModelQuery = $this->baseModelQuery->join(
                    sprintf('%s AS %s', $this->relatorModelParams->tableName, $this->tempAliasRelator),
                    $joinConditions);
            } else
            {
                if (isset($this->relationParams['has_one']))
                {
                    $this->relationType = 'has_one';
                    $this->relatedModelName = $this->relationParams['has_one'];
                    $this->relationSingle = true;
                } else {
                    $this->relationType = 'has_many';
                    $this->relatedModelName = $this->relationParams['has_many'];
                    $this->relationSingle = false;
                }
                $this->relatorModelParams = ModelBase::getModelParams($relatorModelName);
                $this->relatedModelParams = ModelBase::getModelParams($this->relatedModelName);
                $model = $this->relatedModelName;

                if ($this->relatedModelParams === null)
                    throw new ErrorDataModel ($this->relatedModelName, 'Model not defined.');

                $this->tempAliasRelator = ($this->relatorModelParams->aliasName!=null)? $this->relatorModelParams->aliasName: $this->relationName.'_table'.(self::$_temporalAlias++);
                $this->tempAliasRelated = ($this->relatedModelParams->aliasName!=null)? $this->relatedModelParams->aliasName: $this->relationName;

                $this->baseModelQuery = $model::all()->alias($this->tempAliasRelated);

                if (isset($this->relationParams['through']))
                {
                    $tempAliasJoiner = $this->relationName.'_table'.(self::$_temporalAlias++);
                    $this->relationType .= '_through';

                    $joinConditionsRor = array();
                    $joinConditionsRed = array();

                    foreach($this->relationParams['relatorKeys'] as $index=>$key)
                        $joinConditionsRor[] = sprintf('(%s.%s = %s.%s)',
                            $this->tempAliasRelator, $index,
                            $tempAliasJoiner, $key);

                    foreach($this->relationParams['relatedKeys'] as $index=>$key)
                        $joinConditionsRed[] = sprintf('(%s.%s = %s.%s)',
                            $this->tempAliasRelated, $index,
                            $tempAliasJoiner, $key);

                    $this->baseModelQuery = $this->baseModelQuery->join(
                        sprintf('%s AS %s', $this->relationParams['through'], $tempAliasJoiner),
                        $joinConditionsRed)->join(
                        sprintf('%s AS %s', $this->relatorModelParams->tableName, $this->tempAliasRelator),
                        $joinConditionsRor);
                } else
                {
                    $joinConditions = array();
                    foreach($this->relationParams['keys'] as $index=>$key)
                    {
                        if (is_numeric($index))
                            $joinConditions[] = sprintf('(%s.%s = %s.%s)',
                                $this->tempAliasRelated, $key,
                                $this->tempAliasRelator, $this->relatorModelParams->keyFields[$index]);
                        else
                            $joinConditions[] = sprintf('(%s.%s = %s.%s)',
                                $this->tempAliasRelated, $key,
                                $this->tempAliasRelator, $index);
                    }

                    $this->baseModelQuery = $this->baseModelQuery->join(
                        sprintf('%s AS %s', $this->relatorModelParams->tableName, $this->tempAliasRelator),
                        $joinConditions);

                }
            }

            $this->baseModelQuery->setCustomQueries($this->customQueries);
            $this->iterationQuery = $this->baseModelQuery->all();
        }

        public function get($relatorModel)
        {
            if ($this->relationSingle)
            {
                $key = $relatorModel->getSerializedKey();

                if (!$this->inCache($key))
                    $this->setCache($key, $this->baseModelQuery->where($relatorModel->getSQlIdConditions($this->tempAliasRelator))->first());

                return $this->getCache($key);
            }
            else
                return $this->getTiedToRelator($relatorModel);
        }

        public function set($relatorModel, $value)
        {
            if ($this->relationSingle)
            {
                $key = $relatorModel->getSerializedKey();

                if (is_string($value))
                    $value = eval(sprintf('return %s::find($value);', $this->relatedModelName));

                $this->setCache($key, $value);

                if ($value === null)
                {
                    if ($this->relationType == 'belongs_to')
                        foreach($this->relationParams['keys'] as $index=>$key)
                            $relatorModel->{$key} = null;
                    elseif ($this->relationType == 'has_one')
                        throw new ErrorDataModel ($this->relatorModelName, 'Unsupported functionality');
                } elseif (!($value instanceof $this->relatedModelName))
                    throw new ErrorDataModel($this->relatorModelName, sprintf('Can\'t assign values to %s relation: %s because object is not instance of %s', $this->relationType, $this->relationName, $this->relatedModelName));
                elseif ($this->relationType == 'belongs_to')
                    foreach($this->relationParams['keys'] as $index=>$key)
                        $relatorModel->{$key} = $value->{$this->relatedModelParams->keyFields[$index]};
                elseif ($this->relationType == 'has_one')
                    foreach($this->relationParams['keys'] as $index=>$key)
                        $value->{$key} = $relatorModel->{$this->relatorModelParams->keyFields[$index]};
            } else
                throw new ErrorDataModel($this->relatorModelName, sprintf('Can\'t assign values to %s relation: %s', $this->relationType, $this->relationName));
        }

        public function create($values = array())
        {
            if ($this->relationType != 'has_many')
                throw new ErrorDataModel ($this->relatorModelName, sprintf('Can\'t create new instance in %s relation: %s', $this->relationType, $this->relationName));

            $newInstanace = eval(sprintf('return new %s()', $this->relatedModelName));

            foreach ($values as $key=>$value)
                $newInstanace->{$key} = $value;

            foreach($this->relationParams['keys'] as $index=>$key)
                $newInstanace->{$key} = $this->relatorInstance->{$this->relatorModelParams->keyFileds[$index]};

            return $newInstanace;
        }

        public function add($newInstance)
        {
            if ($this->relationType != 'has_many')
                throw new ErrorDataModel($this->relatorModelName, sprintf('Can\'t create new instance in %s relation: %s', $this->relationType, $this->relationName));

            foreach($this->relationParams['keys'] as $index=>$key)
                $newInstanace->{$key} = $this->relatorInstance->{$this->relatorModelParams->keyFileds[$index]};
        }

        public function tieToRelator($relatorModel)
        {
            $this->relatorInstance = $relatorModel;
            $this->iterationQuery = $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator));
        }

        public function getTiedToRelator($relatorModel)
        {
            $relation = clone $this;
            $relation->tieToRelator($relatorModel);
            return $relation;
        }

        public function toArray()
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->toArray();
        }

        public function getRelationName()
        {
            return $this->relationName;
        }

        public function getRelationType()
        {
            return $this->relationType;
        }

        public function getRelatorModelName()
        {
            return $this->relatorModelName;
        }

        public function getRelatedModelName()
        {
            return $this->relatedModelName;
        }

        public function __get($name)
        {
            if (isset($this->customQueries[$name]))
                return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->{$name};
        }

        // ----------- ModelQuery Implementation--------------------------------
        public function fields($fields)
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->fields($fields);
        }

        public function all()
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator));
        }

        public function count()
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->count();
        }

        public function first()
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->first();
        }

        public function groupBy($sqlGrouping)
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->groupBy($sqlGrouping);
        }

        public function last()
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->last();
        }

        public function limit($limit)
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->limit($limit);
        }

        public function orderBy($sqlOrdering)
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->orderBy($sqlOrdering);
        }

        public function where($sqlConditions)
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->where($sqlConditions);
        }

        public function join($table, $conditions)
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->join($table, $conditions);
        }

        public function alias($alias)
        {
            return $this->baseModelQuery->where($this->relatorInstance->getSQlIdConditions($this->tempAliasRelator))->alias($alias);
        }

        // ----------- Iterator Implementation --------------------------------
        public function current()
        {
            return $this->iterationQuery->current();
        }

        public function key()
        {
            return $this->iterationQuery->key();
        }

        public function next()
        {
            $this->iterationQuery->next();
        }

        public function rewind()
        {
            $this->iterationQuery = $this->all();
        }

        public function valid()
        {
            return $this->iterationQuery->valid();
        }

        private function inCache($key)
        {
            return isset($this->cache[$key]) && ($this->cache[$key]['used'] < 10);
        }

        private function getCache($key)
        {
            $this->cache[$key]['used']++;
            return $this->cache[$key]['item'];
        }

        private function setCache($key, $item)
        {
            $this->cache[$key] = array('used' => 0, 'item' => $item);
        }
    }
?>
