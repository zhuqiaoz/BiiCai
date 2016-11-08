<?php
require_once APP_ROOT.'/api/shop/module/response.php';

class api_shipping extends StoreadminbaseApp{

    function shipping($args_data){
        /* 取得列表数据 */
        $store_id=trim($args_data['userID']);
        $model_shipping =& m('shipping');
        $shippings     = $model_shipping->find(array(
            'conditions'    => 'store_id = '.$store_id,
        ));
        $this->assign('shippings', $shippings);
        Response::json(200, '列表',$shippings);

    }

    /**
     *    新增配送方式
     *
     *    @author    Garbin
     *    @return    void
     */
    function add($args_data)
    {
            $data = array(
                'store_id'      => $args_data['userID'],
                'shipping_name' => $args_data['shipping_name'], //名称
                'shipping_desc' => $args_data['shipping_desc'],//简介
                'first_price'   => $args_data['first_price'],//首件邮费
                'step_price'    => $args_data['step_price'],//附加邮费
                'enabled'       => $args_data['enabled'],//单选项 1 ，0
                'sort_order'    => $args_data['sort_order'], //排序
            );
            if (!empty($args_data['cod_regions'])) //添加可货到付款的地区
            {
                $data['cod_regions']    =   serialize($args_data['cod_regions']);
            }
            $model_shipping =& m('shipping');
            if (!($shipping_id = $model_shipping->add($data)))
            {
                $error=$this->pop_warning($model_shipping->get_error());
                Response::json(301, '错误:',$error);
            }
            Response::json(200, 'OK');

    }

    /**
     *    编辑配送方式
     *
     *    @author    Garbin
     *    @return    void
     */
    function edit($args_data)
    {
        $shipping_id = isset($args_data['shipping_id']) ? intval($args_data['shipping_id']) : 0;
        if (!$shipping_id)
        {
            Response::json(302, '没有指定的配送方式');
        }
        /* 判断是否是自己的 */
        $model_shipping =& m('shipping');
        $shipping = $model_shipping->get("store_id=" . $args_data['userID'] . " AND shipping_id={$shipping_id}");
        if (!$shipping)
        {
            Response::json(303, '没有指定的配送方式');
        }

        //提交事件 222
            $data = array(
                'shipping_name' => $args_data['shipping_name'],
                'shipping_desc' => $args_data['shipping_desc'],
                'first_price'   => $args_data['first_price'],
                'step_price'    => $args_data['step_price'],
                'enabled'       => $args_data['enabled'],
                'sort_order'    => $args_data['sort_order'],
            );
            $cod_regions = empty($args_data['cod_regions']) ? array() : $args_data['cod_regions'];
            $data['cod_regions']    =   serialize($cod_regions);
            $model_shipping =& m('shipping');
            $model_shipping->edit($shipping_id, $data);
            if ($model_shipping->has_error())
            {
                $msg = $model_shipping->get_error();
                Response::json(304, $msg['msg']);
            }
            Response::json(200, 'OK');

    }

    /**
     *    删除配送方式
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function drop($args_data)
    {
        $shipping_id = isset($args_data['shipping_id']) ? trim($args_data['shipping_id']) : 0;
        if (!$shipping_id)
        {
            Response::json(305, '没有指定的配送方式');
        }
        $ids = explode(',', $shipping_id);//获取一个类似array(1, 2, 3)的数组
        $model_shipping  =& m('shipping');
        $drop_count = $model_shipping->drop("store_id = " . $args_data['userID'] . " AND shipping_id " . db_create_in($ids));
        if (!$drop_count)
        {
            Response::json(306, '没有该配送方式');
        }

        if ($model_shipping->has_error())    //出错了
        {
            $error_msg=$this->show_warning($model_shipping->get_error());
            Response::json(306, '错误',$error_msg);

        }

        Response::json(200, 'OK');
    }
}

?>

