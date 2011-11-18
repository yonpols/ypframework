<?php
    class MySQLDataBase extends YPFDataBase
	{
        protected $transaction = 0;

        public function __destruct()
        {
            mysql_close($this->db);
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
                mysql_close($this->db);
                $this->db = null;
            }

			if (($this->db = mysql_connect($this->host, $this->user, $this->pass, true)) === false)
			{
				Logger::framework('ERROR:DB', $this->getError());
				return false;
			}

			if (!mysql_select_db($this->dbname, $this->db))
			{
				Logger::framework('ERROR:DB', $this->getError());
				return false;
			}

            mysql_query('SET autocommit=0;', $this->db);
            mysql_set_charset('utf8', $this->db);

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

			$res = mysql_query($sql, $this->db);

			if (!$res)
			{
                Logger::framework('ERROR:SQL', $this->getError($sql));
				return false;
			} else
                Logger::framework('SQL', sprintf('%s: %s', $this->dbname, $sql));

            $id = mysql_insert_id($this->db);
			if ((strtoupper(substr($sql, 0, 6)) == "INSERT") && ($id > 0))
                return $id;
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

            Logger::framework('SQL', sprintf('%s: %s', $this->dbname, $sql));
			if (($res = mysql_query($sql, $this->db)) === false)
			{
                Logger::framework('ERROR:SQL', $this->getError($sql));
				return false;
			}

			$q = new MySQLQuery($this, $sql, $res);
			return $q;
		}

		/*
		 *	Ejecuta una Consulta SQL. Destinado a SELECT
		 *	false 	=>	si hay error
		 *	valor 	=> 	devuelve el valor solicitado de la consulta
		 */
		public function value($sql, $getRow = false)
		{
			if (!$res = mysql_query($sql, $this->db))
			{
                Logger::framework('ERROR:SQL', $this->getError($sql));
				return false;
			} else
                Logger::framework('SQL', sprintf('%s: %s', $this->dbname, $sql));

			$row = mysql_fetch_assoc($res);

			if (!$row)
				return false;

            if ($getRow)
                return $row;
            else
                return array_shift($row);
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
            $query = $this->query(sprintf('SHOW COLUMNS FROM %s', $table));

            if ($query) {
                $fields = array();
                while ($row = $query->getNext())
                {
                    $obj = new YPFObject();

                    $obj->Name = $row['Field'];
                    $obj->Type = $row['Type'];

                    if (($pos = strpos($obj->Type, '(')))
                        $obj->Type = substr ($obj->Type, 0, $pos);

                    $obj->Key = ($row['Key'] == 'PRI');
                    $obj->Null = ($row['Null'] == 'YES');
                    $obj->Default = $row['Default'];

                    $fields[$row['Field']] = $obj;
                }
                return $fields;
            }

            return false;
        }

        public function sqlEscaped($str)
        {
            return mysql_real_escape_string($str, $this->db);
        }

        public function inTransaction() {
            return ($this->transaction > 0);
        }

        public function getError($sql=null)
        {
            if ($sql === null) {
                if (!$this->db)
                    return sprintf('%s: (%d - %s)', $this->dbname, mysql_errno(), mysql_error());
                else
                    return sprintf('%s: (%d - %s)', $this->dbname, mysql_errno($this->db), mysql_error($this->db));
            }
            else
                return sprintf('%s: (%d - %s) "%s"', $this->dbname, mysql_errno($this->db), mysql_error($this->db), $sql);
        }

        /*
         *  Recibe un modelo que debe estar en la base de datos.
         * *version:             versión de la tabla
         *  name:                nombre de la tabla
         *  columns:
         *      name:            nombre de la columna
         *      type:            (key, integer, float, string, text, date, time, datetime, boolean, reference)
         *     *length:          para los strings. 255 por default
         *      default:         valor por defecto
         *
         * *indices:
         *      name:            nombre del indice
         *     *unique:          false por default
         *      columns:         name,name,name,name...
         * *pre_install_sql:     cadena sql a ejecutar antes de la instalación
         * *post_install_sql:    cadena sql a ejecutar despues de la instalación
         * *pre_uninstall_sql:   cadena sql a ejecutar antes de la desinstalación
         * *post_uninstall_sql:  cadena sql a ejecutar despues de la desinstalación
         */
        public function install($model, $from_version = null, $only_sql = false)
        {
            if (!$this->command('CREATE TABLE IF NOT EXISTS ypf_schema_history (version varchar(32), table_name varchar(64), schema_desc text)'))
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

            //La tabla no existe
            if ($ultima_version === false)
            {
                $defs = array();
                foreach ($model['columns'] as $field)
                    $defs[] = $this->getFieldSqlDefinition($field);

                if (isset($model['indices']))
                    foreach ($model['indices'] as $index)
                        $defs[] = $this->getIndexSqlDefinition ($index);

                $sql = sprintf('CREATE TABLE `%s` (%s) ENGINE=InnoDB DEFAULT CHARSET=utf8;', $model['name'], implode(', ', $defs));
            } else
            {
                $defs = array();
                $previous_model = unserialize($ultima_version['schema_desc']);

                //Modificar o agregar columnas
                foreach ($model['columns'] as $field)
                {
                    $found = false;

                    foreach($previous_model['columns'] as $i=>$column)
                        if ($column['name'] == $field['name'])
                        {
                            if (!array_compare($column, $field))
                                $defs[] = sprintf('CHANGE COLUMN `%s` %s', $field['name'], $this->getFieldSqlDefinition($field));
                            unset($previous_model['columns'][$i]);

                            $found = true;
                            break;
                        }

                    if (!$found)
                        $defs[] = sprintf('ADD COLUMN %s', $this->getFieldSqlDefinition($field));
                }

                //Eliminar columnas
                foreach ($previous_model['columns'] as $column)
                    $defs[] = sprintf('DROP COLUMN `%s`', $column['name']);

                //Eliminar indices
                if (!isset($previous_model['indices']))
                    $previous_model['indices'] = array();

                //Crear indices
                if (!isset($model['indices']))
                    $model['indices'] = array();

                foreach ($model['indices'] as $index)
                {
                    $found = false;
                    foreach ($previous_model['indices'] as $i=>$pindex)
                        if ($pindex['name'] == $index['name'])
                        {
                            $found = true;
                            unset($previous_model['indices'][$i]);
                            if (!array_compare($index, $pindex))
                            {
                                $defs[] = sprintf('DROP INDEX `%s`', $index['name']);
                                $defs[] = sprintf('ADD %s', $this->getIndexSqlDefinition($index));
                            }
                        }

                    if (!$found)
                        $defs[] = sprintf('ADD %s', $this->getIndexSqlDefinition($index));
                }

                foreach ($previous_model['indices'] as $index)
                    $defs[] = sprintf('DROP INDEX `%s`', $index['name']);

                if (empty($defs))
                    return false;

                $sql = sprintf('ALTER TABLE `%s` %s', $model['name'], implode(', ', $defs));
            }

            if ($this->command($sql)) {

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

            return false;
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
                'key'       => 'INT(4) UNSIGNED AUTO_INCREMENT KEY',
                'integer'   => 'INT',
                'reference' => 'INT(4)',
                'float'     => 'DOUBLE',
                'text'      => 'TEXT',
                'date'      => 'DATE',
                'time'      => 'TIME',
                'datetime'  => 'DATETIME',
                'boolean'   => 'TINYINT(1)',
                'string'    => 'VARCHAR'
            );

            $sql = sprintf('`%s` %s', $field['name'], $types[$field['type']]);
            if (isset($field['length']))
                $sql .= sprintf('(%d)', $field['length']);
            elseif ($field['type'] == 'string')
                $sql .= '(255)';

            return $sql;
        }

        private function getIndexSqlDefinition($index)
        {
            $sql = ((isset($index['type']) && $index['type'])? 'UNIQUE ': '').'INDEX ';

            $sql .= sprintf('`%s` ', $index['name']);

            $sql .= sprintf('(%s)', implode(', ', array_map(function($e) { return "`$e`"; }, $index['columns'])));

            return $sql;
        }
	}

	class MySQLQuery extends Query implements Iterator
	{
        private $_iteratorKey = null;

        public function __construct(YPFDataBase $database, $sql, $res)
        {
            parent::__construct($database, $sql, $res);
            $this->rows = mysql_num_rows($this->resource);
			$this->cols = mysql_num_fields($this->resource);
        }

        public function __destruct()
        {
            mysql_free_result($this->resource);
        }

        protected function loadMetaData()
        {
			$this->fieldsInfo = array();

			for ($i = 0; $i < $this->cols; $i++)
            {
                $obj = new YPFObject();
                $info = mysql_fetch_field($this->resource, $i);

                $obj->Name = $info->name;
                $obj->Type = $info->type;
                $obj->Key = ($info->primary_key == 1);
                $obj->Null = !$obj->Key;
                $obj->Default = null;

				$this->fieldsInfo[$obj->Name] = $obj;
            }

		}

		public function getNext()
		{
			$this->row = mysql_fetch_assoc($this->resource);
            $this->eof = ($this->row === false);
			return $this->row;
		}

		public function getNextObject()
		{
			$this->row = mysql_fetch_object($this->resource);
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
            if ($this->_iteratorKey !== null)
            {
                $this->eof = true;
                return;
            }
            $this->next();
        }

        public function valid()
        {
            return !$this->eof;
        }
	}
?>
