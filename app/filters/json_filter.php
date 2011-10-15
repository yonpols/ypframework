<?php
    class JsonFilter extends Filter
    {
        protected function processOutput()
        {
            $this->contentType = 'application/json';
            $this->data->error = ($this->output->error == '')? null: $this->output->error;
            $this->data->notice = ($this->output->notice == '')? null: $this->output->notice;

            unset($this->data->route);
            unset($this->data->routes);
            unset($this->data->paths);
            unset($this->data->app);
            unset($this->data->view);
            $this->content = $this->data->__toJSON();
        }
    }
?>
