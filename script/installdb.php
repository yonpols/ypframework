<?php
    //Change this settings according to your implementation
    define('YPF_PATH', realpath(dirname(__FILE__).'/..'));
    define('WWW_PATH', realpath(YPF_PATH.'/../www'));
    define('APP_PATH', realpath(YPF_PATH.'/../app'));

    require YPF_PATH.'/loader.php';
    YPFramework::installDatabase();
?>
