<?php
    class Mime {
        private static $mimes = array(
            '*/*' => array('*'),
            'text/html' => array('html', 'htm'),
            'application/xhtml+xml' => array('xhtml'),
            'text/javascript' => array('js')
        );
        private static $extensions = array(
            'htm' => array('text/html'),
            'html' => array('text/html'),
            'xhtml' => array('application/xhtml+xml'),
            'js' => array('text/javascript')
        );

        /**
         * Register a mime type with the correponding extension on the framework. This
         * is useful when determining the type of data sent to the client
         * @param type $mime
         * @param type $extension
         *
         * @example
         * <code>Mime::register('text/html', 'html');</code>
         */
        public static function register($mime, $extension) {
            if (!isset(self::$mimes[$mime]))
                self::$mimes[$mime] = array();
            self::$mimes[$mime][] = $extension;

            if (!isset(self::$extensions[$extension]))
                self::$extensions[$extension] = array();
            self::$extensions[$extension][] = $mime;
        }

        /**
         * Get the mime/s type/s from the extension
         * @param type $extension
         * @return type
         */
        public static function getMime($extension) {
            if (isset(self::$extensions[$extension]))
                return self::$extensions[$extension];
            else
                return false;
        }

        /**
         * Get the extension/s from the mime type
         * @param type $mime
         * @return type
         */
        public static function getExtension($mime) {
            if (isset(self::$mimes[$mime]))
                return self::$mimes[$mime];
            else
                return false;
        }

        /**
         * Tries to get the mime from a filename using PHP builtin libraries
         * @param type $fileName
         * @return type
         */
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
