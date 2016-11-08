<?php

require_once APP_ROOT.'/api/shop/module/response.php';

class api_account extends MallbaseApp{

        var $_member_mod;
        var $_account_log_mod;
        var $_member_bank_mod;
//    var $_order_mod;

    function api_account() {
          $this->_member_mod = & m('member');
          $this->_account_log_mod = & m('account_log');
          $this->_member_bank_mod = & m('member_bank');
//        $this->_order_mod = & m('order');
    }

    function logall($args_data) {
//    [10] => account_type_admin
//    [20] => account_type_buy
//    [30] => account_type_seller
//    [40] => account_type_in
//    [50] => account_type_out
//    [60] => account_type_cz
//    [70] => account_type_tx
//    [80] => account_type_refund_in
//    [90] => account_type_refund_out
//    [100] => account_type_tuijian_buyer
//    [110] => account_type_tuijian_seller
//    [120] => account_type_trade_charges

        $con = (array(
            array(
                'field' => 'type',
                'equal' => '=',
                'name' => 'type',
                'type' => 'numeric',
            ),
            array(
                'field' => 'complete',
                'equal' => '=',
                'name' => 'complete',
                'type' => 'numeric',
            ),
        ));

        $conditions = $this->_get_query_conditions($args_data,$con);
        $account_log_list = $this->_account_log_mod->find(array(
            'conditions' => 'user_id=5'.$args_data['userID'] .$conditions,
            'order' => "id desc",
            'count' => true));
        return($account_log_list);
        Response::json(200, '成功');
    }



    function czlist($args_data) {

        $model_payment = & m('payment');

        $white_list = $model_payment->get_white_list();

        $payments = $model_payment->get_builtin($white_list);
        foreach ($payments as $payment_key => $payment) {
            $payments[$payment_key]['logo'] = SITE_URL.'/includes/payments/' . $payment_key . '/logo.gif';
            if (!$payment['is_online']) {
                unset($payments[$payment_key]);
            } else {
                if ($payment['is_mobile'] == 0) {
                    unset($payments[$payment_key]);
                }
            }
        }
        return($payments);
        Response::json(200, '成功');
    }



    //余额转帐
    function out($args_data) {
        $userId = trim($args_data['userID']);//登陆的用户Id
        $name = trim($args_data['userName']);//登陆得用户名
        $to_money = trim($args_data['to_money']); //新增输入得金额
        $to_user_name = trim($args_data['to_user_name']); //新增输入的用户名称
        $to_passwd = trim($args_data['to_passwd']); //新增输入的密码

            if (preg_match("/[^0.-9]/", $args_data['to_money'])) {//判断参数to_money是否全身数字
                Response::json(301, '数字金额格式错误');
            }
            if ($name == $to_user_name) {//判断参数用户是否等于用户
                Response::json(302, '错误：不能自己给自己转账!');
            }


            $to_row = $this->_member_mod->get("user_name= '$to_user_name'");
            $to_row_user_id = $to_row['user_id'];
            $to_row_user_name = $to_row['user_name'];
            $to_row_user_money = $to_row['money'];
            if (empty($to_row_user_id)) {
                Response::json(303, '错误：目标用户不存在!');
            }
            $member = $this->_member_mod->get("user_id='$userId'");
            $user_money = $member['money'];
            $user_zf_pass = $member['zf_pass'];
            if(empty($user_zf_pass)){
                Response::json(304, '错误：请先设置支付密码!');
            }
            $zf_pass = md5(trim($to_passwd));
            if ($user_zf_pass != $zf_pass) {
                Response::json(305, '错误：支付密码验证失败!');
            }
            $time = gmtime();
            $order_sn = date('YmdHis', $time + 8 * 3600) . rand(1000, 9999);
            if ($user_money < $to_money) {
                Response::json(306, '错误：账户余额不足');
            } else {
                //添加日志
                $log_text = $name . '给' . $to_row_user_name . '转出金额' . $to_money . '元';
                $add_account_log = array(
                    'user_id' =>$userId,
                    'user_name' =>$name,
                    'order_sn' => $order_sn,
                    'add_time' => $time,
                    'type' => ACCOUNT_TYPE_OUT, //转出
                    'money_flow' => 'outlay',
                    'money' => $to_money,
                    'complete' => 1,
                    'log_text' => $log_text,
                    'states' => 40,
                );
                $this->_account_log_mod->add($add_account_log);
                $log_text_to = $name . '给' . $to_row_user_name . '转入金额' . $to_money .'元';
                $add_account_log_to = array(
                    'user_id' => $to_row_user_id,
                    'user_name' => $to_user_name,
                    'order_sn ' => $order_sn,
                    'add_time' => $time,
                    'type' => ACCOUNT_TYPE_IN, //转入
                    'money_flow' => 'income',
                    'money' => $to_money,
                    'complete' => 1,
                    'log_text' => $log_text_to,
                    'states' => 40,
                );
                $this->_account_log_mod->add($add_account_log_to);

                $new_user_money = $user_money - $to_money;
                $new_to_user_money = $to_row_user_money + $to_money;

                $add_jia = array(
                    'money' => $new_to_user_money,
                );
                $this->_member_mod->edit('user_id=' . $to_row_user_id, $add_jia);


                $add_jian = array(
                    'money' => $new_user_money,
                );
                $this->_member_mod->edit('user_id=' . $userId, $add_jian);
                Response::json(200, '转账成功');
            }
        }




    //修改支付密码
    function editpassword($args_data) {
            $member = $this->_member_mod->get("user_id=" .$args_data['userID']);
            if(!$member['zf_pass']){
                Response::json(600, '没有支付密码状态为600');  //没有支付密码，页面上不显示原支付密码
            }
            $y_pass = trim($args_data['y_pass']);
            $zf_pass = trim($args_data['zf_pass']);
            $zf_pass2 = trim($args_data['zf_pass2']);
            if (empty($zf_pass)) {
                Response::json(302, '错误：支付密码不能为空!');
            }
            if ($zf_pass != $zf_pass2) {
                Response::json(303, '错误：两次输入密码不一致!');
            }
            //如果未设置支付密码，则直接设置,已设置支付密码 需要验证原支付密码

            $md5zf_pass = md5($zf_pass);
            if ($member['zf_pass'] != "") {
                //转换32位 MD5
                $md5y_pass = md5($y_pass);

                if ($member['zf_pass'] != $md5y_pass) {
                    Response::json(303, '错误：原支付密码验证失败!');
                }
            }
            $newpass_array = array(
                'zf_pass' => $md5zf_pass,
            );
            $this->_member_mod->edit('user_id=' .$args_data['userID'], $newpass_array);
             Response::json(200, '支付密码修改成功!');
        }




   //提现申请
    function withdraw($args_data) {
            $member = $this->_member_mod->get("user_id=" .$args_data['userID']);
            if(empty($member['zf_pass'])){
                Response::json(601, '跳转到修改支付密码，并没有原密码');  //没有支付密码，页面上不显示原支付密码
            }

            //获取当前用户设置的银行卡信息
//            error_log(print_r('===========================111111111111111=================',true));
            $bank_list = $this->_member_bank_mod->get("user_id=" .$args_data['userID']);
//            error_log(print_r($bank_list,true));
            if(empty($bank_list)){
                Response::json(602, '没有银行卡信息，跳转bank_add');  //没有支付密码，页面上不显示原支付密码
            }


        //为结束
            $tx_money = trim($_POST['tx_money']);
            $money_row = $this->_member_mod->get("user_id=" . $args_data['userID']);

            $post_zf_pass = trim($_POST['post_zf_pass']);
            if (empty($post_zf_pass)) {
                Response::json(701, '错误：支付密码不能为空!');
            }
            $md5zf_pass = md5($post_zf_pass);
            if ($money_row['zf_pass'] != $md5zf_pass) {
                Response::json(702, '错误：支付密码验证失败!');
            }
            //检测用户的银行信息
            if (empty($tx_money)) {
                Response::json(703, '提现金额不能为空!');
            }
            if (preg_match("/[^0.-9]/", $tx_money)) {
                Response::json(704, '错误：你输入的不是有效金额!');
            }
            if ($money_row['money'] < $tx_money) {
                $this->show_warning('duibuqi_zhanghuyuebuzu');
                Response::json(705, '对不起：账户余额不足!');
            }
            //通过验证 开始操作数据
            $newmoney = $money_row['money'] - $tx_money;
            $newmoney_dj = $money_row['money_dj'] + $tx_money;

            //获取提交的bank_id
            $bank_id = $_POST['bank_id'];
            $bank = $this->_member_bank_mod->get('bank_id=' . $bank_id . ' AND user_id=' . $this->_user_id);
            $bank_str = '开户银行:' . $bank['bank_name'] . ',开户行地址:' . $bank['open_bank'] . ',户名:' . $bank['account_name'] . ',卡号:' . $bank['bank_num'];

            //添加日志
            $order_sn = date('YmdHis', gmtime() + 8 * 3600) . rand(1000, 9999);
            $log_text = $this->_user_name . Lang::get('tixianshenqingjine') . $tx_money . Lang::get('yuan') . $bank_str;



            $add_account_log = array(
                'user_id' => $args_data['userID'],
                'user_name' => $args_data['userName'],
                'order_sn ' => $order_sn,
                'add_time' => gmtime(),
                'type' => ACCOUNT_TYPE_TX, //提现
                'money_flow' => 'outlay',
                'money' => $tx_money,
                'log_text' => $log_text,
                'states' => 70, //待审核
            );
            $this->_account_log_mod->add($add_account_log);
            $edit_mymoney = array(
                'money_dj' => $newmoney_dj,
                'money' => $newmoney,
            );
            $this->_member_mod->edit('user_id=' . $this->_user_id, $edit_mymoney);



            Response::json(200, '提现成功，请等待管理员审核!');
        }


    //银行新增
    function bank_add($args_data) {
            $short_name = trim($args_data['short_name']);
            $account_name = trim($args_data['account_name']);
            $bank_type = trim($args_data['bank_type']); //debit 储值卡  credit 信用卡
            $bank_num = trim($args_data['bank_num']);
            $open_bank = trim($args_data['open_bank']);

            if (empty($short_name)) {
                Response::json(401, '银行简写代码非法');
            }
            if (empty($bank_num)) {
                Response::json(402, '银行卡号不能为空');
            }
            if (empty($account_name) || strlen($account_name) < 6 || strlen($account_name) > 30) {
                Response::json(403, '户名为您的真实姓名，不能为空且必须是2-10个中文字符之间');
            }
            if (!in_array($bank_type, array('储蓄卡', '信用卡'))) {
                Response::json(404, '卡号类型非法');
            }
            $bank_name = $this->_get_bank_name($short_name);

            if (empty($bank_name)) {
                Response::json(405, '银行名称非法');
            }
            $data = array(
                'user_id' => $args_data['userID'],
                'bank_name' => $bank_name,
                'short_name' => strtoupper($short_name),
                'account_name' => $account_name,
                'open_bank' => $open_bank,
                'bank_type' => $bank_type,
                'bank_num' => $bank_num,
            );

            if (!$this->_member_bank_mod->add($data)) {
                Response::json(406, '添加异常');
            }
            Response::json(200, '新增成功');
    }

    //银行修改
    function bank_edit($args_data) {

            $short_name = trim($args_data['short_name']);
            $account_name = trim($args_data['account_name']);
            $bank_type = trim($args_data['bank_type']); //debit 储值卡  credit 信用卡
            $bank_num = trim($args_data['bank_num']);
            $open_bank = trim($args_data['open_bank']);
            $bank_id = trim($args_data['bank_id']);

            $bank = $this->_member_bank_mod->get('bank_id=' . $bank_id . ' AND user_id=' . $args_data['userID']);
            if (empty($bank)) {
                $this->show_warning('error');
                return;
            }

            if (empty($short_name)) {
                Response::json(401, '银行简写代码非法');
            }
            if (empty($bank_num)) {
                Response::json(402, '银行卡号不能为空');
            }
            if (empty($account_name) || strlen($account_name) < 6 || strlen($account_name) > 30) {
                Response::json(403, '户名为您的真实姓名，不能为空且必须是2-10个中文字符之间');
            }
            if (!in_array($bank_type, array('储蓄卡', '信用卡'))) {
                Response::json(404, '卡号类型非法');
            }
            $bank_name = $this->_get_bank_name($short_name);

            if (empty($bank_name)) {
                Response::json(405, '银行名称非法');
            }
            $data = array(
                'user_id' => $args_data['userID'],
                'bank_name' => $bank_name,
                'short_name' => strtoupper($short_name),
                'account_name' => $account_name,
                'open_bank' => $open_bank,
                'bank_type' => $bank_type,
                'bank_num' => $bank_num,
            );

            if (!$this->_member_bank_mod->edit($bank_id, $data)) {
                Response::json(406, '修改异常');
            }
         Response::json(200, '新增成功');

    }


























    function _get_query_conditions($args_data, $con){
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
            !isset($options['type'])  && $options['type']  = 'numeric';
            !isset($options['name'])  && $options['name']  = $options['field'];
            !isset($options['handler']) && $options['handler'] = 'trim';
            if ($args_data['type'] != 0)
            {

                $input = $args_data[$options['name']];



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
        return $str;
    }
    function _get_bank_name($short_name) {
        if (!$this->_check_short_name($short_name))
            return '';
        $bank_inc = $this->_get_bank_inc();
        return $bank_inc[$short_name];
    }
    function _get_bank_inc($type = '') {
        if ($type == 'alipaybank') {
            $bank_inc = include ROOT_PATH . '/data/alipaybank.inc.php';
        } else {
            $bank_inc = include ROOT_PATH . '/data/bank.inc.php';
        }
        if (!is_array($bank_inc) || count($bank_inc) < 1) {
            $this->show_warning('bank_inc_error');
            return;
        }
        return $bank_inc;
    }
    function _check_short_name($short_name) {
        $bank_inc = $this->_get_bank_inc();
        if (!is_array($bank_inc) || count($bank_inc) < 1) {
            return false;
        }
        foreach ($bank_inc as $key => $bank) {
            if (strtoupper($short_name) == strtoupper($key)) {
                return true;
            }
        }
        return false;
    }

}
?>