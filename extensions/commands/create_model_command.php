<?php
    class CreateModelCommand extends YPFCommand {
        public function getDescription() {
            return 'creates a model skeleton and optionally db schema information';
        }

        public function help($parameters) {
            echo "ypf create.model model-name [table-name [field-definition, ...]]\n".
                 "Creates a model for the application using the passed parameters\n".
                 "   model-name:       name of the model class\n".
                 "   table-name:       (optional) name of the table on the database. If not\n".
                 "                     specified, table-name will be model-name underscored\n".
                 "   field-definition: (optional) one or more parámeters with the following syntax\n".
                 "                     field-name:field-type[:field-length] where field-type\n".
                 "                     can be: key, integer, float, string[:length], text, date,\n".
                 "                     time, datetime, boolean, reference\n";

            return YPFCommand::RESULT_OK;
        }

        public function run($parameters) {
            if (empty($parameters)) {
                $this->help($parameters);
                $this->exitNow(YPFCommand::RESULT_INVALID_PARAMETERS);
            }

            $this->requirePackage('application', 'plugin');

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $parameters[0]))
                $this->exitNow (YPFCommand::RESULT_INVALID_PARAMETERS, sprintf('%s is not a valid model name', $parameters[0]));

            $modelClassName = YPFramework::camelize($parameters[0]);
            $modelFileName = YPFramework::getFileName(getcwd(), 'models', YPFramework::underscore(($parameters[0])).'.php');

            if (file_exists($modelFileName))
                $this->exitNow (YPFCommand::RESULT_FILES_ERROR, sprintf('model %s already exists', $modelClassName));

            if (count($parameters) > 1) {
                $tableName = $parameters[1];
                if (!preg_match('/^[a-z_][a-z0-9_]*$/', $tableName))
                    $this->exitNow (YPFCommand::RESULT_INVALID_PARAMETERS, sprintf('%s is not a valid table name', $tableName));

                if (count($parameters) > 2) {
                    $tableSchema = array(
                        'name' => $tableName,
                        'columns' => array()
                    );

                    for($i = 2; $i < count($parameters); $i++) {
                        if (!preg_match('/^([a-z_][a-zA-Z0-9_]*):(key|integer|float|(string)(:([0-9]+))?|text|date|time|datetime|boolean|reference)$/', $parameters[$i], $match))
                            $this->exitNow (YPFCommand::RESULT_INVALID_PARAMETERS, sprintf('%s is not a valid field definition', $parameters[$i]));

                        if ($match[2] == 'reference')
                            $match[1] .= '_id';
                        
                        $column = array('name' => $match[1], 'type' => (isset($match[3])? $match[3]: $match[2]));
                        if (isset($match[5]))
                            $column['length'] = $match[5];

                        $tableSchema['columns'][] = $column;
                    }

                } else {
                    $tableSchema = null;
                }
            } else {
                $tableName = YPFramework::underscore($parameters[0]);
                $tableSchema = null;
            }

            if ($tableSchema !== null) {
                $tableDefinition = "protected static \$_schema = array(\n";
                $tableDefinition .= sprintf("            'name'      => '%s',\n", addslashes($tableSchema['name']));
                $tableDefinition .= "            'columns'   => array(\n";
                $columns = array();

                foreach($tableSchema['columns'] as $column) {
                    $columnDefinition = "                array(";
                    $fields = array();
                    foreach ($column as $key => $val)
                        $fields[] = sprintf("'%s' => '%s'", addslashes($key), addslashes($val));
                    $columnDefinition .= implode(', ', $fields) .")";

                    $columns[] = $columnDefinition;
                }

                $tableDefinition .= implode(",\n", $columns)."\n            )\n        );";
            } else {
                $tableDefinition = sprintf("protected static \$_tableName = '%s';", addslashes($tableName));
            }

            $skeletonFileName = realpath(YPFramework::getFileName(dirname(__FILE__), 'skeletons/model.skeleton'));
            $data = array(
                'model_name' => $modelClassName,
                'table_definition' => $tableDefinition
            );
            $this->getProcessedTemplate($skeletonFileName, $data, $modelFileName);

            printf("model %s created successfully\n", $modelClassName);
            return YPFCommand::RESULT_OK;
        }
    }
?>
