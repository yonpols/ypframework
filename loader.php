<?php
    session_start();

    //Check for PHP version
    if ((!defined('PHP_VERSION_ID')) || PHP_VERSION_ID < 50300)
        include(YPF_PATH.'/errors/php_version.php');

    //Check for WWW_PATH
    if (!defined('WWW_PATH'))
        include(YPF_PATH.'/errors/no_www_path.php');

    ob_start();
    require YPF_PATH.'/framework/version.php';
    require YPF_PATH.'/framework/basic/functions.php';
    require YPF_PATH.'/framework/basic/errors.php';

    //Basic clases
    require YPF_PATH.'/framework/basic/YPFObject.php';
    require YPF_PATH.'/framework/basic/YPFDateTime.php';
    require YPF_PATH.'/framework/basic/YPFExceptions.php';
    require YPF_PATH.'/framework/basic/YPFConfiguration.php';

    //Support classes
    require YPF_PATH.'/framework/support/YPFCache.php';
    require YPF_PATH.'/framework/support/YPFDataBase.php';
    require YPF_PATH.'/framework/support/Logger.php';
    require YPF_PATH.'/framework/support/Mime.php';

    //Application classes
    require YPF_PATH.'/framework/application/YPFApplicationBase.php';
    require YPF_PATH.'/framework/application/input/YPFRequest.php';
    require YPF_PATH.'/framework/application/input/YPFRoute.php';
    require YPF_PATH.'/framework/application/processing/YPFControllerBase.php';
    require YPF_PATH.'/framework/application/processing/YPFViewBase.php';
    require YPF_PATH.'/framework/application/data/IYPFModelQuery.php';
    require YPF_PATH.'/framework/application/data/YPFModelQuery.php';
    require YPF_PATH.'/framework/application/data/YPFModelBaseRelation.php';
    require YPF_PATH.'/framework/application/data/YPFBelongsToRelation.php';
    require YPF_PATH.'/framework/application/data/YPFHasOneRelation.php';
    require YPF_PATH.'/framework/application/data/YPFHasManyRelation.php';
    require YPF_PATH.'/framework/application/data/YPFHasManyThroughRelation.php';
    require YPF_PATH.'/framework/application/data/YPFModelBase.php';
    require YPF_PATH.'/framework/application/output/YPFContentFilter.php';
    require YPF_PATH.'/framework/application/output/YPFOutputFilter.php';
    require YPF_PATH.'/framework/application/output/YPFResponse.php';

    //Framework
    require YPF_PATH.'/framework/YPFramework.php';

    if (!defined('YPF_CMD')) {
        //Load base clases
        require APP_PATH.'/support/base/Application.php';
        require APP_PATH.'/support/base/Controller.php';
        require APP_PATH.'/support/base/Model.php';
        require APP_PATH.'/support/base/View.php';
    } else {
        require YPF_PATH.'/new_app/private/support/base/Application.php';
        require YPF_PATH.'/new_app/private/support/base/Controller.php';
        require YPF_PATH.'/new_app/private/support/base/Model.php';
        require YPF_PATH.'/new_app/private/support/base/View.php';

        //Load libs
        require YPF_PATH.'/extensions/libs/sfYaml/sfYamlDumper.php';
    }

    //Load libs
    require YPF_PATH.'/extensions/libs/sfYaml/sfYamlParser.php';

    set_include_path(get_include_path().
                        PATH_SEPARATOR.realpath(YPF_PATH.'/extensions/libs').
                        PATH_SEPARATOR.realpath(APP_PATH.'/extensions/libs'));

    YPFramework::initialize();
?>
