<?php
    abstract class YPFContentFilter extends YPFObject {
        protected $application;

        public static function processContent($fileName, &$content = null) {
            if ($content === null) {
                if (!is_readable($fileName))
                    return false;
                else
                    $content = file_get_contents($fileName);
            }

            $fileNameExtensions = explode('.', $fileName);
            array_shift($fileNameExtensions); 

            $filters = 0;
            foreach($fileNameExtensions as $extension) {

                $className = YPFramework::camelize($extension.'_content_filter');
                $classFileName = YPFramework::getClassPath($className, '', 'extensions/filters');

                if ($classFileName === false)
                    continue;

                require_once $classFileName;
                $filter = new $className(YPFramework::getApplication());

                $filter->filter($content);
                $filters++;
            }

            return $filters;
        }

        public final function __construct(Application $application) {
            parent::__construct();
            $this->application = $application;
        }

        public final function filter(&$content) {
            if (is_string($content))
                $content = $this->process($content);
            else
                foreach ($content as $name => $data) {
                    $content[$name] = $this->process($data);
                }
        }

        protected abstract function process($content);
    }
?>
