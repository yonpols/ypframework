<?php

abstract class YPFOutputFilter extends YPFObject {

    protected $output;
    protected $data;
    protected $application;
    protected $controller;
    protected $action;
    protected $content;
    protected $contentType;

    public function __construct(Application $application, $controller, $action) {
        parent::__construct();

        $this->application = $application;
        $this->output = $application->getOutput();
        $this->data = $application->getData();
        $this->controller = $controller;
        $this->action = $action;
    }

    public abstract function processOutput(YPFResponse $response);
}

?>
