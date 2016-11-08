<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/goods.app.php';

class api_goodsDetail extends GoodsApp{
    
    var $_goods_mod;
    var $_ju_mod;
    var $_gradegoods_mod; //by qufood
    
	public function getDetail($args_data) {
	    
	    $this->_goods_mod = & m('goods');
	    $this->_ju_mod = &m('ju');
	    $this->_gradegoods_mod = &m('gradegoods'); //by qufood
	    
	    if(empty($args_data['productID'])){
	        Response::json(400, "缺少参数productID");
	        return ;
	    }
	    
	    /* 可缓存数据 */
	    $data = $this->_get_common_info($args_data['productID']);
	    
	    /* 赋值商品评论 */
	    $comment = $this->_get_goods_comment($args_data['productID'], 10);
	    
	    $ruturn = array();
	    
	    if($data){
	        $goods = $data['goods'];
	        $store = $data['store_data'];
	        
	        $return['productID'] =  $goods['goods_id'];
	        $return['storeID'] =  $goods['store_id'];
	        $return['productName'] =  $goods['goods_name'];
	        $return['productImage'] = array();
	        $imageArr = $goods['_images'];
	        foreach ($imageArr as $image){
	            $return['productImage'][] = 'http://www.biicai.com/'.$image['image_url'];
	        }
	        $return['marketPrice'] =  $goods['market_price'];
	        $return['salePrice'] =  $goods['price'];
	        $return['saleNum'] =  $goods['integral_max_exchange'];
	        $return['commentNum'] =  $goods['mall_recommended'];
	        
	        $specArr = $goods['_specs'];
	        $tempArr = array();
	        foreach ($specArr as $spec){
	            $tempArr[] = $spec['spec_1'];
	        }
	        $repeat_arr = array_unique ( $tempArr );
	        
	        $return['productSpec'] = array();
	        foreach ($repeat_arr as $specName){
	            $spec1 = array();
	            $spec1['colorName'] = $specName;
	            $spec1['sizeArr'] = array();
	            foreach ($specArr as $spec){
	                if($specName == $spec['spec_1']){
	                    $spec2 = array();
	                    $spec2['specID'] = $spec['spec_id'];
	                    $spec2['productID'] = $spec['goods_id'];
	                    $spec2['sizeName'] = $spec['spec_2'];
	                    $spec2['price'] = $spec['price'];
	                    $spec2['stock'] = $spec['stock'];
	                    $spec2['sku'] = $spec['sku'];
	                    $spec1['sizeArr'][] = $spec2;
	                }
	            }
	            $return['productSpec'][] = $spec1;
	        }
	        $return['regionName'] =  $store['region_name'];
	        $return['storeID'] =  $store['store_id'];
	        $return['storeName'] =  $store['store_name'];
	        $return['description'] =  $goods['description'];
	        $return['comments'] = array();
    	    foreach ($comment['comments'] as $c){
                $comm = array();
                $comm['level'] = $c['evaluation'] + 2;
                $comm['content'] = $c['comment'];
                $comm['createTime'] = !empty($c['evaluation_time']) ? $this->microtime_format('Y-m-d H:i:s', $c['evaluation_time']) : '';
                $comm['userName'] = $c['buyer_name'];
                $return['comments'][] = $comm;
            }
	         
	        Response::json(200, "返回成功", $return);
	    }else {
	        Response::json(400, "数据获取失败");
	    }
	    
	}
	
	/* 取得商品评论 */
	
	function _get_goods_comment($goods_id, $num_per_page) {
	    $data = array();
	
	    $conditions = "goods_id = '$goods_id' AND evaluation_status = '1'";
	    $page = $this->_get_page($num_per_page);
	    $order_goods_mod = & m('ordergoods');
	    $comments = $order_goods_mod->find(array(
	        'conditions' => $conditions,
	        'join' => 'belongs_to_order',
	        'fields' => 'buyer_id, buyer_name, anonymous, evaluation_time, comment, evaluation',
	        'count' => true,
	        'order' => 'evaluation_time desc',
	        'limit' => $page['limit'],
	    ));
	
	    //获取买家的信誉
	    if (!empty($comments)) {
	        import('evaluation.lib');
	        $evaluation = new Evaluation();
	        foreach ($comments as $key => $value) {
	            $data = $evaluation->get_buyer_evaluation($value['buyer_id']);
	            $comments[$key]['buyer_credit_value'] = $data['buyer_credit_value'];
	            $comments[$key]['buyer_credit_image'] = $data['buyer_credit_image'];
	            $comments[$key]['buyer_praise_rate'] = $data['buyer_praise_rate'];
	        }
	    }
	    $data['comments'] = $comments;
	
	    $page['item_count'] = $order_goods_mod->getCount();
	    $this->_format_page($page);
	    $data['page_info'] = $page;
	    $data['more_comments'] = $page['item_count'] > $num_per_page;
	
	    return $data;
	}
	
	/** 格式化时间戳，精确到毫秒，x代表毫秒 */
	
	function microtime_format($tag, $time)
	{
	    list($usec, $sec) = explode(".", $time);
	    $date = date($tag,$usec);
	    return str_replace('x', $sec, $date);
	}
	
}