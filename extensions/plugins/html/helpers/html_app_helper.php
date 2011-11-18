<?php
    class HtmlAppHelper extends YPFApplicationBase
    {
        public function htmlListPublicFiles($baseDir, $extension)
        {
            $lista = array();

            if ($this->output->profile !== null)
            {
                $fileBasePath = YPFramework::getFileName($this->paths->www, $baseDir, '_profiles', $this->output->profile);
                $urlBasePath = YPFramework::getFileName($baseDir, '_profiles', $this->output->profile);
            } else
            {
                $fileBasePath = YPFramework::getFileName($this->paths->www, $baseDir);
                $urlBasePath = $baseDir;
            }

            if (is_dir($fileBasePath))
            {
                $files = opendir($fileBasePath);
                while ($file = readdir($files))
                {
                    $file = YPFramework::getFileName($fileBasePath, $file);
                    if (is_file($file) && (substr($file, -strlen($extension)) == $extension))
                        $lista[] = YPFramework::getFileName($urlBasePath, basename($file));
                }
                usort($lista, function($f1, $f2) { return strcasecmp(basename($f1), basename($f2)); });
            }

            return $lista;
        }
    }

    YPFApplicationBase::__include('HtmlAppHelper');
?>
