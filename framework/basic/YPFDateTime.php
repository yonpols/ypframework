<?php
    class YPFDateTime extends DateTime {
        private $dbtype = null;

        public static function createFromDB ($type, $time) {
            if ($time === null)
                return null;
            if ($type == 'date')
                $date = DateTime::createFromFormat('Y-m-d', $time);
            elseif ($type == 'time')
                $date = DateTime::createFromFormat('H:i:s', $time);
            else
                $date = DateTime::createFromFormat('Y-m-d H:i:s', $time);

            if ($date === false)
                return null;

            $instance = new YPFDateTime;
            $instance->setTimestamp($date->getTimestamp());
            $instance->setTimezone($date->getTimezone());

            $instance->dbtype = $type;
            return $instance;
        }

        public static function createFromLocal ($type, $time) {
            if ($time === null)
                return null;
            if ($type == 'date')
                $date = DateTime::createFromFormat('d/m/Y', $time);
            elseif ($type == 'time') {
                $date = DateTime::createFromFormat('H:i:s', $time);
                if (!$date)
                    $date = DateTime::createFromFormat('H:i', $time);
            }
            else {
                $date = DateTime::createFromFormat('d/m/Y H:i:s', $time);
                if (!$date)
                    $date = DateTime::createFromFormat('Y-m-d H:i', $time);
            }

            if ($date === false)
                return null;

            $instance = new YPFDateTime;
            $instance->setTimestamp($date->getTimestamp());
            $instance->setTimezone($date->getTimezone());

            $instance->dbtype = $type;
            return $instance;
        }

        public static function now($withDate = true) {
            $instance = new YPFDateTime;
            $instance->dbtype = $withDate? 'datetime': 'time';
            return $instance;
        }

        public static function today() {
            $instance = new YPFDateTime;
            $instance->dbtype = 'date';
            return $instance;
        }

        public function plus($string) {
            $date = clone $this;
            $date->add(new DateInterval($string));
            return $date;
        }

        public function minus($string) {
            $date = clone $this;
            $date->sub(new DateInterval($string));
            return $date;
        }

        public function set($value) {
            $date = YPFDateTime::createFromLocal($this->dbtype, $value);
            $this->setTimestamp($date->getTimestamp());
            $this->setTimezone($date->getTimezone());
        }

        public function getType() {
            return $this->dbtype;
        }

        public function __toString() {
            if ($this->dbtype == 'date')
                return $this->format ('d/m/Y');
            elseif ($this->dbtype == 'time')
                return $this->format ('H:i:s');
            else
                return $this->format ('d/m/Y H:i:s');
        }

        public function __toDBValue() {
            if ($this->dbtype == 'date')
                return $this->format ('Y-m-d');
            elseif ($this->dbtype == 'time')
                return $this->format ('H:i:s');
            else
                return $this->format ('Y-m-d H:i:s');
        }
    }
?>
