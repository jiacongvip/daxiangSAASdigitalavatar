<?php
/**
 * 订单触发器类
 * 用于在订单状态变化时触发相关的缓存更新和业务逻辑
 * 
 * @author 开发团队
 * @date 2025-01-27
 */

namespace app\common;

use think\facade\Db;
use think\facade\Log;
use app\common\PerformanceCacheService;

class OrderTrigger
{
    /**
     * 订单完成触发器
     * 当订单状态变为已完成(status=2或3)时调用
     * 
     * @param int $orderId 订单ID
     * @param array $orderData 订单数据
     * @return bool
     */
    public static function onOrderCompleted($orderId, $orderData = null)
    {
        try {
            // 如果没有传入订单数据，则查询
            if (!$orderData) {
                $orderData = Db::name('shop_order')->where('id', $orderId)->find();
                if (!$orderData) {
                    Log::warning('订单触发器：订单不存在，ID=' . $orderId);
                    return false;
                }
            }
            
            $aid = $orderData['aid'];
            $mid = $orderData['mid'];
            $orderAmount = floatval($orderData['totalprice']);
            
            Log::write('订单完成触发器开始处理：订单ID=' . $orderId . '，用户ID=' . $mid . '，金额=' . $orderAmount);
            
            // 1. 触发用户本人的缓存更新
            self::triggerUserCacheUpdate($aid, $mid, $orderAmount, $orderId);
            
            // 2. 触发上级用户的缓存更新（影响团队业绩）
            self::triggerUplineCacheUpdate($aid, $mid, $orderAmount, $orderId);
            
            // 3. 如果启用了新滑落系统，检查是否需要重新计算接点关系
            self::checkSlideRecalculation($aid, $mid, $orderData);
            
            // 4. 处理接点团队比例奖励（如果设置为订单完成后触发）
            self::processJiedianTeamRatioReward($orderId, $orderData, 'completed');
            
            Log::write('订单完成触发器处理完成：订单ID=' . $orderId);
            return true;
            
        } catch (\Exception $e) {
            Log::error('订单完成触发器异常：' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 触发用户缓存更新
     * 
     * @param int $aid 站点ID
     * @param int $mid 用户ID
     * @param float $orderAmount 订单金额
     * @param int $orderId 订单ID
     */
    private static function triggerUserCacheUpdate($aid, $mid, $orderAmount, $orderId)
    {
        try {
            // 获取当前时间的各种格式
            $currentTime = time();
            $currentDate = date('Y-m-d', $currentTime);
            $currentMonth = date('Y-m', $currentTime);
            $currentHour = date('Y-m-d H:00:00', $currentTime);
            $currentMinute = date('Y-m-d H:i:00', $currentTime);
            
            // 清除用户的各种时间粒度缓存，让系统重新计算
            $cacheTypes = [
                PerformanceCacheService::CACHE_MINUTE => $currentMinute,
                PerformanceCacheService::CACHE_HOURLY => $currentHour,
                PerformanceCacheService::CACHE_DAILY => $currentDate,
                PerformanceCacheService::CACHE_MONTHLY => $currentMonth,
                PerformanceCacheService::CACHE_TOTAL => 'total'
            ];
            
            foreach ($cacheTypes as $cacheType => $timeKey) {
                // 标记缓存为过期，下次查询时会重新计算
                PerformanceCacheService::clearCache($mid, $cacheType, $timeKey, $aid);
            }

            self::queueSnapshotRefresh($aid, $orderId, $orderAmount, 'order_completed');

            Log::write('用户缓存更新触发完成：用户ID=' . $mid . '，订单ID=' . $orderId);
            
        } catch (\Exception $e) {
            Log::error('用户缓存更新触发异常：' . $e->getMessage());
        }
    }
    
    /**
     * 触发上级用户的缓存更新
     * 
     * @param int $aid 站点ID
     * @param int $mid 用户ID
     * @param float $orderAmount 订单金额
     * @param int $orderId 订单ID
     */
    private static function triggerUplineCacheUpdate($aid, $mid, $orderAmount, $orderId)
    {
        try {
            // 获取用户信息
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
            if (!$member || empty($member['path'])) {
                return;
            }
            
            // 解析用户路径，获取所有上级用户
            $pathArray = explode(',', trim($member['path'], ','));
            $pathArray = array_filter($pathArray); // 移除空值
            
            if (empty($pathArray)) {
                return;
            }
            
            // 获取当前时间的各种格式
            $currentTime = time();
            $currentDate = date('Y-m-d', $currentTime);
            $currentMonth = date('Y-m', $currentTime);
            $currentHour = date('Y-m-d H:00:00', $currentTime);
            $currentMinute = date('Y-m-d H:i:00', $currentTime);
            
            $cacheTypes = [
                PerformanceCacheService::CACHE_MINUTE => $currentMinute,
                PerformanceCacheService::CACHE_HOURLY => $currentHour,
                PerformanceCacheService::CACHE_DAILY => $currentDate,
                PerformanceCacheService::CACHE_MONTHLY => $currentMonth,
                PerformanceCacheService::CACHE_TOTAL => 'total'
            ];
            
            // 为每个上级用户触发缓存更新
            foreach ($pathArray as $uplinkId) {
                if (!$uplinkId || $uplinkId == $mid) {
                    continue;
                }
                
                foreach ($cacheTypes as $cacheType => $timeKey) {
                    // 清除上级用户的缓存
                    PerformanceCacheService::clearCache($uplinkId, $cacheType, $timeKey, $aid);
                }
            }

            self::queueSnapshotRefresh($aid, $orderId, $orderAmount, 'downline_order_completed');

            Log::write('上级用户缓存更新触发完成：下级用户ID=' . $mid . '，影响上级数量=' . count($pathArray));
            
        } catch (\Exception $e) {
            Log::error('上级用户缓存更新触发异常：' . $e->getMessage());
        }
    }
    
    /**
     * 检查是否需要重新计算滑落关系
     * 
     * @param int $aid 站点ID
     * @param int $mid 用户ID
     * @param array $orderData 订单数据
     */
    private static function checkSlideRecalculation($aid, $mid, $orderData)
    {
        try {
            // 获取九星设置
            $jiuxingsetinfo = Db::name('jifechi')->where('aid', $aid)->find();
            if (!$jiuxingsetinfo) {
                return;
            }
            
            $jiuxingsetinfo = json_decode($jiuxingsetinfo['jiuxing'], true);
            
            // 检查是否启用新滑落系统
            $enableNewSlide = isset($jiuxingsetinfo['enable_new_slide']) ? $jiuxingsetinfo['enable_new_slide'] : 0;
            if ($enableNewSlide != 1) {
                return;
            }
            
            // 检查滑落模式是否涉及业绩判断
            $hualuoMode = isset($jiuxingsetinfo['hualuo_mode']) ? $jiuxingsetinfo['hualuo_mode'] : 0;
            if ($hualuoMode != 2 && $hualuoMode != 3) { // 不是基于业绩或混合模式
                return;
            }
            
            // 获取用户信息
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
            if (!$member || empty($member['path'])) {
                return;
            }
            
            // 解析用户路径，检查可能受影响的上级用户
            $pathArray = explode(',', trim($member['path'], ','));
            $pathArray = array_filter($pathArray);
            
            $affectedUplinks = array_filter($pathArray, function ($uplinkId) use ($mid) {
                return $uplinkId && intval($uplinkId) !== intval($mid);
            });
            if (!empty($affectedUplinks)) {
                PerformanceCacheService::addSnapshotTask($aid, PerformanceCacheService::CACHE_DAILY, date('Y-m-d'), [
                    'trigger_type' => 'slide_recalc',
                    'trigger_user' => $mid,
                    'trigger_order' => $orderData['id'] ?? 0,
                    'trigger_amount' => $orderData['totalprice'] ?? 0,
                    'priority' => 5,
                ]);
            }

            Log::write('滑落重计算检查完成：触发用户ID=' . $mid . '，可能影响上级数量=' . count($affectedUplinks));
            
        } catch (\Exception $e) {
            Log::error('滑落重计算检查异常：' . $e->getMessage());
        }
    }
    
    /**
     * 订单支付触发器
     * 当订单状态变为已支付(status=1)时调用
     * 
     * @param int $orderId 订单ID
     * @param array $orderData 订单数据
     * @return bool
     */
    public static function onOrderPaid($orderId, $orderData = null)
    {
        try {
            // 如果没有传入订单数据，则查询
            if (!$orderData) {
                $orderData = Db::name('shop_order')->where('id', $orderId)->find();
                if (!$orderData) {
                    Log::warning('订单支付触发器：订单不存在，ID=' . $orderId);
                    return false;
                }
            }
            
            Log::write('订单支付触发器：订单ID=' . $orderId . '，用户ID=' . $orderData['mid'] . '，金额=' . $orderData['totalprice']);
            
            // 处理接点团队比例奖励（如果设置为订单下单后触发）
            self::processJiedianTeamRatioReward($orderId, $orderData, 'paid');
            // 分销持续奖励的一级奖励在订单支付成功后发放，服务内部负责开关、上下级关系和幂等校验。
            self::onTokenreleasePaidEvent(
                intval($orderData['aid'] ?? 0),
                intval($orderData['mid'] ?? 0),
                'shop_order_paid',
                strval(intval($orderId))
            );
            self::autoSubmitZhenxinOrder($orderId, $orderData);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('订单支付触发器异常：' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 批量处理订单触发
     * 用于处理历史订单或批量导入的订单
     * 
     * @param array $orderIds 订单ID数组
     * @param string $triggerType 触发类型
     * @return array 处理结果
     */
    public static function batchTrigger($orderIds, $triggerType = 'completed')
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($orderIds as $orderId) {
            try {
                if ($triggerType === 'completed') {
                    $result = self::onOrderCompleted($orderId);
                } elseif ($triggerType === 'paid') {
                    $result = self::onOrderPaid($orderId);
                } else {
                    throw new \Exception('未知的触发类型：' . $triggerType);
                }
                
                if ($result) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = '订单ID ' . $orderId . '：' . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * 处理接点团队比例奖励
     * 
     * @param int $orderId 订单ID
     * @param array $orderData 订单数据
     * @param string $triggerType 触发类型：'paid'=订单支付/下单后，'completed'=订单完成后
     */
    private static function processJiedianTeamRatioReward($orderId, $orderData, $triggerType = 'completed')
    {
        try {
            // 检查接点团队比例奖励功能是否开启
            $sysset = Db::name('jiedian_team_ratio_sysset')
                ->where('aid', $orderData['aid'])
                ->find();
            
            if (!$sysset || $sysset['status'] != 1) {
                return; // 功能未开启，直接返回
            }
            
            // 检查触发时机是否匹配
            $triggerTiming = isset($sysset['trigger_timing']) ? intval($sysset['trigger_timing']) : 0;
            // 0=订单完成后，1=订单下单后
            
            if ($triggerTiming == 0 && $triggerType != 'completed') {
                // 设置为订单完成后触发，但当前是支付/下单触发，跳过
                return;
            }
            
            if ($triggerTiming == 1 && $triggerType != 'paid') {
                // 设置为订单下单后触发，但当前是完成触发，跳过
                return;
            }
            
            // 调用服务类处理订单奖励
            \app\admin\common\JiedianTeamRatioService::processOrderReward($orderId, $orderData['aid']);
            
            Log::write('接点团队比例奖励处理完成：订单ID=' . $orderId . '，触发类型=' . $triggerType);
            
        } catch (\Exception $e) {
            Log::error('接点团队比例奖励处理异常：' . $e->getMessage());
        }
    }

    /**
     * 内置业务支付成功后的分销持续奖励一级奖励入口。
     * 失败只记日志，不阻断支付主流程。其他插件也可调用本方法，或直接调用 TokenReleaseService::grantDirectToken()。
     */
    public static function onTokenreleasePaidEvent($aid, $fromMid, $sourceType, $sourceId, $amount = null)
    {
        $aid = intval($aid);
        $fromMid = intval($fromMid);
        $sourceType = trim(strval($sourceType));
        $sourceId = trim(strval($sourceId));
        if ($aid <= 0 || $fromMid <= 0 || $sourceType === '' || $sourceId === '') {
            return;
        }

        $serviceClass = '\\app\\plugin\\tokenrelease\\common\\TokenReleaseService';
        if (!class_exists($serviceClass)) {
            return;
        }

        try {
            $payload = [
                'aid' => $aid,
                'from_mid' => $fromMid,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ];
            if ($amount !== null) {
                $payload['amount'] = $amount;
            }
            $result = $serviceClass::grantDirectRewardFromPaidEvent($payload);
            if (intval($result['status'] ?? 0) !== 1) {
                Log::warning('分销持续奖励一级奖励未发放', [
                    'aid' => $aid,
                    'from_mid' => $fromMid,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'message' => strval($result['msg'] ?? '未知原因'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('分销持续奖励一级奖励处理异常', [
                'aid' => $aid,
                'from_mid' => $fromMid,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 合并刷新整站业绩快照，避免订单触发时按每个上级反复扫订单。
     */
    private static function queueSnapshotRefresh($aid, $orderId, $orderAmount, $triggerType)
    {
        $now = time();
        $extra = [
            'trigger_type' => $triggerType,
            'order_id' => intval($orderId),
            'order_amount' => floatval($orderAmount),
            'priority' => 3,
        ];

        PerformanceCacheService::addSnapshotTask($aid, PerformanceCacheService::CACHE_DAILY, date('Y-m-d', $now), $extra);
        PerformanceCacheService::addSnapshotTask($aid, PerformanceCacheService::CACHE_MONTHLY, date('Y-m-01', $now), $extra);
        PerformanceCacheService::addSnapshotTask($aid, PerformanceCacheService::CACHE_TOTAL, date('Y-m-d', $now), $extra);
    }

    private static function autoSubmitZhenxinOrder($orderId, array $orderData): void
    {
        try {
            if (!class_exists('\\app\\plugin\\zhenxin\\service\\ZhenxinOrderService')) {
                return;
            }

            $aid = intval($orderData['aid'] ?? 0);
            if ($aid <= 0 || intval($orderId) <= 0) {
                return;
            }

            $result = (new \app\plugin\zhenxin\service\ZhenxinOrderService())
                ->autoSubmitPaidOrder($aid, intval($orderId));

            if (intval($result['submitted'] ?? 0) === 1) {
                Log::write('甄新汇选支付后自动提单成功：订单ID=' . intval($orderId) . '，供应链订单号=' . trim((string)($result['remote_order_sn'] ?? '')));
            } else {
                Log::write('甄新汇选支付后自动提单跳过：订单ID=' . intval($orderId) . '，原因=' . trim((string)($result['message'] ?? '')));
            }
        } catch (\Throwable $e) {
            Log::error('甄新汇选支付后自动提单失败：订单ID=' . intval($orderId) . '，错误=' . $e->getMessage());
        }
    }
}
