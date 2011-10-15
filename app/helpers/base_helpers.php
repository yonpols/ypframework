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

    function arraize($value)
    {
        if ($value === null)
            return array();
        elseif(is_array($value))
            return $value;
        else
            return array($value);
    }
?>
