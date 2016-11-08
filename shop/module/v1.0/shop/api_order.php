<?php

require_once APP_ROOT.'/api/shop/module/response.php';

class api_order extends StoreadminbaseApp{
    //将原有的seller_order 文件夹 移动到 shop文件夹下。
    function api_order(){
        $this->_member_mod = & m('member');
        $this->_account_log_mod = & m('account_log');
    }

    //标识发货记录  清除operator_type='buyer'
    function clear_seller_logs($args_data)
    {
        $userId = trim($args_data['userID']);//登陆的用户Id
        $order_log_mod = & m('orderlog');
        $seller_order_log = $order_log_mod->find(
            array(
                'conditions' => "seller_id = '$userId' AND order_log_status = 0 AND operator_type='buyer'",
                'join' => 'belongs_to_order',
            )
        );
        if(!empty($seller_order_log)){
            foreach ($seller_order_log as $key => $order) {
                $data['order_log_status'] = 1;
                $order_log_mod->edit($key, $data);
            }
        }
    }


    function order_seller($args_data){
       $this->clear_seller_logs($args_data);

       $this->auto_confirm_order($args_data);

       /* 获取订单列表 */
       $this->_get_orders($args_data);

   }

    function auto_confirm_order($args_data) {
        //获取当前 已发货的 ORDER_SHIPPED   并且支付方式 payment_code !='bank' AND payment_code !='cod' AND payment_code !='post'    auto_finished_time 大于系统设置的数值
        $model_order = & m('order');
        $conditions = "payment_code !='bank' AND payment_code !='cod' AND payment_code !='post' AND status=" . ORDER_SHIPPED . " AND auto_finished_time<" . gmtime()." AND seller_id=" . $args_data['userID'] ;
        $orders = $model_order->findAll(array(
            'conditions' => $conditions,
        ));
        if (!empty($orders)) {
            foreach ($orders as $key => $order_info) {
                $this->auto_confirm($order_info);
            }
        }
    }

    /**获取订单列表 */
    function _get_orders($args_data) {
        $model_order = & m('order');
        $conditions = '';

        // 团购订单  暂时没又发现需要增加团购Id得选择。以后带修改吧。。。。；。
        if (!empty($args_data['group_id']) && intval($args_data['group_id']) > 0) {
            $groupbuy_mod = &m('groupbuy');
            $order_ids = $groupbuy_mod->get_order_ids(intval($_GET['group_id']));
            $order_ids && $conditions .= ' AND order_alias.order_id' . db_create_in($order_ids);
        }


        //参数包含三个
        /*
         * status <0 代表全部订单，默认显示
         *
         * 待付款传入status = 11
         * 已提交 status = 10
         * 代发货 status = 20
         * 已发货 status = 30
         * 已完成 status = 40
         * 已取消 status = 0
         *
         */
        if($args_data['status']>0){
            $cond=(array(array(
                'field' => 'status',
                'name' => 'type',
                'type' => 'numeric',
            )));
        };

        if($args_data['buyer_name']){
            $cond=(array(array(
                'field'=>'buyer_name',
                'equal'=>'LIKE'
            )));
        };
        if($args_data['order_sn']){
            $cond=(array(array(
                'field' => 'order_sn',
            )));
        };

        $conditions=$this->_get_query_conditions($cond,$args_data);
        /* 查找订单 */
        $orders = $model_order->findAll(array(
            'conditions' => "seller_id=" . $args_data['userID'] . "{$conditions}",
            'count' => true,
            'join' => 'has_orderextm',
            'order' => 'add_time DESC',
            'include' => array(
                'has_ordergoods', //取出商品
            ),
        ));

        $member_mod = & m('member');
        $model_spec = & m('goodsspec');

        $refund_mod =& m('refund');

        foreach ($orders as $key1 => $order) {
            foreach ($order['order_goods'] as $key2 => $goods) {

                //empty($goods['goods_image']) && $orders[$key1]['order_goods'][$key2]['goods_image'] = Conf::get('default_goods_image');

                /* 是否申请过退款 */
                $refund = $refund_mod->get(array('conditions' => 'order_id=' . $goods['order_id'] . ' and goods_id=' . $goods['goods_id'] . ' and spec_id=' . $goods['spec_id'], 'fields' => 'status,order_id'));
                if ($refund) {
                    $orders[$key1]['order_goods'][$key2]['refund_status'] = $refund['status'];
                    $orders[$key1]['order_goods'][$key2]['refund_id'] = $refund['refund_id'];
                }

                $spec = $model_spec->get(array('conditions' => 'spec_id=' . $goods['spec_id'], 'fields' => 'sku'));
                $orders[$key1]['order_goods'][$key2]['sku'] = $spec['sku'];
            }
            $orders[$key1]['goods_quantities'] = count($order['order_goods']);
            $orders[$key1]['buyer_info'] = $member_mod->get(array('conditions' => 'user_id=' . $order['buyer_id'], 'fields' => 'real_name,im_qq,im_aliww,im_msn'));
        }

        $this->assign('orders', $orders);

        Response::json(200,'xiedeshenmena',$orders );
    }


    function auto_confirm($order_info) {
        $order_id = $order_info['order_id'];
        $model_order = & m('order');
        $model_order->edit($order_id, array('status' => ORDER_FINISHED, 'finished_time' => gmtime()));
        /* 记录订单操作日志 */
        $order_log = & m('orderlog');
        $remark = '确认收货超时,系统自动确认收货';
        $order_log->add(array(
            'order_id' => $order_id,
            'operator' => 'system',
            'order_status' => order_status($order_info['status']),
            'changed_status' => order_status(ORDER_FINISHED),
            'remark' => $remark,
            'log_time' => gmtime(),
            'operator_type' => 'buyer',
        ));


        /* 更新定单状态 开始***************************************************** */
        $account_log_row = $this->_account_log_mod->get("order_id='$order_id' and type=" . ACCOUNT_TYPE_BUY);
        $money = $account_log_row['money']; //定单价格
        $sell_user_id = $order_info['seller_id']; //卖家ID
        $buyer_user_id = $account_log_row['user_id']; //买家ID
        if ($account_log_row['order_id'] == $order_id) {

            $sell_money_row = $this->_member_mod->get($sell_user_id);
            $sell_money = $sell_money_row['money']; //卖家的资金
            $sell_money_dj = $sell_money_row['money_dj']; //卖家的冻结资金
            $new_money = $sell_money + $money;
            $new_money_dj = $sell_money_dj - $money;
            //更新数据
            $new_money_array = array(
                'money' => $new_money,
                'money_dj' => $new_money_dj,
            );
            $new_buyer_account_log = array(
                'money' => $money,
                'complete' => 1,
                'states' => 40,
            );
            $new_seller_account_log = array(
                'money' => $money,
                'complete' => 1,
                'states' => 40,
            );
            $this->_member_mod->edit('user_id=' . $sell_user_id, $new_money_array);
            $this->_account_log_mod->edit("order_id={$order_id} AND user_id={$sell_user_id}", $new_seller_account_log);
            $this->_account_log_mod->edit("order_id={$order_id} AND user_id={$buyer_user_id}", $new_buyer_account_log);
        }
        /* 更新定单状态 结束***************************************************** */


        /* 更新累计销售件数 */
        $model_goodsstatistics = & m('goodsstatistics');
        $model_ordergoods = & m('ordergoods');
        $order_goods = $model_ordergoods->find("order_id={$order_id}");
        foreach ($order_goods as $goods) {
            $model_goodsstatistics->edit($goods['goods_id'], "sales=sales+{$goods['quantity']}");
        }

        //为资金管理才可以扣除金额
        if ($order_info['payment_code'] != 'cod' && $order_info['payment_code'] != 'bank' && $order_info['payment_code'] != 'post') {
            /* 交易成功后,推荐者可以获得佣金  BEGIN */
            import('tuijian.lib');
            $tuijian = new tuijian();
            $tuijian->do_tuijian($order_info);
            /* 交易成功后,推荐者可以获得佣金  END */

            /* 用户确认收货后 扣除商城佣金 */
            import('account.lib');
            $account = new account();
            $account->trade_charges($order_info);
        }

        /* 用户确认收货后 获得积分 */
        import('integral.lib');
        $integral = new Integral();
        $integral->change_integral_buy($order_info['buyer_id'], $order_info['goods_amount']);

        //卖家确认收货 发送短信给卖家
        import('mobile_msg.lib');
        $mobile_msg = new Mobile_msg();
        $mobile_msg->send_msg_order($order_info, 'check');
    }





    /**
     *    收到货款
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function received_pay($args_data) {
            //传入得参数 Id、用户、remark（操作原因）、订单号ID
            list($order_id, $order_info) = $this->_get_valid_order_info($args_data,ORDER_PENDING);
            if (!$order_id) {
                Response::json(301, '没有该订单!');
            }
            //分割 前判断 传入得订单号是否正确
            $model_order = & m('order');
            $model_order->edit(intval($order_id), array('status' => ORDER_ACCEPTED, 'pay_time' => gmtime()));

            #TODO 发邮件通知
            /* 记录订单操作日志 */
            $order_log = & m('orderlog');
            $order_log->add(array(
                'order_id' => $order_id,
                'operator' => $args_data['userName'],
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_ACCEPTED),
                'remark' => $args_data['remark'],
                'log_time' => gmtime(),
                'operator_type'=>'seller',
            ));

            /* 发送给买家邮件，提示等待安排发货 */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);

            $mail = get_mail('tobuyer_offline_pay_success_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status' => Lang::get('order_accepted'),
                'actions' => array(
                    'cancel',
                    'shipped'
                ), //可以取消可以发货
            );

        Response::json(200, 'OK');

    }

    /**
     *    获取有效的订单信息
     *
     *    @author    Garbin
     *    @param     array $status
     *    @param     string $ext
     *    @return    array
     */
    function _get_valid_order_info($args_data,$status, $ext = '') {
        $order_id = isset($args_data['order_id']) ? intval($args_data['order_id']) : 0;
        $user_id = isset($args_data['userID']) ? intval($args_data['userID']) : 0;

        if (!$order_id) {
            return array();
        }
        if (!$user_id) {
            return array();
        }
        if (!is_array($status)) {
            $status = array($status);
        }

        if ($ext) {
            $ext = ' AND ' . $ext;
        }

        $model_order = & m('order');
        /* 只有已发货的货到付款订单可以收货 */
        $order_info = $model_order->get(array(
            'conditions' => "order_id={$order_id} AND seller_id={$user_id} AND status " . db_create_in($status) . $ext,
        ));
        if (empty($order_info)) {

            return array();
        }

        return array($order_id, $order_info);
    }

    /**
     *    调整费用
     *
     *    @author    Garbin
     *    @return    void
     */
    function adjust_fee($args_data) {

        //事件1
        list($order_id, $order_info) = $this->_get_valid_order_info($args_data,array(ORDER_SUBMITTED, ORDER_PENDING));
        if (!$order_id) {
            Response::json(301, '没有该订单!');
        }


        //提交事件   //商品总价 shipping_fee   //配送费用 goods_amount  //订单Id
        $model_order = & m('order');
        $model_orderextm = & m('orderextm');
        $shipping_info = $model_orderextm->get($order_id);
            /* 配送费用 */
            $shipping_fee = isset($args_data['shipping_fee']) ? abs(floatval($args_data['shipping_fee'])) : 0;
            /* 折扣金额 */
            $goods_amount = isset($args_data['goods_amount']) ? abs(floatval($args_data['goods_amount'])) : 0;
            /* 订单实际总金额 */
            $order_amount = round($goods_amount + $shipping_fee, 2);
            $data = array(
                'goods_amount' => $goods_amount, //修改商品总价
                'order_amount' => $order_amount, //修改订单实际总金额
                'pay_alter' => 1    //支付变更
            );

            if ($shipping_fee != $shipping_info['shipping_fee']) {
                /* 若运费有变，则修改运费 */

                $model_extm = & m('orderextm');
                $model_extm->edit($order_id, array('shipping_fee' => $shipping_fee));
            }
            $model_order->edit($order_id, $data);

            /* 记录订单操作日志 */
            $order_log = & m('orderlog');
            $order_log->add(array(
                'order_id' => $order_id,
                'operator' => $args_data['userName'],
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status($order_info['status']),
                'remark' => '调整费用',
                'log_time' => gmtime(),
                'operator_type'=>'seller',
            ));

            /* 发送给买家邮件通知，订单金额已改变，等待付款 */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_adjust_fee_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'order_amount' => price_format($order_amount),
            );

        Response::json(200, 'OK');
        }

    /**
     *    取消订单
     *
     *    @author    Garbin
     *    @return    void
     *  还没做好
     */
    function cancel_order($args_data) {
        /* 取消的和完成的订单不能再取消 */
        //list($order_id, $order_info)    = $this->_get_valid_order_info(array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED));
        $order_id = isset($args_data['order_id']) ? trim($args_data['order_id']) : '';
        $user_id = $args_data['userID'];//登陆得useid 就是开店得用户店铺ID
        if (!$order_id) {
            Response::json(301, '没有该订单!');
        }
        $status = array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED);
        $order_ids = explode(',', $order_id);
        if ($ext) {
            $ext = ' AND ' . $ext;
        }

        $model_order = & m('order');
        /* 只有已发货的货到付款订单可以收货 */
        $order_info = $model_order->find(array(
            'conditions' => "order_id" . db_create_in($order_ids) . " AND seller_id= {$user_id} AND status " . db_create_in($status) . $ext,
        ));
        $ids = array_keys($order_info);
        if (!$order_info) {
            Response::json(301, '没有该订单!');
        }
        //查看此订单是否在退款的状态中，如果有退款的状态则卖家不允许取消订单
        $refund_mod = &m('refund');
        $refund = $refund_mod->get('order_id='.$order_id);
        if($refund){
            Response::json(304, '此订单正在申请退款中，不允许取消订单!');
        }
            $model_order = & m('order');
            foreach ($ids as $val) {
                $id = intval($val);
                $model_order->edit($id, array('status' => ORDER_CANCELED));

                /* 更新定单状态 开始**************************************************** */
                //买家的订单记录
                $row_account_log = $this->_account_log_mod->get("order_id='$id' and complete = 0 and type=".ACCOUNT_TYPE_BUY);

                $buy_user_id = $row_account_log['user_id']; //买家ID
                $sell_user_id = $this->visitor->get('manage_store'); //卖家ID
                if(empty($buy_user_id)||empty($sell_user_id)){
                    continue;
                }
                if ($row_account_log['order_id'] == $id) {
                    $temp_order = $model_order->get($id);
                    $money = $temp_order['order_amount']; //定单价格

                    $buy_money_row = $this->_member_mod->get($buy_user_id);
                    $buy_money = $buy_money_row['money']; //买家的钱

                    $sell_money_row = $this->_member_mod->get($sell_user_id);
                    $sell_money = $sell_money_row['money_dj']; //卖家的冻结资金

                    $new_buy_money = $buy_money + $money;
                    $new_sell_money = $sell_money - $money;
                    //更新数据
                    $this->_member_mod->edit($buy_user_id, array('money' => $new_buy_money));
                    $this->_member_mod->edit($sell_user_id, array('money_dj' => $new_sell_money));
                    //更新log为 定单已取消
                    $this->_account_log_mod->edit('order_id=' . $id, array('states' => 0));
                }
                /* 更新定单状态 结束*****************************************************

                  /* 加回订单商品库存 */
                $model_order->change_stock('+', $id);
                $cancel_reason = (!empty($args_data['remark'])) ? $args_data['remark'] : $args_data['cancel_reason'];
                /* 记录订单操作日志 */
                $order_log = & m('orderlog');
                $order_log->add(array(
                    'order_id' => $id,
                    'operator' => $args_data['userName'],
                    'order_status' => order_status($order_info[$id]['status']),
                    'changed_status' => order_status(ORDER_CANCELED),
                    'remark' => $cancel_reason,
                    'log_time' => gmtime(),
                    'operator_type'=>'seller',
                ));

                /* 发送给买家订单取消通知 */
                $model_member = & m('member');
                $buyer_info = $model_member->get($order_info[$id]['buyer_id']);
                $mail = get_mail('tobuyer_cancel_order_notify', array('order' => $order_info[$id], 'reason' => $_POST['remark']));
                $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                $new_data = array(
                    'status' => Lang::get('order_canceled'),
                    'actions' => array(), //取消订单后就不能做任何操作了
                );
            }

        Response::json(200, 'OK');
    }



    /**
     *    查看订单详情
     *
     *    @author    Garbin
     *    @return    void
     */
    function view($args_data) {
        //事件1
        $order_id = isset($args_data['order_id']) ? intval($args_data['order_id']) : 0;
        $user_id = trim($args_data['userID']);
        $model_order = & m('order');
        $order_info = $model_order->findAll(array(
            'conditions' => "order_alias.order_id={$order_id} AND seller_id={$user_id}" ,
            'join' => 'has_orderextm',
        ));
        $order_info = current($order_info);


        if (!$order_info) {
            Response::json(301, '没有该订单!');
        }

        /* 团购信息 */
        if ($order_info['extension'] == 'groupbuy') {
            $groupbuy_mod = &m('groupbuy');
            $group = $groupbuy_mod->get(array(
                'join' => 'be_join',
                'conditions' => 'order_id=' . $order_id,
                'fields' => 'gb.group_id',
            ));
            $this->assign('group_id', $group['group_id']);
        }

        //事件二
        /* 调用相应的订单类型，获取整个订单详情数据 */
        $order_type = & ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
        $spec_ids = array();
        foreach ($order_detail['data']['goods_list'] as $key => $goods) {
            empty($goods['goods_image']) && $order_detail['data']['goods_list'][$key]['goods_image'] = Conf::get('default_goods_image');
            $spec_ids[] = $goods['spec_id'];
        }

        /* 查出最新的相应的货号 */
        $model_spec = & m('goodsspec');
        $spec_info = $model_spec->find(array(
            'conditions' => $spec_ids,
            'fields' => 'sku',
        ));
        foreach ($order_detail['data']['goods_list'] as $key => $goods) {
            $order_detail['data']['goods_list'][$key]['sku'] = $spec_info[$goods['spec_id']]['sku'];
        }

        $this->assign('order', $order_info);
        $this->assign($order_detail['data']);
        error_log(print_r($order_info,true));
        error_log(print_r($order_detail,true));

    }



    /**
     *    待发货的订单发货
     *
     *    @author    Garbin
     *    @return    void
     */
    function shipped($args_data) {
        list($order_id, $order_info) = $this->_get_valid_order_info($args_data,array(ORDER_ACCEPTED, ORDER_SHIPPED));
        if (!$order_id) {
            Response::json(301, '没有该订单!');
        }

        //提交    invoice_no  remark  order_id
        $model_order = & m('order');
            if (!$args_data['invoice_no']) {
                Response::json(302, '请输入发货单号!');
            }
            $edit_data = array('status' => ORDER_SHIPPED, 'invoice_no' => $_POST['invoice_no']);

            //未设置则默认为15天
            $auto_finished_day = intval(Conf::get('auto_finished_day'));
            $auto_finished_day = empty($auto_finished_day) ? 15 : $auto_finished_day;
            $edit_data['auto_finished_time'] = gmtime()+$auto_finished_day*3600*24;

            $is_edit = true;
            if (empty($order_info['invoice_no'])) {
                /* 不是修改发货单号 */
                $edit_data['ship_time'] = gmtime();
                $is_edit = false;
            }


            $model_order->edit(intval($order_id), $edit_data);
            if ($model_order->has_error()) {
                $this->pop_warning($model_order->get_error());

                return;
            }

            #TODO 发邮件通知
            /* 记录订单操作日志 */
            $order_log = & m('orderlog');
            $order_log->add(array(
                'order_id' => $order_id,
                'operator' => $args_data['userName'],
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_SHIPPED),
                'remark' => $args_data['remark'],
                'log_time' => gmtime(),
                'operator_type'=>'seller',
            ));


            /* 发送给买家订单已发货通知 */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);
            $order_info['invoice_no'] = $edit_data['invoice_no'];
            $mail = get_mail('tobuyer_shipped_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));


            //发送短信给买家订单已发货通知
            import('mobile_msg.lib');
            $mobile_msg = new Mobile_msg();
            $mobile_msg->send_msg_order($order_info,'send');


            $new_data = array(
                'status' => Lang::get('order_shipped'),
                'actions' => array(
                    'cancel',
                    'edit_invoice_no'
                ), //可以取消可以发货
            );
            if ($order_info['payment_code'] == 'cod') {
                $new_data['actions'][] = 'finish';
            }

            $this->pop_warning('ok');

    }
}
?>