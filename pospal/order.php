<?php
require_once("test.php");
define('appId','E984638A36395D19862D8654DA88AB74');
class order{
    //1. 查询支付方式代码
    function order_queryAllPayMethod(){
        $data = order_queryAllPayMethod;
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/ticketOpenApi/queryAllPayMethod";
        $arr = array(
            "appId" => appId,
        );
        $return = pospal_Interface($http, $arr);
        $return = json_decode($return,true);
        error_log(print_r($return,true));
    }
    //2. 根据单据序列号查询
    function order_queryTicketBySn(){
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/ticketOpenApi/queryTicketBySn";
        $arr = array(
            "appId" => appId,
            "sn"=>"201612140803189370001"
        );
        $return = pospal_Interface($http, $arr);
        $return = json_decode($return,true);
        error_log(print_r($return,true));
    }
    //3. 分页查询所有单据
    function order_queryTicketPages(){
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/ticketOpenApi/queryTicketPages";
        $arr = array(
            "appId" => appId,
            "startTime"=> date("Y-m-d 00:00:00",strtotime("-1 day")),
            "endTime"=> date("Y-m-d 23:59:59",strtotime("-1 day")),
//            "postBackParameter"=>array(
//                "parameterType"=> "abcd",
//                "parameterValue"=> "abcd"
//            ),
        );
        $return = pospal_Interface($http, $arr);
        $return = preg_replace('/("cashierUid":)(\d{1,})/i','${1}"${2}"',$return);
        $return = preg_replace('/("productUid":)(\d{1,})/i','${1}"${2}"',$return);
        $return = json_decode($return,true);
        error_log(print_r($return,true));
        if($return['status']==success){

        }
    }
    /*
     *第二次请求
     *当接口返回的值小于等于接口最大的返回值时，需要请求第二次
     */
    function pagesize($return){
        $Rpage = $return['data']['pageSize']; //接口最大返回的数目  100
        $resultlength = count($return['result']); //接口返回的数目  70
        if($resultlength >= $Rpage){
            $this->order_page($return);
        }
    }
    /*
     * 分页处理
     */
    function order_page($return){
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

    }

}
?>