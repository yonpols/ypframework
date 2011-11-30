<?php
    class YPFRequest {
        private $parameters;
        private $files;

        private $requestUri;
        private $userAgent;
        private $acceptContents;
        private $acceptLanguages;
        private $acceptCharset;

        private $method;
        private $action;

        private static $request = null;

        public static function get($parameters = null) {
            if (self::$request === null) {
                if (isset($_SERVER['HTTP_HOST']))
                    self::$request = new YPFRequest($parameters);
                else
                    self::$request = false;
            }

            return self::$request;
        }

        public function getParameter($path) {
            $path = explode('.', $path);
            $root = $this->parameters;

            foreach ($path as $p)
                if (isset ($root[$p]))
                    $root = $root[$p];
                else
                    return false;

            return $root;
        }

        public function getParameters() {
            return $this->parameters;
        }

        public function getFiles() {
            return $this->files;
        }

        public function getRequestUri() {
            return $this->requestUri;
        }

        public function getUserAgent() {
            return $this->userAgent;
        }

        public function getAcceptContents() {
            return $this->acceptContents;
        }

        public function getFlatAcceptContents() {
            $accept = array();
            foreach ($this->acceptContents as $contents)
                $accept = array_merge ($accept, $contents);

            return $accept;
        }

        public function getAcceptLanguages() {
            return $this->acceptLanguages;
        }

        public function getAcceptCharset() {
            return $this->acceptCharset;
        }

        public function getMethod() {
            return $this->method;
        }

        public function getAction() {
            return $this->action;
        }

        public function isMobile() {
            /*$profile = YPFramework::getSetting('application.profile', true);

            if ($profile === false)
                $this->output->profile = null;
            elseif ($profile === true)
            {*/
                $mobile = preg_match('/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$_SERVER['HTTP_USER_AGENT'])
                            || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i',substr($_SERVER['HTTP_USER_AGENT'],0,4));
                return ($mobile > 0);
                /*
 *
                $this->output->profile = ($mobile? 'mobile': null);
            }
            else
                $this->output->profile = $profile;*/

        }

        public function setParameters($parameters) {
            $this->parameters = $parameters;
        }

        public function setFiles($files) {
            $this->files = $files;
        }

        public function setRequestUri($requestUri) {
            $this->requestUri = $requestUri;
        }

        public function setUserAgent($userAgent) {
            $this->userAgent = $userAgent;
        }

        public function setAcceptContents($acceptContents) {
            $this->acceptContents = $acceptContents;
        }

        public function setAcceptLanguages($acceptLanguages) {
            $this->acceptLanguages = $acceptLanguages;
        }

        public function setAcceptCharset($acceptCharset) {
            $this->acceptCharset = $acceptCharset;
        }

        public function setMethod($method) {
            $this->method = $method;
        }

        public function setAction($action) {
            $this->action = $action;
        }

        public function __toString() {
            return sprintf('%s %s', $this->method, $this->action);
        }

        public function mergeParameters($parameters, $priority = false) {
            if ($priority)
                $this->parameters = array_merge ($this->parameters, $parameters);
            else
                $this->parameters = array_merge ($parameters, $this->parameters);
        }

        private function __construct($parameters = null) {
            if ($parameters !== null)
                $this->parameters = $parameters;
            else
                $this->parameters = array_merge($_GET, $_POST);

            if (isset ($this->parameters['_method'])) {
                $this->method = strtoupper($this->parameters['_method']);
                unset($this->parameters['_method']);
            } else
                $this->method = $_SERVER['REQUEST_METHOD'];

            if (isset ($this->parameters['_action'])) {
                $this->action = $this->parameters['_action'];
                unset($this->parameters['_action']);
            } else
                $this->action = YPFramework::getSetting('application.root');

            if ($this->action[0] == '/')
                $this->action = substr($this->action, 1);

            $this->files = $_FILES;

            $this->requestUri = sprintf('http%s://%s%s%s', (isset($_SERVER['HTTPS'])?'s':''),
                                        $_SERVER['HTTP_HOST'],
                                        $_SERVER['REQUEST_URI'],
                                        ($_SERVER['QUERY_STRING']!='')? '?'.$_SERVER['QUERY_STRING']: '');
            $this->userAgent = $_SERVER['HTTP_USER_AGENT'];

            $this->acceptContents = array();
            foreach (explode(',', $_SERVER['HTTP_ACCEPT']) as $str) {
                $mime = explode(';', $str);
                $accept_type = trim($mime[0]);
                $accept_value = 1;

                for($i = 1; $i < count($mime); $i++) {
                    if (preg_match('/q=(([0-9]*\\.[0-9]+)|([0-9]+(\\.[0-9]*)))/', $mime[$i], $matches)) {
                        $accept_value = $matches[1]*1;
                        break;
                    }
                }

                $accept_value = sprintf('%.1F', $accept_value);
                if (!isset($this->acceptContents[$accept_value]))
                    $this->acceptContents[$accept_value] = array();

                $this->acceptContents[$accept_value][] = $accept_type;
            }
            krsort($this->acceptContents);

            $temp = explode(';', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $this->acceptLanguages = explode(',', $temp[0]);

            $temp = explode(';', $_SERVER['HTTP_ACCEPT_CHARSET']);
            $this->acceptCharset = explode(',', $temp[0]);
        }
    }
?>
