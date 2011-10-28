<?php
    class HasManyRelationThrough extends HasManyRelation {
        protected $joinerTable;

        protected function __construct($relatorModelName, $relationName, $relationParams) {
            parent::__construct($relatorModelName, $relationName, $relationParams);

            $this->joinerTable = $relationParams['through'];

            $joinConditions = array();
            foreach($this->relationParams['relatedKeys'] as $index=>$key)
                $joinConditions[] = sprintf('(%s.%s = %s.%s)',
                    $this->relationName, $index,
                    $this->joinerTable, $key);

            $this->baseModelQuery = $this->baseModelQuery->join($this->joinerTable, $joinConditions);
        }

        public function set($relatorModel, $value) {
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
                        $item = $class::find($ikey);
                        if (!$item)
                            $item = new $class();
                    }

                    if ($idata['_delete']) {
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

        public function tieToRelator($relatorModel) {
            $this->relatorInstance = $relatorModel;
            $relatorKey = $relatorModel->getSerializedKey();

            $whereConditions = array();

            foreach($this->relationParams['relatorKeys'] as $index=>$key)
                $whereConditions[] = sprintf('(%s.%s = %s)',
                    $this->joinerTable, $key,
                    ModelBase::getFieldSQLRepresentation($index, $relatorModel->__get($index), $this->relatorModelParams));

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
                    $result = $result && $item->save() && $this->add($item);
                }

                return $result;
            } else
                return true;
        }

        public function create($values = array()) {
            $newInstanace = eval(sprintf('return new %s()', $this->relatedModelName));

            foreach ($values as $key=>$value)
                $newInstanace->{$key} = $value;

            return $newInstanace;
        }

        public function add($newInstance) {
            $model = $this->relatorModelName;

            $fields = array();
            $values = array();
            foreach($this->relationParams['relatorKeys'] as $index=>$key) {
                $fields[] = $key;
                $values[] = ModelBase::getFieldSQLRepresentation($index, $this->relatorInstance->__get($index), $this->relatorModelParams);
            }
            foreach($this->relationParams['relatedKeys'] as $index=>$key) {
                $fields[] = $key;
                $values[] = ModelBase::getFieldSQLRepresentation($index, $newInstance->__get($index), $this->relatedModelParams);
            }

            $sql = sprintf('INSERT INTO %s (%s) VALUES(%s)', $this->joinerTable, implode(', ', $fields), implode(', ', $values));
            return $model::getDB()->command($sql);
        }
    }
?>