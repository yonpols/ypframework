<?php
    class YPFCache extends YPFObject {
        protected static $cachePath;
        protected static $fileBased;
        protected static $timeBased;
        protected static $active = true;

        public static function initialize() {
            if (defined('YPF_CMD'))
                return false;

            self::$cachePath = YPFramework::getFileName(APP_PATH, 'support/tmp/cache');
            if (!is_dir(self::$cachePath))
                mkdir (self::$cachePath, 0775, true);

            self::$fileBased = YPFramework::getFileName(self::$cachePath,'file_based');
            self::$timeBased = YPFramework::getFileName(self::$cachePath,'time_based');

            if (!is_dir(self::$fileBased))
                mkdir (self::$fileBased, 0775, true);
        }

        public static function finalize() { }

        /**
         * Deletes all cached files
         */
        public static function invalidate() {
            if (is_dir(self::$fileBased))
            {
                $dir = opendir(self::$fileBased);
                while ($file = readdir($dir)) {
                    @unlink(YPFramework::getFileName(self::$fileBased, $file));
                }
            }
        }

        /**
         * Gets/sets data in comparison with a file modification date. This
         * function is useful with data saved in files.
         *
         * @param string $fileName name of the original file where data is taken of
         * @param mixed $value data to be set based on the filename passed
         * @param boolean $serialize serialize data sent in $value
         * @return mixed returns false when cache is disabled, data is not cached or file did not change.
         */
        public static function fileBased($fileName, $value = null, $serialize = true) {
            if (!YPFramework::getSetting('application.cache', true))
                return false;

            if (defined('YPF_CMD'))
                return false;

            $cache_file = YPFramework::getFileName(self::$fileBased, md5($fileName)."-".basename($fileName));

            if ($value !== null)
            {
                if ($serialize) $value = serialize($value);
                file_put_contents($cache_file, $value);
                Logger::framework('INFO:CACHE', "$fileName loaded to cache: $cache_file");
            } else {
                if (!is_file($cache_file))
                    return false;
                elseif (filemtime($fileName) > filemtime($cache_file))
                    return false;
                elseif ($serialize)
                    return unserialize (file_get_contents($cache_file));
                else
                    return file_get_contents($cache_file);
            }
        }

        /**
         * Gets/sets data in comparison with a period of time. This
         * function is useful with data being cached for a period of time.
         *
         * @param string $name name of the data
         * @param int $duration duration of the cache in seconds
         * @param mixed $value data to be set based on the filename passed
         * @param boolean $serialize serialize data sent in $value
         * @return mixed returns false when cache is disabled, data is not cached or it expired
         */
        public static function timeBased($name, $duration, $value = null, $serialize = true) {
            if (!YPFramework::getSetting('application.cache', true))
                return false;

            if (defined('YPF_CMD'))
                return false;

            $cache_file = YPFramework::getFileName(self::$fileBased, md5($name)."-".  YPFramework::normalize($name));

            if ($value !== null)
            {
                if ($serialize) $value = serialize($value);
                file_put_contents($cache_file, $value);
                Logger::framework('INFO:CACHE', "$name loaded to cache: $cache_file");
            } else {
                if (!is_file($cache_file))
                    return false;
                elseif ( (time()-filemtime($cache_file)) > $duration )
                    return false;
                elseif ($serialize)
                    return unserialize (file_get_contents($cache_file));
                else
                    return file_get_contents($cache_file);
            }
        }
    }
?>
