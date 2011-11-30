<?php
    abstract class YPFOutputFilter extends YPFObject {
        private static $registeredContentTypeFilters = array();
        private static $registeredFormatFilters = array();

        protected $output;
        protected $data;
        protected $application;
        protected $controller;
        protected $action;
        protected $content;
        protected $contentType;

        public static function registerFilter($contentTypes, $formats, $filterName) {
            foreach ($contentTypes as $contentType)
                self::$registeredContentTypeFilters[$contentType] = $filterName;
            foreach ($formats as $format)
                self::$registeredFormatFilters[$format] = $filterName;
        }

        public static function getFilter($formats, $application, $controller, $action) {
            $className = 'HtmlOutputFilter';

            if (is_array($formats)) {
                foreach($formats as $format)
                    if (isset (self::$registeredContentTypeFilters[$format])) {
                        $className = self::$registeredContentTypeFilters[$format];
                        break;
                    } elseif (isset(self::$registeredFormatFilters[$format])) {
                        $className = self::$registeredFormatFilters[$format];
                        break;
                    }
            } elseif (isset (self::$registeredContentTypeFilters[$formats]))
                $className = self::$registeredContentTypeFilters[$formats];
            elseif (isset(self::$registeredFormatFilters[$formats]))
                $className = self::$registeredFormatFilters[$formats];

            //Prepare output filter
            $classFileName = YPFramework::getClassPath($className, '', 'extensions/filters');
            if ($classFileName !== false)
            {
                require_once $classFileName;
                return new $className($application, $controller, $action);
            } else
                throw new ErrorComponentNotFound ('Filter', $className);

            return false;
        }

        public function __construct(Application $application, $controller, $action) {
            parent::__construct();

            $this->application = $application;
            $this->output = $application->getOutput();
            $this->data = $application->getData();
            $this->controller = $controller;
            $this->action = $action;
        }

        public abstract function processOutput(YPFResponse $response);
    }

    YPFOutputFilter::registerFilter(array('text/html', 'application/xhtml+xml'), array('html'), 'HtmlOutputFilter');
    YPFOutputFilter::registerFilter(array('text/clean'), array('clean'), 'CleanOutputFilter');
    YPFOutputFilter::registerFilter(array('application/json'), array('json'), 'JsonOutputFilter');
    YPFOutputFilter::registerFilter(array('application/xml', 'text/xml'), array('xml'), 'XmlOutputFilter');
?>
