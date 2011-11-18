<?php
    interface IYPFModelQuery
    {
        public function sum($expression);
        public function max($expression);
        public function min($expression);
        public function value($expression);
        public function fields($fields);
        public function count();
        public function first();
        public function last();
        public function all();
        public function where($sqlConditions);
        public function join($table, $conditions);
        public function alias($alias);
        public function orderBy($sqlOrdering);
        public function groupBy($sqlGrouping);
        public function limit($limit);
    }
?>
