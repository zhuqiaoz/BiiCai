<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/my_address.app.php';


class api_address extends My_addressApp{
    
    /**
     * 获取收货地址
     * @param unknown $args_data
     */
    function getList($args_data) {
        /* 取得列表数据 */
        $model_address =& m('address');
        $addresses = $model_address->find(array(
            'conditions'    => 'user_id = ' . $args_data['userID'],
        ));
        $return_arr = array();
        if($addresses != null && count($addresses) > 0){
            foreach ($addresses as $add){
                $info = array();
                $info['addressID'] = $add['addr_id'];
                $info['consignee'] = $add['consignee'];
                $info['regionName'] = $add['region_name'];
                $info['regionID'] = $add['region_id'];
                $info['address'] = $add['address'];
                $info['zipcode'] = $add['zipcode'];
                $info['phone'] = $add['phone_mob'];
                $return_arr[] = $info;
            }
        }
        $return = array();
        $return['addressList'] = $return_arr;
        Response::json(200, "获取成功", $return);
    }
    
    /**
     * 编辑、添加收货地址
     * @param unknown $args_data
     */
    function editAddress($args_data) {
        //0：新建；1：修改
        if($args_data['type'] == 0){
            $data = array(
                'user_id'       => $args_data['userID'],
                'consignee'     => $args_data['consignee'],
                'region_id'     => $args_data['regionID'],
                'region_name'   => $args_data['regionName'],
                'address'       => $args_data['area'],
                'zipcode'       => $args_data['zipcode'],
                'phone_tel'     => $args_data['phone'],
                'phone_mob'     => $args_data['phone'],
            );
            $model_address =& m('address');
            if (!($address_id = $model_address->add($data)))
            {
                Response::json(400, "新建失败");
                return;
            }
            Response::json(200, "新建成功");
        }else if($args_data['type'] == 1){
            $data = array(
                'consignee'     => $args_data['consignee'],
                'region_id'     => $args_data['regionID'],
                'region_name'   => $args_data['regionName'],
                'address'       => $args_data['area'],
                'zipcode'       => $args_data['zipcode'],
                'phone_tel'     => $args_data['phone'],
                'phone_mob'     => $args_data['phone'],
            );
            $model_address =& m('address');
            $model_address->edit("addr_id = ". $args_data['addressID'] . " AND user_id=" . $args_data['userID'], $data);
            if ($model_address->has_error()){
                Response::json(400, "修改失败");
                return;
            }
            Response::json(200, "修改成功");
        }
    }
    
    /**
     * 获取区域列表
     * @param unknown $args_data
     */
    function getRegion($args_data) {
        $model_region =& m('region');
        $regions = $model_region->get_list($args_data['parentID']);
        $return_arr = array();
        if($regions != null && count($regions) > 0){
            foreach ($regions as $reg){
                $info = array();
                $info['regionID'] = $reg['region_id'];
                $info['regionName'] = $reg['region_name'];
                $info['parentID'] = $reg['parent_id'];
                $return_arr[] = $info;
            }
        }else{
            Response::json(400, "区域信息获取失败");
            return ;
        }
        $return = array();
        $return['regionList'] = $return_arr;
        Response::json(200, "获取成功", $return);
    }
    
    /**
     * 获取地址详情
     * @param unknown $args_data
     */
    function getDetail($args_data) {
        $model_address =& m('address');
        $find_data = $model_address->find("addr_id = ". $args_data['addressID'] ." AND user_id=" . $args_data['userID']);
        if (empty($find_data)){
            Response::json(400, "地址详情获取失败");
            return;
        }
        $address = current($find_data);
        $return = array();
        $return['addressID'] = $address['addr_id'];
        $return['consignee'] = $address['consignee'];
        $return['regionID'] = $address['region_id'];
        $return['regionName'] = $address['region_name'];
        $return['address'] = $address['address'];
        $return['zipcode'] = $address['zipcode'];
        $return['phone'] = $address['phone_mob'];
        Response::json(200, "获取成功", $return);
    }
    
    /**
     * 删除地址
     * @param unknown $args_data
     */
    function deleteAdd($args_data) {
        $model_address  =& m('address');
        $drop_count = $model_address->drop("user_id = " . $args_data['userID'] . " AND addr_id " . db_create_in($args_data['addressID']));
        if (!$drop_count){
            /* 没有可删除的项 */
            Response::json(401, "没有可删除的项");
            return;
        }
        if ($model_address->has_error()) {
           Response::json(402, "删除失败");
            return;
        }
        Response::json(200, "删除成功");
    }
    
} 