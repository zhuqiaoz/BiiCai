<?php
require_once APP_ROOT.'/api/shop/module/response.php';

class api_promotion extends StoreadminbaseApp{

    var $_goods_mod;
    var $_store_mod;
    var $_spec_mod;
    var $_promotion_mod;

    function  api_promotion(){
        $this->_store_id = intval($this->visitor->get('manage_store'));
        $this->_goods_mod = & bm('goods', array('_store_id' => $this->_store_id));
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

    function add() {
        if (!IS_POST) {

            $goods_mod = &bm('goods', array('_store_id' => $this->_store_id));
            $goods_count = $goods_mod->get_count();
            if ($goods_count == 0) {
                $this->show_warning('has_no_goods', 'add_goods', 'index.php?app=my_goods&act=add');
                return;
            }

            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('promotion_manage'), 'index.php?app=seller_promotion', LANG::get('add_promotion'));

            /* 当前用户中心菜单 */
            $this->_curitem('promotion_manage');
            /* 当前所处子菜单 */
            $this->_curmenu('add_promotion');
            $this->assign('store_id', $this->_store_id);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('add_promotion'));
            $this->_import_resource();
            $this->display('seller_promotion.form.html');
        } else {
            /* 检查数据 */
            if (!$this->_handle_post_data($_POST, 0)) {
                $this->show_warning($this->get_error());
                return;
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

            $this->show_message('add_promotion_ok', 'back_list', 'index.php?app=seller_promotion', 'continue_add', 'index.php?app=seller_promotion&amp;act=add'
            );
        }
    }

    function edit() {
        $id = empty($_GET['id']) ? 0 : $_GET['id'];
        if (!$id) {
            $this->show_warning('no_such_promotion');
            return false;
        }
        if (!IS_POST) {
            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member', LANG::get('promotion_manage'), 'index.php?app=seller_promotion', LANG::get('edit_promotion'));

            /* 当前用户中心菜单 */
            $this->_curitem('promotion_manage');

            /* 当前所处子菜单 */
            $this->_curmenu('edit_promotion');

            /* 促销信息 */
            $promotion = $this->_promotion_mod->get($id);
            $promotion['spec_price'] = unserialize($promotion['spec_price']);
            $goods = $this->_query_goods_info($promotion['goods_id']);
            foreach ($goods['_specs'] as $key => $spec) {
                if (!empty($promotion['spec_price'][$spec['spec_id']])) {
                    $goods['_specs'][$key]['pro_price'] = $promotion['spec_price'][$spec['spec_id']]['price'];
                    $goods['_specs'][$key]['pro_type'] = $promotion['spec_price'][$spec['spec_id']]['pro_type'];
                }
            }
            //print_r($goods['_specs']);
            $this->assign('promotion', $promotion);
            $this->assign('goods', $goods);
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('edit_promotion'));
            $this->_import_resource();
            $this->display('seller_promotion.form.html');
        } else {
            /* 检查数据 */
            if (!$this->_handle_post_data($_POST, $id)) {
                $this->show_warning($this->get_error());
                return;
            }

            //  立即更新
            $cache_server = & cache_server();
            $cache_server->clear();

            $this->show_message('edit_promotion_ok', 'back_list', 'index.php?app=seller_promotion', 'continue_edit', 'index.php?app=seller_promotion&act=edit&id=' . $id
            );
        }
    }

    function drop() {
        $id = empty($_GET['id']) ? 0 : $_GET['id'];
        if (!$id) {
            $this->show_warning('no_such_promotion');
            return false;
        }
        if (!$this->_promotion_mod->drop($id)) {
            $this->show_warning($this->_promotion_mod->get_error());

            return;
        }

        $this->show_message('drop_promotion_successed');
    }
}
?>