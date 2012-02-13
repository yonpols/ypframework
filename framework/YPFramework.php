<?php
    /**
     * Base class of the framework. It reads configuration, loads plugins & helpers,
     * provides autoloading of classes in the file system in a logical way, etc.
     * This class is not intended to be instantiated.
     */
    class YPFramework extends YPFObject {
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

        /**
         * Framework initialization method. Must be called before any application
         * object initialization
         */
        public static function initialize() {
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
            self::$configuration = new YPFConfiguration($configurationFile, $root);

            if (!self::$configuration->isValid() && !defined('YPF_CMD'))
                throw new NoApplicationError('Application configuration is invalid or incomplete', 'Configuration');

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
                'route' => 'extensions/routes',
                '*' => 'models'
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

            if (self::$application) {
                //Load application plugins
                self::loadPlugins(self::getSetting('plugins'));
            } else
                self::loadPlugins();


            //Load framework, application and plugins helpers
            self::loadHelpers();
        }

        //TODO This method is intended to close everything openend
        public static function finalize() {
            YPFCache::finalize();
            Logger::finalize();
        }

        //TODO test if it can be run without finalizing
        /**
         * If the framework is being called from a Web application this method
         * runs the application. It's meant to be called from index.php
         */
        public static function run() {
            try
            {
                //Initialize cache manager
                YPFCache::initialize();
                Logger::initialize();

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

        /**
         * Gets a setting from the configurations. This method gets settings under
         * the 'environments.{mode}' root.
         *
         * @see YPFConfiguration
         * @param string    $path       Path of the setting. Each level must be concatenated by dots.
         * @param mixed     $default    A default value to be returned when the setting does not exist.
         * @return mixed                The value of the setting, or the value of $default or, NULL
         */
        public static function getSetting($path, $default=null) {
            $parts = explode('.', $path);

            if (!self::$configuration)
                return $default;

            if (!isset(self::$configuration->getRoot()->environments))
                return $default;

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

        /**
         * Sets a setting to the configurations. This method sets settings under
         * the 'environments.{mode}' root.
         *
         * @see YPFConfiguration
         * @param string    $path   Path of the setting. Each level must be concatenated by dots.
         * @param mixed     $value  The value to be set.
         * @return mixed            TRUE when success, FALSE otherwise
         */
        public static function setSetting($path, $value) {
            $parts = explode('.', $path);

            if (!self::$configuration)
                return false;

            if (!isset(self::$configuration->getRoot()->environments))
                return false;

            $root = self::$configuration->getRoot()->environments->{self::$mode};

            foreach ($parts as $i => $p)
            {
                if ($i == (count($parts)-1)) {
                    $root->{$p} = $value;
                    return true;
                } else
                    $root = $root->{$p};
            }
        }

        /**
         * Gets a database connection. Under the databases setting you can define several conections.
         * One name is mandatory for a connection 'main' which will be the name used by the Models
         * if none specified.
         *
         * @param string    $name   Name of the database connection
         * @return mixed            YPFDatabase instance or NULL
         */
        public static function getDatabase($name = 'main') {
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

        /**
         * Gets a file name for a class name passed by according to YPFramework class
         * loading system.
         *
         * @see YPFramework::registerClassSource
         * @param string $className Name of the class including package prefix.
         * @param string $type      Type of class according to YPFramework extensions types to be returned
         * @return string           Filename of the class or false if not found;
         */
        public static function getClassPath($className, &$type = null) {
            $fileName = str_replace('\\', '/', self::underscore($className));
            $rpos = strrpos($fileName, '_');
            if ($rpos !== false)
                $classSuffix = substr ($fileName, $rpos+1);
            else
                $classSuffix = '';
            $type = $classSuffix;

            $fileName .= '.php';

            if (array_key_exists($classSuffix, self::$classSources))
                $basePath = self::$classSources[$classSuffix];
            else
                $basePath = self::$classSources['*'];

            $baseName = basename($fileName);
            $prefixPath = self::getFileName($basePath, dirname($fileName));

            return self::getComponentPath($baseName, $prefixPath);
        }

        /**
         * Associate a class type with a subpath of the filesystem hierarchy.
         *
         * <p>YPFramework can autoload classes for different types of functionalities.
         * It can load Models, Controllers, Filters, Commands, etc. In order to do this,
         * there's a filesystem structure you must respect and use. </p>
         *
         * <p>Under the APP_PATH dir you have:<br /><code>
         * ... controllers
         * ... models
         * ... extensions
         * ... ... commands
         * ... ... filters</code></p>
         *
         * YPFrameworks defines the following autoloading table:
         * <table>
         *   <tr>
         *     <th>Class Name</th><th>Class Suffix</th><th>Search Path</th><th>File Name</th>
         *   </tr>
         *   <tr>
         *     <td>Person</td><td></td><td>{SEARCH_PATH}/models</td><td>{APP_PATH}/models/person.php<td>
         *   </tr>
         *   <tr>
         *     <td>HumanResources\Employee</td><td></td><td>{SEARCH_PATH}/models</td><td>{APP_PATH}/models/human_resources/employee.php<td>
         *   </tr>
         *   <tr>
         *     <td>Authorization\User</td><td></td><td>{SEARCH_PATH}/models</td><td>{APP_PATH}/plugins/auth/models/authorization/user.php<td>
         *   </tr>
         *   <tr>
         *     <td>PersonsController</td><td>Controller</td><td>{SEARCH_PATH}/controllers</td><td>{APP_PATH}/controllers/persons_controller.php<td>
         *   </tr>
         *   <tr>
         *     <td>SassContentFilter</td><td>Filter</td><td>{SEARCH_PATH}/filters</td><td>{APP_PATH}/plugins/foo/extensions/filters/sass_content_filter.php<td>
         *   </tr>
         * </table>
         *
         * <p>YPFramework will use the suffixes table to look for a filename derived from the class name. The rules to
         * obtain this path are the following:
         * <ol>
         *  <li>Get the class suffix (the last capital word of the classname)</li>
         *  <li>Find the path prefix in the suffixes table, if none found, then use 'models/' path prefix (models don't have a suffix) </li>
         *  <li>Turn the class name into a filename preceding with an underscor each capital letter of the class name, changing package separator \ with path separator / and turning all to lowercase</li>
         *  <li>Find a file name within the search path (application root path, plugins root path, ypf root path) that is: {SEARCH_PATH}/{PATH_PREFIX}/{CLASS_FILE_NAME}.php</li>
         * </ol>
         *
         * @param string    $suffix Suffix of the class name
         * @param string    $path   Path prefix
         */
        public static function registerClassSource($suffix, $path) {
            if ($suffix == '*')
                return false;

            self::$classSources[$suffix] = $path;
        }

        /**
         * Searchs for a file name in all search paths (application root path, plugins root path, ypf root path)
         *
         * @param string    $baseName   Name of the file you are searching. You can use glob (but you must pass TRUE in $useGlob param)
         * @param string    $prefixPath Path prefix where you want YPFramework to search for
         * @param boolean   $useGlob    TRUE if $baseName includes glob expressions
         * @param true      $allPaths   TRUE if you want search not to stop in the first occurence and search at other locations. Whe set to TRUE this function may return an array of file paths.
         * @return mixed                array of file paths (when $allPaths == TRUE), string when found a file, FALSE when nothing is found
         */
        public static function getComponentPath($baseName, $prefixPath = '', $useGlob=false, $allPaths=false) {
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

        /**
         * Returns the application object instance (if present)
         * @return YPFApplicationBase
         */
        public static function getApplication() {
            return self::$application;
        }

        /**
         * Returns YPFrameworks search paths.
         * @return array
         */
        public static function getPaths() {
            return self::$paths;
        }

        /**
         * Returns the package element defined at the configuration file, if present
         * @return YPFObject
         */
        public static function getPackage() {
            return self::$package;
        }

        /**
         * Returns YPFramework working mode or "environment". Usually this will be 'production' or 'development'
         * @return string
         */
        public static function getMode() {
            return self::$mode;
        }

        /**
         * Returns TRUE if the framework is working in development mode
         * @return boolean
         */
        public static function inDevelopment() {
            return self::$development;
        }

        /**
         * Returns TRUE if the framework is working in production mode
         * @return boolean
         */
        public static function inProduction() {
            return self::$production;
        }

        //======================================================================
        //Util functions

        /**
         * Concatenates each param with a DIRECTORY_SEPARATOR when needed. This
         * function accepts N params, all of them must be strings.

         * @return string   The concatenated path
         * @example <code>YPFramework::getFileName(APP_PATH, 'controllers', 'persons_controller.php')
         * returns '/home/yor_app/private/controllers/persons_controller.php'
         *
         */
        public static function getFileName() {
            $filePath = '';

            foreach (func_get_args() as $path)
            {
                if ($path == '')
                    continue;

                if ($path[0] == DIRECTORY_SEPARATOR)
                    $path = substr($path, 1);

                if (substr($path, -1) != DIRECTORY_SEPARATOR)
                    $path = substr($path, 0, -1);

                    $filePath .= $path.DIRECTORY_SEPARATOR;
            }

            return substr($filePath, 0, -1);
        }

        public static function underscore($string) {
            $result = '';
            $last = '#';

            for ($i = 0; $i < strlen($string); $i++)
            {
                if (($i > 0) && (strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ', $string[$i]) !== false) &&
                    (strpos('abcdefghijklmnopqrstuvwxyz', $last) !== false))
                    $result .= '_';

                $last = $string[$i];
                $result .= strtolower($last);
            }

            return $result;
        }

        public static function camelize($string, $firstUp = true) {
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

    function __autoload($className) {
        $fileName = YPFramework::getClassPath($className, $type);

        if ($fileName === false)
            throw new ErrorComponentNotFound ($type, $className);

        require $fileName;
        if (array_search('initialize', get_class_methods($className)) !== false)
            $className::initialize();
    }
?>
