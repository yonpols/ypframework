<?php
    class YPFramework extends YPFObject
    {
        //Framework and application paths
        private static $paths;
        private static $package;

        //Class source paths
        private static $classSources = array();

        //Base paths for components: controllers, models, views, extensions
        private static $applicationComponentPaths = array();

        //Base paths of loaded plugins
        private static $plugins;

        //Application + plugin settings
        private static $configuration;

        //Connected databases
        private static $databases = array();

        //Current application instance
        private static $application = null;

        //Working mode
        private static $mode = null;
        private static $development = true;
        private static $production = false;

        //Framework initialization method. Must be called before any application object initialization
        public static function initialize() {
            //Initialize cache manager
            YPFCache::initialize();

            //Set up basic framework/application paths
            $root = new YPFObject;
            self::$paths = new YPFObject;
            $root->paths = self::$paths;
            $root->paths->app = realpath(APP_PATH);
            $root->paths->ypf = realpath(YPF_PATH);
            $root->paths->www = realpath(WWW_PATH);
            $root->paths->log = self::getFileName($root->paths->app, 'support/log');
            $root->paths->tmp = self::getFileName($root->paths->app, 'support/tmp');

            //Load Application configuration
            $configurationFile = YPFramework::getFileName($root->paths->app, 'config.yml');
            self::$configuration = YPFCache::fileBased($configurationFile);

            if (self::$configuration === false)
            {
                self::$configuration = new YPFConfiguration($configurationFile, $root);
                YPFCache::fileBased($configurationFile, self::$configuration);
            }

            if (!self::$configuration->isValid()) {
                if (defined('YPF_CMD'))
                    self::$application = false;
                else
                    throw new NoApplicationError('Application configuration is invalid or incomplete', 'Configuration');
            }

            $root = self::$configuration->getRoot();
            self::$package = isset($root->package)? $root->package: new YPFObject;

            //Set up searching paths
            self::$applicationComponentPaths = array(
                $root->paths->app,
                $root->paths->ypf
            );

            self::$classSources = array(
                'controller' => 'controllers',
                'command' => 'extensions/commands',
                'filter' => 'extensions/filters',
                'route' => 'extensions/routes'
            );

            //Determine working mode
            if (isset($_SERVER['YPF_MODE']))
                self::$mode = $_SERVER['YPF_MODE'];
            elseif (isset($_ENV['YPF_MODE']))
                self::$mode = $_ENV['YPF_MODE'];
            elseif (isset($root->mode))
                self::$mode = $root->mode;
            else
                self::$mode = 'development';

            if (strpos(self::$mode, 'production') === 0)
            {
                self::$development = false;
                self::$production = true;
            }

            if (self::$application !== false) {
                //Load application plugins
                self::loadPlugins(self::getSetting('plugins'));
            } else
                self::loadPlugins();


            //Load framework, application and plugins helpers
            self::loadHelpers();

            Logger::initialize();
        }

        //TODO This method is intended to close everything openend
        public static function finalize() {
            YPFCache::finalize();
            Logger::finalize();
        }

        //This method runs the framework per request.
        //TODO test if it can be run without finalizing
        public static function run() {
            try
            {
                Application::initialize();
                Controller::initialize();
                Model::initialize();
                View::initialize();

                self::$application = new Application();
                self::$application->run();
                self::finalize();
            }
            catch (EndResponse $e) {
                self::finalize();
            }
        }

        //Public data methods about the framework and application ==============

        //Get a setting from configuration.
        public static function getSetting($path, $default=null) {
            $parts = explode('.', $path);

            $root = self::$configuration->getRoot()->environments->{self::$mode};

            foreach ($parts as $p)
            {
                if (!isset($root->{$p}))
                    return $default;
                else
                    $root = $root->{$p};
            }

            return $root;
        }

        //Get a database connection instance
        public static function getDatabase($name = 'main')
        {
            $mode = self::$mode.'-'.$name;

            if (isset(self::$databases[$mode]))
                return self::$databases[$mode];

            if (!($config = self::getSetting('databases.'.$name)))
                return null;

            if (!isset($config->type))
                return null;

            $driverFileName = self::getFileName(self::$paths->ypf, 'framework/databases', $config->type.'.php');
            if (file_exists($driverFileName))
            {
                require_once $driverFileName;
                $className = $config->type."DataBase";
                self::$databases[$mode] = new $className($config);
                return self::$databases[$mode];
            } else
                throw new ErrorComponentNotFound ('DB:DRIVER', $config->type);
        }

        public static function getApplication()
        {
            return self::$application;
        }

        public static function getPaths() {
            return self::$paths;
        }

        public static function getPackage() {
            return self::$package;
        }

        public static function getMode()
        {
            return self::$mode;
        }

        public static function inDevelopment()
        {
            return self::$development;
        }

        public static function inProduction()
        {
            return self::$production;
        }

        public static function getControllerInstance($controllerName) {
            if (($pos = strrpos($controllerName, '/')) !== false)
            {
                $prefixPath = substr($controllerName, 0, $pos);
                $controllerName = substr($controllerName, $pos+1);
            } else
                $prefixPath = '';

            if (($pos = strrpos($controllerName, ':')) !== false)
            {
                $namespace = str_replace(':', '\\', self::camelize(substr($controllerName, 0, $pos)));
                $controllerName = substr($controllerName, $pos+1);
            } else
                $namespace = "";

            $className = self::camelize(preg_replace('/[^A-Za-z_]/', 'A', $controllerName)).'Controller';
            $classFileName = self::getClassPath($className, $prefixPath, 'controllers');

            if ($classFileName !== false)
            {
                $className = $namespace.'\\'.$className;

                require_once $classFileName;
                $className::initialize();
                return new $className(self::$application);
            } else
                throw new ErrorComponentNotFound ('Controller', $className);
        }

        public static function getClassPath($className, $prefixPath = '', $type = null)
        {
            if ($type === null)
            {
                if (substr($className, -10) == 'Controller')
                    $type = 'controllers';
                elseif (substr($className, -6) == 'Filter')
                    $type = 'filters';
                else
                    $type = 'models';
            }

            return self::getComponentPath(self::underscore($className).'.php', self::getFileName($prefixPath, $type));
        }

        public static function getComponentPath($baseName, $prefixPath = '', $useGlob=false, $allPaths=false)
        {
            $result = array();

            foreach (self::$applicationComponentPaths as $path)
            {
                $fileName = self::getFileName($path, $prefixPath, $baseName);

                if ($useGlob)
                {
                    $files = glob($fileName);

                    if (is_array($files))
                        $result = array_merge ($result, $files);

                    if (count($files) && !$allPaths)
                        break;

                } elseif (is_file($fileName)) {
                    $result[] = $fileName;
                    if (!$allPaths)
                        break;
                }
            }

            if (empty($result))
                return false;
            elseif ((count($result) == 1) && !$useGlob)
                return array_pop($result);
            else
                return $result;
        }

        public static function getFileName() {
            $filePath = '';

            foreach (func_get_args() as $path)
            {
                if ($path == '')
                    continue;

                if (substr($path, -1) != '/')
                    $filePath .= $path . '/';
                else
                    $filePath .= $path;
            }

            if (substr($filePath, -1) == '/')
                return substr($filePath, 0, -1);
            else
                return $filePath;
        }

        public static function underscore($string)
        {
            $result = '';

            for ($i = 0; $i < strlen($string); $i++)
            {
                if (($i > 0) && (strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ', $string[$i]) !== false))
                    $result .= '_';

                $result .= strtolower($string[$i]);
            }

            return $result;
        }

        public static function camelize($string, $firstUp = true)
        {
            $result = '';
            $last = 0;

            while (($pos = stripos($string, '_', $last)) !== false)
            {
                $portion = substr($string, $last, $pos-$last);
                $result .= strtoupper($portion[0]).substr($portion, 1);
                $last = $pos+1;
            }
            $portion = substr($string, $last);
            $result .= strtoupper($portion[0]).substr($portion, 1);

            if ($firstUp)
                return strtoupper($result[0]).substr($result, 1);
            else
                return strtolower($result[0]).substr($result, 1);
        }

        public static function normalize($name) {
            $name = preg_replace('/\\s/', '-', $name);
            preg_replace('/[^a-zA-Z0-9_\\-]/', '', $name);
            return $name;
        }

        public static function processClassName($name)
        {
            if (($pos = strrpos($name, '/')) !== false)
            {
                $prefixPath = substr($name, 0, $pos);
                $name = substr($name, $pos+1);
            } else
                $prefixPath = '';

            if (($pos = strrpos($name, ':')) !== false)
            {
                $namespace = str_replace(':', '\\', YPFramework::camelize(substr($name, 0, $pos)));
                $name = substr($name, $pos+1);
            } else
            if (($pos = strrpos($name, '\\')) !== false)
            {
                $namespace = YPFramework::camelize(substr($name, 0, $pos));
                $name = substr($name, $pos+1);
            } else
                $namespace = "";

            return array(
                'filePrefix' => $prefixPath,
                'namespace' => $namespace,
                'className' => YPFramework::camelize($name)
            );
        }

        //======================================================================

        private static function loadPlugins($selectPlugins = null) {
            self::$plugins = array();
            $basePath = realpath(self::getFileName(self::$applicationComponentPaths[0], 'extensions/plugins'));
            $ypfPath = realpath(self::getFileName(self::$paths->ypf, 'extensions/plugins'));

            if ($selectPlugins !== null) {
                foreach ($selectPlugins as $plugin)
                    if (!self::loadPlugin ($plugin, self::getFileName($basePath, $plugin)))
                        throw new ErrorComponentNotFound ('plugin', $plugin);
            } else {
                $basePath = realpath(self::getFileName(self::$applicationComponentPaths[0], 'extensions/plugins'));
                if (is_dir($basePath))
                {
                    $od = opendir($basePath);
                    while ($dir = readdir($od))
                    {
                        $path = self::getFileName($basePath, $dir);

                        if (!is_dir($path))
                            continue;
                        if ($dir[0] =='.')
                            continue;

                        if (!self::loadPlugin($dir, $path))
                            throw new ErrorComponentNotFound ('plugin', $dir);
                    }
                    closedir($od);
                }
            }

            if (is_dir($ypfPath))
            {
                $od = opendir($ypfPath);
                while ($dir = readdir($od))
                {
                    $path = self::getFileName($ypfPath, $dir);

                    if (!is_dir($path))
                        continue;
                    if ($dir[0] =='.')
                        continue;

                    if (!self::loadPlugin($dir, $path))
                        throw new ErrorComponentNotFound ('plugin', $dir);
                }
                closedir($od);
            }
        }

        private static function loadPlugin($name, $path) {
            if (!is_dir($path))
                return false;

            if (isset(self::$plugins[$name]))
                throw new BaseError (sprintf("Plugin %s already loaded", $name), 'PLUGIN');

            self::$plugins[$name] = $path;

            self::$applicationComponentPaths = array_splice(self::$applicationComponentPaths, 0, -1);
            self::$applicationComponentPaths[] = $path;
            self::$applicationComponentPaths[] = self::$paths->ypf;

            //Plugin has libs to include?
            $libPath = self::getFileName($path, 'lib');
            if (is_dir($libPath))
                set_include_path (get_include_path().PATH_SEPARATOR.$libPath);

            //Plugin has config file to include?
            $configFileName = self::getFileName($path, 'config.yml');
            if (is_file($configFileName))
                self::$configuration->addConfigFile($configFileName);

            //Plugin has initializer?
            $initFileName = self::getFileName($path, 'init.php');
            if (is_file($initFileName))
                require_once $initFileName;

            Logger::framework('DEBUG:PLUGIN', sprintf('%s loaded', $name));
            return true;
        }

        private static function loadHelpers() {
            foreach(self::$applicationComponentPaths as $path)
            {
                $helpersPath = self::getFileName($path, 'extensions/helpers');

                if (!is_dir($helpersPath))
                    continue;

                $od = opendir($helpersPath);
                while ($helper = readdir($od))
                {
                    $helperFileName = self::getFileName($helpersPath, $helper);

                    if (is_file($helperFileName) && (substr($helperFileName, -4) == '.php'))
                        require_once $helperFileName;
                }
            }
        }
    }

    function __autoload($className)
    {
        $info = YPFramework::processClassName($className);

        $fileName = YPFramework::getClassPath($info['className'], $info['filePrefix']);

        if ($fileName === false)
            throw new ErrorComponentNotFound ('', $info['namespace'].'\\'.$info['className']);

        require $fileName;
        $className::initialize();
    }
?>
