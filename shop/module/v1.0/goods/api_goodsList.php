<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/search.app.php';

class api_goodsList extends SearchApp{
    
	public function getGoodsList($args_data) {
	    
	    if(empty($args_data['categoryID']) && empty($args_data['keyword'])){
	        Response::json(400, "缺少参数categoryID或keyword");
	        return ;
	    }
	    
	    if(empty($args_data['sortID'])){
	        Response::json(400, "缺少参数sortID");
	        return ;
	    }
	    
	    if(empty($args_data['pageSize'])){
	        Response::json(400, "缺少参数pageSize");
	        return ;
	    }
	    
	    if(empty($args_data['curPage'])){
	        Response::json(400, "缺少参数curPage");
	        return ;
	    }

	    // 查询参数
	    $param = $this->_get_query_param($args_data);
	    
	    /* 筛选条件 */
	    $this->assign('filters', $this->_get_filter($param));
	    
	    /* 按分类、品牌、地区、价格区间统计商品数量 */
	    $stats = $this->_get_group_by_info($param, ENABLE_SEARCH_CACHE);
	    
	    /* 排序 */
	    $orders = $this->_get_orders();
	    
	    /* 分页信息 */
	    $page = $this->_get_page($args_data);
	    $page['item_count'] = $stats['total_count'];
	    $this->_format_page($page);
	    $this->assign('page_info', $page);
	    
	    /* 商品列表 */
	    $conditions = $this->_get_goods_conditions($param);
	    $goods_mod = & m('goods');
	    
        switch ($args_data['sortID']){
            case 1:
                //销量
                $goods_list = $goods_mod->get_list(array(
                    'conditions' => $conditions,
                    'order' => 'sales desc',
                    'fields' => 's.praise_rate,s.im_qq,s.im_ww,',
                    'limit' => $page['limit'],
                ));
            break;
            case 2:
                //价格
                $goods_list = $goods_mod->get_list(array(
                    'conditions' => $conditions,
                    'order' => 'price desc',
                    'fields' => 's.praise_rate,s.im_qq,s.im_ww,',
                    'limit' => $page['limit'],
                ));
            break;
            case 3:
                //新品
                $goods_list = $goods_mod->get_list(array(
                    'conditions' => $conditions,
                    'order' => 'add_time desc',
                    'fields' => 's.praise_rate,s.im_qq,s.im_ww,',
                    'limit' => $page['limit'],
                ));
            break;
            case 4:
                //好评
                $goods_list = $goods_mod->get_list(array(
                    'conditions' => $conditions,
                    'order' => 'comments desc',
                    'fields' => 's.praise_rate,s.im_qq,s.im_ww,',
                    'limit' => $page['limit'],
                ));
            break;
        }	    
	    
	    $goods_list = $this->_format_goods_list($goods_list);
	    
	    $return = array();
	    $return_arr = array();
	    
	    foreach ($goods_list as $goods){
	          $value = array();
	          $value['productID'] = $goods['goods_id'];
	          $value['productName'] = $goods['goods_name'];
	          $value['productImage'] = "http://www.biicai.com/".$goods['default_image'];
	          $value['productPrice'] = $goods['price'];
	          $return_arr[] = $value;
	    }
	    
	    $return['goodsList'] = $return_arr;
	    $return['curPage'] = $page['curr_page'];
	    $return['pageCount'] = $page['page_count'];
	    $return['pageSize'] = $page['pageper'];
	    $return['count'] = $page['item_count'];
	    
	    Response::json(200, "返回成功", $return);
	}
	
	/**
	 * 取得查询参数（有值才返回）
	 *
	 * @return  array(
	 *              'keyword'   => array('aa', 'bb'),
	 *              'cate_id'   => 2,
	 *              'layer'     => 2, // 分类层级
	 *              'brand'     => 'ibm',
	 *              'region_id' => 23,
	 *              'price'     => array('min' => 10, 'max' => 100),
	 *          )
	 */
	function _get_query_param($args_data) {
	    static $res = null;
	    if ($res === null) {
	        $res = array();
	
	        // keyword
	        $keyword = isset($args_data['keyword']) ? trim($args_data['keyword']) : '';
	        if ($keyword != '') {
	            //$keyword = preg_split("/[\s," . Lang::get('comma') . Lang::get('whitespace') . "]+/", $keyword);
	            $tmp = str_replace(array(Lang::get('comma'), Lang::get('whitespace'), ' '), ',', $keyword);
	            $keyword = explode(',', $tmp);
	            sort($keyword);
	            $res['keyword'] = $keyword;
	        }
	
	        // cate_id
	        if (isset($args_data['categoryID']) && intval($args_data['categoryID']) > 0) {
	            $res['cate_id'] = $cate_id = intval($args_data['categoryID']);
	            $gcategory_mod = & bm('gcategory');
	            $res['layer'] = $gcategory_mod->get_layer($cate_id, true);
	        }
	
	        // brand
	        if (isset($args_data['brand'])) {
	            $brand = trim($args_data['brand']);
	            $res['brand'] = $brand;
	        }
	
	        // region_id
	        if (isset($args_data['region_id']) && intval($args_data['region_id']) > 0) {
	            $res['region_id'] = intval($args_data['region_id']);
	        }
	
	        // price
	        if (isset($args_data['price'])) {
	            $arr = explode('-', $args_data['price']);
	            $min = abs(floatval($arr[0]));
	            $max = abs(floatval($arr[1]));
	            if ($min * $max > 0 && $min > $max) {
	                list($min, $max) = array($max, $min);
	            }
	
	            $res['price'] = array(
	                'min' => $min,
	                'max' => $max
	            );
	        }
	        //  获取属性参数
	        if (isset($args_data['props'])) {
	            if ($this->_check_query_param_by_props()) {
	                $res['props'] = trim($args_data['props']);
	            }
	        }
	    }
	
	    return $res;
	}
	
	/**
	 *    获取分页信息
	 *
	 *    @author    Garbin
	 *    @return    array
	 */
	function _get_page($args_data)
	{
	    $page = empty($args_data['curPage']) ? 1 : intval($args_data['curPage']);
	    if(!is_int($page)||$page<1)
	    {
	        $this->show_warning('Hacking Attempt');
	        return;
	    }
	    $start = ($page -1) * $args_data['pageSize'];
	
	    return array('limit' => "{$start},{$args_data['pageSize']}", 'curr_page' => $page, 'pageper' => $args_data['pageSize']);
	}
	
	/**
	 * 格式化分页信息
	 * @param   array   $page
	 * @param   int     $num    显示几页的链接
	 */
	function _format_page(&$page, $num = 7)
	{
	    $page['page_count'] = ceil($page['item_count'] / $page['pageper']);
	    $mid = ceil($num / 2) - 1;
	    if ($page['page_count'] <= $num)
	    {
	        $from = 1;
	        $to   = $page['page_count'];
	    }
	    else
	    {
	        $from = $page['curr_page'] <= $mid ? 1 : $page['curr_page'] - $mid + 1;
	        $to   = $from + $num - 1;
	        $to > $page['page_count'] && $to = $page['page_count'];
	    }
	
	    /*
	     if (preg_match('/[&|\?]?page=\w+/i', $_SERVER['REQUEST_URI']) > 0)
	     {
	     $url_format = preg_replace('/[&|\?]?page=\w+/i', '', $_SERVER['REQUEST_URI']);
	     }
	     else
	     {
	     $url_format = $_SERVER['REQUEST_URI'];
	     }
	     */
	
	    /* 生成app=goods&act=view之类的URL */
	    if (preg_match('/[&|\?]?page=\w+/i', $_SERVER['QUERY_STRING']) > 0)
	    {
	        $url_format = preg_replace('/[&|\?]?page=\w+/i', '', $_SERVER['QUERY_STRING']);
	    }
	    else
	    {
	        $url_format = $_SERVER['QUERY_STRING'];
	    }
	
	    $page['page_links'] = array();
	    $page['first_link'] = ''; // 首页链接
	    $page['first_suspen'] = ''; // 首页省略号
	    $page['last_link'] = ''; // 尾页链接
	    $page['last_suspen'] = ''; // 尾页省略号
	    for ($i = $from; $i <= $to; $i++)
	    {
	        $page['page_links'][$i] = url("{$url_format}&page={$i}");
	    }
	    if (($page['curr_page'] - $from) < ($page['curr_page'] -1) && $page['page_count'] > $num)
	    {
	        $page['first_link'] = url("{$url_format}&page=1");
	        if (($page['curr_page'] -1) - ($page['curr_page'] - $from) != 1)
	        {
	            $page['first_suspen'] = '..';
	        }
	    }
	    if (($to - $page['curr_page']) < ($page['page_count'] - $page['curr_page']) && $page['page_count'] > $num)
	    {
	        $page['last_link'] = url("{$url_format}&page=" . $page['page_count']);
	        if (($page['page_count'] - $page['curr_page']) - ($to - $page['curr_page']) != 1)
	        {
	            $page['last_suspen'] = '..';
	        }
	    }
	
	    $page['prev_link'] = $page['curr_page'] > $from ? url("{$url_format}&page=" . ($page['curr_page'] - 1)) : "";
	    $page['next_link'] = $page['curr_page'] < $to ? url("{$url_format}&page=" . ($page['curr_page'] + 1)) : "";
	}
	
	/* 商品排序方式  edit    */
	function _get_orders() {
	    return array(
	        '' => Lang::get('default_order'),
	        'sales' => Lang::get('sales_desc'),
	        'price' => Lang::get('price'),
	        'add_time' => Lang::get('add_time'),
	        'comments' => Lang::get('comment'),
	        'credit_value' => Lang::get('credit_value'),
	        'views' => Lang::get('views')
	    );
	}
	
}