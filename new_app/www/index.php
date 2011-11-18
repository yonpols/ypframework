<?php
    //Change this settings according to your implementation
    define('WWW_PATH', realpath(dirname(__FILE__)));
    define('APP_PATH', realpath(dirname(__FILE__).'/..'));
    define('YPF_PATH', '{{ypf_path}}');

    require YPF_PATH.'/loader.php';
    YPFramework::run();
?>