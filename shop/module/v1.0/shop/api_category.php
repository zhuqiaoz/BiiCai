<?php

require_once APP_ROOT.'/api/shop/module/response.php';

class api_category extends StoreadminbaseApp{
    var $_gcategory_mod;

    //列表页
    function my_category($args_data){
        $this->_gcategory_mod =& bm('gcategory', array('_store_id' =>$args_data['userID']));
        /* 取得商品分类 */
        $gcategories = $this->_gcategory_mod->get_list();
        $tree =& $this->_tree($gcategories);

        /* 先根排序 */
        $sorted_gcategories = array();
        $cate_ids = $tree->getChilds();
        foreach ($cate_ids as $id)
        {
            $sorted_gcategories[] = array_merge($gcategories[$id], array('layer' => $tree->getLayer($id)));
        }
        $this->assign('gcategories', $sorted_gcategories);

        /* 构造映射表（每个结点的父结点对应的行，从1开始） */
        $row = array(0 => 0); // cate_id对应的row
        $map = array(); // parent_id对应的row
        foreach ($sorted_gcategories as $key => $gcategory)
        {
            $row[$gcategory['cate_id']] = $key + 1;
            $map[] = $row[$gcategory['parent_id']];
        }
        $this->assign('map', ecm_json_encode($map));
        error_log(print_r($sorted_gcategories,true));
        Response::json(200, '列表',$sorted_gcategories);
    }
    /* 构造并返回树 */
    function &_tree($gcategories)
    {
        import('tree.lib');
        $tree = new Tree();
        $tree->setTree($gcategories, 'cate_id', 'parent_id', 'cate_name');
        return $tree;
    }

    /*新增*/
    function add($args_data)
    {
            $data = array(
                'cate_name'  => $args_data['cate_name'],
               // 'parent_id'  => $args_data['parent_id'],
                'sort_order' => $args_data['sort_order'],
                'if_show'    => $args_data['if_show'],
            );
            /* 检查名称是否已存在 */
            $this->_gcategory_mod =& bm('gcategory', array('_store_id' =>$args_data['userID']));
            if (!$this->_gcategory_mod->unique(trim($data['cate_name']), $data['parent_id']))
            {
                Response::json(301, '该分类名称已存在.');
            }

            /* 保存 */
            $cate_id = $this->_gcategory_mod->add($data);
            if (!$cate_id)
            {
                Response::json(302, '异常错误');
            }
        Response::json(200, 'OK');
        }


    /* 编辑*/
    function edit($args_data)
    {
        $id = empty($args_data['id']) ? 0 : intval($args_data['id']);
            /* 是否存在 */
        $this->_gcategory_mod =& bm('gcategory', array('_store_id' =>$args_data['userID']));
        if (!$this->_gcategory_mod->unique(trim($args_data['cate_name']), $args_data['parent_id']))
        {
            Response::json(301, '该分类名称已存在.');
        }
        $gcategory = $this->_gcategory_mod->get_info($id);
        if (!$gcategory)
            {
                Response::json(303, '该分类不存在，请您返回后刷新页面');
            }
        else
        {
            $data = array(
                'cate_name'  => $args_data['cate_name'],
                //'parent_id'  => $args_data['parent_id'],
                'sort_order' => $args_data['sort_order'],
                'if_show'    => $args_data['if_show'],
            );

            /* 检查名称是否已存在 */
            if (!$this->_gcategory_mod->unique(trim($data['cate_name']), $data['parent_id'], $id))
            {
                $this->pop_warning('name_exist');
                return;
            }

            /* 保存 */
            $rows = $this->_gcategory_mod->edit($id, $data);
            if ($this->_gcategory_mod->has_error())
            {
                $this->pop_warning($this->_gcategory_mod->get_error());
                return;
            }

            Response::json(200, 'OK');
        }
    }

    /* 刪除 */
    function drop($args_data)
    {
        $this->_gcategory_mod =& bm('gcategory', array('_store_id' =>$args_data['userID']));
        $id = isset($args_data['id']) ? trim($args_data['id']) : '';
        if (!$id)
        {
            Response::json(200, '请您选择要删除的分类');
        }

        $ids = explode(',', $id);
        if (!$this->_gcategory_mod->drop($ids))
        {
            Response::json(301, '异常错误');
        }

        Response::json(200, 'OK');
    }
}

?>