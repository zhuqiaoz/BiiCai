<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/member.app.php';

class api_user extends MemberApp{
    
	public function registere($args_data) {
        $user = $this->registerApp($args_data);
        if($user['code'] == 200){
            $return = array(
                'userName' => $args_data['userName'],
                'userID' => $user['userID']
            );
            Response::json(200, "注册成功", $return);
        }else{
            Response::json(400, $user['message']);
        }
	}
	
	public function login($args_data) {
	    $user = $this->loginApp($args_data);
	    if($user['code'] == 200){
	        $return = array(
	            'userName' => $args_data['userName'],
	            'userID' => $user['userID']
	        );
	        Response::json(200, "登录成功", $return);
	    }else{
	        Response::json(400, $user['message']);
	    }
	}
	
	function loginApp($args_data){
	    
	    $temp = $this->check_user($args_data);
	    if($temp == 200){
	        return array('code' => 403, 'message' => '该用户不存在');
	    }
	    
        $user_name = trim($args_data['userName']);
        $password = $args_data['passwd'];

        $ms = & ms();
        $user_id = $ms->user->auth($user_name, $password);

        if (!$user_id) {
            return array('code' => 401, 'message' => '用户名或密码错误');
        } else {
            /* 通过验证，执行登陆操作 */
            $this->_do_login($user_id);
            /* 同步登陆外部系统 */
            $synlogin = $ms->user->synlogin($user_id);
            return array('code' => 200, 'message' => '登录成功', 'userID' => $user_id);
        }
	}
	
	/**
	 *    注册一个新用户
	 *
	 *    @author    Garbin
	 *    @return    void
	 */
	function registerApp($args_data) {
	    
	    $temp = $this->check_user($args_data);
	    if($temp != 200){
	        return array('code' => 403, 'message' => '该用户已存在');
	    }
	    
	    /* 注册并登陆 */
        $user_name = trim($args_data['userName']);
        $password = $args_data['passwd'];
        $email = trim($args_data['email']);
        $phone_mob = trim($args_data['phone']);
        $tuijian_id = trim($args_data['recommCode']);
        $imageCode = trim($args_data['imageCode']);
        $phoneCode = trim($args_data['phoneCode']);
        
        // 图片验证码
//         $code = $this->check_captcha($imageCode);
//         if($code != 200){
//             return array('code' => 400, 'message' => '验证码不正确');
//         }

        $ms = & ms(); //连接用户中心
        $user_id = $ms->user->register($user_name, $password, $email, $phone_mob, $tuijian_id);
        if($user_id == null){
            return array('code' => 404, 'message' => '注册邮箱已使用');
        }
        
        /*用户注册功能后 积分操作*/
        import('integral.lib');
        $integral=new Integral();
        $integral->change_integral_reg($user_id);
        //登录
        $this->_do_login($user_id);
        //修改成长值和会员等级 by qufood
        $user_mod=&m('member');
        $user_mod->edit_growth($user_id,'register');
        /* 同步登陆外部系统 */
        $synlogin = $ms->user->synlogin($user_id);
        return array('code' => 200, 'message' => '注册成功', 'userID' => $user_id, 'userName' => $user_name);
	}
	
	/* 检查验证码 */
	function check_captcha($imageCode){
	    $captcha = empty($imageCode) ? '' : strtolower(trim($imageCode));
	    if (!$captcha){
	        return 401;
	    }
	    if (base64_decode($_SESSION['captcha']) != $captcha){
	        return 402;
	    } else {
	        return 200;
	    }
	}
	
	/**
	 *    检查用户是否存在
	 *
	 *    @author    Garbin
	 *    @return    void
	 */
	function check_user($args_data) {
	    $ms = & ms();
	    $info = $ms->user->check_username(trim($args_data['userName']));
	    if($info == null){
	        return 403;
	    }else{
	        return 200;
	    }
	}

	/**
	 * 联合登录
	 */
	function unionLogin($args_data) {
	    if($args_data['platform'] == 'qq'){
	        $unionid = 0;
	        $third_name = 'qq';
	        $nickname = isset($args_data['nickName']) ? $args_data['nickName'] : '';
	        $user_id = isset($args_data['openID']) ? $args_data['openID'] : '';
	        $openid = $user_id;
	        $this->check_third_login($third_name,$openid,$unionid,$nickname);
	    }else if($args_data['platform'] == 'sina'){
	        $unionid = 0;
	        $third_name = 'sina';
	        $nickname = isset($args_data['nickName']) ? $args_data['nickName'] : '';
	        $user_id = isset($args_data['openID']) ? $args_data['openID'] : '';
	        $openid = $user_id;
	        $this->check_third_login($third_name,$openid,$unionid,$nickname);
	    }else {
	        Response::json(401, "登录平台不存在");
	    }
	}
	
	function check_third_login($third_name,$openid,$unionid=0,$nickname) {
	    if ($unionid) {
	        $conditions = " unionid='$unionid' and third_name='$third_name'";
	    } else {
	        $conditions = " openid='$openid' and third_name='$third_name'";
	    }
	
	    $third_login_mod = & m('third_login');
	    $third_login = $third_login_mod->get($conditions);
	
	    $generate_code = rand(10000,99999);
	    $_SESSION['generate_code'] = $generate_code;
	
	    if (empty($third_login)) {
	        //添加到third_login表
	        $data = array(
	            'third_name' => $third_name,
	            'openid' => $openid,
	            'unionid' => $unionid,
	            'user_id' => '0',
	            'add_time' => gmtime(),
	            'update_time' => gmtime(),
	        );
	        $third_login_id = $third_login_mod->add($data);
	        
	        $param = array();
	        $param['userName'] = $third_name.'_'.$generate_code;
	        $param['passwd'] = 'passwd';
	        $user = $this->registerApp($param);
	        if($user['code'] == 200){
	            $data = array(
	                'user_id' => $user['userID'],
	                'user_name' => $user['userName'],
	                'nick_name' => $nickname
	            );
	            $third_login_mod->edit($third_login_id, $data);
	        }
	        
	        if($user['code'] == 200){
	            $return = array(
	                'userName' => $user['userName'],
	                'userID' => $user['userID']
	            );
	            Response::json(200, "登录成功", $return);
	        }else{
	            Response::json(400, $user['message']);
	        }
	    } else {
	        $third_login_id = $third_login['id'];
	        $data = array(
	            'update_time' => gmtime(),
	        );
	        $third_login_mod->edit($third_login_id, $data);
	
	        if ($third_login["user_id"]) {
	            $member_mod = &m('member');
	            $member = $member_mod->get($third_login["user_id"]);
	            if (empty($member)) {
	                Response::json(402, "用户不存在");
	            } else {
	                $param = array();
	                $param['userName'] = $third_login['user_name'];
	                $param['passwd'] = 'passwd';
	                $this->login($param);
	            }
	        } else {
	            Response::json(402, $third_name."登录失败");
	        }
	    }
	}
	
}