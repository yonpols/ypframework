<?php
    require_once 'clean_output_filter.php';

    class HtmlOutputFilter extends CleanOutputFilter
    {
        public function processOutput(YPFResponse $response)
        {
            $this->data->error = ($this->output->error == '')? null: $this->output->error;
            $this->data->notice = ($this->output->notice == '')? null: $this->output->notice;

            $this->renderData($this->output->viewName);

            $view = new YPFViewBase($this->application, null, $this->output->profile);
            $view->set('output', $this->output);
            $view->set('controller', $this->controller);
            $view->set('action', $this->action);
            foreach ($this->content as $name => $value)
                $view->set($name, $value);

            $this->content = $view->render('_layouts/'.$this->output->layout);

            if (!isset($this->output->disable_content_type) || !$this->output->disable_content_type)
                $response->header('Content-Type', $view->getOutputType());

            $response->write($this->content['main']);
        }
    }
?>
