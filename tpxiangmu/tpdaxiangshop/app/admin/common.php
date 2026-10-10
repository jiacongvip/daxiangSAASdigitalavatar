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

// 应用公共文件
if (!function_exists('getAccessSource')) {
function getAccessSource() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];

    if (strpos($userAgent, 'MicroMessenger') && strpos($userAgent, 'iniProgram')) {
        return 'wx_m';
    } elseif (strpos($userAgent, 'MicroMessenger')) {
        return 'wx_g';
    } elseif (strpos($userAgent, 'BaiduApp')) {
        return 'baidu_m';
    } elseif (strpos($userAgent, 'Douyin')) {
        return 'douyin_m';
    } elseif (strpos($userAgent, 'iPhone') || strpos($userAgent, 'Android')) {
        // 这里假设如果是手机端且不是上述特定的小程序，就认为是 APP
        return 'app';
    } else {
        return 'h5';
    }
}
}

if (!function_exists('jsonEncode')) {
function jsonEncode($arr){
	return json_encode($arr, JSON_UNESCAPED_UNICODE);
}
}
if (!function_exists('request_post')) {
function request_post($url, $keysArr=array(), $timeout = 30){
	$client = new \GuzzleHttp\Client(['timeout'=>$timeout,'verify'=>false]);
	try {
		if(is_array($keysArr)){
			$response = $client->request('POST',$url,['form_params'=>$keysArr]);
		}else{
			$response = $client->request('POST',$url,['body'=>$keysArr]);
		}
		$rs = $response->getBody()->getContents();
	} catch(\Throwable $e) {
		\think\facade\Log::write($e->getMessage());
		//var_dump($e->getMessage());
	}
	return $rs;
}
}

if (!function_exists('request_get')) {
function request_get($url, $keysArr=array(), $timeout = 30){
	$client = new \GuzzleHttp\Client(['timeout'=>$timeout,'verify'=>false]);
	try {
		if($keysArr){
			$response = $client->request('GET',$url,['query'=>$keysArr]);
		}else{
			$response = $client->request('GET',$url);
		}
		$rs = $response->getBody()->getContents();
	} catch(\Throwable $e){
		\think\facade\Log::write($e->getMessage());
		//var_dump($e->getMessage());
	}
	return $rs;
}
}

//curl post请求
if (!function_exists('curl_post')) {
function curl_post($url, $keysArr=array(), $flag = 0,$headers=array()){
	$ch = curl_init();
	if(! $flag) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $keysArr);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	if($headers){
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	try {
		$response = curl_exec($ch);
	} catch (\Exception $e) {
       return null;
    }
	$curlError = curl_error($ch);
    if(!empty($curlError)) {
       return null;
    }
	curl_close($ch);
	//-------请求为空
	if(empty($response)){
		return null;
	}
	return $response;
}
}

if (!function_exists('curl_form_post')) {
function curl_form_post($url, $keysArr){
	$post_data = http_build_query($keysArr);//重点
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));//重点
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
}
}

//curl get请求
if (!function_exists('curl_get')) {
function curl_get($url,$keysArr=array(),$headers=array()){
	if(!empty($keysArr)){
		$url = strpos($url,'?')===false ? ($url."?") : ($url."&");
		$valueArr = array();
		foreach($keysArr as $key => $val){
			$valueArr[] = "$key=$val";
		}
		$keyStr = implode("&",$valueArr);
		$url .= ($keyStr);
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	if($headers){
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	try {
		$response = curl_exec($ch);
	} catch (\Exception $e) {
       return null;
    }
	$curlError = curl_error($ch);
    if(!empty($curlError)) {
       return null;
    }
	curl_close($ch);
	//-------请求为空
	if(empty($response)){
		return null;
	}
	return $response;
}
}

//经纬度转省市区
if (!function_exists('getLocationInfo')) {
function getLocationInfo($latitude, $longitude, $key = '') {
    return \app\admin\common\MapQuery::getLocationInfo($latitude, $longitude, $key);
}
}


/**
 * 使用fsocketopen()方式发送异步请求,put方式
 */
if (!function_exists('syncRequest')) {
function syncRequest($url,$post_data="",$cookie=[], $debug=false){
	$url_array = parse_url($url);
	$hostname = $url_array['host'];
	$port = isset($url_array['port'])? $url_array['port'] : 443;
	$requestPath = $url_array['path'] ."?". $url_array['query'];
	$fp = fsockopen('ssl://'.$hostname, $port, $errno, $errstr, 10);
	//var_dump($fp);
	if (!$fp) {
	  echo "$errstr ($errno)";
	  return false;
	}
	$cookie_str = '';
	foreach ($cookie as $k => $v) {
		$cookie_str .= urlencode($k) .'='. urlencode($v) .'; ';
	}

	$method = "GET";

	if(!empty($post_data)){
	  $method = "POST";
	}
	$header = "$method $requestPath HTTP/1.1\r\n";
	$header.="Host: $hostname\r\n";

	$crlf = "\r\n";
	if (!empty($cookie_str)) {
		$header .= 'Cookie: '. substr($cookie_str, 0, -2) . $crlf;
	}

	if(!empty($post_data)){
		$_post = $post_data;
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";//POST数据
		$header .= "Content-Length: ". strlen($_post) ."\r\n";//POST数据的长度
		$header.="Connection: Close\r\n\r\n";//长连接关闭
		$header .= $_post . "\r\n\r\n"; //传递POST数据
	}else{
		$header.="Connection: Close\r\n\r\n";//长连接关闭
	}
	//var_dump($header);
	fwrite($fp, $header);
	//usleep(1000);
	//-----------------调试代码区间-----------------
	//注如果开启下面的注释,异步将不生效可是方便调试
	if($debug){
		$html = '';
		while (!feof($fp)) {
			$html.=fgets($fp);
		}
		//var_dump($html);
	}
	//-----------------调试代码区间-----------------
	fclose($fp);
}
}


//循环创建目录
if (!function_exists('mk_dir')) {
function mk_dir($dir, $mode = 0777) {
    if (is_dir($dir) || @mkdir($dir, $mode))
        return true;
    if (!mk_dir(dirname($dir), $mode))
        return false;
    return @mkdir($dir, $mode);
}
}
/**
 * 数字转字母 （类似于Excel列标）
 * @param Int $index 索引值
 * @param Int $start 字母起始值
 * @return String 返回字母
 */
if (!function_exists('IntToChr')) {
function IntToChr($index, $start = 65) {
    $str = '';
    if (floor($index / 26) > 0) {
        $str .= IntToChr(floor($index / 26)-1);
    }
    return $str . chr($index % 26 + $start);
}
}

if (!function_exists('random')) {
function random($length, $numeric = false) {
	$seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
	if ($numeric) {
		$hash = '';
	} else {
		$hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
		--$length;
	}
	$max = strlen($seed) - 1;
	for ($i = 0; $i < $length; ++$i) {
		$hash .= $seed[mt_rand(0, $max)];
	}

	return $hash;
}
}

//文件大小格式转换
if (!function_exists('sizecount')) {
function sizecount($size) {
	if($size >= 1073741824) {
		$size = round($size / 1073741824 * 100) / 100 . ' GB';
	} elseif($size >= 1048576) {
		$size = round($size / 1048576 * 100) / 100 . ' MB';
	} elseif($size >= 1024) {
		$size = round($size / 1024 * 100) / 100 . ' KB';
	} else {
		$size = $size . ' Bytes';
		$size = round($size / 1024 * 100) / 100 . ' KB';
	}
	return $size;
}
}

//数组转XML
if (!function_exists('array2xml')) {
function array2xml($arr, $level = 1) {
	$s = $level == 1 ? "<xml>" : '';
	foreach ($arr as $tagname => $value) {
		if (is_numeric($tagname)) {
			$tagname = $value['TagName'];
			unset($value['TagName']);
		}
		if (!is_array($value)) {
			$s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
		} else {
			$s .= "<{$tagname}>" . array2xml($value, $level + 1) . "</{$tagname}>";
		}
	}
	$s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
	return $level == 1 ? $s . "</xml>" : $s;
}
}

//数组去键值
if (!function_exists('arrayMap')) {
	function arrayMap($arr, $key) {
		return array_map(function($value) use($key) { return $value[$key]; }, $arr);
	}
}

//生成毫秒数
if (!function_exists('msectime')) {
function msectime() {
	list($msec, $sec) = explode(' ', microtime());
	$msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
	return $msectime;
}
}

if (!function_exists('echojson')) {
function echojson($data){
	header('Content-type: application/json');
	echo jsonEncode($data);die;
}
}
//阿里云查物流接口
if (!function_exists('ali_getwuliu')) {
function ali_getwuliu($no,$typename){
	return \app\admin\common\ExpressQuery::query($no, $typename);
}
}

//快递100 查快递
if (!function_exists('kuaidi100_getwuliu')) {
function kuaidi100_getwuliu($key,$customer,$param=array()){
	//请求参数
	$post_data = array();
	$post_data["customer"] = $customer;
	$post_data["param"] = json_encode($param);
	$sign = md5($post_data["param"].$key.$customer);
	$post_data["sign"] = strtoupper($sign);
	$url = 'http://poll.kuaidi100.com/poll/query.do';    //实时查询请求地址
	$params = "";
	foreach ($post_data as $k=>$v) {
		$params .= "$k=".urlencode($v)."&";              //默认UTF-8编码格式
	}
	$post_data = substr($params, 0, -1);
	//发送post请求
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	return curl_exec($ch);
}
}
//rgb转换
if (!function_exists('hex2rgb')) {
function hex2rgb($colour) {
	if ($colour[0] == '#') {
		$colour = substr($colour, 1);
	}
	if (strlen($colour) == 6) {
		list($r, $g, $b) = array($colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5]);
	} elseif (strlen($colour) == 3) {
		list($r, $g, $b) = array($colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2]);
	} else {
		return false;
	}
	$r = hexdec($r);
	$g = hexdec($g);
	$b = hexdec($b);
	return ['red' => $r, 'green' => $g, 'blue' => $b];
}
}
//计算两组经纬度坐标 之间的距离  lat1 纬度1； lng1 经度1； lat2 纬度2； lng2 经度2； len_type （1:m or 2:km);  return m or km
if (!function_exists('getdistance')) {
function getdistance($lng1, $lat1, $lng2, $lat2, $len_type = 1, $decimal = 2){
	$radLat1 = $lat1 * PI()/ 180.0;   //PI()圆周率
	$radLat2 = $lat2 * PI() / 180.0;
	$a = $radLat1 - $radLat2;
	$b = ($lng1 * PI() / 180.0) - ($lng2 * PI() / 180.0);
	$s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1) * cos($radLat2) * pow(sin($b/2),2)));
	$s = $s * 6378.137;
	$s = round($s * 1000);
	if ($len_type --> 1) {
	   $s /= 1000;
	}
	return round($s, $decimal);
}
}
//显示时间
if (!function_exists('getshowtime')) {
function getshowtime($time){
	//if(time() - $time < 60){
	//	return '刚刚';
	//}elseif(time() - $time < 3600){
	//	$minite = ceil((time() - $time)/60);
	//	return $minite.'分钟前';
	//}else
	if(date('Ymd')==date('Ymd',$time)){
		return date('H:i',$time);
	}elseif(time()-$time<86400){
		return '昨天 '.date('H:i',$time);
	}elseif(date('Y')==date('Y',$time)){
		return date('m-d H:i',$time);
	}else{
		return date('Y-m-d H:i',$time);
	}
}
}
//评论内容显示
if (!function_exists('getshowcontent')) {
function getshowcontent($content){
	$icontxtArr = ["[微笑]", "[撇嘴]", "[色]", "[发呆]", "[得意]", "[流泪]", "[害羞]", "[闭嘴]", "[睡]", "[大哭]", "[尴尬]", "[发怒]", "[调皮]", "[呲牙]", "[惊讶]", "[难过]", "[酷]", "[囧]", "[抓狂]", "[吐]", "[偷笑]", "[愉快]", "[白眼]", "[傲慢]", "[饥饿]", "[困]", "[惊恐]", "[流汗]", "[憨笑]", "[悠闲]", "[奋斗]", "[咒骂]", "[疑问]", "[嘘]", "[晕]", "[折磨]", "[衰]", "[骷髅]", "[敲打]", "[再见]", "[擦汗]", "[抠鼻]", "[鼓掌]","[糗大了]", "[坏笑]", "[左哼哼]", "[右哼哼]", "[哈欠]", "[鄙视]", "[委屈]", "[快哭了]", "[阴险]", "[亲亲]","[吓]", "[可怜]", "[菜刀]", "[西瓜]", "[啤酒]", "[篮球]", "[乒乓]", "[咖啡]", "[饭]", "[猪头]", "[玫瑰]", "[凋谢]", "[嘴唇]", "[爱心]", "[心碎]", "[蛋糕]","[闪电]", "[炸弹]", "[刀]" ,"[足球]", "[瓢虫]", "[便便]", "[月亮]", "[太阳]","[礼物]", "[拥抱]", "[强]", "[弱]", "[握手]", "[胜利]", "[抱拳]", "[勾引]", "[拳头]", "[差劲]","[爱你]","[NO]","[OK]", "[跳跳]", "[发抖]", "[怄火]", "[转圈]"];
	$imgArr = [];
	for($i=0;$i<94;$i++){
		$imgArr[] = '<img src="'.PRE_URL.'/static/chat/wxface/'.$i.'.png" class="wxfaceimg" style="width:50rpx;display:inline-block">';
	}
	$content = str_replace($icontxtArr,$imgArr,$content);
	return $content;
}
}

//苹果手机上传图片出现歪倒的情况解决函数 需要安装exif扩展
if (!function_exists('iphoneimgrotate')) {
function iphoneimgrotate($path){
	if(function_exists('exif_read_data')){
		$exif = exif_read_data($path);
		//\think\facade\Log::write($exif);
		if(!empty($exif['Orientation'])) {
			$source = imagecreatefromjpeg($path);
			switch($exif['Orientation']) {
				case 8:
					$source = imagerotate($source,90,0);
					break;
				case 3:
					$source = imagerotate($source,180,0);
					break;
				case 6:
					$source = imagerotate($source,-90,0);
					break;
			}
			imagejpeg($source,$path);  //存储图片
		}
	}
}
}
//是否微信浏览器
if (!function_exists('is_weixin')) {
function is_weixin() {
	if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
		return true;
    }
	return false;
}
}
//是否是移动端
if (!function_exists('isMobile')) {
function isMobile(){
    if (isset ($_SERVER['HTTP_X_WAP_PROFILE'])){
        return true;
    }
    if (isset ($_SERVER['HTTP_VIA'])){
        return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
    }
    if (isset ($_SERVER['HTTP_USER_AGENT'])){
        $clientkeywords = array ('nokia','sony','ericsson','mot','samsung','htc','sgh','lg','sharp','sie-','philips','panasonic','alcatel','lenovo','iphone','ipod','blackberry','meizu','android','netfront','symbian','ucweb','windowsce','palm','operamini','operamobi','openwave','nexusone','cldc','midp','wap','mobile');
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))){
            return true;
        }
    }
    if (isset ($_SERVER['HTTP_ACCEPT'])){
        if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))){
            return true;
        }
    }
    return false;
}
}

if (!function_exists('getaddressfromip')) {
function getaddressfromip($ip){
	$opts = array('http'=>array('method'=>"GET",'timeout'=>1));
	$context = stream_context_create($opts);
	$mip = file_get_contents("http://whois.pconline.com.cn/ip.jsp?ip=".$ip,false,$context); //太平洋电脑网接口
	$mip = iconv("gbk","utf-8",$mip);
	$str = str_replace("\n",'',str_replace("\r",'',$mip));
	return $str;
}
}

if (!function_exists('getaddressfromtel')) {
function getaddressfromtel($tel){
	$host = "https://jisusjhmcx.market.alicloudapi.com";//api访问链接
	$path = "/shouji/query";//API访问后缀
	$method = "GET";
	$appcode = "609ee6436aaf444a8d17feebe6feb8f0";//替换成自己的阿里云appcode
	$headers = array();
	array_push($headers, "Authorization:APPCODE " . $appcode);
	$querys = "shouji={$tel}";  //参数写在这里
	$bodys = "";
	$url = $host . $path . "?" . $querys;//url拼接

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_FAILONERROR, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, false);
	//curl_setopt($curl, CURLOPT_HEADER, true); 如不输出json, 请打开这行代码，打印调试头部状态码。
	//状态码: 200 正常；400 URL无效；401 appCode错误； 403 次数用完； 500 API网管错误
	if (1 == strpos("$".$host, "https://"))
	{
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	}
	$rs = curl_exec($curl);
	$rs = json_decode($rs,true);
	$result = $rs['result'];
	if($result && $result['province']){
		$provinceArr = [
			'北京'=>'北京市',
			'天津'=>'天津市',
			'上海'=>'重庆市',
			'重庆'=>'重庆市',
			'内蒙古'=>'内蒙古自治区',
			'广西'=>'广西壮族自治区',
			'西藏'=>'西藏自治区',
			'宁夏'=>'宁夏回族自治区',
			'新疆'=>'新疆维吾尔自治区',
			'香港'=>'香港特别行政区',
			'澳门'=>'澳门特别行政区',
		];
		if($provinceArr[$result['province']]){
			$result['province'] = $provinceArr[$result['province']];
		}else{
			$result['province'] = $result['province'].'省';
		}
		$cityArr = [
			'昌吉'=>'昌吉回族自治区',
			'博尔塔拉'=>'博尔塔拉蒙古自治区',
			'巴音郭楞'=>'巴音郭楞蒙古自治区',
			'阿克苏'=>'阿克苏地区',
			'克孜勒苏'=>'克孜勒苏柯尔克孜自治州',
			'喀什'=>'喀什地区',
			'和田'=>'和田地区',
			'伊犁'=>'伊犁哈萨克自治州',
			'塔城'=>'塔城地区',
			'阿勒泰'=>'阿勒泰地区',
		];
		if($cityArr[$result['city']]){
			$result['city'] = $cityArr[$result['city']];
		}else{
			$result['city'] = $result['city'].'市';
		}
	}
	return $result;
}
}

//信息提示
if (!function_exists('showmsg')) {
function showmsg($msg,$status=0,$url='',$msg2='确定'){
	if(request()->isAjax() || platform=='wx'){
		echojson(['status'=>$status,'msg'=>$msg,'url'=>$url]);
	}
	$gotohtml = '';
	if($url){
		$gotohtml = '<a class="err_fh" href="'.$url.'">'.$msg2.'</a>';
	}else{
		if($status==0 && $_SERVER['HTTP_REFERER']){
			$gotohtml = '<a class="err_fh" href="'.$_SERVER['HTTP_REFERER'].'">返回</a>';
		}
	}

	echo '<!DOCTYPE html><html><head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width,minimum-scale=1.0,minimum-scale=1.0,maximum-scale=1.0,initial-scale=1.0,user-scalable=no">
	<meta name="robots" content="index, follow" />
	<style>
	* {margin: 0px;padding: 0px;font-family: "微软雅黑";font-size: 14px;}
	body {background: #e31a2c;}
	img {display: block;}
	ul,ol,li {list-style-type: none;}
	a {text-decoration: none;display: block;color: #7e7e7e;}
	.bg_f5{background: #f5f5f5;max-width: 640px;margin: 0 auto;}
	.txt_error{text-align: center;color: #999;font-size: 16px;margin-top: 20px;padding:0 5px}
	.txt_success{text-align: center;color: #333;font-size: 18px;margin-top: 20px;padding:0 5px}
	.error-img{width: 30%;margin: 0 auto;margin-top: 35%;}
	.err_fh{width:110px;height: 28px;line-height: 28px;border: 1px solid #f60;color: #f60;margin: 0 auto;text-align: center;margin-top: 50px;border-radius: 15px;}
	</style>
	<title>信息提示</title>
	</head>
	<body class="bg_f5">
		<img src="'.PRE_URL.'/static/img/'.($status==0?'error':'success').'.png" class="error-img"/>
		<p class="txt_'.($status==0?'error':'success').'">'.$msg.'</p>
		'.$gotohtml.'
	</body></html>';
	die;
}
}

if (!function_exists('unescape')) {
function unescape($str){
	$ret = '';
	$len = strlen($str);
	for ($i = 0;$i < $len;$i ++) {
		if ($str[$i] == '%' && $str[$i + 1] == 'u') {
			$val = hexdec(substr($str, $i + 2, 4));
			if ($val < 0x7f)$ret .= chr($val);
			else if ($val < 0x800)$ret .= chr(0xc0 | ($val >> 6)) . chr(0x80 | ($val &0x3f));
			else $ret .= chr(0xe0 | ($val >> 12)) . chr(0x80 | (($val >> 6)&0x3f)) . chr(0x80 | ($val &0x3f));
			$i += 5;
		} else if ($str[$i] == '%') {
			if(substr($str, $i, 3) == '%A1'){
				$ret.='¡';
			}elseif(substr($str, $i, 3) == '%A2'){
				$ret.='¢';
			}elseif(substr($str, $i, 3) == '%A3'){
				$ret.='£';
			}elseif(substr($str, $i, 3) == '%A4'){
				$ret.='¤';
			}elseif(substr($str, $i, 3) == '%A5'){
				$ret.='¥';
			}elseif(substr($str, $i, 3) == '%A6'){
				$ret.='¦';
			}elseif(substr($str, $i, 3) == '%A7'){
				$ret.='§';
			}elseif(substr($str, $i, 3) == '%A8'){
				$ret.='¨';
			}elseif(substr($str, $i, 3) == '%A9'){
				$ret.='©';
			}elseif(substr($str, $i, 3) == '%AA'){
				$ret.='ª';
			}elseif(substr($str, $i, 3) == '%AE'){
				$ret.='®';
			}elseif(substr($str, $i, 3) == '%B0'){
				$ret.='°';
			}elseif(substr($str, $i, 3) == '%B1'){
				$ret.='±';
			}elseif(substr($str, $i, 3) == '%B2'){
				$ret.='²';
			}elseif(substr($str, $i, 3) == '%B3'){
				$ret.='³';
			}elseif(substr($str, $i, 3) == '%B4'){
				$ret.='´';
			}elseif(substr($str, $i, 3) == '%B5'){
				$ret.='µ';
			}elseif(substr($str, $i, 3) == '%B6'){
				$ret.='¶';
			}elseif(substr($str, $i, 3) == '%B7'){
				$ret.='·';
			}elseif(substr($str, $i, 3) == '%B8'){
				$ret.='¸';
			}elseif(substr($str, $i, 3) == '%B9'){
				$ret.='¹';
			}elseif(substr($str, $i, 3) == '%BC'){
				$ret.='¼';
			}elseif(substr($str, $i, 3) == '%BD'){
				$ret.='½';
			}elseif(substr($str, $i, 3) == '%BE'){
				$ret.='¾';
			}elseif(substr($str, $i, 3) == '%D7'){
				$ret.='×';
			}elseif(substr($str, $i, 3) == '%F7'){
				$ret.='÷';
			}else{
				$ret .= urldecode(substr($str, $i, 3));
			}
			$i += 2;
		} else $ret .= $str[$i];
	}
	return $ret;
}
}

//生成二维码
if (!function_exists('createqrcode')) {
function createqrcode($url,$logo=''){
	try {
		// 检查 GD 扩展是否可用
		if (!extension_loaded('gd') || !function_exists('gd_info')) {
			// GD 扩展不可用，使用在线二维码生成API作为备用方案
			return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($url);
		}

		$qrCode=new \Endroid\QrCode\QrCode();
		$qrCode->setText($url);
		$qrCode->setSize(300);
		$qrCode->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0));
		$qrCode->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0));
		$qrCode->setLabelFontSize(16);
		if($logo){
			$qrCode->setLogoPath($logo);
			$qrCode->setLogoSize(80);
		}
		$qrUrl = '/upload/qrcode/'.date('Ym/d_His').rand(1000,9999).'.jpg';
		$file = function_exists('qrcode_public_file') ? qrcode_public_file($qrUrl) : (ROOT_PATH.ltrim($qrUrl,'/'));
		if(!is_dir(dirname($file))){
			@mkdir(dirname($file),0777,true);
		}
		$qrCode->writeFile($file);
		return rtrim(strval(PRE_URL), '/') . $qrUrl;
	} catch (\Exception $e) {
		// 捕获所有异常，使用在线API作为备用方案
		\think\facade\Log::error('生成二维码失败：' . $e->getMessage());
		return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($url);
	}
}
}

if (!function_exists('express_data')) {
function express_data(){
	return array('申通快递'=>'STO','EMS'=>'EMS','顺丰速运'=>'SFEXPRESS','圆通速递'=>'YTO','中通快递'=>'ZTO','韵达快递'=>'YUNDA','天天快递'=>'TTKDEX','百世快递'=>'HTKY','全峰快递'=>'QFKD','德邦快递'=>'DEPPON','宅急送'=>'ZJS','如风达'=>'RFD','安信达'=>'ANXINDA','邦送物流'=>'','DHL快递'=>'DHL','大田物流'=>'DTW','EMS国际'=>'','国通快递'=>'GTO','共速达'=>'GSD','华宇物流'=>'HOAU','佳吉快运'=>'JIAJI','佳怡物流'=>'JIAYI','快捷快递'=>'FASTEXPRESS','龙邦速递'=>'LBEX','联邦快递'=>'FEDEX','联昊通'=>'LTS','能达速递'=>'ND56','全一快递'=>'APEX','全日通'=>'QRT','速尔快递'=>'SURE','TNT快递'=>'TNT','天地华宇'=>'HOAU','新邦物流'=>'XBWL','新蛋物流'=>'','优速快递'=>'UC56','中邮物流'=>'ZYWL','安能物流'=>'ANE','安能快递'=>'ANEEX','品骏快递'=>'PJKD','极兔快递'=>'JITU','京东'=>'JD',"自提"=>'',"其他"=>'');
}
}

//快递100
if (!function_exists('express_data100')) {
function express_data100(){
	return array('圆通速递'=>'yuantong','韵达快递'=>'yunda','中通快递'=>'zhongtong','申通快递'=>'shentong','百世快递'=>'huitongkuaidi','邮政快递包裹'=>'youzhengguonei','顺丰速运'=>'shunfeng','极兔速递'=>'jtexpress','EMS'=>'ems','京东物流'=>'jd','邮政标准快递'=>'youzhengbk','德邦快递'=>'debangkuaidi','德邦'=>'debangwuliu','圆通快运'=>'yuantongkuaiyun','宅急送'=>'zhaijisong','百世快运'=>'baishiwuliu','丰网速运'=>'fengwang','中通快运'=>'zhongtongkuaiyun','中通国际'=>'zhongtongguoji','安能快运'=>'annengwuliu');
}
}

//生成条形码
if (!function_exists('createbarcode')) {
function createbarcode($codetext){
	$codetext = strval($codetext);
	$url = '/upload/qrcode/'.date('Y/m/dHis').rand(1000,9999).'.jpg';
    $file = function_exists('qrcode_public_file') ? qrcode_public_file($url) : (ROOT_PATH.ltrim($url,'/'));
	if(!is_dir(dirname($file))){
		@mkdir(dirname($file),0777,true);
	}
	// 引用class文件夹对应的类
	require_once(ROOT_PATH.'extend/barcodegen/class/BCGFontFile.php');
	require_once(ROOT_PATH.'extend/barcodegen/class/BCGColor.php');
	require_once(ROOT_PATH.'extend/barcodegen/class/BCGDrawing.php');

	// 条形码的编码格式
	require_once(ROOT_PATH.'extend/barcodegen/class/BCGcode128.barcode.php');

	// 加载字体大小
	//$font = new BCGFontFile('./class/font/Arial.ttf', 18);

	//颜色条形码
	$color_black = new BCGColor(0, 0, 0);
	$color_white = new BCGColor(255, 255, 255);

	$drawException = null;
	try {
		$code = new BCGcode128();
		$code->setScale(2);
		$code->setThickness(30); // 条形码的厚度
		$code->setForegroundColor($color_black); // 条形码颜色
		$code->setBackgroundColor($color_white); // 空白间隙颜色
		//$code->setFont($font);
		$code->setFont(0);
		$code->parse($codetext); // 条形码需要的数据内容
	} catch(Exception $exception) {
		$drawException = $exception;
	}

	//根据以上条件绘制条形码
	$drawing = new BCGDrawing($file, $color_white);
	if($drawException) {
		$drawing->drawException($drawException);
	} else {
		$drawing->setBarcode($code);
		$drawing->draw();
	}
	$drawing->finish(BCGDrawing::IMG_FORMAT_PNG);
	return request()->domain().$url;
}
}

// 路径转换为完整URL
if (!function_exists('toPath')) {
function toPath($path){
    if(!$path){
        return '';
    }
    // 已经是完整链接，直接返回
    if(strpos($path,'http://') === 0 || strpos($path,'https://') === 0){
        return $path;
    }
    // 取站点前缀
    if(defined('PRE_URL') && PRE_URL != ''){
        $base = rtrim(PRE_URL,'/');
    }else{
        $base = rtrim(request()->domain(),'/');
    }
    // 确保路径前面有 /
    if($path[0] !== '/'){
        $path = '/'.$path;
    }
    return $base.$path;
}
}

if (!function_exists('db')) {
function db($tablename){
	return \think\facade\Db::name($tablename);
}
}
if (!function_exists('table_name')) {
function table_name($tablename){
	return config('database.connections.mysql.prefix').$tablename;
}
}
if (!function_exists('m_url')) {
function m_url($url,$aid=0){
	if($aid == 0) $aid = aid;
	if(defined('PRE_URL2') && PRE_URL2 !=''){
		$pre_url = PRE_URL2;
	}else{
		$pre_url = PRE_URL;
	}
	return $pre_url.'/h5/'.$aid.'.html#/'.ltrim($url,'/');
}
}
if (!function_exists('send_socket')) {
function send_socket($data, $localUrl = null){
	try {
		$websocket_file = ROOT_PATH.'extend/WebsocketClient.php';
		if (!file_exists($websocket_file)) {
			\think\facade\Log::error('WebsocketClient file not found: ' . $websocket_file);
			return false;
		}
		require_once $websocket_file;
		$config = include(ROOT_PATH.'config.php');
		$socket = new \WebsocketClient('127.0.0.1',$config['kfport'], $localUrl);
		$socket->send(json_encode($data));
		return true;
	} catch (\Exception $e) {
		\think\facade\Log::error('WebSocket send error: ' . $e->getMessage());
		return false;
	}
}
}
if (!function_exists('getplatformname')) {
function getplatformname($platform=null){
	$platformArr = [
		'mp'=>'微信公众号',
		'wx'=>'微信小程序',
		'alipay'=>'支付宝小程序',
		'baidu'=>'百度小程序',
		'toutiao'=>'头条小程序',
		'qq'=>'QQ小程序',
		'h5'=>'手机H5',
		'app'=>'手机APP',
		// 'cashier'=>'收银台',  // 已禁用
	];
	if($platform) return $platformArr[$platform] ?? $platform;
	return $platformArr;
}
}
if (!function_exists('getcustom')) {
function getcustom($name=null){
	$custom = \think\facade\Config::get('database.custom');
	if(!$custom) $custom = [];
	$custom[] = 'restaurant';
	if(file_exists(ROOT_PATH.'custom.php')){
		$custom2 = include(ROOT_PATH.'custom.php');
		$custom = array_merge($custom,$custom2);
	}
	if(!$name) return $custom;
	return in_array($name,$custom);
}
}

//获取自定义文本
if (!function_exists('t')) {
function t($text, $aid=0){
    static $_textset = [];
    if(!$_textset){
        if(defined('aid') && aid > 0) {
            $sysset = db('admin_set')->where('aid', aid)->find();
            if (!$sysset) return $text;
            $_textset = json_decode($sysset['textset'], true);
            $_textset = \app\admin\common\System::mergeDefaultTextsetConfig($_textset);
            $_textset['color1'] = $sysset['color1'];
            $_textset['color2'] = $sysset['color2'];
            $color1rgb = hex2rgb($sysset['color1']);
            $color2rgb = hex2rgb($sysset['color2']);
            $_textset['color1rgb'] = $color1rgb['red'] . ',' . $color1rgb['green'] . ',' . $color1rgb['blue'];
            $_textset['color2rgb'] = $color2rgb['red'] . ',' . $color2rgb['green'] . ',' . $color2rgb['blue'];
        }elseif($aid){
            $sysset = db('admin_set')->where('aid', $aid)->find();
            if (!$sysset) return $text;
            $_textset = json_decode($sysset['textset'], true);
            $_textset = \app\admin\common\System::mergeDefaultTextsetConfig($_textset);
            $_textset['color1'] = $sysset['color1'];
            $_textset['color2'] = $sysset['color2'];
            $color1rgb = hex2rgb($sysset['color1']);
            $color2rgb = hex2rgb($sysset['color2']);
            $_textset['color1rgb'] = $color1rgb['red'] . ',' . $color1rgb['green'] . ',' . $color1rgb['blue'];
            $_textset['color2rgb'] = $color2rgb['red'] . ',' . $color2rgb['green'] . ',' . $color2rgb['blue'];
        }else{
            $_textset = [];
        }
        if(isset($_textset[$text])){
            return $_textset[$text];
        }
        return $text;
    }else{
        if(isset($_textset[$text])){
            return $_textset[$text];
        }
        return $text;
    }
}
}

/**
 *数字金额转换成中文大写金额的函数
 *String Int $num 要转换的小写数字或小写字符串
 *return 大写字母
 *小数位为两位
 **/
if (!function_exists('num_to_rmb')) {
function num_to_rmb($num){
    $c1 = "零壹贰叁肆伍陆柒捌玖";
    $c2 = "分角元拾佰仟万拾佰仟亿";
    //精确到分后面就不要了，所以只留两个小数位
    $num = round($num, 2);
    //将数字转化为整数
    $num = $num * 100;
    if (strlen($num) > 10) {
        return "金额太大，请检查";
    }
    $i = 0;
    $c = "";
    while (1) {
        if ($i == 0) {
            //获取最后一位数字
            $n = substr($num, strlen($num)-1, 1);
        } else {
            $n = $num % 10;
        }
        //每次将最后一位数字转化为中文
        $p1 = substr($c1, 3 * $n, 3);
        $p2 = substr($c2, 3 * $i, 3);
        if ($n != '0' || ($n == '0' && ($p2 == '亿' || $p2 == '万' || $p2 == '元'))) {
            $c = $p1 . $p2 . $c;
        } else {
            $c = $p1 . $c;
        }
        $i = $i + 1;
        //去掉数字最后一位了
        $num = $num / 10;
        $num = (int)$num;
        //结束循环
        if ($num == 0) {
            break;
        }
    }
    $j = 0;
    $slen = strlen($c);
    while ($j < $slen) {
        //utf8一个汉字相当3个字符
        $m = substr($c, $j, 6);
        //处理数字中很多0的情况,每次循环去掉一个汉字“零”
        if ($m == '零元' || $m == '零万' || $m == '零亿' || $m == '零零') {
            $left = substr($c, 0, $j);
            $right = substr($c, $j + 3);
            $c = $left . $right;
            $j = $j-3;
            $slen = $slen-3;
        }
        $j = $j + 3;
    }
    //这个是为了去掉类似23.0中最后一个“零”字
    if (substr($c, strlen($c)-3, 3) == '零') {
        $c = substr($c, 0, strlen($c)-3);
    }
    //将处理的汉字加上“整”
    if (empty($c)) {
        return "零元整";
    }else{
        return $c . "整";
    }
}
}

if (!function_exists('make_rand_code')) {
function make_rand_code($codetype, $codelength)
{
    if($codetype == 1){
        $str = '0123456789';
    }
    if($codetype == 2){
        $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    if($codetype == 3){
        $str = 'abcdefghijklmnopqrstuvwxyz';
    }
    if($codetype == 4){
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    if($codetype == 5){
        $str = 'abcdefghijklmnpqrstuvwxyz0123456789';
    }
    if($codetype == 6){
        $str = 'ABCDEFGHIJKLMNPQRSTUVWXYZ0123456789';
    }
    $len = strlen($str)-1;
    $randstr = '';
    for($j=0;$j<$codelength;$j++) {
        $num=mt_rand(0,$len);
        $randstr .= $str[$num];
    }

    return $randstr;
}
}

if (!function_exists('make_rand_name')) {
	function make_rand_name($length = 6)
	{
		$surNames = ["赵", "钱", "孙", "李", "周", "吴", "郑", "王", "冯", "陈","褚", "卫", "蒋", "沈", "韩", "杨", "朱", "秦", "尤", "许","何", "吕", "施", "张", "孔", "曹", "严", "华", "金", "魏","陶", "姜", "戚", "谢", "邹", "喻", "柏", "欧", "窦", "章","云", "苏", "潘", "葛", "奚", "范", "彭", "郎", "鲁", "韦","昌", "马", "苗", "凤", "花", "方", "俞", "任", "袁", "柳","酆", "鲍", "史", "唐", "费", "廉", "岑", "薛", "雷", "贺","倪", "汤", "滕", "殷", "罗", "毕", "郝", "邬", "安", "常","乐", "于", "时", "傅", "皮", "卞", "齐", "康", "伍", "余","元", "卜", "顾", "孟", "平", "黄", "和", "穆", "萧", "尹"];

		$litsData = [];

		$tempName = array_rand($surNames, $length);
		foreach ($tempName as $key) {
			$litsData[] = [
				'name' => $surNames[$key],
				'time' => date('Y-m-d H:i:s', (time() - rand(10, 300)))
			];
		}

		// 提取出要排序的键的值
		$sortTime = array_column($litsData, 'time');

		// 对年龄进行升序排序
		array_multisort($sortTime, SORT_ASC, $litsData);

		return $litsData;
	}
}

if (!function_exists('getRandomNickname')) {
	/**
	 * @param int $type 1生成昵称，2生成姓名
	 * //汉语 - 给用户自动生成昵称
	 */
	function getRandomNickname($type = 1){
		/**
		 * 随机昵称 形容词
		 */
		$nicheng_tou=['迷你的','鲜艳的','飞快的','真实的','清新的','幸福的','可耐的','快乐的','冷静的','醉熏的','潇洒的','糊涂的','积极的','冷酷的','深情的','粗暴的',
			'温柔的','可爱的','愉快的','义气的','认真的','威武的','帅气的','传统的','潇洒的','漂亮的','自然的','专一的','听话的','昏睡的','狂野的','等待的','搞怪的',
			'幽默的','魁梧的','活泼的','开心的','高兴的','超帅的','留胡子的','坦率的','直率的','轻松的','痴情的','完美的','精明的','无聊的','有魅力的','丰富的','繁荣的',
			'饱满的','炙热的','暴躁的','碧蓝的','俊逸的','英勇的','健忘的','故意的','无心的','土豪的','朴实的','兴奋的','幸福的','淡定的','不安的','阔达的','孤独的',
			'独特的','疯狂的','时尚的','落后的','风趣的','忧伤的','大胆的','爱笑的','矮小的','健康的','合适的','玩命的','沉默的','斯文的','香蕉','苹果','鲤鱼','鳗鱼',
			'任性的','细心的','粗心的','大意的','甜甜的','酷酷的','健壮的','英俊的','霸气的','阳光的','默默的','大力的','孝顺的','忧虑的','着急的','紧张的','善良的',
			'凶狠的','害怕的','重要的','危机的','欢喜的','欣慰的','满意的','跳跃的','诚心的','称心的','如意的','怡然的','娇气的','无奈的','无语的','激动的','愤怒的',
			'美好的','感动的','激情的','激昂的','震动的','虚拟的','超级的','寒冷的','精明的','明理的','犹豫的','忧郁的','寂寞的','奋斗的','勤奋的','现代的','过时的',
			'稳重的','热情的','含蓄的','开放的','无辜的','多情的','纯真的','拉长的','热心的','从容的','体贴的','风中的','曾经的','追寻的','儒雅的','优雅的','开朗的',
			'外向的','内向的','清爽的','文艺的','长情的','平常的','单身的','伶俐的','高大的','懦弱的','柔弱的','爱笑的','乐观的','耍酷的','酷炫的','神勇的','年轻的',
			'唠叨的','瘦瘦的','无情的','包容的','顺心的','畅快的','舒适的','靓丽的','负责的','背后的','简单的','谦让的','彩色的','缥缈的','欢呼的','生动的','复杂的',
			'慈祥的','仁爱的','魔幻的','虚幻的','淡然的','受伤的','雪白的','高高的','糟糕的','顺利的','闪闪的','羞涩的','缓慢的','迅速的','优秀的','聪明的','含糊的',
			'俏皮的','淡淡的','坚强的','平淡的','欣喜的','能干的','灵巧的','友好的','机智的','机灵的','正直的','谨慎的','俭朴的','殷勤的','虚心的','辛勤的','自觉的',
			'无私的','无限的','踏实的','老实的','现实的','可靠的','务实的','拼搏的','个性的','粗犷的','活力的','成就的','勤劳的','单纯的','落寞的','朴素的','悲凉的',
			'忧心的','洁净的','清秀的','自由的','小巧的','单薄的','贪玩的','刻苦的','干净的','壮观的','和谐的','文静的','调皮的','害羞的','安详的','自信的','端庄的',
			'坚定的','美满的','舒心的','温暖的','专注的','勤恳的','美丽的','腼腆的','优美的','甜美的','甜蜜的','整齐的','动人的','典雅的','尊敬的','舒服的','妩媚的',
			'秀丽的','喜悦的','甜美的','彪壮的','强健的','大方的','俊秀的','聪慧的','迷人的','陶醉的','悦耳的','动听的','明亮的','结实的','魁梧的','标致的','清脆的',
			'敏感的','光亮的','大气的','老迟到的','知性的','冷傲的','呆萌的','野性的','隐形的','笑点低的','微笑的','笨笨的','难过的','沉静的','火星上的','失眠的',
			'安静的','纯情的','要减肥的','迷路的','烂漫的','哭泣的','贤惠的','苗条的','温婉的','发嗲的','会撒娇的','贪玩的','执着的','眯眯眼的','花痴的','想人陪的',
			'眼睛大的','高贵的','傲娇的','心灵美的','爱撒娇的','细腻的','天真的','怕黑的','感性的','飘逸的','怕孤独的','忐忑的','高挑的','傻傻的','冷艳的','爱听歌的',
			'还单身的','怕孤单的','懵懂的'];
		$nicheng_wei=['嚓茶','皮皮虾','皮卡丘','马里奥','小霸王','凉面','便当','毛豆','花生','可乐','灯泡','哈密瓜','野狼','背包','眼神','缘分','雪碧','人生','牛排',
			'蚂蚁','飞鸟','灰狼','斑马','汉堡','悟空','巨人','绿茶','自行车','保温杯','大碗','墨镜','魔镜','煎饼','月饼','月亮','星星','芝麻','啤酒','玫瑰',
			'大叔','小伙','哈密瓜，数据线','太阳','树叶','芹菜','黄蜂','蜜粉','蜜蜂','信封','西装','外套','裙子','大象','猫咪','母鸡','路灯','蓝天','白云',
			'星月','彩虹','微笑','摩托','板栗','高山','大地','大树','电灯胆','砖头','楼房','水池','鸡翅','蜻蜓','红牛','咖啡','机器猫','枕头','大船','诺言',
			'钢笔','刺猬','天空','飞机','大炮','冬天','洋葱','春天','夏天','秋天','冬日','航空','毛衣','豌豆','黑米','玉米','眼睛','老鼠','白羊','帅哥','美女',
			'季节','鲜花','服饰','裙子','白开水','秀发','大山','火车','汽车','歌曲','舞蹈','老师','导师','方盒','大米','麦片','水杯','水壶','手套','鞋子','自行车',
			'鼠标','手机','电脑','书本','奇迹','身影','香烟','夕阳','台灯','宝贝','未来','皮带','钥匙','心锁','故事','花瓣','滑板','画笔','画板','学姐','店员',
			'电源','饼干','宝马','过客','大白','时光','石头','钻石','河马','犀牛','西牛','绿草','抽屉','柜子','往事','寒风','路人','橘子','耳机','鸵鸟','朋友',
			'苗条','铅笔','钢笔','硬币','热狗','大侠','御姐','萝莉','毛巾','期待','盼望','白昼','黑夜','大门','黑裤','钢铁侠','哑铃','板凳','枫叶','荷花','乌龟',
			'仙人掌','衬衫','大神','草丛','早晨','心情','茉莉','流沙','蜗牛','战斗机','冥王星','猎豹','棒球','篮球','乐曲','电话','网络','世界','中心','鱼','鸡','狗',
			'老虎','鸭子','雨','羽毛','翅膀','外套','火','丝袜','书包','钢笔','冷风','八宝粥','烤鸡','大雁','音响','招牌','胡萝卜','冰棍','帽子','菠萝','蛋挞','香水',
			'泥猴桃','吐司','溪流','黄豆','樱桃','小鸽子','小蝴蝶','爆米花','花卷','小鸭子','小海豚','日记本','小熊猫','小懒猪','小懒虫','荔枝','镜子','曲奇','金针菇',
			'小松鼠','小虾米','酒窝','紫菜','金鱼','柚子','果汁','百褶裙','项链','帆布鞋','火龙果','奇异果','煎蛋','唇彩','小土豆','高跟鞋','戒指','雪糕','睫毛','铃铛',
			'手链','香氛','红酒','月光','酸奶','银耳汤','咖啡豆','小蜜蜂','小蚂蚁','蜡烛','棉花糖','向日葵','水蜜桃','小蝴蝶','小刺猬','小丸子','指甲油','康乃馨','糖豆',
			'薯片','口红','超短裙','乌冬面','冰淇淋','棒棒糖','长颈鹿','豆芽','发箍','发卡','发夹','发带','铃铛','小馒头','小笼包','小甜瓜','冬瓜','香菇','小兔子',
			'含羞草','短靴','睫毛膏','小蘑菇','跳跳糖','小白菜','草莓','柠檬','月饼','百合','纸鹤','小天鹅','云朵','芒果','面包','海燕','小猫咪','龙猫','唇膏','鞋垫',
			'羊','黑猫','白猫','万宝路','金毛','山水','音响','纸飞机','烧鹅'];
		/**
		 * 百家姓
		 */
		$arrXing=['赵','钱','孙','李','周','吴','郑','王','冯','陈','褚','卫','蒋','沈','韩','杨','朱','秦','尤','许','何','吕','施','张','孔','曹','严','华','金','魏','陶','姜','戚','谢','邹',
			'喻','柏','水','窦','章','云','苏','潘','葛','奚','范','彭','郎','鲁','韦','昌','马','苗','凤','花','方','任','袁','柳','鲍','史','唐','费','薛','雷','贺','倪','汤','滕','殷','罗',
			'毕','郝','安','常','傅','卞','齐','元','顾','孟','平','黄','穆','萧','尹','姚','邵','湛','汪','祁','毛','狄','米','伏','成','戴','谈','宋','茅','庞','熊','纪','舒','屈','项','祝',
			'董','梁','杜','阮','蓝','闵','季','贾','路','娄','江','童','颜','郭','梅','盛','林','钟','徐','邱','骆','高','夏','蔡','田','樊','胡','凌','霍','虞','万','支','柯','管','卢','莫',
			'柯','房','裘','缪','解','应','宗','丁','宣','邓','单','杭','洪','包','诸','左','石','崔','吉','龚','程','嵇','邢','裴','陆','荣','翁','荀','于','惠','甄','曲','封','储','仲','伊',
			'宁','仇','甘','武','符','刘','景','詹','龙','叶','幸','司','黎','溥','印','怀','蒲','邰','从','索','赖','卓','屠','池','乔','胥','闻','莘','党','翟','谭','贡','劳','逄','姬','申',
			'扶','堵','冉','宰','雍','桑','寿','通','燕','浦','尚','农','温','别','庄','晏','柴','瞿','阎','连','习','容','向','古','易','廖','庾','终','步','都','耿','满','弘','匡','国','文',
			'寇','广','禄','阙','东','欧','利','师','巩','聂','关','荆','司马','上官','欧阳','夏侯','诸葛','闻人','东方','赫连','皇甫','尉迟','公羊','澹台','公冶','宗政','濮阳','淳于','单于','太叔',
			'申屠','公孙','仲孙','轩辕','令狐','徐离','宇文','长孙','慕容','司徒','司空','皮'];
		/**
		 * 名
		 */
		$arrMing=['伟','刚','勇','毅','俊','峰','强','军','平','保','东','文','辉','力','明','永','健','世','广','志','义','兴','良','海','山','仁','波','宁','贵','福','生','龙','元','全'
			,'国','胜','学','祥','才','发','武','新','利','清','飞','彬','富','顺','信','子','杰','涛','昌','成','康','星','光','天','达','安','岩','中','茂','进','林','有','坚','和','彪','博','诚'
			,'先','敬','震','振','壮','会','思','群','豪','心','邦','承','乐','绍','功','松','善','厚','庆','磊','民','友','裕','河','哲','江','超','浩','亮','政','谦','亨','奇','固','之','轮','翰'
			,'朗','伯','宏','言','若','鸣','朋','斌','梁','栋','维','启','克','伦','翔','旭','鹏','泽','晨','辰','士','以','建','家','致','树','炎','德','行','时','泰','盛','雄','琛','钧','冠','策'
			,'腾','楠','榕','风','航','弘','秀','娟','英','华','慧','巧','美','娜','静','淑','惠','珠','翠','雅','芝','玉','萍','红','娥','玲','芬','芳','燕','彩','春','菊','兰','凤','洁','梅','琳'
			,'素','云','莲','真','环','雪','荣','爱','妹','霞','香','月','莺','媛','艳','瑞','凡','佳','嘉','琼','勤','珍','贞','莉','桂','娣','叶','璧','璐','娅','琦','晶','妍','茜','秋','珊','莎'
			,'锦','黛','青','倩','婷','姣','婉','娴','瑾','颖','露','瑶','怡','婵','雁','蓓','纨','仪','荷','丹','蓉','眉','君','琴','蕊','薇','菁','梦','岚','苑','婕','馨','瑗','琰','韵','融','园'
			,'艺','咏','卿','聪','澜','纯','毓','悦','昭','冰','爽','琬','茗','羽','希','欣','飘','育','滢','馥','筠','柔','竹','霭','凝','晓','欢','霄','枫','芸','菲','寒','伊','亚','宜','可','姬'
			,'舒','影','荔','枝','丽','阳','妮','宝','贝','初','程','梵','罡','恒','鸿','桦','骅','剑','娇','纪','宽','苛','灵','玛','媚','琪','晴','容','睿','烁','堂','唯','威','韦','雯','苇','萱'
			,'阅','彦','宇','雨','洋','忠','宗','曼','紫','逸','贤','蝶','菡','绿','蓝','儿','翠','烟'];
		$nicheng='';
		switch ($type){
			case 1:
				$tou_num=rand(0,count($nicheng_tou)-1);
				$wei_num=rand(0,count($nicheng_wei)-1);
				$nicheng=$nicheng_tou[$tou_num].$nicheng_wei[$wei_num];
				break;
			case 2:
				$nicheng=$arrXing[mt_rand(0,count($arrXing)-1)];
				for($i=1;$i<=3;$i++)
				{
					$nicheng .=(mt_rand(0,1)?$arrMing[mt_rand(0,count($arrMing)-1)]:$arrMing[mt_rand(0,count($arrMing)-1)]);
				}
				break;
		}
		return $nicheng;
	}
}
