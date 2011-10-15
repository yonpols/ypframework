<?php
    class CleanFilter extends Filter
    {
        protected function processOutput()
        {
            $this->data->error = ($this->output->error == '')? null: $this->output->error;
            $this->data->notice = ($this->output->notice == '')? null: $this->output->notice;
            $view = new View($this->application, $this->data, $this->output->profile);
            $this->content = $view->render($this->output->viewName);
            $this->contentType = $view->getOutputType();
        }
    }
?>
