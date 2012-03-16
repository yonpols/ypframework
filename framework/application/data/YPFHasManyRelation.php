<?php
    class YPFHasManyRelation extends YPFModelBaseRelation implements IYPFModelQuery, Iterator {

        protected $acceptsNested;
        protected $customQueries;

        protected $tiedModelQuery;
        protected $relatorInstance;

        protected $iterationQuery = null;
        protected $iterationKeys = null;
        protected $iterationPos = 0;

        protected function __construct($relatorModelName, $relationName, $relationParams) {
            $this->relationName = $relationName;
            $this->relationParams = $relationParams;

            $this->relatorModelName = $relatorModelName;
            $this->relatorModelParams = YPFModelBase::getModelParams($relatorModelName);

            $this->relatedModelName = $this->relationParams['has_many'];
            $this->relatedModelParams = YPFModelBase::getModelParams($this->relatedModelName);

            if (isset($this->relationParams['accepts_nested']))
                $this->acceptsNested = $this->relationParams['accepts_nested'];

            if ($this->relatedModelParams === null)
                throw new ErrorDataModel ($this->relatedModelName, 'Model not defined.');

            $sqlConditions = isset($relationParams['sqlConditions'])? arraize($relationParams['sqlConditions']): array();
            $sqlGrouping = isset($relationParams['sqlGrouping'])? arraize($relationParams['sqlGrouping']): array();
            $sqlOrdering = isset($relationParams['sqlOrdering'])? arraize($relationParams['sqlOrdering']): array();
            $this->customQueries = isset($relationParams['queries'])? arraize($relationParams['queries']): array();
            $this->customQueries = array_merge($this->customQueries, $this->relatedModelParams->customQueries);

            $model = $this->relatedModelName;
            $this->baseModelQuery = $model::all()->alias($relationName)->where($sqlConditions)->groupBy($sqlGrouping)->orderBy($sqlOrdering);
            $this->baseModelQuery->setCustomQueries($this->customQueries);
        }

        public function __get($name) {
            if (isset($this->customQueries[$name]))
                return $this->tiedModelQuery->{$name};
        }

        public function toArray() {
            if (is_array($this->iterationQuery))
                return $this->iterationQuery;
            else
                return $this->tiedModelQuery->toArray();
        }

        public function get($relatorModel) {
            $relation = clone $this;
            $relation->tieToRelator($relatorModel);
            return $relation;
        }

        public function has($instance) {
            return ($this->tiedModelQuery->where($instance->getSqlIdConditions($this->tiedModelQuery->getAliasName()))->count() > 0);
        }

        public function set($relatorModel, $value)
        {
            if ($this->acceptsNested) {
                if ($this->inCache($relatorModel))
                    $list = $this->getCache($relatorModel);
                else
                    $list = array();

                $class = $this->relatedModelName;

                foreach ($value as $ikey => $idata) {
                    if (isset($list[$ikey]))
                        $item = $list[$ikey];
                    else {
                        $item = $class::find($idata);
                        if (!$item)
                            $item = new $class();
                    }

                    if (isset($idata['_delete']) && $idata['_delete']) {
                        if (!$item->isNew())
                            $item->delete();
                    } else {
                        $item->setAttributes($idata);

                        if ($item->isNew())
                            $list[$ikey] = $item;
                        else
                            $list[$item->getSerializedKey()] = $item;
                    }
                }

                $this->setCache($relatorModel, $list);
            } else
                throw new ErrorDataModel($this->relatorModelName, sprintf('Can\'t assign values to has_many relation: %s', $this->relationName));
        }

        public function tieToRelator($relatorModel)
        {
            $this->relatorInstance = $relatorModel;
            $relatorKey = $relatorModel->getSerializedKey();

            $whereConditions = array();

            foreach($this->relationParams['keys'] as $index=>$key)
            {
                if (is_numeric($index))
                    $whereConditions[sprintf('%s.%s', $this->relationName, $key)] = $relatorModel->__get($this->relatorModelParams->keyFields[$index]);
                else
                    $whereConditions[sprintf('%s.%s', $this->relationName, $key)] = $relatorModel->{$index};
            }

            $this->tiedModelQuery = $this->baseModelQuery->where($whereConditions);

            if ($this->inCache($relatorModel))
                $this->iterationQuery = $this->getCache($relatorModel);
            else
                $this->iterationQuery = null;
        }

        public  function save() {
            if ($this->acceptsNested && is_array($this->iterationQuery)) {
                $result = true;

                foreach ($this->iterationQuery as $item) {
                    $this->add($item);
                    $result = $result && $item->save();
                }

                return $result;
            } else
                return true;
        }

        public function create($values = array())
        {
            $newInstanace = eval(sprintf('return new %s();', $this->relatedModelName));

            foreach ($values as $key=>$value)
                $newInstanace->{$key} = $value;

            foreach($this->relationParams['keys'] as $index=>$key) {
                $pk = $this->relatorModelParams->keyFields[$index];
                $newInstanace->{$key} = $this->relatorInstance->{$pk};
            }

            return $newInstanace;
        }

        public function add($newInstance)
        {
            foreach($this->relationParams['keys'] as $index=>$key) {
                $pk = $this->relatorModelParams->keyFields[$index];
                $newInstance->{$key} = $this->relatorInstance->{$pk};
            }
        }

        public function __toJSON() {
            parent::__toJSON();
        }

        public function __toJSONRepresentable() {
            return $this->tiedModelQuery->__toJSONRepresentable();
        }

        public function __toXML($xmlParent = null) {
            return $this->tiedModelQuery->__toXML($xmlParent);
        }

        // ----------- ModelQuery Implementation--------------------------------
        public function sum($expression) {
            return $this->tiedModelQuery->sum($expression);
        }

        public function max($expression) {
            return $this->tiedModelQuery->max($expression);
        }

        public function min($expression) {
            return $this->tiedModelQuery->min($expression);
        }

        public function value($expression) {
            return $this->tiedModelQuery->value($expression);
        }

        public function fields($fields)
        {
            return $this->tiedModelQuery->fields($fields);
        }

        public function all()
        {
            return $this->tiedModelQuery->all();
        }

        public function count()
        {
            return $this->tiedModelQuery->count();
        }

        public function first()
        {
            return $this->tiedModelQuery->first();
        }

        public function delete()
        {
            return $this->tiedModelQuery->delete();
        }

        public function groupBy($sqlGrouping)
        {
            return $this->tiedModelQuery->groupBy($sqlGrouping);
        }

        public function having($sqlGroupContions) {
            return call_user_func_array(array($this->tiedModelQuery, 'having'), func_get_args());
        }

        public function last()
        {
            return $this->tiedModelQuery->last();
        }

        public function limit($limit)
        {
            return $this->tiedModelQuery->limit($limit);
        }

        public function orderBy($sqlOrdering)
        {
            return $this->tiedModelQuery->orderBy($sqlOrdering);
        }

        public function where($sqlConditions)
        {
            return call_user_func_array(array($this->tiedModelQuery, 'where'), func_get_args());
        }

        public function join($table, $conditions)
        {
            return $this->tiedModelQuery->join($table, $conditions);
        }

        public function alias($alias)
        {
            return $this->tiedModelQuery->alias($alias);
        }

        // ----------- Iterator Implementation --------------------------------
        public function current()
        {
            if (is_array($this->iterationQuery))
                return $this->iterationQuery[$this->iterationKeys[$this->iterationPos]];
            else
                return $this->iterationQuery->current();
        }

        public function key()
        {
            if (is_array($this->iterationQuery))
                return $this->iterationPos;
            else
                return $this->iterationQuery->key();
        }

        public function next()
        {
            if (is_array($this->iterationQuery))
                $this->iterationPos++;
            else
                $this->iterationQuery->next();
        }

        public function rewind()
        {
            if (is_array($this->iterationQuery)) {
                $this->iterationKeys = array_keys($this->iterationQuery);
                $this->iterationPos = 0;
            } else
                $this->iterationQuery = $this->all();
        }

        public function valid()
        {
            if (is_array($this->iterationQuery))
                return ($this->iterationPos < count($this->iterationQuery));
            else
                return $this->iterationQuery->valid();
        }
    }
?>
