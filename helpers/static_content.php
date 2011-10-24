<?php
    function removeTemporaryFiles($parent = '')
    {
        $dir = Configuration::get()->paths->temp.'/'.$parent;
        $dd = opendir($dir);

        while ($file = readdir($dd))
        {
            if ($file == '.' || $file == '..')
                continue;

            if (is_dir($dir.'/'.$file))
                removeTemporaryFiles($parent.'/'.$file);
            else
                unlink ($dir.'/'.$file);
        }

        if ($parent != '')
            rmdir ($dir);
    }
?>
