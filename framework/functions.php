<?php
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
