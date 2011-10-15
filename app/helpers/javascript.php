<?php
    function js_on_action_load($controller, $action)
    {
        if ($controller != null)
        {
            return
                sprintf('if (typeof app.%s !== "undefined") if (typeof app.%s.%s !== "undefined") eval("app.%s.%s();");',
                    YPFramework::underscore($controller->getName()),
                    YPFramework::underscore($controller->getName()),
                    YPFramework::underscore($action),
                    YPFramework::underscore($controller->getName()),
                    YPFramework::underscore($action));
        }

        return "";
    }
?>
