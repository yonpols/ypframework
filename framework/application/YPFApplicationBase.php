<?php
    class YPFApplicationBase extends YPFObject {
        protected $output;
        protected $data;

        protected $routes;
        protected $paths;

        protected $route;

        protected $request;

        protected $actions = array();

        public function __construct() {
            parent::__construct();

            $this->paths = YPFramework::getPaths();
            $routes = YPFramework::getSetting('routes');

            //Process request
            $this->request = YPFRequest::get();

            $url = $this->request->getBaseUrl();
            YPFramework::setSetting('application.url', $url);

            $this->routes = YPFRouter::get($url);

            $this->data = new YPFObject();

            $this->output = new YPFObject();
            $this->output->title = $this->getSetting('title');
            $this->output->notice = '';
            $this->output->error = '';

            //Visualization parameters
            $this->output->viewName = null;
            $this->output->layout = 'main';
            $this->output->profile = null;
            $this->output->format = null;
            $this->output->charset = 'utf-8';

            if (isset(YPFSession::get()->error)) {
                $this->output->error = YPFSession::get()->error;
                unset(YPFSession::get()->error);
            }

            if (isset(YPFSession::get()->notice)) {
                $this->output->notice = YPFSession::get()->notice;
                unset(YPFSession::get()->notice);
            }
        }

        /**
         * Run application to respond to a request
         */
        public function run() {
            $time_start = microtime(true);

            //Start of request
            $this->processRequest();

            //Actions loop
            $controller = null;
            while ($action = array_shift($this->actions))
            {
                $lastAction = $action;

                if ($action['controller'] == null)
                    break;

                try
                {
                    $className = YPFramework::camelize($action['controller'].'_controller');
                    $controller = new $className($this);
                    $this->data = $controller->processAction(YPFramework::camelize($action['action'], false));
                } catch (JumpToNextActionException $e) {

                } catch (ErrorMessage $e) {
                    $this->output->error = $e->getMessage();
                    break;
                } catch (NoticeMessage $e) {
                    $this->output->notice = $e->getMessage();
                }
            }

            //Prepare view name
            $controllerPrefix = str_replace('\\', '/', YPFramework::underscore($controller->getName()));
            if ($this->output->viewName == '')
                $this->output->viewName = YPFramework::getFileName($controllerPrefix, $lastAction['action']);
            if (strpos($this->output->viewName, '/') === false)
                $this->output->viewName = YPFramework::getFileName($controllerPrefix, $this->output->viewName);

            //Output response
            $this->processResponse($controller, $lastAction['action']);

            $time_end = microtime(true);
            Logger::framework('DEBUG:REQ_RENDER', sprintf('Request rendered (%.2F secs)', ($time_end-$time_start)));
        }

        /**
         * Forward execution flow to an other controller/action
         * @param array $action an associative array with the keys action and controller
         * @param array $params an array of params you want to include in the params list
         */
        public function forwardTo($action, $params = array()) {
            $this->actions[] = $action;
            $this->request->mergeParameters($params, true);

            throw new JumpToNextActionException();
        }

        /**
         * Send a mail
         * @param mixed $to string or array containing recipients
         * @param string $subject
         * @param string $text
         * @param array $params associative array with Mime params
         * @return mixed returns true or false or the amount of mails sent
         */
        public function sendEmail($to, $subject, $text, $params = null) {
            if (is_array($to)) {
                $sum = 0;
                foreach ($to as $email)
                    $sum += ($this->sendEmail ($email, $subject, $text, $params))? 1: 0;
                return $sum;
            } else {
                if (is_array($params))
                    $params = implode("\r\n", $params);
                return mail($to, $subject, $text, $params);
            }
        }

        /**
         * Redirect client navigator to the url passed
         * @param string $url
         */
        public function redirectTo($url=null) {
            if ($this->output->error)
                YPFSession::get()->error = $this->output->error;
            if ($this->output->notice)
                YPFSession::get()->notice = $this->output->notice;

            if ($url === null)
                $url = $this->getSetting('url', '');

            YPFResponse::sendRedirect($url);
        }

        public function getCurrentRoute() {
            return $this->route;
        }

        /**
         * Get an application setting
         * @param string $path
         * @param mixed $default
         * @return mixed
         */
        public function getSetting($path, $default=null) {
            return YPFramework::getSetting('application.'.$path, $default);
        }

        public function getOutput() {
            return $this->output;
        }

        public function getData() {
            return $this->data;
        }

        public function getRoutes() {
            return $this->routes;
        }

        public function getRequest() {
            return $this->request;
        }

        private function processRequest() {
            $route = YPFRouter::matchingRoute($this->request);
            if ($route) {
                $this->route = $route;
                $this->request->mergeParameters($route->getParameters());

                if (isset($route->format))
                    $this->output->format = $route->format;

                $this->actions = array(
                    array('controller' => $route->getController(), 'action' => $route->getAction())
                );
            } else
                throw new ErrorNoRoute ($this->request->__toString());
        }

        private function processResponse($controller, $action) {
            $response = new YPFResponse($this, $controller, $action);
            $response->send();
        }
    }
?>
