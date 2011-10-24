<?php
    class YPFramework extends Object implements Initializable
    {
        //Base paths for components: controllers, models, views, filters, helpers
        private static $applicationComponentPaths;

        //Base paths of loaded plugins
        private static $plugins;

        //Application + plugin settings
        private static $configuration;

        //Connected databases
        private static $databases = array();

        private static $application = null;

        //Working mode
        private static $mode = null;
        private static $development = true;
        private static $production = false;

        public static function initialize()
        {
            //Initialize cache manager
            Cache::initialize();

            //if (self::$development)
            //    Cache::invalidate();

            //Set up basic framework/application paths
            $root = new Object();
            $root->paths = new Object();
            $root->paths->app = realpath(APP_PATH);
            $root->paths->ypf = realpath(YPF_PATH);
            $root->paths->www = realpath(WWW_PATH);
            $root->paths->log = self::getFileName($root->paths->app, 'log');
            $root->paths->tmp = self::getFileName($root->paths->app, 'tmp');

            //Load Application configuration
            $configurationFile = YPFramework::getFileName($root->paths->app, 'config.yml');
            self::$configuration = Cache::fileBased($configurationFile);

            if (self::$configuration === false)
            {
                self::$configuration = new Configuration($configurationFile, $root);
                Cache::fileBased($configurationFile, self::$configuration);
            }

            if (!self::$configuration->isValid())
                throw new BaseError ('Application configuration is invalid or incomplete', 'Configuration');

            $root = self::$configuration->getRoot();

            //Set up searching paths
            self::$applicationComponentPaths = array(
                $root->paths->app
            );

            //Determine working mode
            if (!isset($root->mode))
            {
                if (isset($_SERVER['HTTP_HOST']))
                    $requestUri = sprintf('http%s://%s%s%s', (isset($_SERVER['HTTPS'])?'s':''),
                                        $_SERVER['HTTP_HOST'],
                                        $_SERVER['REQUEST_URI'],
                                        ($_SERVER['QUERY_STRING']!='')? '?'.$_SERVER['QUERY_STRING']: '');
                else
                    $requestUri = '';

                $first = null;
                foreach ($root->application as $name=>$mode)
                {
                    if ($first === null) $first = $name;

                    if (stripos($requestUri, $mode->url) === 0)
                    {
                        self::$mode = $name;
                        break;
                    }
                }

                if (self::$mode === null)
                    self::$mode = $first;
            } else
                self::$mode = $root->mode;
            if (strpos(self::$mode, 'production') === 0)
            {
                self::$development = false;
                self::$production = true;
            }

            //Load application plugins
            self::loadPlugins(isset($root->{self::$mode}->plugins)? $root->{self::$mode}->plugins: null);
            self::$applicationComponentPaths[] = $root->paths->ypf;

            //Load framework, application and plugins helpers
            self::loadHelpers();

            Logger::initialize();
        }

        public static function finalize()
        {
            Cache::finalize();
            Logger::finalize();
        }

        public static function run($main = null)
        {
            try
            {
                self::$application = new Application();
                self::$application->run($main);
                self::finalize();
            }
            catch (Exception $e)
            {
                if (!($e instanceof BaseError))
                    Logger::framework('ERROR', $e->getMessage()."\n\t".$e->getTraceAsString());

                if (self::$development)
                    ob_clean();

                //header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                echo $e->getMessage();
            }
        }

        public static function installDatabase()
        {
            foreach (self::$applicationComponentPaths as $path)
            {
                $modelsPath = self::getFileName($path, 'models');
                if (!is_dir($modelsPath))
                    continue;

                $dir = opendir($modelsPath);
                while ($file = readdir($dir))
                {
                    if (substr($file, -4) != '.php')
                        continue;

                    $modelFile = self::getFileName($modelsPath, $file);
                    $modelClass = self::camelize(substr($file, 0, -4));

                    require_once $modelFile;
                    $modelClass::install();
                }
            }
        }

        public static function runTests()
        {
            foreach (self::$applicationComponentPaths as $path)
            {
                $testPath = self::getFileName($path, 'tests');
                if (!is_dir($testPath))
                    continue;

                $dir = opendir($testPath);
                while ($file = readdir($dir))
                {
                    if (substr($file, -4) != '.php')
                        continue;

                    $testFile = self::getFileName($testPath, $file);
                    $testClass = self::camelize(substr($file, 0, -4));

                    require_once $testFile;
                    $testClass::run();
                }
            }
        }

        public static function getApplicationWebPaths()
        {
            return self::$applicationWebPaths;
        }

        public static function getFileName()
        {
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

        public static function getApplication()
        {
            return self::$application;
        }

        public static function getConfiguration()
        {
            return self::$configuration->getRoot();
        }

        public static function getDatabase($mode = null)
        {
            if ($mode == null)
                $mode = self::$mode;

            if (isset(self::$databases[$mode]))
                return self::$databases[$mode];

            $root = self::$configuration->getRoot();

            if (!isset($root->databases->{$mode}))
                return null;

            $config = $root->databases->{$mode};
            if (!isset($config->type))
                return null;

            $driverFileName = self::getFileName($root->paths->ypf, 'framework/databases', $config->type.'.php');
            if (file_exists($driverFileName))
            {
                require_once $driverFileName;
                $className = $config->type."DataBase";
                self::$databases[$mode] = new $className($config);
                return self::$databases[$mode];
            } else
                throw new ErrorComponentNotFound ('DB:DRIVER', $config->type);
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

        private static function loadPlugins($selectPlugins = null)
        {
            self::$plugins = array();
            $basePath = realpath(self::getFileName(self::$applicationComponentPaths[0], 'plugins'));
            $ypfPath = realpath(self::getFileName(self::$configuration->getRoot()->paths->ypf, 'plugins'));

            if ($selectPlugins !== null) {
                foreach ($selectPlugins as $plugin)
                    if (!self::loadPlugin ($plugin, getFileName($basePath, $plugin)))
                        Logger::framework ('ERROR', sprintf("Could not load plugin: %s", $plugin));
            } else {
                $basePath = realpath(self::getFileName(self::$applicationComponentPaths[0], 'plugins'));
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

                        self::loadPlugin($dir, $path);
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

                    self::loadPlugin($dir, $path);
                }
                closedir($od);
            }
        }

        private static function loadPlugin($name, $path)
        {
            if (!is_dir($path))
                return false;

            if (isset(self::$plugins[$name]))
                throw new BaseError (sprintf("Plugin %s already loaded", $name), 'PLUGIN');

            self::$plugins[$name] = $path;
            self::$applicationComponentPaths[] = $path;

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
        }

        private static function loadHelpers()
        {
            foreach(self::$applicationComponentPaths as $path)
            {
                $helpersPath = self::getFileName($path, 'helpers');

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
    }
?>
