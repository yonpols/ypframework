<?php

    class SQLite2DataBase extends YPFDataBase
	{
        public $exists;
        protected $transaction = 0;

        public function __construct($configuration, $connect = true)
        {
            parent::__construct($configuration, $connect);
        }

		/*
		 * 	conectar a la base de datos.
		 *	true 	=> conecta
		 *	false	=> no conecta
		 */
		public function connect($database = null)
		{
            if ($database !== null)
                $this->dbname = $database;
            if (is_resource($this->db))
            {
                sqlite_close($this->db);
                $this->db = null;
            }

            $this->exists = file_exists($this->dbname);

			if (($this->db = sqlite_open($this->dbname)) === false)
			{
				Logger::framework('ERROR:DB', 'Coudn\'t open/create database');
				return false;
			}

			$this->connected = true;
			return true;
		}

		/*
		 *	Ejecuta una consulta SQL. Orientado a consultas DELETE, UPDATE  e INSERT
		 *	false	=> error
		 *	id		=> si es INSERT devuelve el valor de ID
		 *	true 	=> si no se generó id pero se realizo con exito
		 */
		public function command($sql)
		{
			$sql = trim($sql);

			$res = @sqlite_exec($this->db, $sql, $error);

			if (!$res)
			{
                Logger::framework('ERROR:DB', sprintf("%s; '%s'", $error, $sql));
				return false;
			} else
                Logger::framework('SQL:DB', $sql);

			if (strtoupper(substr($sql, 0, 6)) == "INSERT")
				return sqlite_last_insert_rowid($this->db);
			else
				return true;
		}

		/*
		 *	Ejecuta una consulta SQL. Destinado a SELECT
		 *	Devuelve un Objeto YPFWQuery que representa la consulta.
		 *	NULL 	=> si se produjo un error
		 */
		public function query($sql, $limit = NULL)
		{
            if ($limit !== NULL)
            {
                if (is_array($limit))
                    $sql .= sprintf(" LIMIT %d, %d", $limit[0], $limit[1]);
                else
                    $sql .= sprintf(" LIMIT %d", $limit);
            }

			if (!$res = @sqlite_query($this->db, $sql, 1, $error))
			{
                if ($error)
                    Logger::framework('ERROR:DB', sprintf("%s; '%s'", $error, $sql));
                else
    				Logger::framework('ERROR:DB', sprintf("%s; '%s'", sqlite_error_string(sqlite_last_error ($this->db)), $sql));
				return false;
			} else
                Logger::framework('SQL:DB', $sql);

			$q = new SQLite2Query($this, $sql, $res);
			return $q;
		}

		/*
		 *	Ejecuta una Consulta SQL. Destinado a SELECT
		 *	false 	=>	si hay error
		 *	valor 	=> 	devuelve el valor solicitado de la consulta
		 */
		public function value($sql, $getRow = false)
		{
			if (!$res = @sqlite_query($this->db, $sql, 2, $error))
			{
                if ($error)
                    Logger::framework('ERROR:DB', sprintf("%s; '%s'", $error, $sql));
                else
    				Logger::framework('ERROR:DB', sprintf("%s; '%s'", sqlite_error_string(sqlite_last_error ($this->db)), $sql));
				return false;
			} else
                Logger::framework('SQL:DB', $sql);


            if (sqlite_num_rows($res) > 0)
            {
                if ($getRow)
                    return sqlite_fetch_array($res, 1);
                else
                    return sqlite_fetch_single($res);
            }
            else
                return false;
		}

        public function begin() {
            if ($this->command('BEGIN')) {
                $this->transaction++;
                return true;
            } else
                return false;
        }

        public function commit() {
            if ($this->command('COMMIT')) {
                $this->transaction--;
                return true;
            } else
                return false;
        }

        public function rollback() {
            if ($this->command('ROLLBACK')) {
                $this->transaction--;
                return true;
            } else
                return false;
        }

        public function getTableFields($table)
        {
            $fields = array();

            $query = $this->query(sprintf('PRAGMA table_info(%s)', $table));

            if (!$query)
                return false;

            while ($row = $query->getNext())
            {
                $obj = new YPFObject();

                $obj->Name = $row['name'];
                $obj->Type = strtolower($row['type']);

                if (($pos = strpos($obj->Type, '(')))
                    $obj->Type = substr ($obj->Type, 0, $pos);

                $obj->Key = ($row['pk'] == '1');
                $obj->Null = ($row['notnull'] == '1');
                $obj->Default = $row['dflt_value'];

                $fields[$obj->Name] = $obj;
            }

            return $fields;
        }

        public function sqlEscaped($str)
        {
            return sqlite_escape_string($str);
        }

        public function inTransaction() {
            return ($this->transaction > 0);
        }

        /*
         *  Recibe un modelo que debe estar en la base de datos.
         *  version:             versión de la tabla
         *  name:                nombre de la tabla
         *  columns:
         *      name:            nombre de la columna
         *      type:            (key, integer, float, string, text, date, time, datetime, boolean, reference)
         *     *length:          para los strings. 255 por default
         *      default:         valor por defecto
         *
         *  indices:
         *      name:            nombre del indice
         *     *unique:          false por default
         *      columns:         name,name,name,name...
         * *pre_install_sql:     cadena sql a ejecutar antes de la instalación
         * *post_install_sql:    cadena sql a ejecutar despues de la instalación
         * *pre_uninstall_sql:   cadena sql a ejecutar antes de la desinstalación
         * *post_uninstall_sql:  cadena sql a ejecutar despues de la desinstalación
         */
        public function install($model, $from_version = null)
        {
            $name = $this->value("SELECT name FROM sqlite_master WHERE type='table' AND name='ypf_schema_history'");
            if ($name === false)
                if (!$this->command('CREATE TABLE ypf_schema_history (version varchar(32), table_name varchar(64), schema_desc text)'))
                    return false;

            if ($from_version != null)
                $ultima_version = $this->value(sprintf("SELECT * FROM ypf_schema_history WHERE table_name = '%s' AND version = '%s'", $this->sqlEscaped($model['name'])), $this->sqlEscaped($from_version), true);
            else
                $ultima_version = $this->value(sprintf("SELECT * FROM ypf_schema_history WHERE table_name = '%s' ORDER BY version DESC LIMIT 1", $this->sqlEscaped($model['name'])), true);

            if (isset($model['version']) && $model['version']
                && ($ultima_version !== false) && ($ultima_version['version'] == $model['version']))
                    return false;

            if (isset($model['pre_install_sql']))
                if (!$this->command($model['pre_install_sql']))
                    return false;

            $version = date('YmdHis');

            $defs = array();
            foreach ($model['columns'] as $field)
                $defs[] = $this->getFieldSqlDefinition($field);
            $create_table_sql = sprintf('CREATE TABLE %s (%s)', $model['name'], implode(', ', $defs));

            if (isset($model['indices']))
            {
                $create_index_sql = array();
                foreach ($model['indices'] as $index)
                    $create_index_sql[] = $this->getIndexSqlDefinition($model['name'], $index);
            }

            if ($ultima_version !== false)
            {
                $defs = array();
                $previous_model = unserialize($ultima_version['schema_desc']);
                $result = true;

                if (array_compare($model['columns'], $previous_model['columns']))
                    unset($create_table_sql);
                else
                    $result = $this->command(sprintf('DROP TABLE %s', $model['name'])) && $result;

                if (isset($previous_model['indices']))
                    foreach($previous_model['indices'] as $index)
                        $result = $this->command(sprintf('DROP INDEX %s', $index['name'])) && $result;

                if (!$result)
                    throw new ErrorMessage('There was an error trying to drop old tables and indices');
            }

            if (isset($create_table_sql))

            if (isset($create_table_sql) && $this->command($create_table_sql))
            {
                $result = true;
                if (isset ($create_index_sql))
                    foreach($create_index_sql as $sql)
                        $result = $this->command($sql) && $result;

                if (!$result)
                    throw new ErrorMessage('There was an error trying to create indices');

                if (isset($model['post_install_sql']))
                    if (!$this->command($model['post_install_sql']))
                        return false;

                $sql = sprintf("INSERT INTO ypf_schema_history (version, table_name, schema_desc) VALUES ('%s', '%s', '%s')",
                                $this->sqlEscaped($version),
                                $this->sqlEscaped($model['name']),
                                $this->sqlEscaped(serialize($model)));
                $this->command($sql);
                Logger::framework('INFO:DB:INSTALL', sprintf('Installing: %s to version %s', $model['name'], $version));
                return true;
            }
        }

        public function uninstall($table, $version)
        {
            $ultima_version = $this->value(sprintf("SELECT version FROM ypf_schema_history WHERE table_name = '%s' ORDER BY version DESC LIMIT 1", $this->sqlEscaped($table)));
            $version_destino = unserialize($this->value(sprintf("SELECT * FROM ypf_schema_history WHERE table_name = '%s' AND version = '%s'", $this->sqlEscaped($table)), $this->sqlEscaped($version), true));

            if (isset($model['pre_uninstall_sql']))
                if (!$this->command($model['pre_uninstall_sql']))
                    return false;

            if ($this->install($version_destino, $ultima_version)) {
                if (isset($model['post_uninstall_sql']))
                    if (!$this->command($model['post_uninstall_sql']))
                        return false;
                return true;
            }

            return false;
        }

        private function getFieldSqlDefinition($field)
        {
            $types = array(
                'key'       => 'INTEGER PRIMARY KEY',
                'integer'   => 'INTEGER',
                'reference' => 'INTEGER',
                'float'     => 'DOUBLE',
                'text'      => 'TEXT',
                'date'      => 'DATE',
                'time'      => 'TIME',
                'datetime'  => 'DATETIME',
                'boolean'   => 'TINYINT',
                'string'    => 'VARCHAR'
            );

            $sql = sprintf('%s %s', $field['name'], $types[$field['type']]);
            if (isset($field['length']))
                $sql .= sprintf('(%d)', $field['length']);
            elseif ($field['type'] == 'string')
                $sql .= '(255)';

            return $sql;
        }

        private function getIndexSqlDefinition($table, $index)
        {
            $sql = 'CREATE '.((isset($index['type']) && $index['type'])? 'UNIQUE ': '').'INDEX ';

            $sql .= sprintf('%s ON %s ', $index['name'], $table);

            $sql .= sprintf('(%s)', implode(', ', $index['columns']));

            return $sql;
        }
	}

    class SQLite2Query extends Query implements Iterator
	{
        private $_iteratorKey = null;

        public function __construct(YPFDataBase $database, $sql, $res)
        {
            parent::__construct($database, $sql, $res);
            $this->rows = sqlite_num_rows($this->resource);
			$this->cols = sqlite_num_fields($this->resource);
		}

        protected function loadMetaData()
        {
        	$this->fieldsInfo = array();

			for ($i = 0; $i < $this->cols; $i++)
            {
                $obj = new YPFObject();

                $obj->Name = sqlite_field_name($this->resource, $i);
                $obj->Type = 'string';
                $obj->Key = false;
                $obj->Null = true;
                $obj->Default = null;

				$this->fieldsInfo[$obj->Name] = $obj;
            }
		}

		public function getNext()
		{
			$this->row = sqlite_fetch_array($this->resource, 1);
            $this->eof = ($this->row === false);
			return $this->row;
		}

		public function getNextObject()
		{
			$this->row = sqlite_fetch_object($this->resource);
            $this->eof = ($this->row === false);
			return $this->row;
		}

        public function current()
        {
            return $this->row;
        }

        public function key()
        {
            return $this->_iteratorKey;
        }

        public function next()
        {
            $this->getNextObject();
            if ($this->_iteratorKey === null)
                $this->_iteratorKey = 0;
            else
                $this->_iteratorKey++;
        }

        public function rewind()
        {
            if ($this->$_iteratorKey !== null)
            {
                $this->eof = true;
                return;
            }
            $this->next();
        }

        public function valid()
        {
            return !$this->eof();
        }
	}

?>
