<?php
    class HtmlViewHelper extends ViewBase
    {
        public function htmlRenderStylesheets()
        {
            if ($this->application->getSetting('pack_media'))
                printf('<link rel="stylesheet" type="text/css" href="%s" />', $this->data->routes->ypf_html_packed_css->path());
            else
            {
                $lista = $this->application->htmlListPublicFiles('css', '.css');
                foreach ($lista as $file)
                    printf('<link rel="stylesheet" type="text/css" href="%s/%s" />', $this->application->getSetting('url'), $file);
            }
        }

        public function htmlRenderJavascripts()
        {
            if ($this->application->getSetting('pack_media'))
                printf('<script type="text/javascript" src="%s"></script>', $this->data->routes->ypf_html_packed_js->path());
            else
            {
                $lista = $this->application->htmlListPublicFiles('js', '.js');
                foreach ($lista as $file)
                    printf('<script type="text/javascript" src="%s/%s"></script>', $this->application->getSetting('url'), $file);
            }
        }
    }

    ViewBase::__include('HtmlViewHelper');
?>
