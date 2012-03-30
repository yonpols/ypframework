<?php
    class Sample extends Model {
        /*
         * Esquema de la base de datos. Si se especifica, YPF creará las tablas
         * o las modificará según este esquema. Es un arreglo con los siguientes
         * parámetros (los que tienen un * son opcionales)
         *
           *version:             versión de la tabla
            name:                nombre de la tabla
            columns:
                name:            nombre de la columna
                type:            (key, integer, float, string, text, date, time, datetime, boolean, reference)
               *length:          para los strings. 255 por default
                default:         valor por defecto

           *indices:
                name:            nombre del indice
               *unique:          false por default
                columns:         name,name,name,name...
           *pre_install_sql:     cadena sql a ejecutar antes de la instalación
           *post_install_sql:    cadena sql a ejecutar despues de la instalación
           *pre_uninstall_sql:   cadena sql a ejecutar antes de la desinstalación
           *post_uninstall_sql:  cadena sql a ejecutar despues de la desinstalación
         *
         */
        public static $_schema = array(
            'name' => 'samples',
            'columns' => array(
                array('name' => 'id',           'type' => 'key'),
                array('name' => 'nombre',       'type' => 'string'),
                array('name' => 'parent_id',    'type' => 'reference')
            )
        );


        /*
         * Los siguientes son datos necesarios para que funcione el modelo. Si
         * se especifica schema, estos serán inferidos a partir de los datos
         * ingresados. Todos los parámetros tienen valores por defecto.
         * Model admite tablas con claves compuestas.
         */
        public static $_tableName = 'samples';   //DEFECTO: nombre de la clase underscored.
        public static $_keyFields = array('id'); //DEFECTO: Se buscará en la estructura de la
                                                 //tabla en la BD. Si no se define una clave,
                                                 //se utilizará id.

        /**
         * Name of the database connection configured in current environment in
         * config.yml file. By default this value is 'main'
         * @var string 'main' if none specified.
         */
        public static $_databaseName = 'db2';

        /**
         * Table alias to use in sql queries.
         * @var string null if none specified (no alias for SQL queries)
         */
        public static $_tableAlias = 'samples1'; //DEFECTO: null

        /**
         * Charset of data stored in the table. YPFramework works with UTF-8 by
         * default. So if you specify a charset different from UTF-8, data loaded
         * from de DB will be converted to UTF-8 and data loaded into DB will
         * be converted to $_tableCharset.
         *
         * @var string 'utf-8' if none specified.
         */
        public static $_tableCharset = 'iso-8859-1';

        /**
         * Models define object properties automatically according to fields in the
         * database. If you need properties that will not be stored in the database
         * you need to define transient fields. You can also define variables in the
         * model class.
         *
         * $_transientFields accepts an associative array whose keys are fields names
         * and values are initial field values.
         *
         * @var array array() if not defined.
         */
        public static $_transientFields = array(
            'name' => 'default value'
        );


        /**
         * Array of fields to select. If this parameter is not defined all fields will
         * be selected.
         *
         * @var array array('*') if none defined.
         */
        public static $_sqlFields = array('id', 'name');

        /**
         * Array of join clauses to add to SELECT.
         *
         * @var array array() if none defined.
         */
        public static $_sqlJoins = array('JOIN users ON users.id = samples1.user_id');

        /**
         * SQL conditions to specify in WHERE clause. This is an array
         * of strings that will be concatenated with AND.
         * @var array array() if none defined.
         */
        public static $_sqlConditions = array('samples1.active = 1', 'samples1.user_id IS NOT NULL');

        /**
         * GROUP BY fields. This is an array of strings that contain a field
         * name each one.
         *
         * @var array array() if none defined.
         */
        public static $_sqlGrouping = array('samples1.date');

        /**
         * SQL conditions to specify in HAVING clause. This is an array
         * of strings that will be concatenated with AND.
         * @var array array() if none defined.
         */
        public static $_sqlGroupingConditions = array('COUNT(*) > 1');

        /**
         * ORDER BY fields. This is an array of strings that contain a field
         * name each one and optionally a direction modifier (ASC or DESC)
         *
         * @var array array() if none defined.
         */
        public static $_sqlOrdering = array('date DESC');

        /**
         * LIMIT clause. This is an array of one or two integers that will be appended
         * at the end of the SELECT sql query specifying offset and number of rows to
         * load.
         *
         * @var array array() if none defined.
         */
        public static $_sqlLimit = array(20, 10);

        /**
         * List of named queries that can be applied on every query and subquery
         * made to the model. This is an associative array of arrays. Each key will
         * be the name of the query. Each array can specify sqlFields, sqlJoins,
         * sqlConditions, sqlGruping, sqlGroupingConditions, sqlOrdering, sqlLimit
         * to narrow the query.
         *
         * In the example below you can see two subqueries. They can be used as follows:
         *
         *  Samples::all()->active          -> list of all active samples
         *  Samples::all()->active->top5    -> list of newest 5 active samples
         *  Samples::where('user_id = ?', 5)->active ->list of all samples from
         *                                     user#5 which are active.
         *
         * @var array array() if none defined.
         */
        public static $_queries = array(
            'active' => array(
                'sqlConditions' => array('active = 1')
            ),
            'top5' => array(
                'sqlOrdering' => array('creation_date DESC'),
                'sqlLimit' => array(0, 5)
            )
        );

        /**
         * List of relationships with this model. You can define four types
         * of relation: belongs_to (n - 1), has_one (1 - 1), has_many (1 - n),
         * has_many through (n - m)
         *
         * Relationship definition is an associative array of arrays with relation
         * parameters.
         *
         * @var array() array() if none specified.
         */

        public static $_relations = array(
            /**
             * Belongs To relation.
             *  'belongs_to' => 'ModelName'
             *
             * You must specify which foreign keys are present in this model
             * that relate with primary keys of the related model.
             *
             * 
             *
             */
            'sound_file' => array(
                'belongs_to' => 'SoundFile',
                'keys' => array('sound_file_id' => 'id'),
                'keys' => array('sound_file_id'),
            )
        )

    }
?>
