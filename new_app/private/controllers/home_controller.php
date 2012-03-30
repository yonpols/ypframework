<?php
    /**
     * A controller is the glue element between your application presentation layer
     * and your business logic and data (which resides in models)
     *
     * Each function defined in a controller is called an action. You can then
     * set a route that calls an action of a controller (see routes.yml). In an
     * action you will process user input, call models if you need and output data
     * relevant to the presentation.
     *
     * If nothing specified (as usual) YPF will search a view file under views/.
     * This file is expected to reside in a directory matching the name of the
     * controller. For example, if you have a UsersController the path will be
     * views/users/. If you have a namespace, for example Security\UsersController
     * the path will be views/security/users/.
     *
     * The filename will be the underscore version of your action:
     *  for login -> login.html
     *  for resetPassword -> reset_password.html
     *
     * All variables set inside an action usign:
     *  $this->data->var1  or the short ($this->var1)
     *  $this->data->var2  or the short ($this->var2)
     * will be available on your view as var1, var2
     *
     * You can also pass variables to your layout (it's an application shared
     * html layout, also a view) by setting them to the $this->output object.
     * In your layout view you can access this var using output.var1
     *  $this->output->var1 = ...
     *
     * Properties available in the controller for use in an action:
     *
     * $this->name            name of the controller (including namespace)
     * $this->application     instance of application object.
     * $this->routes          instance of router. You can build routes by using
     *                        $this->routes->route_name ->returns route url
     *                        $this->routes->route_name(params) ->returns route
     *                        url with params if route accepts any or with query
     *                        params (the ones that goes after ? mark)
     * $this->route           instance of the route that matched the request.
     * $this->params          array of params available to the action. This array
     *                        is a merge of GET and POST params and the extra params
     *                        present in the route definition.
     * $this->session         instance of session object. php's $_SESSION is not
     *                        supported. You can set/get properties to session in the
     *                        following way: $this->session->user_id = 1;
     * $this->output          instance of output object. This object controls the way
     *                        information is returned to the client and alse can be
     *                        used to pass variables to layout.
     *
     * $this->output->title   title of the application being printed on HTML title tag
     * $this->output->layout  name of layout to use when returning to the client
     *                        (default: main)
     * $this->output->format  format in which data will be presented to the client.
     *                        This value is set by the framework according to the
     *                        request unless it's explicitly set in a route definition
     *                        or by changing it on an action. Possible values are:
     *                        html (by default), clean (view without layout), json, xml
     * $this->output->charset charset to be sent in header Content-Type (default utf-8)
     *
     * Methods available in the controller
     *
     * $this->error(string $message)
     *      set /clear (when $message is null) an error message.
     *      This value will be available on the layout as output.error
     *
     * $this->notice(string $message)
     *      set /clear (when $message is null) a message.
     *      This value will be available on the layout as output.notice
     *
     * $this->output(array $options)
     *      set an output option (title, layout, format, charset). You can pass
     *      several options as an associative array.
     *
     * $this->param(string $name, mixed $default = null)
     * $this->p(string $name, mixed $default = null)
     *      get a param from $this->params. If the parameter does not exists
     *      default value will be returned. If no default value is passed, NULL
     *      will be returned.
     *
     * $this->forwardTo(string $action, array $params = array())
     *      forward execution loop to another action of this controller or other.
     *      $action can be an action name, ex: 'createUser' which means call action
     *      createUser of this controller, or 'invoices.new' which means call action
     *      new of invoices.
     *      aditionally you can add extra parameters that will be available on
     *      $this->params.
     *
     * $this->redirectTo(string $url)
     *      redirects client browser to the url passed.
     *
     * $this->render(string $template, array $options = array())
     *      renders a view using the $template filename instead of the default
     *      template name (the one that is calculated according to the roules
     *      explained above). $options is an optional array that can contain
     *      parameters to the output object (title, charset, format, layout) and
     *      a special parameter partial. If $options['partial'] == true then
     *      the view is rendered and text generated is returned by the render
     *      function. Otherwise, control flow will jump to the application object
     *      and the will be dispatched to the client.
     *
     * Events available in the controller
     * You can attach several functions that will be called 'before', 'after' or
     * 'on' an event. Each class can define it's own events. You can attach an
     * event to a class using event handlers on the initialize static function.
     *
     * self::before('action', 'nameOfControllerMethod');
     * This event is called before every action being called on a controller. You can
     * attach several actions to an event. They will be called in the order they were
     * attached. Inherited classes inherit parents attached events. If you want to clear
     * the list you must call self::before('action', false); Action event send to the
     * handler the action name being called. See for an example below.
     *
     * self::after('action', 'nameOfControllerMethod');
     * This event is called after every action being called on a controller.
     *
     * See also:
     *  models/sample.php, config.yml, views/home/index.html
     *
     */
    class HomeController extends Controller {
        /**
         *  This function is called on every class when it is loaded. It is intended
         *  to configure the class events and if you need to load settings.
         *
         *  Class events are available on every object descendant of YPFObject.
         */
        /*
        public static function initialize() {
            self::before('action', 'checkUser');
        }
        */

        public function index() {

        }

        /*
        protected function checkUser($actionCalled) {
            if ($actionCalled != 'index')
                $this->redirectTo ($this->routes->login);
        }
        */
    }
?>
