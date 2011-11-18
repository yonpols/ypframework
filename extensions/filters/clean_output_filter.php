<?php
    class CleanOutputFilter extends YPFOutputFilter
    {
        public function processOutput(YPFResponse $response)
        {
            $this->data->error = ($this->output->error == '')? null: $this->output->error;
            $this->data->notice = ($this->output->notice == '')? null: $this->output->notice;

            $this->renderData($this->output->viewName);
            $response->header('Content-Type', $this->contentType);
            $response->write($this->content['main']);
        }

        protected function renderData($viewName) {
            $view = new View($this->application, $this->data, $this->output->profile);
            $this->content = $view->render($viewName);
            $this->contentType = $view->getOutputType();
            $fileName = basename($view->getTemplateFileName());

            $filters = YPFContentFilter::processContent($fileName, $this->content);
            Logger::framework('DEBUG:CONTENT_FILTER', sprintf('%s filtered %d times', $fileName, $filters));
        }
    }
?>
