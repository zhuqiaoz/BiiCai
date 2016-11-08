<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/cart.app.php';

class api_cart extends CartApp{
    
    /**
     * 获取购物车
     * @param unknown $args_data
     */
    public function getCart($args_data){
        /* 返回JSON结果 */
        $cart_status = $this->_get_cart_status($args_data);
        $return['storeList'] = array();
        foreach ($cart_status['carts'] as $car){
            $info = array();
            $info['storeName'] = $car['store_name'];
            $info['storePrice'] = $car['amount'];
            $info['goodsList'] = array();
            foreach ($car['goods'] as $goods){
                $pro = array();
                $pro['productID'] = $goods['goods_id'];
                $pro['specID'] = $goods['spec_id'];
                $pro['productName'] = $goods['goods_name'];
                $pro['productImg'] = 'http://www.biicai.com/'.$goods['goods_image'];
                $pro['productPrice'] = $goods['price'];
                $pro['productAmount'] = $goods['quantity'];
                $info['goodsList'][] = $pro;
                $info['storeID'] = $goods['store_id'];
            }
            $return['storeList'][] = $info;
        }
        
        Response::json(200, "获取成功", $return);
    }
    
    /**
     * 加入购物车
     * @param unknown $args_data
     */
    public function addCart($args_data){
        $spec_id = isset($args_data['productID']) ? intval($args_data['productID']) : 0;
        $quantity = isset($args_data['amount']) ? intval($args_data['amount']) : 0;
        if (!$spec_id || !$quantity) {
            return;
        }
        
        /* 是否有商品 */
        $spec_model = & m('goodsspec');
        $spec_info = $spec_model->get(array(
            'fields' => 'g.store_id, g.if_open, g.goods_id, g.goods_name, g.spec_name_1, g.spec_name_2, g.default_image, gs.spec_1, gs.spec_2, gs.stock, gs.price,ling',
            'conditions' => $spec_id,
            'join' => 'belongs_to_goods',
        ));
        if (!$spec_info) {
            Response::json(401, "商品不存在");
            return;
        }
        /* 是否添加过 */
        $cart_mod = & m('cart');
        $item_info = $cart_mod->get("spec_id={$spec_id} AND user_id= " . $args_data['userID']);
        if (!empty($item_info)) {
            $args_data['amount'] = $item_info['quantity'] + $args_data['amount'];
            $this->update($args_data);
            return;
        }
        if ($quantity > $spec_info['stock']) {
            Response::json(403, "库存不足");
            return;
        }
        $spec_1 = $spec_info['spec_name_1'] ? $spec_info['spec_name_1'] . ':' . $spec_info['spec_1'] : $spec_info['spec_1'];
        $spec_2 = $spec_info['spec_name_2'] ? $spec_info['spec_name_2'] . ':' . $spec_info['spec_2'] : $spec_info['spec_2'];
        $specification = $spec_1 . ' ' . $spec_2;
        //当用多个促销条件存在时候， 此方法用来处理具体使用哪一个价格
        $spec_info = $cart_mod->get_spec_price($spec_info, $args_data['userID'], 0);
        //零元购商品购买数量限制为1
        if($spec_info['ling']== '1'){
            //临时解决不能取到user_id的问题。购物前先登录
            if ($args_data['userID'] == 0) {
                Response::json(404, "请先登录");
                return;
            };
            $where_user_id = $args_data['userID'];
            $ling_mod = &m('member');
            $member_ling = $ling_mod ->get(array(
                'conditions' => 'user_id= \''.$where_user_id ."'",
                'fields' => 'buy_ling'
            ));
            //限购一件当前用户购买时
            if($quantity >'1'){
                Response::json(405, "零元购:每个用户限购一件");
                return;
            };
            //购买过的人提示。
            if($member_ling['buy_ling'] >= '1'){
                Response::json(406, "零元购:每个用户限购一件");
                return;
            };
            //新增用户表，添加购买过的标准
            $model_buy_lings =& m('member');
            $model_buy_lings->edit($where_user_id, 'buy_ling=1');
        };
        /* 将商品加入购物车 */
        $cart_item = array(
            'user_id' => $args_data['userID'],
            'session_id' => $args_data['sessionID'],
            'store_id' => $spec_info['store_id'],
            'spec_id' => $spec_id,
            'goods_id' => $spec_info['goods_id'],
            'goods_name' => addslashes($spec_info['goods_name']),
            'specification' => addslashes(trim($specification)),
            'price' => $spec_info['price'],
            'quantity' => $quantity,
            'goods_image' => addslashes($spec_info['default_image']),
            'promotions_id' => $spec_info['promotions_id'],
            'goods_old_price' => $spec_info['goods_old_price'],
            'goods_type' => $spec_info['goods_type'],
        );
        /* 添加并返回购物车统计即可 */
        $cart_model = & m('cart');
        $cart_model->add($cart_item);
        $cart_status = $this->_get_cart_status($args_data);
        /* 更新被添加进购物车的次数 */
        $model_goodsstatistics = & m('goodsstatistics');
        $model_goodsstatistics->edit($spec_info['goods_id'], 'carts=carts+1');
        
        $return['storeList'] = array();
        foreach ($cart_status['carts'] as $car){
            $info = array();
            $info['storeName'] = $car['store_name'];
            $info['storePrice'] = $car['amount'];
            $info['goodsList'] = array();
            foreach ($car['goods'] as $goods){
                $pro = array();
                $pro['productID'] = $goods['goods_id'];
                $pro['specID'] = $goods['spec_id'];
                $pro['productName'] = $goods['goods_name'];
                $pro['productImg'] = 'http://www.biicai.com/'.$goods['goods_image'];
                $pro['productPrice'] = $goods['price'];
                $pro['productAmount'] = $goods['quantity'];
                $info['goodsList'][] = $pro;
                $info['storeID'] = $goods['store_id'];
            }
            $return['storeList'][] = $info;
        }
        
        Response::json(200, "添加成功", $return);
    }
    
    /**
     *    获取购物车状态
     *
     *    @author    Garbin
     *    @return    array
     */
    function _get_cart_status($args_data) {
        /* 默认的返回格式 */
        $data = array(
            'status' => array(
                'quantity' => 0, //总数量
                'amount' => 0, //总金额
                'kinds' => 0, //总种类
            ),
            'carts' => array(), //购物车列表，包含每个购物车的状态
        );
    
        /* 获取所有购物车 */
        $carts = $this->_get_carts($args_data);
        if (empty($carts)) {
            return $data;
        }
        $data['carts'] = $carts;
        foreach ($carts as $store_id => $cart) {
            $data['status']['quantity'] += $cart['quantity'];
            $data['status']['amount'] += $cart['amount'];
            $data['status']['kinds'] += $cart['kinds'];
        }
        return $data;
    }
    
    
    /**
     *    以购物车为单位获取购物车列表及商品项
     *
     *    @author    Garbin
     *    @return    void
     */
    function _get_carts($args_data) {
        $store_id = isset($args_data['storeID']) ? intval($args_data['storeID']) : 0;
        $carts = array();
        /* 获取所有购物车中的内容 */
        $where_store_id = $store_id ? ' AND cart.store_id=' . $store_id : '';
        /* 只有是自己购物车的项目才能购买 */
        $where_user_id = isset($args_data['userID']) ? " cart.user_id=" . $args_data['userID'] : '';
        $cart_model = & m('cart');
        $cart_items = $cart_model->find(array(
            'conditions' => $where_user_id,
            'fields' => 'this.*,store.store_name',
            'join' => 'belongs_to_store',
        ));
        if (empty($cart_items)) {
            Response::json(401, "购物车为空");
            return;
        }
        $kinds = array();
        foreach ($cart_items as $item) {
            /* 小计 */
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $kinds[$item['store_id']][$item['goods_id']] = 1;
        
            /* 以店铺ID为索引 */
            empty($item['goods_image']) && $item['goods_image'] = Conf::get('default_goods_image');
            $carts[$item['store_id']]['store_name'] = $item['store_name'];
            $carts[$item['store_id']]['amount'] += $item['subtotal'];   //各店铺的总金额
            $carts[$item['store_id']]['quantity'] += $item['quantity'];   //各店铺的总数量
            $carts[$item['store_id']]['goods'][] = $item;
        }
        
        foreach ($carts as $_store_id => $cart) {
            $carts[$_store_id]['kinds'] = count(array_keys($kinds[$_store_id]));  //各店铺的商品种类数
        }
        return $carts;
    }
    
    /**
     *    更新购物车中商品的数量，以商品为单位，AJAX更新
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function update($args_data) {
        $spec_id = isset($args_data['productID']) ? intval($args_data['productID']) : 0;
        $quantity = isset($args_data['amount']) ? intval($args_data['amount']) : 0;
        if (!$spec_id || !$quantity) {
            Response::json(407, "参数错误");
            return;
        }
        /* 判断库存是否足够 */
        $spec_mod = & m('goodsspec');
        $spec_ling  =  $spec_mod->get(array(
            'fields' => 'g.store_id, g.if_open, g.goods_id, g.goods_name, g.spec_name_1, g.spec_name_2, g.default_image, gs.spec_1, gs.spec_2, gs.stock, gs.price,g.ling',
            'conditions'    => $spec_id,
            'join'          => 'belongs_to_goods',
        ));
        $spec_info = $spec_mod->get($spec_id);
        if($spec_ling['ling']== 1){
            if($quantity >'1'){
                Response::json(408, "零元购:每个用户限购一件");
                return;
            }
        };
        if (empty($spec_info)) {
            Response::json(408, "没有该规格");
            return;
        }
        if ($quantity > $spec_info['stock']) {
            Response::json(409, "库存不足");
            return;
        }
        /* 修改数量 */
        $where = "spec_id={$spec_id} AND user_id= " . $args_data['userID'];
        $cart_mod = & m('cart');
        /* 获取购物车中的信息，用于获取价格并计算小计 */
        $cart_spec_info = $cart_mod->get($where);
        if (empty($cart_spec_info)) {
            Response::json(410, "商品未加入到购物车");
            return;
        }
        $store_id = $cart_spec_info['store_id'];
        /* 修改数量 */
        $cart_mod->edit($where, array(
            'quantity' => $quantity,
        ));
        /* 小计 */
        $subtotal = $quantity * $cart_spec_info['price'];
        /* 返回JSON结果 */
        $cart_status = $this->_get_cart_status($args_data);
        
        $return['storeList'] = array();
        foreach ($cart_status['carts'] as $car){
            $info = array();
            $info['storeName'] = $car['store_name'];
            $info['storePrice'] = $car['amount'];
            $info['goodsList'] = array();
            foreach ($car['goods'] as $goods){
                $pro = array();
                $pro['productID'] = $goods['goods_id'];
                $pro['specID'] = $goods['spec_id'];
                $pro['productName'] = $goods['goods_name'];
                $pro['productImg'] = 'http://www.biicai.com/'.$goods['goods_image'];
                $pro['productPrice'] = $goods['price'];
                $pro['productAmount'] = $goods['quantity'];
                $info['goodsList'][] = $pro;
                $info['storeID'] = $goods['store_id'];
            }
            $return['storeList'][] = $info;
        }
        
        Response::json(200, "修改成功", $return);
    }
    
    /**
     * 删除购物车
     * @param unknown $args_data
     */
    function delete($args_data) {
        /* 从购物车中删除 */
        $cart_mod = & m('cart');
        $droped_rows = $cart_mod->drop("spec_id = ". $args_data['productID']." AND user_id= " . $args_data['userID']);
        if (!$droped_rows) {
            Response::json(401, "删除失败");
            return;
        }
        /* 返回JSON结果 */
        $cart_status = $this->_get_cart_status($args_data);
    
        $return['storeList'] = array();
        foreach ($cart_status['carts'] as $car){
            $info = array();
            $info['storeName'] = $car['store_name'];
            $info['storePrice'] = $car['amount'];
            $info['goodsList'] = array();
            foreach ($car['goods'] as $goods){
                $pro = array();
                $pro['productID'] = $goods['goods_id'];
                $pro['specID'] = $goods['spec_id'];
                $pro['productName'] = $goods['goods_name'];
                $pro['productImg'] = 'http://www.biicai.com/'.$goods['goods_image'];
                $pro['productPrice'] = $goods['price'];
                $pro['productAmount'] = $goods['quantity'];
                $info['goodsList'][] = $pro;
                $info['storeID'] = $goods['store_id'];
            }
            $return['storeList'][] = $info;
        }
    
        Response::json(200, "删除成功", $return);
    }
    
}