<?php
/*
 * 将接口的数据的UID 转换成数组
 * json_decode 不在接口请求中转换。
 * 在请求结果后，本地进行转换，
 * 此接口返回的是object
 */
function pospal_Interface($http,$arr){
    $appKey = "200838166187280199";
    $jsondata = json_encode($arr);
    $signature = strtoupper(md5($appKey.$jsondata));
    $row = https_request($http,$jsondata,$signature);
    return $row;
}

// 模拟提交数据函数
function https_request($url, $data,$signature)
{
    $time = time();
    $curl = curl_init();// 启动一个CURL会话
    // 设置HTTP头
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "User-Agent: openApi",
        "Content-Type: application/json; charset=utf-8",
        "accept-encoding: gzip,deflate",
        "time-stamp: ".$time,
        "data-signature: ".$signature
    ));
    curl_setopt($curl, CURLOPT_URL, $url);         // 要访问的地址
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);		// Post提交的数据包
    curl_setopt($curl, CURLOPT_POST, 1);		// 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 获取的信息以文件流的形式返回
    $output = curl_exec($curl); // 执行操作
    if (curl_errno($curl)) {
        echo 'Errno'.curl_error($curl);//捕抓异常
    }
    curl_close($curl); // 关闭CURL会话

    return $output; // 返回数据
}
?>