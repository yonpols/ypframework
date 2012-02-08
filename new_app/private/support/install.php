<?php
$htaccess = <<<EOF
SetEnv YPF_MODE {{ypf_mode}}

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase {{rewrite_base}}

  RewriteRule ^(.*)/$ /$1 [R=301,L]
  RewriteRule "(^|/)\." - [F]

  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ index.php?_action=$1 [L,QSA]
</IfModule>

# ----------------------------------------------------------------------
# Better website experience for IE users
# ----------------------------------------------------------------------

# Force the latest IE version, in various cases when it may fall back to IE7 mode
#  github.com/rails/rails/commit/123eb25#commitcomment-118920
# Use ChromeFrame if it's installed for a better experience for the poor IE folk

<IfModule mod_setenvif.c>
  <IfModule mod_headers.c>
    BrowserMatch MSIE ie
    Header set X-UA-Compatible "IE=Edge,chrome=1" env=ie
  </IfModule>
</IfModule>

<IfModule mod_headers.c>
# Because X-UA-Compatible isn't sent to non-IE (to save header bytes),
#   We need to inform proxies that content changes based on UA
  Header append Vary User-Agent
# Cache control is set only if mod_headers is enabled, so that's unncessary to declare
</IfModule>

# ----------------------------------------------------------------------
# Webfont access
# ----------------------------------------------------------------------

# allow access from all domains for webfonts
# alternatively you could only whitelist
#   your subdomains like "sub.domain.com"

<FilesMatch "\.(ttf|otf|eot|woff|font.css)$">
  <IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
  </IfModule>
</FilesMatch>

# ----------------------------------------------------------------------
# Proper MIME type for all files
# ----------------------------------------------------------------------

# audio
AddType audio/ogg                      oga ogg

# video
AddType video/ogg                      ogv
AddType video/mp4                      mp4
AddType video/webm                     webm

# Proper svg serving. Required for svg webfonts on iPad
#   twitter.com/FontSquirrel/status/14855840545
AddType     image/svg+xml              svg svgz
AddEncoding gzip                       svgz

# webfonts
AddType application/vnd.ms-fontobject  eot
AddType font/truetype                  ttf
AddType font/opentype                  otf
AddType application/x-font-woff        woff

# assorted types
AddType image/x-icon                   ico
AddType image/webp                     webp
AddType text/cache-manifest            appcache manifest
AddType text/x-component               htc
AddType application/x-chrome-extension crx
AddType application/x-xpinstall        xpi
AddType application/octet-stream       safariextz

<IfModule mod_deflate.c>
  # force deflate for mangled headers developer.yahoo.com/blogs/ydn/posts/2010/12/pushing-beyond-gzipping/
  <IfModule mod_setenvif.c>
    <IfModule mod_headers.c>
      SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s,?\s(gzip|deflate)?|X{4,13}|~{4,13}|-{4,13})$ HAVE_Accept-Encoding
      RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding
    </IfModule>
  </IfModule>
  # html, txt, css, js, json, xml, htc:
  <IfModule filter_module>
    FilterDeclare   COMPRESS
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type /text/(html|css|javascript|plain|x(ml|-component))/
    FilterProvider  COMPRESS  DEFLATE resp=Content-Type /application/(javascript|json|xml|x-javascript)/
    FilterChain     COMPRESS
    FilterProtocol  COMPRESS  change=yes;byteranges=no
  </IfModule>

  <IfModule !mod_filter.c>
    # Legacy versions of Apache
    AddOutputFilterByType DEFLATE text/html text/plain text/css application/json
    AddOutputFilterByType DEFLATE text/javascript application/javascript application/x-javascript
    AddOutputFilterByType DEFLATE text/xml application/xml text/x-component
  </IfModule>
  # webfonts and svg:
  <FilesMatch "\.(ttf|otf|eot|svg)$" >
    SetOutputFilter DEFLATE
  </FilesMatch>
</IfModule>

# ----------------------------------------------------------------------
# Expires headers (for better cache control)
# ----------------------------------------------------------------------

# these are pretty far-future expires headers
# they assume you control versioning with cachebusting query params like
#   <script src="application.js?20100608">
# additionally, consider that outdated proxies may miscache
#   www.stevesouders.com/blog/2008/08/23/revving-filenames-dont-use-querystring/

# if you don't use filenames to version, lower the css and js to something like
#   "access plus 1 week" or so

<IfModule mod_expires.c>
  ExpiresActive on

# Perhaps better to whitelist expires rules? Perhaps.
  ExpiresDefault                          "access plus 1 month"

# cache.appcache needs re-requests in FF 3.6 (thx Remy ~Introducing HTML5)
  ExpiresByType text/cache-manifest       "access plus 0 seconds"

# your document html
  ExpiresByType text/html                 "access plus 0 seconds"

# data
  ExpiresByType text/xml                  "access plus 0 seconds"
  ExpiresByType application/xml           "access plus 0 seconds"
  ExpiresByType application/json          "access plus 0 seconds"

# rss feed
  ExpiresByType application/rss+xml       "access plus 1 hour"

# favicon (cannot be renamed)
  ExpiresByType image/x-icon              "access plus 1 week"

# media: images, video, audio
  ExpiresByType image/gif                 "access plus 1 month"
  ExpiresByType image/png                 "access plus 1 month"
  ExpiresByType image/jpg                 "access plus 1 month"
  ExpiresByType image/jpeg                "access plus 1 month"
  ExpiresByType video/ogg                 "access plus 1 month"
  ExpiresByType audio/ogg                 "access plus 1 month"
  ExpiresByType video/mp4                 "access plus 1 month"
  ExpiresByType video/webm                "access plus 1 month"

# htc files  (css3pie)
  ExpiresByType text/x-component          "access plus 1 month"

# webfonts
  ExpiresByType font/truetype             "access plus 1 month"
  ExpiresByType font/opentype             "access plus 1 month"
  ExpiresByType application/x-font-woff   "access plus 1 month"
  ExpiresByType image/svg+xml             "access plus 1 month"
  ExpiresByType application/vnd.ms-fontobject "access plus 1 month"

# css and javascript
  ExpiresByType text/css                  "access plus 2 months"
  ExpiresByType application/javascript    "access plus 2 months"
  ExpiresByType text/javascript           "access plus 2 months"

  <IfModule mod_headers.c>
    Header append Cache-Control "public"
  </IfModule>
</IfModule>

# ----------------------------------------------------------------------
# ETag removal
# ----------------------------------------------------------------------

# Since we're sending far-future expires, we don't need ETags for
# static content.
#   developer.yahoo.com/performance/rules.html#etags
FileETag None

AddDefaultCharset utf-8
AddCharset utf-8 .html .css .js .xml .json .rss

php_flag register_globals Off
EOF;

$private_htaccess = <<<EOF
# Don't allow access from the web to this directory
Order Deny,Allow
Deny from All
EOF;

$index = <<<EOF
<?php
    //Change this settings according to your implementation
    define('WWW_PATH', '{{www_path}}');
    define('APP_PATH', '{{app_path}}');
    define('YPF_PATH', '{{ypf_path}}');

    require YPF_PATH.'/loader.php';
    YPFramework::run();
?>
EOF;

    $www_path = dirname($_SERVER['SCRIPT_FILENAME']);
    $app_path = $www_path . DIRECTORY_SEPARATOR . 'private';
    $ypf_path = $app_path . DIRECTORY_SEPARATOR . 'ypf';

    if (!empty($_POST)) {
        $mode = isset($_POST['mode']) ? $_POST['mode'] : 'development';
        $base = dirname($_SERVER['PHP_SELF']);

        $htaccess = str_replace('{{ypf_mode}}', $mode, $htaccess);
        $htaccess = str_replace('{{rewrite_base}}', $base, $htaccess);
        if (@file_put_contents('.htaccess', $htaccess)) {
            $ypf_path = $_POST['ypf_path'];

            $index = str_replace('{{www_path}}', $www_path, $index);
            $index = str_replace('{{app_path}}', $app_path, $index);
            $index = str_replace('{{ypf_path}}', $ypf_path, $index);
            if (@file_put_contents('private/.htaccess', $private_htaccess)) {
                if (@unlink($_SERVER['SCRIPT_FILENAME']) && @file_put_contents('index.php', $index)) {
                    header('Location: index.php');
                } else
                    $error = 'Error: could\'nt write index.php file';
            } else
                $error = 'Error: could\'nt protect private directory';
        } else
            $error = 'Error: could\'nt write .htaccess file';
    } else {
        $mode = isset($_POST['mode']) ? $_POST['mode'] : 'development';
        if (isset($_POST['ypf_path']))
            $ypf_path = $_POST['ypf_path'];
    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <base href="static/message/" /><title>YPFramework Installation</title>
        <style type="text/css">
            html,div,span,head,h1,h2,h3,h4,h5,h6,p,blockquote,a,font,img,small,
            strong,ol,ul,li,body {
                margin:0;
                padding:0;
                border:0;
                outline:0;
                font-size: 100%;
                background:transparent
            }
            ol,ul {
                list-style:none
            }
            blockquote,q{
                quotes: none
            }
            body{
                font-family:Arial,Helvetica,sans-serif;
                line-height:1;
                background:#aaaaaa url(images/bg.jpg) repeat scroll 0 0;
                font-size: 12px;
                line-height: 16px;
                color: #eaeaea;
            }
            h1,h2,h3,h4 {
                color:#e5e5e5;
                font-style:normal;
                font-variant:normal;
                font-weight:lighter;
                padding: 10px 0 15px 0;
                display: block;
                word-wrap: break-word
            }
            h1{
                font-size:38px;
                letter-spacing:2px;
                font-weight:bold;
            }
            h2{
                font-size:30px;
                letter-spacing:2px;
            }
            h3{
                font-size:14px;
                letter-spacing:1px;
            }
            h4{
                font-size:12px;
                letter-spacing:1px;
            }
            a{
                color:#333333;
                text-decoration:none;
                letter-spacing:0.5px;
            }
            button, input[type=submit]{
                background: url(images/button.png) no-repeat scroll 0 0;
                width:92px;
                height:43px;
                border:0 none;
                cursor:pointer;
                color: #fff;
                font-weight: bold;
                text-shadow:1px 1px 0px #393939;
            }
            form ul {
                width: 100%;
            }
            form>ul>li {
                display: block;
                padding: 10px;
            }
            form>ul>li>label {
                width: 30%;
                display: inline-block;
                font-weight: bold;
            }
            form>ul>li>input[type=text],form>ul>li>select {
                width: 65%;
            }
            div.error {
                margin: 15px;
                padding: 10px;
                color: #C00;
                font-size: 1.4em;
                font-weight: bold;
                background-color: #fff;
            }
        </style>
    </head>
    <body>
        <div style="width: 885px;margin: 0 auto;">
            <div style="margin: auto;margin-top: 20px;margin-bottom: 10px;height: 50px;">
                <div style="position: relative;float: left;padding-left: 42px;">
                    <a href="index.php"><img src="images/logo.png" alt="logo"/></a>
                </div>
                <div id="contact_details" style="font-size: 13px;float: right;color: #333333;font-style: normal;line-height: 22px;padding-right: 42px;text-align: right;">
                    <p>
                        <a href="https://github.com/yonpols/ypframework">YPFramework Repo</a></p><p><a href="mailto:jp@jpmarzetti.com.ar">jp@jpmarzetti.com.ar</a>
                    </p>
                </div>
            </div>
            <div style="clear:both"></div>
            <div id="main">
                <div style="background-image: url(images/page_background_top.png);background-repeat: no-repeat;height: 50px;"></div>
                <div style="background-image: url(images/page_background_middle.png);background-repeat: repeat-y;padding: 0px 60px;">
                    <div style="text-align: center;padding-bottom: 20px;border-bottom: 1px solid #262626;"><h2>YPFramework Installation</h2></div>
                    <div style="border-bottom: 1px solid #262626;border-top: 1px solid #4f4f4f;padding-top: 20px;padding-bottom: 20px;">
                        <div style="background-image: url(images/counter_bg_top.png);background-repeat: no-repeat;height: 20px;width: 644px;margin: 0 auto;"></div>
                        <div style="background-image: url(images/counter_bg_middle.png);background-repeat: repeat-y;width: 604px;padding-left: 20px;padding-right: 20px;margin: 0 auto;">
                            <form method="post">
                                <ul>
                                    <li>
                                        <label for="mode">Application Mode</label>
                                        <select id="mode" name="mode">
                                            <option value="development"<?php if($mode=='development') echo ' selected="selected"'; ?>>Development</option>
                                            <option value="production"<?php if($mode=='production') echo ' selected="selected"'; ?>>Production</option>
                                            <option value="testing"<?php if($mode=='testing') echo ' selected="selected"'; ?>>Testing</option>
                                        </select>
                                    </li>
                                    <li>
                                        <label for="ypf_path">YPFramework Location</label>
                                        <input id="ypf_path" name="ypf_path" type="text" value="<?php echo htmlentities($ypf_path, ENT_QUOTES); ?>" />
                                    </li>
                                    <li>
                                        <label></label>
                                        <input type="submit" value="Install" />
                                    </li>
                                </ul>

                            </form>
                        </div>
                        <div style="background-image: url(images/counter_bg_bottom.png);background-repeat: no-repeat;height: 20px;width: 644px;margin: 0 auto;"></div>
                    </div>
                    <div style="border-top: 1px solid #4f4f4f;padding-top: 20px;padding-bottom: 20px;">
                        <?php if (isset($error)) { ?>
                        <div class="error"><?php echo $error; ?></div>
                        <?php } ?>
                    </div></div>
                <div style="background-image: url(images/page_background_bottom.png);background-repeat: no-repeat;height: 106px;"></div>
            </div>
        </div>
    </body>
</html>