<?php
require_once APP_ROOT.'/api/shop/module/response.php';

class api_my_coupon extends MemberbaseApp{

    var $_user_mod;
    var $_store_mod;
    var $_coupon_mod;
    var $_couponsn_mod;

    function api_my_coupon(){
        $this->_user_mod = & m('member');
        $this->_store_mod = & m('store');
        $this->_coupon_mod = & m('coupon');
        $this->_couponsn_mod = & m('couponsn');
    }


}
?>

