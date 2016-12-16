<?php
require_once APP_ROOT.'/api/shop/module/response.php';

class api_msg extends StoreadminbaseApp
{
    /**
     * 注册发送验证码
     */
    function send_code($args_data)
    {
        $mobile = empty($args_data['mobile']) ? '' : trim($args_data['mobile']);

        //发送短信的格式
        $type = $args_data['type'];
        if (!in_array($type, array('register', 'find', 'change'))) {
            Response::json(301, '缺少type');
        }
        //发送验证码
        import('mobile_msg.lib');
        $mobile_msg = new Mobile_msg();
        $result = $mobile_msg->send_msg_system($type, $mobile);
        // $result = $mobile_msg->send_msg_system($type, $mobile);
        if($result['done']){
            Response::json(200, '发送成功');
        }else{
            Response::json(302, '发送失败');
        }
    }

    /**
     * 核对手机发送的验证码是否相同
     * errorCode: 302/303=手机号码错误
     */
    function cmc($args_data)
    {
        
        $phone_mob = empty($args_data['mobile']) ? '' : trim($args_data['mobile']);
        $confirm_code = empty($args_data['confirm_code']) ? '' : trim($args_data['confirm_code']);
        
        //发送短信的格式
        $type = $args_data['type'];
        if (!in_array($type, array('register', 'find', 'change'))) {
            Response::json(301, '缺少type');
        }
        $ms = & ms();

//        Response::json(302, '手机号不存在', array('return' => $ms->user->check_mobile($phone_mob)));



        
        if($args_data['type'] == 'find'){
            if(!$ms->user->check_mobile($phone_mob)){
                Response::json(302, '手机号不存在');
            }
        }else{
            if($ms->user->check_mobile($phone_mob)){
                error_log(print_r('============222============',true));
                error_log(print_r($phone_mob,true));
                error_log(print_r($ms->user->check_mobile($phone_mob),true));
                Response::json(303, '手机号已存在');
            }
        }
        
        $msglog_mod = & m('msglog');
        $sql = 'SELECT code FROM ecm_msglog WHERE to_mobile = '.$args_data['mobile'].' ORDER BY time DESC limit 1';
        $code = $msglog_mod->getOne($sql);
        
        if (empty($code) || !$confirm_code) {
            Response::json(304, '验证错误');
            return;
        } else {
            if ($confirm_code == $code) {
                Response::json(200, '验证成功');
            } else {
                Response::json(305, '验证错误');
            }
        }
    }



    /**
     *    检查手机号码是否唯一
     *
     *    @author    Garbin
     *    @param     string $mobile
     *    @return    bool
     */
    function check_mobile($mobile)
    {
        if(empty($mobile)){
            return true;
        }
        //验证是否为手机号
        if (!preg_match("/^1[34578]\d{9}$/", $mobile)) {
            $this->_error('mobile_error');
            return false;
        }
        $model_member =& m('member');
        $info = $model_member->get("phone_mob='{$mobile}' or user_name='{$mobile}'");
        error_log(print_r($info,true));
        if (!empty($info))
        {
            $this->_error('mobile_exists');

            return false;
        }
        return true;
    }
}

?>