<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/order.app.php';

class api_check extends OrderApp{
    
    var $_name = 'normal';
    
    /**
     * 结算中心
     * @param unknown $args_data
     */
    public function checkOrder($args_data) {
        $goods_info = $this->_get_goods_info($args_data);
        if ($goods_info === false) {
            Response::json(403, "结算失败");
            return;
        }
        /*  检查库存 */
        $goods_beyond = $this->_check_beyond_stock($goods_info['items']);
        if ($goods_beyond) {
            Response::json(404, "库存不足");
            return;
        }
        /* 根据商品类型获取对应订单类型 */
        $goods_type = &gt($goods_info['type']);
        $order_type = &ot($goods_info['otype']);
        /* 显示订单表单 */
        $form = $this->get_order_form($goods_info['store_id'], $args_data['userID']);
        if ($form === false) {
            Response::json(405, "结算失败");
            return;
        }
        $return = array();
        $return['storeName'] = $goods_info['store_name'];
        $return['storeID'] = $goods_info['store_id'];
        $return['allow_coupon'] = $goods_info['allow_coupon'];
        $return['integral_enabled'] = $goods_info['integral_enabled'];
        $return['enable_free_fee'] = $goods_info['enable_free_fee'];
        $return['balance'] = $goods_info['amount_for_free_fee'];
        $return['payTotal'] = $goods_info['amount'];
        //配送地址
        if(!empty($form['my_address'])){
            foreach ($form['my_address'] as $address){
                $return['addressID'] = $address['addr_id'];
                $return['consignee'] = $address['consignee'];
                $return['regionID'] = $address['region_id'];
                $return['regionName'] = $address['region_name'];
                $return['address'] = $address['address'];
                $return['zipcode'] = $address['zipcode'];
                $return['phone'] = $address['phone_mob'];
            }
        }else{
            $return['addressID'] = '';
            $return['consignee'] = '';
            $return['regionID'] = '';
            $return['regionName'] = '';
            $return['address'] = '';
            $return['zipcode'] = '';
            $return['phone'] = '';
        }
        //配送方式
        $return['shipList'] = array();
        foreach ($form['shipping_methods'] as $ship){
            $info = array();
            $info['shipID'] = $ship['shipping_id'];
            $info['shipName'] = $ship['shipping_name'];
            $info['shipPrice'] = $ship['first_price'] + $ship['step_price'] * ($goods_info['quantity'] - 1);
            $info['enabled'] = $ship['enabled'];
            $return['shipList'][] = $info;
        }
        //商品列表
        $return['goodsList'] = array();
        foreach ($goods_info['items'] as $goods){
            $info = array();
            $info['productID'] = $goods['goods_id'];
            $info['specID'] = $goods['spec_id'];
            $info['productName'] = $goods['goods_name'];
            $info['productImg'] = 'http://www.biicai.com/'.$goods['goods_image'];
            $info['productPrice'] = $goods['price'];
            $info['productAmount'] = $goods['quantity'];
            $info['productTotal'] = $goods['subtotal'];
            $return['goodsList'][] = $info;
        }
        Response::json(200, "结算中心", $return);
    }
    
    /**
     *    获取外部传递过来的商品
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _get_goods_info($args_data) {
        $return = array(
            'items' => array(), //商品列表
            'quantity' => 0, //商品总量
            'amount' => 0, //商品总价
            'store_id' => 0, //所属店铺
            'store_name' => '', //店铺名称
            'type' => null, //商品类型
            'otype' => 'normal', //订单类型
            'allow_coupon' => true, //是否允许使用优惠券
            'integral_enabled'=> Conf::get('integral_enabled') ? true : false,    // 获取系统是否开启积分
        );
        /* 从购物车中取商品 */
        $store_id = isset($args_data['storeID']) ? intval($args_data['storeID']) : 0;
        if (!$store_id) {
            Response::json(401, "结算失败");
            return;
        }
        $cart_model = & m('cart');
        $cart_items = $cart_model->find(array(
            'conditions' => "user_id = " . $args_data['userID'] . " AND store_id = {$store_id}",
            'join' => 'belongs_to_goodsspec',
            'fields' => 'gs.spec_id,gs.spec_1,gs.spec_2,gs.color_rgb,gs.stock,gs.sku,cart.*' // 不能有 gs.price， 要不读取的不是促销价格，购物车里面才是促销价格
        ));
        if (empty($cart_items)) {
            Response::json(402, "结算商品错误");
            return;
        }
        $store_model = & m('store');
        $store_info = $store_model->get($store_id);
        foreach ($cart_items as $rec_id => $goods) {
            //判断购物车的价格是否有效待完善
            $return['quantity'] += $goods['quantity'];                      //商品总量
            $return['amount'] += $goods['quantity'] * $goods['price'];    //商品总价
            $cart_items[$rec_id]['subtotal'] = $goods['quantity'] * $goods['price'];   //小计
            empty($goods['goods_image']) && $cart_items[$rec_id]['goods_image'] = Conf::get('default_goods_image');
        }
        $return['items'] = $cart_items;
        $return['store_id'] = $store_id;
        $return['store_name'] = $store_info['store_name'];
        $return['store_im_qq'] = $store_info['im_qq']; //
        $return['type'] = 'material';
        $return['enable_free_fee'] = $store_info['enable_free_fee'];
        $return['amount_for_free_fee'] = $store_info['amount_for_free_fee'];
        $return['acount_for_free_fee'] = $store_info['acount_for_free_fee'];
        $return['otype'] = 'normal';
        return $return;
    }
    
    function _check_beyond_stock($goods_items) {
        $goods_beyond_stock = array();
        foreach ($goods_items as $rec_id => $goods) {
            if ($goods['quantity'] > $goods['stock']) {
                $goods_beyond_stock[$goods['spec_id']] = $goods;
            }
        }
        return $goods_beyond_stock;
    }
    
    /**
     * 结算中心
     * @param unknown $args_data
     */
    public function submitOrder($args_data) {
        $goods_info = $this->_get_goods_info($args_data);
        /* 根据商品类型获取对应订单类型 */
        $goods_type = &gt($goods_info['type']);
        $order_type = &ot($goods_info['otype']);
        /* 在此获取生成订单的两个基本要素：用户提交的数据（POST），商品信息（包含商品列表，商品总价，商品总数量，类型），所属店铺 */
        $store_id = isset($args_data['storeID']) ? intval($args_data['storeID']) : 0;
        if ($goods_info === false) {
            /* 购物车是空的 */
            Response::json(401, "购物车是空的");
            return;
        }
        //获取买家信息
        $model_user = & m('member');
        $profile = $model_user->get_info(intval($args_data['userID']));
        $_POST = array();
        $_POST['address_options'] = $args_data['addressID'];
        $_POST['consignee'] = $args_data['consignee'];
        $_POST['phone_tel'] = $args_data['phone'];
        $_POST['phone_mob'] = $args_data['phone'];
        $_POST['region_id'] = $args_data['regionID'];
        $_POST['region_name'] = $args_data['regionName'];
        $_POST['address'] = $args_data['address'];
        $_POST['shipping_id'] = $args_data['shipID'];
        $_POST['coupon_sn'] = $args_data['coupon'];
        $_POST['pd_amount'] = $args_data['balance'];
        $_POST['buyer_id'] = $profile['user_id'];
        $_POST['buyer_name'] = $profile['user_name'];
        $_POST['buyer_email'] = $profile['email'];
        /* 将这些信息传递给订单类型处理类生成订单(你根据我提供的信息生成一张订单) */
        $order_id = $this->submit_order(array(
            'goods_info' => $goods_info, //商品信息（包括列表，总价，总量，所属店铺，类型）,可靠的!
            'post' => $_POST, //用户填写的订单信息
        ));
        if (!$order_id) {
            Response::json(402, "提交订单失败");
            return;
        }
        /* 下单完成后清理商品，如清空购物车，或将团购拍卖的状态转为已下单之类的 */
        $this->_clear_goods($order_id);
        /* 发送邮件 */
        $model_order = & m('order');
        /* 减去商品库存 */
        $model_order->change_stock('-', $order_id);
        /* 获取订单信息 */
        $order_info = $model_order->get($order_id);
        /* 记录订单操作日志 */
        $order_log =& m('orderlog');
        $order_log->add(array(
            'order_id'  => $order_id,
            'operator'  => addslashes($args_data['userName']),
            'order_status' => '',
            'changed_status' => '下订单',
            'remark'    => '买家下单',
            'log_time'  => gmtime(),
            'operator_type'=>'buyer'
        ));
        /* 发送事件 */
        $feed_images = array();
        foreach ($goods_info['items'] as $_gi) {
            $feed_images[] = array(
                'url' => SITE_URL . '/' . $_gi['goods_image'],
                'link' => SITE_URL . '/' . url('app=goods&id=' . $_gi['goods_id']),
            );
        }
        $this->send_feed('order_created', array(
            'user_id' => $args_data['userID'],
            'user_name' => addslashes($args_data['userName']),
            'seller_id' => $order_info['seller_id'],
            'seller_name' => $order_info['seller_name'],
            'store_url' => SITE_URL . '/' . url('app=store&id=' . $order_info['seller_id']),
            'images' => $feed_images,
        ));
        //获取卖家信息
        $model_member = & m('member');
        $seller_info = $model_member->get($goods_info['store_id']);
        $seller_address = $seller_info['email'];
        /* 发送给买家下单通知 */
        $buyer_mail = get_mail('tobuyer_new_order_notify', array('order' => $order_info));
        $this->_mailto($buyer_address, addslashes($buyer_mail['subject']), addslashes($buyer_mail['message']));
        /* 发送给卖家新订单通知 */
        $seller_mail = get_mail('toseller_new_order_notify', array('order' => $order_info));
        $this->_mailto($seller_address, addslashes($seller_mail['subject']), addslashes($seller_mail['message']));
        /* 更新下单次数 */
        $model_goodsstatistics = & m('goodsstatistics');
        $goods_ids = array();
        foreach ($goods_info['items'] as $goods) {
            $goods_ids[] = $goods['goods_id'];
        }
        $model_goodsstatistics->edit($goods_ids, 'orders=orders+1');
        
        /* 订单下完后清空指定购物车 */
        $args_data['storeID'] = isset($args_data['storeID']) ? intval($args_data['storeID']) : 0;
        $store_id = $args_data['storeID'];
        $model_cart = & m('cart');
        $model_cart->drop("store_id = {$store_id} AND user_id='" . $args_data['userID'] . "'");
        //优惠券信息处理
        if (isset($args_data['coupon']) && !empty($args_data['coupon'])) {
            $sn = trim($args_data['coupon']);
            $couponsn_mod = & m('couponsn');
            $couponsn = $couponsn_mod->get("coupon_sn = '{$sn}'");
            if ($couponsn['remain_times'] > 0) {
                $couponsn_mod->edit("coupon_sn = '{$sn}'", "remain_times= remain_times - 1");
            }
        }
        
        $return = array();
        $return['orderID'] = $order_id;
        Response::json(200, "提交订单成功", $return);
    }
    
    /**
     *    提交生成订单，外部告诉我要下的单的商品类型及用户填写的表单数据以及商品数据，我生成好订单后返回订单ID
     *
     *    @author    Garbin
     *    @param     array $data
     *    @return    int
     */
    function submit_order($data)
    {
        /* 释放goods_info和post两个变量 */
        extract($data);
        /* 处理订单基本信息 */
        $base_info = $this->_handle_order_info($goods_info, $post);
        if (!$base_info)
        {
            /* 基本信息验证不通过 */
    
            return 0;
        }
    
        /* 处理订单收货人信息 */
        $consignee_info = $this->_handle_consignee_info($goods_info, $post);
        if (!$consignee_info)
        {
            /* 收货人信息验证不通过 */
            return 0;
        }
    
        /* 至此说明订单的信息都是可靠的，可以开始入库了 */
    
        /* 插入订单基本信息 */
        //订单总实际总金额，可能还会在此减去折扣等费用
    
        //优惠后的商品总额
        $discount=$base_info['goods_amount'] - $base_info['discount'];
        if($discount > 0){
            $base_info['order_amount'] = $base_info['goods_amount'] + $consignee_info['shipping_fee'] - $base_info['discount'];
        }else{ /* 如果优惠金额大于商品总额 */
    
            $base_info['order_amount'] =$consignee_info['shipping_fee'];
            $base_info['discount'] = $base_info['goods_amount'];
        }
        $order_model =& m('order');
        $order_id    = $order_model->add($base_info);
    
        if (!$order_id)
        {
            /* 插入基本信息失败 */
            $this->_error('create_order_failed');
    
            return 0;
        }
    
        /* 插入收货人信息 */
        $consignee_info['order_id'] = $order_id;
        $order_extm_model =& m('orderextm');
        $order_extm_model->add($consignee_info);
    
        /* 插入商品信息 */
        $goods_items = array();
        foreach ($goods_info['items'] as $key => $value)
        {
            $goods_items[] = array(
                'order_id' => $order_id,
                'goods_id' => $value['goods_id'],
                'goods_name' => $value['goods_name'],
                'spec_id' => $value['spec_id'],
                'specification' => $value['specification'],
                'price' => $value['price'],
                'quantity' => $value['quantity'],
                'goods_image' => $value['goods_image'],
                'goods_old_price'=>$value['goods_old_price'],
                'goods_type'=>$value['goods_type'],
                'promotions_id'=>$value['promotions_id'],
            );
        }
        $order_goods_model =& m('ordergoods');
        $order_goods_model->add(addslashes_deep($goods_items)); //防止二次注入
    
        return $order_id;
    }
    
    /**
     *    处理订单基本信息,返回有效的订单信息数组
     *
     *    @author    Garbin
     *    @param     array $goods_info
     *    @param     array $post
     *    @return    array
     */
    function _handle_order_info($goods_info, $post)
    {
        /* 默认都是待付款 */
        $order_status = ORDER_PENDING;
    
        /* 买家信息 */
        $user_id     =  $post['buyer_id'];
        $user_name   =  $post['buyer_name'];
        $email   =  $post['buyer_email'];
    
        /* 返回基本信息 */
        return array(
            'order_sn'      =>  $this->_gen_order_sn(),
            'type'          =>  $goods_info['type'],
            'extension'     =>  $this->_name,
            'seller_id'     =>  $goods_info['store_id'],
            'seller_name'   =>  addslashes($goods_info['store_name']),
            'buyer_id'      =>  $user_id,
            'buyer_name'    =>  addslashes($user_name),
            'buyer_email'   =>  $email,
            'status'        =>  $order_status,
            'add_time'      =>  gmtime(),
            'goods_amount'  =>  $goods_info['amount'],
            'pd_amount'=>isset($post['pd_amount']) ? floatval($post['pd_amount']) : 0.00,
            'discount'      =>  isset($goods_info['discount']) ? $goods_info['discount'] : 0,
            'anonymous'     =>  intval($post['anonymous']),
            'postscript'          =>  trim($post['postscript']),
        );
    }
    
    /**
     *    处理收货人信息，返回有效的收货人信息
     *
     *    @author    Garbin
     *    @param     array $goods_info
     *    @param     array $post
     *    @return    array
     */
    function _handle_consignee_info($goods_info, $post)
    {
        /* 验证收货人信息填写是否完整 */
        $consignee_info = $this->_valid_consignee_info($post);
        if (!$consignee_info)
        {
            return false;
        }
    
        if (!$consignee_info['is_free_fee']) {
            /* 计算配送费用 */
            $shipping_model = & m('shipping');
            $shipping_info = $shipping_model->get("shipping_id={$consignee_info['shipping_id']} AND store_id={$goods_info['store_id']} AND enabled=1");
            if (empty($shipping_info)) {
                $this->_error('no_such_shipping');
    
                return false;
            }
            /* 配送费用=首件费用＋超出的件数*续件费用 */
            $shipping_fee = $shipping_info['first_price'] + ($goods_info['quantity'] - 1) * $shipping_info['step_price'];
        } else {
            $shipping_fee = 0;
            $consignee_info['shipping_id'] = 0;
            $shipping_info['shipping_name'] = '商家包邮';
        }
    
    
        return array(
            'consignee'     =>  $consignee_info['consignee'],
            'region_id'     =>  $consignee_info['region_id'],
            'region_name'   =>  $consignee_info['region_name'],
            'address'       =>  $consignee_info['address'],
            'zipcode'       =>  $consignee_info['zipcode'],
            'phone_tel'     =>  $consignee_info['phone_tel'],
            'phone_mob'     =>  $consignee_info['phone_mob'],
            'shipping_id'   =>  $consignee_info['shipping_id'],
            'shipping_name' =>  addslashes($shipping_info['shipping_name']),
            'shipping_fee'  =>  $shipping_fee,
        );
    }
    
    /**
     *    生成订单号
     *
     *    @author    Garbin
     *    @return    string
     */
    function _gen_order_sn()
    {
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        $timestamp = gmtime();
        $y = date('y', $timestamp);
        $z = date('z', $timestamp);
        $order_sn = $y . str_pad($z, 3, '0', STR_PAD_LEFT) . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
        $model_order =& m('order');
        $orders = $model_order->find('order_sn=' . $order_sn);
        if (empty($orders))
        {
            /* 否则就使用这个订单号 */
            return $order_sn;
        }
    
        /* 如果有重复的，则重新生成 */
        return $this->_gen_order_sn();
    }
    
    /**
     *    验证收货人信息是否合法
     *
     *    @author    Garbin
     *    @param     array $consignee
     *    @return    void
     */
    function _valid_consignee_info($consignee)
    {
        if (!$consignee['consignee'])
        {
            $this->_error('consignee_empty');
    
            return false;
        }
        if (!$consignee['region_id'])
        {
            $this->_error('region_empty');
    
            return false;
        }
        if (!$consignee['address'])
        {
            $this->_error('address_empty');
    
            return false;
        }
        if (!$consignee['phone_tel'] && !$consignee['phone_mob'])
        {
            $this->_error('phone_required');
    
            return false;
        }
    
        if (!$consignee['shipping_id']&& !$consignee['is_free_fee']) {
            $this->_error('shipping_required');
    
            return false;
        }
    
        return $consignee;
    }
    
    /* 显示订单表单 */
    function get_order_form($store_id, $userID)
    {
        $data = array();
        /* 获取我的收货地址 */
        $data['my_address']         = $this->_get_my_address($userID);
        $data['regions']            = $this->_get_regions();
        /* 配送方式 */
        $data['shipping_methods']   = $this->_get_shipping_methods($store_id);
        foreach ($data['shipping_methods'] as $shipping)
        {
            $data['shipping_options'][$shipping['shipping_id']] = $shipping['shipping_name'];
        }
        return $data;
    }
    
    /**
     *    获取收货人信息
     *
     *    @author    Garbin
     *    @param     int $user_id
     *    @return    array
     */
    function _get_my_address($user_id)
    {
        if (!$user_id)
        {
            return array();
        }
        $address_model =& m('address');
    
        return $address_model->find('user_id=' . $user_id);
    }
    
    /**
     *    获取配送方式
     *
     *    @author    Garbin
     *    @param     int $store_id
     *    @return    array
     */
    function _get_shipping_methods($store_id)
    {
        if (!$store_id)
        {
            return array();
        }
        $shipping_model =& m('shipping');
    
        return $shipping_model->find('enabled=1 AND store_id=' . $store_id);
    }
    
    /**
     *    获取一级地区
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _get_regions()
    {
        $model_region =& m('region');
        $regions = $model_region->get_list(0);
        if ($regions)
        {
            $tmp  = array();
            foreach ($regions as $key => $value)
            {
                $tmp[$key] = $value['region_name'];
            }
            $regions = $tmp;
        }
    
        return $regions;
    }
    
}