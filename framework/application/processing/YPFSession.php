<?php
    class YPFSession extends YPFObject {
        private static $instance = null;

        private $filename = null;
        private $data = null;
        private $session_id = null;
        private $is_new = false;

        public static function get() {
            if (!self::$instance)
                self::$instance = new YPFSession ();

            return self::$instance;
        }

        public function __construct() {
            parent::__construct();

            $path = YPFramework::getFileName(YPFramework::getPaths()->tmp, 'sessions');

            if (!is_dir($path))
                mkdir($path, 0777, true);

            if (isset($_COOKIE['YPFSESSION'])) {
                $this->session_id = basename ($_COOKIE['YPFSESSION']);
                $this->filename = YPFramework::getFileName ($path, $this->session_id);
                if (file_exists($this->filename)) {
                    $this->data = unserialize(file_get_contents($this->filename));
                    return;
                }
            }

            $this->session_id = sha1(time().$_SERVER['REMOTE_ADDR']);
            $this->filename = YPFramework::getFileName ($path, $this->session_id);
            $this->data = new stdClass();
            $this->is_new = true;
        }

        public function __destruct() {
            $this->write();
        }

        public function write() {
            file_put_contents($this->filename, serialize($this->data));
        }

        public function setCookie() {
            if ($this->is_new)
                setcookie('YPFSESSION', $this->session_id, time()+24*3600);
        }

        public function __get($name) {
            if (isset($this->data->{$name}))
                return $this->data->{$name};
            else
                return null;
        }

        public function __isset($name) {
            return isset($this->data->{$name});
        }

        public function __unset($name) {
            unset($this->data->{$name});
        }

        public function __set($name, $value) {
            $this->data->{$name} = $value;
        }
    }
?>
