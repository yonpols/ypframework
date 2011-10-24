<?php
    abstract class ModelBaseRelation
    {
        protected $relationName;
        protected $relationParams;

        protected $relatorModelName;
        protected $relatedModelName;
        protected $relatorModelParams;
        protected $relatedModelParams;

        protected $baseModelQuery;

        protected $cache = array();

        public static function getFor($relatorModelName, $relationName, $relationParams) {
            if (isset($relationParams['belongs_to']))
                return new BelongsToRelation($relatorModelName, $relationName, $relationParams);
            elseif (isset($relationParams['has_one']))
                return new HasOneRelation($relatorModelName, $relationName, $relationParams);
            elseif (isset($relationParams['has_many'])) {
                if (isset($relationParams['through']))
                    return new HasManyThroughRelation($relatorModelName, $relationName, $relationParams);
                else
                    return new HasManyRelation($relatorModelName, $relationName, $relationParams);
            }
        }

        public abstract function get($relatorModel);

        public abstract function set($relatorModel, $value);

        public function getRelationName() {
            return $this->relationName;
        }

        public function getRelatorModelName() {
            return $this->relatorModelName;
        }

        public function getRelatedModelName() {
            return $this->relatedModelName;
        }

        protected function inCache($key) {
            return isset($this->cache[$key]); //&& ($this->cache[$key]['used'] < 10);
        }

        protected function getCache($key) {
            $this->cache[$key]['used']++;
            return $this->cache[$key]['item'];
        }

        protected function setCache($key, $item) {
            $this->cache[$key] = array('used' => 0, 'item' => $item);
        }
    }
?>
