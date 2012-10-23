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
         * Gets/sets cached files in comparison with its modification date. This
         * function is useful with entire files that need to be cached (processed resources)
         *
         * @param string $originalFileName name of the original file to be cached
         * @param string $dataFileName path of the file to be cached
         * @return mixed returns false when cache is disabled, data is not cached or file did not change. returns a file path if a cached file exists
         */
        public static function entireFile($originalFileName, $dataFileName = null) {
            if (!YPFramework::getSetting('application.cache', true))
                return false;

            if (defined('YPF_CMD'))
                return false;

            $cache_file = YPFramework::getFileName(self::$fileBased, md5($originalFileName)."-".basename($originalFileName));

            if ($dataFileName !== null)
            {
                copy($dataFileName, $cache_file);
                Logger::framework('INFO:CACHE', "$dataFileName loaded to cache: $cache_file");
                return $cache_file;
            } else {
                if (!is_file($cache_file))
                    return false;
                elseif (filemtime($originalFileName) > filemtime($cache_file))
                    return false;
                else
                    return $cache_file;
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
        public static function fileBased($fileName, $value = null, $serialize = true, $sub_section = '') {
            if (!YPFramework::getSetting('application.cache', true))
                return false;

            if (defined('YPF_CMD'))
                return false;

            $cache_file = md5($fileName.$sub_section)."-".basename($fileName);

            if (function_exists('apc_store')) {
                $cache_mod = $cache_file.'-modification';
                if ($value !== null) {
                    apc_store($cache_file, $value);
                    apc_store($cache_mod, time());
                    Logger::framework('INFO:CACHE', "$fileName loaded to cache: $cache_file");
                } else {
                    $date = apc_fetch($cache_mod);
                    if (filemtime($fileName) > $date)
                        return false;
                    else
                        return apc_fetch ($cache_file);
                }
            } else {
                $cache_file = YPFramework::getFileName(self::$fileBased, $cache_file);

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

            $cache_file = md5($name)."-".basename($name);

            if (function_exists('apc_store')) {
                if ($value !== null) {
                    apc_store($cache_file, $value, $duration);
                    Logger::framework('INFO:CACHE', "$name loaded to cache: $cache_file");
                } else {
                    return apc_fetch ($cache_file);
                }
            } else {
                $cache_file = YPFramework::getFileName(self::$timeBased, $cache_file);

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
    }
?>
