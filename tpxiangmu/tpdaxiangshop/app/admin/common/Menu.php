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
class Menu
{
	//获取菜单数据
	public static function getdata($aid=0,$uid=0,$includePromotedPluginMenus=true){
		$user = [];
		if($uid > 0){
			$user = Db::name('admin_user')->where('id',$uid)->find() ?: [];
			$userAid = intval($user['aid'] ?? 0);
			if($userAid > 0){
				$aid = $userAid;
			}
		}
		if($aid == 0){
			$platform = ['mp','wx','alipay','baidu','toutiao','qq','h5','app'];
		}else{
			$platform = explode(',',Db::name('admin')->where('id',$aid)->value('platform'));
		}
		if($uid > 0){
			if(intval($user['bid'] ?? 0) > 0){
				$isadmin = false;
				if($user['auth_type'] == 1){
					$parentQuery = Db::name('admin_user')->where('aid',$aid)->where('isadmin','>',0);
					if(intval($user['bid']) > 0){
						$parentQuery->where('bid', intval($user['bid']));
					}
					$parentUser = $parentQuery->order('id asc')->find();
					if($parentUser){
						$user = $parentUser;
					}
				}
			}else{
				$isadmin = true;
			}
		}else{
			if($uid == -1){
				$isadmin = false;
				$user = Db::name('admin_user')->where('aid',$aid)->where('isadmin','>',0)->find();
			}else{
				$isadmin = true;
			}
		}
		$menudata = [];

		$shop_child = [];
		$shop_child[] = ['name'=>'商品管理','path'=>'ShopProduct/index','authdata'=>'ShopProduct/*,ShopCode/*'];
		$shop_child[] = ['name'=>'订单管理','path'=>'ShopOrder/index','authdata'=>'ShopOrder/*'];
        $shop_child[] = ['name'=>'退款申请','path'=>'ShopRefundOrder/index','authdata'=>'ShopRefundOrder/*'];
		$shop_child[] = ['name'=>'评价管理','path'=>'ShopComment/index','authdata'=>'ShopComment/*'];

		// // 添加进货管理和退货管理菜单
		// if($isadmin){
		// 	$shop_child[] = ['name'=>'进货管理','path'=>'ProductPurchase/index','authdata'=>'ProductPurchase/*'];
		// 	$shop_child[] = ['name'=>'退货管理','path'=>'ProductReturn/index','authdata'=>'ProductReturn/*'];
		// 	$shop_child[] = ['name'=>'进货统计','path'=>'AdminPurchaseStatistics/index','authdata'=>'AdminPurchaseStatistics/*'];
		// } else {
		// 	$shop_child[] = ['name'=>'向总平台进货','path'=>'ProductPurchase/index','authdata'=>'ProductPurchase/*'];
		// 	$shop_child[] = ['name'=>'退货申请','path'=>'ProductReturn/index','authdata'=>'ProductReturn/*'];
		// }
		
		if($isadmin){
			$shop_child[] = ['name'=>'商品分类','path'=>'ShopCategory/index','authdata'=>'ShopCategory/*'];
			$shop_child[] = ['name'=>'商品分组','path'=>'ShopGroup/index','authdata'=>'ShopGroup/*'];
		}else{
			$shop_child[] = ['name'=>'商品分类','path'=>'ShopCategory2/index','authdata'=>'ShopCategory2/*'];
		}
		$shop_child[] = ['name'=>'商品参数','path'=>'ShopParam/index','authdata'=>'ShopParam/*'];
		$shop_child[] = ['name'=>'商品服务','path'=>'ShopFuwu/index','authdata'=>'ShopFuwu/*'];
		if($isadmin){
			$shop_child[] = ['name'=>'商品海报','path'=>'ShopPoster/index','authdata'=>'ShopPoster/*'];
			$shop_child[] = ['name'=>'录入订单','path'=>'ShopOrderlr/index','authdata'=>'ShopOrderlr/*,ShopProduct/chooseproduct,ShopProduct/index,ShopProduct/getproduct,Member/index'];
		}
		$shop_child[] = ['name'=>'商品采集','path'=>'ShopTaobao/index','authdata'=>'ShopTaobao/*'];

		$shop_child[] = ['name'=>'销售统计','path'=>'ShopOrder/tongji','authdata'=>'ShopOrder/*'];
		if($isadmin){
            $shop_child[] = ['name'=>'系统设置','path'=>'ShopSet/index','authdata'=>'ShopSet/*'];
		}else{
            }

		$menudata['shop'] = ['name'=>'商城','fullname'=>'商城系统','icon'=>'my-icon my-icon-shop','child'=>$shop_child];
		if($isadmin){
			$member_child = [];
			$member_child[] = ['name'=>t('会员').'列表','path'=>'Member/index','authdata'=>'Member/index,Member/excel,Member/excel,Member/importexcel,Member/getplatform,Member/edit,Member/save,Member/del,Member/getcarddetail,Member/charts,Member/setst,Member/*,MemberStatistics/*,MemberManage/*'];
// 			$member_child[] = ['name'=>t('虚拟账号').'列表','path'=>'Member/ziindex','authdata'=>'Member/index,Member/excel,Member/excel,Member/importexcel,Member/getplatform,Member/edit,Member/save,Member/del,Member/getcarddetail,Member/charts,Member/setst'];
			$member_child[] = ['name'=>'充值','path'=>'Member/recharge','authdata'=>'Member/recharge','hide'=>true];
			$member_child[] = ['name'=>'加配资','path'=>'Member/addAllocation','authdata'=>'Member/addAllocation','hide'=>true];
			$member_child[] = ['name'=>'加积分','path'=>'Member/addscore','authdata'=>'Member/addscore','hide'=>true];
			$member_child[] = ['name'=>'加佣金','path'=>'Member/addcommission','authdata'=>'Member/addcommission','hide'=>true];
				$member_child[] = ['name'=>'加'.t('贡献值'),'path'=>'Member/addgongxianzhi','authdata'=>'Member/addgongxianzhi','hide'=>true];
			$member_child[] = ['name'=>'加'.t('现金券'),'path'=>'Member/addheijifen','authdata'=>'Member/addheijifen','hide'=>true];
			$member_child[] = ['name'=>'等级及分销','path'=>'MemberLevel/index','authdata'=>'MemberLevel/*'];
			$member_child[] = ['name'=>'升级申请记录','path'=>'MemberLevel/applyorder','authdata'=>'MemberLevel/*'];
            $member_child[] = ['name'=>t('会员').'关系图','path'=>'Member/charts','authdata'=>'Member/charts'];
			
			$member_child[] = ['name'=>t('会员').'标签','path'=>'MemberTag/index','authdata'=>'MemberTag/*'];
			$member_child[] = ['name'=>t('会员').'来源管理','path'=>'MemberManage/source','authdata'=>'MemberManage/*'];
			$member_child[] = ['name'=>t('会员').'足迹','path'=>'MemberHistory/index','authdata'=>'MemberHistory/*'];
				
			
            $member_child[] = ['name' => '注册自定义', 'path' => 'RegisterForm/index', 'authdata' => 'RegisterForm/*'];
            
			$member_child[] = ['name'=>'分享海报','path'=>'MemberPoster/index','authdata'=>'MemberPoster/*'];
		
			$member_child[] = ['name'=>'团队分红','path'=>'teamfenhong','authdata'=>'teamfenhong','hide'=>true];
			$member_child[] = ['name'=>'股东分红','path'=>'gdfenhong','authdata'=>'gdfenhong','hide'=>true];
			$member_child[] = ['name'=>'区域分红','path'=>'areafenhong','authdata'=>'areafenhong','hide'=>true];

			$menudata['member'] = ['name'=>t('会员'),'fullname'=>t('会员').'管理','icon'=>'my-icon my-icon-member','child'=>$member_child];
		}

		if($isadmin){
			$finance_child = [];
				$finance_child[] = ['name'=>t('会员').'列表','path'=>'Membercaiwu/index','authdata'=>'Membecaiwu/*'];
			$finance_child[] = ['name'=>'全局明细','path'=>'Payorder/tongjiyue','authdata'=>'Payorder/*'];
			$finance_child[] = ['name'=>'每日收款明细','path'=>'Payorder/caiwu','authdata'=>'Payorder/*'];
				$finance_child[] = ['name'=>'每日货款明细','path'=>'Payorder/fahuomingxi','authdata'=>'Payorder/*'];
		//	$finance_child[] = ['name'=>'销售统计','path'=>'ShopOrder/tongji4','authdata'=>'ShopOrder/*'];
			$finance_child[] = ['name'=>'消费明细','path'=>'Payorder/index','authdata'=>'Payorder/*'];
			$finance_child[] = ['name'=>'用户明细','path'=>'BusinessMoney/memberindex','authdata'=>'BusinessMoney/*'];
			$finance_child[] = ['name'=>t('余额').'明细','path'=>'Money/moneylog','authdata'=>'Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel'];
// 			$finance_child[] = ['name'=>t('余额').'明细2','path'=>'Money/moneylog','authdata'=>'Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel'];
			
// 			$finance_child[] = ['name'=>t('创业值').'明细','path'=>'Money/chuangyelog','authdata'=>'Money/chuangyelog,Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel'];
			$finance_child[] = ['name'=>t('现金券').'明细','path'=>'Money/heijifenlog','authdata'=>'Money/heijifenlog,Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel'];
			$finance_child[] = ['name'=>'充值记录','path'=>'Money/rechargelog','authdata'=>'Money/rechargelog,Money/rechargelogexcel,Money/rechargelogdel'];
			$finance_child[] = ['name'=>t('余额').'提现','path'=>'Money/withdrawlog','authdata'=>'Money/*'];
			$finance_child[] = ['name'=>t('佣金').'记录','path'=>'Commission/record','authdata'=>'Commission/record'];
			$finance_child[] = ['name'=>t('佣金').'明细','path'=>'Commission/commissionlog','authdata'=>'Commission/commissionlog,Commission/commissionlogexcel,Commission/commissionlogdel'];
			$finance_child[] = ['name'=>'预约佣金记录','path'=>'YuyueOrder/commissionlog','authdata'=>'YuyueOrder/commissionlog'];
				$finance_child[] = ['name'=>t('佣金').'提现','path'=>'Commission/withdrawlog','authdata'=>'Commission/*'];
				$finance_child[] = ['name'=>'中金佣金提现','path'=>'Commission/withdrawlogcpcn','authdata'=>'Commission/*'];
// 			$finance_child[] = ['name'=>t('销售分红发放').'记录','path'=>'Commission/sendxsjl','authdata'=>'Commission/*'];
				$finance_child[] = ['name'=>t('积分').'明细','path'=>'Score/scorelog','authdata'=>'Score/*'];
				$finance_child[] = ['name'=>t('黄积分').'明细','path'=>'ScoreHuang/scorelog','authdata'=>'ScoreHuang/scorelog,ScoreHuang/scorelogexcel,ScoreHuang/scorelogdel,ScorehuangLog/getList,ScorehuangLog/export,ScorehuangLog/delete'];
				$finance_child[] = ['name'=>t('消费币').'明细','path'=>'XiaofeibLog/index','authdata'=>'XiaofeibLog/getList,XiaofeibLog/export,XiaofeibLog/delete'];
				$finance_child[] = ['name'=>'会员互转明细','path'=>'Money/membertransfer','authdata'=>'MemberTransferLog/getList'];
					$finance_child[] = ['name'=>t('积分').'转账明细','path'=>'Score/zhuanscorelog','authdata'=>'Score/*'];
						$finance_child[] = ['name'=>t('余额').t('转账').'明细','path'=>'Money/zhuangzhang','authdata'=>'Money/*'];
				$finance_child[] = ['name'=>t('虚拟账号积分').'明细','path'=>'Score/scoreduihuanfen','authdata'=>'Score/*'];
				
			// $finance_child[] = ['name'=>'红包明细','path'=>'Score/scorelogh','authdata'=>'Score/*'];
// 			$finance_child[] = ['name'=>'绿积分明细','path'=>'Score/scoreloglv','authdata'=>'Score/*'];
// 			$finance_child[] = ['name'=>'消费值明细','path'=>'Score/scorelogxiaofeizhi','authdata'=>'Score/*'];
// 			$finance_child[] = ['name'=>'打赏明细','path'=>'Score/scorelogdashang','authdata'=>'Score/*'];
		
// 			$finance_child[] = ['name'=>t('分红池').'明细','path'=>'Money/zijifhlog','authdata'=>'Money/*'];
// 			$finance_child[] = ['name'=>t('分享分红池').'明细','path'=>'Money/fxfhclog','authdata'=>'Money/*'];
// 			$finance_child[] = ['name'=>t('收益池').'明细','path'=>'Money/syclog','authdata'=>'Money/*'];
					
			//
			if(!self::isPluginMenuEnabled('maidan', intval($aid))){
				$finance_child[] = ['name'=>'买单记录','path'=>'Maidan/index','authdata'=>'Maidan/*'];
			}
            // $finance_child[] = ['name'=>'贡献值明细','path'=>'Gxz/index','authdata'=>'Gxz/*'];
			$finance_child[] = ['name'=>'分红记录','path'=>'Commission/fenhonglog','authdata'=>'Commission/*'];
			
		}else{
                $finance_child = [];
                // 获取当前用户类型
                $userinfo = db('admin_user')->where('id', $uid)->find();
                $userType = isset($userinfo['type']) ? $userinfo['type'] : 'business';
			
                if($userType == 'business') {
                    // 商家菜单
                    $finance_child[] = ['name'=>'余额明细','path'=>'BusinessMoney/moneylog','authdata'=>'BusinessMoney/*'];
                    // $finance_child[] = ['name'=>'用户明细','path'=>'BusinessMoney/memberindex','authdata'=>'BusinessMoney/*']; // 会员余额数据只有总后台可见
                    $finance_child[] = ['name'=>'充值申请','path'=>'BusinessMoney/recharge','authdata'=>'BusinessMoney/*'];
                    $finance_child[] = ['name'=>'充值记录','path'=>'BusinessMoney/rechargelog','authdata'=>'BusinessMoney/*'];
                    $finance_child[] = ['name'=>'余额提现','path'=>'BusinessMoney/withdraw','authdata'=>'BusinessMoney/*'];
                    $finance_child[] = ['name'=>'提现记录','path'=>'BusinessMoney/withdrawlog','authdata'=>'BusinessMoney/*'];
                    $finance_child[] = ['name'=>'买单扣费','path'=>'BusinessMaidan/add','authdata'=>'BusinessMaidan/*'];
                    $finance_child[] = ['name'=>'买单记录','path'=>'BusinessMaidan/index','authdata'=>'BusinessMaidan/*'];
				$finance_child[] = ['name'=>'收款码','path'=>'BusinessMaidan/set','authdata'=>'BusinessMaidan/*'];
				
                } else if($userType == 'tuanzhang') {
                    // 团长菜单
                    $finance_child[] = ['name'=>'团长收益','path'=>'TuanzhangMoney/moneylog','authdata'=>'TuanzhangMoney/*'];
                    $finance_child[] = ['name'=>'提现申请','path'=>'TuanzhangMoney/withdraw','authdata'=>'TuanzhangMoney/*'];
                    $finance_child[] = ['name'=>'提现记录','path'=>'TuanzhangMoney/withdrawlog','authdata'=>'TuanzhangMoney/*'];
                    $finance_child[] = ['name'=>'订单记录','path'=>'TuanzhangOrder/index','authdata'=>'TuanzhangOrder/*'];
                }
		}
        $finance_child[] = ['name'=>'核销记录','path'=>'Hexiao/index','authdata'=>'Hexiao/*'];
       
        $finance_child[] = ['name'=>'发票管理','path'=>'Invoice/index','authdata'=>'Invoice/*'];
		$menudata['finance'] = ['name'=>'财务','fullname'=>'财务管理','icon'=>'my-icon my-icon-finance','child'=>$finance_child];
         
        // print_r($menudata);
        // exit();
		$yingxiao_child = [];
		$yingxiao_child[] = ['name'=>t('优惠券'),'path'=>'Coupon/index','authdata'=>'Coupon/*,ShopCategory/index,ShopCategory/choosecategory'];
		if($isadmin){
            $yingxiao_child[] = ['name'=>'注册赠送','path'=>'Member/registerGive','authdata'=>'Member/registerGive'];
            $yingxiao_child[] = ['name'=>'充值赠送','path'=>'Money/giveset','authdata'=>'Money/giveset'];
			$yingxiao_child[] = ['name'=>'购物满减','path'=>'Manjian/set','authdata'=>'Manjian/set'];
			$yingxiao_child[] = ['name'=>'随机立减','path'=>'RandomDiscount/set','authdata'=>'RandomDiscount/set'];
		}
		$yingxiao_child[] = ['name'=>'商品促销','path'=>'Cuxiao/index','authdata'=>'Cuxiao/*'];
		if($isadmin){
			$yingxiao_child[] = ['name'=>'购物返现','path'=>'Cashback/index','authdata'=>'Cashback/*'];
		}

		// 插件能力不进默认菜单；仅未授权插件的站点在运行时挂营销兜底
		foreach(self::yingxiaoPluginFallbackChildren(intval($aid), $isadmin) as $fallback){
			$yingxiao_child[] = $fallback;
		}
		$yingxiao_child = self::filterYingxiaoChildrenByPlugin($yingxiao_child, intval($aid));
        $menudata['yingxiao'] = ['name'=>'营销','fullname'=>'营销活动','icon'=>'my-icon my-icon-yingxiao','child'=>$yingxiao_child];

		$component_child = [];
		$component_tuanzhang = [];
		if($isadmin){
			$component_business = [];
			$component_business[] = ['name'=>'商户管理','path'=>'Business/index','authdata'=>'Business/*,BusinessFreight/*'];
			$component_business[] = ['name'=>'商户分类','path'=>'Business/category','authdata'=>'Business/*'];
			$component_business[] = ['name'=>'商户费率','path'=>'Business/feilv','authdata'=>'Business/*'];
			$component_business[] = ['name'=>'商家海报','path'=>'BusinessPoster/index','authdata'=>'BusinessPoster/*'];
			$component_business[] = ['name'=>'商品管理','path'=>'ShopProduct/index&showtype=2','authdata'=>'ShopProduct/*'];

			
			// $component_business[] = ['name'=>'商品管理','path'=>'ShopProduct/index&showtype=2','authdata'=>'ShopProduct/*'];

			$component_business[] = ['name'=>'商户订单','path'=>'ShopOrder/index&showtype=2','authdata'=>'ShopOrder/*'];
			// 判断排队免单是否开启
			$paidui_set = self::tableExists('paidui_set') ? Db::name('paidui_set')->where('aid', $aid)->find() : null;
			if(!empty($paidui_set) && isset($paidui_set['status']) && $paidui_set['status'] == 1){
				$component_business[] = ['name'=>'排队记录','path'=>'Paidui/paiduilist&showtype=2','authdata'=>'Paidui/*'];
			}
			$component_business[] = ['name'=>'商户评价','path'=>'BusinessComment/index&showtype=2','authdata'=>'BusinessComment/*'];
			$component_business[] = ['name'=>'拼团商品','path'=>'CollageProduct/index&showtype=2','authdata'=>'CollageProduct/*'];
			$component_business[] = ['name'=>'砍价商品','path'=>'KanjiaProduct/index&showtype=2','authdata'=>'KanjiaProduct/*'];
			$component_business[] = ['name'=>'秒杀商品','path'=>'SeckillProduct/index&showtype=2','authdata'=>'SeckillProduct/*'];
			$component_business[] = ['name'=>'团购商品','path'=>'TuangouProduct/index&showtype=2','authdata'=>'TuangouProduct/*'];
			$component_business[] = ['name'=>'服务商品','path'=>'YuyueList/index&showtype=2','authdata'=>'YuyueList/*'];
            $component_business[] = ['name'=>'周期购商品','path'=>'CycleProduct/index&showtype=2','authdata'=>'CycleProduct/*'];
			$component_business[] = ['name'=>'文章列表','path'=>'Article/index&showtype=2','authdata'=>'Article/*'];
			$component_business[] = ['name'=>'短视频列表','path'=>'Shortvideo/index&showtype=2','authdata'=>'Shortvideo/*'];
			if(false){}else{
				$component_business[] = ['name'=>t('自定义表单'),'path'=>'Form/index&showtype=2','authdata'=>'Form/*'];
			}
			$component_business[] = ['name'=>t('余额').'明细','path'=>'BusinessMoney/moneylog','authdata'=>'BusinessMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel'];
			
			$component_business[] = ['name'=>'充值审核','path'=>'BusinessMoney/rechargelog','authdata'=>'BusinessMoney/*'];
			$component_business[] = ['name'=>'提现记录','path'=>'BusinessMoney/withdrawlog','authdata'=>'BusinessMoney/*'];
			$component_business[] = ['name'=>'通知公告','path'=>'BusinessNotice/index','authdata'=>'BusinessNotice/*'];
			$component_business[] = ['name'=>'分账记录','path'=>'BusinessSplitRecords/index','authdata'=>'BusinessSplitRecords/*'];
			$component_business[] = ['name'=>'默认导航','path'=>'DesignerMenu/business','authdata'=>'DesignerMenu/*'];
			$component_business[] = ['name'=>'系统设置','path'=>'Business/sysset','authdata'=>'Business/sysset'];
			$component_business[] = ['name'=>'余额查询','path'=>'MerchantBalanceQuery/index','authdata'=>'MerchantBalanceQuery/*'];


			$menudata['business'] = ['name'=>'多商户','fullname'=>'多商户管理','icon'=>'my-icon my-icon-business','child'=>$component_business];
			if($uid == 0){
			
				
				$component_business2[] = ['name'=>'商品分类','path'=>'ShopCategory2/index','authdata'=>'ShopCategory2/*','hide'=>true];
				$component_business2[] = ['name'=>'余额明细','path'=>'BusinessMoney/moneylog','authdata'=>'BusinessMoney/*','hide'=>true];
				$component_business2[] = ['name'=>'余额提现','path'=>'BusinessMoney/withdraw','authdata'=>'BusinessMoney/*','hide'=>true];
				$component_business2[] = ['name'=>'充值申请','path'=>'BusinessMoney/recharge','authdata'=>'BusinessMoney/*','hide'=>true];
				$component_business2[] = ['name'=>'充值记录','path'=>'BusinessMoney/rechargelog','authdata'=>'BusinessMoney/*','hide'=>true];
				$component_business2[] = ['name'=>'提现记录','path'=>'BusinessMoney/withdrawlog','authdata'=>'BusinessMoney/*','hide'=>true];
				$component_business2[] = ['name'=>'买单扣费','path'=>'BusinessMaidan/add','authdata'=>'BusinessMaidan/*','hide'=>true];
				$component_business2[] = ['name'=>'买单记录','path'=>'BusinessMaidan/index','authdata'=>'BusinessMaidan/*','hide'=>true];
				$component_business2[] = ['name'=>'收款码','path'=>'BusinessMaidan/set','authdata'=>'BusinessMaidan/*','hide'=>true];
				$component_business2[] = ['name'=>'店铺评价','path'=>'BusinessComment/index','authdata'=>'BusinessComment/*','hide'=>true];
                $component_child[] = ['name'=>'商户后台','child'=>$component_business2,'hide'=>true];

			}
				if(!self::isPluginMenuEnabled('maidan', intval($aid))){
					$maidan_child = [];
					$maidan_child[] = ['name'=>'买单扣费','path'=>'Maidan/add','authdata'=>'Maidan/*'];
					// $maidan_child[] = ['name'=>'买单记录','path'=>'Maidan/index','authdata'=>'Maidan/*'];
					$maidan_child[] = ['name'=>'聚合收款码','path'=>'Maidan/set','authdata'=>'Maidan/set'];
					$component_child[] = ['name'=>'买单收款','child'=>$maidan_child];
				}
		// 进货退货管理
		$component_purchase = [];
		if($isadmin){
			$component_purchase[] = ['name'=>'进货管理','path'=>'ProductPurchase/index','authdata'=>'ProductPurchase/*'];
			$component_purchase[] = ['name'=>'退货管理','path'=>'ProductReturn/index','authdata'=>'ProductReturn/*'];
			$component_purchase[] = ['name'=>'进货统计','path'=>'AdminPurchaseStatistics/index','authdata'=>'AdminPurchaseStatistics/*'];
			$component_purchase[] = ['name'=>'进货设置','path'=>'ProductPurchaseSet/index','authdata'=>'ProductPurchaseSet/*'];
		} else {
			$component_purchase[] = ['name'=>'向总平台进货','path'=>'ProductPurchase/index','authdata'=>'ProductPurchase/*'];
			$component_purchase[] = ['name'=>'退货申请','path'=>'ProductReturn/index','authdata'=>'ProductReturn/*'];
			$component_purchase[] = ['name'=>'进货设置','path'=>'ProductPurchaseSet/index','authdata'=>'ProductPurchaseSet/*'];
		}
		$component_child[] = ['name'=>'进货退货管理','child'=>$component_purchase];


			// 添加股权池功能菜单
			$component_equity_pool = [];
			$component_equity_pool[] = ['name'=>'股权池概览','path'=>'EquityPool/index','authdata'=>'EquityPool/*'];
			$component_equity_pool[] = ['name'=>'股权明细','path'=>'EquityPool/detail','authdata'=>'EquityPool/*'];
			$component_equity_pool[] = ['name'=>'操作记录','path'=>'EquityPool/log','authdata'=>'EquityPool/*'];
			$component_equity_pool[] = ['name'=>'股权池设置','path'=>'EquityPool/set','authdata'=>'EquityPool/*'];
			$component_child[] = ['name'=>'股权池','child'=>$component_equity_pool];
			
				// 在这里添加招聘管理模块
				$component_zhaopin = [];
				$component_zhaopin[] = ['name'=>'数据统计','path'=>'Zhaopin/statistics','authdata'=>'Zhaopin/statistics'];
				$component_zhaopin[] = ['name'=>'企业管理','path'=>'Zhaopin/company','authdata'=>'Zhaopin/*,Zhaopin/company,ApiZhaopin/saveCompany,ApiZhaopin/delCompany'];
				$component_zhaopin[] = ['name'=>'职位管理','path'=>'Zhaopin/job','authdata'=>'Zhaopin/job,Zhaopin/job_edit,Zhaopin/jobList,Zhaopin/saveJob,Zhaopin/delJob'];
				$component_zhaopin[] = ['name'=>'职位分类','path'=>'Zhaopin/category','authdata'=>'Zhaopin/category,ApiZhaopin/saveCategory,ApiZhaopin/delCategory'];
					$component_zhaopin[] = ['name'=>'职位类型','path'=>'Zhaopin/type','authdata'=>'Zhaopin/type,ApiZhaopin/typeList,ApiZhaopin/type_edit,ApiZhaopin/saveType'];
				$component_zhaopin[] = ['name'=>'标签类型','path'=>'Zhaopin/option_types','authdata'=>'Zhaopin/option_types,Zhaopin/option_types_edit,Zhaopin/optionTypesList,Zhaopin/saveOptionType,Zhaopin/delOptionType,Zhaopin/setOptionTypeStatus'];
				$component_zhaopin[] = ['name'=>'标签管理','path'=>'Zhaopin/options','authdata'=>'Zhaopin/options,ApiZhaopin/saveOptions,ApiZhaopin/delOptions'];
				$component_zhaopin[] = ['name'=>'求职会员','path'=>'Zhaopin/Member','authdata'=>'Zhaopin/Member,Zhaopin/excel,Zhaopin/excel,Zhaopin/importexcel,Zhaopin/getplatform,Zhaopin/edit,Zhaopin/save,Zhaopin/del,Zhaopin/getcarddetail,Zhaopin/charts,Zhaopin/setst'];
				$component_zhaopin[] = ['name'=>t('信用分').'明细','path'=>'zhaopin/xinyongfenlog','authdata'=>'zhaopin/xinyongfenlog,zhaopin/xinyongfenlogexcel,zhaopin/xinyongfenlogsetst,zhaopin/xinyongfenlogdel'];
				$component_zhaopin[] = ['name'=>'投递记录','path'=>'Zhaopin/apply','authdata'=>'Zhaopin/apply,ApiZhaopin/updateApplyStatus'];
				$component_zhaopin[] = ['name'=>'入职记录','path'=>'Zhaopin/applyruzhi','authdata'=>'Zhaopin/applyruzhi,ApiZhaopin/updateApplyStatus'];
				$component_zhaopin[] = ['name'=>'面试管理','path'=>'Zhaopin/interview','authdata'=>'Zhaopin/interview,ApiZhaopin/saveInterview,ApiZhaopin/saveInterviewResult'];
				$component_zhaopin[] = ['name'=>'招聘设置','path'=>'Zhaopin/setting','authdata'=>'Zhaopin/setting'];
				$component_zhaopin[] = ['name'=>'简历管理','path'=>'Zhaopin/resume','authdata'=>'Zhaopin/resume,Zhaopin/resumeList,Zhaopin/resume_edit,Zhaopin/saveResume,Zhaopin/delResume'];
				$component_child[] = ['name'=>'招聘管理','child'=>$component_zhaopin];
	





				//门店相关插件
				$component_mendian = [];
				// $component_mendian[] = ['name'=>'门店管理','path'=>'Mendian/index','authdata'=>'Mendian/*'];
				$component_mendian[] = ['name'=>'门店分组','path'=>'MendianGroup/index','authdata'=>'MendianGroup/*'];
				$component_mendian[] = ['name'=>'门店等级','path'=>'MendianLevel/index','authdata'=>'MendianLevel/*'];
				$component_mendian[] = ['name'=>'门店管理设置','path'=>'MendianSet/index','authdata'=>'MendianSet/*'];

				$component_child[] = ['name'=>'门店管理','child'=>$component_mendian];
				$component_tuanzhang = [];
		
			$component_tuanzhang[] = ['name'=>'团长管理','path'=>'Tuanzhang/index','authdata'=>'Tuanzhang/*,TuanzhangFreight/*'];
			$component_tuanzhang[] = ['name'=>'团长分类','path'=>'Tuanzhang/category','authdata'=>'Tuanzhang/*'];
			$component_tuanzhang[] = ['name'=>'团管理','path'=>'Tuanzhang/tuanguanli','authdata'=>'Tuanzhang/*'];
			$component_tuanzhang[] = ['name'=>'商品管理','path'=>'TuanzhangProduct/index','authdata'=>'TuanzhangProduct/*'];
				$component_tuanzhang[] = ['name'=>'订单管理','path'=>'TuanzhangOrder/index','authdata'=>'TuanzhangOrder/*'];
// 			$component_tuanzhang[] = ['name'=>'商品管理','path'=>'ShopProduct/index&showtype=2','authdata'=>'ShopProduct/*'];
// 			$component_tuanzhang[] = ['name'=>'团长订单','path'=>'ShopOrder/index&showtype=2','authdata'=>'ShopOrder/*'];
// 			$component_tuanzhang[] = ['name'=>'团长评价','path'=>'TuanzhangComment/index&showtype=2','authdata'=>'TuanzhangComment/*'];
			
		
		
// 			$component_tuanzhang[] = ['name'=>t('余额').'明细','path'=>'TuanzhangMoney/moneylog','authdata'=>'TuanzhangMoney/moneylog,BusinessMoney/moneylogexcel,BusinessMoney/moneylogsetst,BusinessMoney/moneylogdel'];
			
			$component_tuanzhang[] = ['name'=>'提现记录','path'=>'TuanzhangMoney/withdrawlog','authdata'=>'TuanzhangMoney/*'];
// 			$component_business[] = ['name'=>'通知公告','path'=>'TuanzhangNotice/index','authdata'=>'TuanzhangNotice/*'];
// 			$component_business[] = ['name'=>'默认导航','path'=>'DesignerMenu/business','authdata'=>'DesignerMenu/*'];
	$component_tuanzhang[] = ['name'=>'分享海报','path'=>'TuanzhangPoster/index','authdata'=>'TuanzhangPoster/*'];
			$component_tuanzhang[] = ['name'=>'系统设置','path'=>'Tuanzhang/sysset','authdata'=>'Tuanzhang/sysset'];


			//多城市拓展菜单项
			$component_area[] = ['name'=>'基础设置','path'=>'Area/config','authdata'=>'Area/*'];
        	$component_area[] = ['name'=>'装修模板','path'=>'Area/templates','authdata'=>'Area/*'];
        	$component_area[] = ['name'=>'模板配置','path'=>'Area/settings','authdata'=>'Area/*'];
        	$component_area[] = ['name'=>'默认模板','path'=>'Area/defaulttemplate','authdata'=>'Area/*'];
        
        	$component_child[] = ['name'=>'多城市','child'=>$component_area];
        	
        	// 省市区街道管理
        	if($isadmin){
        		$component_area_manage = [];
        		$component_area_manage[] = ['name'=>'地区管理','path'=>'Area/areamanage','authdata'=>'Area/*'];
        		$component_area_manage[] = ['name'=>'地区搜索','path'=>'Area/areasearch','authdata'=>'Area/*'];
        		$component_area_manage[] = ['name'=>'批量导入','path'=>'Area/areaimport','authdata'=>'Area/*'];
        		$component_area_manage[] = ['name'=>'地区统计','path'=>'Area/areastats','authdata'=>'Area/*'];
        		$component_child[] = ['name'=>'地址库管理','child'=>$component_area_manage];
        	}
        	
				//多城市拓展菜单项
// 			$component_shangjia[] = ['name'=>'基础设置','path'=>'shangjia/config','authdata'=>'shangjia/*'];
        
        
//         	$component_child[] = ['name'=>'商户充值','child'=>$component_shangjia];
			
			
			$sweepstakes_settings_child = [
				['name'=>'抽奖列表', 'path'=>'SweepstakesList/index', 'authdata'=>'SweepstakesList/*'],
				['name'=>'抽奖商品', 'path'=>'SweepstakesList/list', 'authdata'=>'SweepstakesList/*'],
				['name'=>'抽奖订单', 'path'=>'SweepstakesOrder/index', 'authdata'=>'SweepstakesOrder/*'],
				['name'=>'抽奖设置', 'path'=>'SweepstakesSet/index', 'authdata'=>'SweepstakesSet/*'],
			];

			$component_child[] = ['name'=>'欢乐抽奖', 'child'=> $sweepstakes_settings_child];

// ... existing code ...

		}
		
		$component_child[] = ['name'=>'团长','child'=>$component_tuanzhang];
		$component_Daihuoyiuan = [];
		$component_Daihuoyiuan[] = ['name'=>'带货团管理','path'=>'Daihuoyiuan/index','authdata'=>'Daihuoyiuan/*'];
		$component_Daihuoyiuan[] = ['name'=>'商品管理','path'=>'TuanzhangProduct/index','authdata'=>'TuanzhangProduct/*'];
		$component_Daihuoyiuan[] = ['name'=>'订单管理','path'=>'TuanzhangOrder/index','authdata'=>'TuanzhangOrder/*'];
		$component_Daihuoyiuan[] = ['name'=>'团长收益','path'=>'TuanzhangMoney/moneylog','authdata'=>'TuanzhangMoney/*'];
		$component_Daihuoyiuan[] = ['name'=>'提现申请','path'=>'TuanzhangMoney/withdraw','authdata'=>'TuanzhangMoney/*'];
		$component_Daihuoyiuan[] = ['name'=>'提现记录','path'=>'TuanzhangMoney/withdrawlog','authdata'=>'TuanzhangMoney/*'];
		if($isadmin){
// 			$component_Daihuoyiuan = [];
// 		$component_Daihuoyiuan[] = ['name'=>'带货团管理','path'=>'Daihuoyiuan/index','authdata'=>'Daihuoyiuan/*'];
		$component_Daihuoyiuan[] = ['name'=>'带货团分类','path'=>'DaihuoyiuanCategory/index','authdata'=>'DaihuoyiuanCategory/*'];
// 			$component_Daihuoyiuan[] = ['name'=>'团长','path'=>'DaihuoyiuanCategory/index','authdata'=>'DaihuoyiuanCategory/*'];
		$component_Daihuoyiuan[] = ['name'=>'评论列表','path'=>'DaihuoyiuanPinglun/index','authdata'=>'DaihuoyiuanPinglun/*'];
		$component_Daihuoyiuan[] = ['name'=>'回评列表','path'=>'DaihuoyiuanPlreply/index','authdata'=>'DaihuoyiuanPlreply/*'];
		$component_Daihuoyiuan[] = ['name'=>'系统设置','path'=>'DaihuoyiuanSet/set','authdata'=>'DaihuoyiuanSet/*'];

		}
				$component_child[] = ['name'=>'带货团专题','child'=>$component_Daihuoyiuan];
				//$menudata['Daihuoyiuan'] = ['name'=>'带货团','fullname'=>'带货团菜单','icon'=>'my-icon my-icon-yingxiao','child'=>$component_Daihuoyiuan];
		
		// 仓库管理
		$component_warehouse = [];
		$component_warehouse[] = ['name'=>'商品列表','path'=>'WarehouseProduct/index','authdata'=>'WarehouseProduct/*'];

		$component_warehouse[] = ['name'=>'入库单','path'=>'WarehouseInbound/index','authdata'=>'WarehouseProduct/*,WarehouseInbound/*'];
		$component_warehouse[] = ['name'=>'出库单','path'=>'WarehouseOutbound/index','authdata'=>'WarehouseProduct/*,WarehouseOutbound/*'];
		$component_warehouse[] = ['name'=>'组装单','path'=>'WarehouseAssemble/index','authdata'=>'WarehouseProduct/*,WarehouseAssemble/*'];
		$component_warehouse[] = ['name'=>'拆卸单','path'=>'WarehouseSplit/index','authdata'=>'WarehouseProduct/*,WarehouseSplit/*'];
		$component_warehouse[] = ['name'=>'库存盘点','path'=>'WarehouseInventory/index','authdata'=>'WarehouseProduct/*,WarehouseInventory/*'];
		$component_warehouse[] = ['name'=>'模板管理','path'=>'WarehouseTemplate/index','authdata'=>'WarehouseProduct/*,WarehouseTemplate/*'];
		$component_warehouse[] = ['name'=>'设置','path'=>'WarehouseSet/index','authdata'=>'WarehouseProduct/*,WarehouseSet/*'];

		$component_child[] = ['name'=>'仓库管理','child'=>$component_warehouse];
		
	
			// 保险管理
			$component_insurance = [];
			$component_insurance[] = ['name'=>'保险订单','path'=>'insurance_order/index','authdata'=>'InsuranceOrder/*,insurance_setting/*'];
			$component_insurance[] = ['name'=>'保险设置','path'=>'insurance_setting/index','authdata'=>'InsuranceSettingr/*,insurance_setting/*'];

			$component_child[] = ['name'=>'保险管理','child'=>$component_insurance];
			
			// Coze API管理
			$component_coze = [];
			$component_coze[] = ['name'=>'API配置','path'=>'coze/config','authdata'=>'Coze/*,coze/config'];
			$component_coze[] = ['name'=>'工作流管理','path'=>'coze/workflow','authdata'=>'Coze/*,coze/workflow'];
			$component_coze[] = ['name'=>'执行日志','path'=>'coze/workflowLog','authdata'=>'Coze/*,coze/workflowLog'];
			$component_coze[] = ['name'=>'工作流演示','path'=>'coze/workflowDemo','authdata'=>'Coze/*,coze/workflowDemo'];
			$component_coze[] = ['name'=>'参数配置','path'=>'coze/workflowParams','authdata'=>'Coze/*,coze/workflowParams'];
			$component_coze[] = ['name'=>'对话助手','path'=>'CozeChatAssistant/index','authdata'=>'CozeChatAssistant/*'];
			$component_coze[] = ['name'=>'对话记录','path'=>'CozeChatAssistant/chatRecord','authdata'=>'CozeChatAssistant/*'];
			$component_coze[] = ['name'=>'会话管理','path'=>'CozeChatAssistant/conversationList','authdata'=>'CozeChatAssistant/*'];
			$component_coze[] = ['name'=>'快捷菜单','path'=>'CozeChatMenu/index','authdata'=>'CozeChatMenu/*'];

			$component_child[] = ['name'=>'Coze API','child'=>$component_coze];

			// 业绩缓存管理
			$component_performance_cache = [];
			$component_performance_cache[] = ['name'=>'系统设置','path'=>'PerformanceCache/settings','authdata'=>'PerformanceCache/*'];
			$component_performance_cache[] = ['name'=>'缓存记录','path'=>'PerformanceCache/records','authdata'=>'PerformanceCache/*'];
			$component_performance_cache[] = ['name'=>'管理操作','path'=>'PerformanceCache/manage','authdata'=>'PerformanceCache/*'];
			$component_performance_cache[] = ['name'=>'开发文档','path'=>'PerformanceCacheDoc/index','authdata'=>'PerformanceCacheDoc/*'];
			$component_performance_cache[] = ['name'=>'用户详情','path'=>'PerformanceCache/detail','authdata'=>'PerformanceCache/*','hide'=>true];
			$component_performance_cache[] = ['name'=>'实时计算','path'=>'PerformanceCache/calculate','authdata'=>'PerformanceCache/*','hide'=>true];

			$component_child[] = ['name'=>'业绩缓存','child'=>$component_performance_cache];

			// 梦想启蒙管理
			$component_dream = [];
			$component_dream[] = ['name'=>'梦想记录','path'=>'DreamInspiration/index','authdata'=>'DreamInspiration/*'];
			$component_dream[] = ['name'=>'启蒙设置','path'=>'DreamInspiration/setting','authdata'=>'DreamInspiration/*'];

			$component_child[] = ['name'=>'梦想启蒙','child'=>$component_dream];

			// 合同管理
			$component_contract = [];
			$component_contract[] = ['name'=>'合同录入','path'=>'Contract/index','authdata'=>'Contract/*'];
			$component_child[] = ['name'=>'合同管理','child'=>$component_contract];

		// 直播管理
		$component_liveWeb = [];
		$component_liveWeb[] = ['name'=>'直播列表','path'=>'LiveWeb/index','authdata'=>'LiveWeb/*,LiveWebProduct/*,LiveWebProduct/*,LiveWebShare/*'];
		$component_liveWeb[] = ['name'=>'直播分组','path'=>'live_web_share_group/index','authdata'=>'live_web_share_group/*'];
		$component_liveWeb[] = ['name'=>'海报设置','path'=>'LiveWebPoster/index','authdata'=>'LiveWebPoster/*'];
		$component_liveWeb[] = ['name'=>'观看记录','path'=>'LiveWeb/watch_log','authdata'=>'LiveWeb/watch_log'];

		$component_child[] = ['name'=>'直播管理','child'=>$component_liveWeb];
		
		// BPV管理
		$component_bpv = [];
		$component_bpv[] = ['name'=>'BPV概览','path'=>'Bpv/index','authdata'=>'Bpv/*'];
		$component_bpv[] = ['name'=>'基础设置','path'=>'Bpv/setting','authdata'=>'Bpv/*'];
		$component_bpv[] = ['name'=>'兑换记录','path'=>'Bpv/exchangeList','authdata'=>'Bpv/*'];
		$component_bpv[] = ['name'=>'提现记录','path'=>'Bpv/cashoutList','authdata'=>'Bpv/*'];
		$component_bpv[] = ['name'=>'分红记录','path'=>'Bpv/profitList','authdata'=>'Bpv/*'];

		$component_child[] = ['name'=>'BPV管理','child'=>$component_bpv];

		// 单数奖管理
		$component_danshujiang = [];
		$component_danshujiang[] = ['name'=>'单数奖列表','path'=>'Danshujiang/index','authdata'=>'Danshujiang/*'];
		$component_danshujiang[] = ['name'=>'奖励记录管理','path'=>'Danshujiang/record','authdata'=>'Danshujiang/*'];
		$component_child[] = ['name'=>'单数奖管理','child'=>$component_danshujiang];

		$tuandui_plugin_installed = false;
		try{
			$tuandui_plugin_installed = Db::name('admin_plugin_menu')
				->where('aid', $aid)
				->where('plugin_name', 'tuandui')
				->count() > 0;
		}catch(\Throwable $e){
			$tuandui_plugin_installed = false;
		}
		
			// 团队业绩奖励管理
			$component_tuandui = [];
			$component_tuandui[] = ['name'=>'奖励活动管理','path'=>'Tuandui/index','authdata'=>'Tuandui/*'];
			$component_tuandui[] = ['name'=>'奖励记录管理','path'=>'Tuandui/record','authdata'=>'Tuandui/*'];
			$component_child[] = ['name'=>'业绩奖励','child'=>$component_tuandui];
		

		// 添加等级推荐奖励菜单
		$component_levelreward = [];
		$component_levelreward[] = ['name'=>'等级奖励设置','path'=>'Levelreward/index','authdata'=>'Levelreward/*'];
		$component_levelreward[] = ['name'=>'奖励记录管理','path'=>'Levelreward/record','authdata'=>'Levelreward/*'];
		$component_levelreward[] = ['name'=>'系统设置','path'=>'Levelreward/set','authdata'=>'Levelreward/*'];
		$component_child[] = ['name'=>'等级推荐奖励','child'=>$component_levelreward];
	// 单数奖管理
		$component_paimingjiang = [];
		$component_paimingjiang[] = ['name'=>'排名奖励列表','path'=>'Paimingjiang/index','authdata'=>'Paimingjiang/*'];
		$component_paimingjiang[] = ['name'=>'奖励记录管理','path'=>'Paimingjiang/record','authdata'=>'Paimingjiang/*'];
		$component_child[] = ['name'=>'排名奖励管理','child'=>$component_paimingjiang];

				// 场馆管理
		$component_venues = [];
		$component_venues[] = ['name'=>'场馆列表','path'=>'Venues/index','authdata'=>'Venues/*'];

		if($isadmin){
			$component_venues[] = ['name'=>'场馆类别','path'=>'VenuesCatalog/index','authdata'=>'Venues/*,VenuesCatalog/*'];
			$component_venues[] = ['name'=>'场馆服务','path'=>'VenuesService/index','authdata'=>'Venues/*,VenuesService/*'];
			$component_venues[] = ['name'=>'节假日管理','path'=>'VenuesHoliday/index','authdata'=>'Venues/*,VenuesHoliday/*'];
		}
		$component_venues[] = ['name'=>'场地管理','path'=>'VenuesField/index','authdata'=>'Venues/*,VenuesField/*'];
		$component_venues[] = ['name'=>'包场管理','path'=>'VenuesPackage/index','authdata'=>'Venues/*,VenuesPackage/*'];		
		$component_venues[] = ['name'=>'订单管理','path'=>'VenuesOrder/index','authdata'=>'Venues/*,VenuesOrder/*'];
		$component_venues[] = ['name'=>'评价管理','path'=>'VenuesComment/index','authdata'=>'Venues/*,VenuesComment/*'];


		$component_venues[] = ['name'=>'活动管理','path'=>'VenuesAppointment/index','authdata'=>'Venues/*,VenuesAppointment/*'];
		
		if($isadmin){
			$component_venues[] = ['name'=>'订场配置','path'=>'Venues/set','authdata'=>'Venues/*'];
		}

		$component_child[] = ['name'=>'场馆管理','child'=>$component_venues];

			// 演出管理
		$component_theater = [];
		$component_theater[] = ['name'=>'场馆列表','path'=>'TheaterVenues/index','authdata'=>'TheaterVenues/*'];
		$component_theater[] = ['name'=>'演出列表','path'=>'TheaterEpisode/index','authdata'=>'TheaterEpisode/*'];
		$component_theater[] = ['name'=>'订单管理','path'=>'TheaterOrder/index','authdata'=>'TheaterOrder/*'];
		$component_theater[] = ['name'=>'创建订单','path'=>'TheaterOrder/placeOrder','authdata'=>'TheaterOrder/*'];
		$component_theater[] = ['name'=>'数据统计','path'=>'TheaterEpisode/statistics','authdata'=>'TheaterEpisode/*'];

		$component_child[] = ['name'=>'演出管理','child'=>$component_theater];

		
		$component_article = [];
		$component_article[] = ['name'=>'文章列表','path'=>'Article/index','authdata'=>'Article/*'];
		$component_article[] = ['name'=>'文章分类','path'=>'ArticleCategory/index','authdata'=>'ArticleCategory/*'];
		$component_article[] = ['name'=>'评论列表','path'=>'ArticlePinglun/index','authdata'=>'ArticlePinglun/*'];
		$component_article[] = ['name'=>'文章回评','path'=>'ArticlePlreply/index','authdata'=>'ArticlePlreply/*'];
		$component_article[] = ['name'=>'系统设置','path'=>'ArticleSet/set','authdata'=>'ArticleSet/*'];
		$component_child[] = ['name'=>'文章管理','child'=>$component_article];

			$component_huodong_baoming=[];
			$component_huodong_baoming[] = ['name'=>'活动报名类型','path'=>'HuodongBaomingCategory/index','authdata'=>'HuodongBaomingCategory/*'];
			$component_huodong_baoming[] = ['name'=>'活动报名列表','path'=>'HuodongBaomingList/index','authdata'=>'HuodongBaomingList/*'];
			$component_huodong_baoming[] = ['name'=>'活动报名订单','path'=>'HuodongBaomingOrder/index','authdata'=>'HuodongBaomingOrder/*'];
            if($isadmin){
				$component_huodong_baoming[] = ['name'=>'海报设置','path'=>'HuodongBaomingPoster/index','authdata'=>'HuodongBaomingPoster/*'];
				$component_huodong_baoming[] = ['name'=>'系统设置','path'=>'HuodongBaomingSet/set','authdata'=>'HuodongBaomingSet/*'];
			}
			$component_child[] = ['name'=>'活动报名','child'=>$component_huodong_baoming];
		
		$component_filem = [];
		$component_filem[] = ['name'=>'文件列表','path'=>'Filem/index','authdata'=>'Filem/*'];
		$component_filem[] = ['name'=>'文件分类','path'=>'FilemCategory/index','authdata'=>'FilemCategory/*'];
		$component_filem[] = ['name'=>'系统设置','path'=>'FilemSet/set','authdata'=>'FilemSet/*'];
		$component_child[] = ['name'=>'文件管家','child'=>$component_filem];
		// $menudata['Filem'] = ['name'=>'文件管家','fullname'=>'文件管家菜单','icon'=>'my-icon my-icon-yingxiao','child'=>$component_filem];
		
		// 用户论坛菜单已迁移到插件系统 (luntan)，由插件自动注册
		if($isadmin){
			$component_luntan = [];
			$component_luntan[] = ['name'=>'帖子列表','path'=>'Luntan/index','authdata'=>'Luntan/*'];
			$component_luntan[] = ['name'=>'帖子分类','path'=>'LuntanCategory/index','authdata'=>'LuntanCategory/*'];
			$component_luntan[] = ['name'=>'评论列表','path'=>'LuntanPinglun/index','authdata'=>'LuntanPinglun/*'];
			$component_luntan[] = ['name'=>'帖子回评','path'=>'LuntanPlreply/index','authdata'=>'LuntanPlreply/*'];
			$component_luntan[] = ['name'=>'系统设置','path'=>'Luntan/sysset','authdata'=>'Luntan/sysset'];
			$component_child[] = ['name'=>'用户论坛','child'=>$component_luntan];
		}
		
		if($isadmin){
            $component_caigou = [];
            $component_caigou[] = ['name'=>'信息列表','path'=>'Caigou/index','authdata'=>'Caigou/*'];
            $component_caigou[] = ['name'=>'分类管理','path'=>'CaigouCategory/index','authdata'=>'CaigouCategory/*'];
            $component_caigou[] = ['name'=>'评论列表','path'=>'CaigouPinglun/index','authdata'=>'CaigouPinglun/*'];
            $component_caigou[] = ['name'=>'评论回复','path'=>'CaigouPlreply/index','authdata'=>'CaigouPlreply/*'];
            $component_caigou[] = ['name'=>'系统设置','path'=>'Caigou/sysset','authdata'=>'Caigou/sysset'];
            $component_child[] = ['name'=>'采购供应','child'=>$component_caigou];
		}
		
	if ($isadmin) {
    $component_farm = [];
    
    // 地块管理
    $component_farm[] = [ 'name' => '地块管理', 'path' => 'Plots/dikuai', 'authdata' => 'Plots/*'];
    
    // 排队队列
    $component_farm[] = ['name' => '排队队列', 'path' => 'Plots/queueList','authdata' => 'Plots/*'];
    
    // 猪仔管理
    $component_farm[] = [ 'name' => '猪仔管理','path' => 'Plots/piglist', 'authdata' => 'Plots/*'];
    
    // 喂养日志
    $component_farm[] = ['name' => '喂养日志','path' => 'Plots/weiyangLogs','authdata' => 'Plots/*'];
    
    // 出售记录
    $component_farm[] = ['name' => '出售记录','path' => 'Plots/saleLogs','authdata' => 'Plots/*'];

    // 将菜单添加到主菜单
    $component_child[] = ['name' => '农场管理', 'child' => $component_farm ];
}


		// 带货笔记菜单已迁移到插件系统（daihuobiji），通过插件授权后在“我的插件/插件管理”进入
		
				$component_shenqingbandian=[];
		$component_shenqingbandian[] = ['name'=>'服务类型','path'=>'Shenqingbandian/index','authdata'=>'Shenqingbandian/*'];
		$component_shenqingbandian[] = ['name'=>'服务商品','path'=>'ShenqingbandianList/index','authdata'=>'ShenqingbandianList/*'];
		$component_shenqingbandian[] = ['name'=>'服务订单','path'=>'ShenqingbandianOrder/index','authdata'=>'ShenqingbandianOrder/*'];
		$component_shenqingbandian[] = ['name'=>'人员类型','path'=>'ShenqingbandianWorkerCategory/index','authdata'=>'ShenqingbandianWorkerCategory/*'];
		$component_shenqingbandian[] = ['name'=>'人员列表','path'=>'ShenqingbandianWorker/index','authdata'=>'ShenqingbandianWorker/*'];
		$component_shenqingbandian[] = ['name'=>'人员评价','path'=>'ShenqingbandianWorkerComment/index','authdata'=>'ShenqingbandianWorkerComment/*'];
	    
	    $component_shenqingbandian[] = ['name'=>'管理人员','path'=>'ShenqingbandianAdmin/index','authdata'=>'ShenqingbandianAdmin/*'];
		$component_shenqingbandian[] = ['name'=>'系统设置','path'=>'ShenqingbandianSet/set','authdata'=>'ShenqingbandianSet/*'];
		$component_shenqingbandian[] = ['name'=>'pdf生成设置','path'=>'ShenqingbandianSet/setpdf','authdata'=>'ShenqingbandianSet/*'];
		$component_child[] = ['name'=>'申请办电','child'=>$component_shenqingbandian];

	// 物业管理
	$component_property = [];
	$component_property[] = ['name'=>'小区管理','path'=>'PropertyCommunity/index','authdata'=>'PropertyCommunity/*'];
	$component_property[] = ['name'=>'楼栋管理','path'=>'PropertyBuilding/index','authdata'=>'PropertyBuilding/*'];
	$component_property[] = ['name'=>'房屋管理','path'=>'PropertyRoom/index','authdata'=>'PropertyRoom/*'];
	$component_property[] = ['name'=>'缴费记录','path'=>'PropertyPayment/index','authdata'=>'PropertyPayment/*'];
	$component_property[] = ['name'=>'账单管理','path'=>'PropertyBill/index','authdata'=>'PropertyBill/*'];
	$component_property[] = ['name'=>'抵扣账单','path'=>'PropertyDeduction/index','authdata'=>'PropertyDeduction/*'];
	$component_property[] = ['name'=>'统计报表','path'=>'PropertyStatistics/index','authdata'=>'PropertyStatistics/*'];
	if($isadmin){
		$component_property[] = ['name'=>'系统设置','path'=>'PropertySetting/index','authdata'=>'PropertySetting/*'];
	}
	$component_child[] = ['name'=>'物业管理','child'=>$component_property];

		$component_tongxunlu=[];
		$component_tongxunlu[] = ['name'=>'区域管理','path'=>'TongxunluFuwuquyu/index','authdata'=>'TongxunluFuwuquyu/*'];
// 		$component_tongxunlu[] = ['name'=>'网点管理','path'=>'TongxunluFuwuzhan/index','authdata'=>'TongxunluFuwuzhan/*'];
	   // $component_tongxunlu[] = ['name'=>'小区管理','path'=>'TongxunluXiaoqu/index','authdata'=>'TongxunluXiaoqu/*'];
		$component_tongxunlu[] = ['name'=>'人员列表','path'=>'TongxunluWorker/index','authdata'=>'TongxunluWorker/*'];
	    $component_tongxunlu[] = ['name'=>'系统设置','path'=>'TongxunluSet/set','authdata'=>'TongxunluSet/*'];
		
		$component_child[] = ['name'=>'服务网点','child'=>$component_tongxunlu];

		$component_electricityform=[];
		$component_electricityform[] = ['name'=>'表单管理','path'=>'ElectricityForm/index','authdata'=>'ElectricityForm/*'];
		$component_electricityform[] = ['name'=>'人员列表','path'=>'ElectricityWorker/index','authdata'=>'ElectricityForm/*,ElectricityWorker/*'];
		$component_child[] = ['name'=>'流程中转','child'=>$component_electricityform];
		
		if($isadmin){
			$component_sign = [];
			$component_sign[] = ['name'=>'签到记录','path'=>'Sign/record','authdata'=>'Sign/record,Sign/recordexcel,Sign/recorddel'];
			$component_sign[] = ['name'=>'签到设置','path'=>'Sign/set','authdata'=>'Sign/set'];
			$component_child[] = ['name'=>t('积分').'签到','child'=>$component_sign];
		}
		//预约服务
		//$yuyue = db('yuyue_set')->field('diyname')->where('aid',aid)->find();
		$component_yuyue=[];
		$component_yuyue[] = ['name'=>'服务类型','path'=>'Yuyue/index','authdata'=>'Yuyue/*'];
		$component_yuyue[] = ['name'=>'服务商品','path'=>'YuyueList/index','authdata'=>'YuyueList/*'];
		$component_yuyue[] = ['name'=>'服务套餐','path'=>'YuyuePackage/index','authdata'=>'YuyuePackage/*'];
		$component_yuyue[] = ['name'=>'套餐订单列表','path'=>'YuyuePackageOrder/index','authdata'=>'YuyuePackageOrder/*']; // 新增套餐订单列表菜单
		$component_yuyue[] = ['name'=>'服务订单','path'=>'YuyueOrder/index','authdata'=>'YuyueOrder/*'];
		$component_yuyue[] = ['name'=>'录入订单','path'=>'YuyueOrderlr/index','authdata'=>'YuyueOrderlr/*,YuyueFuwu/index,YuyueFuwu/getfuwu,Member/index'];
		$component_yuyue[] = ['name'=>'商品服务','path'=>'YuyueFuwu/index','authdata'=>'YuyueFuwu/*'];
		$component_yuyue[] = ['name'=>'服务标签','path'=>'YuyueServiceTag/index','authdata'=>'YuyueServiceTag/*'];
		$component_yuyue[] = ['name'=>'商品评价','path'=>'YuyueComment/index','authdata'=>'YuyueComment/*'];
		if($isadmin){
			$component_yuyue[] = ['name'=>'海报设置','path'=>'YuyuePoster/index','authdata'=>'YuyuePoster/*'];
		}
		$component_yuyue[] = ['name'=>'人员类型','path'=>'YuyueWorkerCategory/index','authdata'=>'YuyueWorkerCategory/*'];
		$component_yuyue[] = ['name'=>'人员列表','path'=>'YuyueWorker/index','authdata'=>'YuyueWorker/*'];
		$component_yuyue[] = ['name'=>'服务日历','path'=>'YuyueCalendar/index','authdata'=>'YuyueCalendar/*'];
		$component_yuyue[] = ['name'=>'人员评价','path'=>'YuyueWorkerComment/index','authdata'=>'YuyueWorkerComment/*'];
		$component_yuyue[] = ['name'=>'提成明细','path'=>'YuyueMoney/moneylog','authdata'=>'YuyueMoney/*'];
		$component_yuyue[] = ['name'=>'提现记录','path'=>'YuyueMoney/withdrawlog','authdata'=>'YuyueMoney/*'];
		$component_yuyue[] = ['name'=>'系统设置','path'=>'YuyueSet/set','authdata'=>'YuyueSet/*'];
		$component_child[] = ['name'=>'预约服务','child'=>$component_yuyue];

//     	$component_yuyue=[];
// 		$component_yuyue[] = ['name'=>'活动类型','path'=>'Huodongbaoming/index','authdata'=>'Yuyue/*'];
// 		$component_yuyue[] = ['name'=>'活动管理','path'=>'HuodongbaomingList/index','authdata'=>'YuyueList/*'];
// 		$component_yuyue[] = ['name'=>'报名订单','path'=>'HuodongbaomingOrder/index','authdata'=>'YuyueOrder/*'];
// 		$component_yuyue[] = ['name'=>'商品评价','path'=>'HuodongbaomingComment/index','authdata'=>'YuyueComment/*'];
// 		if($isadmin){
// 			$component_yuyue[] = ['name'=>'海报设置','path'=>'HuodongbaomingPoster/index','authdata'=>'YuyuePoster/*'];
// 		}
// 		$component_yuyue[] = ['name'=>'提成明细','path'=>'Huodongbaoming/moneylog','authdata'=>'YuyueMoney/*'];
// 		$component_yuyue[] = ['name'=>'提现记录','path'=>'Huodongbaoming/withdrawlog','authdata'=>'YuyueMoney/*'];
// 		$component_yuyue[] = ['name'=>'系统设置','path'=>'HuodongbaomingSet/set','authdata'=>'YuyueSet/*'];
// 		$component_child[] = ['name'=>'活动报名','child'=>$component_yuyue];

		$component_kecheng=[];
		$component_kecheng[] = ['name'=>'课程类型','path'=>'KechengCategory/index','authdata'=>'KechengCategory/*'];
		$component_kecheng[] = ['name'=>'课程列表','path'=>'KechengList/index','authdata'=>'KechengList/*,KechengRecord/*'];
		$component_kecheng[] = ['name'=>'课程训练营','path'=>'KechengTraining/index','authdata'=>'KechengTraining/*'];
		$component_kecheng[] = ['name'=>'课程章节','path'=>'KechengChapter/index','authdata'=>'KechengChapter/*'];
		$component_kecheng[] = ['name'=>'课程笔记','path'=>'KechengNotes/index','authdata'=>'KechengNotes/*'];
		$component_kecheng[] = ['name'=>'题库管理','path'=>'KechengTiku/index','authdata'=>'KechengTiku/*'];
		$component_kecheng[] = ['name'=>'课程订单','path'=>'KechengOrder/index','authdata'=>'KechengOrder/*'];
		$component_kecheng[] = ['name'=>'学习记录','path'=>'KechengStudylog/index','authdata'=>'KechengStudylog/*'];
		if($isadmin){
			$component_kecheng[] = ['name'=>'课程海报','path'=>'KechengPoster/index','authdata'=>'KechengPoster/*'];
			$component_kecheng[] = ['name'=>'课程设置','path'=>'KechengSet/index','authdata'=>'KechengSet/*'];
		}
		$component_child[] = ['name'=>'知识付费','child'=>$component_kecheng];
		
		
		//考试系统
		$component_exam=[];
		$component_exam[] = ['name'=>'题库类型','path'=>'ExamType/index','authdata'=>'ExamType/*'];
		$component_exam[] = ['name'=>'答题分类','path'=>'ExamCategory/index','authdata'=>'ExamCategory/*'];
		$component_exam[] = ['name'=>'试卷管理','path'=>'ExamList/index','authdata'=>'ExamList/*'];
		$component_exam[] = ['name'=>'订单管理','path'=>'ExamOrder/index','authdata'=>'ExamOrder/*'];
		$component_exam[] = ['name'=>'题库管理','path'=>'ExamTiku/index','authdata'=>'ExamTiku/*'];
		$component_exam[] = ['name'=>'PK配置','path'=>'ExamPkConfig/index','authdata'=>'ExamPkConfig/*'];
		if($isadmin){
			$component_exam[] = ['name'=>'PK机器人','path'=>'ExamPkBot/index','authdata'=>'ExamPkBot/*'];
		}
		$component_exam[] = ['name'=>'PK对局','path'=>'ExamPkMatch/index','authdata'=>'ExamPkMatch/*'];
		$component_exam[] = ['name'=>'PK战绩','path'=>'ExamPkStats/index','authdata'=>'ExamPkStats/*'];
		
		$component_child[] = ['name'=>'考试系统','child'=>$component_exam];
		
		$component_daxue = [];
		$component_daxue[] = ['name'=>'大学列表','path'=>'Daxue/index','authdata'=>'Daxue/*,DaxueFreight/*'];
		$component_daxue[] = ['name'=>'笔记管理','path'=>'Daxue/category','authdata'=>'Daxue/*'];
		$component_daxue[] = ['name'=>'类型管理','path'=>'Daxue/leixing','authdata'=>'Daxue/*'];
		$component_daxue[] = ['name'=>'笔记标签','path'=>'Daxue/biaoqian','authdata'=>'Daxue/*'];
		$component_daxue[] = ['name'=>'专业管理','path'=>'Daxue/zhuanye','authdata'=>'Daxue/*'];
		$component_daxue[] = ['name'=>'分数线管理','path'=>'Daxue/fenshuxian','authdata'=>'Daxue/*'];
		$component_daxue[] = ['name'=>'招生资料','path'=>'Daxue/Article','authdata'=>'Daxue/*'];
		$component_daxue[] = ['name'=>'系统设置','path'=>'Daxue/sysset','authdata'=>'Daxue/sysset'];
			$component_child[] = ['name'=>'大学列表','child'=>$component_daxue];

		if($isadmin){
			$component_peisong = [];
			$component_peisong[] = ['name'=>'配送员列表','path'=>'PeisongUser/index','authdata'=>'PeisongUser/*'];
			$component_peisong[] = ['name'=>'配送单列表','path'=>'PeisongOrder/index','authdata'=>'PeisongOrder/*'];
			$component_peisong[] = ['name'=>'评价列表','path'=>'PeisongComment/index','authdata'=>'PeisongComment/*'];
			$component_peisong[] = ['name'=>'提成明细','path'=>'PeisongMoney/moneylog','authdata'=>'PeisongMoney/*'];
			$component_peisong[] = ['name'=>'提现记录','path'=>'PeisongMoney/withdrawlog','authdata'=>'Peisong/*'];
			$component_peisong[] = ['name'=>'退框记录','path'=>'PeisongOrder/tuikuanglog','authdata'=>'PeisongOrder/*'];
			$component_peisong[] = ['name'=>'系统设置','path'=>'Peisong/set','authdata'=>'Peisong/*'];
			$component_peisong[] = ['name'=>'码科跑腿对接','path'=>'Peisong/makeset','authdata'=>'Peisong/*'];
			$component_child[] = ['name'=>'同城配送','child'=>$component_peisong];
			$component_express = [];
			$component_express[] = ['name'=>'活动列表','path'=>'Toupiao/index','authdata'=>'Toupiao/*'];
			$component_express[] = ['name'=>'选手列表','path'=>'Toupiao/joinlist','authdata'=>'Toupiao/*'];
			$component_express[] = ['name'=>'投票记录','path'=>'Toupiao/helplist','authdata'=>'Toupiao/*'];
			//$component_express[] = ['name'=>'投票设置','path'=>'Toupiao/set','authdata'=>'Toupiao/*'];
			$component_child[] = ['name'=>'投票活动','child'=>$component_express];
			
			
		}

		$yingxiao_cycle = [];
		$yingxiao_cycle[] = ['name'=>'商品管理','path'=>'CycleProduct/index','authdata'=>'CycleProduct/*,CycleCode/*'];
		$yingxiao_cycle[] = ['name'=>'订单管理','path'=>'CycleOrder/index','authdata'=>'CycleOrder/*'];
		$yingxiao_cycle[] = ['name'=>'配送管理','path'=>'CycleOrder/cycle_list&status=1','authdata'=>'CycleOrder/*'];
		$yingxiao_cycle[] = ['name'=>'评价管理','path'=>'CycleComment/index','authdata'=>'CycleComment/*'];
		if($isadmin){
			$yingxiao_cycle[] = ['name'=>'商品分类','path'=>'CycleCategory/index','authdata'=>'CycleCategory/*'];
			$yingxiao_cycle[] = ['name'=>'分享海报','path'=>'CyclePoster/index','authdata'=>'CyclePoster/*'];
			$yingxiao_cycle[] = ['name'=>'系统设置','path'=>'CycleSet/index','authdata'=>'CycleSet/*'];
		}
		$component_child[] = ['name'=>'周期购','child'=>$yingxiao_cycle];
		
			$yingxiao_zuji = [];
		$yingxiao_zuji[] = ['name'=>'机型管理','path'=>'ZujiProduct/index','authdata'=>'ZujiProduct/*,ZujiProduct/*'];
		$yingxiao_zuji[] = ['name'=>'订单管理','path'=>'ZujiOrder/index','authdata'=>'ZujiOrder/*'];
// 		$yingxiao_zuji[] = ['name'=>'配送管理','path'=>'CycleOrder/cycle_list&status=1','authdata'=>'CycleOrder/*'];
// 		$yingxiao_zuji[] = ['name'=>'评价管理','path'=>'CycleComment/index','authdata'=>'CycleComment/*'];
		if($isadmin){
			$yingxiao_zuji[] = ['name'=>'商品分类','path'=>'ZujiCategory/index','authdata'=>'ZujiCategory/*'];
// 			$yingxiao_zuji[] = ['name'=>'分享海报','path'=>'CyclePoster/index','authdata'=>'CyclePoster/*'];
// 			$yingxiao_zuji[] = ['name'=>'系统设置','path'=>'CycleSet/index','authdata'=>'CycleSet/*'];
		}
		$component_child[] = ['name'=>'租机插件','child'=>$yingxiao_zuji];
		
		if($isadmin){
			$component_mingpian=[];
			$component_mingpian[] = ['name'=>'名片列表','path'=>'Mingpian/index','authdata'=>'Mingpian/*'];
			$component_mingpian[] = ['name'=>'系统设置','path'=>'Mingpian/set','authdata'=>'Mingpian/*'];
			$component_child[] = ['name'=>'名片','child'=>$component_mingpian];
		}

        $cashier_child = [];
        $cashier_child[] = ['name' => '收银设置', 'path' => 'Cashier/index', 'authdata' => 'Cashier/*'];
        $cashier_child[] = ['name' => '收款渠道', 'path' => 'PayChannel/index', 'authdata' => 'PayChannel/*'];
        $cashier_child[] = ['name' => '收银订单', 'path' => 'CashierOrder/index', 'authdata' => 'CashierOrder/*'];
		$cashier_child[] = ['name' => '订单统计', 'path' => 'CashierOrder/tongji', 'authdata' => 'CashierOrder/*'];
		$cashier_child[] = ['name' => '交班记录', 'path' => 'Cashier/shiftlog', 'authdata' => 'Cashier/*'];
		$cashier_child[] = ['name' => '充值卡管理', 'path' => 'RechargeCard/index', 'authdata' => 'RechargeCard/*'];
		$cashier_child[] = ['name' => '等级卡管理', 'path' => 'LevelCard/index', 'authdata' => 'LevelCard/*'];
		$cashier_child[] = ['name' => '员工分类', 'path' => 'StaffCategory/index', 'authdata' => 'StaffCategory/*'];
		$cashier_child[] = ['name' => '员工管理', 'path' => 'Staff/index', 'authdata' => 'Staff/*'];
		$cashier_child[] = ['name' => '提成明细', 'path' => 'StaffCommission/index', 'authdata' => 'StaffCommission/*'];
		$cashier_child[] = ['name' => '提成汇总', 'path' => 'StaffCommission/summary', 'authdata' => 'StaffCommission/*'];
        $component_child[] = ['name'=>'收银台','child'=>$cashier_child];




		$component_child[] = ['name'=>t('自定义表单'),'path'=>'Form/index','authdata'=>'Form/*'];
		
        if($isadmin){

            $lipin_child = [];
            $lipin_child[] = ['name' => '礼品卡', 'path' => 'Lipin/index', 'authdata' => 'Lipin/*'];
            $lipin_child[] = ['name' => '礼品卡兑换码', 'path' => 'Lipin/codelist', 'authdata' => 'Lipin/codelist'];
            $lipin_child[] = ['name'=>'兑换码生成','path'=>'Lipin/makecode','authdata'=>'Lipin/makecode','hide'=>true];
            $lipin_child[] = ['name'=>'兑换码导入','path'=>'Lipin/importexcel','authdata'=>'Lipin/importexcel','hide'=>true];
            $lipin_child[] = ['name'=>'兑换码导出','path'=>'Lipin/codelistexcel','authdata'=>'Lipin/codelistexcel','hide'=>true];
            $lipin_child[] = ['name'=>'兑换码修改状态','path'=>'Lipin/setst','authdata'=>'Lipin/setst','hide'=>true];
            $lipin_child[] = ['name'=>'兑换码删除','path'=>'Lipin/codelistdel','authdata'=>'Lipin/codelistdel','hide'=>true];
            $lipin_child[] = ['name' => '礼品卡分类', 'path' => 'LipinCategory/index', 'authdata' => 'LipinCategory/*'];
            $component_child[] = ['name'=>'礼品卡','child'=>$lipin_child];

			if(in_array('wx',$platform)){
				$component_child[] = ['name'=>'物流助手','path'=>'Miandan/index','authdata'=>'Miandan/*'];
				$component_child[] = ['name'=>'小程序直播','path'=>'Live/index','authdata'=>'Live/*'];
				$component_child[] = ['name'=>'视频号接入','child'=>[
					['name'=>'申请接入','path'=>'Wxvideo/apply','authdata'=>'Wxvideo/*'],
					['name'=>'商家信息','path'=>'Wxvideo/setinfo','authdata'=>'Wxvideo/*'],
					['name'=>'商品管理','path'=>'ShopProduct/index&fromwxvideo=1','authdata'=>'ShopProduct/*,ShopCode/*'],
					['name'=>'订单管理','path'=>'ShopOrder/index&fromwxvideo=1','authdata'=>'ShopOrder/*'],
					['name'=>'退款申请','path'=>'ShopRefundOrder/index&fromwxvideo=1','authdata'=>'ShopRefundOrder/*'],
					['name'=>'我的类目','path'=>'Wxvideo/category','authdata'=>'Wxvideo/*'],
					['name'=>'我的品牌','path'=>'Wxvideo/brand','authdata'=>'Wxvideo/*'],
					['name'=>'同步修复','path'=>'Wxvideo/deliverytongbu','authdata'=>'Wxvideo/*'],
				]];
			}
			//if(in_array('toutiao',$platform)){
			//	$component_child[] = ['name'=>'抖音接入','child'=>[
			//		['name'=>'接入配置','path'=>'DouyinSet/index','authdata'=>'Douyin/*'],
					//['name'=>'商品管理','path'=>'ShopProduct/index&fromdouyin=1','authdata'=>'ShopProduct/*,ShopCode/*'],
			//		['name'=>'商品管理','path'=>'DouyinProduct/index','authdata'=>'DouyinProduct/*'],
			//	]];
			//}
            }
            
            
            
//             	if($isadmin){
// 			$component_jishou = [];
// 			$component_jishou[] = ['name'=>'场次配置','path'=>'Miaosha/changci','authdata'=>'Miaosha/*'];
// // 			$component_jishou[] = ['name'=>'预付款记录','path'=>'Miaosha/yufukuan&id=4','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'寄售商品','path'=>'MiaoshaProduct/index','authdata'=>'MiaoshaProduct/*'];
// 			$component_jishou[] = ['name'=>'卖单列表','path'=>'Miaosha/index','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'买单列表','path'=>'Miaosha/maidan','authdata'=>'Miaosha/*'];
// // 			$component_jishou[] = ['name'=>'付款记录','path'=>'Miaosha/jilu','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'用户列表','path'=>'Miaosha/miaoshauser','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'签约审核','path'=>'Miaosha/miaoshauserqianyue','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'签约列表','path'=>'Miaosha/miaoshauserqianyuelist','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'预约记录','path'=>'Miaosha/changciyuyue','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'委托付款记录','path'=>'Miaosha/rechargelog','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'订单历史列表','path'=>'Miaosha/historyList','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'回收列表','path'=>'Miaosha/huishou','authdata'=>'Miaosha/*'];
// 			$component_jishou[] = ['name'=>'寄售全局配置','path'=>'MiaoshaSet/index','authdata'=>'MiaoshaSet/*'];
			
// 			$component_child[] = ['name'=>'寄售系统','child'=>$component_jishou];
// 		}
		
		
		
        // if($isadmin){
        $component_yunkucun=[];
        $component_yunkucun[] = ['name'=>'系统设置','path'=>'Yunkuncun/sysset','authdata'=>'Yunkuncun/*'];
        $component_yunkucun[] = ['name'=>'添加云库存商品','path'=>'Yunkuncun/addproductlist','authdata'=>'Yunkuncun/*'];
	        $component_yunkucun[] = ['name'=>'云库存商品','path'=>'Yunkuncun/productlist','authdata'=>'Yunkuncun/*'];
	        $component_yunkucun[] = ['name'=>'云仓商品扩展配置','path'=>'Yunkuncun/productext','authdata'=>'Yunkuncun/*'];
	        $component_yunkucun[] = ['name'=>'云库存订单','path'=>'Yunkuncun/yunorder','authdata'=>'Yunkuncun/*'];
	        $component_yunkucun[] = ['name'=>'提货管理','path'=>'Yunkuncun/tihuo','authdata'=>'Yunkuncun/*'];
	        $component_yunkucun[] = ['name'=>'收益记录','path'=>'Yunkuncun/shouyilog','authdata'=>'Yunkuncun/*'];
	        $component_yunkucun[] = ['name'=>'库存列表','path'=>'Yunkuncun/kuncunlist','authdata'=>'Yunkuncun/*'];
        $component_yunkucun[] = ['name'=>'库存详情','path'=>'Yunkuncun/kundetail','authdata'=>'Yunkuncun/*'];
        $component_yunkucun[] = ['name'=>'库存明细','path'=>'Yunkuncun/kunmingxi','authdata'=>'Yunkuncun/*'];
        $component_child[] = ['name'=>'云库存','child'=>$component_yunkucun];

	        // 云仓 V3（独立商品域）单独菜单
	        $component_yunkucun_v3 = [];
					        if($isadmin){
					        	$component_yunkucun_v3[] = ['name'=>'云仓V3系统设置','path'=>'YunkucunGoodsSysset/index','authdata'=>'YunkucunGoodsSysset/*'];
					        	$component_yunkucun_v3[] = ['name'=>'月度津贴活动','path'=>'YunkucunGoodsMonthlyAllowance/index','authdata'=>'YunkucunGoodsMonthlyAllowance/*'];
					        	$component_yunkucun_v3[] = ['name'=>'云仓前端链接','path'=>'YunkucunGoodsLinks/index','authdata'=>'YunkucunGoodsLinks/*'];
					        	$component_yunkucun_v3[] = ['name'=>'云仓公告','path'=>'YunkucunGoodsNotice/index','authdata'=>'YunkucunGoodsNotice/*'];
					        	$component_yunkucun_v3[] = ['name'=>'云仓赠品规则','path'=>'YunkucunGoodsPromoGift/index','authdata'=>'YunkucunGoodsPromoGift/*'];
					            $component_yunkucun_v3[] = ['name'=>'云仓保证金','path'=>'YunkucunGoodsDeposit/index','authdata'=>'YunkucunGoodsDeposit/*'];
					        	$component_yunkucun_v3[] = ['name'=>'精英排行榜','path'=>'YunkucunGoodsRanking/index','authdata'=>'YunkucunGoodsRanking/*'];
					        }
					        $component_yunkucun_v3[] = ['name'=>'云仓商品库(独立)','path'=>'YunkucunGoods/index','authdata'=>'YunkucunGoods/*'];
						        $component_yunkucun_v3[] = ['name'=>'云仓补货单','path'=>'YunkucunGoodsOrder/replenish','authdata'=>'YunkucunGoodsOrder/*'];
						        $component_yunkucun_v3[] = ['name'=>'云仓升级记录','path'=>'YunkucunGoodsOrder/upgrade','authdata'=>'YunkucunGoodsOrder/*'];
						        $component_yunkucun_v3[] = ['name'=>'云仓提货单','path'=>'YunkucunGoodsOrder/delivery','authdata'=>'YunkucunGoodsOrder/*'];
						        $component_yunkucun_v3[] = ['name'=>'云仓发货统计','path'=>'YunkucunGoodsOrder/deliveryStat','authdata'=>'YunkucunGoodsOrder/*'];
				        if($isadmin){
				            // $component_yunkucun_v3[] = ['name'=>'云仓代发利润','path'=>'YunkucunGoodsOrder/profitLog','authdata'=>'YunkucunGoodsOrder/*'];
				            // $component_yunkucun_v3[] = ['name'=>'云仓代发打款记录','path'=>'YunkucunGoodsOrder/profitMoneyLog','authdata'=>'YunkucunGoodsOrder/*'];
			        }
		        if($isadmin){
		            // $component_yunkucun_v3[] = ['name'=>'总部待发货','path'=>'YunkucunGoodsOrder/deliveryHq','authdata'=>'YunkucunGoodsOrder/*'];
		        }
		        if($isadmin){
		        	// $component_yunkucun_v3[] = ['name'=>'云仓换货单','path'=>'YunkucunGoodsExchange/index','authdata'=>'YunkucunGoodsExchange/*'];
		        }
		        if($isadmin){
		        	$component_yunkucun_v3[] = ['name'=>'云仓小店海报','path'=>'YunkucunGoodsShopPoster/index','authdata'=>'YunkucunGoodsShopPoster/*'];
		        	$component_yunkucun_v3[] = ['name'=>'云仓授权证书','path'=>'YunkucunGoodsCertPoster/index','authdata'=>'YunkucunGoodsCertPoster/*'];
		            $component_yunkucun_v3[] = ['name'=>'代理商库存','path'=>'YunkucunGoodsStock/index','authdata'=>'YunkucunGoodsStock/*'];
		            $component_yunkucun_v3[] = ['name'=>'库存流水','path'=>'YunkucunGoodsStockLedger/index','authdata'=>'YunkucunGoodsStockLedger/*'];
		            $component_yunkucun_v3[] = ['name'=>'资金流水','path'=>'YunkucunGoodsMoneylog/index','authdata'=>'YunkucunGoodsMoneylog/*'];
		            $component_yunkucun_v3[] = ['name'=>'业绩统计','path'=>'YunkucunGoodsPerformance/index','authdata'=>'YunkucunGoodsPerformance/*'];
		        }
	        $component_child[] = ['name'=>'云仓V3','child'=>$component_yunkucun_v3];
	        
	        // }
        $component_yunkucun=[];
        if($isadmin){
            $component_yunkucun[] = ['name'=>'系统设置','path'=>'Paidui/sysset','authdata'=>'Paidui/*'];
            $component_yunkucun[] = ['name'=>'排队池管理','path'=>'Paidui/poolList','authdata'=>'Paidui/*'];
            $component_yunkucun[] = ['name'=>'排队池流水','path'=>'Paidui/poolLogs','authdata'=>'Paidui/*'];
            $component_yunkucun[] = ['name'=>'买单比例设置','path'=>'Paidui/sysset2','authdata'=>'Paidui/*'];
            $component_yunkucun[] = ['name'=>'分期管理','path'=>'Paidui/periods','authdata'=>'Paidui/*'];
            $component_yunkucun[] = ['name'=>'分期配置','path'=>'Paidui/periodsConfig','authdata'=>'Paidui/*'];
			$component_yunkucun[] = ['name'=>'自动分红管理','path'=>'PaiduiAutoTask/index','authdata'=>'PaiduiAutoTask/*'];
            $component_yunkucun[] = ['name'=>'分红日志','path'=>'Paidui/distributionLogs','authdata'=>'Paidui/*'];
            $component_yunkucun[] = ['name'=>'详细分红记录','path'=>'Paidui/distributionDetails','authdata'=>'Paidui/*'];
            $component_yunkucun[] = ['name'=>'免单分红设置','path'=>'Paiduifenhong/index','authdata'=>'Paiduifenhong/*'];
            $component_yunkucun[] = ['name'=>'免单分红明细','path'=>'Paiduifenhong/fenhonglist','authdata'=>'Paiduifenhong/*'];
        }else{
            $component_yunkucun[] = ['name'=>'买单比例设置','path'=>'Paidui/sysset2','authdata'=>'Paidui/*'];
            $component_yunkucun[] = ['name'=>'排队记录','path'=>'Paidui/paiduilist','authdata'=>'Paidui/*'];
        }
        $component_yunkucun[] = ['name'=>'排队列表','path'=>'Paidui/paiduilist','authdata'=>'Paidui/*'];
        $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        $component_child[] = ['name'=>'排队免单','child'=>$component_yunkucun];
        
        // 排队领红包菜单
        $component_hongbao = [];
        if($isadmin){
            $component_hongbao[] = ['name'=>'红包池统计','path'=>'PaiduiHongbao/index','authdata'=>'PaiduiHongbao/*'];
            $component_hongbao[] = ['name'=>'活动管理','path'=>'PaiduiHongbao/activityList','authdata'=>'PaiduiHongbao/*'];
            $component_hongbao[] = ['name'=>'领取记录','path'=>'PaiduiHongbao/receiveList','authdata'=>'PaiduiHongbao/*'];
            $component_hongbao[] = ['name'=>'红包池记录','path'=>'PaiduiHongbao/poolLog','authdata'=>'PaiduiHongbao/*'];
        }else{
            $component_hongbao[] = ['name'=>'红包池统计','path'=>'PaiduiHongbao/index','authdata'=>'PaiduiHongbao/*'];
            $component_hongbao[] = ['name'=>'活动管理','path'=>'PaiduiHongbao/activityList','authdata'=>'PaiduiHongbao/*'];
            $component_hongbao[] = ['name'=>'领取记录','path'=>'PaiduiHongbao/receiveList','authdata'=>'PaiduiHongbao/*'];
        }
        if(!empty($component_hongbao)){
            $component_child[] = ['name'=>'排队领红包','child'=>$component_hongbao];
        }
        
        $component_yunkucun=[];
        if($isadmin){
			$component_yunkucun[] = ['name'=>'系统设置','path'=>'Liandong/sysset','authdata'=>'Liandong/*'];
			$component_yunkucun[] = ['name'=>'脱离记录','path'=>'Liandong/tuolilog','authdata'=>'Paidui/*'];
			$component_yunkucun[] = ['name'=>'滑落记录','path'=>'Liandong/hualalog','authdata'=>'Liandong/*']; // 新增滑落记录菜单
			$component_yunkucun[] = ['name'=>'联动脱离记录','path'=>'Liandong/liandonglog','authdata'=>'Liandong/*'];
			$component_yunkucun[] = ['name'=>'回归记录','path'=>'Liandong/huiguilog','authdata'=>'Paidui/*'];
			$component_yunkucun[] = ['name'=>'扶持金明细','path'=>'Liandong/dongjienum','authdata'=>'Paidui/*'];
		}
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>'联动2+1','child'=>$component_yunkucun];
        
        $component_yunkucun=[];
        if($isadmin){
            $component_yunkucun[] = ['name'=>'系统设置','path'=>'Yihuo/sysset','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'拓展员列表','path'=>'Yihuo/tuozhanyuan','authdata'=>'Yihuo/*'];
			$component_yunkucun[] = ['name'=>'拓展员申请','path'=>'Yihuo/tuozhanyuanapply','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'城市统计','path'=>'Yihuo/citytongji','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'拓展员分类','path'=>'Yihuo/tuozhanyuancate','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'商户信息','path'=>'Yihuo/business','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'拓展费记录','path'=>'Yihuo/tuozhanfeire','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'拓展费明细','path'=>'Yihuo/tuozhanfeilog','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'商家奖励记录','path'=>'Yihuo/businessRewardLog','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'额度明细','path'=>'Yihuo/edulog','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'奖励梯度设置','path'=>'Yihuo/rewardLevelSet','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'奖励梯度列表','path'=>'Yihuo/rewardLevelList','authdata'=>'Yihuo/*'];
            $component_yunkucun[] = ['name'=>'奖励发放记录','path'=>'Yihuo/rewardLog','authdata'=>'Yihuo/*'];

        }
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>'拓展员','child'=>$component_yunkucun];
        
        // 城市代理菜单已迁移为插件（app/plugin/cityagent），由 plugin.json 注册到 admin_plugin_menu，不再在此硬编码

        // 排单模块
        $component_paidan = [];
        if($isadmin){
            $component_paidan[] = ['name'=>'排单配置','path'=>'Paidan/index','authdata'=>'Paidan/*'];
            $component_paidan[] = ['name'=>'点位管理','path'=>'Paidan/positionList','authdata'=>'Paidan/*'];
            $component_paidan[] = ['name'=>'奖励记录','path'=>'Paidan/rewardList','authdata'=>'Paidan/*'];
        }
        $component_child[] = ['name'=>'排单模块','child'=>$component_paidan];
        
           $component_tuozhancrm=[];
        if($isadmin){
            $component_tuozhancrm[] = ['name'=>'系统设置','path'=>'Tuozhancrm/sysset','authdata'=>'Tuozhancrm/*'];
            $component_tuozhancrm[] = ['name'=>'公海列表','path'=>'Tuozhancrm/tuozhangonghai','authdata'=>'Tuozhancrm/*'];
            $component_tuozhancrm[] = ['name'=>'商机列表','path'=>'Tuozhancrm/businessOpportunityList','authdata'=>'Tuozhancrm/*'];
            $component_tuozhancrm[] = ['name'=>'跟进记录','path'=>'Tuozhancrm/followUpList','authdata'=>'Tuozhancrm/*'];
            $component_tuozhancrm[] = ['name'=>'商机分类','path'=>'Tuozhancrm/shangjifenlei','authdata'=>'Tuozhancrm/*'];
            $component_tuozhancrm[] = ['name'=>'意向度分类','path'=>'Tuozhancrm/intentionCategory','authdata'=>'Tuozhancrm/*'];
           // $component_tuozhancrm[] = ['name' => '报告与统计', 'path' => 'Tuozhancrm/report', 'authdata' => 'Tuozhancrm/*'];

        }
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>'拓展员CRM','child'=>$component_tuozhancrm];
        
        $salescrm_plugin_installed = false;
        try{
            $salescrm_plugin_installed = Db::name('admin_plugin_menu')
                ->where('aid', $aid)
                ->where('plugin_name', 'salescrm')
                ->count() > 0;
        }catch(\Throwable $e){
            $salescrm_plugin_installed = false;
        }
        if(!$salescrm_plugin_installed){
            // 销售CRM菜单
            $component_salescrm=[];
            if($isadmin){
                $component_salescrm[] = ['name'=>'系统设置','path'=>'Salescrm/sysset','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'导航管理','path'=>'Salescrm/navIndex','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'公海','path'=>'Salescrm/gonghaiList','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'线索管理','path'=>'Salescrm/leadsList','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'客户管理','path'=>'Salescrm/customerList','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'产品/服务','path'=>'Salescrm/itemList','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'流程模板','path'=>'Salescrm/flowList','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'跟进项目','path'=>'Salescrm/projectList','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'标签管理','path'=>'Salescrm/tagList','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'销售雷达','path'=>'Salescrm/radarList','authdata'=>'Salescrm/*'];
                $component_salescrm[] = ['name'=>'文章管理','path'=>'SalescrmArticle/index','authdata'=>'SalescrmArticle/*'];
                $component_salescrm[] = ['name'=>'文章分类','path'=>'SalescrmArticleCategory/index','authdata'=>'SalescrmArticleCategory/*'];
                $component_salescrm[] = ['name'=>'朋友圈内容','path'=>'SalescrmCircle/index','authdata'=>'SalescrmCircle/*'];
                $component_salescrm[] = ['name'=>'朋友圈分类','path'=>'SalescrmCircleCategory/index','authdata'=>'SalescrmCircleCategory/*'];
                $component_salescrm[] = ['name'=>'朋友圈评论','path'=>'SalescrmCirclePinglun/index','authdata'=>'SalescrmCirclePinglun/*'];
                $component_salescrm[] = ['name'=>'朋友圈海报','path'=>'SalescrmCirclePoster/index','authdata'=>'SalescrmCirclePoster/*'];
            }
            if(!empty($component_salescrm)){
                $component_child[] = ['name'=>'销售CRM','child'=>$component_salescrm];
            }
        }
        
        
             $component_yunkucun=[];
        if($isadmin){
          	 $component_yunkucun[] = ['name'=>'积分池配置','path'=>'Backstage/setjifenchi','authdata'=>'Backstage/setjifenchi'];
          	 	 $component_yunkucun[] = ['name'=>'静态积分明细','path'=>'Score/scoreloga','authdata'=>'Score/*'];
			 $component_yunkucun[] = ['name'=>'动态积分明细','path'=>'Score/scorelogb','authdata'=>'Score/*'];
        }
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>'积分池模式','child'=>$component_yunkucun];
        
        
           
		$component_yunkucun=[];
        if($isadmin){
           $component_yunkucun[] = ['name'=>'九星设置','path'=>'Backstage/setjiuxing','authdata'=>'Backstage/setjiuxing'];
          $component_yunkucun[] = ['name'=>t('接点人').'关系图','path'=>'Member/jieidancharts','authdata'=>'Member/jieidancharts'];
          // 接点人管理功能
          $component_yunkucun[] = ['name'=>'接点记录','path'=>'JiedianManage/record','authdata'=>'JiedianManage/record'];
          $component_yunkucun[] = ['name'=>'修改记录','path'=>'JiedianManage/modifylog','authdata'=>'JiedianManage/modifylog'];
          $component_yunkucun[] = ['name'=>'审核管理','path'=>'JiedianManage/audit','authdata'=>'JiedianManage/audit'];
                         // 接点奖励管理功能
               $component_yunkucun[] = ['name'=>'接点奖励设置','path'=>'JiedianReward/index','authdata'=>'JiedianReward/*'];
               $component_yunkucun[] = ['name'=>'接点奖励记录','path'=>'JiedianReward/record','authdata'=>'JiedianReward/*'];
               // 市场绩效薪酬管理功能
               $component_yunkucun[] = ['name'=>'市场绩效薪酬设置','path'=>'MarketPerformanceReward/index','authdata'=>'MarketPerformanceReward/*'];
               $component_yunkucun[] = ['name'=>'市场绩效薪酬记录','path'=>'MarketPerformanceReward/record','authdata'=>'MarketPerformanceReward/*'];
               // 接点团队比例奖励管理功能
               $component_yunkucun[] = ['name'=>'接点团队比例奖励','path'=>'JiedianTeamRatio/index','authdata'=>'JiedianTeamRatio/*'];
               $component_yunkucun[] = ['name'=>'比例设置','path'=>'JiedianTeamRatio/settings','authdata'=>'JiedianTeamRatio/*'];
               $component_yunkucun[] = ['name'=>'奖励明细','path'=>'JiedianTeamRatio/records','authdata'=>'JiedianTeamRatio/*'];
               $component_yunkucun[] = ['name'=>'系统设置','path'=>'JiedianTeamRatio/sysset','authdata'=>'JiedianTeamRatio/*'];
        }
        
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>'九星公排','child'=>$component_yunkucun];
        
                   $component_yunkucun=[];
        if($isadmin){
           $component_yunkucun[] = ['name'=>'七星设置','path'=>'Backstage/setqixing','authdata'=>'Backstage/setjiuxing'];
           	 $component_yunkucun[] = ['name'=>t('虚拟账号').'列表','path'=>'Member/ziindex','authdata'=>'Member/index,Member/excel,Member/excel,Member/importexcel,Member/getplatform,Member/edit,Member/save,Member/del,Member/getcarddetail,Member/charts,Member/setst'];
              $component_yunkucun[] = ['name'=>t('会员公排').'关系图','path'=>'Member/gongpaicharts','authdata'=>'Member/gongpaicharts'];
            	 $component_yunkucun[] = ['name'=>t('虚拟账号积分').'明细','path'=>'Score/scoreduihuanfen','authdata'=>'Score/*'];
           
        }
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>'七星波卡','child'=>$component_yunkucun];
        
        
        
        $component_yunfenhong=[];
        if($isadmin){
        $component_yunfenhong[] = ['name'=>'分红等级设置','path'=>'Yuefenhong/index','authdata'=>'Yuefenhong/*'];
        $component_yunfenhong[] = ['name'=>'分红记录','path'=>'Yuefenhong/fenhonglist','authdata'=>'Yuefenhong/*'];
           
        }
        
        $component_child[] = ['name'=>'月分红模式','child'=>$component_yunfenhong];
        
              
        $component_zhoufenhong=[];
        if($isadmin){
        $component_zhoufenhong[] = ['name'=>'周分红配置','path'=>'Zhoufenhong/index','authdata'=>'Zhoufenhong/*'];
        $component_zhoufenhong[] = ['name'=>'分红记录','path'=>'Zhoufenhong/loglist','authdata'=>'Zhoufenhong/*'];
           
        }
        
        $component_child[] = ['name'=>'周分红模式','child'=>$component_zhoufenhong];
        
              
        $component_jietikaohe=[];
        if($isadmin){
        $component_jietikaohe[] = ['name'=>'分红等级设置','path'=>'Jietikaohe/index','authdata'=>'Jietikaohe/*'];
        $component_jietikaohe[] = ['name'=>'分红记录','path'=>'Jietikaohe/fenhonglist','authdata'=>'Jietikaohe/*'];
        $component_jietikaohe[] = ['name'=>'阶梯考核设置','path'=>'Jietikaohe/set','authdata'=>'Jietikaohe/*'];
           
        }
        
        $component_child[] = ['name'=>'阶梯考核模式','child'=>$component_jietikaohe];
        
        $component_yuejieset=[];
        if($isadmin){
        $component_yuejieset[] = ['name'=>'系统设置','path'=>'Yuejieset/index','authdata'=>'Yuejieset/*'];
        $component_yuejieset[] = ['name'=>'月结用户','path'=>'Yuejieset/userlist','authdata'=>'Yuejieset/*'];
		$component_yuejieset[] = ['name'=>'月结订单','path'=>'Yuejieset/orderlist','authdata'=>'Yuejieset/*'];
		$component_yuejieset[] = ['name'=>'月结明细','path'=>'Yuejieset/arrearslog','authdata'=>'Yuejieset/*'];
           
        }
        
        $component_child[] = ['name'=>'月结中心','child'=>$component_yuejieset];
        
        
       $component_kucunjinxiaocun = [];

        // 添加主菜单和各子菜单项到 $component_kucunjinxiaocun 数组
        $component_kucunjinxiaocun[] = ['name' => '系统设置', 'path' => 'Kucunjinxiaocun/index', 'authdata' => 'Kucunjinxiaocun/*'];
        $component_kucunjinxiaocun[] = ['name' => '商品管理', 'path' => 'ProductManagement/index', 'authdata' => 'ProductManagement/*'];
        $component_kucunjinxiaocun[] = ['name' => '订单管理', 'path' => 'OrderManagement/index', 'authdata' => 'OrderManagement/*'];
        $component_kucunjinxiaocun[] = ['name' => '订单入库', 'path' => 'OrderManagement/inbound', 'authdata' => 'OrderManagement/inbound'];
        $component_kucunjinxiaocun[] = ['name' => '订单配单', 'path' => 'OrderManagement/dispatch', 'authdata' => 'OrderManagement/dispatch'];
        $component_kucunjinxiaocun[] = ['name' => '库存预警', 'path' => 'InventoryWarning/index', 'authdata' => 'InventoryWarning/*'];
        $component_kucunjinxiaocun[] = ['name' => '出入库管理', 'path' => 'InventoryInOut/index', 'authdata' => 'InventoryInOut/*'];
        $component_kucunjinxiaocun[] = ['name' => '新建出库单', 'path' => 'InventoryInOut/outbound', 'authdata' => 'InventoryInOut/outbound'];
        $component_kucunjinxiaocun[] = ['name' => '新建入库单', 'path' => 'InventoryInOut/inbound', 'authdata' => 'InventoryInOut/inbound'];
        $component_kucunjinxiaocun[] = ['name' => '拆单管理', 'path' => 'SplitManagement/index', 'authdata' => 'SplitManagement/*'];
        $component_kucunjinxiaocun[] = ['name' => '新建拆单', 'path' => 'SplitManagement/create', 'authdata' => 'SplitManagement/create'];
        $component_kucunjinxiaocun[] = ['name' => '组合单管理', 'path' => 'CombinationManagement/index', 'authdata' => 'CombinationManagement/*'];
        $component_kucunjinxiaocun[] = ['name' => '新建组合单', 'path' => 'CombinationManagement/create', 'authdata' => 'CombinationManagement/create'];
        

        $component_child[] = ['name'=>'库存进销存','child'=>$component_kucunjinxiaocun];
        
         
        $component_renzhengset=[];
        if($isadmin){
        $component_renzhengset[] = ['name'=>'认证设置','path'=>'Renzhengset/index','authdata'=>'Renzhengset/*'];
        $component_renzhengset[] = ['name'=>'签约审核','path'=>'Renzhengset/miaoshauserqianyue','authdata'=>'Renzhengset/*'];
		$component_renzhengset[] = ['name'=>'签约列表','path'=>'Renzhengset/miaoshauserqianyuelist','authdata'=>'Renzhengset/*'];
           
        }
        
        $component_child[] = ['name'=>'认证中心','child'=>$component_renzhengset];
        
        // 手动订单分佣插件
        $component_manual_order = [];
        if($isadmin){
            $component_manual_order[] = ['name'=>'订单管理','path'=>'ManualOrder/index','authdata'=>'ManualOrder/*'];
            $component_manual_order[] = ['name'=>'冻结记录','path'=>'ManualOrder/frozenList','authdata'=>'ManualOrder/*'];
        }
        $component_child[] = ['name'=>'手动订单分佣','child'=>$component_manual_order];
        
         $component_ep[] = ['name'=>'EP列表','path'=>'ShouMai/index','authdata'=>'ShouMai/*'];
        $component_ep[] = ['name'=>'通道列表','path'=>'ShouMai/corridorIndex','authdata'=>'ShouMai/*'];
        $component_ep[] = ['name'=>'售卖设置','path'=>'ShouMai/saleSettings','authdata'=>'ShouMai/*'];
        $component_child[] = ['name'=>'现金EP','child' => $component_ep];
         
        
                   $component_yunkucun=[];
				   if($isadmin){
					$component_yunkucun[] = ['name'=>'分红点设置','path'=>'Fenhongdian/setting','authdata'=>'Fenhongdian/setting'];
					$component_yunkucun[] = ['name'=>'手动分红','path'=>'Fenhongdian/manual','authdata'=>'Fenhongdian/manual'];
					$component_yunkucun[] = ['name'=>'自动分红设置','path'=>'Fenhongdian/autosetting','authdata'=>'Fenhongdian/autosetting'];
					$component_yunkucun[] = ['name'=>'自动分红日志','path'=>'Fenhongdian/autologs','authdata'=>'Fenhongdian/autologs'];
					$component_yunkucun[] = ['name'=>'分红点日志','path'=>'Fenhongdian/logs','authdata'=>'Fenhongdian/logs'];
					$component_yunkucun[] = ['name'=>'奖励记录','path'=>'Fenhongdian/rewardlogs','authdata'=>'Fenhongdian/rewardlogs'];
					$component_yunkucun[] = ['name'=>t('销售分红发放').'记录','path'=>'Commission/sendxsjl','authdata'=>'Commission/*'];
					
				 }
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        
        
        
        $component_child[] = ['name'=>'分红点','child'=>$component_yunkucun];
        
        
                   $component_yunkucun=[];
        if($isadmin){
           $component_yunkucun[] = ['name'=>'消费值设置','path'=>'Backstage/setfenhongdian','authdata'=>'Backstage/setfenhongdian'];
           	$component_yunkucun[] = ['name'=>'消费值明细','path'=>'Score/scorelogxiaofeizhi','authdata'=>'Score/*'];
           	$component_yunkucun[] = ['name'=>'绿积分明细','path'=>'Score/scoreloglv','authdata'=>'Score/*'];
           	$component_yunkucun[] = ['name'=>'打赏明细','path'=>'Score/scorelogdashang','authdata'=>'Score/*'];
           
        }
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>'天天打赏','child'=>$component_yunkucun];
        
                        $component_yunkucun=[];
        if($isadmin){
           $component_yunkucun[] = ['name'=>'创业值设置','path'=>'Backstage/setfenhongdian','authdata'=>'Backstage/setfenhongdian'];
           $component_yunkucun[] = ['name'=>t('创业值').'明细','path'=>'Money/chuangyelog','authdata'=>'Money/chuangyelog,Money/moneylog,Money/moneylogexcel,Money/moneylogsetst,Money/moneylogdel'];
           
        }
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>'创业值','child'=>$component_yunkucun];
        
        
                           $component_yunkucun=[];
        if($isadmin){
           $component_yunkucun[] = ['name'=>t('贡献值').'设置','path'=>'Gongxianzhiset/index','authdata'=>'Gongxianzhiset/*'];
             $component_yunkucun[] = ['name'=>t('贡献值').'明细','path'=>'Gxz/index2','authdata'=>'Gxz/*'];
            $component_yunkucun[] = ['name'=>t('贡献值').'分红','path'=>'Gxz/index','authdata'=>'Gxz/*'];
           
        }
        
        // $component_yunkucun[] = ['name'=>'免单金额明细','path'=>'Paidui/score3mingxi','authdata'=>'Paidui/*'];
        
        $component_child[] = ['name'=>t('贡献值'),'child'=>$component_yunkucun];
        
        
        
        // 添加黄积分菜单
        $component_scorehuang = [];
        if($isadmin){
            $component_scorehuang[] = ['name'=>t('黄积分').'设置','path'=>'ScoreHuang/index','authdata'=>'ScoreHuang/*'];
            $component_scorehuang[] = ['name'=>t('黄积分').'记录','path'=>'ScoreHuang/record','authdata'=>'ScoreHuang/record'];
            $component_scorehuang[] = ['name'=>t('黄积分').'明细','path'=>'ScoreHuang/scorelog','authdata'=>'ScoreHuang/scorelog,ScoreHuang/scorelogexcel,ScoreHuang/scorelogdel'];
            $component_scorehuang[] = ['name'=>t('黄积分').'提现记录','path'=>'ScoreHuang/withdrawlog','authdata'=>'ScoreHuang/*'];
        }
        $component_child[] = ['name'=>t('黄积分'),'child'=>$component_scorehuang];
        
        // 添加周期服务菜单
        $component_periodicservice = [];
        $component_periodicservice[] = ['name'=>'周期服务项目','path'=>'PeriodicServiceProduct/index','authdata'=>'PeriodicServiceProduct/*']; // 新增：管理周期服务项目定义
        $component_periodicservice[] = ['name'=>'周期服务单','path'=>'PeriodicServiceOrder/index','authdata'=>'PeriodicServiceOrder/*'];
        $component_periodicservice[] = ['name'=>'服务工单','path'=>'PeriodicServiceOrder/stageIndex','authdata'=>'PeriodicServiceStage/*']; // 修改这里的 PeriodicServiceStage 为 PeriodicServiceOrder
        $component_child[] = ['name'=>'周期服务','child'=>$component_periodicservice];
        
        // 添加消息通知系统菜单
        $component_message_notify = [];
        if($isadmin){
            $component_message_notify[] = ['name'=>'消息通知管理','path'=>'MessageNotify/index','authdata'=>'MessageNotify/*'];
            $component_message_notify[] = ['name'=>'站内通知管理','path'=>'MessageNotify/index?type=site','authdata'=>'MessageNotify/*'];
            $component_message_notify[] = ['name'=>'消息分类管理','path'=>'MessageCategory/index','authdata'=>'MessageCategory/*'];
            $component_message_notify[] = ['name'=>'评论管理','path'=>'MessagePinglun/index','authdata'=>'MessagePinglun/*'];
            $component_message_notify[] = ['name'=>'系统设置','path'=>'MessageSet/index','authdata'=>'MessageSet/*'];
            $component_message_notify[] = ['name'=>'推送记录','path'=>'MessagePushRecord/index','authdata'=>'MessagePushRecord/*'];
        }else{
            $component_message_notify[] = ['name'=>'消息通知管理','path'=>'MessageNotify/index','authdata'=>'MessageNotify/*'];
            $component_message_notify[] = ['name'=>'站内通知管理','path'=>'MessageNotify/index?type=site','authdata'=>'MessageNotify/*'];
            $component_message_notify[] = ['name'=>'消息分类管理','path'=>'MessageCategory/index','authdata'=>'MessageCategory/*'];
            $component_message_notify[] = ['name'=>'评论管理','path'=>'MessagePinglun/index','authdata'=>'MessagePinglun/*'];
            $component_message_notify[] = ['name'=>'系统设置','path'=>'MessageSet/index','authdata'=>'MessageSet/*'];
            $component_message_notify[] = ['name'=>'推送记录','path'=>'MessagePushRecord/index','authdata'=>'MessagePushRecord/*'];
        }
        $component_child[] = ['name'=>'消息通知','child'=>$component_message_notify];
        
        // 添加智能诊疗模块菜单（2025-01-17 扩展支持面诊和综合诊疗）
        $component_shezhen = [];
        if($isadmin){
            $component_shezhen[] = ['name'=>'系统设置','path'=>'SheZhenSet/index','authdata'=>'SheZhenSet/*'];
            $component_shezhen[] = ['name'=>'检测分组','path'=>'SheZhenGroup/index','authdata'=>'SheZhenGroup/*'];
            $component_shezhen[] = ['name'=>'专家管理','path'=>'SheZhenExpert/index','authdata'=>'SheZhenExpert/*'];
            $component_shezhen[] = ['name'=>'客户列表','path'=>'SheZhenCustomer/index','authdata'=>'SheZhenCustomer/*'];
            $component_shezhen[] = ['name'=>'会员次数管理','path'=>'SheZhen/memberQuota','authdata'=>'SheZhen/memberQuota,SheZhen/adjustMemberDetectTimes','vue_path'=>''];
            $component_shezhen[] = ['name'=>'舌诊记录','path'=>'SheZhen/index','authdata'=>'SheZhen/*'];
            $component_shezhen[] = ['name'=>'面诊记录','path'=>'SheZhen/faceIndex','authdata'=>'SheZhen/faceIndex'];
            $component_shezhen[] = ['name'=>'综合诊疗记录','path'=>'SheZhen/comprehensiveIndex','authdata'=>'SheZhen/comprehensiveIndex'];
            $component_shezhen[] = ['name'=>'诊疗报告','path'=>'SheZhen/report','authdata'=>'SheZhen/report'];
            $component_shezhen[] = ['name'=>'商品推荐管理','path'=>'SheZhen/recommendManager','authdata'=>'SheZhen/recommendManager'];
            $component_shezhen[] = ['name'=>'商品分类管理','path'=>'SheZhen/categoryManager','authdata'=>'SheZhen/categoryManager'];
            $component_shezhen[] = ['name'=>'舌型症状管理','path'=>'TongueSymptoms/index','authdata'=>'TongueSymptoms/*'];
            $component_shezhen[] = ['name'=>'数据统计','path'=>'SheZhen/statistics','authdata'=>'SheZhen/statistics'];
			$component_shezhen[] = ['name'=>'诊疗次数充值记录','path'=>'MianzhenRecharge/index','authdata'=>'MianzhenRecharge/*'];
        }else{
            $component_shezhen[] = ['name'=>'客户列表','path'=>'SheZhenCustomer/index','authdata'=>'SheZhenCustomer/*'];
            $component_shezhen[] = ['name'=>'会员次数管理','path'=>'SheZhen/memberQuota','authdata'=>'SheZhen/memberQuota,SheZhen/adjustMemberDetectTimes','vue_path'=>''];
            $component_shezhen[] = ['name'=>'舌诊记录','path'=>'SheZhen/index','authdata'=>'SheZhen/*'];
            $component_shezhen[] = ['name'=>'面诊记录','path'=>'SheZhen/faceIndex','authdata'=>'SheZhen/faceIndex'];
            $component_shezhen[] = ['name'=>'综合诊疗记录','path'=>'SheZhen/comprehensiveIndex','authdata'=>'SheZhen/comprehensiveIndex'];
            $component_shezhen[] = ['name'=>'诊疗报告','path'=>'SheZhen/report','authdata'=>'SheZhen/report'];
        }
                    $component_child[] = ['name'=>'智能诊疗','child'=>$component_shezhen];

            // AI五行管理模块
            $component_wuxing = [];
            if($isadmin){
                $component_wuxing[] = ['name'=>'系统设置','path'=>'WuXingSet/index','authdata'=>'WuXingSet/*'];
                $component_wuxing[] = ['name'=>'测试记录','path'=>'WuXing/index','authdata'=>'WuXing/*'];
                $component_wuxing[] = ['name'=>'商品推荐管理','path'=>'WuXing/recommendManager','authdata'=>'WuXing/recommendManager'];
                $component_wuxing[] = ['name'=>'数据统计','path'=>'WuXing/statistics','authdata'=>'WuXing/statistics'];
            }else{
                $component_wuxing[] = ['name'=>'测试记录','path'=>'WuXing/index','authdata'=>'WuXing/*'];
            }
            $component_child[] = ['name'=>'AI五行','child'=>$component_wuxing];
 		
 		// 节日管理模块
        $component_holiday = [];
        if($isadmin){
            $component_holiday[] = ['name'=>'节日管理','path'=>'Holiday/index','authdata'=>'Holiday/*'];
            $component_holiday[] = ['name'=>'添加节日','path'=>'Holiday/edit','authdata'=>'Holiday/*'];
        }
        $component_child[] = ['name'=>'节日管理','child'=>$component_holiday];
        
        // 上香供奉许愿模块
        $component_shangxiang = [];
        if($isadmin){
            $component_shangxiang[] = ['name'=>'系统设置','path'=>'Shangxiang/index','authdata'=>'Shangxiang/*'];
            $component_shangxiang[] = ['name'=>'许愿记录管理','path'=>'Shangxiang/wishList','authdata'=>'Shangxiang/*'];
            $component_shangxiang[] = ['name'=>'数据统计','path'=>'Shangxiang/statistics','authdata'=>'Shangxiang/*'];
        }
        $component_child[] = ['name'=>'上香供奉许愿','child'=>$component_shangxiang];
        
        // 嘉联支付商户进件管理模块
        $component_jlpay = [];
        if($isadmin){
            $component_jlpay[] = ['name'=>'商户进件管理','path'=>'JlPayMerchant/index','authdata'=>'JlPayMerchant/*'];
            $component_jlpay[] = ['name'=>'申请状态查询','path'=>'JlPayMerchant/query','authdata'=>'JlPayMerchant/*'];
            $component_jlpay[] = ['name'=>'申请修改','path'=>'JlPayMerchant/modify','authdata'=>'JlPayMerchant/*'];
            $component_jlpay[] = ['name'=>'系统设置','path'=>'JlPayMerchant/set','authdata'=>'JlPayMerchant/*'];
        }else{
            $component_jlpay[] = ['name'=>'商户进件管理','path'=>'JlPayMerchant/index','authdata'=>'JlPayMerchant/*'];
            $component_jlpay[] = ['name'=>'申请状态查询','path'=>'JlPayMerchant/query','authdata'=>'JlPayMerchant/*'];
            $component_jlpay[] = ['name'=>'申请修改','path'=>'JlPayMerchant/modify','authdata'=>'JlPayMerchant/*'];
        }
        $component_child[] = ['name'=>'嘉联支付进件','child'=>$component_jlpay];

        // 中金支付商户进件管理模块
        $component_cpcnpay = [];
        if($isadmin){
            $component_cpcnpay[] = ['name'=>'商户进件管理','path'=>'CpcnPayMerchant/index','authdata'=>'CpcnPayMerchant/*'];
            $component_cpcnpay[] = ['name'=>'新增商户进件','path'=>'CpcnPayMerchant/merchant','authdata'=>'CpcnPayMerchant/*'];
            $component_cpcnpay[] = ['name'=>'进件状态查询','path'=>'CpcnPayMerchant/query','authdata'=>'CpcnPayMerchant/*'];
            $component_cpcnpay[] = ['name'=>'修改记录管理','path'=>'CpcnPayMerchant/modifyLog','authdata'=>'CpcnPayMerchant/*'];
            $component_cpcnpay[] = ['name'=>'商户余额查询','path'=>'MerchantBalanceQuery/index','authdata'=>'MerchantBalanceQuery/*'];
            $component_cpcnpay[] = ['name'=>'账户提现管理','path'=>'CpcnWithdrawal/index','authdata'=>'CpcnWithdrawal/*'];
            $component_cpcnpay[] = ['name'=>'我的余额提现','path'=>'MerchantBalanceWithdrawal/index','authdata'=>'MerchantBalanceWithdrawal/*'];
            $component_cpcnpay[] = ['name'=>'分账记录管理','path'=>'BusinessSplitRecords/index','authdata'=>'BusinessSplitRecords/*'];
            $component_cpcnpay[] = ['name'=>'手动执行分账','path'=>'BusinessSplitRecords/manualSplit','authdata'=>'BusinessSplitRecords/*'];
            $component_cpcnpay[] = ['name'=>'余额支付分账','path'=>'CpcnBalanceSplit/index','authdata'=>'CpcnBalanceSplit/*'];
            $component_cpcnpay[] = ['name'=>'余额分账设置','path'=>'CpcnBalanceSplit/setting','authdata'=>'CpcnBalanceSplit/*'];
            $component_cpcnpay[] = ['name'=>'自由划转','path'=>'CpcnBalanceSplit/transfer','authdata'=>'CpcnBalanceSplit/*'];
            $component_cpcnpay[] = ['name'=>'划转记录','path'=>'CpcnBalanceSplit/transferRecords','authdata'=>'CpcnBalanceSplit/*'];
            $component_cpcnpay[] = ['name'=>'账户注销管理','path'=>'CpcnAccountClose/index','authdata'=>'CpcnAccountClose/*'];
            $component_cpcnpay[] = ['name'=>'系统设置','path'=>'CpcnPayMerchant/set','authdata'=>'CpcnPayMerchant/*'];
        }else{
            $component_cpcnpay[] = ['name'=>'商户进件管理','path'=>'CpcnPayMerchant/index','authdata'=>'CpcnPayMerchant/*'];
            $component_cpcnpay[] = ['name'=>'新增商户进件','path'=>'CpcnPayMerchant/merchant','authdata'=>'CpcnPayMerchant/*'];
            $component_cpcnpay[] = ['name'=>'进件状态查询','path'=>'CpcnPayMerchant/query','authdata'=>'CpcnPayMerchant/*'];
            $component_cpcnpay[] = ['name'=>'修改记录管理','path'=>'CpcnPayMerchant/modifyLog','authdata'=>'CpcnPayMerchant/*'];
            $component_cpcnpay[] = ['name'=>'我的余额提现','path'=>'MerchantBalanceWithdrawal/index','authdata'=>'MerchantBalanceWithdrawal/*'];
            $component_cpcnpay[] = ['name'=>'分账记录管理','path'=>'BusinessSplitRecords/index','authdata'=>'BusinessSplitRecords/*'];
            $component_cpcnpay[] = ['name'=>'手动执行分账','path'=>'BusinessSplitRecords/manualSplit','authdata'=>'BusinessSplitRecords/*'];
            $component_cpcnpay[] = ['name'=>'余额支付分账','path'=>'CpcnBalanceSplit/index','authdata'=>'CpcnBalanceSplit/*'];
            $component_cpcnpay[] = ['name'=>'自由划转','path'=>'CpcnBalanceSplit/transfer','authdata'=>'CpcnBalanceSplit/*'];
            $component_cpcnpay[] = ['name'=>'划转记录','path'=>'CpcnBalanceSplit/transferRecords','authdata'=>'CpcnBalanceSplit/*'];
            $component_cpcnpay[] = ['name'=>'账户注销管理','path'=>'CpcnAccountClose/index','authdata'=>'CpcnAccountClose/*'];
        }
        $component_child[] = ['name'=>'中金支付进件','child'=>$component_cpcnpay];

        // 单笔最大奖励（父级）
        $component_reward_limit = [];
        $component_reward_limit[] = ['name'=>'设置','path'=>'RewardLimitSet/index','authdata'=>'RewardLimitSet/*'];
        $component_reward_limit[] = ['name'=>'流式记录','path'=>'RewardLimitLog/index','authdata'=>'RewardLimitLog/*'];
        $component_child[] = ['name'=>'单笔最大奖励','child'=>$component_reward_limit];

        // 团队限额抽奖菜单
        $component_team_lottery = [];
        $component_team_lottery[] = ['name'=>'设置','path'=>'TeamLotterySet/index','authdata'=>'TeamLotterySet/*'];
        $component_team_lottery[] = ['name'=>'抽奖活动管理','path'=>'TeamLotteryActivity/index','authdata'=>'TeamLotteryActivity/*'];
        $component_team_lottery[] = ['name'=>'抽奖记录','path'=>'TeamLotteryRecord/index','authdata'=>'TeamLotteryRecord/*'];
        $component_team_lottery[] = ['name'=>'团队限额流水记录','path'=>'TeamRewardLimitLog/index','authdata'=>'TeamRewardLimitLog/*'];
        $component_child[] = ['name'=>'团队限额抽奖','child'=>$component_team_lottery];

            if($isadmin){
                $component_daily_jinju=[];
                $component_daily_jinju[] = ['name'=>'大类管理','path'=>'DailyjinjuCategory/index','authdata'=>'DailyjinjuCategory/*'];
                $component_daily_jinju[] = ['name'=>'每日金句','path'=>'DailyjinjuProduct/index','authdata'=>'DailyjinjuProduct/*'];
                $component_daily_jinju[] = ['name'=>'打卡列表','path'=>'DailyjinjuDaka/index','authdata'=>'DailyjinjuDaka/*'];
                $component_daily_jinju[] = ['name'=>'感悟列表','path'=>'DailyjinjuGanwu/index','authdata'=>'DailyjinjuGanwu/*'];
                $component_daily_jinju[] = ['name'=>'解锁记录','path'=>'DailyjinjuUnlockRecord/index','authdata'=>'DailyjinjuUnlockRecord/*'];
                $component_daily_jinju[] = ['name'=>'登录统计','path'=>'DailyjinjuUnlockRecord/loginstat','authdata'=>'DailyjinjuUnlockRecord/*'];
                $component_daily_jinju[] = ['name'=>'学习记录统计','path'=>'DailyjinjuStudyStats/index','authdata'=>'DailyjinjuStudyStats/*'];
                $component_daily_jinju[] = ['name'=>'海报设置','path'=>'DailyjinjuPoster/index','authdata'=>'DailyjinjuPoster/*'];
                $component_child[] = ['name'=>'每日金句','child'=>$component_daily_jinju];
			}
			if ($isadmin){
                $component_child[] = ['name'=>'微信广告','child'=>[
                    ['name'=>'广告列表','path'=>'Wxadvert/index','authdata'=>'Wxadvert/*'],
                    ['name'=>'汇总数据','path'=>'Wxadvert/addata','authdata'=>'Wxadvert/*'],
                    ['name'=>'结算数据','path'=>'Wxadvert/settle','authdata'=>'Wxadvert/*'],
                ]];
                
                // 会员押金保证功能
                $component_deposit[] = ['name'=>'押金管理','path'=>'DepositManage/index','authdata'=>'DepositManage/*'];
                $component_deposit[] = ['name'=>'押金释放规则','path'=>'DepositManage/ruleindex','authdata'=>'DepositManage/*'];
                $component_deposit[] = ['name'=>'押金释放记录','path'=>'DepositManage/logindex','authdata'=>'DepositManage/*'];
                $component_deposit[] = ['name'=>'押金系统设置','path'=>'DepositManage/setting','authdata'=>'DepositManage/*'];
                $component_child[] = ['name'=>'会员押金保证','child'=>$component_deposit];
                
                // 视频激励活动功能
                $component_video_reward = [];
                $component_video_reward[] = ['name'=>'活动管理','path'=>'VideoRewardActivity/index','authdata'=>'VideoRewardActivity/*'];
                $component_video_reward[] = ['name'=>'参与统计','path'=>'VideoRewardActivity/statistics','authdata'=>'VideoRewardActivity/*'];
                $component_child[] = ['name'=>'视频激励活动','child'=>$component_video_reward];
            }

            // 阿里云OCR识别
            $component_aliyun_ocr = [];
            $component_aliyun_ocr[] = ['name'=>'识别记录','path'=>'AliyunOcr/index','authdata'=>'AliyunOcr/*'];
            $component_aliyun_ocr[] = ['name'=>'测试识别','path'=>'AliyunOcr/test','authdata'=>'AliyunOcr/*'];
            $component_aliyun_ocr[] = ['name'=>'系统设置','path'=>'AliyunOcr/set','authdata'=>'AliyunOcr/*'];
            $component_child[] = ['name'=>'阿里云OCR','child'=>$component_aliyun_ocr];

        $menudata['component'] = ['name'=>'扩展','fullname'=>'扩展功能','icon'=>'my-icon my-icon-kuozhan','child'=>$component_child];

        // 我的插件 - 一级菜单，直接跳转到插件列表
        $menudata['plugin'] = ['name'=>'插件','fullname'=>'插件管理','icon'=>'my-icon my-icon-kuozhan','path'=>'MyPlugin/index','authdata'=>'MyPlugin/*'];

			if(getcustom('restaurant') && !self::isPluginMenuEnabled('restaurant', intval($aid))){
				$menudata['restaurant'] = \app\admin\custom\Restaurant::getmenu($isadmin);
			}
			if(getcustom('hotel') && self::tableExists('hotel_set')){
				$text = \app\admin\model\Hotel::gettext(aid);
            $hotel_child = [];
            $hotel_child[] = ['name'=>$text['酒店'].'设置','path'=>'Hotel/index','authdata'=>'Hotel/*'];
			if($isadmin){
			  $hotel_child[] = ['name'=>$text['酒店'].'类型','path'=>'HotelCategory/index','authdata'=>'HotelCategory/*'];
			}
			$hotel_child[] = ['name'=>$text['酒店'].'相册','path'=>'HotelPhotos/index','authdata'=>'HotelPhotos/*'];
            $hotel_child[] = ['name'=>'房型列表1','path'=>'HotelRoom/index','authdata'=>'HotelRoom/*'];
			$hotel_child[] = ['name'=>'房型分组','path'=>'HotelGroup/index','authdata'=>'HotelGroup/*'];
            $hotel_child[] = ['name'=>'房态房价','path'=>'HotelRoomPrices/index','authdata'=>'HotelRoomPrices/*'];
            $hotel_child[] = ['name'=>$text['酒店'].'订单','path'=>'HotelOrder/index','authdata'=>'HotelOrder/*'];
		    $hotel_child[] = ['name'=>'押金列表','path'=>'HotelOrderYajin/index','authdata'=>'HotelOrderYajin/*'];
		    $hotel_child[] = ['name'=>'订单评价','path'=>'HotelComment/index','authdata'=>'HotelComment/*'];
			$hotel_child[] = ['name'=>'优惠券','path'=>'HotelCoupon/index','authdata'=>'HotelCoupon/*'];
			if($isadmin){
				$hotel_child[] = ['name'=>'系统设置','path'=>'HotelSet/index','authdata'=>'HotelSet/*'];
			}
			$menudata['hotel'] = ['name'=>$text['酒店'],'fullname'=>$text['酒店'].'管理','icon'=>'my-icon my-icon-kuozhan','child'=>$hotel_child];
        }
		$jiemian_child = [];
		if(false){}else{
			$jiemian_child[] = ['name'=>'页面设计','path'=>'DesignerPage/index','authdata'=>'DesignerPage/*'];
			$jiemian_child[] = ['name'=>'页面分类','path'=>'DesignerCategory/index','authdata'=>'DesignerCategory/*'];
		}
		 
		if($isadmin){
            $jiemian_child[] = ['name'=>'底部导航','path'=>'DesignerMenu/index','authdata'=>'DesignerMenu/*'];
            $jiemian_child[] = ['name'=>'内页导航','path'=>'DesignerMenu/inner','authdata'=>'DesignerMenu/*'];
            $jiemian_child[] = ['name'=>'页面弹窗','path'=>'DesignerPagePopup/index','authdata'=>'DesignerPagePopup/*','vue_path'=>'designer-page-popup'];
            $jiemian_child[] = ['name'=>'开屏配置','path'=>'DesignerMenu/splash','authdata'=>'DesignerMenu/*'];
            $jiemian_child[] = ['name'=>'开屏管理','path'=>'DesignerMenu/splashmanage','authdata'=>'DesignerMenu/*','hide'=>true];
			$jiemian_child[] = ['name'=>'商品详情','path'=>'DesignerMenuShopdetail/shopdetail','authdata'=>'DesignerMenuShopdetail/*'];
        }else{
            $jiemian_child[] = ['name'=>'底部导航','path'=>'DesignerMenu/menu2','authdata'=>'DesignerMenu/*'];
            $jiemian_child[] = ['name'=>'页面弹窗','path'=>'DesignerPagePopup/index','authdata'=>'DesignerPagePopup/*','vue_path'=>'designer-page-popup'];
            $jiemian_child[] = ['name'=>'开屏配置','path'=>'DesignerMenu/splash','authdata'=>'DesignerMenu/*'];
            $jiemian_child[] = ['name'=>'开屏管理','path'=>'DesignerMenu/splashmanage','authdata'=>'DesignerMenu/*','hide'=>true];
			$jiemian_child[] = ['name'=>'商品详情','path'=>'DesignerMenuShopdetail/shopdetail','authdata'=>'DesignerMenuShopdetail/*'];
        }
		if($isadmin){
        	$jiemian_child[] = ['name'=>'登录页面','path'=>'DesignerLogin/index','authdata'=>'DesignerLogin/*'];
            $jiemian_child[] = ['name'=>'移动端后台','path'=>'DesignerMobile/index','authdata'=>'DesignerMobile/*'];
        }
        $jiemian_child[] = ['name'=>'分享设置','path'=>'DesignerShare/index','authdata'=>'DesignerShare/*'];
        $jiemian_child[] = ['name'=>'加载动效','path'=>'LoadingEffect/index','authdata'=>'LoadingEffect/*','admin_only'=>1,'merchant_visible'=>0,'tuanzhang_visible'=>0];
        $jiemian_child[] = ['name'=>'链接地址','path'=>'DesignerPage/chooseurl','params'=>'/type/geturl','authdata'=>'DesignerPage/chooseurl'];
        
        // 分销设置（多tab页面）
        $jiemian_child[] = ['name'=>'分销设置','path'=>'DistributionSettings/index','authdata'=>'DistributionSettings/*','admin_only'=>1,'merchant_visible'=>0,'tuanzhang_visible'=>0];
        $jiemian_child[] = ['name'=>'氛围用户池','path'=>'DesignerVirtualUser/index','authdata'=>'DesignerVirtualUser/*','vue_path'=>'designer-virtual-user','admin_only'=>1,'merchant_visible'=>0,'tuanzhang_visible'=>0];
        $menudata['jiemian'] = ['name'=>'设计','fullname'=>'界面设计','icon'=>'my-icon my-icon-sheji','child'=>$jiemian_child];

		if($isadmin){
			$pingtai_child = [];
			if(in_array('mp',$platform)){
				$pingtai_child_mp = [];
				$pingtai_child_mp[] = ['name'=>'公众号绑定','path'=>'Binding/index','authdata'=>'Binding/*'];
				$pingtai_child_mp[] = ['name'=>'菜单设置','path'=>'Mpmenu/index','authdata'=>'Mpmenu/*'];
				$pingtai_child_mp[] = ['name'=>'支付设置','path'=>'Mppay/set','authdata'=>'Mppay/*'];
				$pingtai_child_mp[] = ['name'=>'模板消息设置','path'=>'Mptmpl/tmplset','authdata'=>'Mptmpl/*'];
				$pingtai_child_mp[] = ['name'=>'类目模板消息','path'=>'Mptmpl/tmplsetNew','authdata'=>'Mptmpl/tmplsetNew'];
				$pingtai_child_mp[] = ['name'=>'已添加的模板','path'=>'Mptmpl/mytmpl','authdata'=>'Mptmpl/*'];
				$pingtai_child_mp[] = ['name'=>'被关注回复','path'=>'Mpkeyword/subscribe','authdata'=>'Mpkeyword/*'];
				$pingtai_child_mp[] = ['name'=>'关键字回复','path'=>'Mpkeyword/index','authdata'=>'Mpkeyword/*'];
				$pingtai_child_mp[] = ['name'=>'粉丝列表','path'=>'Mpfans/fanslist','authdata'=>'Mpfans/*'];
				$pingtai_child_mp[] = ['name'=>'素材管理','path'=>'Mpfans/sourcelist','authdata'=>'Mpfans/*'];
				$pingtai_child_mp[] = ['name'=>'模板消息群发','path'=>'Mpfans/tmplsend','authdata'=>'Mpfans/*'];
				$pingtai_child_mp[] = ['name'=>'活跃粉丝群发','path'=>'Mpfans/kfmsgsend','authdata'=>'Mpfans/*'];
				$pingtai_child[] = ['name'=>'微信公众号','child'=>$pingtai_child_mp];
				$pingtai_child_mpcard = [];
				$pingtai_child_mpcard[] = ['name'=>'领取记录','path'=>'Membercard/record','authdata'=>'Membercard/record'];
				$pingtai_child_mpcard[] = ['name'=>'会员卡/创建','path'=>'Membercard/index','authdata'=>'Membercard/*'];
                $pingtai_child[] = ['name'=>'微信会员卡','child'=>$pingtai_child_mpcard];
			}
			if(in_array('wx',$platform)){
				$pingtai_child_wx = [];
				$pingtai_child_wx[] = ['name'=>'小程序绑定','path'=>'Binding/index','authdata'=>'Binding/*'];
				$pingtai_child_wx[] = ['name'=>'支付设置','path'=>'Wxpay/set','authdata'=>'Wxpay/*'];
				$pingtai_child_wx[] = ['name'=>'订阅消息设置','path'=>'Wxtmpl/tmplset','authdata'=>'Wxtmpl/*'];
				$pingtai_child_wx[] = ['name'=>'服务类目','path'=>'Wxleimu/index','authdata'=>'Wxleimu/*'];
				$pingtai_child_wx[] = ['name'=>'外部链接','path'=>'Wxurl/index','authdata'=>'Wxurl/*'];
				//$pingtai_child_wx[] = ['name'=>'关键字回复','path'=>'Wxkeyword/index','authdata'=>'Wxkeyword/*'];
				$pingtai_child[] = ['name'=>'微信小程序','child'=>$pingtai_child_wx];
			}
			if(in_array('alipay',$platform)){
				$pingtai_child[] = ['name'=>'支付宝小程序','path'=>'Binding/alipay','authdata'=>'Binding/*'];
			}
			if(in_array('baidu',$platform)){
				$pingtai_child[] = ['name'=>'百度小程序','path'=>'Binding/baidu','authdata'=>'Binding/*'];
			}
			if(in_array('toutiao',$platform)){
				$pingtai_child[] = ['name'=>'头条小程序','path'=>'Binding/toutiao','authdata'=>'Binding/*'];
			}
			if(in_array('qq',$platform)){
				$pingtai_child[] = ['name'=>'QQ小程序','path'=>'Binding/qq','authdata'=>'Binding/*'];
			}
			if(in_array('h5',$platform)){
				$pingtai_child[] = ['name'=>'手机H5','path'=>'Binding/h5','authdata'=>'Binding/*'];
			}
			if(in_array('app',$platform)){
				$pingtai_child[] = ['name'=>'手机APP','path'=>'Binding/app','authdata'=>'Binding/*'];
			}
			
			$menudata['pingtai'] = ['name'=>'平台','fullname'=>'平台设置','icon'=>'my-icon my-icon-pingtai','child'=>$pingtai_child];
		}

		$system_child = [];
		$system_child[] = [
			'name'=>$isadmin ? '系统配置' : '店铺设置',
			'path'=>'Backstage/sysset',
			'vue_path'=>$isadmin ? 'backstage/sysset' : 'merchant-store-set',
			'authdata'=>'Backstage/sysset'
		];
		$system_child[] = ['name'=>'提示音配置','path'=>'Backstage/setorder','authdata'=>'Backstage/setorder'];
		$system_child[] = ['name'=>'提示订单处理','path'=>'Backstage/orderchuli','authdata'=>'Backstage/orderchuli'];
		$system_child[] = ['name'=>'门店管理','path'=>'Mendian/index','authdata'=>'Mendian/*'];
		$system_child[] = ['name'=>'素材中心','path'=>'MaterialCenter/index','vue_path'=>'material-center','authdata'=>'Upload/*'];
		// $system_child[] = ['name'=>'门店管理设置','path'=>'MendianSet/index','authdata'=>'MendianSet/*'];
		$system_child[] = ['name'=>'管理员列表','path'=>'User/index','authdata'=>'User/*,UserGroup/*'];
		$system_child[] = ['name'=>'配送方式','path'=>'Freight/index','authdata'=>'Freight/*'];
		$system_child[] = ['name'=>'小票打印机','path'=>'Wifiprint/index','authdata'=>'Wifiprint/*'];
		$system_child[] = ['name'=>t('支付宝提现'),'path'=>'Money/alipay','authdata'=>'Money/*'];

		if($isadmin){
			$system_child[] = ['name'=>'短信设置','path'=>'Sms/set','authdata'=>'Sms/*'];
		}else{
			$system_child[] = ['name'=>'店铺评价','path'=>'BusinessComment/index','authdata'=>'BusinessComment/*'];
		}
        if($isadmin) {
        //    $system_child[] = ['name' => '慧动企微', 'path' => 'Backstage/huidong', 'authdata' => 'Backstage/huidong'];
        }

	$system_child[] = ['name'=>'操作日志','path'=>'Backstage/plog','authdata'=>'Backstage/plog'];

	// 学习中心（设置进度）- 已在顶部添加抽屉入口，侧边栏菜单保留作为完整页面入口
	// if(!$isadmin){
		$system_child[] = ['name'=>'学习中心','path'=>'HelpCenterProgress/index','authdata'=>'HelpCenterProgress/*','hide'=>false];
	// }

	$menudata['system'] = ['name'=>'系统','fullname'=>'系统设置','icon'=>'my-icon my-icon-sysset','child'=>$system_child];

		if($aid > 0){
			if($includePromotedPluginMenus){
				$menudata = self::applyPluginTopMenusForFileMode($menudata, intval($aid), $user);
				if(PluginTopMenuConfig::isHidePluginEntry(intval($aid))){
					unset($menudata['plugin']);
				}
			}else{
				$menudata = self::mergePluginMenusForPermission($menudata, intval($aid), $user);
			}
		}

		$implicitPluginControllers = self::getImplicitPluginControllers(intval($aid), $user);

        
		if($user && $user['auth_type']==0){
		    
		    
			if($user['groupid']){
				$user['auth_data'] = Db::name('admin_user_group')->where('id',$user['groupid'])->value('auth_data');
			}

			$auth_data = json_decode($user['auth_data'],true);
			$auth_data = is_array($auth_data) ? $auth_data : [];
			$hasPluginEntryAccess = self::hasPluginEntryAccess(intval($aid), $user, $auth_data);
			
			
			foreach($menudata as $k=>$v){
				$filteredMenu = self::filterMenuNodeByAuth(
					$v,
					$auth_data,
					$implicitPluginControllers,
					$hasPluginEntryAccess
				);
				if($filteredMenu === null){
					unset($menudata[$k]);
				}else{
					$menudata[$k] = $filteredMenu;
				}
			}
			
		}

		if($user && intval($user['bid'] ?? 0) > 0 && trim((string)($user['type'] ?? 'business')) !== 'tuanzhang'){
			$menudata = self::filterBusinessPermissionMenus($menudata);
		}

		return $menudata;
		
	}

	/**
	 * 递归按权限过滤菜单节点，支持插件菜单的任意层级分组。
	 * 分组节点没有自己的权限值时，只要任一后代菜单有权限就保留。
	 */
	private static function filterMenuNodeByAuth(
		array $menu,
		array $authData,
		array $implicitPluginControllers = [],
		bool $hasPluginEntryAccess = false
	): ?array
	{
		$children = isset($menu['child']) && is_array($menu['child']) ? $menu['child'] : [];
		$filteredChildren = [];
		foreach($children as $childKey => $child){
			if(!is_array($child)){
				continue;
			}

			$filteredChild = self::filterMenuNodeByAuth(
				$child,
				$authData,
				$implicitPluginControllers,
				$hasPluginEntryAccess
			);
			if($filteredChild !== null){
				$filteredChildren[$childKey] = $filteredChild;
			}
		}

		$path = strval($menu['path'] ?? '');
		$menuAuthData = strval($menu['authdata'] ?? '');
		$hasDirectAccess = false;
		if($path === 'MyPlugin/index' && $hasPluginEntryAccess){
			$hasDirectAccess = true;
		}elseif($path !== '' || $menuAuthData !== ''){
			$hasDirectAccess = in_array($path . ',' . $menuAuthData, $authData, true)
				|| self::hasImplicitPluginMenuAccess($menu, $implicitPluginControllers);
		}

		if(!empty($filteredChildren)){
			$menu['child'] = $filteredChildren;
			return $menu;
		}

		if($hasDirectAccess){
			unset($menu['child']);
			return $menu;
		}

		return null;
	}

	private static function hasPluginEntryAccess(int $aid, array $user = [], array $authData = []): bool
	{
		if($aid <= 0){
			return false;
		}

		if(intval($user['bid'] ?? 0) > 0 && self::hasActiveBusinessPluginAuth($aid, intval($user['bid']))){
			return true;
		}

		return self::hasPluginMenuAuthInData($aid, $authData);
	}

	private static function hasActiveBusinessPluginAuth(int $aid, int $bid): bool
	{
		if($aid <= 0 || $bid <= 0 || !class_exists('\\app\\admin\\common\\PluginBusinessAuth') || !self::tableExists('plugin_business_auth')){
			return false;
		}

		try{
			$rows = Db::name('plugin_business_auth')
				->where('aid', $aid)
				->where('bid', $bid)
				->where('status', 1)
				->where(function($query){
					$query->where('expire_time', 0)->whereOr('expire_time', '>', time());
				})
				->column('plugin_key');
		}catch(\Throwable $e){
			return false;
		}

		foreach($rows as $pluginKey){
			if(PluginBusinessAuth::isBusinessPluginEnabled($aid, $bid, strval($pluginKey))){
				return true;
			}
		}

		return false;
	}

	private static function hasPluginMenuAuthInData(int $aid, array $authData = []): bool
	{
		return !empty(self::getAccessiblePluginNames($aid, $authData));
	}

	/**
	 * 根据子账号权限，返回当前账号可访问的插件标识列表
	 * @param int $aid
	 * @param array $authData 权限选择值（支持 path,authdata 或已拆开的 path）
	 * @return array
	 */
	public static function getAccessiblePluginNames(int $aid, array $authData = []): array
	{
		if($aid <= 0 || empty($authData) || !self::tableExists('admin_plugin_menu')){
			return [];
		}

		$userAuthData = [];
		foreach($authData as $item){
			if(!is_string($item) || $item === ''){
				continue;
			}
			foreach(explode(',', $item) as $authItem){
				$authItem = trim(strval($authItem));
				if($authItem !== ''){
					$userAuthData[] = $authItem;
				}
			}
		}
		if(empty($userAuthData)){
			return [];
		}

		try{
			$rows = Db::name('admin_plugin_menu')
				->where('aid', $aid)
				->where('status', 1)
				->where('path', '<>', '')
				->field('plugin_name,path,auth_data')
				->select()
				->toArray();
		}catch(\Throwable $e){
			return [];
		}

		$pluginNames = [];
		foreach($rows as $row){
			$pluginName = trim(strval($row['plugin_name'] ?? ''));
			$path = trim(strval($row['path'] ?? ''));
			if($pluginName === '' || $path === '' || isset($pluginNames[$pluginName])){
				continue;
			}
			$authDataValue = trim(strval($row['auth_data'] ?? ''));
			$menuAuth = $authDataValue !== '' ? $authDataValue : $path . '/*';
			if(self::checkAuthData($menuAuth, $userAuthData)){
				$pluginNames[$pluginName] = true;
			}
		}

		return array_keys($pluginNames);
	}

	private static function tableExists(string $tableName): bool
	{
		try{
			$prefix = config('database.connections.mysql.prefix');
			$fullTableName = $prefix . $tableName;
			return !empty(Db::query("SHOW TABLES LIKE '{$fullTableName}'"));
		}catch(\Throwable $e){
			return false;
		}
	}

	private static function checkAuthData($menuAuth, $userAuthData): bool
	{
		if(empty($menuAuth)){
			return true;
		}

		$menuAuthList = array_values(array_filter(array_map(function($item){
			return trim(str_replace('^_^', '/*', strval($item)));
		}, explode(',', strval($menuAuth)))));

		$userAuthList = array_values(array_filter(array_map(function($item){
			return trim(str_replace('^_^', '/*', strval($item)));
		}, $userAuthData)));

		foreach($menuAuthList as $menuAuthItem){
			foreach($userAuthList as $auth){
				if($auth === $menuAuthItem){
					return true;
				}

				if(strpos($auth, '*') !== false){
					$pattern = str_replace('*', '.*', preg_quote($auth, '/'));
					if(preg_match('/^' . $pattern . '$/', $menuAuthItem)){
						return true;
					}
				}
			}
		}

		return false;
	}

	public static function yingxiaoPluginFallbackRules(): array
	{
		return [
			'collage' => ['name' => '多人拼团', 'prefix' => 'collage'],
			'kanjia' => ['name' => '砍价活动', 'prefix' => 'kanjia'],
			'seckill' => ['name' => '整点秒杀', 'prefix' => 'seckill'],
			'tuangou' => ['name' => '团购活动', 'prefix' => 'tuangou'],
			'scoreshop' => ['name' => '积分兑换', 'prefix' => 'scoreshop'],
			'sweepstakes' => ['name' => '抽奖活动', 'prefix' => 'choujiang'],
			'luckycollage' => ['name' => '幸运拼团', 'prefix' => 'luckycollage'],
			'shortvideo' => ['name' => '短视频', 'prefix' => 'shortvideo'],
			'task' => ['name' => '任务插件', 'prefix' => 'taskactivity'],
			'redpacket' => ['name' => '红包插件', 'prefix' => 'redpacketactivity'],
			'memberredpacket' => ['name' => '会员拆红包', 'prefix' => 'memberredpacket'],
			'hbtk' => ['name' => '拓客活动', 'prefix' => 'hbtk'],
			'evaluation' => ['name' => '测评系统', 'prefix' => 'evaluation'],
		];
	}

	public static function isYingxiaoPluginFallbackMenu(array $menu): bool
	{
		return self::yingxiaoNodeMatchesInstalledPlugin($menu, self::yingxiaoPluginFallbackRules());
	}

	public static function unifyYingxiaoPluginMenus(array $tree, int $aid, bool $isadmin = true): array
	{
		$tree = self::filterYingxiaoTreeByPlugin($tree, $aid);
		return self::injectYingxiaoFallbacks($tree, $aid, $isadmin);
	}

	public static function yingxiaoPluginFallbackChildren(int $aid, bool $isadmin): array
	{
		if($aid <= 0){
			return [];
		}

		$yingxiao_child = [];
		if(!self::isPluginMenuEnabled('collage', $aid)){
			$yingxiao_collage = [];
			$yingxiao_collage[] = ['name'=>'商品管理','path'=>'CollageProduct/index','authdata'=>'CollageProduct/*,CollageCode/*'];
			$yingxiao_collage[] = ['name'=>'订单管理','path'=>'CollageOrder/index','authdata'=>'CollageOrder/*'];
			$yingxiao_collage[] = ['name'=>'拼团管理','path'=>'CollageTeam/index','authdata'=>'CollageTeam/*'];
			$yingxiao_collage[] = ['name'=>'评价管理','path'=>'CollageComment/index','authdata'=>'CollageComment/*'];
			if($isadmin){
				$yingxiao_collage[] = ['name'=>'商品分类','path'=>'CollageCategory/index','authdata'=>'CollageCategory/*'];
				$yingxiao_collage[] = ['name'=>'分享海报','path'=>'CollagePoster/index','authdata'=>'CollagePoster/*'];
				$yingxiao_collage[] = ['name'=>'系统设置','path'=>'CollageSet/index','authdata'=>'CollageSet/*'];
			}
			$yingxiao_child[] = ['name'=>'多人拼团','child'=>$yingxiao_collage];
		}

		if(!self::isPluginMenuEnabled('kanjia', $aid)){
			$yingxiao_kanjia = [];
			$yingxiao_kanjia[] = ['name'=>'商品管理','path'=>'KanjiaProduct/index','authdata'=>'KanjiaProduct/*,KanjiaCode/*'];
			$yingxiao_kanjia[] = ['name'=>'订单管理','path'=>'KanjiaOrder/index','authdata'=>'KanjiaOrder/*'];
			if($isadmin){
				$yingxiao_kanjia[] = ['name'=>'分享海报','path'=>'KanjiaPoster/index','authdata'=>'KanjiaPoster/*'];
				$yingxiao_kanjia[] = ['name'=>'系统设置','path'=>'KanjiaSet/index','authdata'=>'KanjiaSet/*'];
			}
			$yingxiao_child[] = ['name'=>'砍价活动','child'=>$yingxiao_kanjia];
		}

		if(!self::isPluginMenuEnabled('seckill', $aid)){
			$yingxiao_seckill= [];
			$yingxiao_seckill[] = ['name'=>'商品列表','path'=>'SeckillProduct/index','authdata'=>'SeckillProduct/*,SeckillCode/*'];
			$yingxiao_seckill[] = ['name'=>'订单列表','path'=>'SeckillOrder/index','authdata'=>'SeckillOrder/*'];
			$yingxiao_seckill[] = ['name'=>'用户评价','path'=>'SeckillComment/index','authdata'=>'SeckillComment/*'];
			if($isadmin){
				$yingxiao_seckill[] = ['name'=>'秒杀设置','path'=>'SeckillSet/index','authdata'=>'SeckillSet/*'];
			}
			$yingxiao_child[] = ['name'=>'整点秒杀','child'=>$yingxiao_seckill];
		}

		if(!self::isPluginMenuEnabled('tuangou', $aid)){
			$yingxiao_tuangou = [];
			$yingxiao_tuangou[] = ['name'=>'商品管理','path'=>'TuangouProduct/index','authdata'=>'TuangouProduct/*,TuangouCode/*'];
			$yingxiao_tuangou[] = ['name'=>'订单管理','path'=>'TuangouOrder/index','authdata'=>'TuangouOrder/*'];
			$yingxiao_tuangou[] = ['name'=>'评价管理','path'=>'TuangouComment/index','authdata'=>'TuangouComment/*'];
			$yingxiao_tuangou[] = ['name'=>'商品分类','path'=>'TuangouCategory/index','authdata'=>'TuangouCategory/*'];
			if($isadmin){
				$yingxiao_tuangou[] = ['name'=>'分享海报','path'=>'TuangouPoster/index','authdata'=>'TuangouPoster/*'];
				$yingxiao_tuangou[] = ['name'=>'系统设置','path'=>'TuangouSet/index','authdata'=>'TuangouSet/*'];
			}
			$yingxiao_child[] = ['name'=>'团购活动','child'=>$yingxiao_tuangou];
		}

		if($isadmin && !self::isPluginMenuEnabled('scoreshop', $aid)){
			$yingxiao_scoreshop = [];
			$yingxiao_scoreshop[] = ['name'=>'商品管理','path'=>'ScoreshopProduct/index','authdata'=>'ScoreshopProduct/*,ScoreshopCode/*'];
			$yingxiao_scoreshop[] = ['name'=>'兑换记录','path'=>'ScoreshopOrder/index','authdata'=>'ScoreshopOrder/*'];
			$yingxiao_scoreshop[] = ['name'=>'商品分类','path'=>'ScoreshopCategory/index','authdata'=>'ScoreshopCategory/*'];
			$yingxiao_scoreshop[] = ['name'=>'分享海报','path'=>'ScoreshopPoster/index','authdata'=>'ScoreshopPoster/*'];
			$yingxiao_scoreshop[] = ['name'=>'系统设置','path'=>'ScoreshopSet/index','authdata'=>'ScoreshopSet/*'];
			$yingxiao_child[] = ['name'=>t('积分').'兑换','child'=>$yingxiao_scoreshop];
		}

		if($isadmin && !self::isPluginMenuEnabled('sweepstakes', $aid)){
			$yingxiao_choujiang = [];
			$yingxiao_choujiang[] = ['name'=>'活动列表','path'=>'Choujiang/index','authdata'=>'Choujiang/*'];
			$yingxiao_choujiang[] = ['name'=>'抽奖记录','path'=>'Choujiang/record','authdata'=>'Choujiang/*'];
			$yingxiao_child[] = ['name'=>'抽奖活动','child'=>$yingxiao_choujiang,'hide'=>1];
		}

		if(!self::isPluginMenuEnabled('shortvideo', $aid)){
			$short_video= [];
			$short_video[] = ['name'=>'分类列表','path'=>'ShortvideoCategory/index','authdata'=>'ShortvideoCategory/*'];
			$short_video[] = ['name'=>'视频列表','path'=>'Shortvideo/index','authdata'=>'Shortvideo/*'];
			$short_video[] = ['name'=>'评论列表','path'=>'ShortvideoComment/index','authdata'=>'ShortvideoComment/*'];
			$short_video[] = ['name'=>'笔记回评','path'=>'ShortvideoCommentReply/index','authdata'=>'ShortvideoCommentReply/*'];
			if($isadmin){
				$short_video[] = ['name'=>'海报设置','path'=>'ShortvideoPoster/index','authdata'=>'ShortvideoPoster/*'];
				$short_video[] = ['name'=>'系统设置','path'=>'ShortvideoSysset/index','authdata'=>'ShortvideoSysset/*'];
			}
			$yingxiao_child[] = ['name'=>'短视频','child'=>$short_video];
		}

		if(!self::isPluginMenuEnabled('luckycollage', $aid)){
			$yingxiao_luckycollage = [];
			$yingxiao_luckycollage[] = ['name'=>'商品管理','path'=>'LuckyCollageProduct/index','authdata'=>'LuckyCollageProduct/*,LuckyCollageCode/*'];
			$yingxiao_luckycollage[] = ['name'=>'订单管理','path'=>'LuckyCollageOrder/index','authdata'=>'LuckyCollageOrder/*'];
			$yingxiao_luckycollage[] = ['name'=>'拼团管理','path'=>'LuckyCollageTeam/index','authdata'=>'LuckyCollageTeam/*'];
			$yingxiao_luckycollage[] = ['name'=>'评价管理','path'=>'LuckyCollageComment/index','authdata'=>'LuckyCollageComment/*'];
			if($isadmin){
				$yingxiao_luckycollage[] = ['name'=>'商品分类','path'=>'LuckyCollageCategory/index','authdata'=>'LuckyCollageCategory/*'];
				$yingxiao_luckycollage[] = ['name'=>'分享海报','path'=>'LuckyCollagePoster/index','authdata'=>'LuckyCollagePoster/*'];
				$yingxiao_luckycollage[] = ['name'=>'机器人管理','path'=>'LuckyCollageJiqiren/index','authdata'=>'LuckyCollageJiqiren/*'];
				$yingxiao_luckycollage[] = ['name'=>'系统设置','path'=>'LuckyCollageSet/index','authdata'=>'LuckyCollageSet/*'];
			}
			$yingxiao_child[] = ['name'=>'幸运拼团','child'=>$yingxiao_luckycollage];
		}

		if(!self::isPluginMenuEnabled('task', $aid)){
			$yingxiao_task = [];
			$yingxiao_task[] = ['name'=>'活动管理','path'=>'TaskActivity/index','authdata'=>'TaskActivity/*'];
			$yingxiao_task[] = ['name'=>'任务统计','path'=>'TaskActivity/statistics','authdata'=>'TaskActivity/*'];
			$yingxiao_task[] = ['name'=>'用户完成记录','path'=>'TaskActivity/userRecords','authdata'=>'TaskActivity/*'];
			$yingxiao_task[] = ['name'=>'奖励发放明细','path'=>'TaskActivity/rewardRecords','authdata'=>'TaskActivity/*'];
			$yingxiao_child[] = ['name'=>'任务插件','child'=>$yingxiao_task,'hide'=>1];
		}

		if(!self::isPluginMenuEnabled('redpacket', $aid)){
			$yingxiao_redpacket = [];
			$yingxiao_redpacket[] = ['name'=>'活动管理','path'=>'RedpacketActivity/index','authdata'=>'RedpacketActivity/*'];
			$yingxiao_redpacket[] = ['name'=>'活动统计','path'=>'RedpacketActivity/statistics','authdata'=>'RedpacketActivity/*'];
			$yingxiao_redpacket[] = ['name'=>'购买记录','path'=>'RedpacketActivity/purchaseRecords','authdata'=>'RedpacketActivity/*'];
			$yingxiao_redpacket[] = ['name'=>'签到记录','path'=>'RedpacketActivity/checkinRecords','authdata'=>'RedpacketActivity/*'];
			$yingxiao_redpacket[] = ['name'=>'领取记录','path'=>'RedpacketActivity/claimRecords','authdata'=>'RedpacketActivity/*'];
			$yingxiao_child[] = ['name'=>'红包插件','child'=>$yingxiao_redpacket,'hide'=>1];
		}

		if($isadmin && !self::isPluginMenuEnabled('memberredpacket', $aid)){
			$yingxiao_member_redpacket = [];
			$yingxiao_member_redpacket[] = ['name'=>'活动管理','path'=>'MemberRedpacket/index','authdata'=>'MemberRedpacket/*'];
			$yingxiao_member_redpacket[] = ['name'=>'领取记录','path'=>'MemberRedpacket/logs','authdata'=>'MemberRedpacket/*','hide'=>true];
			$yingxiao_child[] = ['name'=>'会员拆红包','child'=>$yingxiao_member_redpacket,'hide'=>1];
		}

		if(!self::isPluginMenuEnabled('hbtk', $aid)){
			$yingxiao_hbtk = [];
			$yingxiao_hbtk[] = ['name'=>'活动列表','path'=>'HbtkActivity/index','authdata'=>'HbtkActivity/*'];
			$yingxiao_hbtk[] = ['name'=>'参与记录','path'=>'HbtkActivity/record','authdata'=>'HbtkActivity/*'];
			$yingxiao_hbtk[] = ['name'=>'中奖记录','path'=>'HbtkActivity/winrecord','authdata'=>'HbtkActivity/*'];
			$yingxiao_hbtk[] = ['name'=>'分润记录','path'=>'HbtkActivity/tuijianrecord','authdata'=>'HbtkActivity/*'];
			$yingxiao_hbtk[] = ['name'=>'分享记录','path'=>'HbtkActivity/sharelog','authdata'=>'HbtkActivity/*'];
			$yingxiao_hbtk[] = ['name'=>'返利记录','path'=>'HbtkActivity/fanlilog','authdata'=>'HbtkActivity/*'];
			$yingxiao_hbtk[] = ['name'=>'浏览记录','path'=>'HbtkActivity/viewlog','authdata'=>'HbtkActivity/*'];
			$yingxiao_hbtk[] = ['name'=>'海报管理','path'=>'HbtkPoster/index','authdata'=>'HbtkPoster/*'];
			$yingxiao_child[] = ['name'=>'拓客活动','child'=>$yingxiao_hbtk,'hide'=>1];
		}

		if(!self::isPluginMenuEnabled('evaluation', $aid)){
			$yingxiao_evaluation = [];
			$yingxiao_evaluation[] = ['name'=>'测评项目','path'=>'Evaluation/index','authdata'=>'Evaluation/*,EvaluationUnit/*,EvaluationQuestion/*,EvaluationQrcode/*,EvaluationResult/*'];
			$yingxiao_evaluation[] = ['name'=>'组织架构管理','path'=>'EvaluationOrganization/index','authdata'=>'EvaluationOrganization/*'];
			$yingxiao_evaluation[] = ['name'=>'评测组织配置','path'=>'EvaluationOrgPage/index','authdata'=>'EvaluationOrgPage/*'];
			$yingxiao_evaluation[] = ['name'=>'聚合页面设置','path'=>'EvaluationAggregation/index','authdata'=>'EvaluationAggregation/*'];
			$yingxiao_evaluation[] = ['name'=>'聚合页面分组','path'=>'EvaluationAggregationGroup/index','authdata'=>'EvaluationAggregationGroup/*'];
			$yingxiao_evaluation[] = ['name'=>'系统设置','path'=>'EvaluationSet/index','authdata'=>'EvaluationSet/*'];
			$yingxiao_child[] = ['name'=>'测评系统','child'=>$yingxiao_evaluation,'hide'=>1];
		}

		return $yingxiao_child;
	}

	public static function injectYingxiaoFallbacks(array $tree, int $aid, bool $isadmin = true): array
	{
		$fallbacks = self::yingxiaoPluginFallbackChildren($aid, $isadmin);
		if(empty($fallbacks)){
			return $tree;
		}

		foreach($tree as $key => $menu){
			if(!is_array($menu)){
				continue;
			}
			$menuKey = trim((string)($menu['key'] ?? $key));
			if($menuKey !== 'yingxiao' && trim((string)($menu['name'] ?? '')) !== '营销'){
				continue;
			}

			$children = array_values($menu['child'] ?? $menu['children'] ?? []);
			$existing = [];
			foreach($children as $child){
				if(is_array($child)){
					$existing[trim((string)($child['name'] ?? ''))] = true;
				}
			}
			foreach($fallbacks as $fallback){
				$name = trim((string)($fallback['name'] ?? ''));
				if($name === '' || !empty($existing[$name])){
					continue;
				}
				$children[] = $fallback;
				$existing[$name] = true;
			}
			$menu['child'] = $children;
			$menu['children'] = $children;
			$tree[$key] = $menu;
		}

		return $tree;
	}

	public static function filterYingxiaoTreeByPlugin(array $tree, int $aid): array
	{
		$result = [];
		foreach($tree as $key => $menu){
			if(!is_array($menu)){
				continue;
			}
			$menuKey = trim((string)($menu['key'] ?? $key));
			if($menuKey === 'yingxiao' || trim((string)($menu['name'] ?? '')) === '营销'){
				$children = $menu['child'] ?? $menu['children'] ?? [];
				if(is_array($children)){
					$children = self::filterYingxiaoChildrenByPlugin($children, $aid);
					$menu['child'] = $children;
					$menu['children'] = $children;
				}
			}
			$result[$key] = $menu;
		}
		return $result;
	}

	public static function filterYingxiaoChildrenByPlugin(array $children, int $aid): array
	{
		$installed = [];
		foreach(self::yingxiaoPluginFallbackRules() as $plugin => $rule){
			if(self::isPluginMenuEnabled($plugin, $aid)){
				$installed[$plugin] = $rule;
			}
		}
		if(empty($installed)){
			return array_values($children);
		}

		$result = [];
		foreach($children as $child){
			if(!is_array($child)){
				continue;
			}
			$filtered = self::filterYingxiaoNodeByPlugin($child, $installed);
			if($filtered !== null){
				$result[] = $filtered;
			}
		}
		return $result;
	}

	private static function filterYingxiaoNodeByPlugin(array $menu, array $installed): ?array
	{
		if(self::yingxiaoNodeMatchesInstalledPlugin($menu, $installed)){
			return null;
		}

		$kids = $menu['child'] ?? $menu['children'] ?? [];
		if(is_array($kids) && !empty($kids)){
			$next = [];
			foreach($kids as $kid){
				if(!is_array($kid)){
					continue;
				}
				$filtered = self::filterYingxiaoNodeByPlugin($kid, $installed);
				if($filtered !== null){
					$next[] = $filtered;
				}
			}
			$menu['child'] = $next;
			$menu['children'] = $next;
			if(empty($next) && empty($menu['path']) && empty($menu['vue_path'])){
				return null;
			}
		}

		return $menu;
	}

	private static function yingxiaoNodeMatchesInstalledPlugin(array $menu, array $installed): bool
	{
		$name = trim((string)($menu['name'] ?? ''));
		$blob = strtolower(implode(' ', [
			(string)($menu['path'] ?? ''),
			(string)($menu['vue_path'] ?? ''),
			(string)($menu['authdata'] ?? ''),
			(string)($menu['auth_data'] ?? ''),
			(string)($menu['key'] ?? ''),
		]));

		foreach($installed as $plugin => $rule){
			$ruleName = trim((string)($rule['name'] ?? ''));
			$prefix = strtolower(trim((string)($rule['prefix'] ?? '')));
			if($ruleName !== '' && $name === $ruleName){
				return true;
			}
			if($plugin === 'scoreshop' && $name !== '' && mb_substr($name, -2) === '兑换' && strpos($name, '券') === false){
				return true;
			}
			if(self::yingxiaoBlobHasPluginPrefix($blob, $plugin, $prefix)){
				return true;
			}
		}

		return false;
	}

	private static function yingxiaoBlobHasPluginPrefix(string $blob, string $plugin, string $prefix): bool
	{
		if($prefix === '' || $blob === ''){
			return false;
		}
		if($plugin === 'collage'){
			$blob = str_replace('luckycollage', '', $blob);
		}
		return strpos($blob, $prefix) !== false;
	}

	private static function isPluginMenuEnabled(string $pluginName, int $aid = 0): bool
	{
		$pluginName = trim($pluginName);
		if($pluginName === '' || $aid <= 0 || !self::tableExists('admin_plugin_menu')){
			return false;
		}

		try{
			return intval(Db::name('admin_plugin_menu')
				->where('aid', $aid)
				->where('plugin_name', $pluginName)
				->where('status', 1)
				->count()) > 0;
		}catch(\Throwable $e){
			return false;
		}
	}

	public static function getPermissionData($aid=0,$uid=0,$includePluginMenus=true){
        $menudata = self::getdata($aid, $uid, false);
        if(!$includePluginMenus && isset($menudata['plugin'])){
            unset($menudata['plugin']);
        }
        return $menudata;
    }

	public static function getScopedPermissionData($aid=0,$uid=0,$includePluginMenus=true){
		if(self::useDatabaseMenuMode() && class_exists('\\app\\admin\\common\\MenuDatabase')){
			$menudata = \app\admin\common\MenuDatabase::getScopedMenuForPermission(intval($aid), intval($uid), $includePluginMenus);
			if(self::shouldUseBusinessPermissionScope(intval($uid))){
				return self::filterBusinessPermissionMenus($menudata);
			}
			return $menudata;
		}

		$menudata = self::getPermissionData($aid, $uid, $includePluginMenus);
		if(self::shouldUseBusinessPermissionScope(intval($uid))){
			return self::filterBusinessPermissionMenus($menudata);
		}
		return $menudata;
	}

		public static function getBusinessPermissionData($aid=0,$includePluginMenus=true){
			if(self::useDatabaseMenuMode() && class_exists('\\app\\admin\\common\\MenuDatabase')){
				$menudata = \app\admin\common\MenuDatabase::getMerchantMenuForPermission(intval($aid), $includePluginMenus);
				return self::filterBusinessPermissionMenus($menudata);
			}

			$menudata = self::getPermissionData($aid, -1, $includePluginMenus);
			return self::filterLegacyBusinessPermissionMenus($menudata);
		}

		public static function getTuanzhangPermissionData($aid=0,$includePluginMenus=false): array
		{
			return [
				'tuanzhang' => [
					'name' => '团长',
					'fullname' => '团长管理',
					'icon' => 'my-icon my-icon-business',
					'child' => self::getTuanzhangPermissionItems(),
				],
				'finance' => [
					'name' => '财务',
					'fullname' => '财务管理',
					'icon' => 'my-icon my-icon-finance',
					'child' => self::getTuanzhangFinancePermissionItems(),
				],
			];
		}

		private static function getTuanzhangPermissionItems(): array
		{
			return [
				['name'=>'团长管理','path'=>'Tuanzhang/index','authdata'=>'Tuanzhang/*,TuanzhangFreight/*'],
				['name'=>'团长分类','path'=>'Tuanzhang/category','authdata'=>'Tuanzhang/*'],
				['name'=>'团管理','path'=>'Tuanzhang/tuanguanli','authdata'=>'Tuanzhang/*'],
				['name'=>'商品管理','path'=>'TuanzhangProduct/index','authdata'=>'TuanzhangProduct/*'],
				['name'=>'订单管理','path'=>'TuanzhangOrder/index','authdata'=>'TuanzhangOrder/*'],
				['name'=>'分享海报','path'=>'TuanzhangPoster/index','authdata'=>'TuanzhangPoster/*'],
				['name'=>'系统设置','path'=>'Tuanzhang/sysset','authdata'=>'Tuanzhang/sysset'],
			];
		}

		private static function getTuanzhangFinancePermissionItems(): array
		{
			return [
				['name'=>'团长收益','path'=>'TuanzhangMoney/moneylog','authdata'=>'TuanzhangMoney/*'],
				['name'=>'提现申请','path'=>'TuanzhangMoney/withdraw','authdata'=>'TuanzhangMoney/*'],
				['name'=>'提现记录','path'=>'TuanzhangMoney/withdrawlog','authdata'=>'TuanzhangMoney/*'],
			];
		}

	private static function useDatabaseMenuMode(): bool
	{
		try{
			$config = Db::name('admin_system_config')->where('key', 'menu_use_database')->find();
			return !empty($config) && ($config['value'] == '1' || $config['value'] == 1 || $config['value'] === true);
		}catch(\Throwable $e){
			return false;
		}
	}

	private static function filterLegacyBusinessPermissionMenus(array $menudata): array
	{
		return self::filterBusinessPermissionMenus($menudata);
	}

	private static function filterBusinessPermissionMenus(array $menudata): array
	{
		if(empty($menudata)){
			return [];
		}

		$allowedTopMenus = [
			'shop' => null,
			'jiemian' => null,
			'finance' => null,
			'yingxiao' => ['优惠券','商品促销','多人拼团','砍价活动','整点秒杀','团购活动'],
			'restaurant' => null,
			'hotel' => null,
			'system' => null,
		];

		$filtered = [];
		foreach($menudata as $key => $menu){
			if(!is_array($menu)){
				continue;
			}

			$isPluginMenu = $key === 'plugin' || strpos((string)$key, 'plugin_') === 0;
			if(!$isPluginMenu && !array_key_exists($key, $allowedTopMenus)){
				continue;
			}

			$menu = self::filterMenuNodeByUserType($menu, ['bid' => 1]);
			if($menu === null){
				continue;
			}

			$menu = self::filterBusinessUnsafeMenuNode($menu);
			if($menu === null){
				continue;
			}

			$allowedChildNames = $isPluginMenu ? null : $allowedTopMenus[$key];
			if(is_array($allowedChildNames) && isset($menu['child']) && is_array($menu['child'])){
				$menu['child'] = array_values(array_filter($menu['child'], function($child) use ($allowedChildNames){
					$childName = trim(strval($child['name'] ?? ''));
					return $childName !== '' && in_array($childName, $allowedChildNames, true);
				}));
			}

			$hasChildren = isset($menu['child']) && is_array($menu['child']) && !empty($menu['child']);
			$hasDirectEntry = !empty($menu['path']) && !empty($menu['authdata']);
			if(!$hasChildren && !$hasDirectEntry){
				continue;
			}

			$filtered[$key] = $menu;
		}

		return $filtered;
	}

	private static function filterBusinessUnsafeMenuNode(array $menu): ?array
	{
		$path = trim((string)($menu['path'] ?? ''));
		if(in_array($path, self::getBusinessUnsafeMenuPaths(), true)){
			return null;
		}

		if(isset($menu['child']) && is_array($menu['child'])){
			$children = [];
			foreach($menu['child'] as $child){
				if(!is_array($child)){
					continue;
				}

				$child = self::filterBusinessUnsafeMenuNode($child);
				if($child !== null){
					$children[] = $child;
				}
			}
			$menu['child'] = $children;
		}

		return $menu;
	}

	private static function getBusinessUnsafeMenuPaths(): array
	{
		return [
			'Business/sysset',
			'ShopSet/index',
		];
	}

		public static function sanitizeBusinessPermissionSelection($aid=0, array $selected = [], bool $includePluginMenus=true): array
		{
			if(empty($selected)){
				return [];
			}

		$normalized = [];
		foreach($selected as $value){
			$value = self::normalizePermissionSelectionValue($value);
			if($value === '' || $value === 'all'){
				continue;
			}
			$normalized[] = $value;
		}
		if(empty($normalized)){
			return [];
		}

		$allowedTree = self::getBusinessPermissionData(intval($aid), $includePluginMenus);
		$allowedValues = array_flip(self::collectPermissionAuthValues($allowedTree));
		if(empty($allowedValues)){
			return [];
		}

		$result = [];
		foreach($normalized as $value){
			if(isset($allowedValues[$value])){
				$result[] = $value;
			}
		}

			return array_values(array_unique($result));
		}

		public static function sanitizeTuanzhangPermissionSelection($aid=0, array $selected = [], bool $includePluginMenus=false): array
		{
			if(empty($selected)){
				return [];
			}

			$normalized = [];
			foreach($selected as $value){
				$value = self::normalizePermissionSelectionValue($value);
				if($value === '' || $value === 'all'){
					continue;
				}
				$normalized[] = $value;
			}
			if(empty($normalized)){
				return [];
			}

			$allowedValues = array_flip(self::collectPermissionAuthValues(self::getTuanzhangPermissionData(intval($aid), $includePluginMenus)));
			if(empty($allowedValues)){
				return [];
			}

			$result = [];
			foreach($normalized as $value){
				if(isset($allowedValues[$value])){
					$result[] = $value;
				}
			}

			return array_values(array_unique($result));
		}

	public static function sanitizeScopedPermissionSelection($aid=0, $uid=0, array $selected = [], bool $includePluginMenus=true): array
	{
		if(empty($selected)){
			return [];
		}

		$normalized = [];
		foreach($selected as $value){
			$value = self::normalizePermissionSelectionValue($value);
			if($value === '' || $value === 'all'){
				continue;
			}
			$normalized[] = $value;
		}
		if(empty($normalized)){
			return [];
		}

		$allowedTree = self::getScopedPermissionData(intval($aid), intval($uid), $includePluginMenus);
		$allowedValues = array_flip(self::collectPermissionAuthValues($allowedTree));
		if(empty($allowedValues)){
			return [];
		}

		$result = [];
		foreach($normalized as $value){
			if(isset($allowedValues[$value])){
				$result[] = $value;
			}
		}

		return array_values(array_unique($result));
	}

	private static function collectPermissionAuthValues(array $menudata): array
	{
		$values = [];
		$walker = function(array $nodes) use (&$walker, &$values){
			foreach($nodes as $node){
				if(!is_array($node)){
					continue;
				}

				$path = trim((string)($node['path'] ?? ''));
				$authdata = trim((string)($node['authdata'] ?? ($node['auth_data'] ?? '')));
				if($path !== '' && $authdata !== ''){
					$values[] = $path . ',' . $authdata;
				}

				if(!empty($node['child']) && is_array($node['child'])){
					$walker($node['child']);
				}
			}
		};

		$walker($menudata);
		return array_values(array_unique($values));
	}

	private static function normalizePermissionSelectionValue($value): string
	{
		$value = trim((string)$value);
		if($value === ''){
			return '';
		}

		// 兼容 html 权限模板里把 /* 替换成 ^_^ 再提交的老格式。
		return str_replace('^_^', '/*', $value);
	}

	private static function shouldUseBusinessPermissionScope(int $uid): bool
	{
		if($uid <= 0){
			return false;
		}

		try{
			$user = Db::name('admin_user')->where('id', $uid)->field('bid,type')->find();
			if(empty($user) || intval($user['bid'] ?? 0) <= 0){
				return false;
			}

			return trim((string)($user['type'] ?? 'business')) !== 'tuanzhang';
		}catch(\Throwable $e){
			return false;
		}
	}

	private static function getImplicitPluginControllers(int $aid, array $user = []): array
	{
		try{
			if($aid <= 0){
				return [];
			}
			if(intval($user['bid'] ?? 0) > 0){
				return [];
			}
			if(intval($user['isadmin'] ?? 0) <= 0){
				return [];
			}

			$prefix = config('database.connections.mysql.prefix');
			$menuTable = $prefix . 'admin_plugin_menu';
			$licenseTable = $prefix . 'admin_plugin_license';
			if(empty(Db::query("SHOW TABLES LIKE '{$menuTable}'")) || empty(Db::query("SHOW TABLES LIKE '{$licenseTable}'"))){
				return [];
			}

			$now = time();
			$pluginNames = Db::name('admin_plugin_license')
				->where('aid', $aid)
				->where('status', 1)
				->where(function ($q) use ($now) {
					$q->where('expire_time', 0)->whereOr('expire_time', '>', $now);
				})
				->column('plugin_name');
			if(empty($pluginNames)){
				return [];
			}

			$paths = Db::name('admin_plugin_menu')
				->where('aid', $aid)
				->where('status', 1)
				->whereIn('plugin_name', $pluginNames)
				->where('path', '<>', '')
				->column('path');

			$controllers = [];
			foreach($paths as $path){
				$controller = self::extractControllerFromPath(strval($path));
				if($controller !== ''){
					$controllers[$controller] = true;
				}
			}

			return $controllers;
		}catch(\Throwable $e){
			return [];
		}
	}

	private static function hasImplicitPluginMenuAccess(array $menuItem, array $implicitPluginControllers = []): bool
	{
		if(empty($implicitPluginControllers)){
			return false;
		}

		$path = strval($menuItem['path'] ?? '');
		if($path === 'MyPlugin/index'){
			return true;
		}

		$controller = self::extractControllerFromPath($path);
		if($controller === ''){
			return false;
		}

		return isset($implicitPluginControllers[$controller]);
	}

	private static function mergePluginMenusForPermission(array $menudata, int $aid, array $user = []): array
	{
		$pluginMenus = self::getPluginMenus($aid, $user);
		if(empty($pluginMenus)){
			return $menudata;
		}

		$pluginChildren = [];
		foreach($pluginMenus as $pluginMenu){
			unset($pluginMenu['plugin_name'], $pluginMenu['__plugin_wrap']);
			$pluginChildren[] = $pluginMenu;
		}

		if(!isset($menudata['plugin']) || !is_array($menudata['plugin'])){
			$menudata['plugin'] = [
				'name' => '插件',
				'fullname' => '插件管理',
				'icon' => 'my-icon my-icon-kuozhan',
				'path' => 'MyPlugin/index',
				'authdata' => 'MyPlugin/*',
				'child' => [],
			];
		}

		$existingChildren = [];
		if(isset($menudata['plugin']['child']) && is_array($menudata['plugin']['child'])){
			$existingChildren = $menudata['plugin']['child'];
		}

		$menudata['plugin']['child'] = array_values(array_merge($existingChildren, $pluginChildren));

		return $menudata;
	}

	private static function applyPluginTopMenusForFileMode(array $menudata, int $aid, array $user = []): array
	{
		$pluginMenus = self::getPluginMenus($aid, $user);
		if(empty($pluginMenus)){
			return $menudata;
		}

		$configMap = PluginTopMenuConfig::getAdjustMap($aid);
		$pluginChildren = [];
		$promotedMenus = [];
		foreach($pluginMenus as $pluginMenu){
			$pluginName = trim((string)($pluginMenu['plugin_name'] ?? ''));
			$config = ($pluginName !== '' && isset($configMap[$pluginName])) ? $configMap[$pluginName] : [];
			$wholeEnabled = intval($config['status'] ?? 0) === 1;
			$promoteItems = is_array($config['promote_items'] ?? null) ? $config['promote_items'] : [];
			$promoteIcons = is_array($config['promote_icons'] ?? null) ? $config['promote_icons'] : [];
			$split = self::splitPromotedPluginMenu($pluginMenu, $promoteItems, $wholeEnabled, $promoteIcons);
			$kept = $split['kept'];
			$lifted = $split['lifted'];

			if($wholeEnabled && $kept !== null){
				if(!empty($config['custom_name'])){
					$kept['name'] = $config['custom_name'];
					$kept['fullname'] = $config['custom_name'];
				}
				if(!empty($config['custom_icon'])){
					$kept['icon'] = $config['custom_icon'];
				}
				$kept['__sort'] = intval($config['sort'] ?? 0);
				unset($kept['plugin_name'], $kept['__plugin_wrap']);
				$promotedMenus['plugin_top_' . $pluginName] = $kept;
			}elseif($kept !== null){
				unset($kept['plugin_name'], $kept['__plugin_wrap']);
				$pluginChildren[] = $kept;
			}

			$baseSort = intval($config['sort'] ?? 0);
			$anchor = $baseSort > 0 ? $baseSort : 900000;
			foreach($lifted as $index => $node){
				unset($node['plugin_name'], $node['__plugin_wrap']);
				$itemSort = intval($node['sort'] ?? 0);
				$node['__sort'] = $anchor * 1000 + max(0, $itemSort);
				$promotedMenus['plugin_item_' . $pluginName . '_' . $index] = $node;
			}
		}

		if(!empty($pluginChildren)){
			if(!isset($menudata['plugin']) || !is_array($menudata['plugin'])){
				$menudata['plugin'] = [
					'name' => '插件',
					'fullname' => '插件管理',
					'icon' => 'my-icon my-icon-kuozhan',
					'path' => 'MyPlugin/index',
					'authdata' => 'MyPlugin/*',
					'child' => [],
				];
			}

			$existingChildren = isset($menudata['plugin']['child']) && is_array($menudata['plugin']['child'])
				? $menudata['plugin']['child']
				: [];
			$menudata['plugin']['child'] = array_values(array_merge($existingChildren, $pluginChildren));
		}

		if(empty($promotedMenus)){
			return $menudata;
		}

		uasort($promotedMenus, function($a, $b){
			$sortCompare = intval($a['__sort'] ?? 0) <=> intval($b['__sort'] ?? 0);
			if($sortCompare !== 0){
				return $sortCompare;
			}

			return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
		});

		foreach($promotedMenus as &$menu){
			unset($menu['__sort'], $menu['plugin_name']);
		}
		unset($menu);

		$result = [];
		$inserted = false;
		foreach($menudata as $key => $menu){
			if($key === 'plugin' && !$inserted){
				foreach($promotedMenus as $promotedKey => $promotedMenu){
					$result[$promotedKey] = $promotedMenu;
				}
				$inserted = true;
			}
			$result[$key] = $menu;
		}

		if(!$inserted){
			foreach($promotedMenus as $promotedKey => $promotedMenu){
				$result[$promotedKey] = $promotedMenu;
			}
		}

		return $result;
	}

	private static function splitPromotedPluginMenu(array $pluginMenu, array $promoteItems, bool $wholeEnabled, array $promoteIcons = []): array
	{
		$lifted = [];
		$keySet = [];
		foreach($promoteItems as $itemKey){
			$itemKey = trim((string)$itemKey);
			if($itemKey !== ''){
				$keySet[$itemKey] = true;
			}
		}

		$extract = function(array $node, string $parentKey, bool $isRoot) use (&$extract, &$lifted, $keySet, $wholeEnabled, $promoteIcons){
			$itemKey = PluginTopMenuConfig::buildMenuItemKey($node, $parentKey);
			$children = [];
			foreach(($node['child'] ?? []) as $child){
				if(!is_array($child)){
					continue;
				}
				$keptChild = $extract($child, $itemKey, false);
				if($keptChild !== null){
					$children[] = $keptChild;
				}
			}
			if($children){
				$node['child'] = array_values($children);
			}else{
				unset($node['child']);
			}

			$shouldLift = isset($keySet[$itemKey]) && !($wholeEnabled && $isRoot);
			if($shouldLift){
				$customIcon = trim((string)($promoteIcons[$itemKey] ?? ''));
				if($customIcon !== ''){
					$node['icon'] = $customIcon;
				}
				$lifted[] = $node;
				return null;
			}
			if(trim((string)($node['path'] ?? '')) === '' && empty($node['child'])){
				return null;
			}
			return $node;
		};

		if(!empty($pluginMenu['__plugin_wrap'])){
			$keptChildren = [];
			foreach(($pluginMenu['child'] ?? []) as $child){
				if(!is_array($child)){
					continue;
				}
				$keptChild = $extract($child, '', true);
				if($keptChild !== null){
					$keptChildren[] = $keptChild;
				}
			}
			unset($pluginMenu['__plugin_wrap']);
			if(!$keptChildren){
				return ['kept' => null, 'lifted' => $lifted];
			}
			$pluginMenu['child'] = $keptChildren;
			return ['kept' => $pluginMenu, 'lifted' => $lifted];
		}

		return [
			'kept' => $extract($pluginMenu, '', true),
			'lifted' => $lifted,
		];
	}

	//白名单 不校验权限
	public static function blacklist(){
		$data = [];
		$data[] = 'Backstage/index';
		$data[] = 'Backstage/welcome';
		$data[] = 'Backstage/setpwd';
		$data[] = 'Backstage/about';
		$data[] = 'Help/*';
		$data[] = 'Upload/*';
		$data[] = 'DesignerPage/chooseurl';
		$data[] = 'Peisong/getpeisonguser';
		$data[] = 'Peisong/peisong';
		$data[] = 'Miandan/addorder';
		$data[] = 'Wxset/*';
		$data[] = 'Notice/*';
		$data[] = 'notice/*';
		$data[] = 'SxpayIncome/*';
		$data[] = 'Member/inputlockpwd';
		$data[] = 'MemberLevel/inputlockpwd';
		$data[] = 'ShopProduct/inputlockpwd';
		$data[] = 'Member/dolock';
		$data[] = 'MemberLevel/dolock';
		$data[] = 'ShopProduct/dolock';
		return $data;
	}
	
	/**
	 * 获取所有菜单模块（用于帮助中心权限关联，包含二级子菜单）
	 * @return array [['key'=>'shop', 'name'=>'商城', 'group'=>'基础模块'], ...]
	 */
	public static function getMenuModules(){
		// 调用一次完整菜单以获取最新结构
		$menudata = self::getdata(0, 0);
		
		// 定义基础模块（不在此列表中的都是扩展模块）
		// 注意：component 是扩展功能的容器，其子菜单才是真正的扩展模块
		$basicModules = ['shop', 'member', 'finance', 'yingxiao', 'restaurant', 'hotel', 'jiemian', 'pingtai', 'system'];
		
		$modules = [];
		$processedKeys = []; // 防止重复添加
		
		foreach($menudata as $key => $menu){
			if(!isset($menu['name'])){
				continue;
			}
			
			// component 是扩展功能的容器，不作为独立菜单项，直接处理其子菜单
			if($key == 'component'){
				continue; // 跳过容器本身，后续特殊处理
			}
			
			// 添加一级菜单
			$group = in_array($key, $basicModules) ? '基础模块' : '扩展模块';
			
			if(!isset($processedKeys[$key])){
				$modules[] = [
					'key' => $key,
					'name' => $menu['name'],
					'group' => $group,
					'level' => 1
				];
				$processedKeys[$key] = true;
			}
			
			// 如果有子菜单（二级菜单），也提取出来
			if(isset($menu['child']) && is_array($menu['child'])){
				foreach($menu['child'] as $childMenu){
					if(!isset($childMenu['name'])){
						continue;
					}
					
					// 判断是否有三级菜单（二级分组）
					if(isset($childMenu['child']) && is_array($childMenu['child']) && !empty($childMenu['child'])){
						// 这是一个分组（比如"微信公众号"），处理其下的三级菜单
						foreach($childMenu['child'] as $grandChildMenu){
							if(!isset($grandChildMenu['name'])){
								continue;
							}
							
							// 从三级菜单提取key
							$grandChildKey = null;
							if(isset($grandChildMenu['path'])){
								$grandChildKey = self::extractControllerFromPath($grandChildMenu['path']);
							}
							
							if($grandChildKey && !isset($processedKeys[$grandChildKey])){
								$modules[] = [
									'key' => $grandChildKey,
									'name' => $menu['name'] . ' - ' . $childMenu['name'] . ' - ' . $grandChildMenu['name'],
									'group' => $group,
									'level' => 3,
									'parent' => $key, // 父级是一级菜单
									'parent2' => $childMenu['name'] // 二级分组名称
								];
								$processedKeys[$grandChildKey] = true;
							}
						}
					} else {
						// 这是普通的二级菜单（有path，没有child）
						$childKey = null;
						$childPath = null;
						if(isset($childMenu['path'])){
							$childPath = $childMenu['path'];
							$childKey = self::extractControllerFromPath($childMenu['path']);
						}
						
						if($childKey){
							// 特殊处理：同一个控制器可能有多个不同的path（如Binding/alipay, Binding/baidu）
							// 对于这种情况，使用完整path作为唯一标识
							$uniqueKey = $childKey;
							if($childPath && strpos($childPath, '/') !== false){
								// 如果path包含参数（如Binding/alipay），使用完整path
								$uniqueKey = str_replace('/', '_', $childPath);
							}
							
							if(!isset($processedKeys[$uniqueKey])){
								$modules[] = [
									'key' => $uniqueKey,
									'name' => $menu['name'] . ' - ' . $childMenu['name'],
									'group' => $group,
									'level' => 2,
									'parent' => $key,
									'path' => $childPath // 保存原始path
								];
								$processedKeys[$uniqueKey] = true;
							}
						}
					}
				}
			}
		}
		
		// 调试：记录处理的菜单数量
		// error_log("处理完基础模块，当前modules数量：" . count($modules));
		
		// 特殊处理：component（扩展功能）容器
		// component 的子菜单是扩展模块的分组（如"预约服务"），需要展开到三级菜单
		if(isset($menudata['component'])){
			// error_log("发现 component 容器");
			if(isset($menudata['component']['child'])){
				// error_log("component 有 " . count($menudata['component']['child']) . " 个子项");
				foreach($menudata['component']['child'] as $extModule){
					if(!isset($extModule['name'])){
						continue;
					}
					
					// 扩展模块的二级分组（如"预约服务"）
					$extModuleName = $extModule['name'];
					// error_log("处理扩展模块：{$extModuleName}");
					
					// 如果有三级菜单，提取出来
					if(isset($extModule['child']) && is_array($extModule['child'])){
						// error_log("  该模块有 " . count($extModule['child']) . " 个菜单项");
						foreach($extModule['child'] as $extMenuItem){
							if(!isset($extMenuItem['name'])){
								continue;
							}
							
							$extMenuKey = null;
							if(isset($extMenuItem['path'])){
								$extMenuKey = self::extractControllerFromPath($extMenuItem['path']);
							}
							
							if($extMenuKey && !isset($processedKeys[$extMenuKey])){
								// 添加扩展模块的菜单项
								// 注意：这里使用扩展模块名作为一级，实际菜单项作为二级
								$modules[] = [
									'key' => $extMenuKey,
									'name' => $extModuleName . ' - ' . $extMenuItem['name'],
									'group' => '扩展模块',
									'level' => 2,
									'parent' => $extModuleName, // 父级是扩展模块名称（如"预约服务"）
									'ext_module' => $extModuleName // 标记所属扩展模块
								];
								$processedKeys[$extMenuKey] = true;
								// error_log("    添加：{$extMenuKey} - {$extMenuItem['name']}");
							}
						}
					}else{
						// error_log("  该模块没有子菜单或不是数组");
					}
				}
			}else{
				// error_log("component 没有 child");
			}
		}else{
			// error_log("未找到 component 容器");
		}
		
		// 调试：输出扩展模块数量
		$extCount = 0;
		foreach($modules as $m){
			if($m['group'] == '扩展模块') $extCount++;
		}
		// error_log("总菜单数：" . count($modules) . "，扩展模块数：" . $extCount);
		
		return $modules;
	}
	
	
	/**
	 * 从路径中提取主控制器名称
	 * 例如：'Yuyue/index' -> 'Yuyue'
	 *      'Business/index' -> 'Business'
	 *      'ShopProduct/index&showtype=2' -> 'ShopProduct'
	 */
	private static function extractControllerFromPath($path){
		// 去掉查询参数
		$path = explode('&', $path)[0];
		$path = explode('?', $path)[0];

		// 提取控制器名称
		$parts = explode('/', $path);
		if(!empty($parts[0])){
			return $parts[0];
		}

		return null;
	}

	/**
	 * 获取插件菜单
	 * @param int $aid 商户ID
	 * @param array $user 用户信息
	 * @return array 插件菜单数组
	 */
	private static function getPluginMenus($aid, $user = []){
		try{
			// 检查表是否存在
			$prefix = config('database.connections.mysql.prefix');
			$fullTableName = $prefix . 'admin_plugin_menu';
			$result = Db::query("SHOW TABLES LIKE '{$fullTableName}'");
			if(empty($result)){
				return [];
			}

			// 获取所有启用的插件菜单
			$pluginMenus = Db::name('admin_plugin_menu')
				->where('aid', $aid)
				->where('status', 1)
				->order('sort', 'asc')
				->select()
				->toArray();

			if(empty($pluginMenus)){
				return [];
			}

			// 【修复】根据插件授权表过滤菜单
			if($aid > 0 && self::tableExists('admin_plugin_license')){
				$licensedPlugins = self::getLicensedPlugins($aid);
				if(!empty($licensedPlugins)){
					$pluginMenus = array_filter($pluginMenus, function($menu) use ($licensedPlugins){
						$pluginName = trim((string)($menu['plugin_name'] ?? ''));
						return $pluginName === '' || in_array($pluginName, $licensedPlugins);
					});
					$pluginMenus = array_values($pluginMenus);
				}else{
					// 如果没有任何授权，则不显示任何插件菜单
					return [];
				}
			}

			// 按插件名称分组
			$groupedMenus = [];
			foreach($pluginMenus as $menu){
				$pluginName = $menu['plugin_name'];
				if(!isset($groupedMenus[$pluginName])){
					$groupedMenus[$pluginName] = [];
				}
				$groupedMenus[$pluginName][] = $menu;
			}

			// 构建菜单结构
			$result = [];
			foreach($groupedMenus as $pluginName => $menus){
				$idMap = [];
				foreach($menus as $menu){
					$idMap[intval($menu['id'])] = $menu;
				}

				$topMenus = [];
				foreach($menus as $menu){
					$pid = intval($menu['pid'] ?? 0);
					if($pid === 0 || !isset($idMap[$pid])){
						$topMenus[] = $menu;
					}
				}

				usort($topMenus, function($a, $b){
					$sortCompare = intval($a['sort'] ?? 0) <=> intval($b['sort'] ?? 0);
					if($sortCompare !== 0){
						return $sortCompare;
					}
					return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
				});

				if(empty($topMenus)){
					continue;
				}

				$pluginTitle = PluginTopMenuConfig::resolvePluginTitle($pluginName, trim((string)($topMenus[0]['name'] ?? $pluginName)));
				$key = 'plugin_' . $pluginName;

				if(count($topMenus) === 1){
					$topMenu = $topMenus[0];
					$item = self::buildPluginMenuNode($topMenu, $menus);
					$item = self::filterMenuNodeByUserType($item, $user);
					if($item === null){
						continue;
					}
					$item['plugin_name'] = $pluginName;
					if(empty($item['fullname'])){
						$item['fullname'] = $item['name'] ?? $pluginTitle;
					}
					if(empty($item['icon'])){
						$item['icon'] = $topMenu['icon'] ?: 'AppstoreOutlined';
					}
					$result[$key] = $item;
					continue;
				}

				$children = [];
				foreach($topMenus as $topMenu){
					$child = self::filterMenuNodeByUserType(self::buildPluginMenuNode($topMenu, $menus), $user);
					if($child !== null){
						$children[] = $child;
					}
				}
				if(empty($children)){
					continue;
				}

				$result[$key] = [
					'name' => $pluginTitle,
					'fullname' => $pluginTitle,
					'icon' => $topMenus[0]['icon'] ?: 'AppstoreOutlined',
					'child' => $children,
					'plugin_name' => $pluginName,
					'__plugin_wrap' => 1,
				];
			}

			return $result;

		}catch(\Exception $e){
			// 出错时返回空数组，不影响其他菜单
			return [];
		}
	}

	private static function buildPluginMenuNode(array $menu, array $allMenus): array
	{
		$authData = trim((string)($menu['auth_data'] ?? ''));
		$vuePath = $menu['vue_path'] ?? '';
		$legacyVuePath = PluginTopMenuConfig::legacyAdminVuePath($menu);
		if ($legacyVuePath !== '') {
			$vuePath = $legacyVuePath;
		}
		$item = [
			'name' => $menu['name'] ?? '',
			'fullname' => $menu['name'] ?? '',
			'path' => $menu['path'] ?? '',
			'vue_path' => $vuePath,
			'authdata' => $authData !== '' ? $authData : (!empty($menu['path']) ? ($menu['path'] . '/*') : ''),
			'icon' => $menu['icon'] ?? '',
			'hide' => false,
			'auth_mode' => intval($menu['auth_mode'] ?? 0),
			'auth_data' => $menu['auth_data'] ?? '',
			'admin_only' => intval($menu['admin_only'] ?? 0),
			'merchant_visible' => intval($menu['merchant_visible'] ?? 1),
			'tuanzhang_visible' => intval($menu['tuanzhang_visible'] ?? 0),
		];

		$children = [];
		foreach($allMenus as $childMenu){
			if(intval($childMenu['pid'] ?? 0) === intval($menu['id'] ?? 0)){
				$children[] = self::buildPluginMenuNode($childMenu, $allMenus);
			}
		}

		if(!empty($children)){
			usort($children, function($a, $b){
				$sortA = intval($a['sort'] ?? 0);
				$sortB = intval($b['sort'] ?? 0);
				if($sortA !== $sortB){
					return $sortA <=> $sortB;
				}
				return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
			});
			$item['authdata'] = '';
			$item['child'] = $children;
		}

		$item['sort'] = intval($menu['sort'] ?? 0);
		return $item;
	}

	private static function filterMenuNodeByUserType(?array $item, array $user = []): ?array
	{
		if($item === null){
			return null;
		}

		$bid = intval($user['bid'] ?? 0);
		$isMerchant = $bid > 0;
		if($isMerchant){
			if(intval($item['admin_only'] ?? 0) === 1){
				return null;
			}
			if(intval($item['merchant_visible'] ?? 1) === 0){
				return null;
			}
		}

		if(!empty($item['child']) && is_array($item['child'])){
			$children = [];
			foreach($item['child'] as $child){
				$filteredChild = self::filterMenuNodeByUserType($child, $user);
				if($filteredChild !== null){
					$children[] = $filteredChild;
				}
			}
			$item['child'] = $children;
		}

		$hasChildren = !empty($item['child']);
		$hasPath = !empty($item['path']) || !empty($item['vue_path']);
		if(!$hasChildren && !$hasPath){
			return null;
		}

		return $item;
	}

	/**
	 * 获取站点已授权的插件列表
	 *
	 * @param int $aid 站点ID
	 * @return array 已授权且启用的插件名称数组
	 */
	private static function getLicensedPlugins(int $aid): array
	{
		if($aid <= 0){
			return [];
		}

		try{
			$licenses = Db::name('admin_plugin_license')
				->where('aid', $aid)
				->where('status', 1)
				->column('plugin_name');

			// 过滤并规范化插件名称
			$result = [];
			foreach($licenses as $pluginName){
				$pluginName = trim((string)$pluginName);
				if($pluginName !== ''){
					$result[] = $pluginName;
				}
			}

			return array_values(array_unique($result));
		}catch(\Throwable $e){
			return [];
		}
	}

}
