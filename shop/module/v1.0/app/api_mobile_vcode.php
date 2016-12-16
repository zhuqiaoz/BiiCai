<?php
require_once APP_ROOT.'/api/shop/module/response.php';

class api_mobile_vcode extends MemberbaseApp{
    /**
     * �˶��ֻ����͵���֤���Ƿ���ͬ
     */
    function cmc($args_data)
    {
        $confirm_code = empty($args_data['confirm_code']) ? '' : trim($args_data['confirm_code']);
        if (empty($_SESSION['MobileConfirmCode']) || !$confirm_code) {
            Response::json(301, '��֤����');
            return;
        } else {
            if ($confirm_code == $_SESSION['MobileConfirmCode']) {
                Response::json(200, '��֤�ɹ�');
            } else {
                Response::json(302, '��֤����');
            }
        }
    }

    /*
     * ���ʱ���Ƿ��Ѿ���ע��
     */
    function check_mobile($args_data) {
        $phone_mob = empty($args_data['phone_mob']) ? '' : trim($args_data['phone_mob']);
        //�Ƿ����ֻ���
        if (!preg_match("/^(13[0-9]|15[012356789]|17[678]|18[0-9]|14[57])[0-9]{8}$/", $args_data['phone_mob'])) {
            Response::json(303, '������Ĳ�����Ч�ֻ���');
        }
        //���Ͷ��ŵĸ�ʽ
        $type = $args_data['type'];
        if (!in_array($type, array('register', 'find', 'change'))) {
            Response::json(304, 'ȱ��type');
        }
        $ms = & ms();
        if ($type == 'find') {
            //�һ���������Ҫ���ڵĵ绰����
            echo ecm_json_encode(!$ms->user->check_mobile($phone_mob));
        } else {
            //ע���Լ��޸�����Ҫ�����ڵĵ绰����
            echo ecm_json_encode($ms->user->check_mobile($phone_mob));
        }
    }
}
?>