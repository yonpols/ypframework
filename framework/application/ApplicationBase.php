<?php
    class ApplicationBase extends Object
    {
        protected $output;
        protected $params;
        protected $data;

        protected $settings;
        protected $routes;
        protected $paths;

        protected $route;

        protected $actions = array();

        public function __construct()
        {
            parent::__construct();

            $configuration = YPFramework::getConfiguration();
            $this->settings = $configuration->application->{YPFramework::getMode()};
            $this->paths = $configuration->paths;
            $this->routes = new Object();

            foreach($configuration->routes as $name => $data)
                $this->routes->{$name} = new Route($name, $data, $this->getSetting('url'));

            $this->data = new Object();

            $this->output = new Object();
            $this->output->title = $this->getSetting('title');
            $this->output->notice = '';
            $this->output->error = '';

            //Visualization parameters
            $this->output->viewName = null;
            $this->output->layout = 'main';
            $this->output->profile = null;
            $this->output->format = 'html';

            if (isset($_SESSION['error']))
            {
                $this->output->error = $_SESSION['error'];
                unset($_SESSION['error']);
            }

            if (isset($_SESSION['notice']))
            {
                $this->output->notice = $_SESSION['notice'];
                unset($_SESSION['notice']);
            }
        }

        public function run($main=null)
        {
            $time_start = microtime(true);

            if ($main != null)
                $main($this);
            else
            {
                //Start of request
                $this->processUrl();
                $this->processProfile();

                //Actions loop
                $controller = null;
                while ($action = array_shift($this->actions))
                {
                    $lastAction = $action;

                    if ($action['controller'] == null)
                        break;

                    try
                    {
                        $controller = $this->getControllerInstance($action['controller']);
                        $controller->processAction(YPFramework::camelize($action['action'], false));
                    } catch (JumpToNextActionException $e) {

                    } catch (ErrorMessage $e) {
                        $this->output->error = $e->getMessage();
                        break;
                    } catch (NoticeMessage $e) {
                        $this->output->notice = $e->getMessage();
                    }
                }

                //Prepare view name
                $controllerPrefix = YPFramework::underscore($controller->getName());
                if ($this->output->viewName == '')
                    $this->output->viewName = YPFramework::getFileName($controllerPrefix, $lastAction['action']);
                if (strpos($this->output->viewName, '/') === false)
                    $this->output->viewName = YPFramework::getFileName($controllerPrefix, $this->output->viewName);

                //Output response
                $this->processOutput($controller, $lastAction['action']);
            }

            $time_end = microtime(true);
            Logger::framework('DEBUG:REQ_RENDER', sprintf('Request rendered (%.2F secs)', ($time_end-$time_start)));
        }

        public function redirectTo($url=null)
        {
            if ($this->output->error)
                $_SESSION['error'] = $this->output->error;
            if ($this->notice)
                $_SESSION['notice'] = $this->output->notice;

            if ($url === null)
                $url = $this->getSetting('url', '');

            header('Location: '.$url);
            exit;
        }

        public function forwardTo($action, $params = array())
        {
            $this->actions[] = $action;

            foreach ($params as $k=>$v)
                if ($v !== null) $this->params[$k] = $v;

            throw new JumpToNextActionException();
        }

        public function sendEmail($to, $subject, $text, $params = null)
        {
            if (is_array($to))
            {
                $sum = 0;
                foreach ($to as $mail)
                    $sum += ($this->sendEmail ($email, $subject, $text, $params))? 1: 0;
                return $sum;
            }
            else {
                return mail($to, $subject, $text, implode("\r\n", $params));
            }
        }

        public function addRoute(Route $route)
        {
            $name = $route->getName();
            if (isset($this->routes->{$name}))
                return false;

            $this->routes->{$name} = $route;
        }

        public function getCurrentRoute()
        {
            return $this->route;
        }

        public function getSetting($name, $default=null)
        {
            return isset($this->settings->{$name})? $this->settings->{$name}: $default;
        }

        public function getOutput()
        {
            return $this->output;
        }

        public function getData()
        {
            return $this->data;
        }

        public function getParams()
        {
            return $this->params;
        }

        public function getRoutes()
        {
            return $this->routes;
        }

        protected function getControllerInstance($controllerName)
        {
            if (($pos = strrpos($controllerName, '/')) !== false)
            {
                $prefixPath = substr($controllerName, 0, $pos);
                $controllerName = substr($controllerName, $pos+1);
            } else
                $prefixPath = '';

            if (($pos = strrpos($controllerName, ':')) !== false)
            {
                $namespace = str_replace(':', '\\', YPFramework::camelize(substr($controllerName, 0, $pos)));
                $controllerName = substr($controllerName, $pos+1);
            } else
                $namespace = "";

            $className = YPFramework::camelize(preg_replace('/[^A-Za-z_]/', 'A', $controllerName)).'Controller';
            $classFileName = YPFramework::getClassPath($className, $prefixPath, 'controllers');

            if ($classFileName !== false)
            {
                $className = $namespace.'\\'.$className;

                require_once $classFileName;
                return new $className($this);
            } else
                throw new ErrorComponentNotFound ('Controller', $className);
        }

        protected function getFilterInstance($controller, $action)
        {
            $className = YPFramework::camelize($this->output->format.'_filter');
            $classFileName = YPFramework::getClassPath($className, '', 'filters');

            if ($classFileName !== false)
            {
                require_once $classFileName;
                return new $className($this, $controller, $action);
            } else
                throw new ErrorComponentNotFound ('Filter', $className);
        }

        protected function processOutput($controller, $action)
        {
            $filter = $this->getFilterInstance($controller, $action);
            $filter->output();
        }

        protected function processUrl()
        {
            if (isset($_GET['::action']))
                $action = $_GET['::action'];
            else
                $action = $this->getSetting('root', '/home/index');

            if ($action[0] == '/')
                $action = substr($action, 1);

            $match = false;
            foreach ($this->routes as $route)
            {
                $match = $route->matches($action);
                if ($match !== false)
                {
                    $this->route = $route;

                    $this->actions = array(array(
                        'controller' => $match['controller'],
                        'action' => $match['action']
                    ));

                    $this->output->format = get('format', $match['format']);
                    unset($match['controller']);
                    unset($match['action']);
                    unset($match['format']);

                    foreach ($_POST as $k=>$v)
                        if ($v == '')
                            unset($_POST[$k]);

                    $this->params = array_merge($match, $_GET, $_POST);
                    return;
                }
            }

            throw new ErrorNoRoute ($action);
        }

        protected function processProfile()
        {
            $profile = $this->getSetting('profile', true);

            if ($profile === false)
                $this->output->profile = null;
            elseif ($profile === true)
            {
                $mobile = preg_match('/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$_SERVER['HTTP_USER_AGENT'])
                            || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i',substr($_SERVER['HTTP_USER_AGENT'],0,4));

                $this->output->profile = ($mobile? 'mobile': null);
            }
            else
                $this->output->profile = $profile;
        }
    }
?>
