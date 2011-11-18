<?php
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
?>
