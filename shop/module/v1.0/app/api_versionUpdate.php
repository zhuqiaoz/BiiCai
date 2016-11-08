<?php

require_once APP_ROOT.'/api/shop/module/response.php';

class api_versionUpdate {
    
	public function update($args_data) {
	    
	    $mysql_server_name="localhost"; //数据库服务器名称
	    $mysql_username="root"; // 连接数据库用户名
	    $mysql_password="123456"; // 连接数据库密码
	    $mysql_database="biicai"; // 数据库的名字
	    
	    // 连接到数据库
	    $conn=mysql_connect($mysql_server_name, $mysql_username,
	        $mysql_password);
	    mysql_query("set character set 'utf8'");//读库
	    mysql_query("set names 'utf8'");//写库
	    // 从表中提取信息的sql语句
	    $strsql = "SELECT * FROM app_apk_version ORDER BY versionID DESC LIMIT 1";
	    // 执行sql查询
	    $result = mysql_db_query($mysql_database, $strsql, $conn);
	    // 获取查询结果
	    $row = mysql_fetch_row($result);
	    
	    if($args_data['bundleVersion'] < $row[5]){
	        $return = array();
	        $return['apkPath'] = $row[8];
	        $return['versionCode'] = $row[5];
	        $return['versionName'] = $row[6];
	        $return['changeLog'] = $row[7];
	        if($row[10] == 1){
	            $return['force_update'] = true;
	        }else{
	            $return['force_update'] = false;
	        }
	        Response::json(200, '更新版本', $return);
	    }else{
	        Response::json(404, '已是最新版本');
	    }
        
	}
}