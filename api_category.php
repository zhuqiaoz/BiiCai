<?php

// +----------------------------------------------------------------------
// | ebSIG
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2020 http://www.ebsig.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: yushenghai <yushenghai@ebsig.com>
// +----------------------------------------------------------------------


/**
 * 分类接口
 * @package  api/wdh/module/v1.0/user
 * @author   yushenghai <yushenghai@ebsig.com>
 * @version 1.0
 */
ebsig_require('module/base/goods/goodsCategory.class.php');
ebsig_require('api/wdh/appFunc.php');

class api_category {
    
    /**
     * 获取分类信息
     * @param $args_data
     * $args_data = array(
     *      'categoryId'  => String  分类ID
     * )
     * @return array
     */
    public function search($args_data='') {

        //实例化商品分类类
        $goodsCategory = new goodsCategory() ;

        //查询大分类
        $big_array = array(
            'sortname' => 'sortOrder',
            'sortorder' => 'DESC',
            'useFlg' => 1,
        );

        if ( isset($args_data['bigCategoryId']) && !empty($args_data['bigCategoryId']) ) {
            $big_array['bigCategoryID'] = $args_data['bigCategoryId'];
        }

        $category = $goodsCategory->searchBigCategory( $big_array ) ;
        if( $category ){
            foreach ($category as $b=>$bigVal) {
                if( $bigVal['bigCategoryID'] == 1){
                    unset($category[$b]);
                }
            }
        }
        //查询中分类
        if ($category) {
            foreach ($category as $b=>$bigVal) {

                $mid_array = array(
                    'bigCategoryID' => $bigVal['bigCategoryID'],
                    'sortname'      => 'sortOrder',
                    'sortorder'     => 'ASC'
                );
                $mid_category = $goodsCategory->searchMidCategory( $mid_array ) ;

                if ($mid_category) {
                    foreach ($mid_category as $m=>$midVal) {

                        $small_array = array(
                            'bigCategoryID' => $bigVal['bigCategoryID'],
                            'midCategoryID' => $midVal['midCategoryID'],
                            'sortname'      => 'sortOrder',
                            'sortorder'     => 'ASC'
                        );
                        $small_category = $goodsCategory->searchSmallCategory( $small_array ) ;

                        if ( $small_category ) {
                            $category[$b]['view'] = 1;
                        } else {
                            $shop_array[$b]['view'] = 0;
                        }
                        $mid_category[$m]['srot'] = $m;
                        $mid_category[$m]['smallCategory'] = $small_category;
                    }
                }
                $category[$b]['midCategory'] = $mid_category;
            }
        }

        /**
         * 处理返回的数据
         */
        $return_category = array();
        if ( $category ) {
            foreach ( $category as $bigKey => $bigCat ) {
                if ( $bigCat['view'] == 1 ) {
                    $return_category[$bigKey] = array(
                        'categoryName' => $bigCat['name'],
                        'midCategory' => array(),
                    );
                    foreach ( $bigCat['midCategory'] as $midKey => $midCat  ) {
                        $return_category[$bigKey]['midCategory'][$midKey] = array(
                            'categoryName' => $midCat['name'],
                            'smallCategory' => array(),
                        );
                        foreach ( $midCat['smallCategory'] as $smallKey => $smallCat ) {
                            $return_category[$bigKey]['midCategory'][$midKey]['smallCategory'][$smallKey] = array(
                                'categoryName' => $smallCat['name'],
                                'categoryPic' => $smallCat['icoPicShow'],
                                'categoryLink' => G_SHOP_CDN . $smallCat['wap_link']
                            );
                        }
                    }
                }
            }
        }

        $return_category = array_merge($return_category);

        return array ( 'code'=>200 , 'message'=>'ok' , 'data'=>$return_category ) ;


    }



}