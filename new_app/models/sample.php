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
        public static $_keyFields = array('id'); //DEFECTO: array('id')

        //Alias a utilizar en la consulta SQL
        public static $_aliasName = array('id'); //DEFECTO: null

        /* YPFRamework trabaja en UTF-8. Si se especifica un charset distinto
         * Model convertirá los textos al cargarlos desde la base de datos y
         * al guardarlos a la misma.*/
        public static $_tableCharset = 'iso-8859-1'; //DEFECTO: utf-8

        /* Transient fields son campos que no se guardan en la base de datos pero
         * están disponibles para su uso como campos comunes. Se inicializan con
         * el valor indicado*/
        public static $_transientFields = array(
            'name' => 'default value'
        ); //DEFECTO: array()

        /* Condiciones SQL: Las siguientes condiciones sirven para restringir el
         * SELECT que se realiza a la BD a la hora de buscar registros.*/

        //Campos a traer de la base de datos
        public static $_sqlFields = array('id', 'name'); //DEFAULT: array();
        //Joins a hacer 
        public static $_sqlJoins = array('id', 'name');  //DEFAULT: array();
        //Campos a traer de la base de datos
        public static $_sqlFields = array('id', 'name'); //DEFAULT: array();
        //Campos a traer de la base de datos
        public static $_sqlFields = array('id', 'name'); //DEFAULT: array();


    }
?>
