<?php
    class Mime {
        private static $mimes = array(
            '*/*' => array('*'),
            'text/html' => array('html', 'htm'),
            'application/xhtml+xml' => array('xhtml'),
            'application/ecmascript' => array('js')
        );
        private static $extensions = array(
            'htm' => array('text/html'),
            'html' => array('text/html'),
            'xhtml' => array('application/xhtml+xml'),
            'js' => array('application/ecmascript')
        );

        public static function register($mime, $extension) {
            if (!isset(self::$mimes[$mime]))
                self::$mimes[$mime] = array();
            self::$mimes[$mime][] = $extension;

            if (!isset(self::$extensions[$extension]))
                self::$extensions[$extension] = array();
            self::$extensions[$extension][] = $mime;
        }

        public static function getMime($extension) {
            if (isset(self::$extensions[$extension]))
                return self::$extensions[$extension];
            else
                return false;
        }

        public static function getExtension($mime) {
            if (isset(self::$mimes[$mime]))
                return self::$mimes[$mime];
            else
                return false;
        }

        public static function getMimeFromFile($fileName) {
            if (function_exists('finfo_open')) {
                $fid = finfo_open(FILEINFO_MIME_TYPE);
                $type = finfo_file($fid, $fileName);
                finfo_close($fid);
                return $type;
            } else
                return mime_content_type($fileName);
        }
    }
?>
