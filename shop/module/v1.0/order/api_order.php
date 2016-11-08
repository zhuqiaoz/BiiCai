<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/buyer_order.app.php';

class api_order extends Buyer_orderApp{
    
    public function getOrderList($args_data) {
        $page = $this->_get_page($args_data);
        $model_order = & m('order');
        !$args_data['type'] && $args_data['type'] = 'all_orders';
        $con = array(
            array(//按订单状态搜索
                'field' => 'status',
                'name' => 'type',
                'handler' => 'order_status_translator',
            )
        );
        $conditions = $this->_get_query_conditions($args_data, $con);
        /* 查找订单 */
        $orders = $model_order->findAll(array(
            'conditions' => "buyer_id=" . $args_data['userID'] . "{$conditions}",
            'fields' => 'this.*',
            'count' => true,
            'limit' => $page['limit'],
            'order' => 'add_time DESC',
            'include' => array(
                'has_ordergoods', //取出商品
            ),
        ));
        
        $member_mod = & m('member');
        $refund_mod = &m('refund');
        foreach ($orders as $key1 => $order) {
            foreach ($order['order_goods'] as $key2 => $goods) {
                empty($goods['goods_image']) && $orders[$key1]['order_goods'][$key2]['goods_image'] = Conf::get('default_goods_image');
                /* 是否申请过退款 */
                $refund = $refund_mod->get(array('conditions' => 'order_id=' . $goods['order_id'] . ' and goods_id=' . $goods['goods_id'] . ' and spec_id=' . $goods['spec_id'], 'fields' => 'status,order_id'));
                if ($refund) {
                    $orders[$key1]['order_goods'][$key2]['refund_status'] = $refund['status'];
                    $orders[$key1]['order_goods'][$key2]['refund_id'] = $refund['refund_id'];
                }
            }
            $orders[$key1]['goods_quantities'] = count($order['order_goods']);
            $orders[$key1]['seller_info'] = $member_mod->get(array('conditions' => 'user_id=' . $order['seller_id'], 'fields' => 'real_name,im_qq,im_aliww,im_msn'));
        }
        
        $page['item_count'] = $model_order->getCount();
        $this->_format_page($page);
        
        $return = array();
        $return['curPage'] = $page['curr_page'];
        $return['pageCount'] = $page['page_count'];
        $return['count'] = $page['item_count'];
        $return['orderList'] = array();
        
        foreach ($orders as $order){
            $info = array();
            
            $info['orderID'] = $order['order_id'];
            $info['orderSn'] = $order['order_sn'];
            $info['sellerName'] = $order['seller_name'];
            $info['status'] = $order['status'];
            $info['goodsAmount'] = $order['goods_amount'];
            $info['discount'] = $order['discount'];
            $info['orderAmount'] = $order['order_amount'];
            $info['evaluationStatus'] = $order['evaluation_status'];
            $info['goodsList'] = array();
            $goodsArr = $order['order_goods'];
            foreach ($goodsArr as $goods){
                $product = array();
                $product['productID'] = $goods['goods_id'];
                $product['productName'] = $goods['goods_name'];
                $product['productImage'] = 'http://www.biicai.com/'.$goods['goods_image'];
                $product['productSpec'] = $goods['specification'];
                $product['productAmount'] = $goods['quantity'];
                $product['productPrice'] = $goods['price'];
                $info['goodsList'][] = $product;
            }
            $return['orderList'][] = $info;
        }
        
        Response::json(200, "获取成功", $return);
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
    
    /**
     *    获取查询条件
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _get_query_conditions($args_data, $query_item){
        $str = '';
        $query = array();
        foreach ($query_item as $options)
        {
            if (is_string($options))
            {
                $field = $options;
                $options['field'] = $field;
                $options['name']  = $field;
            }
            !isset($options['equal']) && $options['equal'] = '=';
            !isset($options['assoc']) && $options['assoc'] = 'AND';
            !isset($options['type'])  && $options['type']  = 'string';
            !isset($options['name'])  && $options['name']  = $options['field'];
            !isset($options['handler']) && $options['handler'] = 'trim';
            if ($args_data['type'] != 'all')
            {
                $input = $args_data['type'];
                $handler = $options['handler'];
                $value = ($input == '' ? $input : $handler($input));
                if ($value === '' || $value === false)  //若未输入，未选择，或者经过$handler处理失败就跳过
                {
                    continue;
                }
                strtoupper($options['equal']) == 'LIKE' && $value = "%{$value}%";
                if ($options['type'] != 'numeric')
                {
                    $value = "'{$value}'";      //加上单引号，安全第一
                }
                else
                {
                    $value = floatval($value);  //安全起见，将其转换成浮点型
                }
                $str .= " {$options['assoc']} {$options['field']} {$options['equal']} {$value}";
                $query[$options['name']] = $input;
            }
        }
        return $str;
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
        $return['ownerAddress'] =  $order_info['region_name'].$order_info['address'];
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
        Response::json(200, "获取成功", $return);
    }
    
    /** 格式化时间戳，精确到毫秒，x代表毫秒 */
    
    function microtime_format($tag, $time)
    {
        list($usec, $sec) = explode(".", $time);
        $date = date($tag,$usec);
        return str_replace('x', $sec, $date);
    }
    
    /**
     * 取消订单
     * @param unknown $args_data
     */
    public function cancelOrder($args_data) {
        $order_id = isset($args_data['orderID']) ? intval($args_data['orderID']) : 0;
        if (!$order_id) {
            Response::json(401, "订单号不正确");
            return;
        }
        $model_order = & m('order');
        /* 只有待付款的订单可以取消 */
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $args_data['userID'] . " AND status " . db_create_in(array(ORDER_PENDING, ORDER_SUBMITTED)));
        if (empty($order_info)) {
            Response::json(402, "订单不存在");
            return;
        }
        $model_order->edit($order_id, array('status' => ORDER_CANCELED));
        if ($model_order->has_error()) {
            Response::json(403, "订单取消失败");
            return;
        }
        
        /* 加回商品库存 */
        $model_order->change_stock('+', $order_id);
        $cancel_reason = $args_data['cancelReson'];
        /* 记录订单操作日志 */
        $order_log = & m('orderlog');
        $order_log->add(array(
            'order_id' => $order_id,
            'operator' => addslashes($args_data['userName']),
            'order_status' => order_status($order_info['status']),
            'changed_status' => order_status(ORDER_CANCELED),
            'remark' => $cancel_reason,
            'log_time' => gmtime(),
            'operator_type' => 'buyer',
        ));
        
        /* 发送给卖家订单取消通知 */
        $model_member = & m('member');
        $seller_info = $model_member->get($order_info['seller_id']);
        $mail = get_mail('toseller_cancel_order_notify', array('order' => $order_info, 'reason' => $cancel_reason));
        $this->_mailto($seller_info['email'], addslashes($mail['subject']), addslashes($mail['message']));
       
        Response::json(200, "取消成功");
    }
    
    public function evaluatOrder($args_data){
        $order_id = isset($args_data['orderID']) ? intval($args_data['orderID']) : 0;
        if (!$order_id) {
            Response::json(401, "该订单不存在");
            return;
        }
        /* 验证订单有效性 */
        $model_order = & m('order');
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $args_data['userID']);
        if (!$order_info) {
            Response::json(402, "该订单不存在");
            return;
        }
        if ($order_info['status'] != ORDER_FINISHED) {
            /* 不是已完成的订单，无法评价 */
            Response::json(403, "该订单未完成");
            return;
        }
        if ($order_info['evaluation_status'] != 0) {
            /* 已评价的订单 */
            Response::json(404, "该订单已评价");
            return;
        }
        $model_ordergoods = & m('ordergoods');
        
        $req['evaluations'] = array();
        $eval[$order_id]  = array(
            'evaluation' => $args_data['level'],
            'comment' => $args_data['content']
        );
        $req['evaluations'] = $eval;
        
        $evaluations = array();
        /* 写入评价 */
        foreach ($req['evaluations'] as $rec_id => $evaluation) {
            if ($evaluation['evaluation'] <= 0 || $evaluation['evaluation'] > 3) {
                Response::json(405, "评价等级错误");
                return;
            }
            switch ($evaluation['evaluation']) {
                case 3:
                    $credit_value = 1;
                    break;
                case 1:
                    $credit_value = -1;
                    break;
                default:
                    $credit_value = 0;
                    break;
            }
            $evaluations[intval($rec_id)] = array(
                'evaluation' => $evaluation['evaluation'],
                /* 新增 店铺动态评分 begin */
                'evaluation_desc' => in_array($evaluation['evaluation_desc'], array("1", "2", "3", "4", "5",)) ? $evaluation['evaluation_desc'] : 5, #描述相符评分
                'evaluation_service' => in_array($evaluation['evaluation_service'], array("1", "2", "3", "4", "5",)) ? $evaluation['evaluation_service'] : 5, #服务动态评分
                'evaluation_speed' => in_array($evaluation['evaluation_speed'], array("1", "2", "3", "4", "5",)) ? $evaluation['evaluation_speed'] : 5, #发货速度评分
                /* 新增 店铺动态评分 end */
                'comment' => $evaluation['comment'],
                'credit_value' => $credit_value
            );
        }
        $goods_list = $model_ordergoods->find("order_id={$order_id}");
        foreach ($evaluations as $rec_id => $evaluation) {
            $model_ordergoods->edit("rec_id={$rec_id} AND order_id={$order_id}", $evaluation);
            $goods_url = SITE_URL . '/' . url('app=goods&id=' . $goods_list[$rec_id]['goods_id']);
            $goods_name = $goods_list[$rec_id]['goods_name'];
            $this->send_feed('goods_evaluated', array(
                'user_id' => $this->visitor->get('user_id'),
                'user_name' => $this->visitor->get('user_name'),
                'goods_url' => $goods_url,
                'goods_name' => $goods_name,
                'evaluation' => Lang::get('order_eval.' . $evaluation['evaluation']),
                'comment' => $evaluation['comment'],
                'images' => array(
                    array(
                        'url' => SITE_URL . '/' . $goods_list[$rec_id]['goods_image'],
                        'link' => $goods_url,
                    ),
                ),
            ));
        }

        /* 更新订单评价状态 */
        $model_order->edit($order_id, array(
            'evaluation_status' => 1,
            'evaluation_time' => gmtime()
        ));

        /* 更新卖家信用度及好评率 */
        $model_store = & m('store');
        /* 新增店铺动态评分 获取评分 begin */
        import('evaluation.lib');
        $evaluation = new Evaluation();
        $average_score = $evaluation->recount_evaluation_dss($order_info['seller_id']);  #获取的为数组
        $evaluation_desc = $average_score['evaluation_desc'];
        $evaluation_service = $average_score['evaluation_service'];
        $evaluation_speed = $average_score['evaluation_speed'];
        /* 新增店铺动态评分 获取评分 end */

        $model_store->edit($order_info['seller_id'], array(
            'credit_value' => $model_store->recount_credit_value($order_info['seller_id']),
            /* 新增店铺动态评分 获取评分 begin */
            'evaluation_desc' => $evaluation_desc,
            'evaluation_service' => $evaluation_service,
            'evaluation_speed' => $evaluation_speed,
            /* 新增店铺动态评分 获取评分 end */
            'praise_rate' => $model_store->recount_praise_rate($order_info['seller_id'])
        ));

        /* 更新商品评价数 */
        $model_goodsstatistics = & m('goodsstatistics');
        $goods_ids = array();
        foreach ($goods_list as $goods) {
            $goods_ids[] = $goods['goods_id'];
        }
        $model_goodsstatistics->edit($goods_ids, 'comments=comments+1');

        Response::json(200, "评价成功");
    }
    
    public function getEvalutate($args_data){
        $order_id = isset($args_data['orderID']) ? intval($args_data['orderID']) : 0;
        if (!$order_id) {
            Response::json(401, "该订单不存在");
            return;
        }
        /* 验证订单有效性 */
        $model_order = & m('order');
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $args_data['userID']);
        if (!$order_info) {
            Response::json(402, "该订单不存在");
            return;
        }
        if ($order_info['status'] != ORDER_FINISHED) {
            /* 不是已完成的订单，无法评价 */
            Response::json(403, "该订单未完成");
            return;
        }
        if ($order_info['evaluation_status'] != 0) {
            /* 已评价的订单 */
            Response::json(404, "该订单已评价");
            return;
        }
        $model_ordergoods = & m('ordergoods');
        
        $goods_list = $model_ordergoods->find("order_id={$order_id}");
        foreach ($goods_list as $key => $goods) {
            empty($goods['goods_image']) && $goods_list[$key]['goods_image'] = Conf::get('default_goods_image');
        }
        
        $return['goodsList'] = array();
        foreach ($goods_list as $goods) {
            $info = array();
            $info['goodsID'] = $goods['goods_id'];
            $info['goodsImg'] = 'http://www.biicai.com/'.$goods['goods_image'];
            $info['goodsName'] = $goods['goods_name'];
            $info['goodsSpec'] = $goods['specification'];
            $info['goodsAmount'] = $goods['quantity'];
            $info['goodsPrice'] = $goods['price'];
            $info['goodsStore'] = $order_info['seller_name'];
            $return['goodsList'][] = $info;
        }
        
        Response::json(200, "获取评价商品", $return);
    }
    
}