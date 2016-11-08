<?php

require_once APP_ROOT.'/api/shop/module/response.php';

class api_intergral extends MallbaseApp{

    public function intergral($args_data){

           $con=(array(
                array(
                    'field' => 'add_time',
                    'name' => 'add_time_from',
                    'equal' => '>=',
                    'handler' => 'gmstr2time',
                ), array(
                    'field' => 'add_time',
                    'name' => 'add_time_to',
                    'equal' => '<=',
                    'handler' => 'gmstr2time_end',
                ), array(
                    'field' => 'integral_type',
                    'equal' => '=',
                    'type' => 'numeric',
                ),
            ));

            $conditions = $this->_get_query_conditions($args_data,$con);
            $integral_log_mod = &m('integral_log');
            $integral_logs = $integral_log_mod->find(array(
                'conditions' => 'user_id=' . $args_data['userID'] . $conditions,
                'order' => 'add_time desc',
                'count' => true
            ));
            
            return($integral_logs);


            Response::json(200, '成功');
        }

    function _get_query_conditions($args_data,$con){
        $str = '';
        $query = array();
        foreach ($con as $options)
        {
            if (is_string($options))
            {
                $field = $options;
                $options['field'] = $field;
                $options['name']  = $field;
            }
            !isset($options['equal']) && $options['equal'] = '=';
            !isset($options['assoc']) && $options['assoc'] = 'AND';
            !isset($options['type'])  && $options['type']  = 'string';
            !isset($options['name'])  && $options['name']  = $options['field'];
            !isset($options['handler']) && $options['handler'] = 'trim';
            if (isset($options['name']))
            {
                $input = $options['name'];
                $handler = $options['handler'];
                $value = ($input == '' ? $input : $handler($input));
                if ($value === '' || $value === false)  //若未输入，未选择，或者经过$handler处理失败就跳过
                {
                    continue;
                }
                strtoupper($options['equal']) == 'LIKE' && $value = "%{$value}%";
                if ($options['type'] != 'numeric')
                {
                    $value = "'{$value}'";      //加上单引号，安全第一
                }
                else
                {
                    $value = floatval($value);  //安全起见，将其转换成浮点型
                }
                $str .= " {$options['assoc']} {$options['field']} {$options['equal']} {$value}";
                $query[$options['name']] = $input;
            }
        }
        $this->assign('query', stripslashes_deep($query));

        return $str;
    }


}


?>
