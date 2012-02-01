<?php
    function request_uri() {
        $uri = 'http';

        if (isset ($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
            $uri .= 's';

        $uri .= sprintf('://%s', $_SERVER['HTTP_HOST']);
        if ($_SERVER['SERVER_PORT'] != '80')
            $uri .= ':'.$_SERVER['SERVER_PORT'];
        $uri .= $_SERVER['PHP_SELF'];

        return $uri;
    }

    function is_cli() {
         if(php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
              return true;
         } else {
              return false;
         }
    }


    function get($index, $value = NULL)
	{
		if (isset($_GET[$index]))
			return $_GET[$index];
		else
			return $value;
	}

	function post($index, $value = NULL)
	{
		if (isset($_POST[$index]))
			return $_POST[$index];
		else
			return $value;
	}

	function session($index, $value = NULL)
	{
		if (isset($_SESSION[$index]))
			return $_SESSION[$index];
		else
			return $value;
	}

    function to_hash()
    {
        $result = array();
        for ($i = 0; $i < func_num_args(); $i+=2)
            $result[func_get_arg ($i)] = func_get_arg ($i+1);

        return $result;
    }

    function normalize($str, $pattern = '(\\s|,)*')
    {
        $str = preg_replace('/[^\w\-~_\.]+/u', '-', $str);
        return strtolower($str);
    }

    function arraize($value)
    {
        if ($value === null)
            return array();
        elseif(is_array($value))
            return $value;
        else
            return array($value);
    }

    function array_compare($a1, $a2)
    {
        $equal = true;
        foreach($a1 as $k=>$v)
        {
            $equal = $equal || (isset($a2[$k]) && $a2[$k] == $a1);
            unset($a2[$k]);
        }

        return $equal && empty($a2);
    }

    function array_merge_deep($a1, $a2)
    {
        if (is_array($a1) && is_array($a2)) {
            $result = array();

            foreach($a1 as $k=>$v)
            {
                if (isset($a2[$k])) {
                    $result[$k] = array_merge_deep ($v, $a2[$k]);
                    unset($a2[$k]);
                } else
                    $result[$k] = $v;
            }

            foreach($a2 as $k=>$v)
                $result[$k] = $v;

            return $result;
        } else
            return $a2;
    }

?>
