<?php

require_once APP_ROOT.'/api/shop/module/response.php';

class api_order extends StoreadminbaseApp{
    //��ԭ�е�seller_order �ļ��� �ƶ��� shop�ļ����¡�
    function api_order(){
        $this->_member_mod = & m('member');
        $this->_account_log_mod = & m('account_log');
    }

    //��ʶ������¼  ���operator_type='buyer'
    function clear_seller_logs($args_data)
    {
        $userId = trim($args_data['userID']);//��½���û�Id
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

       /* ��ȡ�����б� */
       $this->_get_orders($args_data);

   }

    function auto_confirm_order($args_data) {
        //��ȡ��ǰ �ѷ����� ORDER_SHIPPED   ����֧����ʽ payment_code !='bank' AND payment_code !='cod' AND payment_code !='post'    auto_finished_time ����ϵͳ���õ���ֵ
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

    /**��ȡ�����б� */
    function _get_orders($args_data) {
        $model_order = & m('order');
        $conditions = '';

        // �Ź�����  ��ʱû�ַ�����Ҫ�����Ź�Id��ѡ���Ժ���޸İɡ�����������
        if (!empty($args_data['group_id']) && intval($args_data['group_id']) > 0) {
            $groupbuy_mod = &m('groupbuy');
            $order_ids = $groupbuy_mod->get_order_ids(intval($_GET['group_id']));
            $order_ids && $conditions .= ' AND order_alias.order_id' . db_create_in($order_ids);
        }


        //������������
        /*
         * status <0 ����ȫ��������Ĭ����ʾ
         *
         * �������status = 11
         * ���ύ status = 10
         * ������ status = 20
         * �ѷ��� status = 30
         * ����� status = 40
         * ��ȡ�� status = 0
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
        /* ���Ҷ��� */
        $orders = $model_order->findAll(array(
            'conditions' => "seller_id=" . $args_data['userID'] . "{$conditions}",
            'count' => true,
            'join' => 'has_orderextm',
            'order' => 'add_time DESC',
            'include' => array(
                'has_ordergoods', //ȡ����Ʒ
            ),
        ));

        $member_mod = & m('member');
        $model_spec = & m('goodsspec');

        $refund_mod =& m('refund');

        foreach ($orders as $key1 => $order) {
            foreach ($order['order_goods'] as $key2 => $goods) {

                //empty($goods['goods_image']) && $orders[$key1]['order_goods'][$key2]['goods_image'] = Conf::get('default_goods_image');

                /* �Ƿ�������˿� */
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
        /* ��¼����������־ */
        $order_log = & m('orderlog');
        $remark = 'ȷ���ջ���ʱ,ϵͳ�Զ�ȷ���ջ�';
        $order_log->add(array(
            'order_id' => $order_id,
            'operator' => 'system',
            'order_status' => order_status($order_info['status']),
            'changed_status' => order_status(ORDER_FINISHED),
            'remark' => $remark,
            'log_time' => gmtime(),
            'operator_type' => 'buyer',
        ));


        /* ���¶���״̬ ��ʼ***************************************************** */
        $account_log_row = $this->_account_log_mod->get("order_id='$order_id' and type=" . ACCOUNT_TYPE_BUY);
        $money = $account_log_row['money']; //�����۸�
        $sell_user_id = $order_info['seller_id']; //����ID
        $buyer_user_id = $account_log_row['user_id']; //���ID
        if ($account_log_row['order_id'] == $order_id) {

            $sell_money_row = $this->_member_mod->get($sell_user_id);
            $sell_money = $sell_money_row['money']; //���ҵ��ʽ�
            $sell_money_dj = $sell_money_row['money_dj']; //���ҵĶ����ʽ�
            $new_money = $sell_money + $money;
            $new_money_dj = $sell_money_dj - $money;
            //��������
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
        /* ���¶���״̬ ����***************************************************** */


        /* �����ۼ����ۼ��� */
        $model_goodsstatistics = & m('goodsstatistics');
        $model_ordergoods = & m('ordergoods');
        $order_goods = $model_ordergoods->find("order_id={$order_id}");
        foreach ($order_goods as $goods) {
            $model_goodsstatistics->edit($goods['goods_id'], "sales=sales+{$goods['quantity']}");
        }

        //Ϊ�ʽ����ſ��Կ۳����
        if ($order_info['payment_code'] != 'cod' && $order_info['payment_code'] != 'bank' && $order_info['payment_code'] != 'post') {
            /* ���׳ɹ���,�Ƽ��߿��Ի��Ӷ��  BEGIN */
            import('tuijian.lib');
            $tuijian = new tuijian();
            $tuijian->do_tuijian($order_info);
            /* ���׳ɹ���,�Ƽ��߿��Ի��Ӷ��  END */

            /* �û�ȷ���ջ��� �۳��̳�Ӷ�� */
            import('account.lib');
            $account = new account();
            $account->trade_charges($order_info);
        }

        /* �û�ȷ���ջ��� ��û��� */
        import('integral.lib');
        $integral = new Integral();
        $integral->change_integral_buy($order_info['buyer_id'], $order_info['goods_amount']);

        //����ȷ���ջ� ���Ͷ��Ÿ�����
        import('mobile_msg.lib');
        $mobile_msg = new Mobile_msg();
        $mobile_msg->send_msg_order($order_info, 'check');
    }





    /**
     *    �յ�����
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function received_pay($args_data) {
            //����ò��� Id���û���remark������ԭ�򣩡�������ID
            list($order_id, $order_info) = $this->_get_valid_order_info($args_data,ORDER_PENDING);
            if (!$order_id) {
                Response::json(301, 'û�иö���!');
            }
            //�ָ� ǰ�ж� ����ö������Ƿ���ȷ
            $model_order = & m('order');
            $model_order->edit(intval($order_id), array('status' => ORDER_ACCEPTED, 'pay_time' => gmtime()));

            #TODO ���ʼ�֪ͨ
            /* ��¼����������־ */
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

            /* ���͸�����ʼ�����ʾ�ȴ����ŷ��� */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);

            $mail = get_mail('tobuyer_offline_pay_success_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status' => Lang::get('order_accepted'),
                'actions' => array(
                    'cancel',
                    'shipped'
                ), //����ȡ�����Է���
            );

        Response::json(200, 'OK');

    }

    /**
     *    ��ȡ��Ч�Ķ�����Ϣ
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
        /* ֻ���ѷ����Ļ�������������ջ� */
        $order_info = $model_order->get(array(
            'conditions' => "order_id={$order_id} AND seller_id={$user_id} AND status " . db_create_in($status) . $ext,
        ));
        if (empty($order_info)) {

            return array();
        }

        return array($order_id, $order_info);
    }

    /**
     *    ��������
     *
     *    @author    Garbin
     *    @return    void
     */
    function adjust_fee($args_data) {

        //�¼�1
        list($order_id, $order_info) = $this->_get_valid_order_info($args_data,array(ORDER_SUBMITTED, ORDER_PENDING));
        if (!$order_id) {
            Response::json(301, 'û�иö���!');
        }


        //�ύ�¼�   //��Ʒ�ܼ� shipping_fee   //���ͷ��� goods_amount  //����Id
        $model_order = & m('order');
        $model_orderextm = & m('orderextm');
        $shipping_info = $model_orderextm->get($order_id);
            /* ���ͷ��� */
            $shipping_fee = isset($args_data['shipping_fee']) ? abs(floatval($args_data['shipping_fee'])) : 0;
            /* �ۿ۽�� */
            $goods_amount = isset($args_data['goods_amount']) ? abs(floatval($args_data['goods_amount'])) : 0;
            /* ����ʵ���ܽ�� */
            $order_amount = round($goods_amount + $shipping_fee, 2);
            $data = array(
                'goods_amount' => $goods_amount, //�޸���Ʒ�ܼ�
                'order_amount' => $order_amount, //�޸Ķ���ʵ���ܽ��
                'pay_alter' => 1    //֧�����
            );

            if ($shipping_fee != $shipping_info['shipping_fee']) {
                /* ���˷��б䣬���޸��˷� */

                $model_extm = & m('orderextm');
                $model_extm->edit($order_id, array('shipping_fee' => $shipping_fee));
            }
            $model_order->edit($order_id, $data);

            /* ��¼����������־ */
            $order_log = & m('orderlog');
            $order_log->add(array(
                'order_id' => $order_id,
                'operator' => $args_data['userName'],
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status($order_info['status']),
                'remark' => '��������',
                'log_time' => gmtime(),
                'operator_type'=>'seller',
            ));

            /* ���͸�����ʼ�֪ͨ����������Ѹı䣬�ȴ����� */
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
     *    ȡ������
     *
     *    @author    Garbin
     *    @return    void
     *  ��û����
     */
    function cancel_order($args_data) {
        /* ȡ���ĺ���ɵĶ���������ȡ�� */
        //list($order_id, $order_info)    = $this->_get_valid_order_info(array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED));
        $order_id = isset($args_data['order_id']) ? trim($args_data['order_id']) : '';
        $user_id = $args_data['userID'];//��½��useid ���ǿ�����û�����ID
        if (!$order_id) {
            Response::json(301, 'û�иö���!');
        }
        $status = array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED);
        $order_ids = explode(',', $order_id);
        if ($ext) {
            $ext = ' AND ' . $ext;
        }

        $model_order = & m('order');
        /* ֻ���ѷ����Ļ�������������ջ� */
        $order_info = $model_order->find(array(
            'conditions' => "order_id" . db_create_in($order_ids) . " AND seller_id= {$user_id} AND status " . db_create_in($status) . $ext,
        ));
        $ids = array_keys($order_info);
        if (!$order_info) {
            Response::json(301, 'û�иö���!');
        }
        //�鿴�˶����Ƿ����˿��״̬�У�������˿��״̬�����Ҳ�����ȡ������
        $refund_mod = &m('refund');
        $refund = $refund_mod->get('order_id='.$order_id);
        if($refund){
            Response::json(304, '�˶������������˿��У�������ȡ������!');
        }
            $model_order = & m('order');
            foreach ($ids as $val) {
                $id = intval($val);
                $model_order->edit($id, array('status' => ORDER_CANCELED));

                /* ���¶���״̬ ��ʼ**************************************************** */
                //��ҵĶ�����¼
                $row_account_log = $this->_account_log_mod->get("order_id='$id' and complete = 0 and type=".ACCOUNT_TYPE_BUY);

                $buy_user_id = $row_account_log['user_id']; //���ID
                $sell_user_id = $this->visitor->get('manage_store'); //����ID
                if(empty($buy_user_id)||empty($sell_user_id)){
                    continue;
                }
                if ($row_account_log['order_id'] == $id) {
                    $temp_order = $model_order->get($id);
                    $money = $temp_order['order_amount']; //�����۸�

                    $buy_money_row = $this->_member_mod->get($buy_user_id);
                    $buy_money = $buy_money_row['money']; //��ҵ�Ǯ

                    $sell_money_row = $this->_member_mod->get($sell_user_id);
                    $sell_money = $sell_money_row['money_dj']; //���ҵĶ����ʽ�

                    $new_buy_money = $buy_money + $money;
                    $new_sell_money = $sell_money - $money;
                    //��������
                    $this->_member_mod->edit($buy_user_id, array('money' => $new_buy_money));
                    $this->_member_mod->edit($sell_user_id, array('money_dj' => $new_sell_money));
                    //����logΪ ������ȡ��
                    $this->_account_log_mod->edit('order_id=' . $id, array('states' => 0));
                }
                /* ���¶���״̬ ����*****************************************************

                  /* �ӻض�����Ʒ��� */
                $model_order->change_stock('+', $id);
                $cancel_reason = (!empty($args_data['remark'])) ? $args_data['remark'] : $args_data['cancel_reason'];
                /* ��¼����������־ */
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

                /* ���͸���Ҷ���ȡ��֪ͨ */
                $model_member = & m('member');
                $buyer_info = $model_member->get($order_info[$id]['buyer_id']);
                $mail = get_mail('tobuyer_cancel_order_notify', array('order' => $order_info[$id], 'reason' => $_POST['remark']));
                $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                $new_data = array(
                    'status' => Lang::get('order_canceled'),
                    'actions' => array(), //ȡ��������Ͳ������κβ�����
                );
            }

        Response::json(200, 'OK');
    }



    /**
     *    �鿴��������
     *
     *    @author    Garbin
     *    @return    void
     */
    function view($args_data) {
        //�¼�1
        $order_id = isset($args_data['order_id']) ? intval($args_data['order_id']) : 0;
        $user_id = trim($args_data['userID']);
        $model_order = & m('order');
        $order_info = $model_order->findAll(array(
            'conditions' => "order_alias.order_id={$order_id} AND seller_id={$user_id}" ,
            'join' => 'has_orderextm',
        ));
        $order_info = current($order_info);


        if (!$order_info) {
            Response::json(301, 'û�иö���!');
        }

        /* �Ź���Ϣ */
        if ($order_info['extension'] == 'groupbuy') {
            $groupbuy_mod = &m('groupbuy');
            $group = $groupbuy_mod->get(array(
                'join' => 'be_join',
                'conditions' => 'order_id=' . $order_id,
                'fields' => 'gb.group_id',
            ));
            $this->assign('group_id', $group['group_id']);
        }

        //�¼���
        /* ������Ӧ�Ķ������ͣ���ȡ���������������� */
        $order_type = & ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
        $spec_ids = array();
        foreach ($order_detail['data']['goods_list'] as $key => $goods) {
            empty($goods['goods_image']) && $order_detail['data']['goods_list'][$key]['goods_image'] = Conf::get('default_goods_image');
            $spec_ids[] = $goods['spec_id'];
        }

        /* ������µ���Ӧ�Ļ��� */
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
     *    �������Ķ�������
     *
     *    @author    Garbin
     *    @return    void
     */
    function shipped($args_data) {
        list($order_id, $order_info) = $this->_get_valid_order_info($args_data,array(ORDER_ACCEPTED, ORDER_SHIPPED));
        if (!$order_id) {
            Response::json(301, 'û�иö���!');
        }

        //�ύ    invoice_no  remark  order_id
        $model_order = & m('order');
            if (!$args_data['invoice_no']) {
                Response::json(302, '�����뷢������!');
            }
            $edit_data = array('status' => ORDER_SHIPPED, 'invoice_no' => $_POST['invoice_no']);

            //δ������Ĭ��Ϊ15��
            $auto_finished_day = intval(Conf::get('auto_finished_day'));
            $auto_finished_day = empty($auto_finished_day) ? 15 : $auto_finished_day;
            $edit_data['auto_finished_time'] = gmtime()+$auto_finished_day*3600*24;

            $is_edit = true;
            if (empty($order_info['invoice_no'])) {
                /* �����޸ķ������� */
                $edit_data['ship_time'] = gmtime();
                $is_edit = false;
            }


            $model_order->edit(intval($order_id), $edit_data);
            if ($model_order->has_error()) {
                $this->pop_warning($model_order->get_error());

                return;
            }

            #TODO ���ʼ�֪ͨ
            /* ��¼����������־ */
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


            /* ���͸���Ҷ����ѷ���֪ͨ */
            $model_member = & m('member');
            $buyer_info = $model_member->get($order_info['buyer_id']);
            $order_info['invoice_no'] = $edit_data['invoice_no'];
            $mail = get_mail('tobuyer_shipped_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));


            //���Ͷ��Ÿ���Ҷ����ѷ���֪ͨ
            import('mobile_msg.lib');
            $mobile_msg = new Mobile_msg();
            $mobile_msg->send_msg_order($order_info,'send');


            $new_data = array(
                'status' => Lang::get('order_shipped'),
                'actions' => array(
                    'cancel',
                    'edit_invoice_no'
                ), //����ȡ�����Է���
            );
            if ($order_info['payment_code'] == 'cod') {
                $new_data['actions'][] = 'finish';
            }

            $this->pop_warning('ok');

    }
}
?>