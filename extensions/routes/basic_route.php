<?php
    class BasicRoute extends YPFRoute {
        public function __construct($name, $config, $baseUrl) {
            parent::__construct($name, $config, $baseUrl);

            YPFRouter::register($name, $this);
        }
    }
?>
