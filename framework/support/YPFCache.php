<?php
    class YPFCache extends YPFObject
    {
        protected static $cachePath;
        protected static $fileBased;

        public static function initialize() {
            if (defined('YPF_CMD'))
                return false;

            self::$cachePath = YPFramework::getFileName(APP_PATH, 'support/tmp/cache');
            if (!is_dir(self::$cachePath))
                mkdir (self::$cachePath, 0775, true);

            self::$fileBased = YPFramework::getFileName(self::$cachePath,'file_based');

            if (!is_dir(self::$fileBased))
                mkdir (self::$fileBased, 0775, true);
        }

        public static function finalize() { }

        public static function invalidate() {
            if (is_dir(self::$fileBased))
            {
                $dir = opendir(self::$fileBased);
                while ($file = readdir($dir)) {
                    @unlink(YPFramework::getFileName(self::$fileBased, $file));
                }
            }
        }

        public static function fileBased($fileName, $value = null, $serialize = true) {
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
    }
?>
