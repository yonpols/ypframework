<?php
    class YPFResponse {
        private $application;
        private $controller;
        private $action;

        private $headers = array();
        private $content = '';
        private $status = '';

        public function __construct(YPFApplicationBase $application, YPFControllerBase $controller, $action) {
            $this->application = $application;
            $this->controller = $controller;
            $this->action = $action;
        }

        public function send() {
            $output = $this->application->getOutput();

            //Prepare output filter
            $className = YPFramework::camelize($output->format.'_output_filter');
            $classFileName = YPFramework::getClassPath($className, '', 'extensions/filters');
            if ($classFileName === false)
                $classFileName = YPFramework::getClassPath('HtmlOutputFilter', '', 'extensions/filters');


            if ($classFileName !== false)
            {
                require_once $classFileName;
                $filter = new $className($this->application, $this->controller, $this->action);
            } else
                throw new ErrorComponentNotFound ('Filter', $className);

            $filter->processOutput($this);

            //Send response to server
            if (YPFramework::inProduction())
                $previousContent = '';
            else
                $previousContent = ob_get_clean();

            if ($previousContent != '') {
                if (YPFramework::inProduction())
                    Logger::framework('ERROR', sprintf("Out of flow content: %s", $previousContent));
                else
                    throw new ErrorOutOfFlow($previousContent);
            }

            $this->beginOutput();
            echo $this->content;
            $this->endOutput();
        }

        public function sendFile($fileName) {
            $this->beginOutput();
            readfile($fileName);
            $this->endOutput();
            throw new EndResponse();
        }

        public function sendData($content) {
            $this->beginOutput();
            echo $content;
            $this->endOutput();
            throw new EndResponse();
        }

        public function write($content) {
            $this->content .= $content;
        }

        public function header($name, $value = null) {
            if ($value === null)
                return isset($this->headers[$name])? $this->headers[$name]: false;
            else {
                if ($name == 'Content-Type') {
                    if (strpos($value, 'charset=') === false)
                        $value .= sprintf('; charset=%s', $this->application->getOutput()->charset);
                }
                $this->headers[$name] = $value;
            }
        }

        public function status($code = 200, $text = 'OK') {
            $text = explode("\n", $text);
            $this->status = sprintf("HTTP/1.0 %d %s", $code, $text[0]);
        }

        private function beginOutput() {
            ob_end_clean();

            if (extension_loaded('zlib'))
                ob_start('ob_gzhandler');
            else
                ob_start();

            if ($this->status != '')
                header($this->status);

            header_remove();
            foreach ($this->headers as $header => $value)
                header (sprintf('%s: %s', $header, $value));
        }

        private function endOutput() {
            ob_flush();
        }
    }
?>
