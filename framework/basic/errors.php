<?php
$message_template = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<base href="{APP_URL_BASE}/static/message/" /><title>{PAGE_TITLE}</title><style type="text/css">
html,div,span,head,h1,h2,h3,h4,h5,h6,p,blockquote,a,font,img,small,strong,ol,ul,li,body{margin:0;padding:0;border:0;
outline:0;font-size: 100%;background:transparent}ol,ul{list-style:none}blockquote,q{quotes: none}body{
font-family:Arial,Helvetica,sans-serif;line-height:1;background:#aaaaaa url(images/bg.jpg) repeat scroll 0 0;
font-size: 12px; line-height: 16px; color: #eaeaea;}
h1,h2,h3,h4 {color:#e5e5e5;font-style:normal;font-variant:normal;font-weight:lighter; padding: 10px 0 15px 0; display: block;word-wrap: break-word}
h1{font-size:38px;letter-spacing:2px;font-weight:bold;}h2{font-size:30px;letter-spacing:2px;}h3{font-size:14px;letter-spacing:1px;}
h4{font-size:12px;letter-spacing:1px;}a{color:#333333;text-decoration:none;letter-spacing:0.5px;}
button{background: url(images/button.png) no-repeat scroll 0 0;width:92px;height:43px;border:0 none;cursor:pointer;
color: #fff;font-weight: bold;text-shadow:1px 1px 0px #393939;}</style></head>
<body><div style="width: 885px;margin: 0 auto;"><div style="margin: auto;margin-top: 20px;margin-bottom: 10px;height: 50px;">
<div style="position: relative;float: left;padding-left: 42px;"><a href="{APP_INDEX}"><img src="images/logo.png" alt="logo"/></a></div>
<div id="contact_details" style="font-size: 13px;float: right;color: #333333;font-style: normal;line-height: 22px;padding-right: 42px;text-align: right;">
<p><a href="https://github.com/yonpols/ypframework">YPFramework Repo</a></p><p><a href="mailto:jp@jpmarzetti.com.ar">jp@jpmarzetti.com.ar</a>
</p></div></div><div style="clear:both"></div><div id="main">
<div style="background-image: url(images/page_background_top.png);background-repeat: no-repeat;height: 50px;"></div>
<div style="background-image: url(images/page_background_middle.png);background-repeat: repeat-y;padding: 0px 60px;">
<div style="text-align: center;padding-bottom: 20px;border-bottom: 1px solid #262626;"><h2>{PAGE_TITLE}</h2></div>
<div style="border-bottom: 1px solid #262626;border-top: 1px solid #4f4f4f;padding-top: 20px;padding-bottom: 20px;">
<div style="background-image: url(images/counter_bg_top.png);background-repeat: no-repeat;height: 20px;width: 644px;margin: 0 auto;"></div>
<div style="background-image: url(images/counter_bg_middle.png);background-repeat: repeat-y;width: 604px;padding-left: 20px;padding-right: 20px;margin: 0 auto;">
{PAGE_CONTENT}</div><div style="background-image: url(images/counter_bg_bottom.png);background-repeat: no-repeat;height: 20px;width: 644px;margin: 0 auto;">
</div></div><div style="border-top: 1px solid #4f4f4f;padding-top: 20px;padding-bottom: 20px;">{PAGE_FOOTER}</div></div>
<div style="background-image: url(images/page_background_bottom.png);background-repeat: no-repeat;height: 106px;"></div></div></div></body></html>
EOF;

    function output_error($production, $error_message, $error_trace, $application = null) {
        global $message_template;

        if (is_cli()) {
            fprintf(STDERR, $error_message."\n");
            fprintf(STDERR, $error_trace."\n");
            exit;
        }

        $previous_content = ob_get_clean();
        $request_uri = request_uri();
        $request_uri_base = substr($request_uri, 0, strrpos($request_uri, basename($_SERVER['PHP_SELF']))-1);


        $template_file_name = WWW_PATH.'/static/error/index.html';
        if (!(file_exists($template_file_name) && $template = file_get_contents($template_file_name)))
            $template = $message_template;

        $template = str_replace('{APP_URL_BASE}', $request_uri_base, $template);
        $template = str_replace('{APP_INDEX}', $request_uri_base, $template);

        if ($production) {
            $template = str_replace('{PAGE_TITLE}', 'Application error', $template);
            $template = str_replace('{PAGE_CONTENT}', 'There was an error while executing an application accion. Please try again later.', $template);
            $template = str_replace('{PAGE_FOOTER}', '', $template);
        } else {
            $template = str_replace('{PAGE_TITLE}', 'Application error', $template);

            $content = sprintf('<h3>Error: %s</h3>', $error_message);
            $content .= str_replace("\n", "<br />", '<code>'.htmlentities($error_trace).'</code>');

            if ($previous_content != '')
                $content .= '<hr /><h4>Application output</h4><code>'.$previous_content.'</code>';

            $footer = '';

            if ($application) {
                $route = $application->getCurrentRoute();
                $request = $application->getRequest();
                $data = $application->getData();

                if ($request)
                    $footer .= sprintf('<h3>Request</h3><code>%s</code>', $request->getDebugInfo());
                if ($route)
                    $footer .= sprintf('<h3>Action</h3><code>%s</code>', $route->getDebugInfo());
            } else {
                $footer .= sprintf('<h3>Request</h3><code>%s/%s</code>', $request_uri_base, get('_action'));
                $footer .= sprintf('<h3>Parameters</h3><code>%s</code>', var_export(array_merge($_GET, $_POST), true));
            }

            if (!empty ($_FILES))
                $footer .= sprintf('<h3>Uploaded files</h3><code>%s</code>', var_export($_FILES, true));


            $template = str_replace('{PAGE_CONTENT}', $content, $template);
            $template = str_replace('{PAGE_FOOTER}', $footer, $template);
        }

        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);

        echo $template;
        exit;
    }

    function php_error_catcher($errno, $errstr, $errfile = null, $errline = null) {
        if (error_reporting() == 0)
            return;

        if (class_exists('YPFramework'))
            output_error(YPFramework::inProduction(), sprintf("%s\nFile: %s(%s)", $errstr, $errfile, $errline), '');
        else
            output_error(false, $errstr, '');
    }

    function php_exception_catcher($exception) {
        if (class_exists('YPFramework')) {
            if (!($exception instanceof BaseError))
                    Logger::framework('ERROR', $exception->getMessage()."\n\t".$exception->getTraceAsString());
            output_error(YPFramework::inProduction(), $exception->getMessage(), $exception->getTraceAsString(), YPFramework::getApplication());
        } else
            output_error(false, $exception->getMessage(), $exception->getTraceAsString());
    }

    set_error_handler('php_error_catcher');
    set_exception_handler('php_exception_catcher')
?>
