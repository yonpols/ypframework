<?php
    require_once 'clean_filter.php';

    class HtmlFilter extends CleanFilter
    {
        protected function processOutput()
        {
            parent::processOutput();

            $view = new ViewBase($this->application, null, $this->output->profile);

            $view->set('html', $this->content);
            $view->set('output', $this->output);
            $view->set('controller', $this->controller);
            $view->set('action', $this->action);

            $this->content = $view->render('_layouts/'.$this->output->layout);
            $this->contentType = $view->getOutputType();
        }
    }
?>
