<?php
    class Logger extends YPFObject {
        private static $frameworkLog = array();
        private static $frameworkLogFileName = null;
        private static $applicationLog = array();
        private static $applicationLogFileName = null;
        private static $mode = 'development';
        private static $active = true;

        private static $excludes = null;

        private static $colors = array('ERROR' => 31, 'INFO' => 32, 'NOTICE' => 33, 'DEBUG' => 36, 'ROUTE' => 34, 'SQL' => 35);

        public static function initialize() {
            $basePath = YPFramework::getPaths()->log;
            self::$mode = YPFramework::getMode();
            self::$active = YPFramework::getSetting('application.log.active', true);

            if (!defined('YPF_CMD') || YPFramework::getApplication() !== false) {
                self::$frameworkLogFileName = YPFramework::getFileName($basePath, sprintf('ypf-%s-%s.log', self::$mode, date('Y-m')));
                self::$applicationLogFileName = YPFramework::getFileName($basePath, sprintf('app-%s-%s.log', self::$mode, date('Y-m')));
            }

            self::writeLogs();
        }

        public static function finalize() {
            self::writeLogs();
        }

        /**
         * Logs a framework message
         * @param type $type
         * @param type $log
         */
        public static function framework($type, $log) {
            if (strpos($type, ':') !== false)
                list($type, $subtype) = explode(':', $type);
            else
                $subtype = 'LOG';

            if (self::isExcluded($type))
                return;

            $text = sprintf("[%s] \x1B[1;%d;1m%s:%s\x1B[0;0;0m %s\n", strftime('%F %T'), self::getColor($type), $type, $subtype, $log);

            if (self::$frameworkLogFileName)
            {
                if (($fd = @fopen(self::$frameworkLogFileName, "a"))) {
                    fwrite($fd, $text);
                    fclose($fd);
                } else
                    self::$frameworkLog[] = $text;
            } else
                self::$frameworkLog[] = $text;
        }

        /**
         * Logs an application message
         * @param type $type
         * @param type $log
         */
        public static function application($type, $log) {
            if (strpos($type, ':') !== false)
                list($type, $subtype) = explode(':', $type);
            else
                $subtype = 'LOG';

            if (self::isExcluded($type))
                return;

            $text = sprintf("[%s] \x1B[1;%d;1m%s:%s\x1B[0;0;0m %s\n", strftime('%F %T'), self::getColor($type), $type, $subtype, $log);

            if (self::$applicationLogFileName)
            {
                if (($fd = @fopen(self::$applicationLogFileName, "a"))) {
                    fwrite($fd, $text);
                    fclose($fd);
                } else
                    self::$applicationLog[] = $text;
            } else
                self::$applicationLog[] = $text;
        }

        private static function writeLogs() {
            if (self::$active && (!defined('YPF_CMD') || YPFramework::getApplication() !== false)) {
                if (count(self::$frameworkLog))
                {
                    if (($fd = @fopen(self::$frameworkLogFileName, "a"))) {
                        foreach(self::$frameworkLog as $log)
                            fwrite($fd, $log);
                        fclose($fd);
                        self::$frameworkLog = array();
                    }
                }

                if (count(self::$applicationLog))
                {
                    if (($fd = @fopen(self::$applicationLogFileName, "a"))) {
                        foreach(self::$applicationLog as $log)
                            fwrite($fd, $log);
                        fclose($fd);
                        self::$applicationLog = array();
                    }
                }
            }
        }

        private static function getColor($type) {
            if (isset(self::$colors[$type]))
                return self::$colors[$type];
            else
                return 0;
        }

        private static function isExcluded($type) {
            $excludes = array(
                'development' => array(),
                'production' => array(
                    'SQL', 'DEBUG'
                )
            );

            if (self::$excludes === null)
                self::$excludes = YPFramework::getSetting('application.log.exclude', $excludes[self::$mode]);

            return (array_search($type, self::$excludes) !== false);
        }
    }
?>
