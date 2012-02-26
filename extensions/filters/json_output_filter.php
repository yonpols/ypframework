<?php
    class JsonOutputFilter extends YPFOutputFilter
    {
        public function processOutput(YPFResponse $response)
        {
            $response->header('Content-Type', 'application/json');

            if (is_object($this->data)) {
                unset($this->data->route);
                unset($this->data->routes);
                unset($this->data->paths);
                unset($this->data->app);
                unset($this->data->view);

            }

            $response->write(json_encode($this->jsonPrepare($this->data)));
        }

        private function jsonPrepare($object) {
            if (is_object($object) && ($object instanceof YPFObject))
                    return $object->__toJSONRepresentable();
            elseif (is_array($object))
                return array_map (array($this, 'jsonPrepare'), $object);
            else
                return $object;
        }
    }
?>
