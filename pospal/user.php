<?php
require_once("test.php");
require_once APP_ROOT.'/api/shop/module/v1.0/user/api_user.php';
define('appId','E984638A36395D19862D8654DA88AB74');
class user
{
    var $name;
    function  __construct(){
        $this->name = new api_user();
    }
    //���ݻ�Ա�Ų�ѯ��Ա
    function user_customerNum()
    {
        $data = user_customerNum;
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/customerOpenApi/queryByNumber";
        $arr = array(
            "appId" => appId,
            "customerNum" => "000017"
        );
        $return = pospal_Interface($http, $arr);
        $return =preg_replace('/("customerUid":)(\d{1,})/i','${1}"${2}"',$return);
        $return = json_decode($return,true);
        if ($return['status'] == 'success') {
            $Result = $this->user_customerNum_Array($return, $data);
            error_log(print_r($Result,true));
            foreach($Result as $res){
//                $User = $this->name->registere($res);
//                error_log(print_r($User,true));
            }
        }
    }

    /*
     *�ֽⷵ�ص�����
     */
    function  user_customerNum_Array($As, $data)
    {
        $arr1 = array();
        $arr2 = array();
        if ($data == 'user_customerNum') {
            foreach ($As['data'] as $key => $value) {
                if ($key == 'name') { //�û�
                    $arr1['userName'] = $value;
                }
                if ($key == 'password') {//����
                    $arr1['passwd'] = $value;
                }
                if ($key == 'email') {//emaill
                    $arr1[$key] = $value;
                }
                if ($key == 'phone') {//�绰
                    $arr1[$key] = $value;
                }
                if ($key == 'customerUid') {//pospal  Id
                    $arr1['pospal_customerUid'] = $value;
                }
                if ($key == 'number') {//pospal ��Ŷ�Ӧ������
                    $arr1['pospal_number'] = $value;
                }
                if ($key == 'point') {//pospal  ����
                    $arr1['pospal_point'] = $value;
                }
                if ($key == 'discount') {  //pospal  ����  �ۿ�
                    $arr1['pospal_discount'] = $value;
                }
                if ($key == 'balance') {//pospal  ���
                    $arr1['pospal_balance'] = $value;
                }
                if ($key == 'address') {//pospal  ��ַ
                    $arr1['pospal_address'] = $value;
                }

            }
            $arr2[] = $arr1;
        }
        if ($data == 'user_pageSize') {
            foreach ($As['data']['result'] as $key => $value) {
                foreach ($value as $key2 => $value2) {
                    if ($key2 == 'name') { //�û�
                        $arr1['userName'] = $value2;
                    }
                    if ($key2 == 'password') {//����
                        $arr1['passwd'] = $value2;
                    }
                    if ($key2 == 'email') {//emaill
                        $arr1[$key2] = $value2;
                    }
                    if ($key2 == 'phone') {//�绰
                        $arr1[$key2] = $value2;
                    }
                    if ($key2 == 'customerUid') {//pospal  Id
                        $arr1['pospal_customerUid'] = $value2;
                    }
                    if ($key2 == 'number') {//pospal ��Ŷ�Ӧ������
                        $arr1['pospal_number'] = $value2;
                    }
                    if ($key2 == 'point') {//pospal  ����
                        $arr1['pospal_point'] = $value2;
                    }
                    if ($key2 == 'discount') {  //pospal  ����  �ۿ�
                        $arr1['pospal_discount'] = $value2;
                    }
                    if ($key2 == 'balance') {//pospal  ���
                        $arr1['pospal_balance'] = $value2;
                    }
                    if ($key2 == 'address') {//pospal  ��ַ
                        $arr1['pospal_address'] = $value2;
                    }
                }
                $arr2[] = $arr1;
            }
        }
        if ($data == 'user_customerTel') {
            foreach ($As['data'] as $k => $v) {
                foreach ($v as $key => $value) {
                    if ($key == 'name') { //�û�
                        $arr1['userName'] = $value;
                    }
                    if ($key == 'password') {//����
                        $arr1['passwd'] = $value;
                    }
                    if ($key == 'email') {//emaill
                        $arr1[$key] = $value;
                    }
                    if ($key == 'phone') {//�绰
                        $arr1[$key] = $value;
                    }
                    if ($key == 'customerUid') {//pospal  Id
                        $arr1['pospal_customerUid'] = $value;
                    }
                    if ($key == 'number') {//pospal ��Ŷ�Ӧ������
                        $arr1['pospal_number'] = $value;
                    }
                    if ($key == 'point') {//pospal  ����
                        $arr1['pospal_point'] = $value;
                    }
                    if ($key == 'discount') {  //pospal  ����  �ۿ�
                        $arr1['pospal_discount'] = $value;
                    }
                    if ($key == 'balance') {//pospal  ���
                        $arr1['pospal_balance'] = $value;
                    }
                    if ($key == 'address') {//pospal  ��ַ
                        $arr1['pospal_address'] = $value;
                    }

                }
                $arr2[] = $arr1;
            }
        }
        return $arr2;
    }

//���ݻ�Ա��ϵͳ��Ψһ��ʶ��ѯ
    function user_customerUid()
    {
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/customerOpenApi/queryByUid";
        $arr = array(
            "appId" => appId,
            "customerUid" => "887225236037000000"
        );
        $return = pospal_Interface($http, $arr);
        error_log(print_r($return,true));
    }

//��ҳ��ѯȫ����Ա
    function user_pageSize()
    {
        $arr = array(
            "appId" => appId,
        );
        $data = user_pageSize;
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/customerOpenApi/queryCustomerPages";
        $return = pospal_Interface($http, $arr);
        if ($return['status'] == 'success') {
            $Result = $this->user_customerNum_Array($return, $data);
            foreach($Result as $res){
                $User = $this->name->registere($res);
                error_log(print_r($User,true));
            }
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
                $Result = $this->user_pageSize_Page($aaa, $bbb);
            }
            return $Result;
        }
    }

    /*
     *��ҳ�������ݣ���ʱʹ����������������������õķ�����
     * 20161212
     */
    function user_pageSize_Page($aaa, $bbb)
    {
        $arr = array(
            "appId" => appId,
            "postBackParameter" => array(
                "parameterType" => $aaa,
                "parameterValue" => $bbb
            )
        );
        $data = user_pageSize;
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/customerOpenApi/queryCustomerPages";
        $return = pospal_Interface($http, $arr);
        if ($return['status'] == 'success') {
            $Result = $this->user_customerNum_Array($return, $data);
            foreach($Result as $res){
                $User = $this->name->registere($res);
                error_log(print_r($User,true));
            }
            $resultlength = count($return['data']['result']);
            if ($resultlength >= $return['pageSize']) {
                $aaa = $aaa;
                $bbb = $bbb;
                $Result = $this->user_pageSize_Page($aaa, $bbb);
            }//���ؽ���ķ���������Ȼ�������⡣
            return $Result;
        }
    }


//�޸Ļ�Ա������Ϣ
    function user_customerInfo()
    {
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/customerOpenApi/updateBaseInfo";
        $arr = array(
            "appId" => appId,
            "customerNum" => "000017"
        );
    }

//�޸Ļ�Ա������
    function user_balanceIncrement()
    {
        $http = "http://area8-win.pospal.cn:443/pospal-api2/openapi/v1/customerOpenApi/updateBalancePointByIncrement";
        $arr = array(
            "appId" => appId,
            "customerNum" => "000017"
        );

    }

//��ӻ�Ա
    function user_CreatecustomerInfo()
    {
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/customerOpenApi/add";
        $arr = array(
            "appId" => appId,
            "customerNum" => "000017"
        );
    }

//�����ֻ��Ų�ѯ��Ա��Ϣ
    function user_customerTel()
    {
        $data = user_customerTel;
        $http = "https://area8-win.pospal.cn:443/pospal-api2/openapi/v1/customerOpenapi/queryBytel";
        $arr = array(
            "appId" => appId,
            "customerTel" => "13069350650"
        );
        $return = pospal_Interface($http, $arr);
        if ($return['status'] == 'success') {
            $Result = $this->user_customerNum_Array($return, $data);
            error_log(print_r($Result, true));
        }
    }
}

?>