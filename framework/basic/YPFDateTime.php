<?php
    class YPFDateTime extends DateTime {
        public static $local_date_format = 'd/m/Y';
        public static $local_time_format = 'H:i:s';
        public static $local_short_time_format = 'H:i';
        public static $local_date_time_format = 'd/m/Y H:i:s';
        public static $local_short_date_time_format = 'd/m/Y H:i';

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
                $date = DateTime::createFromFormat(self::$local_date_format, $time);
            elseif ($type == 'time') {
                $date = DateTime::createFromFormat(self::$local_time_format, $time);
                if (!$date)
                    $date = DateTime::createFromFormat(self::$local_short_time_format, $time);
            }
            else {
                $date = DateTime::createFromFormat(self::$local_date_time_format, $time);
                if (!$date)
                    $date = DateTime::createFromFormat(self::$local_short_date_time_format, $time);
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

        public function getDate() {
            $date = clone $this;
            $date->dbtype = 'date';
            return $date;
        }

        public function getTime() {
            $date = clone $this;
            $date->dbtype = 'time';
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
