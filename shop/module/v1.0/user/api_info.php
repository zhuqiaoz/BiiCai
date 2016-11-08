<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/member.app.php';


class api_info extends MemberApp{
    
    /**
     * 获取个人信息
     * @param unknown $args_data
     */
    function getInfo($args_data) {

        $model_user = & m('member');
        $profile = $model_user->get_info(intval($args_data['userID']));
        $profile['portrait'] = portrait($profile['user_id'], $profile['portrait'], 'middle');
        
        $return = array();
        $return['userName'] = !empty($profile['user_name']) ? $profile['user_name'] : '';
        $return['email'] = !empty($profile['email']) ? $profile['email'] : '';
        $return['realName'] = !empty($profile['real_name']) ? $profile['real_name'] : '';
        if($profile['birthday'] == '0000-00-00'){
            $return['birth'] = '';
        }else{
            $return['birth'] = !empty($profile['birthday']) ? $profile['birthday'] : '';
        }
        $return['qq'] = !empty($profile['im_qq']) ? $profile['im_qq'] : '';
        
        Response::json(200, "获取成功", $return);
        
    }
    
    /**
     * 保存个人资料
     * @param unknown $args_data
     */
    function saveInfo($args_data) {
        $data = array(
            'real_name' => isset($args_data['realName']) ? $args_data['realName'] : '',
            'birthday' => isset($args_data['birthDay']) ? $args_data['birthDay'] : '',
            'im_qq' => isset($args_data['qq']) ? $args_data['qq'] : ''
        );
        $model_user = & m('member');
        $model_user->edit($args_data['userID'], $data);
        if ($model_user->has_error()) {
            Response::json(400, "保存失败");
            return;
        }
        Response::json(200, "保存成功");
    }
    
    /**
     * 修改密码
     * @param unknown $args_data
     */
    function changePwd($args_data){
        /* 修改密码 */
        $ms = & ms();    //连接用户系统
        $result = $ms->user->edit($args_data['userID'], $args_data['oldPwd'], array(
            'password' => $args_data['newPwd']
        ));
        if (!$result) {
            Response::json(400, '账户密码不正确，修改失败');
            return;
        }
        Response::json(200, "修改成功");
    }
    
    /**
     * 修改邮箱
     * @param unknown $args_data
     */
    function changeEmail($args_data){
        $orig_password = $args_data['oldPwd'];
        $email = isset($args_data['newEmail']) ? trim($args_data['newEmail']) : '';
        $ms = & ms();    //连接用户系统
        $result = $ms->user->edit($args_data['userID'], $orig_password, array(
            'email' => $email
        ));
        if (!$result) {
            Response::json(400, '邮箱修改失败');
            return;
        }
        Response::json(200, "邮箱修改成功");
    }
    
    
    /**
     * 修改手机号
     * @param unknown $args_data
     */
    function changePhone($args_data){
        $orig_password = $args_data['oldPwd'];
        $email = isset($args_data['newPhone']) ? trim($args_data['newPhone']) : '';
        $ms = & ms();    //连接用户系统
        $result = $ms->user->edit($args_data['userID'], $orig_password, array(
            'phone_mob' => $email
        ));
        if (!$result) {
            Response::json(400, '手机号修改失败');
            return;
        }
        Response::json(200, "邮箱修改成功");
    }
} 