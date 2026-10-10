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

namespace app\admin\model;
use think\facade\Db;
use think\facade\Log;
class Payorder
{
	/**
	 * 补结算企业礼品卡额度兑换。
	 *
	 * 支付平台可能重复通知，或在支付单已标记成功后业务结算短暂失败；
	 * 因此每次进入商城支付成功链路都允许按 payorderid 重试。额度服务内部
	 * 以 status=0 行锁保证余额沉淀和额度完成状态只会执行一次。
	 */
	private static function settleEnterpriseQuotaPayment(array $payorder, string $source): void
	{
		if (!in_array(strval($payorder['type'] ?? ''), ['shop', 'shop_hb'], true)
			|| !class_exists('\\app\\admin\\common\\Lipin2ProductRedeemService')) {
			return;
		}
		try {
			\app\admin\common\Lipin2ProductRedeemService::settleEnterpriseQuotaPayorder($payorder);
		} catch (\Throwable $e) {
			Log::error('企业福利额度支付结算失败：' . $e->getMessage(), [
				'payorder_id' => intval($payorder['id'] ?? 0),
				'order_type' => strval($payorder['type'] ?? ''),
				'source' => $source,
			]);
		}
	}

	private static function tableExists(string $tableName): bool
	{
		try {
			$prefix = config('database.connections.mysql.prefix', '');
			$tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
			if ($tableName === '') {
				return false;
			}
			$rows = Db::query("SHOW TABLES LIKE '{$prefix}{$tableName}'");
			return !empty($rows);
		} catch (\Throwable $e) {
			return false;
		}
	}

	private static function hasTableColumn(string $tableName, string $columnName): bool
	{
		static $cache = [];
		$key = $tableName . '.' . $columnName;
		if (isset($cache[$key])) return $cache[$key];

		try {
			$table = Db::name($tableName)->getTable();
			$table = trim(str_replace('`', '', $table));
			if ($table === '') {
				$cache[$key] = false;
				return false;
			}

			$parts = array_values(array_filter(explode('.', $table), function ($p) {
				return trim($p) !== '';
			}));
			$quotedTable = implode('.', array_map(function ($p) {
				return '`' . str_replace('`', '', trim($p)) . '`';
			}, $parts));
			$col = addslashes(str_replace('`', '', $columnName));
			$rows = Db::query("SHOW COLUMNS FROM {$quotedTable} LIKE '{$col}'");
			$cache[$key] = !empty($rows);
			return $cache[$key];
		} catch (\Throwable $e) {
			$cache[$key] = false;
			return false;
		}
	}

	private static function hasEnabledPresaleVoucherPlugin(int $aid): bool
	{
		static $cache = [];
		if ($aid <= 0) return false;
		if (isset($cache[$aid])) return $cache[$aid];

		try {
			if (self::tableExists('admin_plugin_license')) {
				$licenseQuery = Db::name('admin_plugin_license')
					->where('plugin_name', 'presalevoucher');
				if (self::hasTableColumn('admin_plugin_license', 'aid')) {
					$licenseQuery->where('aid', $aid);
				}
				$license = $licenseQuery->find();
				if ($license) {
					$statusOk = !self::hasTableColumn('admin_plugin_license', 'status') || intval($license['status'] ?? 0) === 1;
					$expireTime = self::hasTableColumn('admin_plugin_license', 'expire_time') ? intval($license['expire_time'] ?? 0) : 0;
					$cache[$aid] = $statusOk && ($expireTime === 0 || $expireTime > time());
					return $cache[$aid];
				}
			}

			if (!self::tableExists('admin_plugin_menu')) {
				$cache[$aid] = false;
				return false;
			}

			$menuQuery = Db::name('admin_plugin_menu')
				->where('plugin_name', 'presalevoucher')
				->where('status', 1);
			if (self::hasTableColumn('admin_plugin_menu', 'aid')) {
				$menuQuery->where('aid', $aid);
			}
			$cache[$aid] = (bool)$menuQuery->find();
			return $cache[$aid];
		} catch (\Throwable $e) {
			$cache[$aid] = false;
			return false;
		}
	}

	private static function getPresaleVoucherServiceClass(int $aid): string
	{
		if (!self::hasEnabledPresaleVoucherPlugin($aid)) return '';
		if (!self::tableExists('presalevoucher_product')) return '';
		$serviceClass = '\\app\\plugin\\presalevoucher\\common\\PresaleVoucherService';
		return class_exists($serviceClass) ? $serviceClass : '';
	}

	private static function getAdoptionServiceClass(): string
	{
		$serviceClass = '\\app\\plugin\\adoption\\common\\AdoptionService';
		if (!class_exists($serviceClass) || !self::tableExists('adoption_contract')) {
			return '';
		}
		return $serviceClass;
	}

	private static function settleAdoptionBalance(array $payorder, string $source): void
	{
		$serviceClass = self::getAdoptionServiceClass();
		if ($serviceClass === '') {
			throw new \Exception('认养插件未安装或数据表不可用');
		}
		try {
			$serviceClass::ensureBalanceSettled($payorder);
		} catch (\Throwable $e) {
			Log::error('认养尾款结算失败(' . $source . ')：' . $e->getMessage());
			throw $e;
		}
	}

	private static function issueAdoptionContractsByPayorder(array $payorder, string $source): void
	{
		$type = strval($payorder['type'] ?? '');
		$aid = intval($payorder['aid'] ?? 0);
		if ($aid <= 0 || !in_array($type, ['shop', 'shop_hb'], true)) {
			return;
		}
		$serviceClass = self::getAdoptionServiceClass();
		if ($serviceClass === '') {
			return;
		}
		$orderIds = [];
		if ($type === 'shop') {
			$orderIds[] = intval($payorder['orderid'] ?? 0);
		} else {
			$ordernum = trim(strval($payorder['ordernum'] ?? ''));
			if ($ordernum !== '') {
				$orderIds = Db::name('shop_order')->where('aid', $aid)->where('ordernum', 'like', $ordernum . '%')->column('id');
			}
		}
		foreach (($orderIds ?: []) as $shopOrderId) {
			try {
				$serviceClass::issueByShopOrder($aid, intval($shopOrderId));
			} catch (\Throwable $e) {
				Log::error('认养合同生成失败(' . $source . ')：' . $e->getMessage());
			}
		}
	}

	private static function enqueueJushuitanPaidOrders(array $payorder, string $source): void
	{
		$type = strval($payorder['type'] ?? '');
		$aid = intval($payorder['aid'] ?? 0);
		if ($aid <= 0 || !in_array($type, ['shop', 'shop_hb'], true)) {
			return;
		}
		if (!class_exists('\\app\\plugin\\jushuitan\\service\\JushuitanOrderService')
			|| !self::hasEnabledJushuitanPlugin($aid)) {
			return;
		}

		$orderIds = [];
		if ($type === 'shop') {
			$orderIds[] = intval($payorder['orderid'] ?? 0);
		} else {
			$ordernum = trim(strval($payorder['ordernum'] ?? ''));
			if ($ordernum !== '') {
				try {
					$orderIds = Db::name('shop_order')
						->where('aid', $aid)
						->where('ordernum', 'like', $ordernum . '%')
						->column('id');
				} catch (\Throwable $e) {
					Log::warning('聚水潭合并订单查询失败(' . $source . ')：' . $e->getMessage());
					return;
				}
			}
		}

		foreach (($orderIds ?: []) as $orderId) {
			try {
				\app\plugin\jushuitan\service\JushuitanOrderService::enqueuePaidOrder(
					$aid,
					intval($orderId)
				);
			} catch (\Throwable $e) {
				Log::warning('聚水潭订单入队失败(' . $source . ')：' . $e->getMessage(), [
					'aid' => $aid,
					'order_id' => intval($orderId),
				]);
			}
		}
	}

	private static function hasEnabledJushuitanPlugin(int $aid): bool
	{
		static $cache = [];
		if ($aid <= 0) {
			return false;
		}
		if (isset($cache[$aid])) {
			return $cache[$aid];
		}

		try {
			if (class_exists('\\app\\plugin\\jushuitan\\service\\JushuitanConfigService')) {
				$cache[$aid] = \app\plugin\jushuitan\service\JushuitanConfigService::isReady($aid);
				return $cache[$aid];
			}
			$cache[$aid] = false;
			return false;
		} catch (\Throwable $e) {
			$cache[$aid] = false;
			return false;
		}
	}

	private static function isRestaurantMergedPayorderType(string $type): bool
	{
		return in_array($type, ['restaurant_shop_hb', 'restaurant_takeaway_hb'], true);
	}

	private static function getRestaurantMergedChildPayorders(array $payorder): array
	{
		$type = strval($payorder['type'] ?? '');
		$childType = $type === 'restaurant_shop_hb'
			? 'restaurant_shop'
			: ($type === 'restaurant_takeaway_hb' ? 'restaurant_takeaway' : '');
		$aid = intval($payorder['aid'] ?? 0);
		$ordernum = trim(strval($payorder['ordernum'] ?? ''));
		if ($childType === '' || $aid <= 0 || $ordernum === '') {
			return [];
		}

		return Db::name('payorder')
			->where('aid', $aid)
			->where('type', $childType)
			->where('ordernum', 'like', $ordernum . '_%')
			->order('id asc')
			->select()
			->toArray();
	}

	private static function settleRestaurantMergedOrders(array $payorder, string $paytype, int $paytypeid, string $paynum): void
	{
		$type = strval($payorder['type'] ?? '');
		$orderTable = $type === 'restaurant_shop_hb'
			? 'restaurant_shop_order'
			: ($type === 'restaurant_takeaway_hb' ? 'restaurant_takeaway_order' : '');
		if ($orderTable === '') {
			return;
		}

		$now = time();
		foreach (self::getRestaurantMergedChildPayorders($payorder) as $childPayorder) {
			$childOrderId = intval($childPayorder['orderid'] ?? 0);
			if ($childOrderId <= 0) {
				continue;
			}
			Db::name($orderTable)
				->where('aid', intval($payorder['aid'] ?? 0))
				->where('id', $childOrderId)
				->where('status', 0)
				->update([
					'status' => 1,
					'paytime' => $now,
					'paytype' => $paytype,
					'paytypeid' => $paytypeid,
					'paynum' => $paynum,
					'platform' => strval($payorder['platform'] ?? ''),
				]);
			Db::name('payorder')
				->where('id', intval($childPayorder['id'] ?? 0))
				->where('status', 0)
				->update([
					'status' => 1,
					'paytime' => $now,
					'paytype' => $paytype,
					'paytypeid' => $paytypeid,
					'paynum' => $paynum,
				]);
		}
	}

	private static function commitRestaurantStoredValueCouponForPayorder(array $payorder, string $scene): bool
	{
		if (!class_exists('\\app\\plugin\\restaurant\\common\\RestaurantStoredValueCoupon')) {
			return false;
		}
		$type = strval($payorder['type'] ?? '');
		if (!in_array($type, ['restaurant_shop', 'restaurant_takeaway', 'restaurant_shop_hb', 'restaurant_takeaway_hb'], true)) {
			return false;
		}
		if ($type === 'restaurant_shop' && intval($payorder['paytypeid'] ?? 0) === 4) {
			return false;
		}

		$aid = intval($payorder['aid'] ?? 0);
		if (in_array($type, ['restaurant_shop', 'restaurant_takeaway'], true)) {
			return \app\plugin\restaurant\common\RestaurantStoredValueCoupon::commit(
				$aid,
				intval($payorder['orderid'] ?? 0),
				$type,
				$scene
			);
		}

		$committed = false;
		foreach (self::getRestaurantMergedChildPayorders($payorder) as $childPayorder) {
			$committed = \app\plugin\restaurant\common\RestaurantStoredValueCoupon::commit(
				$aid,
				intval($childPayorder['orderid'] ?? 0),
				strval($childPayorder['type'] ?? ''),
				$scene
			) || $committed;
		}
		return $committed;
	}

	private static function hasEnabledJiedianRewardPlugin(int $aid): bool
	{
		static $cache = [];
		if ($aid <= 0) return false;
		if (isset($cache[$aid])) return $cache[$aid];

		try {
			if (self::tableExists('admin_plugin_license')) {
				$licenseQuery = Db::name('admin_plugin_license')
					->where('plugin_name', 'jiedianreward');
				if (self::hasTableColumn('admin_plugin_license', 'aid')) {
					$licenseQuery->where('aid', $aid);
				}
				$license = $licenseQuery->find();
				if ($license) {
					$statusOk = !self::hasTableColumn('admin_plugin_license', 'status') || intval($license['status'] ?? 0) === 1;
					$expireTime = self::hasTableColumn('admin_plugin_license', 'expire_time') ? intval($license['expire_time'] ?? 0) : 0;
					$cache[$aid] = $statusOk && ($expireTime === 0 || $expireTime > time());
					return $cache[$aid];
				}
			}

			if (!self::tableExists('admin_plugin_menu')) {
				$cache[$aid] = false;
				return false;
			}

			$menuQuery = Db::name('admin_plugin_menu')
				->where('plugin_name', 'jiedianreward')
				->where('status', 1);
			if (self::hasTableColumn('admin_plugin_menu', 'aid')) {
				$menuQuery->where('aid', $aid);
			}
			$cache[$aid] = (bool)$menuQuery->find();
			return $cache[$aid];
		} catch (\Throwable $e) {
			$cache[$aid] = false;
			return false;
		}
	}

	private static function canRunJiedianReward(int $aid): bool
	{
		return self::hasEnabledJiedianRewardPlugin($aid)
			&& self::tableExists('jiedian_reward_settings')
			&& self::tableExists('jiedian_reward_records')
			&& class_exists('\\app\\admin\\common\\JiedianRewardService');
	}

	private static function canUsePaiduiQueueTables(): bool
	{
		return self::tableExists('paidui_set')
			&& self::tableExists('paidui_maidan_set')
			&& self::tableExists('paidui_list');
	}

	private static function issuePresaleVouchersByPayorder(array $payorder, string $source): void
	{
		$type = strval($payorder['type'] ?? '');
		$aid = intval($payorder['aid'] ?? 0);
		if ($aid <= 0 || !in_array($type, ['shop', 'shop_hb'], true)) {
			return;
		}
		$serviceClass = self::getPresaleVoucherServiceClass($aid);
		if ($serviceClass === '') {
			return;
		}
		if ($type === 'shop') {
			try {
				$serviceClass::issueByShopOrder($aid, intval($payorder['orderid'] ?? 0));
			} catch (\Throwable $e) {
				Log::error('预售券发券失败(' . $source . ')：' . $e->getMessage());
			}
			return;
		}

		$ordernum = trim(strval($payorder['ordernum'] ?? ''));
		if ($ordernum === '') {
			return;
		}
		$shopOrderIds = Db::name('shop_order')
			->where('aid', $aid)
			->where('ordernum', 'like', $ordernum . '%')
			->column('id');
		foreach (($shopOrderIds ?: []) as $shopOrderId) {
			try {
				$serviceClass::issueByShopOrder($aid, intval($shopOrderId));
			} catch (\Throwable $e) {
				Log::error('预售券发券失败(' . $source . '_hb)：' . $e->getMessage());
			}
		}
	}

	private static function syncLiveOrderAttributionPaid(array $payorder, string $paytype, int $paytypeid): void
	{
		$type = strval($payorder['type'] ?? '');
		$aid = intval($payorder['aid'] ?? 0);
		if($aid <= 0 || !in_array($type, ['shop', 'shop_hb'], true) || !self::tableExists('live_web_order_attribution')){
			return;
		}

		try {
			$query = Db::name('shop_order')
				->where('aid', $aid)
				->field('id,totalprice,refund_money,status,paytime');
			if($type === 'shop'){
				$query->where('id', intval($payorder['orderid'] ?? 0));
			}else{
				$ordernum = trim(strval($payorder['ordernum'] ?? ''));
				if($ordernum === ''){
					return;
				}
				$query->where('ordernum', 'like', $ordernum.'%');
			}
			$orders = $query->select()->toArray();
			foreach(($orders ?: []) as $order){
				$paytime = intval($order['paytime'] ?? 0) > 0 ? intval($order['paytime']) : time();
				Db::name('live_web_order_attribution')
					->where('aid', $aid)
					->where('orderid', intval($order['id'] ?? 0))
					->update([
						'payorderid' => intval($payorder['id'] ?? 0),
						'totalprice' => round(floatval($order['totalprice'] ?? 0), 2),
						'refund_money' => round(floatval($order['refund_money'] ?? 0), 2),
						'order_status' => intval($order['status'] ?? 1),
						'pay_status' => 1,
						'paytype' => substr($paytype, 0, 80),
						'paytypeid' => $paytypeid,
						'paytime' => $paytime,
						'updatetime' => time(),
					]);
			}
		} catch (\Throwable $e) {
			Log::warning('直播订单归因支付同步失败: ' . $e->getMessage());
		}
	}

	public static function syncJishouPaidOrderState(int $orderId): void
	{
		if ($orderId <= 0 || !class_exists('\\app\\plugin\\jishou\\Plugin')) {
			return;
		}

		try {
			\app\plugin\jishou\Plugin::ensureRuntimeSchema();
			$order = Db::name('shop_order')
				->where('id', $orderId)
				->field('id,aid,mid,miaoshaid,status,paytime')
				->find();
			if (!$order || intval($order['miaoshaid'] ?? 0) <= 0 || !in_array(intval($order['status'] ?? 0), [1, 2, 3], true)) {
				return;
			}

			$orderAid = intval($order['aid']);
			$miaoshaId = intval($order['miaoshaid']);

			$miaosha = Db::name('miaosha')
				->where('aid', $orderAid)
				->where('id', $miaoshaId)
				->field('id,goodsid,stock,sold,locked,prostatus2,sellid,neworderid')
				->find();
			if (!$miaosha) {
				return;
			}

			$now = intval($order['paytime'] ?? 0) > 0 ? intval($order['paytime']) : time();

			$multiStockEnabled = class_exists('\\app\\plugin\\jishou\\JishouStockService')
				&& \app\plugin\jishou\JishouStockService::isMultiStockEnabled($orderAid);
			$product = Db::name('miaosha_product')
				->where('aid', $orderAid)
				->where('id', intval($miaosha['goodsid'] ?? 0))
				->field('id,trade_mode,status2')
				->find();
			$isAuction = intval($product['trade_mode'] ?? 0) === 1;
			$isMultiStock = $multiStockEnabled && !$isAuction;

			if ($isMultiStock) {
				\app\plugin\jishou\JishouStockService::confirmSold($miaoshaId, $orderAid, 1);

				$freshMiaosha = Db::name('miaosha')
					->where('id', $miaoshaId)
					->where('aid', $orderAid)
					->field('stock,sold,locked')
					->find();
				$remaining = \app\plugin\jishou\JishouStockService::getRemaining($freshMiaosha ?: []);

				$miaoshaUpdate = ['paytime' => $now];
				if ($remaining <= 0) {
					$miaoshaUpdate['prostatus2'] = 4;
				} else {
					$miaoshaUpdate['prostatus2'] = 2;
				}
				if (intval($miaosha['sellid'] ?? 0) <= 0) {
					$miaoshaUpdate['sellid'] = intval($order['mid'] ?? 0);
				}
				Db::name('miaosha')
					->where('aid', $orderAid)
					->where('id', $miaoshaId)
					->update($miaoshaUpdate);

				$goodsId = intval($miaosha['goodsid'] ?? 0);
				if ($goodsId > 0 && $remaining <= 0) {
					Db::name('miaosha_product')
						->where('aid', $orderAid)
						->where('id', $goodsId)
						->update(['status2' => 4]);
				}
				if ($remaining > 0) {
					\app\plugin\jishou\JishouStockService::refreshSellOrderStatus($miaoshaId, $orderAid);
				}
			} else {
				$miaoshaUpdate = [];
				if (intval($miaosha['prostatus2'] ?? 0) < 4) {
					$miaoshaUpdate['prostatus2'] = 4;
				}
				if (intval($miaosha['sellid'] ?? 0) <= 0) {
					$miaoshaUpdate['sellid'] = intval($order['mid'] ?? 0);
				}
				if (intval($miaosha['neworderid'] ?? 0) <= 0) {
					$miaoshaUpdate['neworderid'] = intval($order['id'] ?? 0);
				}
				$miaoshaUpdate['paytime'] = $now;
				if ($miaoshaUpdate) {
					Db::name('miaosha')
						->where('aid', $orderAid)
						->where('id', intval($miaosha['id']))
						->update($miaoshaUpdate);
				}

				$goodsId = intval($miaosha['goodsid'] ?? 0);
				if ($goodsId > 0) {
					$productStatus = intval(Db::name('miaosha_product')
						->where('aid', $orderAid)
						->where('id', $goodsId)
						->value('status2'));
					if ($productStatus < 4) {
						Db::name('miaosha_product')
							->where('aid', $orderAid)
							->where('id', $goodsId)
							->update(['status2' => 4]);
					}
				}
			}
		} catch (\Throwable $e) {
			Log::warning('Payorder::syncJishouPaidOrderState failed', [
				'orderid' => $orderId,
				'error' => $e->getMessage(),
			]);
		}
	}

	//创建支付订单
	public static function createorder($aid,$bid,$mid,$type,$orderid,$ordernum,$title,$money,$score=0,$pay_s_info=""){
		$data = [];
		$data['aid'] = $aid;
		$data['bid'] = $bid;
		$data['mid'] = $mid;
		$data['orderid'] = $orderid;
		$data['ordernum'] = $ordernum;
		$data['title'] = $title;
		$data['money'] = $money;
        $data['s_money'] = 0;
        $data['qixian_zhongheng'] = 0;
		$data['score'] = $score;
		$data['pay_s_info'] = $pay_s_info;
		$data['type'] = $type; //shop collage scoreshop kanjia seckill recharge designerpage form
		$data['createtime'] = time();
		$id = Db::name('payorder')->insertGetId($data);
        // var_dump($data);die;
		if($type == 'shop_hb'){
			Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->update(['payorderid'=>$id]);
		}elseif($type == 'allianceactivity'){
			Db::name('allianceactivity_order')->where('id',$orderid)->update(['payorder_id'=>$id]);
		}elseif($type == 'yunkucun_delivery'){
			// 云仓提货运费支付单不直接更新业务订单表（提货主表无 payorderid 字段）
		}elseif($type == 'adoption_balance'){
			// 认养合同和商城订单的尾款支付单 ID 由 AdoptionService 统一回写
		}elseif($type == 'daojia_supplement'){
			// 补差价 payorderid 由到家插件回写，避免误更新 daojia_order
		}else{
			// 部分业务订单表可能没有 payorderid 字段（例如 hbtk_order / allianceactivity_order），避免直接报错导致无法下单
			try{
			Db::name(''.$type.'_order')->where('id',$orderid)->update(['payorderid'=>$id]);
			}catch(\Throwable $e){
				// 仅记录日志，不影响返回 payorder 的 id（前端仍可用 /shopPackage/pay/pay?id=xx 正常支付）
				Log::warning('Payorder::createorder update payorderid failed, type='.$type.', orderid='.$orderid.', err='.$e->getMessage());
			}
		}
		return $id;
	}
	//修改订单
	public static function updateorder($id,$newOrdernum,$newprice,$updateOrderId=0){

//        // 查询订单原价
//        $order_info = Db::name('shop_order')->where('aid',aid)->where('id', $orderid)->find();
//        // 查询订单原来应该支付金额
//        $pay_money = Db::name('payorder')->where('aid',aid)->where('id', $order_info['payorderid'])->value('money');
//        // 计算修改差价
//        $order_num = explode('_', $order_info['ordernum'])[0] ?? $order_info['ordernum'] ; // 原支付订单号
//        // 查询同笔订单信息
//        $shop_order_info = Db::name('shop_order')->where('aid',aid)->where('ordernum', 'like',  $order_num . '%' )->select()->toArray();
//
//        // print_r($shop_order_info);die;
//
//        foreach ($shop_order_info as $k=>$v){
//            // 修改订单号
//            $new_order_num = str_replace($order_num, $newordernum, $v['ordernum']) ;
//            $update_arr = [ 'ordernum'=>$new_order_num  ] ;
//            // 只有当前修改的订单改价 其它子订单只修改单号 解决支付出现重复单号-金额不同问题
//            if($v['id'] == $orderid){
//                $update_arr['totalprice'] = $newprice ; // 后台改动后金额
//            }
//            Db::name('shop_order')->where('aid',aid)->where('id',$v['id'])->update($update_arr);
//            Db::name('shop_order_goods')->where('aid',aid)->where('orderid',$v['id'])->update(['ordernum'=>$new_order_num]);
//            // 修改子支付订单 订单号
//            Db::name('payorder')->where('ordernum', $v['ordernum'])->update(['ordernum'=>$new_order_num]);
//        }
//
//        // 计算差价 修改 总支付订单差价
//        if($order_info['totalprice'] > $newprice){
//            $newprice = bcsub($pay_money, bcsub($order_info['totalprice'], $newprice, 2), 2) ;
//        }else{
//            $newprice = bcadd($pay_money, bcsub($newprice, $order_info['totalprice'], 2), 2) ;
//        }

        $payorder = Db::name('payorder')->where('id',$id)->find();
        if(in_array($payorder['type'],['shop_hb','restaurant_shop_hb','restaurant_takeaway_hb'])){
            //合并支付订单重新计算
            // 查询同笔订单信息
            $child_order = Db::name('payorder')->where('aid',$payorder['aid'])->where('ordernum', 'like',  $payorder['ordernum'] . '_%' )->select()->toArray();
            //修改子价格，修改订单号
            if($child_order){
                $totalprice = 0;
                $newOrdernumArr = explode('_',$newOrdernum);
                $newOrdernum = $newOrdernumArr[0];
                foreach ($child_order as $v){
                    // 修改订单号
                    $order_num = explode('_',$v['ordernum']);
                    $new_order_num = str_replace($order_num[0], $newOrdernum, $v['ordernum']) ;
                    $update_arr = ['ordernum'=>$new_order_num] ;
                    if($v['orderid'] == $updateOrderId){
                        $update_arr['money'] = $newprice ; //改动后金额
                        $totalprice += $newprice;
                    }else{
                        $totalprice += $v['money'];
                        $where=[];
                        $where[]=['aid','=',$v['aid']];
                        $where[]=['id','=',$v['orderid']];
                        Db::name($v['type'].'_order')->where($where)->where('id',$v['orderid'])->update($update_arr);
                        Db::name($v['type'].'_order_goods')->where($where)->where('orderid',$v['orderid'])->update($update_arr);
                    }
                    Db::name('payorder')->where('id',$v['id'])->update($update_arr);
                }

                //修改主价格，修改订单号
                Db::name('payorder')->where('id',$id)->update(['ordernum'=>$newOrdernum,'money'=>$totalprice]);
            }
        }else{
            Db::name('payorder')->where('id',$id)->update(['ordernum'=>$newOrdernum,'money'=>$newprice]);
        }
	}
	
	//商店支付订单;
	public static function shop_pay_order($orderid)
	{
// 	    $member = Db::name('member')->where('id',$order['mid'])->find();
// 	    $tmplcontent = array();
// 		$tmplcontent['first'] = '该订单支付成功';
// 		$tmplcontent['remark'] = '点击进入查看~';
// 		$tmplcontent['accountType'] = t('会员').'昵称';
// 		$tmplcontent['account'] = $member['nickname'];
// 		$tmplcontent['amount'] = $order['money'].'元' . ($givemoney>0?'+赠送'.$givemoney.'元':'');
// 		$tmplcontent['result'] = '支付成功';
// 		\app\admin\common\Wechat::sendhttmpl($aid,0,'tmpl_recharge',$tmplcontent,m_url('admin/finance/rechargelog', $aid),$order['mdid']);
	}
	
	//充值订单
	public static function recharge_pay_no_add_member($orderid){
		$order = Db::name('recharge_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];

		// \app\admin\common\Member::addmoney($aid,$mid,$order['money'],t('余额').'充值');
		//充值赠送
		$giveset = Db::name('recharge_giveset')->where('aid',$aid)->find();
		if($giveset && $giveset['status']==1){
			$givedata = json_decode($giveset['givedata'],true);
		}else{
			$givedata = array();
		}
		$givemoney = 0;
		$givescore = 0;
		$moneyduan = 0;
		if($givedata){
			foreach($givedata as $give){
				if($order['money']*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
					$moneyduan = $give['money']*1;
					$givemoney = $give['give']*1;
                    $givescore = $give['give_score']*1;
				}
			}
		}
		if($givemoney > 0){
			// \app\admin\common\Member::addmoney($aid,$mid,$givemoney,'充值赠送');
		}
        if($givescore > 0){
            \app\admin\common\Member::addscore($aid,$mid,$givescore,'充值赠送');
        }



		//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'recharge',$order['money']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}

		//升级
			\app\admin\common\Member::triggerUplv($aid, $mid, [
				'scene' => \app\admin\common\MemberUpgradeScene::RECHARGE_PAID,
				'source_type' => 'recharge_order',
				'source_id' => strval($order['id'] ?? ''),
			]);


		$tmplcontent = array();
		$tmplcontent['first'] = '有新充值订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['accountType'] = t('会员').'昵称';
		$tmplcontent['account'] = $member['nickname'];
		$tmplcontent['amount'] = $order['money'].'元' . ($givemoney>0?'+赠送'.$givemoney.'元':'');
		$tmplcontent['result'] = '充值成功';
		\app\admin\common\Wechat::sendhttmpl($aid,0,'tmpl_recharge',$tmplcontent,m_url('admin/finance/rechargelog', $aid),$order['mdid']);

		//充值通知
        if(getcustom('zhaopin')){
            \app\admin\model\Zhaopin::sendhtsms('tmpl_recharge',[],$order['aid'],$order['bid'],$order['mdid']);
        }
	}
	
	public  static function payorder_by_shop($orderid,$paytype,$paytypeid,$paynum='')
	{
	      if(!$orderid) return;
		$payorder = Db::name('payorder')->where('id',$orderid)->find();
		//dump($payorder.'看看支付后的操作');
		if(!$payorder) return ['status'=>0,'msg'=>'支付订单不存在'];
		if($payorder['status']==1){
			self::settleEnterpriseQuotaPayment($payorder, 'payorder_by_shop_repeat');
			if(self::commitRestaurantStoredValueCouponForPayorder($payorder, 'payorder_by_shop_repeat')){
				return ['status'=>1,'msg'=>'订单已支付，储值优惠券已结算'];
			}
			if($payorder['type'] === 'shop' && class_exists('\\app\\plugin\\storedvaluecoupon\\common\\StoredValueCouponService')){
				try{
					\app\plugin\storedvaluecoupon\common\StoredValueCouponService::commit(
						intval($payorder['aid'] ?? 0),
						intval($payorder['orderid'] ?? 0),
						['source' => 'payorder_repeat']
					);
					return ['status'=>1,'msg'=>'订单已支付，储值优惠券已结算'];
				}catch(\Throwable $e){
					\think\facade\Log::error('储值优惠券重复支付补结算失败：' . $e->getMessage());
				}
			}
			if($payorder['type'] == 'digitalavatar_package' && class_exists('\\app\\plugin\\digitalavatar\\common\\DigitalAvatarService')){
				try{
					\app\plugin\digitalavatar\common\DigitalAvatarService::onPackageOrderPaid(intval($payorder['orderid']), strval($payorder['paytype'] ?? '在线支付'));
					return ['status'=>1,'msg'=>'订单已支付，业务已结算'];
				}catch(\Throwable $e){
					\think\facade\Log::error('AI数字人套餐已支付订单补结算失败：orderid='.$payorder['orderid'].', err='.$e->getMessage());
					return ['status'=>0,'msg'=>$e->getMessage()];
				}
			}
			return ['status'=>0,'msg'=>'该订单已支付'];
		}
        
        
		if(getcustom('pay_yuanbao') && $payorder['type'] == 'shop'){
            if($payorder['is_yuanbao_pay'] == 1){
                $paytype = t('元宝').'支付+'.$paytype;
            }
            //元宝 更新shop_order和payorder
            self::yuanbao_up($payorder['type'],$payorder);
        }
       

		if($payorder['type'] == 'restaurant_shop' && $paytypeid == 4) {
            Db::name('payorder')->where('id',$orderid)->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum]);
        } else {
            Db::name('payorder')->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum]);
        }
		$type = $payorder['type'];
 

		if($type == 'shop_hb'){
		    
			Db::name('shop_order')->where('ordernum','like',$payorder['ordernum'].'%')->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif(self::isRestaurantMergedPayorderType($type)){
			self::settleRestaurantMergedOrders($payorder, $paytype, intval($paytypeid), $paynum);
		}elseif($type == 'balance'){
			Db::name('shop_order')->where('id',$payorder['orderid'])->update(['balance_pay_status'=>1,'balance_pay_orderid'=>$orderid]);
			return ['status'=>1,'msg'=>''];
		}elseif($type == 'adoption_balance'){
			self::settleAdoptionBalance($payorder, 'payorder_by_shop');
			return ['status'=>1,'msg'=>''];
		}elseif($type == 'yuyue_balance'){
			Db::name('yuyue_order')->where('id',$payorder['orderid'])->update(['status'=>3,'balance_pay_status'=>1,'balance_pay_orderid'=>$orderid]);
		}elseif($type == 'yuyue_addmoney'){
			Db::name('yuyue_order')->where('id',$payorder['orderid'])->update(['addmoneyStatus'=>1]);
		}elseif($type == 'daojia'){
			Db::name('daojia_order')->where('id',$payorder['orderid'])->update([
				'status'=>1,
				'pay_status'=>1,
				'paytime'=>time(),
				'paytype'=>$paytype,
				'payorderid'=>$orderid,
				'updatetime'=>time(),
			]);
		}elseif($type == 'daojia_supplement'){
			// 到家补差价不改主订单支付状态，金额在 daojia_supplement_pay 入账
		}elseif($type == 'xixie'){
			Db::name('xixie_order')->where('id',$payorder['orderid'])->update([
				'paytime'=>time(),
				'paytype'=>$paytype,
				'payorderid'=>$orderid,
				'updatetime'=>time(),
			]);
		}elseif($type == 'seckill2'){
			Db::name('seckill2_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
			$form_sale = Db::name('seckill2_order')->alias('or')->join('seckill2_product pro','or.proid = pro.id')->field('pro.saleid')->where('or.ordernum',$payorder['ordernum'])->find();
			$sale = Db::name('seckill2_sale')->where('id',$form_sale['saleid'])->find();
			Db::name('seckill2_order')->where('ordernum',$sale['form_ordernum'])->update(['status'=>10]);
		}elseif($type == 'form'){
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paystatus'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif($type == 'plug_business_pay'){
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'restaurant_shop' && $paytypeid == 4){
            // Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'workorder'){  //工单订单
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paystatus'=>1,'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'hbtk'){  //拓客活动
            // 获取用户 openid（用于分账）
            $member = Db::name('member')->where('id', $payorder['mid'])->field('wxopenid,mpopenid')->find();
            $openid = $member['wxopenid'] ?? ($member['mpopenid'] ?? '');
            
            // 如果 $paynum 为空，尝试从订单表获取
            if(empty($paynum)){
                $order = Db::name($type.'_order')->where('id',$payorder['orderid'])->field('paynum')->find();
                if($order && !empty($order['paynum'])){
                    $paynum = $order['paynum'];
                }
            }
            
            // 更新订单状态，并保存 openid 和 transaction_id（用于分账）
            $updateData = [
                'status'=>1,
                'paytime'=>time(),
                'paytype'=>$paytype,
                'paytypeid'=>$paytypeid,
                'platform'=>$payorder['platform']
            ];
            
            // 保存 paynum（即使为空也保存，后续可以通过其他方式获取）
            if(!empty($paynum)){
                $updateData['paynum'] = $paynum;
                $updateData['split_transaction_id'] = $paynum;  // 同时保存到 split_transaction_id
            }else{
                // 如果 paynum 为空，尝试从订单表获取（可能之前已经保存过）
                $order = Db::name($type.'_order')->where('id',$payorder['orderid'])->field('paynum')->find();
                if($order && !empty($order['paynum'])){
                    $updateData['split_transaction_id'] = $order['paynum'];
                }
            }
            
            // 如果有 openid，保存用于分账
            if(!empty($openid)){
                $updateData['split_openid'] = $openid;
            }
            
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update($updateData);
            
            // 记录日志
            \think\facade\Log::info('拓客活动支付回调(payorder_by_shop): order_id='.$payorder['orderid'].' paynum='.$paynum.' openid='.$openid.' split_transaction_id='.($updateData['split_transaction_id'] ?? '空').' split_openid='.($updateData['split_openid'] ?? '空').' updateData='.json_encode($updateData));
        }elseif($type == 'allianceactivity'){
            Db::name('allianceactivity_order')->where('id',$payorder['orderid'])->update(['update_time'=>time()]);
	        }elseif($type == 'yuyue_package'){  // 处理套餐订单特殊情况，使用pay_time而不是paytime
	            // 套餐订单表使用pay_time字段而不是paytime
	            Db::name($type.'_order')->where('id',$payorder['orderid'])->update([
                'status'=>1,
                'pay_time'=>time(),  // 注意：这里使用pay_time而不是paytime
                'paytype'=>$paytype,
                'paytypeid'=>$paytypeid,
                'paynum'=>$paynum,
                'platform'=>$payorder['platform']
	            ]);
	        }elseif($type == 'yunkucun_delivery'){
	            // 云仓提货运费支付单：不改变提货主订单状态（仅由 type 回调处理）
	        }elseif($type == 'agentrecharge'){
	            // 代理充值升级支付成功：交由插件结算并触发升级
	            if(class_exists('\\app\\plugin\\agentrecharge\\common\\AgentRechargeService')){
                try{
                    \app\plugin\agentrecharge\common\AgentRechargeService::onPaySuccess(intval($payorder['aid']), strval($payorder['ordernum']), strval($paynum));
                }catch(\Throwable $e){
                    \think\facade\Log::error('代理充值支付回调失败：'.$e->getMessage());
                }
		        }
			}elseif($type == 'presalevoucher_diff'){
				$serviceClass = self::getPresaleVoucherServiceClass(intval($payorder['aid'] ?? 0));
				if ($serviceClass !== '') {
					try{
						$serviceClass::confirmReservationPay($payorder);
					}catch(\Throwable $e){
						\think\facade\Log::error('预售券补差价支付回调失败：'.$e->getMessage());
					}
				}
			}elseif($type == 'lucky_collage'){
				Db::name('lucky_collage_order')->where('id',$payorder['orderid'])->update(['paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>strval($payorder['platform'] ?? '')]);
			}else{
				Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>strval($payorder['platform'] ?? '')]);
			}

			if($type == 'shop'){
				self::syncJishouPaidOrderState(intval($payorder['orderid'] ?? 0));
			}elseif($type == 'shop_hb'){
				$shopOrderIds = Db::name('shop_order')->where('ordernum','like',$payorder['ordernum'].'%')->column('id');
				foreach(($shopOrderIds ?: []) as $shopOrderId){
					self::syncJishouPaidOrderState(intval($shopOrderId));
				}
			}
			self::syncLiveOrderAttributionPaid($payorder, $paytype, intval($paytypeid));
			self::issuePresaleVouchersByPayorder($payorder, 'payorder_by_shop');
			self::issueAdoptionContractsByPayorder($payorder, 'payorder_by_shop');
			self::enqueueJushuitanPaidOrders($payorder, 'payorder_by_shop');
			self::commitRestaurantStoredValueCouponForPayorder($payorder, 'payorder_by_shop');

		if(getcustom('invite_free')){
        	if($payorder['type'] == 'shop'){
        		//发送订单通知
        		self::send_free_notice($payorder);
        	}
        }
		$fun = $type.'_pay';
		
		// 对于hbtk类型，调用抽奖逻辑
		if($type == 'hbtk'){
			$res = self::$fun($payorder['orderid'],$payorder['ordernum']);
			// 如果返回了抽奖类型，说明需要跳转到抽奖页面，将信息存储到订单中
			if(isset($res['lottery_type']) && $res['lottery_type'] > 1){
				// 保存抽奖类型到订单，供前端查询
				Db::name('hbtk_order')->where('id',$payorder['orderid'])->update(['lottery_type'=>$res['lottery_type']]);
			}
		}elseif($type == 'allianceactivity' && method_exists(__CLASS__, $fun)){
			$res = self::$fun($payorder['orderid'],$payorder['ordernum']);
		}elseif($type == 'fangyuan_staff_seat' && method_exists(__CLASS__, $fun)){
			$res = self::$fun($payorder['orderid'],$payorder['ordernum']);
			}elseif($type == 'daojia'){
				$res = self::$fun($payorder['orderid'],$payorder['ordernum']);
			}elseif($type == 'daojia_supplement' && method_exists(__CLASS__, $fun)){
				$res = self::$fun($payorder['orderid'],$payorder['ordernum']);
			}elseif($type == 'xixie' && method_exists(__CLASS__, $fun)){
				$res = self::$fun($payorder['orderid'],$payorder['ordernum']);
			}elseif($type == 'linggong' && method_exists(__CLASS__, $fun)){
				$res = self::$fun($payorder['orderid'],$payorder['ordernum']);
			}
		
		// $res = self::$fun($payorder['orderid'],$payorder['ordernum']);
       // $res = self::recharge_pay_no_add_member($payorder['orderid'],$payorder['ordernum']);
        self::afterusecoupon($payorder['orderid'],$type,1,$payorder['ordernum']);
        
//         //升级
// 		\app\admin\common\Member::uplv($aid,$mid);
        
        
//         $member = Db::name('member')->where('id',$order['mid'])->find();
// 		$tmplcontent = array();
// 		$tmplcontent['first'] = '有新订单支付成功';
// 		$tmplcontent['remark'] = '点击进入查看~';
// 		$tmplcontent['accountType'] = t('会员').'昵称';
// 		$tmplcontent['account'] = $member['nickname'];
// 		$tmplcontent['amount'] = $order['money'].'元' . ($givemoney>0?'+赠送'.$givemoney.'元':'');
// 		$tmplcontent['result'] = '充值成功';
// 		\app\admin\common\Wechat::sendhttmpl($aid,0,'tmpl_recharge',$tmplcontent,"",$order['mdid']);
		return ['status'=>1,'msg'=>'']; 
           
	}
	
	public static function payorder_no_updateMemberbalance($orderid,$paytype,$paytypeid,$paynum='')
	{
	    
	    if(!$orderid) return;
		$payorder = Db::name('payorder')->where('id',$orderid)->find();
		//dump($payorder.'看看支付后的操作');
		if(!$payorder) return ['status'=>0,'msg'=>'支付订单不存在'];
		if($payorder['status']==1){
			self::settleEnterpriseQuotaPayment($payorder, 'payorder_no_updateMemberbalance_repeat');
			return ['status'=>0,'msg'=>'该订单已支付'];
		}

		if(getcustom('pay_yuanbao') && $payorder['type'] == 'shop'){
            if($payorder['is_yuanbao_pay'] == 1){
                $paytype = t('元宝').'支付+'.$paytype;
            }
            //元宝 更新shop_order和payorder
            self::yuanbao_up($payorder['type'],$payorder);
        }
       

		if($payorder['type'] == 'restaurant_shop' && $paytypeid == 4) {
            Db::name('payorder')->where('id',$orderid)->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum]);
        } else {
            Db::name('payorder')->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum]);
        }
		$type = $payorder['type'];
 

		if($type == 'shop_hb'){
			Db::name('shop_order')->where('ordernum','like',$payorder['ordernum'].'%')->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif(self::isRestaurantMergedPayorderType($type)){
			self::settleRestaurantMergedOrders($payorder, $paytype, intval($paytypeid), $paynum);
		}elseif($type == 'balance'){
			Db::name('shop_order')->where('id',$payorder['orderid'])->update(['balance_pay_status'=>1,'balance_pay_orderid'=>$orderid]);
			return ['status'=>1,'msg'=>''];
		}elseif($type == 'adoption_balance'){
			self::settleAdoptionBalance($payorder, 'payorder_no_updateMemberbalance');
			return ['status'=>1,'msg'=>''];
		}elseif($type == 'yuyue_balance'){
			Db::name('yuyue_order')->where('id',$payorder['orderid'])->update(['status'=>3,'balance_pay_status'=>1,'balance_pay_orderid'=>$orderid]);
		}elseif($type == 'yuyue_addmoney'){
			Db::name('yuyue_order')->where('id',$payorder['orderid'])->update(['addmoneyStatus'=>1]);
		}elseif($type == 'daojia'){
			Db::name('daojia_order')->where('id',$payorder['orderid'])->update([
				'status'=>1,
				'pay_status'=>1,
				'paytime'=>time(),
				'paytype'=>$paytype,
				'payorderid'=>$orderid,
				'updatetime'=>time(),
			]);
		}elseif($type == 'daojia_supplement'){
			// 到家补差价不改主订单支付状态，金额在 daojia_supplement_pay 入账
		}elseif($type == 'xixie'){
			Db::name('xixie_order')->where('id',$payorder['orderid'])->update([
				'paytime'=>time(),
				'paytype'=>$paytype,
				'payorderid'=>$orderid,
				'updatetime'=>time(),
			]);
		}elseif($type == 'seckill2'){
			Db::name('seckill2_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
			$form_sale = Db::name('seckill2_order')->alias('or')->join('seckill2_product pro','or.proid = pro.id')->field('pro.saleid')->where('or.ordernum',$payorder['ordernum'])->find();
			$sale = Db::name('seckill2_sale')->where('id',$form_sale['saleid'])->find();
			Db::name('seckill2_order')->where('ordernum',$sale['form_ordernum'])->update(['status'=>10]);
		}elseif($type == 'form'){
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paystatus'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif($type == 'plug_business_pay'){
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'restaurant_shop' && $paytypeid == 4){
            // Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'workorder'){  //工单订单
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paystatus'=>1,'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'hbtk'){  //拓客活动
            // 获取用户 openid（用于分账）
            $member = Db::name('member')->where('id', $payorder['mid'])->field('wxopenid,mpopenid')->find();
            $openid = $member['wxopenid'] ?? ($member['mpopenid'] ?? '');
            
            // 如果 $paynum 为空，尝试从订单表获取
            if(empty($paynum)){
                $order = Db::name($type.'_order')->where('id',$payorder['orderid'])->field('paynum')->find();
                if($order && !empty($order['paynum'])){
                    $paynum = $order['paynum'];
                }
            }
            
            // 更新订单状态，并保存 openid 和 transaction_id（用于分账）
            $updateData = [
                'status'=>1,
                'paytime'=>time(),
                'paytype'=>$paytype,
                'paytypeid'=>$paytypeid,
                'platform'=>$payorder['platform']
            ];
            
            // 保存 paynum（即使为空也保存，后续可以通过其他方式获取）
            if(!empty($paynum)){
                $updateData['paynum'] = $paynum;
                $updateData['split_transaction_id'] = $paynum;  // 同时保存到 split_transaction_id
            }else{
                // 如果 paynum 为空，尝试从订单表获取（可能之前已经保存过）
                $order = Db::name($type.'_order')->where('id',$payorder['orderid'])->field('paynum')->find();
                if($order && !empty($order['paynum'])){
                    $updateData['split_transaction_id'] = $order['paynum'];
                }
            }
            
            // 如果有 openid，保存用于分账
            if(!empty($openid)){
                $updateData['split_openid'] = $openid;
            }
            
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update($updateData);
            
            // 记录日志
            \think\facade\Log::info('拓客活动支付回调(payorder): order_id='.$payorder['orderid'].' paynum='.$paynum.' openid='.$openid.' split_transaction_id='.($updateData['split_transaction_id'] ?? '空').' split_openid='.($updateData['split_openid'] ?? '空').' updateData='.json_encode($updateData));
        }elseif($type == 'allianceactivity'){
            Db::name('allianceactivity_order')->where('id',$payorder['orderid'])->update(['update_time'=>time()]);
        }elseif($type == 'yuyue_package'){  // 处理套餐订单特殊情况，使用pay_time而不是paytime
            // 套餐订单表使用pay_time字段而不是paytime
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update([
                'status'=>1,
                'pay_time'=>time(),  // 注意：这里使用pay_time而不是paytime
                'paytype'=>$paytype,
                'paytypeid'=>$paytypeid,
                'paynum'=>$paynum,
                'platform'=>$payorder['platform']
            ]);
	        }elseif($type == 'yunkucun_delivery'){
	            // 云仓提货运费支付单：不改变提货主订单状态（仅由 type 回调处理）
	        }elseif($type == 'agentrecharge'){
	            // 代理充值升级支付成功：交由插件结算并触发升级
	            if(class_exists('\\app\\plugin\\agentrecharge\\common\\AgentRechargeService')){
                try{
                    \app\plugin\agentrecharge\common\AgentRechargeService::onPaySuccess(intval($payorder['aid']), strval($payorder['ordernum']), strval($paynum));
                }catch(\Throwable $e){
                    \think\facade\Log::error('代理充值支付回调失败：'.$e->getMessage());
                }
		        }
			}elseif($type == 'presalevoucher_diff'){
				$serviceClass = self::getPresaleVoucherServiceClass(intval($payorder['aid'] ?? 0));
				if ($serviceClass !== '') {
					try{
						$serviceClass::confirmReservationPay($payorder);
					}catch(\Throwable $e){
						\think\facade\Log::error('预售券补差价支付回调失败：'.$e->getMessage());
					}
				}
			}elseif($type == 'lucky_collage'){
				Db::name('lucky_collage_order')->where('id',$payorder['orderid'])->update(['paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>strval($payorder['platform'] ?? '')]);
			}else{
				Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>strval($payorder['platform'] ?? '')]);
			}

			if($type == 'shop'){
				self::syncJishouPaidOrderState(intval($payorder['orderid'] ?? 0));
			}elseif($type == 'shop_hb'){
				$shopOrderIds = Db::name('shop_order')->where('ordernum','like',$payorder['ordernum'].'%')->column('id');
				foreach(($shopOrderIds ?: []) as $shopOrderId){
					self::syncJishouPaidOrderState(intval($shopOrderId));
				}
			}
			self::settleEnterpriseQuotaPayment($payorder, 'payorder_no_updateMemberbalance');
				self::syncLiveOrderAttributionPaid($payorder, $paytype, intval($paytypeid));
				self::issuePresaleVouchersByPayorder($payorder, 'payorder_no_updateMemberbalance');
				self::issueAdoptionContractsByPayorder($payorder, 'payorder_no_updateMemberbalance');
				self::enqueueJushuitanPaidOrders($payorder, 'payorder_no_updateMemberbalance');
				self::commitRestaurantStoredValueCouponForPayorder($payorder, 'payorder_no_updateMemberbalance');

		if(getcustom('invite_free')){
        	if($payorder['type'] == 'shop'){
        		//发送订单通知
        		self::send_free_notice($payorder);
        	}
        }
		$fun = $type.'_pay';
		
		// $res = self::$fun($payorder['orderid'],$payorder['ordernum']);
        if($type == 'daojia' && method_exists(__CLASS__, $fun)){
            $res = self::$fun($payorder['orderid'],$payorder['ordernum']);
        }elseif($type == 'daojia_supplement' && method_exists(__CLASS__, $fun)){
            $res = self::$fun($payorder['orderid'],$payorder['ordernum']);
        }elseif($type == 'xixie' && method_exists(__CLASS__, $fun)){
            $res = self::$fun($payorder['orderid'],$payorder['ordernum']);
        }elseif($type == 'allianceactivity' && method_exists(__CLASS__, $fun)){
            $res = self::$fun($payorder['orderid'],$payorder['ordernum']);
	        }elseif($type == 'fangyuan_staff_seat' && method_exists(__CLASS__, $fun)){
	            $res = self::$fun($payorder['orderid'],$payorder['ordernum']);
	        }elseif($type == 'linggong' && method_exists(__CLASS__, $fun)){
	            $res = self::$fun($payorder['orderid'],$payorder['ordernum']);
	        }elseif($type == 'presalevoucher_diff'){
	            $res = ['status'=>1,'msg'=>''];
	        }else{
            $res = self::recharge_pay_no_add_member($payorder['orderid'],$payorder['ordernum']);
        }
        self::afterusecoupon($payorder['orderid'],$type,1,$payorder['ordernum']);
		return ['status'=>1,'msg'=>''];
	}
	//支付完成后操作
	public static function payorder($orderid,$paytype,$paytypeid,$paynum=''){
		if(!$orderid) return;
		$payorder = Db::name('payorder')->where('id',$orderid)->find();
		//dump($payorder.'看看支付后的操作');
		if(!$payorder) return ['status'=>0,'msg'=>'支付订单不存在'];
		if($payorder['status']==1){
			self::settleEnterpriseQuotaPayment($payorder, 'payorder_repeat');
			return ['status'=>0,'msg'=>'该订单已支付'];
		}

		// ================= 支付识别栏 =================
		// 记录支付完成操作的关键信息，便于业务处理和问题追踪
		$payment_info = [
			'payorder_id' => $orderid,
			'order_type' => $payorder['type'],
			'order_id' => $payorder['orderid'],
			'order_num' => $payorder['ordernum'],
			'pay_type' => $paytype,
			'pay_type_id' => $paytypeid,
			'pay_num' => $paynum,
			'pay_time' => time(),
			'aid' => $payorder['aid'],
			'bid' => $payorder['bid'],
			'mid' => $payorder['mid'],
			'amount' => $payorder['money'],
			'platform' => $payorder['platform'] ?? ''
		];
		
		// 记录支付完成日志
		\think\facade\Log::info('支付完成处理开始', $payment_info);
		if (strval($payorder['type'] ?? '') === 'shop') {
			\think\facade\Log::info('阶梯全返调试-支付完成处理开始', $payment_info);
		}
		
		// 根据订单类型进行特殊处理标识
		$type_descriptions = [
			'shop' => '商城订单',
			'shop_hb' => '商城合并订单',
			'property' => '物业费订单',
			'yuyue' => '预约服务订单',
			'daojia' => '到家服务订单',
			'daojia_supplement' => '到家服务补差价',
			'restaurant_shop' => '餐厅订单',
			'seckill' => '秒杀订单',
			'collage' => '拼团订单',
			'scoreshop' => '积分商城订单',
			'recharge' => '充值订单',
			'scorehuang_recharge' => '黄积分充值订单',
			'balance' => '余额支付',
			'form' => '表单订单',
			'huodong_baoming' => '活动报名订单',
			'timed_lottery' => '幸运定时开奖订单',
			'allianceactivity' => '联盟活动订单',
			'fangyuan_staff_seat' => '房源员工席位订单',
			'periodicservice' => '周期服务订单',
			'workorder' => '工单订单',
			'ershou' => '二手交易订单',
			'miaosha_bid_deposit' => '寄售拍卖保证金',
			'presalevoucher_diff' => '预售券预约补差价',
			'adoption_balance' => '认养尾款',
			'linggong' => '零工担保支付',
			'paidcommunity' => '付费社群订单',
			'customdiy' => '个性定制订单',
			'customdiy_package' => '个性定制设计额度套餐'
		];
		
		$type_desc = $type_descriptions[$payorder['type']] ?? '未知类型订单';
		\think\facade\Log::info("开始处理{$type_desc}支付完成逻辑", ['type' => $payorder['type']]);
		// ============================================

		if(getcustom('pay_yuanbao') && $payorder['type'] == 'shop'){
            if($payorder['is_yuanbao_pay'] == 1){
                $paytype = t('元宝').'支付+'.$paytype;
            }
            //元宝 更新shop_order和payorder
            self::yuanbao_up($payorder['type'],$payorder);
        }
       	

		if($payorder['type'] == 'restaurant_shop' && $paytypeid == 4) {
            Db::name('payorder')->where('id',$orderid)->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum]);
        } else {
            Db::name('payorder')->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum]);
		}
		$type = $payorder['type'];

		if($type == 'shop_hb'){
			Db::name('shop_order')->where('ordernum','like',$payorder['ordernum'].'%')->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif(self::isRestaurantMergedPayorderType($type)){
			self::settleRestaurantMergedOrders($payorder, $paytype, intval($paytypeid), $paynum);
		}elseif($type == 'balance'){
			Db::name('shop_order')->where('id',$payorder['orderid'])->update(['balance_pay_status'=>1,'balance_pay_orderid'=>$orderid]);
			return ['status'=>1,'msg'=>''];
		}elseif($type == 'adoption_balance'){
			self::settleAdoptionBalance($payorder, 'payorder');
			return ['status'=>1,'msg'=>''];
		}elseif($type == 'yuyue_balance'){
			Db::name('yuyue_order')->where('id',$payorder['orderid'])->update(['status'=>3,'balance_pay_status'=>1,'balance_pay_orderid'=>$orderid]);
		}elseif($type == 'yuyue_addmoney'){
			Db::name('yuyue_order')->where('id',$payorder['orderid'])->update(['addmoneyStatus'=>1]);
		}elseif($type == 'daojia'){
			Db::name('daojia_order')->where('id',$payorder['orderid'])->update([
				'status'=>1,
				'pay_status'=>1,
				'paytime'=>time(),
				'paytype'=>$paytype,
				'payorderid'=>$orderid,
				'updatetime'=>time(),
			]);
		}elseif($type == 'daojia_supplement'){
			// 到家补差价不改主订单支付状态，金额在 daojia_supplement_pay 入账
		}elseif($type == 'xixie'){
			Db::name('xixie_order')->where('id',$payorder['orderid'])->update([
				'paytime'=>time(),
				'paytype'=>$paytype,
				'payorderid'=>$orderid,
				'updatetime'=>time(),
			]);
		}elseif($type == 'seckill2'){
			Db::name('seckill2_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
			$form_sale = Db::name('seckill2_order')->alias('or')->join('seckill2_product pro','or.proid = pro.id')->field('pro.saleid')->where('or.ordernum',$payorder['ordernum'])->find();
			$sale = Db::name('seckill2_sale')->where('id',$form_sale['saleid'])->find();
			Db::name('seckill2_order')->where('ordernum',$sale['form_ordernum'])->update(['status'=>10]);
		}elseif($type == 'form'){
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paystatus'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif($type == 'plug_business_pay'){
			Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'restaurant_shop' && $paytypeid == 4){
            // Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
        }elseif($type == 'workorder'){  //工单订单
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['paystatus'=>1,'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>$payorder['platform']]);
		}elseif($type=='huodong_baoming') {
            $aid = $payorder['aid'];
            Db::name('huodong_baoming_order')->where('id', $payorder['orderid'])->update(['status' => 1, 'paytime' => time(), 'paytype' => $paytype, 'paytypeid' => $paytypeid, 'platform' => $payorder['platform']]);
            $order = Db::name('huodong_baoming_order')->where('id', $payorder['orderid'])->find();
            Db::name('huodong_baoming_product')->where('aid',$aid)->where('id',$order['proid'])->update(['sales'=>Db::raw("sales+".$order['num'])]);
            if ($order['givescore'] > 0) {
                \app\admin\common\Member::addscore($aid,$order['mid'],$order['givescore'],'参与活动赠送'.t('积分'));
            }
            return ['status' => 1, 'msg' => ''];
        }elseif($type == 'timed_lottery'){
            Db::name('timed_lottery_order')->where('id', $payorder['orderid'])->update([
                'status' => 1,
                'pay_time' => time(),
                'platform' => strval($payorder['platform'] ?? ''),
                'update_time' => time(),
            ]);
        }elseif($type == 'allianceactivity'){
            Db::name('allianceactivity_order')->where('id', $payorder['orderid'])->update([
                'update_time' => time(),
            ]);
        }elseif($type == 'yuyue_package'){  // 处理套餐订单特殊情况，使用pay_time而不是paytime
            // 套餐订单表使用pay_time字段而不是paytime
            Db::name($type.'_order')->where('id',$payorder['orderid'])->update([
                'status'=>1,
                'pay_time'=>time(),  // 注意：这里使用pay_time而不是paytime
                'paytype'=>$paytype,
                'paytypeid'=>$paytypeid,
                'paynum'=>$paynum,
                'platform'=>$payorder['platform']
            ]);
        }elseif ($type == 'periodicservice') {
            // 周期服务订单: 在此处直接更新核心状态
            $updateData = [
                'pay_status' => 1, // 支付状态: 已支付
                'status' => 1,     // 订单状态: 服务中 (或根据业务设置为其他初始状态，如 待排期)
                'pay_time' => time(),
                'pay_type' => $paytype,
                'paytypeid'=>$paytypeid,
                // 'pay_money' => $payMoney, // 记录支付金额
                // 'platform' => $platform,
                'platform'=>$payorder['platform'],
                'paynum' => $paynum,
                // 'paytypeid' => $paytypeid, // 如果 periodic_service_order 表也有此字段，则添加
            ];
           // var_dump($updateData);die;
            Db::name('periodicservice_order')
              ->where('aid', $payorder['aid']) // 添加 aid 条件确保安全
              ->where('id', $payorder['orderid'])
              ->update($updateData);
            \think\facade\Log::info("Payorder-周期服务订单状态更新成功-订单ID: " . $payorder['orderid']);

        }elseif($type == 'yunkucun_delivery'){
            // 云仓提货运费支付单：不改变提货主订单状态（仅由 type 回调处理）
    
	        }elseif($type == 'property'){
	            // 物业费订单：直接调用property_pay方法处理，不需要更新property_order表
	            // 因为现在后台使用的是property_payment缴费记录表和property_bill账单表
	            // property_pay方法会直接通过payorder信息处理这些表
	            \think\facade\Log::info('物业费支付处理', ['payorder_id' => $orderid, 'ordernum' => $payorder['ordernum']]);
	        }elseif($type == 'scorehuang_recharge'){
	            Db::name('scorehuang_recharge_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>strval($payorder['platform'] ?? '')]);
	        }elseif($type == 'agentrecharge'){
	            // 代理充值升级支付成功：交由插件结算并触发升级
	            if(class_exists('\\app\\plugin\\agentrecharge\\common\\AgentRechargeService')){
	                try{
                    \app\plugin\agentrecharge\common\AgentRechargeService::onPaySuccess(intval($payorder['aid']), strval($payorder['ordernum']), strval($paynum));
                }catch(\Throwable $e){
                    \think\facade\Log::error('代理充值支付回调失败：'.$e->getMessage());
                }
	            }
			}elseif($type == 'presalevoucher_diff'){
				$serviceClass = self::getPresaleVoucherServiceClass(intval($payorder['aid'] ?? 0));
				if ($serviceClass !== '') {
					try{
						$serviceClass::confirmReservationPay($payorder);
					}catch(\Throwable $e){
						\think\facade\Log::error('预售券补差价支付回调失败：'.$e->getMessage());
					}
				}
			}elseif($type == 'lucky_collage'){
				Db::name('lucky_collage_order')->where('id',$payorder['orderid'])->update(['paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>strval($payorder['platform'] ?? '')]);
			}else{
				Db::name($type.'_order')->where('id',$payorder['orderid'])->update(['status'=>1,'paytime'=>time(),'paytype'=>$paytype,'paytypeid'=>$paytypeid,'paynum'=>$paynum,'platform'=>strval($payorder['platform'] ?? '')]);
			}

		self::settleEnterpriseQuotaPayment($payorder, 'payorder');

		if($type == 'shop'){
			self::syncJishouPaidOrderState(intval($payorder['orderid'] ?? 0));
		}elseif($type == 'shop_hb'){
			$shopOrderIds = Db::name('shop_order')->where('ordernum','like',$payorder['ordernum'].'%')->column('id');
			foreach(($shopOrderIds ?: []) as $shopOrderId){
				self::syncJishouPaidOrderState(intval($shopOrderId));
			}
		}
		self::syncLiveOrderAttributionPaid($payorder, $paytype, intval($paytypeid));
		self::issuePresaleVouchersByPayorder($payorder, 'payorder');
		self::issueAdoptionContractsByPayorder($payorder, 'payorder');
		self::enqueueJushuitanPaidOrders($payorder, 'payorder');

        if(getcustom('invite_free')){
        	if($payorder['type'] == 'shop'){
        		//发送订单通知
        		self::send_free_notice($payorder);
        	}
        }
		$fun = $type.'_pay';
		// 对于property类型，传递payorder ID而不是orderid
			if($type == 'property') {
				$res = self::$fun($orderid, $payorder['ordernum']);
			} elseif($type == 'presalevoucher_diff') {
				$res = ['status'=>1,'msg'=>''];
			} elseif(method_exists(__CLASS__, $fun)) {
				if($type == 'shop'){
					self::jietiquanfanDebug('payorder will call shop_pay', [
						'payorder_id' => intval($orderid),
						'aid' => intval($payorder['aid'] ?? 0),
						'shop_order_id' => intval($payorder['orderid'] ?? 0),
						'ordernum' => strval($payorder['ordernum'] ?? ''),
					]);
				}
				$res = self::$fun($payorder['orderid'],$payorder['ordernum']);
		}
		self::commitRestaurantStoredValueCouponForPayorder($payorder, 'payorder');

		// 幸运抽奖任务奖励必须在订单业务结算完成后发放，支付回调重复时由任务流水幂等拦截。
		if (in_array(strval($payorder['type'] ?? ''), ['shop', 'shop_hb', 'restaurant_shop', 'restaurant_shop_hb', 'restaurant_takeaway', 'restaurant_takeaway_hb', 'restaurant_booking'], true)) {
			try {
				if (class_exists('\app\plugin\sweepstakes\common\SweepstakesTaskService')) {
					\app\plugin\sweepstakes\common\SweepstakesTaskService::grantForPayorder($payorder);
				}
			} catch (\Throwable $e) {
				\think\facade\Log::warning('大转盘下单任务奖励处理失败：' . $e->getMessage(), [
					'payorder_id' => intval($payorder['id'] ?? $orderid),
					'order_type' => strval($payorder['type'] ?? ''),
				]);
			}
		}
        self::afterusecoupon($payorder['orderid'],$type,1,$payorder['ordernum']);
        
        // ================= 支付识别栏完成 =================
        // 记录支付完成处理结果
        $completion_info = [
        	'payorder_id' => $orderid,
        	'order_type' => $payorder['type'],
        	'processing_result' => 'success',
        	'completion_time' => time(),
        	'specific_handler' => $fun,
        	'handler_result' => $res
        ];
        
        \think\facade\Log::info('支付完成处理结束', $completion_info);
        
        // 对于特定类型订单，记录额外信息
		$type_descriptions = [
			'shop' => '商城订单',
			'shop_hb' => '商城合并订单',
			'property' => '物业费订单',
			'yuyue' => '预约服务订单',
			'daojia' => '到家服务订单',
			'daojia_supplement' => '到家服务补差价',
			'restaurant_shop' => '餐厅订单',
			'seckill' => '秒杀订单',
			'collage' => '拼团订单',
			'scoreshop' => '积分商城订单',
			'recharge' => '充值订单',
			'scorehuang_recharge' => '黄积分充值订单',
			'balance' => '余额支付',
			'form' => '表单订单',
			'huodong_baoming' => '活动报名订单',
			'timed_lottery' => '幸运定时开奖订单',
			'allianceactivity' => '联盟活动订单',
			'fangyuan_staff_seat' => '房源员工席位订单',
			'periodicservice' => '周期服务订单',
			'workorder' => '工单订单',
				'ershou' => '二手交易订单',
				'miaosha_bid_deposit' => '寄售拍卖保证金',
				'presalevoucher_diff' => '预售券预约补差价',
				'adoption_balance' => '认养尾款',
				'linggong' => '零工担保支付',
				'paidcommunity' => '付费社群订单',
				'customdiy' => '个性定制订单',
			'customdiy_package' => '个性定制设计额度套餐'
		];
		$type_desc = $type_descriptions[$payorder['type']] ?? '未知类型订单';
        if(in_array($payorder['type'], ['property', 'yuyue', 'daojia', 'restaurant_shop'])) {
        	\think\facade\Log::info("{$type_desc}支付完成特殊处理", [
        		'type' => $payorder['type'],
        		'amount' => $payorder['money'],
        		'member_id' => $payorder['mid']
        	]);
        }
        // =============================================
        
		return ['status'=>1,'msg'=>''];
	}

	public static function scorehuang_recharge_pay($orderid, $ordernum = '')
	{
		try {
			$order = Db::name('scorehuang_recharge_order')->where('id', intval($orderid))->find();
			if (!$order) {
				Log::warning('黄积分充值回调：订单不存在', ['orderid' => $orderid]);
				return ['status' => 0, 'msg' => '充值订单不存在'];
			}
			$aid = $order['aid'];
			$mid = $order['mid'];
			$score = floatval($order['score']);
			if ($score <= 0) {
				return ['status' => 0, 'msg' => '积分数量无效'];
			}
			\app\plugin\scorehuang\common\ScorehuangHelper::addScorehuang($aid, $mid, $score, '充值黄积分', 'recharge');
			Log::info('黄积分充值完成', ['orderid' => $orderid, 'mid' => $mid, 'score' => $score]);
			return ['status' => 1, 'msg' => ''];
		} catch (\Throwable $e) {
			Log::error('黄积分充值回调失败: ' . $e->getMessage(), ['orderid' => $orderid]);
			return ['status' => 0, 'msg' => $e->getMessage()];
		}
	}

		public static function timed_lottery_pay($orderid, $ordernum){
			$order = Db::name('timed_lottery_order')->where('id', intval($orderid))->find();
			if(!$order){
				return ['status'=>0,'msg'=>'定时开奖订单不存在'];
			}
			return ['status'=>1,'msg'=>''];
		}

		public static function linggong_pay($orderid, $ordernum = '')
		{
			if (!self::tableExists('linggong_order') || !self::tableExists('linggong_payment')) {
				return ['status' => 1, 'msg' => ''];
			}
			try {
				$order = Db::name('linggong_order')->where('id', intval($orderid))->find();
				if (!$order && $ordernum !== '') {
					$order = Db::name('linggong_order')->where('ordernum', strval($ordernum))->find();
				}
				if (!$order) {
					return ['status' => 0, 'msg' => '零工订单不存在'];
				}
				$paymentId = intval($order['payment_id'] ?? 0);
				if ($paymentId <= 0) {
					return ['status' => 0, 'msg' => '零工支付记录不存在'];
				}

				$payorder = Db::name('payorder')
					->where('type', 'linggong')
					->where('orderid', intval($order['id']))
					->order('id desc')
					->find();
				if (!$payorder) {
					$payorder = Db::name('payorder')
						->where('type', 'linggong')
						->where('ordernum', strval($order['ordernum'] ?? $ordernum))
						->order('id desc')
						->find();
				}
				if (!$payorder) {
					$payorder = [
						'aid' => intval($order['aid'] ?? 0),
						'bid' => intval($order['bid'] ?? 0),
						'mid' => intval($order['mid'] ?? 0),
						'orderid' => intval($order['id'] ?? 0),
						'ordernum' => strval($order['ordernum'] ?? $ordernum),
						'paytype' => strval($order['paytype'] ?? ''),
						'paytypeid' => intval($order['paytypeid'] ?? 0),
						'paynum' => strval($order['paynum'] ?? ''),
						'platform' => strval($order['platform'] ?? ''),
					];
				}

				$payType = strval($payorder['paytype'] ?? $order['paytype'] ?? '');
				if ($payType === '') {
					$payType = '在线支付';
				}
				\app\plugin\linggong\service\PaymentService::handlePaid($paymentId, $payType, $payorder);
				return ['status' => 1, 'msg' => ''];
			} catch (\Throwable $e) {
				Log::error('零工担保支付回调失败：' . $e->getMessage());
				return ['status' => 0, 'msg' => $e->getMessage()];
			}
		}

		public static function paidcommunity_pay($orderid, $ordernum = '')
		{
			try {
				if (!class_exists('\\app\\plugin\\paidcommunity\\common\\PaidCommunityOrderService')) {
					return ['status' => 0, 'msg' => '付费社群结算服务不存在'];
				}
				$orderId = intval($orderid);
				if ($orderId <= 0 && trim(strval($ordernum)) !== '') {
					$orderId = intval(Db::name('paidcommunity_order')
						->where('ordernum', strval($ordernum))
						->value('id'));
				}
				if ($orderId <= 0) {
					return ['status' => 0, 'msg' => '付费社群订单不存在'];
				}
				$payorder = Db::name('payorder')
					->where('type', 'paidcommunity')
					->where('orderid', $orderId)
					->order('id desc')
					->find() ?: [];
				$result = \app\plugin\paidcommunity\common\PaidCommunityOrderService::ensureSettled($orderId, $payorder);
				if (intval($result['status'] ?? 0) !== 1) {
					return ['status' => 0, 'msg' => strval($result['msg'] ?? '付费社群结算失败')];
				}
				return ['status' => 1, 'msg' => ''];
			} catch (\Throwable $e) {
				Log::error('付费社群支付回调失败：' . $e->getMessage());
				return ['status' => 0, 'msg' => $e->getMessage()];
			}
		}

		//一键追爆套餐购买：支付成功后发放创作点
		public static function zhuibao_package_pay($orderid, $ordernum){
		if(class_exists('\\app\\plugin\\zhuibaoagent\\common\\ZhuibaoCreditService')){
			try{
				\app\plugin\zhuibaoagent\common\ZhuibaoCreditService::onPackageOrderPaid(intval($orderid));
			}catch(\Throwable $e){
				\think\facade\Log::error('一键追爆套餐支付回调失败：orderid='.$orderid.', err='.$e->getMessage());
				return ['status'=>0,'msg'=>$e->getMessage()];
			}
		}
		return ['status'=>1,'msg'=>''];
	}

		//AI数字人套餐购买：支付成功后幂等发放创作点
		public static function digitalavatar_package_pay($orderid, $ordernum = ''){
			if(class_exists('\\app\\plugin\\digitalavatar\\common\\DigitalAvatarService')){
				try{
					$payType = '在线支付';
					$payorder = Db::name('payorder')
						->where('type', 'digitalavatar_package')
						->where('orderid', intval($orderid))
						->order('id desc')
						->find();
					if ($payorder && trim(strval($payorder['paytype'] ?? '')) !== '') {
						$payType = trim(strval($payorder['paytype']));
					}
					\app\plugin\digitalavatar\common\DigitalAvatarService::onPackageOrderPaid(intval($orderid), $payType);
				}catch(\Throwable $e){
					\think\facade\Log::error('AI数字人套餐支付回调失败：orderid='.$orderid.', err='.$e->getMessage());
					return ['status'=>0,'msg'=>$e->getMessage()];
				}
			}
			return ['status'=>1,'msg'=>''];
		}

		public static function customdiy_pay($orderid, $ordernum = '')
		{
			$order = Db::name('customdiy_order')->where('id', intval($orderid))->find();
			if(!$order){
				return ['status'=>0,'msg'=>'定制订单不存在'];
			}
			try{
				\app\plugin\customdiy\common\CustomDiyOrderService::ensureSettled(intval($order['aid']), intval($orderid));
			}catch(\Throwable $e){
				\think\facade\Log::error('个性定制支付结算失败：orderid='.$orderid.', err='.$e->getMessage());
				return ['status'=>0,'msg'=>$e->getMessage()];
			}
			return ['status'=>1,'msg'=>''];
		}

		public static function customdiy_package_pay($orderid, $ordernum = '')
		{
			if(!class_exists('\\app\\plugin\\customdiy\\common\\CustomDiyCreditService')){
				return ['status'=>1,'msg'=>''];
			}
			try{
				\app\plugin\customdiy\common\CustomDiyCreditService::onPackageOrderPaid(intval($orderid));
			}catch(\Throwable $e){
				\think\facade\Log::error('个性定制设计额度套餐支付回调失败：orderid='.$orderid.', err='.$e->getMessage());
				return ['status'=>0,'msg'=>$e->getMessage()];
			}
			return ['status'=>1,'msg'=>''];
		}
	//商城订单合并支付
	public static function shop_hb_pay($orderid,$ordernum){
		$orderlist = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
		foreach($orderlist as $order){
			self::shop_pay($order['id']);
		}
	}

	// 心理测评完整报告解锁
	public static function psytest_unlock_pay($orderid, $ordernum){
		$order = Db::name('psytest_unlock_order')->where('id', intval($orderid))->find();
		if(!$order){
			return ['status'=>0,'msg'=>'解锁订单不存在'];
		}
		return ['status'=>1,'msg'=>''];
	}

		    /**
		     * 云仓补货单（V2 / V3）
		     *
		     * 支付完成后：
		     * - goods_domain=0（V2）：入库（增加可用库存）并把订单置为“已入库”。
		     * - goods_domain=1（V3 独立商品域）：计入“来货在途(incoming_num)”并把订单置为“已支付”；等待确认入库后转“可用库存”。
		     *
		     * 说明：
		     * - goods_domain=0：沿用 V2（proid/ggid => shop_product/shop_guige）
		     * - goods_domain=1：云仓独立商品域（proid/ggid => yunkucun_goods/yunkucun_goods_sku）
	     */
	    public static function yunkucun_replenish_pay($orderid, $ordernum)
	    {
	        try {
	            Db::transaction(function () use ($orderid, $ordernum) {
	                $order = Db::name('yunkucun_replenish_order')
	                    ->where('id', intval($orderid))
	                    ->lock(true)
	                    ->find();
	                if (!$order) {
	                    throw new \Exception('补货单不存在');
	                }
		                $goodsDomain = intval($order['goods_domain'] ?? 0);
		                $status = intval($order['status'] ?? 0);
		                $enableHqFulfill = false;
		                $enableUpgradePeerReward = true;
		                if ($goodsDomain === 1) {
		                    // 兼容旧库：yunkucun_goods_sysset 可能无 bid 字段（按 aid 配置）
		                    $syssetRow = null;
		                    try {
		                        try {
		                            $syssetRow = Db::name('yunkucun_goods_sysset')
		                                ->where('aid', intval($order['aid']))
		                                ->where('bid', intval($order['bid']))
		                                ->find();
		                        } catch (\Throwable $e) {
		                            $syssetRow = Db::name('yunkucun_goods_sysset')
		                                ->where('aid', intval($order['aid']))
		                                ->find();
		                        }
		                    } catch (\Throwable $e) {
		                        $syssetRow = null;
		                    }

		                    if (is_array($syssetRow)) {
		                        $enableHqFulfill = intval($syssetRow['enable_hq_fulfill'] ?? 0) === 1;
		                        if (array_key_exists('enable_upgrade_peer_reward', $syssetRow)) {
		                            $enableUpgradePeerReward = intval($syssetRow['enable_upgrade_peer_reward'] ?? 1) === 1;
		                        }
		                    }
		                }

	            $snapshot = [];
	            $snapshotRaw = strval($order['snapshot_json'] ?? '');
	            if ($snapshotRaw !== '') {
	                $tmp = json_decode($snapshotRaw, true);
	                if (is_array($tmp)) {
	                    $snapshot = $tmp;
	                }
	            }
	            $isDropship = is_array($snapshot) && isset($snapshot['dropship']);
	            $isUpgrade = is_array($snapshot) && isset($snapshot['upgrade']);
	            $upgradeSnapshot = is_array($snapshot['upgrade'] ?? null) ? $snapshot['upgrade'] : [];
	            $upgradeSpecialSnapshot = is_array($upgradeSnapshot['special_goods'] ?? null) ? $upgradeSnapshot['special_goods'] : [];
	            $isUpgradeSpecialGoodsOrder = $goodsDomain === 1
	                && $isUpgrade
	                && intval($upgradeSpecialSnapshot['enabled'] ?? 0) === 1;
	            $virtualFulfillment = is_array($snapshot['virtual_fulfillment'] ?? null) ? $snapshot['virtual_fulfillment'] : null;
	            $isVirtualFulfillment = $virtualFulfillment && intval($virtualFulfillment['real_delivery'] ?? 0) === 0;

		            // 月度津贴：标记“首单/升级补货单”（仅用于不计本人业绩；仍计入上级链路）
		            // - upgradeCreate 生成的升级单：快照含 upgrade
		            // - 首单：该会员首次完成支付的云仓V3补货单（按 paytime/id 判定）
		            if ($goodsDomain === 1) {
		                $hasFirstOrderCol = array_key_exists('is_first_order', $order);
		                $hasUpgradeOrderCol = array_key_exists('is_upgrade_order', $order);

		                $flagUpdate = [];
		                if ($hasUpgradeOrderCol && $isUpgrade && intval($order['is_upgrade_order'] ?? 0) !== 1) {
		                    $flagUpdate['is_upgrade_order'] = 1;
		                }

		                if ($hasFirstOrderCol && intval($order['is_first_order'] ?? 0) !== 1) {
		                    $paytime = intval($order['paytime'] ?? 0);
		                    $oid = intval($order['id'] ?? 0);
		                    $mid = intval($order['mid'] ?? 0);
		                    $aid0 = intval($order['aid'] ?? 0);

		                    if ($paytime > 0 && $oid > 0 && $mid > 0 && $aid0 > 0) {
		                        $existsPrev = Db::name('yunkucun_replenish_order')
		                            ->where('aid', $aid0)
		                            ->where('mid', $mid)
		                            ->where('goods_domain', 1)
		                            ->where('paytime', '>', 0)
		                            ->where('status', 'in', [1, 2])
		                            ->where(function ($q) use ($paytime, $oid) {
		                                $q->where('paytime', '<', $paytime)
		                                    ->whereOr(function ($q2) use ($paytime, $oid) {
		                                        $q2->where('paytime', $paytime)->where('id', '<', $oid);
		                                    });
		                            })
		                            ->find();
		                        if (!$existsPrev) {
		                            $flagUpdate['is_first_order'] = 1;
		                        }
		                    }
		                }

		                if ($flagUpdate) {
		                    try {
		                        Db::name('yunkucun_replenish_order')
		                            ->where('id', intval($order['id'] ?? 0))
		                            ->update(array_merge($flagUpdate, ['updatetime' => time()]));
		                        foreach ($flagUpdate as $k => $v) {
		                            $order[$k] = $v;
		                        }
		                    } catch (\Throwable $e) {
		                        // 不影响支付主流程
		                    }
		                }
		            }

		                if ($goodsDomain === 1) {
		                    // V3：支付回调时主订单状态通常已被置为 status=1（payorder_by_shop），不能用 status>=1 来做幂等。
		                    if ($isUpgrade) {
	                        // 升级单：支付后直接入库并升级等级，执行完成后置 status=2，重复回调直接跳过
	                        if ($status >= 2) {
	                            return;
	                        }
	                    } elseif (!$isDropship) {
	                        // 普通补货单：支付后计入“来货在途”，用快照标记做幂等
	                        if (intval($snapshot['replenish_paid_processed'] ?? 0) === 1) {
	                            return;
	                        }
	                    }
	                } else {
	                    // V2：支付完成后直接入库（status=2），重复回调直接跳过
	                    if ($status >= 2) {
	                        return;
	                    }
	                }

	                // 代发进货单：支付后不入库（仅保持“已支付”状态即可）
	                if ($isDropship) {
	                    // 代发利润结算（按快照）
	                    $profit = is_array($snapshot['profit'] ?? null) ? $snapshot['profit'] : [];
	                    $settleTiming = intval($profit['settle_timing'] ?? 1); // 1支付后 2收货后
	                    $profitMid = intval($profit['profit_mid'] ?? 0);
	                    $profitAmount = round(floatval($profit['profit_amount'] ?? 0), 2);
	                    $settled = intval($profit['settled'] ?? 0) === 1;

	                    if ($settleTiming === 1 && !$settled && $profitMid > 0 && $profitAmount > 0) {
	                        $credit = \app\admin\common\YunkucunGoodsMoneylog::creditDropshipProfitSettle(
	                            intval($order['aid']),
	                            intval($order['bid'] ?? 0),
	                            intval($order['mid'] ?? 0),
	                            intval($order['id'] ?? 0),
	                            strval($order['ordernum'] ?? $ordernum),
	                            strval($order['client_request_no'] ?? ''),
	                            $profitMid,
	                            $profitAmount,
	                            1,
	                            $snapshot
	                        );
	                        if (is_array($credit) && intval($credit['status'] ?? 1) === 0) {
	                            throw new \Exception(strval($credit['msg'] ?? '云仓代发利润结算失败'));
	                        }
	                        $profit['settled'] = 1;
	                        $profit['settled_time'] = time();
	                        $snapshot['profit'] = $profit;
	                    }

	                    // 代发进货单：支付后不入库（仅保持“已支付”状态即可）
	                    Db::name('yunkucun_replenish_order')
	                        ->where('id', intval($order['id']))
	                        ->update([
	                            'status' => 1,
	                            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
	                            'updatetime' => time(),
	                        ]);

	                    return;
	                }

		                // 升级补货单（真实发货）：支付后直接入库到“可用库存”，并将会员等级更新为目标等级
		                // - 虚拟补货(无需发货)走下方“虚拟履约”统一结算逻辑（支持出货人扣库/货款/差价利润/平级奖）
		                if ($goodsDomain === 1 && $isUpgrade && !$isVirtualFulfillment) {
		                    $upgrade = is_array($snapshot['upgrade'] ?? null) ? $snapshot['upgrade'] : [];
		                    $toLevelId = intval($upgrade['to_levelid'] ?? 0);
		                    if ($toLevelId <= 0) {
		                        throw new \Exception('升级目标等级参数错误');
		                    }

	                    $items = Db::name('yunkucun_replenish_order_item')
	                        ->where('orderid', intval($orderid))
	                        ->select()
	                        ->toArray();
	                    if (!$items) {
	                        $items = [];
	                    }

	                    foreach ($items as $it) {
	                        $proid = intval($it['proid'] ?? 0);
	                        $ggid = intval($it['ggid'] ?? 0);
	                        if ($proid <= 0 || $ggid <= 0) continue;

	                        $qty = intval($it['qty'] ?? 0);
	                        $boxQty = max(1, intval($it['box_qty'] ?? 1));
	                        $qtyPiece = intval($it['qty_piece'] ?? 0);
	                        if ($qtyPiece <= 0) {
	                            $unit = strtolower(trim(strval($it['qty_unit'] ?? 'box')));
	                            $qtyPiece = $unit === 'piece' ? max(0, $qty) : max(0, $qty * $boxQty);
	                        }
	                        if ($qtyPiece <= 0) continue;

	                        // 直接入库到“可用库存”（不走来货在途）
	                        \app\admin\common\YunkucunGoodsStock::change(
	                            intval($order['aid']),
	                            intval($order['bid']),
	                            intval($order['mid']),
	                            $proid,
	                            $ggid,
	                            $qtyPiece,
	                            0,
	                            0,
	                            \app\admin\common\YunkucunV3Const::BIZ_REPLENISH_INBOUND,
	                            intval($order['id']),
	                            '升级入库: ' . strval($order['ordernum'] ?? $ordernum),
	                            strval($order['client_request_no'] ?? ''),
	                            true
	                        );
	                    }

	                    $upgrade['applied_time'] = time();
	                    $snapshot['upgrade'] = $upgrade;

	                    Db::name('member')
	                        ->where('aid', intval($order['aid']))
	                        ->where('id', intval($order['mid']))
	                        ->update([
	                            'levelid' => $toLevelId,
	                        ]);

	                    self::settleUpgradeSpecialLevelDiffProfit($order, $items, $snapshot, $ordernum);
	                    self::settleUpgradeSpecialReferrerRewardForRealDelivery($order, $snapshot, $ordernum);
	                    self::settleUpgradeSpecialDepositIncluded($order, $snapshot);

	                    Db::name('yunkucun_replenish_order')
	                        ->where('id', intval($order['id']))
	                        ->update([
	                            'status' => 2,
	                            'inbound_time' => time(),
	                            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
	                            'updatetime' => time(),
	                        ]);

	                    return;
	                }

	                $items = Db::name('yunkucun_replenish_order_item')
	                    ->where('orderid', intval($orderid))
	                    ->select()
	                    ->toArray();
	                if (!$items) {
	                    $items = [];
	                }

		                // 普通补货（虚拟履约）：货款 + 差价利润入云仓收益钱包
		                // - 出库方收“货款”（按出库方进货价口径，mid 允许为 0=总部）
		                // - 差价利润归属 shipper_mid（mid 允许为 0=总部；不允许命中下单人）
		                $replenishGoodsPayByMid = [];
		                $replenishGoodsPayItemsByMid = [];
		                $buyerMidForSettle = intval($order['mid'] ?? 0);
		                $replenishProfitMid = 0;
		                $replenishProfitAmount = 0.0;
		                $replenishProfitItems = [];
		                $peerRewardQtyPiece = 0;
		                $peerRewardQtyBox = 0.0;
		                $peerRewardMetricsByGoods = [];
		                $shipperMidForSettle = 0;
		                $shipperLevelId = 0;
		                $shipperDiscount = 10.0;
		                $skuMap = [];
		                $calcWholesaleByLevel = null;
		                if ($goodsDomain === 1 && $isVirtualFulfillment) {
		                    $shipperMidForSettle = intval($snapshot['shipper_mid'] ?? ($order['shipper_mid'] ?? 0));
		                    if ($shipperMidForSettle === $buyerMidForSettle && $enableHqFulfill) {
		                        $shipperMidForSettle = 0;
		                    }
		                    if ($shipperMidForSettle >= 0 && $shipperMidForSettle !== $buyerMidForSettle) {
		                        $replenishProfitMid = $shipperMidForSettle; // 0=总部；>0=会员

		                        if ($shipperMidForSettle > 0) {
		                            $shipperLevelId = intval(Db::name('member')
		                                ->where('aid', intval($order['aid']))
		                                ->where('id', $shipperMidForSettle)
		                                ->value('levelid'));
		                            $shipperDiscount = floatval(Db::name('member_level')
		                                ->where('aid', intval($order['aid']))
		                                ->where('id', $shipperLevelId)
		                                ->value('discount'));
		                            if ($shipperDiscount <= 0) $shipperDiscount = 10;
		                        } else {
		                            $shipperLevelId = 0;
		                            $shipperDiscount = 10.0;
		                        }

		                        $needSkuIds = [];
		                        foreach ($items as $it0) {
		                            $isGift0 = intval($it0['is_gift'] ?? 0) === 1 || intval($it0['gift_rule_id'] ?? 0) > 0;
		                            if ($isGift0) continue;
		                            $sid0 = intval($it0['ggid'] ?? 0);
		                            if ($sid0 > 0) $needSkuIds[] = $sid0;
		                        }
		                        $needSkuIds = array_values(array_unique($needSkuIds));

		                        if ($needSkuIds) {
		                            $rows = Db::name('yunkucun_goods_sku')
		                                ->where('aid', intval($order['aid']))
		                                ->where('bid', intval($order['bid']))
		                                ->where('id', 'in', $needSkuIds)
		                                ->field('id,goods_id,sku_name,retail_price,wholesale_price,wholesale_lvprice_json,box_qty')
		                                ->select()
		                                ->toArray();
		                            if ($rows) {
		                                foreach ($rows as $r) {
		                                    $skuMap[intval($r['id'] ?? 0)] = $r;
		                                }
		                            }
		                        }

		                        $calcWholesaleByLevel = function (array $skuRow) use ($shipperLevelId, $shipperDiscount): float {
		                            $levelid = intval($shipperLevelId);
		                            if ($levelid > 0 && !empty($skuRow['wholesale_lvprice_json'])) {
		                                $lv = json_decode(strval($skuRow['wholesale_lvprice_json']), true);
		                                if (is_array($lv) && isset($lv[$levelid]) && $lv[$levelid] !== '') {
		                                    return round(floatval($lv[$levelid]), 2);
		                                }
		                            }
		                            $default = floatval($skuRow['wholesale_price'] ?? 0);
		                            if ($default > 0) return round($default, 2);
		                            $retail = floatval($skuRow['retail_price'] ?? 0);
		                            if ($retail <= 0) return 0.0;
		                            $discount = floatval($shipperDiscount);
		                            if ($discount <= 0) $discount = 10;
		                            return round($retail * $discount / 10, 2);
		                        };
		                    }
		                }

			                foreach ($items as $it) {
			                    $proid = intval($it['proid'] ?? 0);
			                    $ggid = intval($it['ggid'] ?? 0);
			                    $qty = intval($it['qty'] ?? 0);
		                    $boxQty = max(1, intval($it['box_qty'] ?? 1));
		                    $qtyPiece = intval($it['qty_piece'] ?? 0);
		                    if ($qtyPiece <= 0) {
		                        $unit = strtolower(trim(strval($it['qty_unit'] ?? 'box')));
		                        $qtyPiece = $unit === 'piece' ? max(0, $qty) : max(0, $qty * $boxQty);
		                    }
		                    if ($qtyPiece <= 0) {
		                        continue;
		                    }

		                    if ($goodsDomain === 1) {
		                        $isGift = intval($it['is_gift'] ?? 0) === 1 || intval($it['gift_rule_id'] ?? 0) > 0;
				                        if ($isVirtualFulfillment) {
					                            // 虚拟补货：先扣出货人库存，再入自己的可用库存
					                            // 约束：补货不允许“自己给自己出库”（即 shipper_mid 不能等于订单 mid）
					                            $buyerMid = $buyerMidForSettle;
					                            $shipperMid = $shipperMidForSettle;
					                            if ($shipperMid === $buyerMid) {
					                                if ($enableHqFulfill) {
					                                    $shipperMid = 0;
					                                } else {
				                                    throw new \Exception('未找到出货人，无法完成补货');
				                                }
				                            }
			                            $fromShipperPiece = 0;
			                            $fromHqPiece = 0;
				                            if ($shipperMid <= 0) {
				                                // 出货人为总部（mid=0）：默认不要求总部在系统内有库存。
				                                // 若总部库存存在记录，则按库存校验并扣减；否则视为“总部无限库存”直接放行。
				                                $fromHqPiece = $qtyPiece;
				                                $hqAvail = Db::name('yunkucun_goods_stock')
				                                    ->where('aid', intval($order['aid']))
				                                    ->where('bid', intval($order['bid']))
				                                    ->where('mid', 0)
				                                    ->where('goods_id', $proid)
				                                    ->where('sku_id', $ggid)
				                                    ->lock(true)
				                                    ->value('available_num');
				                                if ($hqAvail !== null) {
				                                    $hqAvail = intval($hqAvail);
				                                    if ($hqAvail < $qtyPiece) {
				                                        throw new \Exception('总部库存不足，无法完成补货');
				                                    }
				                                    \app\admin\common\YunkucunGoodsStock::change(
				                                        intval($order['aid']),
				                                        intval($order['bid']),
				                                        0,
				                                        $proid,
				                                        $ggid,
				                                        0 - $qtyPiece,
				                                        0,
				                                        0,
				                                        \app\admin\common\YunkucunV3Const::BIZ_REPLENISH_VIRTUAL_OUT,
				                                        intval($order['id']),
				                                        '补货出库(总部): ' . strval($order['ordernum'] ?? $ordernum),
				                                        strval($order['client_request_no'] ?? ''),
				                                        true
				                                    );
				                                }
				                            } else {
				                                $shipAvail = Db::name('yunkucun_goods_stock')
				                                    ->where('aid', intval($order['aid']))
				                                    ->where('bid', intval($order['bid']))
				                                    ->where('mid', $shipperMid)
				                                    ->where('goods_id', $proid)
				                                    ->where('sku_id', $ggid)
				                                    ->lock(true)
				                                    ->value('available_num');

				                                $shipAvail = intval($shipAvail === null ? 0 : $shipAvail);
				                                if ($shipAvail >= $qtyPiece) {
				                                    $fromShipperPiece = $qtyPiece;
				                                    // 全部由出货人扣减
				                                    \app\admin\common\YunkucunGoodsStock::change(
				                                        intval($order['aid']),
				                                        intval($order['bid']),
				                                        $shipperMid,
				                                        $proid,
				                                        $ggid,
				                                        0 - $qtyPiece,
				                                        0,
				                                        0,
				                                        \app\admin\common\YunkucunV3Const::BIZ_REPLENISH_VIRTUAL_OUT,
				                                        intval($order['id']),
				                                        '补货出库: ' . strval($order['ordernum'] ?? $ordernum),
				                                        strval($order['client_request_no'] ?? ''),
				                                        true
				                                    );
				                                } elseif ($enableHqFulfill) {
				                                    $fromShipperPiece = max(0, $shipAvail);
				                                    $needFromHq = $qtyPiece - $shipAvail;
				                                    $fromHqPiece = max(0, $needFromHq);
				                                    $hqAvail = Db::name('yunkucun_goods_stock')
				                                        ->where('aid', intval($order['aid']))
				                                        ->where('bid', intval($order['bid']))
				                                        ->where('mid', 0)
				                                        ->where('goods_id', $proid)
				                                        ->where('sku_id', $ggid)
				                                        ->lock(true)
				                                        ->value('available_num');

				                                    // 若总部库存存在记录，则校验库存；否则按“总部无限库存”放行
				                                    if ($hqAvail !== null) {
				                                        $hqAvail = intval($hqAvail);
				                                        if ($hqAvail < $needFromHq) {
				                                            throw new \Exception('总部库存不足，无法完成补货');
				                                        }
				                                    }

				                                    // 先扣出货人可用部分
				                                    if ($shipAvail > 0) {
				                                        \app\admin\common\YunkucunGoodsStock::change(
				                                            intval($order['aid']),
				                                            intval($order['bid']),
				                                            $shipperMid,
				                                            $proid,
				                                            $ggid,
				                                            0 - $shipAvail,
				                                            0,
				                                            0,
				                                            \app\admin\common\YunkucunV3Const::BIZ_REPLENISH_VIRTUAL_OUT,
				                                            intval($order['id']),
				                                            '补货出库: ' . strval($order['ordernum'] ?? $ordernum),
				                                            strval($order['client_request_no'] ?? ''),
				                                            true
				                                        );
				                                    }
				                                    // 缺口由总部扣减（若总部未维护库存，则不扣减，只做放行）
				                                    if ($needFromHq > 0 && $hqAvail !== null) {
				                                        \app\admin\common\YunkucunGoodsStock::change(
				                                            intval($order['aid']),
				                                            intval($order['bid']),
				                                            0,
				                                            $proid,
				                                            $ggid,
				                                            0 - $needFromHq,
				                                            0,
				                                            0,
				                                            \app\admin\common\YunkucunV3Const::BIZ_REPLENISH_VIRTUAL_OUT,
				                                            intval($order['id']),
				                                            '补货出库(总部兜底): ' . strval($order['ordernum'] ?? $ordernum),
				                                            strval($order['client_request_no'] ?? ''),
				                                            true
				                                        );
				                                    }
				                                } else {
				                                    throw new \Exception('出货人库存不足，无法完成补货');
				                                }
				                            }

				                            // 货款/利润结算（仅统计非赠品）
				                            if (!$isGift) {
				                                $peerRewardQtyPiece += intval($qtyPiece);
				                                $boxesForPeerReward = floatval($qtyPiece) / max(1, intval($boxQty));
				                                $peerRewardQtyBox = floatval($peerRewardQtyBox) + $boxesForPeerReward;

				                                $buyerWholesale = round(floatval($it['wholesale_price'] ?? 0), 2);
				                                if ($buyerWholesale < 0) $buyerWholesale = 0;

				                                $gid0 = intval($proid);
				                                if ($gid0 > 0) {
				                                    if (!isset($peerRewardMetricsByGoods[$gid0])) {
				                                        $peerRewardMetricsByGoods[$gid0] = [
				                                            'goods_id' => $gid0,
				                                            'goods_name' => strval($it['name'] ?? ''),
				                                            'qty_piece' => 0,
				                                            'qty_box' => 0.0,
				                                            'goods_amount' => 0.0,
				                                        ];
				                                    }
				                                    $peerRewardMetricsByGoods[$gid0]['qty_piece'] = intval($peerRewardMetricsByGoods[$gid0]['qty_piece'] ?? 0) + intval($qtyPiece);
				                                    $peerRewardMetricsByGoods[$gid0]['qty_box'] = floatval($peerRewardMetricsByGoods[$gid0]['qty_box'] ?? 0) + floatval($boxesForPeerReward);
				                                    $peerRewardMetricsByGoods[$gid0]['goods_amount'] = floatval($peerRewardMetricsByGoods[$gid0]['goods_amount'] ?? 0) + (floatval($buyerWholesale) * floatval($boxesForPeerReward));
				                                }

				                                // 出库方进货价口径：默认按 shipper 进货价（若异常则回退按 buyer 进货价，避免负利润/超发）
				                                $shipperWholesale = $buyerWholesale;
				                                if ($replenishProfitMid >= 0 && is_callable($calcWholesaleByLevel)) {
				                                    $skuRow = is_array($skuMap[$ggid] ?? null) ? $skuMap[$ggid] : [];
				                                    if ($skuRow) {
				                                        $shipperWholesale = $calcWholesaleByLevel($skuRow);
				                                    }
				                                }
			                                $shipperWholesale = round(floatval($shipperWholesale), 2);
			                                if ($shipperWholesale <= 0) $shipperWholesale = $buyerWholesale;
			                                if ($shipperWholesale > $buyerWholesale) $shipperWholesale = $buyerWholesale;

			                                $addSettle = function (int $piece, int $receiveMid, string $from) use (
			                                    &$replenishGoodsPayByMid,
			                                    &$replenishGoodsPayItemsByMid,
			                                    &$replenishProfitAmount,
			                                    &$replenishProfitItems,
			                                    $proid,
			                                    $ggid,
			                                    $boxQty,
			                                    $buyerWholesale,
			                                    $shipperWholesale,
			                                    $replenishProfitMid,
			                                    $isUpgradeSpecialGoodsOrder
			                                ) {
			                                    if ($piece <= 0) return;
			                                    $boxes = $piece / max(1, $boxQty);
			                                    $buyerAmount = round($buyerWholesale * $boxes, 2);
			                                    $goodsAmount = round($shipperWholesale * $boxes, 2);
			                                    if ($goodsAmount > $buyerAmount) $goodsAmount = $buyerAmount;
			                                    $profitAmount = round($buyerAmount - $goodsAmount, 2);

			                                    if ($goodsAmount > 0) {
			                                        if (!isset($replenishGoodsPayByMid[$receiveMid])) {
			                                            $replenishGoodsPayByMid[$receiveMid] = 0.0;
			                                            $replenishGoodsPayItemsByMid[$receiveMid] = [];
			                                        }
			                                        $replenishGoodsPayByMid[$receiveMid] = round(floatval($replenishGoodsPayByMid[$receiveMid]) + $goodsAmount, 2);
			                                        $replenishGoodsPayItemsByMid[$receiveMid][] = [
			                                            'proid' => $proid,
			                                            'ggid' => $ggid,
			                                            'qty_piece' => $piece,
			                                            'box_qty' => $boxQty,
			                                            'boxes' => round($boxes, 4),
			                                            'buyer_wholesale_price' => $buyerWholesale,
			                                            'shipper_wholesale_price' => $shipperWholesale,
			                                            'goods_amount' => $goodsAmount,
			                                            'from' => $from,
			                                        ];
			                                    }

				                                    if ($replenishProfitMid >= 0 && $profitAmount > 0 && !$isUpgradeSpecialGoodsOrder) {
				                                        $replenishProfitAmount = round(floatval($replenishProfitAmount) + $profitAmount, 2);
				                                        $replenishProfitItems[] = [
				                                            'proid' => $proid,
				                                            'ggid' => $ggid,
			                                            'qty_piece' => $piece,
			                                            'box_qty' => $boxQty,
			                                            'boxes' => round($boxes, 4),
			                                            'buyer_wholesale_price' => $buyerWholesale,
			                                            'shipper_wholesale_price' => $shipperWholesale,
			                                            'profit_amount' => $profitAmount,
			                                            'from' => $from,
			                                        ];
			                                    }
			                                };

			                                // 出货人扣多少，记多少货款；总部兜底部分，货款记到总部，差价利润记到 shipper_mid
			                                if ($shipperMid <= 0) {
			                                    $addSettle($qtyPiece, 0, 'hq');
			                                } else {
			                                    if ($fromShipperPiece > 0) {
			                                        $addSettle($fromShipperPiece, $shipperMid, 'shipper');
			                                    }
			                                    if ($fromHqPiece > 0) {
			                                        $addSettle($fromHqPiece, 0, 'hq_fulfill');
			                                    }
			                                }
			                            }
		                            // 虚拟补货：支付后直接入可用库存
		                            \app\admin\common\YunkucunGoodsStock::change(
		                                intval($order['aid']),
		                                intval($order['bid']),
		                                intval($order['mid']),
		                                $proid,
		                                $ggid,
		                                $qtyPiece,
		                                0,
		                                0,
		                                \app\admin\common\YunkucunV3Const::BIZ_REPLENISH_INBOUND,
		                                intval($order['id']),
		                                '补货入库: ' . strval($order['ordernum'] ?? $ordernum),
		                                strval($order['client_request_no'] ?? ''),
		                                true
		                            );
		                        } else {
				                        \app\admin\common\YunkucunGoodsStock::replenishPaidIncoming(
				                            intval($order['aid']),
				                            intval($order['bid']),
				                            intval($order['mid']),
				                            $proid,
				                            $ggid,
				                            $qtyPiece,
				                            intval($order['id']),
				                            '补货已支付(来货在途): ' . strval($order['ordernum'] ?? $ordernum),
				                            strval($order['client_request_no'] ?? ''),
				                            true
				                        );
		                        }

		                        // 赠品：扣减总部预占库存（冻结 -> 出库）
		                        if ($isGift) {
		                            \app\admin\common\YunkucunGoodsStock::promoGiftOutHq(
		                                intval($order['aid']),
		                                intval($order['bid']),
		                                $proid,
		                                $ggid,
		                                $qtyPiece,
		                                intval($order['id']),
		                                '赠品出库: ' . strval($order['ordernum'] ?? $ordernum),
		                                strval($order['client_request_no'] ?? ''),
		                                true
			                            );
			                        }
	                    } else {
		                        \app\admin\common\YunkucunV2Stock::change(
		                            intval($order['aid']),
		                            intval($order['bid']),
		                            intval($order['mid']),
		                            $proid,
		                            $ggid,
		                            $qty,
		                            0,
		                            0,
		                            \app\admin\common\YunkucunV3Const::BIZ_REPLENISH_INBOUND,
		                            intval($order['id']),
		                            '补货入库: ' . strval($order['ordernum'] ?? $ordernum),
		                            strval($order['client_request_no'] ?? ''),
		                            true
		                        );
	                    }
	                }

	                // 补货货款（支付后）入云仓收益钱包（按出库方）
	                if ($goodsDomain === 1 && $isVirtualFulfillment && $replenishGoodsPayByMid) {
	                    foreach ($replenishGoodsPayByMid as $recvMid => $amt) {
	                        $amt = round(floatval($amt), 2);
	                        if ($amt <= 0) continue;
	                        $itemsDetail = is_array($replenishGoodsPayItemsByMid[$recvMid] ?? null) ? $replenishGoodsPayItemsByMid[$recvMid] : [];
		                        $credit = \app\admin\common\YunkucunGoodsMoneylog::creditReplenishGoodsPay(
		                            intval($order['aid']),
		                            intval($order['bid'] ?? 0),
		                            intval($order['mid'] ?? 0),
		                            intval($order['id'] ?? 0),
		                            strval($order['ordernum'] ?? $ordernum),
		                            strval($order['client_request_no'] ?? ''),
		                            intval($recvMid),
		                            floatval($amt),
		                            intval($shipperMidForSettle),
		                            $itemsDetail
		                        );
		                        if (is_array($credit) && intval($credit['status'] ?? 1) === 0) {
		                            throw new \Exception(strval($credit['msg'] ?? '云仓补货货款入账失败'));
		                        }
	                    }
	                    $snapshot['replenish_goods_pay'] = [
	                        'items' => $replenishGoodsPayItemsByMid,
	                        'settled_time' => time(),
		                    ];
		                }

		                // 升级专用商品：按推荐链路逐级结算价差利润（平台发货口径）
		                if ($goodsDomain === 1 && $isVirtualFulfillment && $isUpgradeSpecialGoodsOrder) {
		                    self::settleUpgradeSpecialLevelDiffProfit($order, $items, $snapshot, $ordernum);
		                    $replenishProfitMid = -1;
		                    $replenishProfitAmount = 0.0;
		                    $replenishProfitItems = [];
		                }

		                // 补货平级奖/感恩奖：由出货人承担（从差价利润中扣除），实时发放（按商品配置）
		                $peerRewardMid = 0;
		                $peerRewardAmount = 0.0;
		                $peerRewardRawAmount = 0.0;
		                $peerRewardLevelId = 0;
		                $upgradeReferrerMid = 0;
		                $upgradeReferrerRewardAmount = 0.0;
		                $upgradeReferrerRewardRawAmount = 0.0;
		                $upgradeReferrerRewardCapped = 0;
		                $upgradeReferrerRewardSkippedByProfit = 0;
		                $upgradeReferrerRewardSkippedByRule = 0;
		                $upgradeReferrerRewardMode = '';
		                $upgradeReferrerRewardSubsidyAmount = 0.0;
		                $upgradeReferrerRewardFixedAmount = 0.0;
		                $upgradeReferrerRewardEligibleLevelids = [];
		                $upgradeReferrerLevelId = 0;
		                $upgradeReferrerRewardPayerMid = 0;
		                $upgradeReferrerRewardProfitMid = 0;
		                $upgradeReferrerRewardPayerMode = 'platform';
		                $upgradeReferrerRewardTheoreticalShipperMid = 0;
		                $upgradeReferrerRewardDeductAmount = 0.0;
		                $upgradeReferrerRewardFromShipperProfit = 0;
		                $grossProfitAmount = round(floatval($replenishProfitAmount), 2);
		                $peerRewardSkippedBySwitch = ($goodsDomain === 1 && $isVirtualFulfillment && $isUpgrade && !$enableUpgradePeerReward);
		                if ($peerRewardSkippedBySwitch) {
		                    $snapshot['peer_reward_skip'] = [
		                        'skip' => 1,
		                        'reason' => 'upgrade_peer_reward_disabled',
		                        'reason_text' => '升级补货未参与平级奖（系统开关关闭）',
		                        'settled_time' => time(),
		                        'gross_profit_amount' => round(floatval($grossProfitAmount), 2),
		                    ];
		                }

		                if ($goodsDomain === 1 && $isVirtualFulfillment && $grossProfitAmount > 0 && $replenishProfitMid >= 0 && (!$isUpgrade || $enableUpgradePeerReward)) {
		                    // 目标等级：普通补货=下单人当前等级；升级补货=升级目标等级
		                    if ($isUpgrade) {
		                        $upgrade = is_array($snapshot['upgrade'] ?? null) ? $snapshot['upgrade'] : [];
		                        $peerRewardLevelId = intval($upgrade['to_levelid'] ?? 0);
		                    } else {
		                        $peerRewardLevelId = intval($snapshot['buyer_levelid'] ?? 0);
		                    }
		                    if ($peerRewardLevelId <= 0 && $buyerMidForSettle > 0) {
		                        $peerRewardLevelId = intval(Db::name('member')
		                            ->where('aid', intval($order['aid']))
		                            ->where('id', $buyerMidForSettle)
		                            ->value('levelid'));
		                    }

		                    // 先按商品配置计算“应发金额”（未扣差价利润）
		                    $peerRewardBreakdown = [];
		                    $peerRewardReceiverLevelIds = [];
		                    if ($peerRewardLevelId > 0 && $peerRewardMetricsByGoods) {
		                        $cfg = is_array($snapshot['peer_reward_config'] ?? null) ? $snapshot['peer_reward_config'] : [];
		                        $cfgLevelId = intval($cfg['levelid'] ?? 0);
		                        $cfgByGoods = is_array($cfg['by_goods'] ?? null) ? $cfg['by_goods'] : [];
		                        if ($cfgLevelId !== $peerRewardLevelId) {
		                            $cfgByGoods = [];
		                        }

		                        $dbRuleByGoods = [];
		                        if (!$cfgByGoods) {
		                            $needGoodsIds = array_values(array_unique(array_filter(array_map('intval', array_keys($peerRewardMetricsByGoods)), function ($v) {
		                                return $v > 0;
		                            })));
		                            if ($needGoodsIds) {
		                                try {
		                                    $rows = Db::name('yunkucun_goods')
		                                        ->where('aid', intval($order['aid']))
		                                        ->where('bid', intval($order['bid']))
		                                        ->where('id', 'in', $needGoodsIds)
		                                        ->field('id,peer_reward_rule_json')
		                                        ->select()
		                                        ->toArray();
		                                    if ($rows) {
		                                        foreach ($rows as $r0) {
		                                            $gid0 = intval($r0['id'] ?? 0);
		                                            if ($gid0 <= 0) continue;
		                                            $tmp = json_decode(strval($r0['peer_reward_rule_json'] ?? ''), true);
		                                            $tmp = is_array($tmp) ? $tmp : [];
		                                            $rule = $tmp[strval($peerRewardLevelId)] ?? ($tmp[$peerRewardLevelId] ?? []);
		                                            $dbRuleByGoods[$gid0] = is_array($rule) ? $rule : [];
		                                        }
		                                    }
		                                } catch (\Throwable $e) {
		                                    $dbRuleByGoods = [];
		                                }
		                            }
		                        }

		                        foreach ($peerRewardMetricsByGoods as $gid0 => $m0) {
		                            $gid0 = intval($gid0);
		                            if ($gid0 <= 0) continue;
		                            $m0 = is_array($m0) ? $m0 : [];
		                            $qtyPiece0 = intval($m0['qty_piece'] ?? 0);
		                            if ($qtyPiece0 <= 0) continue;
		                            $qtyBox0 = floatval($m0['qty_box'] ?? 0);
		                            $baseGoodsAmount0 = floatval($m0['goods_amount'] ?? 0);
		                            if ($baseGoodsAmount0 < 0) $baseGoodsAmount0 = 0.0;

		                            $rule = $cfgByGoods[strval($gid0)] ?? ($cfgByGoods[$gid0] ?? null);
		                            if (!is_array($rule)) {
		                                $rule = $dbRuleByGoods[$gid0] ?? ($dbRuleByGoods[strval($gid0)] ?? []);
		                            }
		                            $rule = is_array($rule) ? $rule : [];

		                            $type = intval($rule['type'] ?? 0);
		                            if (!in_array($type, [0, 1, 2], true)) $type = 0;
		                            $rate = round(floatval($rule['rate'] ?? 0), 2);
		                            if ($rate < 0) $rate = 0;
		                            if ($rate > 100) $rate = 100;
		                            $fixedAmount = round(max(0, floatval($rule['fixed_amount'] ?? 0)), 2);
		                            $fixedUnit = strtolower(trim(strval($rule['fixed_unit'] ?? 'box')));
		                            if (!in_array($fixedUnit, ['box', 'piece'], true)) $fixedUnit = 'box';

		                            if ($type === 1 && $rate <= 0) $type = 0;
		                            if ($type === 2 && $fixedAmount <= 0) $type = 0;
		                            if ($type <= 0) continue;

		                            $raw0 = 0.0;
		                            if ($type === 1) {
		                                $raw0 = $baseGoodsAmount0 * $rate / 100;
		                            } elseif ($type === 2) {
		                                $raw0 = $fixedUnit === 'piece'
		                                    ? $fixedAmount * $qtyPiece0
		                                    : $fixedAmount * $qtyBox0;
		                            }
		                            $raw0 = round(floatval($raw0), 2);
		                            if ($raw0 <= 0) continue;

		                            $peerRewardRawAmount = round(floatval($peerRewardRawAmount) + $raw0, 2);
		                            $peerRewardBreakdown[$gid0] = [
		                                'goods_id' => $gid0,
		                                'goods_name' => strval($m0['goods_name'] ?? ''),
		                                'base_goods_amount' => round(floatval($baseGoodsAmount0), 2),
		                                'qty_piece' => $qtyPiece0,
		                                'qty_box' => round(floatval($qtyBox0), 6),
		                                'type' => $type,
		                                'rate' => round(floatval($rate), 2),
		                                'fixed_amount' => round(floatval($fixedAmount), 2),
		                                'fixed_unit' => strval($fixedUnit),
		                                'raw_reward_amount' => $raw0,
		                            ];
		                        }
		                    }

		                    // 找“距离最近的同级/同组”（按推荐关系链 path 反向；命中等级集合可配置）
		                    if ($peerRewardRawAmount > 0 && $peerRewardLevelId > 0 && $buyerMidForSettle > 0) {
		                        $ruleRow = [];
		                        try {
		                            $ruleRow = Db::name('yunkucun_goods_level_rule')
		                                ->where('aid', intval($order['aid']))
		                                ->where('levelid', $peerRewardLevelId)
		                                ->find();
		                        } catch (\Throwable $e) {
		                            $ruleRow = [];
		                        }
		                        $ruleRow = is_array($ruleRow) ? $ruleRow : [];
		                        $rawLevelIds = trim(strval($ruleRow['peer_reward_levelids'] ?? ''));
		                        if ($rawLevelIds !== '') {
		                            $peerRewardReceiverLevelIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawLevelIds)), function ($v) {
		                                return $v > 0;
		                            })));
		                        }
		                        if (!$peerRewardReceiverLevelIds) {
		                            $peerRewardReceiverLevelIds = [$peerRewardLevelId];
		                        }
		                        $hitLevelSet = array_fill_keys($peerRewardReceiverLevelIds, 1);

		                        $buyer = Db::name('member')
		                            ->where('aid', intval($order['aid']))
		                            ->where('id', $buyerMidForSettle)
		                            ->field('id,path,isxuni')
		                            ->find();
		                        if ($buyer && intval($buyer['isxuni'] ?? 0) !== 1) {
		                            $pathRaw = trim(strval($buyer['path'] ?? ''));
		                            if ($pathRaw !== '') {
		                                $pathIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $pathRaw)), function ($v) {
		                                    return $v > 0;
		                                })));
		                                $pathIdsRev = array_reverse($pathIds);
		                                if ($pathIdsRev) {
		                                    $rows = Db::name('member')
		                                        ->where('aid', intval($order['aid']))
		                                        ->where('id', 'in', $pathIdsRev)
		                                        ->where('isxuni', 0)
		                                        ->field('id,levelid')
		                                        ->select()
		                                        ->toArray();
		                                    $levelMap = [];
		                                    foreach ($rows as $r0) {
		                                        $levelMap[intval($r0['id'] ?? 0)] = intval($r0['levelid'] ?? 0);
		                                    }
		                                    foreach ($pathIdsRev as $pid0) {
		                                        $lv0 = intval($levelMap[intval($pid0)] ?? 0);
		                                        if ($lv0 > 0 && isset($hitLevelSet[$lv0])) {
		                                            $peerRewardMid = intval($pid0);
		                                            break;
		                                        }
		                                    }
		                                }
		                            }
		                        }
		                    }

		                    // 从差价利润中扣除并入账
			                    if ($peerRewardMid > 0 && $peerRewardMid !== intval($replenishProfitMid)) {
			                        $peerRewardAmount = $peerRewardRawAmount > $grossProfitAmount ? $grossProfitAmount : $peerRewardRawAmount;
			                        $peerRewardAmount = round(max(0, floatval($peerRewardAmount)), 2);
			                        if ($peerRewardAmount > 0) {
		                            $replenishProfitAmount = round($grossProfitAmount - $peerRewardAmount, 2);
		                            if ($replenishProfitAmount < 0) $replenishProfitAmount = 0.0;

		                            $calcDetail = [
		                                'profit_mid' => intval($replenishProfitMid),
		                                'reward_levelid' => intval($peerRewardLevelId),
		                                'receiver_levelids' => implode(',', array_values(array_unique(array_map('intval', $peerRewardReceiverLevelIds)))),
		                                'base_goods_amount' => round(floatval($order['goods_amount'] ?? 0), 2),
		                                'qty_piece' => intval($peerRewardQtyPiece),
		                                'qty_box' => round(floatval($peerRewardQtyBox), 6),
		                                'raw_reward_amount' => round(floatval($peerRewardRawAmount), 2),
		                                'gross_profit_amount' => round(floatval($grossProfitAmount), 2),
		                                'net_profit_amount' => round(floatval($replenishProfitAmount), 2),
		                                'by_goods' => $peerRewardBreakdown,
		                            ];

		                            $credit = \app\admin\common\YunkucunGoodsMoneylog::creditReplenishPeerReward(
		                                intval($order['aid']),
		                                intval($order['bid'] ?? 0),
		                                intval($buyerMidForSettle),
		                                intval($order['id'] ?? 0),
		                                strval($order['ordernum'] ?? $ordernum),
		                                strval($order['client_request_no'] ?? ''),
		                                intval($peerRewardMid),
		                                floatval($peerRewardAmount),
		                                intval($shipperMidForSettle),
		                                $calcDetail
		                            );
		                            if (is_array($credit) && intval($credit['status'] ?? 1) === 0) {
		                                throw new \Exception(strval($credit['msg'] ?? '云仓补货平级奖入账失败'));
		                            }

		                            $snapshot['peer_reward'] = [
		                                'peer_mid' => intval($peerRewardMid),
		                                'reward_amount' => round(floatval($peerRewardAmount), 2),
		                                'raw_reward_amount' => round(floatval($peerRewardRawAmount), 2),
		                                'gross_profit_amount' => round(floatval($grossProfitAmount), 2),
		                                'net_profit_amount' => round(floatval($replenishProfitAmount), 2),
		                                'reward_levelid' => intval($peerRewardLevelId),
		                                'receiver_levelids' => implode(',', array_values(array_unique(array_map('intval', $peerRewardReceiverLevelIds)))),
		                                'by_goods' => $peerRewardBreakdown,
		                                'settled' => 1,
		                                'settled_time' => time(),
		                            ];
			                        }
			                    }
			                }

		                // 升级推荐奖：卖货人=推荐人（pid）
		                // - 普通升级：沿用“每件奖励”口径
		                // - 升级专用商品：按“每单固定金额 + 等级白名单”口径
		                if ($goodsDomain === 1 && $isUpgrade) {
		                    $upgrade = is_array($snapshot['upgrade'] ?? null) ? $snapshot['upgrade'] : [];
		                    $upgradeToLevelId = intval($upgrade['to_levelid'] ?? 0);
		                    $upgradeNeedPiece = intval($upgrade['need_piece'] ?? 0);
		                    if ($upgradeNeedPiece <= 0) {
		                        $upgradeNeedPiece = intval($upgrade['total_piece'] ?? 0);
		                    }
		                    $upgradeSpecial = is_array($upgrade['special_goods'] ?? null) ? $upgrade['special_goods'] : [];
		                    $isUpgradeSpecialGoods = intval($upgradeSpecial['enabled'] ?? 0) === 1;

		                    $upgradeReferrerMid = intval(Db::name('member')
		                        ->where('aid', intval($order['aid']))
		                        ->where('id', intval($order['mid']))
		                        ->value('pid'));
		                    if ($upgradeReferrerMid > 0 && $upgradeReferrerMid !== intval($order['mid'])) {
		                        $upgradeReferrerRewardPerPiece = 0.0;
		                        $upgradeReferrerRewardPiece = max(1, $upgradeNeedPiece);
		                        $upgradeReferrerRewardRawAmount = 0.0;
		                        $upgradeReferrerRewardMode = $isUpgradeSpecialGoods ? 'fixed_order' : 'per_piece';

		                        if ($isUpgradeSpecialGoods) {
		                            $upgradeReferrerRewardFixedAmount = round(max(0, floatval($upgradeSpecial['fixed_reward_amount'] ?? 0)), 2);
		                            $upgradeReferrerRewardEligibleLevelids = self::parsePositiveIdsCsv($upgradeSpecial['reward_levelids'] ?? '');
		                            $upgradeReferrerLevelId = intval(Db::name('member')
		                                ->where('aid', intval($order['aid']))
		                                ->where('id', intval($upgradeReferrerMid))
		                                ->value('levelid'));

		                            if (
		                                $upgradeReferrerRewardFixedAmount <= 0
		                                || !$upgradeReferrerRewardEligibleLevelids
		                                || !in_array($upgradeReferrerLevelId, $upgradeReferrerRewardEligibleLevelids, true)
		                            ) {
		                                $upgradeReferrerRewardSkippedByRule = 1;
		                                $snapshot['upgrade_referrer_reward_skip'] = [
		                                    'skip' => 1,
		                                    'reason' => 'special_level_not_match',
		                                    'reason_text' => '升级专用商品推荐奖未发放（推荐人等级不在可得等级内或金额为0）',
		                                    'mode' => 'fixed_order',
		                                    'referrer_mid' => intval($upgradeReferrerMid),
		                                    'referrer_levelid' => intval($upgradeReferrerLevelId),
		                                    'eligible_levelids' => implode(',', array_values(array_unique(array_map('intval', $upgradeReferrerRewardEligibleLevelids)))),
		                                    'fixed_reward_amount' => round(floatval($upgradeReferrerRewardFixedAmount), 2),
		                                    'to_levelid' => intval($upgradeToLevelId),
		                                    'settled_time' => time(),
		                                ];
		                            } else {
		                                $upgradeReferrerRewardPiece = 1;
		                                $upgradeReferrerRewardPerPiece = round(floatval($upgradeReferrerRewardFixedAmount), 2);
		                                $upgradeReferrerRewardRawAmount = round(floatval($upgradeReferrerRewardFixedAmount), 2);
		                            }
		                        } else {
		                            $upgradeReferrerRewardPerPiece = round(max(0, floatval($upgrade['referrer_reward_per_piece'] ?? 0)), 2);
		                            if ($upgradeReferrerRewardPerPiece <= 0 && $upgradeToLevelId > 0) {
		                                try {
		                                    $upgradeReferrerRewardPerPiece = round(max(0, floatval(Db::name('yunkucun_goods_level_rule')
		                                        ->where('aid', intval($order['aid']))
		                                        ->where('levelid', $upgradeToLevelId)
		                                        ->value('upgrade_referrer_reward_per_piece'))), 2);
		                                } catch (\Throwable $e) {
		                                    $upgradeReferrerRewardPerPiece = 0.0;
		                                }
		                            }
		                            if ($upgradeNeedPiece > 0 && $upgradeReferrerRewardPerPiece > 0) {
		                                $upgradeReferrerRewardRawAmount = round($upgradeNeedPiece * $upgradeReferrerRewardPerPiece, 2);
		                            }
		                        }

		                        if ($upgradeReferrerRewardRawAmount > 0) {
		                            if ($isUpgradeSpecialGoods) {
		                                // 升级专用商品推荐奖：支持“平台承担 / 理论发货方承担（不足平台补差）”
		                                $upgradeReferrerRewardAmount = round(floatval($upgradeReferrerRewardRawAmount), 2);
		                                $upgradeReferrerRewardCapped = 0;
		                                $rewardFunding = is_array($snapshot['upgrade_special_reward_funding'] ?? null)
		                                    ? $snapshot['upgrade_special_reward_funding']
		                                    : [];
		                                $upgradeReferrerRewardPayerMode = strtolower(trim(strval($rewardFunding['mode'] ?? 'platform')));
		                                if ($upgradeReferrerRewardPayerMode !== 'theoretical_shipper') {
		                                    $upgradeReferrerRewardPayerMode = 'platform';
		                                }
		                                $upgradeReferrerRewardTheoreticalShipperMid = max(0, intval($rewardFunding['theoretical_shipper_mid'] ?? 0));
		                                $upgradeReferrerRewardPayerMid = max(0, intval($rewardFunding['payer_mid'] ?? 0));
		                                $upgradeReferrerRewardProfitMid = $upgradeReferrerRewardPayerMid;
		                                $upgradeReferrerRewardDeductAmount = round(max(0, floatval($rewardFunding['deduct_amount'] ?? 0)), 2);
		                                $upgradeReferrerRewardSubsidyAmount = round(max(0, floatval($rewardFunding['subsidy_amount'] ?? $upgradeReferrerRewardAmount)), 2);
		                                $upgradeReferrerRewardFromShipperProfit = $upgradeReferrerRewardDeductAmount > 0 ? 1 : 0;
		                            } else {
		                                $upgradeReferrerRewardAmount = $upgradeReferrerRewardRawAmount;
		                                if ($upgradeReferrerRewardAmount > $replenishProfitAmount) {
		                                    $upgradeReferrerRewardAmount = round(max(0, floatval($replenishProfitAmount)), 2);
		                                }
		                                $upgradeReferrerRewardAmount = round(max(0, floatval($upgradeReferrerRewardAmount)), 2);
		                                $upgradeReferrerRewardCapped = $upgradeReferrerRewardAmount < $upgradeReferrerRewardRawAmount ? 1 : 0;
		                                $upgradeReferrerRewardPayerMode = 'shipper_profit';
		                                $upgradeReferrerRewardTheoreticalShipperMid = 0;
		                                $upgradeReferrerRewardPayerMid = intval($shipperMidForSettle);
		                                $upgradeReferrerRewardProfitMid = intval($replenishProfitMid);
		                                $upgradeReferrerRewardDeductAmount = round(max(0, floatval($upgradeReferrerRewardAmount)), 2);
		                                $upgradeReferrerRewardSubsidyAmount = 0.0;
		                                $upgradeReferrerRewardFromShipperProfit = $upgradeReferrerRewardAmount > 0 ? 1 : 0;
		                            }

		                            if ($upgradeReferrerRewardAmount > 0) {
		                                if (!$isUpgradeSpecialGoods) {
		                                    $replenishProfitAmount = round(floatval($replenishProfitAmount) - $upgradeReferrerRewardAmount, 2);
		                                    if ($replenishProfitAmount < 0) $replenishProfitAmount = 0.0;
		                                }

		                                $credit = \app\admin\common\YunkucunGoodsMoneylog::creditUpgradeReferrerReward(
		                                    intval($order['aid']),
		                                    intval($order['bid'] ?? 0),
		                                    intval($order['mid'] ?? 0),
		                                    intval($order['id'] ?? 0),
		                                    strval($order['ordernum'] ?? $ordernum),
		                                    strval($order['client_request_no'] ?? ''),
		                                    intval($upgradeReferrerMid),
		                                    floatval($upgradeReferrerRewardAmount),
		                                    intval($upgradeToLevelId),
		                                    intval($upgradeReferrerRewardPiece),
		                                    floatval($upgradeReferrerRewardPerPiece),
		                                    [
		                                        'mode' => strval($upgradeReferrerRewardMode),
		                                        'fixed_reward_amount' => round(floatval($upgradeReferrerRewardFixedAmount), 2),
		                                        'eligible_levelids' => implode(',', array_values(array_unique(array_map('intval', $upgradeReferrerRewardEligibleLevelids)))),
		                                        'referrer_levelid' => intval($upgradeReferrerLevelId),
		                                        'shipper_mid' => intval($shipperMidForSettle),
		                                        'payer_mid' => intval($upgradeReferrerRewardPayerMid),
		                                        'profit_mid' => intval($upgradeReferrerRewardProfitMid),
		                                        'reward_payer_mode' => strval($upgradeReferrerRewardPayerMode),
		                                        'theoretical_shipper_mid' => intval($upgradeReferrerRewardTheoreticalShipperMid),
		                                        'raw_reward_amount' => round(floatval($upgradeReferrerRewardRawAmount), 2),
		                                        'gross_profit_amount' => round(floatval($grossProfitAmount), 2),
		                                        'net_profit_amount' => round(floatval($replenishProfitAmount), 2),
		                                        'capped_by_profit' => intval($upgradeReferrerRewardCapped),
		                                        'deduct_amount' => round(floatval($upgradeReferrerRewardDeductAmount), 2),
		                                        'subsidy_amount' => round(floatval($upgradeReferrerRewardSubsidyAmount), 2),
		                                        'from_shipper_profit' => intval($upgradeReferrerRewardFromShipperProfit),
		                                    ]
		                                );
		                                if (is_array($credit) && intval($credit['status'] ?? 1) === 0) {
		                                    throw new \Exception(strval($credit['msg'] ?? '云仓升级推荐奖入账失败'));
		                                }

		                                $snapshot['upgrade_referrer_reward'] = [
		                                    'mode' => strval($upgradeReferrerRewardMode),
		                                    'referrer_mid' => intval($upgradeReferrerMid),
		                                    'referrer_levelid' => intval($upgradeReferrerLevelId),
		                                    'eligible_levelids' => implode(',', array_values(array_unique(array_map('intval', $upgradeReferrerRewardEligibleLevelids)))),
		                                    'to_levelid' => intval($upgradeToLevelId),
		                                    'reward_piece' => intval($upgradeReferrerRewardPiece),
		                                    'reward_per_piece' => round(floatval($upgradeReferrerRewardPerPiece), 2),
		                                    'fixed_reward_amount' => round(floatval($upgradeReferrerRewardFixedAmount), 2),
		                                    'raw_reward_amount' => round(floatval($upgradeReferrerRewardRawAmount), 2),
		                                    'reward_amount' => round(floatval($upgradeReferrerRewardAmount), 2),
		                                    'payer_mid' => intval($upgradeReferrerRewardPayerMid),
		                                    'reward_payer_mode' => strval($upgradeReferrerRewardPayerMode),
		                                    'theoretical_shipper_mid' => intval($upgradeReferrerRewardTheoreticalShipperMid),
		                                    'deduct_amount' => round(floatval($upgradeReferrerRewardDeductAmount), 2),
		                                    'subsidy_amount' => round(floatval($upgradeReferrerRewardSubsidyAmount), 2),
		                                    'shipper_mid' => intval($shipperMidForSettle),
		                                    'profit_mid' => intval($upgradeReferrerRewardProfitMid),
		                                    'gross_profit_amount' => round(floatval($grossProfitAmount), 2),
		                                    'net_profit_amount' => round(floatval($replenishProfitAmount), 2),
		                                    'capped_by_profit' => intval($upgradeReferrerRewardCapped),
		                                    'from_shipper_profit' => intval($upgradeReferrerRewardFromShipperProfit),
		                                    'settled' => 1,
		                                    'settled_time' => time(),
		                                ];
		                            } elseif (!$isUpgradeSpecialGoods) {
		                                $upgradeReferrerRewardSkippedByProfit = 1;
		                                $snapshot['upgrade_referrer_reward_skip'] = [
		                                    'skip' => 1,
		                                    'reason' => 'shipper_profit_insufficient',
		                                    'reason_text' => '升级推荐奖未发放（出货人差价利润不足）',
		                                    'mode' => strval($upgradeReferrerRewardMode),
		                                    'referrer_mid' => intval($upgradeReferrerMid),
		                                    'to_levelid' => intval($upgradeToLevelId),
		                                    'reward_piece' => intval($upgradeReferrerRewardPiece),
		                                    'reward_per_piece' => round(floatval($upgradeReferrerRewardPerPiece), 2),
		                                    'fixed_reward_amount' => round(floatval($upgradeReferrerRewardFixedAmount), 2),
		                                    'raw_reward_amount' => round(floatval($upgradeReferrerRewardRawAmount), 2),
		                                    'available_profit_amount' => round(max(0, floatval($replenishProfitAmount)), 2),
		                                    'shipper_mid' => intval($shipperMidForSettle),
		                                    'profit_mid' => intval($replenishProfitMid),
		                                    'settled_time' => time(),
		                                ];
		                            }
		                        }
		                    }
		                }

			                // 补货差价利润（支付后）入云仓收益钱包
		                if ($goodsDomain === 1 && $isVirtualFulfillment && $replenishProfitMid >= 0 && $replenishProfitAmount > 0) {
		                    $extraDetail = [];
		                    if ($grossProfitAmount > 0) {
		                        $extraDetail = [
		                            'gross_profit_amount' => round(floatval($grossProfitAmount), 2),
		                            'peer_reward_amount' => round(floatval($peerRewardAmount), 2),
		                            'peer_mid' => intval($peerRewardMid),
		                            'upgrade_referrer_reward_amount' => round(floatval($upgradeReferrerRewardAmount), 2),
		                            'upgrade_referrer_reward_raw_amount' => round(floatval($upgradeReferrerRewardRawAmount), 2),
			                            'upgrade_referrer_mid' => intval($upgradeReferrerMid),
			                            'upgrade_referrer_reward_mode' => strval($upgradeReferrerRewardMode),
			                            'upgrade_referrer_reward_fixed_amount' => round(floatval($upgradeReferrerRewardFixedAmount), 2),
			                            'upgrade_referrer_levelid' => intval($upgradeReferrerLevelId),
			                            'upgrade_referrer_reward_payer_mode' => strval($upgradeReferrerRewardPayerMode),
			                            'upgrade_referrer_reward_payer_mid' => intval($upgradeReferrerRewardPayerMid),
			                            'upgrade_referrer_reward_theoretical_shipper_mid' => intval($upgradeReferrerRewardTheoreticalShipperMid),
			                            'upgrade_referrer_reward_deduct_amount' => round(floatval($upgradeReferrerRewardDeductAmount), 2),
			                            'upgrade_referrer_reward_subsidy_amount' => round(floatval($upgradeReferrerRewardSubsidyAmount), 2),
			                            'upgrade_referrer_reward_from_shipper_profit' => intval($upgradeReferrerRewardFromShipperProfit),
			                            'upgrade_referrer_reward_capped' => intval($upgradeReferrerRewardCapped),
			                            'upgrade_referrer_reward_skipped' => intval($upgradeReferrerRewardSkippedByProfit || $upgradeReferrerRewardSkippedByRule),
		                            'upgrade_referrer_reward_skipped_by_profit' => intval($upgradeReferrerRewardSkippedByProfit),
		                            'upgrade_referrer_reward_skipped_by_rule' => intval($upgradeReferrerRewardSkippedByRule),
		                            'net_profit_amount' => round(floatval($replenishProfitAmount), 2),
		                        ];
		                        if ($peerRewardSkippedBySwitch) {
		                            $extraDetail['peer_reward_skipped'] = 1;
		                            $extraDetail['peer_reward_skip_reason'] = 'upgrade_peer_reward_disabled';
		                        }
		                    }
		                    $credit = \app\admin\common\YunkucunGoodsMoneylog::creditReplenishProfitPay(
		                        intval($order['aid']),
		                        intval($order['bid'] ?? 0),
		                        intval($order['mid'] ?? 0),
		                        intval($order['id'] ?? 0),
		                        strval($order['ordernum'] ?? $ordernum),
		                        strval($order['client_request_no'] ?? ''),
		                        intval($replenishProfitMid),
		                        floatval($replenishProfitAmount),
		                        intval($shipperMidForSettle),
		                        $replenishProfitItems,
		                        $extraDetail
		                    );
		                    if (is_array($credit) && intval($credit['status'] ?? 1) === 0) {
		                        throw new \Exception(strval($credit['msg'] ?? '云仓补货差价利润入账失败'));
		                    }
		                    $snapshot['replenish_profit'] = [
		                        'profit_mid' => intval($replenishProfitMid),
		                        'profit_amount' => round(floatval($replenishProfitAmount), 2),
		                        'gross_profit_amount' => round(floatval($grossProfitAmount), 2),
		                        'peer_reward_amount' => round(floatval($peerRewardAmount), 2),
		                        'peer_mid' => intval($peerRewardMid),
		                        'upgrade_referrer_reward_amount' => round(floatval($upgradeReferrerRewardAmount), 2),
		                        'upgrade_referrer_reward_raw_amount' => round(floatval($upgradeReferrerRewardRawAmount), 2),
			                        'upgrade_referrer_mid' => intval($upgradeReferrerMid),
			                        'upgrade_referrer_reward_mode' => strval($upgradeReferrerRewardMode),
			                        'upgrade_referrer_reward_fixed_amount' => round(floatval($upgradeReferrerRewardFixedAmount), 2),
			                        'upgrade_referrer_levelid' => intval($upgradeReferrerLevelId),
			                        'upgrade_referrer_reward_payer_mode' => strval($upgradeReferrerRewardPayerMode),
			                        'upgrade_referrer_reward_payer_mid' => intval($upgradeReferrerRewardPayerMid),
			                        'upgrade_referrer_reward_theoretical_shipper_mid' => intval($upgradeReferrerRewardTheoreticalShipperMid),
			                        'upgrade_referrer_reward_deduct_amount' => round(floatval($upgradeReferrerRewardDeductAmount), 2),
			                        'upgrade_referrer_reward_subsidy_amount' => round(floatval($upgradeReferrerRewardSubsidyAmount), 2),
			                        'upgrade_referrer_reward_from_shipper_profit' => intval($upgradeReferrerRewardFromShipperProfit),
			                        'upgrade_referrer_reward_capped' => intval($upgradeReferrerRewardCapped),
		                        'upgrade_referrer_reward_skipped' => intval($upgradeReferrerRewardSkippedByProfit || $upgradeReferrerRewardSkippedByRule),
		                        'upgrade_referrer_reward_skipped_by_profit' => intval($upgradeReferrerRewardSkippedByProfit),
		                        'upgrade_referrer_reward_skipped_by_rule' => intval($upgradeReferrerRewardSkippedByRule),
		                        'peer_reward_skipped' => $peerRewardSkippedBySwitch ? 1 : 0,
		                        'settled' => 1,
		                        'settled_time' => time(),
		                    ];
		                }

		                // 升级补货（虚拟补货）：结算完成后更新会员等级
		                if ($goodsDomain === 1 && $isUpgrade && $isVirtualFulfillment) {
		                    $upgrade = is_array($snapshot['upgrade'] ?? null) ? $snapshot['upgrade'] : [];
		                    $toLevelId = intval($upgrade['to_levelid'] ?? 0);
		                    if ($toLevelId <= 0) {
		                        throw new \Exception('升级目标等级参数错误');
		                    }
		                    $upgrade['applied_time'] = time();
		                    $snapshot['upgrade'] = $upgrade;

		                    Db::name('member')
		                        ->where('aid', intval($order['aid']))
		                        ->where('id', intval($order['mid']))
		                        ->update([
		                            'levelid' => $toLevelId,
		                        ]);

		                    self::settleUpgradeSpecialDepositIncluded($order, $snapshot);
		                }

		                if ($goodsDomain === 1) {
		                    $snapshot['replenish_paid_processed'] = 1;
		                    $snapshot['replenish_paid_processed_time'] = time();
		                    $update = [
	                        'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
	                        'updatetime' => time(),
	                    ];
	                    if ($isVirtualFulfillment) {
	                        $update['status'] = 2;
	                        $update['inbound_time'] = time();
	                    } else {
	                        $update['status'] = 1;
	                    }
	                    Db::name('yunkucun_replenish_order')
	                        ->where('id', intval($order['id']))
	                        ->update($update);
	                } else {
	                    Db::name('yunkucun_replenish_order')
	                        ->where('id', intval($order['id']))
	                        ->update([
	                            'status' => 2,
	                            'inbound_time' => time(),
	                            'updatetime' => time(),
	                        ]);
	                }
		            });
		        } catch (\Throwable $e) {
		            \think\facade\Log::error('云仓补货支付处理失败', [
		                'orderid' => $orderid,
		                'ordernum' => $ordernum,
		                'err' => $e->getMessage(),
	            ]);
	            return ['status' => 0, 'msg' => $e->getMessage()];
	        }

	        return ['status' => 1, 'msg' => ''];
	    }

	    private static function parsePositiveIdsCsv($raw): array
	    {
	        $vals = [];
	        if (is_array($raw)) {
	            $vals = $raw;
	        } else {
	            $txt = trim(strval($raw));
	            if ($txt !== '') {
	                $vals = explode(',', $txt);
	            }
	        }
	        $set = [];
	        foreach ($vals as $v) {
	            $id = intval($v);
	            if ($id > 0) {
	                $set[$id] = 1;
	            }
	        }
	        if (!$set) {
	            return [];
	        }
	        return array_map('intval', array_keys($set));
	    }

	    private static function normalizeShipperLevelIdsForSettle($raw): array
	    {
	        if (is_array($raw)) {
	            $raw = implode(',', $raw);
	        }
	        $raw = trim(strval($raw));
	        if ($raw === '') {
	            return [];
	        }
	        $ids = array_values(array_unique(array_map('intval', explode(',', $raw))));
	        $ids = array_values(array_filter($ids, function ($v) {
	            return in_array($v, [-1, 0], true) || $v > 0;
	        }));
	        if (!$ids) {
	            return [];
	        }
	        if (in_array(0, $ids, true)) {
	            return [0];
	        }
	        if (in_array(-1, $ids, true)) {
	            return [-1];
	        }
	        $ids = array_values(array_filter($ids, function ($v) {
	            return $v > 0;
	        }));
	        return $ids;
	    }

	    private static function getMemberLevelSortMapForSettle(int $aid): array
	    {
	        $aid = intval($aid);
	        if ($aid <= 0) {
	            return [];
	        }
	        static $cache = [];
	        if (isset($cache[$aid])) {
	            return $cache[$aid];
	        }

	        try {
	            $rows = Db::name('member_level')
	                ->where('aid', $aid)
	                ->field('id,sort')
	                ->select()
	                ->toArray();
	        } catch (\Throwable $e) {
	            $rows = [];
	        }
	        $map = [];
	        foreach ($rows as $r) {
	            $map[intval($r['id'] ?? 0)] = intval($r['sort'] ?? 0);
	        }
	        $cache[$aid] = $map;
	        return $map;
	    }

	    private static function isMemberLevelAtLeastForSettle(int $aid, int $levelId, int $requiredLevelId): bool
	    {
	        $levelId = intval($levelId);
	        $requiredLevelId = intval($requiredLevelId);
	        if ($aid <= 0 || $levelId <= 0 || $requiredLevelId <= 0) {
	            return false;
	        }
	        if ($levelId === $requiredLevelId) {
	            return true;
	        }
	        $sortMap = self::getMemberLevelSortMapForSettle($aid);
	        $levelSort = intval($sortMap[$levelId] ?? 0);
	        $requiredSort = intval($sortMap[$requiredLevelId] ?? 0);
	        if ($levelSort <= 0 || $requiredSort <= 0) {
	            return false;
	        }
	        return $levelSort >= $requiredSort;
	    }

	    private static function resolveShipperMidByLevelIdsForSettle(int $aid, int $startMid, array $shipperLevelIds): int
	    {
	        $aid = intval($aid);
	        $startMid = intval($startMid);
	        if ($aid <= 0 || $startMid <= 0) {
	            return -1;
	        }

	        $shipperLevelIds = array_values(array_unique(array_map('intval', $shipperLevelIds)));
	        $shipperLevelIds = array_values(array_filter($shipperLevelIds, function ($v) {
	            return in_array($v, [-1, 0], true) || $v > 0;
	        }));
	        if (!$shipperLevelIds) {
	            return -1;
	        }
	        if (in_array(-1, $shipperLevelIds, true)) {
	            return 0;
	        }
	        if (in_array(0, $shipperLevelIds, true)) {
	            return $startMid;
	        }

	        $levelSet = [];
	        foreach ($shipperLevelIds as $v) {
	            $v = intval($v);
	            if ($v > 0) {
	                $levelSet[$v] = 1;
	            }
	        }
	        if (!$levelSet) {
	            return -1;
	        }

	        $member = Db::name('member')
	            ->where('aid', $aid)
	            ->where('id', $startMid)
	            ->field('id,levelid,path,isxuni')
	            ->find();
	        if (!$member || intval($member['isxuni'] ?? 0) === 1) {
	            return -1;
	        }
	        if (isset($levelSet[intval($member['levelid'] ?? 0)])) {
	            return $startMid;
	        }

	        $pathRaw = trim(strval($member['path'] ?? ''));
	        if ($pathRaw === '') {
	            return -1;
	        }
	        $pathIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $pathRaw)), function ($v) {
	            return $v > 0;
	        })));
	        if (!$pathIds) {
	            return -1;
	        }
	        $pathIdsRev = array_reverse($pathIds);
	        $rows = Db::name('member')
	            ->where('aid', $aid)
	            ->where('id', 'in', $pathIdsRev)
	            ->where('isxuni', 0)
	            ->field('id,levelid')
	            ->select()
	            ->toArray();
	        $levelMap = [];
	        foreach ($rows as $r) {
	            $levelMap[intval($r['id'] ?? 0)] = intval($r['levelid'] ?? 0);
	        }
	        foreach ($pathIdsRev as $pid) {
	            if (isset($levelSet[intval($levelMap[intval($pid)] ?? 0)])) {
	                return intval($pid);
	            }
	        }
	        return -1;
	    }

	    private static function resolveShipperMidByLevelForSettle(int $aid, int $startMid, int $shipperLevelId): int
	    {
	        $aid = intval($aid);
	        $startMid = intval($startMid);
	        $shipperLevelId = intval($shipperLevelId);
	        if ($aid <= 0 || $startMid <= 0) {
	            return -1;
	        }
	        if ($shipperLevelId <= -1) {
	            return 0;
	        }
	        if ($shipperLevelId === 0) {
	            return $startMid;
	        }

	        $member = Db::name('member')
	            ->where('aid', $aid)
	            ->where('id', $startMid)
	            ->field('id,levelid,path,isxuni')
	            ->find();
	        if (!$member || intval($member['isxuni'] ?? 0) === 1) {
	            return -1;
	        }
	        if (self::isMemberLevelAtLeastForSettle($aid, intval($member['levelid'] ?? 0), $shipperLevelId)) {
	            return $startMid;
	        }

	        $pathRaw = trim(strval($member['path'] ?? ''));
	        if ($pathRaw === '') {
	            return -1;
	        }
	        $pathIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $pathRaw)), function ($v) {
	            return $v > 0;
	        })));
	        if (!$pathIds) {
	            return -1;
	        }
	        $pathIdsRev = array_reverse($pathIds);
	        $rows = Db::name('member')
	            ->where('aid', $aid)
	            ->where('id', 'in', $pathIdsRev)
	            ->where('isxuni', 0)
	            ->field('id,levelid')
	            ->select()
	            ->toArray();
	        $levelMap = [];
	        foreach ($rows as $r) {
	            $levelMap[intval($r['id'] ?? 0)] = intval($r['levelid'] ?? 0);
	        }
	        foreach ($pathIdsRev as $pid) {
	            $lv = intval($levelMap[intval($pid)] ?? 0);
	            if (self::isMemberLevelAtLeastForSettle($aid, $lv, $shipperLevelId)) {
	                return intval($pid);
	            }
	        }
	        return -1;
	    }

	    private static function resolveUpgradeSpecialTheoreticalShipperMidForSettle(int $aid, int $buyerMid, int $toLevelId): int
	    {
	        $aid = intval($aid);
	        $buyerMid = intval($buyerMid);
	        $toLevelId = intval($toLevelId);
	        if ($aid <= 0 || $buyerMid <= 0 || $toLevelId <= 0) {
	            return 0;
	        }

	        $rule = [];
	        try {
	            $rule = Db::name('yunkucun_goods_level_rule')
	                ->where('aid', $aid)
	                ->where('levelid', $toLevelId)
	                ->field('shipper_levelid,shipper_levelids')
	                ->find();
	        } catch (\Throwable $e) {
	            try {
	                $rule = Db::name('yunkucun_goods_level_rule')
	                    ->where('aid', $aid)
	                    ->where('levelid', $toLevelId)
	                    ->field('shipper_levelid')
	                    ->find();
	            } catch (\Throwable $e2) {
	                $rule = [];
	            }
	            if (!is_array($rule)) {
	                $rule = [];
	            }
	            $rule['shipper_levelids'] = '';
	        }
	        if (!is_array($rule)) {
	            $rule = [];
	        }

	        $shipperLevelIds = self::normalizeShipperLevelIdsForSettle($rule['shipper_levelids'] ?? '');
	        $shipperLevelId = intval($rule['shipper_levelid'] ?? 0);
	        if ($shipperLevelId < -1) $shipperLevelId = -1;
	        $shipperMid = $shipperLevelIds
	            ? self::resolveShipperMidByLevelIdsForSettle($aid, $buyerMid, $shipperLevelIds)
	            : self::resolveShipperMidByLevelForSettle($aid, $buyerMid, $shipperLevelId);
	        if ($shipperMid === $buyerMid || $shipperMid < 0) {
	            return 0;
	        }
	        return intval($shipperMid);
	    }

	    private static function calcSkuWholesalePriceByLevelForSettle(int $aid, int $levelId, array $skuRow, array &$levelDiscountCache): float
	    {
	        if ($levelId <= 0) {
	            return 0.0;
	        }

	        $lv = json_decode(strval($skuRow['wholesale_lvprice_json'] ?? ''), true);
	        if (is_array($lv)) {
	            $lvPrice = $lv[strval($levelId)] ?? ($lv[$levelId] ?? null);
	            if ($lvPrice !== null && $lvPrice !== '') {
	                return round(max(0, floatval($lvPrice)), 2);
	            }
	        }

	        $defaultPrice = round(max(0, floatval($skuRow['wholesale_price'] ?? 0)), 2);
	        if ($defaultPrice > 0) {
	            return $defaultPrice;
	        }

	        $retailPrice = round(max(0, floatval($skuRow['retail_price'] ?? 0)), 2);
	        if ($retailPrice <= 0) {
	            return 0.0;
	        }

	        if (!array_key_exists($levelId, $levelDiscountCache)) {
	            $discount = 10.0;
	            try {
	                $discount = round(floatval(Db::name('member_level')
	                    ->where('aid', $aid)
	                    ->where('id', $levelId)
	                    ->value('discount')), 2);
	            } catch (\Throwable $e) {
	                $discount = 10.0;
	            }
	            if ($discount <= 0) {
	                $discount = 10.0;
	            }
	            $levelDiscountCache[$levelId] = $discount;
	        }

	        $discount = floatval($levelDiscountCache[$levelId] ?? 10.0);
	        if ($discount <= 0) {
	            $discount = 10.0;
	        }
	        return round($retailPrice * $discount / 10, 2);
	    }

	    /**
	     * 升级专用商品：平台发货后按推荐链逐级结算价差利润（每级拿与下级的价差）。
	     * 说明：
	     * - 卖货人=推荐人（buyer.pid）
	     * - 链路按推荐关系逐级向上，缺失等级自动跨级
	     * - 推荐奖单独发放；若配置“理论发货方承担”，先从该方本单利润扣减，不足平台补差
	     */
	    private static function settleUpgradeSpecialLevelDiffProfit(array $order, array $items, array &$snapshot, string $ordernum): void
	    {
	        $upgrade = is_array($snapshot['upgrade'] ?? null) ? $snapshot['upgrade'] : [];
	        $special = is_array($upgrade['special_goods'] ?? null) ? $upgrade['special_goods'] : [];
	        if (intval($special['enabled'] ?? 0) !== 1) {
	            return;
	        }

	        $aid = intval($order['aid'] ?? 0);
	        $bid = intval($order['bid'] ?? 0);
	        $buyerMid = intval($order['mid'] ?? 0);
	        $orderId = intval($order['id'] ?? 0);
	        $clientRequestNo = strval($order['client_request_no'] ?? '');
	        if ($aid <= 0 || $buyerMid <= 0 || $orderId <= 0 || !$items) {
	            return;
	        }

	        $rewardPayerMode = strtolower(trim(strval($special['reward_payer_mode'] ?? 'platform')));
	        if ($rewardPayerMode !== 'theoretical_shipper') {
	            $rewardPayerMode = 'platform';
	        }
	        $theoreticalShipperMid = max(0, intval($special['theoretical_shipper_mid'] ?? 0));
	        $rewardFunding = [
	            'prepared' => 0,
	            'mode' => $rewardPayerMode,
	            'effective_mode' => $rewardPayerMode === 'theoretical_shipper' && $theoreticalShipperMid > 0 ? 'theoretical_shipper' : 'platform',
	            'theoretical_shipper_mid' => $theoreticalShipperMid,
	            'payer_mid' => 0,
	            'profit_mid' => 0,
	            'reward_amount' => 0.0,
	            'deduct_amount' => 0.0,
	            'subsidy_amount' => 0.0,
	            'eligible' => 0,
	            'referrer_mid' => 0,
	            'referrer_levelid' => 0,
	            'eligible_levelids' => '',
	        ];

	        $referrerMid = intval(Db::name('member')
	            ->where('aid', $aid)
	            ->where('id', $buyerMid)
	            ->value('pid'));
	        if ($referrerMid <= 0 || $referrerMid === $buyerMid) {
	            $snapshot['replenish_profit_skip'] = [
	                'skip' => 1,
	                'reason' => 'no_referrer_chain',
	                'reason_text' => '升级专用商品价差利润未发放（未找到推荐链路）',
	                'mode' => 'upgrade_special_level_diff',
	                'settled_time' => time(),
	            ];
	            return;
	        }
	        $rewardFunding['referrer_mid'] = $referrerMid;

	        $fixedRewardAmount = round(max(0, floatval($special['fixed_reward_amount'] ?? 0)), 2);
	        $rewardEligibleLevelids = self::parsePositiveIdsCsv($special['reward_levelids'] ?? '');
	        $rewardFunding['eligible_levelids'] = implode(',', array_values(array_unique(array_map('intval', $rewardEligibleLevelids))));
	        $referrerLevelid = intval(Db::name('member')
	            ->where('aid', $aid)
	            ->where('id', $referrerMid)
	            ->value('levelid'));
	        $rewardFunding['referrer_levelid'] = $referrerLevelid;
	        if (
	            $fixedRewardAmount > 0
	            && $rewardEligibleLevelids
	            && $referrerLevelid > 0
	            && in_array($referrerLevelid, $rewardEligibleLevelids, true)
	        ) {
	            $rewardFunding['eligible'] = 1;
	            $rewardFunding['reward_amount'] = $fixedRewardAmount;
	            if ($rewardFunding['effective_mode'] === 'theoretical_shipper') {
	                $rewardFunding['payer_mid'] = $theoreticalShipperMid;
	                $rewardFunding['profit_mid'] = $theoreticalShipperMid;
	            }
	        }

	        $chain = [];
	        $chainLevelMap = [];
	        $visited = [];
	        $cursor = $referrerMid;
	        $depth = 0;
	        while ($cursor > 0 && $depth < 64) {
	            if (isset($visited[$cursor])) {
	                break;
	            }
	            $visited[$cursor] = 1;
	            $row = Db::name('member')
	                ->where('aid', $aid)
	                ->where('id', $cursor)
	                ->field('id,pid,levelid,isxuni')
	                ->find();
	            if (!$row) {
	                break;
	            }
	            $nextCursor = intval($row['pid'] ?? 0);
	            if (intval($row['isxuni'] ?? 0) !== 1) {
	                $lvId = intval($row['levelid'] ?? 0);
	                if ($lvId > 0) {
	                    $mid = intval($row['id'] ?? 0);
	                    $chain[] = ['mid' => $mid, 'levelid' => $lvId];
	                    $chainLevelMap[$mid] = $lvId;
	                }
	            }
	            $cursor = $nextCursor;
	            $depth++;
	        }
	        if (!$chain) {
	            $snapshot['replenish_profit_skip'] = [
	                'skip' => 1,
	                'reason' => 'empty_referrer_chain',
	                'reason_text' => '升级专用商品价差利润未发放（推荐链路无有效代理）',
	                'mode' => 'upgrade_special_level_diff',
	                'settled_time' => time(),
	            ];
	            return;
	        }

	        $skuIds = [];
	        foreach ($items as $it) {
	            if (intval($it['is_gift'] ?? 0) === 1 || intval($it['gift_rule_id'] ?? 0) > 0) {
	                continue;
	            }
	            $sid = intval($it['ggid'] ?? 0);
	            if ($sid > 0) {
	                $skuIds[] = $sid;
	            }
	        }
	        $skuIds = array_values(array_unique($skuIds));
	        if (!$skuIds) {
	            return;
	        }

	        $skuMap = [];
	        $rows = Db::name('yunkucun_goods_sku')
	            ->where('aid', $aid)
	            ->where('bid', $bid)
	            ->where('id', 'in', $skuIds)
	            ->field('id,goods_id,sku_name,retail_price,wholesale_price,wholesale_lvprice_json,box_qty')
	            ->select()
	            ->toArray();
	        foreach ($rows as $r) {
	            $sid = intval($r['id'] ?? 0);
	            if ($sid > 0) {
	                $skuMap[$sid] = $r;
	            }
	        }
	        if (!$skuMap) {
	            return;
	        }

	        $buyerToLevelId = intval($upgrade['to_levelid'] ?? 0);
	        $levelDiscountCache = [];
	        $profitByMid = [];
	        $profitItemsByMid = [];

	        foreach ($items as $it) {
	            if (intval($it['is_gift'] ?? 0) === 1 || intval($it['gift_rule_id'] ?? 0) > 0) {
	                continue;
	            }

	            $proid = intval($it['proid'] ?? 0);
	            $ggid = intval($it['ggid'] ?? 0);
	            if ($proid <= 0 || $ggid <= 0) {
	                continue;
	            }
	            $skuRow = is_array($skuMap[$ggid] ?? null) ? $skuMap[$ggid] : [];
	            if (!$skuRow) {
	                continue;
	            }

	            $qty = intval($it['qty'] ?? 0);
	            $boxQty = max(1, intval($it['box_qty'] ?? ($skuRow['box_qty'] ?? 1)));
	            $qtyPiece = intval($it['qty_piece'] ?? 0);
	            if ($qtyPiece <= 0) {
	                $unit = strtolower(trim(strval($it['qty_unit'] ?? 'box')));
	                $qtyPiece = $unit === 'piece' ? max(0, $qty) : max(0, $qty * $boxQty);
	            }
	            if ($qtyPiece <= 0) {
	                continue;
	            }

	            $boxes = round(floatval($qtyPiece) / max(1, $boxQty), 6);
	            if ($boxes <= 0) {
	                continue;
	            }

	            $buyerPrice = round(floatval($it['wholesale_price'] ?? 0), 2);
	            if ($buyerPrice <= 0 && $buyerToLevelId > 0) {
	                $buyerPrice = self::calcSkuWholesalePriceByLevelForSettle($aid, $buyerToLevelId, $skuRow, $levelDiscountCache);
	            }
	            if ($buyerPrice <= 0) {
	                continue;
	            }

	            $prevPrice = $buyerPrice;
	            $prevLevelId = $buyerToLevelId;
	            foreach ($chain as $node) {
	                $receiverMid = intval($node['mid'] ?? 0);
	                $receiverLevelId = intval($node['levelid'] ?? 0);
	                if ($receiverMid <= 0 || $receiverLevelId <= 0) {
	                    continue;
	                }
	                $receiverPrice = self::calcSkuWholesalePriceByLevelForSettle($aid, $receiverLevelId, $skuRow, $levelDiscountCache);
	                if ($receiverPrice <= 0) {
	                    continue;
	                }
	                if ($receiverPrice > $prevPrice) {
	                    $receiverPrice = $prevPrice;
	                }
	                $diffPerBox = round($prevPrice - $receiverPrice, 2);
	                if ($diffPerBox <= 0) {
	                    $prevPrice = $receiverPrice;
	                    $prevLevelId = $receiverLevelId;
	                    continue;
	                }

	                $profitAmount = round($diffPerBox * $boxes, 2);
	                if ($profitAmount <= 0) {
	                    $prevPrice = $receiverPrice;
	                    $prevLevelId = $receiverLevelId;
	                    continue;
	                }

	                if (!isset($profitByMid[$receiverMid])) {
	                    $profitByMid[$receiverMid] = 0.0;
	                    $profitItemsByMid[$receiverMid] = [];
	                }
	                $profitByMid[$receiverMid] = round(floatval($profitByMid[$receiverMid]) + $profitAmount, 2);
	                $profitItemsByMid[$receiverMid][] = [
	                    'proid' => $proid,
	                    'ggid' => $ggid,
	                    'qty_piece' => $qtyPiece,
	                    'box_qty' => $boxQty,
	                    'boxes' => round($boxes, 4),
	                    'from_levelid' => max(0, intval($prevLevelId)),
	                    'to_levelid' => $receiverLevelId,
	                    'from_price_per_box' => round($prevPrice, 2),
	                    'to_price_per_box' => round($receiverPrice, 2),
	                    'diff_per_box' => round($diffPerBox, 2),
	                    'profit_amount' => $profitAmount,
	                    'settle_mode' => 'upgrade_special_level_diff',
	                ];
	                $prevPrice = $receiverPrice;
	                $prevLevelId = $receiverLevelId;
	            }
	        }

	        $rewardAmount = round(max(0, floatval($rewardFunding['reward_amount'] ?? 0)), 2);
	        if ($rewardAmount > 0) {
	            $deductAmount = 0.0;
	            $payerMid = intval($rewardFunding['payer_mid'] ?? 0);
	            if ($payerMid > 0) {
	                $payerProfit = round(max(0, floatval($profitByMid[$payerMid] ?? 0)), 2);
	                if ($payerProfit > 0) {
	                    $deductAmount = round(min($rewardAmount, $payerProfit), 2);
	                    if ($deductAmount > 0) {
	                        $profitByMid[$payerMid] = round(max(0, $payerProfit - $deductAmount), 2);
	                    }
	                }
	            }
	            $rewardFunding['prepared'] = 1;
	            $rewardFunding['deduct_amount'] = round($deductAmount, 2);
	            $rewardFunding['subsidy_amount'] = round(max(0, $rewardAmount - $deductAmount), 2);
	        }
	        $snapshot['upgrade_special_reward_funding'] = $rewardFunding;

	        if (!$profitByMid) {
	            $snapshot['replenish_profit_skip'] = [
	                'skip' => 1,
	                'reason' => 'no_level_diff_profit',
	                'reason_text' => '升级专用商品价差利润为0，无需结算',
	                'mode' => 'upgrade_special_level_diff',
	                'settled_time' => time(),
	            ];
	            return;
	        }

	        $profitSummary = [];
	        $totalProfitAmount = 0.0;
	        foreach ($chain as $node) {
	            $mid = intval($node['mid'] ?? 0);
	            if ($mid <= 0) continue;
	            $amt = round(floatval($profitByMid[$mid] ?? 0), 2);
	            if ($amt <= 0) continue;

	            $itemsDetail = is_array($profitItemsByMid[$mid] ?? null) ? $profitItemsByMid[$mid] : [];
	            $extraDetail = [
	                'settle_mode' => 'upgrade_special_level_diff',
	                'special_upgrade' => 1,
	                'special_goods_id' => intval($special['goods_id'] ?? 0),
	                'to_levelid' => $buyerToLevelId,
	                'referrer_mid' => intval($referrerMid),
	                'chain_size' => count($chain),
	                'reward_payer_mode' => strval($rewardFunding['mode'] ?? 'platform'),
	                'reward_payer_effective_mode' => strval($rewardFunding['effective_mode'] ?? 'platform'),
	                'reward_payer_mid' => intval($rewardFunding['payer_mid'] ?? 0),
	                'reward_deduct_amount' => round(floatval($rewardFunding['deduct_amount'] ?? 0), 2),
	                'reward_subsidy_amount' => round(floatval($rewardFunding['subsidy_amount'] ?? 0), 2),
	            ];

	            $credit = \app\admin\common\YunkucunGoodsMoneylog::creditReplenishProfitPay(
	                $aid,
	                $bid,
	                $buyerMid,
	                $orderId,
	                strval($order['ordernum'] ?? $ordernum),
	                $clientRequestNo,
	                $mid,
	                $amt,
	                0,
	                $itemsDetail,
	                $extraDetail
	            );
	            if (is_array($credit) && intval($credit['status'] ?? 1) === 0) {
	                throw new \Exception(strval($credit['msg'] ?? '升级专用商品价差利润入账失败'));
	            }

	            $totalProfitAmount = round($totalProfitAmount + $amt, 2);
	            $profitSummary[] = [
	                'mid' => $mid,
	                'levelid' => intval($chainLevelMap[$mid] ?? 0),
	                'profit_amount' => round($amt, 2),
	            ];
	        }

	        if (!$profitSummary) {
	            return;
	        }

	        $snapshot['replenish_profit'] = [
	            'mode' => 'upgrade_special_level_diff',
	            'profit_mid' => 0,
	            'profit_amount' => round(floatval($totalProfitAmount), 2),
	            'profits_by_mid' => $profitSummary,
	            'special_goods_id' => intval($special['goods_id'] ?? 0),
	            'referrer_mid' => intval($referrerMid),
	            'shipper_mid' => 0,
	            'reward_payer_mode' => strval($rewardFunding['mode'] ?? 'platform'),
	            'reward_payer_effective_mode' => strval($rewardFunding['effective_mode'] ?? 'platform'),
	            'reward_payer_mid' => intval($rewardFunding['payer_mid'] ?? 0),
	            'reward_deduct_amount' => round(floatval($rewardFunding['deduct_amount'] ?? 0), 2),
	            'reward_subsidy_amount' => round(floatval($rewardFunding['subsidy_amount'] ?? 0), 2),
	            'settled' => 1,
	            'settled_time' => time(),
	        ];
	    }

	    /**
	     * 真实发货升级单：升级专用商品推荐奖（固定每单）结算。
	     */
	    private static function settleUpgradeSpecialReferrerRewardForRealDelivery(array $order, array &$snapshot, string $ordernum): void
	    {
	        $upgrade = is_array($snapshot['upgrade'] ?? null) ? $snapshot['upgrade'] : [];
	        $special = is_array($upgrade['special_goods'] ?? null) ? $upgrade['special_goods'] : [];
	        if (intval($special['enabled'] ?? 0) !== 1) {
	            return;
	        }

	        $aid = intval($order['aid'] ?? 0);
	        $bid = intval($order['bid'] ?? 0);
	        $buyerMid = intval($order['mid'] ?? 0);
	        $orderId = intval($order['id'] ?? 0);
	        $toLevelId = intval($upgrade['to_levelid'] ?? ($special['to_levelid'] ?? 0));
	        if ($aid <= 0 || $buyerMid <= 0 || $orderId <= 0 || $toLevelId <= 0) {
	            return;
	        }

	        $rewardAmount = round(max(0, floatval($special['fixed_reward_amount'] ?? 0)), 2);
	        $eligibleLevelids = self::parsePositiveIdsCsv($special['reward_levelids'] ?? '');
	        if ($rewardAmount <= 0 || !$eligibleLevelids) {
	            $snapshot['upgrade_referrer_reward_skip'] = [
	                'skip' => 1,
	                'reason' => 'special_rule_invalid',
	                'reason_text' => '升级专用商品推荐奖未发放（规则无效）',
	                'mode' => 'fixed_order',
	                'to_levelid' => $toLevelId,
	                'fixed_reward_amount' => round(floatval($rewardAmount), 2),
	                'eligible_levelids' => implode(',', array_values(array_unique(array_map('intval', $eligibleLevelids)))),
	                'settled_time' => time(),
	            ];
	            return;
	        }

	        $referrerMid = intval(Db::name('member')
	            ->where('aid', $aid)
	            ->where('id', $buyerMid)
	            ->value('pid'));
	        if ($referrerMid <= 0 || $referrerMid === $buyerMid) {
	            $snapshot['upgrade_referrer_reward_skip'] = [
	                'skip' => 1,
	                'reason' => 'no_referrer',
	                'reason_text' => '升级专用商品推荐奖未发放（未找到推荐人）',
	                'mode' => 'fixed_order',
	                'to_levelid' => $toLevelId,
	                'fixed_reward_amount' => round(floatval($rewardAmount), 2),
	                'settled_time' => time(),
	            ];
	            return;
	        }

	        $referrerLevelid = intval(Db::name('member')
	            ->where('aid', $aid)
	            ->where('id', $referrerMid)
	            ->value('levelid'));
	        if (!in_array($referrerLevelid, $eligibleLevelids, true)) {
	            $snapshot['upgrade_referrer_reward_skip'] = [
	                'skip' => 1,
	                'reason' => 'special_level_not_match',
	                'reason_text' => '升级专用商品推荐奖未发放（推荐人等级不在可得等级内）',
	                'mode' => 'fixed_order',
	                'referrer_mid' => $referrerMid,
	                'referrer_levelid' => $referrerLevelid,
	                'eligible_levelids' => implode(',', array_values(array_unique(array_map('intval', $eligibleLevelids)))),
	                'to_levelid' => $toLevelId,
	                'fixed_reward_amount' => round(floatval($rewardAmount), 2),
	                'settled_time' => time(),
	            ];
	            return;
	        }

	        $rewardFunding = is_array($snapshot['upgrade_special_reward_funding'] ?? null)
	            ? $snapshot['upgrade_special_reward_funding']
	            : [];
	        $rewardPayerMode = strtolower(trim(strval($rewardFunding['mode'] ?? ($special['reward_payer_mode'] ?? 'platform'))));
	        if ($rewardPayerMode !== 'theoretical_shipper') {
	            $rewardPayerMode = 'platform';
	        }
	        $theoreticalShipperMid = max(0, intval($rewardFunding['theoretical_shipper_mid'] ?? ($special['theoretical_shipper_mid'] ?? 0)));
	        $payerMid = max(0, intval($rewardFunding['payer_mid'] ?? 0));
	        if ($rewardPayerMode !== 'theoretical_shipper') {
	            $payerMid = 0;
	        }
	        $deductAmount = round(max(0, floatval($rewardFunding['deduct_amount'] ?? 0)), 2);
	        $subsidyAmount = round(max(0, floatval($rewardFunding['subsidy_amount'] ?? ($rewardAmount - $deductAmount))), 2);
	        if ($subsidyAmount < 0) $subsidyAmount = 0.0;
	        $fromShipperProfit = $deductAmount > 0 ? 1 : 0;

	        $credit = \app\admin\common\YunkucunGoodsMoneylog::creditUpgradeReferrerReward(
	            $aid,
	            $bid,
	            $buyerMid,
	            $orderId,
	            strval($order['ordernum'] ?? $ordernum),
	            strval($order['client_request_no'] ?? ''),
	            $referrerMid,
	            $rewardAmount,
	            $toLevelId,
	            1,
	            $rewardAmount,
	            [
	                'mode' => 'fixed_order',
	                'fixed_reward_amount' => round(floatval($rewardAmount), 2),
	                'eligible_levelids' => implode(',', array_values(array_unique(array_map('intval', $eligibleLevelids)))),
	                'referrer_levelid' => intval($referrerLevelid),
	                'shipper_mid' => 0,
	                'payer_mid' => intval($payerMid),
	                'profit_mid' => intval($payerMid),
	                'reward_payer_mode' => strval($rewardPayerMode),
	                'theoretical_shipper_mid' => intval($theoreticalShipperMid),
	                'raw_reward_amount' => round(floatval($rewardAmount), 2),
	                'gross_profit_amount' => 0,
	                'net_profit_amount' => 0,
	                'capped_by_profit' => 0,
	                'deduct_amount' => round(floatval($deductAmount), 2),
	                'subsidy_amount' => round(floatval($subsidyAmount), 2),
	                'from_shipper_profit' => intval($fromShipperProfit),
	                'settle_channel' => 'real_delivery',
	            ]
	        );
	        if (is_array($credit) && intval($credit['status'] ?? 1) === 0) {
	            throw new \Exception(strval($credit['msg'] ?? '云仓升级推荐奖入账失败'));
	        }

	        $snapshot['upgrade_referrer_reward'] = [
	            'mode' => 'fixed_order',
	            'referrer_mid' => intval($referrerMid),
	            'referrer_levelid' => intval($referrerLevelid),
	            'eligible_levelids' => implode(',', array_values(array_unique(array_map('intval', $eligibleLevelids)))),
	            'to_levelid' => intval($toLevelId),
	            'reward_piece' => 1,
	            'reward_per_piece' => round(floatval($rewardAmount), 2),
	            'fixed_reward_amount' => round(floatval($rewardAmount), 2),
	            'raw_reward_amount' => round(floatval($rewardAmount), 2),
	            'reward_amount' => round(floatval($rewardAmount), 2),
	            'payer_mid' => intval($payerMid),
	            'reward_payer_mode' => strval($rewardPayerMode),
	            'theoretical_shipper_mid' => intval($theoreticalShipperMid),
	            'deduct_amount' => round(floatval($deductAmount), 2),
	            'subsidy_amount' => round(floatval($subsidyAmount), 2),
	            'shipper_mid' => 0,
	            'profit_mid' => intval($payerMid),
	            'gross_profit_amount' => 0,
	            'net_profit_amount' => 0,
	            'capped_by_profit' => 0,
	            'from_shipper_profit' => intval($fromShipperProfit),
	            'settled' => 1,
	            'settled_time' => time(),
	        ];
	    }

	    /**
	     * 升级专用商品：订单内已包含保证金，支付后自动将保证金补齐到目标等级要求值。
	     */
	    private static function settleUpgradeSpecialDepositIncluded(array $order, array &$snapshot): void
	    {
	        $upgrade = is_array($snapshot['upgrade'] ?? null) ? $snapshot['upgrade'] : [];
	        $special = is_array($upgrade['special_goods'] ?? null) ? $upgrade['special_goods'] : [];
	        if (intval($special['enabled'] ?? 0) !== 1 || intval($special['deposit_included'] ?? 0) !== 1) {
	            return;
	        }

	        $aid = intval($order['aid'] ?? 0);
	        $mid = intval($order['mid'] ?? 0);
	        $orderId = intval($order['id'] ?? 0);
	        if ($aid <= 0 || $mid <= 0 || $orderId <= 0) {
	            return;
	        }

	        $toLevelId = intval($special['to_levelid'] ?? 0);
	        if ($toLevelId <= 0) {
	            $toLevelId = intval($upgrade['to_levelid'] ?? 0);
	        }
	        if ($toLevelId <= 0) {
	            return;
	        }

	        $requiredAmount = 0.0;
	        try {
	            $requiredAmount = round(max(0, floatval(Db::name('yunkucun_goods_level_rule')
	                ->where('aid', $aid)
	                ->where('levelid', $toLevelId)
	                ->value('deposit_required_amount'))), 2);
	        } catch (\Throwable $e) {
	            $special['deposit_settle'] = [
	                'settled' => 0,
	                'skip' => 1,
	                'reason' => 'schema_not_ready',
	                'reason_text' => '保证金字段未安装或不可用',
	                'to_levelid' => $toLevelId,
	                'settled_time' => time(),
	            ];
	            $upgrade['special_goods'] = $special;
	            $snapshot['upgrade'] = $upgrade;
	            return;
	        }

	        $now = time();
	        $beforeAmount = 0.0;
	        $afterAmount = 0.0;
	        $changeAmount = 0.0;
	        try {
	            $existsLog = Db::name('yunkucun_goods_deposit_log')
	                ->where('aid', $aid)
	                ->where('mid', $mid)
	                ->where('source_type', 'UPGRADE_SPECIAL')
	                ->where('orderid', $orderId)
	                ->find();
	        } catch (\Throwable $e) {
	            $existsLog = null;
	        }
	        if ($existsLog) {
	            $afterAmount = round(floatval($existsLog['after_amount'] ?? 0), 2);
	            $changeAmount = round(floatval($existsLog['change_amount'] ?? 0), 2);
	            $beforeAmount = round($afterAmount - $changeAmount, 2);
	            if ($beforeAmount < 0) $beforeAmount = 0.0;

	            $special['deposit_settle'] = [
	                'settled' => 1,
	                'duplicate' => 1,
	                'source_type' => 'UPGRADE_SPECIAL',
	                'to_levelid' => $toLevelId,
	                'required_amount' => round(floatval($requiredAmount), 2),
	                'before_amount' => round(floatval($beforeAmount), 2),
	                'change_amount' => round(floatval($changeAmount), 2),
	                'after_amount' => round(floatval($afterAmount), 2),
	                'orderid' => $orderId,
	                'settled_time' => $now,
	            ];
	            $upgrade['special_goods'] = $special;
	            $snapshot['upgrade'] = $upgrade;
	            return;
	        }

	        try {
	            $row = Db::name('yunkucun_goods_deposit')
	                ->where('aid', $aid)
	                ->where('mid', $mid)
	                ->lock(true)
	                ->find();
	            $beforeAmount = round(floatval($row['amount'] ?? 0), 2);
	            $targetAmount = round(max(floatval($beforeAmount), floatval($requiredAmount)), 2);
	            $changeAmount = round(max(0, $targetAmount - $beforeAmount), 2);
	            $afterAmount = round($beforeAmount + $changeAmount, 2);

	            if ($changeAmount > 0.00001) {
	                if ($row) {
	                    Db::name('yunkucun_goods_deposit')
	                        ->where('aid', $aid)
	                        ->where('mid', $mid)
	                        ->update([
	                            'amount' => $afterAmount,
	                            'updatetime' => $now,
	                        ]);
	                } else {
	                    Db::name('yunkucun_goods_deposit')->insert([
	                        'aid' => $aid,
	                        'mid' => $mid,
	                        'amount' => $afterAmount,
	                        'createtime' => $now,
	                        'updatetime' => $now,
	                    ]);
	                }

	                Db::name('yunkucun_goods_deposit_log')->insert([
	                    'aid' => $aid,
	                    'mid' => $mid,
	                    'change_amount' => $changeAmount,
	                    'after_amount' => $afterAmount,
	                    'source_type' => 'UPGRADE_SPECIAL',
	                    'admin_uid' => 0,
	                    'orderid' => $orderId,
	                    'reason' => '升级专用商品含保证金，订单号：' . strval($order['ordernum'] ?? ''),
	                    'createtime' => $now,
	                ]);
	            }
	        } catch (\Throwable $e) {
	            $special['deposit_settle'] = [
	                'settled' => 0,
	                'skip' => 1,
	                'reason' => 'settle_failed',
	                'reason_text' => '升级专用商品保证金入账失败',
	                'error' => $e->getMessage(),
	                'to_levelid' => $toLevelId,
	                'required_amount' => round(floatval($requiredAmount), 2),
	                'settled_time' => $now,
	            ];
	            $upgrade['special_goods'] = $special;
	            $snapshot['upgrade'] = $upgrade;
	            return;
	        }

	        $special['deposit_settle'] = [
	            'settled' => 1,
	            'source_type' => 'UPGRADE_SPECIAL',
	            'to_levelid' => $toLevelId,
	            'required_amount' => round(floatval($requiredAmount), 2),
	            'before_amount' => round(floatval($beforeAmount), 2),
	            'change_amount' => round(floatval($changeAmount), 2),
	            'after_amount' => round(floatval($afterAmount), 2),
	            'orderid' => $orderId,
	            'settled_time' => $now,
	        ];
	        $upgrade['special_goods'] = $special;
	        $snapshot['upgrade'] = $upgrade;
	    }

	    /**
	     * 云仓V3保证金缴纳单
	     *
	     * 支付完成后：将缴纳金额计入 yunkucun_goods_deposit，并写入 yunkucun_goods_deposit_log。
	     */
	    public static function yunkucun_deposit_pay($orderid, $ordernum)
	    {
	        try {
	            Db::transaction(function () use ($orderid, $ordernum) {
	                $order = Db::name('yunkucun_deposit_order')
	                    ->where('id', intval($orderid))
	                    ->lock(true)
	                    ->find();
	                if (!$order) {
	                    throw new \Exception('保证金缴纳单不存在');
	                }

	                $aid = intval($order['aid'] ?? 0);
	                $mid = intval($order['mid'] ?? 0);
	                $amount = round(floatval($order['amount'] ?? 0), 2);
	                if ($aid <= 0 || $mid <= 0) {
	                    throw new \Exception('保证金缴纳单数据异常');
	                }
	                if ($amount <= 0) {
	                    return;
	                }

	                // 防重复：已存在相同 orderid 的入账明细则视为已处理
	                $existsLog = Db::name('yunkucun_goods_deposit_log')
	                    ->where('aid', $aid)
	                    ->where('mid', $mid)
	                    ->where('source_type', 'USER_PAY')
	                    ->where('orderid', intval($order['id']))
	                    ->find();
	                if ($existsLog) {
	                    return;
	                }

	                $now = time();
	                $row = Db::name('yunkucun_goods_deposit')
	                    ->where('aid', $aid)
	                    ->where('mid', $mid)
	                    ->lock(true)
	                    ->find();
	                $before = round(floatval($row['amount'] ?? 0), 2);
	                $after = round($before + $amount, 2);

	                if ($row) {
	                    Db::name('yunkucun_goods_deposit')
	                        ->where('aid', $aid)
	                        ->where('mid', $mid)
	                        ->update(['amount' => $after, 'updatetime' => $now]);
	                } else {
	                    Db::name('yunkucun_goods_deposit')->insert([
	                        'aid' => $aid,
	                        'mid' => $mid,
	                        'amount' => $after,
	                        'createtime' => $now,
	                        'updatetime' => $now,
	                    ]);
	                }

	                Db::name('yunkucun_goods_deposit_log')->insert([
	                    'aid' => $aid,
	                    'mid' => $mid,
	                    'change_amount' => $amount,
	                    'after_amount' => $after,
	                    'source_type' => 'USER_PAY',
	                    'admin_uid' => 0,
	                    'orderid' => intval($order['id']),
	                    'reason' => '用户缴纳保证金，单号：' . strval($order['ordernum'] ?? $ordernum),
	                    'createtime' => $now,
	                ]);
	            });
	        } catch (\Throwable $e) {
	            \think\facade\Log::error('云仓保证金入账失败', [
	                'orderid' => $orderid,
	                'ordernum' => $ordernum,
	                'err' => $e->getMessage(),
	            ]);
	            return ['status' => 0, 'msg' => $e->getMessage()];
	        }

	        return ['status' => 1, 'msg' => ''];
	    }

	    /**
	     * 云仓V3提货运费单
	     *
	     * 说明：
	     * - 支付成功后不改变提货主订单 status（仍由发货流程推进状态）
	     * - 仅作为“运费已支付”的支付凭证（通过 payorder.status=1 判定）
	     */
	    public static function yunkucun_delivery_pay($orderid, $ordernum)
	    {
	        try {
	            Db::transaction(function () use ($orderid, $ordernum) {
	                $order = Db::name('yunkucun_delivery_order')
	                    ->where('id', intval($orderid))
	                    ->lock(true)
	                    ->find();
	                if (!$order) {
	                    throw new \Exception('提货单不存在');
	                }
	                if (intval($order['goods_domain'] ?? 0) !== 1) {
	                    throw new \Exception('订单类型错误');
	                }
	                if (intval($order['status'] ?? 0) === 4) {
	                    return;
	                }

	                // 仅刷新更新时间，避免旧系统把状态错误改为“已发货”
	                Db::name('yunkucun_delivery_order')
	                    ->where('id', intval($order['id'] ?? 0))
	                    ->update(['updatetime' => time()]);
	            });
	        } catch (\Throwable $e) {
	            \think\facade\Log::error('云仓提货运费支付回调失败', [
	                'orderid' => $orderid,
	                'ordernum' => $ordernum,
	                'err' => $e->getMessage(),
	            ]);
	            return ['status' => 0, 'msg' => $e->getMessage()];
	        }

	        return ['status' => 1, 'msg' => ''];
	    }

	private static function inboundYkv3ShopBindUpgradeSpecialStocks(
		int $aid,
		int $bid,
		int $buyerMid,
		int $bizOrderId,
		string $bizOrdernum,
		string $clientRequestNo,
		array $bindRows
	): void {
		$aid = intval($aid);
		$bid = intval($bid);
		$buyerMid = intval($buyerMid);
		$bizOrderId = intval($bizOrderId);
		if ($aid <= 0 || $buyerMid <= 0 || $bizOrderId <= 0 || !$bindRows) {
			return;
		}

		$qtyMap = [];
		foreach ($bindRows as $row) {
			$goodsId = intval($row['goods_id'] ?? 0);
			$skuId = intval($row['sku_id'] ?? 0);
			if ($goodsId <= 0 || $skuId <= 0) {
				continue;
			}
			$boxQty = max(1, intval($row['box_qty'] ?? 1));
			$unit = strtolower(trim(strval($row['qty_unit'] ?? 'box')));
			if ($unit !== 'piece') {
				$unit = 'box';
			}
			$qty = max(0, intval($row['qty'] ?? 0) * max(1, intval($row['shop_num'] ?? 1)));
			$qtyPiece = intval($row['qty_piece'] ?? 0);
			if ($qtyPiece <= 0) {
				$qtyPiece = $unit === 'piece' ? $qty : $qty * $boxQty;
			}
			if ($qtyPiece <= 0) {
				continue;
			}

			$key = $goodsId . ':' . $skuId;
			if (!isset($qtyMap[$key])) {
				$qtyMap[$key] = [
					'goods_id' => $goodsId,
					'sku_id' => $skuId,
					'qty_piece' => 0,
				];
			}
			$qtyMap[$key]['qty_piece'] += $qtyPiece;
		}
		if (!$qtyMap) {
			return;
		}

		foreach ($qtyMap as $it) {
			$goodsId = intval($it['goods_id'] ?? 0);
			$skuId = intval($it['sku_id'] ?? 0);
			$qtyPiece = max(0, intval($it['qty_piece'] ?? 0));
			if ($goodsId <= 0 || $skuId <= 0 || $qtyPiece <= 0) {
				continue;
			}

			$exists = Db::name('yunkucun_goods_stock_ledger')
				->where('aid', $aid)
				->where('bid', $bid)
				->where('mid', $buyerMid)
				->where('goods_id', $goodsId)
				->where('sku_id', $skuId)
				->where('biz_type', \app\admin\common\YunkucunV3Const::BIZ_REPLENISH_INBOUND)
				->where('biz_id', $bizOrderId)
				->find();
			if ($exists) {
				continue;
			}

			\app\admin\common\YunkucunGoodsStock::change(
				$aid,
				$bid,
				$buyerMid,
				$goodsId,
				$skuId,
				$qtyPiece,
				0,
				0,
				\app\admin\common\YunkucunV3Const::BIZ_REPLENISH_INBOUND,
				$bizOrderId,
				'商城升级专用商品入库: ' . $bizOrdernum,
				$clientRequestNo,
				true
			);
		}
	}

	private static function settleYkv3ShopBindUpgradeSpecial(array $shopOrder): void
	{
		$aid = intval($shopOrder['aid'] ?? 0);
		$bid = intval($shopOrder['bid'] ?? 0);
		$shopOrderId = intval($shopOrder['id'] ?? 0);
		$buyerMid = intval($shopOrder['mid'] ?? 0);
		if ($aid <= 0 || $shopOrderId <= 0 || $buyerMid <= 0) {
			return;
		}

		$member = Db::name('member')
			->where('aid', $aid)
			->where('id', $buyerMid)
			->field('id,levelid')
			->find();
		if (!$member) {
			return;
		}
		$currentLevelId = intval($member['levelid'] ?? 0);
		$currentLevelSort = intval(Db::name('member_level')
			->where('aid', $aid)
			->where('id', $currentLevelId)
			->value('sort'));

		$bindRows = Db::name('yunkucun_goods_shop_order_bind')->alias('ob')
			->join('yunkucun_goods g', 'g.id=ob.goods_id AND g.aid=ob.aid AND g.bid=ob.bid')
			->join('yunkucun_goods_sku s', 's.id=ob.sku_id AND s.aid=ob.aid AND s.bid=ob.bid')
			->leftJoin('member_level lv', 'lv.id=g.upgrade_to_levelid AND lv.aid=g.aid')
			->where('ob.aid', $aid)
			->where('ob.bid', $bid)
			->where('ob.orderid', $shopOrderId)
			->where('ob.status', 1)
			->where('g.status', 1)
			->where('g.is_upgrade_special', 1)
			->where('g.upgrade_to_levelid', '>', 0)
			->where('s.status', 1)
			->order('ob.id asc')
			->field([
				'ob.goods_id',
				'ob.sku_id',
				'ob.shipper_mid',
				'ob.qty',
				'ob.qty_unit',
				'ob.qty_piece',
				'ob.shop_num',
				'g.name as goods_name',
				'g.upgrade_to_levelid',
				'g.upgrade_recommend_reward',
				'g.upgrade_reward_levelids',
				'g.upgrade_buy_limit',
				's.sku_name',
				's.box_qty',
				'lv.sort as to_level_sort',
			])
			->select()
			->toArray();
		if (!$bindRows) {
			return;
		}

		$selected = null;
		foreach ($bindRows as $row) {
			$toSort = intval($row['to_level_sort'] ?? 0);
			if ($toSort <= $currentLevelSort) {
				continue;
			}
			if (!$selected || $toSort > intval($selected['to_level_sort'] ?? 0)) {
				$selected = $row;
			}
		}
		if (!$selected) {
			return;
		}

			$targetGoodsId = intval($selected['goods_id'] ?? 0);
			$targetLevelId = intval($selected['upgrade_to_levelid'] ?? 0);
			if ($targetGoodsId <= 0 || $targetLevelId <= 0) {
				return;
			}
			$targetLevel = Db::name('member_level')
				->where('aid', $aid)
				->where('id', $targetLevelId)
				->find();
			if (!$targetLevel) {
				return;
			}

		$buyLimit = max(1, intval($selected['upgrade_buy_limit'] ?? 1));
		$replenishPaidCount = intval(Db::name('yunkucun_replenish_order_item')->alias('i')
			->join('yunkucun_replenish_order o', 'o.id=i.orderid')
			->where('o.aid', $aid)
			->where('o.bid', $bid)
			->where('o.mid', $buyerMid)
			->where('o.goods_domain', 1)
			->where('o.status', 'in', [1, 2])
			->where('o.paytime', '>', 0)
			->where('i.proid', $targetGoodsId)
			->count());
		$shopPaidOrderIds = Db::name('yunkucun_goods_shop_order_bind')
			->where('aid', $aid)
			->where('bid', $bid)
			->where('mid', $buyerMid)
			->where('goods_id', $targetGoodsId)
			->where('status', 1)
			->where('orderid', '<>', $shopOrderId)
			->group('orderid')
			->column('orderid');
		$shopPaidCount = is_array($shopPaidOrderIds) ? count($shopPaidOrderIds) : 0;
		if (($replenishPaidCount + $shopPaidCount) >= $buyLimit) {
			return;
		}

		$targetRows = [];
		foreach ($bindRows as $row) {
			if (intval($row['goods_id'] ?? 0) === $targetGoodsId) {
				$targetRows[] = $row;
			}
		}
		if (!$targetRows) {
			return;
		}

		$upgradeSpecialTheoreticalShipperMid = self::resolveUpgradeSpecialTheoreticalShipperMidForSettle(
			$aid,
			$buyerMid,
			$targetLevelId
		);

		$upgradeItems = [];
		$totalPiece = 0;
		foreach ($targetRows as $row) {
			$boxQty = max(1, intval($row['box_qty'] ?? 1));
			$unit = strtolower(trim(strval($row['qty_unit'] ?? 'box')));
			if ($unit !== 'piece') {
				$unit = 'box';
			}
			$qty = max(0, intval($row['qty'] ?? 0) * max(1, intval($row['shop_num'] ?? 1)));
			$qtyPiece = intval($row['qty_piece'] ?? 0);
			if ($qtyPiece <= 0) {
				$qtyPiece = $unit === 'piece' ? $qty : $qty * $boxQty;
			}
			if ($qtyPiece <= 0) {
				continue;
			}
			$totalPiece += $qtyPiece;
			$upgradeItems[] = [
				'proid' => intval($row['goods_id'] ?? 0),
				'ggid' => intval($row['sku_id'] ?? 0),
				'name' => strval($row['goods_name'] ?? ''),
				'ggname' => strval($row['sku_name'] ?? ''),
				'qty' => $qty > 0 ? $qty : 1,
				'qty_unit' => $unit,
				'qty_piece' => $qtyPiece,
				'box_qty' => $boxQty,
				'wholesale_price' => 0,
			];
		}
		if (!$upgradeItems || $totalPiece <= 0) {
			return;
		}

		$upgradeNeedPiece = 0;
		$upgradeReferrerRewardPerPiece = 0.0;
		try {
			$ruleRow = Db::name('yunkucun_goods_level_rule')
				->where('aid', $aid)
				->where('levelid', $targetLevelId)
				->field('upgrade_need_piece,upgrade_referrer_reward_per_piece')
				->find();
			if ($ruleRow) {
				$upgradeNeedPiece = max(0, intval($ruleRow['upgrade_need_piece'] ?? 0));
				$upgradeReferrerRewardPerPiece = round(max(0, floatval($ruleRow['upgrade_referrer_reward_per_piece'] ?? 0)), 2);
			}
		} catch (\Throwable $e) {
			$upgradeNeedPiece = 0;
			$upgradeReferrerRewardPerPiece = 0.0;
		}
		if ($upgradeNeedPiece <= 0) {
			$upgradeNeedPiece = $totalPiece;
		}

		$sysset = null;
		try {
			$sysset = Db::name('yunkucun_goods_sysset')
				->where('aid', $aid)
				->where('bid', $bid)
				->find();
		} catch (\Throwable $e) {
			$sysset = null;
		}
		if (!$sysset) {
			try {
				$sysset = Db::name('yunkucun_goods_sysset')
					->where('aid', $aid)
					->find();
			} catch (\Throwable $e) {
				$sysset = null;
			}
		}
		$rewardPayerMode = intval($sysset['upgrade_special_reward_payer_mode'] ?? 0) === 1
			? 'theoretical_shipper'
			: 'platform';

		$shopOrdernum = strval($shopOrder['ordernum'] ?? '');
		if ($shopOrdernum === '') {
			$shopOrdernum = 'SHOP' . $shopOrderId;
		}
		$bizOrderId = 1000000000 + $shopOrderId;
		if ($bizOrderId <= 0) {
			$bizOrderId = $shopOrderId;
		}
		$bizOrder = [
			'id' => $bizOrderId,
			'aid' => $aid,
			'bid' => $bid,
			'mid' => $buyerMid,
			'ordernum' => $shopOrdernum,
			'client_request_no' => 'shop_bind_upgrade_' . $shopOrderId,
		];

		$snapshot = [
			'buyer_levelid' => $currentLevelId,
			'upgrade' => [
				'from_levelid' => $currentLevelId,
				'to_levelid' => $targetLevelId,
				'need_piece' => $upgradeNeedPiece,
				'referrer_reward_per_piece' => $upgradeReferrerRewardPerPiece,
				'total_piece' => $totalPiece,
					'special_goods' => [
						'enabled' => 1,
						'goods_id' => $targetGoodsId,
						'to_levelid' => $targetLevelId,
					'fixed_reward_amount' => round(max(0, floatval($selected['upgrade_recommend_reward'] ?? 0)), 2),
					'reward_levelids' => trim(strval($selected['upgrade_reward_levelids'] ?? '')),
						'buy_limit' => $buyLimit,
						'deposit_included' => 1,
						'force_shipper_platform' => 1,
						'reward_payer_mode' => $rewardPayerMode,
						'theoretical_shipper_mid' => intval($upgradeSpecialTheoreticalShipperMid),
					],
				],
			];

		Db::transaction(function () use ($bizOrder, $upgradeItems, &$snapshot, $shopOrdernum, $aid, $buyerMid, $targetLevelId, $targetLevel, $targetRows) {
			self::inboundYkv3ShopBindUpgradeSpecialStocks(
				intval($bizOrder['aid'] ?? 0),
				intval($bizOrder['bid'] ?? 0),
				intval($bizOrder['mid'] ?? 0),
				intval($bizOrder['id'] ?? 0),
				strval($bizOrder['ordernum'] ?? $shopOrdernum),
				strval($bizOrder['client_request_no'] ?? ''),
				$targetRows
			);

			self::settleUpgradeSpecialLevelDiffProfit($bizOrder, $upgradeItems, $snapshot, $shopOrdernum);
			self::settleUpgradeSpecialReferrerRewardForRealDelivery($bizOrder, $snapshot, $shopOrdernum);

			if (!\app\admin\common\Member::applyMemberLevel(
				intval($aid),
				intval($buyerMid),
				$targetLevel
			)) {
				throw new \RuntimeException('会员等级更新失败');
			}

			self::settleUpgradeSpecialDepositIncluded($bizOrder, $snapshot);
		});
	}

	private static function createShopPeisongOrderAfterPay($orderid): void
	{
		try {
			$order = Db::name('shop_order')->where('id', intval($orderid))->field('id,ordernum')->find();
			if (!$order || empty($order['ordernum'])) return;

			$rs = \app\admin\common\Transport::addtransport(['ordernum' => $order['ordernum']]);
			if (is_array($rs) && intval($rs['status'] ?? 0) === 0 && ($rs['msg'] ?? '') !== '已存在配送单') {
				Log::warning('shop_pay: 同城配送单自动创建失败', [
					'orderid' => intval($order['id']),
					'ordernum' => $order['ordernum'] ?? '',
					'msg' => $rs['msg'] ?? '',
				]);
			}
		} catch (\Throwable $e) {
			Log::error('shop_pay: 同城配送单自动创建异常', [
				'orderid' => intval($orderid),
				'err' => $e->getMessage(),
			]);
		}
	}

	/**
	 * 商城订单支付后通知营销插件（阶梯全返/九星/矩阵奖励）。
	 * shop_pay 开头调一次：保证占上级席位不被后半段异常吃掉。
	 * 会员升级后再调一次：粉丝买指定商品会升会员，建自己的奖池必须用升级后的等级。
	 */
	private static function notifyShopOrderPluginPaid($aid, $orderId)
	{
		$aid = intval($aid);
		$orderId = intval($orderId);
		self::jietiquanfanDebug('notifyShopOrderPluginPaid enter', ['aid' => $aid, 'order_id' => $orderId]);
		if ($aid <= 0 || $orderId <= 0) {
			self::jietiquanfanDebug('notifyShopOrderPluginPaid skip invalid', ['aid' => $aid, 'order_id' => $orderId]);
			return;
		}
		try {
			if (class_exists('\\app\\plugin\\tenstar\\common\\TenstarService')) {
				\app\plugin\tenstar\common\TenstarService::processOrderStage($aid, $orderId, 'paid', 'shop');
			}
		} catch (\Throwable $e) {
			\think\facade\Log::error('tenstar 支付结算失败：' . $e->getMessage());
		}
		try {
			if (class_exists('\\app\\plugin\\matrixreward\\common\\MatrixRewardService')) {
				\app\plugin\matrixreward\common\MatrixRewardService::processOrderStage($aid, $orderId, 'paid', 'shop');
			}
		} catch (\Throwable $e) {
			\think\facade\Log::error('matrixreward 支付结算失败：' . $e->getMessage());
		}
		try {
			$exists = class_exists('\\app\\plugin\\jietiquanfan\\common\\JietiquanfanService');
			self::jietiquanfanDebug('notifyShopOrderPluginPaid class_exists', ['order_id' => $orderId, 'exists' => $exists ? 1 : 0]);
			if ($exists) {
				$rs = \app\plugin\jietiquanfan\common\JietiquanfanService::processOrderStage($aid, $orderId, 'paid', 'shop');
				self::jietiquanfanDebug('notifyShopOrderPluginPaid result', [
					'order_id' => $orderId,
					'status' => intval($rs['status'] ?? 0),
					'msg' => strval($rs['msg'] ?? ''),
				]);
			}
		} catch (\Throwable $e) {
			self::jietiquanfanDebug('notifyShopOrderPluginPaid error', ['order_id' => $orderId, 'msg' => $e->getMessage()]);
			\think\facade\Log::error('jietiquanfan 支付结算失败：' . $e->getMessage());
		}
	}

	private static function jietiquanfanDebug($msg, $data = [])
	{
		$payload = is_array($data) ? $data : ['data' => $data];
		\think\facade\Log::info('阶梯全返调试-' . $msg, $payload);
		$line = date('Y-m-d H:i:s') . ' 阶梯全返调试-' . $msg . ' ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
		@file_put_contents(app()->getRuntimePath() . 'jietiquanfan-debug.log', $line, FILE_APPEND);
	}

	//商城订单
// 商城订单
	public static function shop_pay($orderid){
    $order = Db::name('shop_order')->where('id', $orderid)->find();
    if(!$order) return;
		self::jietiquanfanDebug('shop_pay enter', [
			'order_id' => intval($orderid),
			'aid' => intval($order['aid'] ?? 0),
			'mid' => intval($order['mid'] ?? 0),
			'status' => intval($order['status'] ?? 0),
			'paytime' => intval($order['paytime'] ?? 0),
			'ordernum' => strval($order['ordernum'] ?? ''),
		]);
		if (class_exists('\\app\\plugin\\combo\\common\\ComboOrderService')) {
			try {
				\app\plugin\combo\common\ComboOrderService::markPaid(intval($orderid), time(), intval($order['aid'] ?? 0));
			} catch (\Throwable $e) {
				\think\facade\Log::warning('搭配购支付状态同步失败: ' . $e->getMessage());
			}
		}
    self::syncJishouPaidOrderState(intval($orderid));
	    $member = Db::name('member')->where('id', $order['mid'])->find();
	    $aid = $order['aid'];
	    $mid = $order['mid'];
	    self::notifyShopOrderPluginPaid($aid, intval($order['id']));
	    if (class_exists('\\app\\plugin\\storedvaluecoupon\\common\\StoredValueCouponService')) {
	        try {
	            $storedValueCommit = \app\plugin\storedvaluecoupon\common\StoredValueCouponService::commit(
	                intval($aid),
	                intval($orderid),
	                ['source' => 'shop_pay']
	            );
	            if (intval($storedValueCommit['status'] ?? 0) !== 1) {
	                \think\facade\Log::error('商城订单储值优惠券扣减失败：' . strval($storedValueCommit['msg'] ?? '未知错误'));
	            }
	        } catch (\Throwable $e) {
	            \think\facade\Log::error('商城订单储值优惠券扣减异常：' . $e->getMessage());
	        }
	    }
	    $serviceClass = self::getPresaleVoucherServiceClass(intval($aid));
    if ($serviceClass !== '') {
        try {
            $serviceClass::issueByShopOrder(intval($aid), intval($orderid));
        } catch (\Throwable $e) {
            \think\facade\Log::error('预售券发券失败(shop_pay)：'.$e->getMessage());
        }
    }
	$adoptionServiceClass = self::getAdoptionServiceClass();
	if ($adoptionServiceClass !== '') {
		try {
			$adoptionServiceClass::issueByShopOrder(intval($aid), intval($orderid));
		} catch (\Throwable $e) {
			\think\facade\Log::error('认养合同生成失败(shop_pay)：' . $e->getMessage());
		}
	}

    // 云仓V3：支付后出库（冻结扣减）
	    try {
	        Db::transaction(function () use ($order, $orderid, $aid) {
	            $rows = Db::name('yunkucun_goods_shop_order_bind')
	                ->where('aid', intval($aid))
	                ->where('orderid', intval($orderid))
                ->where('status', 0)
                ->lock(true)
                ->select()
	                ->toArray();
	            if (!$rows) return;

	            $goodsIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'goods_id')), function ($v) {
	                return $v > 0;
	            })));
	            $upgradeSpecialMap = [];
	            if ($goodsIds) {
	                $goodsRows = Db::name('yunkucun_goods')
	                    ->where('aid', intval($aid))
	                    ->where('id', 'in', $goodsIds)
	                    ->field('id,is_upgrade_special')
	                    ->select()
	                    ->toArray();
	                foreach (($goodsRows ?: []) as $gr) {
	                    $upgradeSpecialMap[intval($gr['id'] ?? 0)] = intval($gr['is_upgrade_special'] ?? 0);
	                }
	            }

	            $now = time();
		            foreach ($rows as $r) {
		                $goodsId = intval($r['goods_id'] ?? 0);
		                $skuId = intval($r['sku_id'] ?? 0);
		                $qtyPiece = intval($r['qty_piece'] ?? 0);
		                $isUpgradeSpecial = intval($upgradeSpecialMap[$goodsId] ?? 0) === 1;

		                // 升级专用商品：商城支付时不做“绑定库存扣减”。
		                // 兼容历史错误预占：若已冻结则释放回可用。
		                if ($isUpgradeSpecial) {
		                    $shipperMid = intval($r['shipper_mid'] ?? 0);
		                    if ($qtyPiece > 0) {
		                        try {
		                            $frozenNum = intval(Db::name('yunkucun_goods_stock')
		                                ->where('aid', intval($aid))
		                                ->where('bid', intval($r['bid'] ?? 0))
		                                ->where('mid', $shipperMid)
		                                ->where('goods_id', $goodsId)
		                                ->where('sku_id', $skuId)
		                                ->value('frozen_num'));
		                            if ($frozenNum >= $qtyPiece) {
		                                \app\admin\common\YunkucunGoodsStock::shopBindCancel(
		                                    intval($aid),
		                                    intval($r['bid'] ?? 0),
		                                    $shipperMid,
		                                    $goodsId,
		                                    $skuId,
		                                    $qtyPiece,
		                                    intval($orderid),
		                                    '商城升级专用商品支付释放历史预占：' . strval($r['ordernum'] ?? ''),
		                                    '',
		                                    true
		                                );
		                            }
		                        } catch (\Throwable $e) {
		                            \think\facade\Log::warning('shop_pay: 释放升级专用商品历史预占失败', [
		                                'orderid' => intval($orderid),
		                                'bind_id' => intval($r['id'] ?? 0),
		                                'err' => $e->getMessage(),
		                            ]);
		                        }
		                    }
		                    Db::name('yunkucun_goods_shop_order_bind')->where('aid', intval($aid))->where('id', intval($r['id']))->update(['status' => 1, 'updatetime' => $now]);
		                    continue;
		                }

		                if ($qtyPiece <= 0) {
		                    Db::name('yunkucun_goods_shop_order_bind')->where('aid', intval($aid))->where('id', intval($r['id']))->update(['status' => 1, 'updatetime' => $now]);
		                    continue;
		                }
		                \app\admin\common\YunkucunGoodsStock::shopBindPayOut(
		                    intval($aid),
		                    intval($r['bid'] ?? 0),
		                    intval($r['shipper_mid'] ?? 0),
		                    $goodsId,
		                    $skuId,
		                    $qtyPiece,
		                    intval($orderid),
		                    '商城订单支付出库：' . strval($r['ordernum'] ?? ''),
	                    '',
	                    true
	                );
	                Db::name('yunkucun_goods_shop_order_bind')->where('aid', intval($aid))->where('id', intval($r['id']))->update(['status' => 1, 'updatetime' => $now]);
	            }
	        });
	    } catch (\Throwable $e) {
        \think\facade\Log::error('shop_pay: 云仓V3出库失败', ['orderid' => $orderid, 'err' => $e->getMessage()]);
    }

	// 云仓V3：商城绑定升级专用商品，支付后触发升级与奖励结算
	try {
		self::settleYkv3ShopBindUpgradeSpecial($order);
	} catch (\Throwable $e) {
		\think\facade\Log::error('shop_pay: 云仓V3绑定升级结算失败', ['orderid' => $orderid, 'err' => $e->getMessage()]);
	}
    
    // 单笔最大奖励累计统计功能
    \think\facade\Log::write('单笔最大奖励日志-订单支付开始:订单ID='.$orderid.',会员ID='.$mid.',订单金额='.$order['totalprice']);
    $rewardLimitSet = self::tableExists('reward_limit_set') ? Db::name('reward_limit_set')->where('aid', $aid)->find() : null;
    if($rewardLimitSet && intval($rewardLimitSet['status']) == 1) {
        // 检查会员等级是否参与累计
        $shouldParticipate = true; // 默认参与
        if($member && !empty($rewardLimitSet['level_participate_config'])) {
            $levelParticipateConfig = json_decode($rewardLimitSet['level_participate_config'], true) ?: [];
            if(isset($levelParticipateConfig[$member['levelid']])) {
                $shouldParticipate = intval($levelParticipateConfig[$member['levelid']]) == 1;
            }
        }
        
        \think\facade\Log::write('单笔最大奖励日志-等级参与状态:会员等级='.$member['levelid'].',是否参与='.($shouldParticipate?'是':'否'));
        
        if($shouldParticipate) {
            // 累加订单金额到会员的max_single_reward字段
            $currentAmount = floatval($member['max_single_reward']);
            $orderAmount = floatval($order['totalprice']);
            $newAmount = $currentAmount + $orderAmount;
            
            Db::name('member')->where('id', $mid)->update(['max_single_reward' => $newAmount]);
            
            \think\facade\Log::write('单笔最大奖励日志-累计更新:原金额='.$currentAmount.',订单金额='.$orderAmount.',累计后='.$newAmount);
            
          
        }
    } else {
        \think\facade\Log::write('单笔最大奖励日志-功能未开启:设置状态='.($rewardLimitSet ? $rewardLimitSet['status'] : '未配置'));
    }
    // var_dump($order);die;
      // 检查是否开启提货编号功能
      $sysset = Db::name('shop_sysset')->where('aid', $aid)->find();
      if($sysset && $sysset['enable_pickup_code'] == 1) {
          // 获取门店设置
          $mendian = Db::name('mendian')->where('id', $order['mdid'])->find();
          if($mendian) {
              // 获取门店的提货编号配置
              $prefix = !empty($mendian['code_prefix']) ? $mendian['code_prefix'] : 'A';
              $max_layer = !empty($mendian['code_max_layer']) ? $mendian['code_max_layer'] : 5;
              $max_number = !empty($mendian['code_max_number']) ? $mendian['code_max_number'] : 50;

              // 获取重置间隔小时数，默认24小时
              $reset_interval = !empty($sysset['pickup_code_reset_interval']) ? intval($sysset['pickup_code_reset_interval']) : 24;
              
              // 计算重置时间范围
              $current_timestamp = time();
              // 将小时转换为秒计算
              $hours_from_epoch = floor($current_timestamp / (3600 * $reset_interval));
              $period_start = $hours_from_epoch * (3600 * $reset_interval);
              $period_end = $period_start + (3600 * $reset_interval);

              // 获取当前时间段内已使用的编号
              $used_codes = Db::name('shop_order')
                  ->where('mdid', $order['mdid'])
                  ->where('pickup_code', '<>', '')
                  ->where('paytime', '>=', $period_start)
                  ->where('paytime', '<', $period_end)
                  ->column('pickup_code');

              // 生成新的编号
              for($layer = 1; $layer <= $max_layer; $layer++) {
                  for($number = 1; $number <= $max_number; $number++) {
                      $code = $prefix . '-' . $layer . '-' . $number;
                      if(!in_array($code, $used_codes)) {
                          // 更新订单的提货编号
                          Db::name('shop_order')->where('id', $orderid)->update([
                              'pickup_code' => $code
                          ]);
                          break 2;
                      }
                  }
              }
          }
      }
    // 扣除用户现金券
    if($order['scoredkscorehei'] > 0){
        // 减去会员的现金券
        \app\admin\common\Member::addhei($aid, $mid, -$order['scoredkscorehei'], '支付订单,订单号: '.$order['ordernum']);
    }
    if($order['scoredkscoreyu'] > 0){
        // 减去会员的余额
        \app\admin\common\Member::addmoney($aid, $mid, -$order['scoredkscoreyu'], '支付订单,订单号: '.$order['ordernum']);
    }
    
    // 返创业值
    $memberlevel = Db::name('member_level')->where('aid', $aid)->where('id', $member['levelid'])->find();
    if($memberlevel['zijifh'] == 1)
    {
        $zijifh_jiang = json_decode($memberlevel['zijifh_jiang'],1);
        
        if($order['totalprice'] >= $zijifh_jiang[3]['min'] &&  $zijifh_jiang[3]['min'] != 0)
        {
            $beishu = $zijifh_jiang[3]['beishu'];
        } elseif($order['totalprice'] >= $zijifh_jiang[2]['min'] && $order['totalprice'] < $zijifh_jiang[2]['max'])
        {
            $beishu = $zijifh_jiang[2]['beishu'];
        } elseif($order['totalprice'] >= $zijifh_jiang[1]['min'] && $order['totalprice'] < $zijifh_jiang[1]['max'])
        {
            $beishu = $zijifh_jiang[1]['beishu'];
        } elseif($order['totalprice'] >= $zijifh_jiang[0]['min'] && $order['totalprice'] < $zijifh_jiang[0]['max'])
        {
            $beishu = $zijifh_jiang[0]['beishu'];
        } else{
            $beishu = 0;
        }
        $jiangli = $order['totalprice'] * $beishu;
        if($jiangli > 0)
        {
            \app\admin\common\Member::addzijifh($aid, $mid, $jiangli, '自己消费奖励'.$jiangli.',倍数为'.$beishu);
        }
    }
    
    if($memberlevel['is_bus_bound_multiples'] == 1){
        $cyz = $memberlevel['bus_bound_multiples'] * $order['totalprice'];
        \app\admin\common\Member::addbusTotal($aid, $mid, $mid, $cyz, '购买产品赠送'.t('创业值'));
    }
    
    if($memberlevel['fxfhc'] == 1)
    {
        $zijifh_jiang = json_decode($memberlevel['fxfhc_jiang'],1);
        if($order['totalprice'] >= $zijifh_jiang[3]['min'] &&  $zijifh_jiang[3]['min'] != 0)
        {
            $beishu = $zijifh_jiang[3]['beishu'];
        } elseif($order['totalprice'] >= $zijifh_jiang[2]['min'] && $order['totalprice'] < $zijifh_jiang[2]['max'])
        {
            $beishu = $zijifh_jiang[2]['beishu'];
        } elseif($order['totalprice'] >= $zijifh_jiang[1]['min'] && $order['totalprice'] < $zijifh_jiang[1]['max'])
        {
            $beishu = $zijifh_jiang[1]['beishu'];
        } elseif($order['totalprice'] >= $zijifh_jiang[0]['min'] && $order['totalprice'] < $zijifh_jiang[0]['max'])
        {
            $beishu = $zijifh_jiang[0]['beishu'];
        } else{
            $beishu = 0;
        }
        $jiangli = $order['totalprice'] * $beishu;
        if($jiangli > 0)
        {
            \app\admin\common\Member::addfxfhc($aid, $mid, $jiangli, '分享分红池奖励'.$jiangli.',倍数为'.$beishu);
        }
    }
    
    if($memberlevel['syc'] == 1)
    {
        $q = 0;
        $zijifh_jiang = json_decode($memberlevel['syc_jiang'],1);
        if($order['totalprice'] >= $zijifh_jiang[3]['min'] &&  $zijifh_jiang[3]['min'] != 0)
        {
            $beishu = $zijifh_jiang[3]['beishu'];
            $jiangli = $order['totalprice'] * $beishu;
        } elseif($order['totalprice'] >= $zijifh_jiang[2]['min'] && $order['totalprice'] < $zijifh_jiang[2]['max'])
        {
            $beishu = $zijifh_jiang[2]['beishu'];
            $jiangli = $order['totalprice'] * $beishu;
        } elseif($order['totalprice'] >= $zijifh_jiang[1]['min'] && $order['totalprice'] < $zijifh_jiang[1]['max'])
        {
            $beishu = $zijifh_jiang[1]['beishu'];
            $jiangli = $order['totalprice'] * $beishu;
        } elseif($order['totalprice'] >= $zijifh_jiang[0]['min'] && $order['totalprice'] < $zijifh_jiang[0]['max'])
        {
            $youwu = Db::name('member_syclog')->where('mid', $mid)->where('aid', $aid)->where('q', 1)->find();
            if(empty($youwu))
            {
                $beishu = $zijifh_jiang[0]['beishu'];
                $jiangli = $beishu;
                // 修改我的状态;
                $q = 1;
            } else{
                $jiangli = 0; 
            }
        } else{
            $beishu = 0;
            $jiangli = 0;
        }
        if($jiangli > 0)
        {
            \app\admin\common\Member::addsyc($aid, $mid, $jiangli, '收益池奖励'.$jiangli.',倍数为'.$beishu, $q);
        }
    }
    
    // 下单后返现处理
    $oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$order['id'])->select()->toArray();
    //购物返现（统一服务：支付与抵扣条件命中、计算、发放、防重复日志）
    \app\admin\common\CashbackRewardService::process($aid, $order, $oglist, $member, 'pay');
    // 购物分期返现插件：按订单商品行创建逐日返现计划。
    try {
        if (class_exists('\\app\\plugin\\shopdailycashback\\common\\ShopDailyCashbackService')) {
            \app\plugin\shopdailycashback\common\ShopDailyCashbackService::processOrder($aid, $order, $oglist, $member, 'pay');
        }
    } catch (\Throwable $e) {
        \think\facade\Log::error('购物分期返现支付后建计划失败', [
            'aid' => intval($aid),
            'order_id' => intval($order['id'] ?? 0),
            'error' => $e->getMessage(),
        ]);
    }
    try {
        if (class_exists('\\app\\plugin\\cityagent\\common\\CityAgentDividendService')) {
            \app\plugin\cityagent\common\CityAgentDividendService::settleOrder($aid, $order, $oglist, 'pay');
        }
    } catch (\Throwable $e) {
        \think\facade\Log::error('城市代理插件支付后分润失败', [
            'aid' => intval($aid),
            'order_id' => intval($order['id'] ?? 0),
            'error' => $e->getMessage(),
        ]);
    }
    
    // 给黑积分上级 $member['pid']
    if($memberlevel['tjmoney'] > 0)
    {
        if($member['jiangcount'] < $memberlevel['tjmoney'])
        {
            $dedao = 0;
            if($order['totalprice'] >= $memberlevel['tjmoney'])
            {
                $dedao = $memberlevel['tjmoney'] - $member['jiangcount'];
            } else{
                // 消��� 2000 设置 3000
                $quezhexie = $memberlevel['tjmoney'] - $member['jiangcount'];
                if($quezhexie >= $order['totalprice'])
                {
                    $dedao = $order['totalprice'];
                } else{
                    $dedao = $quezhexie;
                }
            }
            if($dedao > 0)
            {
                \app\admin\common\Member::addhei($aid, $member['pid'], $dedao, '推荐奖励金额累计'.$memberlevel['tjmoney']);
                // 更改拿到的钱数
                $myjiangcount = $member['jiangcount'] + $dedao;
                Db::name('member')->where('aid', $aid)->where('id', $member['id'])->update(['jiangcount' => $myjiangcount]);
            }
        }
    }
    
    //// 给用户添加贡献值 每消费多少元赠送多少贡献值
    // \app\admin\common\Member::getAddContribution($aid, $mid, 3000, '订单消费送贡献值');
    // // 给用户添加红包 每消费多少元赠送多少红包
    // \app\admin\common\Member::getAddhuangjifen($aid, $mid, $order['totalprice'], $payorder['ordernum'], '订单消费送金币');
    // // 给用户金币 每消费多少元赠送多少金币
    // \app\admin\common\Member::getAddjinbi($aid, $mid, $order['totalprice'], $payorder['ordernum'], '订单消费送金币');
    
    // 根据区间返创业值
    $sysset = Db::name('admin_set')->where('aid', $aid)->find();
    $cyz_qu = json_decode($sysset['cyz_qu'],1);
    $myzonge = $order['product_price'];
    if($sysset['cyz_shi']){
        if($myzonge >= $cyz_qu[6]['min'])
        {
            $jiang = $cyz_qu[6]['jiang'];
        } elseif($myzonge >= $cyz_qu[5]['min'] && $myzonge <= $cyz_qu[5]['max'])
        {
            $jiang = $cyz_qu[5]['jiang'];
        } elseif($myzonge >= $cyz_qu[4]['min'] && $myzonge <= $cyz_qu[4]['max'])
        {
            $jiang = $cyz_qu[4]['jiang'];
        } elseif($myzonge >= $cyz_qu[3]['min'] && $myzonge <= $cyz_qu[3]['max'])
        {
            $jiang = $cyz_qu[3]['jiang'];
        } elseif($myzonge >= $cyz_qu[2]['min'] && $myzonge <= $cyz_qu[2]['max'])
        {
            $jiang = $cyz_qu[2]['jiang'];
        } elseif($myzonge >= $cyz_qu[1]['min'] && $myzonge <= $cyz_qu[1]['max'])
        {
            $jiang = $cyz_qu[1]['jiang'];
        } else{
            $jiang = 0;
        }
        if($jiang > 0)
        {
            $jiangli = $myzonge * $jiang;
            \app\admin\common\Member::addbusTotal($aid, $mid, $mid, $jiangli, '购买产品赠送区间'.t('创业值'));
        }
    }
    
    $oglist = Db::name('shop_order_goods')->where('orderid', $orderid)->select()->toArray();
    
            foreach ($oglist as $og) {
                    // 处理 parent1 的越级奖励
                    if ($og['parent1'] > 0) {
                        $parent1 = Db::name('member')->where('id', $og['parent1'])->find();
                        $parent1_level = Db::name('member_level')->where('aid', $aid)->where('id', $parent1['levelid'])->find();
            
                        if ($parent1_level && isset($parent1_level['upgrade_level_ids']) && isset($parent1_level['upgrade_reward'])) {
                            $upgrade_level_ids = explode(',', $parent1_level['upgrade_level_ids']);
                            $upgrade_reward = $parent1_level['upgrade_reward'] / 100; // 将百分比转换为小数
            
                            if (in_array($member['levelid'], $upgrade_level_ids)) {
                                $jiangli_parent1 = $order['totalprice'] * $upgrade_reward; // 使用 totalprice 计算奖励
            
                                // 调用 addcommission 方法发放奖励到佣金
                                \app\admin\common\Member::addcommission($aid, $og['parent1'], $mid, $jiangli_parent1, '上级奖励单号: '.$order['ordernum']);
                            }
                        }
                    }
                }
    if($sysset['cyz_shi']){
        foreach($oglist as $og)
        {
            
            if($og['parent1'] > 0){
                $parent1 = Db::name('member')->where('id', $og['parent1'])->find();
                if($parent1['bus_total'] >= $og['parent1commission'])
                {
                    // 扣除创业值
                    \app\admin\common\Member::addbusTotal($aid, $og['parent1'], $og['parent1'], '-'.$og['parent1commission'], '佣金扣除'.t('创业值'));
                } else{
                    // 把佣金改成0
                    $upcommission = $parent1['bus_total'];
                    \app\admin\common\Member::addbusTotal($aid, $og['parent1'], $og['parent1'], '-'.$upcommission, '佣金扣除'.t('创业值'));
                    Db::name('member_commission_record')->where('jl_type',1)->where('ogid', $og['id'])->where('mid', $og['parent1'])->update(['commission' => $upcommission]);
                }
            }
            if($og['parent2'] > 0){
                $parent2 = Db::name('member')->where('id', $og['parent2'])->find();
                if($parent2['bus_total'] >= $og['parent2commission'])
                {
                    // 扣���创业值
                    \app\admin\common\Member::addbusTotal($aid, $og['parent2'], $og['parent2'], '-'.$og['parent2commission'], '佣金扣除'.t('创业值'));
                } else{
                    // 把佣金改成0
                    $upcommission = $parent2['bus_total'];
                    \app\admin\common\Member::addbusTotal($aid, $og['parent2'], $og['parent2'], '-'.$upcommission, '佣金扣除'.t('创业值'));
                    Db::name('member_commission_record')->where('jl_type',1)->where('ogid', $og['id'])->where('mid', $og['parent2'])->update(['commission' => $upcommission]);
                }
            }
            if($og['parent3'] > 0){
                $parent3 = Db::name('member')->where('id', $og['parent3'])->find();
                if($parent3['bus_total'] >= $og['parent3commission'])
                {
                    // 扣除创业值
                    \app\admin\common\Member::addbusTotal($aid, $og['parent3'], $og['parent3'], '-'.$og['parent3commission'], '佣金扣除'.t('创业值'));
                } else{
                    // 把佣金改成0
                    $upcommission = $parent3['bus_total'];
                    \app\admin\common\Member::addbusTotal($aid, $og['parent3'], $og['parent3'], '-'.$upcommission, '佣金扣除'.t('创业值'));
                    Db::name('member_commission_record')->where('jl_type',1)->where('ogid', $og['id'])->where('mid', $og['parent3'])->update(['commission' => $upcommission]);
                }
            }
            // var_dump($parent1);die;
        }
    }

    	//去排队
    	foreach($oglist as $og)
    	{
		if(($og['paidui_jiang'] >0 || $og['paidui_give'] >0) && self::canUsePaiduiQueueTables())
    	    {
    	    	$type_status = 0;
    	        $type = 0;
    	       // if($og['bid'] >0)
    	       // {
    	            $buss = Db::name('paidui_maidan_set')->where('aid',aid)->where('bid',$og['bid'])->find();
    	            if(!empty($buss))
    	            {
          	            $type_status = $buss['type_status'];
          	            $type = $buss['type'];  
    	            }
    	       // }
    	        if($type_status == 1 || $og['bid'] == 0)
    	        {
    	           // $buss = Db::name('paidui_maidan_set')->where('aid',aid)->where('bid',0)->find();
        	        $paiduidata = [
        	            'aid'=>$og['aid'],
        	            'mid'=>$og['mid'],
        	            'bid'=>$og['bid'],
        	            'type_status'=>$type_status,
        	            'type'=>$type,
                    'pool_id'=>\app\admin\common\PaiduiPoolService::resolveProductPoolId($og['aid'], intval($og['proid'] ?? 0), [], $og['totalprice'] ?? 0, intval($og['bid'] ?? 0)),
        	            'orderid'=>$og['orderid'],
        	            'ordergoodid'=>$og['id'],
        	            'paidui_jiang'=>$og['paidui_jiang'],
        	            'paidui_give'=>$og['paidui_give'],
        	            'give_shengyu'=>$og['paidui_jiang'],
        	            'shiji_dedao'=>0,
        	            'createtime'=>time(),
        	            'status'=>0,
        	            ];

        	        $paiduiId = Db::name('paidui_list')->insertGetId($paiduidata);

        	        // 检查是否启用分期模式，如果启用则创建分期记录
        	        if($paiduiId && \app\admin\common\PaiduiPeriods::isPeriodsEnabled($og['aid'])) {
        	            $periods_result = \app\admin\common\PaiduiPeriods::createPeriods(
        	                $og['aid'], 
        	                $paiduiId, 
        	                $og['mid'], 
        	                $og['bid'], 
        	                $og['paidui_jiang']
        	            );
        	            
        	            if($periods_result['status'] == 1) {
        	                \think\facade\Log::write("排队分期创建成功: 排队ID={$paiduiId}, 分期数={$periods_result['periods']}, 总金额={$periods_result['total_amount']}");
        	            } else {
        	                \think\facade\Log::error("排队分期创建失败: 排队ID={$paiduiId}, 错误信息={$periods_result['msg']}");
        	            }
        	        }

        	        if($og['bid'] == 0)
        	        	$buss = Db::name('paidui_maidan_set')->where('aid',aid)->where('bid', 0)->find();

		if(!empty($buss) && intval($buss['model'] ?? 0) == 1){
        	            
        	        	\app\admin\common\Member::givepaiduijiangModel($order['aid'],$order['mid'],$paiduiId,$order['ordernum'], $buss);
        	        }
        	        else{
    	    	        //排队给奖励
    	    	      
    	    	        \app\admin\common\Member::givepaiduijiang($og['aid'],$og['mid'],$og['orderid'],$og['id'],$og['ordernum']);
    	    	    }
    	        }
    	    }
    	}

    // 逢单奖励时更新奖金数据
    if(getcustom('fengdanjiangli')){
        $oglist = Db::name('shop_order_goods')->where('orderid', $orderid)->select()->toArray();
        foreach($oglist as $og){
            $member = Db::name('member')->where('id', $order['mid'])->find();
            $newbus_total = $member['bus_total'];
            $product = Db::name('shop_product')->where('id', $og['proid'])->find();
            if($product['fengdanjiangli'] && $product['commissionset'] == 2){
                $commissiondata = json_decode($product['commissiondata2'],true);
                $fengdanjiangliArr = explode(',', $product['fengdanjiangli']);
                $num = $og['num'];
                $ogupdate = [];
                
                if($og['parent1']){
                    $parent1 = Db::name('member')->where('id', $og['parent1'])->find();
                    $agleveldata1 = Db::name('member_level')->where('aid', $aid)->where('id', $parent1['levelid'])->find();
                    $dannum = Db::name('shop_order_goods')->where('aid', $aid)->where('proid', $product['id'])->where('status','in','1,2,3')->where("parent1", $og['parent1'])->sum('num');

                    $ogupdate['parent1commission'] = 0;
                    for($i = 0; $i < $num; $i++){
                        $thisdannum = ($dannum + 1 + $i) % 10;
                        if(in_array($thisdannum.'', $fengdanjiangliArr)){
                            // PHP 8.0+ 类型转换：JSON数据必须显式转换
                            $ogupdate['parent1commission'] += floatval($commissiondata[$agleveldata1['id']]['commission1'] ?? 0);
                            $ogupdate['isfenhong'] = 2;
                        }
                    }
                    $ogupdate['fhparent2commission'] = 0;
                    $ogupdate['fhparent3commission'] = 0;
                    $ogupdate['fhparent4commission'] = 0;

                    if($parent1['pid'] > 0){
                        $parent2 = Db::name('member')->where('id', $parent1['pid'])->find();
                        if($parent2 && $ogupdate['parent1commission'] > 0){
                            $agleveldata2 = Db::name('member_level')->where('aid', $aid)->where('id', $parent2['levelid'])->find();
                            $ogupdate['fhparent2commission'] = $ogupdate['parent1commission'] * floatval($agleveldata2['fh_commission2'] ?? 0) / 100;
                            if($parent2['pid'] > 0){
                                $parent3 = Db::name('member')->where('id', $parent2['pid'])->find();
                                if($parent3 && $ogupdate['parent1commission'] > 0){
                                    $agleveldata3 = Db::name('member_level')->where('aid', $aid)->where('id', $parent3['levelid'])->find();
                                    $ogupdate['fhparent3commission'] = $ogupdate['parent1commission'] * floatval($agleveldata3['fh_commission3'] ?? 0) / 100;
                                }
                                if($parent3['pid'] > 0){
                                    $parent4 = Db::name('member')->where('id', $parent3['pid'])->find();
                                    if($parent4 && $ogupdate['parent1commission'] > 0){
                                        $agleveldata4 = Db::name('member_level')->where('aid', $aid)->where('id', $parent4['levelid'])->find();
                                        $ogupdate['fhparent4commission'] = $ogupdate['parent1commission'] * floatval($agleveldata4['fh_commission4'] ?? 0) / 100;
                                    }
                                }
                            }
                        }
                    }
                }
                if($og['parent2']){
                    $parent2 = Db::name('member')->where('id', $og['parent2'])->find();
                    $agleveldata2 = Db::name('member_level')->where('aid', $aid)->where('id', $parent2['levelid'])->find();
                    $dannum = Db::name('shop_order_goods')->where('aid', $aid)->where('proid', $product['id'])->where('status','in','1,2,3')->where("parent2", $og['parent2'])->sum('num');
                    $ogupdate['parent2commission'] = 0;
                    for($i = 0; $i < $num; $i++){
                        $thisdannum = ($dannum + 1 + $i) % 10;
                        if(in_array($thisdannum.'', $fengdanjiangliArr)){
                            // PHP 8.0+ 类型转换：JSON数据必须显式转换
                            $ogupdate['parent2commission'] += floatval($commissiondata[$agleveldata2['id']]['commission2'] ?? 0);
                            $ogupdate['isfenhong'] = 2;
                        }
                    }
                }
                if($og['parent3']){
                    $parent3 = Db::name('member')->where('id', $og['parent3'])->find();
                    $agleveldata3 = Db::name('member_level')->where('aid', $aid)->where('id', $parent3['levelid'])->find();
                    $dannum = Db::name('shop_order_goods')->where('aid', $aid)->where('proid', $product['id'])->where('status','in','1,2,3')->where("parent3", $og['parent3'])->sum('num');
                    $ogupdate['parent3commission'] = 0;
                    for($i = 0; $i < $num; $i++){
                        $thisdannum = ($dannum + 1 + $i) % 10;
                        if(in_array($thisdannum.'', $fengdanjiangliArr)){
                            // PHP 8.0+ 类型转换：JSON数据必须显式转换
                            $ogupdate['parent3commission'] += floatval($commissiondata[$agleveldata3['id']]['commission3'] ?? 0);
                            $ogupdate['isfenhong'] = 2;
                        }
                    }
                    if(!empty($parent3['pid'])){
                        $parent4 = Db::name('member')->where('id', $parent3['pid'])->find();
                        if($parent4 && $ogupdate['parent1commission'] > 0){
                            $ogupdate['fhparent4commission'] = $ogupdate['parent1commission'] * floatval($agleveldata4['fh_commission4'] ?? 0) / 100;
                        }
                    }
                }

                if($ogupdate){
                    $comzong = $ogupdate['parent1commission'] + $ogupdate['parent2commission'] + $ogupdate['parent3commission'];
                    if($comzong <= $newbus_total){
                        Db::name('shop_order_goods')->where('id', $og['id'])->update($ogupdate);
                        if($og['parent1'] && $ogupdate['parent1commission']){
                            Db::name('member_commission_record')->insert([
                                'aid' => $aid,
                                'mid' => $og['parent1'],
                                'frommid' => $og['mid'],
                                'orderid' => $orderid,
                                'ogid' => $og['id'],
                                'type' => 'shop',
                                'commission' => $ogupdate['parent1commission'],
                                'remark' => '下级购买商品奖励',
                                'createtime' => time()
                            ]);
                        }
                        if($og['parent2'] && $ogupdate['parent2commission']){
                            Db::name('member_commission_record')->insert([
                                'aid' => $aid,
                                'mid' => $og['parent2'],
                                'frommid' => $og['mid'],
                                'orderid' => $orderid,
                                'ogid' => $og['id'],
                                'type' => 'shop',
                                'commission' => $ogupdate['parent2commission'],
                                'remark' => '下二级购买商品奖励',
                                'createtime' => time()
                            ]);
                        }
                        if($og['parent3'] && $ogupdate['parent3commission']){
                            Db::name('member_commission_record')->insert([
                                'aid' => $aid,
                                'mid' => $og['parent3'],
                                'frommid' => $og['mid'],
                                'orderid' => $orderid,
                                'ogid' => $og['id'],
                                'type' => 'shop',
                                'commission' => $ogupdate['parent3commission'],
                                'remark' => '下三级购买商品奖励',
                                'createtime' => time()
                            ]);
                        }
                        if($parent2 && $ogupdate['fhparent2commission'] > 0){
                            Db::name('member_commission_record')->insert([
                                'aid' => $aid,
                                'jl_type' => 2,
                                'mid' => $parent2['id'],
                                'frommid' => $og['mid'],
                                'orderid' => $orderid,
                                'ogid' => $og['id'],
                                'type' => 'shop',
                                'commission' => $ogupdate['fhparent2commission'],
                                'remark' => '下一级收入分红',
                                'createtime' => time()
                            ]);
                        }
                        if($parent3 && $ogupdate['fhparent3commission'] > 0){
                            Db::name('member_commission_record')->insert([
                                'aid' => $aid,
                                'jl_type' => 2,
                                'mid' => $parent3['id'],
                                'frommid' => $og['mid'],
                                'orderid' => $orderid,
                                'ogid' => $og['id'],
                                'type' => 'shop',
                                'commission' => $ogupdate['fhparent3commission'],
                                'remark' => '下二级收入分红',
                                'createtime' => time()
                            ]);
                        }
                        if($parent4 && $ogupdate['fhparent4commission'] > 0){
                            Db::name('member_commission_record')->insert([
                                'aid' => $aid,
                                'jl_type' => 2,
                                'mid' => $parent4['id'],
                                'frommid' => $og['mid'],
                                'orderid' => $orderid,
                                'ogid' => $og['id'],
                                'type' => 'shop',
                                'commission' => $ogupdate['fhparent4commission'],
                                'remark' => '下三级收入分红',
                                'createtime' => time()
                            ]);
                        }
                        if($memberlevel['is_bus_bound_multiples'] == 1){
                            \app\admin\common\Member::addbusTotal($aid, $mid, $mid, '-'.$comzong, '奖励佣金扣除'.t('创业值'));
                        }
                    } else{
                        if($memberlevel['is_bus_bound_multiples'] == 0){
                            Db::name('shop_order_goods')->where('id', $og['id'])->update($ogupdate);
                            if($og['parent1'] && $ogupdate['parent1commission']){
                                Db::name('member_commission_record')->insert([
                                    'aid' => $aid,
                                    'mid' => $og['parent1'],
                                    'frommid' => $og['mid'],
                                    'orderid' => $orderid,
                                    'ogid' => $og['id'],
                                    'type' => 'shop',
                                    'commission' => $ogupdate['parent1commission'],
                                    'remark' => '下级购买商品奖励',
                                    'createtime' => time()
                                ]);
                            }
                            if($og['parent2'] && $ogupdate['parent2commission']){
                                Db::name('member_commission_record')->insert([
                                    'aid' => $aid,
                                    'mid' => $og['parent2'],
                                    'frommid' => $og['mid'],
                                    'orderid' => $orderid,
                                    'ogid' => $og['id'],
                                    'type' => 'shop',
                                    'commission' => $ogupdate['parent2commission'],
                                    'remark' => '下二级购买商品奖励',
                                    'createtime' => time()
                                ]);
                            }
                            if($og['parent3'] && $ogupdate['parent3commission']){
                                Db::name('member_commission_record')->insert([
                                    'aid' => $aid,
                                    'mid' => $og['parent3'],
                                    'frommid' => $og['mid'],
                                    'orderid' => $orderid,
                                    'ogid' => $og['id'],
                                    'type' => 'shop',
                                    'commission' => $ogupdate['parent3commission'],
                                    'remark' => '下三级购买商品奖励',
                                    'createtime' => time()
                                ]);
                            }
                            if($parent2 && $ogupdate['fhparent2commission'] > 0){
                                Db::name('member_commission_record')->insert([
                                    'aid' => $aid,
                                    'jl_type' => 2,
                                    'mid' => $parent2['id'],
                                    'frommid' => $og['mid'],
                                    'orderid' => $orderid,
                                    'ogid' => $og['id'],
                                    'type' => 'shop',
                                    'commission' => $ogupdate['fhparent2commission'],
                                    'remark' => '下一级收入分红',
                                    'createtime' => time()
                                ]);
                            }
                            if($parent3 && $ogupdate['fhparent3commission'] > 0){
                                Db::name('member_commission_record')->insert([
                                    'aid' => $aid,
                                    'jl_type' => 2,
                                    'mid' => $parent3['id'],
                                    'frommid' => $og['mid'],
                                    'orderid' => $orderid,
                                    'ogid' => $og['id'],
                                    'type' => 'shop',
                                    'commission' => $ogupdate['fhparent3commission'],
                                    'remark' => '下二级收入分红',
                                    'createtime' => time()
                                ]);
                            }
                            if($parent4 && $ogupdate['fhparent4commission'] > 0){
                                Db::name('member_commission_record')->insert([
                                    'aid' => $aid,
                                    'jl_type' => 2,
                                    'mid' => $parent4['id'],
                                    'frommid' => $og['mid'],
                                    'orderid' => $orderid,
                                    'ogid' => $og['id'],
                                    'type' => 'shop',
                                    'commission' => $ogupdate['fhparent4commission'],
                                    'remark' => '下三级收入分红',
                                    'createtime' => time()
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    if(getcustom('to86yk')){
        $oglist = Db::name('shop_order_goods')->where('orderid', $orderid)->select()->toArray();
        foreach($oglist as $og){
            if($og['to86yk_tid']){
                $shopset = Db::name('shop_sysset')->where('aid', $aid)->find();
                $formdata = \app\admin\model\Freight::getformdata($orderid, 'shop_order');
                $successnum = 0;
                for($i = 0; $i < $og['num']; $i++){
                    $url = 'http://api.yukawl.cn/api.php?act=pay&tid='.$og['to86yk_tid'].'&input1='.$formdata[0][1].'&user='.$shopset['to86yk_user'].'&pass='.$shopset['to86yk_pwd'];
                    $rs = request_get($url);
                    // \think\facade\Log::write($url);
                 
                    $rs = json_decode($rs, true);
                  
                    if($rs && $rs['code'] == 0) $successnum++;
                }
                Db::name('shop_order_goods')->where('id', $og['id'])->update(['to86yk_successnum' => $successnum]);
            }
        }
    }

    // 处理BPV分红
    try {
        // 调用BpvProfit类处理订单分红
        // 调用BPV插件处理订单分红
        if (class_exists('\app\plugin\bpv\common\BpvProfit')) {
            \app\plugin\bpv\common\BpvProfit::handleOrderProfit($aid, $orderid, $order['ordernum'], $order['totalprice']);
        }
    } catch (\Exception $e) {
        // 记录异常，但不影响订单正常流程
        \think\facade\Log::write('BPV分红处理异常：'.$e->getMessage(), 'error');
    }

    // 处理HGB增长币结算
    try {
        if (class_exists('\app\plugin\hgb\common\HgbService')) {
            \app\plugin\hgb\common\HgbService::processShopOrder($aid, $orderid, $order['ordernum']);
        }
    } catch (\Exception $e) {
        \think\facade\Log::write('HGB订单结算异常：' . $e->getMessage(), 'error');
    }

    // 处理单数奖第三单业绩排除逻辑
    try {
        \think\facade\Log::write('开始处理单数奖第三单业绩排除-订单ID:'.$orderid.'-用户ID:'.$mid.'-应用ID:'.$aid, 'info');
        
        // 检查是否有启用的单数奖活动需要排除第三单业绩
        $danshujiangActivities = Db::name('danshujiang_list')
            ->where('aid', $aid)
            ->where('reward_mode', 3) // 循环模式
            ->where('third_order_no_performance', 1) // 开启第三单不计算业绩
            ->select()
            ->toArray();

        \think\facade\Log::write('查询到的单数奖活动数量:'.count($danshujiangActivities), 'info');

        if (!empty($danshujiangActivities)) {
            foreach ($danshujiangActivities as $activity) {
                \think\facade\Log::write('处理活动-活动ID:'.$activity['id'].'-循环数:'.$activity['cycle_num'].'-奖励类型:'.$activity['reward_type'], 'info');
                
                // 当前订单必须包含活动适用商品，否则不应触发“循环第三单不计算业绩”
                // 否则当用户历史单数停留在第三单位置时，后续非活动商品订单也会被误标记为不计算业绩
                $allowedProductIds = [];
                if (!empty($activity['product_ids'])) {
                    $allowedProductIds = array_values(array_filter(explode(',', $activity['product_ids'])));
                }
                if (empty($allowedProductIds)) {
                    \think\facade\Log::write('活动未配置适用商品，跳过-活动ID:'.$activity['id'], 'info');
                    continue;
                }

                // 根据奖励类型确定需要检查的用户ID和计算方式
                $userOrderCount = 0;
                if ($activity['reward_type'] == 1) {
                    // 当前订单未包含活动适用商品，跳过
                    $hasAllowedGoods = Db::name('shop_order_goods')
                        ->where('aid', $aid)
                        ->where('orderid', $orderid)
                        ->whereIn('proid', $allowedProductIds)
                        ->count();
                    if (empty($hasAllowedGoods)) {
                        \think\facade\Log::write('当前订单不包含活动适用商品，跳过-订单ID:'.$orderid.'-活动ID:'.$activity['id'], 'info');
                        continue;
                    }

                    // 按自己下单：统计当前用户自己的订单数量
                    $checkUserId = $mid;
                    $userOrderCount = \app\admin\controller\ApiDanshujiang::getUserOrderCount($aid, $checkUserId, $activity);
                    \think\facade\Log::write('按自己下单模式-当前下单用户:'.$mid.'-订单数量:'.$userOrderCount, 'info');
                } else {
                    // 按直推人下单：需要找到当前订单的推荐人，然后统计推荐人的直推人员订单数量
                    // 首先从订单商品表中获取推荐人ID（仅基于活动适用商品）
                    $parent1Id = Db::name('shop_order_goods')
                        ->where('aid', $aid)
                        ->where('orderid', $orderid)
                        ->whereIn('proid', $allowedProductIds)
                        ->where('parent1', '>', 0)
                        ->value('parent1');
                        
                    if ($parent1Id > 0) {
                        \think\facade\Log::write('按直推人下单模式-当前下单用户:'.$mid.'-推荐人ID:'.$parent1Id, 'info');
                        
                        // 使用ApiDanshujiang的正确方法计算订单数量
                        $userOrderCount = \app\admin\controller\ApiDanshujiang::getUserOrderCount($aid, $parent1Id, $activity);
                        \think\facade\Log::write('按直推人下单-推荐人ID:'.$parent1Id.'-直推人员订单总数:'.$userOrderCount, 'info');
                    } else {
                        \think\facade\Log::write('按直推人下单模式-订单无推荐人或不包含活动适用商品，跳过-订单ID:'.$orderid.'-活动ID:'.$activity['id'], 'info');
                        continue; // 没有推荐人或订单不参与该活动，跳过这个活动
                    }
                }
                
                \think\facade\Log::write('最终用户订单数量:'.$userOrderCount.'-活动ID:'.$activity['id'], 'info');
                
                // 检查是否为循环中的第三单
                $cycleNum = $activity['cycle_num'];
                if ($cycleNum >= 3 && $userOrderCount > 0) {
                    $currentPosition = ($userOrderCount - 1) % $cycleNum;
                    
                    \think\facade\Log::write('循环计算-订单数:'.$userOrderCount.'-循环数:'.$cycleNum.'-循环位置:'.$currentPosition, 'info');
                    
                    if ($currentPosition == 2) { // 循环中的第三单
                        // 标记该订单的商品为不计算业绩，并记录原因
                        $updateResult = Db::name('shop_order_goods')
                            ->where('aid', $aid)
                            ->where('orderid', $orderid)
                            ->update([
                                'is_yeji' => 0,
                                'no_yeji_reason' => '单数奖循环第三单不计算业绩'
                            ]);
                        
                        \think\facade\Log::write('单数奖循环第三单业绩排除-订单ID:'.$orderid.'-活动ID:'.$activity['id'].'-用户订单数:'.$userOrderCount.'-循环位置:'.$currentPosition.'-更新行数:'.$updateResult, 'info');
                        break; // 只需要一个活动匹配即可
                    } else {
                        \think\facade\Log::write('不是第三单，跳过-订单ID:'.$orderid.'-循环位置:'.$currentPosition, 'info');
                    }
                } else {
                    \think\facade\Log::write('循环数小于3或订单数量为0，跳过-活动ID:'.$activity['id'].'-循环数:'.$cycleNum.'-订单数:'.$userOrderCount, 'info');
                }
            }
        } else {
            \think\facade\Log::write('没有找到符合条件的单数奖活动', 'info');
        }
    } catch (\Exception $e) {
        // 记录异常，但不影响订单正常流程
        \think\facade\Log::write('单数奖第三单业绩排除处理异常：'.$e->getMessage(), 'error');
    }

    Db::name('shop_order_goods')->where('orderid', $orderid)->update(['status' => 1]);

    if($order['fromwxvideo'] == 1){
        if($order['paytypeid'] != 60){ // 不是视频号微信支付的
            Db::name('shop_order')->where('id', $orderid)->update(['fromwxvideo' => 0]);
            $order['fromwxvideo'] = 0;
            // \app\admin\common\Wxvideo::createorder($orderid);
        } else{
            \app\admin\common\Wxvideo::orderpay($orderid);
        }
    }

    // 判断是否开启云库存,开启云库存的,查我的等级可不可以存货,添加我的云库存,然后订单完成
    $ykcrs = self::tableExists('yunkucun_sysset') ? Db::name('yunkucun_sysset')->where('aid', $aid)->find() : null;
    if(!empty($ykcrs))
    {
        if($ykcrs['status'] == 1)
        {
            if($ykcrs['zouhuo'] == 1 && $member['pid'] > 0 && $order['ti'] == 3)
            {
                $giveArr = json_decode($order['nahuoArr'],1);
                $oglist = Db::name('shop_order_goods')->where('orderid', $orderid)->select()->toArray();
                $goodssttaus = \app\admin\common\Member::isyunproduct($aid, $oglist[0]['proid']);
                if($goodssttaus){
                    foreach($oglist as $og){
                        $product = Db::name('shop_product')->where('id', $og['proid'])->find();
                        $guige = Db::name('shop_guige')->where('id', $og['ggid'])->find();
                        if(!empty($giveArr) && count($giveArr) > 1 && $product['kuncun_type'] != 1)
                        {
                            $keys = array_keys($giveArr);
                            $keynum = 0;
                            $num = $og['num'];
                            foreach($giveArr as $kk1 => $vv1)
                            {
                                if($kk1)
                                {
                                    if($order['nahuoid'] != $kk1)
                                    {
                                        $curmembernahuo = Db::name('member')->where('aid', $aid)->where('id', $kk1)->find();
                                        $curmemberlevelnahuo = Db::name('member_level')->where('aid', $aid)->where('id', $curmembernahuo['levelid'])->find();
                                        
                                        // 计算我的拿货价格
                                        if(empty($guige['lvprice_data']))
                                        {
                                            $nahuoprice = $guige['sell_price'];
                                        } else{
                                            $lvprice_data = json_decode($guige['lvprice_data'],1);
                                            $nahuoprice = $lvprice_data[$curmembernahuo['levelid']];
                                        }
                                        if($curmemberlevelnahuo['commissiontype'] == 1)
                                        {
                                            $commission1nahuo = $curmemberlevelnahuo['commission1'];
                                        } else{
                                            $commission1nahuo = $nahuoprice * $num * $curmemberlevelnahuo['commission1'] * 0.01; 
                                        }
                                        $nahuodata = [
                                            'orderid' => $orderid,
                                            'aid' => $aid,
                                            'mid' => $kk1,
                                            'nahuoid' => isset($keys[$keynum + 1]) ? $keys[$keynum + 1] : 0,
                                            'proid' => $product['id'],
                                            'ggid' => $guige['id'],
                                            'proname' => $product['name'],
                                            'ggname' => $guige['name'],
                                            'pic' => $product['pic'],
                                            'num' => $num,
                                            'price' => $nahuoprice * $num,
                                            'pid' => $curmembernahuo['pid'],
                                            'pidprice' => $commission1nahuo,
                                            'ylevelid' => $curmembernahuo['levelid'],
                                            'createtime' => time(),
                                        ];
                                        Db::name('yun_nahuo')->insert($nahuodata);
                                        // 给直推奖和培养奖
                                        Db::name('member_commission_record')->insert([
                                            'aid' => $aid,
                                            'mid' => $curmembernahuo['pid'],
                                            'frommid' => $kk1,
                                            'orderid' => $orderid,
                                            'ogid' => 0,
                                            'type' => 'shop',
                                            'commission' => $commission1nahuo,
                                            'remark' => '用户:'.$kk1.'补货-下级购买商品奖励,订单编号:'.$order['ordernum'],
                                            'createtime' => time()
                                        ]);
                                        \app\admin\common\Member::givepeiyang($aid, $nahuoprice * $num, $kk1, $orderid, $curmembernahuo['levelid'], 1);
                                        $keynum = $keynum + 1;
                                    } else{
                                        break;
                                    }
                                }
                            }
                        }
                    } 
                    if($member['pid'] > 0 && $order['ti'] == 2 && $ykcrs['zouhuo'] == 0)
                    {
                        if($order['zouhuoshouxu'] == 0)
                        {
                            $shouxufei = 0;
                            $totalprice_daozhang = $order['totalprice'];
                        } else{
                            $shouxufei = $order['totalprice'] * $order['zouhuoshouxu'] * 0.01; 
                            $totalprice_daozhang = $order['totalprice'] - $shouxufei;
                        }
                         
                        \app\admin\common\Member::addcommission($aid, $member['pid'], $mid, $totalprice_daozhang, '下级下单我的云库存商品,订单编号:'.$order['ordernum'].',应到账:'.$order['totalprice'].',实际到账:'.$totalprice_daozhang.',扣除手续费:'.$shouxufei);
                    } elseif($ykcrs['zouhuo'] == 1 && $member['pid'] > 0 && $order['ti'] == 3)
                    {
                        $giveArr = json_decode($order['nahuoArr'],1);
                        $keys = array_keys($giveArr);
                        $keynums = 0;
                        foreach ($giveArr as $k => $v)
                        {
                            $peiyang = 0;
                            // 新改
                            if($keynums == 0)
                            {
                                // 查订单的
                                $zhitui = 0 + Db::name('shop_order_goods')->where('aid', $aid)->where('mid', $order['mid'])->where('orderid', $orderid)->sum('parent1commission');
                                foreach($oglist as $og){
                                    $product = Db::name('shop_product')->where('id', $og['proid'])->where('aid', $aid)->find();
                                    if($product['kuncun_type'] != 1)
                                    {
                                        $peiyangog = \app\admin\common\Member::givepeiyang2($aid, $og['sell_price'], $order['mid'], $order['id'], $order['ylevelid'], 1);
                                        $peiyang = $peiyang + $peiyangog;
                                    }
                                }
                            } else{
                                // 查补货的
                                $xiajiid = $keys[$keynums - 1];
                                $zhitui = 0 + Db::name('yun_nahuo')->where('aid', $aid)->where('nahuoid', $k)->where('orderid', $orderid)->sum('pidprice');
                                $buhuoorder = Db::name('yun_nahuo')->where('aid', $aid)->where('nahuoid', $k)->where('orderid', $orderid)->find();
                                $buhuoorder2  =  Db::name('yun_nahuo')->where('aid', $aid)->where('nahuoid', $k)->where('orderid', $orderid)->select()->toArray();
                                foreach($buhuoorder2 as $buhuoorder){
                                    $peiyangog = \app\admin\common\Member::givepeiyang2($aid, $buhuoorder['price'], $xiajiid, $order['id'], $buhuoorder['ylevelid'], 1);
                                    $peiyang = $peiyang + $peiyangog;
                                }
                            }
                            $keynums = $keynums + 1;
                            // 新改结束
                            $tuijian = $peiyang + $zhitui;
                            $yingdao = $v - $tuijian;
                            // 给佣金
                            if($yingdao > 0){
                                \app\admin\common\Member::addcommission($aid, $k, $mid, $yingdao, '下级下单云库存商品,订单编号:'.$order['ordernum'].',应到账:'.$v.',实际到账:'.$yingdao.',扣除培养奖和推荐奖:'.$tuijian);
                            }
                        }
                    }
                }
                // 给我的上级返钱
                \app\admin\common\Member::givepeiyang($aid, $order['totalprice'], $mid, $orderid, $member['levelid']);
            }
            $goodssttaus = \app\admin\common\Member::isyunproduct($aid, $oglist[0]['proid']);
            $levelArr = Db::name('member_level')->where('aid', $aid)->where('id', $member['levelid'])->find();
            if($levelArr['can_cunhuo'] == 1 && $goodssttaus)
            {
                // 添加我的云库存 ddwx_users_yun
                foreach($oglist as $ordergoods)
                {
                    \app\admin\common\Member::yunkucunlog($aid, $mid, $ordergoods['proid'], $ordergoods['ggid'], $ordergoods['num'], $ordergoods, '用户自己下单购买');
                    // 添加我的当前商品捆绑商品
                    // $product = Db::name('shop_product')->where('id', $ordergoods['proid'])->find();
                    // if(!empty($product['dddata']))
                    // {
                    //     $dddata = json_decode($product['dddata'],1);
                    //     foreach ($dddata as $kgoods => $vgoods)
                    //     {
                    //         $productgood = Db::name('shop_product')->where('id', $kgoods)->find();
                    //         $gg = Db::name('shop_guige')->where('id', $vgoods['ggid'])->find();
                    //         $ordergoods2 =[
                    //             'aid' => $ordergoods['aid'],
                    //             'bid' => 0,
                    //             'mid' => $mid,
                    //             'proid' => $kgoods,
                    //             'ggid' => $vgoods['ggid'],
                    //             'ggname' => $gg['name'],
                    //             'name' => $productgood['name'],
                    //             'pic' => $productgood['pic'],
                    //             'procode' => '',
                    //             'num' => $vgoods['num'] * $ordergoods['num']
                    //         ];
                    //         $num1 = $vgoods['num'] * $ordergoods['num'];
                    //         \app\admin\common\Member::yunkucunlog($aid, $mid, $kgoods, $vgoods['ggid'], $num1, $ordergoods2, '用户自己下单组合商品购买');
                    //     }
                    // }
                }
                // 然后订单完成
                \app\admin\common\Order::collect($order);
                Db::name('shop_order')->where('id', $orderid)->update(['status' => 3, 'collect_time' => time(), 'send_time' => time()]);
                Db::name('shop_order_goods')->where('orderid', $orderid)->update(['status' => 3, 'endtime' => time()]);
                
                // 触发订单完成的缓存更新
                try {
                    \app\common\OrderTrigger::onOrderCompleted($orderid, $order);
                } catch (\Exception $e) {
                    \think\facade\Log::error('订单完成触发器调用异常：' . $e->getMessage());
                }
            }
        }
    }

    if ($order['paytypeid'] == 41) { // 月结订单：支付成功后记账欠费
        $yuejieRemark = '订单号: ' . $order['ordernum'] . ' 月结支付';
        // 月结中心插件已安装则走插件记账（保留可扩展性），未安装时回退核心实现
        if (class_exists('\\app\\plugin\\yuejie\\common\\YuejieMember')) {
            \app\plugin\yuejie\common\YuejieMember::addarrears($aid, $order['mid'], $order['totalprice'], $yuejieRemark);
        } else {
            \app\admin\common\Member::addarrears($aid, $order['mid'], $order['totalprice'], $yuejieRemark);
        }
    }

    // 自动发货
    if($order['freight_type'] == 3){
        $og = Db::name('shop_order_goods')->where('orderid', $order['id'])->find();
        $freight_content = Db::name('shop_product')->where('id', $og['proid'])->value('freightcontent');
        Db::name('shop_order')->where('id', $order['id'])->update(['freight_content' => $freight_content, 'status' => 2, 'send_time' => time()]);
        Db::name('shop_order_goods')->where('orderid', $order['id'])->update(['status' => 2]);
        
        // 触发订单完成的缓存更新
        try {
            \app\common\OrderTrigger::onOrderCompleted($order['id'], $order);
        } catch (\Exception $e) {
            \think\facade\Log::error('订单完成触发器调用异常：' . $e->getMessage());
        }

        if($order['fromwxvideo'] == 1){
            \app\admin\common\Wxvideo::deliverysend($orderid);
        }
        // 发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'], $order);
        }
        if(getcustom('plug_zhiming')){
            \app\admin\common\Order::collect($order);
            Db::name('shop_order')->where('id', $orderid)->update(['status' => 3, 'collect_time' => time()]);
            Db::name('shop_order_goods')->where('orderid', $orderid)->update(['status' => 3, 'endtime' => time()]);
        }
    }
    
    // 在线卡密
    if($order['freight_type'] == 4){
        $og = Db::name('shop_order_goods')->where('orderid', $order['id'])->find();
        $codelist = Db::name('shop_codelist')->where('proid', $og['proid'])->where('status', 0)->order('id')->limit($og['num'])->select()->toArray();
        if($codelist && count($codelist) >= $og['num']){
            $pscontent = [];
            foreach($codelist as $codeinfo){
                $pscontent[] = $codeinfo['content'];
                Db::name('shop_codelist')->where('id', $codeinfo['id'])->update([
                    'orderid' => $order['id'],
                    'ordernum' => $order['ordernum'],
                    'headimg' => $member['headimg'],
                    'nickname' => $member['nickname'],
                    'buytime' => time(),
                    'status' => 1
                ]);
            }
            $pscontent = implode("\r\n", $pscontent);
                    Db::name('shop_order')->where('id', $order['id'])->update(['freight_content' => $pscontent, 'status' => 2, 'send_time' => time()]);
        Db::name('shop_order_goods')->where('orderid', $order['id'])->update(['status' => 2]);
        
        // 触发订单完成的缓存更新
        try {
            \app\common\OrderTrigger::onOrderCompleted($order['id'], $order);
        } catch (\Exception $e) {
            \think\facade\Log::error('订单完成触发器调用异常：' . $e->getMessage());
        }
        }
        if($order['fromwxvideo'] == 1){
            \app\admin\common\Wxvideo::deliverysend($orderid);
        }

        // 发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'], $order);
        }
        if(getcustom('plug_zhiming')){
            \app\admin\common\Order::collect($order);
            Db::name('shop_order')->where('id', $orderid)->update(['status' => 3, 'collect_time' => time()]);
            Db::name('shop_order_goods')->where('orderid', $orderid)->update(['status' => 3, 'endtime' => time()]);
        }
    }
    
    if(getcustom('huawa') && $aid == 1){ // 定制1 订单对接 同步到花娃
        \app\admin\custom\Huawa::api($order);
    }
    if(getcustom('cefang') && $aid == 2){ // 定制2 订单对接 同步到策方
        \app\admin\custom\Cefang::api($order);
    }

    if(getcustom('hmy_yuyue')){  // 红蚂蚁定制同步到跑腿订单
        \app\admin\custom\Yuyue::api($order);
    }

    // 支付后送券
    $couponlist = \app\admin\common\Coupon::getpaygive($aid, $mid, 'shop', $order['totalprice'], $order['id']);
    if($couponlist){
        $proids = db('shop_order_goods')->where('orderid', '=', $order['id'])->column('proid');
        foreach($couponlist as $coupon){
            if($coupon['paygive'] == 1 && $coupon['paygive_minprice'] <= $order['totalprice'] && $coupon['paygive_maxprice'] >= $order['totalprice'] && in_array('shop', explode(',', $coupon['paygive_scene']))){
                \app\admin\common\Coupon::send($aid, $mid, $coupon['id']);
            }
            if($coupon['buyprogive'] == 1){
                $coupon['buyproids'] = explode(',', $coupon['buyproids']);
                $coupon['buypro_give_num'] = explode(',', $coupon['buypro_give_num']);
                foreach($coupon['buyproids'] as $k => $proid) {
                    if(in_array($proid, $proids) && $coupon['buypro_give_num'][$k] > 0) {
                        for($i = 0; $i < $coupon['buypro_give_num'][$k]; $i++) {
                            \app\admin\common\Coupon::send($aid, $mid, $coupon['id']);
                        }
                    }
                }
            }
        }
    }
    
    // 送积分
    if($order['givescore2'] > 0){
        \app\admin\common\Member::addscore($aid, $order['mid'], $order['givescore2'], '购买产品赠送'.t('积分'));
    }
     
    // 处理满额赠送的资产奖励
    if (isset($order['give_assets']) && !empty($order['give_assets'])) {
        // 解析JSON数据
       // var_dump($order['give_assets']);die;
        $give_assets = is_array($order['give_assets']) ? $order['give_assets'] : json_decode($order['give_assets'], true);
        
        // 处理各种资产赠送
        if (isset($give_assets['jifen']) && $give_assets['jifen'] > 0) {
            \app\admin\common\Member::addscore($aid, $order['mid'], $give_assets['jifen'], '满额赠送'.t('积分'));
        }
        
        if (isset($give_assets['money']) && $give_assets['money'] > 0) {
            \app\admin\common\Member::addmoney($aid, $order['mid'], $give_assets['money'], '满额赠送'.t('余额'));
        }
        
        if (isset($give_assets['commission']) && $give_assets['commission'] > 0) {
            \app\admin\common\Member::addcommission($aid, $order['mid'], 0, $give_assets['commission'], '满额赠送'.t('佣金'));
        }
        
        if (isset($give_assets['contribution']) && $give_assets['contribution'] > 0) {
            \app\admin\common\Member::addgongxianzhi($aid, $order['mid'], $give_assets['contribution'], '满额赠送'.t('贡献值'));
        }
        
        if (isset($give_assets['yellow_points']) && $give_assets['yellow_points'] > 0) {
            \app\admin\common\Member::addscorehuang($aid, $order['mid'], $give_assets['yellow_points'], '满额赠送'.t('黄积分'));
        }
        
        if (isset($give_assets['cash_coupon']) && $give_assets['cash_coupon'] > 0) {
            \app\admin\common\Member::addhei($aid, $order['mid'], $give_assets['cash_coupon'], '满额赠送'.t('现金券'));
        }
        
        // 记录日志
     
        
    }

    $ress = Db::name('shop_order_goods')->where('orderid', $orderid)->select()->toArray();
    // 送积分2  $order
    $jifenchiAvailable = \app\admin\common\PluginBusinessAuth::isAidPluginReady(
        intval($aid),
        'jifenchi',
        ['jifechi', 'member_scoreloga', 'member_scorelogb']
    );
    $jifen2info = $jifenchiAvailable ? Db::name('jifechi')->where('aid', $aid)->find() : null;
    if($jifen2info && intval($jifen2info['jfc_status']) == 1){
        $jing = 0;
        $dong = 0;
        $totalprice = 0;
        foreach($ress as $v)
        {
            $product = Db::name('shop_product')->where('id', $v['proid'])->field('id,zeng_jingtai,zeng_dongtai')->find();
            if($product['zeng_jingtai'] > 0 || $product['zeng_dongtai'] > 0)
            {
                $jing = $jing + $v['totalprice'] * $product['zeng_jingtai'] * 0.01;
                $dong = $dong + $v['totalprice'] * $product['zeng_dongtai'] * 0.01;
            } elseif($jifen2info['fanbeishu'] > 0){
                $totalprice = $totalprice + $v['totalprice'];
            }
        }
        // 我的下单金额大于等于设置的消费金额 返积分
        if($totalprice > 0 && $totalprice >= $jifen2info['xiaofeimoney'])
        {
            $jifen2 = $totalprice * $jifen2info['fanbeishu']; // 要返的积分
            $jingA = $jifen2 * $jifen2info['baifenbi'] / 100;
            $jingB = $jifen2 * (100 - $jifen2info['baifenbi']) / 100;
            \app\admin\common\Member::addscoreA($aid, $order['mid'], $jingA, '购买产品满'.$jifen2info['xiaofeimoney'].'赠送'.$jifen2info['fanbeishu'].'倍积分的'.$jifen2info['baifenbi'].'%的静态积分');
            \app\admin\common\Member::addscoreB($aid, $order['mid'], $jingB, '购买产品满'.$jifen2info['xiaofeimoney'].'赠送'.$jifen2info['fanbeishu'].'倍积分的'.(100 - $jifen2info['baifenbi']).'%的动态积分');
        }
        if($jing > 0)
        {
           \app\admin\common\Member::addscoreA($aid, $order['mid'], $jing, '购买产品赠送静态积分'); 
        }
        if($dong > 0)
        {
            \app\admin\common\Member::addscoreB($aid, $order['mid'], $dong, '购买产品送动态积分');
            \app\admin\common\Member::addshengjiscore($aid, $order['mid'], $dong, '购买产品送升级积分');
        }
        // 加速释放动态积分
        $xiadanmember = Db::name('member')->where('aid', $aid)->where('id', $order['mid'])->field('id,pid')->find(); // 下单用户信息  
        if($xiadanmember['pid'] > 0)
        {
            $jiazhimember = Db::name('member')->where('aid', $aid)->where('id', $xiadanmember['pid'])->field('id,pid,scoreB')->find(); // 直接加速用户信息  
            // 直接返
            $jiasumoney1 = $order['totalprice'] * $jifen2info['zhiB'] / 100;
            $jiasumoney1 = round($jiasumoney1, 2);
            $jiasumoney123 = 0;
            $jiasumoney123_hei = 0;
            if($jiazhimember['scoreB'] >= $jiasumoney1)
            {
                $jiasumoney12 = $jiasumoney1;
            } else{
                $jiasumoney12 = $jiazhimember['scoreB'];
            }
            if($jifen2info['dongtaiB_money'] > 0)
            {
                $jiasumoney123 = round($jiasumoney12 * $jifen2info['dongtaiB_money'] * 0.01, 2);
            }
            if($jifen2info['dongtaiB_hei'] > 0)
            {
                $jiasumoney123_hei = round($jiasumoney12 * $jifen2info['dongtaiB_hei'] * 0.01, 2);
            }
            // var_dump($jiasumoney123);die;
            // var_dump($jiasumoney123_hei);die;
            $day = date('Y-m-d'); // 添加日期变量定义
            $res1 = \app\admin\common\Member::addscoreB($aid, $jiazhimember['id'], '-'.$jiasumoney12, $day.'分享加速释放减去动态积分:'.$jiasumoney12);
            if($jiasumoney123 > 0 ){
                \app\admin\common\Member::addmoney($aid, $jiazhimember['id'], $jiasumoney123, $day.'分享加速释放加入金额'.$jiasumoney123);
            }
            if($jiasumoney123_hei > 0 ){
                \app\admin\common\Member::addhei($aid, $jiazhimember['id'], $jiasumoney123_hei, $day.'分享加速释放加入'.t('现金券').$jiasumoney123_hei);
            }
            if($jiazhimember['pid'] > 0)
            {
                $jiajianjiemember = Db::name('member')->where('aid', $aid)->where('id', $jiazhimember['pid'])->field('id,pid,scoreB')->find(); // 间接加速用户信息
                // 间接返
                $jiasumoney2 = $order['totalprice'] * $jifen2info['jiaB'] / 100;
                $jiasumoney2 = round($jiasumoney2, 2);
                $jiasumoney21 = 0;
                $jiasumoney21_hei = 0;
                if($jiajianjiemember['scoreB'] >= $jiasumoney2)
                {
                    $jiasumoney22 = $jiasumoney2;
                } else{
                    $jiasumoney22 = $jiazhimember['scoreB'];
                }
                if($jifen2info['dongtaiB_money'] > 0)
                {
                    $jiasumoney21 = round($jiasumoney22 * $jifen2info['dongtaiB_money'] * 0.01, 2);
                }
                if($jifen2info['dongtaiB_hei'] > 0)
                {
                    $jiasumoney21_hei = round($jiasumoney22 * $jifen2info['dongtaiB_hei'] * 0.01, 2);
                }
                $res2 = \app\admin\common\Member::addscoreB($aid, $jiajianjiemember['pid'], '-'.$jiasumoney22, $day.'分销加速释放减去动态积分:'.$jiasumoney22);
                if($jiasumoney21 > 0 ){
                    \app\admin\common\Member::addmoney($aid, $jiajianjiemember['pid'], $jiasumoney21, $day.'分销加速释放加入金额'.$jiasumoney21);
                }
                if($jiasumoney21_hei > 0 ){
                    \app\admin\common\Member::addhei($aid, $jiajianjiemember['pid'], $jiasumoney21_hei, $day.'分销加速释放加入'.t('现金券').$jiasumoney21_hei);
                }
            }
        }
    }

    // 这里修改用户的星级
    $jiuxingCoreAvailable = \app\admin\common\PluginBusinessAuth::isAidPluginReady(
        intval($aid),
        'jiuxingcore',
        ['jifechi']
    );
    $jiuxingRow = $jiuxingCoreAvailable ? Db::name('jifechi')->where('aid', $aid)->find() : null;
    $jiuxingsetinfo = [];
    if($jiuxingRow && !empty($jiuxingRow['jiuxing'])) {
        $jiuxingsetinfo = json_decode($jiuxingRow['jiuxing'],1);
        if(!is_array($jiuxingsetinfo)) {
            $jiuxingsetinfo = [];
        }
    }
    if(($jiuxingsetinfo['jiuxing_status'] ?? 0) == 1)
    {
        $counttotalprice = 0;
        $oldxingji = Db::name('member')->where('aid', $aid)->where('id', $mid)->field('xingji')->find();
        $oldxingji = $oldxingji['xingji'];
        
        // 获取升级计算基准
        $upgradeBase = $jiuxingsetinfo['upgrade_base'] ?? 'order_amount';
        
        // 查询所有已付款订单（用于星级计算）
        \think\facade\Log::write("=== 累计消费计算开始 ===");
        \think\facade\Log::write("升级计算基准: " . $upgradeBase);
        
        $ress1 = Db::name('shop_order_goods')->where('aid', $aid)->where('mid', $mid)->where('status','in','1,2,3')->select()->toArray();
        \think\facade\Log::write("查询到订单商品数量: " . count($ress1));
        
        foreach($ress1 as $ordergoods){
            $product = Db::name('shop_product')->where('id', $ordergoods['proid'])->find(); 
            \think\facade\Log::write("商品ID: " . $ordergoods['proid'] . ", 是否业绩商品: " . ($product['is_yeji'] ?? 0) . ", 订单金额: " . $ordergoods['totalprice']);
            
            if($product['is_yeji'] == 1)
            {
                // 根据不同基准计算金额
                switch($upgradeBase) {
                    case 'cost':
                        // 按成本计算：成本价 × 数量
                        $amount = ($product['cost_price'] ?? 0) * $ordergoods['num'];
                        \think\facade\Log::write("按成本计算: " . ($product['cost_price'] ?? 0) . " * " . $ordergoods['num'] . " = " . $amount);
                        break;
                    case 'profit':
                        // 按利润计算：(售价 - 成本价) × 数量
                        $costPrice = $product['cost_price'] ?? 0;
                        $sellPrice = $ordergoods['price'];
                        $profit = ($sellPrice - $costPrice) * $ordergoods['num'];
                        $amount = max(0, $profit); // 利润不能为负数
                        \think\facade\Log::write("按利润计算: (" . $sellPrice . " - " . $costPrice . ") * " . $ordergoods['num'] . " = " . $amount);
                        break;
                    case 'order_amount':
                    default:
                        // 按下单金额计算（默认）
                        $amount = $ordergoods['totalprice'];
                        \think\facade\Log::write("按下单金额计算: " . $amount);
                        break;
                }
                $counttotalprice = $counttotalprice + $amount;
                \think\facade\Log::write("累加后总金额: " . $counttotalprice);
            } else {
                \think\facade\Log::write("❌ 不是业绩商品，跳过计算");
            }
        }
        
        \think\facade\Log::write("最终累计消费金额: " . $counttotalprice);
        
        // 使用新的分层配置计算星级
        \think\facade\Log::write("=== 星级计算开始 ===");
        \think\facade\Log::write("累计消费金额: " . $counttotalprice);
        \think\facade\Log::write("旧星级: " . $oldxingji);
        
        $xingji = 0;
        if(isset($jiuxingsetinfo['layer_config']) && !empty($jiuxingsetinfo['layer_config'])) {
            \think\facade\Log::write("使用分层配置计算星级");
            // 使用分层配置
            for($i = 9; $i >= 1; $i--) {
                $layerMoney = $jiuxingsetinfo['layer_config'][$i]['xiaofeimoney'] ?? ($i * ($jiuxingsetinfo['xiaofeimoney'] ?? 1000));
                \think\facade\Log::write($i . "星级要求金额: " . $layerMoney . ", 当前累计: " . $counttotalprice);
                if($counttotalprice >= $layerMoney) {
                    $xingji = $i;
                    \think\facade\Log::write("✅ 达到" . $i . "星级要求，设置星级为: " . $xingji);
                    break;
                }
            }
        } else {
            \think\facade\Log::write("使用旧版本计算方式");
            $baseAmount = floatval($jiuxingsetinfo['xiaofeimoney'] ?? 1000);
            // 防止除零错误
            if ($baseAmount <= 0) {
                $baseAmount = 1000;
            }
            \think\facade\Log::write("基础金额: " . $baseAmount);
            // 兼容旧版本计算方式
            $xingji = floor($counttotalprice / $baseAmount);
            if($xingji > 9) {
                $xingji = 9; 
            }
            \think\facade\Log::write("计算结果: " . $counttotalprice . " / " . $baseAmount . " = " . $xingji);
        }
        
        \think\facade\Log::write("最终计算星级: " . $xingji);
        
        // 检查复购要求（如果启用）
        if($xingji > $oldxingji && $xingji > 1 && isset($jiuxingsetinfo['layer_config'][$xingji]['fugou_money'])) {
            $fugouMoney = $jiuxingsetinfo['layer_config'][$xingji]['fugou_money'];
            if($fugouMoney > 0) {
                // 检查复购情况（这里可以根据需要实现复购检查逻辑）
                // 暂时先允许升级，后续可以添加复购验证
            }
        }
        
        Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['xingji' => $xingji]);
        
        // 根据奖励发放时机决定是否立即发放奖励
        $rewardTiming = $jiuxingsetinfo['reward_timing'] ?? 'completed';
        if($rewardTiming == 'paid') {
            // 下单后发放：立即发放奖励
            if(isset($jiuxingsetinfo['layer_config']) && !empty($jiuxingsetinfo['layer_config'])) {
                \app\admin\common\Member::getjieidanpidNew($aid, $mid, $order['totalprice'], $xingji, $order['id'], $oldxingji, $jiuxingsetinfo);
            } else {
                // 兼容旧版本
                \app\admin\common\Member::getjieidanpid($aid, $mid, $order['totalprice'], $xingji, $order['id'], $oldxingji);
            }
        }
        // 如果设置为完成后发放，则在Order::collect()中处理奖励
    }

    foreach($ress as $ordergoods)
    {
        $product = Db::name('shop_product')->where('id', $ordergoods['proid'])->find();
        // 送消费值
        if($product['xiaofeizhi'])
        {
            $xiaofeizhi = json_decode($product['xiaofeizhi'],1);
            // PHP 8.0+ 类型转换：JSON数据必须显式转换
            $xiaofeifan = isset($xiaofeizhi[$member['levelid']]) ? floatval($xiaofeizhi[$member['levelid']]) : 0;
            $xiaofeifan = $xiaofeifan * intval($ordergoods['num']);
            $xiaofeizhicount = $xiaofeifan;
            // 转换绿积分
            // 绿积分的价值 $sysset['lvjifenjiazhi']
            // 消费值绿积分转换 xiaofeizhizhuan
            $sysset = Db::name('admin_set')->where('aid', $aid)->find();
            // 防止除零错误
            $lvjifenjiazhi = floatval($sysset['lvjifenjiazhi']);
            if ($lvjifenjiazhi > 0) {
                $kouchulvjifen = $xiaofeizhicount * floatval($sysset['xiaofeizhizhuan']) * 0.01 / $lvjifenjiazhi;
                $kouchulvjifen = round($kouchulvjifen, 2);
            } else {
                $kouchulvjifen = 0;
            }
            if($ordergoods['bid'] == 0){
                // 给消费值
                \app\admin\common\Member::addscorexiaofeizhi($aid, $mid, $xiaofeizhicount, '购买产品赠送消费值'.$xiaofeizhicount);
            } else{
                // 扣绿积分
                $store_info = Db::name('business')->where('aid', $aid)->where('id', $ordergoods['bid'])->find();
                $store_member_info = Db::name('member')->where('aid', $aid)->where('id', $store_info['mid2'])->find();
                if($store_info && $store_member_info)
                {
                    \app\admin\common\Member::addscorexiaofeizhi($aid, $mid, $xiaofeizhicount, '购买产品赠送消费值'.$xiaofeizhicount);
                    \app\admin\common\Member::addscorelv($aid, $store_info['mid2'], '-'.$kouchulvjifen, '用户购买产品赠送消费值,扣除绿积分'.$kouchulvjifen);
                }
            }
        }
        
        if ($product['xianjinquan'])
        {
            $xianjinquan2 = json_decode($product['xianjinquan'],1);
            // PHP 8.0+ 类型转换：JSON数据必须显式转换
            $xiaofeifan2 = isset($xianjinquan2[$member['levelid']]) ? floatval($xianjinquan2[$member['levelid']]) : 0;
            $xiaofeifan2 = $xiaofeifan2 * intval($ordergoods['num']);
            $xiaofeizhicount2 = $xiaofeifan2;
            // 转换绿积分
            // 绿积分的价值 $sysset['lvjifenjiazhi']
            // 消费值绿积分转换 xiaofeizhizhuan
            $sysset = Db::name('admin_set')->where('aid', $aid)->find();
            $kouchulvjifen2 = $xiaofeizhicount2;
            $kouchulvjifen2 = round($kouchulvjifen2, 2);
            if($ordergoods['bid'] == 0){
                // 给消费值
                \app\admin\common\Member::addhei($aid, $mid, $xiaofeizhicount2, '购买产品赠送'.t('现金券').$xiaofeizhicount2);
            } else{
                // 扣绿积分
                $store_info = Db::name('business')->where('aid', $aid)->where('id', $ordergoods['bid'])->find();
                $store_member_info = Db::name('member')->where('aid', $aid)->where('id', $store_info['mid2'])->find();
                if($store_info && $store_member_info)
                {
                    // 暂时不处理
                    // \app\admin\common\Member::addscorexiaofeizhi($aid, $mid, $xiaofeizhicount, '购买产品赠送消费值'.$xiaofeizhicount);
                    // \app\admin\common\Member::addscorelv($aid, $store_info['mid2'], '-'.$kouchulvjifen, '用户购买产品赠送消费值,扣除绿积分'.$kouchulvjifen);
                }
            }
        }
        
        if ($product['shengjiscore']) {
            // 解析产品中的升级积分设置
            $shengjiscore2 = json_decode($product['shengjiscore'], true);
            
            // 获取当前会员等级对应的积分值
            // PHP 8.0+ 类型转换：JSON数据必须显式转换
            $shengjiscore2 = isset($shengjiscore2[$member['levelid']]) ? floatval($shengjiscore2[$member['levelid']]) : 0;
            // 根据购买数量计算总积分值
            $shengjiscore2 = $shengjiscore2 * intval($ordergoods['num']);
            
            // 总消费值
            $shengjiscorecount2 =  $shengjiscore2;
    
            // 获取系统设置
            $sysset = Db::name('admin_set')->where('aid', $aid)->find();
            // 计算扣除的绿积分，实际操作中不再使用
            $kouchulvjifen2 =   $shengjiscorecount2;
            $kouchulvjifen2 = round($kouchulvjifen2, 2);
            
            // 如果订单商品的 bid 为 0，则直接给消费值
            if ($ordergoods['bid'] == 0) {
                \app\admin\common\Member::addshengjiscore($aid, $mid, $shengjiscorecount2, '购买产品赠送' . t('升级积分') . $xiaofeizhicount2);
            } else {
                // 这里不再进行扣除绿积分的操作，仅记录相关信息
                // \app\admin\common\Member::addscorexiaofeizhi($aid, $mid, $xiaofeizhicount, '购买产品赠送消费值' . $xiaofeizhicount);
                // \app\admin\common\Member::addscorelv($aid, $store_info['mid2'], '-' . $kouchulvifen, '用户购买产品赠送消费值,扣除绿积分' . $kouchulvjifen);
            }
        }
        
        // 返佣新
        if($product['if_fanyong'] > 0)
        {
            if($ordergoods['totalprice'] > 0)
            {
                $levelid = $member['levelid'];
                $fanyong = json_decode($product['fanyong'],1);
                // var_dump('用户等级'.$fanyong);
                // PHP 8.0+ 类型转换：JSON数据必须显式转换
                if(isset($fanyong[$levelid]))
                {
                    $fanyongbl = floatval($fanyong[$levelid]);
                } else{
                    $fanyongbl = isset($fanyong[0]) ? floatval($fanyong[0]) : 0;
                }
                if($product['if_fanyong'] == 1)
                {
                    $fangou_product = $ordergoods['totalprice'] * $fanyongbl * 0.01;
                    $fangou_product = round($fangou_product, 2);
                } elseif($product['if_fanyong'] == 2)
                {
                    $fangou_product = $fanyongbl * $ordergoods['num'];
                }
                Db::name('member_commission_record')->insert([
                    'aid' => $aid,
                    'mid' => $ordergoods['mid'],
                    'frommid' => $mid,
                    'orderid' => $orderid,
                    'ogid' => $ordergoods['id'],
                    'type' => 'shop',
                    'commission' => $fangou_product,
                    'remark' => '自购商品'.$ordergoods['name'].'返佣,奖励'.$fangou_product,
                    'createtime' => time()
                ]);
                \app\admin\common\Member::getzhituichouyong($aid, $mid, $fangou_product, $orderid, $ordergoods['id'], $ordergoods['ordernum']);
                \app\admin\common\Member::getjiantuichouyong($aid, $mid, $fangou_product, $orderid, $ordergoods['id'], $ordergoods['ordernum']);
                \app\admin\common\Member::addParentcommissionLogs($aid, $mid, $orderid, $ordergoods['id'], $fangou_product, '下级分佣奖励2');
            }
        }
    }

    // 推荐送消费值
    foreach($ress as $ordergoods){
        // 一级
        if($ordergoods['xfzparent2id'] && $ordergoods['xfzparent2commission'])
        {
             \app\admin\common\Member::addscorexiaofeizhi($aid, $ordergoods['xfzparent2id'], $ordergoods['xfzparent2commission'], '下级购买产品赠送消费值'.$ordergoods['xfzparent2commission']);
        }
        // 二级
        if($ordergoods['xfzparent3id'] && $ordergoods['xfzparent3commission'])
        {
             \app\admin\common\Member::addscorexiaofeizhi($aid, $ordergoods['xfzparent3id'], $ordergoods['xfzparent3commission'], '下下级购买产品赠送消费值'.$ordergoods['xfzparent3commission']);
        }
        // 三级
        if($ordergoods['xfzparent4id'] && $ordergoods['xfzparent4commission'])
        {
           \app\admin\common\Member::addscorexiaofeizhi($aid, $ordergoods['xfzparent4id'], $ordergoods['xfzparent4commission'], '下下下级购买产品赠送消费值'.$ordergoods['xfzparent4commission']); 
        }
    }

    foreach($ress as $ordergoods)
    {
        $member = Db::name('member')->where('id', $order['mid'])->find();
        $newbus_total = $member['bus_total'];
        
        // 给积分
        \app\admin\common\Member::addscore($aid, $order['mid'], $ordergoods['jingji'], '购买产品静态分佣'.t('积分'));
        if($memberlevel['is_bus_bound_multiples'] == 1)
        {
            if($newbus_total >= $ordergoods['jingyong'])
            {
                // 给佣金 
                if($ordergoods['jingyong'] > 0){
                    // \app\admin\common\Member::addcommission($aid, $order['mid'], $order['mid'], $ordergoods['jingyong'], '购买产品静态分佣佣金');
                    Db::name('member_commission_record')->insert([
                        'jl_type' => 3,
                        'aid' => $aid,
                        'mid' => $order['mid'],
                        'frommid' => $order['mid'],
                        'orderid' => $orderid,
                        'ogid' => $ordergoods['id'],
                        'type' => 'shop',
                        'commission' => $ordergoods['jingyong'],
                        'remark' => '购买产品静态分佣佣金',
                        'createtime' => time()
                    ]);
                    \app\admin\common\Member::addbusTotal($aid, $mid, $mid, '-'.$ordergoods['jingyong'], '奖励静态分佣扣除'.t('创业值'));
                }
            }
        } else{
            if($ordergoods['jingyong'] > 0){
                // 给佣金 
                // \app\admin\common\Member::addcommission($aid, $order['mid'], $order['mid'], $ordergoods['jingyong'], '购买产品静态分佣佣金');
                Db::name('member_commission_record')->insert([
                    'jl_type' => 3,
                    'aid' => $aid,
                    'mid' => $order['mid'],
                    'frommid' => $order['mid'],
                    'orderid' => $orderid,
                    'ogid' => $ordergoods['id'],
                    'type' => 'shop',
                    'commission' => $ordergoods['jingyong'],
                    'remark' => '购买产品静态分佣佣金',
                    'createtime' => time()
                ]);
            }
        }
    }

    // 给积分一级二级三级持续
    foreach($ress as $ordergoods)
    {
        // 一级
        \app\admin\common\Member::addscore($aid, $ordergoods['parent1'], $ordergoods['parent1commissionscore'], '下级购买产品产生'.t('积分'));
        // 二级
        \app\admin\common\Member::addscore($aid, $ordergoods['parent2'], $ordergoods['parent2commissionscore'], '下下级购买产品产生'.t('积分'));
        // 三级 
        \app\admin\common\Member::addscore($aid, $ordergoods['parent3'], $ordergoods['parent3commissionscore'], '下下下级购买产品产生'.t('积分'));
        // 持续推荐奖励
        \app\admin\common\Member::addscore($aid, $ordergoods['parent4'], $ordergoods['parent4commissionscore'], '下下下下级购买产品产生'.t('积分'));
    }

    // 开发奖直推下级所有业绩每达到25箱就奖励12800
    $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
    if($member['pid'] > 0 )
    {
        // 代表有上级
        \app\admin\common\Member::sendkfj($aid, $member['pid']);
    }

    // 发送创业基金
    \app\admin\common\Member::sendcyjijin($aid, $mid);
    $set = Db::name('admin_set')->where('aid', $aid)->find();
    // var_dump(1);
    if($jiuxingCoreAvailable && $set['gongpaizhi_shi'] > 0 && $set['gongpaizhi_ren'] > 0 && $set['gongpaizhi_xiaofei'] > 0)
    {
        // var_dump($product['songduihuanfen']);die;
        \app\admin\common\Member::addduihuanfen($aid, $order['mid'], $product['songduihuanfen'], '购买产品赠送');
        \app\admin\common\Member::setgongpai($aid, $mid);
    }

    $uplvResult = \app\admin\common\Member::triggerUplv($aid, $mid, [
        'scene' => \app\admin\common\MemberUpgradeScene::ORDER_PAID,
        'source_type' => 'shop_order',
        'source_id' => strval($order['id']),
        'orderid' => intval($order['id']),
    ]);
    self::jietiquanfanDebug('shop_pay after triggerUplv', [
        'order_id' => intval($order['id']),
        'mid' => intval($mid),
        'levelid' => intval(Db::name('member')->where('id', $mid)->value('levelid')),
        'uplv' => is_array($uplvResult) ? ($uplvResult['status'] ?? '') : $uplvResult,
    ]);
    // 阶梯全返按升级后的等级建奖池：粉丝买指定商品会升会员，必须等 triggerUplv 完成后再跑
    self::notifyShopOrderPluginPaid($aid, intval($order['id']));
    // 在 \app\admin\common\Member::uplv($aid, $mid, $order['id']); 后面添加

// 处理加速卡
foreach($oglist as $og) {
    // 获取商品信息
    $product = Db::name('shop_product')->where('id', $og['proid'])->find();
    
    // 检查商品是否开启加速卡功能
    if($jifenchiAvailable && $jifen2info && intval($jifen2info['jfc_status'] ?? 0) === 1 && $product && $product['card_enable'] == 1) {
        // 获取购买者信息
        $buyer = Db::name('member')->where('id', $mid)->find();
        
        // 处理直推加速卡
        if($buyer['pid'] > 0 && $product['direct_speed'] > 0) {
            Db::name('acceleration_card')->insert([
                'aid' => $aid,
                'mid' => $buyer['pid'], // 发给直推上级
                'from_mid' => $mid, // 来源用户
                'order_id' => $orderid,
                'goods_id' => $og['proid'],
                'type' => 1, // 1直推 2间推
                'speed_ratio' => $product['direct_speed'],
                'days' => $product['card_days'],
                'start_time' => time(),
                'end_time' => time() + ($product['card_days'] * 86400),
                'status' => 1,
                'create_time' => time()
            ]);
            
         
        }
        
        // 处理间推加速卡
        if($buyer['pid'] > 0 && $product['indirect_speed'] > 0) {
            // 获取二级上级
            $parent = Db::name('member')->where('id', $buyer['pid'])->find();
            if($parent && $parent['pid'] > 0) {
                Db::name('acceleration_card')->insert([
                    'aid' => $aid,
                    'mid' => $parent['pid'], // 发给间推上级
                    'from_mid' => $mid, // 来源用户
                    'order_id' => $orderid,
                    'goods_id' => $og['proid'],
                    'type' => 2, // 1直推 2间推
                    'speed_ratio' => $product['indirect_speed'],
                    'days' => $product['card_days'],
                    'start_time' => time(),
                    'end_time' => time() + ($product['card_days'] * 86400),
                    'status' => 1,
                    'create_time' => time()
                ]);
                
                
            }
        }
    }
    
	    // ================= 中金支付余额支付分账处理 =================
	    try {
	        $payorderInfo = Db::name('payorder')->where('orderid', $orderid)->where('type', 'shop')->find();
	        self::handleCpcnDeductSplitAfterPay('shop', $order, $payorderInfo);
	    } catch (\Exception $e) {
	        \think\facade\Log::error('商城订单余额支付分账异常', [
	            'order_id' => $orderid,
	            'error' => $e->getMessage(),
	            'trace' => $e->getTraceAsString()
	        ]);
	    }
	    // =============================================
    
    // 押金释放检查（订单支付成功后触发）
    if(class_exists('\app\plugin\deposit\common\DepositHelper')){
        try {
            \app\plugin\deposit\common\DepositHelper::checkOrderRelease($mid, $aid);
        } catch (\Exception $e) {
            \think\facade\Log::write('【押金释放】订单支付后检查失败：'.$e->getMessage(), 'error');
        }
    }
}

// 继续原有的通知发送逻辑
\app\admin\common\Wifiprint::print($aid, 'shop', $order['id']);
    \app\admin\common\Wifiprint::print($aid, 'shop', $order['id']);

    // 公众号通知 订单支付成功
    $tmplcontent = [];
    if($order['paytypeid'] != 4) {
        $tmplcontent['first'] = '有新订单支付成功';
    } else {
        $tmplcontent['first'] = '有新订单下单成功（'.$order['paytype'].'）';
    }
    $tmplcontent['remark'] = '点击进入查看~';
    $tmplcontent['keyword1'] = $member['nickname']; // 用户名
    $tmplcontent['keyword2'] = $order['ordernum']; // 订单号
    $tmplcontent['keyword3'] = $order['totalprice'].'元'; // 订单金额
    $tmplcontent['keyword4'] = $order['title']; // 商品信息
    $tmplcontentNew = [];
    $tmplcontentNew['thing8'] = \app\admin\common\Mendian::getNameWithBusines($order); // 门店
    $tmplcontentNew['phrase18'] = $member['nickname']; // 用户名
    $tmplcontentNew['character_string2'] = $order['ordernum']; // 订单号
    $tmplcontentNew['amount5'] = $order['totalprice'] == 0 ? '0.00' : $order['totalprice']; // 订单金额
    $tmplcontentNew['thing3'] = $order['title']; // 商品信息
    
    $adminResponse = \app\admin\common\Wechat::sendhttmpl($aid, 0, 'tmpl_orderpay', $tmplcontent, m_url('admin/order/shoporder', $aid), $order['mdid'], $tmplcontentNew);
   
    \app\admin\common\AdminNotice::sendOrder([
        'aid' => $aid,
        'bid' => $order['bid'],
        'uid' => $order['mid'],
        'title' => $order['title'],
        'content' => json_encode($tmplcontent),
        'ordernum' => $order['ordernum'],
    ]);
    if($order['paytypeid'] != 4) {
        $tmplcontent['first'] = '恭喜您的订单已支付成功';
    } else {
        $tmplcontent['first'] = '恭喜您的订单已下单成功';
    }
    $rs = \app\admin\common\Wechat::sendtmpl($aid, $mid, 'tmpl_orderpay', $tmplcontent, m_url('pages/my/usercenter', $aid), $tmplcontentNew);

    $tmplcontent = [];
    $tmplcontent['thing11'] = $order['title'];
    $tmplcontent['character_string2'] = $order['ordernum'];
    if($order['paytypeid'] != 4) {
        $tmplcontent['phrase10'] = '已支付';
    } else{
        $tmplcontent['phrase10'] = $order['paytype'];
    }
    $tmplcontent['amount13'] = $order['totalprice'].'元';
    $tmplcontent['thing27'] = $member['nickname'];
    \app\admin\common\Wechat::sendhtwxtmpl($aid, $order['bid'], 'tmpl_orderpay', $tmplcontent, 'admin/order/shoporder', $order['mdid']);

    // 短信通知
    $rs = \app\admin\common\Sms::send($aid, $member['tel'] ? $member['tel'] : $order['tel'], 'tmpl_orderpay', ['ordernum' => $order['ordernum']]);
 // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
    $set = Db::name('admin_set')->where('aid', $aid)->find();
    if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
        \app\admin\common\Order::giveCommission($order, 'shop');
    }

    // 商城订单的区域/团队/股东分红需要尽量在支付成功当下固化名单。
    // 线上当前常见配置是 apiauto，若仍等计划任务批量扫 isfenhong=0 订单，
    // 会按“结算当时”的代理配置计算，容易出现同区域前后订单名单不一致。
    try {
        $fenhongSet = Db::name('admin_set')
            ->where('aid', $aid)
            ->field('fhjiesuantime_type,fenhong_mode')
            ->find();
        if($fenhongSet && intval($fenhongSet['fhjiesuantime_type']) === 1) {
            \think\facade\Log::write('商城订单支付成功后立即结算分红，订单ID:'.$orderid.', 模式:'.($fenhongSet['fenhong_mode'] ?? ''));
            \app\admin\common\Fenhong::jiesuan_single($aid, $orderid, 'shop');
        }
    } catch (\Throwable $e) {
        \think\facade\Log::error('商城订单支付成功触发分红结算失败，订单ID:'.$orderid.', 错误:'.$e->getMessage());
    }

    // 同步更新 daigou_order 表的订单状态和 paytypeid
    try {
        // 获取当前 shop_order 记录
        $shopOrder = Db::name('shop_order')->where('id', $orderid)->find();

        if ($shopOrder && isset($shopOrder['d_orderid'])) {
            $d_orderid = $shopOrder['d_orderid'];

            // 查找对应的 daigou_order 记录
            $daigouOrder = Db::name('daigou_order')->where('id', $d_orderid)->find();

            if ($daigouOrder) {
                // 更新 daigou_order 的状态和 paytypeid
                $updateData = [
                    'status' => '1', // 请根据您的状态定义进行调整，例如 'paid', 'completed' 等
                   
                    'paytypeid' => $shopOrder['paytypeid'], // 同步 paytypeid
                    // 其他需要更新的字段
                ];

                Db::name('daigou_order')->where('id', $d_orderid)->update($updateData);

                
            } else {
              
            }
        } else {
         
        }
    } catch (\Exception $e) {
        // 处理异常，例如记录日志
     
    }
    
    // 订单支付成功后处理排单逻辑
    try {
        // 检查是否启用排单功能
        $paidan_enabled = Db::name('paidan_config')
            ->where('aid', $aid)
            ->where('is_enabled', 1)
            ->count();
            
        if($paidan_enabled > 0) {
            // 调用排单逻辑创建点位
            \app\admin\common\PaidanLogic::createPosition($aid, $mid, $orderid, $order['totalprice']);
            Log::write('订单支付成功调用排单逻辑-订单ID:' . $orderid . '-会员ID:' . $mid . '-金额:' . $order['totalprice']);
        } else {
            Log::write('排单功能未启用，跳过排单逻辑-订单ID:' . $orderid);
        }
    } catch (\Exception $e) {
        Log::error('排单逻辑处理异常-订单ID:' . $orderid . '-错误信息:' . $e->getMessage());
    }
    
    // 分红点计算逻辑
    try {
        \app\admin\common\FenhongDian::calculateAndReward($aid, $mid, $orderid, 'order_pay');
        Log::write('订单支付成功分红点计算-订单ID:' . $orderid . '-会员ID:' . $mid);
    } catch (\Exception $e) {
        Log::error('分红点计算异常-订单ID:' . $orderid . '-错误信息:' . $e->getMessage());
    }
    
    // ================= 接点奖励处理 =================
    // 添加详细的调试日志
    \think\facade\Log::write('=== 开始检查接点奖励功能 ===');
    \think\facade\Log::write('订单ID: ' . $orderid);
    \think\facade\Log::write('商户ID: ' . $aid);
    \think\facade\Log::write('会员ID: ' . $mid);
    \think\facade\Log::write('订单金额: ' . ($order['totalprice'] ?? '未知'));
    
	    if (!self::canRunJiedianReward(intval($aid))) {
	        \think\facade\Log::write('接点奖励插件未启用、未授权或数据表不存在，跳过处理');
	    } else {
	        // 检查接点奖励功能是否开启
	        $jiedianSettings = Db::name('jiedian_reward_settings')->where('aid', $aid)->find();
	        
	        \think\facade\Log::write('接点奖励设置查询结果: ' . json_encode($jiedianSettings));
	        
	        if ($jiedianSettings && $jiedianSettings['status'] == 1) {
	            \think\facade\Log::write('接点奖励功能已开启，开始处理...');
	            
	            // 调用接点奖励处理
	            try {
	                $rewardResult = \app\admin\common\JiedianRewardService::processOrderReward($orderid, $aid);
	                
	                \think\facade\Log::write('接点奖励处理结果: ' . json_encode($rewardResult));
	                
	                // 记录接点奖励处理日志
	                if ($rewardResult['success']) {
	                    \think\facade\Log::info('接点奖励处理成功', [
	                        'order_id' => $orderid,
	                        'member_id' => $mid,
	                        'message' => $rewardResult['message'],
	                        'reward_count' => $rewardResult['reward_count'] ?? 0,
	                        'distributed_count' => $rewardResult['distributed_count'] ?? 0,
	                        'total_reward_amount' => $rewardResult['total_reward_amount'] ?? 0
	                    ]);
	                } else {
	                    \think\facade\Log::warning('接点奖励处理失败', [
	                        'order_id' => $orderid,
	                        'member_id' => $mid,
	                        'message' => $rewardResult['message']
	                    ]);
	                }
	            } catch (\Exception $e) {
	                \think\facade\Log::error('接点奖励处理异常', [
	                    'order_id' => $orderid,
	                    'error' => $e->getMessage(),
	                    'trace' => $e->getTraceAsString()
	                ]);
	            }
	        } else {
	            $statusText = $jiedianSettings ? '状态: ' . $jiedianSettings['status'] : '设置不存在';
	            \think\facade\Log::write('接点奖励功能未开启或设置不存在 - ' . $statusText);
	        }
	    }
    
    \think\facade\Log::write('=== 接点奖励功能检查完毕 ===');
    
    // 统一触发订单支付成功事件。
    // 接点团队奖励会在触发器内部自行按配置判断，甄新汇选自动提单也依赖这个统一入口。
    \app\common\OrderTrigger::onOrderPaid($orderid, $order);

    // ================= 分期福利插件 =================
    try {
        \app\adminapi\controller\FenqifuliApi::createPeriodsForOrder(
            $aid, $mid, $order['bid'] ?? 0, $orderid,
            $order['ordernum'], $order['totalprice']
        );
	    } catch (\Exception $e) {
	        Log::error('分期福利创建失败-订单ID:' . $orderid . '-错误:' . $e->getMessage());
	    }

	    self::createShopPeisongOrderAfterPay($orderid);
	}

	//积分商城订单
	public static function scoreshop_pay($orderid){
		$order = Db::name('scoreshop_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		//自动发货
		if($order['freight_type']==3){
			$og = Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->find();
			$freight_content = Db::name('scoreshop_product')->where('id',$og['proid'])->value('freightcontent');
			Db::name('scoreshop_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
			Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
			if(getcustom('scoreshop_wx_hongbao')){
			    //如果是兑��红包
                if($og['type'] ==1){
                    $hb_scoreshop_product = Db::name('scoreshop_product')->where('id',$og['proid'])->field('hongbao_money,scene_id')->find();

                    if($hb_scoreshop_product['hongbao_money'] > 0){
                        $money =  dd_money_format($hb_scoreshop_product['hongbao_money'],2);
                        $rs = \app\admin\common\Wxpay::sendredpackage($order['aid'],$order['mid'],$order['platform'],$money,mb_substr($order['title'],0,10),'微信红包','恭喜发财','微信红包',$hb_scoreshop_product['scene_id']);
                        if($rs['status']==0){ //发放失败
                            Db::name('scoreshop_order')->where('id',$order['id'])->update(['send_remark'=>$rs['msg']]);
                        }else{
                            //修改订单状态
                            Db::name('scoreshop_order')->where('id',$order['id'])->update(['status'=>3,'send_time'=>time(),'send_remark'=>'红包发放成功']);
                            Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>3]);
                        }
                    }
                }
            }
            if(getcustom('scoreshop_to_money')){
                if($order['type'] ==2 && $order['bid'] == 0){
                    $give_money = Db::name('scoreshop_product')->where('id',$og['proid'])->value('give_money');
                     if($give_money > 0){
                         $give_money =  dd_money_format($give_money,2);
                         //增加余额
                         $rs = \app\admin\common\Member::addmoney($order['aid'],$mid,$give_money,'积分兑换,订单号:'.$order['ordernum']);
                         if($rs['status'] ==1){
                             //修改订单状态
                             Db::name('scoreshop_order')->where('id',$order['id'])->update(['status'=>3,'send_time'=>time(),'send_remark'=>'余额发放成功']);
                             Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>3]);
                         }else{
                             Db::name('scoreshop_order')->where('id',$order['id'])->update(['send_remark'=>'余额发放失败']);
                         }

                     }
                }
            }
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\admin\common\Order::wxShipping($order['aid'],$order,'scoreshop');
            }
		}

		//在线卡密
		if($order['freight_type']==4){
			$og = Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->find();
			$codelist = Db::name('scoreshop_codelist')->where('proid',$og['proid'])->where('status',0)->order('id')->limit($og['num'])->select()->toArray();
			if($codelist && count($codelist) >= $og['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('scoreshop_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('scoreshop_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
				Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>2]);
			}

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\admin\common\Order::wxShipping($order['aid'],$order,'scoreshop');
			}
		}
		//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'scoreshop',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		 \app\admin\common\Member::addgongxianzhi($aid, $mid, $order['givescoregongxianzhi'], '参与积分兑换赠送'.t('贡献值'));
		\app\admin\common\Wifiprint::print($aid,'scoreshop',$order['id']);
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新'.t('积分').'商城订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalscore'].t('积分').($order['totalprice']>0?' + '.$order['totalprice'].'元':'');//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,0,'tmpl_orderpay',$tmplcontent,m_url('admin/order/scoreshoporder', $aid),$order['mdid']);

		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/scoreshop/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,0,'tmpl_orderpay',$tmplcontent,'admin/order/scoreshoporder',$order['mdid']);
		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
			\app\admin\common\Order::giveCommission($order,'scoreshop');
		}
		\app\common\OrderTrigger::onTokenreleasePaidEvent($aid, $mid, 'scoreshop_order_paid', strval(intval($orderid)));
	}
	//拼团订单
	public static function collage_pay($orderid){
		$order = Db::name('collage_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		if($order['buytype']!=1){
			$team = Db::name('collage_order_team')->where('id',$order['teamid'])->find();
			$tdata = [];
			$tdata['num'] = $team['num'] + 1;
			if($team['num'] + 1 >= $team['teamnum']){
				$tdata['status'] = 2;
				//团长奖励积分
				$orderlist = Db::name('collage_order')->where(['teamid'=>$team['id'],'status'=>1])->select()->toArray();
				$leader = Db::name('member')->where('id',$team['mid'])->find();
				foreach($orderlist as $v){
					if($v['givescore'] > 0){
						\app\admin\common\Member::addscore($aid,$v['mid'],$v['givescore'],'购买产品奖励'.t('积分'));
					}
					//自动发货
					if($order['freight_type']==3){
						$freight_content = Db::name('collage_product')->where('id',$order['proid'])->value('freightcontent');
						Db::name('collage_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
  //发货信息录入 微信小程序+微信支付
  if($v['platform'] == 'wx' && $v['paytypeid'] == 2){
	\app\admin\common\Order::wxShipping($v['aid'],$v,'collage');
}					}
					//在线卡密
					if($order['freight_type']==4){
						$codelist = Db::name('collage_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
						if($codelist && count($codelist) >= $order['num']){
							$pscontent = [];
							foreach($codelist as $codeinfo){
								$pscontent[] = $codeinfo['content'];
								Db::name('collage_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
							}
							$pscontent = implode("\r\n",$pscontent);
							Db::name('collage_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
						}
					}
					//公众号通知 拼团成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '有新拼团订单拼团成功';
					$tmplcontent['remark'] = '点击进入查看~';
					$tmplcontent['keyword1'] = $order['title']; //商品名称
					$tmplcontent['keyword2'] = $leader['nickname'];//团长
					$tmplcontent['keyword3'] = $team['teamnum'];//成团人数
					//\app\admin\common\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_collagesuccess',$tmplcontent,m_url('admin/order/collageorder'));
					$tmplcontent['first'] = '恭喜您拼团成功';
					$rs = \app\admin\common\Wechat::sendtmpl($aid,$v['mid'],'tmpl_collagesuccess',$tmplcontent,m_url('activity/collage/orderlist', $aid));
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['thing1'] = $order['title'];
					$tmplcontent['thing10'] = $leader['nickname'];
					$tmplcontent['number12'] = $team['teamnum'];

					$tmplcontentnew = [];
					$tmplcontentnew['thing7'] = $order['title'];
					$tmplcontentnew['thing12'] = $leader['nickname'];
					$tmplcontentnew['number2'] = $team['teamnum'];
					\app\admin\common\Wechat::sendwxtmpl($aid,$v['mid'],'tmpl_collagesuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
					//短信通知
					$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_collagesuccess',['ordernum'=>$order['ordernum']]);
				}
			}else{
				$tdata['status'] = 1;
			}
			Db::name('collage_order_team')->where('aid',$aid)->where('id',$order['teamid'])->update($tdata);
		}else{
			//自动发货
			if($order['freight_type']==3){
				$freight_content = Db::name('collage_product')->where('id',$order['proid'])->value('freightcontent');
				Db::name('collage_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
                //发货信息录入 微信小程序+微信支付
                if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                    \app\admin\common\Order::wxShipping($order['aid'],$order,'collage');
                }
			}
			//在线卡密
			if($order['freight_type']==4){
				$codelist = Db::name('collage_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
				if($codelist && count($codelist) >= $order['num']){
					$pscontent = [];
					foreach($codelist as $codeinfo){
						$pscontent[] = $codeinfo['content'];
						Db::name('collage_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
					}
					$pscontent = implode("\r\n",$pscontent);
					Db::name('collage_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
				}
                //发货信息录入 微信小程序+微信支付
                if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                    \app\admin\common\Order::wxShipping($order['aid'],$order,'collage');
			}
			}
            \app\admin\common\Wifiprint::print($aid,'collage',$order['id']);//成功后打印
		}

		//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'collage',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		\app\admin\common\Wifiprint::print($aid,'collage',$order['id']);

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新拼团订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/collageorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/collage/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/collageorder',$order['mdid']);

		//短信通知
		if($order['buytype']==1){ //直接购买
			$rs = \app\admin\common\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
		}
		\app\common\OrderTrigger::onTokenreleasePaidEvent($aid, $mid, 'collage_order_paid', strval(intval($orderid)));
	}
    public static function cycle_pay($orderid){
        $order = Db::name('cycle_order')->where('id',$orderid)->find();

        $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
        $mid = $order['mid'];
        Db::name('cycle_order_stage')->where('aid',$aid)->where('orderid',$order['id'])->update(['status' =>1]);

        //支付后送券
        $couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'cycle',$order['totalprice']);
        if($couponlist){
            foreach($couponlist as $coupon){
                \app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
            }
        }
        \app\admin\common\Wifiprint::print($aid,'cycle',$order['id']);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'cycle');
        }

        //公众号通知 订单支付成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新周期购订单支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        \app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/cycleorder', $aid),$order['mdid']);
        $tmplcontent['first'] = '恭喜您的订单已支付成功';
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('pagesExt/cycle/orderList', $aid));

        $tmplcontent = [];
        $tmplcontent['thing11'] = $order['title'];
        $tmplcontent['character_string2'] = $order['ordernum'];
        $tmplcontent['phrase10'] = '已支付';
        $tmplcontent['amount13'] = $order['totalprice'].'元';
        $tmplcontent['thing27'] = $member['nickname'];
        \app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/cycleorder',$order['mdid']);

        $rs = \app\admin\common\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
        \app\common\OrderTrigger::onTokenreleasePaidEvent($aid, $mid, 'cycle_order_paid', strval(intval($orderid)));
    }
		//幸运拼团订单
	public static function lucky_collage_pay($orderid){

		$order = Db::name('lucky_collage_order')->where('id',$orderid)->find();
		if(!$order || intval($order['status']) !== 0){
			return;
		}
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		if($order['buytype'] == 1){
			Db::name('lucky_collage_order')->where('id', $order['id'])->where('status',0)->update(['status'=>1]);
		}
		if($order['buytype'] !=1 ){
			Db::startTrans();
	        try {
				$team = Db::name('lucky_collage_order_team')->lock(true)->where('id',$order['teamid'])->find();
				$orderRow = Db::name('lucky_collage_order')->lock(true)->where('id',$order['id'])->find();
				if(!$orderRow || intval($orderRow['status']) !== 0){
					Db::commit();
					return;
				}
				$paidSeats = 0;
				if(!empty($team)){
					$paidSeats = Db::name('lucky_collage_order')->where('teamid',$team['id'])->where('buytype','<>',1)->where('status','in','1,2,3')->where('id','<>',$order['id'])->count();
				}
				$teamMissing = empty($team);
				$teamClosed = !$teamMissing && (intval($team['status']) === 2 || intval($team['status']) === 3);
				$teamFull = $teamMissing || $teamClosed || intval($team['num']) >= intval($team['teamnum']) || intval($paidSeats) >= intval($team['teamnum']);
				if(!$teamFull){
					$affected = Db::name('lucky_collage_order_team')->where('id',$team['id'])->whereRaw('`num` < `teamnum`')->where('status','not in',[2,3])->update(['num'=>Db::raw('`num`+1')]);
					if($affected < 1){
						$teamFull = true;
					}
				}
				if($teamFull){
					Db::commit();
					\app\admin\common\Order::refund($order, $order['totalprice'], '拼团已满员退款');
					Db::name('lucky_collage_order')->where('id', $order['id'])->update(['status'=>4,'refund_status'=>2,'refund_time'=>time(),'refund_money'=>$order['totalprice'],'refund_reason'=>'拼团已满员退款']);
					Db::name('member_commission_record')->where('aid',$aid)->where('type','lucky_collage')->where('orderid',$order['id'])->where('status',0)->update(['status'=>2]);
					return;
				}
				$team = Db::name('lucky_collage_order_team')->where('id',$team['id'])->find();
				$tData = [];
				$tData['num'] = intval($team['num']);
				$tData['status'] = ($tData['num'] >= intval($team['teamnum'])) ? 2 : 1;
				Db::name('lucky_collage_order_team')->where('id', $team['id'])->update(['status'=>$tData['status']]);
				Db::name('lucky_collage_order')->where('id', $order['id'])->where('status',0)->update(['status'=>1]);

				// 增加拼团统计数据
				$userData = Db::name('lucky_collage_user_data')->where('uid', $member['id'])->find();
				if(!empty($userData)){
					$userData['number_day']  = $userData['number_day'] + 1;
					$userData['total_number']  = $userData['total_number'] + 1;
					Db::name('lucky_collage_user_data')->update($userData);
				}
				Db::commit();
			} catch (\Exception $e) {
	            // 回滚
	            Db::rollback();
	            // 退款
	            \app\admin\common\Order::refund($order, $order['totalprice'], '拼团失败订单退款');
	            Db::name('lucky_collage_order')->where('id', $order['id'])->update(['status'=>4,'refund_status'=>2,'refund_time'=>time(),'refund_money'=>$order['totalprice'],'refund_reason'=>'拼团失败退款']);
	            Db::name('member_commission_record')->where('aid',$aid)->where('type','lucky_collage')->where('orderid',$order['id'])->where('status',0)->update(['status'=>2]);
	            
	         
	            
	            return;
	        }

		    \app\admin\common\Member::addgongxianzhi($aid, $order['mid'], -$order['gongxianzhiNumber'], '参与拼团扣除'.t('贡献值'));
		      //self::calculateUpwardRewards($order, $member);
			//支付成功后奖励分享者余额，积分，佣金，优惠券
			if(getcustom('plug_luckycollage')){
				if($order['shareid']>0){
					//取出分享
					$pmember = Db::name('member')->where('id',$order['shareid'])->find();
					if($pmember && $order['sharemoney'] > 0){  //1 奖励余额
						\app\admin\common\Member::addmoney($aid,$order['shareid'],$order['sharemoney'],'推荐奖励'.t('余额'));
					}else if($order['sharescore'] > 0){  //2 奖励积分
						\app\admin\common\Member::addscore($aid,$order['shareid'],$order['sharescore'],'推荐奖励'.t('积分'));
					}else if($order['sharecommission'] > 0){  //3 奖励佣金
						\app\admin\common\Member::addcommission($aid,$order['shareid'],$order['mid'],$order['sharecommission'],'推荐奖励'.t('佣金'));
					}elseif(!empty($order['share_yhqids'])){ //奖励优惠券
						foreach(explode(',',strval($order['share_yhqids'])) as $yhqid){
							if(intval($yhqid) > 0) \app\admin\common\Coupon::send($aid,$order['shareid'],intval($yhqid));
						}
					}
				}
				if($order['buytype']==2){
					//减掉开团次数
					if($order['isjiqiren']!=1 && $member['ktnum']>0){
						$ktnum = $member['ktnum']-1;
						Db::name('member')->where(['aid'=>$aid,'id'=>$order['mid']])->update(['ktnum'=>$ktnum]);
					}
				}
			}


			$product = Db::name('lucky_collage_product')->where('id', $team['proid'])->find();
			if($tData['status'] == 2 && $product['start_from_full'] == 1){
				\app\admin\model\LuckyCollage::kaijiang($order);
			}
		}

		//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'lucky_collage',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新拼团订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/luckycollageorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/luckycollage/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/luckycollageorder',$order['mdid']);

		\app\admin\common\Wifiprint::print($aid,'collage',$order['id']);
		//短信通知
		if($order['buytype']==1){ //直接购买
			$rs = \app\admin\common\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
		}

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
	}
            public static function calculateUpwardRewards($order, $member)
            {
                $aid = $order['aid'];
                $mid = $order['mid'];
                $totalPrice = $order['totalprice'];
                
                // 获取用户的 path 字段，表示所有上级的 ID 列表
                $path = $member['path']; // 直接从 $member 中获取 path
                if (!$path) {
                    return; // 没有上级，不进行奖励分配
                }
            
                // 将 path 字段解析为数组
                $parentIds = explode(',', $path);
                $parentIds = array_reverse($parentIds); // 从最近的上级开始遍历
            
                // 遍历每个上级，计算奖励并分配
                foreach ($parentIds as $parentId) {
                    $parentMember = Db::name('member')->where('aid', $aid)->where('id', $parentId)->find();
                    if (!$parentMember) {
                        continue; // 上级不存在，跳过
                    }
            
                    // 获取上级的会员等级
                    $parentLevel = Db::name('member_level')->where('aid', $aid)->where('id', $parentMember['levelid'])->find();
                    if (!$parentLevel || $parentLevel['can_agent'] == 0) {
                        continue; // 上级没有分销资格，跳过
                    }
            
                    // 计算奖励金额
                    $rewardAmount = 0;
                    if ($order['uprewardset'] == 1) { // 单独设置奖励比例
                        $uprewarddata = json_decode($order['uprewarddata'], true);
                        if (isset($uprewarddata[$parentLevel['id']])) {
                            $rewardPercentage = $uprewarddata[$parentLevel['id']]['upreward'];
                            $rewardAmount = $rewardPercentage * $totalPrice * 0.01;
                        }
                    } else { // 按照会员等级默认的比例进行奖励
                        $rewardPercentage = $parentLevel['upreward'];
                        $rewardAmount = $rewardPercentage * $totalPrice * 0.01;
                    }
            
                    // 分配奖励（可以是余额、积分、佣金等）
                    if ($rewardAmount > 0) {
                        // 根据需要修改这里的奖励类型，当前示例为佣金
                        \app\admin\common\Member::addcommission($aid, $parentId, $mid, $rewardAmount, '下级购买商品奖励');
                    }
                }
            }

	//砍价订单
	public static function kanjia_pay($orderid){
		$order = Db::name('kanjia_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$product = Db::name('kanjia_product')->where('id',$order['proid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		//自动发货
		if($order['freight_type']==3){
			$freight_content = $product['freightcontent'];
			Db::name('collage_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
		 //发货信息录入 微信小程序+微信支付
		 if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
			\app\admin\common\Order::wxShipping($order['aid'],$order,'kanjia');
		}
	}
		//在线卡密
		if($order['freight_type']==4){
			$codelist = Db::name('kanjia_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
			if($codelist && count($codelist) >= $order['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('kanjia_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('kanjia_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
			}
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\admin\common\Order::wxShipping($order['aid'],$order,'kanjia');
            }
			
		}

		//买单后发放帮砍得积分或余额
		if($product['helpgive_ff'] == 1 && $product['helpgive_percent'] > 0 && $order['joinid']){
			$helplist = Db::name('kanjia_help')->where('aid',$aid)->where('joinid',$order['joinid'])->where('mid','<>',$mid)->select()->toArray();
			foreach($helplist as $help){
				if($product['helpgive_type'] == 0){ //积分
					$givescore = intval($help['cut_price'] * $product['helpgive_percent'] * 0.01);
					if($givescore > 0){
						\app\admin\common\Member::addscore($aid,$help['mid'],$givescore,'帮好友砍价奖励');
					}
				}
				if($product['helpgive_type'] == 1){ //余额
					$givemoney = round($help['cut_price'] * $product['helpgive_percent'] * 0.01,2);
					if($givemoney > 0){
						\app\admin\common\Member::addmoney($aid,$help['mid'],$givemoney,'帮好友砍价奖励');
					}
				}
			}
		}
		//下单增加帮砍次数
		if($product['perhelpnum_buyadd'] > 0){
			$sharelog = Db::name('kanjia_sharelog')->where('aid',$aid)->where('proid',$product['id'])->where('mid',$mid)->find();
			if($sharelog){
				Db::name('kanjia_sharelog')->where('id',$sharelog['id'])->inc('addtimes',$product['perhelpnum_buyadd'])->update();
			}else{
				$data = [];
				$data['aid'] = $aid;
				$data['proid'] = $product['id'];
				$data['mid'] = $mid;
				$data['addtimes'] = $product['perhelpnum_buyadd'];
				Db::name('kanjia_sharelog')->insert($data);
			}
		}
		//下单送抽奖/余额/积分/优惠券
		if($product['paygive_choujiangtimes'] > 0 && $product['paygive_choujiangid'] > 0){
			$sharelog = Db::name('choujiang_sharelog')->where('aid',$aid)->where('hid',$product['paygive_choujiangid'])->where('mid',$mid)->find();
			if($sharelog){
				Db::name('choujiang_sharelog')->where('id',$sharelog['id'])->inc('extratimes',$product['paygive_choujiangtimes'])->update();
			}else{
				$data = [];
				$data['aid'] = $aid;
				$data['hid'] = $product['paygive_choujiangid'];
				$data['mid'] = $mid;
				$data['extratimes'] = $product['paygive_choujiangtimes'];
				Db::name('choujiang_sharelog')->insert($data);
			}
		}
		if($product['paygive_money'] > 0){
			\app\admin\common\Member::addmoney($aid,$mid,$product['paygive_money'],'砍价活动下单赠送');
		}
		if($product['paygive_score'] > 0){
			\app\admin\common\Member::addscore($aid,$mid,$product['paygive_score'],'砍价活动下单赠送');
		}
		if($product['paygive_couponid'] > 0){
			\app\admin\common\Coupon::send($aid,$mid,$product['paygive_couponid']);
		}


		//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'collage',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		\app\admin\common\Wifiprint::print($aid,'kanjia',$order['id']);

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新砍价订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/kanjiaorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/kanjia/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/kanjiaorder',$order['mdid']);

		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
		\app\common\OrderTrigger::onTokenreleasePaidEvent($aid, $mid, 'kanjia_order_paid', strval(intval($orderid)));
	}
	//秒杀订单
	public static function seckill_pay($orderid){
		$order = Db::name('seckill_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		//自动发货
		if($order['freight_type']==3){
			$freight_content = Db::name('seckill_product')->where('id',$order['proid'])->value('freightcontent');
			Db::name('seckill_order')->where('id',$order['id'])->update(['freight_content'=>$freight_content,'status'=>2,'send_time'=>time()]);
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\admin\common\Order::wxShipping($order['aid'],$order,'seckill');
            }
		}
		//在线卡密
		if($order['freight_type']==4){
			$codelist = Db::name('seckill_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
			if($codelist && count($codelist) >= $order['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('seckill_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('seckill_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
			}
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\admin\common\Order::wxShipping($order['aid'],$order,'seckill');
            }
		}

		//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'seckill',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		 \app\admin\common\Member::addgongxianzhi($aid, $mid, $order['givescoregongxianzhi'], '参与秒杀奖励'.t('贡献值'));
		\app\admin\common\Wifiprint::print($aid,'seckill',$order['id']);

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新秒杀订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/seckillorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/seckill/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/seckillorder',$order['mdid']);

		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel'] ? $member['tel'] : $order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);

		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
			\app\admin\common\Order::giveCommission($order,'seckill');
		}
		\app\common\OrderTrigger::onTokenreleasePaidEvent($aid, $mid, 'seckill_order_paid', strval(intval($orderid)));
	}

	//充值订单
	public static function recharge_pay($orderid){
		$order = Db::name('recharge_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];

		\app\admin\common\Member::addmoney($aid,$mid,$order['money'],t('余额').'充值');
		//充值赠送
		$giveset = Db::name('recharge_giveset')->where('aid',$aid)->find();
		if($giveset && $giveset['status']==1){
			$givedata = json_decode($giveset['givedata'],true);
		}else{
			$givedata = array();
		}
		$givemoney = 0;
		$givescore = 0;
		$moneyduan = 0;
		if($givedata){
			foreach($givedata as $give){
				if($order['money']*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
					$moneyduan = $give['money']*1;
					$givemoney = $give['give']*1;
                    $givescore = $give['give_score']*1;
				}
			}
		}
		if($givemoney > 0){
			\app\admin\common\Member::addmoney($aid,$mid,$givemoney,'充值赠送');
		}
        if($givescore > 0){
            \app\admin\common\Member::addscore($aid,$mid,$givescore,'充值赠送');
        }



		//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'recharge',$order['money']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}

		//升级
			\app\admin\common\Member::triggerUplv($aid, $mid, [
				'scene' => \app\admin\common\MemberUpgradeScene::RECHARGE_PAID,
				'source_type' => 'recharge_order',
				'source_id' => strval($order['id'] ?? $orderid),
			]);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'recharge');
        }

		$tmplcontent = array();
		$tmplcontent['first'] = '有新充值订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['accountType'] = t('会员').'昵称';
		$tmplcontent['account'] = $member['nickname'];
		$tmplcontent['amount'] = $order['money'].'元' . ($givemoney>0?'+赠送'.$givemoney.'元':'');
		$tmplcontent['result'] = '充值成功';
		\app\admin\common\Wechat::sendhttmpl($aid,0,'tmpl_recharge',$tmplcontent,m_url('admin/finance/rechargelog', $aid),$order['mdid']);

		//充值通知
        if(getcustom('zhaopin')){
            \app\admin\model\Zhaopin::sendhtsms('tmpl_recharge',[],$order['aid'],$order['bid'],$order['mdid']);
        }

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
	}


// 月结充值订单
public static function arrears_recharge_pay($orderid){
   // var_dump($orderid);die;
    // 获取订单信息
    $order = Db::name('arrears_recharge_order')->where('id', $orderid)->find();
    if (!$order) {
        return; // 订单不存在，直接返回
    }

    $aid = $order['aid'];
    $mid = $order['mid'];

    // 获取会员信息
    $member = Db::name('member')->where('id', $mid)->find();
    if (!$member) {
        return; // 会员不存在，直接返回
    }

    // 更新欠费金额
    $arrears = Db::name('member')->where('id', $mid)->value('arrears');
    $new_arrears = $arrears + $order['money']; // 增加欠费金额
    Db::name('member')->where('id', $mid)->update(['arrears' => $new_arrears]);

  
    // 月结充值赠送（如果有）
    $giveset = Db::name('recharge_giveset')->where('aid', $aid)->find();
    if ($giveset && $giveset['status'] == 1) {
        $givedata = json_decode($giveset['givedata'], true);
    } else {
        $givedata = [];
    }

    $givemoney = 0;
    $givescore = 0;
    $moneyduan = 0;
    if ($givedata) {
        foreach ($givedata as $give) {
            if ($order['money'] * 1 >= $give['money'] * 1 && $give['money'] * 1 > $moneyduan) {
                $moneyduan = $give['money'] * 1;
                $givemoney = $give['give'] * 1;
                $givescore = $give['give_score'] * 1;
            }
        }
    }

    // 赠送金额处理
    if ($givemoney > 0) {
        \app\admin\common\Member::addmoney($aid, $mid, $givemoney, '月结充值赠送');
    }

    // 赠送积分处理
    if ($givescore > 0) {
        \app\admin\common\Member::addscore($aid, $mid, $givescore, '月结充值赠送');
    }

    // 发放优惠券
    $couponlist = \app\admin\common\Coupon::getpaygive($aid, $mid, 'arrears_recharge', $order['money']);
    if ($couponlist) {
        foreach ($couponlist as $coupon) {
            \app\admin\common\Coupon::send($aid, $mid, $coupon['id']);
        }
    }

    // 升级会员等级
    \app\admin\common\Member::triggerUplv($aid, $mid, [
        'scene' => \app\admin\common\MemberUpgradeScene::RECHARGE_PAID,
        'source_type' => 'arrears_recharge_order',
        'source_id' => strval($order['id'] ?? $orderid),
    ]);

    // 如果是微信支付，发货信息录入
    if ($order['platform'] == 'wx' && $order['paytypeid'] == 2) {
        \app\admin\common\Order::wxShipping($order['aid'], $order, 'arrears_recharge');
    }

    // 发送充值成功通知
    $tmplcontent = [];
    $tmplcontent['first'] = '有新月结充值订单支付成功';
    $tmplcontent['remark'] = '点击进入查看~';
    $tmplcontent['accountType'] = t('会员') . '昵称';
    $tmplcontent['account'] = $member['nickname'];
    $tmplcontent['amount'] = $order['money'] . '元' . ($givemoney > 0 ? '+赠送' . $givemoney . '元' : '');
    $tmplcontent['result'] = '月结充值成功';
    \app\admin\common\Wechat::sendhttmpl($aid, 0, 'tmpl_recharge', $tmplcontent, m_url('admin/finance/arrearslog', $aid), $order['mdid']);

    // 月结充值通知
    if (getcustom('zhaopin')) {
        \app\admin\model\Zhaopin::sendhtsms('tmpl_recharge', [], $order['aid'], $order['bid'], $order['mdid']);
    }

    // 发送新订单通知给管理员
    \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
        'consignee' => $order['consignee'],
        'phone' => $order['tel'],
        'ordernum' => $order['ordernum'],
        'amount' => $order['money']
    ]);
}

	//会员升级订单
	public static function member_levelup_pay($orderid){
		$order = Db::name('member_levelup_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];

		//成为分销商
			$leveldata = Db::name('member_level')->where('aid',$aid)->where('id',$order['levelid'])->find();
			if(!$leveldata){
				throw new \RuntimeException('升级等级不存在');
			}
			$beforeLevelId = intval($member['levelid'] ?? 0);
			if($leveldata['apply_check']){
			//$return = array('status'=>2,'msg'=>'付款成功请等待审核');

			$tmplcontent = [];
			$tmplcontent['first'] = '有新用户申请升级,请及时处理';
			$tmplcontent['remark'] = '请进入电脑端后台进行审核~';
			$tmplcontent['keyword1'] = $leveldata['name']; //会员等级
			$tmplcontent['keyword2'] = '待审核';//审核状态
			\app\admin\common\Wechat::sendhttmpl($aid,0,'tmpl_uplv',$tmplcontent);

		}else{
				$levelendtime = \app\admin\common\Member::getLevelEndTime($leveldata);
            Db::name('member_levelup_order')->where('id',$orderid)->update(['status'=>2,'paytime'=>time(),'isfenhong'=>1]);
            //判断是否默认分组
			if($leveldata['cid'] > 0)
            $is_default = Db::name('member_level_category')->where('id', $leveldata['cid'])->value('isdefault');
            if($is_default || $leveldata['cid'] == 0) {
	                Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['levelid'=>$leveldata['id'],'levelendtime'=>$levelendtime,'levelstarttime'=>time(),'ylevelid'=>$beforeLevelId]);

                //更新代理区域
                if($leveldata['areafenhong']==1){
                    Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province']]);
                }elseif($leveldata['areafenhong']==2){
                    Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city']]);
                }elseif($leveldata['areafenhong']==3){
                    Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city'],'areafenhong_area'=>$order['areafenhong_area']]);
                }elseif($leveldata['areafenhong']==10){
                    Db::name('member')->where('aid',$aid)->where('id',$order['mid'])->update(['areafenhong_largearea'=>$order['areafenhong_largearea']]);
                }
            } else {
                if(getcustom('plug_sanyang')) {
                    $count = Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->count();
                    if($count) Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['levelid' => $leveldata['id'], 'levelendtime' => $levelendtime]);
                    else {
                        $record_data = ['levelid' => $leveldata['id'], 'levelendtime' => $levelendtime];
                        $record_data['aid'] = $aid;
                        $record_data['mid'] = $mid;
                        $record_data['createtime'] = time();
                        $record_data['cid'] = $leveldata['cid'];
                        Db::name('member_level_record')->insertGetId($record_data);
                    }
                    if(getcustom('level_comwithdraw')){
                        if($member['levelstarttime']<=0 && $leveldata['fenhong'] > 0){
                            Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['levelstarttime'=>time()]);
                        }
                    }else{
                        Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['levelstarttime'=>time()]);
                    }

                    //更新代理区域
                    if($leveldata['areafenhong']==1){
                        Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['areafenhong_province'=>$order['areafenhong_province']]);
                    }elseif($leveldata['areafenhong']==2){
                        Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city']]);
                    }elseif($leveldata['areafenhong']==3){
                        Db::name('member_level_record')->where('aid', $aid)->where('mid', $mid)->where('cid', $leveldata['cid'])->update(['areafenhong_province'=>$order['areafenhong_province'],'areafenhong_city'=>$order['areafenhong_city'],'areafenhong_area'=>$order['areafenhong_area']]);
                    }
                }
            }

            //0702给上级更新拉新时间
            if(getcustom('seckill2') && in_array($order['levelid'],explode(',',Db::name('seckill2_sysset')->where('aid',$order['aid'])->value('seckill_level')))){
                Db::name('member')->where(['aid'=>$aid,'id'=>$member['pid']])->update(['laxin_time'=>time()]);
            }

            if($leveldata['apply_payfenxiao'] == 1){ //升级费用参与分销及分红
                \app\admin\common\Common::applypayfenxiao($aid,$order['id']);
            }
            if($leveldata['apply_payfenxiao'] == 1){ //升级费用参与分销及分红
                Db::name('member_levelup_order')->where('id',$orderid)->update(['isfenhong'=>1]);
            }
            //赠送积分
            if($leveldata['up_give_score'] > 0) {
                \app\admin\common\Member::addscore($aid,$mid,$leveldata['up_give_score'],'升级奖励');
            }
            //赠送现金券
            //升级赠送现金券
            if($leveldata['up_give_xianjinquan'] >0)
            {
                 \app\admin\common\Member::addhei($aid, $mid, $leveldata['up_give_xianjinquan'], '升级奖励');
            }

            //奖励佣金
            if($leveldata['up_give_commission'] > 0) {
                \app\admin\common\Member::addcommission($aid,$mid,0,$leveldata['up_give_commission'],'升级奖励');
            }

            //奖励余额
            if($leveldata['up_give_money'] > 0) {
                \app\admin\common\Member::addmoney($aid,$mid,$leveldata['up_give_money'],'升级奖励');
            }

            //赠送上级佣金
            if ($leveldata['up_give_parent_money'] > 0) {
                $pid = Db::name('member')->where('aid', $aid)->where('id', $mid)->value('pid');
                if($pid > 0 && \app\admin\common\Member::isUpgradeParentCommissionAllowed($aid, intval($pid), $leveldata)) {
                    \app\admin\common\Member::addcommission($aid, $pid, $mid, $leveldata['up_give_parent_money'], '直推奖');
                }
            }

            // 处理HGB增长币升级结算
            try {
                if (class_exists('\app\plugin\hgb\common\HgbService')) {
                    \app\plugin\hgb\common\HgbService::processLevelupOrder($aid, $orderid);
                }
            } catch (\Exception $e) {
                \think\facade\Log::write('HGB升级结算异常：' . $e->getMessage(), 'error');
            }

			$tmplcontent = [];
			$tmplcontent['first'] = '恭喜您成功升级为'.$leveldata['name'];
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $leveldata['name']; //会员等级
				$tmplcontent['keyword2'] = '已生效';//审核状态
				$rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_uplv',$tmplcontent,m_url('pages/my/usercenter', $aid));

                \app\admin\common\Member::dispatchLevelChangedHooks(
                    intval($aid),
                    intval($mid),
                    intval($member['levelid'] ?? 0),
                    intval($leveldata['id'] ?? 0)
                );

                // 升级团队级差奖和评级奖在升级成功后立即结算，计划任务继续兜底。
                try {
                    \app\admin\common\Fenhong::settleLevelupOrder(intval($aid), intval($orderid));
                } catch (\Throwable $e) {
                    \think\facade\Log::warning('会员升级支付后团队奖励立即结算调用失败: aid=' . intval($aid) . ', orderid=' . intval($orderid) . ', error=' . $e->getMessage());
                }

			}
	}
	//表单支付
	// 表单支付
public static function form_pay($orderid){
    // 获取订单信息
    $order = Db::name('form_order')->where('id', $orderid)->find();
    if (!$order) {
        throw new \Exception('订单不存在');
    }

    // 获取用户信息
    $member = Db::name('member')->where('id', $order['mid'])->find();
    if (!$member) {
        throw new \Exception('用户不存在');
    }

    $aid = $order['aid'];
    $mid = $order['mid'];

    // 获取表单信息
    $form = Db::name('form')->where('id', $order['formid'])->find();
    if (!$form) {
        throw new \Exception('表单不存在');
    }

    $totalcommission = 0;
    $ogdata = [];
    $agleveldata1 = $agleveldata2 = $agleveldata3 = null;

    if ($form && $form['commissionset'] != -1) {
        // 分销提成逻辑
        if ($member['pid']) {
            $parent1 = Db::name('member')->where('aid', $aid)->where('id', $member['pid'])->find();
            if ($parent1) {
                $agleveldata1 = Db::name('member_level')->where('aid', $aid)->where('id', $parent1['levelid'])->find();
                if ($agleveldata1 && $agleveldata1['can_agent'] != 0) {
                    $ogdata['parent1'] = $parent1['id'];
                }
            }
        }

        if (isset($parent1) && $parent1['pid']) {
            $parent2 = Db::name('member')->where('aid', $aid)->where('id', $parent1['pid'])->find();
            if ($parent2) {
                $agleveldata2 = Db::name('member_level')->where('aid', $aid)->where('id', $parent2['levelid'])->find();
                if ($agleveldata2 && $agleveldata2['can_agent'] > 1) {
                    $ogdata['parent2'] = $parent2['id'];
                }
            }
        }

        if (isset($parent2) && $parent2['pid']) {
            $parent3 = Db::name('member')->where('aid', $aid)->where('id', $parent2['pid'])->find();
            if ($parent3) {
                $agleveldata3 = Db::name('member_level')->where('aid', $aid)->where('id', $parent3['levelid'])->find();
                if ($agleveldata3 && $agleveldata3['can_agent'] > 2) {
                    $ogdata['parent3'] = $parent3['id'];
                }
            }
        }

        // 根据佣金设置计算佣金
        if ($form['commissionset'] == 1) { // 按比例
            $commissiondata = json_decode($form['commissiondata1'], true);
            if ($commissiondata && $agleveldata1 && $agleveldata2 && $agleveldata3) {
                $ogdata['parent1commission'] = isset($commissiondata[$agleveldata1['id']]['commission1']) 
                    ? $commissiondata[$agleveldata1['id']]['commission1'] * $order['money'] * 0.01 
                    : 0;
                $ogdata['parent2commission'] = isset($commissiondata[$agleveldata2['id']]['commission2']) 
                    ? $commissiondata[$agleveldata2['id']]['commission2'] * $order['money'] * 0.01 
                    : 0;
                $ogdata['parent3commission'] = isset($commissiondata[$agleveldata3['id']]['commission3']) 
                    ? $commissiondata[$agleveldata3['id']]['commission3'] * $order['money'] * 0.01 
                    : 0;
            }
        } elseif ($form['commissionset'] == 2) { // 按固定金额
            $commissiondata = json_decode($form['commissiondata2'], true);
            if ($commissiondata && $agleveldata1 && $agleveldata2 && $agleveldata3) {
                $ogdata['parent1commission'] = isset($commissiondata[$agleveldata1['id']]['commission1']) 
                    ? $commissiondata[$agleveldata1['id']]['commission1'] 
                    : 0;
                $ogdata['parent2commission'] = isset($commissiondata[$agleveldata2['id']]['commission2']) 
                    ? $commissiondata[$agleveldata2['id']]['commission2'] 
                    : 0;
                $ogdata['parent3commission'] = isset($commissiondata[$agleveldata3['id']]['commission3']) 
                    ? $commissiondata[$agleveldata3['id']]['commission3'] 
                    : 0;
            }
        } else { // 默认按比例
            if ($agleveldata1 && $agleveldata2 && $agleveldata3) {
                $ogdata['parent1commission'] = $agleveldata1['commission1'] * $order['money'] * 0.01;
                $ogdata['parent2commission'] = $agleveldata2['commission2'] * $order['money'] * 0.01;
                $ogdata['parent3commission'] = $agleveldata3['commission3'] * $order['money'] * 0.01;
            }
        }

        // 分发佣金
        if (!empty($ogdata['parent1']) && $ogdata['parent1commission'] > 0) {
            $totalcommission += $ogdata['parent1commission'];
            \app\admin\common\Member::addcommission($aid, $ogdata['parent1'], $mid, $ogdata['parent1commission'], '下级购买奖励2');
        }
        if (!empty($ogdata['parent2']) && $ogdata['parent2commission'] > 0) {
            $totalcommission += $ogdata['parent2commission'];
            \app\admin\common\Member::addcommission($aid, $ogdata['parent2'], $mid, $ogdata['parent2commission'], '���二级购买奖���');
        }
        if (!empty($ogdata['parent3']) && $ogdata['parent3commission'] > 0) {
            $totalcommission += $ogdata['parent3commission'];
            \app\admin\common\Member::addcommission($aid, $ogdata['parent3'], $mid, $ogdata['parent3commission'], '下三级购买奖励');
        }

        // 提升等级
        if (!empty($ogdata['parent1'])) {
            \app\admin\common\Member::triggerUplv($aid, $ogdata['parent1'], [
                'scene' => \app\admin\common\MemberUpgradeScene::REFERRAL_REWARD,
                'source_type' => 'form_order',
                'source_id' => strval($order['id'] ?? $orderid),
            ]);
        }
    }

    // 处理商家货款
    if ($order['bid'] != 0) { // 入驻商家的货款
        $binfo = Db::name('business')->where('aid', $aid)->where('id', $order['bid'])->find();
        if ($binfo) {
            $totalmoney = $order['money'] - $totalcommission;
            if ($totalmoney > 0) {
                $totalmoney = $totalmoney * (100 - $binfo['feepercent']) * 0.01;
            }
            \app\admin\common\Business::addmoney($aid, $order['bid'], $totalmoney, '表单支付 订单号：' . $order['ordernum']);
        }
    }

    // 处理发货信息录入（微信小程序+微信支付）
    if ($order['platform'] == 'wx' && $order['paytypeid'] == 2) {
        \app\admin\common\Order::wxShipping($order['aid'], $order, 'form');
    }

    // 检查并处理余额充值逻辑
    if ($form['jinruyue'] == 1) { // 判断是否开启进入余额
        // 确认支付是否成功
        if ($order['paytypeid'] == 2) { // 假设 status=1 表示支付成功
            // 添加到用户余额
            
              \app\admin\common\Member::addmoney($order['aid'],$mid,$order['money'],'表单充值');
              \app\admin\common\Member::addscore($order['aid'],$mid,$order['money'],'表单充值');
            // 记录余额变动日志（假设有一个 balance_log 表）
     
        }
    }

    // 发送模板消息
    $tmplcontent = [];
    $tmplcontent['first'] = '有客户提交表单成功';
    $tmplcontent['remark'] = '点击查看详情~';
    $tmplcontent['keyword1'] = $order['title'];
    $tmplcontent['keyword2'] = date('Y-m-d H:i');
    \app\admin\common\Wechat::sendhttmpl(
        $aid,
        $order['bid'],
        'tmpl_formsub',
        $tmplcontent,
        m_url('admin/form/formdetail?id=' . $orderid, $aid)
    );

    // 发送新订单通知给管理员
    \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
        'consignee' => $order['consignee'],
        'phone' => $order['tel'],
        'ordernum' => $order['ordernum'],
        'amount' => $order['money']
    ]);

    // 可选：返回或记录支付处理结果
}

	//工单支付
	public static function workorder_pay($orderid){
		$order = Db::name('workorder_order')->where('id',$orderid)->find();
		$aid = $order['aid'];

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'workorder');
        }

		$tmplcontent = [];
		$tmplcontent['first'] = '有客户提交工单成功';
		$tmplcontent['remark'] = '请进入电脑端后台进行查看~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = date('Y-m-d H:i');
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_formsub',$tmplcontent);
	}

	//付费查看页面
	public static function designerpage_pay($orderid){
		$order = Db::name('designerpage_order')->where('id',$orderid)->find();
		\app\admin\common\Order::collect($order,'designerpage');
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'designerpage');
        }
	}
	//购买优惠券
	public static function coupon_pay($orderid){
		$order = Db::name('coupon_order')->where('id',$orderid)->find();
		\app\admin\common\Coupon::send($order['aid'],$order['mid'],$order['cpid']);
		\app\admin\common\Order::collect($order,'coupon');
//发货信息录入 微信小程序+微信支付
if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
	\app\admin\common\Order::wxShipping($order['aid'],$order,'coupon');
}
	}
// 买单
public static function maidan_pay($orderid){
    // 开启事务
    Db::startTrans();
    try {
        // 获取买单订单信息
        $order = Db::name('maidan_order')->where('id', $orderid)->find();
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        // 获取会员信息
        $member = Db::name('member')->where('id', $order['mid'])->find();
        if (!$member) {
            throw new \Exception('会员信息不存在');
        }

        $aid = $order['aid'];
        $mid = $order['mid'];
        $bid = $order['bid'];
        $moneydkmoney = round(floatval($order['moneydkmoney'] ?? 0), 2);

        // 买单余额抵扣改为支付成功后再执行，避免用户未完成支付时提前扣款。
        if($moneydkmoney > 0){
            $deductRemark = '买单余额支付-'.$order['ordernum'];
            $deductRes = \app\admin\common\Member::addmoney($aid, $mid, -$moneydkmoney, $deductRemark, $orderid);
            if(!is_array($deductRes) || intval($deductRes['status'] ?? 0) !== 1){
                $deductMsg = is_array($deductRes) ? ($deductRes['msg'] ?? '余额扣减失败') : '余额扣减失败';
                \think\facade\Log::error('买单支付成功后余额扣减失败', [
                    'order_id' => $orderid,
                    'ordernum' => $order['ordernum'],
                    'mid' => $mid,
                    'moneydkmoney' => $moneydkmoney,
                    'result' => $deductRes,
                ]);
                throw new \Exception($deductMsg);
            }
        }

	        // 买单奖励基数：可配置按原始金额或按实际支付金额计算
	        $originalAmount = $order['money'] ?? 0; // 原始金额
	        $payAmount = $order['paymoney'] ?? 0; // 实际支付金额
	        $disprice = $order['disprice'] ?? 0; // 会员折扣
	        $scoredk = $order['scoredk'] ?? 0; // 积分抵扣
	        $couponmoney = $order['couponmoney'] ?? 0; // 优惠券抵扣

        // 计算总抵扣金额（包含所有抵扣方式）
        $totalDeduction = $disprice + $scoredk + $couponmoney + $moneydkmoney;

        // 验证：原始金额应该等于实际支付金额加上总抵扣金额
        $calculatedOriginal = $payAmount + $totalDeduction;

	        // 获取商户系统设置
	        $sysset = Db::name('business_sysset')->where('aid', $aid)->find();
	        $rewardIncludeDeduct = $sysset && isset($sysset['maidan_reward_deduct_gift_enabled'])
	            ? intval($sysset['maidan_reward_deduct_gift_enabled']) == 1
	            : true;

	        // 获取商户信息（用于获取抽佣费率）
	        $business = Db::name('business')->where('aid', $aid)->where('id', $bid)->find();
	        $businessFeepercent = $business ? $business['feepercent'] : 0; // 商户抽佣费率

	        // 确定奖励计算基数
	        $baseAmount = $rewardIncludeDeduct ? $originalAmount : $payAmount;
	        $rewardBase = $baseAmount; // 默认按流水百分比计算

	        // 如果设置为按抽佣百分比计算（值为2）
	        if ($sysset && $sysset['maidan_reward_type'] == 2) {
	            $rewardBase = $baseAmount * $businessFeepercent / 100; // 按奖励基数计算抽佣金额作为基数
	        }

	        // 记录奖励计算日志
	        $rewardType = ($sysset && $sysset['maidan_reward_type'] == 2) ? '抽佣百分比' : '流水百分比';
	        $rewardBaseSource = $rewardIncludeDeduct ? '原始金额(含抵扣)' : '实际支付金额(不含抵扣)';
	        $logDetail = "买单奖励计算详情：原始金额={$originalAmount}，实际支付={$payAmount}，会员折扣={$disprice}，积分抵扣={$scoredk}，优惠券抵扣={$couponmoney}，余额抵扣={$moneydkmoney}，总抵扣={$totalDeduction}，奖励基数来源={$rewardBaseSource}，计算方式={$rewardType}";
	        if ($sysset && $sysset['maidan_reward_type'] == 2) {
	            $logDetail .= "，商户抽佣费率={$businessFeepercent}%，奖励基数={$rewardBase}";
        } else {
            $logDetail .= "，奖励基数={$rewardBase}";
        }
        \think\facade\Log::write('买单奖励计算：' . $logDetail);
        
        // 检查支付方式是否允许赠送奖励
        $canReward = true; // 默认允许
        if ($sysset && isset($sysset['maidan_reward_paytype'])) {
            $allowedPaytypes = !empty($sysset['maidan_reward_paytype']) ? explode(',', $sysset['maidan_reward_paytype']) : [];
            if (!empty($allowedPaytypes)) {
                // 根据订单的 paytypeid 判断支付方式
                $orderPaytypeid = $order['paytypeid'] ?? 0;
                $currentPaytype = '';
                if ($orderPaytypeid == 1) {
                    $currentPaytype = 'balance'; // 余额支付
                } else {
                    // 除余额支付外，其他都归为微信支付（包括普通微信、中金支付、视频号等）
                    $currentPaytype = 'wechat'; // 微信支付
                }
                // 检查当前支付方式是否在允许列表中
                $canReward = in_array($currentPaytype, $allowedPaytypes);
                \think\facade\Log::write('买单奖励支付方式检查：订单支付方式ID=' . $orderPaytypeid . '，对应类型=' . $currentPaytype . '，允许列表=' . implode(',', $allowedPaytypes) . '，是否允许=' . ($canReward ? '是' : '否'));
            } else {
                // 配置为空，表示不赠送任何奖励
                $canReward = false;
                \think\facade\Log::write('买单奖励支付方式检查：配置为空，不赠送奖励');
            }
        }
        
        // 给买单用户发放贡献值和黄积分（需要检查支付方式）
        if ($canReward && $sysset && $sysset['maidan_user_contribution_percent'] > 0) {
            $userContribution = $rewardBase * $sysset['maidan_user_contribution_percent'] / 100;
            if ($userContribution > 0) {
                // 使用通用函数增加用户贡献值
                \app\admin\common\Member::addgongxianzhi($aid, $mid, $userContribution, '买单'.t('贡献值').'奖励，订单号：' . $order['ordernum'] . '，计算方式：' . $rewardType, $orderid);
            }
        }
        
        if ($canReward && $sysset && $sysset['maidan_user_yellow_score_percent'] > 0) {
            $userYellowScore = $rewardBase * $sysset['maidan_user_yellow_score_percent'] / 100;
            if ($userYellowScore > 0) {
                // 使用通用函数增加用户黄积分
                \app\admin\common\Member::addscorehuang($aid, $mid, $userYellowScore, '买单'.t('黄积分').'奖励，订单号：' . $order['ordernum'] . '，计算方式：' . $rewardType, '买单奖励');
            }
        }
        
        // 给商家管理员发放贡献值和黄积分（需要检查支付方式）
        if ($canReward && $bid > 0) {
            // 查找商家的管理员ID
            $business = Db::name('business')->where('aid', $aid)->where('id', $bid)->find();
            if ($business && $business['mid2'] > 0) {
                $adminMid = $business['mid2'];
                
                // 给商家管理员发放贡献值
                if ($sysset && $sysset['maidan_admin_contribution_percent'] > 0) {
                    $adminContribution = $rewardBase * $sysset['maidan_admin_contribution_percent'] / 100;
                    if ($adminContribution > 0) {
                        // 使用通用函数增加商家管理员贡献值
                        \app\admin\common\Member::addgongxianzhi($aid, $adminMid, $adminContribution, '买单管理员'.t('贡献值').'奖励，订单号：' . $order['ordernum'] . '，计算方式：' . $rewardType, $orderid);
                    }
                }
                
                // 给商家管理员发放黄积分
                if ($sysset && $sysset['maidan_admin_yellow_score_percent'] > 0) {
                    $adminYellowScore = $rewardBase * $sysset['maidan_admin_yellow_score_percent'] / 100;
                    if ($adminYellowScore > 0) {
                        // 使用通用函数增加商家管理员黄积分
                        \app\admin\common\Member::addscorehuang($aid, $adminMid, $adminYellowScore, '买单管理员'.t('黄积分').'奖励，订单号：' . $order['ordernum'] . '，计算方式：' . $rewardType, '买单管理员奖励');
                    }
                }
            }
        }

        // 处理优惠券
        if($order['couponrid']){
            Db::name('coupon_record')->where('id', $order['couponrid'])->update(['status' => 1, 'usetime' => time()]);
        }

        // 收集订单
        \app\admin\common\Order::collect($order, 'maidan');

        $merchantSubsidyQueueOrder = 0;
        if (self::tableExists('paidui_set')) {
            try {
                $paiduiSet = Db::name('paidui_set')->where('aid', $aid)->find();
                $merchantSubsidyQueueOrder = intval($paiduiSet['merchant_subsidy_queue_order'] ?? 0);
            } catch (\Throwable $e) {
                $merchantSubsidyQueueOrder = 0;
            }
        }

        if ($merchantSubsidyQueueOrder === 1) {
            try {
                self::handleMerchantSubsidy($orderid, $mid, $bid, $originalAmount, $aid);
            } catch (\Exception $e) {
                \think\facade\Log::write('【商家补贴】处理失败:' . $e->getMessage());
            }
        }

        // 去排队
        if(($order['paidui_jiang'] > 0 || $order['paidui_give'] > 0) && self::canUsePaiduiQueueTables()){
            $buss = Db::name('paidui_maidan_set')->where('aid', $order['aid'])->where('bid', 0)->find();
            if($order['bid'] > 0){
                $buss = Db::name('paidui_maidan_set')->where('aid', $order['aid'])->where('bid', $order['bid'])->find();
            }

         

            if(!empty($buss) && intval($buss['type_status'] ?? 0) == 1){
                $paiduidata = [
                    'aid' => intval($order['aid']),
                    'mid' => intval($order['mid']),
                    'bid' => intval($order['bid']),
                    'type_status' => intval($buss['type_status']),
                    'type' => intval($buss['type']),
                    'pool_id' => \app\admin\common\PaiduiPoolService::resolveMaidanPoolId($order['aid'], intval($order['bid']), $order['totalprice'] ?? ($order['money'] ?? 0)),
                    'orderid' => intval($order['id']),
                    'ordergoodid' => 0,
                    'paidui_jiang' => number_format($order['paidui_jiang'], 5, '.', ''),
                    'paidui_give' => number_format($order['paidui_give'], 5, '.', ''),
                    'give_shengyu' => number_format($order['paidui_jiang'], 5, '.', ''),
                    'shiji_dedao' => 0.00000,
                    'createtime' => time(),
                    'status' => 0,
                    'qu' => 1,
                    'jifen' => 0.00000,
                    'qutime' => 0,
                    'yue' => 0.00000,
                    'ischoujiang' => 0
                ];

             
                
                try {
                    $paiduiId = Db::name('paidui_list')->insertGetId($paiduidata);

                    // 检查是否启用分期模式，如果启用则创建分期记录
                    if($paiduiId && \app\admin\common\PaiduiPeriods::isPeriodsEnabled($order['aid'])) {
                        $periods_result = \app\admin\common\PaiduiPeriods::createPeriods(
                            $order['aid'], 
                            $paiduiId, 
                            $order['mid'], 
                            $order['bid'], 
                            $order['paidui_jiang']
                        );
                        
                        if($periods_result['status'] == 1) {
                            \think\facade\Log::write("排队分期创建成功: 排队ID={$paiduiId}, 分期数={$periods_result['periods']}, 总金额={$periods_result['total_amount']}");
                        } else {
                            \think\facade\Log::error("排队分期创建失败: 排队ID={$paiduiId}, 错误信息={$periods_result['msg']}");
                        }
                    }

                    if(!empty($buss) && intval($buss['model'] ?? 0) == 1){
                      
                        \app\admin\common\Member::givepaiduijiangModel($order['aid'], $order['mid'], $paiduiId, $order['ordernum'], $buss);
                    }
                    else{
                       
                        // 排队给奖励
                        \app\admin\common\Member::givepaiduijiang($order['aid'], $order['mid'], $order['id'], 0, $order['ordernum'], 1);
                    }
                } catch (\Throwable $e) {
                   
                    throw $e;
                }
            }
        }

        // 提交事务前再次验证数据
        if(isset($paiduiId) && self::tableExists('paidui_list')) {
            $finalCheck = Db::name('paidui_list')->where('id', $paiduiId)->find();
          
        }

        // 处理城市代理佣金（买单支付成功后）
        try {
            \app\admin\common\CityAgent::onOrderPaid($order, 'maidan');
        } catch (\Exception $e) {
            \think\facade\Log::write('买单城市代理佣金处理失败:' . $e->getMessage());
            // 不影响主流程，只记录日志
        }

        // 处理商家补贴功能（买单支付成功后）
        if ($merchantSubsidyQueueOrder !== 1) {
            try {
                self::handleMerchantSubsidy($orderid, $mid, $bid, $originalAmount, $aid);
            } catch (\Exception $e) {
                \think\facade\Log::write('【商家补贴】处理失败:' . $e->getMessage());
                // 不影响主流程，只记录日志
            }
        }

        // 处理红包池转入（买单支付成功后）
        try {
            $hongbaoResult = \app\admin\common\HongbaoPoolService::consumeReturnToHongbao($aid, $bid, $mid, $originalAmount, $orderid);
            \think\facade\Log::write('【红包池】处理结果：' . json_encode($hongbaoResult, JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            \think\facade\Log::write('【红包池】处理异常:' . $e->getMessage() . '，trace:' . $e->getTraceAsString());
            // 不影响主流程，只记录日志
        }

        // 分期福利插件（买单）
        try {
            \app\adminapi\controller\FenqifuliApi::createPeriodsForOrder(
                $aid, $mid, $bid, $orderid,
                $order['ordernum'], $originalAmount
            );
        } catch (\Exception $e) {
            \think\facade\Log::error('分期福利创建失败(买单)-订单ID:' . $orderid . '-错误:' . $e->getMessage());
        }

	        // 提交事务
	        Db::commit();

	        // 买单返现属于插件奖励链路，失败不影响买单支付主流程。
	        try {
	            if (class_exists('\\app\\plugin\\maidan\\common\\MaidanCashbackService')) {
	                \app\plugin\maidan\common\MaidanCashbackService::process($aid, $order);
	            }
	        } catch (\Throwable $e) {
	            \think\facade\Log::error('买单返现处理异常: ' . $e->getMessage(), [
	                'aid' => $aid,
	                'order_id' => $orderid,
	                'ordernum' => $order['ordernum'] ?? '',
	            ]);
	        }

	        // 公众号通知 买单支付成功
	        $tmplcontent = [];
        $tmplcontent['first'] = '有新买单订单支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname'] ? $member['nickname'] : '会员';
        $tmplcontent['keyword2'] = $order['ordernum'];
        $tmplcontent['keyword3'] = ($order['money'] ? $order['money'] : $order['paymoney']).'元';
        $tmplcontent['keyword4'] = $order['title'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing8'] = \app\admin\common\Mendian::getNameWithBusines($order);
        $tmplcontentNew['phrase18'] = $member['nickname'] ? $member['nickname'] : '会员';
        $tmplcontentNew['character_string2'] = $order['ordernum'];
        $tmplcontentNew['amount5'] = $order['money'] ? $order['money'] : ($order['paymoney'] ? $order['paymoney'] : '0.00');
        $tmplcontentNew['thing3'] = $order['title'];
        \app\admin\common\Wechat::sendhttmpl($aid, $order['bid'], 'tmpl_maidanpay', $tmplcontent, m_url('admin/order/maidanlog', $aid), $order['mdid'], $tmplcontentNew);

	        // ================= 中金支付余额支付分账处理（买单订单） =================
	        try {
	            $payorderInfo = Db::name('payorder')->where('orderid', $orderid)->where('type', 'maidan')->find();
	            self::handleCpcnDeductSplitAfterPay('maidan', $order, $payorderInfo);
	        } catch (\Exception $e) {
	            \think\facade\Log::error('买单订单余额支付分账异常', [
	                'order_id' => $orderid,
	                'error' => $e->getMessage(),
	                'trace' => $e->getTraceAsString()
	            ]);
	        }
	        // =============================================

        // 事务提交成功后，发送云喇叭播报通知
        try {
            \app\admin\model\CloudSpeaker::sendMaidanPayNotification($order);
        } catch (\Exception $e) {
            // 云喇叭播报失败不影响主流程，只记录日志
            \think\facade\Log::write('云喇叭播报失败：' . $e->getMessage());
        }

    } catch(\Exception $e) {
        // 回滚事务
        Db::rollback();

        throw $e;
    }
	}

    private static function handleCpcnDeductSplitAfterPay($orderType, $order, $payorderInfo = null)
    {
        $orderid = intval($order['id'] ?? 0);
        $orderType = $orderType === 'maidan' ? 'maidan' : 'shop';
        $logPrefix = $orderType === 'maidan' ? '买单订单' : '商城订单';
        $orderTable = $orderType === 'maidan' ? 'maidan_order' : 'shop_order';
        $orderData = self::buildCpcnDeductSplitOrderData($orderType, $order, $payorderInfo);

        if (!$orderData) {
            \think\facade\Log::info($logPrefix . '跳过余额支付分账：订单数据无效', [
                'order_id' => $orderid,
            ]);
            return;
        }

        $totalDeduct = round(
            floatval($orderData['balance_deduct'] ?? 0) +
            floatval($orderData['score_deduct'] ?? 0) +
            floatval($orderData['coupon_deduct'] ?? 0),
            2
        );
        if ($totalDeduct <= 0) {
            \think\facade\Log::info($logPrefix . '跳过余额支付分账：无抵扣金额', [
                'order_id' => $orderid,
                'paytypeid' => $payorderInfo['paytypeid'] ?? 'null',
            ]);
            return;
        }

        \think\facade\Log::info($logPrefix . '开始余额支付分账检测', [
            'order_id' => $orderid,
            'order_num' => $orderData['ordernum'] ?? '',
            'paytypeid' => $payorderInfo['paytypeid'] ?? 'null',
            'commission_rate' => ($orderData['commission_rate'] ?? 0) . '%',
            'deduct_summary' => [
                'balance_deduct' => $orderData['balance_deduct'] ?? 0,
                'score_deduct' => $orderData['score_deduct'] ?? 0,
                'coupon_deduct' => $orderData['coupon_deduct'] ?? 0,
                'pure_balance_pay_amount' => $orderData['pure_balance_pay_amount'] ?? 0,
            ],
        ]);

        $result = \app\admin\service\CpcnBalanceSplitService::checkAndCreateSplitRecord($orderData, $orderTable);

        \think\facade\Log::info($logPrefix . '余额支付分账结果', [
            'order_id' => $orderid,
            'result' => $result,
        ]);
    }

    private static function buildCpcnDeductSplitOrderData($orderType, $order, $payorderInfo = null)
    {
        if (!$order || empty($order['id'])) {
            return null;
        }

        $businessInfo = null;
        $commissionRate = 0;
        if (!empty($order['bid'])) {
            $businessInfo = Db::name('business')
                ->where('id', intval($order['bid']))
                ->field('id,feepercent')
                ->find();
            if ($businessInfo) {
                $commissionRate = floatval($businessInfo['feepercent'] ?? 0);
            }
        }

        $pureBalancePayAmount = 0;
        if (intval($payorderInfo['paytypeid'] ?? 0) === 1) {
            $pureBalancePayAmount = round(max(0, floatval($payorderInfo['money'] ?? 0)), 2);
        }

        if ($orderType === 'maidan') {
            $balanceDeduct = round(max(0, floatval($order['moneydkmoney'] ?? 0)), 2);
            if ($pureBalancePayAmount > 0) {
                if ($balanceDeduct <= 0 || abs($balanceDeduct - $pureBalancePayAmount) >= 0.01) {
                    $balanceDeduct = round($balanceDeduct + $pureBalancePayAmount, 2);
                }
            }

            return [
                'aid' => intval($order['aid'] ?? 0),
                'bid' => intval($order['bid'] ?? 0),
                'mid' => intval($order['mid'] ?? 0),
                'id' => intval($order['id'] ?? 0),
                'ordernum' => $order['ordernum'] ?? '',
                'price' => floatval($order['money'] ?? $order['totalprice'] ?? $order['price'] ?? 0),
                'realprice' => floatval($order['paymoney'] ?? $order['realprice'] ?? $order['payprice'] ?? 0),
                'balance_deduct' => $balanceDeduct,
                'score_deduct' => round(max(0, floatval($order['scoredk'] ?? 0)), 2),
                'score_deduct_num' => intval($order['decscore'] ?? 0),
                'coupon_deduct' => round(max(0, floatval($order['couponmoney'] ?? 0)), 2),
                'commission_rate' => $commissionRate,
                'pure_balance_pay_amount' => $pureBalancePayAmount,
            ];
        }

        $balanceDeduct = round(max(0, floatval($order['scoredk_moneyyu'] ?? 0)), 2);
        if ($pureBalancePayAmount > 0) {
            if ($balanceDeduct <= 0 || abs($balanceDeduct - $pureBalancePayAmount) >= 0.01) {
                $balanceDeduct = round($balanceDeduct + $pureBalancePayAmount, 2);
            }
        }

        return [
            'aid' => intval($order['aid'] ?? 0),
            'bid' => intval($order['bid'] ?? 0),
            'mid' => intval($order['mid'] ?? 0),
            'id' => intval($order['id'] ?? 0),
            'ordernum' => $order['ordernum'] ?? '',
            'price' => floatval($order['totalprice'] ?? $order['price'] ?? 0),
            'realprice' => floatval($order['realprice'] ?? $order['payprice'] ?? 0),
            'balance_deduct' => $balanceDeduct,
            'score_deduct' => round(max(0, floatval($order['scoredk_money'] ?? 0)), 2),
            'score_deduct_num' => intval($order['scoredkscore'] ?? 0),
            'coupon_deduct' => round(max(0, floatval($order['coupon_money'] ?? 0)), 2),
            'commission_rate' => $commissionRate,
            'pure_balance_pay_amount' => $pureBalancePayAmount,
        ];
    }

    public static function getPaymentCompositionFromOrderData($orderData)
    {
        if (empty($orderData) || !is_array($orderData)) {
            return [];
        }

        $pureBalancePayAmount = round(max(0, floatval($orderData['pure_balance_pay_amount'] ?? 0)), 2);
        $payAmount = round(max(0, floatval($orderData['realprice'] ?? $orderData['pay_amount'] ?? 0)), 2);

        return [
            'direct_pay' => round(max(0, $payAmount - $pureBalancePayAmount), 2),
            'balance_amount' => round(max(0, floatval($orderData['balance_deduct'] ?? $orderData['money_deduct'] ?? 0)), 2),
            'score_amount' => round(max(0, floatval($orderData['score_deduct'] ?? 0)), 2),
            'coupon_amount' => round(max(0, floatval($orderData['coupon_deduct'] ?? 0)), 2),
        ];
    }

    public static function buildPaymentCompositionRemark($composition, $scene = 'general')
    {
        if (empty($composition) || !is_array($composition)) {
            return '';
        }

        $directPay = round(max(0, floatval($composition['direct_pay'] ?? 0)), 2);
        $balanceAmount = round(max(0, floatval($composition['balance_amount'] ?? 0)), 2);
        $scoreAmount = round(max(0, floatval($composition['score_amount'] ?? 0)), 2);
        $couponAmount = round(max(0, floatval($composition['coupon_amount'] ?? 0)), 2);

        if ($directPay <= 0 && $balanceAmount <= 0 && $scoreAmount <= 0 && $couponAmount <= 0) {
            return '';
        }

        $parts = [];
        if ($scene === 'direct_split') {
            $parts[] = '本次为现金支付分账';
        } elseif ($scene === 'deduct_split') {
            $parts[] = '本次为余额抵扣分账';
        } elseif ($scene === 'balance_row') {
            $parts[] = '本条为余额抵扣记录';
        }

        $balanceLabel = ($directPay <= 0 && $scoreAmount <= 0 && $couponAmount <= 0) ? '余额支付' : '余额抵扣';

        if ($directPay > 0) {
            $parts[] = '现金支付：￥' . number_format($directPay, 2, '.', '');
        }
        if ($balanceAmount > 0) {
            $parts[] = $balanceLabel . '：￥' . number_format($balanceAmount, 2, '.', '');
        }
        if ($scoreAmount > 0) {
            $parts[] = '积分抵扣：￥' . number_format($scoreAmount, 2, '.', '');
        }
        if ($couponAmount > 0) {
            $parts[] = '优惠券抵扣：￥' . number_format($couponAmount, 2, '.', '');
        }

        return implode('；', $parts);
    }

    public static function mergeRemarkText($originRemark, $paymentRemark)
    {
        $originRemark = trim((string)$originRemark);
        $paymentRemark = trim((string)$paymentRemark);

        if ($paymentRemark === '') {
            return $originRemark;
        }
        if ($originRemark === '') {
            return $paymentRemark;
        }
        if (strpos($originRemark, $paymentRemark) !== false) {
            return $originRemark;
        }

        return rtrim($originRemark, '；; ') . '；' . $paymentRemark;
    }

    public static function getPaymentCompositionByPayorder($payorder)
    {
        if (empty($payorder) || !is_array($payorder)) {
            return [];
        }

        static $cache = [];
        $cacheKey = implode(':', [
            intval($payorder['aid'] ?? 0),
            intval($payorder['id'] ?? 0),
            strval($payorder['type'] ?? ''),
            intval($payorder['orderid'] ?? 0),
            strval($payorder['ordernum'] ?? '')
        ]);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $type = strval($payorder['type'] ?? '');
        $paytypeid = intval($payorder['paytypeid'] ?? 0);
        $composition = [];

        if ($type === 'maidan') {
            $order = Db::name('maidan_order')
                ->where('id', intval($payorder['orderid'] ?? 0))
                ->find();

            if ($order) {
                $directPay = round(max(0, floatval($order['paymoney'] ?? $order['realprice'] ?? $order['payprice'] ?? 0)), 2);
                $balanceAmount = round(max(0, floatval($order['moneydkmoney'] ?? 0)), 2);
                $pureBalancePayAmount = round(max(0, floatval($payorder['money'] ?? 0)), 2);

                if ($paytypeid === 1) {
                    $directPay = 0;
                    if ($balanceAmount <= 0 || abs($balanceAmount - $pureBalancePayAmount) >= 0.01) {
                        $balanceAmount = round($balanceAmount + $pureBalancePayAmount, 2);
                    }
                }

                $composition = [
                    'direct_pay' => $directPay,
                    'balance_amount' => $balanceAmount,
                    'score_amount' => round(max(0, floatval($order['scoredk'] ?? 0)), 2),
                    'coupon_amount' => round(max(0, floatval($order['couponmoney'] ?? 0)), 2),
                ];
            }
        } elseif ($type === 'shop') {
            $order = Db::name('shop_order')
                ->where('id', intval($payorder['orderid'] ?? 0))
                ->find();

            if ($order) {
                $directPay = round(max(0, floatval($order['realprice'] ?? $order['payprice'] ?? $payorder['money'] ?? 0)), 2);
                $composition = [
                    'direct_pay' => $directPay,
                    'balance_amount' => round(max(0, floatval($order['scoredk_moneyyu'] ?? 0)), 2),
                    'score_amount' => round(max(0, floatval($order['scoredk_money'] ?? 0)), 2),
                    'coupon_amount' => round(max(0, floatval($order['coupon_money'] ?? 0)), 2),
                ];
            }
        } elseif ($type === 'shop_hb') {
            $orders = Db::name('shop_order')
                ->where('aid', intval($payorder['aid'] ?? 0))
                ->where('ordernum', 'like', strval($payorder['ordernum'] ?? '') . '_%')
                ->select()
                ->toArray();

            if ($orders) {
                $composition = [
                    'direct_pay' => 0,
                    'balance_amount' => 0,
                    'score_amount' => 0,
                    'coupon_amount' => 0,
                ];
                foreach ($orders as $order) {
                    $composition['direct_pay'] += round(max(0, floatval($order['realprice'] ?? $order['payprice'] ?? $order['totalprice'] ?? 0)), 2);
                    $composition['balance_amount'] += round(max(0, floatval($order['scoredk_moneyyu'] ?? 0)), 2);
                    $composition['score_amount'] += round(max(0, floatval($order['scoredk_money'] ?? 0)), 2);
                    $composition['coupon_amount'] += round(max(0, floatval($order['coupon_money'] ?? 0)), 2);
                }
                foreach ($composition as $key => $value) {
                    $composition[$key] = round($value, 2);
                }
            }
        } elseif ($type === 'balance') {
            $order = Db::name('shop_order')
                ->where('id', intval($payorder['orderid'] ?? 0))
                ->find();

            $composition = [
                'direct_pay' => 0,
                'balance_amount' => round(max(0, floatval($payorder['money'] ?? 0)), 2),
                'score_amount' => round(max(0, floatval($order['scoredk_money'] ?? 0)), 2),
                'coupon_amount' => round(max(0, floatval($order['coupon_money'] ?? 0)), 2),
            ];
        }

        $cache[$cacheKey] = $composition;
        return $composition;
    }

    public static function getPaymentCompositionRemarkByPayorder($payorder, $scene = 'general')
    {
        $composition = self::getPaymentCompositionByPayorder($payorder);
        return self::buildPaymentCompositionRemark($composition, $scene);
    }

    public static function findPrimaryPayorderByOrdernum($aid, $ordernum)
    {
        if (!$aid || !$ordernum) {
            return null;
        }

        return Db::name('payorder')
            ->where('aid', intval($aid))
            ->where('ordernum', strval($ordernum))
            ->where('type', '<>', 'balance')
            ->order('id', 'desc')
            ->find();
    }

    /**
     * 处理商家补贴功能
     * @param int $order_id 买单订单ID
     * @param int $customer_mid 消费者会员ID
     * @param int $bid 商户ID
     * @param float $order_amount 订单金额
     * @param int $aid 应用ID
     */
	    private static function handleMerchantSubsidy($order_id, $customer_mid, $bid, $order_amount, $aid)
	    {
	        try {
	            \think\facade\Log::write('【商家补贴】开始处理，订单ID:' . $order_id . '，消费者ID:' . $customer_mid . '，商户ID:' . $bid . '，订单金额:' . $order_amount);

	            if ($bid <= 0) {
	                \think\facade\Log::write('【商家补贴】跳过：非商户订单(bid<=0)');
	                return; // 非商户订单，无需处理
	            }

	            if (!self::canUsePaiduiQueueTables()) {
	                \think\facade\Log::write('【商家补贴】跳过：排队插件未安装');
	                return;
	            }

	            // 获取商户信息，查找绑定的管理员
	            $business_info = Db::name('business')
	                ->where('aid', $aid)
	                ->where('id', $bid)
	                ->find();
            
            if (!$business_info) {
                \think\facade\Log::write('【商家补贴】跳过：未找到商户信息');
                return;
            }
            
            if (empty($business_info['mid2'])) {
                \think\facade\Log::write('【商家补贴】跳过：商户未绑定管理员(mid2为空)，商户名称:' . ($business_info['name'] ?? ''));
                return; // 商户未绑定管理员，无需处理
            }
            
            $admin_mid = $business_info['mid2'];
            \think\facade\Log::write('【商家补贴】找到绑定管理员，admin_mid:' . $admin_mid);
            
            // 调用商家补贴功能
            $result = \app\admin\common\ShareAcceleration::createMerchantSubsidy(
                $aid,
                $bid,
                $admin_mid,
                $customer_mid,
                $order_id,
                $order_amount
            );
            
            if ($result['status'] == 1) {
                \think\facade\Log::write('【商家补贴】创建成功，订单ID:' . $order_id . '，商户ID:' . $bid . '，管理员ID:' . $admin_mid);
	            } else {
	                \think\facade\Log::write('【商家补贴】创建失败：' . $result['msg']);
	            }

	        } catch (\Throwable $e) {
	            \think\facade\Log::error('【商家补贴】处理异常：' . $e->getMessage());
	        }
	    }

	//餐饮订单合并支付
	public static function restaurant_takeaway_hb_pay($orderid,$ordernum){
		$anchorOrder = Db::name('restaurant_takeaway_order')->where('id', intval($orderid))->find();
		if (!$anchorOrder) {
			return ['status' => 0, 'msg' => '餐饮外卖合并订单不存在'];
		}
		$orderlist = Db::name('restaurant_takeaway_order')
			->where('aid', intval($anchorOrder['aid']))
			->where('ordernum','like',$ordernum.'_%' )
			->select()
			->toArray();
		foreach($orderlist as $order){
			self::restaurant_takeaway_pay($order['id']);
		}
		return ['status' => 1, 'msg' => ''];
	}
	//餐饮订单
	public static function restaurant_takeaway_pay($orderid){
		$order = Db::name('restaurant_takeaway_order')->where('id',$orderid)->find();
		if (!$order) {
			return ['status' => 0, 'msg' => '餐饮外卖订单不存在'];
		}
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		\app\plugin\restaurant\common\RestaurantStoredValueCoupon::commit(
			intval($aid),
			intval($orderid),
			'restaurant_takeaway',
			'restaurant_takeaway_pay'
		);
			Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->update(['status'=>1]);
			$takeaway_set = Db::name('restaurant_takeaway_sysset')->where('aid',$aid)->where('bid',$order['bid'])->find();
			if($takeaway_set['confirm_auto']==1){ //自动接单
				Db::name('restaurant_takeaway_order')->where('id',$orderid)->update(['status'=>12]);
				Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->update(['status'=>12]);
			}
			try {
				if (
					\app\admin\common\PluginBusinessAuth::isAidPluginEnabled(intval($aid), 'timedlottery')
					&& class_exists('\app\plugin\timedlottery\common\TimedLotteryTaskService')
				) {
					\app\plugin\timedlottery\common\TimedLotteryTaskService::awardRestaurantTakeawayOrder($order);
				}
			} catch (\Throwable $e) {
				\think\facade\Log::warning('餐饮外卖订单抽奖任务发放失败，订单ID：' . intval($orderid) . '，原因：' . $e->getMessage());
			}

			//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'restaurant',$order['totalprice'],$order['id']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}

			\app\admin\common\Member::triggerUplv($aid, $mid, [
				'scene' => \app\admin\common\MemberUpgradeScene::ORDER_PAID,
				'source_type' => 'restaurant_takeaway_order',
				'source_id' => strval($order['id'] ?? $orderid),
			]);
			\app\admin\custom\Restaurant::maybeSettleMemberCommissionOnPay($aid, 'restaurant_takeaway', $order);
        \app\admin\custom\Restaurant::print('restaurant_takeaway', $order);

        if($takeaway_set['confirm_auto']==1){ //自动接单
            //判断是否自动派单
            if($order['freight_type'] == 2){
                $peisong_set = \db('peisong_set')->where('aid',$aid)->find();
                if($peisong_set['express_wx_status'] == 1 && $peisong_set['express_wx_paidan'] == 1){
                    Db::name('restaurant_takeaway_order')->where('id',$orderid)->update(['express_type'=>'express_wx']);
                    \app\admin\custom\ExpressWx::addOrder('restaurant_takeaway_order',$order);
                }
            }
        }

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/restaurant/takeawayorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('restaurant/takeaway/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/restaurant/takeawayorder',$order['mdid']);

		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
		\app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
		\app\common\OrderTrigger::onTokenreleasePaidEvent($aid, $mid, 'restaurant_takeaway_paid', strval(intval($orderid)));
		return ['status' => 1, 'msg' => ''];
	}
    //餐饮订单
    public static function restaurant_booking_pay($orderid){
        $order = Db::name('restaurant_booking_order')->where('id',$orderid)->find();
        $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
        $mid = $order['mid'];
        Db::name('restaurant_booking_order_goods')->where('orderid',$orderid)->update(['status'=>1]);

        //支付后送券
        $couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'restaurant',$order['totalprice'],$order['id']);
        if($couponlist){
            foreach($couponlist as $coupon){
                \app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
            }
        }

        \app\admin\common\Member::triggerUplv($aid, $mid, [
            'scene' => \app\admin\common\MemberUpgradeScene::ORDER_PAID,
            'source_type' => 'restaurant_booking_order',
            'source_id' => strval($order['id'] ?? $orderid),
        ]);
        \app\admin\custom\Restaurant::print('restaurant_booking', $order,'','',1);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'restaurant_booking');
        }

        //公众号通知 订单支付成功
        $tmplcontent = [];
        $tmplcontent['first'] = '有新预定支付成功';
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        \app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/restaurant/bookingorder', $aid),$order['mdid']);
        $tmplcontent['first'] = '恭喜您的预定订单已支付成功';
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('restaurant/booking/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/restaurant/bookingorder',$order['mdid']);

        //短信通知
        $rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
    }

	//餐饮订单合并支付
	public static function restaurant_shop_hb_pay($orderid,$ordernum){
		$anchorOrder = Db::name('restaurant_shop_order')->where('id', intval($orderid))->find();
		if (!$anchorOrder) {
			return ['status' => 0, 'msg' => '餐饮点餐合并订单不存在'];
		}
		$orderlist = Db::name('restaurant_shop_order')
			->where('aid', intval($anchorOrder['aid']))
			->where('ordernum', 'like', $ordernum . '_%')
			->select()
			->toArray();
		foreach($orderlist as $order){
			self::restaurant_shop_pay($order['id']);
		}
		return ['status' => 1, 'msg' => ''];
	}

    //餐饮订单
    public static function restaurant_shop_pay($orderid){
        $order = Db::name('restaurant_shop_order')->where('id',$orderid)->find();
        if (!$order) {
            return ['status' => 0, 'msg' => '餐饮点餐订单不存在'];
        }
        $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
        $bid = $order['bid'];
        $mid = $order['mid'];
        if (intval($order['status'] ?? 0) >= 1) {
            \app\plugin\restaurant\common\RestaurantStoredValueCoupon::commit(
                intval($aid),
                intval($orderid),
                'restaurant_shop',
                'restaurant_shop_pay'
            );
        }
        Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->update(['status'=>1]);

        //支付后送券
        $couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'restaurant',$order['totalprice'],$order['id']);
        if($couponlist){
            foreach($couponlist as $coupon){
                \app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
            }
        }

        \app\admin\common\Member::triggerUplv($aid, $mid, [
            'scene' => \app\admin\common\MemberUpgradeScene::ORDER_PAID,
            'source_type' => 'restaurant_shop_order',
            'source_id' => strval($order['id'] ?? $orderid),
        ]);
        \app\admin\custom\Restaurant::maybeSettleMemberCommissionOnPay($aid, 'restaurant_shop', $order);
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'restaurant_shop');
        }

        //结账单只在 paytime 已写入后打一次。餐后付款的后厨单已在下单/加菜打过，这里只打结账单；先付款则后厨和结账一起打。
        //货到付款（paytypeid=4）不会写 paytime，不能在这里打出未付款结账单，结账单留给收银结账。
        $restaurant_shop_sysset = Db::name('restaurant_shop_sysset')->where('aid', $aid)->where('bid', $bid)->find();
        $payAfter = intval($restaurant_shop_sysset['pay_after'] ?? 0) === 1;
        if (!empty($order['paytime'])) {
            $printTemplate = $payAfter ? 0 : '';
            \app\admin\custom\Restaurant::print('restaurant_shop',$order,[],$printTemplate);
        } elseif (!$payAfter) {
            \app\admin\custom\Restaurant::print('restaurant_shop',$order,[],1);
        }

        if (!empty($order['tableid'])) {
            \app\admin\custom\Restaurant::applyTableAfterPaid($aid, $bid, $order['tableid'], false);
        }

        //公众号通知 订单支付成功
        $tmplcontent = [];
        if($order['paytypeid'] != 4) {
            $tmplcontent['first'] = '有新点餐订单支付成功';
        } else {
            $tmplcontent['first'] = '有新点餐订单下单成功（线下支付），';
        }
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = $member['nickname']; //用户名
        $tmplcontent['keyword2'] = $order['ordernum'];//订单号
        $tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
        $tmplcontent['keyword4'] = $order['title'];//商品信息
        \app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/restaurant/shoporder', $aid),$order['mdid']);
        if($order['paytypeid'] != 4) {
            $tmplcontent['first'] = '恭喜您的点餐订单已支付成功';
        } else {
            $tmplcontent['first'] = '恭喜您的点餐订单下单成功';
        }
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('restaurant/shop/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/restaurant/shoporder',$order['mdid']);

        //短信通知
        $rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
        \app\common\OrderTrigger::onTokenreleasePaidEvent($aid, $mid, 'restaurant_shop_paid', strval(intval($orderid)));
        return ['status' => 1, 'msg' => ''];
    }



    	/**
	 * 物业费支付处理
	 * 直接通过payorder信息处理property_payment缴费记录和property_bill账单
	 * @param int $payorder_id 支付订单ID（这里的$orderid实际上是payorder的orderid字段值）
	 * @param string $ordernum 支付订单号
	 * @return array
	 */
	public static function property_pay($payorder_id, $ordernum=''){
		// 通过payorder信息查找对应的缴费记录
		$payorder = Db::name('payorder')->where('id', $payorder_id)->find();
		if(!$payorder) {
			return ['status'=>0,'msg'=>'支付订单不存在'];
		}
		
		$aid = $payorder['aid'];
		$bid = $payorder['bid'] ?? 0;
		$mid = $payorder['mid'];
		$amount = $payorder['money'];
		
		// 根据订单号查找对应的property_payment缴费记录
		$payment = Db::name('property_payment')
			->where('aid', $aid)
			->where('bid', $bid)
			->where('ordernum', $payorder['ordernum'])
			->find();
		
		if(!$payment) {
			return ['status'=>0,'msg'=>'缴费记录不存在'];
		}
		
		if($payment['payment_status'] == 1) {
			return ['status'=>0,'msg'=>'该缴费记录已处理'];
		}
		
		// 获取会员信息
		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
		if(!$member) return ['status'=>0,'msg'=>'会员信息不存在'];
		
		// 查找对应的订单记录，获取bill_ids
		$property_order = Db::name('property_order')
			->where('aid', $aid)
			->where('bid', $bid)
			->where('ordernum', $payorder['ordernum'])
			->find();
		
		$bills = [];
		if($property_order && !empty($property_order['bill_ids'])) {
			// 通过订单中的bill_ids来查找账单
			$bill_ids = explode(',', $property_order['bill_ids']);
			$bills = Db::name('property_bill')
				->where('aid', $aid)
				->where('bid', $bid)
				->where('id', 'in', $bill_ids)
				->where('bill_status', 0) // 未缴费状态
				->select()
				->toArray();
		} else {
			// 兼容旧逻辑：通过room_id和payment_period查找
			$bills = Db::name('property_bill')
				->where('aid', $aid)
				->where('bid', $bid)
				->where('room_id', $payment['room_id'])
				->where('bill_period', $payment['payment_period'])
				->where('bill_status', 0) // 未缴费状态
				->select()
				->toArray();
		}
		
		// 即使没有找到账单，也要更新缴费记录状态
		
		// 开启事务
		Db::startTrans();
		try {
			// 处理积分抵扣
			if($payment['deduct_score'] > 0) {
				// 验证用户积分是否足够
				if($member['score'] < $payment['deduct_score']) {
					throw new \Exception('用户积分不足，无法完成抵扣');
				}
				
				// 扣除用户积分
				$addscore_result = \app\admin\common\Member::addscore($aid, $mid, -$payment['deduct_score'], '物业费缴费积分抵扣');
				if($addscore_result['status'] == 0){
					throw new \Exception('积分扣除失败：' . $addscore_result['msg']);
				}
				
				// 创建积分抵扣记录
				$deduct_data = [
					'aid' => $aid,
					'bid' => $bid,
					'mid' => $mid,
					'payment_id' => $payment['id'],
				'order_id' => $payorder['orderid'] ?? 0,
				'room_id' => $payment['room_id'],
				'deduct_score' => $payment['deduct_score'],
				'deduct_amount' => $payment['deduct_amount'],
				'score_value_ratio' => floatval($payment['deduct_score']) > 0 
					? $payment['deduct_amount'] / $payment['deduct_score'] 
					: 0,
					'original_amount' => $payment['original_amount'],
					'final_amount' => $payment['amount'],
					'status' => 1,
					'deduct_time' => time(),
					'createtime' => time(),
					'updatetime' => time()
				];
				
				Db::name('property_score_deduct')->insert($deduct_data);
			}
			
			// 更新缴费记录状态
			Db::name('property_payment')
				->where('id', $payment['id'])
				->update([
					'payment_status' => 1, // 已支付
					'payment_time' => time(),
					'payment_method' => $payorder['paytype'] ?? '在线支付',
					'transaction_id' => $payorder['paynum'] ?? '',
					'updatetime' => time()
				]);
			
			// 更新对应账单状态
			if(!empty($bills)) {
				$bill_ids = array_column($bills, 'id');
				$current_time = time();
				$update_result = Db::name('property_bill')
					->where('id', 'in', $bill_ids)
					->update([
						'bill_status' => 1, // 已缴费
						'paid_amount' => Db::raw('amount'), // 已缴金额等于应缴金额
						'paid_time' => $current_time,
						'updatetime' => $current_time
					]);
				

			}
			
			// 积分奖励处理
			$property_setting = Db::name('property_setting')
				->where('aid', $aid)
				->where('bid', $bid)
				->find();
			
			if($property_setting && $property_setting['score_enabled'] == 1 && $property_setting['score_ratio'] > 0) {
				$score_reward = intval($amount * $property_setting['score_ratio']);
				if($score_reward > 0) {
					\app\admin\common\Member::addscore($aid, $mid, $score_reward, '物业费缴费奖励');
					
									// 更新缴费记录的积分奖励
				Db::name('property_payment')->where('id', $payment['id'])->update(['score_reward' => $score_reward]);
				}
			}
			
			// 提交事务
			Db::commit();
			
			return ['status'=>1,'msg'=>'支付成功'];
			
		} catch (\Exception $e) {
			Db::rollback();
			return ['status'=>0,'msg'=>'支付失败：' . $e->getMessage()];
		}
	}
	
	/**
	 * 活动报名订单支付处理
	 * @param int $orderid 订单ID
	 * @return array
	 */
	public static function huodong_baoming_pay($orderid){
		$order = Db::name('huodong_baoming_order')->where('id',$orderid)->find();
		if(!$order) return ['status'=>0,'msg'=>'订单不存在'];
		if($order['status'] != 0) return ['status'=>0,'msg'=>'订单状态不符合'];
		
		$aid = $order['aid'];
		
		// 更新销量
		Db::name('huodong_baoming_product')->where('aid',$aid)->where('id',$order['proid'])->update(['sales'=>Db::raw("sales+".$order['num'])]);
		
		// 赠送积分
		if($order['givescore'] > 0){
			\app\admin\common\Member::addscore($aid,$order['mid'],$order['givescore'],'参与活动赠送'.t('积分'));
		}
		
		// 发货信息录入 微信小程序+微信支付
		if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
			\app\admin\common\Order::wxShipping($aid,$order,'huodong_baoming');
		}
		
		return ['status'=>1,'msg'=>''];
	}

	// 订票
	public static function theater_pay($orderid){
	    return true;
	}
	/**
	 * 猫算命支付回调
	 * 仅做订单与报告的支付状态落库，报告生成由业务接口轮询触发。
	 */
	public static function maosuanming_pay($orderid, $ordernum = '')
	{
		$orderid = intval($orderid);
		if ($orderid <= 0) {
			return ['status' => 0, 'msg' => '订单ID错误'];
		}

		$order = Db::name('maosuanming_order')->where('id', $orderid)->find();
		if (!$order) {
			return ['status' => 0, 'msg' => '猫算命订单不存在'];
		}

		$aid = intval($order['aid'] ?? 0);
		$now = time();
		$paytime = intval($order['paytime'] ?? 0);
		$paytype = strval($order['paytype'] ?? '');
		$paytypeid = intval($order['paytypeid'] ?? 0);
		$paynum = strval($order['paynum'] ?? '');
		$platform = strval($order['platform'] ?? '');
		$payorderid = intval($order['payorderid'] ?? 0);

		if ($payorderid > 0 && $aid > 0) {
			$payorder = Db::name('payorder')->where('aid', $aid)->where('id', $payorderid)->find();
			if ($payorder) {
				$paytime = intval($payorder['paytime'] ?? $paytime);
				$paytype = strval($payorder['paytype'] ?? $paytype);
				$paytypeid = intval($payorder['paytypeid'] ?? $paytypeid);
				$paynum = strval($payorder['paynum'] ?? $paynum);
				$platform = strval($payorder['platform'] ?? $platform);
			}
		}
		if ($paytime <= 0) {
			$paytime = $now;
		}

		Db::name('maosuanming_order')->where('id', $orderid)->update([
			'status' => 1,
			'paytime' => $paytime,
			'paytype' => $paytype,
			'paytypeid' => $paytypeid,
			'paynum' => $paynum,
			'platform' => $platform,
			'updatetime' => $now,
		]);

		$orderType = trim(strval($order['order_type'] ?? 'report'));
		if ($orderType === '') {
			$orderType = 'report';
		}
		if ($orderType === 'wallpaper_regenerate') {
			self::applyMaosuanmingWallpaperRegenerate($order, $paytime);
			return ['status' => 1, 'msg' => ''];
		}
		if ($orderType === 'businesscard_regenerate') {
			self::applyMaosuanmingBusinesscardRegenerate($order, $paytime);
			return ['status' => 1, 'msg' => ''];
		}

		$reportId = intval($order['report_id'] ?? 0);
		if ($reportId <= 0 && $ordernum !== '') {
			$reportId = intval(Db::name('maosuanming_report')->where('aid', $aid)->where('ordernum', strval($ordernum))->value('id'));
		}
		if ($reportId > 0) {
			Db::name('maosuanming_report')->where('aid', $aid)->where('id', $reportId)->update([
				'status' => 1,
				'paytime' => $paytime,
				'updatetime' => $now,
			]);
		}

		return ['status' => 1, 'msg' => ''];
	}

	private static function decodeMaosuanmingOrderExtra($json): array
	{
		if (!is_string($json) || trim($json) === '') {
			return [];
		}
		$data = json_decode($json, true);
		return is_array($data) ? $data : [];
	}

	private static function applyMaosuanmingWallpaperRegenerate(array $order, int $paytime): void
	{
		$aid = intval($order['aid'] ?? 0);
		$targetId = intval($order['target_id'] ?? 0);
		$extra = self::decodeMaosuanmingOrderExtra($order['extra_json'] ?? '');
		if ($targetId <= 0) {
			$targetId = intval($extra['wallpaper_id'] ?? 0);
		}
		if ($aid <= 0 || $targetId <= 0) {
			return;
		}
		Db::name('maosuanming_wallpaper')
			->where('aid', $aid)
			->where('id', $targetId)
			->where('unlocked', 1)
			->update([
				'image_url' => '',
				'status' => 0,
				'error_msg' => '',
				'generatetime' => 0,
				'updatetime' => $paytime > 0 ? $paytime : time(),
			]);
	}

	private static function applyMaosuanmingBusinesscardRegenerate(array $order, int $paytime): void
	{
		$aid = intval($order['aid'] ?? 0);
		$targetId = intval($order['target_id'] ?? 0);
		$extra = self::decodeMaosuanmingOrderExtra($order['extra_json'] ?? '');
		if ($targetId <= 0) {
			$targetId = intval($extra['card_id'] ?? 0);
		}
		$style = isset($extra['style']) && is_array($extra['style']) ? $extra['style'] : [];
		if ($aid <= 0 || $targetId <= 0 || empty($style)) {
			return;
		}
		$row = Db::name('maosuanming_businesscard')->where('aid', $aid)->where('id', $targetId)->find();
		if (!$row) {
			return;
		}
		Db::name('maosuanming_businesscard')->where('aid', $aid)->where('id', $targetId)->update([
			'style_id' => intval($style['id'] ?? 0),
			'style_name' => strval($style['name'] ?? ''),
			'style_mode' => strval($style['mode'] ?? 'avatar'),
			'template_image' => strval($style['template_image'] ?? ''),
			'prompt' => strval($style['prompt'] ?? ''),
			'image_url' => '',
			'status' => 0,
			'error_msg' => '',
			'generatetime' => 0,
			'regenerate_count' => intval($row['regenerate_count'] ?? 0) + 1,
			'updatetime' => $paytime > 0 ? $paytime : time(),
		]);
	}

	public static function ershou_pay($orderid, $ordernum = '')
	{
		$orderid = intval($orderid);
		if ($orderid <= 0) {
			return ['status' => 0, 'msg' => '订单ID错误'];
		}

		$order = Db::name('ershou_order')->where('id', $orderid)->find();
		if (!$order) {
			return ['status' => 0, 'msg' => '二手订单不存在'];
		}
		if (intval($order['pay_status'] ?? 0) === 1 && intval($order['status'] ?? 0) >= 1) {
			return ['status' => 1, 'msg' => ''];
		}

		$now = time();
		$paytime = intval($order['paytime'] ?? 0);
		$payorderid = intval($order['payorderid'] ?? 0);
		$paytype = strval($order['paytype'] ?? '');
		$paytypeid = intval($order['paytypeid'] ?? 0);
		$paynum = strval($order['paynum'] ?? '');
		$platform = strval($order['platform'] ?? '');

		if ($payorderid > 0) {
			$payorder = Db::name('payorder')->where('id', $payorderid)->find();
			if ($payorder) {
				$paytime = intval($payorder['paytime'] ?? $paytime);
				$paytype = strval($payorder['paytype'] ?? $paytype);
				$paytypeid = intval($payorder['paytypeid'] ?? $paytypeid);
				$paynum = strval($payorder['paynum'] ?? $paynum);
				$platform = strval($payorder['platform'] ?? $platform);
			}
		}
		if ($paytime <= 0) {
			$paytime = $now;
		}

		Db::name('ershou_order')->where('id', $orderid)->update([
			'status' => 1,
			'pay_status' => 1,
			'paytime' => $paytime,
			'paytype' => $paytype,
			'paytypeid' => $paytypeid,
			'paynum' => $paynum,
			'platform' => $platform,
			'updatetime' => $now,
		]);

		if (intval($order['goods_id'] ?? 0) > 0) {
			Db::name('ershou_goods')
				->where('aid', intval($order['aid'] ?? 0))
				->where('id', intval($order['goods_id']))
				->update([
					'status' => 2,
					'updatetime' => $now,
				]);
		}

		if (Db::query("SHOW TABLES LIKE '" . config('database.connections.mysql.prefix') . "ershou_order_log'")) {
			$hasPayLog = Db::name('ershou_order_log')
				->where('aid', intval($order['aid'] ?? 0))
				->where('order_id', $orderid)
				->where('action', 'pay_success')
				->find();
			if (!$hasPayLog) {
				Db::name('ershou_order_log')->insert([
					'aid' => intval($order['aid'] ?? 0),
					'order_id' => $orderid,
					'action' => 'pay_success',
					'operator_mid' => intval($order['buyer_mid'] ?? 0),
					'operator_role' => 'system',
					'content' => '订单支付成功，等待卖家确认',
					'ext_json' => json_encode([
						'ordernum' => strval($ordernum ?: ($order['ordernum'] ?? '')),
						'payorderid' => $payorderid,
					], JSON_UNESCAPED_UNICODE),
					'createtime' => $now,
				]);
			}
		}

		return ['status' => 1, 'msg' => ''];
	}
    // 订场
    public static function venues_pay($orderid){
    	return true;
    }

     // 约场
    public static function venues_appointment_pay($orderid){
    	$appointmentInfo = Db::name('venues_appointment')->alias('a')->field('a.*')
    		->join('venues_appointment_order o', 'o.appointment_id=a.id')
    		->where('o.id', $orderid)->find();
    	if(!empty($appointmentInfo)){
    		// 是否已够人成团
    		$joinCount = Db::name('venues_appointment_order')->where('aid', aid)->where('appointment_id', $appointmentInfo['id'])->where('status', 1)->count();
    		if($joinCount == $appointmentInfo['application']){
    			Db::name('venues_appointment')->where('id', $appointmentInfo['id'])->update(['status' => 1]);
    		}
    	}
    	return true;
    }
    
	public static function plug_businessqr_pay_pay($orderid){
        $order = Db::name('plug_businessqr_pay_order')->where('id',$orderid)->find();
        $member = Db::name('member')->where('id',$order['mid'])->find();
        $aid = $order['aid'];
		if($order['bid']!=0){//入驻商家的货款
			$binfo = Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->find();
			$totalmoney = $order['cost_price'];
			\app\admin\common\Business::addmoney($aid,$order['bid'],$totalmoney,'会员支付，订单号：'.$order['ordernum']);
			//店铺加销量
			Db::name('business')->where('aid',$aid)->where('id',$order['bid'])->inc('sales',1)->update();

			//公众号通知 订单支付成功
			$tmplcontent = [];
			$tmplcontent['first'] = '有新会员支付订单支付成功';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $member['nickname']; //用户名
			$tmplcontent['keyword2'] = $order['ordernum'];//订单号
			$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
			$tmplcontent['keyword4'] = $order['title'];//商品信息
			\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/finance/bmoneylog', $aid));
			$tmplcontent['first'] = '恭喜您已支付成功';
			$rs = \app\admin\common\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('pages/money/moneylog?st=1', $aid));

			$tmplcontent = [];
			$tmplcontent['thing11'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			$tmplcontent['phrase10'] = '已支付';
			$tmplcontent['amount13'] = $order['totalprice'].'元';
			$tmplcontent['thing27'] = $member['nickname'];
			\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/finance/bmoneylog');

            // 发送新订单通知给管理员
            \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
                'consignee' => $binfo['name'],
                'phone' => $binfo['tel'],
                'ordernum' => $order['ordernum'],
                'amount' => $order['totalprice']
            ]);
		}
	}
	//到家服务支付
	public static function daojia_pay($orderid, $ordernum = ''){
		$order = Db::name('daojia_order')->where('id',$orderid)->find();
		if(!$order) return ['status'=>0,'msg'=>'订单不存在'];
		if(intval($order['pay_status'] ?? 0) !== 1 && intval($order['status'] ?? 0) !== 1){
			return ['status'=>0,'msg'=>'订单状态不符合'];
		}
		Db::name('daojia_order')->where('id',$orderid)->update(['updatetime'=>time()]);
		$order = Db::name('daojia_order')->where('id',$orderid)->find();
		$setInfo = [];
		if (Db::query("SHOW TABLES LIKE '" . config('database.connections.mysql.prefix', '') . "daojia_set'")) {
			$setInfo = Db::name('daojia_set')
				->where('aid', intval($order['aid'] ?? 0))
				->where('bid', intval($order['bid'] ?? 0))
				->find() ?: [];
			if (!$setInfo && intval($order['bid'] ?? 0) > 0) {
				$setInfo = Db::name('daojia_set')
					->where('aid', intval($order['aid'] ?? 0))
					->where('bid', 0)
					->find() ?: [];
			}
		}
		$appointWorkerDirectDispatch = intval($setInfo['appoint_worker_direct_dispatch'] ?? 1) === 1;
		try{
			\app\plugin\daojia\service\DaojiaNoticeService::orderPaid($order ?: ['id'=>$orderid,'ordernum'=>$ordernum]);
		}catch(\Throwable $e){
			\think\facade\Log::error('daojia_pay notice failed: '.$e->getMessage(),[
				'orderid'=>$orderid,
				'ordernum'=>$ordernum,
			]);
		}
		try{
			\app\plugin\daojia\service\CommissionService::distribute($order ?: ['id'=>$orderid,'ordernum'=>$ordernum], 'pay');
		}catch(\Throwable $e){
			\think\facade\Log::error('daojia_pay commission failed: '.$e->getMessage(),[
				'orderid'=>$orderid,
				'ordernum'=>$ordernum,
			]);
		}
		try{
			if(
				intval($order['appoint_worker_id'] ?? 0) > 0
				&& intval($order['worker_order_id'] ?? 0) <= 0
				&& $appointWorkerDirectDispatch
			){
				\app\plugin\daojia\service\AutoDispatchService::assignSpecifiedWorker($order ?: ['id'=>$orderid], intval($order['appoint_worker_id'] ?? 0), 'customer_choose', true);
			}elseif(intval($order['appoint_worker_id'] ?? 0) <= 0){
				\app\plugin\daojia\service\AutoDispatchService::tryDispatchOrder($order ?: ['id'=>$orderid], 'payment', true);
			}else{
				\think\facade\Log::info('daojia_pay skip direct assign by setting',[
					'orderid'=>$orderid,
					'ordernum'=>$ordernum,
					'appoint_worker_id'=>intval($order['appoint_worker_id'] ?? 0),
					'appoint_worker_direct_dispatch'=>0,
				]);
			}
		}catch(\Throwable $e){
			\think\facade\Log::error('daojia_pay autodispatch failed: '.$e->getMessage(),[
				'orderid'=>$orderid,
				'ordernum'=>$ordernum,
			]);
		}
		\think\facade\Log::info('daojia_pay success',[
			'orderid'=>$orderid,
			'ordernum'=>$order['ordernum'] ?? '',
			'aid'=>$order['aid'] ?? 0,
			'mid'=>$order['mid'] ?? 0
		]);
		return ['status'=>1,'msg'=>''];
	}

	//到家加钟/加项补差价支付
	public static function daojia_supplement_pay($orderid, $ordernum = ''){
		try{
			return \app\plugin\daojia\service\SupplementService::applyPaid(intval($orderid));
		}catch(\Throwable $e){
			\think\facade\Log::error('daojia_supplement_pay failed: '.$e->getMessage(),[
				'orderid'=>$orderid,
				'ordernum'=>$ordernum,
			]);
			return ['status'=>0,'msg'=>$e->getMessage()];
		}
	}
	//预约服务支付
	public static function yuyue_pay($orderid){
			//var_dump($orderid);
		   $order = Db::name('yuyue_order')->where('id',$orderid)->find();
		   $aid = $order['aid'];
		   $member = Db::name('member')->where('id',$order['mid'])->find();
			if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
			if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
			if(getcustom('hmy_yuyue') && in_array($order['paidan_type'],[2,3])){ //定制 将订单同步到师傅app端
				\app\admin\custom\Yuyue::apiyuyue($order);
			}else{
				$yyset = Db::name('yuyue_set')->where('aid',$aid)->find();
				if($yyset['paidantype']==0 && $yyset['isautopd']==1){   //自动派单到大厅
					$rs = \app\admin\model\YuyueWorkerOrder::create($order,0,'');
				}
				if($order['worker_id']){
					//如果用户已经选择服务人员则支付后直接进行派单
					$rs = \app\admin\model\YuyueWorkerOrder::create($order,$order['worker_id'],'');
					if($rs['status']==0) return json($rs);
					//\app\admin\common\System::plog('预约派单'.$orderid);
				}
			}
			
			// 【新增】支付成功后佣金发放
			\app\admin\common\YuyueCommission::distribute($orderid, 'payment');
			
			 //发货信息录入 微信小程序+微信支付
			 if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
				\app\admin\common\Order::wxShipping($order['aid'],$order,'yuyue');
			}
			//公众号通知 订单支付成功
			$tmplcontent = [];
			$tmplcontent['first'] = '有新预约订单支付成功';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $member['nickname']; //用户名
			$tmplcontent['keyword2'] = $order['ordernum'];//订单号
			$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
			$tmplcontent['keyword4'] = $order['title'];//商品信息
			\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/yuyueorder', $aid),$order['mdid']);
			$tmplcontent['first'] = '恭喜您的订单已支付成功';
			$rs = \app\admin\common\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/yuyue/orderlist', $aid));

			$tmplcontent = [];
			$tmplcontent['thing11'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			$tmplcontent['phrase10'] = '已支付';
			$tmplcontent['amount13'] = $order['totalprice'].'元';
			$tmplcontent['thing27'] = $member['nickname'];
			\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/yuyueorder',$order['mdid']);

			//短信通知
			$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

            // 发送新订单通知给管理员
            \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
                'consignee' => $order['consignee'],
                'phone' => $order['tel'],
                'ordernum' => $order['ordernum'],
                'amount' => $order['totalprice']
            ]);
	}

//酒店订单
public static function hotel_pay($orderid){
    $order = Db::name('hotel_order')->where('id',$orderid)->find();
    $member = Db::name('member')->where('id',$order['mid'])->find();
    $aid = $order['aid'];
    $mid = $order['mid'];
    if($order['use_money']>0){
        \app\admin\common\Member::addmoney($aid,$mid,-$order['use_money'],t('余额').'抵扣，订单号: '.$order['ordernum']);
    }
    $room = Db::name('hotel_room')->where('id',$order['roomid'])->find();
    //增加押金记录
    if($order['yajin_money']>0){
        $yjdata = [];
        $yjdata['aid'] = $order['aid'];
        $yjdata['bid'] = $order['bid'];
        $yjdata['mid'] = $order['mid'];
        $yjdata['orderid'] = $order['id'];
        $yjdata['ordernum'] = $order['ordernum'];
        $yjdata['yajin_money'] = $order['yajin_money'];
        $yjdata['yajin_type'] = $order['yajin_type'];
        $yjdata['refund_money'] = $order['yajin_money'];
        $yjdata['refund_status'] = 0;
        $yjdata['refund_ordernum'] = '' . date('ymdHis') . rand(100000, 999999);
        //$yjdata['apply_time'] = time();
        $yjdata['yd_num'] = $order['totalnum']; //预定人数
        $yajinid = Db::name('hotel_order_yajin')->insertGetId($yjdata);
        //修改关联的押金订单
        Db::name('hotel_order')->where('id',$orderid)->update(['yajin_orderid'=>$yajinid]);
    }
    $couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'hotel',$order['totalprice']);
   
    if($couponlist){
        foreach($couponlist as $coupon){
            \app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
        }
    }
    //发货信息录入 微信小程序+微信支付
    if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
        \app\admin\common\Order::wxShipping($order['aid'],$order,'hotel');
    }
    //是否为即时确认
    if($room['qrtype']==1){
        Db::name('hotel_order')->where('id',$orderid)->update(['status'=>2,'confirm_time'=>time()]);
        //发送消息通知
        \app\admin\model\Hotel::sendNotice($aid,$order);
    }
    //加销量
    \app\admin\model\Hotel::addroomsales($order,$order['totalnum']);
    if(getcustom('yx_queue_free_hotel',$aid)){
        \app\admin\custom\QueueFree::join($order,'hotel');
    }
    //短信通知
    $rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

    
}

	//预约尾款支付
	public static function yuyue_balance_pay($orderid){
		//var_dump($orderid);
	    $order = Db::name('yuyue_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
        $member = Db::name('member')->where('id',$order['mid'])->find();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['balance_pay_status']!=1) return json(['status'=>0,'msg'=>'订单状态不符合']);
		if(getcustom('hmy_yuyue') && $order['sysOrderNo']){ //将订单同步到师傅app端
			//直接完成订单
			db('yuyue_order')->where(['aid'=>$order['aid'],'id'=>$orderid])->update(['status'=>3,'collect_time'=>time()]);
			$rs = \app\admin\common\Order::collect($order,'yuyue');
			Db::name('yuyue_worker_order')->where('id',$order['worker_orderid'])->update(['status'=>3,'endtime'=>time()]);
			\app\admin\custom\Yuyue::payoff($order);
		}
		
        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'yuyue');
        }
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '尾款订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/yuyueorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/yuyue/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/yuyueorder',$order['mdid']);

		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
	}


 /**
     * 套餐订单支付成功回调
     * @param int $orderid 套餐订单ID (yuyue_package_order表的ID)
     */
    public static function yuyue_package_pay($orderid){
        $order = Db::name('yuyue_package_order')->where('id', $orderid)->find();
        
        if(!$order){
            Log::error('套餐支付回调失败：订单不存在，ID: ' . $orderid);
            return;
        }
        
        // 检查订单状态是否为待支付
        if($order['status'] != 0){
            Log::warning('套餐支付回调：订单状态不正确或已处理，ID: ' . $orderid . ', Status: ' . $order['status']);
            return;
        }
        
        // 计算过期时间
        $expires_time = time() + $order['valid_days'] * 86400; 
        
        // 更新订单状态
        $update_data = [
            'status' => 1, // 已支付
            'pay_time' => time(),
            'expires_time' => $expires_time,
            'updatetime' => time()
        ];
        $res = Db::name('yuyue_package_order')->where('id', $orderid)->update($update_data);
        
        if($res !== false) {
            // 增加套餐销量
            Db::name('yuyue_package')
                ->where('id', $order['package_id'])
                ->inc('sales')
                ->update();
                
            // 发送购买成功通知给用户
            try {
                $tmplcontent = [];
                $tmplcontent['first'] = '您的服务套餐[' . $order['package_name'] . ']购买成功';
                $tmplcontent['keyword1'] = $order['package_name']; // 套餐名称
                $tmplcontent['keyword2'] = '￥'.$order['total_price']; // 支付金额
                $tmplcontent['keyword3'] = date('Y-m-d H:i:s', $update_data['pay_time']); // 支付时间
                $tmplcontent['keyword4'] = $order['ordernum']; // 订单编号
                $tmplcontent['remark'] = '套餐已生效，有效期至：'.date('Y-m-d', $expires_time).'。请在有效期内使用。';
                
                \app\admin\common\Wechat::sendtmpl($order['aid'], $order['mid'], 'tmpl_ordersucc', $tmplcontent, m_url('pages/my/packageorderlist')); // 跳转到套餐订单列表
            } catch (\Exception $e) {
                Log::error('发送套餐购买成功通知失败：orderid=' . $orderid . ', error=' . $e->getMessage());
            }

            // 可在此处添加分销、积分等逻辑（如果套餐参与）
            // ...

        } else {
            Log::error('更新套餐订单状态失败，ID: ' . $orderid);
        }
    }
    public static function periodicservice_pay($orderid){ // $orderid 是 periodicservice_order 表的 id
        // 日志记录：开始处理周期服务支付回调的特定逻辑
        $logPrefixBase = '[Payorder]-[periodic_service_pay_'; // 日志基础前缀
        $log = function($level, $seq, $message) use ($logPrefixBase, $orderid) {
            $timestamp = date('Y-m-d H:i:s') . ',' . round(microtime(true) * 1000) % 1000;
            Log::$level("{$timestamp}-{$level}-{$logPrefixBase}{$seq}] {$message}, periodicservice_order_id: {$orderid}");
        };

        $log('INFO', '001', "开始处理周期服务特定逻辑");

        // 直接查询周期服务订单
        $order = Db::name('periodicservice_order')->where('id', $orderid)->find();
        if(!$order){
            $log('WARN', '002', "周期服务订单未找到");
            return;
        }

        // 检查订单支付状态
        if($order['pay_status'] != 1){
             $log('INFO', '003', "周期服务订单未支付或支付状态异常, pay_status: " . $order['pay_status']);
             return;
        }
    
        $log('INFO', '004', "周期服务订单状态检查通过");
    
        // --- 生成服务工单 (periodic_service_stage) ---
        $stagesData = [];
        $startDate = $order['start_date'];
        $totalPeriod = $order['total_period'] ?? 0; // 总期数
        $psCycle = $order['ps_cycle'] ?? 0; // 周期类型：1=周，2=月，5=按天间隔
        $psFrequency = $order['ps_frequency'] ?? 0; // 频率（周几/每月几号）
        $psDays = $order['ps_days'] ?? 0; // 天数间隔
        
        $log('INFO', '005', "开始计算工单日期, start_date: {$startDate}, total_period: {$totalPeriod}, ps_cycle: {$psCycle}, ps_frequency: {$psFrequency}, ps_days: {$psDays}");
        
        if (empty($startDate) || $totalPeriod <= 0) {
            $log('ERROR', '006', "开始日期为空或总期数无效");
            return;
        }

        $startDateTimestamp = strtotime($startDate);
        if ($startDateTimestamp === false) {
            $log('ERROR', '007', "无法解析开始日期: {$startDate}");
             return;
        }
    
        // 计算每期的服务日期
        for ($i = 1; $i <= $totalPeriod; $i++) {
            $currentDate = null;
            
            // 第一期的日期计算
            if ($i == 1) {
                $log('INFO', '008_1', "计算第1期日期");
                
                // 根据周期类型计算第一期日期
                if ($psCycle == 5) { // 按天数间隔
                    // 第一期就是开始日期
                    $currentDate = date('Y-m-d', $startDateTimestamp);
                    $log('INFO', '009_1', "按天数间隔, 第1期日期: {$currentDate}");
                } 
                else if ($psCycle == 1) { // 按周
                    // 确定开始日期是周几
                    $startDayOfWeek = date('N', $startDateTimestamp); // 1(周一)到7(周日)
                    
                    // 目标是周几
                    $targetDayOfWeek = $psFrequency;
                    
                    if ($startDayOfWeek == $targetDayOfWeek) {
                        // 如果开始日期刚好是目标周几，直接使用
                        $currentDate = date('Y-m-d', $startDateTimestamp);
        } else {
                        // 计算距离目标周几的天数
                        $daysUntilTarget = ($targetDayOfWeek - $startDayOfWeek + 7) % 7;
                        if ($daysUntilTarget == 0) $daysUntilTarget = 7; // 如果计算结果为0，则为下周同一天
                        $timestamp = strtotime("+{$daysUntilTarget} days", $startDateTimestamp);
                        $currentDate = date('Y-m-d', $timestamp);
                    }
                    $log('INFO', '009_1', "按周, 开始日期周{$startDayOfWeek}, 目标周{$targetDayOfWeek}, 第1期日期: {$currentDate}");
                } 
                else if ($psCycle == 2) { // 按月
                    // 获取开始日期的月和日
                    $startMonth = date('n', $startDateTimestamp);
                    $startDay = date('j', $startDateTimestamp);
                    $startYear = date('Y', $startDateTimestamp);
                    
                    // 目标日是每月几号
                    $targetDay = $psFrequency;
                    
                    // 检查当月是否还有这一天
                    $daysInMonth = date('t', $startDateTimestamp);
                    if ($targetDay > $daysInMonth) {
                        $targetDay = $daysInMonth; // 如果目标日大于当月天数，则使用月底
                    }
                    
                    // 如果开始日期已过目标日，则使用下个月的目标日
                    if ($startDay > $targetDay) {
                        $nextMonth = $startMonth == 12 ? 1 : $startMonth + 1;
                        $nextYear = $startMonth == 12 ? $startYear + 1 : $startYear;
                        $timestamp = strtotime("{$nextYear}-{$nextMonth}-{$targetDay}");
                        // 检查下个月是否有目标日
                        $daysInNextMonth = date('t', $timestamp);
                        if ($targetDay > $daysInNextMonth) {
                            $targetDay = $daysInNextMonth;
                            $timestamp = strtotime("{$nextYear}-{$nextMonth}-{$targetDay}");
                        }
                    } else {
                        // 使用当月目标日
                        $timestamp = strtotime("{$startYear}-{$startMonth}-{$targetDay}");
                    }
                    
                    $currentDate = date('Y-m-d', $timestamp);
                    $log('INFO', '009_1', "按月, 开始日期{$startDay}号, 目标{$targetDay}号, 第1期日期: {$currentDate}");
                }
            } 
            // 后续期数的日期计算
            else {
                $log('INFO', "008_{$i}", "计算第{$i}期日期");
                
                // 获取上一期的日期作为基准
                $prevDate = $stagesData[$i - 2]['scheduled_date'];
                $prevTimestamp = strtotime($prevDate);
                
                if ($psCycle == 5) { // 按天数间隔
                    $timestamp = strtotime("+{$psDays} days", $prevTimestamp);
                    $currentDate = date('Y-m-d', $timestamp);
                    $log('INFO', "009_{$i}", "按天数间隔{$psDays}天, 上期日期: {$prevDate}, 第{$i}期日期: {$currentDate}");
                } 
                else if ($psCycle == 1) { // 按周
                    $timestamp = strtotime("+7 days", $prevTimestamp);
                    $currentDate = date('Y-m-d', $timestamp);
                    $log('INFO', "009_{$i}", "按周, 上期日期: {$prevDate}, 第{$i}期日期: {$currentDate}");
                } 
                else if ($psCycle == 2) { // 按月
                    $prevDay = date('j', $prevTimestamp);
                    $prevMonth = date('n', $prevTimestamp);
                    $prevYear = date('Y', $prevTimestamp);
                    
                    // 确定下个月的月份和年份
                    $nextMonth = $prevMonth == 12 ? 1 : $prevMonth + 1;
                    $nextYear = $prevMonth == 12 ? $prevYear + 1 : $prevYear;
                    
                    // 目标日是每月几号
                    $targetDay = $psFrequency;
                    
                    // 检查下个月是否有目标日
                    $daysInNextMonth = date('t', strtotime("{$nextYear}-{$nextMonth}-01"));
                    if ($targetDay > $daysInNextMonth) {
                        $targetDay = $daysInNextMonth;
                    }
                    
                    $timestamp = strtotime("{$nextYear}-{$nextMonth}-{$targetDay}");
                    $currentDate = date('Y-m-d', $timestamp);
                    $log('INFO', "009_{$i}", "按月, 上期日期: {$prevDate}, 第{$i}期日期: {$currentDate}");
                }
            }
            
            // 如果无法计算出有效日期，跳过
            if (empty($currentDate)) {
                $log('ERROR', "010_{$i}", "无法计算第{$i}期日期, 跳过此期");
                continue;
            }
            
            // 添加工单数据
            $stagesData[] = [
                'aid' => $order['aid'],
                'bid' => $order['bid'],
                'order_id' => $order['id'],
                'ordernum' => $order['ordernum'],
                'mid' => $order['mid'],
                'product_id' => $order['product_id'],
                'product_name' => $order['product_name'],
                'period_num' => $i,
                'scheduled_date' => $currentDate,
                'status' => 0, // 待服务
                'createtime' => time(),
                'updatetime' => time(),
            ];
        }
        
        if(empty($stagesData)) {
            $log('ERROR', '011', "未生成任何工单数据");
            return;
        }
        
        $log('INFO', '012', "成功生成 " . count($stagesData) . " 条工单数据");
        
        // 检查是否已经生成过工单
        $existingStages = Db::name('periodic_service_stage')->where('order_id', $order['id'])->count();
        if ($existingStages > 0) {
            $log('INFO', '013', "该订单已生成工单，共 {$existingStages} 条，跳过生成步骤");
            return;
        }
        
        // 使用事务确保数据一致性
        Db::startTrans();
        try {
            // 批量插入工单数据
            $result = Db::name('periodic_service_stage')->insertAll($stagesData);
            if (!$result) {
                throw new \Exception("工单插入失败");
            }
            $log('INFO', '014', "批量插入工单成功, 共 " . count($stagesData) . " 条");
            
            // 更新主订单状态为服务中
            $updateData = [
                'status' => 1, // 服务中
                'remain_period' => $totalPeriod, // 剩余期数
                'served_period' => 0, // 已服务期数
                'next_service_date' => $stagesData[0]['scheduled_date'], // 下次服务日期
                'updatetime' => time()
            ];
            
            $updateResult = Db::name('periodicservice_order')->where('id', $order['id'])->update($updateData);
            if ($updateResult === false) {
                throw new \Exception("更新订单状态失败");
            }
            $log('INFO', '015', "更新订单状态为服务中成功, next_service_date: {$stagesData[0]['scheduled_date']}");
            
            Db::commit();
            $log('INFO', '016', "事务提交成功");
        } catch (\Exception $e) {
            Db::rollback();
            $log('ERROR', '017', "处理失败, 事务回滚: " . $e->getMessage());
            return;
        }
        
        // --- 后续操作 (积分、优惠券、分销、通知等) ---
        // 发送通知给客户
        try {
            // 获取会员信息
            $member = Db::name('member')->where('id', $order['mid'])->find();
            if ($member) {
                // 构建通知内容
                $noticeData = [
                    'type' => 'periodic_service_order',
                    'template' => 'order_paid',
                    'aid' => $order['aid'],
                    'bid' => $order['bid'],
                    'mid' => $order['mid'],
                    'orderid' => $order['id'],
                    'ordernum' => $order['ordernum'],
                    'createtime' => time(),
                    'data' => [
                        'nickname' => $member['nickname'],
                        'order_id' => $order['id'],
                        'order_sn' => $order['ordernum'],
                        'order_amount' => $order['total'],
                        'product_name' => $order['product_name'],
                        'service_time' => $stagesData[0]['scheduled_date'],
                        'total_period' => $totalPeriod
                    ]
                ];
                
                // 这里调用您系统中的通知发送方法
                // \app\admin\common\service\Notice::send($noticeData);
                $log('INFO', '018', "发送订单支付成功通知");
            }
        } catch (\Exception $e) {
            $log('WARN', '019', "发送通知失败: " . $e->getMessage());
            // 通知失败不影响主流程
        }
        
        $log('INFO', '020', "周期服务特定逻辑处理完成");
    }
	//课程支付
	public static function kecheng_pay($orderid){
		//var_dump($orderid);
	    $order = Db::name('kecheng_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
		//增加学习人数
		Db::name('kecheng_list')->where('aid',$order['aid'])->where('id',$order['kcid'])->inc('join_num')->update();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
        $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->find();

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'kecheng');
        }
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新课程订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['price'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/kechengorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/kecheng/orderlist?bid='.$order['bid'], $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/kechengorder',$order['mdid']);

		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	    \app\admin\common\Order::collect($order,'kecheng');

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['price']
        ]);
	}

	//课程支付
	public static function exam_pay($orderid){
		//var_dump($orderid);
	    $order = Db::name('exam_order')->where('id',$orderid)->find();
        $aid = $order['aid'];
		//增加学习人数
		Db::name('exam_list')->where('aid',$order['aid'])->where('id',$order['kcid'])->inc('join_num')->update();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
        $member = Db::name('member')->where('aid',$order['aid'])->where('id',$order['mid'])->find();

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'exam');
        }
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新课程订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['price'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/kechengorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/kecheng/orderlist?bid='.$order['bid'], $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/kechengorder',$order['mdid']);

		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);
	    \app\admin\common\Order::collect($order,'kecheng');

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['price']
        ]);
	}


	//团购
	public static function tuangou_pay($orderid){
		$order = Db::name('tuangou_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
		//自动发货
		if($order['freight_type']==3){
			Db::name('tuangou_order')->where('id',$order['id'])->update(['status'=>2,'send_time'=>time()]);
		}
		//在线卡密
		if($order['freight_type']==4){
			$codelist = Db::name('tuangou_codelist')->where('proid',$order['proid'])->where('status',0)->order('id')->limit($order['num'])->select()->toArray();
			if($codelist && count($codelist) >= $order['num']){
				$pscontent = [];
				foreach($codelist as $codeinfo){
					$pscontent[] = $codeinfo['content'];
					Db::name('tuangou_codelist')->where('id',$codeinfo['id'])->update(['orderid'=>$order['id'],'ordernum'=>$order['ordernum'],'headimg'=>$member['headimg'],'nickname'=>$member['nickname'],'buytime'=>time(),'status'=>1]);
				}
				$pscontent = implode("\r\n",$pscontent);
				Db::name('tuangou_order')->where('id',$order['id'])->update(['freight_content'=>$pscontent,'status'=>2,'send_time'=>time()]);
			}
			
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\admin\common\Order::wxShipping($order['aid'],$order,'tuangou');
            }
		}
		//支付后送券
		$couponlist = \app\admin\common\Coupon::getpaygive($aid,$mid,'tuangou',$order['totalprice']);
		if($couponlist){
			foreach($couponlist as $coupon){
				\app\admin\common\Coupon::send($aid,$mid,$coupon['id']);
			}
		}
		\app\admin\common\Wifiprint::print($aid,'tuangou',$order['id']);
		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新团购订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/tuangouorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_orderpay',$tmplcontent,m_url('activity/tuangou/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/tuangouorder',$order['mdid']);

		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['fxjiesuantime'] == 1 && $set['fxjiesuantime_delaydays'] == '0'){
			\app\admin\common\Order::giveCommission($order,'tuangou');
		}

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
	}

	//约课服务支付
	public static function yueke_pay($orderid){
	   $order = Db::name('yueke_order')->where('id',$orderid)->find();
	   $aid = $order['aid'];
	   $member = Db::name('member')->where('id',$order['mid'])->find();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['status']!=1 && $order['status']!=12) return json(['status'=>0,'msg'=>'订单状态不符合']);
		$workerinfo = Db::name('yuyue_worker')->where('aid',$aid)->where('workerid',$order['workerid'])->find();

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'yueke');
        }

		//公众号通知 订单支付成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有新预约订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['totalprice'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/yuekeorder', $aid),$order['mdid']);
		if($workerinfo && $workerinfo['mid']){
			$rs = \app\admin\common\Wechat::sendtmpl($aid,$workerinfo['mid'],'tmpl_orderpay',$tmplcontent,m_url('pagesExt/yueke/workerorderlist', $aid));
		}
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('pagesExt/yueke/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['totalprice'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/yuekeorder',$order['mdid']);

		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['totalprice']
        ]);
	}

	public function business_recharge_pay($orderid){
		$order = Db::name('business_recharge_order')->where('id',$orderid)->find();
		$info = Db::name('business')->where('id',$order['bid'])->find();
		\app\admin\common\Business::addmoney($order['aid'],$order['bid'],$order['money'],t('余额').'充值');

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\admin\common\Order::wxShipping($order['aid'],$order,'business_recharge');
        }
		//\app\admin\common\System::plog('给商户充值'.$order['bid']);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $info['name'],
            'phone' => $info['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['money']
        ]);
	}
	public function yuyue_workerapply_pay($orderid){
		$order = Db::name('yuyue_workerapply_order')->where('id',$orderid)->find();
		$info = Db::name('yuyue_worker')->where('id',$order['bid'])->find();
		//入驻成功给管理员发通知
		$tmplcontent = [];
		$tmplcontent['first'] = '有师傅申请成功';
		$tmplcontent['remark'] = '请登录后台，查看申请详情~';
		$tmplcontent['keyword1'] = '预约师傅申请';
		$tmplcontent['keyword2'] = date('Y-m-d H:i');
		\app\admin\common\Wechat::sendhttmpl(aid,$info['bid'],'tmpl_formsub',$tmplcontent,'');
	}


	//预约补余款支付
	public static function yuyue_addmoney_pay($orderid){
		//var_dump($orderid);
	    $order = Db::name('yuyue_order')->where('id',$orderid)->find();
		$aid = $order['aid'];
        $member = Db::name('member')->where('id',$order['mid'])->find();
		if(!$order) return json(['status'=>0,'msg'=>'订单不存在']);
		if($order['addmoneyStatus']!=1) return json(['status'=>0,'msg'=>'订单状态不符合']);


		//公众号通知 订单支付成功
		/*$tmplcontent = [];
		$tmplcontent['first'] = '补余款订单支付成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $member['nickname']; //用户名
		$tmplcontent['keyword2'] = $order['ordernum'];//订单号
		$tmplcontent['keyword3'] = $order['addmoney'].'元';//订单金额
		$tmplcontent['keyword4'] = $order['title'];//商品信息
		\app\admin\common\Wechat::sendhttmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/yuyueorder', $aid),$order['mdid']);
		$tmplcontent['first'] = '恭喜您的订单已支付成功';
		$rs = \app\admin\common\Wechat::sendtmpl($aid,$order['mid'],'tmpl_orderpay',$tmplcontent,m_url('activity/yuyue/orderlist', $aid));

		$tmplcontent = [];
		$tmplcontent['thing11'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		$tmplcontent['phrase10'] = '已支付';
		$tmplcontent['amount13'] = $order['addmoney'].'元';
		$tmplcontent['thing27'] = $member['nickname'];
		\app\admin\common\Wechat::sendhtwxtmpl($aid,$order['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/yuyueorder',$order['mdid']);
		*/
		//短信通知
		$rs = \app\admin\common\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_orderpay',['ordernum'=>$order['ordernum']]);

        // 发送新订单通知给管理员
        \app\admin\common\Sms::sendToAdmin($aid, 'tmpl_neworder', [
            'consignee' => $order['consignee'],
            'phone' => $order['tel'],
            'ordernum' => $order['ordernum'],
            'amount' => $order['addmoney']
        ]);
	}
	//元宝 更新shop_order和payorder
    static private function yuanbao_up($type,$payorder){

        //元宝支付
        if(getcustom('pay_yuanbao') && $type == 'shop'){
            //如果是元宝支付，则需要重置支付金额
            if($payorder['is_yuanbao_pay'] == 1){
                //更新pay_order
                $up_pay = Db::name('payorder')->where('id',$payorder['id'])->update(['money'=>$payorder['yuanbao_money']]);

                //查询订单
                $order = Db::name('shop_order')->where('id',$payorder['orderid'])->field('aid,ordernum,mid,total_yuanbao')->find();
                if($order){
                    //更新shop_order
                    $up_order = Db::name('shop_order')->where('id',$payorder['orderid'])->update(['is_yuanbao_pay'=>1,'yuanbao_money'=>$payorder['yuanbao_money'],'totalprice'=>$payorder['yuanbao_money']]);
                    //更新商品id
                   	$sel_goods = Db::name('shop_order_goods')
                   		->where('orderid',$payorder['orderid'])
                   		->select()
                   		->toArray();
                   	if($sel_goods){
                   		//查询系统设置
                   		$sysset = Db::name('admin_set')->where('aid',$order['aid'])->find();
                   		//查询商城设置
                   		$shopset = Db::name('shop_sysset')->where('aid',$order['aid'])->field('showjd,comment,showcommission,hide_sales,hide_stock,show_lvupsavemoney')->find();
                   		//查询下单者
                   		$member  = Db::name('member')->where('id',$order['mid'])->find();
				        self::deal_commission($order['aid'],$order,$sel_goods,$sysset,$shopset,$member);
                   	}
                }
                //扣除元宝
                  \app\admin\common\Member::addyuanbao($order['aid'],$order['mid'],-$order['total_yuanbao'],'支付订单：'.$order['ordernum']);
            }
        }
    }
    //元宝转账
    static private function member_yuanbao_transfer_pay($id){

        //元宝支付
        if(getcustom('pay_yuanbao')){

            //查询订单
            $order = Db::name('member_yuanbao_transfer_order')->where('id',$id)->field('aid,mid,to_mid,money,yuanbao,parent1,parent2,parent3,parent1commission,parent2commission,parent3commission,iscommission')->find();
            $aid = $order['aid'];
            if($order){
                $member     = Db::name('member')->where('id',$order['mid'])->field('nickname')->find();
                $to_member  = Db::name('member')->where('id',$order['to_mid'])->field('nickname')->find();
                if($member){
                    //直接转账
                    $rs = \app\admin\common\Member::addyuanbao($aid,$order['to_mid'],$order['yuanbao'],sprintf("来自%s的".t('元宝')."转赠", $member["nickname"]));
                    if ($rs['status'] == 1) {
                        \app\admin\common\Member::addyuanbao($aid,$order['mid'],$order['yuanbao'] * -1, sprintf(t('元宝')."转赠给：%s",$to_member['nickname']));
                    }
                }

                if($order['iscommission'] != 1){
                    $totalcommission = 0;
                    //发奖
                    if($order['parent1'] && $order['parent1commission'] > 0){
                        $totalcommission+=$order['parent1commission'];
                        \app\admin\common\Member::addcommission($aid,$order['parent1'],$order['mid'],$order['parent1commission'],'下级'.t('元宝').'转账奖励');
                    }
                    if($order['parent2'] && $order['parent2commission'] > 0){
                        $totalcommission+=$order['parent2commission'];
                        \app\admin\common\Member::addcommission($aid,$order['parent2'],$order['mid'],$order['parent2commission'],'下二级'.t('元宝').'转账奖励');
                    }
                    if($order['parent3'] && $order['parent3commission'] > 0){
                        $totalcommission+=$order['parent3commission'];
                        \app\admin\common\Member::addcommission($aid,$order['parent3'],$order['mid'],$order['parent3commission'],'下三级'.t('元宝').'转账奖励');
                    }

                    //更新发佣金状态
                    $up = Db::name('member_yuanbao_transfer_order')->where('id',$id)->update(['iscommission'=>1]);
                }
            }
        }
    }
    //重新计算佣金
    static private function deal_commission($aid,$order,$sel_goods,$sysset,$shopset,$member){
        //元宝支付
        if(getcustom('pay_yuanbao')){
            if($sysset){
                $yuanbao_money_ratio = $sysset['yuanbao_money_ratio']/100;
            }else{
                $yuanbao_money_ratio  = 0;
            }
            foreach($sel_goods as $ogdata){

                //计算商品元宝现金价格
                $yuanbao_money = $ogdata['total_yuanbao']*$yuanbao_money_ratio;
                $yuanbao_money = round($yuanbao_money,2);
                //更新商品金额
                $up_goods = Db::name('shop_order_goods')->where('id',$ogdata['id'])->update(['yuanbao_money'=>$yuanbao_money,'totalprice'=>$yuanbao_money,'real_totalprice'=>$yuanbao_money]);


                //删除之前的会员佣金记录
                Db::name('member_commission_record')->where('orderid',$ogdata['orderid'])->where('ogid',$ogdata['id'])->delete();

                //查询规格
                $guige = Db::name('shop_guige')->where('aid',$aid)->where('id',$ogdata['ggid'])->find();

                //实际支付价格
                $og_totalprice = $yuanbao_money;

                //数量
                $num = $ogdata['num'];
                //佣金总价格
                $commission_totalprice   = 0;

                if($sysset['fxjiesuantype']==1){ //按成交价格
					$commission_totalprice = $yuanbao_money;
					if($commission_totalprice < 0){
						$commission_totalprice = 0;
					}
				}
				if($sysset['fxjiesuantype']==2){ //按销售利润
					$commission_totalprice = $yuanbao_money - $guige['cost_price'] * $num;
					if($commission_totalprice < 0) {
						$commission_totalprice = 0;
					}
				}
                $commission_totalpriceCache = $commission_totalprice;

                $ogupdate = [];
                $ogupdate['parent1'] = 0;
            	$ogupdate['parent2'] = 0;
            	$ogupdate['parent3'] = 0;
            	$ogupdate['parent4'] = 0;

            	$ogupdate['parent1commission'] = 0;
            	$ogupdate['parent2commission'] = 0;
            	$ogupdate['parent3commission'] = 0;
            	$ogupdate['parent4commission'] = 0;
                $ogupdate['fhparent2commission'] = 0;
                $ogupdate['fhparent3commission'] = 0;
                $ogupdate['fhparent4commission'] = 0;
                        if($parent1['pid']>0){
                            $parent2 = Db::name('member')->where('id',$parent1['pid'])->find();
                            if($parent2&&$ogupdate['parent1commission']>0){
                                $ogupdate['fhparent2commission'] = $ogupdate['parent1commission']*$agleveldata2['fh_commission2']%10;
                                if($parent2['pid']>0){
                                    $parent3 = Db::name('member')->where('id',$parent2['pid'])->find();
                                    if($parent3&&$ogupdate['parent1commission']>0){
                                        $ogupdate['fhparent3commission'] = $ogupdate['parent1commission']*$agleveldata3['fh_commission3']%10;
                                    }
                                    if($parent3['pid']>0){
                                        $parent4 = Db::name('member')->where('id',$parent3['pid'])->find();
                                        if($parent4&&$ogupdate['parent1commission']>0){
                                            $ogupdate['fhparent4commission'] = $ogupdate['parent1commission']*$agleveldata4['fh_commission4']%10;
                                        }
                                    }
                                }
                            }
                        }
            	$ogupdate['parent1score'] = 0;
            	$ogupdate['parent2score'] = 0;
            	$ogupdate['parent3score'] = 0;

            	$ogupdate['hongbaoEdu'] = 0;
            	$ogupdate['business_total_money'] = 0;
            	//自己是否拿一级分成
                $agleveldata = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
                if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
                    $member['pid'] = $ogdata['mid'];
                }

                //查询商品
                $product = Db::name('shop_product')->where('id',$ogdata['proid'])->find();

                if($product['bid'] > 0) {

                	$store_info = Db::name('business')->where('aid',$aid)->where('id',$product['bid'])->find();

                    $totalprice_business = $og_totalprice;
                    //商品独立费率
                    if($product['feepercent'] != '' && $product['feepercent'] != null && $product['feepercent'] >= 0) {
                        $ogupdate['business_total_money'] = $totalprice_business * (100-$product['feepercent']) * 0.01;
                    } else {
                        //商户费率
                        $ogupdate['business_total_money'] = $totalprice_business * (100-$store_info['feepercent']) * 0.01;
                    }

                }

                if($product['commissionset']!=-1){

                    if($member['pid']){
                        $parent1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->find();
                        if($parent1){
                            $agleveldata1 = Db::name('member_level')->where('aid',$aid)->where('id',$parent1['levelid'])->find();
                            if($agleveldata1['can_agent']!=0){
                                $ogupdate['parent1'] = $parent1['id'];
                            }
                        }
                    }
                    if($parent1['pid']>0){
                        $parent2 = Db::name('member')->where('aid',$aid)->where('id',$parent1['pid'])->find();
                        if($parent2){
                            $agleveldata2 = Db::name('member_level')->where('aid',$aid)->where('id',$parent2['levelid'])->find();
                            if($agleveldata2['can_agent']>1){
                                $ogupdate['parent2'] = $parent2['id'];
                            }
                        }
                    }
                    if($parent2['pid']>0){
                        $parent3 = Db::name('member')->where('aid',$aid)->where('id',$parent2['pid'])->find();
                        if($parent3){
                            $agleveldata3 = Db::name('member_level')->where('aid',$aid)->where('id',$parent3['levelid'])->find();
                            if($agleveldata3['can_agent']>2){
                                $ogupdate['parent3'] = $parent3['id'];
                            }
                        }
                    }
                    if($parent3['pid']>0){
                        $parent4 = Db::name('member')->where('aid',$aid)->where('id',$parent3['pid'])->find();
                        if($parent4){
                            $agleveldata4 = Db::name('member_level')->where('aid',$aid)->where('id',$parent4['levelid'])->find();
                            //持续推荐奖励
                            if($agleveldata4['can_agent'] > 0 && ($agleveldata4['commission_parent'] > 0 || ($parent4['levelid']==$parent3['levelid'] && $agleveldata4['commission_parent_pj'] > 0))){
                                $ogupdate['parent4'] = $parent4['id'];
                            }
                        }
                    }
                    if($product['commissionset']==1){//按商品设置的分销比例
                        $commissiondata = json_decode($product['commissiondata1'],true);
                        if($commissiondata){
                            // PHP 8.0+ 类型转换：JSON数据必须显式转换
                            if($agleveldata1) $ogupdate['parent1commission'] = floatval($commissiondata[$agleveldata1['id']]['commission1'] ?? 0) * $commission_totalprice * 0.01;
                            if($agleveldata2) $ogupdate['parent2commission'] = floatval($commissiondata[$agleveldata2['id']]['commission2'] ?? 0) * $commission_totalprice * 0.01;
                            if($agleveldata3) $ogupdate['parent3commission'] = floatval($commissiondata[$agleveldata3['id']]['commission3'] ?? 0) * $commission_totalprice * 0.01;
                        }
                    }elseif($product['commissionset']==2){//按固定金额
                        $commissiondata = json_decode($product['commissiondata2'],true);
                        if($commissiondata){
                            if(getcustom('fengdanjiangli') && $product['fengdanjiangli']){

                            }else{
                                // PHP 8.0+ 类型转换：JSON数据必须显式转换
                                if($agleveldata1) $ogupdate['parent1commission'] = floatval($commissiondata[$agleveldata1['id']]['commission1'] ?? 0) * intval($num);
                                if($agleveldata2) $ogupdate['parent2commission'] = floatval($commissiondata[$agleveldata2['id']]['commission2'] ?? 0) * intval($num);
                                if($agleveldata3) $ogupdate['parent3commission'] = floatval($commissiondata[$agleveldata3['id']]['commission3'] ?? 0) * intval($num);
                            }
                        }
                    }elseif($product['commissionset']==3){//提成是积分
                        $commissiondata = json_decode($product['commissiondata3'],true);
                        if($commissiondata){
                            // PHP 8.0+ 类型转换：JSON数据必须显式转换
                            if($agleveldata1) $ogupdate['parent1score'] = floatval($commissiondata[$agleveldata1['id']]['commission1'] ?? 0) * intval($num);
                            if($agleveldata2) $ogupdate['parent2score'] = floatval($commissiondata[$agleveldata2['id']]['commission2'] ?? 0) * intval($num);
                            if($agleveldata3) $ogupdate['parent3score'] = floatval($commissiondata[$agleveldata3['id']]['commission3'] ?? 0) * intval($num);
                        }
                    }else{ //按会员等级设置的分销比例
                        if($agleveldata1){
                            if(getcustom('plug_ttdz') && $ogdata['isfg'] == 1){
                                $agleveldata1['commission1'] = $agleveldata1['commission4'];
                            }
                            if($agleveldata1['commissiontype']==1){ //固定金额按单
                                if($istc1==0){
                                    $ogupdate['parent1commission'] = $agleveldata1['commission1'];
                                    $istc1 = 1;
                                }
                            }else{
                                $ogupdate['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                            }
                        }
                        if($agleveldata2){
                            if(getcustom('plug_ttdz') && $ogdata['isfg'] == 1){
                                $agleveldata2['commission2'] = $agleveldata2['commission5'];
                            }
                            $ogupdate['fhparent2commission'] = $ogupdate['parent1commission']*$agleveldata2['fh_commission2']%10;
                            if($agleveldata2['commissiontype']==1){
                                if($istc2==0){
                                    $ogupdate['parent2commission'] = $agleveldata2['commission2'];
                                    $istc2 = 1;
                                    //持续推荐奖励
                                    if($agleveldata2['commission_parent'] > 0) {
                                        $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent'];
                                    }
                                    if($agleveldata1['id'] == $agleveldata2['id'] && $agleveldata2['commission_parent_pj'] > 0) {
                                        $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent_pj'];
                                    }
                                }
                            }else{
                                $ogupdate['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                                //持续推荐奖励
                                if($agleveldata2['commission_parent'] > 0 && $ogupdate['parent1commission'] > 0) {
                                    $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent'] * 0.01;
                                }
                                if($agleveldata1['id'] == $agleveldata2['id'] && $agleveldata2['commission_parent_pj'] > 0 && $ogupdate['parent1commission'] > 0) {
                                    $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent_pj'] * 0.01;
                                }
                            }
                        }
                        if($agleveldata3){
                            if(getcustom('plug_ttdz') && $ogdata['isfg'] == 1){
                                $agleveldata3['commission3'] = $agleveldata3['commission6'];
                            }
                            $ogupdate['fhparent3commission'] = $ogupdate['parent1commission']*$agleveldata2['fh_commission3']%10;
                            if($agleveldata3['commissiontype']==1){
                                if($istc3==0){
                                    $ogupdate['parent3commission'] = $agleveldata3['commission3'];
                                    $istc3 = 1;
                                    //持续推荐奖励
                                    if($agleveldata3['commission_parent'] > 0) {
                                        $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent'];
                                    }
                                    if($agleveldata2['id'] == $agleveldata3['id'] && $agleveldata3['commission_parent_pj'] > 0) {
                                        $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent_pj'];
                                    }
                                }
                            }else{
                                $ogupdate['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                                //持续推荐奖励
                                if($agleveldata3['commission_parent'] > 0 && $ogupdate['parent2commission'] > 0) {
                                    $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent'] * 0.01;
                                }
                                if($agleveldata2['id'] == $agleveldata3['id'] && $agleveldata3['commission_parent_pj'] > 0 && $ogupdate['parent2commission'] > 0) {
                                    $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent_pj'] * 0.01;
                                }
                            }
                        }
                        if($agleveldata4){
                            $ogupdate['fhparent4commission'] = $ogupdate['parent1commission']*$agleveldata2['fh_commission4']%10;
                        }
                        //持续推荐奖励
                        if($agleveldata4['commission_parent'] > 0) {
                            if($agleveldata3['commissiontype']==1){
                                $ogupdate['parent4commission'] = $agleveldata4['commission_parent'];
                            } else {
                                $ogupdate['parent4commission'] = $ogupdate['parent3commission'] * $agleveldata4['commission_parent'] * 0.01;
                            }
                        }
                        if($agleveldata3['id'] == $agleveldata4['id'] && $agleveldata4['commission_parent_pj'] > 0) {
                            if($agleveldata3['commissiontype']==1){
                                $ogupdate['parent4commission'] = $agleveldata4['commission_parent_pj'];
                            } else {
                                $ogupdate['parent4commission'] = $ogupdate['parent3commission'] * $agleveldata4['commission_parent_pj'] * 0.01;
                            }
                        }
                    }
                }
                
                if($ogupdate){
                    Db::name('shop_order_goods')->where('id',$ogdata['id'])->update($ogupdate);
                }

                if($product['commissionset4']==1 && $product['lvprice']==1){ //极差分销

                    if($member['path']){
                        $parentList = Db::name('member')->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
                        if($parentList){
                            $parentList   = array_reverse($parentList);
                            $lvprice_data = json_decode($guige['lvprice_data'],true);
                            $nowprice     = $commission_totalpriceCache;
                            $giveidx      = 0;
                            foreach($parentList as $k=>$parent){
                                if($parent['levelid'] && $lvprice_data[$parent['levelid']]){
                                    $thisprice = floatval($lvprice_data[$parent['levelid']]) * $num;
                                    if($nowprice > $thisprice){
                                        $commission = $nowprice - $thisprice;
                                        $nowprice = $thisprice;
                                        $giveidx++;
                                        //添加新的
                                        Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parent['id'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$commission,'score'=>0,'remark'=>'下级购买商品差价','createtime'=>time()]);
                                    }
                                }
                            }
                        }
                    }
                }
                
                if($parent2 && $ogupdate['fhparent2commission']>0){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'jl_type'=>2,'mid'=>$parent2['id'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$ogupdate['fhparent2commission'],'remark'=>'下一级收入分红','createtime'=>time()]);
                        }
                        if($parent3 && $ogupdate['fhparent3commission']>0){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'jl_type'=>2,'mid'=>$parent3['id'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$ogupdate['fhparent3commission'],'remark'=>'下二级收入分红','createtime'=>time()]);
                        }
                        if($parent4 && $ogupdate['fhparent4commission']>0){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'jl_type'=>2,'mid'=>$parent4['id'],'frommid'=>$og['mid'],'orderid'=>$orderid,'ogid'=>$og['id'],'type'=>'shop','commission'=>$ogupdate['fhparent4commission'],'remark'=>'下三级收入分红','createtime'=>time()]);
                        }
                
                if($product['commissionset']!=4){
                    if(getcustom('plug_ttdz') && $ogdata['isfg'] == 1){
                        if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent1'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>'下级复购奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent2'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>'下二级复购奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent3'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>'下三级复购奖励','createtime'=>time()]);
                        }
                    }else{
                        if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent1'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent2'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent3'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
                        }
                        if($ogupdate['parent4'] && ($ogupdate['parent4commission'])){
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$ogupdate['parent4'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$ogupdate['parent4commission'],'score'=>0,'remark'=>'持续推荐奖励','createtime'=>time()]);
                        }
                    }
                    if($order['checkmemid'] && $commission_totalprice > 0){
                        $checkmember = Db::name('member')->where('aid',$aid)->where('id',$order['checkmemid'])->find();
                        if($checkmember){
                            $buyselect_commission = Db::name('member_level')->where('id',$checkmember['levelid'])->value('buyselect_commission');
                            $checkmemcommission = $buyselect_commission * $commission_totalprice * 0.01;
                            Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$checkmember['id'],'frommid'=>$ogdata['mid'],'orderid'=>$ogdata['orderid'],'ogid'=>$ogdata['id'],'type'=>'shop','commission'=>$checkmemcommission,'score'=>0,'remark'=>'购买商品时指定奖励','createtime'=>time()]);
                        }
                    }
                }

                if(getcustom('everyday_hongbao')) {

                    $hd = Db::name('hongbao_everyday')->where('aid', $aid)->find();
                    $hongbaoEdu = 0;
                    if($product['everyday_hongbao_bl'] === null) {
                        $hongbaoEdu = $og_totalprice * $hd['shop_product_hongbao_bl'] / 100;
                    } elseif($product['everyday_hongbao_bl'] > 0 ) {
                        $hongbaoEdu = $og_totalprice * $product['everyday_hongbao_bl'] / 100;
                    }
                    $hongbaoEdu = round($hongbaoEdu,2);
                    if($hongbaoEdu > 0){
                        Db::name('shop_order_goods')->where('id',$ogupdate['id'])->update(['hongbaoEdu' => $hongbaoEdu]);
                    }
                }
            }
            unset($sg_v);
        }

    }
    public static function cashier_pay($orderid){
	    //如果是多商家且余额支付，则结算商家费用
        $order = Db::name('cashier_order')->where('id',$orderid)->find();
        $totalmoney = $order['totalprice'];
        if($order['paytypeid']==1 && $order['bid']>0 && $totalmoney>0){
            //商家费率
            $feepercent = Db::name('business')->where('aid',$order['aid'])->where('id',$order['bid'])->value('feepercent');
            if($feepercent){
                $totalmoney = $totalmoney * (100-$feepercent) * 0.01;
            }
            //结算给商家
            $res = \app\admin\common\Business::addmoney($order['aid'],$order['bid'],$totalmoney,'收银台收款');
            if(!$res || $res['status']!=1){
              
            }
        }
	    return true;
    }
    public static function dscj_pay($orderid){
        $order = Db::name('dscj_order')->where('id',$orderid)->find();
        //其他订单关闭
        $where = [];
        $where[] = ['status','=',0];
        $where[] = ['bid','=',$order['bid']];
        $where[] = ['aid','=',$order['aid']];
        $where[] = ['hid','=',$order['hid']];
        $where[] = ['mid','=',$order['mid']];
        Db::name('dscj_order')->where($where)->update(['status'=>4]);
        Db::name('dscj')->where('id',$order['hid'])->inc('joinnum',1)->update();
        return true;
    }
    //招聘置顶
    public static function zhaopin_top_pay($orderid){
	    if(getcustom('zhaopin')){
            $order = Db::name('zhaopin_top_order')->where('id',$orderid)->find();
            $zhaopinupdate  = [];
            if($order['status']==1 && $order['related_id']){
                $starttime = $order['paytime'];
                $endtimeS = $order['paytime'];
                $duration = $order['top_duration'];
                $durationTotal = 86400 * $duration;
                $relateinfo = Db::name('zhaopin')->where('aid',$order['aid'])->where('id',$order['related_id'])->find();
                if($relateinfo){
                    if($relateinfo['top_endtime'] && $relateinfo['top_endtime']>time()){
                        $starttime = $relateinfo['top_starttime'];
                        $endtimeS = $relateinfo['top_endtime'];
                    }
                    $zhaopinupdate['top_starttime'] = $starttime;
                    $zhaopinupdate['top_endtime'] = $endtimeS + $durationTotal;
                    $zhaopinupdate['top_feetype'] = $order['top_feetype'];
                    $zhaopinupdate['top_area'] = $order['top_area'];
//                    Db::name('zhaopin')->where('id',$order['related_id'])->update($zhaopinupdate);
                }
            }
            if($order['assurance_total']>0){
                //同步保证金订单
                $ordera = [];
                $ordera['ordernum'] = $order['ordernum'];
                $ordera['top_orderid'] = $order['id'];
                $ordera['totalprice'] = $order['assurance_total'];
                $ordera['createtime'] = time();
                $ordera['title'] = '担保招聘保证金';
                $ordera['aid'] = $order['aid'];
                $ordera['bid'] = $order['bid'];
                $ordera['mid'] = $order['mid'];
                $ordera['apply_id'] = $order['apply_id'];
                $ordera['status'] = 1;
                $ordera['paynum'] = $order['paynum'];
                $ordera['paytype'] = $order['paytype'];
                $ordera['paytime'] = $order['paytime'];
                Db::name('zhaopin_assurancefee_order')->insertGetId($ordera);

                //保证金累加
                Db::name('zhaopin_apply')->where('aid',$order['aid'])->where('id',$order['apply_id'])->update(['assurance_fee'=>Db::raw("assurance_fee+{$order['assurance_total']}")]);

                $data = [];
                $data['createtime'] = time();
                $data['aid'] = $order['aid'];
                $data['bid'] = $order['bid'];
                $data['mid'] = $order['mid'];
                $data['apply_id'] = $order['apply_id'];
                $data['zhaopin_id'] = $order['related_id'];
                $data['status'] = 1;//担保中
                $data['fee'] =  $order['assurance_total'];
                $data['remark'] =  '置顶担保招聘';
                $assurance_id = Db::name('zhaopin_assurance')->insertGetId($data);
                $zhaopinupdate['assurance_id'] = $assurance_id;
            }
            if($order['totalprice']>0){
                //发放奖励
                $givescore = \app\admin\model\Zhaopin::getSetValue($order['aid'],'zhaopin','top_give_score',0);
                if($givescore>0){
                    \app\admin\common\Member::addscore($order['aid'],$order['mid'],$givescore,'招聘置顶奖励');
                }
            }
            if($zhaopinupdate){
                Db::name('zhaopin')->where('id',$order['related_id'])->update($zhaopinupdate);
            }
        }
        return true;
    }

    //vip
    public static function zhaopin_vip_pay($orderid){
        if(getcustom('zhaopin')){
            //其他未支付的订单删除
            $order = Db::name('zhaopin_vip_order')->where('id',$orderid)->find();
            Db::name('zhaopin_vip_order')->where('status',0)->where('mid',$order['mid'])->where('zhaopin_id',$order['zhaopin_id'])->delete();
            Db::name('zhaopin')->where('aid',$order['aid'])->where('id',$order['zhaopin_id'])->update(['vip_orderid'=>$orderid]);
            //vip_order
            Db::name('zhaopin_apply')->where('aid',$order['aid'])->where('mid',$order['mid'])->update(['vip_orderid'=>$orderid,'zhaopin_id'=>$order['zhaopin_id']]);
            return;
        }
        return true;
    }

    //求职置顶
    public static function zhaopin_qiuzhi_top_pay($orderid){
        if(getcustom('zhaopin')) {
            $order = Db::name('zhaopin_qiuzhi_top_order')->where('id', $orderid)->find();
            if ($order['status'] == 1 && $order['related_id']) {
                $starttime = $order['paytime'];
                $endtimeS = $order['paytime'];
                $duration = $order['top_duration'];
                $durationTotal = 86400 * ($duration + 1);
                $relateinfo = Db::name('zhaopin_qiuzhi')->where('aid', $order['aid'])->where('id', $order['related_id'])->find();
                if ($relateinfo) {
                    if ($relateinfo['top_endtime'] && $relateinfo['top_endtime']>time()) {
                        $starttime = $relateinfo['top_starttime'];
                        $endtimeS = $relateinfo['top_endtime'];
                    }
                    $update['top_starttime'] = $starttime;
                    $update['top_endtime'] = $endtimeS + $durationTotal;
                    $update['top_feetype'] = $order['top_feetype'];
                    $update['top_area'] = $order['top_area'];
                    Db::name('zhaopin_qiuzhi')->where('id', $order['related_id'])->update($update);
                }
            }
            //发放奖励
            $givescore = \app\admin\model\Zhaopin::getSetValue($order['aid'],'qiuzhi','top_give_score',0);
            if($givescore>0){
                \app\admin\common\Member::addscore($order['aid'],$order['mid'],$givescore,'求职置顶奖励');
            }
        }
        return true;
    }
    //招聘置顶
    public static function zhaopin_assurancefee_pay($orderid){
        if(getcustom('zhaopin')){
            $order = Db::name('zhaopin_assurancefee_order')->where('id',$orderid)->find();
            if($order['apply_id']){
                //更新商家缴纳保证金的费用
                Db::name('zhaopin_apply')->where('aid',$order['aid'])->where('id',$order['apply_id'])->inc('assurance_fee',$order['totalprice'])->update();
            }
        }
        return true;
    }

    /**
     * @param $orderid
     * @param $type
     * @param $usecoupon_type 1付款后，2确认收货
     * @param $ordernum
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function afterusecoupon($orderid,$type,$usecoupon_type=1,$ordernum='')
    {
        //所有使用优惠券的订单类型maidan = couponrid
        if(getcustom('usecoupon_give_score') || getcustom('usecoupon_give_coupon') || getcustom('usecoupon_give_money')){
            if(in_array($type,[
                'shop','shop_hb','yuyue','tuangou','seckill2','seckill',
                'restaurant_takeaway','restaurant_shop','restaurant_booking',
                'maidan','lucky_collage','dscj','collage','cashier'
            ])){
                $couponOrders = [];//优惠券字段 全部转为coupon_rid
                //特殊处理的表
                if($type=='shop_hb'){
                    $couponOrders = Db::name('shop_order')->where('ordernum','like',$ordernum.'%')->select()->toArray();
                }elseif($type=='maidan'){
                    $couponOrders[] = Db::name('maidan_order')->where('id',$orderid)->field('*,couponrid coupon_rid')->find();
                }else{
                    $couponOrders[] = Db::name($type.'_order')->where('id',$orderid)->find();
                }
                if(empty($couponOrders)){
                    return true;
                }
                foreach ($couponOrders as $key=>$order){
                    if(!isset($order['coupon_rid']) || empty($order['coupon_rid'])){
                        continue;
                    }
                    \app\admin\common\Coupon::useCoupon($order['aid'],$order['coupon_rid'],$usecoupon_type);
                }
            }
        }

        return true;
    }

    public static function send_free_notice($payorder){

    	if(getcustom('invite_free')){
    		//查询免单设置
            $set = Db::name('invite_free')->where('aid',$payorder['aid'])->find();
    		if($set && $set['status'] ==1 && $set['start_time']<=time() && $set['end_time']>=time() && $payorder['aid'] && $payorder['aid'] == 10){
    			//查询订单
		        $order = Db::name('shop_order')->where('id',$payorder['orderid'])->field('id,ordernum,mid,title,totalprice,mdid')->find();
		        if($order){
		        	//查询他下单次数
		        	$count_num = Db::name('shop_order')
		        		->where('mid',$order['mid'])
		        		->where('status','>=',1)
		        		->where('status','<=',3)
		        		->count();
		        	if($count_num == 1){
		        		//查询他上级
		        		$member = Db::name('member')
		        			->where('id',$order['mid'])
		        			->field('pid')
		        			->find();
		        		if($member && $member['pid']>0){

							// $tmplcontent['keyword1'] = Db::name('admin_set')->where('aid',aid)->value('name'); //店铺
							// $tmplcontent['thing27'] = $this->member['nickname'];

						//公众号通知 发送有新伙伴下单
							$tmplcontent = [];
							$tmplcontent['first']    = '有新伙伴下单';
							$tmplcontent['remark']   = '点击进入查看~';
							$tmplcontent['keyword1'] = ''; //店铺
							$tmplcontent['keyword2'] = date('Y-m-d H:i:s',$payorder['paytime']);//下单时间
							$tmplcontent['keyword3'] = $order['title']?$order['title']:'';//商品
							$tmplcontent['keyword4'] = $order['totalprice']?$order['totalprice']:'';//金额
							$tmplcontentNew = [];
							$tmplcontentNew['thing8'] = \app\admin\common\Mendian::getNameWithBusines($order);
							$tmplcontentNew['character_string2'] = $order['ordernum']?$order['ordernum']:'';
							$tmplcontentNew['thing3'] = $order['title']?$order['title']:'';
							$tmplcontentNew['amount7'] = $order['totalprice']?$order['totalprice']:'0.00';
							$tmplcontentNew['time4'] = date('Y-m-d H:i:s',$payorder['paytime']);
							\app\admin\common\Wechat::sendtmpl($payorder['aid'],$member['pid'],'tmpl_orderconfirm',$tmplcontent,m_url('pagesExt/invite_free/index'),$tmplcontentNew);

				        	//查询通知设置
				        	$wx_tmplset = Db::name('wx_tmplset')->where('aid',$payorder['aid'])->field('tmpl_orderconfirm')->find();
				        	if($wx_tmplset && $wx_tmplset['tmpl_orderconfirm']){
								$tmplcontent = [];
								$tmplcontent['thing11']  = $order['title']?$order['title']:'';//商品
								$tmplcontent['character_string2'] = $order['ordernum']?$order['ordernum']:'';
								$tmplcontent['phrase10'] = '新伙伴下单';
								$tmplcontent['amount13'] =  $order['totalprice']?$order['totalprice']:'';//金额
								$tmplcontent['thing27']  = '';
								\app\admin\common\Wechat::sendwxtmpl($payorder['aid'],$member['pid'],'tmpl_orderconfirm',$tmplcontent,m_url('pagesExt/invite_free/index'),$order['mdid']);
							}
		        		}
		        	}
		        }
    		}

	    }
    }

    public static function xixie_pay($orderid){
        if (class_exists('\\app\\plugin\\xixie\\service\\XixieService')) {
            return \app\plugin\xixie\service\XixieService::onPay(intval($orderid));
        }
        return ['status'=>0,'msg'=>'清洗到家插件未安装'];
    }
    
 // 秒杀订单手续费
public static function miaoshasxf_pay($orderid)
{
    if (class_exists('\\app\\plugin\\jishou\\Plugin')) {
        \app\plugin\jishou\Plugin::ensureRuntimeSchema();
    }
    $order = Db::name('miaoshasxf_order')->where('id', $orderid)->find();
    if (!empty($order)) {
        $shop_order_id = $order['orderid'];
        $aid = $order['aid'];
        $mid = $order['mid'];
        $bid = $order['bid'];

        // 获取系统设置，包括 miaosha_zengsonggxz
        $admin_set = Db::name('miaosha_sysset')->where('aid', $aid)->find();

        // 获取商品信息
        $product = Db::name('miaosha_product')->where('aid', $aid)->where('id', $shop_order_id)->find();
        if (!$product) {
            return;
        }
        $insertdata = [];

        // 计算利润
        if ($admin_set['miaosha_lirun'] > 0) {
            $miaosha_lirun = $product['sell_price'] * $admin_set['miaosha_lirun'] * 0.01;
        } else {
            $miaosha_lirun = 0;
        }

        // 计算价格
        if ($admin_set['iszhang'] == 2) {
            // 虚拟支付价格 + 手续费 + 用户利润
            $price = $product['xuni_price'] + $miaosha_lirun + $order['money'];
            $price = \app\plugin\jishou\Plugin::roundZhangfuMoney($price, $admin_set);
        } else {
            $price = \app\plugin\jishou\Plugin::calcZhangfuSellPrice($product['sell_price'], $admin_set['miaosha_zhangfu'] ?? 0, $admin_set);
        }

        // 准备插入数据
        $insertdata['ordernum'] = 'DB' . date('YmdHis') . uniqid();
        $insertdata['aid'] = $aid;
        $insertdata['status'] = 1;
        $insertdata['status2'] = 2;
        $insertdata['prostatus2'] = 2;
        $insertdata['sxf'] = $order['money'];
        $insertdata['price'] = $price;
        $insertdata['changci'] = 1;

        // 修改部分：使用商品的 changciid，如果没有则使用 admin_set 的
        $preferredChangciId = intval($product['changciid'] ?? 0);
        if ($preferredChangciId <= 0) {
            $preferredChangciId = intval($admin_set['miaosha_changciid'] ?? 0);
        }
        $resolvedChangci = class_exists('\\app\\plugin\\jishou\\Plugin')
            ? \app\plugin\jishou\Plugin::resolveAvailableChangci($aid, $preferredChangciId, intval($product['bid'] ?? 0), true)
            : [];
        $insertdata['changciid'] = intval($resolvedChangci['id'] ?? 0);
        if ($insertdata['changciid'] <= 0) {
            $insertdata['changciid'] = $preferredChangciId;
        }
        if ($insertdata['changciid'] > 0 && intval($product['changciid'] ?? 0) !== $insertdata['changciid']) {
            Db::name('miaosha_product')
                ->where('aid', $aid)
                ->where('id', $shop_order_id)
                ->update([
                    'changciid' => $insertdata['changciid'],
                    'updatetime' => time(),
                ]);
        }
        $changciId = intval($insertdata['changciid'] ?? 0);
        $changci = !empty($resolvedChangci) ? $resolvedChangci : [];
        if ($changciId > 0 && empty($changci)) {
            $changci = Db::name('miaosha_changci')
                ->where('aid', $aid)
                ->where('id', $changciId)
                ->find();
        }
        $miaoshaStarttime = intval($changci['starttime'] ?? 0);
        if ($miaoshaStarttime <= 0) {
            $miaoshaStarttime = time();
        }
        $insertdata['starttime'] = $miaoshaStarttime;
        $insertdata['miaosha_date'] = date('Y-m-d H:i:s', $miaoshaStarttime);

        $insertdata['bid'] = $bid;
        $insertdata['outid'] = $mid;
        $insertdata['orderid'] = $shop_order_id;
        $insertdata['goodsid'] = $shop_order_id;
        $insertdata['goodcate'] = $product['cid'];
        $insertdata['zhangfu'] = $admin_set['miaosha_zhangfu'];
        $insertdata['addtime'] = date('Y-m-d H:i:s');
        $insertdata['updatetime'] = date('Y-m-d H:i:s');
        $insertdata['memberids'] = '';
        $insertdata['tiqian'] = 0;
        $insertdata['tiqiandetail'] = [];
        $insertdata['zhangprice'] = \app\plugin\jishou\Plugin::calcZhangfuAmount($product['sell_price'], $admin_set['miaosha_zhangfu'] ?? 0, $admin_set);
        $existMiaosha = Db::name('miaosha')
            ->where('aid', $aid)
            ->where('goodsid', $shop_order_id)
            ->where('outid', $mid)
            ->order('id desc')
            ->find();
        if ($existMiaosha) {
            Db::name('miaosha')
                ->where('aid', $aid)
                ->where('id', intval($existMiaosha['id']))
                ->update([
                    'status' => $insertdata['status'],
                    'status2' => $insertdata['status2'],
                    'prostatus2' => $insertdata['prostatus2'],
                    'sxf' => $insertdata['sxf'],
                    'price' => $insertdata['price'],
                    'changci' => $insertdata['changci'],
                    'changciid' => $insertdata['changciid'],
                    'miaosha_date' => $insertdata['miaosha_date'],
                    'starttime' => $insertdata['starttime'],
                    'bid' => $insertdata['bid'],
                    'outid' => $insertdata['outid'],
                    'orderid' => $insertdata['orderid'],
                    'goodsid' => $insertdata['goodsid'],
                    'goodcate' => $insertdata['goodcate'],
                    'zhangfu' => $insertdata['zhangfu'],
                    'updatetime' => $insertdata['updatetime'],
                    'memberids' => $insertdata['memberids'],
                    'tiqian' => $insertdata['tiqian'],
                    'tiqiandetail' => $insertdata['tiqiandetail'],
                    'zhangprice' => $insertdata['zhangprice'],
                ]);
            $id = intval($existMiaosha['id']);
        } else {
            $id = Db::name('miaosha')->insertGetId($insertdata);
        }

        // 更新商品信息
        Db::name('miaosha_product')->where('id', $insertdata['goodsid'])->update(['sell_price' => $price, 'status2' => 2]);

        // 添加利润记录
        $insertdata2 = [];
        $insertdata2['aid'] = $aid;
        $insertdata2['mid'] = $mid;
        $insertdata2['lirun'] = $miaosha_lirun;
        $insertdata2['sell_price'] = $product['sell_price'];
        $insertdata2['zhang_price'] = \app\plugin\jishou\Plugin::calcZhangfuAmount($product['sell_price'], $admin_set['miaosha_zhangfu'] ?? 0, $admin_set);
        $insertdata2['sxf'] = $order['money'];

        // 修改部分：使用商品的 changciid，如果没有则使用 admin_set 的
        $insertdata2['changciid'] = intval($insertdata['changciid'] ?? 0);

        $insertdata2['goodsid'] = $shop_order_id;
        $insertdata2['addtime'] = date('Y-m-d H:i:s');
        try {
            $id = Db::name('miaosha_lirun')->insertGetId($insertdata2);
        } catch (\Throwable $e) {
            Log::warning('miaoshasxf_pay profit log insert failed: ' . $e->getMessage(), [
                'aid' => $aid,
                'mid' => $mid,
                'goodsid' => $shop_order_id,
                'changciid' => intval($insertdata2['changciid'] ?? 0),
            ]);
        }

        // 给上级佣金
        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->field('pid')->find();
        if ($member['pid'] > 0) {
            $miaoshasysset = Db::name('miaosha_sysset')->where('aid', $aid)->field('miaosha_zhitui')->find();
            if ($miaoshasysset['miaosha_zhitui'] > 0) {
                $totalprice_daozhang = $product['sell_price'] * $miaoshasysset['miaosha_zhitui'] * 0.01;
                $totalprice_daozhang = round($totalprice_daozhang, 2);
                \app\admin\common\Member::addcommission($aid, $member['pid'], $mid, $totalprice_daozhang, '我的下级委托上架佣金');
            }
        }

        // 扣除收益池金额
        if ($miaosha_lirun > 0) {
            \app\admin\common\Member::addsyc($aid, $mid, -$miaosha_lirun, '扣除收益池金额' . $miaosha_lirun, 0);
        }

        // 添加贡献值
        if ($admin_set['miaosha_zengsonggxz'] > 0) {
            // 计算贡献值：手续费 * 比例
            $gongxianzhi = $order['money'] * $admin_set['miaosha_zengsonggxz'] * 0.01;
            $gongxianzhi = round($gongxianzhi, 2);

            // 调用添加贡献值的方法
            \app\admin\common\Member::addgongxianzhi($aid, $mid, $gongxianzhi, '支付手续费赠送' . t('贡献值'));
        }
    }
}

public static function miaosha_bid_deposit_pay($orderid, $ordernum = '')
{
    $order = Db::name('miaosha_bid_deposit_order')->where('id', intval($orderid))->find();
    if (!$order) {
        return ['status' => 0, 'msg' => '拍卖保证金订单不存在'];
    }
    Db::name('miaosha_bid_deposit_order')->where('id', intval($orderid))->update([
        'status' => 1,
        'updatetime' => time(),
        'paytime' => intval($order['paytime'] ?? 0) > 0 ? intval($order['paytime']) : time(),
    ]);
    return ['status' => 1, 'msg' => ''];
}



    public static function xixie_vip_pay($orderid){
        return ['status'=>1,'msg'=>''];
    }

    public static function article_reward_pay($orderid){
        if(getcustom('article_reward')){
            $order = Db::name('article_reward_order')->where('id',$orderid)->find();
            if($order){
                //增加打赏
                \app\admin\common\Member::addmoney($order['aid'],$order['send_mid'],$order['num'],'文章打赏');
            }
        }
    }

    public static function allianceactivity_pay($orderid, $ordernum = ''){
        $order = Db::name('allianceactivity_order')->where('id',$orderid)->find();
        if(!$order){
            return ['status'=>0,'msg'=>'订单不存在'];
        }
        return \app\plugin\allianceactivity\common\AllianceActivityService::syncDirectbuyOrderPaidByOrderId(
            intval($order['aid'] ?? 0),
            intval($orderid),
            [
                'expected_mid' => intval($order['mid'] ?? 0),
                'payorder_id' => intval($order['payorder_id'] ?? 0),
            ]
        );
    }

    public static function fangyuan_staff_seat_pay($orderid, $ordernum = '')
    {
        if (!class_exists('\\app\\plugin\\fangyuan\\common\\FangyuanService')) {
            return ['status' => 0, 'msg' => '房源协作服务不可用'];
        }
        return \app\plugin\fangyuan\common\FangyuanService::markStaffSeatOrderPaid(intval($orderid));
    }



    
	//更新商户销量
	//拓客活动支付成功
	public static function hbtk_pay($orderid,$ordernum=''){
		$order = Db::name('hbtk_order')->where('id',$orderid)->find();
		if(!$order) return ['status'=>0,'msg'=>'订单不存在'];
		$hbtkOrderTable = config('database.connections.mysql.prefix') . 'hbtk_order';
		$has_lottery_status_column = !empty(Db::query("SHOW COLUMNS FROM `{$hbtkOrderTable}` LIKE 'lottery_status'"));
		$has_lottery_time_column = !empty(Db::query("SHOW COLUMNS FROM `{$hbtkOrderTable}` LIKE 'lottery_time'"));

		$lottery_status = intval($order['lottery_status'] ?? 0);
		if($lottery_status === 0 && intval($order['jx'] ?? 0) > 0){
			$lottery_status = 2;
		}

		// 检查是否已经抽过奖
		if($lottery_status > 0) return ['status'=>1,'msg'=>'订单已抽奖','lottery_type'=>0,'jx'=>$order['jx'],'jxmc'=>$order['jxmc'],'lottery_status'=>$lottery_status];

		// 调用抽奖逻辑（使用反射调用私有方法或直接复制逻辑）
		$hid = $order['hid'];
		$hd = Db::name('hbtk_activity')->where('id',$hid)->find();
		
		$mid = $order['mid'];
		$aid = $order['aid'];
		
		// ==================== 推荐分润处理 ====================
		// 只在首次处理时执行推荐分润（通过 tuijian_status 字段判断）
		if($order['pid'] > 0 && $order['tuijian_status'] == 0 && ($hd['tuijian_status'] ?? 0) == 1){
			self::hbtk_tuijian_reward($orderid, $order, $hd);
		}
		// ==================== 推荐分润处理结束 ====================
		
		// ==================== 分享抽奖资格处理 ====================
		// 如果开启了分享抽奖功能，给推荐人增加一次抽奖资格
		if($order['pid'] > 0 && ($hd['share_lottery_status'] ?? 0) == 1){
			// 检查表是否存在
			$tableExists = Db::query("SHOW TABLES LIKE 'ddwx_hbtk_share_lottery'");
			if(!empty($tableExists)){
				// 检查是否已经给过这个订单的分享抽奖资格（避免重复）
				$exists = Db::name('hbtk_share_lottery')
					->where('aid', $aid)
					->where('hid', $hid)
					->where('order_id', $orderid)
					->find();
				
				if(!$exists){
					// 获取配置的分享抽奖次数，默认为1次
					$share_lottery_count = isset($hd['share_lottery_count']) ? intval($hd['share_lottery_count']) : 1;
					if($share_lottery_count < 1){
						$share_lottery_count = 1; // 最少1次
					}
					
					// 检查分享者已经获得的抽奖资格数量（包括已使用和未使用的）
					$share_lottery_max = isset($hd['share_lottery_max']) ? intval($hd['share_lottery_max']) : 0;
					$already_got_count = 0;
					if($share_lottery_max > 0){
						// 如果设置了上限，检查分享者已经获得的抽奖资格总数
						$already_got_count = Db::name('hbtk_share_lottery')
							->where('aid', $aid)
							->where('hid', $hid)
							->where('mid', $order['pid'])
							->count();
					}
					
					// 计算本次可以增加的次数
					$can_add_count = $share_lottery_count;
					if($share_lottery_max > 0){
						// 如果设置了上限，计算还能增加多少次
						$remaining = $share_lottery_max - $already_got_count;
						if($remaining <= 0){
							// 已经达到上限，不再增加
							\think\facade\Log::info('分享抽奖资格：分享者已达到上限（'.$share_lottery_max.'次） pid='.$order['pid'].', orderid='.$orderid);
							$can_add_count = 0;
						} else if($remaining < $share_lottery_count){
							// 剩余次数少于配置的次数，只能增加剩余次数
							$can_add_count = $remaining;
							\think\facade\Log::info('分享抽奖资格：分享者剩余次数不足，只能增加'.$can_add_count.'次（已获得'.$already_got_count.'次，上限'.$share_lottery_max.'次） pid='.$order['pid'].', orderid='.$orderid);
						}
					}
					
					// 给推荐人增加抽奖次数
					if($can_add_count > 0){
						for($i = 0; $i < $can_add_count; $i++){
							Db::name('hbtk_share_lottery')->insert([
								'aid' => $aid,
								'hid' => $hid,
								'mid' => $order['pid'], // 推荐人ID
								'order_id' => $orderid, // 被分享人下单的订单ID
								'buyer_mid' => $mid, // 被分享人ID
								'status' => 0, // 未使用
								'createtime' => time()
							]);
						}
						\think\facade\Log::info('分享抽奖资格：给推荐人增加'.$can_add_count.'次抽奖资格 pid='.$order['pid'].', orderid='.$orderid);
					}
				}
			}
		}
		// ==================== 分享抽奖资格处理结束 ====================
		
		// 检查是否启用抽奖
		if($hd['lottery_type'] == 0){
			// 不启用抽奖，直接完成订单
			Db::name('hbtk_order')->where('id',$orderid)->update(['status'=>1]);
			return ['status'=>1,'msg'=>'','prize'=>'','lottery_type'=>0];
		}
		
		// 如果抽奖方式不是默认（直接发放），返回抽奖类型和订单ID，让前端跳转到抽奖页面
		if($hd['lottery_type'] > 1){
			// 不立即抽奖，返回抽奖类型让前端跳转
			return ['status'=>1,'msg'=>'','lottery_type'=>$hd['lottery_type'],'orderid'=>$orderid,'hid'=>$hid];
		}
		
		// 抽奖逻辑（复制自 ApiHbtkActivity::hbtk_pay）
		if($hd['j1yj'] > $hd['j1sl']) $hd['j1yj'] = $hd['j1sl'];
		if($hd['j2yj'] > $hd['j2sl']) $hd['j2yj'] = $hd['j2sl'];
		if($hd['j3yj'] > $hd['j3sl']) $hd['j3yj'] = $hd['j3sl'];
		if($hd['j4yj'] > $hd['j4sl']) $hd['j4yj'] = $hd['j4sl'];
		if($hd['j5yj'] > $hd['j5sl']) $hd['j5yj'] = $hd['j5sl'];
		if($hd['j6yj'] > $hd['j6sl']) $hd['j6yj'] = $hd['j6sl'];
		if($hd['j7yj'] > $hd['j7sl']) $hd['j7yj'] = $hd['j7sl'];
		if($hd['j8yj'] > $hd['j8sl']) $hd['j8yj'] = $hd['j8sl'];
		if($hd['j9yj'] > $hd['j9sl']) $hd['j9yj'] = $hd['j9sl'];
		if($hd['j10yj'] > $hd['j10sl']) $hd['j10yj'] = $hd['j10sl'];
		if($hd['j11yj'] > $hd['j11sl']) $hd['j11yj'] = $hd['j11sl'];
		if($hd['j12yj'] > $hd['j12sl']) $hd['j12yj'] = $hd['j12sl'];
		$count =  ($hd['j1sl'] - $hd['j1yj']) + ($hd['j2sl'] - $hd['j2yj']) + ($hd['j3sl'] - $hd['j3yj']) + ($hd['j4sl'] - $hd['j4yj']) + ($hd['j5sl'] - $hd['j5yj']) + ($hd['j6sl'] - $hd['j6yj']) + ($hd['j7sl'] - $hd['j7yj']) + ($hd['j8sl'] - $hd['j8yj']) + ($hd['j9sl'] - $hd['j9yj']) + ($hd['j10sl'] - $hd['j10yj']) + ($hd['j11sl'] - $hd['j11yj']) + ($hd['j12sl'] - $hd['j12yj']);

		$jx = 0;
		$jxmc = '';
		$jxtp = 0;
		
		if($count>0){
			$jparr = [
				($hd['j1sl'] - $hd['j1yj']),
				($hd['j2sl'] - $hd['j2yj']),
				($hd['j3sl'] - $hd['j3yj']),
				($hd['j4sl'] - $hd['j4yj']),
				($hd['j5sl'] - $hd['j5yj']),
				($hd['j6sl'] - $hd['j6yj']),
				($hd['j7sl'] - $hd['j7yj']),
				($hd['j8sl'] - $hd['j8yj']),
				($hd['j9sl'] - $hd['j9yj']),
				($hd['j10sl'] - $hd['j10yj']),
				($hd['j11sl'] - $hd['j11yj']),
				($hd['j12sl'] - $hd['j12yj']),
			];
			$rands = rand(1,$count);
			$qian = 0;
			foreach ($jparr as $k=>$v) {
				if($rands > $qian && $rands <= $qian + $v){
					$jx = $k+1;
					$jxmc = $hd["j{$jx}mc"];
					$jxtp = $hd["j{$jx}tp"];
					break;
				}
				$qian += $v;
			}
			
			// 更新活动已出奖数
			Db::name('hbtk_activity')->where('id',$hid)->update(["j{$jx}yj" => Db::raw("j{$jx}yj+1")]);
			
			// 发放奖品
			if($jx > 0 && $jxtp > 0){
				// 2=微信红包, 3=优惠券, 4=积分, 5=余额, 6=分账（发放到微信）
				if($jxtp == 2){
					// 微信红包
					try {
						srand(microtime(true) * 1000);
						$moneyArr = explode('-',str_replace('~','-',$jxmc));
						if(!$moneyArr[1]) $moneyArr[1] = $moneyArr[0];
						$ss = rand($moneyArr[0]*100,$moneyArr[1]*100);
						$money = number_format($ss/100, 2, '.', '');
						$jxmc = $money.'元红包';
						$rs = \app\admin\common\Wxpay::sendredpackage($aid,$mid,platform,$money,mb_substr($hd['name'],0,10),'微信红包','恭喜发财','微信红包',$hd['scene_id']);
						if($rs['status']==0){
							$jxmc .= '(发放失败:'.$rs['msg'].')';
						}
					} catch (\Exception $e) {
						// 红包发放失败，记录错误但不中断流程
						$jxmc .= '(发放失败:证书配置错误)';
						\think\facade\Log::error('微信红包发放失败: ' . $e->getMessage());
					}
				}elseif($jxtp == 3){
					// 优惠券
					$coupon_id = intval($jxmc);
					if($coupon_id > 0){
						$coupon = Db::name('coupon')->where('id',$coupon_id)->where('aid',$aid)->find();
						if($coupon){
							\app\admin\common\Member::addcoupon($aid,$mid,$coupon_id,1,'拓客活动抽奖');
							$jxmc = $coupon['name'];
						}
					}
				}elseif($jxtp == 4){
					// 积分
					$score = floatval($jxmc);
					if($score > 0){
						\app\admin\common\Member::addscore($aid,$mid,$score,t('积分').'抽奖');
						$jxmc = $score.t('积分');
					}
				}elseif($jxtp == 5){
					// 余额
					$money = floatval($jxmc);
					if($money > 0){
						\app\admin\common\Member::addmoney($aid,$mid,$money,t('余额').'抽奖');
						$jxmc = '¥'.$money;
					}
				}elseif($jxtp == 6){
					// 分账（发放到微信）- 异步执行，先记录待分账信息
					$split_money = floatval($jxmc);
					if($split_money > 0){
						// 记录待分账信息，由定时任务执行分账
						Db::name('hbtk_order')->where('id',$orderid)->update([
							'split_money' => $split_money,
							'split_status' => 0  // 0=待分账
						]);
						$jxmc = '¥'.$split_money.'(发放中)';
					}
				}
			}
		}
		
		$data = [];
		$data['jx'] = $jx;
		$data['jxtp'] = $jxtp;
		$data['jxmc'] = $jxmc;
		if($has_lottery_status_column){
			$data['lottery_status'] = $jx > 0 ? 2 : 1;
		}
		if($has_lottery_time_column){
			$data['lottery_time'] = time();
		}
		$data['status'] = 1;
		Db::name('hbtk_order')->where('id',$orderid)->update($data);
		
		return ['status'=>1,'msg'=>'','prize'=>$data['jxmc'],'lottery_type'=>1,'jx'=>$jx,'jxtp'=>$jxtp,'jxmc'=>$jxmc,'lottery_status'=>$data['lottery_status'] ?? ($jx > 0 ? 2 : 1), 'lottery_time' => $data['lottery_time'] ?? 0];
	}
	
	public static function addSales($orderid,$type,$aid,$bid=0,$sale_num=0){
        $sales_type = [
            'sales' => 'sales',//虚拟销量
            'shop' => 'shop_sales',//普通商铺
            'collage' => 'collage_sales',//多人拼团
            'kanjia' => 'kanjia_sales',//砍价
            'seckill' => 'seckill_sales',//秒杀
            'tuangou' => 'tuangou_sales',//团购
            'scoreshop' => 'scoreshop_sales',//积分商城
            'lucky_collage' => 'lucky_collage_sales',//幸运拼团
            'yuyue' => 'yuyue_sales',//预约服务
            'kecheng' => 'kecheng_sales',//课程
            'cycle' => 'cycle_sales',//周期购
            'restaurant_takeaway' => 'restaurant_takeaway_sales',//餐饮外卖
            'restaurant_shop' => 'restaurant_shop_sales',//餐饮点餐
            'maidan' => 'maidan_sales'//买单
        ];
        if(!empty($sales_type[$type])){
            if($sale_num==0 && $orderid){
                switch ($type){
                    case 'shop':
                        $sale_num = Db::name('shop_order_goods')->where('orderid',$orderid)->sum('num');
                        break;
                    case 'collage':
                        $sale_num = Db::name('collage_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'kanjia':
                        $sale_num = Db::name('kanjia_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'seckill':
                        $sale_num = Db::name('seckill_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'tuangou':
                        $sale_num = Db::name('tuangou_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'scoreshop':
                        $sale_num = Db::name('scoreshop_order_goods')->where('orderid',$orderid)->sum('num');
                        break;
                    case 'lucky_collage':
                        $sale_num = Db::name('lucky_collage_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'yuyue':
                        $sale_num = Db::name('yuyue_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'kecheng':
                        $sale_num = 1;
                        break;
                    case 'cycle':
                        $sale_num = Db::name('cycle_order')->where('id',$orderid)->sum('num');
                        break;
                    case 'restaurant_takeaway':
                        $sale_num = Db::name('restaurant_takeaway_order_goods')->where('orderid',$orderid)->sum('num');
                        break;
                    case 'restaurant_shop':
                        $sale_num = Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->sum('num');
                        break;
                    case 'maidan':
                        $sale_num = 1;
                        break;
                }
            }

            $sales_field = $sales_type[$type];
            $business_sales = Db::name('business_sales')
                ->where('aid',$aid)
                ->where('bid',$bid)
                ->find();
            if(!$business_sales && $sale_num>0){
                $data_sales = [];
                $data_sales['aid'] = $aid;
                $data_sales['bid'] = $bid;
                $data_sales[$sales_field] = $sale_num;
                $data_sales['total_sales'] = $sale_num;
                Db::name('business_sales')->insert($data_sales);
            }else{
                $data_sales = [];
                $data_sales[$sales_field] = $business_sales[$sales_field]+$sale_num;
                $data_sales['total_sales'] = $business_sales['total_sales']+$sale_num;
                Db::name('business_sales')->where('id',$business_sales['id'])->update($data_sales);
            }

        }
    }

    /**
     * 大转盘推荐分润处理
     * @param int $orderid 订单ID
     * @param array $order 订单信息
     * @param array $hd 活动信息
     */
    private static function hbtk_tuijian_reward($orderid, $order, $hd){
        try {
            $aid = $order['aid'];
            $pid = $order['pid'];  // 推荐人ID
            
            // 获取推荐人信息
            $parent = Db::name('member')->where('id', $pid)->find();
            if(!$parent){
                \think\facade\Log::info('大转盘推荐分润：推荐人不存在 pid='.$pid);
                return;
            }
            
            // 解析等级配置
            $tuijian_config = json_decode($hd['tuijian_config'] ?? '{}', true) ?: [];
            $parent_levelid = $parent['levelid'] ?? 0;
            
            // 获取推荐人等级对应的奖励配置
            $reward_value = floatval($tuijian_config[$parent_levelid] ?? 0);
            if($reward_value <= 0){
                \think\facade\Log::info('大转盘推荐分润：该等级无奖励配置 levelid='.$parent_levelid);
                return;
            }
            
            // 计算奖励金额
            $tuijian_type = $hd['tuijian_type'] ?? 1;  // 1固定金额 2按比例
            $tuijian_mode = $hd['tuijian_mode'] ?? 1;  // 1佣金 2分账
            $order_price = floatval($order['price']);
            
            if($tuijian_type == 1){
                // 固定金额
                $reward_money = $reward_value;
            }else{
                // 按比例
                $reward_money = round($order_price * $reward_value / 100, 2);
            }
            
            if($reward_money <= 0){
                \think\facade\Log::info('大转盘推荐分润：计算奖励金额为0');
                return;
            }
            
            \think\facade\Log::info('大转盘推荐分润开始: orderid='.$orderid.', pid='.$pid.', levelid='.$parent_levelid.', reward_money='.$reward_money.', mode='.$tuijian_mode);
            
            // 根据发放方式处理
            if($tuijian_mode == 1){
                // 发放到佣金
                \app\admin\common\Member::addcommission($aid, $pid, 0, $reward_money, '大转盘推荐奖励');
                // 更新订单推荐分润状态
                Db::name('hbtk_order')->where('id', $orderid)->update([
                    'tuijian_money' => $reward_money,
                    'tuijian_status' => 1,
                    'tuijian_errmsg' => '佣金已发放',
                    'tuijian_mode' => 1
                ]);
                \think\facade\Log::info('大转盘推荐分润：佣金发放成功 pid='.$pid.' money='.$reward_money);
            }else{
                // 分账方式 - 记录待分账信息，由定时任务执行
                // 注意：分账需要有支付订单号，这里使用订单的 split_transaction_id
                Db::name('hbtk_order')->where('id', $orderid)->update([
                    'tuijian_money' => $reward_money,
                    'tuijian_status' => 0,  // 待分账
                    'tuijian_errmsg' => '',
                    'tuijian_mode' => 2
                ]);
                \think\facade\Log::info('大转盘推荐分润：分账待执行 pid='.$pid.' money='.$reward_money);
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('大转盘推荐分润异常: '.$e->getMessage());
        }
    }

}
