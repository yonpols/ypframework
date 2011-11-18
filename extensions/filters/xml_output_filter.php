<?php
    class XmlOutputFilter extends YPFOutputFilter
    {
        protected function processOutput()
        {
            $this->contentType = 'text/xml';
            $this->data->error = ($this->output->error == '')? null: $this->output->error;
            $this->data->notice = ($this->output->notice == '')? null: $this->output->notice;
            $this->content = $this->data->__toXML();
        }
    }
?>
