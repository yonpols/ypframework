<?php
    $form_ids = 0;

    function form($url = '', $method = 'POST', $options = array())
    {
        global $form_ids;

        $html = sprintf('<form id="form%d" action="%s" method="%s"',
            ++$form_ids,
            $url,
            htmlentities($method, ENT_QUOTES, 'utf-8'));

        foreach ($options as $key=>$val)
            $html .= sprintf(' %s="%s"', $key, $val);

        return $html.'>';
    }

    function form_end()
    {
        return '</form>';
    }

    function form_textfield($name, $object = '', $attrs = array())
    {
        return _form_inputfield('text', $name, $object, $attrs);
    }

    function form_passwordfield($name, $object = '', $attrs = array())
    {
        return _form_inputfield('password', $name, $object, $attrs);
    }

    function form_submit($name, $object = '', $attrs = array())
    {
        return _form_inputfield('submit', $name, $object, $attrs);
    }

    function form_button($name, $text, $jsFunc, $attrs = array())
    {
        $id = _form_fieldname($name);

        $html = sprintf('<button id="%s" name="%s" onclick="%s"',
            $id['id'], $id['name'], htmlentities($jsFunc, ENT_QUOTES, 'utf-8'));

        foreach ($attrs as $k=>$v)
            $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));

        $html .= '>'.htmlentities($text, ENT_QUOTES, 'utf-8').'</button>';

        return $html;
    }

    function form_hiddenfield($name, $object = '', $attrs = array())
    {
        return _form_inputfield('hidden', $name, $object, $attrs, null, false);
    }

    function form_filefield($name, $object = '', $attrs = array())
    {
        return _form_inputfield('file', $name, $object, $attrs);
    }

    function form_radiofield($name, $value, $object = '', $attrs = array())
    {
        if (is_object($object) && isset ($object->{$name}) && ($object->{$name} == $value))
            $attrs['checked'] = 'checked';

        return _form_inputfield('radio', $name, $object, $attrs, $value);
    }

    function form_checkfield($name, $value, $object = '', $attrs = array())
    {
        if (is_object($object) && isset ($object->{$name}) && ($object->{$name} == $value))
            $attrs['checked'] = 'checked';

        return _form_inputfield('checkbox', $name, $object, $attrs, $value);
    }

    function form_select($name, $values, $object = '', $allowBlank=false, $attrs = array())
    {
        $value = null;
        $id = _form_fieldname($name, $object, $value);

        $html = sprintf('<select id="%s" name="%s"', $id['id'], $id['name']);

        if (is_object($object) && ($object instanceof Model) && $object->getError($id['field']))
            $attrs['class'] = (isset($attrs['class'])? $attrs['class'].' ': '').'error';

        if (is_array($attrs))
        foreach ($attrs as $k=>$v)
            $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));

        $html .= '>';

        if ($allowBlank)
        {
            $html .= '<option value=""';
            if ($value === null) $html .= ' selected="selected"';
            $html .= '></option>';
        }

        foreach ($values as $k=>$v)
        {
            if (is_object($v) && ($v instanceof Model))
            {
                $key = $v->getSerializedKey();

                $html .= sprintf('<option value="%s"', htmlentities($key, ENT_QUOTES, 'utf-8'));
                if ($key == $value) $html .= ' selected="selected"';
            } else {
                $html .= sprintf('<option value="%s"', htmlentities($k, ENT_QUOTES, 'utf-8'));
                if ((string)$k == $value) $html .= ' selected="selected"';
            }
            $html .= sprintf('>%s</option>', htmlentities($v, ENT_QUOTES, 'utf-8'));
        }

        $html .= '</select>';

        if (is_object($object) && ($object instanceof Model) && $object->getError($id['field']))
            $html .= sprintf('<div class="error"><ul><li>%s</li></ul></div>',
                implode('</li><li>', $object->getError($id['field'])));

        return $html;
    }

    function form_multipleselect($name, $values, $object = '', $attrs = array())
    {
        $value = null;
        $id = _form_fieldname($name, $object, $value);

        $html = sprintf('<select id="%s" name="%s" multiple="multiple"', $id['id'], $id['name']);

        if (is_object($object) && ($object instanceof Model) && $object->getError($id['field']))
            $attrs['class'] = (isset($attrs['class'])? $attrs['class'].' ': '').'error';

        if (is_array($attrs))
        foreach ($attrs as $k=>$v)
            $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));

        $html .= '>';

        foreach ($values as $k=>$v)
        {
            if (is_object($v) && ($v instanceof Model))
            {
                $key = $v->getSerializedKey();

                $html .= sprintf('<option value="%s"', htmlentities($key, ENT_QUOTES, 'utf-8'));

                if ($value instanceof YPFModelBaseRelation && ($value->has($key)))
                    $html .= ' selected="selected"';
                elseif (is_array($value) && (array_search($key, $value) !== false))
                    $html .= ' selected="selected"';
            } else {
                $html .= sprintf('<option value="%s"', htmlentities($k, ENT_QUOTES, 'utf-8'));

                if ($value instanceof YPFModelBaseRelation && ($value->has($k)))
                    $html .= ' selected="selected"';
                elseif (is_array($value) && (array_search($k, $value) !== false))
                    $html .= ' selected="selected"';
            }
            $html .= sprintf('>%s</option>', htmlentities($v, ENT_QUOTES, 'utf-8'));
        }

        $html .= '</select>';

        if (is_object($object) && ($object instanceof Model) && $object->getError($id['field']))
            $html .= sprintf('<div class="error"><ul><li>%s</li></ul></div>',
                implode('</li><li>', $object->getError($id['field'])));

        return $html;
    }

    function form_textarea($name, $object = '', $attrs = array())
    {
        $value = null;
        $id = _form_fieldname($name, $object, $value);

        $html = sprintf('<textarea id="%s" name="%s"',
            $id['id'], $id['name']);

        if (is_object($object) && ($object instanceof Model) && $object->getError($id['field']))
            $attrs['class'] = (isset($attrs['class'])? $attrs['class'].' ': '').'error';

        foreach ($attrs as $k=>$v)
            $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));

        $html .= '>'.htmlentities($value, ENT_QUOTES, 'utf-8');

        $html .= '</textarea>';

        if (is_object($object) && ($object instanceof Model) && $object->getError($id['field']))
            $html .= sprintf('<div class="error"><ul><li>%s</li></ul></div>',
                implode('</li><li>', $object->getError($id['field'])));

        return $html;
    }

    function form_relationfield($name, $object, $condition=null, $allowBlank=false)
    {
        $relation = $object->getRelationObject($name);
        if (!$relation instanceof YPFBelongsToRelation)
            return;

        $modelName = $relation->getRelatedModelName();

        if ($condition !== null)
            $values = $modelName::where($condition)->toArray();
        else
            $values = $modelName::all()->toArray();

        return form_select($name.'_id', $values, $object, $allowBlank);
    }

    function form_datefield($name, $object)
    {
        return form_textfield($name, $object, array('class'=>'form_field date'));
    }

    function _form_inputfield($type, $name, $object = '', $attrs = array(), $value = null, $showError = true)
    {
        $id = _form_fieldname($name, $object, $value, ($type=='text'));

        if (is_object($object) && ($object instanceof Model) && $object->getError($id['field']) && $showError)
        {
            $errors = sprintf('<div class="error"><ul><li>%s</li></ul></div>',
                implode('</li><li>', $object->getError($id['field'])));
            $attrs['class'] = (isset($attrs['class'])? $attrs['class']: '').' error';
        } else
            $errors = '';

        $html = sprintf('<input id="%s" type="%s" name="%s" value="%s"',
            $id['id'],
            $type,
            $id['name'],
            htmlentities($value, ENT_QUOTES, 'utf-8'));

        if (is_array($attrs))
            foreach ($attrs as $k=>$v)
                $html .= sprintf(' %s="%s"', $k, htmlentities($v, ENT_QUOTES, 'utf-8'));
        $html .= '/>';

        return $html.$errors;
    }

    function _form_fieldname($name, $object = null, &$value = null, $get_string=false)
    {
        if (preg_match('/^[a-zA-Z0-9_]+(\\.[a-zA-Z0-9_]+)+(\\.\\*\\.[a-zA-Z0-9_]+)?$/', $name)) {
            if (is_object($object)) {
                if ($object instanceof Model) {
                    $key = $object->getSerializedKey();
                    if ($key === null)
                        $key = $object->getObjectId();
                    $name = str_replace('*', $key, $name);
                }

                $name = explode('.', $name);
                $field = $name[count($name)-1];

                if (isset ($object->{$field}))
                {
                    if ($value === null) $value = $object->{$field};
                    if ($value instanceof Model)
                        if ($get_string)
                            $value = $value->__toString();
                        else
                            $value = $value->getSerializedKey();
                }
            }

            $id = implode('_', $name);
            $name = $name[0].'['.implode('][', array_slice($name, 1)).']';
        } else {
            if (is_object($object))
            {
                $field = $name;
                if (isset ($object->{$name}))
                {
                    if ($value === null) $value = $object->{$name};
                    if ($value instanceof Model)
                        if ($get_string)
                            $value = $value->__toString();
                        else
                            $value = $value->getSerializedKey();
                }

                $id = sprintf('%s_%s', get_class($object), $name);
                $name = sprintf('%s[%s]', get_class($object), $name);
            } else
            {
                if ($value === null) $value = $object;
                $id = $name;
                $field = $name;
            }
        }

        return array('id' => YPFramework::normalize($id), 'name' => $name, 'field' => $field);
    }

    function form_process_uploaded_file($model, $field, $path, $process_function = 'move_uploaded_file')
    {
        $modelName = get_class($model);

        if (!isset($_FILES[$modelName]))
            return false;

        $data = array(
            'type' => $_FILES[$modelName]['type'][$field],
            'tmp_name' => $_FILES[$modelName]['tmp_name'][$field],
            'name' => $_FILES[$modelName]['name'][$field],
            'error' => $_FILES[$modelName]['error'][$field],
            'size' => $_FILES[$modelName]['size'][$field],
        );

        if (is_uploaded_file($data['tmp_name']))
        {
            $search = explode(",","ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u");
            $replace = explode(",","c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
            $file_name = str_replace($search, $replace, $data['name']);
            $extension = substr($file_name, strrpos($file_name, '.'));

            $file_name = substr($file_name, 0, -strlen($extension));
            $file_name = preg_replace('/[^\w\-~_\.]+/u', '-', $file_name);

            $i = '';

            while (file_exists(YPFramework::getFileName($path, $file_name.$i.$extension)))
                $i += 1;

            $dest_file = YPFramework::getFileName($path, $file_name.$i.$extension);
            $dest_path = dirname($dest_file);

            if (!is_dir($dest_path))
                @mkdir ($dest_path, 0777, true);

            if (!@$process_function($data['tmp_name'], $dest_file))
                return false;

            if (file_exists($path.$model->{$field}))
                @unlink($path.$model->{$field});

            $model->{$field} = $file_name.$i.$extension;

            if (isset($model->{$field.'_tamanio'}))
                $model->{$field.'_tamanio'} = filesize($dest_file);

            return true;
        }

        return false;
    }
?>
