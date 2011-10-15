<?php
    class YpfMediaController extends ControllerBase
    {
        public function css()
        {
            $compiledFile = YPFramework::getFileName($this->configuration->paths->tmp, 'media',
                                                     $this->output->profile, 'css');
            if (!file_exists($compiledFile))
            {
                if (!is_dir(dirname($compiledFile)))
                    mkdir(dirname($compiledFile), 0777, true);

                $files = $this->application->htmlListPublicFiles('css', '.css');
                $css = '';
                foreach ($files as $file)
                    $css .= file_get_contents(YPFramework::getFileName($this->configuration->paths->www, $file));

                require_once 'Minify/CSS.php';
                $css = Minify_CSS::minify($css);
                file_put_contents($compiledFile, $css);
            }

            ob_end_clean();
            if (YPFramework::inDevelopment())
                header('Expires: '.date("r", time()-24*3600));

            header('Content-type: text/css');

            if (extension_loaded('zlib'))
               ob_start('ob_gzhandler');
           readfile($compiledFile);
           ob_end_flush();
           exit;
        }

        public function js()
        {
            $compiledFile = YPFramework::getFileName($this->configuration->paths->tmp, 'media', $this->output->profile, 'js');
            if (!file_exists($compiledFile))
            {
                if (!is_dir(dirname($compiledFile)))
                    mkdir(dirname($compiledFile), 0777, true);

                $files = $this->application->htmlListPublicFiles('js', '.js');
                $script = '';

                require_once 'JSMin/JSMin.php';

                foreach ($files as $file)
                    if (strpos($file, '.min.') !== false)
                        $script .= file_get_contents(YPFramework::getFileName($this->configuration->paths->www, $file));
                    else
                        $script .= JSMin::minify(file_get_contents(YPFramework::getFileName($this->configuration->paths->www, $file)));

                file_put_contents($compiledFile, $script);
            }

            ob_end_clean();
            if (YPFramework::inDevelopment())
                header('Expires: '.date("r", time()-24*3600));

            header('Content-type: application/javascript');

            if (extension_loaded('zlib'))
               ob_start('ob_gzhandler');
            readfile($compiledFile);
            ob_end_flush();
            exit;
        }
    }
?>
