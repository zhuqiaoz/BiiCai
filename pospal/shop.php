<?php
require_once("test.php");
define('appId','E984638A36395D19862D8654DA88AB74');
class shop{
    /*
     * 1. 分页查询全部商品分类
     * 分页内容（商品分类）不会超过最大值pageSize100
     * 不做分页处理
     */
    function shop_queryProductCategoryPages(){
        $data = shop_queryProductCategoryPages;
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/productOpenApi/queryProductCategoryPages";
        $arr = array(
            "appId" => appId,
        );
        $return = pospal_Interface($http, $arr);
        $return =preg_replace('/("Uid":)(\d{1,})/i','${1}"${2}"',$return);
        $return = json_decode($return,true);
        if($return['status']==success){
            $Result = $this->array_pag($data,$return);
            error_log(print_r($Result,true));
        }

    }

    /*
     * 分页内容结果
     */
    function  array_pag($data,$return){
        $arr1 =array();
        $arr2 =array();
        if($data ==shop_queryProductCategoryPages ){
            foreach($return['data']['result'] as $key => $value){
                foreach($value as $key1 => $value1){
                    $arr2[$key1] = $value1;
                }
                $arr1[] = $arr2;
            }
        }
        //if
        return $arr1;
    }



    /*
     * 2. 分页查询全部商品图片
     * 同上
     * 没有分类图片
     */
    function shop_queryProductImagePages(){
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/productOpenApi/queryProductImagePages";
        $arr = array(
            "appId" => appId,
        );
        $return = pospal_Interface($http, $arr);
        $return =preg_replace('/("productUid":)(\d{1,})/i','${1}"${2}"',$return);
        $return = json_decode($return,true);
        error_log(print_r($return,true));
    }
    //3. 根据条形码查询商品信息
    function shop_queryProductByBarcode(){
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/productOpenApi/queryProductByBarcode";
        $arr = array(
            "appId" => appId,
            "barcode" =>"Number"
        );
        $return = pospal_Interface($http, $arr);
    }
    //4. 分页查询全部商品信息
    function shop_queryProductPages(){
        $data = shop_queryProductCategoryPages;
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/productOpenApi/queryProductPages";
        $arr = array(
            "appId" => appId,
        );
        $return = pospal_Interface($http, $arr);
        $return =preg_replace('/("Uid":)(\d{1,})/i','${1}"${2}"',$return);
        $return = json_decode($return,true);
        if($return['status']==success){
            $Result = $this->array_pag($data,$return);
            $relest = $return['data'];
            foreach ($relest as $key => $values) {
                if ($key == postBackParameter) {
                    foreach ($values as $key => $values1) {
                        if ($key == parameterType) {
                            $aaa = $values1;
                        }
                        if ($key == parameterValue) {
                            $bbb = $values1;
                        }
                    }
                }
            }
            $resultlength = count($return['data']['result']);
            if ($resultlength >= $return['pageSize']) {
                $Result = $this->shop_queryProductPages_Page($aaa, $bbb);
            }
        }
    }
    /*
     * shop_queryProductPages_Page
     * 分页内容
     */
    function shop_queryProductPages_Page($aaa, $bbb){
        $data = shop_queryProductCategoryPages;
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/productOpenApi/queryProductPages";
        $arr = array(
            "appId" => appId,
            "postBackParameter" =>array(
                "parameterType"  =>$aaa,
                "parameterValue" =>$bbb
            )
        );
        $return = pospal_Interface($http, $arr);
        $return = preg_replace('/("Uid":)(\d{1,})/i','${1}"${2}"',$return);
        $return = json_decode($return,true);
        if($return['status']==success){
            //$Result = $this->array_pag($data,$return);
            $relest = $return['data'];
            foreach ($relest as $key => $values) {
                if ($key == postBackParameter) {
                    foreach ($values as $key => $values1) {
                        if ($key == parameterType) {
                            $aaa = $values1;
                        }
                        if ($key == parameterValue) {
                            $bbb = $values1;
                        }
                    }
                }
            }
            $resultlength = count($return['data']['result']);
            if ($resultlength >= $return['data']['pageSize']) {
                $Result = $this->shop_queryProductPages_Page($aaa, $bbb);
            }
        }
    }
    //5. 修改商品信息
    function shop_updateProductInfo(){
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/productOpenApi/updateProductInfo";
        $arr = array(
          "appId" =>appId,
            //...
        );
        $return = pospal_Interface($http, $arr);
    }
    //6. 根据唯一标识查询商品信息
    function shop_queryProductByUid(){
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/productOpenApi/queryProductByUid";
        $arr = array(
            "appId" =>appId,
            "productUid"=>"1366004607492564649"
        );
        $return = pospal_Interface($http, $arr);
    }
}
?>