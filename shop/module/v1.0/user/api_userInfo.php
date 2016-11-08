<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/member.app.php';


class api_userInfo extends MemberApp{
    
    function getMemberInfo($args_data) {
        
        $user = $this->_get_detail($args_data);
        $user_mod = & m('member');
        $info = $user_mod->get_info($user['user_id']);
        $user['portrait'] = portrait($user['user_id'], $info['portrait'], 'middle');
        $user['ugrade']=$user_mod->get_grade_info($user['user_id']);
        $user['integral'] = $info['integral'];
        $user['total_integral'] = $info['total_integral'];
        $user['money'] = $info['money'];
        $user['money_dj'] = $info['money_dj'];
        
        /* 店铺信用和好评率 */
        if ($user['has_store']) {
            $store_mod = & m('store');
            $store = $store_mod->get_info($user['has_store']);
            $step = intval(Conf::get('upgrade_required'));
            $step < 1 && $step = 5;
            $store['credit_image'] = $this->_view->res_base . '/images/' . $store_mod->compute_credit($store['credit_value'], $step);
        }
        
        $goodsqa_mod = & m('goodsqa');
        $groupbuy_mod = & m('groupbuy');
        /* 买家提醒：待付款、待确认、待评价订单数 */
        $order_mod = & m('order');
        $sql1 = "SELECT COUNT(*) FROM {$order_mod->table} WHERE buyer_id = '{$user['user_id']}' AND status = '" . ORDER_PENDING . "'";
        $sql2 = "SELECT COUNT(*) FROM {$order_mod->table} WHERE buyer_id = '{$user['user_id']}' AND status = '" . ORDER_SHIPPED . "'";
        $sql3 = "SELECT COUNT(*) FROM {$order_mod->table} WHERE buyer_id = '{$user['user_id']}' AND status = '" . ORDER_FINISHED . "' AND evaluation_status = 0";
        $sql4 = "SELECT COUNT(*) FROM {$goodsqa_mod->table} WHERE user_id = '{$user['user_id']}' AND reply_content !='' AND if_new = '1' ";
        $sql5 = "SELECT COUNT(*) FROM " . DB_PREFIX . "groupbuy_log AS log LEFT JOIN {$groupbuy_mod->table} AS gb ON gb.group_id = log.group_id WHERE log.user_id='{$user['user_id']}' AND gb.state = " . GROUP_CANCELED;
        $sql6 = "SELECT COUNT(*) FROM " . DB_PREFIX . "groupbuy_log AS log LEFT JOIN {$groupbuy_mod->table} AS gb ON gb.group_id = log.group_id WHERE log.user_id='{$user['user_id']}' AND gb.state = " . GROUP_FINISHED;
        $sql7 = "SELECT COUNT(*) FROM {$order_mod->table} WHERE buyer_id = '{$user['user_id']}' AND status = '" . ORDER_ACCEPTED . "'";
        $buyer_stat = array(
            'pending' => $order_mod->getOne($sql1),
            'shipped' => $order_mod->getOne($sql2),
            'finished' => $order_mod->getOne($sql3),
            'my_question' => $goodsqa_mod->getOne($sql4),
            'groupbuy_canceled' => $groupbuy_mod->getOne($sql5),
            'groupbuy_finished' => $groupbuy_mod->getOne($sql6),
            'accepted' => $order_mod->getOne($sql7),
        );
        $sum = array_sum($buyer_stat);
        $buyer_stat['sum'] = $sum;
        
        /* 卖家提醒：待处理订单和待发货订单 */
        if ($user['has_store']) {
        
            $sql7 = "SELECT COUNT(*) FROM {$order_mod->table} WHERE seller_id = '{$user['user_id']}' AND status = '" . ORDER_SUBMITTED . "'";
            $sql8 = "SELECT COUNT(*) FROM {$order_mod->table} WHERE seller_id = '{$user['user_id']}' AND status = '" . ORDER_ACCEPTED . "'";
            $sql9 = "SELECT COUNT(*) FROM {$goodsqa_mod->table} WHERE store_id = '{$user['user_id']}' AND reply_content ='' ";
            $sql10 = "SELECT COUNT(*) FROM {$groupbuy_mod->table} WHERE store_id='{$user['user_id']}' AND state = " . GROUP_END;
            $seller_stat = array(
                'submitted' => $order_mod->getOne($sql7),
                'accepted' => $order_mod->getOne($sql8),
                'replied' => $goodsqa_mod->getOne($sql9),
                'groupbuy_end' => $goodsqa_mod->getOne($sql10),
            );
        }
        
        /* 卖家提醒： 店铺等级、有效期、商品数、空间 */
        if ($user['has_store']) {
            $store_mod = & m('store');
            $store = $store_mod->get_info($user['has_store']);
        
            $grade_mod = & m('sgrade');
            $grade = $grade_mod->get_info($store['sgrade']);
        
            $goods_mod = &m('goods');
            $goods_num = $goods_mod->get_count_of_store($user['has_store']);
            $uploadedfile_mod = &m('uploadedfile');
            $space_num = $uploadedfile_mod->get_file_size($user['has_store']);
            $sgrade = array(
                'grade_name' => $grade['grade_name'],
                'add_time' => empty($store['end_time']) ? 0 : sprintf('%.2f', ($store['end_time'] - gmtime()) / 86400),
                'goods' => array(
                    'used' => $goods_num,
                    'total' => $grade['goods_limit']),
                'space' => array(
                    'used' => sprintf("%.2f", floatval($space_num) / (1024 * 1024)),
                    'total' => $grade['space_limit']),
            );
        }
        
        $return = array();
        $return['iconImage'] = 'http://www.biicai.com/'.$user['portrait'];//用户头像
        $return['gradeName'] = $user['ugrade']['grade_name'];//会员等级
        $return['growth'] = $user['ugrade']['growth'];//会员成长值
        $return['integral'] = $user['integral'];//当前积分
        $return['totalIntegral'] = $user['total_integral'];//总积分
        $return['money'] = $user['money'];//可用金额
        $return['moneyDj'] = $user['money_dj'];//冻结金额
        $return['pending'] = $buyer_stat['pending'];//待付款订单
        $return['shipped'] = $buyer_stat['shipped'];//已发货订单
        $return['has_store'] = isset($user['has_store']) ? $user['has_store'] : -1;//商户身份（卖家、买家）
        $return['submitted'] = $seller_stat['submitted'];//待处理订单
        $return['accepted'] = $seller_stat['accepted'];//待发货订单
        $return['storeGrade'] = $sgrade['grade_name'];//店铺等级
        $return['goodsNem'] = $sgrade['goods']['used'];//商品发布
        
        Response::json(200, "获取成功", $return);
        
    }
    
    /**
     *    获取用户详细信息
     *
     *    @author    Garbin
     *    @return    array
     */
    function _get_detail($args_data)
    {
        $model_member =& m('member');
    
        /* 获取当前用户的详细信息，包括权限 */
        $member_info = $model_member->findAll(array(
            'conditions'    => "member.user_id = '{$args_data['userID']}'",
            'join'          => 'has_store',                 //关联查找看看是否有店铺
            'fields'        => 'email, password, real_name, logins, ugrade, portrait, store_id, state, sgrade , feed_config',
            'include'       => array(                       //找出所有该用户管理的店铺
                'manage_store'  =>  array(
                    'fields'    =>  'user_priv.privs, store.store_name',
                ),
            ),
        ));
        $detail = current($member_info);
    
        /* 如果拥有店铺，则默认管理的店铺为自己的店铺，否则需要用户自行指定 */
        if ($detail['store_id'] && $detail['state'] != STORE_APPLYING) // 排除申请中的店铺
        {
            $detail['manage_store'] = $detail['has_store'] = $detail['store_id'];
        }
    
        return $detail;
    }
    
}