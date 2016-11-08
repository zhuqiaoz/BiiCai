<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/member.app.php';

class api_message extends MemberApp{
    
	public function getCode($args_data) {
	    
	    Response::json(200, "注册成功");
	    
	}
}