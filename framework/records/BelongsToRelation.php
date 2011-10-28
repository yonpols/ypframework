<?php
    class BelongsToRelation extends ModelBaseRelation {
        protected function __construct($relatorModelName, $relationName, $relationParams) {
            $this->relationName = $relationName;
            $this->relationParams = $relationParams;

            $this->relatorModelName = $relatorModelName;
            $this->relatorModelParams = ModelBase::getModelParams($relatorModelName);

            $this->relatedModelName = $this->relationParams['belongs_to'];
            $this->relatedModelParams = ModelBase::getModelParams($this->relatedModelName);

            if ($this->relatedModelParams === null)
                throw new ErrorDataModel ($this->relatedModelName, 'Model not defined.');

            $model = $this->relatedModelName;
            $this->baseModelQuery = $model::all()->alias($relationName);
        }

        public function get($relatorModel)
        {
            if (!$this->inCache($relatorModel)) {
                $whereConditions = array();

                foreach($this->relationParams['keys'] as $index=>$key)
                    $whereConditions[] = sprintf('(%s.%s = %s)',
                        $this->relationName, $this->relatedModelParams->keyFields[$index],
                        ModelBase::getFieldSQLRepresentation($this->relatedModelParams->keyFields[$index], $relatorModel->{$key}, $this->relatedModelParams));

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
    }
?>
