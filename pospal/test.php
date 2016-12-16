<?php
/*
 * ���ӿڵ����ݵ�UID ת��������
 * json_decode ���ڽӿ�������ת����
 * ���������󣬱��ؽ���ת����
 * �˽ӿڷ��ص���object
 */
function pospal_Interface($http,$arr){
    $appKey = "200838166187280199";
    $jsondata = json_encode($arr);
    $signature = strtoupper(md5($appKey.$jsondata));
    $row = https_request($http,$jsondata,$signature);
    return $row;
}

// ģ���ύ���ݺ���
function https_request($url, $data,$signature)
{
    $time = time();
    $curl = curl_init();// ����һ��CURL�Ự
    // ����HTTPͷ
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "User-Agent: openApi",
        "Content-Type: application/json; charset=utf-8",
        "accept-encoding: gzip,deflate",
        "time-stamp: ".$time,
        "data-signature: ".$signature
    ));
    curl_setopt($curl, CURLOPT_URL, $url);         // Ҫ���ʵĵ�ַ
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // ����֤֤����Դ�ļ��
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // ��֤���м��SSL�����㷨�Ƿ����
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);		// Post�ύ�����ݰ�
    curl_setopt($curl, CURLOPT_POST, 1);		// ����һ�������Post����
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// ��ȡ����Ϣ���ļ�������ʽ����
    $output = curl_exec($curl); // ִ�в���
    if (curl_errno($curl)) {
        echo 'Errno'.curl_error($curl);//��ץ�쳣
    }
    curl_close($curl); // �ر�CURL�Ự

    return $output; // ��������
}
?>