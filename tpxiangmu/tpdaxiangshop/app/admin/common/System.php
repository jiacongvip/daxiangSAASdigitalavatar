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
use app\plugin\jishou\Plugin as JishouPlugin;
use think\facade\Db;
class System
{
	public static function plainText($value): string
	{
		$text = html_entity_decode(strval($value ?? ''), ENT_QUOTES, 'UTF-8');
		if (trim($text) === '') {
			return '';
		}
		$text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
		$text = preg_replace('/<\/\s*(p|div|li|h[1-6]|tr)\s*>/i', "\n", $text);
		$text = strip_tags(strval($text));
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		$text = str_replace("\xc2\xa0", ' ', $text);
		$text = preg_replace("/[ \t]+\n/", "\n", $text);
		$text = preg_replace("/\n{3,}/", "\n\n", $text);
		return trim(strval($text));
	}

	protected static function getMemberLevelDefaultMap(int $aid): array{
		static $cache = [];
		$aid = intval($aid);
		if($aid <= 0){
			return [];
		}
		if(isset($cache[$aid])){
			return $cache[$aid];
		}
		$levelRows = Db::name('member_level')->where('aid',$aid)->field('id,isdefault')->select()->toArray();
		$defaultMap = [];
		foreach($levelRows as $levelRow){
			$defaultMap[(string)$levelRow['id']] = intval($levelRow['isdefault'] ?? 0) === 1 ? 1 : 0;
		}
		$cache[$aid] = $defaultMap;
		return $defaultMap;
	}
	protected static function getProductMemberPriceMeta(array $product, ?array $member = null): array{
		$meta = [
			'member_price_match' => 0,
			'member_price_current_match' => 0,
			'member_price_current_is_default' => 0,
			'member_price_value' => 0,
			'member_price_min' => 0,
			'member_price_level_count' => 0,
		];
		if(!isset($product['lvprice']) || intval($product['lvprice']) !== 1){
			return $meta;
		}
		$rawPrices = [];
		if(isset($product['lvprice_data'])){
			if(is_array($product['lvprice_data'])){
				$rawPrices = $product['lvprice_data'];
			}else{
				$rawPrices = json_decode((string)$product['lvprice_data'], true);
			}
		}
		if(!$rawPrices || !is_array($rawPrices)){
			return $meta;
		}
		$aid = isset($product['aid']) && is_numeric($product['aid']) ? intval($product['aid']) : (defined('aid') && is_numeric(aid) ? intval(aid) : 0);
		$levelDefaultMap = self::getMemberLevelDefaultMap($aid);
		$priceMap = [];
		$previewPriceMap = [];
		foreach($rawPrices as $levelId => $price){
			if(is_array($price) || $price === '' || $price === null || !is_numeric($price)){
				continue;
			}
			$levelKey = (string)$levelId;
			$priceMap[$levelKey] = round((float)$price, 2);
			if(empty($levelDefaultMap[$levelKey])){
				$previewPriceMap[$levelKey] = $priceMap[$levelKey];
			}
		}
		if(!$priceMap){
			return $meta;
		}
		$meta['member_price_level_count'] = count($previewPriceMap);
		$meta['member_price_min'] = $previewPriceMap ? min($previewPriceMap) : 0;
		if($member && isset($member['levelid'])){
			$levelKey = (string)$member['levelid'];
			if(array_key_exists($levelKey, $priceMap)){
				$meta['member_price_current_match'] = 1;
				$meta['member_price_value'] = $priceMap[$levelKey];
				if(!empty($levelDefaultMap[$levelKey])){
					$meta['member_price_current_is_default'] = 1;
				}else{
					$meta['member_price_match'] = 1;
				}
			}
		}
		return $meta;
	}
	protected static function appendProductMemberPriceMeta(array $product, ?array $member = null): array{
		$meta = self::getProductMemberPriceMeta($product, $member);
		foreach($meta as $key => $value){
			$product[$key] = $value;
		}
		return $product;
	}
	protected static function isShopMemberPriceCheckoutOnly(int $aid): bool{
		static $cache = [];
		$aid = intval($aid);
		if(!array_key_exists($aid, $cache)){
			try{
				$shopset = Db::name('shop_sysset')->where('aid', $aid)->find();
				$cache[$aid] = intval($shopset['member_price_checkout_only'] ?? 0) === 1;
			}catch(\Throwable $e){
				$cache[$aid] = false;
			}
		}
		return $cache[$aid];
	}
	protected static function hideShopMemberPriceData(array $product): array{
		unset($product['lvprice_data'], $product['member_price_value'], $product['member_price_min']);
		$product['member_price_match'] = 0;
		$product['member_price_current_match'] = 0;
		$product['member_price_current_is_default'] = 0;
		$product['member_price_level_count'] = 0;
		$product['member_price_hidden'] = 1;
		return $product;
	}
	// 取商品所属第一个分组名称，作为列表标题前缀标签
	protected static function resolveProductGroupTag($gid, $groupNameArr){
		if(empty($gid) || empty($groupNameArr)){
			return '';
		}
		foreach(explode(',', strval($gid)) as $gidItem){
			$gidItem = intval(trim($gidItem));
			if($gidItem > 0 && isset($groupNameArr[$gidItem])){
				return $groupNameArr[$gidItem];
			}
		}
		return '';
	}
	// 取商品服务名称列表（最多3个），作为列表标题下方服务标签
	protected static function resolveProductServiceTags($fwid, $fuwuNameArr){
		$tags = [];
		if(empty($fwid) || empty($fuwuNameArr)){
			return $tags;
		}
		foreach(explode(',', strval($fwid)) as $fwItem){
			$fwItem = intval(trim($fwItem));
			if($fwItem > 0 && isset($fuwuNameArr[$fwItem])){
				$tags[] = $fuwuNameArr[$fwItem];
			}
			if(count($tags) >= 3){
				break;
			}
		}
		return $tags;
	}
	protected static function tableExists(string $tableName): bool{
		try{
			$prefix = config('database.connections.mysql.prefix', '');
			$tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
			if($tableName === ''){
				return false;
			}
			$rows = Db::query("SHOW TABLES LIKE '{$prefix}{$tableName}'");
			return !empty($rows);
		}catch(\Throwable $e){
			return false;
		}
	}

	/**
	 * 预约服务运行时依赖检查。
	 *
	 * DIY 页面中的商家组件会把预约服务商品合并到商家商品列表，
	 * 因此不能只检查表是否存在，还要确认预约插件已安装并获得当前站点授权。
	 */
	protected static function isYuyuePluginReady(int $aid): bool{
		static $cache = [];
		$aid = intval($aid);
		if($aid <= 0){
			return false;
		}
		if(array_key_exists($aid, $cache)){
			return $cache[$aid];
		}

		if(!class_exists('\\app\\admin\\common\\PluginBusinessAuth')){
			$cache[$aid] = false;
			return false;
		}

		$cache[$aid] = PluginBusinessAuth::isAidPluginEnabled($aid, 'yuyue')
			&& self::tableExists('yuyue_product')
			&& self::tableExists('yuyue_category')
			&& self::tableExists('yuyue_worker');
		return $cache[$aid];
	}

	protected static function isDaojiaWorkerReady(int $aid): bool{
		if($aid <= 0 || !class_exists('\\app\\admin\\common\\PluginBusinessAuth')){
			return false;
		}
		return PluginBusinessAuth::isAidPluginEnabled($aid, 'daojia') && self::tableExists('daojia_worker');
	}

	protected static function isDaojiaMemberOrderReady(int $aid): bool
	{
		if ($aid <= 0 || !class_exists('\\app\\admin\\common\\PluginBusinessAuth')) {
			return false;
		}
		return PluginBusinessAuth::isAidPluginReady($aid, 'daojia', ['daojia_order']);
	}

	protected static function buildDaojiaMemberOrderCounts(int $aid, int $mid): array
	{
		$counts = [
			'ready' => 1,
			'wait_pay' => 0,
			'wait_service' => 0,
			'wait_comment' => 0,
		];
		if ($mid <= 0 || !self::tableExists('daojia_order')) {
			return $counts;
		}
		$base = function () use ($aid, $mid) {
			return Db::name('daojia_order')->where('aid', $aid)->where('mid', $mid);
		};
		$counts['wait_pay'] = (int)$base()->where('status', 0)->count();
		$counts['wait_service'] = (int)$base()->where('status', 1)->count();
		$commentQuery = $base()->where('status', 3);
		if (self::tableExists('daojia_comment')) {
			$commentOrderIds = Db::name('daojia_comment')->where('aid', $aid)->where('mid', $mid)->column('order_id');
			$commentOrderIds = array_values(array_unique(array_filter(array_map('intval', $commentOrderIds))));
			if ($commentOrderIds) {
				$commentQuery->whereNotIn('id', $commentOrderIds);
			}
		}
		$counts['wait_comment'] = (int)$commentQuery->count();
		return $counts;
	}

	protected static function isDiancanMemberOrderReady(int $aid): bool
	{
		if ($aid <= 0 || !class_exists('\\app\\admin\\common\\PluginBusinessAuth')) {
			return false;
		}
		if (!PluginBusinessAuth::isAidPluginReady($aid, 'restaurant')) {
			return false;
		}
		return self::tableExists('restaurant_shop_order') || self::tableExists('restaurant_takeaway_order');
	}

	protected static function countDiancanMemberOrders(int $aid, int $mid, array $statuses): int
	{
		$total = 0;
		foreach (['restaurant_shop_order', 'restaurant_takeaway_order'] as $table) {
			if (!self::tableExists($table)) {
				continue;
			}
			$total += (int)Db::name($table)
				->where('aid', $aid)
				->where('mid', $mid)
				->where('delete', 0)
				->where('status', 'in', $statuses)
				->count();
		}
		return $total;
	}

	protected static function buildDiancanMemberOrderCounts(int $aid, int $mid): array
	{
		$counts = [
			'ready' => 1,
			'wait_pay' => 0,
			'wait_service' => 0,
			'wait_comment' => 0,
		];
		if ($mid <= 0) {
			return $counts;
		}
		$counts['wait_pay'] = self::countDiancanMemberOrders($aid, $mid, [0]);
		$counts['wait_service'] = self::countDiancanMemberOrders($aid, $mid, [1, 12, 2]);
		$counts['wait_comment'] = self::countDiancanMemberOrders($aid, $mid, [3]);
		return $counts;
	}

	protected static function isPluginMemberOrderReady(int $aid, string $plugin, string $table): bool
	{
		if ($aid <= 0 || $plugin === '' || $table === '' || !class_exists('\\app\\admin\\common\\PluginBusinessAuth')) {
			return false;
		}
		return PluginBusinessAuth::isAidPluginReady($aid, $plugin, [$table]);
	}

	protected static function countPluginMemberOrders(string $table, int $aid, int $mid, callable $apply, bool $withDelete = true): int
	{
		if ($mid <= 0 || !self::tableExists($table)) {
			return 0;
		}
		$query = Db::name($table)->where('aid', $aid)->where('mid', $mid);
		if ($withDelete) {
			$query->where('delete', 0);
		}
		$apply($query);
		return (int)$query->count();
	}

	protected static function buildPluginMemberOrderCounts(int $aid, int $mid, string $temp): array
	{
		$empty = ['ready' => 0, 'wait_pay' => 0, 'wait_service' => 0, 'wait_comment' => 0];
		$specs = [
			'yuyue_order' => ['yuyue', 'yuyue_order', true],
			'hotel_order' => ['hotel', 'hotel_order', true],
			'venues_order' => ['venues', 'venues_order', false],
			'baoming_order' => ['activity', 'huodong_baoming_order', true],
			'xixie_order' => ['xixie', 'xixie_order', true],
			'theater_order' => ['theater', 'theater_order', false],
		];
		if (!isset($specs[$temp])) {
			return $empty;
		}
		[$plugin, $table, $withDelete] = $specs[$temp];
		if (!self::isPluginMemberOrderReady($aid, $plugin, $table)) {
			return $empty;
		}
		$counts = ['ready' => 1, 'wait_pay' => 0, 'wait_service' => 0, 'wait_comment' => 0];
		if ($mid <= 0) {
			return $counts;
		}
		if ($temp === 'yuyue_order') {
			$counts['wait_pay'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 0);
			}, $withDelete);
			$counts['wait_service'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 2);
			}, $withDelete);
			$counts['wait_comment'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 3)->where('isconmement', 0);
			}, $withDelete);
		} elseif ($temp === 'hotel_order') {
			$counts['wait_pay'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 0);
			}, $withDelete);
			$counts['wait_service'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 2);
			}, $withDelete);
			$counts['wait_comment'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('refund_status', '>', 0);
			}, $withDelete);
		} elseif ($temp === 'venues_order') {
			$counts['wait_pay'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 0);
			}, false);
			$counts['wait_service'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 1);
			}, false);
			$counts['wait_comment'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 2);
			}, false);
		} elseif ($temp === 'baoming_order') {
			$counts['wait_pay'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 0);
			}, $withDelete);
			$counts['wait_service'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 1);
			}, $withDelete);
			$counts['wait_comment'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 3);
			}, $withDelete);
		} elseif ($temp === 'xixie_order') {
			$counts['wait_pay'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 0);
			}, $withDelete);
			$counts['wait_service'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 3);
			}, $withDelete);
			$counts['wait_comment'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 4);
			}, $withDelete);
		} elseif ($temp === 'theater_order') {
			$counts['wait_pay'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 0);
			}, false);
			$counts['wait_service'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 1);
			}, false);
			$counts['wait_comment'] = self::countPluginMemberOrders($table, $aid, $mid, function ($query) {
				$query->where('status', 2);
			}, false);
		}
		return $counts;
	}

	/**
	 * 搭子卡片样式优先读取到家技师，补齐在线、评分、城市、标签和起步价。
	 */
	protected static function buildDaziWorkerList(int $aid, array $module, array $fallback): array{
		$params = isset($module['params']) && is_array($module['params']) ? $module['params'] : [];
		$limit = intval($params['workershownum'] ?? 6);
		if($limit <= 0){
			$limit = 6;
		}
		if(!self::isDaojiaWorkerReady($aid)){
			return self::decorateYuyueDaziWorkers($fallback);
		}

		$rows = [];
		$workerFrom = strval($params['workerfrom'] ?? '1');
		if($workerFrom === '0'){
			foreach(($module['data'] ?? []) as $worker){
				$workerId = intval($worker['workerid'] ?? ($worker['id'] ?? 0));
				if($workerId <= 0){
					continue;
				}
				$row = Db::name('daojia_worker')->where('aid', $aid)->where('id', $workerId)->where('status', 1)->find();
				if($row){
					$rows[] = $row;
				}
			}
		}else{
			$query = Db::name('daojia_worker')->where('aid', $aid)->where('status', 1)->where('accept_status', 1);
			if(isset($params['bid']) && $params['bid'] !== '' && $params['bid'] !== null){
				$query->where('bid', intval($params['bid']));
			}
			$sortby = strval($params['sortby'] ?? 'sort');
			if($sortby === 'createtimedesc'){
				$query->order('createtime desc,id desc');
			}elseif($sortby === 'createtime'){
				$query->order('createtime asc,id asc');
			}elseif($sortby === 'rand'){
				$query->orderRaw('rand()');
			}else{
				$query->order('online_status desc,score desc,order_count desc,id desc');
			}
			$rows = $query->limit($limit)->select()->toArray();
		}
		if(!$rows){
			return self::decorateYuyueDaziWorkers($fallback);
		}

		$priceMap = self::buildDaojiaWorkerPriceMap($aid, $rows);
		$list = [];
		foreach($rows as $row){
			$list[] = self::formatDaojiaDaziItem($row, $priceMap);
		}
		return $list;
	}

	protected static function buildDaojiaWorkerPriceMap(int $aid, array $rows): array{
		if(!self::tableExists('daojia_product') || !$rows){
			return [];
		}
		$bids = [];
		foreach($rows as $row){
			$bids[] = intval($row['bid'] ?? 0);
		}
		$bids = array_values(array_unique($bids));
		$products = Db::name('daojia_product')
			->where('aid', $aid)
			->where('bid', 'in', $bids)
			->where('status', 1)
			->field('bid,sell_price,is_need_worker_gender,worker_gender')
			->select()
			->toArray();
		$map = [];
		foreach($products as $product){
			$bid = intval($product['bid'] ?? 0);
			$price = round(floatval($product['sell_price'] ?? 0), 2);
			if($price <= 0){
				continue;
			}
			$gender = intval($product['is_need_worker_gender'] ?? 0) === 1 ? intval($product['worker_gender'] ?? 0) : 0;
			$key = $bid . ':' . $gender;
			if(!isset($map[$key]) || $price < $map[$key]){
				$map[$key] = $price;
			}
		}
		return $map;
	}

	protected static function formatDaojiaDaziItem(array $row, array $priceMap): array{
		$bid = intval($row['bid'] ?? 0);
		$sex = intval($row['sex'] ?? 0);
		$price = 0;
		$openKey = $bid . ':0';
		$sexKey = $bid . ':' . $sex;
		if(isset($priceMap[$openKey])){
			$price = $priceMap[$openKey];
		}
		if($sex > 0 && isset($priceMap[$sexKey]) && ($price <= 0 || $priceMap[$sexKey] < $price)){
			$price = $priceMap[$sexKey];
		}
		$skills = array_values(array_filter(array_map('trim', explode(',', strval($row['skill_tags'] ?? '')))));
		$city = self::resolveDaojiaWorkerCity($row);
		$workerId = intval($row['id'] ?? 0);
		$online = intval($row['online_status'] ?? 0) === 1 ? 1 : 0;
		return [
			'id' => 'D' . $workerId,
			'workerid' => $workerId,
			'bid' => $bid,
			'realname' => trim(strval($row['realname'] ?? '')),
			'headimg' => strval($row['headimg'] ?? ''),
			'tel' => strval($row['tel'] ?? ''),
			'age' => intval($row['age'] ?? 0),
			'sex' => $sex,
			'sex_text' => $sex === 1 ? '男' : ($sex === 2 ? '女' : ''),
			'city_text' => $city,
			'online_status' => $online,
			'online_text' => $online === 1 ? '在线' : '离线',
			'score' => number_format(floatval($row['score'] ?? 5), 1, '.', ''),
			'order_count' => intval($row['order_count'] ?? 0),
			'skills_array' => $skills,
			'desc' => trim(strval($row['intro'] ?? '')),
			'price' => $price,
			'detail_url' => '/daojia/workerdetail?id=' . $workerId . '&bid=' . $bid,
		];
	}

	protected static function resolveDaojiaWorkerCity(array $row): string{
		if(class_exists('\\app\\plugin\\daojia\\service\\AutoDispatchService')){
			try{
				$meta = \app\plugin\daojia\service\AutoDispatchService::buildWorkerServiceAreaMeta($row);
				$first = $meta['service_area_arr'][0] ?? [];
				$city = trim(strval($first['city_name'] ?? ''));
				if($city !== ''){
					return $city;
				}
			}catch(\Throwable $e){
			}
		}
		$decoded = json_decode(strval($row['service_area_json'] ?? ''), true);
		if(is_array($decoded)){
			$first = isset($decoded[0]) && is_array($decoded[0]) ? $decoded[0] : $decoded;
			$city = trim(strval($first['city_name'] ?? ''));
			if($city !== ''){
				return $city;
			}
		}
		return '';
	}

	protected static function decorateYuyueDaziWorkers(array $rows): array{
		$list = [];
		foreach($rows as $row){
			if(!is_array($row)){
				continue;
			}
			$sex = intval($row['sex'] ?? 0);
			$workerId = intval($row['workerid'] ?? ($row['id'] ?? 0));
			$skills = $row['skills_array'] ?? [];
			if(!is_array($skills)){
				$skills = array_values(array_filter(array_map('trim', explode(',', strval($row['worker_skills'] ?? '')))));
			}
			$city = trim(strval($row['citys'] ?? ''));
			if(strpos($city, ',') !== false){
				$cityParts = explode(',', $city);
				$city = trim(strval($cityParts[0] ?? ''));
			}
			$list[] = array_merge($row, [
				'workerid' => $workerId,
				'sex_text' => $sex === 1 ? '男' : ($sex === 2 ? '女' : ''),
				'city_text' => $city,
				'online_status' => intval($row['online_status'] ?? 0),
				'online_text' => intval($row['online_status'] ?? 0) === 1 ? '在线' : '',
				'score' => number_format(floatval($row['comment_score'] ?? ($row['score'] ?? 5)), 1, '.', ''),
				'order_count' => intval($row['totalnum'] ?? ($row['order_count'] ?? 0)),
				'skills_array' => $skills,
				'price' => round(floatval($row['price'] ?? 0), 2),
				'detail_url' => '/yuyue/peodetail?id=' . $workerId,
			]);
		}
		return $list;
	}

	protected static function getBusinessSalesMap(int $aid, array $businessIds): array{
		$businessIds = array_values(array_unique(array_filter(array_map('intval', $businessIds))));
		$salesMap = array_fill_keys($businessIds, 0.0);
		if(empty($businessIds)) return $salesMap;

		$shopRows = Db::name('shop_order')
			->where('aid', $aid)
			->where('bid', 'in', $businessIds)
			->where('status', 'in', '1,2,3')
			->field('bid,SUM(totalprice) as total')
			->group('bid')
			->select()
			->toArray();
		foreach($shopRows as $row){
			$salesMap[intval($row['bid'])] = floatval($row['total'] ?? 0);
		}

		$maidanRows = Db::name('maidan_order')
			->where('aid', $aid)
			->where('bid', 'in', $businessIds)
			->where('status', 1)
			->field('bid,SUM(money) as total')
			->group('bid')
			->select()
			->toArray();
		foreach($maidanRows as $row){
			$bid = intval($row['bid']);
			$salesMap[$bid] = ($salesMap[$bid] ?? 0) + floatval($row['total'] ?? 0);
		}
		return $salesMap;
	}

	protected static function normalizeFollowBuyImageUrl($url): string{
		$url = trim((string)$url);
		if($url === ''){
			return '';
		}
		if(preg_match('/^https?:\/\//i', $url)){
			return $url;
		}
		if(function_exists('toPath')){
			return toPath($url);
		}
		if($url[0] !== '/'){
			$url = '/' . $url;
		}
		return (defined('PRE_URL') ? rtrim(PRE_URL, '/') : '') . $url;
	}

	protected static function normalizeFollowBuyUserList($avatars): array{
		if(!is_array($avatars)){
			return [];
		}
		$list = [];
		foreach($avatars as $avatar){
			if(is_string($avatar)){
				$headimg = $avatar;
				$nickname = '';
			}else if(is_array($avatar)){
				$headimg = $avatar['headimg'] ?? $avatar['url'] ?? $avatar['imgurl'] ?? $avatar['image'] ?? '';
				$nickname = $avatar['nickname'] ?? $avatar['name'] ?? '';
			}else{
				continue;
			}
			$headimg = self::normalizeFollowBuyImageUrl($headimg);
			if($headimg === ''){
				continue;
			}
			$list[] = [
				'headimg' => $headimg,
				'nickname' => trim((string)$nickname),
			];
		}
		return $list;
	}

	protected static function getFollowBuyVirtualUsers(int $aid, int $limit = 50): array{
		if($aid <= 0 || !self::tableExists('admin_virtual_user')){
			return [];
		}
		$limit = max(1, min(200, $limit));
		try{
			$list = Db::name('admin_virtual_user')
				->where('aid', $aid)
				->where('status', 1)
				->where('headimg', '<>', '')
				->field('id,nickname,headimg')
				->order('sort desc,id desc')
				->limit($limit)
				->select()
				->toArray();
		}catch(\Throwable $e){
			return [];
		}
		return self::normalizeFollowBuyUserList($list);
	}

	protected static function normalizeFollowBuyParams($params): array{
		$params = is_array($params) ? $params : [];
		$enabled = strval($params['show_follow_buy'] ?? '0') === '1';
		$avatarCount = intval($params['follow_buy_avatar_count'] ?? 5);
		$avatarCount = max(1, min(9, $avatarCount));
		$min = intval($params['follow_buy_count_min'] ?? 3000);
		$max = intval($params['follow_buy_count_max'] ?? 5000);
		if($min < 0) $min = 0;
		if($max < $min) $max = $min;
		$template = trim((string)($params['follow_buy_text'] ?? '{count}人正在跟买'));
		if($template === ''){
			$template = '{count}人正在跟买';
		}
		return [
			'enabled' => $enabled,
			'avatar_source' => strval($params['follow_buy_avatar_source'] ?? 'pool') === 'manual' ? 'manual' : 'pool',
			'avatar_count' => $avatarCount,
			'count_mode' => strval($params['follow_buy_count_mode'] ?? 'range') === 'fixed' ? 'fixed' : 'range',
			'count_fixed' => max(0, intval($params['follow_buy_count_fixed'] ?? 3527)),
			'count_min' => $min,
			'count_max' => $max,
			'text' => $template,
			'random_mode' => strval($params['follow_buy_random_mode'] ?? 'daily') === 'refresh' ? 'refresh' : 'daily',
			'manual_avatars' => self::normalizeFollowBuyUserList($params['follow_buy_avatars'] ?? []),
		];
	}

	protected static function resolveFollowBuyCount(array $config, string $seed): int{
		if($config['count_mode'] === 'fixed'){
			return intval($config['count_fixed']);
		}
		$span = max(1, intval($config['count_max']) - intval($config['count_min']) + 1);
		$hash = sprintf('%u', crc32($seed . ':count'));
		return intval($config['count_min']) + (intval($hash) % $span);
	}

	protected static function pickFollowBuyAvatars(array $avatars, int $count, string $seed): array{
		if(!$avatars){
			return [];
		}
		$total = count($avatars);
		$count = min($count, $total);
		$start = intval(sprintf('%u', crc32($seed . ':avatar'))) % $total;
		$result = [];
		for($i = 0; $i < $count; $i++){
			$result[] = $avatars[($start + $i) % $total];
		}
		return $result;
	}

	protected static function appendFollowBuyAtmosphere(array $items, array $params, int $aid, $componentId = ''): array{
		$config = self::normalizeFollowBuyParams($params);
		if(!$config['enabled'] || !$items){
			return $items;
		}
		$avatars = $config['avatar_source'] === 'manual'
			? $config['manual_avatars']
			: self::getFollowBuyVirtualUsers($aid, max(30, $config['avatar_count'] * 5));
		if(!$avatars){
			return $items;
		}
		// refresh模式用微秒级唯一串做种子，避免同一秒内刷新数字不变
		$daySeed = $config['random_mode'] === 'daily' ? date('Ymd') : uniqid('', true);
		foreach($items as $index => &$item){
			$productId = $item['proid'] ?? $item['id'] ?? $index;
			$seed = $aid . ':' . $componentId . ':' . $productId . ':' . $daySeed;
			$count = self::resolveFollowBuyCount($config, $seed);
			$item['_follow_buy_avatars'] = self::pickFollowBuyAvatars($avatars, $config['avatar_count'], $seed);
			$item['_follow_buy_count'] = $count;
			$item['_follow_buy_text'] = str_replace('{count}', (string)$count, $config['text']);
		}
		unset($item);
		return $items;
	}

	protected static function isZichanfenqiUserinfoReady(): bool{
		return \app\admin\common\PluginBusinessAuth::isAidPluginEnabled(intval(aid), 'zichanfenqi')
			&& self::tableExists('zichanfenqi_grant_log')
			&& self::tableExists('zichanfenqi_plan');
	}

	protected static function isZhuibaoagentUserinfoReady(): bool{
		return \app\admin\common\PluginBusinessAuth::isAidPluginEnabled(intval(aid), 'zhuibaoagent')
			&& self::tableExists('zhuibao_credit_config')
			&& self::tableExists('zhuibao_member_credit')
			&& class_exists('\\app\\plugin\\zhuibaoagent\\common\\ZhuibaoCreditService');
	}

	protected static function getZhuibaoagentUserinfoCredit(int $mid): array{
		$defaults = [
			'zhuibaoagent_ready' => 1,
			'zhuibao_credit_total' => 0,
			'zhuibao_credit_used' => 0,
			'zhuibao_credit_balance' => 0,
			'zhuibao_credit_frozen' => 0,
			'zhuibao_member_status_text' => '非会员',
		];
		if($mid <= 0){
			return $defaults;
		}
		try{
			$summary = \app\plugin\zhuibaoagent\common\ZhuibaoCreditService::getMemberCreditSummary(intval(aid), intval(bid), $mid);
			return [
				'zhuibaoagent_ready' => 1,
				'zhuibao_credit_total' => max(0, intval($summary['credit_total'] ?? 0)),
				'zhuibao_credit_used' => max(0, intval($summary['credit_used'] ?? 0)),
				'zhuibao_credit_balance' => max(0, intval($summary['credit_balance'] ?? 0)),
				'zhuibao_credit_frozen' => max(0, intval($summary['credit_frozen'] ?? 0)),
				'zhuibao_member_status_text' => trim(strval($summary['member_status_text'] ?? '')) ?: '非会员',
			];
		}catch(\Throwable $e){
			return $defaults;
		}
	}

	protected static function getZhuibaoagentCreditLabel(): string{
		if(!self::isZhuibaoagentUserinfoReady()){
			return '算力';
		}
		try{
			$label = \app\plugin\zhuibaoagent\common\ZhuibaoCreditService::getCreditUnitLabel(intval(aid));
			return trim($label) !== '' ? $label : '算力';
		}catch(\Throwable $e){
			return '算力';
		}
	}

	protected static function normalizeUserinfoAssetAmount($value): string{
		$value = trim((string)$value);
		if($value === ''){
			return '0.00000';
		}
		if(!preg_match('/^-?\d+(\.\d+)?$/', $value)){
			return '0.00000';
		}
		if(strpos($value, '.') === false){
			return $value . '.00000';
		}
		$parts = explode('.', $value, 2);
		$decimal = substr(str_pad($parts[1], 5, '0'), 0, 5);
		return $parts[0] . '.' . $decimal;
	}

	protected static function trimUserinfoAssetAmount($value): string{
		$normalized = self::normalizeUserinfoAssetAmount($value);
		if(function_exists('bccomp') && bccomp($normalized, '0', 5) === 0){
			return '0';
		}
		$trimmed = rtrim(rtrim($normalized, '0'), '.');
		return $trimmed === '' ? '0' : $trimmed;
	}

	/**
	 * 资产分期 userinfo 展示值：根据组件配置的取值方式/除以系数/保留小数位算好最终展示字符串。
	 * 前端只展示这个数字，不再写死除数；原始三项金额保持真实值不变。
	 */
	protected static function buildZichanfenqiDisplayValue($params, array $userinfo): string{
		$params = is_array($params) ? $params : [];
		$mode = isset($params['zichanfenqi_mode']) ? (string)$params['zichanfenqi_mode'] : 'total_target_amount';

		$divisor = isset($params['zichanfenqi_divisor']) ? floatval($params['zichanfenqi_divisor']) : 1;
		if(!is_finite($divisor) || $divisor <= 0){
			$divisor = 1;
		}

		$decimals = isset($params['zichanfenqi_decimals']) ? intval($params['zichanfenqi_decimals']) : 2;
		if($decimals < 0){
			$decimals = 0;
		}
		if($decimals > 6){
			$decimals = 6;
		}

		if($mode === 'granted_amount'){
			$base = $userinfo['zichanfenqi_granted_amount'] ?? 0;
		}elseif($mode === 'pending_amount'){
			$base = $userinfo['zichanfenqi_pending_amount'] ?? 0;
		}else{
			$base = $userinfo['zichanfenqi_total_target_amount'] ?? 0;
		}

		$display = floatval($base) / $divisor;
		return number_format($display, $decimals, '.', '');
	}
	protected static function getKechengLevelDiscountMap(int $aid): array{
		static $cache = [];
		$aid = intval($aid);
		if($aid <= 0){
			return [];
		}
		if(isset($cache[$aid])){
			return $cache[$aid];
		}
		$levels = Db::name('member_level')->where('aid',$aid)->field('id,kecheng_discount')->select()->toArray();
		$discountMap = [];
		foreach($levels as $level){
			$discount = isset($level['kecheng_discount']) ? round((float)$level['kecheng_discount'], 2) : 0;
			if($discount > 0 && $discount < 10){
				$discountMap[(string)$level['id']] = $discount;
			}
		}
		$cache[$aid] = $discountMap;
		return $discountMap;
	}
	protected static function getKechengMemberPriceMeta(array $product, ?array $member = null, int $aid = 0): array{
		$meta = [
			'member_price_match' => 0,
			'member_price_current_match' => 0,
			'member_price_current_is_default' => 0,
			'member_price_value' => 0,
			'member_price_min' => 0,
			'member_price_level_count' => 0,
		];
		$basePrice = isset($product['price']) && is_numeric($product['price']) ? round((float)$product['price'], 2) : 0;
		$levelDefaultMap = self::getMemberLevelDefaultMap($aid);
		if(isset($product['lvprice']) && intval($product['lvprice']) === 1){
			$rawPrices = [];
			if(isset($product['lvprice_data'])){
				if(is_array($product['lvprice_data'])){
					$rawPrices = $product['lvprice_data'];
				}else{
					$rawPrices = json_decode((string)$product['lvprice_data'], true);
				}
			}
			if(!$rawPrices || !is_array($rawPrices)){
				return $meta;
			}
			$priceMap = [];
			$previewPriceMap = [];
			foreach($rawPrices as $levelId => $priceInfo){
				$moneyPrice = null;
				if(is_array($priceInfo)){
					if(array_key_exists('money_price', $priceInfo)){
						$moneyPrice = $priceInfo['money_price'];
					}elseif(array_key_exists('price', $priceInfo)){
						$moneyPrice = $priceInfo['price'];
					}
				}else{
					$moneyPrice = $priceInfo;
				}
				if($moneyPrice === '' || $moneyPrice === null || !is_numeric($moneyPrice)){
					continue;
				}
				$levelKey = (string)$levelId;
				$priceMap[$levelKey] = round((float)$moneyPrice, 2);
				if(empty($levelDefaultMap[$levelKey])){
					$previewPriceMap[$levelKey] = $priceMap[$levelKey];
				}
			}
			if(!$priceMap){
				return $meta;
			}
			$meta['member_price_level_count'] = count($previewPriceMap);
			$meta['member_price_min'] = $previewPriceMap ? min($previewPriceMap) : 0;
			if($member && isset($member['levelid'])){
				$levelKey = (string)$member['levelid'];
				if(array_key_exists($levelKey, $priceMap)){
					$meta['member_price_current_match'] = 1;
					$meta['member_price_value'] = $priceMap[$levelKey];
					if(!empty($levelDefaultMap[$levelKey])){
						$meta['member_price_current_is_default'] = 1;
					}else{
						$meta['member_price_match'] = 1;
					}
				}
			}
			return $meta;
		}
		if($basePrice <= 0){
			return $meta;
		}
		$discountMap = self::getKechengLevelDiscountMap($aid);
		if(!$discountMap){
			return $meta;
		}
		$priceMap = [];
		foreach($discountMap as $levelId => $discount){
			$levelKey = (string)$levelId;
			$priceMap[$levelKey] = round($basePrice * $discount * 0.1, 2);
		}
		if(!$priceMap){
			return $meta;
		}
		$previewPriceMap = [];
		foreach($priceMap as $levelKey => $price){
			if(empty($levelDefaultMap[$levelKey])){
				$previewPriceMap[$levelKey] = $price;
			}
		}
		$meta['member_price_level_count'] = count($previewPriceMap);
		$meta['member_price_min'] = $previewPriceMap ? min($previewPriceMap) : 0;
		if($member && isset($member['levelid'])){
			$levelKey = (string)$member['levelid'];
			if(array_key_exists($levelKey, $priceMap)){
				$meta['member_price_current_match'] = 1;
				$meta['member_price_value'] = $priceMap[$levelKey];
				if(!empty($levelDefaultMap[$levelKey])){
					$meta['member_price_current_is_default'] = 1;
				}else{
					$meta['member_price_match'] = 1;
				}
			}
		}
		return $meta;
	}
	protected static function appendKechengMemberPriceMeta(array $product, ?array $member = null, int $aid = 0): array{
		$meta = self::getKechengMemberPriceMeta($product, $member, $aid);
		foreach($meta as $key => $value){
			$product[$key] = $value;
		}
		return $product;
	}
	//网站名称
	public static function webname(){
		$webinfo = Db::name('sysset')->where(['name'=>'webinfo'])->value('value');
		$webinfo = json_decode($webinfo,true);
		$webname = $webinfo['webname'];
		return $webname;
	}
	//公众号或小程序 平台信息
	public static function appinfo($aid,$platform){
		if(!$platform) $platform = 'mp';
		$appinfo = Db::name('admin_setapp_'.$platform)->where(['aid'=>$aid])->find();
		return $appinfo;
	}
	//操作日志记录
	public static function plog($remark,$aid=0){
		if($aid){
			Db::name('plog')->insert(['aid'=>$aid,'bid'=>0,'uid'=>1,'remark'=>$remark,'createtime'=>time()]);
		}else{
			if($remark == '系统升级'){
				Db::name('plog')->insert(['aid'=>1,'bid'=>0,'uid'=>1,'remark'=>$remark,'createtime'=>time()]);
			}else{
				$logAid = defined('aid') && is_numeric(aid) ? intval(aid) : 0;
				$logBid = defined('bid') && is_numeric(bid) ? intval(bid) : 0;
				$logUid = defined('uid') && is_numeric(uid) ? intval(uid) : 0;
				Db::name('plog')->insert(['aid'=>$logAid,'bid'=>$logBid,'uid'=>$logUid,'remark'=>$remark,'createtime'=>time()]);
			}
		}
	}

	public static function initaccount($aid){
		$admin_set = Db::name('admin_set')->where('aid',$aid)->find();
		if(!$admin_set){
			$default_textset = self::getDefaultTextsetConfig();
			$textset_json = json_encode($default_textset, JSON_UNESCAPED_UNICODE);
			if($textset_json === false){
				$textset_json = json_encode(self::getLegacyTextsetConfig(), JSON_UNESCAPED_UNICODE);
			}
			$textset_max_length = self::getColumnMaxLength('admin_set', 'textset');
			if($textset_max_length > 0 && strlen($textset_json) > $textset_max_length){
				$textset_json = json_encode(self::getLegacyTextsetConfig(), JSON_UNESCAPED_UNICODE);
			}
			
		// 2025-10-31 修复：兼容新字段（jifenchi_*等）未提供默认值导致MySQL 5.7+插入失败
		Db::name('admin_set')->insert(self::buildSafeInsertData('admin_set', [
			'aid'=>$aid,
			'name'=>'商城系统',
			'maidian'=>'',
			'logo'=>PRE_URL.'/static/imgsrc/logo.jpg',
			'textset'=>$textset_json
		]));
			$sysset = Db::name('admin_set')->where('aid',$aid)->find();

			Db::name('mendian')->insert(self::buildSafeInsertData('mendian', ['aid'=>$aid,'name'=>'总店','address'=>'北京天安门广场','pic'=>PRE_URL.'/static/imgsrc/picture-1.jpg','longitude'=>'116.39709949493408','latitude'=>'39.90407402228','createtime'=>time()]));


		Db::name('admin_setapp_mp')->insert(self::buildSafeInsertData('admin_setapp_mp', ['aid'=>$aid]));
		Db::name('admin_setapp_wx')->insert(self::buildSafeInsertData('admin_setapp_wx', ['aid'=>$aid]));
		Db::name('admin_setapp_alipay')->insert(self::buildSafeInsertData('admin_setapp_alipay', ['aid'=>$aid]));
		Db::name('admin_setapp_baidu')->insert(self::buildSafeInsertData('admin_setapp_baidu', ['aid'=>$aid]));
		Db::name('admin_setapp_toutiao')->insert(self::buildSafeInsertData('admin_setapp_toutiao', ['aid'=>$aid]));
		Db::name('admin_setapp_qq')->insert(self::buildSafeInsertData('admin_setapp_qq', ['aid'=>$aid]));
		Db::name('admin_setapp_h5')->insert(self::buildSafeInsertData('admin_setapp_h5', ['aid'=>$aid]));
		Db::name('admin_setapp_app')->insert(self::buildSafeInsertData('admin_setapp_app', ['aid'=>$aid]));

		Db::name('freight')->insert(self::buildSafeInsertData('freight', [
			'aid'=>$aid,
			'name'=>'普通快递',
			'pstype'=>0,
			'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
			'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
			'status'=>1,
		]));
		Db::name('freight')->insert(self::buildSafeInsertData('freight', [
			'aid'=>$aid,
			'name'=>'到店自提',
			'pstype'=>1,
			'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]',
			'pstimedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
			'status'=>1,
		]));

		Db::name('member_level')->insert(self::buildSafeInsertData('member_level', [
			'aid'=>$aid,
			'sort'=>1,
			'isdefault'=>1,
			'name'=>'普通会员',
			'icon'=>PRE_URL.'/static/imgsrc/level_1.png',
			'explain'=>'<p>1、享受消费送积分，积分可以抵扣金额、兑换礼品、参与抽奖活动；</p><p>2、购买商品享受商城优惠价格；</p><p>3、免费领取优惠券、购买商品直接抵扣商品金额；</p><p>4、更多优惠活动请随时关注平台更新信息。</p>'
		]));
		$level2id = Db::name('member_level')->insertGetId(self::buildSafeInsertData('member_level', [
			'aid'=>$aid,
			'sort'=>2,
			'isdefault'=>0,
			'name'=>'分销商',
			'icon'=>PRE_URL.'/static/imgsrc/level_2.png',
			'can_apply'=>1,
			'apply_check'=>1,
			'can_agent'=>3,
			'commission1'=>3,
			'commission2'=>2,
			'commission3'=>1,
			'explain'=>'<p>1、享受消费送积分，积分可以抵扣金额、兑换礼品、参与抽奖活动；</p><p>2、购买商品享受商城优惠价格；</p><p>3、免费领取优惠券、购买商品直接抵扣商品金额；</p><p>4、分享商品给好友购买，可获得佣金奖励，佣金可以提现，也可以转换成余额在平台进行消费。</p><p>5、更多优惠活动请随时关注平台更新信息。</p>'
		]));

			Db::name('admin_set_sms')->insert(self::buildSafeInsertData('admin_set_sms', ['aid'=>$aid]));

		Db::name('shop_group')->insert(self::buildSafeInsertData('shop_group', ['aid'=>$aid,'name'=>'最新']));
		Db::name('shop_group')->insert(self::buildSafeInsertData('shop_group', ['aid'=>$aid,'name'=>'热卖']));
		Db::name('shop_group')->insert(self::buildSafeInsertData('shop_group', ['aid'=>$aid,'name'=>'推荐']));
		Db::name('shop_group')->insert(self::buildSafeInsertData('shop_group', ['aid'=>$aid,'name'=>'促销']));

		$shopcid = Db::name('shop_category')->insertGetId(self::buildSafeInsertData('shop_category', ['aid'=>$aid,'name'=>'分类一','pic'=>PRE_URL.'/static/imgsrc/picture-1.jpg']));
		$shopcid = Db::name('shop_category')->insertGetId(self::buildSafeInsertData('shop_category', ['aid'=>$aid,'name'=>'分类二','pic'=>PRE_URL.'/static/imgsrc/picture-2.jpg']));
		$shopcid = Db::name('shop_category')->insertGetId(self::buildSafeInsertData('shop_category', ['aid'=>$aid,'name'=>'分类三','pic'=>PRE_URL.'/static/imgsrc/picture-3.jpg']));

		Db::name('shop_sysset')->insert(self::buildSafeInsertData('shop_sysset', ['aid'=>$aid]));
		$optionalSyssetDefaults = [
			'seckill_sysset'=>['aid'=>$aid],
			'collage_sysset'=>['aid'=>$aid,'pics'=>PRE_URL.'/static/imgsrc/pintuan_banner1.png'],
			'kanjia_sysset'=>['aid'=>$aid,'pic'=>PRE_URL.'/static/imgsrc/kanjia_banner.png'],
			'scoreshop_sysset'=>['aid'=>$aid],
			'business_sysset'=>['aid'=>$aid],
		];
		foreach($optionalSyssetDefaults as $table => $defaultData){
			if(self::tableExists($table)){
				Db::name($table)->insert(self::buildSafeInsertData($table, $defaultData));
			}
		}

		if(self::tableExists('signset')){
			Db::name('signset')->insert(self::buildSafeInsertData('signset', [
				'aid'=>$aid,
				'score'=>1,
				'lxqdset'=>'[{"days":"3","score":"2"},{"days":"7","score":"3"},{"days":"15","score":"5"}]',
				'lxzsset'=>'[{"days":"3","score":"10"},{"days":"7","score":"20"},{"days":"10","score":"30"},{"days":"15","score":"40"}]',
				'guize'=>'<p>每天签到即可获得一个积分；</p><p>连续签到3天以上，每天签到获得2积分；</p><p>连续签到7天以上，每天签到获得3积分；</p><p>连续签到15天以上，每天签到获得5积分；</p><p>连续签到3天，额外赠送10积分；</p><p>连续签到7天，额外赠送20积分；</p><p>连续签到10天，额外赠送30积分；</p><p>连续签到15天，额外赠送40积分。</p>'
			]));
		}

		$insertdata = [];
		$insertdata['aid'] = $aid;
		$insertdata['name'] = $sysset['name'] ? $sysset['name'] : '主页';
		$insertdata['ishome'] = 1;
		$insertdata['pageinfo'] = '[{"id":"M0000000000001","temp":"topbar","params":{"title":"'.$insertdata['name'].'","bgcolor":"#F6F6F6","quanxian":{"all":true},"fufei":"0","showgg":0,"guanggao":"'.PRE_URL.'/static/imgsrc/picture-1.jpg","hrefurl":"","ggrenqun":{"0":true},"cishu":"0"}}]';
		$insertdata['content'] = '[{"id":"M161781932574672351","temp":"search","params":{"placeholder":"输入关键字搜索您感兴趣的商品","color":"#666666","bgcolor":"#f5f5f5","borderradius":5,"bordercolor":"#FFFFFF","hrefurl":"/shopPackage/shop/search","hrefname":"基础功能>商品搜索","margin_x":"0","margin_y":"0","padding_x":"6","padding_y":"6","quanxian":{"all":true},"platform":{"all":true}},"data":"","other":"","content":""}, {"id":"M1617819327569344084","temp":"banner","params":{"shape":"","align":"center","bgcolor":"#ffffff","margin_x":"0","margin_y":"0","padding_x":"0","padding_y":"0","height":"200","indicatordots":"1","indicatorcolor":"#edeef0","indicatoractivecolor":"#3db51e","interval":5,"previous_margin":0,"next_margin":0,"quanxian":{"all":true},"platform":{"all":true}},"data":[{"id":"B0000000000001","imgurl":"'.PRE_URL.'/static/imgsrc/banner-1.jpg","hrefurl":""}, {"id":"B0000000000002","imgurl":"'.PRE_URL.'/static/imgsrc/banner-2.jpg","hrefurl":""}],"other":"","content":""}, {"id":"M1617819329073434298","temp":"notice","params":{"showimg":0,"img":"'.PRE_URL.'/static/imgsrc/hotdot3.png","showicon":1,"icon":"'.PRE_URL.'/static/imgsrc/notice2.png","color":"#666666","bgcolor":"#ffffff","scroll":3,"fontsize":"14","padding_x":"5","padding_y":"7","margin_x":"0","margin_y":"0","borderradius":0,"quanxian":{"all":true},"platform":{"all":true}},"data":[{"id":"N001","title":"这里是第一条自定义公告的标题","hrefurl":""}, {"id":"N002","title":"这里是第二条自定义公告的标题","hrefurl":""}],"other":"","content":""}, {"id":"M161781933705712200","temp":"product","params":{"style":"2","bgcolor":"#ffffff","showname":"1","showsellpoint":"0","showcart":"1","cartimg":"'.PRE_URL.'/static/imgsrc/cart.svg","showprice":"1","showsales":"1","saleimg":"","productfrom":"1","bid":"0","sortby":"sort","proshownum":6,"margin_x":"0","margin_y":"0","padding_x":"8","padding_y":"8","group":{"all":true},"quanxian":{"all":true},"platform":{"all":true}},"data":[],"other":"","content":""}]';
		$insertdata['createtime'] = time();
		Db::name('designerpage')->insert(self::buildSafeInsertData('designerpage', $insertdata));


		$insertdata = [];
		$insertdata['aid'] = $aid;
		$insertdata['name'] = '会员中心';
		$insertdata['ishome'] = 2;
		$insertdata['pageinfo'] = '[{"id":"M0000000000002","temp":"topbar","params":{"title":"会员中心","bgcolor":"#F6F6F6"}}]';
		$insertdata['content'] = '[{"id":"M1617821038824192432","temp":"userinfo","params":{"moneyshow":"1","scoreshow":"1","couponshow":"1","cardshow":"0","levelshow":"1","ordershow":"1","commissionshow":"1","bgimg":"'.PRE_URL.'/static/imgsrc/userinfobg.png","style":"2","margin_x":"0","margin_y":0,"padding_x":10,"padding_y":10,"quanxian":{"all":true},"platform":{"all":true}},"data":{},"other":"","content":""},{"id":"M161782071160920389","temp":"menu","params":{"num":"4","radius":"0","fontsize":"12","fontheight":"20","pernum":"10","bgcolor":"#ffffff","margin_x":"10","margin_y":0,"padding_x":"5","padding_y":"5","iconsize":"30","showicon":"1","showline":"0","showtitle":"1","title":"我的推广","titlesize":"14","titlecolor":"#333333","boxradius":"8","quanxian":{"'.$level2id.'":true,"all":false},"platform":{"all":true}},"data":[{"id":"F0000000000001","imgurl":"'.PRE_URL.'/static/imgsrc/ico-myteam.png","text":"我的团队","hrefurl":"/activity/commission/myteam","color":"#666666","hrefname":"基础功能>我的团队"},{"id":"F0000000000002","imgurl":"'.PRE_URL.'/static/imgsrc/ico-downorder.png","text":"分销订单","hrefurl":"/activity/commission/downorder","color":"#666666","hrefname":"基础功能>分销订单"},{"id":"F0000000000003","imgurl":"'.PRE_URL.'/static/imgsrc/ico-poster.png","text":"分享海报","hrefurl":"/activity/commission/poster","color":"#666666","hrefname":"基础功能>分享海报"},{"id":"F0000000000004","imgurl":"'.PRE_URL.'/static/imgsrc/ico-commission.png","text":"我的佣金","hrefurl":"/activity/commission/index","color":"#666666","hrefname":"基础功能>我的佣金"}],"other":"","content":""},{"id":"M1617821690792736493","temp":"blank","params":{"height":"10","bgcolor":"#f5f5f5","margin_x":"0","margin_y":"0","quanxian":{"'.$level2id.'":true,"all":false},"platform":{"all":true}},"data":"","other":"","content":""},{"id":"M1596398978642125977","temp":"menu","params":{"num":"4","radius":"0","fontsize":"12","fontheight":20,"pernum":"12","bgcolor":"#ffffff","margin_x":10,"margin_y":0,"padding_x":5,"padding_y":5,"iconsize":30,"showicon":"1","showline":"0","showtitle":"1","title":"常用工具","titlecolor":"#333333","boxradius":8,"titlesize":14,"platform":{"all":true},"quanxian":{"all":true}},"data":[{"id":"F0000000000001","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-cart.png","text":"我的购物车","hrefurl":"/shopPackage/shop/cart","color":"#666666","hrefname":"基础功能>购物车"},{"id":"F0000000000002","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-favrite.png","text":"我的收藏","hrefurl":"/shopPackage/my/favorite","color":"#666666","hrefname":"基础功能>我的收藏"},{"id":"F0000000000003","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-zuji.png","text":"我的足迹","hrefurl":"/shopPackage/my/history","color":"#666666","hrefname":"基础功能>我的足迹"},{"id":"F0000000000004","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-quan.png","text":"我的优惠券","hrefurl":"/pagesExb/coupon/mycoupon","color":"#666666","hrefname":"基础功能>我的优惠券"},{"id":"F0000000000005","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-lingquan.png","text":"领券中心","hrefurl":"/pagesExb/coupon/couponlist","color":"#666666","hrefname":"基础功能>领券中心"},{"id":"M1596399075025131459","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-tixian.png","text":"余额提现","hrefurl":"/pagesExb/money/withdraw","color":"#666666","hrefname":"基础功能>卡金提现"},{"id":"M159639907692086731","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-mingxi.png","text":"余额明细","hrefurl":"/pagesExb/money/moneylog","color":"#666666","hrefname":"基础功能>余额明细"},{"id":"M1596399078152887395","imgurl":"'.PRE_URL.'/static/imgsrc/ico2-address.png","text":"收货地址","hrefurl":"/shopPackage/address/address","color":"#666666","hrefname":"基础功能>收货地址"}],"other":"","content":""},{"id":"M1592344367534823433","temp":"blank","params":{"height":15,"bgcolor":"#f7f7f8","margin_x":"0","margin_y":"0","platform":{"all":true},"quanxian":{"all":true}},"data":"","other":"","content":""}]';
		$insertdata['createtime'] = time();
		Db::name('designerpage')->insert(self::buildSafeInsertData('designerpage', $insertdata));

			$data_index_mp = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' =>[
					['left' => '30px','top' => '70px','type' => 'img','width' => '285px','height' => '285px','src' => PRE_URL.'/static/imgsrc/picture-1.jpg'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '商城系统'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐您加入'],
					['left' => '221px','top' =>'446px','type' => 'qrmp','width' => '94px','height' => '94px','size' => ''],
				]
			]);
			$data_index_wx = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' =>[
					['left' => '30px','top' => '70px','type' => 'img','width' => '285px','height' => '285px','src' => PRE_URL.'/static/imgsrc/picture-1.jpg'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '商城系统'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐您加入'],
					['left' => '221px','top' =>'446px','type' => 'qrwx','width' => '94px','height' => '94px','size' => ''],
				]
			]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'mp','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'wx','content'=>$data_index_wx,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'alipay','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'baidu','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'toutiao','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'qq','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'h5','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'index','platform'=>'app','content'=>$data_index_mp,'guize'=>"第一步、转发链接或图片给微信好友；\r\n第二步、从您转发的链接或图片进入商城的好友，系统将自动锁定成为您的客户, 他们在商城中购买商品，您就可以获得佣金；\r\n第三步、您可以在会员中心查看【我的团队】和【分销订单】，好友确认收货后佣金方可提现。"]);


			$data_product_mp = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' => [
					['left' => '30px','top' => '70px','type' => 'pro_img','width' => '285px','height' => '285px'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '[商品名称]'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐给你一个宝贝'],
					['left' => '35px','top' => '516px','type' => 'text','width' => '142px','height' => '22px','size' => '20px','color' => '#FD0000','content' => '￥[商品销售价]'],
					['left' => '125px','top' => '518px','type' => 'text','width' => '135px','height' => '16px','size' => '14px','color' => '#BBBBBB','content' => '原价:￥[商品市场价]'],
					['left' => '221px','top' => '446px','type' => 'qrmp','width' => '94px','height' => '94px','size' => '',],
				]
			]);
			$data_product_wx = jsonEncode([
				'poster_bg' => PRE_URL.'/static/imgsrc/posterbg.jpg',
				'poster_data' => [
					['left' => '30px','top' => '70px','type' => 'pro_img','width' => '285px','height' => '285px'],
					['left' => '30px','top' => '370px','type' => 'textarea','width' => '286px','height' => '47px','size' => '16px','color' => '#000','content' => '[商品名称]'],
					['left' => '34px','top' => '452px','type' => 'head','width' => '47px','height' => '47px','radius' => '100'],
					['left' => '89px','top' => '459px','type' => 'text','width' => '50px','height' => '18px','size' => '16px','color' => '#333333','content' => '[昵称]'],
					['left' => '90px','top' => '484px','type' => 'text','width' => '98px','height' => '14px','size' => '12px','color' => '#B6B6B6','content' => '推荐给你一个宝贝'],
					['left' => '35px','top' => '516px','type' => 'text','width' => '142px','height' => '22px','size' => '20px','color' => '#FD0000','content' => '￥[商品销售价]'],
					['left' => '125px','top' => '518px','type' => 'text','width' => '135px','height' => '16px','size' => '14px','color' => '#BBBBBB','content' => '原价:￥[商品市场价]'],
					['left' => '221px','top' => '446px','type' => 'qrwx','width' => '94px','height' => '94px','size' => '',],
				]
			]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'product','platform'=>'app','content'=>$data_product_mp]);

			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collage','platform'=>'app','content'=>$data_product_mp]);

			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'collageteam','platform'=>'app','content'=>$data_product_mp]);

			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjia','platform'=>'app','content'=>$data_product_mp]);

			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>$aid,'type'=>'kanjiajoin','platform'=>'app','content'=>$data_product_mp]);

			$insertdata = [];
			$insertdata['aid'] = $aid;
			$insertdata['menucount'] = 4;
			$insertdata['indexurl'] = '/pages/index/index';
			$insertdata['menudata'] = jsonEncode([
				"color"=>"#BBBBBB",
				"selectedColor"=>"#FD4A46",
				"backgroundColor"=>"#ffffff",
				"borderStyle"=>"black",
				"position"=>"bottom",
				"list"=>[
					["text"=>"首页","pagePath"=>"/pages/index/index","iconPath"=>PRE_URL.'/static/img/tabbar/home.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/home2.png',"pagePathname"=>"基础功能>首页"
					],
					["text"=>"分类","pagePath"=>"/shopPackage/shop/classify","iconPath"=>PRE_URL.'/static/img/tabbar/category.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/category2.png',"pagePathname"=>"基础功能>分类商品"
					],
					["text"=>"购物车","pagePath"=>"/shopPackage/shop/cart","iconPath"=>PRE_URL.'/static/img/tabbar/cart.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/cart2.png',"pagePathname"=>"基础功能>购物车"
					],
					["text"=>"我的","pagePath"=>"/pagesMember/usercenter/index","iconPath"=>PRE_URL.'/static/img/tabbar/my.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/my2.png',"pagePathname"=>"基础功能>会员中心"
					],
					["text"=>"导航名称","pagePath"=>"","iconPath"=>PRE_URL.'/static/img/tabbar/category.png',"selectedIconPath"=>PRE_URL.'/static/img/tabbar/category2.png',"pagePathname"=>""
					],
				]
			]);
			$insertdata['navigationBarBackgroundColor'] = '#333333';
			$insertdata['navigationBarTextStyle'] = 'white';
			$insertdata['platform'] = 'mp';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'wx';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'alipay';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'baidu';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'toutiao';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'qq';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'h5';
			Db::name('designer_menu')->insert($insertdata);
			$insertdata['platform'] = 'app';
			Db::name('designer_menu')->insert($insertdata);

			$h5dir = ROOT_PATH.'h5/';
			$h5tpl = $h5dir.'index.html';
			if(!is_file($h5tpl)){
				$h5dir = ROOT_PATH.'public/h5/';
				$h5tpl = $h5dir.'index.html';
			}
			if(is_file($h5tpl)){
				$html = file_get_contents($h5tpl);
				$thishtml = str_replace('var uniacid=1;','var uniacid='.$aid.';',$html);
				if(!is_dir($h5dir)) mk_dir($h5dir);
				file_put_contents($h5dir.$aid.'.html',$thishtml);
			}
		}
	}

	public static function getDefaultTextsetConfig(): array
	{
		return [
			'余额'=>'余额',
			'积分'=>'积分',
			'代币A'=>'代币A',
			'积分3'=>'积分3',
			'佣金'=>'佣金',
			'优惠券'=>'优惠券',
			'会员'=>'会员',
			'一级'=>'销售一组',
			'二级'=>'销售二组',
			'奖励队列'=>'排队中',
			'奖励记录'=>'已完成',
			'团队分红'=>'团队分红',
			'股东分红'=>'股东分红',
			'区域代理分红'=>'区域代理分红',
			'现金券'=>'成就值',
			'我的团队'=>'我的团队',
			'分销订单'=>'分销订单',
			'自定义表单'=>'自定义表单',
			'推荐人'=>'推荐人',
			'贡献值'=>'贡献值',
			'配资'=>'配资',
			'消费币'=>'消费币',
			'绿积分'=>'绿积分',
			'黄积分'=>'金元宝',
			'虚拟账号积分'=>'虚拟账号积分',
			'创业值'=>'创业值',
			'收益池'=>'收益池',
			'让利'=>'让利',
			'列表导航'=>'列表导航',
			'列表名称'=>'列表名称',
			'列表1'=>'列表1',
			'列表2'=>'列表2',
			'备注信息'=>'备注信息'
		];
	}

	public static function mergeDefaultTextsetConfig($textset): array
	{
		if(!is_array($textset)){
			$textset = [];
		}
		foreach(self::getDefaultTextsetConfig() as $textKey => $textValue){
			if(!isset($textset[$textKey]) || $textset[$textKey] === ''){
				$textset[$textKey] = $textValue;
			}
		}
		return $textset;
	}

	private static function getLegacyTextsetConfig(): array
	{
		return [
			'余额'=>'余额',
			'积分'=>'积分',
			'佣金'=>'佣金',
			'优惠券'=>'优惠券',
			'会员'=>'会员',
		];
	}

	private static function getColumnMaxLength(string $table, string $column): int
	{
		$prefix = config('database.connections.mysql.prefix');
		$table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
		$column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
		if($table === '' || $column === ''){
			return 0;
		}
		$columns = Db::query("SHOW COLUMNS FROM `{$prefix}{$table}` LIKE '{$column}'");
		if(!$columns || !isset($columns[0]['Type'])){
			return 0;
		}
		if(preg_match('/\((\d+)\)/', strtolower($columns[0]['Type']), $matches)){
			return intval($matches[1]);
		}
		return 0;
	}

	/**
	 * 根据数据表结构，为非空且无默认值的字段填充安全默认值，合并到基础数据中。
	 * 用于兼容MySQL 5.7+严格模式，避免因新字段无默认值导致插入失败
	 * @param string $table 不含前缀的数据表名，例如 admin_set、admin_setapp_mp
	 * @param array $baseData 需要插入的基础数据
	 * @return array 可安全插入的数据
	 */
	private static function buildSafeInsertData(string $table, array $baseData): array
	{
		$prefix = config('database.connections.mysql.prefix');
		$columns = Db::query("SHOW COLUMNS FROM `{$prefix}{$table}`");
		$data = $baseData;
		foreach($columns as $col){
			$field = $col['Field'];
			if($field === 'id') continue; // 自增主键跳过
			if(array_key_exists($field, $data)) continue; // 已赋值的跳过
			$nullable = isset($col['Null']) ? strtolower($col['Null']) : '';
			$defaultVal = array_key_exists('Default', $col) ? $col['Default'] : null;
			$type = isset($col['Type']) ? strtolower($col['Type']) : '';
			if($nullable === 'no' && $defaultVal === null){
				if(strpos($type,'decimal') !== false || strpos($type,'float') !== false || strpos($type,'double') !== false){
					$data[$field] = 0;
				}elseif(strpos($type,'int') !== false || strpos($type,'bit') !== false || strpos($type,'bool') !== false){
					$data[$field] = 0;
				}elseif(strpos($type,'char') !== false || strpos($type,'text') !== false){
					$data[$field] = '';
				}elseif(strpos($type,'datetime') !== false || strpos($type,'timestamp') !== false || strpos($type,'date') !== false){
					$data[$field] = date('Y-m-d H:i:s');
				}else{
					$data[$field] = 0; // 兜底
				}
			}
		}
		return $data;
	}

	//设计页面数据处理
	// 批发阶梯价：为商品补充 wholesale_enable 与阶梯最低价 wholesale_min_price（用于 DIY 列表展示）
	public static function appendWholesaleMinPrice($pro){
		$pro['wholesale_min_price'] = 0;
		if(isset($pro['wholesale_enable']) && intval($pro['wholesale_enable'])==1 && !empty($pro['wholesale_data'])){
			$tiers = json_decode($pro['wholesale_data'], true);
			if(is_array($tiers) && $tiers){
				$minp = null;
				foreach($tiers as $t){
					if(isset($t['price']) && is_numeric($t['price'])){
						$p = (float)$t['price'];
						if($minp === null || $p < $minp) $minp = $p;
					}
				}
				if($minp !== null) $pro['wholesale_min_price'] = round($minp,2);
			}
		}
		$pro['wholesale_enable'] = isset($pro['wholesale_enable']) ? intval($pro['wholesale_enable']) : 0;
		unset($pro['wholesale_data']);
		return $pro;
	}
	protected static function normalizeDesignerProductPageSize(array $params): int{
		$pageSize = intval($params['_page_size'] ?? $params['page_load_num'] ?? $params['page_size'] ?? 10);
		if($pageSize <= 0){
			$pageSize = 10;
		}
		return max(1, min(50, $pageSize));
	}
	protected static function normalizeDesignerProductTotalLimit(array $params): int{
		$totalLimit = intval($params['proshownum'] ?? 10);
		if($totalLimit <= 0){
			$totalLimit = 10;
		}
		return max(1, $totalLimit);
	}
		protected static function normalizeDesignerProductPage(array $params): int{
			$page = intval($params['_page'] ?? 1);
			return max(1, $page);
		}
		protected static function normalizeDesignerCategoryIds($value): array{
			$values = is_array($value) ? $value : explode(',', strval($value));
			$ids = [];
			foreach($values as $item){
				if(is_array($item)) continue;
				$id = intval(trim(strval($item)));
				if($id > 0){
					$ids[$id] = $id;
				}
			}
			return array_values($ids);
		}
		protected static function appendDesignerProductCategoryWhere(array &$where, $categoryParam, int $aid, $member = null): void{
			$categoryIds = self::normalizeDesignerCategoryIds($categoryParam);
			if(!$categoryIds){
				return;
			}

			$where2 = "find_in_set('-1',showtj)";
			if($member){
				$levelid = intval($member['levelid'] ?? 0);
				if($levelid > 0){
					$where2 .= " or find_in_set('".$levelid."',showtj)";
				}
				if(intval($member['subscribe'] ?? 0) == 1){
					$where2 .= " or find_in_set('0',showtj)";
				}
			}
			$tjwhere = [Db::raw($where2)];

			$allCategoryIds = $categoryIds;
			$parentIds = $categoryIds;
			for($depth = 0; $depth < 2 && $parentIds; $depth++){
				$childIds = Db::name('shop_category')
					->where($tjwhere)
					->where('aid',$aid)
					->where('pid','in',$parentIds)
					->column('id');
				$childIds = self::normalizeDesignerCategoryIds($childIds);
				$childIds = array_values(array_diff($childIds, $allCategoryIds));
				if(!$childIds){
					break;
				}
				$allCategoryIds = array_values(array_unique(array_merge($allCategoryIds, $childIds)));
				$parentIds = $childIds;
			}

			$whereCid = [];
			foreach($allCategoryIds as $cid){
				$whereCid[] = "find_in_set({$cid},cid)";
			}
			if($whereCid){
				$where[] = Db::raw('('.implode(' or ', $whereCid).')');
			}
		}
		protected static function resolveDesignerProductRandSeed(array $component, int $aid): int{
			$params = $component['params'] ?? [];
			$seed = intval($params['_rand_seed'] ?? 0);
		if($seed > 0){
			return $seed;
		}
		$componentId = strval($component['id'] ?? '');
		$rawSeed = sprintf('%u', crc32($aid . ':' . $componentId . ':' . date('Ymd')));
		return max(1, intval($rawSeed) % 2147483647);
	}
	protected static function buildDesignerProductPageMeta(array $component, int $page, int $pageSize, bool $hasMore, int $randSeed, int $totalLimit): array{
		return [
			'enabled' => 1,
			'page' => $page,
			'page_size' => $pageSize,
			'total_limit' => $totalLimit,
			'has_more' => $hasMore ? 1 : 0,
			'load_more_api' => 'ApiIndex/getProductPage',
			'rand_seed' => $randSeed,
			'component_id' => strval($component['id'] ?? ''),
		];
	}

	protected static function resolvePromoFloorDiscountText($sellPrice, $marketPrice): string{
		if(!is_numeric($sellPrice) || !is_numeric($marketPrice)){
			return '';
		}
		$sellPrice = floatval($sellPrice);
		$marketPrice = floatval($marketPrice);
		if($sellPrice <= 0 || $marketPrice <= 0 || $sellPrice >= $marketPrice){
			return '';
		}
		$discount = round($sellPrice / $marketPrice * 10, 1);
		if($discount <= 0 || $discount >= 10){
			return '';
		}
		$discountText = abs($discount - round($discount)) < 0.001
			? strval(intval(round($discount)))
			: rtrim(rtrim(number_format($discount, 1, '.', ''), '0'), '.');
		return $discountText . '折';
	}

	/**
	 * 营销商品楼层只负责分组陈列，商品运行时数据统一复用 product 组件解析链路。
	 */
	protected static function resolvePromoFloorGroups(array $component, $aid, $mid, $platform, $latitude, $longitude, $area, array $mendianOptions): array{
		$params = isset($component['params']) && is_array($component['params']) ? $component['params'] : [];
		$groups = isset($params['groups']) && is_array($params['groups']) ? $params['groups'] : [];
		$layout = strval($params['layout'] ?? 'rail');
		$groupLimit = $layout === 'rail' ? 1 : ($layout === 'double' ? 2 : 3);
		$productLimit = $layout === 'rail' ? 10 : ($layout === 'double' ? 20 : 1);
		$groups = array_slice($groups, 0, $groupLimit);
		$resolvedGroups = [];

		foreach($groups as $groupIndex => $group){
			if(!is_array($group)){
				continue;
			}
			$productFrom = strval($group['productfrom'] ?? '0') === '1' ? '1' : '0';
			$manualProducts = isset($group['products']) && is_array($group['products'])
				? array_slice($group['products'], 0, $productLimit)
				: [];
			foreach($manualProducts as $manualIndex => $manualProduct){
				if(!is_array($manualProduct)){
					unset($manualProducts[$manualIndex]);
					continue;
				}
				if(empty($manualProduct['id'])){
					$manualProduct['id'] = 'G' . time() . $groupIndex . $manualIndex . rand(1000, 9999);
				}
				$manualProducts[$manualIndex] = $manualProduct;
			}
			$manualProducts = array_values($manualProducts);
			$virtualParams = [
				'platform' => ['all' => true],
				'quanxian' => ['all' => true],
				'showmids' => '',
				'productfrom' => $productFrom,
				'bid' => strval($group['bid'] ?? ''),
				'category' => $group['category'] ?? [],
				'category2' => '',
				'group' => isset($group['group']) && is_array($group['group']) ? $group['group'] : ['all' => true],
				'sortby' => strval($group['sortby'] ?? 'sort'),
				'proshownum' => $productLimit,
				'page_load_num' => $productLimit,
				'product_page' => 1,
				'showbname' => '0',
				'showcoupon' => '0',
				'show_follow_buy' => '0',
				'member_price_style' => '1',
			];
			$virtualComponent = [
				'id' => strval($component['id'] ?? 'promo_floor') . '_group_' . $groupIndex,
				'temp' => 'product',
				'params' => $virtualParams,
				'data' => $productFrom === '0' ? $manualProducts : [],
			];
			$resolvedContent = json_decode(self::initpagecontent(
				json_encode([$virtualComponent], JSON_UNESCAPED_UNICODE),
				$aid,
				$mid,
				$platform,
				$latitude,
				$longitude,
				$area,
				$mendianOptions
			), true);
			$products = isset($resolvedContent[0]['data']) && is_array($resolvedContent[0]['data'])
				? array_slice($resolvedContent[0]['data'], 0, $productLimit)
				: [];

			$manualOverrides = [];
			foreach($manualProducts as $manualProduct){
				if(!is_array($manualProduct)) continue;
				$manualProid = intval($manualProduct['proid'] ?? $manualProduct['product_id'] ?? 0);
				if($manualProid > 0){
					$manualOverrides[$manualProid] = $manualProduct;
				}
			}

			foreach($products as $productIndex => $product){
				$proid = intval($product['proid'] ?? 0);
				$override = $manualOverrides[$proid] ?? [];
				$promoText = trim(strval($override['promo_text'] ?? ''));
				if($promoText === ''){
					$tagMode = strval($group['promo_tag_mode'] ?? 'auto_discount');
					if($tagMode === 'fixed'){
						$promoText = trim(strval($group['promo_tag_text'] ?? ''));
					}elseif($tagMode === 'auto_discount'){
						$promoText = self::resolvePromoFloorDiscountText($product['sell_price'] ?? '', $product['market_price'] ?? '');
					}
				}
				$products[$productIndex]['promo_text'] = $promoText;
				$products[$productIndex]['promo_color'] = strval($override['promo_color'] ?? $group['badge_color'] ?? $params['theme_color'] ?? '#ff4d4f');
			}

			$resolvedGroup = $group;
			$resolvedGroup['products'] = $products;
			$resolvedGroups[] = $resolvedGroup;
		}
		return $resolvedGroups;
	}
	public static function initpagecontent($pagecontent,$aid,$mid=-1,$platform='all',$latitude='',$longitude='',$area='',$mendianOptions=[]){
		$pagecontent = self::normalizePageContent($pagecontent);
		$mendianBid = intval($mendianOptions['bid'] ?? $mendianOptions['context_bid'] ?? 0);
		$mendianRuntimeOptions = [
			'latitude' => $latitude,
			'longitude' => $longitude,
		];
		if(isset($mendianOptions['mdid'])){
			$mendianRuntimeOptions['mdid'] = intval($mendianOptions['mdid']);
		}
		if(!empty($mendianOptions['strict_bid'])){
			$mendianRuntimeOptions['strict_bid'] = true;
		}
		$mendianGroupbuyContext = MendianGroupbuy::resolveRuntimeContext($aid, $mendianBid, $mendianRuntimeOptions);
		if($platform !='all'){
			$newpagecontent = [];
			foreach($pagecontent as $k=>$v){
				if($v['params']['platform']['all'] || $v['params']['platform'][$platform]){
					$newpagecontent[] = $v;
				}
			}
			$pagecontent = $newpagecontent;
		}
		if($mid !='-1'){
			if($mid == 0){
				$levelid = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
			}else{
				$levelid = Db::name('member')->where('aid',aid)->where('id',$mid)->value('levelid');
			}
			$newpagecontent = [];
			foreach($pagecontent as $k=>$v){
				if($v['params']['quanxian']['all'] || $v['params']['quanxian'][$levelid] || ($v['params']['showmids'] && in_array($mid,explode(',',$v['params']['showmids'])))){
					$newpagecontent[] = $v;
				}
			}
			$pagecontent = $newpagecontent;
		}

		if($mid > 0){
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
		}else{
			$member = [];
		}
		$memberPriceCheckoutOnly = self::isShopMemberPriceCheckoutOnly(intval($aid));
		foreach($pagecontent as $k=>$v){
			if($v['temp'] == 'text'){ //文本
				$showcontent = $v['params']['content'];
				if(strpos($showcontent,'[会员数]')!==false){
					$defaultlv = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
					$membercount = Db::name('member')->where('aid',$aid)->where('levelid','<>',$defaultlv['id'])->count();
					$showcontent = str_replace('[会员数]',$membercount,$showcontent);
				}elseif(strpos($showcontent,'[会员数+')!==false){
					$defaultlv = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
					$membercount = Db::name('member')->where('aid',$aid)->where('levelid','<>',$defaultlv['id'])->count();
					$showcontent = preg_replace_callback('/\[会员数\+(\d+)\]/',function($matches) use ($membercount){return $membercount + $matches[1];},$showcontent);
				}
				$pagecontent[$k]['params']['showcontent'] = $showcontent;
			}
			if($v['temp'] == 'cube'){ //图片魔方 获取魔方高度
				$maxheight = 0;
				foreach($v['params']['layout'] as $k1=>$rows){
					foreach($rows as $k2=>$col){
						if(!$col['isempty'] && $k1 + $col['rows'] > $maxheight){
							$maxheight = $k1 + $col['rows'];
						}
					}
				}
				$pagecontent[$k]['params']['maxheight'] = $maxheight;
			}elseif($v['temp'] == 'promo_floor'){
				$pagecontent[$k]['data'] = self::resolvePromoFloorGroups(
					$v,
					$aid,
					$mid,
					$platform,
					$latitude,
					$longitude,
					$area,
					$mendianOptions
				);
			}elseif($v['temp'] == 'product'){//产品列表 获取产品信息
				// 检查是否启用了混合模式
				$isMixedMode = isset($v['params']['mixedMode']) && $v['params']['mixedMode'] === true;
				$productPage = self::normalizeDesignerProductPage($v['params'] ?? []);
				$productPageSize = self::normalizeDesignerProductPageSize($v['params'] ?? []);
				$productTotalLimit = self::normalizeDesignerProductTotalLimit($v['params'] ?? []);
				$productOffset = ($productPage - 1) * $productPageSize;
				
				if($v['params']['showbname'] == '1'){
					$bArr = Db::name('business')->where('aid',$aid)->column('id,name,logo','id');
					$sysset = Db::name('admin_set')->where('aid',$aid)->field('name,logo')->find();
					$bArr['0'] = ['id'=>0,'name'=>$sysset['name'],'logo'=>$sysset['logo']];
				}
				// 商品分组名称映射（用于标题前缀标签）
				$groupNameArr = Db::name('shop_group')->where('aid',$aid)->where('status',1)->order('sort desc,id')->column('name','id');
				// 商品服务名称映射（用于标题下方服务标签）
				$fuwuNameArr = Db::name('shop_fuwu')->where('aid',$aid)->where('status',1)->order('sort desc,id')->column('name','id');
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$lookupProid = isset($pro['proid']) ? $pro['proid'] : '';
						if(!$lookupProid && isset($pro['id'])){
							$fallbackId = $pro['id'];
							if(!(is_string($fallbackId) && preg_match('/^G\d+$/',$fallbackId))){
								$lookupProid = $fallbackId;
							}
						}
						if(!$lookupProid){
							continue;
						}
						$field = 'id proid,bid,name,pic,sellpoint,sell_price,lvprice,lvprice_data,market_price,sales,sort,price_type,stock,cid,gid,fwid,scoredkmaxsethuang,scoredkmaxunithuang,show_price,hide_price_text,is_newcustom,yh_price,wholesale_enable,wholesale_data';
						$newpro = Db::name('shop_product')->field($field)->where('aid',$aid)->where('id',$lookupProid)->where('status',1)->where('ischecked',1)->find();
						if($newpro){
							$newpro = self::appendWholesaleMinPrice($newpro);
							$newpro['group_tag'] = self::resolveProductGroupTag($newpro['gid'] ?? '', $groupNameArr);
							$newpro['service_tags'] = self::resolveProductServiceTags($newpro['fwid'] ?? '', $fuwuNameArr);
							if(!MendianGroupbuy::isProductAllowed($mendianGroupbuyContext, intval($newpro['proid'] ?? 0))){
								continue;
							}
							$newpro['id'] = $pro['id'];
							// 添加 is_member_yh 字段
							$newpro['is_member_yh'] = isset($member['is_yh']) ? $member['is_yh'] : 0;
							$newpro['sell_price_origin'] = $newpro['sell_price'];
							if($memberPriceCheckoutOnly){
								$newpro = self::hideShopMemberPriceData($newpro);
							}else{
								$newpro = self::appendProductMemberPriceMeta($newpro, $member);
								if($newpro['member_price_current_match'] == 1){
									$newpro['sell_price'] = $newpro['member_price_value'];
								}
							}
							if($v['params']['showbname'] == '1'){
								$newpro['binfo'] = $bArr[$newpro['bid']];
							}
							if($v['params']['showcoupon']){
								$couponlist = Db::name('coupon')->where('aid',$aid)->where('bid',$newpro['bid'])->where('isgive','<>',2)->where('tolist',1)->where('type','in','1')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc,id desc')->select()->toArray();
								$newcplist = [];
								foreach($couponlist as $k3=>$v3){
									$showtj = explode(',',$v3['showtj']);
									$memberLevelId = isset($member['levelid']) ? $member['levelid'] : '';
									if(!in_array('-1',$showtj) && !in_array($memberLevelId,$showtj)){ //不是所有人
										continue;
									}
									//0全场通用,1指定类目,2指定商品
									if(!in_array($v3['fwtype'],[0,1,2])){
										continue;
									}
									if($v3['fwtype']==2){//指定商品可用
										$productids = explode(',',$v3['productids']);
										if(!in_array($newpro['proid'],$productids)){
											continue;
										}
									}
									if($v3['fwtype']==1){//指定类目可用
										$categoryids = explode(',',$v3['categoryids']);
										$cids = explode(',',$newpro['cid']);
										$clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
										foreach($clist as $kc=>$vc){
											$categoryids[] = $vc['id'];
											$cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
											$categoryids[] = $cate2['id'];
										}
										if(!array_intersect($cids,$categoryids)){
											continue;
										}
									}
									$newcplist[] = $v3;
								}
								$newpro['couponlist'] = $newcplist;
							}

							// 2024-07-09 - 隐藏价格
							if($newpro['show_price'] > 0) {
								//限制等级
								$levelids = explode(',', $newpro['show_price']);
								$memberLevelId = isset($member['levelid']) ? $member['levelid'] : '';
								if(!in_array($memberLevelId, $levelids)) {
									$newpro['sell_price'] = $newpro['hide_price_text'];
									unset($newpro['market_price']);
								}
							}

							// 计算红包 - 需要传入原始的商品数据
							$newpro['huang_dx'] = \app\admin\common\Member::getShopProductScoredkmaxsethuang(aid, [
								'scoredkmaxsethuang' => $newpro['scoredkmaxsethuang'],
								'scoredkmaxunithuang' => $newpro['scoredkmaxunithuang']
							]);

							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = self::appendFollowBuyAtmosphere($newdata, $v['params'] ?? [], intval($aid), $v['id'] ?? $k);
					}else{
						$productRandSeed = self::resolveDesignerProductRandSeed($v, intval($aid));
				   
						$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['ischecked','=',1];
					//$where[] = ['status','=',1];
					if(defined('isdouyin') && isdouyin == 1){
						$where[] = ['douyin_product_id','<>',''];
					}else{
						$where[] = ['douyin_product_id','=',''];
					}
					$nowtime = time();
					$nowhm = date('H:i');
					$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

					if(isset($v['params']['category'])){
						self::appendDesignerProductCategoryWhere($where, $v['params']['category'], intval($aid), $member);
					}
					if($v['params']['category2']){
						$cid2 = intval($v['params']['category2']);
						if($cid2 > 0){
							$chidlc2 = Db::name('shop_category2')->where('aid',$aid)->where('pid',$cid2)->column('id');
							if($chidlc2){
								$chidlc2 = array_merge($chidlc2, [$cid2]);
								$whereCid2 = '(';
								foreach($chidlc2 as $k => $c){
									if(count($chidlc2) == ($k + 1))
										$whereCid2 .= "find_in_set({$c},cid2)";
									else
										$whereCid2 .= " find_in_set({$c},cid2) or ";
								}
								$where[] = Db::raw($whereCid2 . ')');
							}else{
								$where[] = Db::raw("find_in_set({$cid2},cid2)");
							}
						}
					}
					if($v['params']['group']){
						$_string = array();
						foreach($v['params']['group'] as $gid=>$istrue){
							$gid = strval($gid);
							if($istrue=='true'){
								if($gid == 'all'){
									$_string[] = "1=1";
								}elseif($gid == '0'){
									$_string[] = "gid is null or gid=''";
								}else{
									$_string[] = "find_in_set({$gid},gid)";
								}
							}
						}
						if(!$_string){
							$where2 = '0=1';
						}else{
							$where2 = implode(" or ",$_string);
						}
					}else{
						$where2 = '1=1';
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
					$componentBid = intval($v['params']['bid'] ?? 0);
					$componentMendianContext = $componentBid > 0
						? MendianGroupbuy::resolveRuntimeContext($aid, $componentBid, [
							'mdid' => intval($mendianGroupbuyContext['mdid'] ?? 0),
							'latitude' => $latitude,
							'longitude' => $longitude,
							'strict_bid' => !empty($mendianOptions['strict_bid']),
						])
						: $mendianGroupbuyContext;
					MendianGroupbuy::appendProductWhere($where, $componentMendianContext);
					$where3 = "find_in_set('-1',showtj)";
					if($member){
						$where3.= " or find_in_set('".$member['levelid']."',showtj)";
						if($member['subscribe']==1){
							$where3 .= " or find_in_set('0',showtj)";
						}
					}

					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand('.$productRandSeed.')');

					$bjuli = [];
					if($v['params']['sortby'] == 'juli' && $latitude && $longitude){
						$border = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
						$blist = Db::name('business')->where('aid',$aid)->where('status',1)->where("longitude!='' and latitude!=''")->field('id,longitude,latitude')->order($border)->select()->toArray();
						$bids = [];
						$sysset = Db::name('admin_set')->where('aid',$aid)->find();
						$b0juli = getdistance($longitude,$latitude,$sysset['longitude'],$sysset['latitude'],1);
						foreach($blist as $binfo){
							$juli = getdistance($longitude,$latitude,$binfo['longitude'],$binfo['latitude'],1);
							if($juli > $b0juli && !in_array('0',$bids)){
								$bids[] = '0';
								$bjuli['0'] = ''.$b0juli.'km';
							}
							$bids[] = $binfo['id'];
							$bjuli[''.$binfo['id']] = ''.$juli.'km';
						}
						$order = Db::raw('field(bid,'.implode(',',$bids).'),sort desc,id desc');
					}
					
					//区域限制
        			$area_set = Db::name('admin_set')->where('aid',aid)->field('area_on,areamode')->find();
        			$area_id = input('param.area_id/d');
        			if($area_set && $area_set['area_on'] && $area_id && self::tableExists('admin_set_area_permission')){
        			    $permission = Db::name('admin_set_area_permission')->where(['a_area'=>md5(aid.$area_id)])->value('permission');
        			    //var_dump($permission);
        			    if($permission){
            			    $permission = json_decode($permission,true);
            			    if($area_set['areamode']==0)$limit_field = 'province_id';
            			    if($area_set['areamode']==1)$limit_field = 'city_id';
            			    if($area_set['areamode']==2)$limit_field = 'district_id';
            			    if($permission['product_visible_area']==0){
            			        //仅可见该区域
            			        $where[] = [$limit_field,'=',$area_id];
            			    }else if($permission['product_visible_area']==1){
            			        //可见部分区域
            			        $where[] = [$limit_field,'in',explode(',',$permission['product_visible_areas'])];
            			    }else if($permission['product_visible_area']==2){
            			        //不可见部分区域
            			        $where[] = [$limit_field,'notin',explode(',',$permission['product_unvisible_areas'])];
            			    } 
        			    }
        			}
					

					$field = 'id proid,bid,name,pic,sellpoint,sell_price,lvprice,lvprice_data,market_price,sales,sort,price_type,stock,cid,gid,fwid,scoredkmaxsethuang,scoredkmaxunithuang,show_price,hide_price_text,is_newcustom,yh_price,wholesale_enable,wholesale_data';
					$productCurrentLimit = max(0, min($productPageSize, $productTotalLimit - $productOffset));
					if($productCurrentLimit > 0){
			        	$result = Db::name('shop_product')->field($field)->where($where)->where($where2)->where($where3)->order($order)->limit($productOffset, $productCurrentLimit + 1)->select()->toArray();
					}else{
						$result = [];
					}
//                    dd(Db::getlastsql());
					if(!$result) $result = array();
					$productHasExtra = count($result) > $productCurrentLimit;
					$productHasMore = $productHasExtra && ($productOffset + $productCurrentLimit) < $productTotalLimit;
					if($productHasExtra){
						$result = array_slice($result, 0, $productCurrentLimit);
					}
					foreach($result as $k2=>$v2){
					   $result[$k2] = self::appendWholesaleMinPrice($result[$k2]);
					   $v2 = $result[$k2];
					   // var_dump($member['is_yh']);
					   // $result[$k2]['is_member_yh'] = $member['is_yh']?$member['is_yh']:1;
					   $result[$k2]['is_member_yh'] = isset($member['is_yh']) ? $member['is_yh'] : 0;
						$result[$k2]['group_tag'] = self::resolveProductGroupTag($v2['gid'] ?? '', $groupNameArr);
						$result[$k2]['service_tags'] = self::resolveProductServiceTags($v2['fwid'] ?? '', $fuwuNameArr);
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						$result[$k2]['sell_price_origin'] = $result[$k2]['sell_price'];
						if($memberPriceCheckoutOnly){
							$result[$k2] = self::hideShopMemberPriceData($result[$k2]);
						}else{
							$result[$k2] = self::appendProductMemberPriceMeta($result[$k2], $member);
							if($result[$k2]['member_price_current_match'] == 1){
								$result[$k2]['sell_price'] = $result[$k2]['member_price_value'];
							}
						}
						if($bjuli){
							$result[$k2]['juli'] = $bjuli[''.$v2['bid']];
						}
						if($v['params']['showbname'] == '1'){
							$result[$k2]['binfo'] = $bArr[$v2['bid']];
						}
						if($v['params']['showcoupon']){
							$couponlist = Db::name('coupon')->where('aid',$aid)->where('bid',$v2['bid'])->where('isgive','<>',2)->where('tolist',1)->where('type','in','1,4,10')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc,id desc')->select()->toArray();
							$newcplist = [];
							foreach($couponlist as $k3=>$v3){
								$showtj = explode(',',$v3['showtj']);
								$memberLevelId = isset($member['levelid']) ? $member['levelid'] : '';
								if(!in_array('-1',$showtj) && !in_array($memberLevelId,$showtj)){ //不是所有人
									continue;
								}
								//0全场通用,1指定类目,2指定商品
								if(!in_array($v3['fwtype'],[0,1,2])){
									continue;
								}
								if($v3['fwtype']==2){//指定商品可用
									$productids = explode(',',$v3['productids']);
									if(!in_array($v2['proid'],$productids)){
										continue;
									}
								}
								if($v3['fwtype']==1){//指定类目可用
									$categoryids = explode(',',$v3['categoryids']);
									$cids = explode(',',$v2['cid']);
									$clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
									foreach($clist as $kc=>$vc){
										$categoryids[] = $vc['id'];
										$cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
										$categoryids[] = $cate2['id'];
									}
									if(!array_intersect($cids,$categoryids)){
										continue;
									}
								}
								$newcplist[] = $v3;
							}
							$result[$k2]['couponlist'] = $newcplist;
						}
						
						//计算红包
			            $result[$k2]['huang_dx']=\app\admin\common\Member::getShopProductScoredkmaxsethuang(aid,$v2);

			            // 2024-07-09 - 隐藏价格
			            if($result[$k2]['show_price'] > 0) {
			                //限制等级
			                $levelids = explode(',', $result[$k2]['show_price']);
			                $memberLevelId = isset($member['levelid']) ? $member['levelid'] : '';
			                if(!in_array($memberLevelId, $levelids)) {
			                	$result[$k2]['sell_price'] = $result[$k2]['hide_price_text'];
			                	unset($result[$k2]['market_price']);
			                }
			            }
					}
					
					 
					$pagecontent[$k]['data'] = self::appendFollowBuyAtmosphere($result, $v['params'] ?? [], intval($aid), $v['id'] ?? $k);
					$pagecontent[$k]['product_page'] = self::buildDesignerProductPageMeta($v, $productPage, $productPageSize, $productHasMore, $productRandSeed, $productTotalLimit);
				}
				
				// 检查是否启用了混合模式
				$isMixedMode = isset($v['params']['mixedMode']) && $v['params']['mixedMode'] === true;
				
				// 如果启用了混合模式，在后端处理文章和视频数据
				if ($isMixedMode) {
					// 记录日志
					\think\facade\Log::write("2024-07-23 15:30:00-INFO-[System.php][initpagecontent_001] 后端处理混合模式数据", 'info');
					
					// 初始化混合数据数组
					$pagecontent[$k]['mixed_data'] = [
						'products' => $pagecontent[$k]['data'],
						'articles' => [],
						'videos' => []
					];
					
					// 处理文章数据 (如果启用)
					if (isset($v['params']['mixArticle']) && $v['params']['mixArticle'] === true) {
						\think\facade\Log::write("2024-07-23 15:30:00-INFO-[System.php][initpagecontent_002] 处理混合模式文章数据", 'info');
						$articles = [];
						
						if (isset($v['params']['articleFrom']) && $v['params']['articleFrom'] == 1 && isset($v['params']['articleCategory'])) {
							// 从分类获取文章
							$category = intval($v['params']['articleCategory']);
							$shownum = isset($v['params']['articleShownum']) ? intval($v['params']['articleShownum']) : 5;
							
							$where = [
								['aid', '=', $aid],
								['status', '=', 1]
							];
							
							if ($category) {
								$chidlc = Db::name('article_category')->where('aid', $aid)->where('pid', $category)->select()->toArray();
								if ($chidlc) {
									$cids = [$category];
									foreach ($chidlc as $c) {
										$cids[] = intval($c['id']);
									}
									$where[] = ['cid', 'in', $cids];
								} else {
									$where[] = ['cid', '=', $category];
								}
							}
							
							$order = 'sort desc';
							if (isset($v['params']['articleSortby'])) {
								if ($v['params']['articleSortby'] == 'createtimedesc') $order = 'createtime desc';
								if ($v['params']['articleSortby'] == 'createtime') $order = 'createtime';
								if ($v['params']['articleSortby'] == 'readnum') $order = 'readnum desc';
							}
							
							$articles = Db::name('article')
								->field('id,name,cid,pic,content,description,readnum,createtime')
								->where($where)
								->order($order)
								->limit($shownum)
								->select()
								->toArray();
								
							// 标记数据类型为文章
							foreach ($articles as &$art) {
								$art['type'] = 'article';
								$art['id'] = 'A' . time() . rand(10000000, 99999999);
							}
						}
						
						$pagecontent[$k]['mixed_data']['articles'] = $articles;
					}
					
					// 处理短视频数据 (如果启用)
					if (isset($v['params']['mixVideo']) && $v['params']['mixVideo'] === true) {
						\think\facade\Log::write("2024-07-23 15:30:00-INFO-[System.php][initpagecontent_003] 处理混合模式视频数据", 'info');
						$videos = [];
						
						if (isset($v['params']['videoFrom']) && $v['params']['videoFrom'] == 1 && isset($v['params']['videoCategory'])) {
							// 从分类获取视频
							$category = intval($v['params']['videoCategory']);
							$shownum = isset($v['params']['videoShownum']) ? intval($v['params']['videoShownum']) : 3;
							
							$where = [
								['aid', '=', $aid],
								['status', '=', 1]
							];
							
							if ($category) {
								$where[] = ['cid', '=', $category];
							}
							
							$order = 'sort desc';
							if (isset($v['params']['videoSortby'])) {
								if ($v['params']['videoSortby'] == 'createtimedesc') $order = 'createtime desc';
								if ($v['params']['videoSortby'] == 'createtime') $order = 'createtime';
								if ($v['params']['videoSortby'] == 'view_num') $order = 'view_num desc';
							}
							
							$videos = Db::name('shortvideo')
								->field('id videoId,name,description,coverimg,view_num,zan_num,createtime')
								->where($where)
								->order($order)
								->limit($shownum)
								->select()
								->toArray();
								
							// 标记数据类型为视频
							foreach ($videos as &$vid) {
								$vid['type'] = 'video';
								$vid['id'] = 'V' . time() . rand(10000000, 99999999);
							}
						}
						
						$pagecontent[$k]['mixed_data']['videos'] = $videos;
					}
					
					// 记录日志
					\think\facade\Log::write("2024-07-23 15:30:00-INFO-[System.php][initpagecontent_004] 混合模式数据处理完成", 'info');
				}
	            }elseif($v['temp'] == 'restaurant_product'){//菜品列表 获取菜品信息
	                if($v['params']['productfrom'] == 0){//手动选择
	                    $newdata = array();
	                    foreach($v['data'] as $pro){
	                    	// 新版选择器保存 id，旧版保存 proid；同时兼容历史 G 开头的展示 ID。
	                    	$lookupProid = $pro['proid'] ?? ($pro['product_id'] ?? '');
	                    	if(!$lookupProid && isset($pro['id']) && !(is_string($pro['id']) && preg_match('/^G\d+$/', $pro['id']))) {
	                    		$lookupProid = $pro['id'];
	                    	}
	                        $newpro = Db::name('restaurant_product')->field('id proid,name,pic,market_price,sell_price,lvprice,lvprice_data,sales')->where('aid',$aid)->where('id',$lookupProid)->where('status',1)->where('ischecked',1)->find();
	                        if($newpro){
	                            $newpro['id'] = $pro['id'] ?? ('G'.time().rand(10000000,99999999));
                            if($newpro['lvprice']==1 && $member){
                                $lvprice_data = json_decode($newpro['lvprice_data'],true);
                                if($lvprice_data && isset($lvprice_data[$member['levelid']])){
                                    $newpro['sell_price'] = $lvprice_data[$member['levelid']];
                                }
                            }
                            $newdata[] = $newpro;
                        }
                    }
                    $pagecontent[$k]['data'] = $newdata;
                }else{
                    $where = [];
                    $where[] = ['aid','=',$aid];
                    $where[] = ['ischecked','=',1];
                    //$where[] = ['status','=',1];
                    $nowtime = time();
                    $nowhm = date('H:i');
                    $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

                    if($v['params']['category']){
                        $cid = intval($v['params']['category']);
                        $where[] = Db::raw("find_in_set({$cid},cid)");
                    }
                    $where2 = '1=1';
                    if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        $where[] = ['bid','=',$v['params']['bid']];
                    }

                    $order = 'sort desc';
                    if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
                    if($v['params']['sortby'] == 'createtimedesc') $order = 'create_time desc';
                    if($v['params']['sortby'] == 'createtime') $order = 'create_time';
                    if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
                    if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
                    $result = Db::name('restaurant_product')->field('id proid,name,pic,sell_price,lvprice,lvprice_data,market_price,sales')->where($where)->where($where2)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
                    if(!$result) $result = array();
                    foreach($result as $k2=>$v2){
                        $result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                        if($v2['lvprice']==1 && $member){
                            $lvprice_data = json_decode($v2['lvprice_data'],true);
                            if($lvprice_data && isset($lvprice_data[$member['levelid']])){
                                $result[$k2]['sell_price'] = $lvprice_data[$member['levelid']];
                            }
                        }
                    }
                    $pagecontent[$k]['data'] = $result;
                }
			}elseif($v['temp'] == 'scoreshop'){//产品列表 获取产品信息
				if(!self::tableExists('scoreshop_product')){
					$pagecontent[$k]['data'] = [];
				}elseif($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,score_price,money_price,sales,lvprice,lvprice_data';
				        $newpro = Db::name('scoreshop_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							if($newpro['lvprice']==1 && $member){
								$lvprice_data = json_decode($newpro['lvprice_data'],true);
								if($lvprice_data && isset($lvprice_data[$member['levelid']])){
									if(isset($lvprice_data[$member['levelid']]['money_price']))
									$newpro['money_price'] = $lvprice_data[$member['levelid']]['money_price'];
									if(isset($lvprice_data[$member['levelid']]['score_price']))
									$newpro['score_price'] = $lvprice_data[$member['levelid']]['score_price'];
								}
							}
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$chidlc = [];
						if(self::tableExists('scoreshop_category')){
							$chidlc = Db::name('scoreshop_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
						}
						if($chidlc){
							$cids = array($cid);
							foreach($chidlc as $c){
								$cids[] = intval($c['id']);
							}
							$where[] = ['cid','in',$cids];
						}else{
							$where[] = ['cid','=',$cid];
						}
					}
					if($v['params']['group']){
						$_string = array();
						foreach($v['params']['group'] as $gid=>$istrue){
							$gid = strval($gid);
							if($istrue=='true'){
								if($gid == 'all'){
									$_string[] = "1=1";
								}elseif($gid == '0'){
									$_string[] = "gid is null or gid=''";
								}else{
									$_string[] = "find_in_set({$gid},gid)";
								}
							}
						}
						if(!$_string){
							$where2 = '0=1';
						}else{
							$where2 = implode(" or ",$_string);
						}
					}else{
						$where2 = '1=1';
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,score_price,money_price,sales,lvprice,lvprice_data';
			        $result = Db::name('scoreshop_product')->field($field)->where($where)->where($where2)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);

						if($v2['lvprice']==1 && $member){
							$lvprice_data = json_decode($v2['lvprice_data'],true);
							if($lvprice_data && isset($lvprice_data[$member['levelid']])){
								if(isset($lvprice_data[$member['levelid']]['money_price']))
								$result[$k2]['money_price'] = $lvprice_data[$member['levelid']]['money_price'];
								if(isset($lvprice_data[$member['levelid']]['score_price']))
								$result[$k2]['score_price'] = $lvprice_data[$member['levelid']]['score_price'];
							}
						}
						}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'cycle'){//产品列表 获取产品信息
                if($v['params']['productfrom'] == 0){//手动选择
                    $newdata = array();
                    foreach($v['data'] as $pro){
                        $field = 'id proid,name,pic,market_price,sell_price,sales,ps_cycle';

                        $newpro = Db::name('cycle_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
                        if($newpro){
                            $newpro['id'] = $pro['id'];
                            $newdata[] = $newpro;
                        }
                    }
                    $pagecontent[$k]['data'] = $newdata;
                }else{
                    $where = [];
                    $where[] = ['aid','=',$aid];
                    $where[] = ['status','=',1];
                    $where[] = ['ischecked','=',1];
                    if($v['params']['category']){
                        $cid = intval($v['params']['category']);
                        $chidlc = Db::name('cycle_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
                        if($chidlc){
                            $cids = array($cid);
                            foreach($chidlc as $c){
                                $cids[] = intval($c['id']);
                            }
                            $where[] = ['cid','in',$cids];
                        }else{
                            $where[] = ['cid','=',$cid];
                        }
                    }
                    if($v['params']['bid']!=='' && $v['params']['bid']!==null){
                        $where[] = ['bid','=',$v['params']['bid']];
                    }
                    if($v['params']['group']){
                        $_string = array();
                        foreach($v['params']['group'] as $gid=>$istrue){
                            $gid = strval($gid);
                            if($istrue=='true'){
                                if($gid == 'all'){
                                    $_string[] = "1=1";
                                }elseif($gid == '0'){
                                    $_string[] = "gid is null or gid=''";
                                }else{
                                    $_string[] = "find_in_set({$gid},gid)";
                                }
                            }
                        }
                        if(!$_string){
                            $where2 = '0=1';
                        }else{
                            $where2 = implode(" or ",$_string);
                        }
                    }else{
                        $where2 = '1=1';
                    }
                    $order = 'sort desc';
                    if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
                    if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
                    if($v['params']['sortby'] == 'createtime') $order = 'createtime';
                    if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
                    if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
                    $field = 'id proid,name,pic,sell_price,market_price,sales,ps_cycle';

                    $result = Db::name('cycle_product')->field($field)->where($where)->where($where2)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
                    if(!$result) $result = array();

                    foreach($result as $k2=>$v2){
                        $result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                        $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
                        $result[$k2]['pspl'] = $ps_cycle[$v2['ps_cycle']];
                    }
                    $pagecontent[$k]['data'] = $result;
                }
            }elseif($v['temp'] == 'collage'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,market_price,sell_price,sales,stock,teamnum';
						$newpro = Db::name('collage_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$chidlc = Db::name('collage_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
						if($chidlc){
							$cids = array($cid);
							foreach($chidlc as $c){
								$cids[] = intval($c['id']);
							}
							$where[] = ['cid','in',$cids];
						}else{
							$where[] = ['cid','=',$cid];
						}
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
					if($v['params']['group']){
						$_string = array();
						foreach($v['params']['group'] as $gid=>$istrue){
							$gid = strval($gid);
							if($istrue=='true'){
								if($gid == 'all'){
									$_string[] = "1=1";
								}elseif($gid == '0'){
									$_string[] = "gid is null or gid=''";
								}else{
									$_string[] = "find_in_set({$gid},gid)";
								}
							}
						}
						if(!$_string){
							$where2 = '0=1';
						}else{
							$where2 = implode(" or ",$_string);
						}
					}else{
						$where2 = '1=1';
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,market_price,sales,stock,teamnum';
					$result = Db::name('collage_product')->field($field)->where($where)->where($where2)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'kanjia'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,min_price,sales';
						$newpro = Db::name('kanjia_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
					if($v['params']['category']){

					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,min_price,sales';
					$result = Db::name('kanjia_product')->field($field)->where($where)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'seckill'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,market_price,sales,stock,seckill_date,seckill_time,starttime';
						$newpro = Db::name('seckill_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
                    $set = Db::name('seckill_sysset')->where('aid',aid)->find();
                    $duration = $set['duration'];
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
					if($v['params']['category']){

					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
                    if($v['shopstyle'] == 2){
                        //风格2只显示当前这一场的秒杀
                        $timeset = explode(',',$set['timeset']);
                        $hour = date('H');
                        $current = '';
                        foreach ($timeset as $k2 => $item){
                            if(($hour >= $item && $hour < $timeset[$k2]) || ($hour >= $item && $k2 == (count($timeset)-1))){
                                $current = $item;break;
                            }
                        }
                        $where[] = ['seckill_date','=',date('Y-m-d')];
                        $where[] = ['seckill_time','=',$current];
                        //距离结束时间
                    }
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,market_price,sales,stock,seckill_date,seckill_time,starttime';
					$result = Db::name('seckill_product')->field($field)->where($where)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
					if(!$result) $result = array();
					else {
                        $nowtime = time();
                        foreach($result as $k2=>$v2){
                            $result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                            //倒计时
                            $seckill_endtime = $v2['starttime'] + $duration * 3600;
                            if($seckill_endtime < $nowtime) {//已结束
                                $result[$k2]['seckill_status'] = 2;
                                $result[$k2]['hour'] = 0;
                                $result[$k2]['minute'] = 0;
                                $result[$k2]['second'] = 0;
                            }else{
                                if($v2['starttime'] > $nowtime){ //未开始
                                    $result[$k2]['seckill_status'] = 0;
                                    $lefttime = $v2['starttime'] - $nowtime;
                                    $result[$k2]['hour'] = floor($lefttime / 3600);
                                    $result[$k2]['minute'] = floor(($lefttime - $result[$k2]['hour'] * 3600) / 60);
                                    $result[$k2]['second'] = $lefttime - ($result[$k2]['hour'] * 3600) - ($result[$k2]['minute'] * 60);
                                    //带天数
                                    $result[$k2]['day'] = floor($lefttime / 86400);
                                    $result[$k2]['day_hour'] = floor(($lefttime - $result[$k2]['day'] * 86400) / 3600);
                                }else{ //进行中
                                    $result[$k2]['seckill_status'] = 1;
                                    $lefttime = $seckill_endtime - $nowtime;
                                    $result[$k2]['hour'] = floor($lefttime / 3600);
                                    $result[$k2]['minute'] = floor(($lefttime - $result[$k2]['hour'] * 3600) / 60);
                                    $result[$k2]['second'] = $lefttime - ($result[$k2]['hour'] * 3600) - ($result[$k2]['minute'] * 60);//带天数
                                    $result[$k2]['day'] = floor($lefttime / 86400);
                                    $result[$k2]['day_hour'] = floor(($lefttime - $result[$k2]['day'] * 86400) / 3600);
                                }
                            }
                            }
                        }

					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'tuangou'){//产品列表 团购商品
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,sales,pricedata';
						$newpro = Db::name('tuangou_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$buynum = $newpro['sales'];
							$pricedata = json_decode($newpro['pricedata'],true);
							$nowpricedata = array('num'=>0,'money'=>$newpro['sell_price']);
							foreach($pricedata as $k3=>$v3){
								if($buynum >= $v3['num']){
									$nowpricedata = $v3;
								}
							}
							$newpro['sell_price'] = $nowpricedata['money'];
							$minpricedata = end($pricedata);
							$min_price = $minpricedata['money'];
							$newpro['min_price'] = $min_price;

							$newdata[] = $newpro;
						}
					}
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$chidlc = Db::name('tuangou_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
						if($chidlc){
							$cids = array($cid);
							foreach($chidlc as $c){
								$cids[] = intval($c['id']);
							}
							$where[] = ['cid','in',$cids];
						}else{
							$where[] = ['cid','=',$cid];
						}
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}

					$where3 = "find_in_set('-1',showtj)";
					if($member){
						$where3.= " or find_in_set('".$member['levelid']."',showtj)";
						if($member['subscribe']==1){
							$where3 .= " or find_in_set('0',showtj)";
						}
					}

					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,sales,pricedata';
					$newdata = Db::name('tuangou_product')->field($field)->where($where)->where($where3)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
					if(!$newdata) $newdata = array();
					foreach($newdata as $k2=>$v2){
						$v2['id'] = 'G'.time().rand(10000000,99999999);
						$buynum = $v2['sales'];
						$pricedata = json_decode($v2['pricedata'],true);
						$nowpricedata = array('num'=>0,'money'=>$v2['sell_price']);
						foreach($pricedata as $k3=>$v3){
							if($buynum >= $v3['num']){
								$nowpricedata = $v3;
							}
						}
						$v2['sell_price'] = $nowpricedata['money'];
						$minpricedata = end($pricedata);
						$min_price = $minpricedata['money'];
						$v2['min_price'] = $min_price;
						$newdata[$k2] = $v2;
						}
					}
				$pagecontent[$k]['data'] = $newdata;
				}elseif($v['temp'] == 'kecheng'){//产品列表 获取产品信息
					if($v['params']['productfrom'] == 0){//手动选择
						$newdata = array();
					foreach($v['data'] as $pro){
						$newpro = Db::name('kecheng_list')->field('id proid,name,pic,market_price,price,lvprice,lvprice_data,join_num,kctype')->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$basePrice = isset($newpro['price']) && is_numeric($newpro['price']) ? round((float)$newpro['price'], 2) : 0;
							$newpro = self::appendKechengMemberPriceMeta($newpro, $member, $aid);
							if($newpro['member_price_current_match'] == 1){
								if(intval($newpro['lvprice']) === 1){
									$originPrice = (isset($newpro['market_price']) && $newpro['market_price'] > 0) ? $newpro['market_price'] : $basePrice;
									if((float)$originPrice > (float)$newpro['member_price_value']){
										$newpro['market_price'] = $originPrice;
									}
								}else{
									$newpro['market_price'] = $basePrice;
								}
								$newpro['price'] = $newpro['member_price_value'];
							}
							$newpro['count'] = Db::name('kecheng_chapter')->where('kcid',$pro['proid'])->where('status',1)->count();
							$newdata[] = $newpro;
						}
					}
						$pagecontent[$k]['data'] = $newdata;
					}else{
						$where = [];
						$where[] = ['aid','=',$aid];
						$where[] = ['status','=',1];
						if($v['params']['category']){
							$cid = intval($v['params']['category']);
							$chidlc = Db::name('kecheng_category')->where('aid',$aid)->where('pid',$cid)->column('id');
							if($chidlc){
								$chidlc = array_merge($chidlc, [$cid]);
								$whereCid = '(';
								foreach($chidlc as $k2 => $c){
									if(count($chidlc) == ($k2 + 1)){
										$whereCid .= "find_in_set({$c},cid)";
									}else{
										$whereCid .= " find_in_set({$c},cid) or ";
									}
								}
								$where[] = Db::raw($whereCid . ')');
							}else{
								$where[] = Db::raw("find_in_set({$cid},cid)");
							}
						}
						if($v['params']['bid']!=='' && $v['params']['bid']!==null){
							$where[] = ['bid','=',$v['params']['bid']];
						}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'join_num desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$result = Db::name('kecheng_list')->field('id proid,name,pic,price,lvprice,lvprice_data,market_price,join_num,sort,kctype')->where($where)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						$basePrice = isset($v2['price']) && is_numeric($v2['price']) ? round((float)$v2['price'], 2) : 0;
						$result[$k2] = self::appendKechengMemberPriceMeta($v2, $member, $aid);
						if($result[$k2]['member_price_current_match'] == 1){
							if(intval($result[$k2]['lvprice']) === 1){
								$originPrice = (isset($result[$k2]['market_price']) && $result[$k2]['market_price'] > 0) ? $result[$k2]['market_price'] : $basePrice;
								if((float)$originPrice > (float)$result[$k2]['member_price_value']){
									$result[$k2]['market_price'] = $originPrice;
								}
							}else{
								$result[$k2]['market_price'] = $basePrice;
							}
							$result[$k2]['price'] = $result[$k2]['member_price_value'];
						}
						$result[$k2]['count'] = Db::name('kecheng_chapter')->where('kcid',$v2['proid'])->where('status',1)->count();
					}
					$pagecontent[$k]['data'] = $result;
				}
            }elseif($v['temp'] == 'luckycollage'){//产品列表 获取产品信息
				if($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$field = 'id proid,name,pic,sell_price,market_price,sales,teamnum,gua_num,fy_money,fy_money_val,fy_type';
						$newpro = Db::name('lucky_collage_product')->field($field)->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
						if($newpro){
							$newpro['money'] = round($newpro['fy_money_val'],2);
							if($newpro['fy_type']==1){
								$newpro['money'] = round($newpro['fy_money']*$newpro['sell_price']/100,2);
							}

							$newpro['linktype'] = 0;
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}

					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$chidlc = Db::name('lucky_collage_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
						if($chidlc){
							$cids = array($cid);
							foreach($chidlc as $c){
								$cids[] = intval($c['id']);
							}
							$where[] = ['cid','in',$cids];
						}else{
							$where[] = ['cid','=',$cid];
						}
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id proid,name,pic,sell_price,market_price,sales,teamnum,gua_num,fy_money,fy_money_val,fy_type';
					$result = Db::name('lucky_collage_product')->field($field)->where($where)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						$result[$k2]['money'] = round($v2['fy_money_val'],2);
						if($v2['fy_type']==1){
							$result[$k2]['money'] = round($v2['fy_money']*$v2['sell_price']/100,2);
						}
						$result[$k2]['linktype'] = 0;
						}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'yuyue'){//预约服务 获取产品信息
				if(!self::isYuyuePluginReady($aid)){
					$pagecontent[$k]['data'] = [];
				}elseif($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$newpro = Db::name('yuyue_product')->field('id proid,name,pic,sell_price,sales,danwei')->where('aid',$aid)->where('id',$pro['proid'])->where('status',1)->where('ischecked',1)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newdata[] = $newpro;
						}
					}
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					//$where[] = ['status','=',1];
					$where[] = ['ischecked','=',1];
					$nowtime = time();
					$nowhm = date('H:i');
					$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)");
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$chidlc = Db::name('yuyue_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
						if($chidlc){
							$cids = array($cid);
							foreach($chidlc as $c){
								$cids[] = intval($c['id']);
							}
							$where[] = ['cid','in',$cids];
						}else{
							$where[] = ['cid','=',$cid];
						}
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$newdata = Db::name('yuyue_product')->field('id proid,name,pic,sell_price,sales,danwei')->where($where)->order($order)->limit($v['params']['proshownum'])->select()->toArray();
					if(!$newdata) $newdata = array();
                    foreach($newdata as $k2=>$v2){
                        $newdata[$k2]['id'] = 'G'.time().rand(10000000,99999999);
                    }
                    $pagecontent[$k]['data'] = $result;
			}
			$pagecontent[$k]['data'] = $newdata;
		}elseif($v['temp'] == 'countdown'){//秒杀倒计时
			// 倒计时组件不需要从数据库获取数据，只需要传递配置参数
			// 前端会根据endtime参数实时计算倒计时
			$pagecontent[$k]['endtime'] = isset($v['params']['endtime']) ? $v['params']['endtime'] : '';
			$pagecontent[$k]['data'] = [];
		}elseif($v['temp'] == 'paidui'){//排队免单数据展示
			// 获取当前用户的排队汇总信息
			$paidui_data = [];
			if($mid > 0 && self::tableExists('paidui_list')){
				try {
					$summary = Db::name('paidui_list')
						->where('aid', $aid)
						->where('mid', $mid)
						->field('
							COUNT(*) as total_records,
							SUM(paidui_jiang) as total_amount,
							SUM(shiji_dedao) as received_amount,
							SUM(CASE WHEN status = 2 THEN shiji_dedao ELSE 0 END) as completed_amount,
							SUM(CASE WHEN status IN (0,1) THEN (paidui_jiang - shiji_dedao) ELSE 0 END) as waiting_amount
						')
						->find();
					
					if($summary){
						$paidui_data = [
							'help_count' => intval($summary['total_records'] ?? 0),
							'total_amount' => floatval($summary['total_amount'] ?? 0),
							'received_amount' => floatval($summary['completed_amount'] ?? 0),
							'waiting_amount' => floatval($summary['waiting_amount'] ?? 0)
						];
					}
				} catch (\Exception $e) {
					// 如果查询失败，返回默认值
					$paidui_data = [
						'help_count' => 0,
						'total_amount' => 0,
						'received_amount' => 0,
						'waiting_amount' => 0
					];
				}
			}else{
				// 未登录用户显示默认值
				$paidui_data = [
					'help_count' => 0,
					'total_amount' => 0,
					'received_amount' => 0,
					'waiting_amount' => 0
				];
			}
			$pagecontent[$k]['data'] = $paidui_data;
		}elseif($v['temp'] == 'worker'){//师傅推荐 获取师傅信息
			$newdata = array();
			if(self::isYuyuePluginReady($aid)){
				if($v['params']['workerfrom'] == 0){//手动选择
					foreach($v['data'] as $worker){
				$newworker = Db::name('yuyue_worker')
					->field('id workerid,realname,tel,headimg,age,sex,`desc`,fwcids,worker_skills,mid')
					->where('aid',$aid)
					->where('id',$worker['workerid'])
					->where('status',1)
					->where('shstatus',1)
					->find();
						if($newworker){
							$newworker['id'] = $worker['id'];
							// 处理技能标签
							if($newworker['worker_skills']){
								$newworker['skills_array'] = explode(',', $newworker['worker_skills']);
							}else{
								$newworker['skills_array'] = [];
							}
							// 获取关联的会员信息
							$member = Db::name('member')->where('id',$newworker['mid'])->find();
							if($member){
								if(empty($newworker['headimg'])) $newworker['headimg'] = $member['headimg'];
							}
							$newdata[] = $newworker;
						}
					}
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['shstatus','=',1]; // 审核通过的师傅
					
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$where[] = Db::raw("find_in_set({$cid},fwcids)");
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					
				$newdata = Db::name('yuyue_worker')
					->field('id workerid,realname,tel,headimg,age,sex,`desc`,fwcids,worker_skills,mid')
					->where($where)
					->order($order)
					->limit($v['params']['workershownum'] ? $v['params']['workershownum'] : 6)
					->select()
					->toArray();
					
				if(!$newdata) $newdata = array();
				foreach($newdata as $k2=>$v2){
					$newdata[$k2]['id'] = 'G'.time().rand(10000000,99999999);
					// 处理技能标签
					if($v2['worker_skills']){
						$newdata[$k2]['skills_array'] = explode(',', $v2['worker_skills']);
					}else{
						$newdata[$k2]['skills_array'] = [];
					}
					// 获取关联的会员信息
					if(!empty($v2['mid'])){
						$member = Db::name('member')->where('id',$v2['mid'])->find();
						if($member){
							if(empty($v2['headimg'])) $newdata[$k2]['headimg'] = $member['headimg'];
						}
					}
				}
				}
			}
			if(in_array(strval($v['params']['style'] ?? ''), ['dazi', 'bigimg'], true) || !$newdata){
				$filled = self::buildDaziWorkerList($aid, $v, $newdata);
				if($filled){
					$newdata = $filled;
				}
			}
			$pagecontent[$k]['data'] = $newdata;
			}elseif($v['temp'] == 'daihuobiji'){ // 短视频
    $newdata = array();
    $midList = []; // 用于存储所有member的id

	    if($v['params']['productfrom'] == 0){ // 手动选择
	        foreach($v['data'] as $art){
	            $noteId = isset($art['videoId']) ? intval($art['videoId']) : 0;
	            if($noteId <= 0 && isset($art['bijiId'])){
	                $noteId = intval($art['bijiId']);
	            }
	            if($noteId <= 0){
	                continue;
	            }
	            $newart = Db::name('daihuobiji')
	                ->field('id daihuobijiId, mid, title, content, coverimg, createtime, readcount, zan, bid')
	                ->where('aid', $aid)
	                ->where('id', $noteId)
	                ->where('status', 1)
	                ->find();
	            if($newart){
	                $newart['id'] = $art['id'] ?? ('G' . time() . rand(10000000,99999999));
	                $newdata[] = $newart;
	                $midList[] = $newart['mid']; // 收集member的id
	            }
	        }
	    }
    else{
        $where = [];
        $where[] = ['aid', '=', $aid];
        $where[] = ['status', '=', 1];
        if($v['params']['category']){
            $cid = intval($v['params']['category']);
            $where[] = ['cid', '=', $cid];
        }
        if($v['params']['bid'] !== '' && $v['params']['bid'] !== null){
            $where[] = ['bid', '=', $v['params']['bid']];
        }
        $order = 'sort desc';
        if($v['params']['sortby'] == 'sort') $order = 'sort desc, id desc';
        if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
        if($v['params']['sortby'] == 'createtime') $order = 'createtime';
        if($v['params']['sortby'] == 'viewnum') $order = 'view_num desc, sort desc';
        if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
        
	        $shownum = intval($v['params']['shownum'] ?? 0);
	        if($shownum <= 0){
	            $shownum = 6;
	        }
	        
	        $newdata = Db::name('daihuobiji')
	            ->field('id daihuobijiId, mid, title, content, coverimg, createtime, readcount, zan, bid')
	            ->where($where)
	            ->order($order)
	            ->limit($shownum)
	            ->select()
	            ->toArray();
        
        if(!$newdata) $newdata = array();
        
        foreach($newdata as $k2 => $v2){
            $newdata[$k2]['id'] = 'G' . time() . rand(10000000,99999999);
            $midList[] = $v2['mid']; // 收集member的id
        }
    }

    // 去重member的id
    $midList = array_unique($midList);

    // 批量查询member表获取最新的nickname和headimg
    $memberMap = [];
    if(!empty($midList)){
        $members = Db::name('member')
            ->field('id, nickname, headimg') // 使用id代替mid
            ->whereIn('id', $midList) // 查询条件改为id
            ->select()
            ->toArray();
        
        // 构建id到member信息的映射
        foreach($members as $member){
            $memberMap[$member['id']] = [
                'nickname' => $member['nickname'],
                'headimg' => $member['headimg'],
            ];
        }
    }

    // 替换newdata中的nickname和headimg
    foreach($newdata as $k2 => $v2){
        if(isset($memberMap[$v2['mid']])){ // 使用memberMap中的id
            $newdata[$k2]['nickname'] = $memberMap[$v2['mid']]['nickname'];
            $newdata[$k2]['headimg'] = $memberMap[$v2['mid']]['headimg'];
        }

        // 处理logo
        if($v2['bid'] != 0){
            $newdata[$k2]['logo'] = Db::name('business')
                ->where('aid', $aid)
                ->where('id', $v2['bid'])
                ->value('logo');
        } else {
            $newdata[$k2]['logo'] = Db::name('admin_set')
                ->where('aid', $aid)
                ->value('logo');
        }
    }

    $pagecontent[$k]['data'] = $newdata;
}

			elseif($v['temp'] == 'shortvideo'){//短视频
				if(!self::tableExists('shortvideo') || !self::tableExists('shortvideo_category')){
					$newdata = [];
				}elseif($v['params']['productfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $art){
						$videoId = intval($art['videoId'] ?? 0);
						if($videoId <= 0 && isset($art['id']) && is_numeric($art['id'])){
							$videoId = intval($art['id']);
						}
						if($videoId <= 0) continue;
						$newart = Db::name('shortvideo')->field('id videoId,bid,name,description,coverimg,view_num,zan_num,createtime')->where('aid',$aid)->where('id',$videoId)->where('status',1)->find();
						if($newart){
							$newart['id'] = $art['id'];
							$newart['videoId'] = $videoId;
							$newdata[] = $newart;
						}
					}
				}
				else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$where[] = ['cid','=',$cid];
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'viewnum') $order = 'view_num desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$newdata = Db::name('shortvideo')->field('id videoId,name,description,coverimg,view_num,zan_num,createtime')->where($where)->order($order)->limit($v['params']['shownum'])->select()->toArray();
					if(!$newdata) $newdata = array();
					foreach($newdata as $k2=>$v2){
						$newdata[$k2]['id'] = 'G'.time().rand(10000000,99999999);
					}
				}
				foreach($newdata as $k2=>$v){
					if($v['bid']!=0){
						$newdata[$k2]['logo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('logo');
					} else {
						$newdata[$k2]['logo'] = Db::name('admin_set')->where('aid',aid)->value('logo');
					}
				}
				$pagecontent[$k]['data'] = $newdata;
			}
			//带货团购列表
			elseif($v['temp'] == 'daihuoyiuan'){
				//手动选择
				if($v['params']['productfrom'] == 0){
					$newdata = array();
					foreach($v['data'] as $pro){
						$newart = Db::name('daihuoyiuan')->field('id as proid,aid,author,name,pic,pic2,readcount,status,createtime,productids')->where('aid',$aid)->where('id',$pro['id'])->where('status',1)->find();
						$newpro['id'] = $pro['proid'];

						$newart['createtime'] = date('Y-m-d',$newart['createtime']);
						// 保留 'pic2' 为原始的逗号分隔的字符串形式，并限制最多三张图片
						if(isset($newart['pic2']) && $newart['pic2']){
						    $pic2_arr = explode(',', $newart['pic2']);
						    $newart['pic2'] = implode(',', array_slice($pic2_arr, 0, 3));
						} else {
						    $newart['pic2'] = '';
						}

						// 获取当前笔记的产品ID列表
						$productIds = explode(',', $newart['productids']);
						$products = Db::name('shop_product')->whereIn('id', $productIds)->field('sell_price')->select()->toArray();

						// 计算该笔记的价格范围
						$prices = array_column($products, 'sell_price');
						if(!empty($prices)){
						    $minPrice = min($prices);
						    $maxPrice = max($prices);
						    $newart['priceRange'] = ($minPrice == $maxPrice) ? $minPrice : "{$minPrice}~{$maxPrice}";
						} else {
						    $newart['priceRange'] = '';
						}

						$newdata[] = $newart;
					}
				}else{
					$where = [
						['aid','=', $aid],
						['status','=', 1],
						['bid', '=', !empty($v['params']['bid'])? $v['params']['bid'] : 0]
					];
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					// if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$result = Db::name('daihuoyiuan')->field('id as proid,aid,author,name,pic,pic2,readcount,status,createtime,productids')->where($where)->order($order)->limit($v['params']['shownum'])->select()->toArray();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);

					    $result[$k2]['createtime'] = date('Y-m-d',$v['createtime']);
					    
					    // 保留 'pic2' 为原始的逗号分隔的字符串形式，并限制最多三张图片
					    if(isset($v2['pic2']) && $v2['pic2']){
					        $pic2_arr = explode(',', $v2['pic2']);
					        // $result[$k2]['pic2'] = implode(',', array_slice($pic2_arr, 0, 3));
					        $result[$k2]['pic2'] = explode(',', implode(',', array_slice($pic2_arr, 0, 3)));
					    } else {
					        $result[$k2]['pic2'] = '';
					    }

					    // 获取当前笔记的产品ID列表
					    $productIds = explode(',', $v2['productids']);
					    $products = Db::name('shop_product')
					                  ->whereIn('id', $productIds)
					                  ->field('sell_price')
					                  ->select()
					                  ->toArray();

					    // 计算该笔记的价格范围
					    $prices = array_column($products, 'sell_price');
					    if(!empty($prices)){
					        $minPrice = min($prices);
					        $maxPrice = max($prices);
					        $result[$k2]['priceRange'] = ($minPrice == $maxPrice) ? $minPrice : "{$minPrice}~{$maxPrice}";
					    } else {
					        $result[$k2]['priceRange'] = '';
					    }
					}

					$pagecontent[$k]['data'] = $result;
				}
			}
			elseif($v['temp'] == 'liveroom'){//直播列表
				if($v['params']['liveroomfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$newpro = Db::name('live_room')->field('id,bid,roomId,name,coverImg,shareImg,startTime,endTime,anchorName')->where('aid',$aid)->where('roomId',$pro['roomId'])->where('status',1)->find();
						if($newpro){
							$newpro['id'] = $pro['id'];
							$newpro['commentscore'] = floor($newpro['comment_score']);
							$newpro['startTime'] = date('m-d H:i',$newpro['startTime']);
							$newpro['endTime'] = date('m-d H:i',$newpro['endTime']);
							$newpro['status'] = 1;
							if($newpro['startTime'] > time()){ //未开始
								$newpro['status'] = 0;
								if(date('Y-m-d') == date('Y-m-d',$newpro['startTime'])){
									$newpro['showtime'] = '今天'.date('H:i',$newpro['startTime']).'开播';
								}elseif(date('Y-m-d',time()+86400) == date('Y-m-d',$newpro['startTime'])){
									$newpro['showtime'] = '明天'.date('H:i',$newpro['startTime']).'开播';
								}elseif(date('Y-m-d',time()+86400*2) == date('Y-m-d',$newpro['startTime'])){
									$newpro['showtime'] = '后天'.date('H:i',$newpro['startTime']).'开播';
								}else{
									$newpro['showtime'] = date('m-d H:i',$newpro['startTime']).'开播';
								}
							}
							if($newpro['endTime'] < time()){ //已结束
								$newpro['status'] = 2;
							}

							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$where[] = ['cid','=',$cid];
					}
					$order = 'roomId desc';
					if($v['params']['sortby'] == 'sort') $order = 'roomId desc';
					if($v['params']['sortby'] == 'starttimedesc') $order = 'startTime desc';
					if($v['params']['sortby'] == 'starttime') $order = 'startTime';
					if($v['params']['sortby'] == 'endtimedesc') $order = 'endTime desc';
					if($v['params']['sortby'] == 'endtime') $order = 'endTime';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$result = Db::name('live_room')->field('id,bid,roomId,name,coverImg,shareImg,startTime,endTime,anchorName')->where($where)->order($order)->limit($v['params']['shownum'])->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						$result[$k2]['startTime'] = date('m-d H:i',$v2['startTime']);
						$result[$k2]['endTime'] = date('m-d H:i',$v2['endTime']);
						$result[$k2]['status'] = 1;
						if($v2['startTime'] > time()){ //未开始
							$result[$k2]['status'] = 0;
							if(date('Y-m-d') == date('Y-m-d',$v2['startTime'])){
								$result[$k2]['showtime'] = '今天'.date('H:i',$v2['startTime']).'开播';
							}elseif(date('Y-m-d',time()+86400) == date('Y-m-d',$v2['startTime'])){
								$result[$k2]['showtime'] = '明天'.date('H:i',$v2['startTime']).'开播';
							}elseif(date('Y-m-d',time()+86400*2) == date('Y-m-d',$v2['startTime'])){
								$result[$k2]['showtime'] = '后天'.date('H:i',$v2['startTime']).'开播';
							}else{
								$result[$k2]['showtime'] = date('m-d H:i',$v2['startTime']).'开播';
							}
						}
						if($v2['endTime'] < time()){ //已结束
							$result[$k2]['status'] = 2;
						}
					}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'business'){//商家列表
				// 获取排队免单设置状态
					$paidui_set = self::tableExists('paidui_set') ? Db::name('paidui_set')->where('aid', $aid)->find() : null;
				$paidui_status = !empty($paidui_set) && $paidui_set['status'] == 1 ? 1 : 0;
				
					if($v['params']['businessfrom'] == 0){//手动选择
						$manualItems = is_array($v['data'] ?? null) ? $v['data'] : [];
						$businessIds = [];
						$productIds = [];
						foreach($manualItems as $manualItem){
							$businessId = intval($manualItem['bid'] ?? $manualItem['business_id'] ?? 0);
							if($businessId <= 0 && isset($manualItem['id']) && is_numeric($manualItem['id'])){
								$businessId = intval($manualItem['id']);
							}
							if($businessId > 0) $businessIds[] = $businessId;
							foreach((array)($manualItem['prolist'] ?? []) as $manualProduct){
								$productId = intval($manualProduct['id'] ?? $manualProduct['proid'] ?? 0);
								if($productId > 0) $productIds[] = $productId;
							}
						}

						$businessIds = array_values(array_unique($businessIds));
						$businessRows = empty($businessIds) ? [] : Db::name('business')
							->field('id bid,name,logo,address,comment_score,sales,content,feepercent,is_open,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3,cid')
							->where('aid',$aid)
							->where('id','in',$businessIds)
							->where('status',1)
							->where('is_open',1)
							->select()
							->toArray();
						$businessMap = [];
						foreach($businessRows as $businessRow){
							$businessMap[intval($businessRow['bid'])] = $businessRow;
						}
						$salesMap = self::getBusinessSalesMap(intval($aid), $businessIds);

						$nowtime = time();
						$nowhm = date('H:i');
						$showProducts = intval($v['params']['showproduct'] ?? 0) === 1;
						$statuswhere = "`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )";
						$productRows = !$showProducts || empty($productIds) ? [] : Db::name('shop_product')
							->field('id,bid,name,pic,sell_price,price_type,sales,market_price')
							->where('aid',$aid)
							->where('id','in',array_values(array_unique($productIds)))
							->where('ischecked',1)
							->where($statuswhere)
							->select()
							->toArray();
						$productMap = [];
						foreach($productRows as $productRow){
							$productMap[intval($productRow['id'])] = $productRow;
						}

						$newdata = [];
						foreach($manualItems as $manualItem){
							$businessId = intval($manualItem['bid'] ?? $manualItem['business_id'] ?? 0);
							if($businessId <= 0 && isset($manualItem['id']) && is_numeric($manualItem['id'])){
								$businessId = intval($manualItem['id']);
							}
							if(empty($businessMap[$businessId])) continue;
							$newpro = $businessMap[$businessId];
							$newpro['id'] = $manualItem['id'] ?? ('G'.time().rand(10000000,99999999));
							$newpro['commentscore'] = floor($newpro['comment_score']);
							$newpro['sales_price'] = number_format($salesMap[$businessId] ?? 0, 2, '.', '');
							$newpro['content_text'] = trim(html_entity_decode(strip_tags(strval($newpro['content'] ?? '')), ENT_QUOTES, 'UTF-8'));
							$newpro['paidui_jiang'] = $paidui_status == 1 ? floatval($newpro['feepercent'] ?? 0) : 0;
							$newpro['is_open_now'] = 0;
							if($newpro['start_hours'] == $newpro['end_hours']){
								$newpro['is_open_now'] = 1;
							}else{
								foreach([['start_hours','end_hours'],['start_hours2','end_hours2'],['start_hours3','end_hours3']] as $hours){
									$startHour = strval($newpro[$hours[0]] ?? '');
									$endHour = strval($newpro[$hours[1]] ?? '');
									if($startHour === '' || $endHour === '' || $startHour === $endHour) continue;
									$startTime = strtotime(date('Y-m-d '.$startHour));
									$endTime = strtotime(date('Y-m-d '.$endHour));
									if(($startTime < $endTime && $startTime <= $nowtime && $endTime >= $nowtime) || ($startTime > $endTime && ($startTime <= $nowtime || $endTime >= $nowtime))){
										$newpro['is_open_now'] = 1;
										break;
									}
								}
							}
							$newpro['prolist'] = [];
							if($showProducts){
								foreach((array)($manualItem['prolist'] ?? []) as $manualProduct){
									$productId = intval($manualProduct['id'] ?? $manualProduct['proid'] ?? 0);
									if(isset($productMap[$productId]) && intval($productMap[$productId]['bid']) === $businessId){
										$product = $productMap[$productId];
										unset($product['bid']);
										$newpro['prolist'][] = $product;
									}
								}
							}
							$newdata[] = $newpro;
						}
						$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
                    $where_str = '';
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					$where[] = ['is_open','=',1];
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						//$where[] = ['cid','=',$cid];
                        //$where_str .= " and cid={$cid}";
						$where[] = Db::raw("find_in_set({$cid},cid)");
                        $where_str .= " and find_in_set({$cid},cid)";
					}
					
					
					//区域限制
        			$area_set = Db::name('admin_set')->where('aid',aid)->field('area_on,areamode')->find();
        			$area_id = input('param.area_id/d');
        			if($area_set && $area_set['area_on'] && $area_id && self::tableExists('admin_set_area_permission')){
        			    $permission = Db::name('admin_set_area_permission')->where(['a_area'=>md5(aid.$area_id)])->value('permission');
        			    //var_dump($permission);
        			    if($permission){
            			    $permission = json_decode($permission,true);
            			    if($area_set['areamode']==0)$limit_field = 'province_id';
            			    if($area_set['areamode']==1)$limit_field = 'city_id';
            			    if($area_set['areamode']==2)$limit_field = 'district_id';
            			    if($permission['client_visible_area']==0){
            			        //仅可见该区域
            			        $where[] = [$limit_field,'=',$area_id];
            			    }else if($permission['client_visible_area']==1){
            			        //可见部分区域
            			        $where[] = [$limit_field,'in',explode(',',$permission['client_visible_areas'])];
            			    }else if($permission['client_visible_area']==2){
            			        //不可见部分区域
            			        $where[] = [$limit_field,'notin',explode(',',$permission['client_unvisible_areas'])];
            			    } 
        			    }
        			}
					
					
					
					
					
					
					
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'sales') $order = 'sales desc,sort desc';
					if($v['params']['sortby'] == 'scoredesc') $order = 'comment_score desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');

					$filedraw = '';
					if($v['params']['sortby'] == 'juli' && $latitude && $longitude){
					    if($v['params']['distance']) {
					        //$filedraw .= ",round(( st_distance(point({$longitude}, {$latitude}),point(longitude, latitude)) / 0.0111 ) * 1000) AS distance";
							$filedraw .= ",round(6378.138*2*asin(sqrt(pow(sin( ({$latitude}*pi()/180-latitude*pi()/180)/2),2)+cos({$latitude}*pi()/180)*cos(latitude*pi()/180)* pow(sin( ({$longitude}*pi()/180-longitude*pi()/180)/2),2)))*1000) AS distance";
							//\think\facade\Log::write($filedraw);
                            $order = "distance asc";
                            $result = Db::query("select * from ((select id bid,name,logo,address,comment_score,sales,content,longitude,latitude,feepercent,is_open,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3,cid".$filedraw." from ".table_name('business').
								" where aid=:aid and status = 1 and is_open = 1 ".$where_str." order by $order ) as A) where distance <= :distance limit ".$v['params']['shownum'], ['aid' => $aid, 'distance' => $v['params']['distance'] * 1000]);
                        } else {
                            $order = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
                            $result = Db::name('business')->fieldRaw('id bid,name,logo,address,comment_score,sales,content,longitude,latitude,feepercent,is_open,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3,cid'.$filedraw)->where($where)->order($order)->limit($v['params']['shownum'])->select()->toArray();
                        }
					} else {
                        $result = Db::name('business')->fieldRaw('id bid,name,logo,address,comment_score,sales,content,longitude,latitude,feepercent,is_open,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3,cid'.$filedraw)->where($where)->order($order)->limit($v['params']['shownum'])->select()->toArray();
                    }
//
						if(!$result) $result = array();
						$nowtime = time();
						$nowhm = date('H:i');
						$salesMap = self::getBusinessSalesMap(intval($aid), array_column($result, 'bid'));
						$showProducts = intval($v['params']['showproduct'] ?? 0) === 1;
						foreach($result as $k2=>$v2){
							$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
							$result[$k2]['commentscore'] = floor($v2['comment_score']);
							$result[$k2]['sales_price'] = number_format($salesMap[intval($v2['bid'])] ?? 0, 2, '.', '');
							$result[$k2]['content_text'] = trim(html_entity_decode(strip_tags(strval($v2['content'] ?? '')), ENT_QUOTES, 'UTF-8'));
						if($longitude && $latitude){
							// 如果 SQL 查询返回了 distance 字段（单位是米），需要转换为公里
							if(isset($v2['distance']) && $v2['distance'] > 0){
								// distance 字段单位是米，转换为公里
								$result[$k2]['juli'] = round($v2['distance'] / 1000, 2).'km';
							} else {
								// 使用 getdistance 函数计算距离（len_type=1返回公里）
							$result[$k2]['juli'] = getdistance($longitude,$latitude,$v2['longitude'],$v2['latitude'],1).'km';
							}
						}else{
							$result[$k2]['juli'] = '';
						}
						// 获取商家的让利比例（使用商家抽佣比例）
						$result[$k2]['paidui_jiang'] = 0;
						if($paidui_status == 1){
							$result[$k2]['paidui_jiang'] = floatval($v2['feepercent'] ?? 0);
						}
						// 判断营业状态
						$result[$k2]['is_open_now'] = 0;
						if($v2['is_open'] == 1){
							$is_open = 0;
							// 判断第一个营业时间段
							if($v2['start_hours'] != $v2['end_hours']){
								$start_time = strtotime(date('Y-m-d '.$v2['start_hours']));
								$end_time = strtotime(date('Y-m-d '.$v2['end_hours']));
								if(($start_time < $end_time && ($start_time <= $nowtime && $end_time >= $nowtime)) || ($start_time > $end_time && ($start_time <= $nowtime || $end_time >= $nowtime))){
									$is_open = 1;
								}
							}else{
								$is_open = 1;
							}
							// 判断第二个营业时间段
							if($is_open == 0 && $v2['start_hours2'] && $v2['end_hours2']){
								$start_time = strtotime(date('Y-m-d '.$v2['start_hours2']));
								$end_time = strtotime(date('Y-m-d '.$v2['end_hours2']));
								if($start_time != $end_time && (($start_time < $end_time && ($start_time <= $nowtime && $end_time >= $nowtime)) || ($start_time > $end_time && ($start_time <= $nowtime || $end_time >= $nowtime)))){
									$is_open = 1;
								}
							}
							// 判断第三个营业时间段
							if($is_open == 0 && $v2['start_hours3'] && $v2['end_hours3']){
								$start_time = strtotime(date('Y-m-d '.$v2['start_hours3']));
								$end_time = strtotime(date('Y-m-d '.$v2['end_hours3']));
								if($start_time != $end_time && (($start_time < $end_time && ($start_time <= $nowtime && $end_time >= $nowtime)) || ($start_time > $end_time && ($start_time <= $nowtime || $end_time >= $nowtime)))){
									$is_open = 1;
								}
							}
							$result[$k2]['is_open_now'] = $is_open;
						}
							$prolist = [];
							if($showProducts){
								$statuswhere = "`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )";
								$prolist = Db::name('shop_product')->field('id,name,pic,sell_price,price_type,sales,market_price')->where('bid',$v2['bid'])->where('ischecked',1)->where($statuswhere)->limit(8)->order('sales desc,sort desc,id desc')->select()->toArray();
								if(!$prolist) $prolist = [];
								if(count($prolist) < 8 && self::isYuyuePluginReady($aid)){
									$prolist2 = Db::name('yuyue_product')->field('id,name,pic,sell_price,sales,danwei')->where('bid',$v2['bid'])->where('ischecked',1)->where("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime)")->limit(8-count($prolist))->order('sales desc,sort desc,id desc')->select()->toArray();
									foreach($prolist2 as $pro2){
										$pro2['module'] = 'yuyue';
										$prolist[] = $pro2;
									}
								}
							}
						$result[$k2]['prolist'] = $prolist;
					}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'jishou'){//寄售场次/商品
				$result = [];
				try{
					JishouPlugin::ensureRuntimeSchema();
					$params = is_array($v['params'] ?? null) ? $v['params'] : [];
					$contentType = (isset($params['content_type']) && $params['content_type'] === 'product') ? 'product' : 'changci';
					$sourceMode = (isset($params['source_mode']) && $params['source_mode'] === 'manual') ? 'manual' : 'auto';
					$shownum = intval($params['shownum'] ?? 6);
					if($shownum <= 0) $shownum = 6;
					if($shownum > 20) $shownum = 20;
					$fetchLimit = max($shownum * 4, 40);
					$nowtime = time();
					$manualRows = is_array($v['data'] ?? null) ? $v['data'] : [];
					$manualIds = [];
					foreach($manualRows as $manualRow){
						if(!is_array($manualRow)){
							continue;
						}
						$manualId = intval($manualRow['id'] ?? 0);
						if($manualId > 0 && !in_array($manualId, $manualIds, true)){
							$manualIds[] = $manualId;
						}
					}
					if(count($manualIds) > 50){
						$manualIds = array_slice($manualIds, 0, 50);
					}

					if($sourceMode === 'manual' && $manualIds){
						if($contentType === 'product'){
							$query = Db::name('miaosha')->alias('sell')
								->leftJoin('miaosha_product product', 'product.id=sell.goodsid AND product.aid=sell.aid')
								->leftJoin('miaosha_changci changci', 'changci.id=sell.changciid AND changci.aid=sell.aid')
								->field('sell.id,sell.changciid,sell.price,sell.status2,sell.prostatus2,sell.outid,product.name as product_name,product.pic as product_pic,product.sell_price as product_sell_price,product.market_price as product_market_price,product.trade_mode,product.auction_start_price,changci.name as changci_name,changci.starttime,changci.endtime,changci.qingsuan_status,changci.isfukuan')
								->where('sell.aid', $aid)
								->where('sell.status', 1)
								->where('product.status', 1)
								->where('sell.id', 'in', $manualIds);
							if($mid > 0){
								$query->where('sell.outid', '<>', $mid);
							}
							$rows = $query->select()->toArray();
							$rowMap = [];
							foreach($rows as $row){
								$rowMap[intval($row['id'] ?? 0)] = $row;
							}

							$bidCountMap = [];
							$leaderPriceMap = [];
							$leaderMemberMap = [];
							$leaderNameMap = [];
							$bidRows = Db::name('miaosha_bid_record')
								->where('aid', $aid)
								->where('miaosha_id', 'in', $manualIds)
								->where('status', 1)
								->order('miaosha_id asc,bid_price desc,id asc')
								->select()
								->toArray();
							$leaderMemberIds = [];
							foreach($bidRows as $bidRow){
								$miaoshaId = intval($bidRow['miaosha_id'] ?? 0);
								if($miaoshaId <= 0){
									continue;
								}
								if(!isset($bidCountMap[$miaoshaId])){
									$bidCountMap[$miaoshaId] = 0;
								}
								$bidCountMap[$miaoshaId]++;
								if(!isset($leaderPriceMap[$miaoshaId])){
									$leaderPriceMap[$miaoshaId] = number_format(floatval($bidRow['bid_price'] ?? 0), 2, '.', '');
									$leaderMemberMap[$miaoshaId] = intval($bidRow['member_id'] ?? 0);
									if($leaderMemberMap[$miaoshaId] > 0){
										$leaderMemberIds[] = $leaderMemberMap[$miaoshaId];
									}
								}
							}
							$leaderMemberIds = array_values(array_unique(array_filter($leaderMemberIds)));
							if($leaderMemberIds){
								$memberRows = Db::name('member')
									->where('aid', $aid)
									->where('id', 'in', $leaderMemberIds)
									->column('nickname', 'id');
								foreach($leaderMemberMap as $miaoshaId => $memberId){
									if($memberId > 0 && isset($memberRows[$memberId])){
										$leaderNameMap[$miaoshaId] = strval($memberRows[$memberId]);
									}
								}
							}

							foreach($manualIds as $sellId){
								if(empty($rowMap[$sellId])){
									continue;
								}
								$row = $rowMap[$sellId];
								$starttime = intval($row['starttime'] ?? 0);
								$endtime = intval($row['endtime'] ?? 0);
								$qingsuanStatus = intval($row['qingsuan_status'] ?? 0);
								$isFukuan = intval($row['isfukuan'] ?? 0);
								$statusKey = 'active';
								$statusText = '进行中';
								if($qingsuanStatus === 2){
									$statusKey = 'settled';
									$statusText = '已清算';
								}elseif($qingsuanStatus === 1 && $isFukuan === 1){
									$statusKey = 'settling';
									$statusText = '待付款';
								}elseif($starttime > 0 && $nowtime < $starttime){
									$statusKey = 'upcoming';
									$statusText = '未开始';
								}elseif($endtime > 0 && $nowtime > $endtime){
									$statusKey = 'ended';
									$statusText = '已结束';
								}elseif(intval($row['prostatus2'] ?? 0) !== 2){
									$statusKey = 'settling';
									$statusText = '处理中';
								}

								$tradeMode = intval($row['trade_mode'] ?? 0);
								$startPrice = floatval($row['auction_start_price'] ?? 0);
								if($startPrice <= 0){
									$startPrice = floatval($row['price'] ?? 0);
								}
								if($startPrice <= 0){
									$startPrice = floatval($row['product_sell_price'] ?? 0);
								}
								$currentPrice = $tradeMode === 1 ? floatval($leaderPriceMap[$sellId] ?? $startPrice) : floatval($row['price'] ?? 0);
								if($currentPrice <= 0){
									$currentPrice = floatval($row['product_sell_price'] ?? 0);
								}
								$marketPrice = floatval($row['product_market_price'] ?? 0);
								$marketPriceText = '';
								if($tradeMode !== 1 && $marketPrice > $currentPrice){
									$marketPriceText = number_format($marketPrice, 2, '.', '');
								}

								$result[] = [
									'id' => $sellId,
									'changciid' => intval($row['changciid'] ?? 0),
									'cover' => strval($row['product_pic'] ?? ''),
									'title' => strval($row['product_name'] ?? ''),
									'changci_name' => strval($row['changci_name'] ?? ''),
									'trade_mode_text' => $tradeMode === 1 ? '拍卖模式' : '常规模式',
									'price_label' => $tradeMode === 1 ? '当前价' : '售价',
									'price_text' => number_format($currentPrice, 2, '.', ''),
									'market_price_text' => $marketPriceText,
									'bid_count_text' => !empty($bidCountMap[$sellId]) ? '出价 '.$bidCountMap[$sellId] : '',
									'leader_text' => !empty($leaderNameMap[$sellId]) ? '领先 '.$leaderNameMap[$sellId] : '',
									'status_key' => $statusKey,
									'status_text' => $statusText,
									'time_text' => ($starttime > 0 || $endtime > 0) ? date('m-d H:i', $starttime).' - '.date('m-d H:i', $endtime) : '',
									'jump_url' => '/pagesK/miaosha/product?id='.$sellId.(intval($row['changciid'] ?? 0) > 0 ? '&ccid='.intval($row['changciid']) : ''),
								];
							}
						}else{
							$rows = Db::name('miaosha_changci')
								->where('aid', $aid)
								->where('status', 1)
								->where('id', 'in', $manualIds)
								->select()
								->toArray();
							$rowMap = [];
							foreach($rows as $row){
								$rowMap[intval($row['id'] ?? 0)] = $row;
							}

							$productCountMap = [];
							$sellableCountMap = [];
							$reservationCountMap = [];
							$productCounts = Db::name('miaosha_product')
								->field('changciid,COUNT(*) as total')
								->where('aid', $aid)
								->where('status', 1)
								->where('changciid', 'in', $manualIds)
								->group('changciid')
								->select()
								->toArray();
							foreach($productCounts as $countRow){
								$productCountMap[intval($countRow['changciid'] ?? 0)] = intval($countRow['total'] ?? 0);
							}

							$sellableCounts = Db::name('miaosha')
								->field('changciid,COUNT(*) as total')
								->where('aid', $aid)
								->where('status', 1)
								->where('prostatus2', 2)
								->where('changciid', 'in', $manualIds)
								->group('changciid')
								->select()
								->toArray();
							foreach($sellableCounts as $countRow){
								$sellableCountMap[intval($countRow['changciid'] ?? 0)] = intval($countRow['total'] ?? 0);
							}

							$reservationCounts = Db::name('miaosha_user_appointments')
								->field('changci_id,COUNT(*) as total')
								->where('aid', $aid)
								->where('status', 1)
								->where('changci_id', 'in', $manualIds)
								->group('changci_id')
								->select()
								->toArray();
							foreach($reservationCounts as $countRow){
								$reservationCountMap[intval($countRow['changci_id'] ?? 0)] = intval($countRow['total'] ?? 0);
							}
							$alreadyReservedMap = [];
							if($mid > 0){
								$reservedIds = Db::name('miaosha_user_appointments')
									->where('aid', $aid)
									->where('user_id', $mid)
									->where('changci_id', 'in', $manualIds)
									->where('status', 1)
									->column('changci_id');
								foreach($reservedIds as $reservedId){
									$alreadyReservedMap[intval($reservedId)] = 1;
								}
							}

							foreach($manualIds as $changciId){
								if(empty($rowMap[$changciId])){
									continue;
								}
								$row = $rowMap[$changciId];
								$starttime = intval($row['starttime'] ?? 0);
								$endtime = intval($row['endtime'] ?? 0);
								$qingsuanStatus = intval($row['qingsuan_status'] ?? 0);
								$isFukuan = intval($row['isfukuan'] ?? 0);
								$statusKey = 'active';
								$statusText = '进行中';
								if($qingsuanStatus === 2){
									$statusKey = 'settled';
									$statusText = '已清算';
								}elseif($qingsuanStatus === 1 && $isFukuan === 1){
									$statusKey = 'settling';
									$statusText = '待付款';
								}elseif($starttime > 0 && $nowtime < $starttime){
									$statusKey = 'upcoming';
									$statusText = '未开始';
								}elseif($endtime > 0 && $nowtime > $endtime){
									$statusKey = 'ended';
									$statusText = '已结束';
								}

								$productCount = intval($productCountMap[$changciId] ?? 0);
								$sellableCount = intval($sellableCountMap[$changciId] ?? 0);
								$reservationCount = intval($reservationCountMap[$changciId] ?? 0);
								$isAppointment = intval($row['is_appointment'] ?? 0);
								$alreadyReserved = !empty($alreadyReservedMap[$changciId]);
								$summaryParts = [];
								$summaryParts[] = $productCount.'件拍品';
								$summaryParts[] = $sellableCount.'条卖单';
								if($reservationCount > 0){
									$summaryParts[] = $reservationCount.'次围观';
								}
								$cardReserveText = '';
								$cardReserveKey = '';
								if($isAppointment === 1 && $qingsuanStatus === 0 && ($endtime <= 0 || $nowtime <= $endtime)){
									$cardReserveText = $alreadyReserved ? '已预约' : '待预约';
									$cardReserveKey = $alreadyReserved ? 'done' : 'pending';
								}
								$cardActionType = 'go';
								$cardActionDisabled = 0;
								if($qingsuanStatus === 2){
									$cardActionType = 'result';
								}elseif($qingsuanStatus === 1 && $isFukuan === 1){
									$cardActionType = 'view';
								}elseif($qingsuanStatus === 1 && $isFukuan === 0){
									$cardActionType = '';
									$cardActionDisabled = 1;
								}elseif($endtime > 0 && $nowtime > $endtime){
									$cardActionType = 'result';
								}elseif($cardReserveKey === 'pending' && $starttime > 0 && $nowtime < $starttime){
									$cardActionType = 'reserve';
								}
								if($qingsuanStatus === 0 && $sellableCount <= 0){
									$cardActionType = '';
									$cardActionDisabled = 1;
								}

								$result[] = [
									'id' => $changciId,
									'cover' => strval($row['backimg'] ?? ''),
									'title' => strval($row['name'] ?? ''),
									'status_key' => $statusKey,
									'status_text' => $statusText,
									'time_text' => ($starttime > 0 || $endtime > 0) ? date('m-d H:i', $starttime).' - '.date('m-d H:i', $endtime) : '',
									'summary_text' => implode(' · ', $summaryParts),
									'product_count' => $productCount,
									'sellable_count' => $sellableCount,
									'reservation_count' => $reservationCount,
									'starttime_formatted_short' => $starttime > 0 ? date('m-d H:i', $starttime) : '',
									'endtime_formatted_short' => $endtime > 0 ? date('m-d H:i', $endtime) : '',
									'card_status_key' => $statusKey === 'settled' ? 'finished' : $statusKey,
									'card_status_text' => $statusText,
									'card_time_label' => '拍卖时间',
									'card_time_value' => ($starttime > 0 || $endtime > 0) ? '开拍: '.date('m-d H:i', $starttime).' — 结拍: '.date('m-d H:i', $endtime) : '',
									'card_reserve_text' => $cardReserveText,
									'card_reserve_key' => $cardReserveKey,
									'card_action_type' => $cardActionType,
									'card_action_disabled' => $cardActionDisabled,
									'jump_url' => '/pagesK/miaosha/classify2?ccid='.$changciId,
								];
							}
						}
					}elseif($contentType === 'product'){
						$sortby = strval($params['sortby'] ?? 'latest');
						$query = Db::name('miaosha')->alias('sell')
							->leftJoin('miaosha_product product', 'product.id=sell.goodsid AND product.aid=sell.aid')
							->leftJoin('miaosha_changci changci', 'changci.id=sell.changciid AND changci.aid=sell.aid')
							->field('sell.id,sell.changciid,sell.price,sell.status2,sell.prostatus2,sell.outid,product.name as product_name,product.pic as product_pic,product.sell_price as product_sell_price,product.market_price as product_market_price,product.trade_mode,product.auction_start_price,changci.name as changci_name,changci.starttime,changci.endtime,changci.qingsuan_status,changci.isfukuan')
							->where('sell.aid', $aid)
							->where('sell.status', 1)
							->where('product.status', 1);
						if(!empty($params['changciid'])){
							$query->where('sell.changciid', intval($params['changciid']));
						}
						if($mid > 0){
							$query->where('sell.outid', '<>', $mid);
						}
						if($sortby === 'price_desc'){
							$query->order('sell.price desc,sell.id desc');
						}elseif($sortby === 'price_asc'){
							$query->order('sell.price asc,sell.id desc');
						}else{
							$query->order('sell.id desc');
						}

						$rows = $query->limit($fetchLimit)->select()->toArray();
						$sellIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'id')))));
						$bidCountMap = [];
						$leaderPriceMap = [];
						$leaderMemberMap = [];
						$leaderNameMap = [];

						if($sellIds){
							$bidRows = Db::name('miaosha_bid_record')
								->where('aid', $aid)
								->where('miaosha_id', 'in', $sellIds)
								->where('status', 1)
								->order('miaosha_id asc,bid_price desc,id asc')
								->select()
								->toArray();
							$leaderMemberIds = [];
							foreach($bidRows as $bidRow){
								$miaoshaId = intval($bidRow['miaosha_id'] ?? 0);
								if($miaoshaId <= 0){
									continue;
								}
								if(!isset($bidCountMap[$miaoshaId])){
									$bidCountMap[$miaoshaId] = 0;
								}
								$bidCountMap[$miaoshaId]++;
								if(!isset($leaderPriceMap[$miaoshaId])){
									$leaderPriceMap[$miaoshaId] = number_format(floatval($bidRow['bid_price'] ?? 0), 2, '.', '');
									$leaderMemberMap[$miaoshaId] = intval($bidRow['member_id'] ?? 0);
									if($leaderMemberMap[$miaoshaId] > 0){
										$leaderMemberIds[] = $leaderMemberMap[$miaoshaId];
									}
								}
							}
							$leaderMemberIds = array_values(array_unique(array_filter($leaderMemberIds)));
							if($leaderMemberIds){
								$memberRows = Db::name('member')
									->where('aid', $aid)
									->where('id', 'in', $leaderMemberIds)
									->column('nickname', 'id');
								foreach($leaderMemberMap as $miaoshaId => $memberId){
									if($memberId > 0 && isset($memberRows[$memberId])){
										$leaderNameMap[$miaoshaId] = strval($memberRows[$memberId]);
									}
								}
							}
						}

						$scope = strval($params['product_status_scope'] ?? 'active');
						foreach($rows as $row){
							$starttime = intval($row['starttime'] ?? 0);
							$endtime = intval($row['endtime'] ?? 0);
							$qingsuanStatus = intval($row['qingsuan_status'] ?? 0);
							$isFukuan = intval($row['isfukuan'] ?? 0);
							$statusKey = 'active';
							$statusText = '进行中';
							if($qingsuanStatus === 2){
								$statusKey = 'settled';
								$statusText = '已清算';
							}elseif($qingsuanStatus === 1 && $isFukuan === 1){
								$statusKey = 'settling';
								$statusText = '待付款';
							}elseif($starttime > 0 && $nowtime < $starttime){
								$statusKey = 'upcoming';
								$statusText = '未开始';
							}elseif($endtime > 0 && $nowtime > $endtime){
								$statusKey = 'ended';
								$statusText = '已结束';
							}elseif(intval($row['prostatus2'] ?? 0) !== 2){
								$statusKey = 'settling';
								$statusText = '处理中';
							}

							if($scope === 'upcoming' && $statusKey !== 'upcoming'){
								continue;
							}
							if($scope === 'active' && !in_array($statusKey, ['active', 'settling'], true)){
								continue;
							}
							if($scope === 'ended' && !in_array($statusKey, ['ended', 'settled'], true)){
								continue;
							}

							$sellId = intval($row['id'] ?? 0);
							$tradeMode = intval($row['trade_mode'] ?? 0);
							$startPrice = floatval($row['auction_start_price'] ?? 0);
							if($startPrice <= 0){
								$startPrice = floatval($row['price'] ?? 0);
							}
							if($startPrice <= 0){
								$startPrice = floatval($row['product_sell_price'] ?? 0);
							}
							$currentPrice = $tradeMode === 1 ? floatval($leaderPriceMap[$sellId] ?? $startPrice) : floatval($row['price'] ?? 0);
							if($currentPrice <= 0){
								$currentPrice = floatval($row['product_sell_price'] ?? 0);
							}
							$marketPrice = floatval($row['product_market_price'] ?? 0);
							$marketPriceText = '';
							if($tradeMode !== 1 && $marketPrice > $currentPrice){
								$marketPriceText = number_format($marketPrice, 2, '.', '');
							}

							$result[] = [
								'id' => $sellId,
								'changciid' => intval($row['changciid'] ?? 0),
								'cover' => strval($row['product_pic'] ?? ''),
								'title' => strval($row['product_name'] ?? ''),
								'changci_name' => strval($row['changci_name'] ?? ''),
								'trade_mode_text' => $tradeMode === 1 ? '拍卖模式' : '常规模式',
								'price_label' => $tradeMode === 1 ? '当前价' : '售价',
								'price_text' => number_format($currentPrice, 2, '.', ''),
								'market_price_text' => $marketPriceText,
								'bid_count_text' => !empty($bidCountMap[$sellId]) ? '出价 '.$bidCountMap[$sellId] : '',
								'leader_text' => !empty($leaderNameMap[$sellId]) ? '领先 '.$leaderNameMap[$sellId] : '',
								'status_key' => $statusKey,
								'status_text' => $statusText,
								'time_text' => ($starttime > 0 || $endtime > 0) ? date('m-d H:i', $starttime).' - '.date('m-d H:i', $endtime) : '',
								'jump_url' => '/pagesK/miaosha/product?id='.$sellId.(intval($row['changciid'] ?? 0) > 0 ? '&ccid='.intval($row['changciid']) : ''),
							];
							if(count($result) >= $shownum){
								break;
							}
						}
					}else{
						$sortby = strval($params['sortby'] ?? 'latest');
						$query = Db::name('miaosha_changci')
							->where('aid', $aid)
							->where('status', 1);
						if($sortby === 'start_asc'){
							$query->order('starttime asc,id asc');
						}elseif($sortby === 'start_desc'){
							$query->order('starttime desc,id desc');
						}else{
							$query->order('id desc');
						}
						$rows = $query->limit($fetchLimit)->select()->toArray();
						$changciIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'id')))));
						$productCountMap = [];
						$sellableCountMap = [];
						$reservationCountMap = [];
						if($changciIds){
							$productCounts = Db::name('miaosha_product')
								->field('changciid,COUNT(*) as total')
								->where('aid', $aid)
								->where('status', 1)
								->where('changciid', 'in', $changciIds)
								->group('changciid')
								->select()
								->toArray();
							foreach($productCounts as $countRow){
								$productCountMap[intval($countRow['changciid'] ?? 0)] = intval($countRow['total'] ?? 0);
							}

							$sellableCounts = Db::name('miaosha')
								->field('changciid,COUNT(*) as total')
								->where('aid', $aid)
								->where('status', 1)
								->where('prostatus2', 2)
								->where('changciid', 'in', $changciIds)
								->group('changciid')
								->select()
								->toArray();
							foreach($sellableCounts as $countRow){
								$sellableCountMap[intval($countRow['changciid'] ?? 0)] = intval($countRow['total'] ?? 0);
							}

							$reservationCounts = Db::name('miaosha_user_appointments')
								->field('changci_id,COUNT(*) as total')
								->where('aid', $aid)
								->where('status', 1)
								->where('changci_id', 'in', $changciIds)
								->group('changci_id')
								->select()
								->toArray();
							foreach($reservationCounts as $countRow){
								$reservationCountMap[intval($countRow['changci_id'] ?? 0)] = intval($countRow['total'] ?? 0);
							}
							$alreadyReservedMap = [];
							if($mid > 0){
								$reservedIds = Db::name('miaosha_user_appointments')
									->where('aid', $aid)
									->where('user_id', $mid)
									->where('changci_id', 'in', $changciIds)
									->where('status', 1)
									->column('changci_id');
								foreach($reservedIds as $reservedId){
									$alreadyReservedMap[intval($reservedId)] = 1;
								}
							}
						}

						$scope = strval($params['changci_status_scope'] ?? 'all');
						foreach($rows as $row){
							$changciId = intval($row['id'] ?? 0);
							$starttime = intval($row['starttime'] ?? 0);
							$endtime = intval($row['endtime'] ?? 0);
							$qingsuanStatus = intval($row['qingsuan_status'] ?? 0);
							$isFukuan = intval($row['isfukuan'] ?? 0);
							$statusKey = 'active';
							$statusText = '进行中';
							if($qingsuanStatus === 2){
								$statusKey = 'settled';
								$statusText = '已清算';
							}elseif($qingsuanStatus === 1 && $isFukuan === 1){
								$statusKey = 'settling';
								$statusText = '待付款';
							}elseif($starttime > 0 && $nowtime < $starttime){
								$statusKey = 'upcoming';
								$statusText = '未开始';
							}elseif($endtime > 0 && $nowtime > $endtime){
								$statusKey = 'ended';
								$statusText = '已结束';
							}

							if($scope === 'upcoming' && $statusKey !== 'upcoming'){
								continue;
							}
							if($scope === 'active' && !in_array($statusKey, ['active', 'settling'], true)){
								continue;
							}
							if($scope === 'ended' && !in_array($statusKey, ['ended', 'settled'], true)){
								continue;
							}

							$productCount = intval($productCountMap[$changciId] ?? 0);
							$sellableCount = intval($sellableCountMap[$changciId] ?? 0);
							$reservationCount = intval($reservationCountMap[$changciId] ?? 0);
							$isAppointment = intval($row['is_appointment'] ?? 0);
							$alreadyReserved = !empty($alreadyReservedMap[$changciId]);
							$summaryParts = [];
							$summaryParts[] = $productCount.'件拍品';
							$summaryParts[] = $sellableCount.'条卖单';
							if($reservationCount > 0){
								$summaryParts[] = $reservationCount.'次围观';
							}
							$cardReserveText = '';
							$cardReserveKey = '';
							if($isAppointment === 1 && $qingsuanStatus === 0 && ($endtime <= 0 || $nowtime <= $endtime)){
								$cardReserveText = $alreadyReserved ? '已预约' : '待预约';
								$cardReserveKey = $alreadyReserved ? 'done' : 'pending';
							}
							$cardActionType = 'go';
							$cardActionDisabled = 0;
							if($qingsuanStatus === 2){
								$cardActionType = 'result';
							}elseif($qingsuanStatus === 1 && $isFukuan === 1){
								$cardActionType = 'view';
							}elseif($qingsuanStatus === 1 && $isFukuan === 0){
								$cardActionType = '';
								$cardActionDisabled = 1;
							}elseif($endtime > 0 && $nowtime > $endtime){
								$cardActionType = 'result';
							}elseif($cardReserveKey === 'pending' && $starttime > 0 && $nowtime < $starttime){
								$cardActionType = 'reserve';
							}
							if($qingsuanStatus === 0 && $sellableCount <= 0){
								$cardActionType = '';
								$cardActionDisabled = 1;
							}

							$result[] = [
								'id' => $changciId,
								'cover' => strval($row['backimg'] ?? ''),
								'title' => strval($row['name'] ?? ''),
								'status_key' => $statusKey,
								'status_text' => $statusText,
								'time_text' => ($starttime > 0 || $endtime > 0) ? date('m-d H:i', $starttime).' - '.date('m-d H:i', $endtime) : '',
								'summary_text' => implode(' · ', $summaryParts),
								'product_count' => $productCount,
								'sellable_count' => $sellableCount,
								'reservation_count' => $reservationCount,
								'starttime_formatted_short' => $starttime > 0 ? date('m-d H:i', $starttime) : '',
								'endtime_formatted_short' => $endtime > 0 ? date('m-d H:i', $endtime) : '',
								'card_status_key' => $statusKey === 'settled' ? 'finished' : $statusKey,
								'card_status_text' => $statusText,
								'card_time_label' => '拍卖时间',
								'card_time_value' => ($starttime > 0 || $endtime > 0) ? '开拍: '.date('m-d H:i', $starttime).' — 结拍: '.date('m-d H:i', $endtime) : '',
								'card_reserve_text' => $cardReserveText,
								'card_reserve_key' => $cardReserveKey,
								'card_action_type' => $cardActionType,
								'card_action_disabled' => $cardActionDisabled,
								'jump_url' => '/pagesK/miaosha/classify2?ccid='.$changciId,
							];
							if(count($result) >= $shownum){
								break;
							}
						}
					}
				}catch(\Throwable $e){
					$result = [];
				}
				$pagecontent[$k]['data'] = $result;
			}elseif(in_array($v['temp'], ['ershou', 'ershou_exchange'], true)){//二手推荐
				$result = [];
				if(\app\admin\common\ErshouAdmin::tableExists('ershou_goods') && \app\admin\common\ErshouAdmin::tableExists('ershou_goods_media') && \app\admin\common\ErshouAdmin::tableExists('ershou_campus')){
					$where = [];
					$where[] = ['goods.aid', '=', $aid];
					$where[] = ['goods.status', '=', 1];
					$where[] = ['goods.audit_status', '=', 1];
					$dealModeScope = trim(strval($v['params']['deal_mode_scope'] ?? 'all'));

					if(in_array($dealModeScope, ['sale', 'goods', 'xianzhi'], true)){
						$where[] = ['goods.deal_mode', 'in', [1, 3]];
					}elseif($dealModeScope === 'exchange'){
						$where[] = ['goods.deal_mode', 'in', [2, 3]];
					}

					if(!empty($v['params']['school_id'])){
						$where[] = ['goods.school_id', '=', intval($v['params']['school_id'])];
					}
					if(!empty($v['params']['campus_id'])){
						$where[] = ['goods.campus_id', '=', intval($v['params']['campus_id'])];
					}
					if(!empty($v['params']['community_id'])){
						$where[] = ['goods.community_id', '=', intval($v['params']['community_id'])];
					}

					$shownum = intval($v['params']['shownum'] ?? 6);
					if($shownum <= 0) $shownum = 6;
					if($shownum > 20) $shownum = 20;

					$order = 'goods.last_refresh_time desc,goods.id desc';
					if(($v['params']['sortby'] ?? '') == 'new'){
						$order = 'goods.createtime desc,goods.id desc';
					}elseif(($v['params']['sortby'] ?? '') == 'view'){
						$order = 'goods.view_num desc,goods.last_refresh_time desc,goods.id desc';
					}

					$result = Db::name('ershou_goods')->alias('goods')
						->leftJoin('ershou_campus campus', 'campus.id=goods.campus_id AND campus.aid=goods.aid')
						->leftJoin('ershou_campus community', 'community.id=goods.community_id AND community.aid=goods.aid')
						->where($where)
						->field('goods.id,goods.school_id,goods.campus_id,goods.community_id,goods.title,goods.subtitle,goods.price,goods.origin_price,goods.condition_level,goods.condition_text,goods.deal_mode,goods.exchange_want_text,goods.location_text,goods.view_num,goods.last_refresh_time,goods.createtime,campus.name as campus_name,community.name as community_name')
						->order($order)
						->limit($shownum)
						->select()
						->toArray();

					if($result){
						$goodsIds = array_values(array_unique(array_filter(array_map('intval', array_column($result, 'id')))));
						$coverMap = [];
						if($goodsIds){
							$mediaRows = Db::name('ershou_goods_media')
								->where('aid', $aid)
								->where('goods_id', 'in', $goodsIds)
								->order('sort desc,id asc')
								->select()
								->toArray();
							foreach($mediaRows as $mediaRow){
								$goodsId = intval($mediaRow['goods_id'] ?? 0);
								if($goodsId > 0 && !isset($coverMap[$goodsId])){
									$coverMap[$goodsId] = strval($mediaRow['media_url'] ?? '');
								}
							}
						}

						foreach($result as $k2 => $goods){
							$campusInfo = [];
							if(!empty($goods['campus_name'])) $campusInfo[] = strval($goods['campus_name']);
							if(!empty($goods['community_name'])) $campusInfo[] = strval($goods['community_name']);
							$result[$k2]['cover'] = strval($coverMap[intval($goods['id'] ?? 0)] ?? '');
							$result[$k2]['deal_mode'] = intval($goods['deal_mode'] ?? 1);
							$result[$k2]['deal_mode_text'] = \app\admin\common\ErshouAdmin::goodsDealModeText(intval($goods['deal_mode'] ?? 1));
							$result[$k2]['exchange_want_text'] = strval($goods['exchange_want_text'] ?? '');
							$result[$k2]['condition_text'] = \app\admin\common\ErshouAdmin::goodsConditionText(intval($goods['condition_level'] ?? 0), strval($goods['condition_text'] ?? ''));
							$result[$k2]['campus_info_text'] = implode(' · ', $campusInfo);
							$result[$k2]['last_refresh_time_text'] = intval($goods['last_refresh_time'] ?? 0) > 0 ? date('m-d H:i', intval($goods['last_refresh_time'])) : '';
							$result[$k2]['createtime_text'] = intval($goods['createtime'] ?? 0) > 0 ? date('m-d H:i', intval($goods['createtime'])) : '';
						}
					}
				}
				$pagecontent[$k]['data'] = $result;
			}elseif($v['temp'] == 'zhaopinzhiwei'){//招聘职位列表
				if($v['params']['jobfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $pro){
						$newpro = Db::name('zhaopin_position')
							->field('id zwid,title,salary,education,experience,work_address,company_id,benefits,views,numbers,description,work_mode,payment,work_time_type,work_intensity,options,reward_type,reward_data1,reward_data2,age_requirement,gender_requirement')
							->where('aid',$aid)
							->where('id',$pro['zwid'])
							->where('status',1)
							->find();
							//var_dump($newpro);die;
						if($newpro){
							$newpro['id'] = $pro['id'];
							
							//根据显示控制参数处理数据
							if(!isset($v['params']['showsalary']) || $v['params']['showsalary'] == 0) unset($newpro['salary']);
							if(!isset($v['params']['showeducation']) || $v['params']['showeducation'] == 0) unset($newpro['education']);
							if(!isset($v['params']['showexperience']) || $v['params']['showexperience'] == 0) unset($newpro['experience']);
							if(!isset($v['params']['showaddress']) || $v['params']['showaddress'] == 0) unset($newpro['work_address']);
							if(!isset($v['params']['shownumbers']) || $v['params']['shownumbers'] == 0) unset($newpro['numbers']);
							if(!isset($v['params']['showdetail']) || $v['params']['showdetail'] == 0) unset($newpro['description']);
							
							//获取企业信息
							if(isset($v['params']['showcompany']) && $v['params']['showcompany'] == 1){
								$company = Db::name('zhaopin_company')
									->field('name company_name,logo company_logo')
									->where('id',$newpro['company_id'])
									->find();
								if($company){
									$newpro = array_merge($newpro,$company);
								}
							}

							// 处理工作标签
							$tags = [];
							if($newpro['work_mode']) $tags[] = $newpro['work_mode'];
							if($newpro['payment']) $tags[] = $newpro['payment'];
							if($newpro['work_time_type']) $tags[] = $newpro['work_time_type'];
							if($newpro['work_intensity']) $tags[] = $newpro['work_intensity'];
							// 添加年龄要求标签
							if($newpro['age_requirement']) $tags[] = $newpro['age_requirement'];
							// 添加性别要求标签
							if($newpro['gender_requirement']) {
								switch($newpro['gender_requirement']) {
									case 1:
										$tags[] = '限男';
										break;
									case 2:
										$tags[] = '限女';
										break;
									default:
										$tags[] = '不限性别';
								}
							}
							
							// 处理自定义标签选项
							if($newpro['options']){
								$options = json_decode($newpro['options'], true);
								if(is_array($options)){
									foreach($options as $opt){
										$tags[] = $opt;
									}
								}
							}
							
							$newpro['tags'] = $tags;

							// 计算佣金
							if($mid > 0) {
								$commission_data = self::calculateCommission($newpro, $mid);
								if(!empty($commission_data)) {
									$newpro['commission_data'] = $commission_data;
									// 添加佣金说明
									$salary_range = explode('-', $newpro['salary']);
									$min_salary = intval($salary_range[0]);
									$max_salary = intval($salary_range[1]);
									
									switch($newpro['reward_type']) {
										case 0:
											$newpro['commission_text'] = " {$commission_data['commission_min']}-{$commission_data['commission_max']}";
											break;
										case 1:
											$newpro['commission_text'] = " {$commission_data['commission_min']}-{$commission_data['commission_max']}";
											break;
										case 2:
											$newpro['commission_text'] = "{$commission_data['commission_min']}";
											break;
									}
								}
							}

							$newdata[] = $newpro;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					
					//分类筛选
					if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$where[] = ['category_id','=',$cid];
					}
					
					//区域限制
					$area_set = Db::name('admin_set')->where('aid',aid)->field('area_on,areamode')->find();
					$area_id = input('param.area_id/d');
						if($area_set && $area_set['area_on'] && $area_id && self::tableExists('admin_set_area_permission')){
							$permission = Db::name('admin_set_area_permission')->where(['a_area'=>md5(aid.$area_id)])->value('permission');
						if($permission){
							$permission = json_decode($permission,true);
							if($area_set['areamode']==0)$limit_field = 'province_id';
							if($area_set['areamode']==1)$limit_field = 'city_id';
							if($area_set['areamode']==2)$limit_field = 'district_id';
							if($permission['client_visible_area']==0){
								//仅可见该区域
								$where[] = [$limit_field,'=',$area_id];
							}else if($permission['client_visible_area']==1){
								//可见部分区域
								$where[] = [$limit_field,'in',explode(',',$permission['client_visible_areas'])];
							}else if($permission['client_visible_area']==2){
								//不可见部分区域
								$where[] = [$limit_field,'notin',explode(',',$permission['client_unvisible_areas'])];
							} 
						}
					}
					
					//排序方式
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'create_time desc';
					if($v['params']['sortby'] == 'createtime') $order = 'create_time';
					if($v['params']['sortby'] == 'views') $order = 'views desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');

					//构建查询字段
					$fields = ['id zwid'];
					if(!isset($v['params']['showsalary']) || $v['params']['showsalary'] == 1) $fields[] = 'salary';
					if(!isset($v['params']['showeducation']) || $v['params']['showeducation'] == 1) $fields[] = 'education';
					if(!isset($v['params']['showexperience']) || $v['params']['showexperience'] == 1) $fields[] = 'experience';
					if(!isset($v['params']['showaddress']) || $v['params']['showaddress'] == 1) $fields[] = 'work_address';
					if(!isset($v['params']['shownumbers']) || $v['params']['shownumbers'] == 1) $fields[] = 'numbers';
					if(!isset($v['params']['showdetail']) || $v['params']['showdetail'] == 1) $fields[] = 'description';
					$fields[] = 'company_id';
					$fields[] = 'title';
					$fields[] = 'views';
					$fields[] = 'longitude';
					$fields[] = 'latitude';
					$fields[] = 'work_mode';
					$fields[] = 'payment';
					$fields[] = 'work_time_type'; 
					$fields[] = 'work_intensity';
					$fields[] = 'options';
					$fields[] = 'reward_type';
					$fields[] = 'reward_data1';  // 提成比例数据
					$fields[] = 'reward_data2';  // 提成金额数据
					$fields[] = 'age_requirement'; // 年龄要求
					$fields[] = 'gender_requirement'; // 性别要求

					//距离计算
					$filedraw = '';
					if($v['params']['sortby'] == 'distance' && $latitude && $longitude){
						if($v['params']['distance']) {
							$filedraw .= ",round(6378.138*2*asin(sqrt(pow(sin( ({$latitude}*pi()/180-latitude*pi()/180)/2),2)+cos({$latitude}*pi()/180)*cos(latitude*pi()/180)* pow(sin( ({$longitude}*pi()/180-longitude*pi()/180)/2),2)))*1000) AS distance";
							$order = "distance asc";
							$result = Db::query("select * from ((select ".implode(',',$fields).$filedraw." from ".table_name('zhaopin_position').
								" where aid=:aid and status = 1 order by $order ) as A) where distance <= :distance limit ".$v['params']['shownum'], ['aid' => $aid, 'distance' => $v['params']['distance'] * 1000]);
						} else {
							$order = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
							$result = Db::name('zhaopin_position')
								->field(implode(',',$fields).$filedraw)
								->where($where)
								->order($order)
								->limit($v['params']['shownum'])
								->select()
								->toArray();
						}
					} else {
						$result = Db::name('zhaopin_position')
							->field(implode(',',$fields).$filedraw)
							->where($where)
							->order($order)
							->limit($v['params']['shownum'])
							->select()
							->toArray();
					}

					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'Z'.time().rand(10000000,99999999);
						//获取企业信息
						if(isset($v['params']['showcompany']) && $v['params']['showcompany'] == 1){
							$company = Db::name('zhaopin_company')
								->field('name company_name,logo company_logo')
								->where('id',$v2['company_id'])
								->find();
							if($company){
								$result[$k2] = array_merge($result[$k2],$company);
							}
						}
						//计算距离
						if($longitude && $latitude){
							$result[$k2]['juli'] = getdistance($longitude,$latitude,$v2['longitude'],$v2['latitude'],1).'km';
						}else{
							$result[$k2]['juli'] = '';
						}
						
						//检查是否已收藏
						if($mid > 0) {
							$is_favorite = Db::name('zhaopin_favorite')
								->where('aid', aid)
								->where('position_id', $v2['zwid'])
								->where('mid', $mid)
								->find();
							$result[$k2]['is_favorite'] = $is_favorite ? 1 : 0;

							// 计算佣金
							$commission_data = self::calculateCommission($v2, $mid);
							if(!empty($commission_data)) {
								$result[$k2]['commission_data'] = $commission_data;
								// 添加佣金说明
								$salary_range = explode('-', $v2['salary']);
								$min_salary = intval($salary_range[0]);
								$max_salary = intval($salary_range[1]);
								
								switch($v2['reward_type']) {
									case 0:
										$result[$k2]['commission_text'] = "{$commission_data['commission_min']}-{$commission_data['commission_max']}";
										break;
									case 1:
										$result[$k2]['commission_text'] = "{$commission_data['commission_min']}-{$commission_data['commission_max']}";
										break;
									case 2:
										$result[$k2]['commission_text'] = "{$commission_data['commission_min']}";
										break;
								}
							}
						} else {
							$result[$k2]['is_favorite'] = 0;
						}
						
						// 处理工作标签
						$tags = [];
						if($v2['work_mode']) $tags[] = $v2['work_mode'];
						if($v2['payment']) $tags[] = $v2['payment'];
						if($v2['work_time_type']) $tags[] = $v2['work_time_type'];
						if($v2['work_intensity']) $tags[] = $v2['work_intensity'];
						// 添加年龄要求标签
						if($v2['age_requirement']) $tags[] = $v2['age_requirement'];
						// 添加性别要求标签
						if($v2['gender_requirement']) {
							switch($v2['gender_requirement']) {
								case 1:
									$tags[] = '限男';
									break;
								case 2:
									$tags[] = '限女';
									break;
								default:
									$tags[] = '不限性别';
							}
						}
						
						// 处理自定义标签选项
						if($v2['options']){
							$options = json_decode($v2['options'], true);
							if(is_array($options)){
								foreach($options as $type_id => $option_ids) {
									// 获取标签选项信息
									$option_list = Db::name('zhaopin_options')
										->whereIn('id', $option_ids)
										->where([
											'aid' => $aid,
											'status' => 1
										])
										->field('id,name')
										->select()
										->toArray();
									
									foreach($option_list as $option) {
										$tags[] = $option['name'];
									}
								}
							}
						}
						
						$result[$k2]['tags'] = $tags;
					}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'property_fee'){//物业费展示 获取用户物业费信息
				// 初始化物业费数据
				$property_data = array(
					'current_year_fee' => '0.00',  // 当年物业费
					'deducted_fee' => '0.00',      // 已抵扣费用
					'payable_fee' => '0.00',       // 应交物业费
					'room_name' => '',             // 房屋信息
					'owner_name' => '',            // 业主信息
					'user_score' => 0,             // 用户积分
				);
				
			// 如果有用户ID，查询真实数据
			if($mid > 0){
				// 记录调试信息
				$debug_info = [
					'step' => '开始查询',
					'mid' => $mid,
					'aid' => aid,
					'current_year' => date('Y')
				];
				
				// 1. 获取用户绑定的房屋ID列表和房屋信息
				// 注意：不限制bid，因为用户可能绑定了不同商户创建的房屋
				$rooms = Db::name('property_room')
					->alias('room')
					->leftJoin('property_community community', 'room.community_id = community.id')
					->leftJoin('property_building building', 'room.building_id = building.id')
					->where('room.aid', aid)
					->where('room.owner_mid', $mid)
					->field('room.id,room.room_number,room.bid,community.name as community_name,building.name as building_name')
					->select()
					->toArray();
				
				$debug_info['rooms_sql'] = Db::getLastSql();
				$debug_info['rooms_count'] = count($rooms);
				$debug_info['rooms_data'] = $rooms;
					
					if(!empty($rooms)){
						$room_ids = array_column($rooms, 'id');
						$debug_info['room_ids'] = $room_ids;
						
						// 获取第一个房屋的完整信息用于显示
						$first_room = $rooms[0];
						$room_display = '';
						if($first_room['community_name']){
							$room_display .= $first_room['community_name'];
						}
						if($first_room['building_name']){
							$room_display .= ' ' . $first_room['building_name'];
						}
						if($first_room['room_number']){
							$room_display .= ' ' . $first_room['room_number'];
						}
						$property_data['room_name'] = $room_display ?: '未绑定房屋';
						
						// 获取当前年份
						$current_year = date('Y');
						$year_start = strtotime($current_year . '-01-01 00:00:00');
						$year_end = strtotime($current_year . '-12-31 23:59:59');
						
						$debug_info['current_year'] = $current_year;
						$debug_info['year_start'] = $year_start;
						$debug_info['year_end'] = $year_end;
						
				// 2. 计算当年物业费（当年账单的总金额）
				// bill_period 格式为 "2025-01"，使用 LIKE 匹配当年
				// 注意：不限制bid，因为账单可能来自不同商户创建的房屋
				
				// 先查询所有账单记录用于调试
				$all_bills = Db::name('property_bill')
					->where('aid', aid)
					->where('room_id', 'in', $room_ids)
					->field('id,room_id,bill_period,amount,paid_amount')
					->select()
					->toArray();
				
				$debug_info['bills_sql'] = Db::getLastSql();
				$debug_info['bills_count'] = count($all_bills);
				$debug_info['bills_data'] = $all_bills;
				
				$current_year_fee_sum = Db::name('property_bill')
					->where('aid', aid)
					->where('room_id', 'in', $room_ids)
					->sum('amount');
				
				$debug_info['current_year_fee_sql'] = Db::getLastSql();
				$debug_info['current_year_fee_sum'] = $current_year_fee_sum;
				
				// 3. 计算已缴费金额（从账单表的paid_amount统计，包含在线和线下缴费）
				// 修复：直接从property_bill表统计paid_amount，包含所有缴费方式
				// 原逻辑问题：从property_payment表查询会遗漏线下缴费（mid=0）
				$deducted_fee_sum = Db::name('property_bill')
					->where('aid', aid)
					->where('room_id', 'in', $room_ids)
					->sum('paid_amount');
				
				$debug_info['deducted_fee_sql'] = Db::getLastSql();
				$debug_info['deducted_fee_sum'] = $deducted_fee_sum;
						
						// 4. 计算应交物业费（当年物业费 - 已抵扣费用）
						$payable_fee = ($current_year_fee_sum ?: 0) - ($deducted_fee_sum ?: 0);
						// 确保应交费用不为负数
						$payable_fee = max(0, $payable_fee);
						
						$debug_info['payable_fee'] = $payable_fee;
						
						// 5. 格式化数据
						$property_data['current_year_fee'] = number_format($current_year_fee_sum ?: 0, 2, '.', '');
						$property_data['deducted_fee'] = number_format($deducted_fee_sum ?: 0, 2, '.', '');
						$property_data['payable_fee'] = number_format($payable_fee, 2, '.', '');
					}else{
						$debug_info['error'] = '未找到绑定的房屋';
					}
					
					// 6. 获取业主信息
					$member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
					if($member){
						$property_data['owner_name'] = $member['realname'] ?: $member['nickname'] ?: '未认证';
						// 获取用户积分
						$property_data['user_score'] = intval(\app\admin\common\Member::getscore($member));
						$debug_info['member_info'] = [
							'id' => $member['id'],
							'nickname' => $member['nickname'],
							'realname' => $member['realname']
						];
					}
				}else{
					$debug_info = [
						'error' => '未登录或mid为0',
						'mid' => $mid
					];
				}
				
				$pagecontent[$k]['data'] = $property_data;
			}elseif($v['temp'] == 'huizhan'){//会展展商列表
				$result = [];
				try{
					$where = [];
					$where[] = ['aid','=',$aid];
					$where[] = ['status','=',1];
					if(isset($v['params']['huizhanfrom']) && (strval($v['params']['huizhanfrom']) === '1' || intval($v['params']['huizhanfrom']) === 1) && !empty($v['params']['category'])){
						$where[] = ['cate_id','=',intval($v['params']['category'])];
					}
					$order = 'sort desc,id desc';
					if(isset($v['params']['sortby'])){
						if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc,id desc';
						if($v['params']['sortby'] == 'createtime') $order = 'createtime asc,id asc';
					}
					$shownum = intval($v['params']['shownum'] ?? 6);
					if($shownum <= 0) $shownum = 6;
					$rows = Db::name('huizhan_exhibitor')->where($where)->order($order)->limit($shownum)->select()->toArray();
					foreach($rows as $row){
						$exhibitorId = intval($row['id'] ?? 0);
						$description = trim(strval($row['description'] ?? ''));
						$summary = $description !== '' ? preg_replace('/\s+/', ' ', strip_tags($description)) : '';
						$result[] = [
							'id' => $exhibitorId,
							'exhibitor_id' => $exhibitorId,
							'name' => strval($row['name'] ?? ''),
							'logo' => strval($row['logo'] ?? ''),
							'booth_no' => strval($row['booth_no'] ?? ''),
							'main_product' => strval($row['main_product'] ?? ''),
							'contact_name' => strval($row['contact_name'] ?? ''),
							'contact_phone' => strval($row['contact_mobile'] ?? ''),
							'contact_mobile' => strval($row['contact_mobile'] ?? ''),
							'cate_name' => strval($row['cate_name'] ?? ''),
							'description' => $description,
							'summary' => $summary,
						];
					}
				}catch(\Exception $e){
					// huizhan表不存在时忽略
				}
				$pagecontent[$k]['data'] = $result;
			}elseif($v['temp'] == 'goldprice'){//黄金金价条（插件）
				$goldData = [
					'plugin_ready' => 0,
					'list' => [],
					'updatetime' => '',
					'market' => '',
					'market_name' => '',
				];
				try{
					if(class_exists('\\app\\plugin\\goldprice\\service\\GoldpriceService')){
						$goldData = \app\plugin\goldprice\service\GoldpriceService::buildDiyData(intval($aid), $v['params'] ?? []);
					}
				}catch(\Throwable $e){
					$goldData['msg'] = $e->getMessage();
				}
				$pagecontent[$k]['params']['plugin_ready'] = intval($goldData['plugin_ready'] ?? 0);
				$pagecontent[$k]['params']['updatetime'] = $goldData['updatetime'] ?? '';
				$pagecontent[$k]['data'] = $goldData['list'] ?? [];
				// 未安装/未授权：标记隐藏，前端不渲染
				if(intval($goldData['plugin_ready'] ?? 0) !== 1){
					$pagecontent[$k]['params']['hidden'] = 1;
				}
			}elseif($v['temp'] == 'article'){//文章列表 获取文章信息
				if($v['params']['articlefrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $art){
                        $where = [];
                        $where[] = ['aid','=',$aid];
                        $where[] = ['status','=',1];
                        $field = 'id artid,name,subname,pic,author,readcount,sort,createtime,sendtime';
                        $newart = Db::name('article')
							->field($field)
							->where($where)->where('id',$art['artid'])
							->find();
						if($newart){
							$newart['id'] = $art['id'];
							$newart['sendtime'] = date('Y-m-d',$newart['createtime']);
							$newart['createtime'] = date('Y-m-d',$newart['createtime']);
							$newdata[] = $newart;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
                    $where = [];
                    $where[] = ['aid','=',$aid];
                    $where[] = ['status','=',1];
                    if($v['params']['category']){
						$cid = intval($v['params']['category']);
						$chidlc = Db::name('article_category')->where('aid',$aid)->where('pid',$cid)->select()->toArray();
						if($chidlc){
							$cids = array($cid);
							foreach($chidlc as $c){
								$cids[] = intval($c['id']);
							}
							$where[] = ['cid','in',$cids];
						}else{
							$where[] = ['cid','=',$cid];
						}
					}
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where[] = ['bid','=',$v['params']['bid']];
					}
					if($v['params']['group']){
						$_string = array();
						foreach($v['params']['group'] as $gid=>$istrue){
							$gid = strval($gid);
							if($istrue=='true'){
								if($gid == 'all'){
									$_string[] = "1=1";
								}elseif($gid == '0'){
									$_string[] = "gid is null or gid=''";
								}else{
									$_string[] = "find_in_set({$gid},gid)";
								}
							}
						}
						if(!$_string){
							$where2 = '0=1';
						}else{
							$where2 = implode(" or ",$_string);
						}
					}else{
						$where2 = '1=1';
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'sendtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'sendtime') $order = 'createtime';
					if($v['params']['sortby'] == 'readcount') $order = 'readcount desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$field = 'id artid,name,subname,pic,author,readcount,sort,createtime,sendtime';
                    $result = Db::name('article')->field($field)->where($where)->where($where2)->order($order)->limit($v['params']['shownum'])->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['sendtime'] = date('Y-m-d',$v2['createtime']);
						$result[$k2]['createtime'] = date('Y-m-d',$v2['createtime']);
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
						}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'huodong'){//活动推广 处理进度条和倒计时
				// 遍历每个活动
				if(isset($v['data']) && is_array($v['data'])){
					foreach($v['data'] as $k2=>$activity){
						
						// 处理任务活动进度 - 直接根据 task_activity_id 计算
						if(isset($activity['showprogress']) && $activity['showprogress'] && isset($activity['task_activity_id']) && $activity['task_activity_id']){
							$activityId = $activity['task_activity_id'];
							$activityIdStr = is_string($activityId) ? $activityId : strval($activityId);
							
							\think\facade\Log::write("[DIY任务进度] activity_id={$activityIdStr}, user_id={$mid}, aid={$aid}");
							
							// 检查是否是视频激励活动
							if(strpos($activityIdStr, 'vr_') === 0){
								// 视频激励活动
								try {
									$videoRewardActivityId = intval(substr($activityIdStr, 3));
									
									$tasks = Db::name('video_reward_task')
										->where('aid', $aid)
										->where('activity_id', $videoRewardActivityId)
										->where('status', 1)
										->column('id');
									
									if($tasks && $mid > 0){
										$completedCount = Db::name('video_reward_record')
											->where('aid', $aid)
											->where('activity_id', $videoRewardActivityId)
											->where('user_id', $mid)
											->where('task_id', 'in', $tasks)
											->where('is_completed', 1)
											->count();
										
										$totalCount = count($tasks);
										$progressPercent = $totalCount > 0 ? min(100, round(($completedCount / $totalCount) * 100)) : 0;
										
										\think\facade\Log::write("[DIY任务进度-视频激励] activity_id={$videoRewardActivityId}, 完成={$completedCount}/{$totalCount}, 进度={$progressPercent}%");
										
										$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
										$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
											'stat_type' => 'activity',
											'activity_source' => 'video_reward',
											'activity_id' => $videoRewardActivityId,
											'completed' => $completedCount,
											'total' => $totalCount,
											'percent' => $progressPercent
										];
									}
								} catch(\Exception $e) {
									\think\facade\Log::write("[DIY任务进度-视频激励] 查询出错: " . $e->getMessage());
								}
							}else{
								// 普通任务活动
								try {
									$tasks = Db::name('task_config')
										->where('aid', $aid)
										->where('activity_id', $activityId)
										->where('status', 1)
										->column('id');
									
									if($tasks && $mid > 0){
										$completedCount = Db::name('task_user_record')
											->where('aid', $aid)
											->where('user_id', $mid)
											->where('task_id', 'in', $tasks)
											->where('is_completed', 1)
											->count();
										
										$totalCount = count($tasks);
										$progressPercent = $totalCount > 0 ? min(100, round(($completedCount / $totalCount) * 100)) : 0;
										
										\think\facade\Log::write("[DIY任务进度-任务活动] activity_id={$activityId}, 完成={$completedCount}/{$totalCount}, 进度={$progressPercent}%");
										
										$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
										$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
											'stat_type' => 'activity',
											'activity_id' => $activityId,
											'completed' => $completedCount,
											'total' => $totalCount,
											'percent' => $progressPercent
										];
									}
								} catch(\Exception $e) {
									\think\facade\Log::write("[DIY任务进度-任务活动] 查询出错: " . $e->getMessage());
								}
							}
						}
						// 兼容旧版：处理 enabletask 和复杂的 task_stat_type 配置
						elseif(isset($activity['enabletask']) && $activity['enabletask']){
							$statType = isset($activity['task_stat_type']) ? $activity['task_stat_type'] : 'task';
							
							\think\facade\Log::write("[DIY任务进度-旧版兼容] 统计方式: {$statType}, user_id={$mid}, aid={$aid}");
							
							// 四种统计方式
							if($statType == 'activity_type' && isset($activity['task_activity_type']) && $activity['task_activity_type']){
								// 按活动类型统计
								$activityType = $activity['task_activity_type'];
								
								// 视频激励活动类型
								if($activityType == 'video_reward'){
									try {
										// 获取所有启用的视频激励活动
										$vrActivities = Db::name('video_reward_activity')
											->where('aid', $aid)
											->where('status', 1)
											->column('id');
										
										if($vrActivities){
											// 获取这些活动下的所有视频任务
											$tasks = Db::name('video_reward_task')
												->where('aid', $aid)
												->where('activity_id', 'in', $vrActivities)
												->where('status', 1)
												->column('id');
											
											if($tasks && $mid > 0){
												// 统计用户完成的任务数
												$completedCount = Db::name('video_reward_record')
													->where('aid', $aid)
													->where('user_id', $mid)
													->where('task_id', 'in', $tasks)
													->where('is_completed', 1)
													->count();
												
												$totalCount = count($tasks);
												$progressPercent = $totalCount > 0 ? min(100, round(($completedCount / $totalCount) * 100)) : 0;
												
												\think\facade\Log::write("[DIY任务进度-视频激励活动类型] 完成={$completedCount}/{$totalCount}, 进度={$progressPercent}%");
												
												$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
												$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
													'stat_type' => 'activity_type',
													'activity_type' => 'video_reward',
													'completed' => $completedCount,
													'total' => $totalCount,
													'percent' => $progressPercent
												];
											}else{
												\think\facade\Log::write("[DIY任务进度-视频激励活动类型] 未登录或无任务，保持原进度");
											}
										}
									} catch(\Exception $e) {
										\think\facade\Log::write("[DIY任务进度-视频激励活动类型] 查询出错: ".$e->getMessage());
									}
								}else{
									// 普通任务活动类型
									// 获取该类型下所有活动
									$activities = Db::name('task_activity')
										->where('aid', $aid)
										->where('type', $activityType)
										->where('status', 1)
										->column('id');
									
									if($activities){
										// 获取这些活动下的所有任务
										$tasks = Db::name('task_config')
											->where('aid', $aid)
											->where('activity_id', 'in', $activities)
											->where('status', 1)
											->column('id');
										
										if($tasks && $mid > 0){
											// 统计用户完成的任务数
											$completedCount = Db::name('task_user_record')
												->where('aid', $aid)
												->where('user_id', $mid)
												->where('task_id', 'in', $tasks)
												->where('is_completed', 1)
												->count();
											
											$totalCount = count($tasks);
											$progressPercent = $totalCount > 0 ? min(100, round(($completedCount / $totalCount) * 100)) : 0;
											
											\think\facade\Log::write("[DIY任务进度-活动类型] type={$activityType}, 完成={$completedCount}/{$totalCount}, 进度={$progressPercent}%");
											
											$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
											$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
												'stat_type' => 'activity_type',
												'activity_type' => $activityType,
												'completed' => $completedCount,
												'total' => $totalCount,
												'percent' => $progressPercent
											];
										}else{
											\think\facade\Log::write("[DIY任务进度-活动类型] 未登录或无任务，保持原进度");
										}
									}
								}
								
							}elseif($statType == 'task_type' && isset($activity['task_type_filter']) && $activity['task_type_filter']){
								// 按任务类型统计
								$taskTypeFilter = $activity['task_type_filter'];
								$taskTypes = explode(',', $taskTypeFilter);
								
								// 获取该任务类型的所有任务
								$tasks = Db::name('task_config')
									->where('aid', $aid)
									->where('task_type', 'in', $taskTypes)
									->where('status', 1)
									->column('id');
								
								if($tasks && $mid > 0){
									// 统计用户完成的任务数
									$completedCount = Db::name('task_user_record')
										->where('aid', $aid)
										->where('user_id', $mid)
										->where('task_id', 'in', $tasks)
										->where('is_completed', 1)
										->count();
									
									$totalCount = count($tasks);
									$progressPercent = $totalCount > 0 ? min(100, round(($completedCount / $totalCount) * 100)) : 0;
									
									\think\facade\Log::write("[DIY任务进度-任务类型] task_types={$taskTypeFilter}, 完成={$completedCount}/{$totalCount}, 进度={$progressPercent}%");
									
									$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
									$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
										'stat_type' => 'task_type',
										'task_types' => $taskTypeFilter,
										'completed' => $completedCount,
										'total' => $totalCount,
										'percent' => $progressPercent
									];
								}else{
									\think\facade\Log::write("[DIY任务进度-任务类型] 未登录或无任务，保持原进度");
								}
								
							}elseif($statType == 'activity' && isset($activity['task_activity_id']) && $activity['task_activity_id']){
								// 按活动ID统计
								$activityId = $activity['task_activity_id'];
								$activityIdStr = is_string($activityId) ? $activityId : strval($activityId);
								
								if(strpos($activityIdStr, 'vr_') === 0){
									$videoRewardActivityId = intval(substr($activityIdStr, 3));
									
									$tasks = Db::name('video_reward_task')
										->where('aid', $aid)
										->where('activity_id', $videoRewardActivityId)
										->where('status', 1)
										->column('id');
									
									if($tasks && $mid > 0){
										$completedCount = Db::name('video_reward_record')
											->where('aid', $aid)
											->where('activity_id', $videoRewardActivityId)
											->where('user_id', $mid)
											->where('task_id', 'in', $tasks)
											->where('is_completed', 1)
											->count();
										
										$totalCount = count($tasks);
										$progressPercent = $totalCount > 0 ? min(100, round(($completedCount / $totalCount) * 100)) : 0;
										
										\think\facade\Log::write("[DIY任务进度-视频激励活动] activity_id={$videoRewardActivityId}, 完成={$completedCount}/{$totalCount}, 进度={$progressPercent}%");
										
										$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
										$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
											'stat_type' => 'activity',
											'activity_source' => 'video_reward',
											'activity_id' => $videoRewardActivityId,
											'completed' => $completedCount,
											'total' => $totalCount,
											'percent' => $progressPercent
										];
									}else{
										\think\facade\Log::write("[DIY任务进度-视频激励活动] 未登录或无任务，保持原进度");
									}
								}else{
									// 获取该活动下所有任务
									$tasks = Db::name('task_config')
										->where('aid', $aid)
										->where('activity_id', $activityId)
										->where('status', 1)
										->column('id');
									
									if($tasks && $mid > 0){
										// 统计用户完成的任务数
										$completedCount = Db::name('task_user_record')
											->where('aid', $aid)
											->where('user_id', $mid)
											->where('task_id', 'in', $tasks)
											->where('is_completed', 1)
											->count();
										
										$totalCount = count($tasks);
										$progressPercent = $totalCount > 0 ? min(100, round(($completedCount / $totalCount) * 100)) : 0;
										
										\think\facade\Log::write("[DIY任务进度-活动] activity_id={$activityId}, 完成={$completedCount}/{$totalCount}, 进度={$progressPercent}%");
										
										$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
										$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
											'stat_type' => 'activity',
											'activity_id' => $activityId,
											'completed' => $completedCount,
											'total' => $totalCount,
											'percent' => $progressPercent
										];
									}else{
										\think\facade\Log::write("[DIY任务进度-活动] 未登录或无任务，保持原进度");
									}
								}
								
							}elseif($statType == 'task' && isset($activity['task_id']) && $activity['task_id']){
								// 按具体任务统计
								$taskId = $activity['task_id'];
								
								$taskIdStr = is_string($taskId) ? $taskId : strval($taskId);
								
								if(strpos($taskIdStr, 'vr_task_') === 0){
									$videoRewardTaskId = intval(substr($taskIdStr, 8));
									$task = Db::name('video_reward_task')->where('id',$videoRewardTaskId)->where('aid',$aid)->find();
									
									if($task && $mid > 0){
										$userTask = Db::name('video_reward_record')
											->where('aid',$aid)
											->where('task_id',$videoRewardTaskId)
											->where('user_id',$mid)
											->find();
										
										$targetValue = floatval($task['required_duration']);
										$currentProgress = $userTask ? floatval($userTask['watch_duration']) : 0;
										$isCompleted = $userTask ? intval($userTask['is_completed']) : 0;
										
										if($targetValue > 0){
											$progressPercent = min(100, round(($currentProgress / $targetValue) * 100));
											if($isCompleted == 1){
												$progressPercent = 100;
											}
										}else{
											$progressPercent = $isCompleted == 1 ? 100 : 0;
										}
										
										\think\facade\Log::write("[DIY任务进度-视频激励任务] task_id={$videoRewardTaskId}, current={$currentProgress}/{$targetValue}, 进度={$progressPercent}%");
										
										$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
										$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
											'stat_type' => 'task',
											'task_source' => 'video_reward',
											'task_id' => $videoRewardTaskId,
											'task_name' => $task['name'],
											'current' => $currentProgress,
											'target' => $targetValue,
											'percent' => $progressPercent
										];
									}else{
										\think\facade\Log::write("[DIY任务进度-视频激励任务] 无记录或任务不存在，保持原进度");
									}
								}else{
									// 获取任务信息
									$task = Db::name('task_config')->where('id',$taskId)->find();
									
									if($task && $mid > 0){
										// 获取用户任务记录
										$userTask = Db::name('task_user_record')
											->where('aid',$aid)
											->where('user_id',$mid)
											->where('task_id',$taskId)
											->find();
										
										// 如果有任务记录且目标值大于0，计算真实进度
										if($userTask && $task['target_value'] > 0){
											// 兼容不同的字段名
											$currentProgress = 0;
											if(isset($userTask['current_progress'])){
												$currentProgress = floatval($userTask['current_progress']);
											}elseif(isset($userTask['progress'])){
												$currentProgress = floatval($userTask['progress']);
											}
											
											$targetValue = floatval($task['target_value']);
											$progressPercent = min(100, round(($currentProgress / $targetValue) * 100));
											
											\think\facade\Log::write("[DIY任务进度-任务] task_id={$taskId}, current={$currentProgress}/{$targetValue}, 进度={$progressPercent}%");
											
											// 更新为真实进度
											$pagecontent[$k]['data'][$k2]['progress'] = $progressPercent;
											$pagecontent[$k]['data'][$k2]['task_progress_info'] = [
												'stat_type' => 'task',
												'task_id' => $taskId,
												'task_name' => $task['name'],
												'current' => $currentProgress,
												'target' => $targetValue,
												'percent' => $progressPercent
											];
										}else{
											\think\facade\Log::write("[DIY任务进度-任务] 无记录或目标为0，保持原进度");
										}
									}
								}
							}
						} // 结束任务进度处理
						
						// 处理红包活动倒计时关联
						if(isset($activity['showcountdown']) && $activity['showcountdown'] && isset($activity['countdown_source']) && $activity['countdown_source'] == 'redpacket'){
							$selectedActivityIds = [];
							if(isset($activity['redpacket_activity_ids']) && $activity['redpacket_activity_ids']){
								$rawIds = $activity['redpacket_activity_ids'];
								if(is_array($rawIds)){
									$isList = array_keys($rawIds) === range(0, count($rawIds) - 1);
									if($isList){
										foreach($rawIds as $val){
											if(is_numeric($val)){
												$selectedActivityIds[] = intval($val);
											}
										}
									}else{
										foreach($rawIds as $id=>$isSelected){
											if($isSelected === true || $isSelected === 'true' || $isSelected === 1 || $isSelected === '1'){
												if(is_numeric($id)){
													$selectedActivityIds[] = intval($id);
												}
											}
										}
									}
								}elseif(is_string($rawIds)){
									foreach(explode(',', $rawIds) as $id){
										$id = trim($id);
										if(is_numeric($id)){
											$selectedActivityIds[] = intval($id);
										}
									}
								}
							}
							if(empty($selectedActivityIds) && isset($activity['redpacket_activity_id']) && $activity['redpacket_activity_id']){
								$selectedActivityIds[] = intval($activity['redpacket_activity_id']);
							}
							$selectedActivityIds = array_values(array_unique(array_filter($selectedActivityIds)));
							
							if(!empty($selectedActivityIds)){
								try {
									$rpActivities = Db::name('member_redpacket_activity')
										->where('aid', $aid)
										->where('status', 1)
										->where('id', 'in', $selectedActivityIds)
										->select()
										->toArray();
									
									if($rpActivities){
										$now = time();
										$selectedRpActivity = null;
										$selectedEndTimestamp = 0;
										
										foreach($rpActivities as $rpActivity){
											$endTimestamp = 0;
											if($rpActivity['is_daily'] == 1){
												$todayDate = date('Y-m-d');
												$dailyEndTime = $rpActivity['daily_end_time'] ? $rpActivity['daily_end_time'] : '23:59';
												$endTimestamp = strtotime($todayDate . ' ' . $dailyEndTime . ':59');
												if($endTimestamp <= $now){
													$endTimestamp = strtotime('+1 day', strtotime($todayDate . ' ' . $dailyEndTime . ':59'));
												}
											}else{
												$endTimestamp = intval($rpActivity['end_time']);
												if($endTimestamp <= $now){
													$endTimestamp = 0;
												}
											}
											
											if($endTimestamp > 0 && ($selectedEndTimestamp == 0 || $endTimestamp < $selectedEndTimestamp)){
												$selectedEndTimestamp = $endTimestamp;
												$selectedRpActivity = $rpActivity;
											}
										}
										
										if($selectedRpActivity && $selectedEndTimestamp > 0){
											if($selectedRpActivity['is_daily'] == 1){
												$pagecontent[$k]['data'][$k2]['countdown_type'] = 'daily';
												$pagecontent[$k]['data'][$k2]['daily_start_time'] = $selectedRpActivity['daily_start_time'];
												$pagecontent[$k]['data'][$k2]['daily_end_time'] = $selectedRpActivity['daily_end_time'];
											}else{
												$pagecontent[$k]['data'][$k2]['countdown_type'] = 'fixed';
											}
											
											$pagecontent[$k]['data'][$k2]['endtime'] = date('Y-m-d H:i:s', $selectedEndTimestamp);
											$pagecontent[$k]['data'][$k2]['endtime_timestamp'] = $selectedEndTimestamp;
											
											$pagecontent[$k]['data'][$k2]['redpacket_info'] = [
												'id' => $selectedRpActivity['id'],
												'name' => $selectedRpActivity['name'],
												'is_daily' => $selectedRpActivity['is_daily']
											];
											
											$selectedIdsText = implode(',', $selectedActivityIds);
											\think\facade\Log::write("[DIY红包倒计时] selected_ids={$selectedIdsText}, chosen_id={$selectedRpActivity['id']}, is_daily={$selectedRpActivity['is_daily']}, endtime=" . date('Y-m-d H:i:s', $selectedEndTimestamp));
										}
									}
								} catch(\Exception $e) {
									\think\facade\Log::write("[DIY红包倒计时] 查询出错: " . $e->getMessage());
								}
							}
						}
					}
				}
			}elseif($v['temp'] == 'huodong_baoming'){//活动报名列表 获取活动信息
				$pagecontent[$k]['data'] = \app\plugin\activity\common\DiyListService::resolvePageData(intval($aid), $v);
			}elseif($v['temp'] == 'holiday'){//节日倒计时 获取节日信息
				$where = [];
				$where[] = ['aid','=',$aid];
				$where[] = ['status','=',1];
				$where[] = ['holiday_date','>=',strtotime(date('Y-m-d'))]; // 只显示今天及以后的节日

				$order = 'holiday_date asc'; // 默认按日期升序排列
				if($v['params']['sortby'] == 'importance') $order = 'importance desc,holiday_date asc';
				if($v['params']['sortby'] == 'createtime') $order = 'createtime desc';

				$shownum = intval($v['params']['shownum']) ?: 5;

				$result = Db::name('holiday')
					->field('id,name,holiday_date,description,icon,importance,createtime')
					->where($where)
					->order($order)
					->limit($shownum)
					->select()
					->toArray();

				if(!$result) $result = array();

				$now = time();
				foreach($result as $k2=>$v2){
					$result[$k2]['holiday_date_format'] = date('Y-m-d', $v2['holiday_date']);
					$result[$k2]['holiday_date_text'] = date('m月d日', $v2['holiday_date']);

					// 计算倒计时天数
					$days_diff = ceil(($v2['holiday_date'] - $now) / 86400);
					if($days_diff == 0){
						$result[$k2]['countdown_text'] = '今天';
						$result[$k2]['countdown_days'] = 0;
					} else if($days_diff == 1){
						$result[$k2]['countdown_text'] = '明天';
						$result[$k2]['countdown_days'] = 1;
					} else {
						$result[$k2]['countdown_text'] = '还有' . $days_diff . '天';
						$result[$k2]['countdown_days'] = $days_diff;
					}

					// 设置默认图标
					if(empty($v2['icon'])){
						$result[$k2]['icon'] = PRE_URL.'/static/img/holiday-default.png';
					}

					// 生成唯一ID
					$result[$k2]['id'] = 'H'.time().rand(10000000,99999999);
				}

				$pagecontent[$k]['data'] = $result;
			}elseif($v['temp'] == 'coupon'){//优惠券
				if($v['params']['couponfrom'] == 0){//手动选择
					$newdata = array();
					foreach($v['data'] as $cp){
						$newcp = Db::name('coupon')->field('id couponid,type,limit_count,price,name,money,minprice,starttime,endtime,score')->where('aid',$aid)->where('id',$cp['couponid'])->find();
						if($newcp){
							$newart['id'] = $cp['id'];
							$newdata[] = $newcp;
						}
					}
					$pagecontent[$k]['data'] = $newdata;
				}else{
					$time = time();
					$where = "aid={$aid} and unix_timestamp(starttime)<={$time} and unix_timestamp(endtime)>={$time}";
					if($v['params']['bid']!=='' && $v['params']['bid']!==null){
						$where .= ' and bid='.$v['params']['bid'];
					}
					$order = 'sort desc';
					if($v['params']['sortby'] == 'sort') $order = 'sort desc,id desc';
					if($v['params']['sortby'] == 'createtimedesc') $order = 'createtime desc';
					if($v['params']['sortby'] == 'createtime') $order = 'createtime';
					if($v['params']['sortby'] == 'stock') $order = 'stock desc,sort desc';
					if($v['params']['sortby'] == 'rand') $order = Db::raw('rand()');
					$result = Db::name('coupon')->field('id couponid,type,limit_count,price,name,money,minprice,starttime,endtime,score')->where($where)->order($order)->limit($v['params']['shownum'])->select()->toArray();
					if(!$result) $result = array();
					foreach($result as $k2=>$v2){
						$result[$k2]['id'] = 'G'.time().rand(10000000,99999999);
					}
					$pagecontent[$k]['data'] = $result;
				}
			}elseif($v['temp'] == 'form'){//表单信息
				$formdata = Db::name('form')->where('aid',$aid)->where('id',$v['data']['id'])->find();
				if($formdata){
					$formcontent = json_decode($formdata['content'],true);
					if($v['params']['isquery'] == '1'){
						$newformdata = [];
						foreach($formcontent as $fk=>$fv){
							if($fv['query'] == '1'){
								$fv['val3'] = '0';
								$newformdata[] = $fv;
							}
						}
						$formcontent = $newformdata;
						$formdata['payset'] = '0';
					}
					$formdata['content'] = $formcontent;
					//表单支付明细
                    }else{
					$formdata = '';
				}
				$pagecontent[$k]['data'] = $formdata;
				if($mid != -1 && strtotime($formdata['starttime']) > time() && $v['params']['wkstpis']){
					die(jsonEncode(['status'=>-4,'msg'=>$v['params']['wkstpis']]));
				}
            }elseif($v['temp'] == 'form-log' && getcustom('form_log_plug')){//表单信息
                $formdata = Db::name('form')->where('aid',$aid)->where('id',$v['data']['id'])->field('id,name')->find();
                if($formdata){
                    $formdata['count'] = 0 + \db('form_order')->where('formid',$formdata['id'])->count();
                    $log = \db('form_order')->where('formid',$formdata['id'])->order('id', 'desc')->field('id,mid,createtime')->find();
                    if($log){
                        $logmember = \db('member')->where('id',$log['mid'])->field('nickname,realname')->find();
                        $log['nickname'] = $logmember['nickname'];
                        $log['realname'] = $logmember['realname'];
                        $log['time'] = date('Y-m-d H:i',$log['createtime']);
                    }

                    $formdata['log'] = $log;
                }else{
                    $formdata = ['count'=>0,'name'=>'','log'=>[]];
                }
                $pagecontent[$k]['data'] = $formdata;
			}elseif($v['temp'] == 'menu'){//按钮导航
				$data = $v['data'];
				$newdata = [];
				if(!$v['params']['pernum']){
					$v['params']['pernum'] = 10;
					$pagecontent[$k]['params']['pernum'] = 10;
				}
				$pagecount = ceil(count($data)/$v['params']['pernum']);
				if(!$pagecount) $pagecount = 1;
				for($i=0;$i<$pagecount;$i++){
					$newdata[$i]=array_slice($data,$v['params']['pernum']*$i,$v['params']['pernum']);
				}
				if($v['params']['showicon']==0){
					$pagecontent[$k]['params']['iconsize'] = 0;
				}
				//dump($newdata);
				$pagecontent[$k]['params']['newdata'] = $newdata;
				$pagecontent[$k]['params']['newdata_linenum'] = ceil($v['params']['pernum']/$v['params']['num']);
			}elseif($v['temp'] == 'shop'){
				if($v['params']['bid'] == 0){
					$shopinfo = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,tel,kfurl')->find();
					if($shopinfo){
						$shopinfo['desc'] = self::plainText($shopinfo['desc'] ?? '');
					}
				}else{
					$business = Db::name('business')->where('id',$v['params']['bid'])->find();
					$shopinfo = ['name'=>$business['name'],'logo'=>$business['logo'],'desc'=>$business['address'],'tel'=>$business['tel']];
				}
				$pagecontent[$k]['shopinfo'] = $shopinfo;
            }elseif($v['temp'] == 'jidian'){
                $jidian = ['name'=>''];
                if($v['params']['bid'] > 0) {
                    $set = Db::name('jidian_set')->where('aid', aid)->where('bid', $v['params']['bid'])->find();
                    if ($set && $set['status'] == 1) {
                        $jidianNum= self::getOrderNumFromJidian(aid,$v['params']['bid'],$set,$mid);
                        $jidian = [
                            'name' => $set['name'],
                            'bid' => $set['bid'],
                            'reward_name' => $jidianNum['reward_name'],
                            'reward_num' => $jidianNum['reward_num'],
                            'have_num' => $jidianNum['have_num'],
                            'total_num' => $jidianNum['total_num'],
                        ];
                    }
                }
                $pagecontent[$k]['data'] = $jidian;
			}elseif($v['temp'] == 'userinfo'){
                $zhaopin['show_zhaopin']  = 0;
                $zichanfenqiReady = self::isZichanfenqiUserinfoReady();
                $zhuibaoagentReady = self::isZhuibaoagentUserinfoReady();
                if($zhuibaoagentReady){
                    if(!isset($v['params']['zhuibao_credit_show']) || $v['params']['zhuibao_credit_show'] === ''){
                        $v['params']['zhuibao_credit_show'] = '1';
                    }
                    $currentZhuibaoLabel = trim(strval($v['params']['zhuibao_credit_label'] ?? ''));
                    if($currentZhuibaoLabel === '' || in_array($currentZhuibaoLabel, ['算力', '算力点'], true)){
                        $v['params']['zhuibao_credit_label'] = self::getZhuibaoagentCreditLabel();
                    }
                    if(!isset($v['params']['zhuibao_credit_hrefurl'])){
                        $v['params']['zhuibao_credit_hrefurl'] = '';
                    }
                    $pagecontent[$k]['params'] = $v['params'];
                }
				if($mid > 0){
                    if($v['params']['ordershow']) {
                        $orderBaseWhere = [
                            ['aid','=',aid],
                            ['mid','=',$mid],
                            ['delete','=',0],
                        ];
                        $count0 = 0 + Db::name('shop_order')->where(array_merge($orderBaseWhere, [['status','=',0]]) )->count();
                        $count1 = 0 + Db::name('shop_order')->where(array_merge($orderBaseWhere, [['status','=',1]]) )->count();
                        $count2 = 0 + Db::name('shop_order')->where(array_merge($orderBaseWhere, [['status','=',2]]) )->count();
                        $count4 = 0 + Db::name('shop_refund_order')->where('aid',aid)->where('mid',$mid)->where('refund_status',1)->count();
                        $orderinfo = ['count0'=>$count0,'count1'=>$count1,'count2'=>$count2,'count4'=>$count4];
                    }
                    $field = 'id,levelid,levelendtime,nickname,headimg,sex,realname,tel,weixin,aliaccount,country,province,city,area,address,birthday,createtime,bankcardnum,bankname,bankcarduser,money,commission,commission2,score,xiaofeib,duihuanfen,paypwd,card_id,card_code,aid,pid,ktnum,kuang_num,yqcode,temp_frozen_money';
                    $userinfo = Db::name('member')->field($field)->where('id',$mid)->find();
                    $userinfo['money'] = \app\admin\common\Member::getmoney($userinfo);
                    $userinfo['score'] = \app\admin\common\Member::getscore($userinfo);
                    
                    // 初始化推荐人相关信息为空或默认值
                    $userinfo['referral_name'] = '';
                    $userinfo['referral_avatar'] = PRE_URL.'/static/img/touxiang.png'; // 默认头像
                    $userinfo['referral_detail'] = null;

                    // 查询上级推荐人信息
                    if (!empty($userinfo['pid']) && $userinfo['pid'] > 0) {
                        $referral_field = 'id,nickname,headimg,tel,weixin'; // 根据需要选择字段
                        $referral_user = Db::name('member')->field($referral_field)->where('id', $userinfo['pid'])->find();
                        if ($referral_user) {
                            $userinfo['referral_name'] = $referral_user['nickname'];
                            $userinfo['referral_avatar'] = $referral_user['headimg'] ?: PRE_URL.'/static/img/touxiang.png';
                            // 为了弹窗显示更多信息，可以传递整个推荐人对象或选定字段
                            $userinfo['referral_detail'] = [
                                'nickname' => $referral_user['nickname'],
                                'weixin'   => $referral_user['weixin'],
                                'tel'      => $referral_user['tel']
                                // 您可以根据弹窗需要添加更多字段
                            ];
                        }
                    }
                    
                    // 资产字段始终注入真实值，显示开关只控制前端是否渲染卡片，
                    // 避免 customasset_formula 引用了隐藏字段后被强制算成 0。
                    $userinfo['scorehuang'] = \app\admin\common\Member::getscorehuang($userinfo);
                    
                    $userinfo['heiscore'] = \app\admin\common\Member::getscoheijifen($userinfo);
                    $userinfo['sunshijine'] = \app\admin\common\Member::getsunshijine($userinfo);
                    $userinfo['contribution_num'] = \app\admin\common\Member::getgongxianzhi($userinfo);
                    $userinfo['syc_num'] = \app\admin\common\Member::getshouyichi($userinfo);
                    
                    $userinfo['peizi'] = \app\admin\common\Member::getpeizi($userinfo);

                    $userinfo['zichanfenqi_total_target_amount'] = 0;
                    $userinfo['zichanfenqi_granted_amount'] = 0;
                    $userinfo['zichanfenqi_pending_amount'] = 0;
                    if($zichanfenqiReady){
                        $zichanfenqiSummary = Db::name('zichanfenqi_plan')
                            ->where('aid', aid)
                            ->where('mid', $mid)
                            ->fieldRaw(
                                'COALESCE(SUM(target_total_amount),0) AS total_target_amount,' .
                                'COALESCE(SUM(granted_amount),0) AS granted_amount'
                            )
                            ->find();
                        $totalTargetAmount = self::normalizeUserinfoAssetAmount($zichanfenqiSummary['total_target_amount'] ?? 0);
                        $grantedAmount = self::normalizeUserinfoAssetAmount($zichanfenqiSummary['granted_amount'] ?? 0);
                        $pendingAmount = bcsub($totalTargetAmount, $grantedAmount, 5);
                        if (bccomp($pendingAmount, '0', 5) < 0) {
                            $pendingAmount = '0.00000';
                        }
                        $userinfo['zichanfenqi_total_target_amount'] = self::trimUserinfoAssetAmount($totalTargetAmount);
                        $userinfo['zichanfenqi_granted_amount'] = self::trimUserinfoAssetAmount($grantedAmount);
                        $userinfo['zichanfenqi_pending_amount'] = self::trimUserinfoAssetAmount($pendingAmount);
                    }

                    // 资产分期展示口径：按组件配置的取值方式/除以系数/保留小数位算好展示值，
                    // 前端只负责展示，不再写死除数。原始三项金额保持真实值不变。
                    $userinfo['zichanfenqi_display_value'] = self::buildZichanfenqiDisplayValue($v['params'] ?? [], $userinfo);
                    $userinfo = array_merge($userinfo, $zhuibaoagentReady ? self::getZhuibaoagentUserinfoCredit(intval($mid)) : [
                        'zhuibaoagent_ready' => 0,
                        'zhuibao_credit_total' => 0,
                        'zhuibao_credit_used' => 0,
                        'zhuibao_credit_balance' => 0,
                        'zhuibao_credit_frozen' => 0,
                        'zhuibao_member_status_text' => '',
                    ]);
                    
                    // 只有在设置显示时才获取绿积分
                    if(isset($v['params']['lvjifenshow']) && $v['params']['lvjifenshow'] == 1) {
                        $userinfo['scorelv'] = \app\admin\common\Member::getscorelv($userinfo);
                    } else {
                        $userinfo['scorelv'] = 0;
                    }
                    
                    // 只有在设置显示时才获取积分3
                    if(isset($v['params']['score3show']) && $v['params']['score3show'] == 1) {
                        $userinfo['score3'] = \app\admin\common\Member::getscore3($userinfo);
                    } else {
                        $userinfo['score3'] = 0;
                    }

                    // 消费币为会员表直存字段，这里按显示开关统一兜底
                    if(isset($v['params']['xiaofeibshow']) && $v['params']['xiaofeibshow'] == 1) {
                        $userinfo['xiaofeib'] = floatval($userinfo['xiaofeib'] ?? 0);
                    } else {
                        $userinfo['xiaofeib'] = 0;
                    }
                    
                    $userinfo['guquan'] = \app\admin\common\Member::getguquan($userinfo);
                    
                    // 只有在设置显示时才获取信用分
                    if(isset($v['params']['creditshow']) && $v['params']['creditshow'] == 1) {
                        $userinfo['credit_score'] = \app\admin\common\Member::getcreditscore($userinfo);
                    } else {
                        $userinfo['credit_score'] = 0;
                    }
                    
                    // 获取已入职的职位数量
                    $userinfo['job_count'] = \app\admin\common\Member::getjobcount($userinfo);
                    // 获取分销团队数量
                    $userinfo['team_count'] =  \app\admin\common\Member::getteamcount($userinfo);
                    
                    // 获取会员标签信息（包括勋章图片）
                    $userinfo['member_tags'] = \app\admin\common\MemberTagService::getMemberTags($aid, $mid);
                    
                    $userlevel = [];
                    if($v['params']['levelshow']) {
                        $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$userinfo['levelid'])->find();
                        if(!$userlevel) $userlevel = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
                        //扩展等级
                        $userlevelList = [];
                        }
					$sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,tel,recharge,reg_invite_code,reg_invite_code_type')->find();

					//优惠券数
                    if($v['params']['couponshow']) {
                        $userinfo['couponcount'] = Db::name('coupon_record')->where('aid',aid)->where('mid',$mid)->where('status',0)->where('endtime','>=',time())->count();
                    }
					if($v['params']['formshow']) {
                        $userinfo['formcount'] = Db::name('form_order')->where('aid',aid)->where('mid',$mid)->where('status',1)->count();
                    }
                    //开卡链接
                    if($v['params']['cardshow']) {
                        $membercard = Db::name('membercard')->where('aid',aid)->where('status',1)->order('id desc')->find();
                        $card_returl = $membercard['ret_url'];
                        $card_id = $membercard['card_id'];
                    } else {
                        $card_returl = '';
                        $card_id = '';
                    }
                    
                    // 2025-01-03 22:55:53,565-INFO-[dp-userinfo][parent_show_logic_001] 根据模板参数设置parent_show值
                    $parent_show = isset($v['params']['parent_show']) && $v['params']['parent_show'] == 1 ? true : false;
                    
                    // 2025-01-03 22:55:53,565-INFO-[dp-userinfo][parent_data_init_001] 初始化parent数据，兼容旧版显示方式
                    $parent = null;
                    if (!empty($userinfo['pid']) && $userinfo['pid'] > 0) {
                        $parent_field = 'id,nickname,headimg,tel,weixin';
                        $parent = Db::name('member')->field($parent_field)->where('id', $userinfo['pid'])->find();
                    }
                    
                    // 初始化订单信息
                    if(!isset($orderinfo)) {
                        $orderinfo = [];
                    }
                    
                    }else{
					$userinfo = ['id'=>0,'nickname'=>'未登录','headimg'=>PRE_URL.'/static/img/touxiang.png','money'=>0,'score'=>0,'duihuanfen'=>0,'couponcount'=>0,'kuang_num'=>0];
					$userinfo['zichanfenqi_total_target_amount'] = 0;
					$userinfo['zichanfenqi_granted_amount'] = 0;
					$userinfo['zichanfenqi_pending_amount'] = 0;
					$userinfo['zichanfenqi_display_value'] = self::buildZichanfenqiDisplayValue($v['params'] ?? [], $userinfo);
					$userinfo = array_merge($userinfo, $zhuibaoagentReady ? self::getZhuibaoagentUserinfoCredit(0) : [
						'zhuibaoagent_ready' => 0,
						'zhuibao_credit_total' => 0,
						'zhuibao_credit_used' => 0,
						'zhuibao_credit_balance' => 0,
						'zhuibao_credit_frozen' => 0,
						'zhuibao_member_status_text' => '',
					]);
					$userlevel = Db::name('member_level')->where('aid',aid)->where('isdefault',1)->find();
					$orderinfo = [];
					$card_returl = '';
					$card_id = '';
                    
                    // 2025-01-03 22:55:53,565-INFO-[dp-userinfo][parent_show_logic_002] 未登录状态也根据配置设置parent_show
                    $parent_show = isset($v['params']['parent_show']) && $v['params']['parent_show'] == 1 ? true : false;
                    
                    // 2025-01-03 22:55:53,565-INFO-[dp-userinfo][parent_data_init_002] 未登录状态parent为null
                    $parent = null;
				}
				$pagecontent[$k]['data'] = ['sysset'=>$sysset,'userinfo'=>$userinfo,'userlevel'=>$userlevel,'userlevelList' => $userlevelList, 'orderinfo'=>$orderinfo,'card_returl'=>$card_returl,'card_id'=>$card_id,'parent' => $parent, 'parent_show'=>$parent_show,'zhaopin'=>$zhaopin];
			}elseif($v['temp'] == 'wallet'){
				$walletMoney = '0.00';
				$walletCouponCount = 0;
				if($mid > 0){
					$walletMember = Db::name('member')->where('aid', aid)->where('id', $mid)->field('id,money')->find();
					if($walletMember){
						$walletMoney = \app\admin\common\Member::getmoney($walletMember);
					}
					$walletCouponCount = 0 + Db::name('coupon_record')->where('aid', aid)->where('mid', $mid)->where('status', 0)->where('endtime', '>=', time())->count();
				}
				$pagecontent[$k]['data'] = [
					'money' => $walletMoney,
					'couponcount' => $walletCouponCount,
				];
			}elseif($v['temp'] == 'daojia_order'){
				if (!self::isDaojiaMemberOrderReady(intval($aid))) {
					$pagecontent[$k]['data'] = [
						'ready' => 0,
						'wait_pay' => 0,
						'wait_service' => 0,
						'wait_comment' => 0,
					];
				} else {
					$pagecontent[$k]['data'] = self::buildDaojiaMemberOrderCounts(intval($aid), intval($mid));
				}
			}elseif($v['temp'] == 'diancan_order'){
				if (!self::isDiancanMemberOrderReady(intval($aid))) {
					$pagecontent[$k]['data'] = [
						'ready' => 0,
						'wait_pay' => 0,
						'wait_service' => 0,
						'wait_comment' => 0,
					];
				} else {
					$pagecontent[$k]['data'] = self::buildDiancanMemberOrderCounts(intval($aid), intval($mid));
				}
			}elseif(in_array($v['temp'], ['yuyue_order', 'hotel_order', 'venues_order', 'baoming_order', 'xixie_order', 'theater_order'], true)){
				$pagecontent[$k]['data'] = self::buildPluginMemberOrderCounts(intval($aid), intval($mid), strval($v['temp']));
			}elseif($v['temp'] == 'search'){
                $v['params']['image_search'] = 0;
                
                //添加搜索框必要数据
                //var_dump($pagecontent);
                //$v['params']：后台配置的模板参数
                $admin_set = Db::name('admin_set')->where(['aid'=>aid])->field('area_on,area_set')->find();
                $area_set = [];
                if($admin_set['area_set']) $area_set = json_decode($admin_set['area_set'],true);
                $v['params']['area_config'] = $area_set;
                
                
                if($admin_set['area_on']){
                    
                    //获取所有区域数据
                    $areamode = $area_set['areamode'];
            	    $areas =  Db::name('area')->where(['level'=>intval($areamode)])->select()->toArray();
            	    //按首字母分组
            	    usort($areas, function($a, $b) {
                        return strcmp($a['first_letter'], $b['first_letter']);
                    });
                    
                    $groups = [];
                    foreach ($areas as $item) {
                        $initial = $item['first_letter'];
                        if (!isset($groups[$initial])) {
                            $groups[$initial] = [];
                        }
                        $groups[$initial][] = $item;
                    }
            	    ksort($groups);
            	    $v['params']['areas'] = $groups;
                    
                    //if(isset($v['params']['areamode']))unset($v['params']['areamode']);
                    //if(isset($v['params']['areamode_str']))unset($v['params']['areamode_str']);
                    //模板配置ID，需要根据这个查询商户，商品权限
                    $v['params']['template_setting_id'] = template_setting_id;
                    
                    
                    //像前端输出当前区域ID和名称
                    $area_id = input('param.area_id/d');
                    $current_area_name = '';
                    
                    // 如果没有 area_id，尝试通过经纬度获取
                    if(!$area_id){
                        $lat = $latitude ?: input('param.latitude');
                        $lng = $longitude ?: input('param.longitude');
                        
                        if($lat && $lng){
                            $locationError = '';
                            $locationInfo = AreaMultiCityService::getLocationInfoByGlobalMap(aid, $lat, $lng, $locationError);
                            if($locationInfo){
                                $areamode = intval($area_set['areamode'] ?? 0);
                                $current_area_name = AreaMultiCityService::getAreaNameByMode($locationInfo, $areamode);
                                
                                // 根据区域名称查找 area_id
                                if($current_area_name){
                                    $areaInfo = Db::name('area')->where(['name'=>$current_area_name, 'level'=>$areamode])->find();
                                    if($areaInfo){
                                        $area_id = $areaInfo['id'];
                                    }
                                }
                            }
                        }
                        
                        // 如果还是没有，使用默认区域
                        if(!$area_id){
                            switch(intval($area_set['areamode'] ?? 0)){
                                case 0:
                                    $area_id = $area_set['defaultprovince'] ?? 0;
                                    $current_area_name = $area_set['defaultprovince_name'] ?? '';
                                    break;
                                case 1:
                                    $area_id = $area_set['defaultcity'] ?? 0;
                                    $current_area_name = $area_set['defaultcity_name'] ?? '';
                                    break;
                                case 2:
                                    $area_id = $area_set['defaultdistrict'] ?? 0;
                                    $current_area_name = $area_set['defaultdistrict_name'] ?? '';
                                    break;
                            }
                        }
                    }
                    
                    if($area_id){
                        $v['params']['current_area_id'] = $area_id;
                        // 如果已经有区域名称就用，否则从数据库查询
                        $v['params']['current_area_name'] = $current_area_name ?: Db::name('area')->where(['id'=>$area_id])->value('name');
                    }else{
                        $v['params']['current_area_id'] = 0;
                        // 使用默认区域名称
                        $v['params']['current_area_name'] = $area_set['defaultcity_name'] ?? '选择城市';
                    }
                    
                }
                
                
                $pagecontent[$k]['params'] = $v['params'];
			}elseif($v['temp'] == 'supervipcard'){
            	$userinfo = Db::name('member')->field($field)->where('id',$mid)->find();

            	// 检查多商户用户余额数据表是否存在
            	$tableName = config('database.connections.mysql.prefix') . 'business_member';
            	$is_exist = Db::query('SHOW TABLES LIKE "'. $tableName .'"');
            	if($is_exist){
            		$money =  Db::name('business_member')->field("totalmoney")->where('mid', $mid)->count();
            	}else{
            		$money = \app\admin\common\Member::getmoney($userinfo);
            	}

            	$score = \app\admin\common\Member::getscore($userinfo);
            	$coupon = Db::name('coupon_record')->where('aid',aid)->where('mid',$mid)->where('status',0)->where('endtime','>=',time())->count();

            	foreach ($pagecontent[$k]['data'] as $key => $value) {
            		switch ($value['id']) {
            			case 'VIP000000000001':
            				$pagecontent[$k]['data'][$key]['value'] = $money;
            				break;
            			case 'VIP000000000002':
            				$pagecontent[$k]['data'][$key]['value'] = $score;
            				break;
            			case 'VIP000000000003':
            				$pagecontent[$k]['data'][$key]['value'] = $coupon;
            				break;
            		}
            	}
            }elseif($v['temp'] == 'referral'){
                // 我的推荐人组件
                if($mid > 0){
                    // 获取用户信息
                    $field = 'id,nickname,headimg,levelid,pid';
                    $userinfo = Db::name('member')->field($field)->where('id',$mid)->find();
                    
                    // 初始化推荐人信息
                    $referral_info = [
                        'referral_id' => 0,
                        'referral_name' => '',
                        'referral_avatar' => PRE_URL.'/static/img/touxiang.png',
                        'referral_level' => '普通会员'
                    ];
                    
                    // 查询推荐人信息
                    if(!empty($userinfo['pid']) && $userinfo['pid'] > 0){
                        $referral_field = 'id,nickname,headimg,levelid,tel,weixin';
                        $referral_user = Db::name('member')->field($referral_field)->where('id', $userinfo['pid'])->find();
                        
                        if($referral_user){
                            $referral_info['referral_id'] = $referral_user['id'];
                            $referral_info['referral_name'] = $referral_user['nickname'];
                            $referral_info['referral_avatar'] = $referral_user['headimg'] ?: PRE_URL.'/static/img/touxiang.png';
                            
                            // 获取推荐人等级信息
                            $referral_level = Db::name('member_level')->where('aid',aid)->where('id',$referral_user['levelid'])->find();
                            if($referral_level){
                                $referral_info['referral_level'] = $referral_level['name'];
                            }
                            
                            // 保存联系方式
                            $referral_info['referral_tel'] = $referral_user['tel'] ?: '';
                            $referral_info['referral_weixin'] = $referral_user['weixin'] ?: '';
                        }
                    }
                }else{
                    // 未登录状态
                    $referral_info = [
                        'referral_id' => 0,
                        'referral_name' => '',
                        'referral_avatar' => PRE_URL.'/static/img/touxiang.png',
                        'referral_level' => '普通会员'
                    ];
                }
                
                $pagecontent[$k]['data'] = ['userinfo' => $referral_info];
            }elseif($v['temp'] == 'commission_header'){
                $pagecontent[$k]['data'] = \app\admin\common\CommissionCenterService::buildHeaderData(intval($aid), intval($mid), is_array($v['params'] ?? null) ? $v['params'] : []);
            }elseif($v['temp'] == 'commission_stat'){
                $pagecontent[$k]['data'] = \app\admin\common\CommissionCenterService::buildStatData(intval($aid), intval($mid), is_array($v['params'] ?? null) ? $v['params'] : []);
            }elseif($v['temp'] == 'membercard'){
            	// 会员卡组件
            	if($mid > 0){
            		// 获取会员信息
					$userinfo = Db::name('member')->field('id,nickname,levelid')->where('id',$mid)->find();
					
					// 获取会员等级信息
					$levelinfo = null;
					$is_max_level = false; // 是否最高等级标志
					
					if($userinfo && $userinfo['levelid'] > 0) {
						// 2025-01-03 22:55:53,603-INFO-[member_card_display][get_level_info_001] - 获取会员等级信息包含新增的卡片自定义字段
						$levelinfo = Db::name('member_level')
							->field('id,name,next_level_id,card_main_cover,card_sub_cover,card_primary_color,card_secondary_color,card_description,card_activate_url,card_activate_text,card_upgrade_btn_color,card_upgrade_btn_border_color,card_level_name_color,card_description_color')
							->where('id', $userinfo['levelid'])
							->find();
							
						if($levelinfo) {
							// 2025-01-03 22:55:53,604-INFO-[member_card_display][process_card_data_001] - 处理会员卡自定义数据
							// 设置默认值
							$levelinfo['card_primary_color'] = $levelinfo['card_primary_color'] ?: '';
							$levelinfo['card_secondary_color'] = $levelinfo['card_secondary_color'] ?: '';
							$levelinfo['card_activate_text'] = $levelinfo['card_activate_text'] ?: '立即激活';
							$levelinfo['card_upgrade_btn_color'] = $levelinfo['card_upgrade_btn_color'] ?: '#000000';
							$levelinfo['card_upgrade_btn_border_color'] = $levelinfo['card_upgrade_btn_border_color'] ?: '#333333';
							$levelinfo['card_level_name_color'] = $levelinfo['card_level_name_color'] ?: '';
							$levelinfo['card_description_color'] = $levelinfo['card_description_color'] ?: '';
							
							// 判断是否为最高等级
							if($levelinfo['next_level_id'] == 0 || $levelinfo['next_level_id'] == $levelinfo['id']) {
								$is_max_level = true;
								$levelinfo['is_max_level'] = true;
							} else {
								// 获取下一级别信息
								$nextlevel = Db::name('member_level')
									->field('id,name')
									->where('id', $levelinfo['next_level_id'])
									->find();
									
								if($nextlevel) {
									$levelinfo['next_level_name'] = $nextlevel['name'];
									$levelinfo['is_max_level'] = false;
								} else {
									// 下一级ID存在但找不到对应数据，视为最高级
									$levelinfo['is_max_level'] = true;
								}
							}
							
							// 获取当前等级下的所有会员人数（可用于显示排名）
							$levelinfo['level_member_count'] = Db::name('member')
								->where('aid', aid)
								->where('levelid', $levelinfo['id'])
								->count();
								
							// 获取当前会员在此等级的排名
							if($levelinfo['level_member_count'] > 0) {
								// 这里可以根据具体排名规则调整，默认使用会员ID作为排序依据
								$levelinfo['level_rank'] = Db::name('member')
									->where('aid', aid)
									->where('levelid', $levelinfo['id'])
									->where('id', '<=', $mid)
									->count();
							} else {
								$levelinfo['level_rank'] = 1;
							}
						}
					}
					
					// 获取会员卡信息
					//$card = Db::name('membercard')->where('aid',aid)->where('status',1)->field('id,card_id,title as card_name,bg_color,description,logo')->order('id desc')->find();
					
					if($card){
						// 获取会员卡领取状态
						$card_record = Db::name('membercard_record')->where('aid',aid)->where('mid',$mid)->where('card_id',$card['card_id'])->field('id,status,code')->find();
						
						if($card_record){
							$card['card_status'] = $card_record['status'] == 1 ? 'NORMAL' : 'INACTIVE';
							$card['card_code'] = $card_record['code'];
						}else{
							$card['card_status'] = 'NOT_RECEIVED';
							$card['card_code'] = '';
						}
					}
            	}else{
            		// 未登录状态
            		$userinfo = ['id'=>0,'nickname'=>'未登录','levelid'=>0];
            		$card = null;
					$levelinfo = null;
            	}
            	
				// 2025-01-03 22:55:53,605-INFO-[member_card_display][return_data_001] - 返回包含自定义字段的数据给前端
            	$pagecontent[$k]['data'] = [
            		'userinfo' => $userinfo,
            		'card' => $card,
					'levelinfo' => $levelinfo,
					'is_max_level' => $is_max_level ?? false
            	];
            }

			}
		return json_encode($pagecontent);
	}

	private static function normalizePageContent($pagecontent){
		$raw = trim((string)$pagecontent);
		if($raw === '' || $raw === '[]' || strtolower($raw) === 'null'){
			return [];
		}
		$decoded = json_decode($raw,true);
		if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
			return $decoded;
		}
		return [[
			'id' => 'M0000000000000',
			'temp' => 'richtext',
			'params' => [
				'bgcolor' => '#FFFFFF',
				'margin_x' => '0',
				'margin_y' => '0',
				'padding_x' => '10',
				'padding_y' => '10',
				'quanxian' => ['all' => true],
				'platform' => ['all' => true],
			],
			'data' => '',
			'other' => '',
			'content' => $raw,
		]];
	}

	public static function getOrderNumFromJidian($aid,$bid,$set,$mid,$num=0,$giveReward=false)
    {
//        $num = 0;
        $currentReward = [];//当前奖励
        $lastReward = [];
        //存在多笔消费，多个奖励的情况
        //统计下单数量
        $paygive_scene = explode(',',$set['paygive_scene']);
        $set['price_start'] = $set['price_start'] > 0 ? $set['price_start'] : 0;

        $where = [];
        $where[] = ['aid','=',$aid];
        $where[] = ['bid','=',$bid];
        $where[] = ['mid','=',$mid];
        $where[] = ['status','=',3];
        $where[] = ['totalprice','>=',$set['price_start']];
        if($set['days'] > 0)
            $where[] = ['createtime','>=',time()-$set['days']*86400];
        $where[] = ['createtime','between',[$set['starttime'],$set['endtime']]];
        if(in_array('shop',$paygive_scene) && $mid > 0){
            $num_shop = \db('shop_order')->where($where)->count();
        }
//        dd( Db::getLastSql());
        if(in_array('restaurant_shop',$paygive_scene) && $mid > 0){
            $num_restaurant_shop = \db('restaurant_shop_order')->where($where)->count();
        }
        if(in_array('restaurant_takeaway',$paygive_scene) && $mid > 0){
            $num_restaurant_takeaway = \db('restaurant_takeaway_order')->where($where)->count();
        }
        $num = $num + $num_shop + $num_restaurant_shop + $num_restaurant_takeaway;

        $setArr = json_decode($set['set'],true);

        //todo 减去已领奖励的订单（第n轮 N>1）
        $lastkey = count($setArr)-1;
        $num = $num % $setArr[$lastkey]['days'];
        if($num === 0) $num = $setArr[$lastkey]['days'];

        if($setArr){
            foreach ($setArr as $key => $item){
                if($item['coupon_id'] > 0 && $num >= $item['days']) {
                    $currentReward = $item;
                    //发放奖励
                    if($giveReward){
                        $record = \db('jidian_record')->where('aid',$aid)->where('bid',$bid)->where('mid',$mid)->order('id','desc')->find();
                        if(empty($record) || $record['jidian_num'] < $item['days']) {
                            $member = \db('member')->where('aid',$aid)->where('id',$mid)->find();
                            $data = [
                                'aid'=>$aid,
                                'bid'=>$bid,
                                'name' => $set['name'],
                                'mid' => $mid,
                                'headimg'=>$member['headimg'],
                                'nickname' => $member['nickname'],
                                'jidian_num' => $item['days'],
                                'coupon_ids' => $item['coupon_id'],
                                'createtime' => time(),
                                'createdate' => date('Y-m-d'),
                                'status' => 1
                            ];
                            \db('jidian_record')->insert($data);
                            \app\admin\common\Coupon::send($aid,$mid,$item['coupon_id']);
                        }
                    }
                }
                if($item['coupon_id'] > 0 && $item['days'] > 0) {
                    $lastReward = $item;
                }
            }
            if(empty($currentReward)){
                $currentReward = $setArr[0];
            }
            if($lastReward['days'] == $num){
                $currentReward = $setArr[0];
                $num = 0;
            }
        }
        return ['total_num'=>$lastReward['days'],'have_num'=>$num,'reward_name'=>$currentReward['coupon_name'],'reward_num'=>$currentReward['days']];
    }

	/**
     * 计算职位推荐佣金
     * @param array $job 职位信息
     * @param int $mid 会员ID
     * @return array 佣金信息
     */
    public static function calculateCommission($job, $mid = 0) {
        if(empty($job) || $mid <= 0 || $job['reward_type'] == -1) {
            return [];
        }
        
        // 记录计算佣金的日志
        trace(date('Y-m-d H:i:s').'-INFO-[System][calculateCommission_001] 开始计算职位佣金: '.json_encode([
            'position_id' => $job['zwid'] ?? $job['id'] ?? 0,
            'reward_type' => $job['reward_type']
        ], JSON_UNESCAPED_UNICODE), 'info');
        
        // 获取当前会员信息和会员等级
        $member = Db::name('member')
            ->alias('m')
            ->join('member_level l', 'm.levelid=l.id')
            ->where('m.id', $mid)
            ->field('m.id,m.levelid,m.nickname,l.name as level_name,
                    l.can_agent,l.commission1,l.commission2,l.commission3,
                    l.commissiontype')
            ->find();
            
        if (!$member) {
            return [];
        }
        
        // 薪资范围解析
        $salary_range = explode('-', $job['salary']);
        $salary_min = isset($salary_range[0]) ? intval($salary_range[0]) : 0;
        $salary_max = isset($salary_range[1]) ? intval($salary_range[1]) : $salary_min;
        
        $result = [
            'commission_min' => 0,
            'commission_max' => 0,
            'commission_desc' => '元'
        ];
        
        // 根据不同推荐奖励类型计算佣金
        switch ($job['reward_type']) {
            case 0: // 按照会员等级
                if ($member['can_agent'] == 0) {
                    return [];
                }
                
                // 判断会员等级的佣金计算方式
                if ($member['commissiontype'] == 1) { // 固定金额
                    $result['commission_min'] = $member['commission1'];
                    $result['commission_max'] = $member['commission1'];
                } else { // 比例计算
                    // 根据薪资计算佣金
                    $commission_min = round($salary_min * $member['commission1'] * 0.01, 2);
                    $commission_max = round($salary_max * $member['commission1'] * 0.01, 2);
                    
                    $result['commission_min'] = $commission_min;
                    $result['commission_max'] = $commission_max;
                }
                break;
                
            case 1: // 单独设置提成比例
                if ($member['can_agent'] == 0) {
                    return [];
                }
                
                // 解析职位中设置的佣金比例数据
                $reward_data = json_decode($job['reward_data1'], true);
                
                if (!$reward_data || !isset($reward_data[$member['levelid']])) {
                    return [];
                }
                
                // 获取当前会员等级的一级佣金比例
                $commission_rate = isset($reward_data[$member['levelid']]['1']) ? 
                                  $reward_data[$member['levelid']]['1'] : 0;
                
                // 根据薪资范围和佣金比例计算最终佣金
                $commission_min = round($salary_min * $commission_rate * 0.01, 2);
                $commission_max = round($salary_max * $commission_rate * 0.01, 2);
                
                $result['commission_min'] = $commission_min;
                $result['commission_max'] = $commission_max;
                $result['rate'] = $commission_rate;
                break;
                
            case 2: // 单独设置提成金额
                if ($member['can_agent'] == 0) {
                    return [];
                }
                
                // 解析职位中设置的固定金额数据
                $reward_data = json_decode($job['reward_data2'], true);
                
                if (!$reward_data || !isset($reward_data[$member['levelid']])) {
                    return [];
                }
                
                // 获取当前会员等级的一级固定佣金金额
                $commission = isset($reward_data[$member['levelid']]['1']) ? 
                             $reward_data[$member['levelid']]['1'] : 0;
                
                $result['commission_min'] = $commission;
                $result['commission_max'] = $commission;
                break;
        }
        
        // 佣金金额四舍五入到小数点后两位
        $result['commission_min'] = round($result['commission_min'], 2);
        $result['commission_max'] = round($result['commission_max'], 2);
        
        // 记录计算结果
        trace(date('Y-m-d H:i:s').'-INFO-[System][calculateCommission_002] 职位佣金计算结果: '.json_encode([
            'position_id' => $job['zwid'] ?? $job['id'] ?? 0,
            'member_id' => $member['id'],
            'level_id' => $member['levelid'],
            'level_name' => $member['level_name'],
            'result' => $result
        ], JSON_UNESCAPED_UNICODE), 'info');
        
        return $result;
    }
}
