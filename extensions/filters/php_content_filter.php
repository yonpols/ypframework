<?php
    class PhpContentFilter extends YPFContentFilter {
        protected function process($content) {
            $previous = ob_get_clean(); ob_end_clean(); ob_start();

            eval('?>'.$content);
            $content = ob_get_clean();

            echo $previous;

            return $content;
        }
    }
?>
