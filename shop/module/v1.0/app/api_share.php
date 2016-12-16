<?php

require_once APP_ROOT.'/api/shop/module/response.php';

class api_share {
    
	public function share($args_data) {
	    
	    $return = array();
	    $return['shareTitle'] = '必采';
	    $return['shareCon'] = '必采商城，必采生活';
	    $return['shareUrl'] = 'http://www.biicai.com';
	    $return['shareImg'] = 'http://www.biicai.com/image/ic_launcher.png';
	    Response::json(200, '分享', $return);
        
	}
}