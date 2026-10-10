<?php
/**
 * 鲸犀商城 - 微信公众号小程序商城系统!
 * Copyright © 2020 鲸犀网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：供应链版本
 * 授权主体：无限制版本
 * 授权域名：@@@
     
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

namespace app\admin\common;
use ZipArchive;
class File{
	/**
     * 上传所有文件
     * @access public
     * @param string $savePath  上传文件保存路径
     * @return string
     */
    public function upload($path ='',$maxwidth=0,$maxheight=0) {
        //如果不指定保存文件名，则由系统默认
        if(empty($path)){
			$path = 'upload/'.date('Ym').'/';
		}
        $savePath = ROOT_PATH.$path;
        // 检查上传目录
        if(!is_dir($savePath)) {
			// 尝试创建目录
			if(!$this->creat_dir($savePath)){
				return array('code'=>1,'msg'=>'上传目录'.$savePath.'不存在');
			}
        }else {
            if(!is_writeable($savePath)) {
				return array('code'=>1,'msg'=>'上传目录'.$savePath.'不可写');
            }
        }
        $fileInfo = array();
        $isUpload   = false;

        // 对$_FILES数组信息处理
		$files = array();
		$n = 0;
		foreach ($_FILES as $file){
			if(is_array($file['name'])) {
				$keys = array_keys($file);
				$count	 =	 count($file['name']);
				for ($i=0; $i<$count; $i++) {
					foreach ($keys as $key)
						$files[$n][$key] = $file[$key][$i];
					$n++;
				}
			}else{
				$files[$n] = $file;
				$n++;
			}
		}
		//dump($files);
        foreach($files as $key => $file) {
            //过滤无效的上传
            if(!empty($file['name'])) {
                //登记上传文件的扩展信息
                $file['key']          =  $key;
				$pathinfo = pathinfo($file['name']);
                $file['extension']  = $pathinfo['extension'];
                $file['savepath']   = $savePath;
                $file['savename']   = date('d_His').rand(1000,9999);
				if($file['error']!== 0) {
					switch($file['error']) {
						case 1:
							$error = '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值';
							break;
						case 2:
							$error = '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值';
							break;
						case 3:
							$error = '文件只有部分被上传';
							break;
						case 4:
							$error = '没有文件被上传';
							break;
						case 6:
							$error = '找不到临时文件夹';
							break;
						case 7:
							$error = '文件写入失败';
							break;
						default:
							$error = '未知上传错误！';
					}
					return array('code'=>1,'msg'=>$error);
				}
				if(!in_array(strtolower($file['type']),array('image/jpg','image/pjpeg','image/x-png','image/gif','image/jpeg','image/png','image/bmp'))){
					return array('code'=>1,'msg'=>'上传文件类型不允许');
				}
				if(!in_array(strtolower($file['extension']),array('jpg','gif','jpeg','png','bmp','doc','xls'),true)){
					return array('code'=>1,'msg'=>'上传文件类型不允许!');
				}
				if(!is_uploaded_file($file['tmp_name'])){
					return array('code'=>1,'msg'=>'非法上传文件！');
				}
				if($file['size'] > 1024 * 1024 *5){ //5M
					return array('code'=>1,'msg'=>'上传文件过大！');
				}

                //保存上传文件
				$filename = $file['savename'].".".$file['extension'];
				// 如果是图像文件 检测文件格式
				if( in_array(strtolower($file['extension']),array('gif','jpg','jpeg','bmp','png','swf')) && false === getimagesize($file['tmp_name'])) {
					return array('code'=>1,'msg'=>'非法图像文件');
				}
				if(!move_uploaded_file($file['tmp_name'], autoCharset($file['savepath'].$filename,'utf-8','gbk'))) {
					return array('code'=>1,'msg'=>'文件上传保存错误！');
				}
				if(($maxwidth>0 || $maxheight>0) && in_array(strtolower($file['extension']),array('gif','jpg','jpeg','bmp','png'))) {
					$image =  getimagesize($file['savepath'].$filename);
					if(false !== $image) {
						// 生成图像缩略图
						require_once('image.php');
						$filenamethumb = $file['savename']."_t.".$file['extension'];
						Image::thumb($file['savepath'].$filename,$file['savepath'].$filenamethumb,'',$maxwidth,$maxheight,true);
						// 生成缩略图之后删除原图
						//unlink($filename);
						$filename = $filenamethumb;
					}
				}

                if(function_exists($this->hashType)) {
                    $fun =  $this->hashType;
                    $file['hash']   =  $fun($this->autoCharset($file['savepath'].$file['savename'],'utf-8','gbk'));
                }
                //上传成功后保存文件信息，供其他地方调用
                unset($file['tmp_name'],$file['error']);
				$fileInfo[] = '/'.$path.$filename;
				$isUpload = true;
            }
        }
        if($isUpload) {
			return array('code'=>0,'msg'=>'上传成功','url'=>implode(',',$fileInfo));
        }else{
			return array('code'=>0,'msg'=>'没有选择上传文件');
        }
    }
	/**
	 * 创建多级文件夹 参数为带有文件名的路径
	 * @param string $path 路径名称
	 */
	public static function creat_dir_with_filepath($path,$mode=0777){
		return self::creat_dir(dirname($path),$mode);
	}
	
	/**
	 * 创建多级文件夹
	 * @param string $path 路径名称
	 */
	public static function creat_dir($path,$mode=0777){
		if(!is_dir($path)){
			if(self::creat_dir(dirname($path))){
				return mkdir($path,$mode);
			}
		}else{
			return true;
		}
	}
	
	/**
	 * 递归复制文件
	 * 
	 * @param $source 源文件或目录名
	 * @param $destination 目的文件或目录名
	 * @param $child 是不是包含的子目录
	 * @param $justnew 只拷贝新更改过的文件
	 **/
	public static function all_copy($source, $destination,$justnew = false){
		if(!is_dir($source)){
			if(!file_exists(dirname($destination))){
				File::creat_dir(dirname($destination));
			}
			if(!$justnew||!file_exists($destination)||filemtime($destination)<filemtime($source)){
				@copy($source,$destination);
			}
		}else{
			self::creat_dir($destination);
			$handle=dir($source);
			while($entry=$handle->read()){
				if(strpos($entry, '.')!==0){
					self::all_copy($source."/".$entry,$destination."/".$entry,$justnew);
				}
			}
		}
	}
	
	
	/**
	 *
	 * 清空文件夹
	 * @param $dirName
	 * @param $oldtime 小于的时间
	 * @param $newtime 大于的时间
	 */
	public static function clear_dir($dirName){
		if(is_dir($dirName)){//如果传入的参数不是目录，则为文件，应将其删除
			//如果传入的参数是目录
			$handle = @opendir($dirName);
			while(($file = @readdir($handle)) !== false){
				if($file!='.'&&$file!='..'){
					$dir = $dirName . '/' . $file; //当前文件$dir为文件目录+文件
					self::remove_dir($dir);
				}
			}
		}
	}
	
	/**
	 * 判断文件夹是否为空
	 * 
	 * @param string $path
	 * @return boolean
	 */
	public static function  is_empty_dir($path){
		$dh = opendir($path);
		while(false !== ($f = readdir($dh))){
			if($f != "." && $f != ".."){
				return   false;
			}
		}
		return   true;
	}
	
	/**
	 * 删除文件<br/>
	 * 如果此文件的上级文件夹为空则递归删除
	 * 
	 * @param string $filepath
	 */
	public static function remove_file_with_parentdir($filepath){		
		$parentdir = dirname($filepath);
		@unlink($filepath);
		if(self::is_empty_dir($parentdir)){
			self::remove_file_with_parentdir($parentdir);
		}
		return true;
	}
	
	/**
	 *
	 * 清空并删除文件夹
	 * @param $dirName
	 * @param $oldtime 小于的时间
	 * @param $newtime 大于的时间
	 */
	public static function remove_dir($dirName,$oldtime=null,$newtime=null){
		if(!is_dir($dirName)){//如果传入的参数不是目录，则为文件，应将其删除
			$mtime = filectime($dirName);
			if($oldtime===null&&$newtime===null){
				@unlink($dirName);
			}else{
				if(isset($oldtime)){
					if($mtime<$oldtime){
						@unlink($dirName);
					}
				}
				if(isset($newtime)){
					if($mtime>$newtime){
						@unlink($dirName);
					}
				}
			}
			return false;
		}
		//如果传入的参数是目录
		$handle = @opendir($dirName);
		while(($file = @readdir($handle)) !== false){
			if($file!='.'&&$file!='..'){
				$dir = $dirName . '/' . $file; //当前文件$dir为文件目录+文件
				self::remove_dir($dir,$oldtime,$newtime);
			}
		}
		closedir($handle);
		return @rmdir($dirName) ;
	}
	/**
	 * 递归的文件夹大小 返回文本形式
	 * @param string $dir
	 */
	public static function dir_size($dir){
		return self::get_real_size(self::get_dir_size($dir));
	}
	
	/**
	 * 文件大小 返回文本形式
	 * @param string $path
	 */
	public static function file_size($path){
		return self::get_real_size(filesize($path));
	}
	
	/**
	 * 获得文件夹大小的调用文件
	 * @param $dir
	 */
	public static function get_dir_size($dir){
		$sizeResult = 0;
		$handle = opendir($dir);
		while (false!==($FolderOrFile = readdir($handle))){
			if($FolderOrFile != "." && $FolderOrFile != ".."){
				if(is_dir("$dir/$FolderOrFile")){
					$sizeResult += self::get_dir_size("$dir/$FolderOrFile");
				}else{
					$sizeResult += filesize("$dir/$FolderOrFile");
				}
			}
		}
		closedir($handle);
		return $sizeResult;
	}
	
	/**
	 * 文件大小的文本描述转换
	 * @param integer $size
	 */
	public static function get_real_size($size){
		$size = intval($size);
		$kb = 1024;          // Kilobyte
		$mb = 1024 * $kb;    // Megabyte
		$gb = 1024 * $mb;    // Gigabyte
		$tb = 1024 * $gb;    // Terabyte
		if($size < $kb){
			return $size." B";
		}else if($size < $mb){
			return round($size/$kb,2)." KB";
		}else if($size < $gb){
			return round($size/$mb,2)." MB";
		}else if($size < $tb){
			return round($size/$gb,2)." GB";
		}else{
			return round($size/$tb,2)." TB";
		}
	}
	
	
	/**
	 * scandir目录列举
	 * 
	 * @param string $dir
	 * @param integer $sort 1:名称 2:名称倒序 3时间  4时间倒序
	 * @return array 文件列表
	 */
	public static function scandir($dir,$sort=0){
		$files = array();
		if(!file_exists($dir)){
			return $files;
		}		
		if (function_exists('scandir')){
			$files = scandir($dir);
		}else{
			$dh  = opendir($dir);
			while (false !== ($filename = readdir($dh))) {
				$files[] = $filename;
			}
		}
		$resf = array();
		if($sort<3){
			foreach ($files as $fn){
				if(strpos($fn, '.')!==0){
					$resf[] = $fn;
				}
			}
		}else{
			foreach ($files as $fn){
				if(strpos($fn, '.')!==0){
					$resf[$fn] = filemtime($dir.'/'.$fn);
				}
			}
		}
		
		if($sort==1){
			sort($resf);
		}else if($sort ==2){
			rsort($resf);
		}if($sort==3){
			asort($resf);
			$resf = array_keys($resf);
		}else if($sort==4){
			arsort($resf);
			$resf = array_keys($resf);
		}
		
		
		return $resf;
	}
	
	/**
	 * 获得文件扩展名
	 *
	 * @param  string  $path
	 * @return string
	 */
	public static function extension($path){
		return pathinfo($path, PATHINFO_EXTENSION);
	}
	
	/**
	 * 获得文件名
	 *
	 * @param  string  $path
	 * @return string
	 */
	public static function name_juest($path){
		return substr($path, 0, strlen($path)- strlen(self::extension($path))-1);
	}
	
	
	/**
	 * 读取文件内容.
	 *
	 * @param  string  $path
	 * @param  mixed   $default 默认值
	 * @return string
	 */
	public static function get($path, $default = null)
	{
		return (file_exists($path)) ? file_get_contents($path) : YYUC::value($default);
	}
	
	/**
	 * 写入信息
	 *
	 * @param  string  $path
	 * @param  string  $data
	 * @return int
	 */
	public static function put($path, $data)
	{
		self::creat_dir_with_filepath($path);
		return file_put_contents($path, $data, LOCK_EX);
	}
	
	/**
	 * 文件中继续写入信息
	 *
	 * @param  string  $path
	 * @param  string  $data
	 * @return int
	 */
	public static function append($path, $data)	{
		return file_put_contents($path, $data, LOCK_EX | FILE_APPEND);
	}
	
	/**
	 * 获得文件的Mime类型
	 *
	 * @param  string  $path
	 * @return int
	 */
	public static function mime($path, $data){
		return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
	}
	
	/**
	 * 把文件压缩成zip
	 * 
	 * @param string $path
	 * @param string $zip
	 * @param string $ziproot zip文件夹内相对路径
	 */
	public static function add_file_to_zip($path,$zip,$ziproot=''){
		$zpath = null;
		if(is_string($zip)){
			$zpath = $zip;
			$zip = 	new ZipArchive();
			if(!($zip->open($zpath, ZipArchive::OVERWRITE)===TRUE)){
				die('压缩文件创建失败!'.$zpath);
			}
		}
		$handler = opendir($path); //打开当前文件夹由$path指定。
		while(($filename=readdir($handler))!==false){
			if($filename != "." && $filename != ".."){
				if(is_dir($path."/".$filename)){// 如果读取的某个对象是文件夹，则递归
					self::add_file_to_zip($path."/".$filename, $zip,$ziproot."/".$filename);
				}else{ //将文件加入zip对象
					$zip->addFile($path."/".$filename,$ziproot."/".$filename);
				}
			}
		}
		@closedir($path);
		if($zpath!=null){
			$zip->close();
		}		
	}
	
	
	/**
	 * 把zip解压缩
	 *
	 * @param string $path
	 * @param string $zip
	 */
	public static function unzip_to_file($path,$zippath){
		$zip=new ZipArchive();
		if($zip->open($zippath)===TRUE){
			$zip->extractTo($path);
			$zip->close();
		}
	}
}