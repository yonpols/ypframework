<?php
    class Cache extends Object implements Initializable
    {
        protected static $cachePath;
        protected static $fileBased;

        public static function initialize() {
            self::$cachePath = YPFramework::getFileName(APP_PATH, 'tmp/cache');
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

        public static function fileBased($fileName, $value = null) {
            $cache_file = YPFramework::getFileName(self::$fileBased, md5($fileName)."-".basename($fileName));

            if ($value !== null)
            {
                $value = serialize($value);
                file_put_contents($cache_file, $value);
                Logger::framework('INFO:CACHE', "$fileName loaded to cache: $cache_file");
            } else {
                if (!is_file($cache_file))
                    return false;
                elseif (filemtime($fileName) > filemtime($cache_file))
                    return false;
                else
                    return unserialize (file_get_contents($cache_file));
            }
        }
    }
?>
