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
        if ($order_info['pd_amount'] != '0.00') {
            if ($member_info['money'] >= $order_info['order_amount']) {
                //有足够的余额，直接进行支付
                $order_model->edit($order_id, array('pd_amount' => $order_info['order_amount']));
                header('Location:index.php?app=account_log&act=payment&order_id=' . $order_id);
            } else {
                //没有足够的余额，则把预付款支付设置为余额设置为最大值
                $order_model->edit($order_id, array('pd_amount' => $member_info['money']));
                $order_info['pd_amount']=$member_info['money'];
            }
        }
        
        $payment_model = & m('payment');
        if (!$order_info['payment_id']) {
            /* 若还没有选择支付方式，则让其选择支付方式 */
            $payments = $payment_model->get_enabled($order_info['seller_id']);
            $this->assign('member_info', $member_info);
            if (empty($payments)) {
                Response::json(404, "订单错误");
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
    
}