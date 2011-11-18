<?php
    class YPFControllerBase extends YPFObject
    {
        protected $name;
        protected $application;
        protected $routes;
        protected $route;

        protected $data;
        protected $output;

        protected static $_beforeAction = array();
        protected static $_afterAction = array();

        public function  __construct(Application $application) {
            parent::__construct();

            $name = get_class($this);
            if (($pos = strrpos($name, '\\')) !== false)
                $name = substr($name, $pos+1);

            $this->name = substr($name, 0, -10);
            $this->application = $application;
            $this->routes = $application->getRoutes();
            $this->route = $application->getCurrentRoute();
        }

        public function __get($name) {
            if (isset($this->data->{$name}))
                return $this->data->{$name};
            return null;
        }

        public function __set($name, $value) {
            $this->data->{$name} = $value;
        }

        public function __isset($name) {
            return isset($this->data->{$name});
        }

        public function __unset($name) {
            unset($this->data->{$name});
        }

        public function getName() {
            return $this->name;
        }

        public function processAction($action) {
            $time_start = microtime(true);
            $className = get_class($this);

            $this->data = $this->application->getData();
            $this->params = $this->application->getRequest()->getParameters();
            $this->output = $this->application->getOutput();

            foreach($className::$_beforeAction as $proc)
                if (is_callable (array($this, $proc)))
                    call_user_func(array($this, $proc), $action);
                else
                    throw new ErrorNoCallback(get_class($this), $proc);

            try
            {
                if (is_callable (array($this, $action)))
                    call_user_func(array($this, $action));
                else
                    throw new ErrorNoAction($this->name, $action);

            } catch (StopRenderException $r) { }

            foreach($className::$_afterAction as $proc)
                if (is_callable (array($this, $proc)))
                    call_user_func(array($this, $proc), $action);
                else
                    throw new ErrorNoCallback(get_class($this), $proc);

            $time_end = microtime(true);
            Logger::framework('DEBUG:ACT_RENDER', sprintf('Action rendered (%.2F secs)', ($time_end-$time_start)));
        }

        protected function param($name, $default = null) {
            return array_key_exists($name, $this->params)? $this->params[$name]: $default;
        }

        protected function p($name, $default = null) {
            return array_key_exists($name, $this->params)? $this->params[$name]: $default;
        }

        protected function render($template, $options = null) {
            if (is_array($options))
            {
                if (isset($options['partial']) && $options['partial'])
                {
                    $view = new View($this->application,
                                    isset($options['data'])? $options['data']: $this->data,
                                    isset($options['profile'])? $options['profile']: $this->output->profile);

                    if (strpos($template, '/') === false)
                        $template = $this->name.'/'.$template;

                    return $view->render($template);
                }

                $this->output($options);
            }

            $this->output->viewName = $template;
            throw new StopRenderException();
        }

        protected function output($options) {
            if (array_key_exists('title', $options))
                $this->output->title = $options['title'];
            if (array_key_exists('layout', $options))
                $this->output->layout = $options['layout'];
            if (array_key_exists('profile', $options))
                $this->output->profile = $options['profile'];
            if (array_key_exists('format', $options))
                $this->output->format = $options['format'];
            if (array_key_exists('charset', $options))
                $this->output->charset = $options['charset'];
        }

        protected function redirectTo($url=null) {
            $this->application->redirectTo($url);
        }

        protected function forwardTo($action, $params = array()) {
            if (strpos($action, '.') === false)
                $action = $this->name.'.'.$action;

            $data = explode('.', $action);
            $this->application->forwardTo(array('controller' => $data[0], 'action' => $data[1]), $params);
        }

        protected function error($message) {
            $this->output->error = $message;
        }

        protected function notice($message) {
            $this->output->notice = $message;
        }
    }
?>
