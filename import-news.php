<?php

define( 'HOME_URL', 'http://wp.ecmarkets.sc' );
define( 'IMAGE_PATH', 'old-web/' );

//创建保存目录
$save_dir=__DIR__ . '/wp-content/uploads/'.IMAGE_PATH;
if(!file_exists($save_dir)&&!mkdir($save_dir,0777,true)){
 	die("创建目录失败");
}
 
// 数据库配置信息
$host = '192.168.0.129:3333'; // 数据库服务器
$username = 'root'; // 数据库用户名
$password = '123456'; // 数据库密码
$dbname = 'tmd_operation_config'; // 数据库名
 
// 创建数据库连接
$conn = new mysqli($host, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}
 
// 查询 
$sql = "SELECT count(tmd_activity_config_information_lang.id) as total FROM tmd_activity_config_information_lang LEFT JOIN activity_config ON tmd_activity_config_information_lang.activity_config_id = activity_config.id WHERE activity_config.operate_type = 2";
$count = $conn->query($sql);
 
$pageSize = 100; // 每页数据量
$currentPage = 1; // 当前页码
$totalRows = $count->fetch_assoc()['total']; // 假设总共有10000行数据
// 计算总页数
  
$totalPages = ceil($totalRows / $pageSize);
 
// 循环写入数据
for ($currentPage = 1; $currentPage <= $totalPages; $currentPage++) {
	$start = ($currentPage - 1) * $pageSize;
    $end = $start + $pageSize - 1;
    $search_sql = "SELECT * FROM tmd_activity_config_information_lang LEFT JOIN activity_config ON tmd_activity_config_information_lang.activity_config_id = activity_config.id WHERE activity_config.operate_type = 2 limit $start,$pageSize";
	$result = $conn->query($search_sql);
	$res=insert($result);
}

function insert($data){
	$mysqli = new mysqli("localhost", "root", "root", "ec-wp");
 
	// 检查连接
	if ($mysqli->connect_error) {
	    die("连接失败: " . $mysqli->connect_error);
	}
	// 启动事务
	$mysqli->begin_transaction();

	while($row = $data->fetch_assoc()) {
        $insertPost=[];
        $insertPost=[
        	//'id' =>  (string)$row['id'],
        	'post_author' => 2,
        	'post_date' => date("Y-m-d H:i:s", strtotime($row['create_time'])),
        	'post_date_gmt' => date("Y-m-d H:i:s", strtotime($row['create_time'])),
        	'post_content' => (!empty($row['link_pc']))?$row['link_pc']:' ',
        	'post_title' => $row['subject'],
        	'post_excerpt' => $row['description'],
        	'comment_status' => 'closed',
        	'ping_status' => 'closed',
        	'post_name' => substr(urlencode(str_replace(" ", "-", $row['subject'])).$row['id'], 0,199) ,
        	'post_modified' => date("Y-m-d H:i:s", strtotime($row['update_time'])),
        	'post_modified_gmt' => date("Y-m-d H:i:s", strtotime($row['update_time'])),
        	//'guid' => 'https://wp.ecmarkets.sc/?post_type=news&#038;p='.$row['id'],
        	'post_type' => 'news',
        	'to_ping' => ' ',
        	'pinged' => ' ',
        	'post_content_filtered' => ' ',
        ];
        $fields=implode(',', array_keys($insertPost));
        $values='"'.implode('","', $insertPost).'"';
        //这里是预览图的插入数据
		$filename = pathinfo(parse_url($row['main_img_url'], PHP_URL_PATH), PATHINFO_FILENAME);
		$fileext = basename($row['main_img_url']);
        $insertImage=$insertPost;
        $insertImage['post_content']='';
        $insertImage['post_title']=$filename.'-'.$row['id'];
        $insertImage['post_excerpt']='';
        $insertImage['post_status']='inherit';
        $insertImage['post_name']=$filename.'-'.$row['id'];
        $insertImage['post_excerpt']='';
        $insertImage['guid']=HOME_URL.'/wp-content/uploads/' . IMAGE_PATH . $fileext;
        $insertImage['post_type']='attachment';
        $insertImage['post_mime_type']='image/png';
        
         try {
         	// 预备一个参数化的插入语句
         	$placeholders = implode(',', array_fill(0, count($insertPost), '?'));
			$sql = "INSERT INTO wp_posts ($fields) VALUES ($placeholders)";
			$stmt = $mysqli->prepare($sql);
 			// 绑定参数
			$stmt->bind_param(implode('', array_fill(0, count($insertPost), 's')), $post_author,$post_date,$post_date_gmt,$post_content,$post_title,$post_excerpt,$comment_status,$ping_status,$post_name,$post_modified,$post_modified_gmt,$post_type,$to_ping,$pinged,$post_content_filtered);
			foreach ($insertPost as $key => $value) {
				$$key=$value;
			}
			// 执行语句
			if ($stmt->execute()) {
			} else {
				echo "Error: " . $sql . "<br>" . $stmt->error;
			    // 回滚事务
				$mysqli->rollback();
				$mysqli->close();
				return ;
			}
			$post_id=mysqli_insert_id($mysqli);
			//修改guid
			$guid=HOME_URL."/?post_type=news&#038;p=".$post_id;
			$sql = "UPDATE wp_posts SET guid = '".$guid."' WHERE ID = ".$post_id;
			if ($mysqli->query($sql) != TRUE) {
	        	echo '写入数据ID：'.$post_id.',修改guid失败';
			    echo "Error: " . $sql . "<br>" . $mysqli->error;
			    // 回滚事务
				$mysqli->rollback();
				$mysqli->close();
				return ;
			}
			//添加扩展自定义字段
			$insertExtend=[];
	        $insertExtend=[
	        	'country_status' => $row['country_status'],
	        	'country_limit' => $row['country_limit'],
	        	'show_status' => $row['show_status'],
	        	'top_status' => $row['top_status'],
	        	'symbol_type_limit' => $row['symbol_type_limit'],
	        	'lang_check_status' => $row['lang_check_status'],
	        	'link_h5' => $row['link_h5'],
	        ];
	        // 预备一个参数化的插入语句
         	$placeholders = implode(',', array_fill(0, count($insertExtend), '?'));
			$sql = "INSERT INTO wp_postmeta (post_id,meta_key,meta_value) VALUES (?,?,?)";
			$stmt = $mysqli->prepare($sql);
 			// 绑定参数
			$stmt->bind_param('sss', $insert_post_id,$meta_key,$meta_value);
			foreach ($insertExtend as $key => $value) {
				$insert_post_id=$post_id;
				$meta_key=$key;
				$meta_value=$value;
				// 执行语句
				if ($stmt->execute()) {
				} else {
					echo "Error: " . $sql . "<br>" . $stmt->error;
				    // 回滚事务
					$mysqli->rollback();
					$mysqli->close();
					return ;
				}
			}
			
	        //添加分类关系
	        $object_id=$post_id;
	        $sql = "INSERT INTO wp_term_relationships (object_id,term_taxonomy_id) VALUES ($object_id,89)";
	        if ($mysqli->query($sql) != TRUE) {
	        	echo '写入数据ID：'.$post_id.',分类关系失败';
			    echo "Error: " . $sql . "<br>" . $mysqli->error;
			    // 回滚事务
				$mysqli->rollback();
				$mysqli->close();
				return ;
			}
			//先下载图片到本地
			$file = __DIR__ . '/wp-content/uploads/' . IMAGE_PATH .$fileext; // 保存到本地的文件名
			$imageData = file_get_contents($row['main_img_url']);
			 
			$res=file_put_contents($file, $imageData);
			
			//添加缩略图
			$insertImage['post_parent']=$post_id;
			$img_fields=implode(',', array_keys($insertImage));
        	$img_values='"'.implode('","', $insertImage).'"';
			$sql = "INSERT INTO wp_posts ($img_fields) VALUES ($img_values)";
	        if ($mysqli->query($sql) != TRUE) {
	        	echo '写入数据ID：'.$post_id.',缩略图失败';
			    echo "Error: " . $sql . "<br>" . $mysqli->error;
			    // 回滚事务
				$mysqli->rollback();
				$mysqli->close();
				return ;
			}
			$image_id=mysqli_insert_id($mysqli);
			//写入关联表
			$sql = "INSERT INTO wp_postmeta (post_id,meta_key,meta_value) VALUES ($post_id,'_thumbnail_id',$image_id)";
	        if ($mysqli->query($sql) != TRUE) {
	        	echo '写入数据ID：'.$post_id.',文章缩略图关联表失败';
			    echo "Error: " . $sql . "<br>" . $mysqli->error;
			    // 回滚事务
				$mysqli->rollback();
				$mysqli->close();
				return ;
			}
			//写入图片的关联表
			$path = IMAGE_PATH . $fileext;
			$sql = "INSERT INTO wp_postmeta (post_id,meta_key,meta_value) VALUES ($image_id,'_wp_attached_file','$path')";

	        if ($mysqli->query($sql) != TRUE) {
	        	echo '写入数据ID：'.$post_id.',图片关联表失败';
			    echo "Error: " . $sql . "<br>" . $mysqli->error;
			    // 回滚事务
				$mysqli->rollback();
				$mysqli->close();
				return ;
			}
			echo '写入数据ID：'.$post_id.',成功';
			usleep(200);

         } catch (Exception $e) {
         	echo '写入数据ID：'.$post_id.',失败';
         	echo 'Message: ' .$e->getMessage();
         	// 回滚事务
			$mysqli->rollback();
			return ;
         }
    }
    echo '第一页写入成功';
    sleep(1);
    // 提交事务
	$mysqli->commit();
	$mysqli->close();
     

	 

}






?>





