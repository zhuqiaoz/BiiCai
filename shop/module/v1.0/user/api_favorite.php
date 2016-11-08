<?php

require_once APP_ROOT.'/api/shop/module/response.php';
require_once APP_ROOT . '/mobile/app/my_favorite.app.php';


class api_favorite extends My_favoriteApp{
    
    /**
     *    添加收藏商品
     *
     *    @author    Garbin
     *    @param     int    $goods_id
     *    @param     string $keyword
     *    @return    void
     */
    function addGoods($args_data) {
        
        $goods_id = $args_data['productID'];
        $keyword = '';
        
        /* 验证要收藏的商品是否存在 */
        $model_goods =& m('goods');
        $goods_info  = $model_goods->get($goods_id);
    
        if (empty($goods_info))
        {
            /* 商品不存在 */
            Response::json(401, "商品不存在");
            return;
        }
        $model_user =& m('member');
        $model_user->createRelation('collect_goods', $args_data['userID'], array(
            $goods_id   =>  array(
                'keyword'   =>  $keyword,
                'add_time'  =>  gmtime(),
            )
        ));
    
        /* 更新被收藏次数 */
        $model_goods->update_collect_count($goods_id);
    
        $goods_image = $goods_info['default_image'] ? $goods_info['default_image'] : Conf::get('default_goods_image');
        $goods_url  = SITE_URL . '/' . url('app=goods&id=' . $goods_id);
        $this->send_feed('goods_collected', array(
            'user_id'   => $args_data['userID'],
            'user_name'   => $args_data['userName'],
            'goods_url'   => $goods_url,
            'goods_name'   => $goods_info['goods_name'],
            'images'    => array(array(
                'url' => SITE_URL . '/' . $goods_image,
                'link' => $goods_url,
            )),
        ));
    
        /* 收藏成功 */
        Response::json(200, "收藏成功");
    }
    
    /**
     *    获取收藏商品
     *
     *    @author    Garbin
     *    @param     int    $goods_id
     *    @param     string $keyword
     *    @return    void
     */
    function getGoods($args_data) {
        $conditions = $this->_get_query_conditions($args_data, array(
            'field' => 'goods_name',         //可搜索字段title
            'equal' => 'LIKE',          //等价关系,可以是LIKE, =, <, >, <>
        ));
        $model_goods =& m('goods');
        $page   =   $this->_get_page($args_data);    //获取分页信息
        $collect_goods = $model_goods->find(array(
            'join'  => 'be_collect,belongs_to_store,has_default_spec',
            'fields'=> 'this.*,store.store_name,store.store_id,collect.add_time,goodsspec.price,goodsspec.spec_id',
            'conditions' => 'collect.user_id = ' . $args_data['userID'] . $conditions,
            'count' => true,
            'order' => 'collect.add_time DESC',
            'limit' => $page['limit'],
        ));
        foreach ($collect_goods as $key => $goods)
        {
            empty($goods['default_image']) && $collect_goods[$key]['default_image'] = Conf::get('default_goods_image');
        }
        $page['item_count'] = $model_goods->getCount();   //获取统计的数据
        $this->_format_page($page);
        
        $return['list'] = array();
        foreach ($collect_goods as $collect){
            $info = array();
            $info['goodsImg'] = 'http://www.biicai.com/'.$collect['default_image'];
            $info['goodsName'] = $collect['goods_name'];
            $info['goodsID'] = $collect['goods_id'];
            $info['goodsPrice'] = $collect['price'];
            $info['storeID'] = $collect['store_id'];
            $info['addTime'] = !empty($collect['add_time']) ? $this->microtime_format('Y-m-d H:i:s', $collect['add_time']) : '';
            $return['list'][] = $info;
        }
        $return['curPage'] = $page['curr_page'];
        $return['pageCount'] = $page['page_count'];
        $return['count'] = $page['item_count'];
        
        Response::json(200, "获取成功", $return);
    }
    
    /**
     *    获取查询条件
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function _get_query_conditions($args_data, $query_item){
        $str = '';
        $query = array();
        foreach ($query_item as $options)
        {
            if (is_string($options))
            {
                $field = $options;
                $options['field'] = $field;
                $options['name']  = $field;
            }
            !isset($options['equal']) && $options['equal'] = '=';
            !isset($options['assoc']) && $options['assoc'] = 'AND';
            !isset($options['type'])  && $options['type']  = 'string';
            !isset($options['name'])  && $options['name']  = $options['field'];
            !isset($options['handler']) && $options['handler'] = 'trim';
        }
        return $str;
    }
    
    /**
     *    获取分页信息
     *
     *    @author    Garbin
     *    @return    array
     */
    function _get_page($args_data)
    {
        $page = empty($args_data['curPage']) ? 1 : intval($args_data['curPage']);
        if(!is_int($page)||$page<1)
        {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $start = ($page -1) * $args_data['pageSize'];
    
        return array('limit' => "{$start},{$args_data['pageSize']}", 'curr_page' => $page, 'pageper' => $args_data['pageSize']);
    }
    
    /**
     * 格式化分页信息
     * @param   array   $page
     * @param   int     $num    显示几页的链接
     */
    function _format_page(&$page, $num = 7)
    {
        $page['page_count'] = ceil($page['item_count'] / $page['pageper']);
        $mid = ceil($num / 2) - 1;
        if ($page['page_count'] <= $num)
        {
            $from = 1;
            $to   = $page['page_count'];
        }
        else
        {
            $from = $page['curr_page'] <= $mid ? 1 : $page['curr_page'] - $mid + 1;
            $to   = $from + $num - 1;
            $to > $page['page_count'] && $to = $page['page_count'];
        }
    
        /* 生成app=goods&act=view之类的URL */
        if (preg_match('/[&|\?]?page=\w+/i', $_SERVER['QUERY_STRING']) > 0)
        {
            $url_format = preg_replace('/[&|\?]?page=\w+/i', '', $_SERVER['QUERY_STRING']);
        }
        else
        {
            $url_format = $_SERVER['QUERY_STRING'];
        }
    
        $page['page_links'] = array();
        $page['first_link'] = ''; // 首页链接
        $page['first_suspen'] = ''; // 首页省略号
        $page['last_link'] = ''; // 尾页链接
        $page['last_suspen'] = ''; // 尾页省略号
        for ($i = $from; $i <= $to; $i++)
        {
            $page['page_links'][$i] = url("{$url_format}&page={$i}");
        }
        if (($page['curr_page'] - $from) < ($page['curr_page'] -1) && $page['page_count'] > $num)
        {
            $page['first_link'] = url("{$url_format}&page=1");
            if (($page['curr_page'] -1) - ($page['curr_page'] - $from) != 1)
            {
                $page['first_suspen'] = '..';
            }
        }
        if (($to - $page['curr_page']) < ($page['page_count'] - $page['curr_page']) && $page['page_count'] > $num)
        {
            $page['last_link'] = url("{$url_format}&page=" . $page['page_count']);
            if (($page['page_count'] - $page['curr_page']) - ($to - $page['curr_page']) != 1)
            {
                $page['last_suspen'] = '..';
            }
        }
    
        $page['prev_link'] = $page['curr_page'] > $from ? url("{$url_format}&page=" . ($page['curr_page'] - 1)) : "";
        $page['next_link'] = $page['curr_page'] < $to ? url("{$url_format}&page=" . ($page['curr_page'] + 1)) : "";
    }
    
    /** 格式化时间戳，精确到毫秒，x代表毫秒 */
    function microtime_format($tag, $time)
    {
        list($usec, $sec) = explode(".", $time);
        $date = date($tag,$usec);
        return str_replace('x', $sec, $date);
    }
    
    /**
     *    删除收藏商品
     *
     *    @author    Garbin
     *    @param     int    $goods_id
     *    @param     string $keyword
     *    @return    void
     */
    function deleteGoods($args_data) {
        /* 解除“我”与商品ID为$ids的收藏关系 */
        $model_user =& m('member');
        $model_user->unlinkRelation('collect_goods', $args_data['userID'], $args_data['productID']);
        if ($model_user->has_error()){
            Response::json(401, "删除失败");
            return;
        }
        Response::json(200, "删除成功");
    }
    
}