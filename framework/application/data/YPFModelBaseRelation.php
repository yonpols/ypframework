<?php
    abstract class YPFModelBaseRelation implements YPFSerializable
    {
        protected $relationName;
        protected $relationParams;

        protected $relatorModelName;
        protected $relatedModelName;
        protected $relatorModelParams;
        protected $relatedModelParams;

        protected $baseModelQuery;

        protected $cache = array();

        protected $tableAlias;

        public static function getFor($relatorModelName, $relationName, $relationParams) {
            if (isset($relationParams['belongs_to']))
                return new YPFBelongsToRelation($relatorModelName, $relationName, $relationParams);
            elseif (isset($relationParams['has_one']))
                return new YPFHasOneRelation($relatorModelName, $relationName, $relationParams);
            elseif (isset($relationParams['has_many'])) {
                if (isset($relationParams['through']))
                    return new YPFHasManyThroughRelation($relatorModelName, $relationName, $relationParams);
                else
                    return new YPFHasManyRelation($relatorModelName, $relationName, $relationParams);
            }
        }

        public function __toJSON() {
            return $this->baseModelQuery->__toJSON();
        }

        public function __toXML($xmlParent = null) {
            return $this->baseModelQuery->__toXML($xmlParent);
        }

        public function __toJSONRepresentable() {
            return $this->baseModelQuery;
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

        protected function inCache($keyObject) {
            return isset($this->cache[$keyObject->getObjectId()]); //&& ($this->cache[$key]['used'] < 10);
        }

        protected function getCache($keyObject) {
            $key = $keyObject->getObjectId();
            $this->cache[$key]['used']++;
            return $this->cache[$key]['item'];
        }

        protected function setCache($keyObject, $item) {
            $this->cache[$keyObject->getObjectId()] = array('used' => 0, 'item' => $item);
        }
    }
?>
