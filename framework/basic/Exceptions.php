<?php
    class BaseError extends Exception
    {
        public function __construct($message, $subtype = null)
        {
            parent::__construct($message);
            Logger::framework('ERROR'.(($subtype===null)?'':':'.$subtype), $message);
        }
    }

    class ErrorComponentNotFound extends BaseError
    {
        public function __construct ($componentType, $componentName)
        {
            parent::__construct(sprintf('%s not found: %s', $componentType, $componentName));
        }
    }

    class ErrorNoRoute extends BaseError
    {
        public function __construct ($action)
        {
            parent::__construct(sprintf('No route for action: %s %s', $_SERVER['REQUEST_METHOD'], $action), 'ROUTE');
        }
    }

    class ErrorMultipleViewsFound extends BaseError
    {
        public function __construct ($viewName)
        {
            parent::__construct(sprintf('There are multiple files for view: %s', $viewName), 'VIEW');
        }
    }

    class ErrorOutOfFlow extends BaseError
    {
        public function __construct ($content)
        {
            parent::__construct(sprintf('Content has been output before action rendering: %s', $content));
        }
    }

    class ErrorNoCallback extends BaseError
    {
        public function __construct ($class, $callback)
        {
            parent::__construct(sprintf('Function callback %s does not exist on class: %s', $callback, $class));
        }
    }

    class ErrorNoAction extends BaseError
    {
        public function __construct ($controller, $action)
        {
            parent::__construct(sprintf('%s does not have an action defined for name: %s', $controller, $action));
        }
    }

    class ErrorCorruptFile extends BaseError
    {
        public function __construct ($fileName, $message)
        {
            parent::__construct(sprintf('%s is corrupt or invalid: %s', $fileName, $message));
        }
    }

    class ErrorDataModel extends BaseError
    {
        public function __construct ($model, $message)
        {
            parent::__construct(sprintf('In model %s: %s', $model, $message));
        }
    }

    //==========================================================================
    class ErrorMessage extends Exception { }

    class NoticeMessage extends Exception { }

    class StopRenderException extends Exception { }

    class JumpToNextActionException extends Exception { }
?>
