<?php

require_once APP_ROOT.'/api/shop/module/response.php';

class api_home extends MallbaseApp{
    
	public function getHome($args_data) {
	    
	    $this->_ad_mod = & m('ad');
	    
	    $conditions = $this->_get_query_conditions(array(
	        array(
	            'field' => 'ad_type',
	            'equal' => '=',
	            'name' => 'ad_type',
	            'type' => 'numeric',
	        ),
	        array(
	            'field' => 'ad_name',
	            'equal' => 'LIKE',
	            'assoc' => 'AND',
	            'name'  => 'ad_name',
	            'type'  => 'string',
	        ),
	    ));

	    //更新排序
	    $sort = 'ad_id';
        $order = 'desc';

        //获取首页广告分类
	    $ad_type_list = $this->get_ad_type_list();
	    
	    $page = $this->_get_page(20);   //获取分页信息
	    $ads = $this->_ad_mod->find(array(
	        'conditions' => 'user_id=0' . $conditions,
	        'limit' => $page['limit'],
	        'order' => "$sort $order",
	        'count' => true
	    ));
	    $page['item_count'] = $this->_ad_mod->getCount();   //获取统计数据
	    $this->_format_page($page);
	    
	    foreach ($ads as $key => $ad) {
	        $ad['ad_logo'] && $ads[$key]['ad_logo'] = dirname(site_url()) . '/' . $ad['ad_logo'];
	        $ads[$key]['ad_type'] = $ad_type_list[$ad['ad_type']];
	    }
	    
        error_log(print_r($ads, true));
        
        Response::json(200, '返回成功');
	}
	
	/* 返回所有的图片类型 */
	
	function get_ad_type_list() {
	    return array(
	        1 => '手机首页轮播图', //手机版首页轮播
	        2 => '手机首页按钮', //手机版按钮
	        3 => '手机天天抢鲜', //手机版精彩活动
	        4 => '手机超市精选', //手机版精彩活动
	        5 => '必采选项卡', //手机版新增
	        6 => '手机爱生活', //手机版新增
	        7 => '手机广告单张', //手机版新增
	        8 => '手机广告多张', //手机版新增
	        9 => '必采众筹', //手机版新增
	        10 => '必采必抢', //手机版新增
	        11 => '限时限量抢',//手机版新增
	        12 => '0元购',//手机版新增
	    );
	}
	
	
}



