<?php
    class Filter extends Object
    {
        protected $output;
        protected $data;
        protected $application;
        protected $controller;
        protected $action;

        protected $content;
        protected $contentType;

        public function __construct(Application $application, $controller, $action)
        {
            parent::__construct();

            $this->application = $application;
            $this->output = $application->getOutput();
            $this->data = $application->getData();
            $this->controller = $controller;
            $this->action = $action;
        }

        public function output()
        {
            if (YPFramework::inProduction())
                $previousContent = '';
            else
                $previousContent = ob_get_clean();

            $this->processOutput();

            if ($previousContent != '')
            {
                if (YPFramework::inProduction())
                    Logger::framework('ERROR', sprintf("Out of flow content: %s", $previousContent));
                else
                    throw new ErrorOutOfFlow($previousContent);
            }

            ob_end_clean();
            ob_start('ob_gzhandler');

            if ($this->contentType)
                header("Content-Type: $this->contentType; charset=utf-8");

            echo $this->content;
            ob_flush();
        }

        protected function processOutput()
        {
            $this->contentType = null;
            $this->content = is_string($this->data)? $this->data: $this->data->__toString();
        }
    }
?>
