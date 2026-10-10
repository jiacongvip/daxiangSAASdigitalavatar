<?php
/**
 * 点大商城（www.diandashop.com） - 微信公众号小程序商城系统!
 * Copyright © 2020 山东点大网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：V2
 * 授权主体：安徽微澳网络科技有限公司
 * 授权域名：wx.ahwakj.com
 * 授权码：vGXrteGbUAyyNMvoJzVPaIdgI
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

namespace app\admin\common;
use think\Exception;
use think\facade\Db;
use think\facade\Log;
use app\admin\common\System;
//use think\facade\Queue;

class Wechat
{
    private static $adminUserNoticeFieldCache = [];

    private static function resolveNoticeMemberIds($aid, $midOrOpenid, $openidField = 'mpopenid')
    {
        if (is_numeric($midOrOpenid)) {
            $mid = intval($midOrOpenid);
            return $mid > 0 ? [$mid] : [];
        }

        $openid = trim((string)$midOrOpenid);
        if ($openid === '') {
            return [];
        }

        $mid = Db::name('member')->where('aid', $aid)->where($openidField, $openid)->value('id');
        return $mid ? [intval($mid)] : [];
    }

    private static function bridgeMessageNotice($aid, $mids, $tmpltype, $content, $tourl = '', $bid = 0, $contentNew = [])
    {
        if (
            !class_exists('\\app\\plugin\\messagenotify\\common\\MessageSettings')
            || !class_exists('\\app\\plugin\\messagenotify\\common\\MessageScene')
        ) {
            return ['status' => 1, 'msg' => '消息通知插件未安装，已跳过桥接'];
        }
        try {
            return BizMessageNoticeBridge::notifyMembers($aid, $mids, $tmpltype, $content, $tourl, $bid, $contentNew);
        } catch (\Throwable $e) {
            Log::warning('消息通知桥接失败并已跳过: ' . $e->getMessage(), [
                'aid' => intval($aid),
                'tmpltype' => strval($tmpltype),
                'bid' => intval($bid),
            ]);
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    private static function normalizeMiniProgramQrPage($aid, $page, array $scene = [])
    {
        $page = ltrim(trim((string)$page), '/');
        if ($page === '') {
            return $page;
        }

        if ($page === 'pagesK/allianceactivity/detail') {
            $activityId = intval($scene['id'] ?? ($scene['activity_id'] ?? 0));
            if ($activityId > 0) {
                $activityType = trim((string)Db::name('allianceactivity_activity')
                    ->where('aid', intval($aid))
                    ->where('id', $activityId)
                    ->where('delete_time', 0)
                    ->value('activity_type'));
                if ($activityType === 'groupbuy') {
                    return 'pagesK/allianceactivity/groupbuy/detail';
                }
                if ($activityType === 'directbuy') {
                    return 'pagesK/allianceactivity/merchant/detail';
                }
            }
        }

        return $page;
    }

    private static function normalizeMiniProgramQrScene($aid, $page, array $scene = [])
    {
        $page = ltrim(trim((string)$page), '/');
        if ($page !== 'pagesExt/lipin2enterprise/index') {
            return $scene;
        }

        $activityKey = trim((string)($scene['activity_key'] ?? ''));
        if ($activityKey === '') {
            return $scene;
        }
        $activityId = intval(Db::name('lipin2')
            ->where('aid', intval($aid))
            ->where('scene', 1)
            ->where('activity_key', $activityKey)
            ->value('id'));
        return $activityId > 0 ? ['hid' => $activityId] : $scene;
    }

    private static function filterAdminNoticeUsers($userlist, $tmpltype, $mdid = 0)
    {
        $result = [];
        foreach ((array)$userlist as $user) {
            if ($user['isadmin'] == 0) {
                if ($user['groupid']) {
                    $group = Db::name('admin_user_group')->where('id', $user['groupid'])->find();
                    if ($group) {
                        $user['notice_auth_data'] = $group['notice_auth_data'];
                        $user['mdid'] = $group['mdid'];
                    }
                    if (intval($user['mdid']) != 0 && intval($user['mdid']) != intval($mdid)) {
                        continue;
                    }
                }
                $notice_auth_data = json_decode($user['notice_auth_data'], true);
                if (!$notice_auth_data || !in_array($tmpltype, $notice_auth_data)) {
                    continue;
                }
            }
            $result[] = $user;
        }
        return $result;
    }

    private static function extractAdminTmplTitle($content): string
    {
        if (!is_array($content)) {
            return '';
        }
        $first = $content['first'] ?? ($content['keyword1'] ?? '');
        if (is_array($first)) {
            $first = $first['value'] ?? '';
        }
        return mb_substr(trim((string)$first), 0, 191);
    }

    private static function extractAdminTmplBizNo($content): string
    {
        if (!is_array($content)) {
            return '';
        }
        foreach (['keyword1', 'character_string2', 'ordernum'] as $key) {
            if (!isset($content[$key])) {
                continue;
            }
            $val = $content[$key];
            if (is_array($val)) {
                $val = $val['value'] ?? '';
            }
            $val = trim((string)$val);
            if ($val !== '') {
                return mb_substr($val, 0, 64);
            }
        }
        return '';
    }

    private static function logAdminTmplPush($aid, $bid, $user, $tmpltype, $content, $status, $errmsg = '', $openid = '')
    {
        $title = self::extractAdminTmplTitle($content);
        AdminNotice::logPush([
            'aid' => intval($aid),
            'bid' => intval($bid),
            'uid' => intval($user['id'] ?? 0),
            'mid' => intval($user['mid'] ?? 0),
            'channel' => 'mp',
            'scene' => (string)$tmpltype,
            'target' => (string)$openid,
            'target_name' => (string)($user['nickname'] ?? ''),
            'title' => $title,
            'biz_no' => self::extractAdminTmplBizNo($content),
            'status' => $status ? 1 : 0,
            'errmsg' => (string)$errmsg,
        ]);
    }

    private static function getAdminUserNoticeField($tmpltype)
    {
        $tmpltype = trim((string)$tmpltype);
        if ($tmpltype === '') {
            return $tmpltype;
        }
        if ($tmpltype === 'tmpl_maidanpay' && !self::adminUserFieldExists('tmpl_maidanpay')) {
            return 'tmpl_orderpay';
        }
        return $tmpltype;
    }

    private static function adminUserFieldExists($field)
    {
        $field = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$field);
        if ($field === '') {
            return false;
        }
        if (array_key_exists($field, self::$adminUserNoticeFieldCache)) {
            return self::$adminUserNoticeFieldCache[$field];
        }
        $prefix = config('database.connections.mysql.prefix');
        $rows = Db::query("SHOW COLUMNS FROM `{$prefix}admin_user` LIKE '{$field}'");
        self::$adminUserNoticeFieldCache[$field] = !empty($rows);
        return self::$adminUserNoticeFieldCache[$field];
    }

	//获取平台的access_token
	public static function component_access_token($aid=0){
		$componentinfo = Db::name('sysset')->where('name','component')->value('value');
		$componentinfo = json_decode($componentinfo,true);
		$component_access_token = cache('component_access_token');
		if(!$component_access_token){
			$url = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
			$component_verify_ticket = cache('component_verify_ticket');
			$data = array();
			$data['component_appid'] = $componentinfo['appid'];
			$data['component_appsecret'] = $componentinfo['appsecret'];
			$data['component_verify_ticket'] = $component_verify_ticket;
			$rs = request_post($url,jsonEncode($data));
			$rs = json_decode($rs);
			if($rs->component_access_token){
				cache('component_access_token',$rs->component_access_token,7000);
				$component_access_token = $rs->component_access_token;
			}else{
				echojson(['status'=>0,'msg'=>self::geterror((array)$rs)]);
				//return false;
			}
		}
		return $component_access_token;
	}
	//获取平台appid
	public static function component_appid(){
		$componentinfo = Db::name('sysset')->where('name','component')->value('value');
		$componentinfo = json_decode($componentinfo,true);
		$component_appid = $componentinfo['appid'];
		return $component_appid;
	}
	/**
	 * 原子写入 access_token 缓存表(appid 为唯一键)。
	 * 通过 INSERT ... ON DUPLICATE KEY UPDATE 消除首屏多接口并发时
	 * 「先查后插」造成的 SQLSTATE[23000] 1062 Duplicate entry for key 'appid' 竞态。
	 * @param string $appid  唯一键 appid
	 * @param array  $fields 需要写入/更新的字段(会自动剔除 appid)
	 */
	private static function saveAccessTokenCache($appid, array $fields){
		$appid = (string)$appid;
		unset($fields['appid']);
		if($appid === '' || !$fields){
			return;
		}
		$data = array_merge(['appid'=>$appid], $fields);
		try{
			Db::name('access_token')->duplicate(array_keys($fields))->insert($data);
		}catch(\Throwable $e){
			//兜底：极端情况下回退为按 appid 更新，确保不向前台抛出异常
			Db::name('access_token')->where('appid',$appid)->update($fields);
		}
	}
	//获取access_token $platform mp:公众号 wx:小程序
	public static function access_token($aid,$platform='wx',$iscache=true){
		$appinfo = System::appinfo($aid,$platform);
		$appid = $appinfo['appid'];
		$appsecret = $appinfo['appsecret'];
		if(!$appid) return '';
		$tokendata = Db::name('access_token')->where('appid',$appid)->find();   
		if($iscache && $tokendata && $tokendata['access_token'] && $tokendata['expires_time'] > time()){
			return $tokendata['access_token'];
		}else{
			if($appinfo['authtype']==1){ //授权接入
				//刷新调用凭证
				$url = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.self::component_access_token();
				$data = array();
				$data['component_appid'] = self::component_appid();
				$data['authorizer_appid'] = $appid;
				$data['authorizer_refresh_token'] = $appinfo['refresh_token'];
				$rs = request_post($url,jsonEncode($data));
				$rs = json_decode($rs);
				if($rs->authorizer_access_token){
					$access_token = $rs->authorizer_access_token;
					Db::name('access_token')->where('appid',$appid)->update(['access_token'=>$access_token,'expires_time'=>time()+7000]);
					return $access_token;
				}else{
					//\think\facade\Log::write($rs);
					//return '';
					echojson(array('status'=>0,'msg'=>self::geterror($rs)));
				}
			}else{ //普通接入
				if(!$appsecret) return '';
				$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
				$res = json_decode(request_get($url));
				$access_token = $res->access_token;
				if($access_token) {
					self::saveAccessTokenCache($appid, ['access_token'=>$access_token,'expires_time'=>time()+7000]);
					return $access_token;
				}else{
					//\think\facade\Log::write($res);
					//return '';
					echojson(array('status'=>0,'msg'=>self::geterror($res)));
				}
			}
		}
	}
	public static function jsapi_ticket($aid){
		$appinfo = System::appinfo($aid,'mp');
		$appid = $appinfo['appid'];
		$tokendata = Db::name('access_token')->where('appid',$appid)->find();
		if($tokendata['jsapi_ticket'] && $tokendata['ticket_expires_time'] > time()){
			return $tokendata['jsapi_ticket'];
		}else{
			$access_token = self::access_token($aid,'mp');
			if(!$access_token) return '';
			$url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
			$res = json_decode(request_get($url));
			$jsapi_ticket = $res->ticket;
			if ($jsapi_ticket) {
				Db::name('access_token')->where('appid',$appid)->update(['jsapi_ticket'=>$jsapi_ticket,'ticket_expires_time'=>time()+7000]);
				return $jsapi_ticket;
			}else{
				return '';
				//echojson(['status'=>0,'msg'=>self::geterror($res)]);
			}
		}
	}
	
	public static function share_package($aid){
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		$appinfo = System::appinfo($aid,'mp');
		$jsapiTicket = self::jsapi_ticket($aid);
		$appid = $appinfo['appid'];
		if(!$jsapiTicket) return [
			"appId"     => '',
			"nonceStr"  => '',
			"timestamp" => time(),
			"url"       => '',
			"signature" => '',
			"rawString" => '',
		];
		// 注意 URL 一定要动态获取，不能 hardcode.
		$url = PRE_URL.'/h5/'.$aid.'.html';
		$timestamp = time();
		$nonceStr = random(6);
		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
		$signature = sha1($string);
		$signPackage = [
		  "appId"     => $appid,
		  "nonceStr"  => $nonceStr,
		  "timestamp" => $timestamp,
		  "url"       => $url,
		  "signature" => $signature,
		  "rawString" => $string
		];
		return $signPackage;
	}
	//获取卡券js接口用的 card_ticket
	public static function card_ticket($aid){
		$appinfo = System::appinfo($aid,'mp');
		$appid = $appinfo['appid'];
		$tokendata = Db::name('access_token')->where('appid',$appid)->find();
		if($tokendata['card_ticket'] && $tokendata['card_ticket_expires_time'] > time()){
			return $tokendata['card_ticket'];
		}else{
			$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=wx_card&access_token=".self::access_token($aid,'mp');
			$res = json_decode(request_get($url));
			$jcard_ticket = $res->ticket;
			if ($jcard_ticket) {
				Db::name('access_token')->where('appid',$appid)->update(['card_ticket'=>$jcard_ticket,'card_ticket_expires_time'=>time()+7000]);
				return $jcard_ticket;
			}else{
				return '';
				//if(request()->domain() != 'http://wxtt.com')
				//echojson(['status'=>0,'msg'=>self::geterror($res)]);
			}
		}
	}
	//上传到微信素材库
	public static function getmediaid($aid,$picurl,$type="image",$description=null){
		if(strpos($picurl,'/') === false){
			return $picurl;
		}
		$material = Db::name('mp_material')->where('aid',$aid)->where('url',$picurl)->where('type',$type)->where('description',$description)->find();
		if($material){
			return $material['media_id'];
		}
		$url = \app\admin\common\Pic::tolocal($picurl);
		$mediapath = ROOT_PATH.str_replace(PRE_URL.'/','',$url);
		$data = array('media'=>'@'.$mediapath);
		$data = [];
		$data['media'] = new \CurlFile($mediapath); 
		if($type == 'video'){
			$data['description'] = $description ? $description : jsonEncode(['title'=>date('点击视频查看'),'introduction'=>'本视频上传于'.date('Y年m月d日')]);
		}
		$res = curl_post('https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.self::access_token($aid,'mp').'&type='.$type,$data);
		$res = json_decode($res,true);
		if($res['media_id']){
			Db::name('mp_material')->insert(['aid'=>$aid,'url'=>$picurl,'media_id'=>$res['media_id'],'type'=>$type,'description'=>$description,'createtime'=>time()]);
			return $res['media_id'];
		}else{
			return ['status'=>0,'msg'=>self::geterror($res)];
		}
	}
	public static function pictomedia($aid,$platform,$picurl,$isyj=false){
		if(strpos($picurl,'/') === false){
			return $picurl;
		}
		$url = \app\admin\common\Pic::tolocal($picurl);
		$mediapath = ROOT_PATH.str_replace(PRE_URL.'/','',$url);
		$data = [];
		$data['media'] = new \CurlFile($mediapath); 
		$access_token = self::access_token($aid,$platform);
		if($isyj){//上传永久素材 小程序不行
			$url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$access_token.'&type=image';
		}else{//上传临时素材
			$url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$access_token.'&type=image';
		}
		$res = curl_post($url,$data);
		$res = json_decode($res,true);
		if($res['media_id']){
			return $res['media_id'];
		}else{
			return ['status'=>0,'msg'=>self::geterror($res)];
		}
	}
    /**
     * 生成公众号二维码和小程序码
     * @param $aid
     * @param $platform wx:小程序，mp 公众号
     * @param $page 路径,开头不能加斜杠
     * @param array $scene 格式：['id' => 1, 'name' => 'demo']，最终参数格式：id_1-cid_2
     * @param int $bid  用于获取使用商家logo
     * @return array
     */
	public static function getQRCode($aid,$platform,$page,array $scene=[],$bid=0){
        $page = trim((string)$page);
        $params = [];
        if(strpos($page,'?')){
            $pageArr = explode('?',$page);
            $page = $pageArr[0];
            $params =  explode('&',$pageArr[1]);
        }
		if(!empty($params)) {
			foreach($params as $v){
				if($v!=''){
					$vArr = explode('=',$v,2);
					$scene[$vArr[0]] = rawurldecode($vArr[1] ?? '');
				}
			}
		}
		if($platform=='wx'){
			$scene = self::normalizeMiniProgramQrScene($aid, $page, $scene);
		}
		$sceneFormat = '';
        if ($scene) {
            $i = 1;
            foreach ($scene as $key => $val) {
                $sceneFormat .= $key . '_' . $val;
                if ($i < count($scene)) {
                    $sceneFormat .= '-';
                }
                $i++;
            }
        }       
		if($platform=='wx'){
            $page = self::normalizeMiniProgramQrPage($aid, $page, $scene);
			$url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.self::access_token($aid,$platform);
			$data = array();
			$data['scene'] = $sceneFormat ? $sceneFormat : '0';
			$data['page'] = $page;
			$res = request_post($url,jsonEncode($data));
			$errmsg =json_decode($res,true);
			if($errmsg){
				return ['status'=>0,'msg'=>self::geterror($errmsg),'rs'=>$errmsg];
			}
			$dir = 'upload/'.date('Ym');
			// 智能判断保存路径：如果 ROOT_PATH 已经指向 public 目录，则不需要再加 public/
			$rootPath = rtrim(ROOT_PATH, '/');
			if (substr($rootPath, -6) === 'public' || is_dir(ROOT_PATH.'index.php')) {
				$savePath = ROOT_PATH.$dir;
			} else {
				$savePath = ROOT_PATH.'public/'.$dir;
			}
			if(!is_dir($savePath)) mk_dir($savePath);
			
			//获取商家logo
			$business = Db::name('business')->where('aid',$aid)->where('id',$bid)->find();
			if($business && $business['logo']){
				try {
					$old_logo = file_get_contents($business['logo']);
					$logo = self::yuan_img($old_logo);
					$sharePic = self::qrcode_with_logo($res, $logo);
					$filename = date('d_His').rand(1000,9999).'.png';
					$mediapath = $dir.'/'.$filename;
					$fullPath = $savePath.'/'.$filename;
					file_put_contents($fullPath,$sharePic);
				} catch (\Exception $e) {
					$filename = date('d_His').rand(1000,9999).'.png';
					$mediapath = $dir.'/'.$filename;
					$fullPath = $savePath.'/'.$filename;
					file_put_contents($fullPath,$res);
				}
			}else{
				$filename = date('d_His').rand(1000,9999).'.png';
				$mediapath = $dir.'/'.$filename;
				$fullPath = $savePath.'/'.$filename;
				file_put_contents($fullPath,$res);
			}
			$url = \app\admin\common\Pic::uploadoss(PRE_URL.'/'.$mediapath,false,false);
			return ['status'=>1,'url'=>$url];
		}elseif($platform == 'mp'){
			$url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.self::access_token($aid,$platform);
			$data = array();
			$data['action_name'] = 'QR_LIMIT_STR_SCENE';
			$data['action_info'] = array('scene'=>array('scene_str'=>$sceneFormat));
			$rs = request_post($url,jsonEncode($data));
			$rs = json_decode($rs,true);
			if($rs['url']){
				$url = createqrcode($rs['url']);
				return ['status'=>1,'url'=>$url];
			}else{
				return ['status'=>0,'msg'=>self::geterror($rs)];
			}
		}
	}
	 /**
     * [yuan_img 剪切图片为圆形]
     * @param  [type] $picture [图片数据流 比如file_get_contents(imageurl)返回的数据]
     * @return [type]          [图片数据流]
     */
    public static function yuan_img($picture)
    {
        $src_img = imagecreatefromstring($picture);
        $w = imagesx($src_img);
        $h = imagesy($src_img);
        $w = min($w, $h);
        $h = $w;
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false); // 设定图像的混色模式
        imagesavealpha($img, true); // 这一句一定要有（设置标记以在保存 PNG 图像时保存完整的 alpha 通道信息）
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 127); // 拾取一个完全透明的颜色,最后一个参数127为全透明
        imagefill($img, 0, 0, $bg);
        $r = $w / 2; //圆半径
        $y_x = $r; //圆心X坐标
        $y_y = $r; //圆心Y坐标
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($src_img, $x, $y);
                if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
                    imagesetpixel($img, $x, $y, $rgbColor);
                }
            }
        }
        /**
         * 如果想要直接输出图片，应该先设header。header("Content-Type: image/png; charset=utf-8");
         * 并且去掉缓存区函数
         */
        //获取输出缓存，否则imagepng会把图片输出到浏览器
        ob_start();
        imagepng($img);
        imagedestroy($img);
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    /**
     * [qrcode_with_logo 在二维码的中间区域镶嵌图片]
     * @param  [type] $QR   [二维码数据流。比如file_get_contents(imageurl)返回的数据,或者微信给返回的数据]
     * @param  [type] $logo [中间显示图片的数据流。比如file_get_contents(imageurl)返回的东东]
     * @return [type]       [返回图片数据流]
     */
    public static function qrcode_with_logo($QR, $logo)
    {
        $QR = imagecreatefromstring($QR);
        $logo = imagecreatefromstring($logo);
        $QR_width = imagesx($QR); // 二维码图片宽度
        $QR_height = imagesy($QR); // 二维码图片高度
        $logo_width = imagesx($logo); // logo图片宽度
        $logo_height = imagesy($logo); // logo图片高度
        $logo_qr_width = $QR_width / 2.2; // 组合之后logo的宽度(占二维码的1/2.2)
        $scale = $logo_width / $logo_qr_width; // logo的宽度缩放比(本身宽度/组合后的宽度)
        $logo_qr_height = $logo_height / $scale; // 组合之后logo的高度
        $from_width = ($QR_width - $logo_qr_width) / 2; // 组合之后logo左上角所在坐标点
        /**
         * 重新组合图片并调整大小
         * imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
         */
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        /**
         * 如果想要直接输出图片，应该先设header。header("Content-Type: image/png; charset=utf-8");
         * 并且去掉缓存区函数
         */
        //获取输出缓存，否则imagepng会把图片输出到浏览器
        ob_start();
        imagepng($QR);
        imagedestroy($QR);
        imagedestroy($logo);
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
	//发模板消息 https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html#5
	public static function sendtmpl($aid=1,$mid,$tmpltype,$content,$tourl='',$contentNew=[],$preferNew=false){
	    $member = [];
        if(is_numeric($mid)){
            $member = Db::name('member')->where('id',$mid)->field('id,nickname,mpopenid')->find();
            $openid = $member['mpopenid'];
             // var_dump($openid);die;
        }else{
            $openid = $mid;
    }

	    $bridgeMids = !empty($member['id']) ? [intval($member['id'])] : self::resolveNoticeMemberIds($aid, $mid, 'mpopenid');
        self::bridgeMessageNotice($aid, $bridgeMids, $tmpltype, $content, $tourl, 0, $contentNew);
        //var_dump($openid);die;
        if(!$openid) return ['status'=>0,'msg'=>'openid为空'];
        $set = Db::name('mp_tmplset')->where('aid',$aid)->find();
        $tmplid = $set[$tmpltype] ?? '';
        if(!$tmplid && $tmpltype == 'tmpl_maidanpay'){
            $tmplid = $set['tmpl_orderpay'] ?? '';
        }
        $contentNew = self::buildMpCategoryTmplContent($aid,0,$tmpltype,$content,$contentNew);
        $setnew = [];
        if(!$contentNew && self::isMpCompatTemplate($tmpltype)){
            $setnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
            if(!empty($setnew[$tmpltype])){
                $contentNew = $content;
            }
        }
        $tmplidNew = '';
        if($contentNew){
            if(!$setnew){
                $setnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
            }
            $tmplidNew = $setnew[$tmpltype] ?? '';
            if(!$tmplidNew && $tmpltype == 'tmpl_maidanpay'){
                $tmplidNew = $setnew['tmpl_orderpay'] ?? '';
            }
        }
        if(!$tmplid && !$tmplidNew) return ['status'=>0,'msg'=>'未配置模板ID'];
        $sendOld = $tmplid ? true : false;
        if(($preferNew || $contentNew) && $tmplidNew && $contentNew){
            $sendOld = false;
        }
        $access_token = self::access_token($aid,'mp');
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
        if($sendOld){
            $data = [];
            $data['template_id'] = trim($tmplid);
            if($tourl){
                $data['url'] = $tourl;
            }
            $newcontent = [];
            foreach($content as $k=>$v){
                if($v == "[nickname]" && $member) {
                    $v = $member['nickname'];
                }
                if(is_array($v)){
                    $newcontent[$k] = $v;
                }else{
                    $newcontent[$k] = ['value'=>strval($v)];
                }
            }
            $data['data'] = $newcontent;
            $data['touser'] = $openid;
            syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
        }
        if($tmplidNew && $contentNew){
            $datanew = [];
            $datanew['template_id'] = trim($tmplidNew);
            if($tourl){
                $datanew['url'] = $tourl;
            }
            $newcontent = [];
            foreach($contentNew as $k=>$v){
                if($v == "[nickname]" && $member) {
                    $v = $member['nickname'];
                }
                if(is_array($v)){
                    $newcontent[$k] = $v;
                }else{
                    //格式处理
                    $newcontent[$k] = ['value'=>self::formatTmplParam($k,$v)];
                }
            }
            $datanew['data'] = $newcontent;
            $datanew['touser'] = $openid;
            Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                '$datanew'=>$datanew
            ]);
            syncRequest($url,str_replace('\\/','/',jsonEncode($datanew)));
        }
		return ['status'=>1,'msg'=>'发送成功'];
		/*
        $rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
        $rs = json_decode($rs,true);
        if($rs['errcode']!=0){
            return ['status'=>0,'msg'=>self::geterror($rs)];
        }else{
            return ['status'=>1,'msg'=>'发送成功'];
        }
		*/
	    }

    /**
     * 同步发送单个会员公众号模板消息，并保留微信返回的 msgid。
     * 仅供带投递尝试账本的 worker 使用，旧 sendtmpl() 行为保持不变。
     */
    public static function sendtmplWithResult(
        $aid,
        $mid,
        $tmpltype,
        $content,
        $tourl = '',
        $contentNew = [],
        $preferNew = false
    ) {
        $aid = intval($aid);
        $mid = intval($mid);
        $tmpltype = trim(strval($tmpltype));
        if ($aid <= 0 || $mid <= 0 || $tmpltype === '' || !is_array($content)
            || !is_array($contentNew)) {
            return self::templateDeliveryResult(
                'failed',
                'template_request_invalid',
                '模板消息参数不完整'
            );
        }
        try {
            $member = Db::name('member')
                ->where('aid', $aid)
                ->where('id', $mid)
                ->field('id,nickname,mpopenid')
                ->find();
            if (!$member || trim(strval($member['mpopenid'] ?? '')) === '') {
                return self::templateDeliveryResult(
                    'failed',
                    'template_openid_missing',
                    'openid为空'
                );
            }
            $set = Db::name('mp_tmplset')->where('aid', $aid)->find() ?: [];
            $tmplid = trim(strval($set[$tmpltype] ?? ''));
            if ($tmplid === '' && $tmpltype === 'tmpl_maidanpay') {
                $tmplid = trim(strval($set['tmpl_orderpay'] ?? ''));
            }
            $contentNew = self::buildMpCategoryTmplContent(
                $aid,
                0,
                $tmpltype,
                $content,
                $contentNew
            );
            $setnew = [];
            if (empty($contentNew) && self::isMpCompatTemplate($tmpltype)) {
                $setnew = Db::name('mp_tmplset_new')->where('aid', $aid)->find() ?: [];
                if (!empty($setnew[$tmpltype])) {
                    $contentNew = $content;
                }
            }
            $tmplidNew = '';
            if (!empty($contentNew)) {
                if (empty($setnew)) {
                    $setnew = Db::name('mp_tmplset_new')->where('aid', $aid)->find() ?: [];
                }
                $tmplidNew = trim(strval($setnew[$tmpltype] ?? ''));
                if ($tmplidNew === '' && $tmpltype === 'tmpl_maidanpay') {
                    $tmplidNew = trim(strval($setnew['tmpl_orderpay'] ?? ''));
                }
            }
            if ($tmplid === '' && $tmplidNew === '') {
                return self::templateDeliveryResult(
                    'failed',
                    'template_id_missing',
                    '未配置模板ID'
                );
            }

            $sendNew = ($preferNew || !empty($contentNew))
                && $tmplidNew !== '' && !empty($contentNew);
            $templateId = $sendNew ? $tmplidNew : $tmplid;
            $templateContent = $sendNew ? $contentNew : $content;
            if ($templateId === '') {
                return self::templateDeliveryResult(
                    'failed',
                    'template_id_missing',
                    '未配置可用模板ID'
                );
            }
            $formattedContent = [];
            foreach ($templateContent as $key => $value) {
                if ($value === '[nickname]') {
                    $value = strval($member['nickname'] ?? '');
                }
                if (is_array($value)) {
                    $formattedContent[$key] = $value;
                } else {
                    $formattedContent[$key] = [
                        'value' => $sendNew
                            ? self::formatTmplParam($key, $value)
                            : strval($value),
                    ];
                }
            }
            $accessToken = trim(strval(self::access_token($aid, 'mp')));
            if ($accessToken === '') {
                return self::templateDeliveryResult(
                    'failed',
                    'template_access_token_missing',
                    '公众号access_token为空'
                );
            }
            $data = [
                'touser' => strval($member['mpopenid']),
                'template_id' => $templateId,
                'data' => $formattedContent,
            ];
            if ($tourl) {
                $data['url'] = $tourl;
            }
            $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='
                . $accessToken;
            $raw = request_post(
                $url,
                str_replace('\/', '/', jsonEncode($data))
            );
            $result = self::classifyTemplateSendResponse($raw);
            if (($result['delivery_state'] ?? '') === 'succeeded') {
                try {
                    self::bridgeMessageNotice(
                        $aid,
                        [$mid],
                        $tmpltype,
                        $content,
                        $tourl,
                        0,
                        $contentNew
                    );
                } catch (\Throwable $bridgeException) {
                    Log::write([
                        'function' => __FUNCTION__,
                        'aid' => $aid,
                        'mid' => $mid,
                        'bridge_error' => $bridgeException->getMessage(),
                    ]);
                }
            }
            return $result;
        } catch (\Throwable $e) {
            return self::templateDeliveryResult(
                'failed',
                'template_prepare_failed',
                $e->getMessage()
            );
        }
    }

    public static function classifyTemplateSendResponse($response): array
    {
        if (!is_string($response) || trim($response) === '') {
            return self::templateDeliveryResult(
                'unknown',
                'template_response_missing',
                '微信模板消息响应为空'
            );
        }
        $decoded = json_decode(
            $response,
            true,
            512,
            JSON_BIGINT_AS_STRING
        );
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE
            || !array_key_exists('errcode', $decoded)) {
            return self::templateDeliveryResult(
                'unknown',
                'template_response_invalid',
                '微信模板消息响应无法解析'
            );
        }
        $errorCode = intval($decoded['errcode']);
        if ($errorCode !== 0) {
            return self::templateDeliveryResult(
                'failed',
                'wechat_template_rejected:' . $errorCode,
                self::geterror($decoded)
            );
        }
        $messageId = trim(strval($decoded['msgid'] ?? ''));
        if ($messageId === '' || strlen($messageId) > 128
            || !preg_match('/^[A-Za-z0-9_-]+$/', $messageId)) {
            return self::templateDeliveryResult(
                'unknown',
                'template_receipt_missing',
                '微信已返回成功但缺少有效msgid'
            );
        }
        return self::templateDeliveryResult(
            'succeeded',
            '',
            '微信已受理模板消息',
            'wechat_template:' . $messageId
        );
    }

    private static function templateDeliveryResult(
        string $state,
        string $errorCode,
        string $message,
        string $externalReceipt = ''
    ): array {
        if (!in_array($state, ['succeeded', 'failed', 'unknown'], true)) {
            throw new \InvalidArgumentException('wechat_template_delivery_state_invalid');
        }
        return [
            'status' => $state === 'succeeded' ? 1 : 0,
            'delivery_state' => $state,
            'error_code' => $errorCode,
            'msg' => $message,
            'external_receipt' => $externalReceipt,
        ];
    }

    private static function getMpTmplValue($content, $keys, $default = '')
    {
        foreach((array)$keys as $key){
            if(!isset($content[$key])) continue;
            $value = $content[$key];
            if(is_array($value) && isset($value['value'])){
                $value = $value['value'];
            }
            if($value !== '' && $value !== null){
                return strval($value);
            }
        }
        return $default;
    }

    private static function getMpTmplBusinessName($aid, $bid = 0)
    {
        $name = '';
        if($bid){
            $name = Db::name('business')->where('aid',$aid)->where('id',$bid)->value('name');
        }
        if(!$name){
            $name = Db::name('admin_set')->where('aid',$aid)->value('name');
        }
        return $name ? $name : '默认门店';
    }

    private static function isMpCompatTemplate($tmpltype)
    {
        return in_array($tmpltype, ['tmpl_withdraw', 'tmpl_uplv', 'tmpl_kehuzixun']);
    }

    private static function isMpCommonNoticeType($tmpltype)
    {
        return in_array($tmpltype, ['tmpl_withdraw', 'tmpl_uplv', 'tmpl_kehuzixun']);
    }

    private static function buildMpCommonNoticeContent($aid, $bid, $tmpltype, $content, $contentNew = [])
    {
        if($contentNew || !is_array($content) || !$content){
            return $contentNew;
        }
        $siteName = self::getMpTmplBusinessName($aid, $bid);
        switch($tmpltype){
            case 'tmpl_withdraw':
                return [
                    'thing1' => self::getMpTmplValue($content, ['first', 'keyword1'], '提现申请提醒'),
                    'time16' => self::getMpTmplValue($content, ['keyword2', 'time16'], date('Y-m-d H:i')),
                    'thing4' => $siteName,
                ];
            case 'tmpl_uplv':
                return [
                    'thing1' => self::getMpTmplValue($content, ['first', 'keyword1'], '升级申请提醒'),
                    'time16' => self::getMpTmplValue($content, ['keyword2', 'time16'], date('Y-m-d H:i')),
                    'thing4' => $siteName,
                ];
            case 'tmpl_kehuzixun':
                return [
                    'thing1' => self::getMpTmplValue($content, ['first', 'keyword1'], '客户咨询提醒'),
                    'time16' => self::getMpTmplValue($content, ['keyword3', 'time16'], date('Y-m-d H:i')),
                    'thing4' => $siteName,
                ];
        }
        return [];
    }

    private static function buildMpCategoryTmplContent($aid, $bid, $tmpltype, $content, $contentNew = [])
    {
        if($contentNew || !is_array($content) || !$content){
            return $contentNew;
        }
        $siteName = self::getMpTmplBusinessName($aid, $bid);
        switch($tmpltype){
            case 'tmpl_orderpay':
            case 'tmpl_maidanpay':
                $orderNo = self::getMpTmplValue($content, ['keyword2','character_string2','orderName']);
                if(!$orderNo) return [];
                return [
                    'thing8' => $siteName,
                    'character_string2' => $orderNo,
                    'thing3' => self::getMpTmplValue($content, ['keyword4','thing11','orderProductName']),
                    'phrase18' => self::getMpTmplValue($content, ['keyword1','thing27'], '会员'),
                    'amount5' => self::getMpTmplValue($content, ['keyword3','amount13','orderProductPrice']),
                ];
            case 'tmpl_orderfahuo':
                $orderTitle = self::getMpTmplValue($content, ['keyword1','thing2','thing4','orderProductName']);
                if(!$orderTitle) return [];
                return [
                    'thing4' => $orderTitle,
                    'thing13' => self::getMpTmplValue($content, ['keyword2','thing7','thing13']),
                    'character_string14' => self::getMpTmplValue($content, ['keyword3','character_string4','character_string14']),
                    'thing16' => self::getMpTmplValue($content, ['keyword4','thing11','thing16']),
                ];
            case 'tmpl_ordershouhuo':
                $orderNo = self::getMpTmplValue($content, ['keyword2','character_string6','character_string7','orderName']);
                if(!$orderNo) return [];
                return [
                    'thing3' => self::getMpTmplValue($content, ['keyword1','thing3','thing16'], '会员'),
                    'character_string7' => $orderNo,
                    'time8' => date('Y-m-d H:i:s'),
                ];
            case 'tmpl_ordertui':
                $orderNo = self::getMpTmplValue($content, ['keyword1','number2','character_string4','orderName']);
                if(!$orderNo) return [];
                return [
                    'number2' => $orderNo,
                    'amount4' => self::getMpTmplValue($content, ['keyword2','amount4','amount9','orderProductPrice']),
                ];
            case 'tmpl_tuisuccess':
            case 'tmpl_tuierror':
                $orderNo = self::getMpTmplValue($content, ['orderName','keyword3','character_string1','character_string2','character_string4']);
                if(!$orderNo) return [];
                return [
                    'character_string1' => $orderNo,
                    'thing2' => self::getMpTmplValue($content, ['orderProductName','keyword2','thing2','thing3','thing6','thing8']),
                    'amount3' => self::getMpTmplValue($content, ['orderProductPrice','keyword1','amount3','amount6','amount9']),
                ];
            case 'tmpl_tixiansuccess':
                $amount = self::getMpTmplValue($content, ['money','amount2','amount3','keyword1','amount1']);
                if(!$amount) return [];
                return [
                    'amount2' => $amount,
                    'time3' => self::getMpTmplValue($content, ['timet','time3','time5','date2','keyword2'], date('Y-m-d H:i:s')),
                ];
            case 'tmpl_formsub':
                $title = self::getMpTmplValue($content, ['keyword1','thing3']);
                if(!$title) return [];
                return [
                    'thing3' => $title,
                    'time5' => self::getMpTmplValue($content, ['keyword2','time5'], date('Y-m-d H:i:s')),
                ];
            case 'tmpl_moneychange':
                $remark = self::getMpTmplValue($content, ['keyword2','thing2']);
                if(!$remark) return [];
                return [
                    'thing2' => $remark,
                    'amount3' => self::getMpTmplValue($content, ['keyword3','amount3']),
                    'amount4' => self::getMpTmplValue($content, ['keyword4','amount4']),
                    'time6' => self::getMpTmplValue($content, ['keyword1','time6'], date('Y-m-d H:i')),
                ];
            case 'tmpl_shenhe':
                $title = self::getMpTmplValue($content, ['keyword1','thing2','thing9']);
                if(!$title) return [];
                return [
                    'thing9' => $title,
                    'thing2' => self::getMpTmplValue($content, ['keyword2','phrase1','phrase2']),
                    'time3' => self::getMpTmplValue($content, ['keyword3','time3'], date('Y-m-d H:i:s')),
                ];
        }
        return [];
    }

    private static function formatTmplParam($type,$value)
    {
        $value = strval($value);
        if(strpos($type,'thing') !== false){
            //事物 	20个以内字符	可汉字、数字、字母或符号组合
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 20)
                    $value = mb_substr($value,0,20,'UTF-8');
            }
        }elseif(strpos($type,'character_string') !== false){
            //字符串	32位以内数字、字母或符号	可数字、字母或符号组合
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 32)
                    $value = mb_substr($value,0,32,'UTF-8');
            }
        }elseif(strpos($type,'amount') !== false){
            //金额	1个币种符号+12位以内纯数字，可带小数，结尾可带“元”	可带小数
            if($value == '' || is_null($value)){
                $value = '0';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 12)
                    $value = mb_substr($value,0,12,'UTF-8');
            }
        }elseif(strpos($type,'phone_number') !== false){
            //电话	17位以内，数字、符号	电话号码，例：+86-0766-66888866
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 17)
                    $value = mb_substr($value,0,17,'UTF-8');
            }
        }elseif(strpos($type,'car_number') !== false){
            //电话	车牌	8位以内，第一位与最后一位可为汉字，其余为字母或数字	车牌号码：粤A8Z888挂
            if($value == '' || is_null($value)){
                $value = '无';
            }else{
                $length = mb_strlen($value,'UTF-8');
                if($length > 8)
                    $value = mb_substr($value,0,8,'UTF-8');
            }
        }

        return $value;
    }

	//发订阅消息
	public static function sendwxtmpl($aid,$mid,$tmpltype,$contentnew,$tourl='',$content){
        $member = [];
		if(is_numeric($mid)){
			$member = Db::name('member')->where('id',$mid)->field('id,wxopenid')->find();
			$openid = $member['wxopenid'];
		}else{
			$openid = $mid;
		}
        $bridgeMids = !empty($member['id']) ? [intval($member['id'])] : self::resolveNoticeMemberIds($aid, $mid, 'wxopenid');
        self::bridgeMessageNotice($aid, $bridgeMids, $tmpltype, $content, $tourl, 0, $contentnew);
		if(!$openid) return ['status'=>0,'msg'=>'openid为空'];
		$set = Db::name('wx_tmplset')->where('aid',$aid)->find();
		$tmpltypenew = $tmpltype.'_new';
		$tmplid = $set[$tmpltype.'_new'];
		if(!$tmplid){
			$tmplid = $set[$tmpltype];
		}else{
			$content = $contentnew;
		}
		if(!$tmplid) return ['status'=>0,'msg'=>'未配置模板ID'];
		$access_token = self::access_token($aid,'wx');
		$url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$access_token;
		$data = [];
		$data['template_id'] = trim($tmplid);
		if($tourl){
			$data['page'] = $tourl;
		}
		$newcontent = [];
		foreach($content as $k=>$v){
			if(is_array($v)){
				$newcontent[$k] = $v;
			}else{
				if(strpos($k,'thing') === 0){
					if(!$v) $v = '暂无';
					$v = mb_substr($v,0,20);
				}
				if($v=='' && strpos($k,'character_string') === 0){
					$v = 'empty';
				}
				$v = strval($v);
				$newcontent[$k] = ['value'=>$v];
			}
		}
		$data['data'] = $newcontent;
		$data['touser'] = $openid;
		//$rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
		syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
		\think\facade\Log::write($rs);
		\think\facade\Log::write($data);
//		$rs = json_decode($rs,true);
		//if($rs['errcode']!=0){
		//	return ['status'=>0,'msg'=>self::geterror($rs)];
		//}else{
			return ['status'=>1,'msg'=>'发送成功'];
		//}
		}

    /** 会员升级 outbox 专用小程序订阅消息入口，保留微信明确响应。 */
    public static function sendwxtmplWithResult($aid, $mid, $tmpltype, $contentnew, $tourl = '', $content = [])
    {
        $aid = intval($aid);
        $mid = intval($mid);
        $tmpltype = trim(strval($tmpltype));
        if ($aid <= 0 || $mid <= 0 || $tmpltype === ''
            || !is_array($contentnew) || !is_array($content)) {
            return self::templateDeliveryResult('failed', 'subscribe_request_invalid', '订阅消息参数不完整');
        }
        try {
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)
                ->field('id,wxopenid')->find();
            $openid = trim(strval($member['wxopenid'] ?? ''));
            if (!$member || $openid === '') {
                return self::templateDeliveryResult('failed', 'subscribe_openid_missing', '小程序openid为空');
            }
            $set = Db::name('wx_tmplset')->where('aid', $aid)->find() ?: [];
            $templateId = trim(strval($set[$tmpltype . '_new'] ?? ''));
            $selectedContent = $contentnew;
            if ($templateId === '') {
                $templateId = trim(strval($set[$tmpltype] ?? ''));
                $selectedContent = $content;
            }
            if ($templateId === '') {
                return self::templateDeliveryResult('failed', 'subscribe_template_id_missing', '未配置订阅消息模板ID');
            }
            $formatted = [];
            foreach ($selectedContent as $key => $value) {
                if (is_array($value)) {
                    $formatted[$key] = $value;
                    continue;
                }
                if (strpos($key, 'thing') === 0) {
                    $value = $value === '' ? '暂无' : mb_substr(strval($value), 0, 20);
                }
                if ($value === '' && strpos($key, 'character_string') === 0) {
                    $value = 'empty';
                }
                $formatted[$key] = ['value' => strval($value)];
            }
            $accessToken = trim(strval(self::access_token($aid, 'wx')));
            if ($accessToken === '') {
                return self::templateDeliveryResult('failed', 'subscribe_access_token_missing', '小程序access_token为空');
            }
            $payload = ['touser' => $openid, 'template_id' => $templateId, 'data' => $formatted];
            if ($tourl !== '') {
                $payload['page'] = $tourl;
            }
            $raw = request_post(
                'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . $accessToken,
                str_replace('\\/', '/', jsonEncode($payload))
            );
            if (!is_string($raw) || trim($raw) === '') {
                return self::templateDeliveryResult('unknown', 'subscribe_response_missing', '微信订阅消息响应为空');
            }
            $decoded = json_decode($raw, true, 512, JSON_BIGINT_AS_STRING);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE
                || !array_key_exists('errcode', $decoded)) {
                return self::templateDeliveryResult('unknown', 'subscribe_response_invalid', '微信订阅消息响应无法解析');
            }
            $errorCode = intval($decoded['errcode']);
            if ($errorCode !== 0) {
                return self::templateDeliveryResult(
                    'failed',
                    'wechat_subscribe_rejected:' . $errorCode,
                    self::geterror($decoded)
                );
            }
            try {
                self::bridgeMessageNotice($aid, [$mid], $tmpltype, $content, $tourl, 0, $contentnew);
            } catch (\Throwable $bridgeException) {
                Log::write(['function' => __FUNCTION__, 'aid' => $aid, 'mid' => $mid, 'bridge_error' => $bridgeException->getMessage()]);
            }
            return [
                'status' => 1,
                'delivery_state' => 'succeeded',
                'error_code' => '',
                'external_receipt' => 'wechat_subscribe:' . substr(hash('sha256', $raw . '|' . jsonEncode($payload)), 0, 48),
                'msg' => '',
            ];
        } catch (\Throwable $e) {
            return self::templateDeliveryResult('unknown', 'subscribe_request_exception', $e->getMessage());
        }
    }
    //发模板消息 后台人员
    public static function sendhttmplByUids($aid,$uids,$tmpltype,$content,$tourl='',$mdid=0){
        if(!$uids){
            return ['status'=>0,'msg'=>'无接收用户'];
        }
        $bridgeTmplType = strlen($tmpltype) == 43 ? 'custom_template' : $tmpltype;
        $tmplid = '';
        if(strlen($tmpltype) == 43){
            $tmplid = $tmpltype;
            $tmpltype = '1';
        }else{
            $set = Db::name('mp_tmplset')->where('aid',$aid)->find();
            $tmplid = $set[$tmpltype] ?? '';
            if(!$tmplid && $tmpltype == 'tmpl_maidanpay'){
                $tmplid = $set['tmpl_orderpay'] ?? '';
            }
        }
        if($tmplid){
            $access_token = self::access_token($aid,'mp');
            $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
            $data = [];
            $data['template_id'] = trim($tmplid);
            if($tourl){
                $data['url'] = $tourl;
            }
            $newcontent = [];
            foreach($content as $k=>$v){
                if(is_array($v)){
                    $newcontent[$k] = $v;
                }else{
                    $newcontent[$k] = ['value'=>strval($v)];
                }
            }
            $data['data'] = $newcontent;
        }
        $adminNoticeField = self::getAdminUserNoticeField($tmpltype);
        $where = [];
        $where[] = ['aid','=',$aid];
        $where[] = ['mid','<>',0];
        $where[] = [$adminNoticeField,'=',1];
        $where[] = ['id','in',$uids];
        $userlist = Db::name('admin_user')->where($where)->select()->toArray();
        $userlist = self::filterAdminNoticeUsers($userlist, $adminNoticeField, $mdid);
        if(!$userlist){
            self::logAdminTmplPush($aid, 0, [], $tmpltype, $content, 0, '没有设置接收用户');
            return ['status'=>0,'msg'=>'没有设置接收用户'];
        }
        $mids = array_values(array_unique(array_filter(array_map('intval', array_column($userlist, 'mid')))));
        self::bridgeMessageNotice($aid, $mids, $bridgeTmplType, $content, $tourl);
        if(!$tmplid) {
            self::logAdminTmplPush($aid, 0, [], $tmpltype, $content, 0, '未配置模板ID');
            return ['status'=>0,'msg'=>'未配置模板ID'];
        }
        foreach($userlist as $user){
            if($tmplid){
                $member = Db::name('member')->where('id',$user['mid'])->field('mpopenid,nickname')->find() ?: [];
                $user['mpopenid'] = trim((string)($member['mpopenid'] ?? ''));
                $user['nickname'] = trim((string)($member['nickname'] ?? ''));
                if(!$user['mpopenid']) {
                    self::logAdminTmplPush($aid, 0, $user, $tmpltype, $content, 0, '未关注公众号');
                    continue;
                }

                $data['touser'] = $user['mpopenid'];
                //$rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
                syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
                self::logAdminTmplPush($aid, 0, $user, $tmpltype, $content, 1, '已提交', $user['mpopenid']);
                //\think\facade\Log::write($rs);
                //\think\facade\Log::write($user);
            }
        }

        if(defined('PRE_URL2') && PRE_URL2 !=''){
            $pre_url = PRE_URL2;
        }elseif(defined('PRE_URL')){
            $pre_url = PRE_URL;
        }
        if ($pre_url) {
            $urlstart = $pre_url.'/h5/'.$aid.'.html#';
            $pagepath = str_replace($urlstart,'',$tourl);
            send_socket(['type'=>'notice','data'=>['aid'=>$aid,'mids'=>$mids,'title'=>$content['first'],'desc'=>$content['remark'],'url'=>$pagepath]]);
        } else {
            if($tourl) {
                $url_arr = explode('.html#', $tourl);
                $pagepath = $url_arr[1];
                $search = '~^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?~i';
                preg_match_all($search,$tourl,$array2);
                $local_url = $array2[1][0].$array2[3][0];
                send_socket(['type'=>'notice','data'=>['aid'=>$aid,'mids'=>$mids,'title'=>$content['first'],'desc'=>$content['remark'],'url'=>$pagepath]], $local_url);
            }
        }
        return ['status'=>1,'msg'=>'发送成功'];
	}
	//发模板消息 后台人员
	public static function sendhttmpl($aid,$bid,$tmpltype,$content,$tourl='',$mdid=0,$contentNew=[],$preferNew=false){
	   
        if(!$mdid) $mdid = 0;
        if(!$bid) $bid = 0;
        $tmplidNew = '';
        $tmpltypeNew = $tmpltype;
        $bridgeTmplType = strlen($tmpltype) == 43 ? 'custom_template' : $tmpltype;
        if(strlen($tmpltype) == 43){
            $tmplid = $tmpltype;
            $tmpltype = '1';
        }else{
            $set = Db::name('mp_tmplset')->where('aid',$aid)->find();
            $tmplid = $set[$tmpltype] ?? '';
            if(!$tmplid && $tmpltype == 'tmpl_maidanpay'){
                $tmplid = $set['tmpl_orderpay'] ?? '';
            }
            $contentNew = self::buildMpCategoryTmplContent($aid,$bid,$tmpltype,$content,$contentNew);
            $setnew = [];
            if(!$contentNew && self::isMpCompatTemplate($tmpltype)){
                $setnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
                if(!empty($setnew[$tmpltype])){
                    $contentNew = $content;
                }
            }
            if(!$contentNew && self::isMpCommonNoticeType($tmpltype)){
                if(!$setnew){
                    $setnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
                }
                if(!empty($setnew['tmpl_common_notice'])){
                    $contentNew = self::buildMpCommonNoticeContent($aid,$bid,$tmpltype,$content);
                    $tmpltypeNew = 'tmpl_common_notice';
                }
            }
            if($contentNew){
                if(!$setnew){
                    $setnew = Db::name('mp_tmplset_new')->where('aid',$aid)->find();
                }
                $tmplidNew = $setnew[$tmpltypeNew] ?? '';
                if(!$tmplidNew && $tmpltype == 'tmpl_maidanpay'){
                    $tmplidNew = $setnew['tmpl_orderpay'] ?? '';
                }
            }
        }
        $sendOld = $tmplid ? true : false;
        if(($preferNew || $contentNew) && $tmplidNew && $contentNew){
            $sendOld = false;
        }

		if($sendOld){
			$access_token = self::access_token($aid,'mp');
			$url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
			$data = [];
			$data['template_id'] = trim($tmplid);
			if($tourl){
				$data['url'] = $tourl;
			}
			$newcontent = [];
			foreach($content as $k=>$v){
				if(is_array($v)){
					$newcontent[$k] = $v;
				}else{
					$newcontent[$k] = ['value'=>strval($v)];
				}
			}
			$data['data'] = $newcontent;
		}
        if($tmplidNew && $contentNew){
            $access_token = self::access_token($aid,'mp');
            $urlnew = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$access_token;
            $datanew = [];
            $datanew['template_id'] = trim($tmplidNew);
            if($tourl){
                $datanew['url'] = $tourl;
            }
            $newcontent = [];
            foreach($contentNew as $k=>$v){
                if(is_array($v)){
                    $newcontent[$k] = $v;
                }else{
                    $newcontent[$k] = ['value'=>self::formatTmplParam($k,$v)];
                }
            }
            $datanew['data'] = $newcontent;
        }
        $adminNoticeField = self::getAdminUserNoticeField($tmpltype);
        $userlist = Db::name('admin_user')->where("`aid`={$aid} and `bid`={$bid} and `mid`!=0 and `{$adminNoticeField}`=1 and (`mdid`=0 or `mdid`='{$mdid}' or `groupid`>0)")->select()->toArray();
        \think\facade\Log::write(Db::getlastsql());
    

        $userlist = self::filterAdminNoticeUsers($userlist, $adminNoticeField, $mdid);
        if(!$userlist){
            \think\facade\Log::write([
                'file'=>__FILE__.' '.__LINE__,
                'error'=>'没有设置接收用户'
            ]);
            self::logAdminTmplPush($aid, $bid, [], $tmpltype, $content, 0, '没有设置接收用户');
            return ['status'=>0,'msg'=>'没有设置接收用户'];
        }

        $mids = array_values(array_unique(array_filter(array_map('intval', array_column($userlist, 'mid')))));
        self::bridgeMessageNotice($aid, $mids, $bridgeTmplType, $content, $tourl, $bid, $contentNew);
        if(!$tmplid && !$tmplidNew) {
            self::logAdminTmplPush($aid, $bid, [], $tmpltype, $content, 0, '未配置模板ID');
            return ['status' => 0, 'msg' => '未配置模板ID'];
        }
        $lastRsArr = ['errcode'=>0];
        
    foreach($userlist as $user){
        $member = Db::name('member')->where('id', $user['mid'])->field('mpopenid,nickname')->find() ?: [];
        $user['mpopenid'] = trim((string)($member['mpopenid'] ?? ''));
        $user['nickname'] = trim((string)($member['nickname'] ?? ''));
    if($sendOld){
        if(!$user['mpopenid']) {
            self::logAdminTmplPush($aid, $bid, $user, $tmpltype, $content, 0, '未关注公众号');
            continue;
        }

        $data['touser'] = $user['mpopenid'];
        $rs = request_post($url, str_replace('\\/', '/', jsonEncode($data)));
        \think\facade\Log::write("推送给用户 {$user['mid']} 的响应: {$rs}");
        \think\facade\Log::write("推送给用户 {$user['mid']} 的数据: " . json_encode($data));

        $rsArr = json_decode($rs, true);
        if(!is_array($rsArr) || ($rsArr['errcode'] ?? 0) != 0){
            $errmsg = is_array($rsArr) ? self::geterror($rsArr) : '模板发送失败';
            self::logAdminTmplPush($aid, $bid, $user, $tmpltype, $content, 0, $errmsg, $user['mpopenid']);
            \think\facade\Log::write([
                'file' => __FILE__ . ' ' . __LINE__,
                'error' => $errmsg,
                'response' => $rs
            ]);
            return ['status' => 0, 'msg' => $errmsg];
        }
        self::logAdminTmplPush($aid, $bid, $user, $tmpltype, $content, 1, '发送成功', $user['mpopenid']);
        $lastRsArr = $rsArr;
    }
    if($tmplidNew){
        if(!$user['mpopenid']) {
            self::logAdminTmplPush($aid, $bid, $user, $tmpltypeNew, $contentNew ?: $content, 0, '未关注公众号');
            continue;
        }

        $datanew['touser'] = $user['mpopenid'];
        $rs = syncRequest($urlnew, str_replace('\\/', '/', jsonEncode($datanew)));
        \think\facade\Log::write("推送给用户 {$user['mid']} 的新模板响应: {$rs}");
        \think\facade\Log::write("推送给用户 {$user['mid']} 的新模板数据: " . json_encode($datanew));

        $rsArr = json_decode($rs, true);
        if(!is_array($rsArr) || ($rsArr['errcode'] ?? 0) != 0){
            $errmsg = is_array($rsArr) ? self::geterror($rsArr) : '模板发送失败';
            self::logAdminTmplPush($aid, $bid, $user, $tmpltypeNew, $contentNew ?: $content, 0, $errmsg, $user['mpopenid']);
            \think\facade\Log::write([
                'file' => __FILE__ . ' ' . __LINE__,
                'error' => $errmsg,
                'response' => $rs
            ]);
            return ['status' => 0, 'msg' => $errmsg];
        }
        self::logAdminTmplPush($aid, $bid, $user, $tmpltypeNew, $contentNew ?: $content, 1, '发送成功', $user['mpopenid']);
        $lastRsArr = $rsArr;
    }
}



        if(defined('PRE_URL2') && PRE_URL2 !=''){
            $pre_url = PRE_URL2;
        }elseif(defined('PRE_URL')){
            $pre_url = PRE_URL;
        }
        if ($pre_url) {
            $urlstart = $pre_url.'/h5/'.$aid.'.html#';
            $pagepath = str_replace($urlstart,'',$tourl);
            send_socket(['type'=>'notice','data'=>['aid'=>$aid,'mids'=>$mids,'title'=>$content['first'],'desc'=>$content['remark'],'url'=>$pagepath]]);
        } else {
            if($tourl) {
                $url_arr = explode('.html#', $tourl);
                $pagepath = $url_arr[1];
                $search = '~^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?~i';
                preg_match_all($search,$tourl,$array2);
                $local_url = $array2[1][0].$array2[3][0];
                send_socket(['type'=>'notice','data'=>['aid'=>$aid,'mids'=>$mids,'title'=>$content['first'],'desc'=>$content['remark'],'url'=>$pagepath]], $local_url);
            }
        }
\think\facade\Log::write("所有推送操作完成");
        if($lastRsArr['errcode']!=0){
            \think\facade\Log::write([
                'file'=>__FILE__.' '.__LINE__,
                'error'=>self::geterror($lastRsArr)
            ]);
            return ['status'=>0,'msg'=>self::geterror($lastRsArr)];
        }else{
            return ['status'=>1,'msg'=>'发送成功'];
        }
    }
	//发订阅消息 后台人员
	public static function sendhtwxtmpl($aid,$bid,$tmpltype,$content,$tourl='',$mdid=0){
		if(!$mdid) $mdid = 0;
        if(!$bid) $bid = 0;
        $bridgeTmplType = strlen($tmpltype) == 43 ? 'custom_template' : $tmpltype;
        if(strlen($tmpltype) == 43){
            $tmplid = $tmpltype;
            $tmpltype = '1';
        }else{
			$set = Db::name('wx_tmplset')->where('aid',$aid)->find();
			if($tmpltype == 'tmpl_orderpay'){
				$tmplid = $set['tmpl_orderconfirm'];
			}else{
				$tmplid = $set[$tmpltype];
			}
		}
		//\think\facade\Log::write($set);
		//\think\facade\Log::write($tmplid);
		//\think\facade\Log::write($tmpltype);

		$access_token = self::access_token($aid,'wx');
		$url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$access_token;
		$data = [];
		$data['template_id'] = trim($tmplid);
		if($tourl){
			$data['page'] = $tourl;
		}
		$newcontent = [];
		foreach($content as $k=>$v){
			if(is_array($v)){
				$newcontent[$k] = $v;
			}else{
				if(strpos($k,'thing') === 0){
					if(!$v) $v = '暂无';
					$v = mb_substr($v,0,20);
				}
				if(strpos($k,'name') === 0){ //字母或中文 10个字符内
					if($v && preg_match("/^[\x41-\x5a\x61-\x7a\x80-\xff]+$/", $v)){
						$v = mb_substr($v,0,10);
					}else{
						$v = '未知';
					}
				}
				if($v=='' && strpos($k,'character_string') === 0){
					$v = 'empty';
				}
				$v = strval($v);
				$newcontent[$k] = ['value'=>$v];
			}
		}
		$data['data'] = $newcontent;

        $adminNoticeField = self::getAdminUserNoticeField($tmpltype);
		$userlist = Db::name('admin_user')->where("`aid`={$aid} and `bid`={$bid} and `mid`!=0 and `{$adminNoticeField}`=1 and (`mdid`=0 or `mdid`='{$mdid}' or `groupid`>0)")->select()->toArray();
        $userlist = self::filterAdminNoticeUsers($userlist, $adminNoticeField, $mdid);
        if(!$userlist){
            return ['status'=>0,'msg'=>'没有设置接收用户'];
        }
        $mids = array_values(array_unique(array_filter(array_map('intval', array_column($userlist, 'mid')))));
        self::bridgeMessageNotice($aid, $mids, $bridgeTmplType, $content, $tourl, $bid, $content);
		if(!$tmplid) return ['status'=>0,'msg'=>'未配置模板ID'];
        foreach($userlist as $user){
            $user['wxopenid'] = Db::name('member')->where('id',$user['mid'])->value('wxopenid');
            if(!$user['wxopenid']) continue;
            
            $data['touser'] = $user['wxopenid'];
            //$rs = request_post($url,str_replace('\\/','/',jsonEncode($data)));
			syncRequest($url,str_replace('\\/','/',jsonEncode($data)));
            //\think\facade\Log::write($rs);
            //\think\facade\Log::write($data);
			
			$tmplnum = Db::name('member_tmplnum')->where('aid',$aid)->where('mid',$user['mid'])->where('tmplid',$tmplid)->value('num');
			if($tmplnum > 0){
				Db::name('member_tmplnum')->where('aid',$aid)->where('mid',$user['mid'])->where('tmplid',$tmplid)->dec('num')->update();
			}
        }

		//if($rs['errcode']!=0){
		//	return ['status'=>0,'msg'=>self::geterror($rs)];
		//}else{
			return ['status'=>1,'msg'=>'发送成功'];
		//}
	}

	//会员卡信息变更
	public static function updatemembercard($aid,$mid,$record_bonus=''){
		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
		if(!$member || !$member['card_id'] || !$member['card_code']) return;
		if($member['card_id'] && $member['card_code']){
			$url = 'https://api.weixin.qq.com/card/get?access_token='.self::access_token($aid,'mp');
			$rs = request_post($url,jsonEncode(['card_id'=>$member['card_id']]));
			$rs = json_decode($rs,true);
			if(!is_array($rs) || !empty($rs['errcode']) || empty($rs['card']['member_card'])) return;
			//\think\facade\Log::write($rs);
			$custom_field1 = $rs['card']['member_card']['custom_field1'] ?? [];
			$custom_field2 = $rs['card']['member_card']['custom_field2'] ?? [];
			$custom_field3 = $rs['card']['member_card']['custom_field3'] ?? [];
			$supply_bonus = $rs['card']['member_card']['supply_bonus'] ?? false;

			$membercard = Db::name('membercard')->where('aid',$aid)->where('card_id',$member['card_id'])->find() ?: [];

			$url = 'https://api.weixin.qq.com/card/membercard/updateuser?access_token='.self::access_token($aid,'mp');
			$postdata = [];
			$postdata['card_id'] = $member['card_id'];
			$postdata['code'] = $member['card_code'];
			if($supply_bonus){
				$postdata['bonus'] = $member['score'];
				if($record_bonus){
					$postdata['record_bonus'] = $record_bonus;
				}
			}
			$notify_optional = [];
			if($custom_field1){
				if($custom_field1['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
					$couponcount = Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
					$postdata['custom_field_value1'] = $couponcount;
				}elseif($custom_field1['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
					$memberlv = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
					$postdata['custom_field_value1'] = $memberlv['name'];
				}elseif($custom_field1['name']=='余额' || $custom_field1['name']==t('余额')){//余额
					$postdata['custom_field_value1'] = $member['money'];
				}elseif($custom_field1['name']==($membercard['custom_field_customize1_name'] ?? '')){
					$postdata['custom_field_value1'] = $membercard['custom_field_customize1_value'] ?? '';
				}elseif($custom_field1['name']==($membercard['custom_field_customize2_name'] ?? '')){
					$postdata['custom_field_value1'] = $membercard['custom_field_customize2_value'] ?? '';
				}
				$notify_optional['is_notify_custom_field1'] = true;
			}
			if($custom_field2){
				if($custom_field2['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
					$couponcount = Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
					$postdata['custom_field_value2'] = $couponcount;
				}elseif($custom_field2['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
					$memberlv = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
					$postdata['custom_field_value2'] = $memberlv['name'];
				}elseif($custom_field2['name']=='余额' || $custom_field2['name']==t('余额')){//余额
					$postdata['custom_field_value2'] = $member['money'];
				}elseif($custom_field2['name']==($membercard['custom_field_customize1_name'] ?? '')){
					$postdata['custom_field_value2'] = $membercard['custom_field_customize1_value'] ?? '';
				}elseif($custom_field2['name']==($membercard['custom_field_customize2_name'] ?? '')){
					$postdata['custom_field_value2'] = $membercard['custom_field_customize2_value'] ?? '';
				}
				$notify_optional['is_notify_custom_field2'] = true;
			}
			if($custom_field3){
				if($custom_field3['name_type']=='FIELD_NAME_TYPE_COUPON'){//优惠券
					$couponcount = Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
					$postdata['custom_field_value3'] = $couponcount;
				}elseif($custom_field3['name_type']=='FIELD_NAME_TYPE_LEVEL'){//等级
					$memberlv = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
					$postdata['custom_field_value3'] = $memberlv['name'];
				}elseif($custom_field3['name']=='余额' || $custom_field3['name']==t('余额')){//余额
					$postdata['custom_field_value3'] = $member['money'];
				}elseif($custom_field3['name']==($membercard['custom_field_customize1_name'] ?? '')){
					$postdata['custom_field_value3'] = $membercard['custom_field_customize1_value'] ?? '';
				}elseif($custom_field3['name']==($membercard['custom_field_customize2_name'] ?? '')){
					$postdata['custom_field_value3'] = $membercard['custom_field_customize2_value'] ?? '';
				}
				$notify_optional['is_notify_custom_field3'] = true;
			}
			if($notify_optional){
				$postdata['notify_optional'] = $notify_optional;
			}
			syncRequest($url,jsonEncode($postdata));
			//\think\facade\Log::write($rs);
			return [];
		}
	}

	/**
	 * 会员升级 outbox 专用同步入口，必须得到微信明确响应后才返回成功。
	 */
	public static function updatemembercardWithResult($aid,$mid,$record_bonus=''){
		$aid = intval($aid);
		$mid = intval($mid);
		if($aid <= 0 || $mid <= 0){
			return ['status'=>0,'delivery_state'=>'failed','error_code'=>'member_card_input_invalid','msg'=>'会员卡更新参数无效'];
		}
		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
		if(!$member){
			return ['status'=>0,'delivery_state'=>'failed','error_code'=>'member_missing','msg'=>'会员不存在'];
		}
		$cardId = trim(strval($member['card_id'] ?? ''));
		$cardCode = trim(strval($member['card_code'] ?? ''));
		if($cardId === '' && $cardCode === ''){
			return [
				'status'=>1,
				'delivery_state'=>'succeeded',
				'skipped'=>1,
				'external_receipt'=>'member_card:no_binding:'.$mid,
				'msg'=>'会员未绑定微信会员卡，无需同步',
			];
		}
		if($cardId === '' || $cardCode === ''){
			return ['status'=>0,'delivery_state'=>'failed','error_code'=>'member_card_binding_incomplete','msg'=>'会员卡绑定信息不完整'];
		}

		try{
			$accessToken = trim(strval(self::access_token($aid,'mp')));
			if($accessToken === ''){
				return ['status'=>0,'delivery_state'=>'failed','error_code'=>'member_card_access_token_missing','msg'=>'会员卡 access_token 为空'];
			}
			$getUrl = 'https://api.weixin.qq.com/card/get?access_token='.$accessToken;
			$getRaw = request_post($getUrl,jsonEncode(['card_id'=>$cardId]));
			if(!is_string($getRaw) || trim($getRaw) === ''){
				return ['status'=>0,'delivery_state'=>'unknown','error_code'=>'member_card_get_result_unknown','msg'=>'读取微信会员卡配置未取得响应'];
			}
			$getResult = json_decode($getRaw,true);
			if(!is_array($getResult)){
				return ['status'=>0,'delivery_state'=>'unknown','error_code'=>'member_card_get_response_invalid','msg'=>'读取微信会员卡配置响应无法解析'];
			}
			if(intval($getResult['errcode'] ?? 0) !== 0){
				return [
					'status'=>0,
					'delivery_state'=>'failed',
					'error_code'=>'member_card_get_rejected',
					'wechat_errcode'=>intval($getResult['errcode']),
					'msg'=>trim(strval($getResult['errmsg'] ?? '微信拒绝读取会员卡配置')),
				];
			}
			$card = $getResult['card']['member_card'] ?? null;
			if(!is_array($card)){
				return ['status'=>0,'delivery_state'=>'failed','error_code'=>'member_card_config_missing','msg'=>'微信会员卡配置缺失'];
			}

			$customField1 = is_array($card['custom_field1'] ?? null) ? $card['custom_field1'] : [];
			$customField2 = is_array($card['custom_field2'] ?? null) ? $card['custom_field2'] : [];
			$customField3 = is_array($card['custom_field3'] ?? null) ? $card['custom_field3'] : [];
			$supplyBonus = !empty($card['supply_bonus']);
			$membercard = Db::name('membercard')->where('aid',$aid)->where('card_id',$cardId)->find() ?: [];
			$postdata = ['card_id'=>$cardId,'code'=>$cardCode];
			if($supplyBonus){
				$postdata['bonus'] = $member['score'];
				if($record_bonus) $postdata['record_bonus'] = $record_bonus;
			}
			$notifyOptional = [];
			$fields = [1=>$customField1,2=>$customField2,3=>$customField3];
			foreach($fields as $index=>$field){
				if(!$field) continue;
				$value = self::resolveMemberCardCustomFieldValue($aid,$member,$membercard,$field);
				if($value !== null) $postdata['custom_field_value'.$index] = $value;
				$notifyOptional['is_notify_custom_field'.$index] = true;
			}
			if($notifyOptional) $postdata['notify_optional'] = $notifyOptional;

			$updateUrl = 'https://api.weixin.qq.com/card/membercard/updateuser?access_token='.$accessToken;
			$updateRaw = request_post($updateUrl,jsonEncode($postdata));
			if(!is_string($updateRaw) || trim($updateRaw) === ''){
				return ['status'=>0,'delivery_state'=>'unknown','error_code'=>'member_card_update_result_unknown','msg'=>'微信会员卡更新未取得响应'];
			}
			$updateResult = json_decode($updateRaw,true);
			if(!is_array($updateResult)){
				return ['status'=>0,'delivery_state'=>'unknown','error_code'=>'member_card_update_response_invalid','msg'=>'微信会员卡更新响应无法解析'];
			}
			if(intval($updateResult['errcode'] ?? 0) !== 0){
				return [
					'status'=>0,
					'delivery_state'=>'failed',
					'error_code'=>'member_card_update_rejected',
					'wechat_errcode'=>intval($updateResult['errcode']),
					'msg'=>trim(strval($updateResult['errmsg'] ?? '微信拒绝更新会员卡')),
				];
			}
			$receiptHash = substr(hash('sha256',implode('|',[
				$aid,$mid,$cardId,$cardCode,jsonEncode($postdata)
			])),0,32);
			return [
				'status'=>1,
				'delivery_state'=>'succeeded',
				'skipped'=>0,
				'external_receipt'=>'member_card:updateuser:'.$receiptHash,
				'wechat_errcode'=>0,
				'msg'=>'会员卡同步成功',
			];
		}catch(\Throwable $e){
			return ['status'=>0,'delivery_state'=>'unknown','error_code'=>'member_card_request_exception','msg'=>$e->getMessage()];
		}
	}

	private static function resolveMemberCardCustomFieldValue($aid,$member,$membercard,$field){
		$nameType = strval($field['name_type'] ?? '');
		$name = strval($field['name'] ?? '');
		if($nameType === 'FIELD_NAME_TYPE_COUPON'){
			return Db::name('coupon_record')->where('aid',$aid)->where('mid',$member['id'])->where('status',0)->where('endtime','>=',time())->count();
		}
		if($nameType === 'FIELD_NAME_TYPE_LEVEL'){
			return Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->value('name') ?: '';
		}
		if($name === '余额' || $name === t('余额')) return $member['money'];
		if($name !== '' && $name === strval($membercard['custom_field_customize1_name'] ?? '')){
			return $membercard['custom_field_customize1_value'] ?? '';
		}
		if($name !== '' && $name === strval($membercard['custom_field_customize2_name'] ?? '')){
			return $membercard['custom_field_customize2_value'] ?? '';
		}
		return null;
	}
	public static function setauthinfo($aid,$authorization_code,$createtype=0){
		//使用授权码换取公众号或小程序的接口调用凭据和授权信息
		$url = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token='.self::component_access_token();
		$data = array();
		$data['component_appid'] = self::component_appid();
		$data['authorization_code'] = $authorization_code;
		$rs = request_post($url,jsonEncode($data));
		//dump($rs);die; 
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return ['status'=>0,'msg'=>self::geterror($rs)];
		}
		$info = $rs['authorization_info'];
		$appid = $info['authorizer_appid'];
		$refresh_token = $info['authorizer_refresh_token'];
		if($info){
			$update = array();
			$update['appid'] = $appid;
			$update['access_token'] = $info['authorizer_access_token'];
			$update['expires_time'] = time() + 7000;
			$update['jsapi_ticket'] = '';
			$update['ticket_expires_time'] = null;
			self::saveAccessTokenCache($update['appid'], $update);
		}
		//获取授权方的帐号基本信息
		$url = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token='.self::component_access_token();
		$data = array();
		$data['component_appid'] = self::component_appid();
		$data['authorizer_appid'] = $appid;
		$rs = request_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if(isset($rs['errcode']) && $rs['errcode']!=0){
			return ['status'=>0,'msg'=>self::geterror($rs)];
		}

		$authorizer_info = $rs['authorizer_info'];
		$authorization_info = $rs['authorization_info'];

		$infodata = array();
		$infodata['aid'] = $aid;
		$infodata['authtype'] = 1;
		$infodata['appid'] = $appid;
		$infodata['nickname'] = $authorizer_info['nick_name'];
		$infodata['headimg'] = \app\admin\common\Pic::tolocal($authorizer_info['head_img']);
		$infodata['qrcode'] = \app\admin\common\Pic::tolocal($authorizer_info['qrcode_url']);
		$infodata['refresh_token'] = $refresh_token;
		if($authorizer_info['MiniProgramInfo']){
			$apptype = 'wx';
			$verify_type_info = $authorizer_info['verify_type_info']['id'];
			if($verify_type_info > -1){
				$infodata['level'] = 1; //已认证
			}else{
				$infodata['level'] = 0; //未认证
			}
			$infodata['createtype'] = $createtype;
			if(Db::name('admin_setapp_wx')->where('aid',$aid)->find()){
				Db::name('admin_setapp_wx')->where('aid',$aid)->update($infodata);
			}else{
				Db::name('admin_setapp_wx')->insert($infodata);
			}
		}else{
			$apptype = 'mp';
			$service_type_info = $authorizer_info['service_type_info']['id'];
			$verify_type_info = $authorizer_info['verify_type_info']['id'];
			$infodata['level'] = 0;
			if($service_type_info==2){
				if($verify_type_info > -1){
					$infodata['level'] = 4; //认证服务号
				}else{
					$infodata['level'] = 2; //未认证服务号
				}
			}else{
				if($verify_type_info > -1){
					$infodata['level'] = 3; //认证订阅号
				}else{
					$infodata['level'] = 1; //未认证订阅号
				}
			}
			if(Db::name('admin_setapp_mp')->where('aid',$aid)->find()){
				Db::name('admin_setapp_mp')->where('aid',$aid)->update($infodata);
			}else{
				Db::name('admin_setapp_mp')->insert($infodata);
			}
			Db::name('mp_material')->where('aid',$aid)->delete();
		}
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['name'] == '商城系统' && $set['logo'] == request()->domain().'/static/imgsrc/logo.jpg'){
			Db::name('admin_set')->where('aid',$aid)->update(['name'=>$infodata['nickname'],'logo'=>$infodata['headimg'],'desc'=>$authorizer_info['signature']]);
		}
		$access_token = \app\admin\common\Wechat::access_token($aid,$apptype);
		if($apptype == 'wx'){
			//设置小程序服务器域名
			$url = 'https://api.weixin.qq.com/wxa/modify_domain?access_token='.$access_token;
			$postdata = array();
			$postdata['action'] = 'set';
			$postdata['requestdomain'] = array(str_replace('http://','https://',request()->domain()));
			$postdata['wsrequestdomain'] = array('wss://'.$_SERVER['HTTP_HOST']);
			$postdata['uploaddomain'] = array(str_replace('http://','https://',request()->domain()));
			$postdata['downloaddomain'] = array(str_replace('http://','https://',request()->domain()));
			$rs = request_post($url,jsonEncode($postdata));
			//dump($rs);
			//设置小程序业务域名
			$url = 'https://api.weixin.qq.com/wxa/setwebviewdomain?access_token='.$access_token;
			$postdata = array();
			//$postdata['action'] = 'add';
			$postdata['webviewdomain'] = array(request()->domain());
			$rs = request_post($url,jsonEncode($postdata));
			//dump($rs);die;
		}
		//获取开放平台账号appid
		$url = 'https://api.weixin.qq.com/cgi-bin/open/get?access_token='.$access_token;
		$postdata = array();
		$postdata['appid'] = $appid;
		$rs = request_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs && $rs['open_appid']){
			$open_appid = $rs['open_appid'];
		}elseif($rs['errcode']==0 || $rs['errcode']==89002){
			$open_appid = null;
		}else{
			$open_appid = '-1'; //没有权限 获取不到
		}
		if($apptype == 'wx'){
			Db::name('admin_setapp_wx')->where('aid',$aid)->update(['open_appid'=>$open_appid]);
			\app\admin\common\System::plog('授权绑定小程序');
		}else{
			Db::name('admin_setapp_mp')->where('aid',$aid)->update(['open_appid'=>$open_appid]);
			\app\admin\common\System::plog('授权绑定公众号');
		}
		return ['status'=>1];
	}
	
	//微信公众平台返回错误码对应含义
	public static function geterror($rs){
		if(is_object($rs)) $rs = (array)$rs;
		$err = array('-1'=>'系统繁忙','0'=>'请求成功','40001'=>'不合法的调用凭证','40002'=>'不合法的凭证类型','40003'=>'不合法的OpenID','40004'=>'不合法的媒体文件类型','40005'=>'不合法的文件类型','40006'=>'不合法的文件大小','40007'=>'不合法的media_id','40008'=>'不合法的消息类型','40009'=>'不合法的图片大小','40010'=>'不合法的语音大小','40011'=>'不合法的视频大小','40012'=>'不合法的缩略图大小','40013'=>'不合法的AppID','40014'=>'不合法的access_token','40015'=>'不合法的菜单类型','40016'=>'不合法的按钮个数','40017'=>'不合法的按钮类型','40018'=>'不合法的按钮名称长度','40019'=>'不合法的按钮KEY长度','40019'=>'不合法的按钮KEY长度','40020'=>'不合法的url长度','40021'=>'不合法的菜单版本号','40022'=>'不合法的子菜单级数','40023'=>'不合法的子菜单按钮个数','40024'=>'不合法的子菜单类型','40025'=>'不合法的子菜单按钮名称长度','40026'=>'不合法的子菜单按钮KEY长度','40027'=>'不合法的子菜单按钮url长度','40028'=>'不合法的自定义菜单使用用户','40029'=>'不合法或已过期的code','40030'=>'不合法的refresh_token','40031'=>'不合法的 openid 列表','40032'=>'不合法的 openid 列表长度','40033'=>'不合法的请求字符','40035'=>'不合法的参数','40036'=>'不合法的template_id长度','40037'=>'不合法的template_id','40038'=>'不合法的请求格式','40039'=>'不合法的url长度','40048'=>'不合法的url域名','40050'=>'不合法的分组 id','40051'=>'分组名字不合法','40054'=>'不合法的子菜单url域名','40055'=>'不合法的菜单url域名','40056'=>'不合法的agentid','40057'=>'不合法的callbackurl或者callbackurl验证失败','40058'=>'不合法的参数','40059'=>'不合法的上报地理位置标志位','40060'=>'删除单篇图文时，指定的 article_idx 不合法','40063'=>'参数为空','40066'=>'不合法的url或部门列表','40068'=>'不合法的标签ID','40070'=>'指定的标签范围结点全部无效','40071'=>'不合法的标签名字','40072'=>'不合法的标签名字长度','40073'=>'不合法的openid','40074'=>'news消息不支持保密消息类型','40077'=>'不合法的pre_auth_code参数','40078'=>'不合法的auth_code参数','40080'=>'不合法的suite_secret','40082'=>'不合法的suite_token','40083'=>'不合法的suite_id','40084'=>'不合法的permanent_code参数','40085'=>'不合法的的suite_ticket参数','40086'=>'不合法的第三方应用appid','40088'=>'jobid不存在','40089'=>'批量任务的结果已清理','40091'=>'secret不合法','40092'=>'导入文件存在不合法的内容','40093'=>'不合法的jsapi_ticket参数','40094'=>'不合法的URL','40096'=>'不合法的外部联系人userid','40097'=>'参数错误','40098'=>'接替成员尚未实名认证','40099'=>'接替成员的外部联系人数量已达上限','40100'=>'此用户的外部联系人已经在转移流程中','40117'=>'分组名字不合法','40118'=>'media_id 大小不合法','40119'=>'button 类型错误','40120'=>'button 类型错误','40121'=>'不合法的 media_id 类型','40125'=>'无效的appsecret','40132'=>'微信号不合法','40137'=>'不支持的图片格式','40155'=>'请勿添加其他公众号的主页链接','40163'=>'oauth_code已使用','41001'=>'缺失access_token参数','41002'=>'缺失appid参数','41003'=>'缺失refresh_token参数','41004'=>'缺失secret参数','41005'=>'缺少多媒体文件数据','41006'=>'缺失media_id参数','41007'=>'缺失子菜单数据','41008'=>'缺失code参数','41009'=>'缺失openid参数','41010'=>'缺失url参数','41011'=>'缺少agentid参数','41016'=>'缺少title参数','41017'=>'缺少tagid参数','41019'=>'缺少 department 参数','41021'=>'缺少suite_id参数','41022'=>'缺少suite_access_token参数','41023'=>'缺少suite_ticket参数','41024'=>'缺少secret参数','41025'=>'缺少permanent_code参数','41033'=>'缺少 description 参数','41035'=>'缺少外部联系人userid参数','42001'=>'access_token已过期','42002'=>'refresh_token超时','42003'=>'code超时','42007'=>'pre_auth_code已过期','42009'=>'suite_access_token已过期','43001'=>'需要使用GET方法请求','43002'=>'需要使用POST方法请求','43003'=>'需要使用HTTPS','43004'=>'需要接收者关注','43005'=>'需要好友关系','43019'=>'需要将接收者从黑名单中移除','44001'=>'多媒体文件为空','44002'=>'POST 的数据包为空','44003'=>'图文消息内容为空','44004'=>'文本消息内容为空','44005'=>'空白的列表','45001'=>'多媒体文件大小超过限制','45002'=>'消息内容大小超过限制','45003'=>'标题字段超过限制','45004'=>'描述字段超过限制','45005'=>'链接字段超过限制','45006'=>'图片链接字段超过限制','45007'=>'语音播放时间超过限制','45008'=>'图文消息超过限制','45009'=>'接口调用超过限制','45010'=>'创建菜单个数超过限制','45011'=>'API 调用太频繁，请稍候再试','45012'=>'模板大小超过限制','45015'=>'回复时间超过限制','45016'=>'不能修改默认组','45017'=>'分组名字过长','45018'=>'分组数量超过上限','45022'=>'应用name参数长度不符合系统限制','45024'=>'帐号数量超过上限','45026'=>'已添加的模板数量超过限制','45032'=>'图文消息author参数长度超过限制','45033'=>'接口并发调用超过限制','45047'=>'客服接口下行条数超过上限','45064'=>'创建菜单包含未关联的小程序','45065'=>'相同 clientmsgid 已存在群发记录，返回数据中带有已存在的群发任务的 msgid','45066'=>'相同 clientmsgid 重试速度过快，请间隔1分钟重试','45067'=>'clientmsgid 长度超过限制','46001'=>'不存在媒体数据','46002'=>'不存在的菜单版本','46003'=>'不存在的菜单数据','46004'=>'指定的用户不存在','47001'=>'解析 JSON/XML 内容错误','48001'=>'api 功能未授权，请确认公众号已获得该接口，可以在公众平台官网 - 开发者中心页中查看接口权限','48002'=>'粉丝拒收消息（粉丝在公众号选项中，关闭了 “ 接收消息 ” ）','48003'=>'不合法的suite_id','48004'=>'api 接口被封禁或授权关系无效','48005'=>'api 禁止删除被自动回复和自定义菜单引用的素材或API接口已废弃','48006'=>'api 禁止清零调用次数，因为清零次数达到上限','48008'=>'没有该类型消息的发送权限','50001'=>'接口未授权或redirect_url未登记可信域名','50002'=>'用户受限，可能是违规后接口被封禁','50003'=>'应用已禁用','50005'=>'用户未关注公众号','53010'=>'名称格式不合法','53011'=>'名称检测命中频率限制','53012'=>'禁止使用该名称','53013'=>'公众号：名称与已有公众号名称重复;小程序：该名称与已有小程序名称重复','53014'=>'公众号：公众号已有{名称A+}时，需与该帐号相同主体才可申请{名称A};小程序：小程序已有{名称A+}时，需与该帐号相同主体才可申请{名称A}','53015'=>'公众号：该名称与已有小程序名称重复，需与该小程序帐号相同主体才可申请;小程序：该名称与已有公众号名称重复，需与该公众号帐号相同主体才可申请','53016'=>'公众号：该名称与已有多个小程序名称重复，暂不支持申请;小程序：该名称与已有多个公众号名称重复，暂不支持申请','53017'=>'公众号：小程序已有{名称A+}时，需与该帐号相同主体才可申请{名称A};小程序：公众号已有{名称A+}时，需与该帐号相同主体才可申请{名称A}','53018'=>'名称命中微信号','53019'=>'名称在保护期内','53200'=>'本月功能介绍修改次数已用完','53201'=>'功能介绍内容命中黑名单关键字','53202'=>'本月头像修改次数已用完','53300'=>'超出每月次数限制','53301'=>'超出可配置类目总数限制','53302'=>'当前账号主体类型不允许设置此种类目','53303'=>'提交的参数不合法','53304'=>'与已有类目重复','53305'=>'包含未通过IPC校验的类目','53306'=>'修改类目只允许修改类目资质，不允许修改类目ID','53307'=>'只有审核失败的类目允许修改','53308'=>'审核中的类目不允许删除','60001'=>'部门长度不符合限制','60003'=>'部门ID不存在','60004'=>'父部门不存在','60005'=>'部门下存在成员','60006'=>'部门下存在子部门','60007'=>'不允许删除根部门','60008'=>'部门已存在','60009'=>'部门名称含有非法字符','60010'=>'部门存在循环关系','60011'=>'指定的成员/部门/标签参数无权限','60012'=>'不允许删除默认应用','60020'=>'访问ip不在白名单之中','60028'=>'不允许修改第三方应用的主页 URL','60102'=>'UserID已存在','60103'=>'手机号码不合法','60104'=>'手机号码已存在','60105'=>'邮箱不合法','60106'=>'邮箱已存在','60107'=>'微信号不合法','60110'=>'用户所属部门数量超过限制','60111'=>'UserID不存在','60112'=>'成员name参数不合法','60123'=>'无效的部门id','60124'=>'无效的父部门id','60125'=>'非法部门名字','60127'=>'缺少department参数','60129'=>'成员手机和邮箱都为空','61450'=>'系统错误 (system error)','61451'=>'参数错误 (invalid parameter)','61452'=>'无效客服账号 (invalid kf_account)','61453'=>'客服帐号已存在 (kf_account exsited)','61454'=>'客服帐号名长度超过限制 ( 仅允许 10 个英文字符，不包括 @ 及 @ 后的公众号的微信号 )(invalid kf_acount length)','61455'=>'客服帐号名包含非法字符 ( 仅允许英文 + 数字 )(illegal character in kf_account)','61456'=>'客服帐号个数超过限制 (10 个客服账号 )(kf_account count exceeded)','61457'=>'无效头像文件类型 (invalid file type)','61500'=>'日期格式错误','65301'=>'不存在此 menuid 对应的个性化菜单','65302'=>'没有相应的用户','65303'=>'没有默认菜单，不能创建个性化菜单','65304'=>'MatchRule 信息为空','65305'=>'个性化菜单数量受限','65306'=>'不支持个性化菜单的帐号','65307'=>'个性化菜单信息为空','65308'=>'包含没有响应类型的 button','65309'=>'个性化菜单开关处于关闭状态','65310'=>'填写了省份或城市信息，国家信息不能为空','65311'=>'填写了城市信息，省份信息不能为空','65312'=>'不合法的国家信息','65313'=>'不合法的省份信息','65314'=>'不合法的城市信息','65316'=>'该公众号的菜单设置了过多的域名外跳（最多跳转到 3 个域名的链接）','65317'=>'不合法的 URL','72023'=>'发票已被其他公众号锁定','72024'=>'发票状态错误','72037'=>'存在发票不属于该用户','80001'=>'可信域名不正确，或者无ICP备案','81001'=>'部门下的结点数超过限制（3W）','81002'=>'部门最多15层','81011'=>'无权限操作标签','81013'=>'UserID、部门ID、标签ID全部非法或无权限','81014'=>'标签添加成员，单次添加user或party过多','82001'=>'指定的成员/部门/标签全部无效','82002'=>'不合法的PartyID列表长度','82003'=>'不合法的TagID列表长度','84014'=>'成员票据过期','84015'=>'成员票据无效','84019'=>'缺少templateid参数','84020'=>'templateid不存在','84021'=>'缺少register_code参数','84022'=>'无效的register_code参数','84023'=>'不允许调用设置通讯录同步完成接口','84024'=>'无注册信息','84025'=>'不符合的state参数','84052'=>'缺少caller参数','84053'=>'缺少callee参数','84054'=>'缺少auth_corpid参数','84055'=>'超过拨打公费电话频率','84056'=>'被拨打用户安装应用时未授权拨打公费电话权限','84057'=>'公费电话余额不足','84058'=>'caller 呼叫号码不支持','84059'=>'号码非法','84060'=>'callee 呼叫号码不支持','84061'=>'不存在外部联系人的关系','84062'=>'未开启公费电话应用','84063'=>'caller不存在','84064'=>'callee不存在','84065'=>'caller跟callee电话号码一致','84066'=>'服务商拨打次数超过限制','84067'=>'管理员收到的服务商公费电话个数超过限制','84071'=>'不合法的外部联系人授权码','84072'=>'应用未配置客服','84073'=>'客服userid不在应用配置的客服列表中','84074'=>'没有外部联系人权限','85001'=>'微信号不存在或微信号设置为不可搜索','85002'=>'小程序绑定的体验者数量达到上限','85002'=>'包含不合法的词语','85003'=>'微信号绑定的小程序体验者达到上限','85004'=>'微信号已经绑定','85004'=>'每企业每个月设置的可信域名不可超过20个','85005'=>'可信域名未通过所有权校验','85006'=>'标签格式错误','85007'=>'页面路径错误','85008'=>'类目填写错误','85009'=>'已经有正在审核的版本','85010'=>'item_list有项目为空','85011'=>'标题填写错误','85012'=>'无效的审核id','85012'=>'无效的审核id','85013'=>'无效的自定义配置','85014'=>'无效的模版编号','85015'=>'该账号不是小程序账号','85015'=>'版本输入错误','85016'=>'域名数量超过限制','85017'=>'没有新增域名，请确认小程序已经添加了域名或该域名是否没有在第三方平台添加','85018'=>'域名没有在第三方平台设置','85019'=>'没有审核版本','85020'=>'审核状态未满足发布','85023'=>'审核列表填写的项目数不在1-5以内','85026'=>'微信号绑定管理员名额达到上限','85027'=>'身份证绑定管理员名额达到上限','85043'=>'模版错误','85044'=>'代码包超过大小限制','85045'=>'ext_json有不存在的路径','85046'=>'tabBar中缺少path','85047'=>'pages字段为空','85048'=>'ext_json解析失败','85060'=>'无效的taskid','85061'=>'手机号绑定管理员名额达到上限','85062'=>'手机号黑名单','85063'=>'身份证黑名单','85064'=>'找不到模版','85064'=>'找不到模版','85064'=>'找不到草稿','85064'=>'找不到草稿','85065'=>'模版库已满','85066'=>'链接错误','85068'=>'测试链接不是子链接','85069'=>'校验文件失败','85070'=>'链接为黑名单','85071'=>'已添加该链接，请勿重复添加','85072'=>'该链接已被占用','85073'=>'二维码规则已满','85074'=>'小程序未发布, 小程序必须先发布代码才可以发布二维码跳转规则','85075'=>'个人类型小程序无法设置二维码规则','85076'=>'链接没有ICP备案','85077'=>'小程序类目信息失效（类目中含有官方下架的类目，请重新选择类目）','85079'=>'小程序没有线上版本，不能进行灰度','85080'=>'小程序提交的审核未审核通过','85081'=>'无效的发布比例','85082'=>'当前的发布比例需要比之前设置的高','85083'=>'搜索标记位被封禁，无法修改','85084'=>'非法的status值，只能填0或者1','85085'=>'近7天提交审核的小程序数量过多，请耐心等待审核完毕后再次提交','85086'=>'提交代码审核之前需提前上传代码','85087'=>'小程序已使用api navigateToMiniProgram，请声明跳转appid列表后再次提交','86000'=>'不是由第三方代小程序进行调用','86000'=>'不是由第三方代小程序进行调用','86000'=>'不是由第三方代小程序进行调用','86001'=>'不存在第三方的已经提交的代码','86001'=>'不存在第三方的已经提交的代码','86001'=>'不存在第三方的已经提交的代码','86001'=>'参数 chatid 不合法','86002'=>'小程序未初始化完成，请确保已设置小程序昵称、头像、简介、服务类目','86003'=>'参数 chatid 不存在','86004'=>'参数 群名不合法','86005'=>'参数 群主不合法','86006'=>'群成员数过多或过少','86007'=>'不合法的群成员','86008'=>'非法操作非自己创建的群','86101'=>'仅群主才有操作权限','86201'=>'参数 需要chatid','86202'=>'参数 需要群名','86203'=>'参数 需要群主','86204'=>'参数 需要群成员','86205'=>'参数 字符串chatid过长','86206'=>'参数 数字chatid过大','86207'=>'群主不在群成员列表','86215'=>'会话ID已经存在','86216'=>'存在非法会话成员ID','86217'=>'会话发送者不在会话成员列表中','86220'=>'指定的会话参数不合法','87011'=>'现网已经在灰度发布，不能进行版本回退','87012'=>'该版本不能回退，可能的原因：1:无上一个线上版用于回退 2:此版本为已回退版本，不能回退 3:此版本为回退功能上线之前的版本，不能回退','87013'=>'撤回次数达到上限（每天一次，每个月10次）','89000'=>'该公众号 / 小程序 已经绑定了开放平台帐号','89001'=>'not same contractor，Authorizer与开放平台帐号主体不相同','89001'=>'not same contractor，Authorizer与开放平台帐号主体不相同','89002'=>'open not exists，该公众号/小程序未绑定微信开放平台帐号。','89003'=>'该开放平台帐号并非通过api创建，不允许操作','89003'=>'该开放平台帐号并非通过api创建，不允许操作','89004'=>'该开放平台帐号所绑定的公众号/小程序已达上限（100个）','89019'=>'业务域名无更改，无需重复设置','89020'=>'尚未设置小程序业务域名，请先在第三方平台中设置小程序业务域名后在调用本接口','89021'=>'请求保存的域名不是第三方平台中已设置的小程序业务域名或子域名','89029'=>'业务域名数量超过限制','89231'=>'个人小程序不支持调用setwebviewdomain 接口','89236'=>'该插件不能申请','89237'=>'已经添加该插件','89238'=>'申请或使用的插件已经达到上限','89239'=>'该插件不存在','89256'=>'token信息有误','89257'=>'该插件版本不支持快速更新','89258'=>'当前小程序帐号存在灰度发布中的版本，不可操作快速更新','90001'=>'未认证摇一摇周边','90002'=>'缺少摇一摇周边ticket参数','90003'=>'摇一摇周边ticket参数不合法','90100'=>'非法的对外属性类型','90101'=>'对外属性：文本类型长度不合法','90102'=>'对外属性：网页类型标题长度不合法','90103'=>'对外属性：网页url不合法','90104'=>'对外属性：小程序类型标题长度不合法','90105'=>'对外属性：小程序类型pagepath不合法','90106'=>'对外属性：请求参数不合法','91001'=>'不是公众号快速创建的小程序','91002'=>'小程序发布后不可改名','91003'=>'改名状态不合法','91004'=>'昵称不合法','91005'=>'昵称命中主体保护','91006'=>'昵称命中微信号','91007'=>'昵称已被占用','91008'=>'昵称命中7天侵权保护期','91009'=>'需要提交材料','91010'=>'其他错误','91011'=>'查不到昵称修改审核单信息','91012'=>'其它错误','91040'=>'获取ticket的类型无效','301002'=>'无权限操作指定的应用','301005'=>'不允许删除创建者','301012'=>'参数 position 不合法','301013'=>'参数 telephone 不合法','301014'=>'参数 english_name 不合法','301015'=>'参数 mediaid 不合法','301016'=>'上传语音文件不符合系统要求','301017'=>'上传语音文件仅支持AMR格式','301021'=>'参数 userid 无效','301022'=>'获取打卡数据失败','301023'=>'useridlist非法或超过限额','301024'=>'获取打卡记录时间间隔超限','301036'=>'不允许更新该用户的userid','302003'=>'批量导入任务的文件中userid有重复','302004'=>'组织架构不合法（1不是一棵树，2 多个一样的partyid，3 partyid空，4 partyid name 空，5 同一个父节点下有两个子节点 部门名字一样 可能是以上情况，请一一排查）','302005'=>'批量导入系统失败，请重新尝试导入','302006'=>'批量导入任务的文件中partyid有重复','302007'=>'批量导入任务的文件中，同一个部门下有两个子部门名字一样','600001'=>'不合法的sn','600002'=>'设备已注册','600003'=>'不合法的硬件activecode','600004'=>'该硬件尚未授权任何企业','600005'=>'硬件Secret无效','600007'=>'缺少硬件sn','600008'=>'缺少nonce参数','600009'=>'缺少timestamp参数','600010'=>'缺少signature参数','600011'=>'签名校验失败','600012'=>'长连接已经注册过设备','600013'=>'缺少activecode参数','600014'=>'设备未网络注册','600015'=>'缺少secret参数','600016'=>'设备未激活','600018'=>'无效的起始结束时间','600020'=>'设备未登录','600021'=>'设备sn已存在','600023'=>'时间戳已失效','600024'=>'固件大小超过5M','600025'=>'固件名为空或者超过20字节','600026'=>'固件信息不存在','600027'=>'非法的固件参数','600028'=>'固件版本已存在','600029'=>'非法的固件版本','600030'=>'缺少固件版本参数','600031'=>'硬件固件不允许升级','600032'=>'无法解析硬件二维码','2000002'=>'CorpId参数无效','9001001'=>'POST 数据参数不合法','9001002'=>'远端服务不可用','9001003'=>'Ticket 不合法','9001004'=>'获取摇周边用户信息失败','9001005'=>'获取商户信息失败','9001006'=>'获取 OpenID 失败','9001007'=>'上传文件缺失','9001008'=>'上传素材的文件类型不合法','9001009'=>'上传素材的文件尺寸不合法','9001010'=>'上传失败','9001020'=>'帐号不合法','9001021'=>'已有设备激活率低于 50% ，不能新增设备','9001022'=>'设备申请数不合法，必须为大于 0 的数字','9001023'=>'已存在审核中的设备 ID 申请','9001024'=>'一次查询设备 ID 数量不能超过 50','9001025'=>'设备 ID 不合法','9001026'=>'页面 ID 不合法','9001027'=>'页面参数不合法','9001028'=>'一次删除页面 ID 数量不能超过 10','9001029'=>'页面已应用在设备中，请先解除应用关系再删除','9001030'=>'一次查询页面 ID 数量不能超过 50','9001031'=>'时间区间不合法','9001032'=>'保存设备与页面的绑定关系参数错误','9001033'=>'门店 ID 不合法','9001034'=>'设备备注信息过长','9001035'=>'设备申请参数不合法','9001036'=>'查询起始值 begin 不合法',
			'89007'=>'小程序本月被关联的名额已用完','89008'=>'小程序为海外帐号，不允许关联','89009'=>'小程序关联达到上限','89010'=>'已经发送关联邀请','89011'=>'在附近中展示的小程序不能取消关联','89012'=>'门店、小店小程序不能取消关联','89013'=>'小程序被封禁','89015'=>'已经关联该小程序','89016'=>'公众号本月关联相同主体达到上限','89017'=>'公众号本月关联不同主体达到上限','89035'=>'已经从公众平台后台发起关联申请，处于小程序管理员确认中，无法从第三方重复发起关联申请','88000'=>'公众号没有开通留言功能','41030'=>'小程序页面尚未发布,无法生成','61051'=>'公众号主体类型不允许快速创建','61052'=>'公众号未认证','61053'=>'超过主体可注册数量上限','61054'=>'主体黑名单','61055'=>'超出公众号每月可快速创建限额','61056'=>'政府、媒体、其他组织必须复选微信认证','61057'=>'公众号仍有快速创建的账号在流程中','61058'=>'用户扫码凭证校验不通过','61028'=>'第三方平台未发布','61029'=>'第三方平台缺少必备权限集(帐号服务权限、程序帐号管理权限、小程序开发管理与数据分析权限)','61060'=>'转 uri 不合法','61061'=>'海外帐号不允许快速创建','89249'=>'该主体已有任务执行中，距上次任务24h后再试','89247'=>'内部错误','86004'=>'无效微信号','61070'=>'企业代码类型无效，请选择正确类型填写','89250'=>'未找到该任务','89251'=>'待法人人脸核身校验','89252'=>'法人&企业信息一致性校验中	','89253'=>'缺少参数','89254'=>'第三方权限集不全，补全权限集全网发布后生效','89255'=>'企业代码不正确','92000'=>'该经营资质已添加，请勿重复添加','92002'=>'附近地点添加数量达到上线，无法继续添加','92003'=>'地点已被其它小程序占用','92004'=>'附近功能被封禁','92005'=>'地点正在审核中','92006'=>'地点正在展示小程序','92007'=>'地点审核失败','92008'=>'程序未展示在该地点','93009'=>'小程序未上架或不可见','93010'=>'地点不存在','93011'=>'个人类型小程序不可用','93012'=>'非普通类型小程序（门店小程序、小店小程序等）不可用','93013'=>'从腾讯地图获取地址详细信息失败','93014'=>'同一资质证件号重复添加','61007'=>'无接口权限或您授权了多个平台,请前往公众平台登录小程序账号,在[设置-第三方设置]中取消所有授权,然后重新授权','1003'=>'商品id不存在','300001'=>'禁止创建/更新商品 或 禁止编辑&更新房间','300002'=>'名称长度不符合规则','300003'=>'价格输入不合规（如：现价比原价大、传入价格非数字等）','300004'=>'商品名称存在违规违法内容','300005'=>'商品图片存在违规违法内容','300006'=>'图片上传失败（如：mediaID过期）','300007'=>'线上小程序版本不存在该链接','300008：添加商品失败','300009：商品审核撤回失败','300010：商品审核状态不对（如：商品审核中）','300011：操作非法（API不允许操作非API创建的商品）','300012'=>'没有提审额度（每天500次提审额度）','300013'=>'提审失败','300014'=>'审核中，无法删除（非零代表失败）','300017'=>'商品未提审','300018'=>'商品图片尺寸过大','300021'=>'商品添加成功，审核失败','300022'=>'此房间号不存在','300023'=>'房间状态 拦截（当前房间状态不允许此操作）','300024'=>'商品不存在','300025'=>'商品审核未通过','300026'=>'房间商品数量已经满额','300027'=>'导入商品失败','300028'=>'房间名称违规','300029'=>'主播昵称违规','300030'=>'主播微信号不合法','300031'=>'直播间封面图不合规','300032'=>'直播间分享图违规','300033'=>'添加商品超过直播间上限','300034'=>'主播微信昵称长度不符合要求','300035'=>'主播微信号不存在','300036'=>'主播微信号未实名认证','300037'=>'购物直播频道封面图不合规','300038'=>'未在小程序管理后台配置客服','300039'=>'主播副号微信号不合法','300040'=>'名称含有非限定字符（含有特殊字符）','300041'=>'创建者微信号不合法','9410000'=>'直播间列表为空','9410001'=>'获取房间失败','9410002'=>'获取商品失败','9410003'=>'获取回放失败','400001'=>'微信号不合规','400002'=>'微信号需要实名认证，仅设置主播角色时可能出现','400003'=>'添加角色达到上限（管理员10个，运营者500个，主播500个）','400004'=>'重复添加角色','400005'=>'主播角色删除失败，该主播存在未开播的直播间',
		);
		if(is_array($rs)){
			return $err[$rs['errcode']] ? $err[$rs['errcode']] : $rs['errcode'].': '.$rs['errmsg'];
		}else{
			return $err[$rs] ? $err[$rs] : $rs;
		}
	}
	
	//服务商设置广告分账比例
    public static function setCustomShareRatio($aid,$platform,$share_ratio){
        try {
            $component_token = self::component_access_token();
            $url = "https://api.weixin.qq.com/wxa/setdefaultamsinfo?action=agency_set_custom_share_ratio&access_token={$component_token}";
            $data['share_ratio'] = (int)$share_ratio;
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,'true');
            if($rs['ret'] =='0'){
                return true;
            }else{
                return false;  
            }
        }catch (\Exception $e){
            return false;
        }
    }
    //服务商获取广告分账比例
    public static function getCustomShareRatio($aid,$platform){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            $component_token = self::component_access_token();
            $url = "https://api.weixin.qq.com/wxa/getdefaultamsinfo?action=agency_get_custom_share_ratio&access_token={$component_token}";
            $data['appid'] = $appid;
            $rs = request_post($url,jsonEncode($data));
            \think\facade\Log::write('获取分账比例_'.$rs);
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['share_ratio' => $rs['share_ratio']];
            }else{
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }
    //是否能开启流量主 小程序端
    public static function isOpenPublisher($aid,$platform){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_check_can_open_publisher&access_token={$access_token}";
            $rs = request_post($url,jsonEncode([]));
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['status'=>1, 'isopen'=>$rs['status']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                $msg =  $msgdata[$rs['ret']]??$rs['errmsg'];
                throw  new Exception($msg);
            }
        }catch (\Exception $e){
            return ['status' => 0,'msg' =>  $e->getMessage()];
        }
    }
    //开通流量主
    public static function createPublisher($aid,$platform='wx'){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_create_publisher&access_token={$access_token}";
            $rs = request_post($url,jsonEncode([]));
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return true;
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2021' => '已开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['ret']]);
            }
        }catch (\Exception $e){
            return ['status' =>0,'msg' =>  $e->getMessage()];
        }
    }

    //创建广告
    public static function createAdunit($aid,$platform='wx',$name,$type,$tmpl_id=''){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_create_adunit&access_token={$access_token}";
            $data['name'] = $name;
            $data['type'] = $type;
            if($tmpl_id){
                $data['tmpl_type'] = (int)$tmpl_id; 
            }
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['ad_unit_id' => $rs['ad_unit_id']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                $msg = $msgdata[$rs['ret']]??$rs['msg'];
                throw  new Exception($msg);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    public static function editAdunit($aid,$platform='wx',$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_update_adunit&access_token={$access_token}";
            $rs = request_post($url,jsonEncode($data));
            \think\facade\Log::write('编辑广告_'.$rs);
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return true;
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    //获取 代码
    public static function getAdunitCode($aid,$platform='wx',$ad_unit_id){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_get_adunit_code&access_token={$access_token}";
            $data['ad_unit_id'] = $ad_unit_id;
            $rs = request_post($url,jsonEncode($data));
            \think\facade\Log::write('获取代码_'.$rs);
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['code' => $rs['code']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                
                throw  new Exception($msgdata[$rs['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    //获取细分数据
    public static function getAdxfData($aid,$platform='wx',$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_get_adunit_general&access_token={$access_token}";
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['base_resp']['ret'] =='0'){
                return ['list' =>$rs['list'],'total_num' => $rs['total_num']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['base_resp']['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    //获取小程序广告汇总数据
    public static function getAdSummaryData($aid,$platform,$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_get_adpos_genenral&access_token={$access_token}";
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['base_resp']['ret'] =='0'){
                return ['list' =>$rs['list'],'total_num' => $rs['total_num'],'summary' =>$rs['summary']];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['base_resp']['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }

    //获取小程序结算收入数据
    public static function getAdSettleData($aid,$platform,$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $access_token = self::access_token($aid,$platform);
            $url = "https://api.weixin.qq.com/wxa/operationams?action=agency_get_settlement&access_token={$access_token}";
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['base_resp']['ret'] =='0'){
                return ['list' =>$rs['settlement_list'],'jsdata' => ['revenue_all' =>$rs['revenue_all'],'penalty_all' => $rs['penalty_all'],'settled_revenue_all'=>$rs['settled_revenue_all'] ]];
            }else{
                $msgdata  =[
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['base_resp']['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }
    //服务商结算数据
    public static function getComponentSettleData($aid,$platform,$data){
        try {
            $appinfo = System::appinfo($aid,$platform);
            $appid = $appinfo['appid'];
            if(!$appid ) return ['msg' => '请配置小程序'];
            if($appinfo['authtype'] ==0){
                $appsecret = $appinfo['appsecret'];
                if(!$appsecret) return ['msg' => '请配置小程序信息'];
            }
            $component_token = self::component_access_token();
            $url = "https://api.weixin.qq.com/wxa/operationams?action=get_agency_settled_revenue&access_token={$component_token}";
            $rs = request_post($url,jsonEncode($data));
            $rs = json_decode($rs,true);
            if($rs['ret'] =='0'){
                return ['list' =>$rs['settlement_list'],'jsdata' => ['revenue_all' =>$rs['revenue_all'],'penalty_all' => $rs['penalty_all'],'settled_revenue_all'=>$rs['settled_revenue_all'] ]];
            }else{
                $msgdata  =[
                    '-202' => '可在一段时间后重试',
                    '1700' => '参数错误',
                    '1701' => '参数错误',
                    '1735' => '请完成签约操作',
                    '1737' => '等待一分钟后重新操作',
                    '1803' => '广告单元名称重复',
                    '1807' => '请开通流量主',
                    '2009' => '请开通流量主',
                    '2056' => '请在第三方平台页面的变现专区开通服务',
                ];
                throw  new Exception($msgdata[$rs['ret']]);
            }
        }catch (\Exception $e){
            return ['msg' =>  $e->getMessage()];
        }
    }

    /**
     * 查询小程序是否已开通发货信息管理服务 https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/business-capabilities/order-shipping/order-shipping.html#%E4%B8%83%E3%80%81%E6%9F%A5%E8%AF%A2%E5%B0%8F%E7%A8%8B%E5%BA%8F%E6%98%AF%E5%90%A6%E5%B7%B2%E5%BC%80%E9%80%9A%E5%8F%91%E8%B4%A7%E4%BF%A1%E6%81%AF%E7%AE%A1%E7%90%86%E6%9C%8D%E5%8A%A1
     * @param $aid
     * @param $platform
     * @return array|void
     */
    public static function isTradeManaged($aid,$platform='wx'){
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/sec/order/is_trade_managed?access_token={$token}";
                $data['appid'] = $appid;
                $rs = request_post($url,jsonEncode($data));
                \think\facade\Log::write('查询小程序是否已开通发货信息管理服务:'.$rs);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1, 'is_trade_managed' => $rs['is_trade_managed']];
                }else{
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * //发货信息录入接口 https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/business-capabilities/order-shipping/order-shipping.html#%E4%B8%80%E3%80%81%E5%8F%91%E8%B4%A7%E4%BF%A1%E6%81%AF%E5%BD%95%E5%85%A5%E6%8E%A5%E5%8F%A3
     * @param $aid
     * @param $platform
     * @param $data
     * @return array|int[]|void
     * 报错：支付单不存在（10秒后再次请求，微信官方同步订单需要10秒）
     */
    public static function uploadShippingInfo($aid,$platform='wx',$data=[]){
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/sec/order/upload_shipping_info?access_token={$token}";
                \think\facade\Log::write('发货信息录入接口:'.jsonEncode($data));
                $rs = request_post($url,jsonEncode($data));
                \think\facade\Log::write('发货信息录入接口:'.$rs);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1];
                }else{
                    \think\facade\Log::error('发货信息录入接口:'.jsonEncode($rs));
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 获取运力id列表 https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/industry/express/business/express_search.html
     * @param $aid
     * @param $platform
     * @param $data
     * @return array|void
     */
    public static function get_delivery_list($aid,$platform='wx'){
        $wx_get_delivery_list = cache('wx_get_delivery_list');
        if(!$wx_get_delivery_list){
            try {
                $appinfo = System::appinfo($aid,$platform);
                if($appinfo){
                    $appid = $appinfo['appid'];
                    $token = self::access_token($aid,$platform);
                    $url = "https://api.weixin.qq.com/cgi-bin/express/delivery/open_msg/get_delivery_list?access_token={$token}";
                    $rs = request_post($url,'{}');
                    \think\facade\Log::write('get_delivery_list:'.$rs);
                    $rs = json_decode($rs,true);
                    if($rs['errcode'] =='0'){
                        cache('wx_get_delivery_list', $rs['delivery_list']);
                        return ['status' => 1,'delivery_list'=>$rs['delivery_list']];
                    }else{
                        return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                    }
                }
            }catch (\Exception $e){
                return ['status' => 0, 'msg' => $e->getMessage()];
            }
        }
        return ['status' => 1,'delivery_list'=>$wx_get_delivery_list];
    }
    //申请授权 半屏小程序 https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/embedded-management/addEmbedded.html
    public static function addEmbedded($aid,$appid='',$apply_reason=''){
        try {
            $access_token =  self::access_token($aid,'wx');
            $url = 'https://api.weixin.qq.com/wxaapi/wxaembedded/add_embedded?access_token='.$access_token;

            $data['appid']  = (string)$appid;
            $data['apply_reason']  = (string) $apply_reason;
            $rs = curl_post($url,jsonEncode($data),0,array('Content-Type: application/json'));
            $res = json_decode( $rs,true);
            if($res['errcode'] == 0){
                return ['status' => 1];
            } else{    
                $error = [
                    '-1' => '系统繁忙',
                    '89408' => '半屏小程序系统错误',
                    '89410' => '添加半屏小程序appid参数错误',
                    '89411' => '添加半屏小程序appid参数为空',
                    '89412' => '添加半屏小程序申请理由不得超过30个字',
                    '89413' => '该小程序被申请次数已达今日限制',
                    '89414' => '每天仅允许申请50次半屏小程序',
                    '89420' => '不支持添加个人主体小程序',
                    '89423' => '申请次数添加到达上限',
                    '89424' => '授权次数到达上限',
                    '89425' => '申请添加已超时',
                    '89426' => '申请添加状态异常',
                    '89427' => '申请号和授权号相同',
                    '89428' => '该小程序已申请，不允许重复添加',
                    '89429' => '已到达同一小程序每日最多申请次数',
                    '89430' => '该小程序已设置自动拒绝申请',
                    '89431' => '不支持此类型小程序',
                    '89432' => '不是小程序',
                    '89418' => '添加半屏小程序每日申请次数失败',
                    '89419' => '添加半屏小程序每日授权次数失败',
                ];
                $errormsg = $error[$res['errcode']]?$error[$res['errcode']]:self::geterror($res['errcode']);
                throw new Exception($errormsg);
            }
        }catch (\Exception $e){
            return  ['status' =>0 ,'msg' => $e->getMessage()];
        }
    }
    //获取半屏小程序授权列表 https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/EmbeddedMiniProgram/get_own_list.html#%E8%BF%94%E5%9B%9E%E7%A0%81%E8%AF%B4%E6%98%8E
    
    public static function getEmbeddedList($aid,$start=0,$num=0){
        try {
            $access_token =  self::access_token($aid,'wx');
            $url = "https://api.weixin.qq.com/wxaapi/wxaembedded/get_own_list?access_token={$access_token}&start={$start}";
//            if($num > 0){
//                $url.='&num='.$num;
//            }
            $res = json_decode(request_get($url),true);
            if($res['errcode'] == 0){
                return $res['wxa_embedded_list'];
            } else{
                return false;
            }
        }catch (\Exception $e){
            return false;
        }
    }

    /**
     * 获取小程序服务内容类型
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpServiceContentTypes.html
     * 该接口所属的权限集id为：156
     */
    public static function queryIcpServiceContentTypes($aid,$platform='wx')
    {
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_service_content_types?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }
    /**
     * 获取证件类型
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpCertificateTypes.html
     * 该接口所属的权限集id为：156
     * 证件类型与单位性质存在关联，例如：个人的证件类型有居民身份证、护照，而企业则不存在这两类证件。接口返回的证件列表中会带有 subject_type 字段指示该证件属于哪种单位，例如：政府机关的单位性质 id 为 2，那么政府机关可选的证件类型仅有 subject_type 为 2 的“统一社会信用代码证书”以及“组织机构代码证”
     */
    public static function queryIcpCertificateTypes($aid,$platform='wx')
    {
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_certificate_types?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 获取单位性质
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpSubjectTypes.html
     * 该接口所属的权限集id为：156
     */
    public static function queryIcpSubjectTypes($aid,$platform='wx')
    {
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $appid = $appinfo['appid'];
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_subject_types?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 获取区域信息
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpDistrictCode.html
     * 该接口所属的权限集id为：156
     */
    public static function queryIcpDistrictCode($aid,$platform='wx')
    {
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_district_code?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }
    /**
     * 获取前置审批项类型
     * https://developers.weixin.qq.com/doc/oplatform/openApi/OpenApiDoc/miniprogram-management/record/queryIcpNrlxTypes.html
     * 该接口所属的权限集id为：156
     */
    public static function queryIcpNrlxTypes($aid,$platform='wx')
    {
        try {
            $appinfo = System::appinfo($aid,$platform);
            if($appinfo){
                $token = self::access_token($aid,$platform);
                $url = "https://api.weixin.qq.com/wxa/icp/query_icp_nrlx_types?access_token={$token}";
                $rs = request_get($url);
                \think\facade\Log::write([
                    'file'=>__FILE__,
                    'line'=>__LINE__,
                    'fun'=>__FUNCTION__,
                    'rs'=>$rs
                ]);
                $rs = json_decode($rs,true);
                if($rs['errcode'] =='0'){
                    return ['status' => 1,'items'=>$rs['items']];
                }else{
                    \think\facade\Log::error([
                        'file'=>__FILE__,
                        'line'=>__LINE__,
                        'fun'=>__FUNCTION__,
                        'rs'=>jsonEncode($rs)
                    ]);
                    return ['status' => 0, 'msg' => self::geterror($rs['errcode']),'errcode'=>$rs['errcode']];
                }
            }
        }catch (\Exception $e){
            return ['status' => 0, 'msg' => $e->getMessage()];
        }
    }
}
