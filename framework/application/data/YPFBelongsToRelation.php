<?php
    class YPFBelongsToRelation extends YPFModelBaseRelation {
        protected function __construct($relatorModelName, $relationName, $relationParams) {
            $this->relationName = $relationName;
            $this->relationParams = $relationParams;

            $this->relatorModelName = $relatorModelName;
            $this->relatorModelParams = YPFModelBase::getModelParams($relatorModelName);

            $this->relatedModelName = $this->relationParams['belongs_to'];
            $this->relatedModelParams = YPFModelBase::getModelParams($this->relatedModelName);

            if ($this->relatedModelParams === null)
                throw new ErrorDataModel ($this->relatedModelName, 'Model not defined.');

            $model = $this->relatedModelName;
            $this->baseModelQuery = $model::all();

            if (isset($relationParams['alias'])) {
                $this->tableAlias = $relationParams['alias'];
                $this->baseModelQuery = $this->baseModelQuery->alias($this->tableAlias);
            }
        }

        public function get($relatorModel)
        {
            if (!$this->inCache($relatorModel)) {
                $whereConditions = array();

                $aliasPrefix = ($this->tableAlias !== null)? $this->tableAlias: $this->relatedModelParams->tableName;

                foreach($this->relationParams['keys'] as $index=>$key)
                    $whereConditions[sprintf('`%s`.`%s`', $aliasPrefix, $this->relatedModelParams->keyFields[$index])] = $relatorModel->{$key};

                $this->setCache($relatorModel, $this->baseModelQuery->where($whereConditions)->first());
            }

            return $this->getCache($relatorModel);
        }

        public function set($relatorModel, $value)
        {
            $relatorKey = $relatorModel->getSerializedKey();

            if (is_string($value))
                $value = eval(sprintf('return %s::find($value);', $this->relatedModelName));

            $this->setCache($relatorModel, $value);

            if ($value === null)
                foreach($this->relationParams['keys'] as $index=>$key)
                    $relatorModel->{$key} = null;
            elseif (!($value instanceof $this->relatedModelName))
                throw new ErrorDataModel($this->relatorModelName, sprintf('Can\'t assign values to belongs to relation: %s because object is not instance of %s', $this->relationName, $this->relatedModelName));
            else
                foreach($this->relationParams['keys'] as $index=>$key)
                    $relatorModel->{$key} = $value->{$this->relatedModelParams->keyFields[$index]};
        }

        public function includeInQuery(YPFModelQuery $query) {
            if ($this->tableAlias) {
                $joinedTableName = sprintf('`%s` AS `%s`', $this->relatedModelParams->tableName, $this->tableAlias);
                $joinedAliasPrefix = $this->tableAlias;
            } elseif ($this->relatedModelParams->tableAlias) {
                $joinedTableName = sprintf('`%s` AS `%s`', $this->relatedModelParams->tableName, $this->relatedModelParams->tableAlias);
                $joinedAliasPrefix = $this->relatedModelParams->tableAlias;
            } else {
                $joinedTableName = sprintf('`%s`', $this->relatedModelParams->tableName);
                $joinedAliasPrefix = $this->relatedModelParams->tableName;
            }

            if ($this->relatorModelParams->tableAlias)
                $joiningAliasPrefix = $this->relatorModelParams->tableAlias;
            else
                $joiningAliasPrefix = $this->relatorModelParams->tableName;

            $joinConditions = array();

            foreach($this->relationParams['keys'] as $index=>$key)
                $joinConditions[] = sprintf('`%s`.`%s` = `%s`.`%s`',
                                                $joiningAliasPrefix, $key,
                                                $joinedAliasPrefix, $this->relatedModelParams->keyFields[$index]);

            return $query->join($joinedTableName, $joinConditions);
        }
    }
?>
