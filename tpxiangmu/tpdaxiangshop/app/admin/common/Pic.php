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
use think\facade\Db;
class Pic
{
	private static $lastError = '';

	public static function getLastError()
	{
		return self::$lastError;
	}

	private static function setLastError($msg)
	{
		self::$lastError = trim((string)$msg);
	}

	private static function cloudBaseUrl($conf)
	{
		if(!is_array($conf)){
			return '';
		}
		$url = trim((string)($conf['url'] ?? ''));
		if($url === ''){
			$url = trim((string)($conf['domain'] ?? ''));
		}
		return rtrim($url, '/');
	}

	private static function startsWithCloudUrl($picurl, $cloudUrl)
	{
		if($cloudUrl === ''){
			return false;
		}
		return strpos((string)$picurl, $cloudUrl) === 0;
	}

	//远程图片保存到本地
	public static function tolocal($picurl,$store=false){
		if(strpos($picurl,PRE_URL) !== 0){ //非本地
			if(!$picurl) return '';
			if($store){
				$info = db('pictolocal')->where('pic',$picurl)->find();
				if($info){
					return $info['url'];
				}
			}
			$picurlArr = explode('.',$picurl);
			$ext = end($picurlArr);
			if(!$ext || strlen($ext) > 6) $ext = 'jpg';
			if(!in_array($ext,explode(',',config('app.upload_type')))){
				return '';
			}
			$dir = 'upload/'.date('Ym');
			$filename = date('d_His').rand(1000,9999).'.'.$ext;
			$mediapath = $dir.'/'.$filename;
			$piccontent = request_get($picurl);
			$filePath = self::getPublicFilePath($mediapath);
			self::ensureDirectory(dirname($filePath));
			file_put_contents($filePath,$piccontent);
			$url = PRE_URL.'/'.$mediapath;
			if($store){
				db('pictolocal')->insert(['pic'=>$picurl,'url'=>$url,'createtime'=>time()]);
			}
		}else{
			$url = $picurl;
		}
		return $url;
	}
	//上传图片
	public static function uploadoss($picurl,$store=false,$actualFilePath=null,$aidOverride=null){
		self::$lastError = '';
		$oldpicurl = $picurl;
		if(!$oldpicurl) return '';
		$effectiveAid = intval($aidOverride);
		if($effectiveAid <= 0 && defined('aid') && aid > 0){
			$effectiveAid = intval(aid);
		}
		
		if($store){
			$info = db('pictolocal')->where('pic',$oldpicurl)->find();
			if($info){
				return $info['url'];
			}
		}
		if($effectiveAid > 0){
			$remoteset = db('admin')->where('id',$effectiveAid)->value('remote');
			$remoteset = json_decode($remoteset,true);
			if(!$remoteset || $remoteset['type']==0){
				$remoteset = db('sysset')->where('name','remote')->value('value');
				$remoteset = json_decode($remoteset,true);
			}
		}else{
			$remoteset = db('sysset')->where('name','remote')->value('value');
			$remoteset = json_decode($remoteset,true);
		}
		
		//dump($remoteset);
		if($remoteset['type']==2){ //阿里云
			$alyunossConf = is_array($remoteset['alioss'] ?? null) ? $remoteset['alioss'] : [];
			$cloudUrl = self::cloudBaseUrl($alyunossConf);
			if($cloudUrl === '' || trim((string)($alyunossConf['key'] ?? '')) === '' || trim((string)($alyunossConf['bucket'] ?? '')) === ''){
				self::setLastError('阿里云OSS配置不完整，请填写 Key、Secret、Bucket、节点和域名');
				return $oldpicurl;
			}
			if(self::startsWithCloudUrl($picurl,$cloudUrl)) return $picurl;
			$picurl = \app\admin\common\Pic::tolocal($picurl);
			$accessKeyId = $alyunossConf['key'];
			$accessKeySecret = $alyunossConf['secret'];
			$endpoint = 'http://'.$alyunossConf['ossurl'];
			$bucket= $alyunossConf['bucket'];
			// 文件名称
			$object = self::getObjectPathFromLocalUrl($picurl);
			
			$filePath = self::resolveObjectFilePath($object, $actualFilePath);
			
			if(!$filePath){
				self::setLastError('阿里云OSS上传失败：本地文件不存在');
				return $oldpicurl;
			}
			try{
				$ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
				$ossClient->uploadFile($bucket, $object, $filePath);
				@unlink($filePath);
				$picurl = $cloudUrl.'/'.$object;
				if($store){
					db('pictolocal')->insert(['pic'=>$oldpicurl,'url'=>$picurl,'createtime'=>time()]);
				}
				return $picurl;
			} catch(OssException $e) {
				self::setLastError('阿里云OSS上传失败：'.$e->getMessage());
				return $oldpicurl;
			}
		}elseif($remoteset['type']==3){ //七牛云
			$qiniuConf = is_array($remoteset['qiniu'] ?? null) ? $remoteset['qiniu'] : [];
			$cloudUrl = self::cloudBaseUrl($qiniuConf);
			if($cloudUrl === '' || trim((string)($qiniuConf['accesskey'] ?? '')) === '' || trim((string)($qiniuConf['bucket'] ?? '')) === ''){
				self::setLastError('七牛云配置不完整，请填写 Accesskey、Secretkey、Bucket 和域名');
				return $oldpicurl;
			}
			if(self::startsWithCloudUrl($picurl,$cloudUrl)) return $picurl;
			$picurl = \app\admin\common\Pic::tolocal($picurl);

			$auth = new \Qiniu\Auth($qiniuConf['accesskey'], $qiniuConf['secretkey']);
			$token = $auth->uploadToken($qiniuConf['bucket']);
			$object = self::getObjectPathFromLocalUrl($picurl);

			$filePath = self::resolveObjectFilePath($object, $actualFilePath);

			if(!$filePath){
				self::setLastError('七牛云上传失败：本地文件不存在');
				return $oldpicurl;
			}
			try{
				$reqOpt = class_exists('\Qiniu\Http\RequestOptions') ? new \Qiniu\Http\RequestOptions(30, null, 600) : null;
				$uploadMgr = new \Qiniu\Storage\UploadManager(null, $reqOpt);
				list($ret, $err) = $uploadMgr->putFile($token, $object, $filePath);
				if ($err !== null) {
					$errMsg = method_exists($err, 'message') ? $err->message() : json_encode($err, JSON_UNESCAPED_UNICODE);
					\think\facade\Log::error('七牛云上传失败: '.$errMsg);
					self::setLastError('七牛云上传失败：'.$errMsg);
					return $oldpicurl;
				} else {
					// 备份文件到本地qiniuupload目录
					$backupPath = self::getQiniuBackupFilePath($object);
					$backupDir = dirname($backupPath);
					self::ensureDirectory($backupDir);
					if (file_exists($filePath)) {
						copy($filePath, $backupPath);
					}

					// 删除原始文件
					@unlink($filePath);
					$picurl = $cloudUrl.'/'.$object;
					if($store){
						db('pictolocal')->insert(['pic'=>$oldpicurl,'url'=>$picurl,'createtime'=>time()]);
					}
					return $picurl;
				}
			} catch(\Exception $e) {
				\think\facade\Log::error('七牛云上传异常: '.$e->getMessage());
				self::setLastError('七牛云上传异常：'.$e->getMessage());
				return $oldpicurl;
			}
		}elseif($remoteset['type']==4){ //腾讯云
			$cosConf = is_array($remoteset['cos'] ?? null) ? $remoteset['cos'] : [];
			$cloudUrl = self::cloudBaseUrl($cosConf);
			if($cloudUrl === '' || trim((string)($cosConf['secretid'] ?? '')) === '' || trim((string)($cosConf['bucket'] ?? '')) === ''){
				self::setLastError('腾讯云COS配置不完整，请填写密钥、Bucket、地域和域名');
				return $oldpicurl;
			}
			if(self::startsWithCloudUrl($picurl,$cloudUrl)) return $picurl;
			$picurl = \app\admin\common\Pic::tolocal($picurl);
			$secretId = $cosConf['secretid'];
			$secretKey = $cosConf['secretkey'];
			$region = $cosConf['local'];
			$bucket = str_replace("-".$cosConf['appid'],'',$cosConf['bucket'])."-".$cosConf['appid'];
			$object = self::getObjectPathFromLocalUrl($picurl);
			
			$filePath = self::resolveObjectFilePath($object, $actualFilePath);

			if(!$filePath){
				self::setLastError('腾讯云COS上传失败：本地文件不存在');
				return $oldpicurl;
			}
			try {
				if(!class_exists('\Qcloud\Cos\Client')) {
					\think\facade\Log::error('腾讯云COS SDK未安装，跳过远程上传');
					self::setLastError('腾讯云COS SDK未安装');
					return $oldpicurl;
				}
				$cosClient = new \Qcloud\Cos\Client(array(
					'region' => $region,
					//'schema' => 'https', //协议头部，默认为http
					'credentials'=> array('secretId'  => $secretId ,'secretKey' => $secretKey)
				));
				$result = $cosClient->putObject([
					'Bucket'=>$bucket,
					'Key'=>$object,
					'Body'=>fopen($filePath, 'rb'),
					'ACL'=>'public-read'
				]);
				@unlink($filePath);
				$picurl = $cloudUrl.'/'.$object;
				if($store){
					db('pictolocal')->insert(['pic'=>$oldpicurl,'url'=>$picurl,'createtime'=>time()]);
				}
				return $picurl;
			} catch (\Throwable $e){
				\think\facade\Log::error('腾讯云COS上传失败: '.$e->getMessage());
				self::setLastError('腾讯云COS上传失败：'.$e->getMessage());
				return $oldpicurl;
			}
		}else{
			// 没有配置云存储或配置为本地存储
			return $oldpicurl;
		}
	}

	private static function getProjectRootPath(){
		if(function_exists('app')){
			try{
				$root = app()->getRootPath();
				if($root){
					return rtrim(str_replace('\\','/',$root),'/').'/';
				}
			}catch(\Throwable $e){}
		}

		if(defined('ROOT_PATH')){
			$root = rtrim(str_replace('\\','/',ROOT_PATH),'/');
			if(basename($root) === 'public'){
				$root = dirname($root);
			}
			return rtrim($root,'/').'/';
		}

		return rtrim(str_replace('\\','/',dirname(__DIR__,3)),'/').'/';
	}

	private static function normalizeRelativePath($path){
		$path = str_replace('\\','/',trim((string)$path));
		$path = ltrim($path,'/');
		if(strpos($path,'public/') === 0){
			$path = substr($path,7);
		}
		return preg_replace('#/+#','/',$path);
	}

	private static function getPublicFilePath($relativePath){
		return self::getProjectRootPath().'public/'.self::normalizeRelativePath($relativePath);
	}

	private static function getLegacyFilePath($relativePath){
		return self::getProjectRootPath().self::normalizeRelativePath($relativePath);
	}

	private static function getQiniuBackupFilePath($object){
		return self::getProjectRootPath().'qiniuupload/'.self::normalizeRelativePath($object);
	}

	private static function ensureDirectory($dir){
		if(!is_dir($dir)){
			mk_dir($dir,0755,true);
		}
	}

	private static function getObjectPathFromLocalUrl($url){
		$url = trim((string)$url);
		if(defined('PRE_URL') && PRE_URL !== '' && strpos($url,PRE_URL) === 0){
			$url = substr($url,strlen(PRE_URL));
		}else{
			$path = parse_url($url,PHP_URL_PATH);
			if($path !== null && $path !== false && $path !== ''){
				$url = $path;
			}
		}
		return self::normalizeRelativePath($url);
	}

	private static function resolveObjectFilePath($object,$actualFilePath=null){
		if(is_string($actualFilePath) && $actualFilePath !== '' && file_exists($actualFilePath)){
			return $actualFilePath;
		}

		$candidates = [
			self::getPublicFilePath($object),
			self::getLegacyFilePath($object),
		];

		if(defined('ROOT_PATH')){
			$rootPath = rtrim(str_replace('\\','/',ROOT_PATH),'/');
			$candidates[] = $rootPath.'/'.self::normalizeRelativePath($object);
			$candidates[] = rtrim(dirname($rootPath),'/').'/'.self::normalizeRelativePath($object);
		}

		foreach(array_unique($candidates) as $filePath){
			if($filePath && file_exists($filePath)){
				return $filePath;
			}
		}

		return '';
	}
	//删除图片
	public static function deletepic($picurl){
		if(defined('aid') && aid > 0){ 
			$remoteset = db('admin')->where('id',aid)->value('remote');
			$remoteset = json_decode($remoteset,true);
			if(!$remoteset || $remoteset['type']==0){
				$remoteset = db('sysset')->where('name','remote')->value('value');
				$remoteset = json_decode($remoteset,true);
			}
		}else{
			$remoteset = db('sysset')->where('name','remote')->value('value');
			$remoteset = json_decode($remoteset,true);
		}
		if(strpos($picurl,PRE_URL) !== 0 && !getcustom('retain_osspic')){ //非本地
			if($remoteset['type']==2){ //阿里云
				$alyunossConf = $remoteset['alioss'];
				if(strpos($picurl,$alyunossConf['url']) !== 0) return ['status'=>0,'msg'=>''];
				$accessKeyId = $alyunossConf['key'];
				$accessKeySecret = $alyunossConf['secret'];
				$endpoint = 'http://'.$alyunossConf['ossurl'];
				$bucket= $alyunossConf['bucket'];
				// 文件名称
				$object = ltrim(str_replace($alyunossConf['url'],'',$picurl),'/');
				try{
					$ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
					$ossClient->deleteObject($bucket, $object);
					return ['status'=>1,'msg'=>''];
				} catch(OssException $e) {
					return ['status'=>0,'msg'=>''];
				}
			}elseif($remoteset['type']==3){ //七牛云
				$qiniuConf = $remoteset['qiniu'];
				if(strpos($picurl,$qiniuConf['url']) !== 0) return ['status'=>0,'msg'=>''];
				$auth = new \Qiniu\Auth($qiniuConf['accesskey'], $qiniuConf['secretkey']);
				$object = ltrim(str_replace($qiniuConf['url'],'',$picurl),'/');
				$config = new \Qiniu\Config();
				$bucketManager = new \Qiniu\Storage\BucketManager($auth, $config);
				$err = $bucketManager->delete($qiniuConf['bucket'],$object);
				return ['status'=>1,'msg'=>''];
			}elseif($remoteset['type']==4){ //腾讯云
				$cosConf = $remoteset['cos'];
				if(strpos($picurl,$cosConf['url']) !== 0) return ['status'=>0,'msg'=>''];
				$secretId = $cosConf['secretid'];
				$secretKey = $cosConf['secretkey'];
				$region = $cosConf['local'];
				$bucket = str_replace("-".$cosConf['appid'],'',$cosConf['bucket'])."-".$cosConf['appid'];
				$object = ltrim(str_replace($cosConf['url'],'',$picurl),'/');
				try {
					if(!class_exists('\Qcloud\Cos\Client')) {
						return ['status'=>0,'msg'=>'腾讯云COS SDK未安装'];
					}
					$cosClient = new \Qcloud\Cos\Client(array(
						'region' => $region,
						//'schema' => 'https', //协议头部，默认为http
						'credentials'=> array('secretId'  => $secretId ,'secretKey' => $secretKey)
					));
					$result = $cosClient->deleteObject(['Bucket'=>$bucket,'Key'=>$object]);
					return ['status'=>1,'msg'=>''];
				} catch (\Throwable $e){
					echojson(['status'=>0,'msg'=>$e->getMessage()]);
					return $picurl;
				}
			}
		}else{
			$filePath = ROOT_PATH.ltrim(str_replace(PRE_URL,'',$picurl),'/');
			@unlink($filePath);
			return ['status'=>1,'msg'=>''];
		}
	}
}
