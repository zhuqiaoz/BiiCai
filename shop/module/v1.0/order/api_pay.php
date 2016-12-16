<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/cashier.app.php';

class api_pay extends CashierApp{

    /**
     * 支付页面
     * @param unknown $args_data
     */
    public function payOrder($args_data) {
        /* 外部提供订单号 */
        $order_id = isset($args_data['orderID']) ? intval($args_data['orderID']) : 0;
        if (!$order_id) {
            Response::json(401, "订单不存在");
            return;
        }
        /* 内部根据订单号收银,获取收多少钱，使用哪个支付接口 */
        $order_model = & m('order');
        $order_info = $order_model->get("order_id={$order_id} AND buyer_id=" . $args_data['userID']);
        if (empty($order_info)) {
            Response::json(402, "订单错误");
            return;
        }
        /* 订单有效性判断 */
        if ($order_info['payment_code'] != 'cod' && $order_info['status'] != ORDER_PENDING) {
            Response::json(403, "订单错误");
            return;
        }

        /* 使用余额支付 当用户选择部分预付款支付时 */
        $member_info = &m('member')->get($args_data['userID']);
        if ($order_info['pd_amount'] > 0) {
            if ($member_info['money'] >= $order_info['order_amount']) {
                //有足够的余额，直接进行支付
                $return = array();
                $return['orderID'] = $order_info['order_id'];
                $return['orderSN'] = $order_info['order_sn'];
                $return['paySum'] = $order_info['order_amount'];
                $return['sellerName'] = $order_info['seller_name'];
                $return['balance'] = $order_info['pd_amount'];
                $return['payList'] = array();
                Response::json(200, "获取成功", $return);
            } else {
                //没有足够的余额，则把预付款支付设置为余额设置为最大值
                Response::json(501, "余额不足", $return);
            }
        }

        $payment_model = & m('payment');
        if (!$order_info['payment_id']) {
            /* 若还没有选择支付方式，则让其选择支付方式 */
            $payments = $payment_model->get_enabled($order_info['seller_id']);
            $this->assign('member_info', $member_info);
            if (empty($payments)) {
                Response::json(404, "指定的店铺没有该支付方式");
                return;
            }

            /* 找出配送方式，判断是否可以使用货到付款 */
            $model_extm = & m('orderextm');
            $consignee_info = $model_extm->get($order_id);
            if (!empty($consignee_info)) {
                /* 需要配送方式 */
                $model_shipping = & m('shipping');
                $shipping_info = $model_shipping->get($consignee_info['shipping_id']);
                $cod_regions = unserialize($shipping_info['cod_regions']);
                $cod_usable = true; //默认可用
                if (is_array($cod_regions) && !empty($cod_regions)) {
                    /* 取得支持货到付款地区的所有下级地区 */
                    $all_regions = array();
                    $model_region = & m('region');
                    foreach ($cod_regions as $region_id => $region_name) {
                        $all_regions = array_merge($all_regions, $model_region->get_descendant($region_id));
                    }

                    /* 查看订单中指定的地区是否在可货到付款的地区列表中，如果不在，则不显示货到付款的付款方式 */
                    if (!in_array($consignee_info['region_id'], $all_regions)) {
                        $cod_usable = false;
                    }
                } else {
                    $cod_usable = false;
                }
                if (!$cod_usable) {
                    /* 从列表中去除货到付款的方式 */
                    foreach ($payments as $_id => $_info) {
                        if ($_info['payment_code'] == 'cod') {
                            /* 如果安装并启用了货到付款，则将其从可选列表中去除 */
                            unset($payments[$_id]);
                        }
                    }
                }
            }
            $all_payments = array('online' => array(), 'offline' => array());
            foreach ($payments as $key => $payment) {
                //如果不是在微信端打开屏蔽微信支付方式
                if ($payment['payment_code'] == 'epaywxjs') {
                    if (!is_weixin()) {
                        continue;
                    }
                }
                if ($payment['is_online']) {
                    //                    $all_payments['online'][] = $payment;
                    if ($payment['is_mobile'] == 1) {
                        $all_payments['online'][] = $payment;
                    }
                } else {
                    $all_payments['offline'][] = $payment;
                }
            }

            $return = array();
            $return['orderID'] = $order_info['order_id'];
            $return['orderSN'] = $order_info['order_sn'];
            $return['paySum'] = $order_info['order_amount'];
            $return['sellerName'] = $order_info['seller_name'];
            $return['balance'] = $order_info['pd_amount'];
            $return['payList'] = array();
            foreach ($all_payments['online'] as $payment){
                $info = array();
                $info['payName'] = $payment['payment_name'];
                $info['payID'] = $payment['payment_id'];
                $return['payList'][] = $info;
            }
            Response::json(200, "获取成功", $return);
        }
    }

    /**
     * 支付宝支付
     * @param unknown $args_data
     */
    public function aliPay($args_data) {

        $privateKey = 'MIICXAIBAAKBgQDjIvpH9YxpvTklmkd+AKK5Gy38L+bdDeKWM+o9UKxJjiQ5pLAyPw7Yh9Rqf5TPxagn2FHF21hPEF9f8ZjHmQgcZ47zprwueDU30fT1EdYC0BuqkGE942HJHkCW54hqMwCXfQr9VYAKyx0JOILTnXDchR9WCTp10U3YfWbq3Hi83QIDAQABAoGAE4h6hZ20SZOgSn+ODmP3mnuf5MQp0nDTP5+PUV6SsnCq5Noo3OlXWX+04MPABG43G9Yaki1e1s3Npe6c+O1MKRulZlvqZxFKpURIxZpr1+9OsEWM1T4UYna5tXtqWt5LUUa3MD1bN9OMCK6GIo67p7qWGD69T1XnEHHuqF11jQECQQD+rDARlit42CxViGMNbc5D1nunosBqZPnGz0KUM7C0RZZDkc8chMennCXJeV9d65NbyFOH0rRMGLXYxaMxnaktAkEA5FIMYVUdibuWtWHD1jE6ZoqbjGVV6eydauAmfSB539W7i5NkKNX2VxwKYXbIJipUUwupcJ6IJHCfaxNGwO9QcQJBALebSniTbLoOGEB+OPOIk+oCq1nbk5/hNtcnvBd/AMmnVcNXTxt/ezYS9IdB0wiye6XzUo2c0lH+irRDIPn3ce0CQAEmUJ2k2hM5eJbNOTk44jxl8kaQtBALevdwzYDPyw1PfDRFt7lk6mqh34OCH5vhlq8cXewNQE4+qu7VGAQcsGECQC4V/zSroABALMuVMUPyJgYGT+gZCAWMBLT3DdNK5ybV2zi8fmWMcYC4NTJYoijYVUXlr0ISZvp/aDheK6qh8AY=';
        $order = $this->getDetail($args_data);

        $params = array(
            'service'        => '"'.'mobile.securitypay.pay'.'"',//接口名称
            'partner'        => '"'.'2088121216039472'.'"',//合作伙伴ID
            '_input_charset' => '"'.'utf-8'.'"',//参数编码字符集
            'notify_url'     => '"'.'http://www.biicai.com/notifyalipay.php'.'"', //异步返回
            'out_trade_no'   => '"'.$order['orderSn'].'"',//商品外部交易号，必填（保证唯一性）
            'subject'        => '"'.$this->app_to_string($order['goodsList'][0]['productName']).'"',//门店编号，必填
            'total_fee'      => '"'.$this->app_to_string(sprintf("%.2f",$order['payAmount'])).'"',//商品单价，必填（价格不能为0）
            'seller_id'      => '"'.'admin@biicai.com'.'"',//卖家支付宝用户号
            'payment_type'   => '"'.'1'.'"',
            'body'           => '"'.'订单'.'"', //支付平台 + 订单类型（1、普通订单 2、代付订单 3、充值订单）
        );
        //需要签名的字符串
        $str = $this->createLinkstring($params);
        //生成签名
        $rsa_sign=urlencode($this->rsaSign($str, $privateKey));
        //把签名得到的sign和签名类型sign_type拼接在待签名字符串后面。
        $sign_str = $str.'&sign='.'"'.$rsa_sign.'"'.'&sign_type='.'"'.'RSA'.'"';

        $return = array();
        $return['payInfo'] = $sign_str;
        Response::json(200, "签名成功", $return);
    }

    /**
     * app 格式化string类型的请求以及返回参数
     * @param any $val  		数据
     * @param string $default   数据默认值
     * @return string
     */
    function app_to_string($val,$default=''){
        if (! isset($val)){
            return $default;
        }
        else {
            if ($val == 'null'){
                return $default;
            }

            return strval($val);
        }
    }

    /**
     * @author yushenghai@ebsig.com
     *
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param $para 需要拼接的数组
     * return 拼接完成以后的字符串
     * */
    function createLinkstring($para) {
        $arg  = "";
        while (list ($key, $val) = each ($para)) {
            $arg .= $key.'='.$val.'&';
        }
        //去掉最后一个&字符
        $arg = substr($arg,0,count($arg)-2);

        //如果存在转义字符，那么去掉转义
        if(get_magic_quotes_gpc()){
            $arg = stripslashes($arg);
        }

        return $arg;
    }

    /**
     * RSA签名
     * @param $data 待签名数据
     * @param $private_key 商户私钥字符串
     * return 签名结果
     */
    function rsaSign($data, $private_key) {

        //以下为了初始化私钥，保证在您填写私钥时不管是带格式还是不带格式都可以通过验证。
        $private_key=str_replace("-----BEGIN RSA PRIVATE KEY-----","",$private_key);
        $private_key=str_replace("-----END RSA PRIVATE KEY-----","",$private_key);
        $private_key=str_replace("\n","",$private_key);

        $private_key="-----BEGIN RSA PRIVATE KEY-----".PHP_EOL .wordwrap($private_key, 64, "\n", true). PHP_EOL."-----END RSA PRIVATE KEY-----";

        $res=openssl_pkey_get_private($private_key);

        if($res){
            openssl_sign($data, $sign,$res);
        }else {
            Response::json(401, "您的私钥格式不正确!");
        }
        openssl_free_key($res);
        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * 获取订单详情
     * @param unknown $args_data
     */
    public function getDetail($args_data) {
        $order_id = isset($args_data['orderID']) ? intval($args_data['orderID']) : 0;
        $model_order = & m('order');
        $order_info = $model_order->get(array(
            'fields' => "*, order.add_time as order_add_time",
            'conditions' => "order_id={$order_id} AND buyer_id=" . $args_data['userID'],
            'join' => 'belongs_to_store',
        ));
        if (!$order_info) {
            Response::json(401, "订单信息不存在");
            return;
        }

        /* 团购信息 */
        if ($order_info['extension'] == 'groupbuy') {
            $groupbuy_mod = &m('groupbuy');
            $group = $groupbuy_mod->get(array(
                'join' => 'be_join',
                'conditions' => 'order_id=' . $order_id,
                'fields' => 'gb.group_id',
            ));
        }
        /* 调用相应的订单类型，获取整个订单详情数据 */
        $order_type = & ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
        foreach ($order_detail['data']['goods_list'] as $key => $goods) {
            empty($goods['goods_image']) && $order_detail['data']['goods_list'][$key]['goods_image'] = Conf::get('default_goods_image');
        }

        $return = array();
        $return['orderID'] =  $order_info['order_id'];
        $return['orderSn'] =  $order_info['order_sn'];
        $return['status'] =  $order_info['status'];
        if(isset($order_detail['data']['payment_info'])){
            $return['paymentID'] =  $order_detail['data']['payment_info']['payment_id'];
            $return['paymentCode'] =  $order_detail['data']['payment_info']['payment_code'];
            $return['paymentName'] =  $order_detail['data']['payment_info']['payment_name'];
            $return['paymentDesc'] =  $order_detail['data']['payment_info']['payment_desc'];
        }else{
            $return['paymentID'] = '';
            $return['paymentCode'] = '';
            $return['paymentName'] = '';
            $return['paymentDesc'] = '';
        }
        $return['orderTime'] = !empty($order_info['order_add_time']) ? $this->microtime_format('Y-m-d H:i:s', $order_info['order_add_time']) : '';
        $return['payTime'] =  !empty($order_info['pay_time']) ? $this->microtime_format('Y-m-d H:i:s', $order_info['pay_time']) : '';
        $return['storeName'] =  $order_info['store_name'];
        $return['storeTel'] =  $order_info['tel'];
        $return['ownerAddress'] =  $order_detail['data']['order_extm']['region_name'].$order_detail['data']['order_extm']['address'];
        $return['consignee'] =  $order_detail['data']['order_extm']['consignee'];
        $return['ownerTel'] =  $order_detail['data']['order_extm']['phone_mob'];
        $return['goodsAmount'] =  $order_info['goods_amount'];
        $return['shipName'] =  $order_detail['data']['order_extm']['shipping_name'];
        $return['shipPrice'] =  $order_detail['data']['order_extm']['shipping_fee'];
        $return['discout'] =  $order_info['discount'];
        $return['payAmount'] =  $order_info['order_amount'];
        $goodsList = array();
        foreach ($order_detail['data']['goods_list'] as $goods) {
            $product = array();
            $product['productID'] = $goods['goods_id'];
            $product['productName'] = $goods['goods_name'];
            $product['productImage'] = 'http://www.biicai.com/'.$goods['goods_image'];
            $product['productSpec'] = $goods['specification'];
            $product['productAmount'] = $goods['quantity'];
            $product['productPrice'] = $goods['price'];
            $goodsList[] = $product;
        }
        $return['goodsList'] = $goodsList;

        return $return;
    }

    /** 格式化时间戳，精确到毫秒，x代表毫秒 */

    function microtime_format($tag, $time)
    {
        list($usec, $sec) = explode(".", $time);
        $date = date($tag,$usec);
        return str_replace('x', $sec, $date);
    }

    //余额支付
    public function balance($args_data) {

        /* 外部提供订单号 */
        $order_id = isset($args_data['orderID']) ? intval($args_data['orderID']) : 0;
        if (!$order_id) {
            Response::json(401, "订单信息不存在");
        }
        /* 内部根据订单号收银,获取收多少钱，使用哪个支付接口 */
        $order_model = &m('order');
        $order_info = $order_model->get("order_id={$order_id} AND buyer_id=" . $args_data['userID']);
        if (empty($order_info)) {
            Response::json(402, "订单信息不存在");
        }
        /* 订单有效性判断 */
        if ($order_info['payment_code'] != 'cod' && $order_info['status'] != ORDER_PENDING) {
            Response::json(403, "订单信息不存在");
        }

        $member = &m('member')->get($args_data['userID']);
        if (empty($member['zf_pass'])) {
            $member->edit("user_id=" . $args_data['userID'], array('zf_pass' => md5('123456')));
        }

        $zf_pass = trim($args_data['zf_pass']);
        $post_money = trim($args_data['post_money']); #提交过来的订单金额
        //检测支付密码
        if (empty($zf_pass)) {
            Response::json(404, "支付密码为空");
        }
        $md5zf_pass = md5($zf_pass);
        if ($member['zf_pass'] != $md5zf_pass) {
            Response::json(405, "支付密码错误");
        }
        //检测余额是否足够
        if ($member['money'] < $order_info['order_amount']) {
            Response::json(406, "账户余额不足");
        }
        //金额是否相同
        if ($post_money != $order_info['order_amount']) {
            Response::json(407, "金额数据可疑");
        }

        //读取卖家SQL
        $seller_row = &m('member')->get("user_id=" . $order_info['seller_id']);
        if (empty($seller_row)) {
            Response::json(407, "卖家暂未开通支付，请联系卖家登录");
        }

        $seller_id = $seller_row['user_id']; #卖家ID
        $seller_name = $seller_row['user_name']; #卖家用户名
        $seller_money_dj = $seller_row['money_dj']; #卖家的原始冻结金钱
        //扣除买家的金钱
        $buyer_array = array(
            'money' => $member['money'] - $order_info['order_amount'],
        );
        $member_mod = & m('member');
        $member_mod->edit('user_id=' . $args_data['userID'], $buyer_array);

        //更新卖家的冻结金钱
        $seller_array = array(
            'money_dj' => $seller_money_dj + $order_info['order_amount'],
        );
        $member_mod->edit($seller_id, $seller_array);
        //买家添加日志
        $time = gmtime();
        $buyer_log_text = Lang::get('goumaishangpin_dianzhu') . $seller_name;
        $buyer_add_array = array(
            'user_id' => $args_data['userID'],
            'user_name' => $member['user_name'],
            'order_id' => $order_id,
            'order_sn ' => $order_info['order_sn'],
            'add_time' => $time,
            'type' => ACCOUNT_TYPE_BUY,
            'money_flow' => 'outlay',
            'money' => $order_info['order_amount'],
            'log_text' => $buyer_log_text,
            'states' => 20,
        );
        $_account_log_mod = & m('account_log');
        $_account_log_mod->add($buyer_add_array);
        //卖家添加日志
        $seller_log_text = Lang::get('chushoushangpin_maijia') . $member['user_name'];
        $seller_add_array = array(
            'user_id' => $seller_id,
            'user_name' => $seller_name,
            'order_id' => $order_id,
            'order_sn ' => $order_info['order_sn'],
            'add_time' => $time,
            'type' => ACCOUNT_TYPE_SELLER,
            'money_flow' => 'income',
            'money' => $order_info['order_amount'],
            'log_text' => $seller_log_text,
            'states' => 20,
        );
        $_account_log_mod->add($seller_add_array);
        //改变定单为 已支付等待卖家确认  status10改为20
        $payment_code = "zjgl";
        //更新定单状态
        $order_edit_array = array(
            'payment_name' => Lang::get('zjgl'),
            'payment_code' => $payment_code,
            'pay_time' => $time,
            'status' => ORDER_ACCEPTED, //20就是 待发货了
        );
        $_order_mod = & m('order');
        $_order_mod->edit($order_id, $order_edit_array);

        Response::json(200, "支付成功");
    }



    //银联支付
    function unionpay($args_data){
        $order_id = isset($args_data['orderID']) ? intval($args_data['orderID']) : 0;
        if(!$order_id){
            Response::json(401, "订单不存在");
            return;
        }
        $model_order = & m('order');
        $order_info = $model_order->get(array(
            'fields' => "*, order.add_time as order_add_time",
            'conditions' => "order_id={$order_id} AND buyer_id=" . $args_data['userID'],
            'join' => 'belongs_to_store',
        ));
        $payment = $this->_get_payment(epayunionpay, array());
        $payment_form = $payment->get_payform($order_info);
        if(!$payment_form){
            Response::json(444, "errorepayunionpay");
            return;
        }else{
            Response::json(200, "tn为：",$payment_form);
            return;
        }
    }
}