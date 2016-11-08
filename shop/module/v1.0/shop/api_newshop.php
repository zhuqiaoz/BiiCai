<?php
require_once APP_ROOT.'/api/shop/module/response.php';

class api_newshop extends MallbaseApp{

    function newshop($args_data){

        $userId = trim($args_data['userID']);//验证是否开过店铺
        $store_mod = & m('store');
        $store = $store_mod->get("store_id='$userId'");
        //error_log(print_r($store,true));
        if($store){
            if($store['state'] == '0'){
                Response::json(301,'您的申请已提交，我们会尽快处理。请留意您的短消息');
            }
            if($store['state'] == '1'){
                Response::json(302,'您已经开过一家店铺了！');
            }
        }else{
            $sgrade_mod = & m('sgrade');
            $sgrades = $sgrade_mod->find(array(
                'order' => 'sort_order',
            ));
            foreach ($sgrades as $key => $sgrade) {
                if (!$sgrade['goods_limit']) {
                    $sgrades[$key]['goods_limit'] = '不限';
                }
                if (!$sgrade['space_limit']) {
                    $sgrades[$key]['space_limit'] = '不限';
                }


        }
            return $sgrades;

        }

    }


    function  button($args_data){
        //带入参数 setp=2，根据Id区分是旗舰店还是普通店铺
        $region_mod = & m('region');
        $this->assign('site_url', site_url());
        $this->assign('regions', $region_mod->get_options(0));
        $this->assign('scategories', $this->_get_scategory_options());
        $userId = trim($args_data['userID']);

        error_log(print_r('===================1111111111111111111===============',true));
        error_log(print_r($userId,true));
        error_log(print_r($this,true));

        $store_mod = & m('store');
        $store_id = $store_mod->get("store_id='$userId'");
        error_log(print_r('===================2222222222222222222===============',true));
        error_log(print_r($store_id,true));

        $sgrade_mod = & m('sgrade');
        $sgrade = $sgrade_mod->find(array(
            'order' => 'sort_order',
        ));
        error_log(print_r('===================33333333333333333===============',true));
        error_log(print_r($sgrade,true));


//        $data = array(
//            'store_id' => $userId,
//            'store_name' => $args_data['store_name'],//店铺名页面第三个
//            'owner_name' => $args_data['owner_name'],//店主名称
//            'owner_card' => $args_data['owner_card'],//身份证号
//            'region_id' => $args_data['region_id'],  //地区ID
//            'region_name' => $args_data['region_name'],//所在地区
//            'address' => $args_data['address'],//详细地址
//            'zipcode' => $args_data['zipcode'],//邮政编码
//            'tel' => $args_data['tel'],//电话
//            'sgrade' => $sgrade['grade_id'],//上一页面选择时带得参数，参数中表示旗舰店还是标准得标准。旗舰店2标准店1
//            'state' => $sgrade['need_confirm'] ? 0 : 1,//开店还是闭点//开店是1闭店是2  申请时默认是0 需要审核得状态
//            'add_time' => gmtime(),
//        );


            $image = $this->_upload_image($store_id);


            if ($this->has_error()) {
                $this->show_warning($this->get_error());
                return;
            }


        /* 判断是否已经申请过 */
        $state = $store_mod->get("store_id='$userId'");

        if ($state['state'] != '' && $state['state'] == STORE_APPLYING) {
            $store_mod->edit($store_id, array_merge($data, $image));
        } else {
            $store_mod->add(array_merge($data, $image));
        }

        if ($store_mod->has_error()) {
            $this->show_warning($store_mod->get_error());
            return;
        }

        $cate_id = intval($_POST['cate_id']);
        $store_mod->unlinkRelation('has_scategory', $store_id);
        if ($cate_id > 0) {
            $store_mod->createRelation('has_scategory', $store_id, $cate_id);
        }

        if ($sgrade['need_confirm']) {
            $this->show_message('apply_ok', 'index', 'index.php');
        } else {
            $this->send_feed('store_created', array(
                'user_id' => $this->visitor->get('user_id'),
                'user_name' => $this->visitor->get('user_name'),
                'store_url' => SITE_URL . '/' . url('app=store&id=' . $store_id),
                'seller_name' => $data['store_name'],
            ));
            $this->_hook('after_opening', array('user_id' => $store_id));

            $this->show_message('store_opened', 'index', 'index.php');
        }


    }



    /* 上传图片 */
    function _upload_image($store_id) {
        import('uploader.lib');
        $uploader = new Uploader();
        $uploader->allowed_type(IMAGE_FILE_TYPE);
        $uploader->allowed_size(SIZE_STORE_CERT); // 400KB

        $data = array();
        for ($i = 1; $i <= 3; $i++) {
            $file = $_FILES['image_' . $i];
            if ($file['error'] == UPLOAD_ERR_OK) {
                if (empty($file)) {
                    continue;
                }
                $uploader->addFile($file);
                if (!$uploader->file_info()) {
                    $this->_error($uploader->get_error());
                    return false;
                }

                $uploader->root_dir(ROOT_PATH);
                $dirname = 'data/files/mall/application';
                $filename = 'store_' . $store_id . '_' . $i;
                $data['image_' . $i] = $uploader->save($dirname, $filename);
            }
        }
        return $data;
    }


    /* 取得店铺分类 */
    function _get_scategory_options() {
        $mod = & m('scategory');
        $scategories = $mod->get_list();
        import('tree.lib');
        $tree = new Tree();
        $tree->setTree($scategories, 'cate_id', 'parent_id', 'cate_name');
        return $tree->getOptions();
    }

}
?>