<?php
    session_start();

    //Check for PHP version
    if ((!defined('PHP_VERSION_ID')) || PHP_VERSION_ID < 50300)
        include(YPF_PATH.'/app/errors/php_version.php');

    //Check for WWW_PATH
    if (!defined('WWW_PATH'))
        include(YPF_PATH.'/app/errors/no_www_path.php');

    ob_start();
    require_once YPF_PATH.'/framework/functions.php';

    //Load YPF clases
    require_once YPF_PATH.'/framework/basic/Object.php';
    require_once YPF_PATH.'/framework/basic/Initializable.php';
    require_once YPF_PATH.'/framework/basic/YPFramework.php';
    require_once YPF_PATH.'/framework/basic/Exceptions.php';
    require_once YPF_PATH.'/framework/basic/Logger.php';
    require_once YPF_PATH.'/framework/basic/Configuration.php';
    require_once YPF_PATH.'/framework/basic/Test.php';
    require_once YPF_PATH.'/framework/databases/DataBase.php';

    require_once YPF_PATH.'/framework/application/ApplicationBase.php';
    require_once YPF_PATH.'/framework/application/ControllerBase.php';
    require_once YPF_PATH.'/framework/application/Route.php';
    require_once YPF_PATH.'/framework/application/Cache.php';
    require_once YPF_PATH.'/framework/application/Filter.php';

    require_once YPF_PATH.'/framework/templates/ViewBase.php';

    require_once YPF_PATH.'/framework/records/IModelQuery.php';
    require_once YPF_PATH.'/framework/records/ModelQuery.php';
    require_once YPF_PATH.'/framework/records/ModelBaseRelation.php';
    require_once YPF_PATH.'/framework/records/ModelBase.php';

    //Load base clases
    require_once APP_PATH.'/base/Application.php';
    require_once APP_PATH.'/base/Controller.php';
    require_once APP_PATH.'/base/Model.php';
    require_once APP_PATH.'/base/View.php';

    //Load libs
    require_once YPF_PATH.'/lib/sfYaml/sfYamlParser.php';

    set_include_path(get_include_path().
                        PATH_SEPARATOR.realpath(YPF_PATH.'/lib').
                        PATH_SEPARATOR.realpath(APP_PATH.'/lib'));

    YPFramework::initialize();
?>
