<?php
namespace app\common;

use think\facade\Db;
use think\facade\Log;

/**
 * 城市代理公共类
 * 支持多身份和合伙代理功能
 */
class CityAgent
{
    /**
     * 代理级别定义
     */
    const LEVEL_CITY = 1;      // 市级代理
    const LEVEL_PROVINCE = 2;  // 省级代理
    const LEVEL_DISTRICT = 3;  // 区县代理
    const LEVEL_STREET = 4;    // 街道代理
    
    /**
     * 获取级别名称
     * @param int $level
     * @return string
     */
    public static function getLevelName($level)
    {
        $names = [
            self::LEVEL_CITY => '市级代理',
            self::LEVEL_PROVINCE => '省级代理',
            self::LEVEL_DISTRICT => '区县代理',
            self::LEVEL_STREET => '街道代理',
        ];
        return $names[$level] ?? '未知';
    }
    
    /**
     * 计算订单的城市代理佣金（支持多身份和合伙叠加）
     * 
     * @param int $aid 应用ID
     * @param array $order 订单信息
     * @param array $address 收货地址信息
     * @return array 佣金计算结果
     */
    public static function calculateCommission($aid, $order, $address)
    {
        $result = [
            'detail' => [],           // 每个代理的佣金明细
            'member_totals' => [],    // 按会员汇总的佣金
            'total_commission' => 0,  // 总佣金
        ];
        
        // 获取订单金额
        $orderAmount = floatval($order['total_price'] ?? $order['price'] ?? 0);
        if ($orderAmount <= 0) {
            return $result;
        }
        
        // 获取地址信息
        $provinceId = intval($address['province_id'] ?? $address['province'] ?? 0);
        $cityId = intval($address['city_id'] ?? $address['city'] ?? 0);
        $districtId = intval($address['district_id'] ?? $address['district'] ?? 0);
        $streetId = intval($address['street_id'] ?? $address['street'] ?? 0);
        
        // 查找所有匹配的代理（包括多身份和合伙代理）
        $matchedAgents = self::findMatchedAgents($aid, $provinceId, $cityId, $districtId, $streetId);
        
        if (empty($matchedAgents)) {
            return $result;
        }
        
        $totalCommission = 0;
        $memberTotals = [];
        
        foreach ($matchedAgents as $agent) {
            // 计算该代理的佣金
            $commission = bcmul($orderAmount, bcdiv($agent['commission_rate'], 100, 6), 2);
            
            if ($commission > 0) {
                $result['detail'][] = [
                    'agent_id' => $agent['id'],
                    'member_id' => $agent['member_id'],
                    'agent_name' => $agent['name'],
                    'agent_level' => $agent['agent_level'],
                    'agent_level_name' => self::getLevelName($agent['agent_level']),
                    'commission_rate' => $agent['commission_rate'],
                    'commission' => $commission,
                    'order_amount' => $orderAmount,
                    'is_partner' => $agent['is_partner'] ?? 0,
                    'partner_group_id' => $agent['partner_group_id'] ?? 0,
                ];
                
                // 按会员ID汇总（支持一个会员多身份叠加）
                $memberId = $agent['member_id'];
                if (!isset($memberTotals[$memberId])) {
                    $memberTotals[$memberId] = [
                        'member_id' => $memberId,
                        'total_commission' => 0,
                        'agent_count' => 0,
                        'details' => [],
                    ];
                }
                $memberTotals[$memberId]['total_commission'] = bcadd($memberTotals[$memberId]['total_commission'], $commission, 2);
                $memberTotals[$memberId]['agent_count']++;
                $memberTotals[$memberId]['details'][] = [
                    'agent_id' => $agent['id'],
                    'level_name' => self::getLevelName($agent['agent_level']),
                    'commission' => $commission,
                ];
                
                $totalCommission = bcadd($totalCommission, $commission, 2);
            }
        }
        
        $result['member_totals'] = $memberTotals;
        $result['total_commission'] = $totalCommission;
        
        return $result;
    }
    
    /**
     * 查找匹配的所有代理（支持同一区域多个代理-合伙场景）
     * 
     * @param int $aid 应用ID
     * @param int $provinceId 省份ID
     * @param int $cityId 城市ID
     * @param int $districtId 区县ID
     * @param int $streetId 街道ID
     * @return array 匹配的代理列表
     */
    public static function findMatchedAgents($aid, $provinceId, $cityId, $districtId, $streetId)
    {
        $matchedAgents = [];
        
        // 1. 查找省级代理（包括所有合伙代理）
        if ($provinceId > 0) {
            $provinceAgents = Db::name('city_agent')
                ->where('aid', $aid)
                ->where('agent_level', self::LEVEL_PROVINCE)
                ->where('status', 1)
                ->where(function($query) use ($provinceId) {
                    $query->whereRaw("JSON_CONTAINS(coverage_provinces, ?)", ['"'.$provinceId.'"'])
                          ->whereOr('coverage_provinces', 'like', '%"'.$provinceId.'"%')
                          ->whereOr('coverage_provinces', 'like', '%'.$provinceId.'%');
                })
                ->select()
                ->toArray();
            
            $matchedAgents = array_merge($matchedAgents, $provinceAgents);
        }
        
        // 2. 查找市级代理
        if ($cityId > 0) {
            $cityAgents = Db::name('city_agent')
                ->where('aid', $aid)
                ->where('agent_level', self::LEVEL_CITY)
                ->where('status', 1)
                ->where(function($query) use ($cityId) {
                    $query->whereRaw("JSON_CONTAINS(coverage_cities, ?)", ['"'.$cityId.'"'])
                          ->whereOr('coverage_cities', 'like', '%"'.$cityId.'"%')
                          ->whereOr('coverage_cities', 'like', '%'.$cityId.'%');
                })
                ->select()
                ->toArray();
            
            $matchedAgents = array_merge($matchedAgents, $cityAgents);
        }
        
        // 3. 查找区县代理
        if ($districtId > 0) {
            $districtAgents = Db::name('city_agent')
                ->where('aid', $aid)
                ->where('agent_level', self::LEVEL_DISTRICT)
                ->where('status', 1)
                ->where(function($query) use ($districtId) {
                    $query->whereRaw("JSON_CONTAINS(coverage_districts, ?)", ['"'.$districtId.'"'])
                          ->whereOr('coverage_districts', 'like', '%"'.$districtId.'"%')
                          ->whereOr('coverage_districts', 'like', '%'.$districtId.'%');
                })
                ->select()
                ->toArray();
            
            $matchedAgents = array_merge($matchedAgents, $districtAgents);
        }
        
        // 4. 查找街道代理
        if ($streetId > 0) {
            $streetAgents = Db::name('city_agent')
                ->where('aid', $aid)
                ->where('agent_level', self::LEVEL_STREET)
                ->where('status', 1)
                ->where(function($query) use ($streetId) {
                    $query->whereRaw("JSON_CONTAINS(coverage_streets, ?)", ['"'.$streetId.'"'])
                          ->whereOr('coverage_streets', 'like', '%"'.$streetId.'"%')
                          ->whereOr('coverage_streets', 'like', '%'.$streetId.'%');
                })
                ->select()
                ->toArray();
            
            $matchedAgents = array_merge($matchedAgents, $streetAgents);
        }
        
        return $matchedAgents;
    }
    
    /**
     * 发放代理佣金（支持多身份和合伙）
     * 
     * @param int $aid 应用ID
     * @param array $order 订单信息
     * @param array $commissionResult 佣金计算结果
     * @return bool
     */
    public static function grantCommission($aid, $order, $commissionResult)
    {
        if (empty($commissionResult['detail'])) {
            return false;
        }
        
        $orderId = $order['id'] ?? 0;
        $orderSn = $order['order_sn'] ?? $order['ordersn'] ?? '';
        
        Db::startTrans();
        try {
            foreach ($commissionResult['detail'] as $item) {
                $agentId = $item['agent_id'];
                $commission = $item['commission'];
                
                // 1. 增加代理余额和累计收入
                Db::name('city_agent')
                    ->where('id', $agentId)
                    ->inc('money', $commission)
                    ->inc('total_income', $commission)
                    ->update(['updatetime' => time()]);
                
                // 2. 记录收益明细
                Db::name('city_agent_income_log')->insert([
                    'aid' => $aid,
                    'agent_id' => $agentId,
                    'member_id' => $item['member_id'],
                    'order_id' => $orderId,
                    'order_sn' => $orderSn,
                    'order_amount' => $item['order_amount'],
                    'commission_rate' => $item['commission_rate'],
                    'commission' => $commission,
                    'agent_level' => $item['agent_level'],
                    'agent_level_name' => $item['agent_level_name'],
                    'is_partner' => $item['is_partner'],
                    'remark' => $item['agent_level_name'] . '佣金' . ($item['is_partner'] ? '(合伙)' : ''),
                    'createtime' => time(),
                ]);
            }
            
            Db::commit();
            Log::info('城市代理佣金发放成功，订单ID：' . $orderId . '，总佣金：' . $commissionResult['total_commission']);
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('城市代理佣金发放失败：' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取会员的所有代理身份
     * 
     * @param int $aid 应用ID
     * @param int $memberId 会员ID
     * @return array
     */
    public static function getMemberAgents($aid, $memberId)
    {
        $agents = Db::name('city_agent')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->order('agent_level asc')
            ->select()
            ->toArray();
        
        foreach ($agents as &$agent) {
            $agent['agent_level_name'] = self::getLevelName($agent['agent_level']);
            $agent['coverage_area_names'] = self::getCoverageAreaNames($agent);
        }
        
        return $agents;
    }
    
    /**
     * 获取会员的代理身份汇总统计
     * 
     * @param int $aid 应用ID
     * @param int $memberId 会员ID
     * @return array
     */
    public static function getMemberAgentSummary($aid, $memberId)
    {
        $agents = self::getMemberAgents($aid, $memberId);
        
        $summary = [
            'agent_count' => count($agents),
            'total_money' => '0.00',
            'total_income' => '0.00',
            'agents' => [],
        ];
        
        foreach ($agents as $agent) {
            $summary['total_money'] = bcadd($summary['total_money'], $agent['money'] ?? 0, 2);
            $summary['total_income'] = bcadd($summary['total_income'], $agent['total_income'] ?? 0, 2);
            $summary['agents'][] = [
                'id' => $agent['id'],
                'name' => $agent['name'],
                'agent_level' => $agent['agent_level'],
                'agent_level_name' => $agent['agent_level_name'],
                'commission_rate' => $agent['commission_rate'],
                'money' => number_format($agent['money'] ?? 0, 2, '.', ''),
                'total_income' => number_format($agent['total_income'] ?? 0, 2, '.', ''),
                'coverage_area_names' => $agent['coverage_area_names'],
                'is_partner' => $agent['is_partner'] ?? 0,
            ];
        }
        
        return $summary;
    }
    
    /**
     * 获取合伙分组列表
     * 
     * @param int $aid 应用ID
     * @return array
     */
    public static function getPartnerGroups($aid)
    {
        $groups = Db::name('city_agent')
            ->where('aid', $aid)
            ->where('is_partner', 1)
            ->where('partner_group_id', '>', 0)
            ->where('status', '<>', 2) // 排除禁用的
            ->group('partner_group_id')
            ->field('partner_group_id as id, agent_level, coverage_area_key, COUNT(*) as partner_count, SUM(commission_rate) as total_rate')
            ->select()
            ->toArray();
        
        // 获取详细信息
        foreach ($groups as &$group) {
            $group['level_name'] = self::getLevelName($group['agent_level']);
            $group['area_name'] = self::getAreaNameByKey($group['coverage_area_key']);
            
            // 获取合伙人列表
            $group['partners'] = Db::name('city_agent')
                ->where('partner_group_id', $group['id'])
                ->where('is_partner', 1)
                ->field('id, name, tel, commission_rate, headimg')
                ->select()
                ->toArray();
        }
        
        return $groups;
    }
    
    /**
     * 获取合伙分组详情
     * 
     * @param int $aid 应用ID
     * @param int $groupId 分组ID
     * @return array|null
     */
    public static function getPartnerGroupInfo($aid, $groupId)
    {
        $partners = Db::name('city_agent')
            ->where('aid', $aid)
            ->where('partner_group_id', $groupId)
            ->where('is_partner', 1)
            ->select()
            ->toArray();
        
        if (empty($partners)) {
            return null;
        }
        
        $first = $partners[0];
        $totalRate = 0;
        
        foreach ($partners as &$p) {
            $totalRate = bcadd($totalRate, $p['commission_rate'], 2);
            $p['agent_level_name'] = self::getLevelName($p['agent_level']);
        }
        
        return [
            'group_id' => $groupId,
            'agent_level' => $first['agent_level'],
            'level_name' => self::getLevelName($first['agent_level']),
            'area_name' => self::getAreaNameByKey($first['coverage_area_key']),
            'coverage_area_key' => $first['coverage_area_key'],
            'coverage_provinces' => json_decode($first['coverage_provinces'], true) ?: [],
            'coverage_cities' => json_decode($first['coverage_cities'], true) ?: [],
            'coverage_districts' => json_decode($first['coverage_districts'], true) ?: [],
            'coverage_streets' => json_decode($first['coverage_streets'], true) ?: [],
            'partners' => $partners,
            'partner_count' => count($partners),
            'total_rate' => $totalRate,
        ];
    }
    
    /**
     * 生成覆盖区域唯一标识
     * 
     * @param array $info 代理信息
     * @return string
     */
    public static function generateCoverageAreaKey($info)
    {
        $level = intval($info['agent_level'] ?? 1);
        $key = '';
        
        switch ($level) {
            case self::LEVEL_PROVINCE:
                $provinces = $info['coverage_provinces'] ?? [];
                if (is_string($provinces)) {
                    $provinces = json_decode($provinces, true) ?: [];
                }
                sort($provinces);
                $key = 'province_' . implode('_', $provinces);
                break;
                
            case self::LEVEL_CITY:
                $cities = $info['coverage_cities'] ?? [];
                if (is_string($cities)) {
                    $cities = json_decode($cities, true) ?: [];
                }
                sort($cities);
                $key = 'city_' . implode('_', $cities);
                break;
                
            case self::LEVEL_DISTRICT:
                $districts = $info['coverage_districts'] ?? [];
                if (is_string($districts)) {
                    $districts = json_decode($districts, true) ?: [];
                }
                sort($districts);
                $key = 'district_' . implode('_', $districts);
                break;
                
            case self::LEVEL_STREET:
                $streets = $info['coverage_streets'] ?? [];
                if (is_string($streets)) {
                    $streets = json_decode($streets, true) ?: [];
                }
                sort($streets);
                $key = 'street_' . implode('_', $streets);
                break;
        }
        
        return $key;
    }
    
    /**
     * 根据区域Key获取区域名称
     * 
     * @param string $areaKey
     * @return string
     */
    public static function getAreaNameByKey($areaKey)
    {
        if (empty($areaKey)) {
            return '未知区域';
        }
        
        $parts = explode('_', $areaKey);
        $type = $parts[0] ?? '';
        $ids = array_slice($parts, 1);
        
        if (empty($ids)) {
            return '未知区域';
        }
        
        $names = [];
        foreach ($ids as $id) {
            if (empty($id)) continue;
            $area = Db::name('area')->where('id', $id)->value('name');
            if ($area) {
                $names[] = $area;
            }
        }
        
        return implode('、', $names) ?: '未知区域';
    }
    
    /**
     * 获取代理覆盖区域名称
     * 
     * @param array $agent 代理信息
     * @return string
     */
    public static function getCoverageAreaNames($agent)
    {
        $level = intval($agent['agent_level'] ?? 1);
        $ids = [];
        
        switch ($level) {
            case self::LEVEL_PROVINCE:
                $ids = json_decode($agent['coverage_provinces'] ?? '[]', true) ?: [];
                break;
            case self::LEVEL_CITY:
                $ids = json_decode($agent['coverage_cities'] ?? '[]', true) ?: [];
                break;
            case self::LEVEL_DISTRICT:
                $ids = json_decode($agent['coverage_districts'] ?? '[]', true) ?: [];
                break;
            case self::LEVEL_STREET:
                $ids = json_decode($agent['coverage_streets'] ?? '[]', true) ?: [];
                break;
        }
        
        if (empty($ids)) {
            return '暂无';
        }
        
        $names = Db::name('area')->whereIn('id', $ids)->column('name');
        return implode('、', $names) ?: '暂无';
    }
    
    /**
     * 检查会员是否可以成为某区域的代理
     * 
     * @param int $aid 应用ID
     * @param int $memberId 会员ID
     * @param int $agentLevel 代理级别
     * @param string $areaKey 区域标识
     * @param int $excludeAgentId 排除的代理ID（编辑时使用）
     * @return array ['can' => bool, 'msg' => string]
     */
    public static function checkCanBeAgent($aid, $memberId, $agentLevel, $areaKey, $excludeAgentId = 0)
    {
        // 检查该会员是否已在同一区域同一级别有代理身份
        $query = Db::name('city_agent')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('agent_level', $agentLevel)
            ->where('coverage_area_key', $areaKey);
        
        if ($excludeAgentId > 0) {
            $query->where('id', '<>', $excludeAgentId);
        }
        
        $exists = $query->find();
        
        if ($exists) {
            return [
                'can' => false,
                'msg' => '该会员已是此区域的' . self::getLevelName($agentLevel),
                'existing_agent' => $exists,
            ];
        }
        
        return ['can' => true, 'msg' => '可以创建'];
    }
    
    /**
     * 获取下一个合伙分组ID
     * 
     * @param int $aid 应用ID
     * @return int
     */
    public static function getNextPartnerGroupId($aid)
    {
        $maxGroupId = Db::name('city_agent')
            ->where('aid', $aid)
            ->max('partner_group_id');
        
        return intval($maxGroupId) + 1;
    }
}



