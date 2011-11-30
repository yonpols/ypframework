<?php
    function css_fila($i)
    {
        return $i % 2;
    }

    function to_html($text, $br=true)
    {
        if ($br)
            return str_replace("\n", "<br />", htmlentities($text, ENT_COMPAT, 'utf-8'));
        else
            return htmlentities($text);
    }

    function to_js($js) {
        return str_replace("\n", "\\n", addslashes($js));
    }
?>
