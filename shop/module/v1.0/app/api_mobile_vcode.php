<?php
require_once APP_ROOT.'/api/shop/module/response.php';

class api_mobile_vcode extends MemberbaseApp{
    /**
     * 核对手机发送的验证码是否相同
     */
    function cmc($args_data)
    {
        $confirm_code = empty($args_data['confirm_code']) ? '' : trim($args_data['confirm_code']);
        if (empty($_SESSION['MobileConfirmCode']) || !$confirm_code) {
            Response::json(301, '验证错误');
            return;
        } else {
            if ($confirm_code == $_SESSION['MobileConfirmCode']) {
                Response::json(200, '验证成功');
            } else {
                Response::json(302, '验证错误');
            }
        }
    }

    /*
     * 检测时候是否已经被注册
     */
    function check_mobile($args_data) {
        $phone_mob = empty($args_data['phone_mob']) ? '' : trim($args_data['phone_mob']);
        //是否是手机号
        if (!preg_match("/^(13[0-9]|15[012356789]|17[678]|18[0-9]|14[57])[0-9]{8}$/", $args_data['phone_mob'])) {
            Response::json(303, '您输入的不是有效手机号');
        }
        //发送短信的格式
        $type = $args_data['type'];
        if (!in_array($type, array('register', 'find', 'change'))) {
            Response::json(304, '缺少type');
        }
        $ms = & ms();
        if ($type == 'find') {
            //找回密码是需要存在的电话号码
            echo ecm_json_encode(!$ms->user->check_mobile($phone_mob));
        } else {
            //注册以及修改是需要不存在的电话号码
            echo ecm_json_encode($ms->user->check_mobile($phone_mob));
        }
    }
}
?>