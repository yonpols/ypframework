<?php
    class XmlOutputFilter extends YPFOutputFilter
    {
        public function processOutput(YPFResponse $response)
        {
            $response->header('Content-Type', 'text/xml');
            $this->data->error = ($this->output->error == '')? null: $this->output->error;
            $this->data->notice = ($this->output->notice == '')? null: $this->output->notice;

            $response->write($this->data->__toXML());
        }
    }
?>
