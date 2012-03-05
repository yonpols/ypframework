<?php
    /**
     * Base class of the framework. It reads configuration, loads plugins & helpers,
     * provides autoloading of classes in the file system in a logical way, etc.
     * This class is not intended to be instantiated.
     */
    class YPFramework extends YPFObject {
        //Application + plugin settings
        private static $configurationRoot = null;
        private static $configuration = null;
        private static $configurationFile = null;

        private static $environment = null;

        //Connected databases
        private static $databases = array();

        //Current application instance
        private static $application = null;

        /**
         * Framework initialization method. Must be called before any application
         * object initialization
         */
        public static function initialize() {
            //Initialize cache manager
            YPFCache::initialize();

            self::$configurationFile = YPFramework::getFileName(APP_PATH, 'config.yml');
            if (!file_exists(self::$configurationFile))
                self::$configurationFile = YPFramework::getFileName(APP_PATH, 'config.php');

            if (file_exists(self::$configurationFile))
                self::$configuration = YPFCache::fileBased(self::$configurationFile);

            if (self::$configuration) {
                self::$configurationRoot = self::$configuration->getRoot();
                if (self::$configuration && isset(self::$configuration->environments) && isset(self::$configuration->environments->{self::$configurationRoot->mode}))
                    self::$environment = self::$configuration->environments->{self::$configurationRoot->mode};
            } else {
                //Set up basic framework/application paths
                $root = new YPFObject;
                self::$configuration = $root;
                self::$configurationRoot = $root;

                $root->paths = new YPFObject;
                $root->paths->app = realpath(APP_PATH);
                $root->paths->ypf = realpath(YPF_PATH);
                $root->paths->www = realpath(WWW_PATH);
                $root->paths->log = self::getFileName($root->paths->app, 'support/log');
                $root->paths->tmp = self::getFileName($root->paths->app, 'support/tmp');

                //Set up searching paths
                $root->applicationComponentPaths = array(
                    $root->paths->app,
                    $root->paths->ypf
                );

                $root->production = false;
                $root->development = true;

                //Load Application configuration
                if (self::$configurationFile && is_readable(self::$configurationFile))
                    self::$configuration = new YPFConfiguration(self::$configurationFile, $root);

                $root->classSources = array(
                    'controller' => 'controllers',
                    'command' => 'extensions/commands',
                    'filter' => 'extensions/filters',
                    'route' => 'extensions/routes',
                    '*' => 'models'
                );
                //Determine working mode
                if (isset($_SERVER['YPF_MODE']))
                    $root->mode = $_SERVER['YPF_MODE'];
                elseif (isset($_ENV['YPF_MODE']))
                    $root->mode = $_ENV['YPF_MODE'];
                elseif (!isset($root->mode))
                    $root->mode = 'development';

                if (strpos($root->mode, 'production') === 0) {
                    $root->development = false;
                    $root->production = true;
                } else {
                    $root->development = true;
                    $root->production = false;
                }

                $root->includedLibs = array();
                $root->loadedHelpers = array();
                $root->loadedPlugins = array();

                if (self::$configuration && isset(self::$configuration->environments) && isset(self::$configuration->environments->{$root->mode}))
                    self::$environment = self::$configuration->environments->{$root->mode};

                self::loadPlugins(self::getSetting('plugins'));

                //Load framework, application and plugins helpers
                self::loadHelpers();

                if (self::$configuration)
                    YPFCache::fileBased(self::$configurationFile, self::$configuration);
            }

            foreach (self::$configurationRoot->loadedPlugins as $info)
                if (isset($info['initializer']))
                    require($info['initializer']);

            foreach (self::$configurationRoot->loadedHelpers as $helper)
                require($helper);

            if (!empty(self::$configurationRoot->includedLibs))
                set_include_path(get_include_path().PATH_SEPARATOR.implode(PATH_SEPARATOR, self::$configurationRoot->includedLibs));

            Logger::initialize();
            YPFDateTime::$local_date_format = self::getSetting('application.locale.date_format', 'd/m/Y');
            YPFDateTime::$local_time_format = self::getSetting('application.locale.time_format', 'H:i:s');
            YPFDateTime::$local_short_time_format = self::getSetting('application.locale.short_time_format', 'H:i');
            YPFDateTime::$local_date_time_format = self::getSetting('application.locale.date_time_format', 'd/m/Y H:i:s');
            YPFDateTime::$local_short_date_time_format = self::getSetting('application.locale.short_date_time_format', 'd/m/Y H:i');
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
                if (!self::$environment)
                    throw new NoApplicationError('Application configuration is invalid or incomplete', 'Configuration');

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
         * Get the main configuration file loaded by the framework
         * @return string path to the main configuration file loaded
         */
        public static function getConfigurationFile() {
            return self::$configurationFile;
        }

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

            if (!self::$environment)
                return $default;
            else
                $root = self::$environment;

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

            if (!self::$environment)
                return false;
            else
                $root = self::$environment;

            foreach ($parts as $i => $p)
            {
                if ($i == (count($parts)-1)) {
                    $root->{$p} = $value;
                    return true;
                } else {
                    if (!isset ($root->{$p}))
                        $root->{$p} = new YPFObject;

                    $root = $root->{$p};
                }
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
            $mode = self::$configurationRoot->mode.'-'.$name;

            if (isset(self::$databases[$mode]))
                return self::$databases[$mode];

            if (!($config = self::getSetting('databases.'.$name)))
                return null;

            if (!isset($config->type))
                return null;

            $driverFileName = self::getFileName(self::$configurationRoot->paths->ypf, 'framework/databases', $config->type.'.php');
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

            if (array_key_exists($classSuffix, self::$configurationRoot->classSources))
                $basePath = self::$configurationRoot->classSources[$classSuffix];
            else
                $basePath = self::$configurationRoot->classSources['*'];

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

            self::$configurationRoot->classSources[$suffix] = $path;
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

            foreach (self::$configurationRoot->applicationComponentPaths as $path)
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
            return self::$configurationRoot->paths;
        }

        /**
         * Returns the package element defined at the configuration file, if present
         * @return YPFObject
         */
        public static function getPackage() {
            if (isset(self::$configurationRoot->package))
                return self::$configurationRoot->package;
            else
                return false;
        }

        /**
         * Returns YPFramework working mode or "environment". Usually this will be 'production' or 'development'
         * @return string
         */
        public static function getMode() {
            return self::$configurationRoot->mode;
        }

        /**
         * Returns TRUE if the framework is working in development mode
         * @return boolean
         */
        public static function inDevelopment() {
            if (self::$configurationRoot && isset(self::$configurationRoot->development))
                return self::$configurationRoot->development;
            else
                return true;
        }

        /**
         * Returns TRUE if the framework is working in production mode
         * @return boolean
         */
        public static function inProduction() {
            if (self::$configurationRoot && isset(self::$configurationRoot->production))
                return self::$configurationRoot->production;
            else
                return false;
        }

        /**
         * Returns YPFramework version
         */
        public static function getVersion() {
            $configFileName = self::getFileName(self::$configurationRoot->paths->ypf, 'config.yml');
            $config = new YPFConfiguration($configFileName);
            return $config->package->version;
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
            $arguments = func_get_args();

            $i = 0; while (($i < count($arguments)) && (trim($arguments[$i]) == '')) $i++;
            $j = count($arguments)-1; while (($j > $i) && (trim($arguments[$i]) == '')) $j--;


            $new_arguments = array_splice($arguments, $i, $j-$i+1);

            $filePath = implode(DIRECTORY_SEPARATOR, $new_arguments);

            return $filePath;
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
                return strtoupper(substr($result, 0, 1)).substr($result, 1);
            else
                return strtolower(substr($result, 0, 1)).substr($result, 1);
        }

        public static function normalize($name) {
            $name = preg_replace('/\\s/', '-', $name);
            $name = preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $name);
            return $name;
        }

        //======================================================================

        private static function loadPlugins($selectPlugins = null) {
            self::$configurationRoot->loadedPlugins = array();

            $basePath = realpath(self::getFileName(self::$configurationRoot->applicationComponentPaths[0], 'extensions/plugins'));
            $ypfPath = realpath(self::getFileName(self::$configurationRoot->paths->ypf, 'extensions/plugins'));

            if ($selectPlugins !== null) {
                foreach ($selectPlugins as $plugin)
                    if (!self::loadPlugin ($plugin, self::getFileName($basePath, $plugin)))
                        throw new ErrorComponentNotFound ('plugin', $plugin);
            } else {
                $basePath = realpath(self::getFileName(self::$configurationRoot->applicationComponentPaths[0], 'extensions/plugins'));
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

            if (isset(self::$configurationRoot->loadedPlugins[$name]))
                throw new BaseError (sprintf("Plugin %s already loaded", $name), 'PLUGIN');

            self::$configurationRoot->loadedPlugins[$name] = array('path' => $path);

            self::$configurationRoot->applicationComponentPaths = array_splice(self::$configurationRoot->applicationComponentPaths, 0, -1);
            self::$configurationRoot->applicationComponentPaths[] = $path;
            self::$configurationRoot->applicationComponentPaths[] = self::$configurationRoot->paths->ypf;

            //Plugin has libs to include?
            $libPath = self::getFileName($path, 'extensions/libs');
            if (is_dir($libPath))
                self::$configurationRoot->includedLibs[] = $libPath;

            //Plugin has config file to include?
            $configFileName = self::getFileName($path, 'config.yml');
            if (is_file($configFileName))
                self::$configuration->append(new YPFConfiguration($configFileName));

            //Plugin has initializer?
            $initFileName = self::getFileName($path, 'init.php');
            if (is_file($initFileName))
                self::$configurationRoot->loadedPlugins[$name]['initializer'] = $initFileName;

            Logger::framework('DEBUG:PLUGIN', sprintf('%s loaded', $name));
            return true;
        }

        private static function loadHelpers() {
            self::$configurationRoot->loadedHelpers = self::getComponentPath('*.php', 'extensions/helpers', true, true);
        }
    }

    function __autoload($className) {
        $fileName = YPFramework::getClassPath($className, $type);

        if ($fileName === false)
            throw new ErrorComponentNotFound ($type, $className);

        require $fileName;

        if (!class_exists($className, false))
            throw new ErrorComponentNotFound ($type, $className);

        if (array_search('initialize', get_class_methods($className)) !== false)
            $className::initialize();
    }
?>
