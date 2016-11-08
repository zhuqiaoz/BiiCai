<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/find_password.app.php';

class api_getPwd extends Find_passwordApp{
    
    public function getPwd($args_data){
        if($args_data['type'] == 1){
            //通过手机找回
             
        }else if($args_data['type'] == 2){
            //通过邮箱找回
            $username = trim($args_data['userName']);
            $email = trim($args_data['email']);
             
            /* 简单验证是否是该用户 */
            $ms =& ms();     //连接用户系统
            $info = $ms->user->get($username, true);
            if (empty($info)){
                Response::json(401, "该用户不存在");
                return;
            }
            if($info['email'] != $email){
                Response::json(402, "邮箱地址与用户绑定邮箱不匹配");
                return;
            }
            $word = $this->_rand();
            $md5word = md5($word);
            $res = $this->_password_mod->get($info['user_id']);
            if (empty($res)){
                $info['activation'] = $md5word;
                $this->_password_mod->add($info);
            } else {
                $this->_password_mod->edit($info['user_id'], array('activation' => "{$md5word}"));
            }
            $mail = get_mail('touser_find_password', array('user' => $info, 'word' => $word));
            $this->_mailto($email, addslashes($mail['subject']), addslashes($mail['message']));
            Response::json(200, "邮件已发送到您的邮箱，请注意查收");
            return;
        }
    }
    
}