<?php
    class YPFHasOneRelation extends YPFModelBaseRelation {
        protected function __construct($relatorModelName, $relationName, $relationParams) {
            $this->relationName = $relationName;
            $this->relationParams = $relationParams;

            $this->relatorModelName = $relatorModelName;
            $this->relatorModelParams = YPFModelBase::getModelParams($relatorModelName);

            $this->relatedModelName = $this->relationParams['has_one'];
            $this->relatedModelParams = YPFModelBase::getModelParams($this->relatedModelName);

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
                {
                    if (is_numeric($index))
                        $whereConditions[sprintf('%s.%s', $this->relationName, $key)] = $relatorModel->__get($this->relatorModelParams->keyFields[$index]);
                    else
                        $whereConditions[sprintf('%s.%s', $this->relationName, $key)] = $relatorModel->{$index};
                }

                $this->setCache($relatorModel, $this->baseModelQuery->where($whereConditions)->first());
            }

            return $this->getCache($relatorModel);
        }

        public function set($relatorModel, $value)
        {
            if (is_string($value))
                $value = eval(sprintf('return %s::find($value);', $this->relatedModelName));

            $this->setCache($relatorModel, $value);

            if ($value === null)
                throw new ErrorDataModel ($this->relatorModelName, 'Unsupported functionality');
            elseif (!($value instanceof $this->relatedModelName))
                throw new ErrorDataModel($this->relatorModelName, sprintf('Can\'t assign values to has_one relation: %s because object is not instance of %s', $this->relationName, $this->relatedModelName));
            else
                foreach($this->relationParams['keys'] as $index=>$key)
                        $value->{$key} = $relatorModel->{$this->relatorModelParams->keyFields[$index]};
        }
    }
?>
