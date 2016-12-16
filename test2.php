<?php

//$Result = array(
//    "status"=>"success",
//    "messages"=>array(),
//    "data"=>array(
//        "postBackParameter"=>array("parameterType"=>LAST_RESULT_MAX_ID,"parameterValue"=>135884),
//        "result"=>array(),
//        "pageSize"=>100)
//);
//
//    if($Result['status']==success){
//        $relest = $Result['data'];//返回数组的所有值(非键名)
//        foreach($relest as $key =>$values){
//            if($key == postBackParameter){
//                foreach($values as $key =>$values1){
//                   // error_log(print_r($values,true));
//                    if($key == parameterType){
//                        $aaa = $values1;
//                    }
//                    if($key == parameterValue){
//                        $bbb = $values1;
//                    }
//
//
//                }
////               error_log(print_r($aaa,true));
////               error_log(print_r($bbb,true));
//            }
//
//
//        }
//
//
//    }


//$obj='{"order_id":21347781535117525,"buyer":10000116926915449}';
//$obj1=json_decode($obj,TRUE);
//error_log(print_r($obj1,true));
//foreach ($obj1 as $key=>$val){
//    $obj2[$key]=number_format($val,0,'','');
//}
//error_log(print_r($obj2,true));

//$aa = '{"status":"success","messages":[],"data":{"customrUid":22455399795866744,"customerUid":22455399795866744,"categoryName":"会员卡","number":"000001","name":"宁家芳","point":671.20,"discount":100,"balance":528.8,"phone":"18837308611","birthday":"","qq":"","email":"","address":"","remarks":"","createdDate":"2016-10-05 00:00:00","onAccount":0,"enable":1,"password":"0C3921FFA98C7FB1722FC1885BE72C1D","createStoreAppIdOrAccount":"E984638A36395D19862D8654DA88AB74"}}';
//$row1 = preg_replace('/"customerUid":(\d{1,})$/', '"customerUid":"\\1"', $aa);
//$row2 = json_decode($row1);
//print_r($row1);
//$json = preg_replace('/("id":)(\d{9,})/i', '${1}"${2}"', $json);
//$row1 = preg_replace('/"customerUid":(\d{1,})$/', '"customerUid":"\\1"', $row);
//
//
//$row1 = preg_replace('/("customerUid":)(\d{1,})/i','${1}"${2}"',$row1);
//date_default_timezone_set('PRC'); //默认时区
echo "昨天:",date("Y-m-d 00:00:00",strtotime("-1 day")),"<br>";
echo "昨天:",date("Y-m-d 23:59:59",strtotime("-1 day")), "<br>";




?>