<?php
/**
 * 点大商城（www.diandashop.com） - 微信公众号小程序商城系统!
 * Copyright © 2020 山东点大网络科技有限公司 保留所有权利
 * =========================================================
 * 版本：V2
 * 授权主体：全网矩阵（厦门）供应链有限公司
 * 授权域名：@@@
 * 授权码：iKIhYWRiGyzXtPXXMjNl
 * ----------------------------------------------
 * 您只能在商业授权范围内使用，不可二次转售、分发、分享、传播
 * 任何企业和个人不得对代码以任何目的任何形式的再发布
 * =========================================================
 */

namespace app\admin\common;
use think\facade\Db;
class Common
{
	//获取支持的平台
	public static function getplatform($aid){
		$admin = Db::name('admin')->where('id',$aid)->find();
		$platform = explode(',',$admin['platform']);
		return $platform;
	}
	//获取编辑器的内容
	public static function geteditorcontent($content,$aid=aid){
		$contentArr = json_decode(htmlspecialchars_decode($content), true);
		if(!$contentArr){
			$content = preg_replace_callback('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is', function($matches)use($aid){
				//dump(trim(str_replace('&quot;','',$matches[2])));die;
				$rurl = \app\admin\common\Pic::uploadoss(trim(str_replace('&quot;','',$matches[2])));
				return $matches[1].$rurl.$matches[3];
			}, $content);

			$content = preg_replace_callback('/(background(\-image)?\s*:\s*url\([|\"|\']?)([^\"\']*?)([|\"|\']?\))/is', function($matches)use($aid){
				$rurl = \app\admin\common\Pic::uploadoss(trim(str_replace('&quot;','',$matches[3])));
				return $matches[1].$rurl.$matches[4];
			}, $content);
			return $content;
		}
		if (!empty($contentArr)) {
			//dump($contentArr);die;
			foreach ($contentArr as $k => $v) {
				if ($v['temp'] == 'richtext') {
					$richtext = unescape($v['content']);
					
					$richtext = preg_replace_callback('/(<img.*?src=[\'|\"])([^\"\']*?)([\'|\"].*?[\/]?>)/is', function($matches)use($aid){
						$rurl = \app\admin\common\Pic::uploadoss(trim(str_replace('&quot;','',$matches[2])));
						return $matches[1].$rurl.$matches[3];
					}, $richtext);

					$richtext = preg_replace_callback('/(background(\-image)?\s*:\s*url\([|\"|\']?)([^\"\']*?)([|\"|\']?\))/is', function($matches)use($aid){
						$rurl = \app\admin\common\Pic::uploadoss(trim(str_replace('&quot;','',$matches[3])));
						return $matches[1].$rurl.$matches[4];
					}, $richtext);

					//$richtext = str_replace(' data-src',' src',$richtext);
					$contentArr[$k]['content'] = $richtext;
				}
				if($v['temp'] == 'cube'){
					$contentArr[$k]['params']['currentLayout'] = ['isempty'=>1];
				}
			}
		}
		$content = jsonEncode($contentArr);
		return $content;
	}
	//自动发货
	public static function autofh($type,$order){
		if($order['freight_type']!=3) return ;
		$aid = $order['aid'];
		$freight = Db::name('freight')->where('aid',$aid)->where('id',$order['freightid'])->find();
		if(!$freight || $freight['pstype']!=3) return ;
		if($freight['pscontenttype'] == 0){
			$pscontent = $freight['pscontent'];
		}else{
			if($type == 'shop'){
				$num = Db::name($type.'_order_goods')->where('orderid',$order['id'])->sum('num');
			}else{
				$num = $order['num'];
			}
			$codelist = Db::name('freight_codelist')->where('aid',$aid)->where('fid',$freight['id'])->where('status',0)->order('id')->limit($num)->select()->toArray();
			if(!$codelist || count($codelist) < $num) return ;
			$memberinfo = Db::name('member')->where('id',$order['mid'])->find();
			$pscontent = [];
			foreach($codelist as $codeinfo){
				$pscontent[] = $codeinfo['content'];
				Db::name('freight_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$memberinfo['headimg'],'nickname'=>$memberinfo['nickname'],'buytime'=>time(),'status'=>1]);
			}
			$pscontent = implode("\r\n",$pscontent);
		}
		Db::name($order.'_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
		if($type == 'shop'){
			Db::name($type.'_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
		}
	}

	public static function getwuliu($express_no,$express,$express_type='',&$meta=null){
		if(is_array($meta)){
			$meta['message'] = '';
			$meta['upstream_status'] = '';
		}
		if($express == '同城配送'){
		    if($express_type == 'express_wx'){
                $psorderinfo =Db::name('express_wx_order')->where('id',$express_no)->find();
                $psuser=['realname'=>$psorderinfo['rider_name'],'tel'=>$psorderinfo['rider_phone']];
            }else{
                $psorderinfo =Db::name('peisong_order')->where('id',$express_no)->find();
                if($psorderinfo['psid'] == -1){
                    $psuser=['realname'=>$psorderinfo['make_rider_name'],'tel'=>$psorderinfo['make_rider_mobile']];
                }else{
                    $psuser = Db::name('peisong_user')->where('id',$psorderinfo['psid'])->find();
                }
            }

			$list = [];
			if($psorderinfo['createtime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['createtime']),'context'=>'已发布配送单'];
			}
			if($psorderinfo['starttime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['starttime']),'context'=>'配送员'.$psuser['realname'].'('.$psuser['tel'].')'.'正在为您配送'];
			}
			if($psorderinfo['daodiantime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['daodiantime']),'context'=>'配送员已到店'];
			}
			if($psorderinfo['quhuotime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['quhuotime']),'context'=>'配送员已取货'];
			}
			if($psorderinfo['endtime']){
				$list[] = ['time'=>date('Y-m-d H:i',$psorderinfo['endtime']),'context'=>'配送完成'];
			}
			$list = array_reverse($list);
		}else{
			$content = ali_getwuliu($express_no,$express);
			$data = is_string($content) ? json_decode($content,true) : [];
			if(!$data){
				if(is_array($meta)){
					$meta['message'] = '物流接口返回无效数据';
				}
				$list = [];
			}elseif(($data['msg'] ?? '')!='ok'){
				if(is_array($meta)){
					$meta['message'] = trim((string)($data['msg'] ?? '物流查询失败')) ?: '物流查询失败';
					$meta['upstream_status'] = (string)($data['status'] ?? '');
				}
				$list = [];
			}else{
					$list = $data['result']['list'];
					if(empty($list) && is_array($meta)){
						$meta['message'] = '暂无物流轨迹';
					}
					foreach($list as $k=>$v){
						$list[$k]['context'] = $v['status'];
					}
			}
		}
		return $list;
	}

	//推荐积分
	public static function user_tjscore($aid,$mid){
		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
		if(!$member) return;
		if($member['pid']){
			//成员加入提醒 OPENTM207685059
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您推荐新成员加入成功';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $member['nickname']; //姓名
			$tmplcontent['keyword2'] = date('Y-m-d H:i');//时间
			$rs = \app\admin\common\Wechat::sendtmpl($aid,$member['pid'],'tmpl_joinin',$tmplcontent,m_url('pages/my/usercenter', $aid));
			
			$parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
			if($parent1){
				$agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
				if($agleveldata1['can_agent']!=0 && $agleveldata1['score1']>0){
					if(!Db::name('member_tjscore')->where('mid',$parent1['id'])->where('frommid',$mid)->find()){
						$givescore = $agleveldata1['score1'];
						if($agleveldata1['scoremax'] > 0){
							$sumscore = Db::name('member_tjscore')->where('mid',$parent1['id'])->sum('score');
							if($sumscore >= $agleveldata1['scoremax']){
								$givescore = 0;
							}elseif($agleveldata1['scoremax'] - $sumscore < $givescore){
								$givescore = $agleveldata1['scoremax'] - $sumscore;
							}
						}
						if($givescore > 0){
							Db::name('member_tjscore')->insert(['aid'=>$aid,'mid'=>$parent1['id'],'frommid'=>$mid,'score'=>$givescore,'createtime'=>time()]);
							\app\admin\common\Member::addscore($aid,$parent1['id'],$givescore,'推荐奖励');
						}
					}
				}
					\app\admin\common\Member::triggerUplv($aid, $parent1['id'], [
						'scene' => \app\admin\common\MemberUpgradeScene::REFERRAL_REWARD,
						'source_type' => 'referral_reward',
						'source_id' => strval($mid),
					]);
			}
			if($parent1['pid']){
				$parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
				if($parent2){
					$agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
					if($agleveldata2['can_agent']>1&& $agleveldata2['score2']>0){
						if(!Db::name('member_tjscore')->where('mid',$parent2['id'])->where('frommid',$mid)->find()){
							$givescore = $agleveldata2['score2'];
							if($agleveldata2['scoremax'] > 0){
								$sumscore = Db::name('member_tjscore')->where('mid',$parent2['id'])->sum('score');
								if($sumscore >= $agleveldata2['scoremax']){
									$givescore = 0;
								}elseif($agleveldata2['scoremax'] - $sumscore < $givescore){
									$givescore = $agleveldata2['scoremax'] - $sumscore;
								}
							}
							if($givescore > 0){
								Db::name('member_tjscore')->insert(['aid'=>$aid,'mid'=>$parent2['id'],'frommid'=>$mid,'score'=>$givescore,'createtime'=>time()]);
								\app\admin\common\Member::addscore($aid,$parent2['id'],$givescore,'下级推荐奖励');
							}
						}
					}
				}
			}
			if($parent2['pid']){
				$parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
				if($parent3){
					$agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
					if($agleveldata3['can_agent']>2&& $agleveldata3['score3']>0){
						if(!Db::name('member_tjscore')->where('mid',$parent3['id'])->where('frommid',$mid)->find()){
							$givescore = $agleveldata3['score3'];
							if($agleveldata3['scoremax'] > 0){
								$sumscore = Db::name('member_tjscore')->where('mid',$parent3['id'])->sum('score');
								if($sumscore >= $agleveldata3['scoremax']){
									$givescore = 0;
								}elseif($agleveldata3['scoremax'] - $sumscore < $givescore){
									$givescore = $agleveldata3['scoremax'] - $sumscore;
								}
							}
							if($givescore > 0){
								Db::name('member_tjscore')->insert(['aid'=>$aid,'mid'=>$parent3['id'],'frommid'=>$mid,'score'=>$givescore,'createtime'=>time()]);
								\app\admin\common\Member::addscore($aid,$parent3['id'],$givescore,'下下级推荐奖励');
							}
						}
					}
				}
			}
		}
	}

	//注册赠送
	public static function registerGive($aid,$member)
    {
        $mid = $member['id'];
        $set = Db::name('register_giveset')->where('aid',$aid)->find();
        if($set['status']) {
            $date = date('Y-m-d H:i:s');
            if($date > $set['endtime'] || $date < $set['starttime']) {
                return ;
            }

            $tmpl_remark = [];
            if($set['money'] > 0) {
                \app\admin\common\Member::addmoney($aid,$mid,$set['money'],'注册赠送');
                $tmpl_remark[] = "赠送".t('余额').$set['money'].'元';
            }

            if($set['score'] > 0) {
                \app\admin\common\Member::addscore($aid,$mid,$set['score'],'注册赠送');
                $tmpl_remark[] = "赠送积分".$set['score'].'个';
            }

            if($set['coupon_ids']) {
                $coupon_ids = explode(',', $set['coupon_ids']);
                if($coupon_ids) {
                    foreach($coupon_ids as $coupon_id){
                        \app\admin\common\Coupon::send($aid,$mid,$coupon_id);
                    }
                    $tmpl_remark[] = "赠送优惠券".count($coupon_ids).'张';
                }
            }

            // 储值优惠券属于可选插件：只有插件已安装、已授权且核心表完整时才发放。
            $storedValueRuleId = intval($set['storedvaluecoupon_rule_id'] ?? 0);
            if ($storedValueRuleId > 0 && \app\admin\common\PluginBusinessAuth::isAidPluginReady($aid, 'storedvaluecoupon', array(
                'storedvaluecoupon_rule',
                'storedvaluecoupon_account',
                'storedvaluecoupon_order_use',
                'storedvaluecoupon_log',
            ))) {
                try {
                    $storedValueResult = \app\plugin\storedvaluecoupon\common\StoredValueCouponService::grant($aid, $mid, $storedValueRuleId, array(
                        'source_type' => 'register_give',
                        'source_id' => 'register-give:' . intval($mid),
                        'remark' => '注册赠送储值优惠券',
                    ));
                    if (intval($storedValueResult['status'] ?? 0) === 1) {
                        $storedValueAmount = $storedValueResult['data']['amount'] ?? '';
                        $tmpl_remark[] = '赠送储值优惠券' . ($storedValueAmount !== '' ? $storedValueAmount . '元' : '');
                    }
                } catch (\Throwable $e) {
                    \think\facade\Log::warning('注册赠送储值优惠券失败(aid=' . intval($aid) . ',mid=' . intval($mid) . ')：' . $e->getMessage());
                }
            }

            if(!empty($tmpl_remark)) {
                //成员加入提醒
                $tmplcontent = [];
                $tmplcontent['first'] = '欢迎您的加入';
                $tmplcontent['keyword1'] = $member['nickname']; //姓名
                $tmplcontent['keyword2'] = date('Y-m-d H:i');//时间
                $tmplcontent['remark'] = implode('，',$tmpl_remark);
                \app\admin\common\Wechat::sendtmpl(aid,$mid,'tmpl_joinin',$tmplcontent,m_url('pages/my/usercenter', $aid));
            }
        }
    }

	//升级费用分销
	public static function applypayfenxiao($aid,$orderid){
		$order = db('member_levelup_order')->where('aid',$aid)->where('id',$orderid)->find();
		$member = db('member')->where('id',$order['mid'])->find();
		$ogdata = [];
		if($member['pid']){
			$parent1 = db('member')->where('aid',$aid)->where('id',$member['pid'])->find();
			if($parent1){
				$agleveldata1 = db('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
				if($agleveldata1['can_agent']!=0){
					$ogdata['parent1'] = $parent1['id'];
				}
			}
		}
		if($parent1['pid']){
			$parent2 = db('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
			if($parent2){
				$agleveldata2 = db('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
				if($agleveldata2['can_agent']>1){
					$ogdata['parent2'] = $parent2['id'];
				}
			}
		}
		if($parent2['pid']){
			$parent3 = db('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
			if($parent3){
				$agleveldata3 = db('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
				if($agleveldata3['can_agent']>2){
					$ogdata['parent3'] = $parent3['id'];
				}
			}
		}
		
		if($agleveldata1){
			if($agleveldata1['commissiontype']==1){ //固定金额按单
				$ogdata['parent1commission'] = $agleveldata1['commission1'];
			}else{
				$ogdata['parent1commission'] = $agleveldata1['commission1'] * $order['totalprice'] * 0.01;
			}
		}
		if($agleveldata2){
			if($agleveldata2['commissiontype']==1){
				$ogdata['parent2commission'] = $agleveldata2['commission2'];
			}else{
				$ogdata['parent2commission'] = $agleveldata2['commission2'] * $order['totalprice'] * 0.01;
			}
		}
		if($agleveldata3){
			if($agleveldata3['commissiontype']==1){
				$ogdata['parent3commission'] = $agleveldata3['commission3'];
			}else{
				$ogdata['parent3commission'] = $agleveldata3['commission3'] * $order['totalprice'] * 0.01;
			}
		}

		if($ogdata['parent1'] && $ogdata['parent1commission'] > 0){
			\app\admin\common\Member::addcommission($aid,$ogdata['parent1'],$order['mid'],$ogdata['parent1commission'],'下级'.t('会员').'升级奖励');

			Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent1'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent1commission'],'score'=>0,'remark'=>'下级'.t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

			//公众号通知 分销成功提醒
			$parent1 = db('member')->where('aid',$aid)->where('id',$ogdata['parent1'])->find();
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent1commission'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['title']; //商品信息
			$tmplcontent['keyword2'] = $order['totalprice'];//商品单价
			$tmplcontent['keyword3'] = $ogdata['parent1commission'].'元';//商品佣金
			$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
			$rs = \app\admin\common\Wechat::sendtmpl($aid,$parent1['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
			//短信通知
			$rs = \app\admin\common\Sms::send($aid,$parent1['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent1commission']]);
		}
		if($ogdata['parent2'] && $ogdata['parent2commission'] > 0){
			\app\admin\common\Member::addcommission($aid,$ogdata['parent2'],$order['mid'],$ogdata['parent2commission'],'下二级'.t('会员').'升级奖励');
			
			Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent2'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent2commission'],'score'=>0,'remark'=>'下二级'.t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

			//公众号通知 分销成功提醒
			$parent2 = db('member')->where('aid',$aid)->where('id',$ogdata['parent2'])->find();
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent2commission'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['title']; //商品信息
			$tmplcontent['keyword2'] = $order['totalprice'];//商品单价
			$tmplcontent['keyword3'] = $ogdata['parent2commission'].'元';//商品佣金
			$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
			$rs = \app\admin\common\Wechat::sendtmpl($aid,$parent2['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
			//短信通知
			$rs = \app\admin\common\Sms::send($aid,$parent2['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent2commission']]);
		}
		if($ogdata['parent3'] && $ogdata['parent3commission'] > 0){
			\app\admin\common\Member::addcommission($aid,$ogdata['parent3'],$order['mid'],$ogdata['parent3commission'],'下三级'.t('会员').'升级奖励');

			Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent3'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent3commission'],'score'=>0,'remark'=>'下三级'.t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

			//公众号通知 分销成功提醒
			$parent3 = db('member')->where('aid',$aid)->where('id',$ogdata['parent3'])->find();
			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent3commission'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['title']; //商品信息
			$tmplcontent['keyword2'] = $order['totalprice'];//商品单价
			$tmplcontent['keyword3'] = $ogdata['parent3commission'].'元';//商品佣金
			$tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
			$rs = \app\admin\common\Wechat::sendtmpl($aid,$parent3['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
			//短信通知
			$rs = \app\admin\common\Sms::send($aid,$parent3['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent3commission']]);
		}
	}

    //生成订单号
    public static function generateOrderNo($aid,$order_type = 'shop_order')
    {
        $prefix = '';
        if ($order_type == 'shop_order') {

        } elseif ($order_type == 'booking_order') {
            $prefix = '50';
        }
        elseif ($order_type == 'restaurant_shop_order') {
            $prefix = '51';
        }
        $date = date('ymdHis');
        $rand = rand(100000,999999);
        $order_no = $prefix . $date . $rand;
        return $order_no;
    }

    public static function getAreaByLocation($longitude,$latitude)
    {
        $geo = MapQuery::reverseGeocode($latitude, $longitude, 'getAreaByLocation');
        if (!empty($geo['ok'])) {
            return [
                'status' => 1,
                'msg' => 'ok',
                'province' => $geo['province'] ?? '',
                'city' => $geo['city'] ?? '',
                'district' => $geo['district'] ?? '',
            ];
        }
        return ['status' => 0, 'msg' => $geo['error'] ?? '请输入坐标'];
    }
    	// 回收卡分销
    public static function recycleCardFenxiao($order){
        $orderid = $order['id'];
        $aid = $order['aid'];
          $order['mid'] = $order['uid'];
        $member = db('member')->where('id',$order['mid'])->find();
        $ogdata = [];
        if($member['pid']){
            $parent1 = db('member')->where('aid',$aid)->where('id',$member['pid'])->find();
            if($parent1){
                $agleveldata1 = db('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
                if($agleveldata1['can_agent']!=0){
                    $ogdata['parent1'] = $parent1['id'];
                }
            }
        }
        if($parent1['pid']){
            $parent2 = db('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
            if($parent2){
                $agleveldata2 = db('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
                if($agleveldata2['can_agent']>1){
                    $ogdata['parent2'] = $parent2['id'];
                }
            }
        }
        if($parent2['pid']){
            $parent3 = db('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
            if($parent3){
                $agleveldata3 = db('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
                if($agleveldata3['can_agent']>2){
                    $ogdata['parent3'] = $parent3['id'];
                }
            }
        }
        $order['totalprice'] = $order['real_money'] / 100 ;
        $coupon = Db::name('fee_card')->where('id', $order['card_id'])->find();
        $order['title'] = $coupon['name'].'('. $order['face_value'].')' ;
        if($agleveldata1){
            if($agleveldata1['commissiontype']==1){ //固定金额按单
                $ogdata['parent1commission'] = $agleveldata1['commission1'];
            }else{
                $ogdata['parent1commission'] = $agleveldata1['commission1'] * $order['totalprice'] * 0.01;
            }
        }
        if($agleveldata2){
            if($agleveldata2['commissiontype']==1){
                $ogdata['parent2commission'] = $agleveldata2['commission2'];
            }else{
                $ogdata['parent2commission'] = $agleveldata2['commission2'] * $order['totalprice'] * 0.01;
            }
        }
        if($agleveldata3){
            if($agleveldata3['commissiontype']==1){
                $ogdata['parent3commission'] = $agleveldata3['commission3'];
            }else{
                $ogdata['parent3commission'] = $agleveldata3['commission3'] * $order['totalprice'] * 0.01;
            }
        }

        if($ogdata['parent1'] && $ogdata['parent1commission'] > 0){
            \app\admin\common\Member::addcommission($aid,$ogdata['parent1'],$order['mid'],$ogdata['parent1commission'],'下级'.t('会员').'升级奖励');

            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent1'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent1commission'],'score'=>0,'remark'=>'下级'.t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

            //公众号通知 分销成功提醒
            $parent1 = db('member')->where('aid',$aid)->where('id',$ogdata['parent1'])->find();
            $tmplcontent = [];
            $tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent1commission'];
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $order['title']; //商品信息
            $tmplcontent['keyword2'] = $order['totalprice'];//商品单价
            $tmplcontent['keyword3'] = $ogdata['parent1commission'].'元';//商品佣金
            $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
            $rs = \app\admin\common\Wechat::sendtmpl($aid,$parent1['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
            //短信通知
            $rs = \app\admin\common\Sms::send($aid,$parent1['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent1commission']]);
        }
        if($ogdata['parent2'] && $ogdata['parent2commission'] > 0){
            \app\admin\common\Member::addcommission($aid,$ogdata['parent2'],$order['mid'],$ogdata['parent2commission'],'下二级'.t('会员').'升级奖励');

            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent2'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent2commission'],'score'=>0,'remark'=>'下二级'.t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

            //公众号通知 分销成功提醒
            $parent2 = db('member')->where('aid',$aid)->where('id',$ogdata['parent2'])->find();
            $tmplcontent = [];
            $tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent2commission'];
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $order['title']; //商品信息
            $tmplcontent['keyword2'] = $order['totalprice'];//商品单价
            $tmplcontent['keyword3'] = $ogdata['parent2commission'].'元';//商品佣金
            $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
            $rs = \app\admin\common\Wechat::sendtmpl($aid,$parent2['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
            //短信通知
            $rs = \app\admin\common\Sms::send($aid,$parent2['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent2commission']]);
        }
        if($ogdata['parent3'] && $ogdata['parent3commission'] > 0){
            \app\admin\common\Member::addcommission($aid,$ogdata['parent3'],$order['mid'],$ogdata['parent3commission'],'下三级'.t('会员').'升级奖励');

            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogdata['parent3'],'frommid'=>$order['mid'],'orderid'=>$orderid,'ogid'=>0,'type'=>'levelup','commission'=>$ogdata['parent3commission'],'score'=>0,'remark'=>'下三级'.t('会员').'升级奖励','createtime'=>time(),'status'=>1,'endtime'=>time()]);

            //公众号通知 分销成功提醒
            $parent3 = db('member')->where('aid',$aid)->where('id',$ogdata['parent3'])->find();
            $tmplcontent = [];
            $tmplcontent['first'] = '恭喜您，成功分销获得'.t('佣金').'：￥'.$ogdata['parent3commission'];
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $order['title']; //商品信息
            $tmplcontent['keyword2'] = $order['totalprice'];//商品单价
            $tmplcontent['keyword3'] = $ogdata['parent3commission'].'元';//商品佣金
            $tmplcontent['keyword4'] = date('Y-m-d H:i:s',$order['createtime']);//分销时间
            $rs = \app\admin\common\Wechat::sendtmpl($aid,$parent3['id'],'tmpl_fenxiaosuccess',$tmplcontent,m_url('pages/my/usercenter', $aid));
            //短信通知
            $rs = \app\admin\common\Sms::send($aid,$parent3['tel'],'tmpl_fenxiaosuccess',['money'=>$ogdata['parent3commission']]);
        }
    }

    /**
     * 记录接点人关系变更
     * @param int $aid 站点ID
     * @param int $mid 会员ID
     * @param int $jiedianpid 接点人ID
     * @param int $type 记录类型：1注册时设置，2用户修改，3管理员修改
     * @param int $operator_id 操作人ID
     * @param int $operator_type 操作人类型：1会员，2管理员
     * @param string $remark 备注说明
     * @param string $ip IP地址
     * @return bool
     */
    public static function recordJiedianRelation($aid, $mid, $jiedianpid, $type = 1, $operator_id = 0, $operator_type = 1, $remark = '', $ip = '')
    {
        try {
            // 如果没有传入IP，尝试获取当前IP
            if (empty($ip)) {
                $ip = request()->ip() ?: '127.0.0.1';
            }
            
            // 如果没有传入操作人ID，默认为会员本身
            if ($operator_id == 0) {
                $operator_id = $mid;
            }
            
            // 插入接点人关系记录
            $data = [
                'aid' => $aid,
                'mid' => $mid,
                'jiedianpid' => $jiedianpid,
                'type' => $type,
                'operator_id' => $operator_id,
                'operator_type' => $operator_type,
                'remark' => $remark,
                'createtime' => time(),
                'ip' => $ip
            ];
            
            $result = Db::name('member_jiedian_record')->insert($data);
            
            // 记录日志
            if ($result) {
                $typeNames = [1 => '注册时设置', 2 => '用户修改', 3 => '管理员修改'];
                $typeName = $typeNames[$type] ?? '未知';
                \think\facade\Log::write("接点人关系记录成功 - 站点:{$aid}, 会员:{$mid}, 接点人:{$jiedianpid}, 类型:{$typeName}, 操作人:{$operator_id}");
            }
            
            return $result !== false;
        } catch (\Exception $e) {
            \think\facade\Log::write("接点人关系记录失败: " . $e->getMessage());
            return false;
        }
    }
}
