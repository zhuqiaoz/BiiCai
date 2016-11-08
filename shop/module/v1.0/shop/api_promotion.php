<?php
require_once APP_ROOT.'/api/shop/module/response.php';

class api_promotion extends StoreadminbaseApp{
    //新增促销管理
    var $_goods_mod;
    var $_store_mod;
    var $_spec_mod;
    var $_promotion_mod;

    function  api_promotion(){
//        $this->_store_id = intval($this->visitor->get('manage_store'));
//        $this->_goods_mod = & bm('goods', array('_store_id' => $this->_store_id));
        $this->_spec_mod = & m('goodsspec');
        $this->_promotion_mod = & m('promotion');
    }

   function seller_promotion($args_data){
       if (!empty($args_data['pro_name'])) {
           $conditions = " AND pro.pro_name LIKE '%" . trim($args_data['pro_name']) . "%' ";
       } else {
           $conditions = '';
       }

       $promotion_list = $this->_promotion_mod->find(
           array(
               'join' => 'belong_goods',
               'conditions' => "pro.store_id=" . $args_data['userID'] . $conditions,
               'order' => 'pro.pro_id DESC',
               'fields' => 'pro.*,g.default_image,g.price,g.default_spec',
               'count' => true
           )
       );

       foreach ($promotion_list as $key => $promotion) {
           $promotion['spec_price'] = unserialize($promotion['spec_price']);
           if ($promotion['spec_price'][$promotion['default_spec']]['is_pro'] == 1) {
               if ($promotion['spec_price'][$promotion['default_spec']]['pro_type'] == 'price') { // 这里是计算默认规格的价格
                   $promotion_list[$key]['pro_price'] = round($promotion['price'] - $promotion['spec_price'][$promotion['default_spec']]['price'], 2);
               } else {
                   $promotion_list[$key]['pro_price'] = round($promotion['price'] * $promotion['spec_price'][$promotion['default_spec']]['price'] / 10, 2);
               }
           } else {
               $promotion_list[$key]['pro_price'] = $promotion['price']; // 如果默认规格没有设置促销，则显示原价
           }
       }
       $this->assign('promotion_list', $promotion_list);
       $this->assign('time_now', gmtime());
       Response::json(200, '列表',$promotion_list);
   }

    function add($args_data) {
            /* 检查数据 */
            error_log(print_r($args_data,true));
            if (!$this->_handle_post_data($args_data, 0)) {
                $error_log=show_warning($this->get_error());
                Response::json(200,$error_log);
            }
            $promotion_info = $this->_promotion_mod->get($this->_last_update_id);
            if (true) {
                $_goods_info = $this->_query_goods_info($promotion_info['goods_id']);
                $promotion_url = SITE_URL . '/' . url('app=goods&id=' . $promotion_info['goods_id']);
                $feed_images = array();
                $feed_images[] = array(
                    'url' => SITE_URL . '/' . $_goods_info['default_image'],
                    'link' => $promotion_url,
                );
                $this->send_feed('promotion_created', array(
                    'user_id' => $this->visitor->get('user_id'),
                    'user_name' => $this->visitor->get('user_name'),
                    'promotion_url' => $groupbuy_url,
                    'pro_name' => $promotion_info['pro_name'],
                    'message' => $groupbuy_info['pro_desc'],
                    'images' => $feed_images,
                ));
            }

            //  立即更新
            $cache_server = & cache_server();
            $cache_server->clear();
    }

    function edit($args_data) {
         $id = empty($args_data['id']) ? 0 : $args_data['id'];
            /* 检查数据 */
            if (!$this->_handle_post_data($args_data, $id)) {
                $this->show_warning($this->get_error());
                return;
            }

            //  立即更新
            $cache_server = & cache_server();
            $cache_server->clear();

        Response::json(200, 'OK');
    }

    function drop($args_data) {
        $id = empty($args_data['id']) ? 0 : $args_data['id'];
        if (!$id) {
            Response::json(306, '没有该促销活动');
        }
        if (!$this->_promotion_mod->drop($id)) {
            $error_log=show_warning($this->_promotion_mod->get_error());
            Response::json(307, $error_log);
        }

        Response::json(200, 'OK');
    }

    /**
     * 检查提交的数据
     */
    function _handle_post_data($post, $id = 0) {
        error_log(print_r($post,true));
        if (gmstr2time($post['start_time']) <= gmtime()) {
            $post['start_time'] = gmtime();
        } else {
            $post['start_time'] = gmstr2time($post['start_time']);
        }

        if (intval($post['end_time'])) {
            $post['end_time'] = gmstr2time_end($post['end_time']);
        } else {
            Response::json(301,'请填写结束时间');
        }

        if ($post['end_time'] < $post['start_time']) {
            Response::json(302,'开始时间不能大于结束时间');
        }

        if (($post['goods_id'] = intval($post['goods_id'])) == 0) {
            Response::json(303,'请先搜索促销商品');
        }

        if ($id == 0 && $this->_promotion_mod->get(array('conditions' => 'goods_id=' . $post['goods_id']))) {
            Response::json(304,'该商品已经设置了促销策略，不能重复设置，你可以进入促销列表重新选择编辑。');
        }

        if (empty($post['spec_id']) || !is_array($post['spec_id'])) {
            Response::json(305,'请先勾选促销商品规格');
        }

        $spec_id_yx = array();
        foreach ($post['spec_id'] as $key => $val) {
            if (empty($post['pro_price' . $val])) {
                Response::json(306,'请正确填写减价或折扣值，填写的数值代表，在原价的基础上减少多少价格或者打多少折扣');
            }
            $spec_id_yx[] = $val;
            $spec_price[$val] = array('price' => $post['pro_price' . $val], 'pro_type' => $post['pro_type' . $val], 'is_pro' => 1);
        }

        // 取得所有 spec_id,对未设置的进行处理
        $goods_info = $this->_query_goods_info($post['goods_id']);
        foreach ($goods_info['_specs'] as $spec) {
            if (!in_array($spec['spec_id'], $spec_id_yx)) {
                $spec_price[$spec['spec_id']] = array('is_pro' => 0); // 设置未选中的 spec_id
            }
        }


        $data = array(
            'pro_name' => $post['pro_name'],
            'pro_desc' => $post['pro_desc'],
            'start_time' => $post['start_time'],
            'end_time' => $post['end_time'] - 1,
            'goods_id' => $post['goods_id'],
            'spec_price' => serialize($spec_price),
            'store_id' => $this->_store_id
        );
        if ($id > 0) {
            $this->_promotion_mod->edit($id, $data);
            if ($this->_promotion_mod->has_error()) {
                $this->_error($this->_promotion_mod->get_error());
                return false;
            }
        } else {
            if (!($id = $this->_promotion_mod->add($data))) {
                $this->_error($this->_promotion_mod->get_error());
                return false;
            }
        }
        $this->_last_update_id = $id;

        return true;
    }


    function _query_goods_info($args_data) {
        $goods_id = empty($args_data['goods_id']) ? 0 : intval($args_data['goods_id']);
        if ($goods_id) {
            $goods = $this->_query_goods_info($goods_id);
            $this->json_result($goods);
        }
    }
}
?>