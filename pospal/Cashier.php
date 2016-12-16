<?php
require_once("test.php");
define('appId','E984638A36395D19862D8654DA88AB74');
class Cashier{
    function Cashier_queryAllCashier(){
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/cashierOpenApi/queryAllCashier";
        $arr = array(
            "appId" => appId,
        );
        error_log(print_r($arr,true));
        $return = pospal_Interface($http, $arr);
        error_log(print_r($return,true));
    }

}
?>