<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/eccore/ecmall.php';
require_once APP_ROOT . '/eccore/model/model.base.php';

class api_category {
    
	public function getFirstCategory($args_data) {

	    $db = &db();
	    $db->set_mysql_charset('utf-8');

	    $gcategory_mod =& bm('gcategory', array('_store_id' => 0));
	    $gcategories = $gcategory_mod->get_list(-1,true);
	    
	    import('tree.lib');
	    $tree = new Tree();
	    $tree->setTree($gcategories, 'cate_id', 'parent_id', 'cate_name');
	    $data_arr = $tree->getArrayList(0);
	    
	    $return_arr = array();
	    foreach ($data_arr as $data) {
	        $firstGoods = array();
	        $firstGoods['firstID'] = $data['id'];
	        $firstGoods['firstName'] = $data['value'];
	        $child_arr = array();
	        foreach ($data['children'] as $second) {
	            $child = array();
	            $child['secondID'] = $second['id'];
	            $child['secondName'] = $second['value'];
    	        $child_arr[] = $child;
	        }
	        $firstGoods['secondArr'] = $child_arr;
	        $return_arr[] = $firstGoods;
	    }
	    
	    $return = array();
	    $return['firstArr'] = $return_arr;
	    
	    Response::json(200, "返回成功", $return);
	    
	}
}