<?php
    class YPFViewBase extends YPFObject {
        protected $templateFileName;
        protected $compiledFileName;

        protected $application;
        protected $data;
        protected $prefix;
        protected $profile;
        protected $tempPath;
        protected $formats;
        protected $outputType;

        private $content = array('main' => '');
        private $current_section = 'main';
        private $sections = array();

        public final function __construct(Application $application, $viewData = null, $viewProfile = null) {
            parent::__construct();

            $this->profile = $viewProfile;
            $this->application = $application;
            $this->formats = $application->getRequest()->getAcceptContents();
            $this->tempPath = YPFramework::getPaths()->tmp;

            $this->clear($viewData);

            if ($viewProfile !== null)
                $this->prefix = YPFramework::getFileName('_profiles', $viewProfile);
        }

        public function set($key, $value = true) {
            if (is_array($key))
                foreach($key as $n=>$v)
                    $this->data->{$n} = $v;
            elseif(is_object($key))
                foreach(get_object_vars($key) as $n=>$v)
                    $this->data->{$n} = $v;
            else
                $this->data->{$key} = $value;
        }

        public function clear($viewData = null) {
            if ($viewData === null)
                $this->data = new YPFObject;
            else
                $this->data = $viewData;

            $this->data->view = $this;
            $this->data->app = $this->application;
            $this->data->routes = $this->application->getRoutes();
            $this->data->paths = YPFramework::getPaths();
            $this->data->route = $this->application->getCurrentRoute();
        }

        public function render($viewName, $nested = false) {
            $time_start = microtime(true);

            $viewName = YPFramework::getFileName($this->prefix, YPFramework::underscore($viewName));
            $templateFileName = $this->getTemplateFile($viewName);
            $templatePath = substr($templateFileName, strrpos($templateFileName, '/views/')+7);

            $compiledFileName = YPFramework::getFileName($this->tempPath, $templatePath);

            //Needs compiling
            if (!file_exists($compiledFileName) || filemtime($compiledFileName) <= filemtime($templateFileName))
                $this->compile($viewName, $templateFileName, $compiledFileName);

            $className = get_class($this);
            foreach($className::before('filter') as $proc)
                if (is_callable (array($this, $proc)))
                    call_user_func(array($this, $proc));
                else
                    throw new ErrorNoCallback(get_class($this), $proc);

            $this->content_for($this->current_section);
            include($compiledFileName);
            $this->end_content();

            foreach($className::after('filter') as $proc)
                if (is_callable (array($this, $proc)))
                    call_user_func(array($this, $proc));
                else
                    throw new ErrorNoCallback(get_class($this), $proc);

            $time_end = microtime(true);
            Logger::framework('DEBUG:VIEW_RENDER', sprintf('%s rendered (%.2F secs)',
                $templatePath, ($time_end-$time_start)));

            if (!$nested) {
                $this->templateFileName = $templateFileName;
                $this->compiledFileName = $compiledFileName;
            }

            return $this->content;
        }

        public function getTemplateFileName() {
            return $this->templateFileName;
        }

        public function getCompiledFileName() {
            return $this->compiledFileName;
        }

        public function getOutputType() {
            return $this->outputType;
        }

        private function getTemplateFile($viewName) {
            $templateFiles = array();

            foreach($this->formats as $priority) {
                foreach ($priority as $mime) {
                    $extensions = Mime::getExtension($mime);
                    if ($extensions) {
                        foreach ($extensions as $ext) {
                            $templateFiles = YPFramework::getComponentPath($viewName.'.*'.$ext, 'views', true);
                            if (($templateFiles !== false) && (count($templateFiles) > 0)) {
                                $contentType = $mime;
                                break 3;
                            }
                        }
                    }
                }
            }

            if (($templateFiles === false) || (count($templateFiles) == 0))
                throw new ErrorComponentNotFound ('TEMPLATE', $viewName);
            elseif (count($templateFiles) > 1 && YPFramework::inDevelopment())
                throw new ErrorMultipleViewsFound ($viewName);

            if ($contentType == '*/*')
                $contentType = Mime::getMimeFromFile ($templateFiles[0]);

            $this->outputType = $contentType;
            return $templateFiles[0];
        }

        private function compile($viewName, $templateFile, $compiledFile) {
            $compiledDir = dirname($compiledFile);
            if (!file_exists(dirname($compiledFile))) mkdir(dirname($compiledFile), 0744, true);

            $source = file_get_contents($templateFile);

            $num = preg_match_all('/\{%([^}]*)\}/', $source, $matches);
            if($num > 0) {
                for($i = 0; $i < $num; $i++) {
                    $match = $matches[1][$i];
                    $new = $this->transformSyntax($viewName, $matches[1][$i]);
                    $source = str_replace($matches[0][$i], $new, $source);
                }
            }

            file_put_contents($compiledFile, $source);
        }

        private function transformSyntax($viewName, $input) {
            if (($pos = strrpos($viewName, '/')) !== false)
                $prefixPath = substr($viewName, 0, $pos+1);
            else
                $prefixPath = '';

            preg_match('/^((if|switch|elsif|ifnot|begin_section|end_section|foreach|end|endswitch|else|case|include):)?(.*)$/', $input, $parts);
            $command = $parts[2];
            $parameters = $parts[3];

            $string = '<?php ';
            switch($command) { // check for a template statement
                case 'if':
                case 'switch':
                    $string .= $command . '(' . $this->replaceSyntax($parameters) . ') { ' . ($command == 'switch' ? 'default: ' : '');
                    break;
                case 'elsif':
                    $string .= '} elseif (' . $this->replaceSyntax($parameters) . ') { ';
                    break;
                case 'ifnot':
                    $string .= 'if (!(' . $this->replaceSyntax($parameters) . ')) { ';
                    break;
                case 'begin_section':
                    $string .=  sprintf("\$this->content_for('%s');", $parameters);
                    break;
                case 'end_section':
                    $string .=  '$this->end_content();';
                    break;
                case 'foreach':
                    $pieces = explode(',', $parameters);
                    $string .= 'foreach(' . $this->replaceSyntax($pieces[0]) . ' as ';
                    $string .= $this->replaceSyntax($pieces[1]);
                    if(sizeof($pieces) == 3) // prepares the $value portion of foreach($var as $key=>$value)
                        $string .= '=>' . $this->replaceSyntax($pieces[2]);
                    $string .= ') { ';
                    break;
                case 'end':
                case 'endswitch':
                    $string .= '}';
                    break;
                case 'else':
                    $string .= '} else {';
                    break;
                case 'case':
                    $string .= 'break; case ' . $this->replaceSyntax($parameters) . ':';
                    break;
                case 'include':
                    if (strpos($parameters, '/') === false)
                        $parameters = $prefixPath . $parameters;

                    $string .= '$this->render("' . addslashes($parameters) . '", true);';
                    break;
                default:
                    $string .= 'echo ' . $this->replaceSyntax($input) . ';';
                    break;
            }
            $string .= ' ?>';
            return $string;
        }

        private function replaceSyntax($syntax) {
            $from = array(
                '/(^|\[|,|\(|\+| )([a-zA-Z_][a-zA-Z0-9_]*)($|\.|\)|,|\[|\]|\+)/',
                '/(^|\[|,|\(|\+| )([a-zA-Z_][a-zA-Z0-9_]*)($|\.|\)|,|\[|\]|\+)/' // again to catch those bypassed by overlapping start/end characters
            );
            $to = array(
                '$1$this->data->$2$3',
                '$1$this->data->$2$3'
            );

            $syntax = preg_replace($from, $to, $syntax);
            $start = 0;

            while (preg_match('/\./', $syntax, $matches, PREG_OFFSET_CAPTURE, $start))
            {
                $pos = $matches[0][1];

                $str_start = 0;
                $in_str = false;

                //while (preg_match("/'(\\'|[^'])*'/", $syntax, $matches, PREG_OFFSET_CAPTURE, $str_start))
                while ($matches = $this->match_string($syntax, $str_start))
                {
                    /*if ($matches[0][1] < $pos && $pos <= ($matches[0][1]+strlen($matches[0][0])))
                    {
                        $in_str = true;
                        break;
                    }
                    $str_start = ($matches[0][1]+strlen($matches[0][0]));*/

                    if ($matches[0] < $pos && $pos < $matches[1])
                    {
                        $in_str = true;
                        break;
                    }
                    $str_start = $matches[1];
                }
                if (!$in_str)
                    $syntax = substr ($syntax, 0, $pos).'->'.substr ($syntax, $pos+1);

                $start = $pos + 1;
            }

            $syntax = str_replace('$this->data->null', 'null', $syntax);
            $syntax = str_replace('$this->data->true', 'true', $syntax);
            $syntax = str_replace('$this->data->false', 'false', $syntax);

            return  $syntax;
        }

        private function match_string($text, $start) {
            $ini = stripos($text, "'", $start);
            if ($ini === false)
                return false;
            $fin = strlen($text);

            for ($i = $ini+1; $i < strlen($text); $i++)
                if ($text[$i] == "\\")
                    $i++;
                elseif ($text[$i] == "'")
                {
                    $fin = $i+1;
                    break;
                }

            return array($ini, $fin);
        }

        private function content_for($section) {
            if (!isset($this->content[$this->current_section]))
                $this->content[$this->current_section] = ob_get_clean();
            else
                $this->content[$this->current_section] .= ob_get_clean();

            ob_start();
            array_push($this->sections, $this->current_section);
            $this->current_section = $section;
        }

        private function end_content() {
            if (!isset($this->content[$this->current_section]))
                $this->content[$this->current_section] = ob_get_clean();
            else
                $this->content[$this->current_section] .= ob_get_clean();

            $this->current_section = array_pop($this->sections);
            ob_start();
        }
    }
?>