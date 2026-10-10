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
use think\facade\Log;
use app\common\RedisClient;

use app\admin\model\Member as m;
use app\admin\common\JiedianPerformanceService;

class Member
{
    /**
     * 旧版全局队列键仅保留为命名基准，不再直接投递或消费。
     */
    public const UPLV_REDIS_QUEUE = 'member:uplv:queue';
    private const UPLV_LOCK_TIMEOUT_SECONDS = 3;
    private const UPLV_LOCK_ATTEMPTS = 2;

    public static function getDefaultAvatar($aid = 0)
    {
        if (!$aid) {
            return PRE_URL . '/static/img/touxiang.png';
        }
        $set = Db::name('admin_set')->where('aid', $aid)->field('textset')->find();
        if (!$set || empty($set['textset'])) {
            return PRE_URL . '/static/img/touxiang.png';
        }
        $textset = json_decode($set['textset'], true);
        if (!is_array($textset)) {
            return PRE_URL . '/static/img/touxiang.png';
        }
        $avatar = trim((string)($textset['member_default_avatar'] ?? ''));
        return $avatar !== '' ? $avatar : PRE_URL . '/static/img/touxiang.png';
    }

    public static function isDefaultAvatar($aid = 0, $headimg = '')
    {
        $headimg = trim((string)$headimg);
        if ($headimg === '') {
            return true;
        }
        if (strpos($headimg, '/static/img/touxiang.png') !== false) {
            return true;
        }
        $defaultAvatar = trim((string)self::getDefaultAvatar($aid));
        if ($defaultAvatar === '') {
            return false;
        }
        if ($headimg === $defaultAvatar) {
            return true;
        }

        $normalize = function ($url) {
            $url = trim((string)$url);
            if ($url === '') {
                return '';
            }
            if (preg_match('/^https?:\/\//i', $url)) {
                return rtrim($url, '/');
            }
            if (strpos($url, '//') === 0) {
                return 'https:' . rtrim($url, '/');
            }
            if ($url[0] !== '/') {
                $url = '/' . $url;
            }
            return rtrim(PRE_URL, '/') . $url;
        };

        return $normalize($headimg) === $normalize($defaultAvatar);
    }

    /**
     * 自动注册会员（静默登录功能）
     * @param int $aid 应用ID
     * @param string $sessionid 会话ID
     * @param string $platform 平台标识
     * @return array|false 会员信息或失败
     */
    public static function autoReg($aid, $sessionid, $platform)
    {
        \think\facade\Log::write('=== Member::autoReg调用 ===');
        \think\facade\Log::write('Member::autoReg参数: aid='.$aid.', sessionid='.$sessionid.', platform='.$platform);
        \think\facade\Log::write('Member::autoReg调用堆栈: '.json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)));
        
        $member = Db::name('member')->where('aid', $aid)->where('session_id', $sessionid)->find();
        if(!$member){
            // 创建新会员数据
            $data = [];
            $data['aid'] = $aid;
            $data['sex'] = 3;
            $data['nickname'] = '用户'.random(6);
            $data['headimg'] = self::getDefaultAvatar($aid);
            $data['createtime'] = time();
            $data['session_id'] = $sessionid;
            $data['last_visittime'] = time();
            $data['platform'] = $platform;
            $data['checkst'] = 1; // 默认审核通过
            $data['isfreeze'] = 0; // 默认未冻结

            // 添加会员并更新session
            $mid = \app\admin\model\Member::add($aid, $data);
            Db::name('session')->where('aid', $aid)->where('session_id', $sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
        }else{
            $mid = $member['id'];
            Db::name('session')->where('aid', $aid)->where('session_id', $sessionid)->update([
                'mid' => $mid,
                'login_time' => time()
            ]);
        }
        
        // 设置缓存时间（默认7天）
        $sessionid_time = 7*86400;
        // 检查后台设置的免登录天数
        $nologin_day = Db::name('admin_set')->where('aid', $aid)->value('nologin_day');
        if($nologin_day > 0){
            $sessionid_time = $nologin_day*86400;
        }
        cache($sessionid.'_mid', $mid, $sessionid_time);
        return $member;
    }

    /**
     * 会员升级统一入口。
     *
     * 旧调用继续兼容，但未显式声明场景时按 legacy 业务事件处理，不能再误判为个人中心。
     */
    public static function uplv($aid, $mid, $orderid = 0, $levelid = 0, $scene = '')
    {
        if (is_array($orderid)) {
            return self::triggerUplv($aid, $mid, $orderid);
        }

        $sourceType = 'legacy';
        $sourceId = '';
        $normalizedOrderId = is_numeric($orderid) ? max(0, intval($orderid)) : 0;
        $normalizedScene = trim(strval($scene));

        if ($normalizedScene === '') {
            if ($normalizedOrderId > 0) {
                $normalizedScene = MemberUpgradeScene::ORDER_PAID;
                $sourceType = 'shop_order';
                $sourceId = strval($normalizedOrderId);
            } elseif (is_string($orderid) && trim($orderid) !== '') {
                $sourceType = trim($orderid);
                $normalizedScene = $sourceType === 'cashier'
                    ? MemberUpgradeScene::MAIDAN_PAID
                    : MemberUpgradeScene::LEGACY;
            } else {
                $normalizedScene = MemberUpgradeScene::LEGACY;
            }
        }

        $normalizedScene = MemberUpgradeScene::normalize($normalizedScene);
        if ($normalizedScene === MemberUpgradeScene::LEGACY) {
            self::logLegacyUplvCaller(intval($aid), intval($mid));
        }

        return self::triggerUplv($aid, $mid, [
            'scene' => $normalizedScene,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'orderid' => $normalizedOrderId,
            'levelid' => max(0, intval($levelid)),
        ]);
    }

    public static function triggerUplv($aid, $mid, array $context = [])
    {
        $startedAt = microtime(true);
        $aid = intval($aid);
        $mid = intval($mid);
        if ($aid <= 0 || $mid <= 0) {
            return ['status' => 'skipped', 'reason' => 'invalid_member'];
        }

        $scene = MemberUpgradeScene::normalize($context['scene'] ?? MemberUpgradeScene::LEGACY);
        $orderid = max(0, intval($context['orderid'] ?? 0));
        $levelid = max(0, intval($context['levelid'] ?? 0));
        $context['scene'] = $scene;
        $context['source_type'] = trim(strval($context['source_type'] ?? ''));
        $context['source_id'] = trim(strval($context['source_id'] ?? ''));
        $context['orderid'] = $orderid;
        $context['levelid'] = $levelid;

        $adminSet = Db::name('admin_set')->where('aid', $aid)->find();
        if (!$adminSet) {
            return ['status' => 'skipped', 'reason' => 'settings_missing'];
        }

        $isUserCenter = MemberUpgradeScene::isUserCenter($scene);
        if ($isUserCenter && intval($adminSet['uplv_usercenter_enabled'] ?? 1) !== 1) {
            return ['status' => 'skipped', 'reason' => 'usercenter_disabled'];
        }

        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
        if (!$member || empty($member['id'])) {
            return ['status' => 'skipped', 'reason' => 'member_missing'];
        }

        $eventMeta = [];
        $executionContext = [];
        if (intval($adminSet['uplv_event_v2_enabled'] ?? 0) === 1) {
            $executeRequested = intval($adminSet['uplv_event_execute_enabled'] ?? 0) === 1;
            $executeEnabled = $executeRequested
                && MemberUpgradeEventService::isExecutionReady($aid);
            if ($executeRequested && !$executeEnabled) {
                Log::warning('会员升级二阶段执行开关已开启，但执行器尚未就绪，已降级为影子记录并继续旧升级链路', [
                    'aid' => $aid,
                    'mid' => $mid,
                    'scene' => $scene,
                ]);
            }
            try {
                $eventResult = MemberUpgradeEventService::createUpgradeEvent($aid, $mid, $context, $executeEnabled);
                $eventMeta = [
                    'status' => intval($eventResult['created'] ?? 0) === 1 ? 'recorded' : 'exists',
                    'scene' => $scene,
                    'event_id' => intval($eventResult['event_id'] ?? 0),
                    'trigger_key' => strval($eventResult['trigger_key'] ?? ''),
                    'event_status' => strval($eventResult['status'] ?? 'pending_plan'),
                    'execute_enabled' => $executeEnabled ? 1 : 0,
                ];
                $executionContext = is_array($eventResult['context'] ?? null)
                    ? $eventResult['context']
                    : [];
                if ($executeEnabled) {
                    return array_merge($eventMeta, [
                        'status' => intval($eventResult['created'] ?? 0) === 1 ? 'event_queued' : 'event_exists',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('会员升级二阶段事件创建失败', [
                    'aid' => $aid,
                    'mid' => $mid,
                    'scene' => $scene,
                    'source_type' => $context['source_type'],
                    'source_id' => $context['source_id'],
                    'error' => $e->getMessage(),
                ]);
                if ($executeEnabled) {
                    return [
                        'status' => 'error',
                        'reason' => 'uplv_event_create_failed',
                        'msg' => $e->getMessage(),
                    ];
                }
                $eventMeta = [
                    'status' => 'record_failed',
                    'execute_enabled' => 0,
                    'reason' => 'uplv_event_create_failed',
                ];
            }
        }

        if (empty($executionContext) && $scene !== MemberUpgradeScene::LEGACY) {
            try {
                $executionContext = MemberUpgradeSourceResolver::resolve($aid, $mid, $context);
            } catch (\Throwable $e) {
                Log::warning('会员升级执行上下文解析失败，保留原始上下文继续兼容链路', [
                    'aid' => $aid,
                    'mid' => $mid,
                    'scene' => $scene,
                    'source_type' => $context['source_type'],
                    'source_id' => $context['source_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
        if (empty($executionContext)) {
            $executionContext = $context;
        }
        $executionContext['aid'] = $aid;
        $executionContext['origin_mid'] = $mid;
        $executionContext['scene'] = $scene;

        $asyncEnabled = intval($adminSet['uplv_async_enabled'] ?? 0) === 1;
        $threshold = max(0, intval($adminSet['uplv_async_threshold'] ?? 10000));
        $limitParentCount = intval($adminSet['uplv_limit_parent_count'] ?? 0) === 1;
        $limitParentLevels = max(0, intval($adminSet['uplv_limit_parent_levels'] ?? 5));
        $result = [
            'status' => 'success',
            'scene' => $scene,
            'self' => 'skipped',
            'parent_sync_count' => 0,
            'parent_queue_count' => 0,
            'duration_ms' => 0,
        ];
        if (!empty($eventMeta)) {
            $result['event'] = $eventMeta;
        }

        $checkSelf = array_key_exists('check_self', $executionContext)
            ? intval($executionContext['check_self']) === 1
            : (!$isUserCenter || intval($adminSet['uplv_usercenter_check_self'] ?? 1) === 1);
        if ($checkSelf) {
            $selfAsync = $isUserCenter
                && $asyncEnabled
                && strval($adminSet['uplv_usercenter_check_mode'] ?? 'async') === 'async';
            if ($selfAsync) {
                $queueResult = self::addToUplvQueue($aid, $mid, $orderid, $levelid, null, 'self', $executionContext);
                $result['self'] = strval($queueResult['status'] ?? 'error');
                $result['self_queue_id'] = intval($queueResult['queue_id'] ?? 0);
            } else {
                $executed = self::douplv($aid, $member, $orderid, $levelid, false, $executionContext);
                if ($executed) {
                    $result['self'] = 'direct';
                } else {
                    $queueResult = self::addToUplvQueue($aid, $mid, $orderid, $levelid, null, 'self', $executionContext);
                    $result['self'] = 'deferred_' . strval($queueResult['status'] ?? 'error');
                    $result['self_queue_id'] = intval($queueResult['queue_id'] ?? 0);
                }
            }
        }

        $checkParents = array_key_exists('check_parent', $executionContext)
            ? intval($executionContext['check_parent']) === 1
            : ($isUserCenter
                ? intval($adminSet['uplv_usercenter_check_parent'] ?? 1) === 1
                : intval($adminSet['uplv_order_check_parent_upgrade'] ?? 1) === 1);
        if ($checkParents) {
            $useAsyncThreshold = $asyncEnabled && ($isUserCenter
                || intval($adminSet['uplv_order_check_parent'] ?? 1) === 1);
            $targets = self::planUplvParentTargets(
                $aid,
                $member,
                $limitParentCount,
                $limitParentLevels,
                $useAsyncThreshold
            );

            $syncTargets = [];
            foreach ($targets as $target) {
                $teamCount = $target['team_count'];
                if ($useAsyncThreshold && $teamCount !== null && $teamCount > $threshold) {
                    $queueResult = self::addToUplvQueue(
                        $aid,
                        intval($target['mid']),
                        $orderid,
                        $levelid,
                        $teamCount,
                        strval($target['relation_type']),
                        $executionContext
                    );
                    if (in_array(strval($queueResult['status'] ?? ''), ['queued', 'exists'], true)) {
                        $result['parent_queue_count']++;
                    }
                    continue;
                }
                $syncTargets[intval($target['mid'])] = $target;
            }

            if (!empty($syncTargets)) {
                $parentRows = Db::name('member')
                    ->where('aid', $aid)
                    ->where('id', 'in', array_keys($syncTargets))
                    ->select()
                    ->toArray();
                $parentMap = array_column($parentRows, null, 'id');
                foreach ($syncTargets as $parentId => $target) {
                    if (!isset($parentMap[$parentId])) {
                        continue;
                    }
                    $executed = self::douplv(
                        $aid,
                        $parentMap[$parentId],
                        $orderid,
                        $levelid,
                        false,
                        $executionContext
                    );
                    if ($executed) {
                        $result['parent_sync_count']++;
                        continue;
                    }
                    $queueResult = self::addToUplvQueue(
                        $aid,
                        $parentId,
                        $orderid,
                        $levelid,
                        $target['team_count'] ?? null,
                        strval($target['relation_type'] ?? ''),
                        $executionContext
                    );
                    if (in_array(strval($queueResult['status'] ?? ''), ['queued', 'exists'], true)) {
                        $result['parent_queue_count']++;
                    }
                }
            }
        }

        $result['duration_ms'] = round((microtime(true) - $startedAt) * 1000, 2);
        Log::write([
            'event' => 'member_uplv_triggered',
            'aid' => $aid,
            'mid' => $mid,
            'scene' => $scene,
            'source_type' => $context['source_type'],
            'source_id' => $context['source_id'],
            'result' => $result,
        ]);
        return $result;
    }

    private static function planUplvParentTargets(int $aid, array $member, bool $limitParentCount, int $limitParentLevels, bool $withTeamCount): array
    {
        $mid = intval($member['id'] ?? 0);
        $targets = [];
        $recommendIds = [];
        if (!empty($member['path'])) {
            $recommendIds = array_values(array_filter(array_map('intval', explode(',', strval($member['path'])))));
            if ($limitParentCount && $limitParentLevels > 0) {
                $recommendIds = array_slice($recommendIds, -$limitParentLevels);
            }
            $recommendIds = array_reverse($recommendIds);
        }

        $jiedianIds = MemberRelationService::getJiedianUpMembers($aid, $mid, 0, [], false);
        $jiedianIds = array_values(array_filter(array_map('intval', $jiedianIds)));
        if ($limitParentCount && $limitParentLevels > 0) {
            $jiedianIds = array_slice($jiedianIds, 0, $limitParentLevels);
        }

        foreach ([
            'recommend' => $recommendIds,
            'jiedian' => $jiedianIds,
        ] as $relationType => $parentIds) {
            foreach ($parentIds as $parentId) {
                if ($parentId <= 0 || $parentId === $mid) {
                    continue;
                }
                if (!isset($targets[$parentId])) {
                    $targets[$parentId] = [
                        'mid' => $parentId,
                        'relation_type' => $relationType,
                        'relation_types' => [],
                        'team_count' => null,
                        'team_count_recommend' => null,
                        'team_count_jiedian' => null,
                    ];
                }
                if (!in_array($relationType, $targets[$parentId]['relation_types'], true)) {
                    $targets[$parentId]['relation_types'][] = $relationType;
                }
            }
        }

        if (!$withTeamCount) {
            return array_values($targets);
        }

        foreach ($targets as $parentId => &$target) {
            $counts = [];
            if (in_array('recommend', $target['relation_types'], true)) {
                $count = self::getTeamCountForAsync($aid, $parentId);
                if ($count !== null) {
                    $target['team_count_recommend'] = $count;
                    $counts[] = $count;
                }
            }
            if (in_array('jiedian', $target['relation_types'], true)) {
                $count = self::getJiedianTeamCountForAsync($aid, $parentId);
                if ($count !== null) {
                    $target['team_count_jiedian'] = $count;
                    $counts[] = $count;
                }
            }
            $target['team_count'] = empty($counts) ? null : max($counts);
        }
        unset($target);

        return array_values($targets);
    }

    /**
     * 二阶段事件规划只读入口。返回单目标任务计划，不执行升级、不写队列。
     */
    public static function planUpgradeEventTargets(int $aid, int $originMid, array $context): array
    {
        if ($aid <= 0 || $originMid <= 0) {
            throw new \InvalidArgumentException('uplv_event_plan_invalid_member');
        }
        $member = Db::name('member')->where('aid', $aid)->where('id', $originMid)->find();
        if (!$member) {
            throw new \RuntimeException('uplv_event_plan_member_missing:' . $originMid);
        }
        $adminSet = Db::name('admin_set')->where('aid', $aid)->find();
        if (!$adminSet) {
            throw new \RuntimeException('uplv_event_plan_settings_missing:' . $aid);
        }

        $scene = MemberUpgradeScene::normalize($context['scene'] ?? MemberUpgradeScene::LEGACY);
        $isUserCenter = MemberUpgradeScene::isUserCenter($scene);
        $checkSelf = array_key_exists('check_self', $context)
            ? intval($context['check_self']) === 1
            : (!$isUserCenter || intval($adminSet['uplv_usercenter_check_self'] ?? 1) === 1);
        $checkParent = array_key_exists('check_parent', $context)
            ? intval($context['check_parent']) === 1
            : ($isUserCenter
                ? intval($adminSet['uplv_usercenter_check_parent'] ?? 1) === 1
                : intval($adminSet['uplv_order_check_parent_upgrade'] ?? 1) === 1);
        $limitParentCount = intval($adminSet['uplv_limit_parent_count'] ?? 0) === 1;
        $limitParentLevels = max(0, intval($adminSet['uplv_limit_parent_levels'] ?? 5));
        $targets = [];

        if ($checkSelf) {
            $recommendCount = self::getTeamCountForAsync($aid, $originMid);
            $jiedianCount = self::getJiedianTeamCountForAsync($aid, $originMid);
            $targets[$originMid] = [
                'target_mid' => $originMid,
                'relation_types' => ['self'],
                'team_count_recommend' => $recommendCount,
                'team_count_jiedian' => $jiedianCount,
                'snapshot_version' => self::buildUplvTeamSnapshotVersion(
                    $originMid,
                    ['self'],
                    $recommendCount,
                    $jiedianCount
                ),
                'priority' => 2,
            ];
        }

        if ($checkParent) {
            $parentTargets = self::planUplvParentTargets(
                $aid,
                $member,
                $limitParentCount,
                $limitParentLevels,
                true
            );
            foreach ($parentTargets as $parentTarget) {
                $targetMid = intval($parentTarget['mid'] ?? 0);
                if ($targetMid <= 0) {
                    continue;
                }
                $relationTypes = array_values(array_unique(array_filter(
                    array_map('strval', $parentTarget['relation_types'] ?? [])
                )));
                sort($relationTypes, SORT_STRING);
                $recommendCount = $parentTarget['team_count_recommend'] ?? null;
                $jiedianCount = $parentTarget['team_count_jiedian'] ?? null;
                $targets[$targetMid] = [
                    'target_mid' => $targetMid,
                    'relation_types' => $relationTypes,
                    'team_count_recommend' => $recommendCount,
                    'team_count_jiedian' => $jiedianCount,
                    'snapshot_version' => self::buildUplvTeamSnapshotVersion(
                        $targetMid,
                        $relationTypes,
                        $recommendCount,
                        $jiedianCount
                    ),
                    'priority' => 1,
                ];
            }
        }

        ksort($targets, SORT_NUMERIC);
        return array_values($targets);
    }

    private static function buildUplvTeamSnapshotVersion(
        int $targetMid,
        array $relationTypes,
        $recommendCount,
        $jiedianCount
    ): string {
        return hash('sha256', implode('|', [
            $targetMid,
            implode(',', $relationTypes),
            $recommendCount === null ? 'unknown' : intval($recommendCount),
            $jiedianCount === null ? 'unknown' : intval($jiedianCount),
        ]));
    }

    private static function getJiedianTeamCountForAsync(int $aid, int $mid): ?int
    {
        try {
            $memberIds = MemberRelationService::getJiedianDownMembers($aid, $mid, 0, [], false);
            return count(array_unique(array_filter(array_map('intval', $memberIds))));
        } catch (\Throwable $e) {
            Log::error('会员升级接点团队人数统计失败: aid=' . $aid . ', mid=' . $mid . ', error=' . $e->getMessage());
            return null;
        }
    }

    private static function logLegacyUplvCaller(int $aid, int $mid): void
    {
        static $logged = [];
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? ($trace[1] ?? []);
        $callerKey = strval($caller['file'] ?? 'unknown') . ':' . intval($caller['line'] ?? 0);
        if (isset($logged[$callerKey])) {
            return;
        }
        $logged[$callerKey] = true;
        Log::warning('Member::uplv 未显式声明场景，已按 legacy 业务事件处理', [
            'aid' => $aid,
            'mid' => $mid,
            'caller' => $callerKey,
        ]);
    }

    /**
     * 旧升级分流实现，保留用于短期回滚对照，不再作为业务入口。
     */
    private static function uplvLegacy($aid,$mid,$orderid=0,$levelid=0){
        // ========== uplv 性能日志开始 ==========
        $uplv_start = microtime(true);
        \think\facade\Log::write("    [uplv] 开始等级升级检查 - AID:{$aid}, MID:{$mid}, OrderID:{$orderid}");
        
        // 步骤1：获取配置
        $step_start = microtime(true);
        $admin_set = Db::name('admin_set')->where('aid', $aid)->find();
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("    [uplv-步骤1] 查询配置 - 耗时: {$step_time}ms");
        
        // 判断场景：个人中心（orderid=0）还是订单完成（orderid>0）
        $is_usercenter = ($orderid == 0);
        
        // 个人中心场景：检查是否启用个人中心升级检查
        if($is_usercenter) {
            $usercenter_enabled = $admin_set['uplv_usercenter_enabled'] ?? 1;
            if($usercenter_enabled != 1) {
                \think\facade\Log::write("    [uplv] 个人中心升级检查已关闭，跳过");
                return;
            }
        }

        // 检查是否启用异步处理
        $has_large_team_parent = false; // 标记是否有大团队上级已加入队列
        $threshold = $admin_set['uplv_async_threshold'] ?? 10000;
        $limit_parent_count = $admin_set['uplv_limit_parent_count'] ?? 0;
        $limit_parent_levels = $admin_set['uplv_limit_parent_levels'] ?? 5;
        $early_stop = $admin_set['uplv_early_stop'] ?? 0;
        
        $async_enabled = ($admin_set && isset($admin_set['uplv_async_enabled']) && $admin_set['uplv_async_enabled'] == 1);
        $usercenter_check_mode = 'async';
        if($is_usercenter && isset($admin_set['uplv_usercenter_check_mode'])) {
            $mode = strval($admin_set['uplv_usercenter_check_mode']);
            $usercenter_check_mode = in_array($mode, ['async', 'direct'], true) ? $mode : 'async';
        }
        if($async_enabled) {
            // 步骤2：检查上级用户是否是大团队
            $should_check_parent_team = false;
            
            if($is_usercenter) {
                // 个人中心场景：根据配置决定是否检查上级大团队
                $should_check_parent_team = ($admin_set['uplv_usercenter_check_parent'] ?? 1) == 1;
            } else {
                // 订单完成场景：根据配置决定是否检查上级大团队
                $should_check_parent_team = ($admin_set['uplv_order_check_parent'] ?? 1) == 1;
            }
            
            if($should_check_parent_team) {
                $step_start = microtime(true);
                $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
                if($member && $member['path']) {
                    $parent_ids = explode(',', $member['path']);
                    $parent_ids = array_filter($parent_ids);
                    
                    // 限制检查上级数量
                    if($limit_parent_count == 1 && $limit_parent_levels > 0) {
                        $parent_ids = array_slice($parent_ids, -$limit_parent_levels); // 只取最近的N级
                        \think\facade\Log::write("    [uplv-步骤2] 限制检查上级数量，只检查最近{$limit_parent_levels}级");
                    }
                    
                    $parent_count = count($parent_ids);
                    \think\facade\Log::write("    [uplv-步骤2] 检查上级用户，上级数量: {$parent_count}");

                    foreach($parent_ids as $parent_id) {
                        $parent_step_start = microtime(true);
                        $parent_team_count = self::getTeamCountForAsync($aid, $parent_id);
                        $parent_step_time = round((microtime(true) - $parent_step_start) * 1000, 2);
                        if ($parent_team_count === null) {
                            \think\facade\Log::write("    [uplv-步骤2] 上级{$parent_id}团队人数统计失败，跳过本次大团队判断，耗时: {$parent_step_time}ms");
                            continue;
                        }
                        \think\facade\Log::write("    [uplv-步骤2] 上级{$parent_id}团队数: {$parent_team_count}, 耗时: {$parent_step_time}ms");

                        // 如果上级是大团队，将上级加入队列（而不是当前用户）
                        if($parent_team_count > $threshold) {
                            \think\facade\Log::write("    [uplv] ⚡ 上级{$parent_id}是大团队（团队数:{$parent_team_count}），将上级加入异步队列");
                            self::addToUplvQueue($aid, $parent_id, $orderid, $levelid, $parent_team_count);
                            $has_large_team_parent = true;
                            
                            // 提前结束检查：找到第一个大团队后停止
                            if($early_stop == 1) {
                                \think\facade\Log::write("    [uplv-步骤2] 提前结束检查：已找到大团队上级，停止检查其他上级");
                                break;
                            }
                        }
                    }
                    
                    if($has_large_team_parent) {
                        \think\facade\Log::write("    [uplv] 已将大团队上级加入队列，上级将通过队列异步处理");
                    }
                }
                $step_time = round((microtime(true) - $step_start) * 1000, 2);
                \think\facade\Log::write("    [uplv-步骤2] 检查所有上级完成 - 总耗时: {$step_time}ms");
            } else {
                \think\facade\Log::write("    [uplv-步骤2] 根据配置跳过上级大团队检查");
            }

            if($is_usercenter) {
                \think\facade\Log::write("    [uplv] 个人中心场景已开启异步升级，本人检查方式：" . ($usercenter_check_mode == 'direct' ? '直接检查' : '异步检查'));
            } else {
                \think\facade\Log::write("    [uplv] 订单场景当前用户自己下单，直接同步处理升级（不加入队列）");
            }
        }

        // 步骤4：查询会员信息
        $step_start = microtime(true);
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member['id']) return;
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("    [uplv-步骤4] 查询会员信息 - 耗时: {$step_time}ms");
        
        // 步骤5：执行当前用户升级检查
        $should_check_self = true;
        if($is_usercenter) {
            // 个人中心场景：根据配置决定是否检查自己升级
            $should_check_self = ($admin_set['uplv_usercenter_check_self'] ?? 1) == 1;
        }
        // 订单完成场景：始终检查自己升级
        
        if($should_check_self) {
            $step_start = microtime(true);
            if($is_usercenter && $async_enabled && $usercenter_check_mode == 'async') {
                $queueResult = self::addToUplvQueue($aid, $mid, $orderid, $levelid, null, 'self');
                $step_time = round((microtime(true) - $step_start) * 1000, 2);
                \think\facade\Log::write("    [uplv-步骤5] 当前用户升级检查已加入异步队列 - 状态:" . ($queueResult['status'] ?? '') . ", 队列ID:" . ($queueResult['queue_id'] ?? 0) . ", Redis:" . ($queueResult['redis_queued'] ?? 0) . ", 耗时: {$step_time}ms");
            } else {
                self::douplv($aid,$member,$orderid,$levelid); //提升该用户等级;
                $step_time = round((microtime(true) - $step_start) * 1000, 2);
                \think\facade\Log::write("    [uplv-步骤5] ⚠️ 当前用户升级检查(douplv，直接检查) - 耗时: {$step_time}ms ⚠️");
            }
        } else {
            \think\facade\Log::write("    [uplv-步骤5] 根据配置跳过当前用户升级检查");
        }

        // 步骤6：处理所有上级的升级检查
        $should_check_parent_upgrade = false;
        
        if($is_usercenter) {
            // 个人中心场景：不检查上级升级（已在步骤2中处理大团队）
            $should_check_parent_upgrade = false;
        } else {
            // 订单完成场景：根据配置决定是否检查上级升级
            $should_check_parent_upgrade = ($admin_set['uplv_order_check_parent_upgrade'] ?? 1) == 1;
        }
        
        // 如果上级是大团队已加入队列，不同步检查上级（通过队列异步处理）
        if($should_check_parent_upgrade && $orderid > 0 && !$has_large_team_parent) {
            $step_start = microtime(true);
            
            // 推荐上级按原path取，执行顺序保持从近到远
            $recommend_parent_ids = [];
            if(!empty($member['path'])) {
                $recommend_parent_ids = explode(',', $member['path']);
                $recommend_parent_ids = array_filter(array_map('intval', $recommend_parent_ids));
                if($limit_parent_count == 1 && $limit_parent_levels > 0) {
                    $recommend_parent_ids = array_slice($recommend_parent_ids, -$limit_parent_levels);
                    \think\facade\Log::write("    [uplv-步骤6] 限制推荐上级数量，只检查最近{$limit_parent_levels}级");
                }
                $recommend_parent_ids = array_reverse($recommend_parent_ids);
            }

            // 接点上级基于jiedianpid取，补齐接点小区业绩升级的触发链路
            $jiedian_parent_ids = MemberRelationService::getJiedianUpMembers($aid, $mid, 0);
            if($limit_parent_count == 1 && $limit_parent_levels > 0) {
                $jiedian_parent_ids = array_slice($jiedian_parent_ids, 0, $limit_parent_levels);
                \think\facade\Log::write("    [uplv-步骤6] 限制接点上级数量，只检查最近{$limit_parent_levels}级");
            }

            $parent_ids = [];
            $parent_id_map = [];
            foreach(array_merge($recommend_parent_ids, $jiedian_parent_ids) as $parent_id) {
                $parent_id = intval($parent_id);
                if($parent_id <= 0 || $parent_id == $mid || isset($parent_id_map[$parent_id])) {
                    continue;
                }
                $parent_ids[] = $parent_id;
                $parent_id_map[$parent_id] = true;
            }
            
            if($parent_ids){
                $parentList = Db::name('member')->where('aid',$aid)->where('id','in',implode(',', $parent_ids))->order(Db::raw('field(id,'.implode(',', $parent_ids).')'))->select()->toArray();
                $query_time = round((microtime(true) - $step_start) * 1000, 2);
                \think\facade\Log::write("    [uplv-步骤6] 查询上级列表 - 耗时: {$query_time}ms, 推荐上级数: " . count($recommend_parent_ids) . ", 接点上级数: " . count($jiedian_parent_ids) . ", 去重后上级数: " . count($parentList));

                $parent_index = 0;
                foreach($parentList as $parent){
                    $parent_index++;
                    $parent_start = microtime(true);
                    self::douplv($aid,$parent,$orderid,$levelid);
                    $parent_time = round((microtime(true) - $parent_start) * 1000, 2);
                    \think\facade\Log::write("    [uplv-步骤6] ⚠️ 上级{$parent_index}(ID:{$parent['id']})升级检查 - 耗时: {$parent_time}ms ⚠️");
                }
            } else {
                $query_time = round((microtime(true) - $step_start) * 1000, 2);
                \think\facade\Log::write("    [uplv-步骤6] 无推荐或接点上级需要检查 - 耗时: {$query_time}ms");
            }
        } else {
            if($is_usercenter) {
                \think\facade\Log::write("    [uplv-步骤6] 个人中心场景，跳过上级升级检查（仅检查当前用户）");
            } elseif($has_large_team_parent) {
                \think\facade\Log::write("    [uplv-步骤6] 上级是大团队已加入队列，跳过同步检查（将通过队列异步处理）");
            } elseif(!$should_check_parent_upgrade) {
                \think\facade\Log::write("    [uplv-步骤6] 根据配置跳过上级升级检查");
            }
        }
        
        // ========== uplv 性能日志结束 ==========
        $uplv_time = round((microtime(true) - $uplv_start) * 1000, 2);
        \think\facade\Log::write("    [uplv] 等级升级检查完成 - 总耗时: {$uplv_time}ms\n");
    }

    /**
     * 获取用户团队数量（用于异步队列判断）- 不使用缓存
     */
    public static function getTeamCountForAsync($aid, $mid): ?int {
        return self::calculateTeamCountForAsync(intval($aid), intval($mid));
    }

    /**
     * 实时统计推荐关系团队人数。
     *
     * 返回 null 表示查询失败，避免调用方把数据库异常误认为真实的 0 人团队。
     */
    private static function calculateTeamCountForAsync(int $aid, int $mid): ?int {
        if ($aid <= 0 || $mid <= 0) {
            return null;
        }

        try {
            return intval(Db::name('member')
                ->where('aid', $aid)
                ->whereRaw('FIND_IN_SET(?, path)', [$mid])
                ->count());
        } catch(\Throwable $e) {
            Log::error('会员升级团队人数统计失败: aid=' . $aid . ', mid=' . $mid . ', error=' . $e->getMessage());
            return null;
        }
    }

    /**
     * worker 执行任务前刷新团队人数快照；刷新失败时保留原值。
     */
    public static function refreshUplvQueueTeamCount(int $queueId, int $aid, int $mid, ?int $fallback = null, string $relationType = ''): ?int {
        $fallback = $fallback === null ? null : max(0, $fallback);
        $teamCount = $relationType === 'jiedian'
            ? self::getJiedianTeamCountForAsync($aid, $mid)
            : self::calculateTeamCountForAsync($aid, $mid);
        if ($teamCount === null) {
            return $fallback;
        }

        if ($queueId > 0) {
            try {
                Db::name('member_uplv_queue')
                    ->where('id', $queueId)
                    ->where('aid', $aid)
                    ->where('mid', $mid)
                    ->update(['team_count' => $teamCount]);
            } catch(\Throwable $e) {
                Log::error('会员升级队列团队人数快照刷新失败: queue_id=' . $queueId . ', aid=' . $aid . ', mid=' . $mid . ', error=' . $e->getMessage());
            }
        }

        return $teamCount;
    }

    /**
     * 添加用户到升级队列
     */
    public static function addToUplvQueue($aid, $mid, $orderid, $levelid, $team_count, string $relationType = '', array $context = []) {
        $aid = intval($aid);
        $mid = intval($mid);
        $orderid = intval($orderid);
        $levelid = intval($levelid);
        $relationType = in_array($relationType, ['self', 'recommend', 'jiedian'], true) ? $relationType : '';
        $providedTeamCount = is_numeric($team_count) ? max(0, intval($team_count)) : null;
        // 调用方已完成统计时直接复用；self 任务传 null，由队列层统一实时计算。
        $calculatedTeamCount = $providedTeamCount ?? ($relationType === 'jiedian'
            ? self::getJiedianTeamCountForAsync($aid, $mid)
            : self::calculateTeamCountForAsync($aid, $mid));
        $team_count = $calculatedTeamCount;
        if ($aid <= 0 || $mid <= 0) {
            return ['status' => 'error', 'msg' => '升级任务参数无效'];
        }
        $scene = MemberUpgradeScene::normalize($context['scene'] ?? MemberUpgradeScene::LEGACY);
        $sourceType = substr(trim(strval($context['source_type'] ?? '')), 0, 32);
        $sourceId = substr(trim(strval($context['source_id'] ?? '')), 0, 64);
        $activeDedupKey = self::buildUplvActiveDedupKey($aid, $mid, $relationType);
        $triggerKey = hash('sha256', implode('|', [
            $aid,
            $mid,
            $scene,
            $sourceType,
            $sourceId,
            $orderid,
            $levelid,
            $relationType,
        ]));

        // 检查是否已经在队列中
        $existingQuery = Db::name('member_uplv_queue')
            ->where('status', 'in', '0,1'); // 待处理或处理中
        $existingQuery->where('active_dedup_key', $activeDedupKey);
        $existing = $existingQuery->find();

        if($existing) {
            // 重复触发时同步刷新快照，避免旧任务一直保留错误的 0。
            if ($calculatedTeamCount !== null && intval($existing['team_count'] ?? -1) !== $team_count) {
                try {
                    Db::name('member_uplv_queue')
                        ->where('id', intval($existing['id']))
                        ->update(['team_count' => $team_count]);
                } catch(\Throwable $e) {
                    Log::error('会员升级重复任务团队人数刷新失败: queue_id=' . intval($existing['id']) . ', error=' . $e->getMessage());
                }
            }
            $effectiveTeamCount = $calculatedTeamCount;
            if ($effectiveTeamCount === null && array_key_exists('team_count', $existing) && $existing['team_count'] !== null) {
                $effectiveTeamCount = max(0, intval($existing['team_count']));
            }
            return [
                'status' => 'exists',
                'queue_id' => intval($existing['id'] ?? 0),
                'redis_queued' => 0,
                'team_count' => $effectiveTeamCount,
                'msg' => '升级任务已在队列中，请稍后查看结果',
            ];
        }

        // 添加到队列
        $queue_data = [
            'aid' => $aid,
            'mid' => $mid,
            'orderid' => $orderid,
            'levelid' => $levelid,
            'team_count' => $team_count,
            'status' => 0,
            'priority' => 1,
            'created_time' => date('Y-m-d H:i:s'),
            'updated_time' => date('Y-m-d H:i:s'),
        ];
        $queue_data['relation_type'] = $relationType;
        $queue_data['scene'] = $scene;
        $queue_data['source_type'] = $sourceType;
        $queue_data['source_id'] = $sourceId;
        $queue_data['trigger_key'] = $triggerKey;
        $queue_data['active_dedup_key'] = $activeDedupKey;
        $queue_data['worker_token'] = '';
        $queue_data['lease_expire_time'] = null;
        $queue_data['next_retry_time'] = date('Y-m-d H:i:s');
        $queue_data['last_error_code'] = '';

        try {
            $queueId = intval(Db::name('member_uplv_queue')->insertGetId($queue_data));

            if($queueId > 0) {
                $redisQueued = self::pushUplvTaskToRedis($queueId);
                return [
                    'status' => 'queued',
                    'queue_id' => $queueId,
                    'redis_queued' => $redisQueued ? 1 : 0,
                    'team_count' => $team_count,
                    'msg' => '升级任务已加入后台队列处理，请稍后查看结果',
                ];
            } else {
                return ['status' => 'error', 'msg' => '加入队列失败，请重试'];
            }
        } catch(\Throwable $e) {
            if (self::isDuplicateKeyException($e)) {
                $existing = Db::name('member_uplv_queue')
                    ->where('active_dedup_key', $activeDedupKey)
                    ->where('status', 'in', '0,1')
                    ->find();
                if ($existing) {
                    return [
                        'status' => 'exists',
                        'queue_id' => intval($existing['id']),
                        'redis_queued' => 0,
                        'team_count' => isset($existing['team_count']) ? intval($existing['team_count']) : $team_count,
                        'msg' => '升级任务已在队列中，请稍后查看结果',
                    ];
                }
            }
            $errorCode = self::isMissingUplvQueueSchemaException($e)
                ? 'uplv_queue_schema_missing'
                : 'uplv_queue_insert_failed';
            Log::error('会员升级任务入队失败: aid=' . $aid . ', mid=' . $mid . ', error_code=' . $errorCode . ', error=' . $e->getMessage());
            return ['status' => 'error', 'error_code' => $errorCode, 'msg' => '加入队列异常: ' . $e->getMessage()];
        }
    }

    public static function buildUplvActiveDedupKey(int $aid, int $mid, string $relationType = ''): string
    {
        $relationType = in_array($relationType, ['self', 'recommend', 'jiedian'], true) ? $relationType : '';
        return hash('sha256', $aid . '|' . $mid . '|' . $relationType);
    }

    private static function isDuplicateKeyException(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return strpos($message, 'duplicate') !== false || strpos($message, '1062') !== false;
    }

    private static function isMissingUplvQueueSchemaException(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return strpos($message, 'doesn\'t exist') !== false
            || strpos($message, 'unknown column') !== false
            || strpos($message, '1146') !== false
            || strpos($message, '1054') !== false;
    }

    public static function pushUplvTaskToRedis(int $queueId): bool
    {
        if ($queueId <= 0) {
            return false;
        }

        try {
            return RedisClient::lPush(self::uplvRedisQueueKey(), json_encode([
                'task_id' => $queueId,
            ], JSON_UNESCAPED_UNICODE)) > 0;
        } catch (\Throwable $e) {
            Log::write('会员升级任务推送Redis失败: queue_id=' . $queueId . ', error=' . $e->getMessage());
            return false;
        }
    }

    /**
     * 多个独立站点可能共用同一 Redis，升级任务必须按数据库隔离。
     */
    public static function uplvRedisQueueKey(): string
    {
        $connection = config('database.connections.mysql', []);
        return self::buildUplvRedisQueueKey(
            is_array($connection) ? $connection : []
        );
    }

    public static function buildUplvRedisQueueKey(array $connection): string
    {
        $identity = implode('|', [
            strtolower(trim(strval($connection['hostname'] ?? ''))),
            strval(max(0, intval($connection['hostport'] ?? 0))),
            strtolower(trim(strval($connection['database'] ?? ''))),
            trim(strval($connection['prefix'] ?? '')),
        ]);
        if ($identity === '|0||') {
            $identity = defined('ROOT_PATH')
                ? strval(ROOT_PATH)
                : __DIR__;
        }

        return self::UPLV_REDIS_QUEUE
            . ':v2:'
            . substr(hash('sha256', $identity), 0, 20);
    }

    /**
     * 强制执行升级（用于队列处理）
     */
    public static function uplv_force(
        $aid,
        $mid,
        $orderid = 0,
        $levelid = 0,
        string $relationType = '',
        array $context = []
    ) {
        // 队列任务只负责一个明确会员，上级目标必须在触发阶段独立规划和入队。
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member || empty($member['id'])) return false;
        return self::douplv($aid, $member, $orderid, $levelid, true, $context);
    }

    public static function uplvByAssetReceived($aid, $mid, array $context = [])
    {
        $aid = intval($aid);
        $mid = intval($mid);
        if ($aid <= 0 || $mid <= 0 || !self::isZichanfenqiUpgradeReady($aid)) {
            return;
        }

        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
        if (!$member || empty($member['id'])) {
            return;
        }

        $context = array_merge([
            'scene' => MemberUpgradeScene::ASSET_RECEIVED,
            'source_type' => 'zichanfenqi',
            'source_id' => '',
        ], $context);
        $context['scene'] = MemberUpgradeScene::ASSET_RECEIVED;

        // 资产插件仍使用自己的团队规则；二阶段尚无资产专用规划器时只留影子事件，
        // 继续走现有资产规则，避免把资产到账误改成普通订单升级。
        // 不指定新字段，兼容二阶段 migration 尚未执行的旧站点。
        $adminSet = Db::name('admin_set')->where('aid', $aid)->find();
        if (intval($adminSet['uplv_event_v2_enabled'] ?? 0) === 1
            && trim(strval($context['source_id'] ?? '')) !== '') {
            try {
                MemberUpgradeEventService::createUpgradeEvent($aid, $mid, $context, false);
            } catch (\Throwable $e) {
                Log::warning('资产到账二阶段影子事件记录失败: aid=' . $aid . ', mid=' . $mid . ', error=' . $e->getMessage());
            }
        }

        $asyncConfig = self::getUplvAsyncConfig($aid);
        if ($asyncConfig['enabled']) {
            self::addToUplvQueue($aid, $mid, 0, 0, null, 'self', $context);
        } else {
            $context['aid'] = $aid;
            $context['origin_mid'] = $mid;
            $executed = self::douplv($aid, $member, 0, 0, false, $context);
            if (!$executed) {
                self::addToUplvQueue($aid, $mid, 0, 0, null, 'self', $context);
            }
        }
        self::triggerZichanfenqiTeamUplv($aid, $member, $context);
    }

    private static function isZichanfenqiUpgradeReady(int $aid): bool
    {
        static $cache = [];
        if (isset($cache[$aid])) {
            return $cache[$aid];
        }

        if ($aid <= 0
            || !self::tableExistsStatic('admin_plugin_menu')
            || !self::tableExistsStatic('zichanfenqi_plan')
            || !self::tableExistsStatic('zichanfenqi_convert_log')
            || !self::tableExistsStatic('zichanfenqi_reward_log')
            || !self::columnExistsStatic('member_level', 'up_zichanfenqi_status')) {
            return $cache[$aid] = false;
        }

        try {
            $cache[$aid] = Db::name('admin_plugin_menu')
                ->where('aid', $aid)
                ->where('plugin_name', 'zichanfenqi')
                ->where('status', 1)
                ->count() > 0;
        } catch (\Throwable $e) {
            $cache[$aid] = false;
        }

        return $cache[$aid];
    }

    private static function calculateZichanfenqiUpgradeAmount(int $aid, int $mid, array $lv): float
    {
        static $cache = [];
        if (!self::isZichanfenqiUpgradeReady($aid)) {
            return 0;
        }

        $assetType = trim(strval($lv['up_zichanfenqi_asset_type'] ?? ''));
        if ($assetType === '') {
            return 0;
        }

        $scope = strval($lv['up_zichanfenqi_scope'] ?? 'all');
        if (!in_array($scope, ['all', 'self', 'reward'], true)) {
            $scope = 'all';
        }

        $cacheKey = $aid . ':' . $mid . ':' . $scope . ':' . $assetType;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $total = '0.00000';
        if (in_array($scope, ['all', 'self'], true)) {
            $selfQuery = Db::name('zichanfenqi_convert_log')
                ->alias('c')
                ->where('c.aid', $aid)
                ->where('c.mid', $mid)
                ->where('c.status', 1)
                ->where('c.target_asset_type', $assetType);
            self::applyZichanfenqiPerformanceStatusFilter($selfQuery, 'c', 'self');
            $selfAmount = $selfQuery->sum('c.source_deduct_amount');
            $total = bcadd($total, self::normalizeAmountString($selfAmount), 5);
        }

        if (in_array($scope, ['all', 'reward'], true)) {
            $rewardQuery = Db::name('zichanfenqi_reward_log')
                ->alias('r')
                ->where('r.aid', $aid)
                ->where('r.reward_mid', $mid)
                ->where('r.status', 1)
                ->where('r.reward_asset_type', $assetType);
            self::applyZichanfenqiPerformanceStatusFilter($rewardQuery, 'r', 'self');
            $rewardAmount = $rewardQuery->sum('r.reward_amount');
            $total = bcadd($total, self::normalizeAmountString($rewardAmount), 5);
        }

        $cache[$cacheKey] = floatval($total);
        return $cache[$cacheKey];
    }

    private static function calculateZichanfenqiTeamUpgradeAmount(int $aid, int $mid, array $lv): float
    {
        static $cache = [];
        if (!self::isZichanfenqiUpgradeReady($aid)) {
            return 0;
        }

        $assetType = trim(strval($lv['up_zichanfenqi_team_asset_type'] ?? ''));
        if ($assetType === '') {
            return 0;
        }

        $relationType = strval($lv['up_zichanfenqi_team_relation_type'] ?? 'recommend');
        if (!in_array($relationType, ['recommend', 'jiedian'], true)) {
            $relationType = 'recommend';
        }

        $cacheKey = $aid . ':' . $mid . ':' . $relationType . ':' . $assetType;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $total = '0.00000';
        if ($relationType === 'jiedian') {
            $teamMids = MemberRelationService::getJiedianDownMembers($aid, $mid, 0, [], false);
            $total = self::sumZichanfenqiConvertedTargetByMids($aid, $teamMids, $assetType);
        } else {
            $grantQuery = Db::name('zichanfenqi_convert_log')
                ->alias('c')
                ->join('member m', 'm.id = c.mid AND m.aid = c.aid')
                ->where('c.aid', $aid)
                ->where('c.status', 1)
                ->where('c.target_asset_type', $assetType)
                ->where('find_in_set(' . $mid . ',m.path)');
            self::applyZichanfenqiPerformanceStatusFilter($grantQuery, 'c', 'team');
            $grantAmount = $grantQuery->sum('c.source_deduct_amount');
            $total = self::normalizeAmountString($grantAmount);
        }

        $cache[$cacheKey] = floatval($total);
        return $cache[$cacheKey];
    }

    private static function sumZichanfenqiConvertedTargetByMids(int $aid, array $mids, string $assetType): string
    {
        $mids = array_values(array_unique(array_filter(array_map('intval', $mids))));
        if (empty($mids)) {
            return '0.00000';
        }

        $total = '0.00000';
        foreach (array_chunk($mids, 500) as $chunk) {
            $grantQuery = Db::name('zichanfenqi_convert_log')
                ->alias('c')
                ->where('c.aid', $aid)
                ->where('c.status', 1)
                ->where('c.target_asset_type', $assetType)
                ->where('c.mid', 'in', $chunk);
            self::applyZichanfenqiPerformanceStatusFilter($grantQuery, 'c', 'team');
            $grantAmount = $grantQuery->sum('c.source_deduct_amount');
            $total = bcadd($total, self::normalizeAmountString($grantAmount), 5);
        }

        return $total;
    }

    private static function getZichanfenqiPerformanceFilterColumn(string $scope = 'self'): string
    {
        static $cache = [];
        if (isset($cache[$scope])) {
            return $cache[$scope];
        }

        if (!self::tableExistsStatic('zichanfenqi_activity')) {
            return $cache[$scope] = '';
        }

        $preferredColumn = $scope === 'team' ? 'performance_team_status' : 'performance_self_status';
        if (self::columnExistsStatic('zichanfenqi_activity', $preferredColumn)) {
            return $cache[$scope] = $preferredColumn;
        }
        if (self::columnExistsStatic('zichanfenqi_activity', 'performance_status')) {
            return $cache[$scope] = 'performance_status';
        }

        return $cache[$scope] = '';
    }

    private static function applyZichanfenqiPerformanceStatusFilter($query, string $logAlias, string $scope = 'self'): void
    {
        $column = self::getZichanfenqiPerformanceFilterColumn($scope);
        if ($column === '') {
            return;
        }

        $query->join('zichanfenqi_activity act', 'act.id = ' . $logAlias . '.activity_id AND act.aid = ' . $logAlias . '.aid')
            ->where('act.' . $column, 1);
    }

    private static function hasZichanfenqiTeamUpgradeRules(int $aid): bool
    {
        static $cache = [];
        if (isset($cache[$aid])) {
            return $cache[$aid];
        }

        if (!self::isZichanfenqiUpgradeReady($aid) || !self::columnExistsStatic('member_level', 'up_zichanfenqi_team_status')) {
            return $cache[$aid] = false;
        }

        try {
            $cache[$aid] = Db::name('member_level')
                ->where('aid', $aid)
                ->where('can_up', 1)
                ->where('up_zichanfenqi_team_status', 1)
                ->where('up_zichanfenqi_team_amount', '>', 0)
                ->where('up_zichanfenqi_team_asset_type', '<>', '')
                ->count() > 0;
        } catch (\Throwable $e) {
            $cache[$aid] = false;
        }

        return $cache[$aid];
    }

    private static function getZichanfenqiTeamRelationTypes(int $aid): array
    {
        static $cache = [];
        if (isset($cache[$aid])) {
            return $cache[$aid];
        }

        if (!self::hasZichanfenqiTeamUpgradeRules($aid)) {
            return $cache[$aid] = [];
        }

        try {
            $values = Db::name('member_level')
                ->where('aid', $aid)
                ->where('can_up', 1)
                ->where('up_zichanfenqi_team_status', 1)
                ->where('up_zichanfenqi_team_amount', '>', 0)
                ->where('up_zichanfenqi_team_asset_type', '<>', '')
                ->column('up_zichanfenqi_team_relation_type');
        } catch (\Throwable $e) {
            $values = [];
        }

        $relationTypes = [];
        foreach ($values as $value) {
            $value = in_array($value, ['recommend', 'jiedian'], true) ? $value : 'recommend';
            if (!in_array($value, $relationTypes, true)) {
                $relationTypes[] = $value;
            }
        }

        return $cache[$aid] = $relationTypes;
    }

    private static function triggerZichanfenqiTeamUplv(int $aid, array $member, array $context = []): void
    {
        $mid = intval($member['id'] ?? 0);
        if ($aid <= 0 || $mid <= 0 || !self::hasZichanfenqiTeamUpgradeRules($aid)) {
            return;
        }

        $relationTypes = self::getZichanfenqiTeamRelationTypes($aid);
        if (empty($relationTypes)) {
            return;
        }

        $asyncConfig = self::getUplvAsyncConfig($aid);
        $handledMids = [];

        foreach ($relationTypes as $relationType) {
            $ancestorIds = self::getZichanfenqiTeamAncestorIds($aid, $member, $relationType);
            if (empty($ancestorIds)) {
                continue;
            }

            foreach ($ancestorIds as $ancestorId) {
                $ancestorId = intval($ancestorId);
                if ($ancestorId <= 0 || isset($handledMids[$ancestorId])) {
                    continue;
                }

                if ($asyncConfig['enabled']) {
                    self::addToUplvQueue($aid, $ancestorId, 0, 0, null, $relationType, $context);
                    $handledMids[$ancestorId] = true;
                    continue;
                }

                $ancestor = Db::name('member')->where('aid', $aid)->where('id', $ancestorId)->find();
                if (!$ancestor || empty($ancestor['id'])) {
                    continue;
                }
                $executionContext = $context;
                $executionContext['aid'] = $aid;
                $executionContext['origin_mid'] = $mid;
                $executed = self::douplv($aid, $ancestor, 0, 0, false, $executionContext);
                if (!$executed) {
                    self::addToUplvQueue($aid, $ancestorId, 0, 0, null, $relationType, $context);
                }
                $handledMids[$ancestorId] = true;
            }
        }
    }

    /**
     * 读取后台「升级队列配置」的异步开关与阈值（admin_set.uplv_async_enabled / uplv_async_threshold）
     */
    private static function getUplvAsyncConfig(int $aid): array
    {
        static $cache = [];
        if (isset($cache[$aid])) {
            return $cache[$aid];
        }

        $config = ['enabled' => false, 'threshold' => 10000];
        try {
            $adminSet = Db::name('admin_set')->where('aid', $aid)->field('uplv_async_enabled,uplv_async_threshold')->find();
            $config['enabled'] = intval($adminSet['uplv_async_enabled'] ?? 0) === 1;
            $config['threshold'] = max(0, intval($adminSet['uplv_async_threshold'] ?? 10000));
        } catch (\Throwable $e) {
        }

        return $cache[$aid] = $config;
    }

    private static function getZichanfenqiTeamAncestorIds(int $aid, array $member, string $relationType): array
    {
        $mid = intval($member['id'] ?? 0);
        if ($mid <= 0) {
            return [];
        }

        if ($relationType === 'jiedian') {
            return array_values(array_filter(array_map('intval', MemberRelationService::getJiedianUpMembers($aid, $mid, 0, [], false))));
        }

        $path = strval($member['path'] ?? '');
        if ($path === '') {
            return [];
        }

        $parentIds = array_filter(array_map('intval', explode(',', $path)));
        return array_reverse($parentIds);
    }

    private static function getRelationTeamCountForAsync(int $aid, int $mid, string $relationType = 'recommend'): int
    {
        static $cache = [];
        $relationType = $relationType === 'jiedian' ? 'jiedian' : 'recommend';
        $cacheKey = $aid . ':' . $mid . ':' . $relationType;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        try {
            if ($relationType === 'jiedian') {
                $cache[$cacheKey] = count(MemberRelationService::getJiedianDownMembers($aid, $mid, 0, [], false));
            } else {
                $cache[$cacheKey] = intval(Db::name('member')
                    ->where('aid', $aid)
                    ->where('find_in_set(' . $mid . ',path)')
                    ->count());
            }
        } catch (\Throwable $e) {
            $cache[$cacheKey] = 0;
        }

        return $cache[$cacheKey];
    }

    /**
     * 统计达标接点市场数：一个直接接点分支内出现指定等级及以上会员，即算一个市场。
     */
    private static function countQualifiedJiedianMarkets(int $aid, int $mid, array $qualifiedLevelIds, int $requiredCount = 0): int
    {
        $qualifiedLevelIds = array_values(array_unique(array_filter(array_map('intval', $qualifiedLevelIds))));
        if ($aid <= 0 || $mid <= 0 || empty($qualifiedLevelIds)) {
            return 0;
        }

        $directJiedianMembers = MemberRelationService::getDirectJiedianDownMembers($aid, $mid);
        if (empty($directJiedianMembers)) {
            return 0;
        }

        $qualifiedCount = 0;
        foreach ($directJiedianMembers as $directMid) {
            $directMid = intval($directMid);
            if ($directMid <= 0) {
                continue;
            }

            $branchMids = MemberRelationService::getJiedianDownMembers($aid, $directMid, 0, [], true);
            $branchMids = array_values(array_unique(array_filter(array_map('intval', $branchMids))));
            if (empty($branchMids)) {
                continue;
            }

            $hasQualifiedMember = 0;
            foreach (array_chunk($branchMids, 500) as $branchChunk) {
                $hasQualifiedMember = Db::name('member')
                    ->where('aid', $aid)
                    ->where('id', 'in', $branchChunk)
                    ->where('levelid', 'in', $qualifiedLevelIds)
                    ->value('id');
                if ($hasQualifiedMember) {
                    break;
                }
            }

            if ($hasQualifiedMember) {
                $qualifiedCount++;
                if ($requiredCount > 0 && $qualifiedCount >= $requiredCount) {
                    break;
                }
            }
        }

        return $qualifiedCount;
    }

    public static function ensureUplvQueueReady(): void
    {
        static $validated = false;
        if ($validated) {
            return;
        }

        $requiredColumns = [
            'relation_type', 'scene', 'source_type', 'source_id', 'trigger_key',
            'active_dedup_key', 'worker_token', 'lease_expire_time',
            'next_retry_time', 'last_error_code', 'updated_time', 'processed_time',
        ];
        if (!self::tableExistsDirect('member_uplv_queue')) {
            throw new \RuntimeException('uplv_queue_schema_missing: member_uplv_queue table not found');
        }
        foreach ($requiredColumns as $column) {
            if (!self::columnExistsDirect('member_uplv_queue', $column)) {
                throw new \RuntimeException('uplv_queue_schema_missing: member_uplv_queue.' . $column . ' not found');
            }
        }
        if (!self::indexExistsDirect('member_uplv_queue', 'uniq_active_dedup_key')) {
            throw new \RuntimeException('uplv_queue_schema_missing: uniq_active_dedup_key not found');
        }

        $validated = true;
    }

    private static function tableExistsStatic(string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $fullTable = config('database.connections.mysql.prefix', '') . preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $cache[$table] = !empty(Db::query("SHOW TABLES LIKE '{$fullTable}'"));
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    private static function ensureGongxianzhiInstalledIfAvailable(): bool
    {
        static $checked = null;
        if ($checked !== null) {
            return $checked;
        }

        $installerClass = '\\app\\plugin\\gongxianzhi\\common\\GongxianzhiInstaller';
        if (!class_exists($installerClass)) {
            Log::warning('贡献值插件安装器不存在，按未安装贡献值插件处理');
            $checked = false;
            return false;
        }

        try {
            $installerClass::ensureInstalled();
            $checked = true;
            return true;
        } catch (\Throwable $e) {
            Log::warning('贡献值插件安装器执行失败，按未安装贡献值插件处理：' . $e->getMessage());
            $checked = false;
            return false;
        }
    }

    private static function tableExistsDirect(string $table): bool
    {
        try {
            $fullTable = config('database.connections.mysql.prefix', '') . preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            return !empty(Db::query("SHOW TABLES LIKE '{$fullTable}'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function columnExistsDirect(string $table, string $column): bool
    {
        try {
            $prefix = config('database.connections.mysql.prefix', '');
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
            return !empty(Db::query("SHOW COLUMNS FROM `{$prefix}{$table}` LIKE '{$column}'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function indexExistsDirect(string $table, string $indexName): bool
    {
        try {
            $prefix = config('database.connections.mysql.prefix', '');
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $indexName = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
            return !empty(Db::query("SHOW INDEX FROM `{$prefix}{$table}` WHERE Key_name = '{$indexName}'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function columnExistsStatic(string $table, string $column): bool
    {
        static $cache = [];
        $cacheKey = $table . ':' . $column;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        try {
            $prefix = config('database.connections.mysql.prefix', '');
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
            $cache[$cacheKey] = !empty(Db::query("SHOW COLUMNS FROM `{$prefix}{$table}` LIKE '{$column}'"));
        } catch (\Throwable $e) {
            $cache[$cacheKey] = false;
        }

        return $cache[$cacheKey];
    }

    private static function columnsExistStatic(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!self::columnExistsStatic($table, (string)$column)) {
                return false;
            }
        }

        return true;
    }

    private static function isPluginEnabledStatic(int $aid, string $pluginName): bool
    {
        static $cache = [];
        $pluginName = preg_replace('/[^a-zA-Z0-9_]/', '', trim($pluginName));
        if ($aid <= 0 || $pluginName === '') {
            return false;
        }

        $cacheKey = $aid . ':' . $pluginName;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        try {
            if (self::tableExistsStatic('admin_plugin_license')) {
                $license = Db::name('admin_plugin_license')
                    ->where('aid', $aid)
                    ->where('plugin_name', $pluginName)
                    ->find();

                if (!empty($license)) {
                    $active = intval($license['status'] ?? 1) === 1;
                    $expireTime = intval($license['expire_time'] ?? 0);
                    if (!$active || ($expireTime > 0 && $expireTime <= time())) {
                        $cache[$cacheKey] = false;
                        return false;
                    }

                    if (self::tableExistsStatic('admin_plugin_menu')) {
                        $menuCount = Db::name('admin_plugin_menu')
                            ->where('aid', $aid)
                            ->where('plugin_name', $pluginName)
                            ->count();
                        if ($menuCount > 0) {
                            $cache[$cacheKey] = Db::name('admin_plugin_menu')
                                ->where('aid', $aid)
                                ->where('plugin_name', $pluginName)
                                ->where('status', 1)
                                ->count() > 0;
                            return $cache[$cacheKey];
                        }
                    }

                    $cache[$cacheKey] = true;
                    return true;
                }

                $licenseCount = Db::name('admin_plugin_license')
                    ->where('aid', $aid)
                    ->count();
                if ($licenseCount > 0) {
                    $cache[$cacheKey] = false;
                    return false;
                }
            }

            if (!self::tableExistsStatic('admin_plugin_menu')) {
                $cache[$cacheKey] = false;
                return false;
            }

            $cache[$cacheKey] = Db::name('admin_plugin_menu')
                ->where('aid', $aid)
                ->where('plugin_name', $pluginName)
                ->where('status', 1)
                ->count() > 0;
        } catch (\Throwable $e) {
            $cache[$cacheKey] = false;
        }

        return $cache[$cacheKey];
    }

    private static function getEnabledLiandongSet($aid, array $requiredTables = [], array $requiredColumns = []): ?array
    {
        $aid = intval($aid);
        if ($aid <= 0 || !self::isPluginEnabledStatic($aid, 'liandong')) {
            return null;
        }

        $requiredTables[] = 'liandong_set';
        foreach (array_unique($requiredTables) as $table) {
            if (!self::tableExistsStatic($table)) {
                return null;
            }
        }

        foreach ($requiredColumns as $table => $columns) {
            if (!self::tableExistsStatic((string)$table) || !self::columnsExistStatic((string)$table, (array)$columns)) {
                return null;
            }
        }

        try {
            $set = Db::name('liandong_set')->where('aid', $aid)->find();
        } catch (\Throwable $e) {
            return null;
        }

        return !empty($set) ? $set : null;
    }

    private static function normalizeAmountString($amount): string
    {
        return number_format((float)$amount, 5, '.', '');
    }

    public static function douplv(
        $aid,
        $member,
        $orderid = 0,
        $levelid = 0,
        bool $throwOnLockTimeout = false,
        array $context = []
    )
    {
        $aid = intval($aid);
        $mid = intval($member['id'] ?? 0);
        if ($aid <= 0 || $mid <= 0) {
            return false;
        }

        $lockName = 'member_uplv_' . $aid . '_' . $mid;
        $lockAcquired = false;
        for ($attempt = 1; $attempt <= self::UPLV_LOCK_ATTEMPTS; $attempt++) {
            $lockResult = Db::query("SELECT GET_LOCK('{$lockName}', " . self::UPLV_LOCK_TIMEOUT_SECONDS . ') AS uplv_lock');
            $lockAcquired = intval($lockResult[0]['uplv_lock'] ?? 0) === 1;
            if ($lockAcquired) {
                break;
            }
            if ($attempt < self::UPLV_LOCK_ATTEMPTS) {
                usleep(100000 * $attempt);
            }
        }

        if (!$lockAcquired) {
            $message = 'member upgrade lock wait timeout: aid=' . $aid . ', mid=' . $mid;
            Log::warning($message);
            if ($throwOnLockTimeout) {
                throw new \RuntimeException($message);
            }
            return false;
        }

        try {
            // 等待锁期间会员等级可能已经变化，必须用锁内最新数据重新计算。
            $currentMember = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
            if (!$currentMember) {
                return false;
            }
            return self::douplvLocked(
                $aid,
                $currentMember,
                intval($orderid),
                intval($levelid),
                $context
            );
        } finally {
            try {
                Db::query("SELECT RELEASE_LOCK('{$lockName}')");
            } catch (\Throwable $e) {
                Log::warning('会员升级锁释放失败: aid=' . $aid . ', mid=' . $mid . ', error=' . $e->getMessage());
            }
        }
    }

    private static function douplvLocked($aid, $member, $orderid = 0, $levelid = 0, array $context = []){
        // ========== douplv 性能日志开始 ==========
        $douplv_start = microtime(true);
        $mid = $member['id'];
        \think\facade\Log::write("        [douplv] 开始执行用户{$mid}的升级计算");
        
        // 步骤1：统计微信支付总额
        $step_start = microtime(true);
        $wxpaymoney = 0 + Db::name('wxpay_log')->where('aid',$aid)->where('mid',$mid)->sum('total_fee'); //查找微信支付日志; //获取微信支付总额;
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("        [douplv-步骤1] 统计微信支付总额 - 耗时: {$step_time}ms, 金额: {$wxpaymoney}");
        
        // 步骤2：统计订单总额
        $step_start = microtime(true);
        $orderStatuses = MemberUpgradeOrderStatusPolicy::statuses(intval($aid));
        $ordermoney = 0 + Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->whereIn('status',$orderStatuses)->sum('product_price'); //获取商店有效订单总额;
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("        [douplv-步骤2] 统计订单总额 - 耗时: {$step_time}ms, 金额: {$ordermoney}");
        
        // 步骤3：统计充值总额
        $step_start = microtime(true);
        $rechargemoney = 0 + Db::name('recharge_order')->where('aid',$aid)->where('mid',$mid)->where('status',1)->sum('money');
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("        [douplv-步骤3] 统计充值总额 - 耗时: {$step_time}ms, 金额: {$rechargemoney}");

        // 步骤4：执行等级计算（可能非常耗时）
        $step_start = microtime(true);
        $executionResult = self::upLevel(
            $aid,
            $member,
            $member,
            $ordermoney,
            $wxpaymoney,
            $rechargemoney,
            0,
            $orderid,
            $levelid,
            $context
        );
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("        [douplv-步骤4] 🔥 执行等级计算(upLevel) - 耗时: {$step_time}ms 🔥");

        // ========== douplv 性能日志结束 ==========
        $douplv_time = round((microtime(true) - $douplv_start) * 1000, 2);
        $resultCode = MemberUpgradeExecutionResult::code($executionResult);
        \think\facade\Log::write("        [douplv] 用户{$mid}升级计算完成 - 结果:{$resultCode}, 总耗时: {$douplv_time}ms");
        
        //其他分组等级
        return $executionResult;
        }

    /**
     * @param $aid
     * @param $member
     * @param $levelInfo 等级信息 levelid,levelstarttime
     * @param $ordermoney
     * @param $wxpaymoney
     * @param $rechargemoney
     * @param $cid 其他分组等级为空时使用此字段
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function upLevel(
        $aid,
        $member,
        $levelInfo,
        $ordermoney,
        $wxpaymoney,
        $rechargemoney,
        $cid = 0,
        $orderid = 0,
        $levelid = 0,
        array $context = []
    )
    {
        $mid = intval($member['id'] ?? 0);
        $beforeLevelId = intval($levelInfo['levelid'] ?? 0);
        if ($mid <= 0 || $beforeLevelId <= 0) {
            return false;
        }
        $orderStatuses = MemberUpgradeOrderStatusPolicy::statuses(intval($aid));
        if ($beforeLevelId === 1) {
            $nowlv = Db::name('member_level')->where('id', $beforeLevelId)->find();
        } else {
            $nowlv = Db::name('member_level')
                ->where('aid', $aid)
                ->where('id', $beforeLevelId)
                ->find();
        }
        if (!$nowlv) {
            return false;
        }

        $cid = $cid ? $cid : intval($nowlv['cid'] ?? 0);
        //等级列表
        if($levelid>0){
            $lvlist = Db::name('member_level')->where('aid',$aid)->where('id','=',$levelid)->order('sort,id')->select(); //
        }else{
            //->where('sort', '>',$nowlv['sort'])
             $lvlist = Db::name('member_level')->where('aid',$aid)->where('cid', $cid)->where('can_up',1)->order('sort,id')->select();
        }
        $newlv = $nowlv;
        $matchedEligibleLevel = false;
        $ordermoneys = self::resolveUplvSingleOrderAmount($aid, $mid, intval($orderid), $context);
        foreach($lvlist as $lv){
            $isup = false;
            $condition_or = false;
            $requiredConditionFailed = false;
            $structuredRulesPresent = false;
            $structuredAndMatched = true;
            $structuredOrConfigured = false;
            $structuredOrMatched = false;
            $legacyEligibilityConfigured = false;
            $zichanfenqiAmount = null;
            $zichanfenqiTeamAmount = null;
            $upMydengjiIds = array_filter(
                array_map('trim', explode(',', str_replace('，', ',', (string)($lv['up_mydengji'] ?? '')))),
                function($id) {
                    return $id !== '' && $id !== '0';
                }
            );
            $matchZichanfenqiUpgrade = function() use (&$zichanfenqiAmount, $aid, $mid, $lv) {
                if (intval($lv['up_zichanfenqi_status'] ?? 0) !== 1) {
                    return false;
                }
                if (trim(strval($lv['up_zichanfenqi_asset_type'] ?? '')) === '' || floatval($lv['up_zichanfenqi_amount'] ?? 0) <= 0) {
                    return false;
                }
                if ($zichanfenqiAmount === null) {
                    $zichanfenqiAmount = self::calculateZichanfenqiUpgradeAmount($aid, $mid, $lv);
                }
                return $zichanfenqiAmount >= floatval($lv['up_zichanfenqi_amount'] ?? 0);
            };
            $matchZichanfenqiTeamUpgrade = function() use (&$zichanfenqiTeamAmount, $aid, $mid, $lv) {
                if (intval($lv['up_zichanfenqi_team_status'] ?? 0) !== 1) {
                    return false;
                }
                if (trim(strval($lv['up_zichanfenqi_team_asset_type'] ?? '')) === '' || floatval($lv['up_zichanfenqi_team_amount'] ?? 0) <= 0) {
                    return false;
                }
                if ($zichanfenqiTeamAmount === null) {
                    $zichanfenqiTeamAmount = self::calculateZichanfenqiTeamUpgradeAmount($aid, $mid, $lv);
                }
                return $zichanfenqiTeamAmount >= floatval($lv['up_zichanfenqi_team_amount'] ?? 0);
            };
            if($lv['maxnum'] > 0){
                $lvmembercount = Db::name('member')->where('aid',$aid)->where('levelid',$lv['id'])->count();
                if($lvmembercount >= $lv['maxnum']) continue;
            }

            $structuredLevel = is_array($lv) ? $lv : $lv->toArray();
            $structuredRulesPresent = MemberUpgradeRuleConfig::hasRawConfig($structuredLevel);
            if ($structuredRulesPresent) {
                // 后台显式指定等级沿用原有强制升级语义。
                if (intval($levelid) > 0) {
                    $newlv = $lv;
                    $matchedEligibleLevel = true;
                    continue;
                }
                $structuredEvaluation = MemberUpgradeBoxConditionService::evaluateLevel(
                    intval($aid),
                    intval($mid),
                    $structuredLevel
                );
                if (intval($structuredEvaluation['valid'] ?? 0) !== 1) {
                    continue;
                }
                foreach ($structuredEvaluation['rules'] ?? [] as $ruleResult) {
                    $ruleMatched = intval($ruleResult['matched'] ?? 0) === 1;
                    if (strval($ruleResult['operator'] ?? 'and') === 'or') {
                        $structuredOrConfigured = true;
                        $structuredOrMatched = $structuredOrMatched || $ruleMatched;
                    } elseif (!$ruleMatched) {
                        $structuredAndMatched = false;
                    }
                }
                $legacyEligibilityConfigured = MemberUpgradeRuleConfig::hasLegacyEligibilityConditions(
                    $structuredLevel
                );
                // JSON 的 OR 与旧 OR 共用触发组，JSON 的 AND 在结尾作为必选门槛复核。
                $isup = $structuredOrMatched
                    || (!$structuredOrConfigured
                        && $structuredAndMatched
                        && !MemberUpgradeRuleConfig::hasLegacyOrConditions($structuredLevel));
                $condition_or = $structuredOrConfigured;
            }
            
            if(($lv['up_ordermoney']>0 && $lv['up_ordermoney'] <= $ordermoney) || ($lv['up_wxpaymoney']>0 && $lv['up_wxpaymoney'] <= $wxpaymoney)|| ($lv['up_rechargemoney']>0 && $lv['up_rechargemoney'] <= $rechargemoney) || ($member['card_code'] && $lv['up_getmembercard']==1) || (!empty($upMydengjiIds) && in_array((string)$member['levelid'], $upMydengjiIds, true)) ||($levelid >0) || ($lv['oneordermoney']>0 && $lv['oneordermoney'] <= $ordermoneys) ){
                $isup = true;
            }
           
            
            if(!$isup && $lv['up_fxordermoney'] > 0 && $lv['up_fxorder_condition'] == 'or'){
                if($lv['up_fxordermoney_removemax'] ==1){
                    $downmids = self::getdownmids_removemax($aid,$mid,$lv['up_fxorderlevelnum'],$lv['up_fxorderlevelid']);
                    //\think\facade\Log::write('---'.$mid.'去除最高的业绩后会员id---');
                    //\think\facade\Log::write($downmids);
                }else{
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxorderlevelnum'],$lv['up_fxorderlevelid']);
                }
                $fxordermoney = 0 + Db::name('shop_order_goods')->whereIn('status',$orderStatuses)->where('mid','in',$downmids)->where('is_yeji','1')->sum('real_totalprice');
                if($fxordermoney >= $lv['up_fxordermoney']){
                    $isup = true;
                }
            }


            //或的时候
            if(!$isup && $lv['up_fxordermoney_two'] > 0 && $lv['up_fxorder_condition_two'] == 'or'){
                //var_dump('走这里111');
                if($lv['up_fxordermoney_removemax'] ==1){
                    $downmids = self::getdownmids_removemax($aid,$mid,$lv['up_fxorderlevelnum_two'],$lv['up_fxorderlevelid_two']);
                }else{
                    $downmids = self::getdownmids($aid,$mid,1,$lv['up_fxorderlevelid_two']);
                }
                $downmids[] = $mid;
                // var_dump($downmids);die;
                        $performanceList = [];
                    $line_totals = [];
                    foreach ($downmids as $subordinateMid) {
                        // var_dump($subordinateMid);die;
                        if ($subordinateMid == $mid) {
                            // 如果是自己的ID，只计算自己的业绩
                            // 注意：用户自己的订单升级时应该包含所有订单（包括第三单is_yeji=0），
                            // 因为第三单虽然不参与分佣给上级，但应该能用于用户自己的升级
                            $subordinatePerformance = Db::name('shop_order_goods')
                                ->whereIn('status', $orderStatuses)
                                ->where('mid', $mid)
                                ->sum('real_totalprice');
                        } else {
                            // 否则，计算包括所有下级的总业绩
                            $allSubordinates = self::getdownmids($aid, $subordinateMid, $lv['up_fxorderlevelnum']);
                            $allSubordinates[] = $subordinateMid; // 包含直推下级自身
                            //  var_dump($allSubordinates);die;
                            $subordinatePerformance = Db::name('shop_order_goods')
                                ->whereIn('status', $orderStatuses)
                                ->where('is_yeji', '1')
                                ->where('mid', 'in', $allSubordinates)
                                ->sum('real_totalprice');
                        }
                    
                        $line_totals[$subordinateMid] = $subordinatePerformance;
                    }
                    // 当只有一个直推下级时的特殊处理
                    // var_dump( $line_totals);die;
                    if (count($line_totals) == 1) {
                        // 当只有一个下级时，大区业绩为该下级的业绩，小区业绩为0
                        $max_totalprice = reset($line_totals); // 获取该下级的业绩作为大区业绩
                        $min_totalprice = 0; // 小区业绩设置为0
                    } else {
                        // 计算大小区
                        arsort($line_totals); // 业绩降序排序
                        $max_totalprice = reset($line_totals); // 最大业绩
                        array_shift($line_totals); // 移除大区业绩
                        $min_totalprice = array_sum($line_totals); // 小区总业绩
                    }


//  \think\facade\Log::write('用户: ' . $mid);
 
//  \think\facade\Log::write('小区业绩: ' . $min_totalprice);
 
//  \think\facade\Log::write('大区业绩: ' . $max_totalprice);
//                 \think\facade\Log::write('当前业绩计算值: ' . $fxordermoney);
//                 \think\facade\Log::write('目标业绩阈值: ' . $lv['up_fxordermoney_two']);

                    //      var_dump('self'.$max_line);
                    //      var_dump('self'.$self_line_total);
                    // var_dump('大区'.$max_totalprice);
                    // var_dump('小区'.$min_totalprice);die;
                    // 小区业绩*3倍大于大区业绩就按照 ，总业绩就等于所有业绩之和 如果小区业绩*3倍小于等于大区业绩，总业绩就等于所有小区业绩之和乘以2
                    if($min_totalprice*2 > $max_totalprice){
                        $fxordermoney=$min_totalprice+$max_totalprice;
                    }else{
                        $fxordermoney=$min_totalprice*2;
                    }
                    
                    //小区业绩*3倍大于大区业绩,总业绩就等于所有业绩之和  或  小区业绩*3倍小于等于大区业绩，总业绩就等于所有小区业绩之和
                    // if($min_totalprice*3 <= $max_totalprice){
                    //     $fxordermoney=$min_totalprice;
                    // }
                    // //如果小区业绩2倍大于等于大区业绩就按照 ，它的考核总业绩就等于所有业绩之和如果小区业绩2倍小于大区业绩，它的考核总业绩就等于所有小区业绩的3倍
                    // if($min_totalprice*2<$max_totalprice){
                    //     $fxordermoney=$min_totalprice*3;
                    // }
                
                // var_dump($fxordermoney);die;
                // var_dump($lv['up_fxordermoney_two']);
                //加上自己的业绩
                // $midordermoney= 0 + Db::name('shop_order_goods')->where('status','in','1,2,3')->where('is_yeji','1')->where('mid',$mid)->sum('real_totalprice');
                // // var_dump($midordermoney);die;
                // $fxordermoney=$fxordermoney+$midordermoney;
                    
                if($fxordermoney >= $lv['up_fxordermoney_two']){
                    // var_dump(555);
                    // var_dump($lv['up_fxordermoney_two']);die;
                    $isup_up_fxordermoney = true;
                    $isup = true;
                   
                } else {
                    // var_dump(666);
                    $isup = false;
                }
                // die;
             
            }
// var_dump($isup);die;
            //小区业绩（去除一个大区）条件判断
          
            if(!$isup && $lv['up_fxordermoney_three'] > 0 && $lv['up_fxorder_condition_three'] == 'or'){
                // 使用新方法计算去除最大区的业绩
                $fxordermoney = self::getmytuanduiyeji_removemax($aid, $mid);
                // 记录日志
                //\think\facade\Log::write('用户: ' . $mid);
               // \think\facade\Log::write('小区业绩（去除一个大区）: ' . $fxordermoney);
               // \think\facade\Log::write('目标业绩阈值: ' . $lv['up_fxordermoney_three']);
                 
                if($fxordermoney >= $lv['up_fxordermoney_three']){
                    $isup = true;
                }
             
            }
            if(!$isup && $lv['up_fxordermoney_three'] > 0 && $lv['up_fxorder_condition_three'] == 'and'){
                // 使用新方法计算去除最大区的业绩
                $fxordermoney = self::getmytuanduiyeji_removemax($aid, $mid);
                // 记录日志
               // \think\facade\Log::write('and用户: ' . $mid);
               // \think\facade\Log::write('小区业绩（去除一个大区）: ' . $fxordermoney);
               // \think\facade\Log::write('目标业绩阈值: ' . $lv['up_fxordermoney_three']);
                 
                if($fxordermoney >= $lv['up_fxordermoney_three']){
                    $isup = true;
                }else {
                    // var_dump(666);
                   $isup = false;
        $condition_or = true; // 小区业绩条件不满足
                }
             
            }

            if(!$isup && $lv['up_fxordermoney_xiao'] > 0){
                $downmids = self::getdownmids_xiao($aid,$mid,$lv['up_fxorderlevelnum_xiao'],$lv['up_fxorderlevelid_xiao']);
                $fxordermoney = 0 + Db::name('shop_order_goods')->whereIn('status',$orderStatuses)->where('is_yeji','1')->where('mid','in',$downmids)->sum('real_totalprice');
                if($fxordermoney >= $lv['up_fxordermoney_xiao']){
                    $isup = true;
                }
            }

            if(!$isup && $lv['up_jifen'] > 0 && $lv['up_jifen_condition'] == 'or'){
                // $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->field('shengjiscore')->find();
                if($member['shengjiscore'] >= $lv['up_jifen']){
                    $isup = true;
                }
            }
            if(!$isup && intval($lv['up_zichanfenqi_status'] ?? 0) === 1 && ($lv['up_zichanfenqi_condition'] ?? 'or') == 'or'){
                if($matchZichanfenqiUpgrade()){
                    $isup = true;
                }
            }
            if(!$isup && intval($lv['up_zichanfenqi_team_status'] ?? 0) === 1 && ($lv['up_zichanfenqi_team_condition'] ?? 'or') == 'or'){
                if($matchZichanfenqiTeamUpgrade()){
                    $isup = true;
                }
            }

            // 新增：接点人小区业绩升级条件判断（或条件）
            if(!$isup && $lv['up_jiedian_xiaoqu_money'] > 0 && $lv['up_jiedian_xiaoqu_condition'] == 'or'){
                \think\facade\Log::write("开始检查接点人小区业绩升级条件（或条件）");
                \think\facade\Log::write("要求业绩: {$lv['up_jiedian_xiaoqu_money']}元");
                \think\facade\Log::write("计算类型: " . ($lv['up_jiedian_xiaoqu_type'] ?: 'turnover'));
                \think\facade\Log::write("用户接点人ID: " . ($member['jiedianpid'] ?? '未设置'));
                
                try {
                    // 构建查询条件
                    $conditions = [
                        'levelnum' => $lv['up_jiedian_xiaoqu_levelnum'] ?: 0,
                        'levelid' => $lv['up_jiedian_xiaoqu_levelid'] ?: 0,
                        'start_time' => 0,
                        'end_time' => 0,
                        'order_status' => $orderStatuses,
                        'is_yeji' => 1,
                        'include_self' => false,
                        'calculation_type' => $lv['up_jiedian_xiaoqu_type'] ?: 'turnover' // 计算类型
                    ];
                    
                    \think\facade\Log::write("查询条件: " . json_encode($conditions));
                    
                    // 使用JiedianPerformanceService计算接点人小区业绩
                    $jiedianXiaoquPerformance = JiedianPerformanceService::getJiedianXiaoquPerformance(
                        $aid, 
                        $mid, 
                        $conditions
                    );
                    
                    \think\facade\Log::write("用户{$mid}接点人小区业绩升级条件检查（或）：计算业绩={$jiedianXiaoquPerformance}，要求业绩={$lv['up_jiedian_xiaoqu_money']}");
                    
                    if($jiedianXiaoquPerformance >= $lv['up_jiedian_xiaoqu_money']){
                        $isup = true;
                        $condition_or = true;
                        \think\facade\Log::write("✅ 用户{$mid}满足接点人小区业绩升级条件（或）：{$jiedianXiaoquPerformance} >= {$lv['up_jiedian_xiaoqu_money']}");
                    } else {
                        \think\facade\Log::write("❌ 用户{$mid}不满足接点人小区业绩升级条件（或）：{$jiedianXiaoquPerformance} < {$lv['up_jiedian_xiaoqu_money']}");
                    }
                } catch(\Exception $e) {
                    \think\facade\Log::write("❌ 接点人小区业绩升级条件检查异常（或）：" . $e->getMessage());
                    \think\facade\Log::write("异常堆栈: " . $e->getTraceAsString());
                }
            } else if($lv['up_jiedian_xiaoqu_money'] > 0 && $lv['up_jiedian_xiaoqu_condition'] == 'or') {
                \think\facade\Log::write("跳过接点人小区业绩升级条件（或）检查：isup={$isup}");
            }

            // 新增：接点人市场数量和业绩升级条件判断（或条件）
            if(!$isup && $lv['up_jiedian_market_count'] > 0 && $lv['up_jiedian_market_money'] > 0 && $lv['up_jiedian_market_condition'] == 'or'){
                try {
                    // 使用JiedianPerformanceService检查市场条件
                    $marketCheck = JiedianPerformanceService::checkJiedianMarketCondition(
                        $aid, 
                        $mid, 
                        $lv['up_jiedian_market_count'],
                        $lv['up_jiedian_market_money']
                    );
                    
                    \think\facade\Log::write("用户{$mid}接点人市场升级条件检查：合格市场数={$marketCheck['qualified_market_count']}，要求={$lv['up_jiedian_market_count']}，每个市场要求业绩={$lv['up_jiedian_market_money']}");
                    
                    if($marketCheck['is_satisfied']){
                        $isup = true;
                        \think\facade\Log::write("用户{$mid}满足接点人市场升级条件");
                    }
                } catch(\Exception $e) {
                    \think\facade\Log::write("接点人市场升级条件检查异常：" . $e->getMessage());
                }
            }

            // 接点人数和市场升级条件（或条件）
            if(
                !$isup
                && intval($lv['up_jiedian_renshu'] ?? 0) > 0
                && (($lv['up_jiedian_renshu_condition'] ?? 'or') == 'or')
            ){
                $directJiedianMembers = MemberRelationService::getDirectJiedianDownMembers($aid, $mid);
                if(count($directJiedianMembers) >= intval($lv['up_jiedian_renshu'] ?? 0)){
                    $qualifiedJiedianCount = count($directJiedianMembers);
                    if(intval($lv['up_jiedian_renshu_count'] ?? 0) > 0 && intval($lv['up_jiedian_renshu_shichang'] ?? 0) > 0){
                        $targetLevel = Db::name('member_level')->where('aid', $aid)->where('id', intval($lv['up_jiedian_renshu_shichang']))->find();
                        if($targetLevel){
                            $qualifiedLevelIds = Db::name('member_level')
                                ->where('aid', $aid)
                                ->where('can_up', 1)
                                ->where('sort', '>=', $targetLevel['sort'])
                                ->order('sort,id')
                                ->column('id');
                            $qualifiedJiedianCount = self::countQualifiedJiedianMarkets($aid, $mid, $qualifiedLevelIds, intval($lv['up_jiedian_renshu_count'] ?? 0));
                        }else{
                            $qualifiedJiedianCount = 0;
                        }
                    }
                    if($qualifiedJiedianCount >= intval($lv['up_jiedian_renshu_count'] ?? 0)){
                        if(floatval($lv['up_jiedian_renshu_tuanduiyeji'] ?? 0) > 0){
                            $jiedianTeamPerformance = JiedianPerformanceService::getJiedianTotalPerformance($aid, $mid, [
                                'include_self' => false,
                            ]);
                            if($jiedianTeamPerformance >= floatval($lv['up_jiedian_renshu_tuanduiyeji'] ?? 0)){
                                $isup = true;
                            }
                        }else{
                            $isup = true;
                        }
                    }
                }
            }

            if(!$isup && ($lv['up_fxdowncount']>0 || $lv['up_fxdowncount2']>0 || $lv['up_fxdowncount3']>0)){
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                $downmidcount3 = 0;
                $up_fxdowncount = intval($lv['up_fxdowncount']);
                $up_fxdowncount2 = intval($lv['up_fxdowncount2']);
                $up_fxdowncount3 = intval($lv['up_fxdowncount3']);
                if($lv['up_fxdowncount'] > 0){
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum'],$lv['up_fxdownlevelid']);
                    $downmidcount1 = count($downmids);
                }
                if($lv['up_fxdowncount2'] > 0){
                    $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2'],$lv['up_fxdownlevelid2']);
                    $downmidcount2 = count($downmids2);
                }
                if($lv['up_fxdowncount3'] > 0){
                    $downmids3 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum3'],$lv['up_fxdownlevelid3']);
                    $downmidcount3 = count($downmids3);
                }
                if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2 && $downmidcount3 >= $up_fxdowncount3){
                    $isup = true;
                }
            }
            // 第一个
            if(!$isup && ($lv['up_fxdowncount_new1']>0 || $lv['up_fxdowncount2_new1']>0)){
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                $downmidcount3 = 0;
                $up_fxdowncount = intval($lv['up_fxdowncount_new1']);
                $up_fxdowncount2 = intval($lv['up_fxdowncount2_new1']);
                if($lv['up_fxdowncount_new1'] > 0){
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum_new1'],$lv['up_fxdownlevelid_new1']);
                    $downmidcount1 = count($downmids);
                }
                if($lv['up_fxdowncount2_new1'] > 0){
                    $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2_new1'],$lv['up_fxdownlevelid2_new1']);
                    $downmidcount2 = count($downmids2);
                }
                if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2){
                    $isup = true;
                }
            }   
            //第二个
            if(!$isup && ($lv['up_fxdowncount_new2']>0 || $lv['up_fxdowncount2_new2']>0)){
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                $downmidcount3 = 0;
                $up_fxdowncount = intval($lv['up_fxdowncount_new2']);
                $up_fxdowncount2 = intval($lv['up_fxdowncount2_new2']);
                if($lv['up_fxdowncount_new2'] > 0){
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum_new2'],$lv['up_fxdownlevelid_new2']);
                    $downmidcount1 = count($downmids);
                }
                if($lv['up_fxdowncount2_new2'] > 0){
                    $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2_new2'],$lv['up_fxdownlevelid2_new2']);
                    $downmidcount2 = count($downmids2);
                }
                if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2){
                    $isup = true;
                }
            }   
            //第三个
            if(!$isup && ($lv['up_fxdowncount_new3']>0 || $lv['up_fxdowncount2_new3']>0)){
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                $downmidcount3 = 0;
                $up_fxdowncount = intval($lv['up_fxdowncount_new3']);
                $up_fxdowncount2 = intval($lv['up_fxdowncount2_new3']);
                if($lv['up_fxdowncount_new3'] > 0){
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum_new3'],$lv['up_fxdownlevelid_new3']);
                    $downmidcount1 = count($downmids);
                }
                if($lv['up_fxdowncount2_new3'] > 0){
                    $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2_new3'],$lv['up_fxdownlevelid2_new3']);
                    $downmidcount2 = count($downmids2);
                }
                if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2){
                    $isup = true;
                }
            }       
            //第四个
            if(!$isup && ($lv['up_fxdowncount_new4']>0 || $lv['up_fxdowncount2_new4']>0)){
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                $downmidcount3 = 0;
                $up_fxdowncount = intval($lv['up_fxdowncount_new4']);
                $up_fxdowncount2 = intval($lv['up_fxdowncount2_new4']);
                if($lv['up_fxdowncount_new4'] > 0){
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum_new4'],$lv['up_fxdownlevelid_new4']);
                    $downmidcount1 = count($downmids);
                }
                if($lv['up_fxdowncount2_new4'] > 0){
                    $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2_new4'],$lv['up_fxdownlevelid2_new4']);
                    $downmidcount2 = count($downmids2);
                }
                if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2){
                    $isup = true;
                }
            }       
            //第五个
            if(!$isup && ($lv['up_fxdowncount_new5']>0 || $lv['up_fxdowncount2_new5']>0)){
                $downmidcount1 = 0;
                $downmidcount2 = 0;
                $downmidcount3 = 0;
                $up_fxdowncount = intval($lv['up_fxdowncount_new5']);
                $up_fxdowncount2 = intval($lv['up_fxdowncount2_new5']);
                if($lv['up_fxdowncount_new5'] > 0){
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum_new5'],$lv['up_fxdownlevelid_new5']);
                    $downmidcount1 = count($downmids);
                }
                if($lv['up_fxdowncount2_new5'] > 0){
                    $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2_new5'],$lv['up_fxdownlevelid2_new5']);
                    $downmidcount2 = count($downmids2);
                }
                if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2){
                    $isup = true;
                }
            }       
                
            if(!$isup && ($lv['up_proid']!='0' && $lv['up_proid']!='')){ //购买指定商品
                $up_proids = explode(',',str_replace('，',',',$lv['up_proid']));
                $up_pronums = explode(',',str_replace('，',',',$lv['up_pronum']));
                if(count($up_pronums) > 1) {
                    foreach($up_proids as $k=>$up_proid){
                        $pronum = $up_pronums[$k];
                        if(!$pronum) $pronum = 1;
                        // 用户自己购买商品数量升级条件：包含所有订单（包括第三单），因为第三单应该能用于用户自己的升级
                        $buynum = Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->whereIn('status',$orderStatuses)->sum('num');
                        if($buynum >= $pronum){
                            $isup = true;
//                        break;
                        }
                    }
                } else {
                    $pronum = $up_pronums[0];
                    if(!$pronum) $pronum = 1;
                    $buynum = 0;
                    foreach($up_proids as $k=>$up_proid){
                        // 用户自己购买商品数量升级条件：包含所有订单（包括第三单），因为第三单应该能用于用户自己的升级
                        $buynum += Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$up_proid)->whereIn('status',$orderStatuses)->sum('num');
                        if($buynum >= $pronum){
                            $isup = true;
//                        break;
                        }
                    }
                }
            }
            // var_dump($lv['up_zt_new']);
            //判断直推2等级id1业绩达到5000
            //up_zt_new  up_zt_new_dengjiid  up_zt_new_dengjiyeji
           
            if(!$isup && ($lv['up_zt_new']>0))
            {
                if($lv['up_zt_new_dengjiid']>0)
                {
                    $up_zt_new_dengjiid = Db::name('member_level')->where('aid',$aid)->where('id','=',$lv['up_zt_new_dengjiid'])->find();
                    $up_zt_new_dengjiid_dayu = Db::name('member_level')->where('aid',$aid)->where('sort','>=',$up_zt_new_dengjiid['sort'])->order('sort,id')->select()->toArray();
                }else{
                   $up_zt_new_dengjiid_dayu = Db::name('member_level')->where('aid',$aid)->order('sort,id')->select()->toArray(); 
                }
                $up_zt_new_dengjiid_dayuids = array_column($up_zt_new_dengjiid_dayu,'id');
                $up_zt_new_dengjiid_dayuids = implode(',',$up_zt_new_dengjiid_dayuids);
                ///获取我的直推下级
                $memberlist = Db::name('member')->field('id,nickname,createtime')->where('aid',$aid)->where('pid',$mid)->where('levelid','in',$up_zt_new_dengjiid_dayuids)->select()->toArray();
                $memberlistcount = count($memberlist);
                if($memberlistcount >=$lv['up_zt_new'])
                {
                    // var_dump(3444);
                    if($lv['up_zt_new_dengjiyeji'] >0)
                    {
                        //设置业绩判断团队业绩是否
                        $mytuandui = self::getmytuanduiyeji($aid,$mid);;
                        // var_dump($mytuandui);
                        if($mytuandui >= $lv['up_zt_new_dengjiyeji'])
                        {
                            $isup = true;
                        }
                    }else{
                         $isup = true;
                    }
                }
            }
            // var_dump($lv['up_zt_renshu']);
            //判断 直推3人且至少2个市场出现等级id 2 且团队业绩达到 5000元
            //up_zt_renshu  up_zt_renshu_count  up_zt_renshu_shichang   up_zt_renshu_tuanduiyeji
            if(!$isup && ($lv['up_zt_renshu']>0))
            {
                $zhituicount = Db::name('member')->field('id,nickname,createtime')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
                // var_dump(count($zhituicount));
                if(count($zhituicount) >= $lv['up_zt_renshu']){
                    // var_dump($lv['up_zt_renshu_count']);
                    // var_dump($lv['up_zt_renshu_shichang']);
                    if($lv['up_zt_renshu_count']>0 && $lv['up_zt_renshu_shichang']>0)
                    {
                        $up_zt_new_dengjiid = Db::name('member_level')->where('aid',$aid)->where('id','=',$lv['up_zt_renshu_shichang'])->find();
                        $up_zt_new_dengjiid_dayu = Db::name('member_level')->where('aid',$aid)->where('can_up',1)->where('sort','>=',$up_zt_new_dengjiid['sort'])->order('sort,id')->select()->toArray();
                    }else{
                         $up_zt_new_dengjiid_dayu = Db::name('member_level')->where('aid',$aid)->order('sort,id')->select()->toArray(); 
                    }
                    $up_zt_new_dengjiid_dayuids = array_column($up_zt_new_dengjiid_dayu,'id');
                    $up_zt_new_dengjiid_dayuids = implode(',',$up_zt_new_dengjiid_dayuids);
                    // var_dump($up_zt_new_dengjiid_dayuids);
                     ///获取我的直推下级
                    $memberlist = Db::name('member')->field('id,nickname,createtime')->where('aid',$aid)->where('pid',$mid)->where('levelid','in',$up_zt_new_dengjiid_dayuids)->select()->toArray();
                    $memberlistcount = count($memberlist);
                    // var_dump($memberlistcount);
                    if($memberlistcount>=$lv['up_zt_renshu_count'])
                    {
                        if($lv['up_zt_renshu_tuanduiyeji']>0)
                        {
                            //设置业绩判断团队业绩是否
                            $mytuandui = self::getmytuanduiyeji($aid,$mid);;
                            if($mytuandui >= $lv['up_zt_renshu_tuanduiyeji'])
                            {
                                $isup = true;
                            }
                        }else{
                            $isup = true; 
                        }
                    }
                }
                
            }
            /**或 判断直推多少人,多少市场出现等级idn*/
             if(!$isup && ($lv['up_zt_new3_duo']>0))
             {
                $q = 0;
                $zhituicount = Db::name('member')->field('id,nickname,createtime')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
                if(count($zhituicount) >= $lv['up_zt_new3_duo'])
                {
                    $qumemberid = '';
                    if($lv['up_zt_new3_dengjiid_shichang1'] >0 && $lv['up_zt_new3_dengjiid_duo1'] > 0 )
                    {
                        $memberlist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
                        $up_zt_new3_dengjiid_shichang1 = 0;
                        foreach($memberlist as $membervalue)
                        {
                            if($up_zt_new3_dengjiid_shichang1 <= $lv['up_zt_new3_dengjiid_shichang1']){
                                    $up_zt_new2_dengjiid_duo1ids = explode(',',$lv['up_zt_new3_dengjiid_duo1']);
                                    if(in_array($membervalue['levelid'],$up_zt_new2_dengjiid_duo1ids) !==false)
                                    {
                                        $membervaluelist = $membervalue;
                                    }else{
                                        $membervaluelist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('find_in_set('.$membervalue['id'].',path)')->whereIn('levelid',$up_zt_new2_dengjiid_duo1ids)->select()->toArray();
                                    }
                                if(!empty($membervaluelist))
                                {
                                    $up_zt_new3_dengjiid_shichang1 = $up_zt_new3_dengjiid_shichang1+1;
                                    $qumemberid = ','.$membervalue['id'];
                                }
                            }
                        }
                        if($up_zt_new3_dengjiid_shichang1 >= $lv['up_zt_new3_dengjiid_shichang1'])
                        {
                             $isup = true;
                             $q = 1;
                        }
                    }else{
                         $q=1;
                    }
                    if($lv['up_zt_new3_dengjiid_shichang2'] >0 && $lv['up_zt_new3_dengjiid_duo2'] > 0  && $q==1)
                    {
                        if($qumemberid)
                            {
                                $qumemberid = substr($qumemberid,0,1);
                                $memberlist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('pid',$mid)->where('id','not in',$qumemberid)->select()->toArray();
                            }else{
                                 $memberlist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
                            }
                            $up_zt_new3_dengjiid_shichang2 = 0;
                            foreach($memberlist as $membervalue)
                            {
                                $up_zt_new2_dengjiid_duo1ids = explode(',',$lv['up_zt_new3_dengjiid_duo2']);
                                if($up_zt_new3_dengjiid_shichang2 <= $lv['up_zt_new3_dengjiid_duo2']){
                                     if(in_array($membervalue['levelid'],$up_zt_new2_dengjiid_duo1ids) !==false)
                                    {
                                        $membervaluelist = $membervalue;
                                    }else{
                                        $membervaluelist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('find_in_set('.$membervalue['id'].',path)')->whereIn('levelid',$up_zt_new2_dengjiid_duo1ids)->select()->toArray();
                                    }
                                    if(!empty($membervaluelist))
                                    {
                                        $up_zt_new3_dengjiid_shichang2 = $up_zt_new3_dengjiid_shichang2+1;
                                    }
                                }
                            }
                            if($up_zt_new3_dengjiid_shichang2 >= $lv['up_zt_new3_dengjiid_shichang2'])
                            {
                                 $isup = true;
                                 $q = 1;
                            }
                    }else{
                        $q=1;
                    }
                    if($lv['up_zt_new3_tuanduiyeji'] > 0  && $q==1)
                    {
                        $mytuandui = self::getmytuanduiyeji($aid,$mid);
                        if($mytuandui >= $lv['up_zt_new3_tuanduiyeji'])
                        {
                            $isup = true;
                        }
                    }
                    
                }
                
             }
            // var_dump('第一个');
            // 新增：直推市场业绩条件检查
            if(!$isup && ($lv['up_zt_market_count'] > 0 && $lv['up_zt_market_money'] > 0))
            {
                $levelName = strval($lv['name'] ?? ('等级ID:' . intval($lv['id'] ?? 0)));
                \think\facade\Log::write('直推市场升级条件检查开始 - 会员ID:'.$mid.' 应用ID:'.$aid.' 等级:'.$levelName.' 要求市场数:'.$lv['up_zt_market_count'].' 每市场业绩:'.$lv['up_zt_market_money'].'万元 计算类型:'.$lv['up_zt_market_type']);
                
                // 获取直推下级列表
                $zhituiList = Db::name('member')->field('id,nickname')->where('aid', $aid)->where('pid', $mid)->select()->toArray();
                \think\facade\Log::write('直推下级列表数量:'.count($zhituiList).' 详情:'.json_encode($zhituiList));
                
                if(count($zhituiList) >= $lv['up_zt_market_count'])
                {
                    // 计算每个直推下级的市场业绩
                    $qualifiedMarkets = 0;
                    $targetAmount = $lv['up_zt_market_money'] * 10000; // 转换为分
                    \think\facade\Log::write('目标业绩金额:'.$targetAmount.'分 ('.$lv['up_zt_market_money'].'万元)');
                    
                    foreach($zhituiList as $zhitui) {
                        $marketPerformance = 0;
                        
                        if($lv['up_zt_market_type'] == 'profit') {
                            // 按利润计算
                            $marketPerformance = self::getMarketProfit($aid, $zhitui['id']);
                        } else {
                            // 按业绩计算（默认）
                            $marketPerformance = self::getMarketTurnover($aid, $zhitui['id']);
                        }
                        
                        \think\facade\Log::write('直推下级 '.$zhitui['nickname'].'(ID:'.$zhitui['id'].') 市场业绩:'.$marketPerformance.'分 ('.$marketPerformance/10000 .'万元) 计算类型:'.$lv['up_zt_market_type'].' 是否达标:'.($marketPerformance >= $targetAmount ? '是' : '否'));
                        
                        if($marketPerformance >= $targetAmount) {
                            $qualifiedMarkets++;
                        }
                    }
                    
                    \think\facade\Log::write('达标市场数:'.$qualifiedMarkets.' 要求市场数:'.$lv['up_zt_market_count'].' 是否升级:'.($qualifiedMarkets >= $lv['up_zt_market_count'] ? '是' : '否'));
                    
                    // 检查是否达到要求的市场数量
                    if($qualifiedMarkets >= $lv['up_zt_market_count']) {
                        $isup = true;
                        \think\facade\Log::write('直推市场升级条件满足，升级成功！会员ID:'.$mid);
                    } else {
                        \think\facade\Log::write('直推市场升级条件不满足，需要'.$lv['up_zt_market_count'].'个达标市场，实际只有'.$qualifiedMarkets.'个达标');
                    }
                } else {
                    \think\facade\Log::write('直推下级数量不足，需要至少'.$lv['up_zt_market_count'].'个直推下级，实际只有'.count($zhituiList).'个');
                }
            } else {
                if($isup) {
                    \think\facade\Log::write('已通过其他升级条件，跳过直推市场检查 - 会员ID:'.$mid);
                } else if($lv['up_zt_market_count'] <= 0 || $lv['up_zt_market_money'] <= 0) {
                    \think\facade\Log::write('直推市场升级条件未配置或无效 - 会员ID:'.$mid.' 市场数:'.$lv['up_zt_market_count'].' 业绩要求:'.$lv['up_zt_market_money']);
                }
            }
            
            //是否包含or的条件
            if($lv['up_ordermoney']>0  || $lv['oneordermoney']>0 || $lv['up_wxpaymoney']>0 || $lv['up_rechargemoney']>0 || $lv['up_getmembercard']==1 || !empty($upMydengjiIds) || ($lv['up_fxordermoney'] > 0 && $lv['up_fxorder_condition'] == 'or') || $lv['up_fxordermoney_xiao'] > 0
             || $lv['up_fxdowncount']>0 || $lv['up_fxdowncount2']>0 || $lv['up_fxdowncount3']>0 || ($lv['up_proid']!='0' && $lv['up_proid']!='') || ($lv['up_zt_new']>0) ||($lv['up_zt_renshu']>0) ||($lv['up_zt_new3_duo']>0) || ($lv['up_zt_market_count'] > 0 && $lv['up_zt_market_money'] > 0) || ($lv['up_jifen'] > 0 && $lv['up_jifen_condition'] == 'or') || (intval($lv['up_zichanfenqi_status'] ?? 0) === 1 && ($lv['up_zichanfenqi_condition'] ?? 'or') == 'or' && floatval($lv['up_zichanfenqi_amount'] ?? 0) > 0 && trim(strval($lv['up_zichanfenqi_asset_type'] ?? '')) !== '') || (intval($lv['up_zichanfenqi_team_status'] ?? 0) === 1 && ($lv['up_zichanfenqi_team_condition'] ?? 'or') == 'or' && floatval($lv['up_zichanfenqi_team_amount'] ?? 0) > 0 && trim(strval($lv['up_zichanfenqi_team_asset_type'] ?? '')) !== '') || (($lv['up_market_ab_total'] > 0 || $lv['up_market_other_min'] > 0) && $lv['up_market_condition'] == 'or') || (intval($lv['up_jiedian_renshu'] ?? 0) > 0 && (($lv['up_jiedian_renshu_condition'] ?? 'or') == 'or'))) {
                 $condition_or = true;
            }
            if(($isup || ($isup === false && $condition_or === false)) && $lv['up_fxordermoney'] > 0 && $lv['up_fxorder_condition'] == 'and'){
                if($lv['up_fxordermoney_removemax'] ==1){
                    $downmids = self::getdownmids_removemax($aid,$mid,$lv['up_fxorderlevelnum'],$lv['up_fxorderlevelid']);
                }else{
                    $downmids = self::getdownmids($aid,$mid,$lv['up_fxorderlevelnum'],$lv['up_fxorderlevelid']);
                }
                $fxordermoney = 0 + Db::name('shop_order_goods')->whereIn('status',$orderStatuses)->where('is_yeji','1')->where('mid','in',$downmids)->sum('real_totalprice');

                if($fxordermoney >= $lv['up_fxordermoney']){
                    $isup_up_fxordermoney = true;
                } else {
                    $isup = false;
                }
            }
        
            //var_dump(111);
            //var_dump($lv['up_fxordermoney_two']);
            //var_dump($lv['up_fxorder_condition_two']);
            if(($isup || ($isup === false && $condition_or === false)) && $lv['up_fxordermoney_two'] > 0 && $lv['up_fxorder_condition_two'] == 'and'){
                //var_dump('走这里222');
                  if($lv['up_fxordermoney_removemax'] ==1){
                    $downmids = self::getdownmids_removemax($aid,$mid,$lv['up_fxorderlevelnum_two'],$lv['up_fxorderlevelid_two']);
                }else{
                    $downmids = self::getdownmids($aid,$mid,1,$lv['up_fxorderlevelid_two']);
                }
                $downmids[] = $mid;
                // var_dump($downmids);die;
                        $performanceList = [];
                    $line_totals = [];
                    foreach ($downmids as $subordinateMid) {
                        // var_dump($subordinateMid);die;
                        if ($subordinateMid == $mid) {
                            // 如果是自己的ID，只计算自己的业绩
                            // 注意：用户自己的订单升级时应该包含所有订单（包括第三单is_yeji=0），
                            // 因为第三单虽然不参与分佣给上级，但应该能用于用户自己的升级
                            $subordinatePerformance = Db::name('shop_order_goods')
                                ->whereIn('status', $orderStatuses)
                                ->where('mid', $mid)
                                ->sum('real_totalprice');
                        } else {
                            // 否则，计算包括所有下级的总业绩
                            $allSubordinates = self::getdownmids($aid, $subordinateMid, $lv['up_fxorderlevelnum']);
                            $allSubordinates[] = $subordinateMid; // 包含直推下级自身
                            //  var_dump($allSubordinates);die;
                            $subordinatePerformance = Db::name('shop_order_goods')
                                ->whereIn('status', $orderStatuses)
                                ->where('is_yeji', '1')
                                ->where('mid', 'in', $allSubordinates)
                                ->sum('real_totalprice');
                        }
                    
                        $line_totals[$subordinateMid] = $subordinatePerformance;
                    }
                    // 当只有一个直推下级时的特殊处理
                    // var_dump( $line_totals);die;
                    if (count($line_totals) == 1) {
                        // 当只有一个下级时，大区业绩为该下级的业绩，小区业绩为0
                        $max_totalprice = reset($line_totals); // 获取该下级的业绩作为大区业绩
                        $min_totalprice = 0; // 小区业绩设置为0
                    } else {
                        // 计算大小区
                        arsort($line_totals); // 业绩降序排序
                        $max_totalprice = reset($line_totals); // 最大业绩
                        array_shift($line_totals); // 移除大区业绩
                        $min_totalprice = array_sum($line_totals); // 小区总业绩
                    }


//  \think\facade\Log::write('用户: ' . $mid);
 
//  \think\facade\Log::write('小区业绩: ' . $min_totalprice);
 
//  \think\facade\Log::write('大区业绩: ' . $max_totalprice);
//                 \think\facade\Log::write('当前业绩计算值: ' . $fxordermoney);
//                 \think\facade\Log::write('目标业绩阈值: ' . $lv['up_fxordermoney_two']);

                    //      var_dump('self'.$max_line);
                    //      var_dump('self'.$self_line_total);
                    // var_dump('大区'.$max_totalprice);
                    // var_dump('小区'.$min_totalprice);die;
                    // 小区业绩*3倍大于大区业绩就按照 ，总业绩就等于所有业绩之和 如果小区业绩*3倍小于等于大区业绩，总业绩就等于所有小区业绩之和乘以2
                    if($min_totalprice*2 > $max_totalprice){
                        $fxordermoney=$min_totalprice+$max_totalprice;
                    }else{
                        $fxordermoney=$min_totalprice*2;
                    }
                    
                    //小区业绩*3倍大于大区业绩,总业绩就等于所有业绩之和  或  小区业绩*3倍小于等于大区业绩，总业绩就等于所有小区业绩之和
                    // if($min_totalprice*3 <= $max_totalprice){
                    //     $fxordermoney=$min_totalprice;
                    // }
                    // //如果小区业绩2倍大于等于大区业绩就按照 ，它的考核总业绩就等于所有业绩之和如果小区业绩2倍小于大区业绩，它的考核总业绩就等于所有小区业绩的3倍
                    // if($min_totalprice*2<$max_totalprice){
                    //     $fxordermoney=$min_totalprice*3;
                    // }
                
                // var_dump($fxordermoney);die;
                // var_dump($lv['up_fxordermoney_two']);
                //加上自己的业绩
                // $midordermoney= 0 + Db::name('shop_order_goods')->where('status','in','1,2,3')->where('is_yeji','1')->where('mid',$mid)->sum('real_totalprice');
                // // var_dump($midordermoney);die;
                // $fxordermoney=$fxordermoney+$midordermoney;
                    
                if($fxordermoney >= $lv['up_fxordermoney_two']){
                    // var_dump(555);
                    // var_dump($lv['up_fxordermoney_two']);die;
                    $isup_up_fxordermoney = true;
                    $isup = true;
                   
                } else {
                    // var_dump(666);
                    $isup = false;
                }
                // die;
             
            }
            
            // 新增：接点人小区业绩升级条件（且条件）
            if(($isup || ($isup === false && $condition_or === false)) && $lv['up_jiedian_xiaoqu_money'] > 0 && $lv['up_jiedian_xiaoqu_condition'] == 'and'){
                \think\facade\Log::write("开始检查接点人小区业绩升级条件（且条件）");
                \think\facade\Log::write("要求业绩: {$lv['up_jiedian_xiaoqu_money']}元");
                \think\facade\Log::write("计算类型: " . ($lv['up_jiedian_xiaoqu_type'] ?: 'turnover'));
                \think\facade\Log::write("用户接点人ID: " . ($member['jiedianpid'] ?? '未设置'));
                
                try {
                    // 构建查询条件
                    $conditions = [
                        'levelnum' => $lv['up_jiedian_xiaoqu_levelnum'] ?: 0,
                        'levelid' => $lv['up_jiedian_xiaoqu_levelid'] ?: 0,
                        'start_time' => 0,
                        'end_time' => 0,
                        'order_status' => $orderStatuses,
                        'is_yeji' => 1,
                        'include_self' => false,
                        'calculation_type' => $lv['up_jiedian_xiaoqu_type'] ?: 'turnover' // 计算类型
                    ];
                    
                    \think\facade\Log::write("查询条件: " . json_encode($conditions));
                    
                    // 使用JiedianPerformanceService计算接点人小区业绩
                    $jiedianXiaoquPerformance = JiedianPerformanceService::getJiedianXiaoquPerformance(
                        $aid, 
                        $mid, 
                        $conditions
                    );
                    
                    \think\facade\Log::write("用户{$mid}接点人小区业绩升级条件检查（且）：计算业绩={$jiedianXiaoquPerformance}，要求业绩={$lv['up_jiedian_xiaoqu_money']}");
                    
                    if($jiedianXiaoquPerformance >= $lv['up_jiedian_xiaoqu_money']){
                        $isup = true;
                        \think\facade\Log::write("✅ 用户{$mid}满足接点人小区业绩升级条件（且）：{$jiedianXiaoquPerformance} >= {$lv['up_jiedian_xiaoqu_money']}");
                    } else {
                        $isup = false;
                        \think\facade\Log::write("❌ 用户{$mid}不满足接点人小区业绩升级条件（且）：{$jiedianXiaoquPerformance} < {$lv['up_jiedian_xiaoqu_money']}");
                    }
                } catch(\Exception $e) {
                    \think\facade\Log::write("❌ 接点人小区业绩升级条件检查异常（且）：" . $e->getMessage());
                    \think\facade\Log::write("异常堆栈: " . $e->getTraceAsString());
                    $isup = false;
                }
            } else if($lv['up_jiedian_xiaoqu_money'] > 0 && $lv['up_jiedian_xiaoqu_condition'] == 'and') {
                \think\facade\Log::write("跳过接点人小区业绩升级条件（且）检查：isup={$isup}，condition_or={$condition_or}");
            }
            
            // 新增：接点人市场数量和业绩升级条件判断（且条件）
            if(($isup || ($isup === false && $condition_or === false)) && $lv['up_jiedian_market_count'] > 0 && $lv['up_jiedian_market_money'] > 0 && $lv['up_jiedian_market_condition'] == 'and'){
                try {
                    // 使用JiedianPerformanceService检查市场条件
                    $marketCheck = JiedianPerformanceService::checkJiedianMarketCondition(
                        $aid, 
                        $mid, 
                        $lv['up_jiedian_market_count'],
                        $lv['up_jiedian_market_money']
                    );
                    
                    \think\facade\Log::write("用户{$mid}接点人市场升级条件检查（且）：合格市场数={$marketCheck['qualified_market_count']}，要求={$lv['up_jiedian_market_count']}，每个市场要求业绩={$lv['up_jiedian_market_money']}");
                    
                    if($marketCheck['is_satisfied']){
                        $isup = true;
                        \think\facade\Log::write("用户{$mid}满足接点人市场升级条件（且）");
                    } else {
                        $isup = false;
                    }
                } catch(\Exception $e) {
                    \think\facade\Log::write("接点人市场升级条件检查异常（且）：" . $e->getMessage());
                    $isup = false;
                }
            }

            // 接点人数和市场升级条件（且条件）
            if(
                !$requiredConditionFailed
                &&
                ($isup || ($isup === false && $condition_or === false))
                && intval($lv['up_jiedian_renshu'] ?? 0) > 0
                && (($lv['up_jiedian_renshu_condition'] ?? 'or') == 'and')
            ){
                $directJiedianMembers = MemberRelationService::getDirectJiedianDownMembers($aid, $mid);
                if(count($directJiedianMembers) >= intval($lv['up_jiedian_renshu'] ?? 0)){
                    $qualifiedJiedianCount = count($directJiedianMembers);
                    if(intval($lv['up_jiedian_renshu_count'] ?? 0) > 0 && intval($lv['up_jiedian_renshu_shichang'] ?? 0) > 0){
                        $targetLevel = Db::name('member_level')->where('aid', $aid)->where('id', intval($lv['up_jiedian_renshu_shichang']))->find();
                        if($targetLevel){
                            $qualifiedLevelIds = Db::name('member_level')
                                ->where('aid', $aid)
                                ->where('can_up', 1)
                                ->where('sort', '>=', $targetLevel['sort'])
                                ->order('sort,id')
                                ->column('id');
                            $qualifiedJiedianCount = self::countQualifiedJiedianMarkets($aid, $mid, $qualifiedLevelIds, intval($lv['up_jiedian_renshu_count'] ?? 0));
                        }else{
                            $qualifiedJiedianCount = 0;
                        }
                    }
                    if($qualifiedJiedianCount >= intval($lv['up_jiedian_renshu_count'] ?? 0)){
                        if(floatval($lv['up_jiedian_renshu_tuanduiyeji'] ?? 0) > 0){
                            $jiedianTeamPerformance = JiedianPerformanceService::getJiedianTotalPerformance($aid, $mid, [
                                'include_self' => false,
                            ]);
                            if($jiedianTeamPerformance >= floatval($lv['up_jiedian_renshu_tuanduiyeji'] ?? 0)){
                                $isup = true;
                            }else{
                                $isup = false;
                                $requiredConditionFailed = true;
                            }
                        }else{
                            $isup = true;
                        }
                    }else{
                        $isup = false;
                        $requiredConditionFailed = true;
                    }
                }else{
                    $isup = false;
                    $requiredConditionFailed = true;
                }
            }
            
             if(($isup || ($isup === false && $condition_or === false)) && $lv['up_jifen'] > 0 && $lv['up_jifen_condition'] == 'and'){
                // $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->field('shengjiscore')->find();
                if($member['shengjiscore'] >= $lv['up_jifen']){
                    // $isup = true;
                   $isup = true;
                }else{
                     $isup = false;
                     
                }
            }
            if(!$requiredConditionFailed && ($isup || ($isup === false && $condition_or === false)) && intval($lv['up_zichanfenqi_status'] ?? 0) === 1 && ($lv['up_zichanfenqi_condition'] ?? 'or') == 'and'){
                if($matchZichanfenqiUpgrade()){
                    $isup = true;
                }else{
                    $isup = false;
                    $requiredConditionFailed = true;
                }
            }
            if(!$requiredConditionFailed && ($isup || ($isup === false && $condition_or === false)) && intval($lv['up_zichanfenqi_team_status'] ?? 0) === 1 && ($lv['up_zichanfenqi_team_condition'] ?? 'or') == 'and'){
                if($matchZichanfenqiTeamUpgrade()){
                    $isup = true;
                }else{
                    $isup = false;
                    $requiredConditionFailed = true;
                }
            }

            if(false){}else{
                //没设置或条件$isup === false && $condition_or === false
                if(($isup || ($isup === false && $condition_or === false)) && ($lv['up_fxdowncount_and']>0 || $lv['up_fxdowncount2_and']>0)){
                    $downmidcount1 = 0;
                    $downmidcount2 = 0;
                    $up_fxdowncount = intval($lv['up_fxdowncount_and']);
                //  var_dump($up_fxdowncount);
                    $up_fxdowncount2 = intval($lv['up_fxdowncount2_and']);
                //  var_dump($up_fxdowncount2);
                    if($lv['up_fxdowncount_and'] > 0){
                        $downmids = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum_and'],$lv['up_fxdownlevelid_and']);
                        $downmidcount1 = count($downmids);
                    }
                    if($lv['up_fxdowncount2_and'] > 0){
                        $downmids2 = self::getdownmids($aid,$mid,$lv['up_fxdownlevelnum2_and'],$lv['up_fxdownlevelid2_and']);
                        $downmidcount2 = count($downmids2);
                    }
                //  var_dump($up_fxdowncount);
                //  var_dump($downmidcount1);
                    if($downmidcount1 >= $up_fxdowncount && $downmidcount2 >= $up_fxdowncount2){
                        $isup_up_fxdowncount_and = true;
                    } else {
                        $isup = false;
                    }
                }
                

                
                
            }
//          die;
            if($isup || ($isup === false && $condition_or === false)) {
                if(($lv['up_fxordermoney'] > 0 && $lv['up_fxorder_condition'] == 'and') && ($lv['up_fxdowncount_and']>0 || $lv['up_fxdowncount2_and']>0)) {
                    if($isup_up_fxordermoney && $isup_up_fxdowncount_and) {
                        $isup = true;
                    }
                }elseif(!($lv['up_fxordermoney'] > 0 && $lv['up_fxorder_condition'] == 'and') && ($lv['up_fxdowncount_and']>0 || $lv['up_fxdowncount2_and']>0)) {
                    if($isup_up_fxdowncount_and) {
                        $isup = true;
                    }
                }elseif(($lv['up_fxordermoney'] > 0 && $lv['up_fxorder_condition'] == 'and') && !($lv['up_fxdowncount_and']>0 || $lv['up_fxdowncount2_and']>0)) {
                    if($isup_up_fxordermoney) {
                        $isup = true;
                    }
                }
            }

           
if(!$isup && ($lv['up_market_ab_total'] > 0 && $lv['up_market_other_min'] >= 0) && $lv['up_market_condition'] == 'or'){
    // 获取所有直推下级
    $directDowns = Db::name('member')->field('id')->where('aid', $aid)->where('pid', $mid)->select()->toArray();
    $directDownIds = array_column($directDowns, 'id');

    //var_dump(111);die;
    // 计算每个市场的业绩
    $marketPerformances = [];
    foreach($directDownIds as $downId) {
        // 获取该下级及其所有下级
        $allSubordinates = self::getdownmids($aid, $downId, 0);
        $allSubordinates[] = $downId; // 包含直推下级自身
        
        // 计算该市场的业绩
        $marketPerformance = Db::name('shop_order_goods')
            ->whereIn('status', $orderStatuses)
            ->where('is_yeji', '1')
            ->where('mid', 'in', $allSubordinates)
            ->sum('real_totalprice');
            
        $marketPerformances[$downId] = $marketPerformance;
    }

    // 按业绩排序
    arsort($marketPerformances);
    
    // 检查条件1: A+B≥25万
    $topTwoMarkets = array_slice($marketPerformances, 0, 2, true);
    $abTotal = array_sum($topTwoMarkets);
    
    // 检查条件2: 其它任意一个市场业绩≥25万
    $otherMarkets = array_slice($marketPerformances, 2);
    $otherMarketMeetsCondition = false;
    
    // 特殊处理：当阈值为0且存在其他市场时，直接视为满足条件
    if ($lv['up_market_other_min'] == 0 || !empty($otherMarkets)) {
        $otherMarketMeetsCondition = true;
    } else {
        foreach($otherMarkets as $performance) {
            if($performance >= $lv['up_market_other_min']) {
                $otherMarketMeetsCondition = true;
                break;
            }
        }
    }
    
    // 两个条件都满足时升级
    if($abTotal >= $lv['up_market_ab_total'] && $otherMarketMeetsCondition) {
        $isup = true;
    }
}

// 如果是"且"条件，则需要与其他条件一起判断
if(($isup || ($isup === false && $condition_or === false)) && ($lv['up_market_ab_total'] > 0 || $lv['up_market_other_min'] > 0) && $lv['up_market_condition'] == 'and'){
    // 获取所有直推下级
    $directDowns = Db::name('member')->field('id')->where('aid', $aid)->where('pid', $mid)->select()->toArray();
    $directDownIds = array_column($directDowns, 'id');
    
    // 计算每个市场的业绩
    $marketPerformances = [];
    foreach($directDownIds as $downId) {
        // 获取该下级及其所有下级
        $allSubordinates = self::getdownmids($aid, $downId, 0);
        $allSubordinates[] = $downId; // 包含直推下级自身
        
        // 计算该市场的业绩
        $marketPerformance = Db::name('shop_order_goods')
            ->whereIn('status', $orderStatuses)
            ->where('is_yeji', '1')
            ->where('mid', 'in', $allSubordinates)
            ->sum('real_totalprice');
            
        $marketPerformances[$downId] = $marketPerformance;
    }
    
    // 按业绩排序
    arsort($marketPerformances);
    
    // 检查条件1: A+B≥25万
    $topTwoMarkets = array_slice($marketPerformances, 0, 2, true);
    $abTotal = array_sum($topTwoMarkets);
    
    // 检查条件2: 其它任意一个市场业绩≥25万
    $otherMarkets = array_slice($marketPerformances, 2);
    $otherMarketMeetsCondition = false;
    foreach($otherMarkets as $performance) {
        if($performance >= $lv['up_market_other_min']) {
            $otherMarketMeetsCondition = true;
            break;
        }
    }
    
    // 两个条件都满足时才保持升级状态
    if(!($abTotal >= $lv['up_market_ab_total'] && $otherMarketMeetsCondition)) {
        $isup = false;
    }
}


// ... existing code ...
          ///// var_dump('第二个');
        ///  var_dump($isup);
            if($isup )
        {
    if($lv['up_zishen_levelid'] > 0)
    {
        $levelidsss = (string)$member['levelid'];
        // 将等级 ID 列表转换为数组
        $levelid_array = explode(',', str_replace('，', ',', $lv['up_zishen_levelid']));
        // 使用 in_array 精确匹配
        if(in_array($levelidsss, $levelid_array))
        {
            $isup = true;
        } else {
            $isup = false;
            $condition_or = true;
        }
    }
}


            /**且 判断直推多少人,多少市场出现等级idn*/
            if($isup || ($isup === false && $condition_or === false))
            {
                if($lv['up_zt_new2_duo'] >0)
                {
                 // var_dump('条件直推'.$lv['up_zt_new2_duo']);
                    $q = 0;
                    $zhituicount = Db::name('member')->field('id,nickname,createtime')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
                   // var_dump('实际直推'.count($zhituicount));
                // var_dump(count($zhituicount));
                    if(count($zhituicount) >= $lv['up_zt_new2_duo'])
                    {
                        $qumemberid = '';
                       // var_dump('条件几个市场'.$lv['up_zt_new2_dengjiid_shichang1']);
                      //  var_dump('条件等级'.$lv['up_zt_new2_dengjiid_duo1']);
                        if($lv['up_zt_new2_dengjiid_shichang1'] >0 && $lv['up_zt_new2_dengjiid_duo1'] > 0 )
                        {
                            $memberlist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
                            $up_zt_new2_dengjiid_shichang1 = 0;
                            foreach($memberlist as $membervalue)
                            {
                                if($up_zt_new2_dengjiid_shichang1 <= $lv['up_zt_new2_dengjiid_shichang1']){
                                   // var_dump('当前我的等级'.$membervalue['levelid']);
                                    $up_zt_new2_dengjiid_duo1ids = explode(',',$lv['up_zt_new2_dengjiid_duo1']);
                                    if(in_array($membervalue['levelid'],$up_zt_new2_dengjiid_duo1ids) !==false)
                                    {
                                        $membervaluelist = $membervalue;
                                    }else{
                                        $membervaluelist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('find_in_set('.$membervalue['id'].',path)')->whereIn('levelid',$up_zt_new2_dengjiid_duo1ids)->select()->toArray();
                                        
                                    } 
                                    //var_dump(Db::getlastsql());
                                    if(!empty($membervaluelist))
                                    {
                                        $up_zt_new2_dengjiid_shichang1 = $up_zt_new2_dengjiid_shichang1+1;
                                        $qumemberid = ','.$membervalue['id'];
                                    }
                                }
                            }
                           // var_dump('去除的市场'.$qumemberid);
                           // var_dump('实际市场'.$up_zt_new2_dengjiid_shichang1);
                            if($up_zt_new2_dengjiid_shichang1 >= $lv['up_zt_new2_dengjiid_shichang1'])
                            {
                                 $isup = true;
                                 $q = 1;
                            }else{
                                $isup = false;
                                $condition_or = true; 
                                $q = 0;
                            }
                        }else{
                            $q = 1;
                        }
                        //var_dump('条件一市场条件');var_dump($isup);
                        if($lv['up_zt_new2_dengjiid_shichang2'] >0 && $lv['up_zt_new2_dengjiid_duo2'] > 0 && $q==1)
                        {
                            if($qumemberid)
                            {
                                $qumemberid = substr($qumemberid,0,1);
                                $memberlist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('pid',$mid)->where('id','not in',$qumemberid)->select()->toArray();
                            }else{
                                 $memberlist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
                            }
                            $up_zt_new2_dengjiid_shichang2 = 0;
                            foreach($memberlist as $membervalue)
                            {
                                $up_zt_new2_dengjiid_duo1ids = explode(',',$lv['up_zt_new2_dengjiid_duo2']);
                                if($up_zt_new2_dengjiid_shichang2 <= $lv['up_zt_new2_dengjiid_shichang2']){
                                    if(in_array($membervalue['levelid'],$up_zt_new2_dengjiid_duo1ids) !==false)
                                    {
                                        $membervaluelist = $membervalue;
                                    }else{
                                        $membervaluelist = Db::name('member')->field('id,nickname,createtime,levelid')->where('aid',$aid)->where('find_in_set('.$membervalue['id'].',path)')->whereIn('levelid',$up_zt_new2_dengjiid_duo1ids)->select()->toArray();
                                    }
                                    if(!empty($membervaluelist))
                                    {
                                        $up_zt_new2_dengjiid_shichang2 = $up_zt_new2_dengjiid_shichang2+1;
                                    }
                                }
                            }
                            if($up_zt_new2_dengjiid_shichang2 >= $lv['up_zt_new2_dengjiid_shichang2'])
                            {
                                 $isup = true;
                                 $q = 1;
                            }else{
                                $isup = false;
                                $condition_or = true; 
                                $q = 0;
                            }
                        }else{
                            $q = 1;
                        }
                        if($lv['up_zt_new2_tuanduiyeji'] > 0 && $q==1)
                        {
                            $mytuandui = self::getmytuanduiyeji($aid,$mid);
                            if($mytuandui >= $lv['up_zt_new2_tuanduiyeji'])
                            {
                                $isup = true;
                            }else{
                                $isup = false;
                                $condition_or = true;
                            }
                        }
                    }else{
                        $isup = false;
                        $condition_or = true;
                    }
                }
            }
           // var_dump('判断直推多少人');
           // var_dump($isup);

            
            // var_dump('自身等级');var_dump($isup);
            if($isup || ($isup === false && $condition_or === false))
            {
               // var_dump('下单金额'.$ordermoneys);
                //var_dump('设置金额'.$lv['up_mei_order_monnnney'] );
                if($lv['up_mei_order_monnnney']  >  0)
                {
                    if($lv['up_mei_order_monnnney']  <= $ordermoneys)
                    {
                        $isup = true;
                    }else{
                         $isup = false;
                         $condition_or = true;
                    }
                }
            }
          /// var_dump('每次消费');var_dump($isup);
         ///  var_dump($isup);
           //  \think\facade\Log::write('and: ' . $isup);
            if($isup || ($isup === false && $condition_or === false))
            {
                //var_dump($lv['up_zt_new_duo']);
             //   \think\facade\Log::write('进来了 ' );
                if($lv['up_zt_new_duo']>0)
                {
                  // var_dump($lv['up_zt_new_dengjiid_duo']);
                    if(!empty($lv['up_zt_new_dengjiid_duo']))
                    {
                        $up_zt_new_dengjiid_duo_dayuids = $lv['up_zt_new_dengjiid_duo'];
                    }else{
                        $up_zt_new_dengjiid_duo_dayuids = Db::name('member_level')->where('aid',$aid)->order('sort,id')->select()->toArray(); 
                        $up_zt_new_dengjiid_duo_dayuids = array_column($up_zt_new_dengjiid_duo_dayuids,'id');
                        $up_zt_new_dengjiid_duo_dayuids = implode(',',$up_zt_new_dengjiid_duo_dayuids);
                    }
                    ///获取我的直推下级
                    $memberlist = Db::name('member')->field('id,nickname,createtime')->where('aid',$aid)->where('pid',$mid)->where('levelid','in',$up_zt_new_dengjiid_duo_dayuids)->select()->toArray();
                    $memberlistcount = count($memberlist);
                    if($memberlistcount >=$lv['up_zt_new_duo'])
                    {
                      //  var_dump($lv['up_zt_new_dengjiyeji_duo']);
                        if($lv['up_zt_new_dengjiyeji_duo'] >0)
                        {
                            $mytuandui = self::getmytuanduiyeji2($aid,$mid,$memberlist);
                           //var_dump($mytuandui);
                            if($mytuandui >= $lv['up_zt_new_dengjiyeji_duo'])
                            {
                                $isup = true;
                            }else{
                                 $isup = false;
                                 $condition_or = true;
                            }
                        }else{
                            $isup = true;
                        }
                    }else{
                         $isup = false;
                         $condition_or = true;
                    }
                }
              //  \think\facade\Log::write('出去了 ' . $isup);
            }
           ///var_dump('最多人数');var_dump($isup);
           ///var_dump($isup);
           
             $condition_or2 = false;
            //   && $lv['up_mydengji'] ==0
            if(($isup || ($isup === false && $condition_or === false)) && $lv['up_tuandui'] >0){
                $myxiatwodi = self::getZaoTwo($aid,$mid);
                if($myxiatwodi >= $lv['up_tuandui'])
                {
                     $isup = true;
                   
                }else{
                     $isup = false;
                     $condition_or2 = true;
                   
                }
                
            }
          //  var_dump('团队');var_dump($isup);
            if(($isup || ($isup === false && $condition_or2 === false && $condition_or === false)) && $lv['up_orderpaymoney'] >0 && empty($upMydengjiIds))
            {
                if($orderid >0)
                {
                    $order = Db::name('shop_order')->where('id',$orderid)->find();
                    if($mid == $order['mid']){
                        if($order['product_price'] >= $lv['up_orderpaymoney'])
                        {
                            $isup = true;
                        }else{
                            $isup = false;
                        }
                    }else{
                        $resss = Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('product_price','>=',$lv['up_orderpaymoney'])->whereIn('status',$orderStatuses)->select()->toArray();
                        if(empty($resss)){
                            $isup = false;
                        }else{
                            $isup = true;
                        }
                    }
                }else{
                    $isup = false;
                }
            }
           // var_dump('最后一个');var_dump($isup);
            // if(($isup || ($isup === false && $condition_or2 === false && $condition_or === false))
            // {
            //     if($levelid)
            //     {
                    
            //     }
            // }
//                         var_dump($isup);
// var_dump($condition_or);die;
//var_dump($lv);die;
            if ($structuredRulesPresent) {
                if (!$structuredAndMatched) {
                    $requiredConditionFailed = true;
                } elseif (!$legacyEligibilityConfigured && !$structuredOrConfigured) {
                    // 仅配置 JSON AND 条件时，全部满足即可触发升级。
                    $isup = true;
                } elseif (!$legacyEligibilityConfigured && $structuredOrConfigured) {
                    $isup = $structuredOrMatched;
                }
            }
            if($requiredConditionFailed) $isup = false;
            if($isup) {
                $newlv = $lv;
                $matchedEligibleLevel = true;
            }
            
        }
        // die;
       //判断等级 是否进行了降级
        $member_dis=Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
        if (!$member_dis) {
            return false;
        }
        // 获取当前会员等级的排序值
        if (intval($member_dis['levelid'] ?? 0) === 1) {
            $current_level = Db::name('member_level')->where('id', 1)->find();
        } else {
            $current_level = Db::name('member_level')
                ->where('aid',$aid)
                ->where('id',$member_dis['levelid'])
                ->find();
        }
        if (!$current_level) {
            return false;
        }
        
        // die;
        // 修改判断条件，先比较sort字段，再比较id字段
        // 原来的判断: if($newlv && $newlv['id'] != $levelInfo['levelid'] && $newlv['id']>$member_dis['levelid'])
        if($newlv && $newlv['id'] != $levelInfo['levelid'] && 
           ($newlv['sort'] > $current_level['sort'] || ($newlv['sort'] == $current_level['sort'] && $newlv['id'] > $member_dis['levelid']))) {
            
            if (intval($member_dis['levelid'] ?? 0) === 1) {
                $curlevelid = Db::name('member_level')->where('id', 1)->find();
            } else {
                $curlevelid = Db::name('member_level')
                    ->where('aid', $aid)
                    ->where('id', $member_dis['levelid'])
                    ->find();
            }
            if(intval($curlevelid['huigui_status'] ?? 0) == 1 && intval($member_dis['ylevelid'] ?? 0) > 0)
            {
                $ylevelidArr = Db::name('member_level')->where('aid',$aid)->where('id',$member_dis['ylevelid'])->find();
                if(!empty($ylevelidArr))
                {
                    $newlv = $ylevelidArr;
                }
            }
            $levelendtime = self::getLevelEndTime($newlv);
            //判断是否默认分组
            $is_default = 0;
            if($newlv['cid'] > 0)
                $is_default = intval(Db::name('member_level_category')->where('id', $newlv['cid'])->value('isdefault'));
            if ($newlv['cid'] == 0 || $is_default == 1) {
                $memberUpdated = Db::name('member')
                    ->where('aid', $aid)
                    ->where('id', $mid)
                    ->where('levelid', intval($member_dis['levelid']))
                    ->update([
                        'levelid' => $newlv['id'],
                        'levelendtime' => $levelendtime,
                        'levelstarttime' => time(),
                        'ylevelid' => intval($member_dis['levelid']),
                    ]);
                if ($memberUpdated !== 1) {
                    Log::warning('会员升级条件更新未命中，跳过奖励: aid=' . $aid . ', mid=' . $mid . ', before_level=' . intval($member_dis['levelid']) . ', target_level=' . intval($newlv['id']));
                    $latestLevelId = intval(Db::name('member')
                        ->where('aid', $aid)
                        ->where('id', $mid)
                        ->value('levelid'));
                    return MemberUpgradeExecutionResult::make(
                        MemberUpgradeExecutionResult::ALREADY_SATISFIED,
                        $mid,
                        intval($member_dis['levelid']),
                        $latestLevelId > 0 ? $latestLevelId : intval($newlv['id']),
                        ['reason' => 'level_changed_before_commit']
                    );
                }
            } else {
                Log::warning('非默认等级分组没有会员等级落库字段，已在奖励前停止: aid=' . $aid . ', mid=' . $mid . ', target_level=' . intval($newlv['id']));
                return MemberUpgradeExecutionResult::make(
                    MemberUpgradeExecutionResult::NOT_ELIGIBLE,
                    $mid,
                    intval($member_dis['levelid']),
                    intval($newlv['id']),
                    ['reason' => 'non_default_category_not_applied']
                );
            }
            /**
             * 锁定培养人
             * */
            self::getsuoding($aid,$newlv['id'],$mid);
            
            Wechat::updatemembercard($aid, $mid);
            //赠送积分
            if($newlv['up_give_score'] > 0) {
                self::addscore($aid, $mid, $newlv['up_give_score'], '升级奖励');
            }
            //升级赠送现金券
            if($newlv['up_give_xianjinquan'] >0)
            {
                 self::addhei($aid, $mid, $newlv['up_give_xianjinquan'], '升级奖励');
            }
            //奖励佣金
            if($newlv['up_give_commission'] > 0) {
                self::addcommission($aid,$mid,0,$newlv['up_give_commission'],'升级奖励');
            }
            //奖励余额
            if($newlv['up_give_money'] > 0) {
                self::addmoney($aid,$mid,$newlv['up_give_money'],'升级奖励');
            }
                        //奖励贡献值
            if($newlv['up_give_gongxianzhi'] > 0) {
                // 检查是否设置了来源等级限制
                $fromLevelIds = trim($newlv['up_give_gongxianzhi_from_levelids'] ?? '');
                $shouldGiveReward = true;
                
                if (!empty($fromLevelIds)) {
                    // 如果设置了来源等级限制，检查用户之前的等级是否在允许列表中
                    $allowedFromLevelIds = explode(',', $fromLevelIds);
                    $allowedFromLevelIds = array_filter($allowedFromLevelIds); // 移除空值
                    
                    if (!empty($allowedFromLevelIds)) {
                        $previousLevelId = $member_dis['levelid'] ?? 0; // 用户升级前的等级ID
                        $shouldGiveReward = in_array($previousLevelId, $allowedFromLevelIds);
                        
                        // 记录日志用于调试
                        \think\facade\Log::write('贡献值奖励等级检查：用户ID='.$mid.'，升级前等级='.$previousLevelId.'，允许等级='.implode(',',$allowedFromLevelIds).'，是否奖励='.($shouldGiveReward?'是':'否'));
                    }
                }
                
                if ($shouldGiveReward) {
                    self::addgongxianzhi($aid,$mid,$newlv['up_give_gongxianzhi'],'升级奖励贡献值');
                } else {
                    \think\facade\Log::write('用户升级贡献值奖励被跳过：用户ID='.$mid.'，升级前等级不符合要求');
                }
            }
            //赠送上级贡献值
            if($newlv['up_give_parent_gongxianzhi'] > 0 && $member['pid']) {
                self::addgongxianzhi(
                    $aid,
                    $member['pid'],
                    $newlv['up_give_parent_gongxianzhi'],
                    '直推奖励贡献值',
                    $orderid
                );
            }
            //赠送上上级贡献值
            if($newlv['up_give_parent2_gongxianzhi'] > 0 && $member['pid']) {
                // 获取上上级
                $parent2 = Db::name('member')
                    ->where('aid', $aid)
                    ->where('id', $member['pid'])
                    ->value('pid');
                if($parent2 > 0) {
                    self::addgongxianzhi(
                        $aid,
                        $parent2,
                        $newlv['up_give_parent2_gongxianzhi'],
                        '间推奖励贡献值',
                        $orderid
                    );
                }
            }
            //升级奖励指定等级上级积分（向上查找符合条件的上级）
            // 模式：0=找到即停止，1=往上找(不限高低)，2=往上找(只奖更高)
            if(!empty($newlv['up_give_parent_level_ids']) && $newlv['up_give_parent_level_score'] > 0 && $member['pid']) {
                $targetLevelIds = explode(',', $newlv['up_give_parent_level_ids']);
                $targetLevelIds = array_filter(array_map('trim', $targetLevelIds)); // 清理空值和空格
                $continueSearch = intval($newlv['up_give_parent_level_continue'] ?? 0);
                
                if(!empty($targetLevelIds)) {
                    // 向上查找符合指定等级的上级
                    $currentPid = $member['pid'];
                    $maxLoop = 100; // 防止死循环
                    $loopCount = 0;
                    $rewardedLevelIds = []; // 记录已奖励的等级，每个等级只奖励一次
                    $maxRewardedSort = 0; // 记录已奖励的最高等级sort值（用于模式2）
                    
                    // 预先获取所有目标等级的sort值（用于模式2判断高低）
                    $levelSortMap = [];
                    if($continueSearch == 2) {
                        $levelInfos = Db::name('member_level')->where('aid', $aid)->whereIn('id', $targetLevelIds)->column('sort', 'id');
                        $levelSortMap = $levelInfos;
                    }
                    
                    while($currentPid > 0 && $loopCount < $maxLoop) {
                        $parentMember = Db::name('member')->where('id', $currentPid)->where('aid', $aid)->find();
                        if(!$parentMember) break;
                        
                        // 检查上级是否符合指定等级，且该等级未被奖励过
                        if(in_array($parentMember['levelid'], $targetLevelIds) && !in_array($parentMember['levelid'], $rewardedLevelIds)) {
                            $shouldReward = true;
                            
                            // 模式2：只奖励比已奖励等级更高的
                            if($continueSearch == 2 && !empty($rewardedLevelIds)) {
                                $currentLevelSort = $levelSortMap[$parentMember['levelid']] ?? 0;
                                // 如果当前等级sort小于等于已奖励的最高等级sort，则跳过
                                if($currentLevelSort <= $maxRewardedSort) {
                                    $shouldReward = false;
                                    \think\facade\Log::write('升级奖励指定等级上级积分(跳过-等级不够高)：会员ID='.$mid.'，上级ID='.$currentPid.'，上级等级='.$parentMember['levelid'].'(sort='.$currentLevelSort.')，已奖励最高sort='.$maxRewardedSort);
                                }
                            }
                            
                            if($shouldReward) {
                                // 找到符合条件的上级，奖励积分
                                self::addscore($aid, $currentPid, $newlv['up_give_parent_level_score'], '下级升级奖励积分');
                                \think\facade\Log::write('升级奖励指定等级上级积分：会员ID='.$mid.'，找到符合条件的上级ID='.$currentPid.'，上级等级='.$parentMember['levelid'].'，奖励积分='.$newlv['up_give_parent_level_score'].'，模式='.$continueSearch);
                                
                                // 记录已奖励的等级
                                $rewardedLevelIds[] = $parentMember['levelid'];
                                
                                // 更新已奖励的最高等级sort值（用于模式2）
                                if($continueSearch == 2) {
                                    $currentLevelSort = $levelSortMap[$parentMember['levelid']] ?? 0;
                                    if($currentLevelSort > $maxRewardedSort) {
                                        $maxRewardedSort = $currentLevelSort;
                                    }
                                }
                                
                                // 模式0：找到即停止
                                if($continueSearch == 0) {
                                    break;
                                }
                                
                                // 所有指定等级都已奖励，停止查找
                                if(count($rewardedLevelIds) >= count($targetLevelIds)) {
                                    break;
                                }
                            }
                        }
                        
                        // 继续向上查找
                        $currentPid = $parentMember['pid'];
                        $loopCount++;
                    }
                }
            }
            //奖励单笔最大奖励额度
            if($newlv['up_give_max_single_reward'] > 0) {
                // 获取当前会员的单笔最大奖励额度
                $currentMaxReward = floatval($member_dis['max_single_reward'] ?? 0);
                $newMaxReward = $currentMaxReward + floatval($newlv['up_give_max_single_reward']);
                
                // 更新会员的单笔最大奖励额度
                Db::name('member')
                    ->where('aid', $aid)
                    ->where('id', $mid)
                    ->update(['max_single_reward' => $newMaxReward]);
                
                // 记录操作日志
                \app\admin\common\System::plog('升级奖励单笔最大奖励：会员ID='.$mid.'，原额度='.$currentMaxReward.'，增加='.$newlv['up_give_max_single_reward'].'，新额度='.$newMaxReward);
            }
            //赠送上级佣金
            if($newlv['up_give_parent_money'] > 0 && $member['pid']
                && self::isUpgradeParentCommissionAllowed($aid, $member['pid'], $newlv)) {
                self::addcommission($aid, $member['pid'], $mid, $newlv['up_give_parent_money'], '直推奖');
            }
            self::sendpyj($aid,$mid, $newlv['id']);
            //升级完 判断是否需要脱离
            self::liandongtuoli($aid,$mid);
            //脱离完 判断是否解冻
            self::jiedongpanduan($aid,$mid);
            
            if($newlv['up_give_parent_coupon_ids'] && $newlv['up_give_parent_coupon_nums'] && $member['pid']){
                $coupon_ids = explode(',',$newlv['up_give_parent_coupon_ids']);
                $coupon_nums = explode(',',$newlv['up_give_parent_coupon_nums']);
                if($coupon_ids){
                    foreach($coupon_ids as $ck=>$coupon_id){
                        if(!$coupon_nums[$ck]){
                            $coupon_nums[$ck] = 1;
                        }
                        for($i=0;$i<$coupon_nums[$ck];$i++){
                            \app\admin\common\Coupon::send($aid,$member['pid'],$coupon_id,true);
                        }
                    }
                }
            }

            //升级赠送优惠券
            //升级记录
            $order = [
                'aid' => $aid,
                'mid' => $mid,
                'from_mid' => $mid,
                'levelid' => $newlv['id'] ,
                'title' => '自动升级',
                'totalprice' => 0,
                'createtime' => time(),
                'levelup_time' => time(),
                'isfenhong' => 1,
                'beforelevelid' => $member_dis['levelid'],
                'form0' => '类型^_^自动升级',
                'platform' => defined('platform')
                    ? strval(constant('platform'))
                    : (strval($member['platform'] ?? '') ?: 'h5'),
                'status' => 2
            ];
            $levelupOrderId = intval(Db::name('member_levelup_order')->insertGetId($order));

            $tmplcontent = [];
            $tmplcontent['first'] = '恭喜您成功升级为'.$newlv['name'];
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = $newlv['name']; //会员等级
            $tmplcontent['keyword2'] = '已生效';//审核状态
            $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_uplv',$tmplcontent,m_url('pages/my/usercenter', $aid));

            self::dispatchLevelChangedHooks(
                intval($aid),
                intval($mid),
                intval($member_dis['levelid']),
                intval($newlv['id'])
            );

            // 升级订单奖励立即结算，分红计划任务仅作为兜底，避免升级成功后
            // 团队级差奖和评级奖因计划任务未运行而长期停留在待处理状态。
            try {
                \app\admin\common\Fenhong::settleLevelupOrder(
                    intval($aid),
                    $levelupOrderId
                );
            } catch (\Throwable $e) {
                \think\facade\Log::warning('自动升级团队奖励立即结算调用失败: aid=' . intval($aid) . ', mid=' . intval($mid) . ', error=' . $e->getMessage());
            }

            return MemberUpgradeExecutionResult::make(
                MemberUpgradeExecutionResult::UPGRADED,
                $mid,
                intval($member_dis['levelid']),
                intval($newlv['id']),
                ['target_level_name' => strval($newlv['name'] ?? '')]
            );
            }

        $resultCode = $matchedEligibleLevel
            ? MemberUpgradeExecutionResult::ALREADY_SATISFIED
            : MemberUpgradeExecutionResult::NOT_ELIGIBLE;
        return MemberUpgradeExecutionResult::make(
            $resultCode,
            $mid,
            intval($member_dis['levelid']),
            intval($newlv['id'] ?? $member_dis['levelid']),
            ['reason' => $matchedEligibleLevel ? 'eligible_level_not_higher' : 'upgrade_conditions_not_met']
        );
    }

    private static function resolveUplvSingleOrderAmount(
        int $aid,
        int $targetMid,
        int $orderId,
        array $context
    ): float {
        $scene = MemberUpgradeScene::normalize($context['scene'] ?? MemberUpgradeScene::LEGACY);
        $originMid = intval($context['origin_mid'] ?? 0);
        $sourceType = strtolower(trim(strval($context['source_type'] ?? '')));
        $businessOrderType = strtolower(trim(strval($context['business_order_type'] ?? '')));
        $isOrderScene = in_array($scene, [
            MemberUpgradeScene::ORDER_PAID,
            MemberUpgradeScene::ORDER_COLLECT,
            MemberUpgradeScene::PAYMENT_CALLBACK,
        ], true);

        $isShopOrderContext = $sourceType === 'shop_order'
            || ($sourceType === 'payorder'
                && in_array($businessOrderType, ['shop', 'shop_order'], true));
        if ($isOrderScene && $isShopOrderContext && $originMid > 0 && $originMid === $targetMid) {
            $sourceOrderId = $orderId > 0
                ? $orderId
                : intval($context['business_order_id'] ?? ($context['source_id'] ?? 0));
            if ($sourceOrderId <= 0) {
                return 0.0;
            }
            $sourceOrder = Db::name('shop_order')
                ->where('aid', $aid)
                ->where('id', $sourceOrderId)
                ->where('mid', $targetMid)
                ->whereIn('status', MemberUpgradeOrderStatusPolicy::statuses($aid))
                ->field('product_price')
                ->find();
            return $sourceOrder ? max(0, floatval($sourceOrder['product_price'] ?? 0)) : 0.0;
        }

        if ($isOrderScene && $originMid > 0 && $originMid === $targetMid
            && array_key_exists('order_amount', $context)
            && is_numeric($context['order_amount'])) {
            return max(0, floatval($context['order_amount']));
        }
        if ($originMid > 0 && $originMid !== $targetMid) {
            return 0.0;
        }

        $canUseLegacyShopOrder = $scene === MemberUpgradeScene::LEGACY
            || in_array($sourceType, ['', 'legacy', 'order', 'shop_order'], true)
            || $businessOrderType === 'shop_order';
        if (!$canUseLegacyShopOrder || $orderId <= 0) {
            return 0.0;
        }

        return max(0, floatval(Db::name('shop_order')
            ->where('aid', $aid)
            ->where('id', $orderId)
            ->where('mid', $targetMid)
            ->whereIn('status', MemberUpgradeOrderStatusPolicy::statuses($aid))
            ->value('product_price')));
    }
    
    /**
     * 锁定培养人
     * */
     public static function getsuoding($aid,$newlevelid,$mid)
     {
         $suo1 = 0;
         $sou2 = 0;
         $member = Db::name('member')->field('id,path,levelid')->where('aid',$aid)->where('id',$mid)->find();
         $memberlevel = Db::name('member_level')->where('aid',$aid)->where('id',$newlevelid)->find();
         $pathArr = explode(',',$member['path']);
         $pathArr = array_reverse($pathArr);
         $jiangliArr1 = explode(',',$memberlevel['suoding_levelid1']);
         $jiangliArr2 = explode(',',$memberlevel['suoding_levelid2']);
         foreach($pathArr as $v1)
         {
              $membershangji = Db::name('member')->field('id,path,levelid')->where('aid',$aid)->where('id',$v1)->find();
               if(in_array($membershangji['levelid'],$jiangliArr1) !== false && $suo1 ==0)
               {
                   $suo1 = $v1;
               }
               if(in_array($membershangji['levelid'],$jiangliArr2) !== false  && $sou2 ==0)
               {
                   $sou2 = $v1;
               }
               if($suo1 != 0 && $sou2!=0)
               {
                   break;
               }
         }
         $data =  ['suo1'=>$suo1,'suo2'=>$sou2,'suo1jiang'=>$memberlevel['suodingpeiyang1'],'suo2jiang'=>$memberlevel['suodingpeiyang2']];
         Db::name('member')->where('aid',$aid)->where('id',$mid)->update($data);
     }
    /**
     * 培育奖
     * 下级升级到等级id  0 拿该会员团队业绩的 0.00%  ,去除团队内等级id  的销售业绩
     * */
    public static function sendpyj($aid,$mid,$levelid)
    {
         $member = Db::name('member')->field('id,path,levelid')->where('aid',$aid)->where('id',$mid)->find();
         $memberlevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
         $memberzhi = Db::name('member')->field('id,path,levelid')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
         $pathArr = explode(',',$member['path']);
         foreach($pathArr as $k1=>$v1)
         {
             //上级信息
             $membershangji = Db::name('member')->field('id,path,levelid')->where('aid',$aid)->where('id',$v1)->find();
             $memberlevelshangji = Db::name('member_level')->where('aid',$aid)->where('id',$membershangji['levelid'])->find();//上级获取levelArr
             //获取上级的培育奖信息
             if($memberlevelshangji['pyj_dengji'] >0 && $memberlevelshangji['pyj_dengji'] == $levelid)
             {
                 if($memberlevelshangji['pyj_qudengji'] >0)
                 {
                     $yaozhituixiaji= [];
                     foreach($memberzhi as $k2=>$v2)
                     {
                         if($v2['levelid'] != $memberlevelshangji['pyj_qudengji'])
                         {
                             $yaozhituixiaji[] = $v2['id'];
                         }
                     }
                     $myxiaji = [];
                     foreach ($yaozhituixiaji as $v3)
                     {
                         $fenxiji = self::getdownmids($aid,$v3);
                         $fenxiji[] =$v3;
                         $myxiaji = array_merge($myxiaji,$fenxiji);
                     }
                     
                    //  var_dump($myxiaji);die;
                     
                 }else{
                     $myxiaji = self::getdownmids($aid,$mid);
                 }
                  $myxiajistr = implode(',',$myxiaji);
                  $myxiajicount = Db::name('shop_order')->where('aid',$aid)->where('mid','in',$myxiajistr)->where('status','in','1,2,3')->sum('product_price');
                  $fanqian = $myxiajicount * $memberlevelshangji['pyj_baifenbi']*0.01;//pyj_baifenbi
                   if($fanqian > 0){
                    //   self::addmoney($aid,$mid,$fanqian,'培育奖:'.$fanqian);
                      self::addcommissionmy($aid,$mid,$mid,$fanqian,'培育奖:'.$fanqian,'佣金');
                   }
             }
         }
    }
    
    /**
     * 我的团队业绩
     * */
    public static function getmytuanduiyeji($aid,$mid)
    {
        $myxiaji = self::getdownmids($aid,$mid);
        $myxiajistr = implode(',',$myxiaji);
         //分别获取团队业绩
        $myxiajicount = Db::name('shop_order')->where('aid',$aid)->where('mid','in',$myxiajistr)->whereIn('status',MemberUpgradeOrderStatusPolicy::statuses(intval($aid)))->sum('product_price');
        return $myxiajicount;
    }
    
    /**
     * 根据会员id获取 业绩
     * */
     public static function getmytuanduiyeji2($aid,$mid,$memberlist)
     {
         $mids = array_column($memberlist,'id');
         $midstr = implode(',',$mids);
         //分别获取团队业绩
         $myxiajicount = Db::name('shop_order')->where('aid',$aid)->where('mid','in',$midstr)->whereIn('status',MemberUpgradeOrderStatusPolicy::statuses(intval($aid)))->sum('product_price');
         return $myxiajicount;
     }
    /**
     * 我的团队业绩去等级
     * */
     
    public static function getmytuanduiyejiqudengji($aid,$mid,$cyjj_qudengji)
    {
        $myxiaji = self::getdownmids2($aid,$mid,$cyjj_qudengji);
        $myxiajistr = implode(',',$myxiaji);
        
         //分别获取团队业绩
        $myxiajicount = Db::name('shop_order')->where('aid',$aid)->where('mid','in',$myxiajistr)->whereIn('status',MemberUpgradeOrderStatusPolicy::statuses(intval($aid)))->sum('totalprice');
        return $myxiajicount;
    }
    /**
    *发开发奖 开发奖直推下级所有业绩每达到25元就奖励12800
    **/
    public static function sendkfj($aid,$mid)
    {
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!empty($member))
        {
            
            $memberlevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
            if(!empty($memberlevel))
            {
                //我的直属下级
                $memberlist = Db::name('member')->field('id')->where('aid',$aid)->where('pid',$mid)->order('createtime','asc')->select()->toArray();
                if(!empty($memberlist)){
                    $memberids = array_column($memberlist,'id');
                    $myxiajistr = implode(',',$memberids);
                     // kfj_shengyu
                    $myxiajiorder= Db::name('shop_order')->where('aid',$aid)->field('id,kfj_shengyu')->where('mid','in',$myxiajistr)->where('status','in','1,2,3')->where('kfj_shengyu','>',0)->select()->order('id','desc')->toArray();
                    $kfj_shengyu = 0;
                    foreach($myxiajiorder as $k=>$v)
                    {
                       $kfj_shengyu = $kfj_shengyu+ $v['kfj_shengyu'];
                    }
                    // 防止除零错误
                    $kfj_yeji = floatval($memberlevel['kfj_yeji'] ?? 0);
                    if ($kfj_yeji > 0) {
                        $bei = floor($kfj_shengyu / $kfj_yeji);
                        $fanqian = floatval($memberlevel['kfj_jiangli'] ?? 0) * $bei;
                    } else {
                        $bei = 0;
                        $fanqian = 0;
                    }
                    if($fanqian > 0){
                        $shengyu = $kfj_shengyu - ($bei*$memberlevel['kfj_yeji']);
                         self::addcommissionmy($aid,$mid,$mid,$fanqian,'直推下级所有业绩每达到'.$memberlevel['kfj_yeji'].'元,奖励'.$memberlevel['kfj_jiangli'].'元,现业绩达到'.$kfj_shengyu.'元,剩余:'.$shengyu.'元,奖励开发奖'.$fanqian,'佣金');
                        // self::addmoney($aid,$mid,$fanqian,'直推下级所有业绩每达到'.$memberlevel['kfj_yeji'].'元,奖励'.$memberlevel['kfj_jiangli'].'元,现业绩达到'.$kfj_shengyu.'元,剩余:'.$shengyu.'元,奖励开发奖'.$fanqian,'元');
                        $orderids = array_column($myxiajiorder,'id');
                        $orderids = implode(',',$orderids);
                        Db::name('shop_order')->where('aid',$aid)->where('id','in',$orderids)->update(['kfj_shengyu'=>0]);
                        Db::name('shop_order')->where('aid',$aid)->where('id',$myxiajiorder[0]['id'])->update(['kfj_shengyu'=>$shengyu]);
                    }
                }
            }
        }
    }
    
    /** 创业基金
     * 团队业绩每累积到  0.00 元且不包含等级id  0  的业绩,达到就奖励  0.00  佣金,往上找第一个等级id  奖励 元
     * */
     public static function  sendcyjijin($aid,$mid)
     {
         //获取我的所有上级
         $member = Db::name('member')->field('id,path')->where('aid',$aid)->where('id',$mid)->find();
         $pathArr = explode(',',$member['path']);
         foreach($pathArr as $k=>$v)
         {
           
              $memberbenren = Db::name('member')->field('id,levelid')->where('aid',$aid)->where('id',$v)->find();
              $memberlevel = Db::name('member_level')->where('aid',$aid)->where('id',$memberbenren['levelid'])->find();
              if(!empty($memberlevel) && $memberlevel['cyjj_tuandui'] >0){
                      //获取我的不包含等级 levelid 的 全部下级
                      if($memberlevel['cyjj_qudengji'] >0)
                      {
                            $myxiaji = self::getdownmids2($aid,$v,$memberlevel['cyjj_qudengji']);
                      }else{
                            $myxiaji = self::getdownmids($aid,$v);
                      }
                      $myxiajistr = implode(',',$myxiaji);
                      $myxiajiorder= Db::name('shop_order')->where('aid',$aid)->field('id,cyjj_shengyu')->where('mid','in',$myxiajistr)->where('status','in','1,2,3')->where('cyjj_shengyu','>',0)->select()->order('id','desc')->toArray();
                      $cyjj_shengyu = 0;
                      if(!empty($myxiajiorder)){
                          foreach($myxiajiorder as $k2=>$v2)
                          {
                               $cyjj_shengyu = $cyjj_shengyu+ $v2['cyjj_shengyu'];
                          }
                          if($cyjj_shengyu >= $memberlevel['cyjj_tuandui'])
                          {
                              $bei = floor($cyjj_shengyu/$memberlevel['cyjj_tuandui']);
                              $fanqian = $memberlevel['cyjj_jiangli']*$bei;
                              if($fanqian > 0){
                                  $shengyu = $cyjj_shengyu - ($bei*$memberlevel['cyjj_tuandui']);
                                  //獎勵我佣金
                                  self::addcommissionmy($aid,$v,$mid,$fanqian,'创业基金:团队业绩每累积到'.$memberlevel['cyjj_tuandui'].'奖励'.$memberlevel['cyjj_jiangli'].',共奖励佣金'.$fanqian);
                                  $orderids = array_column($myxiajiorder,'id');
                                  $orderids = implode(',',$orderids);
                                  Db::name('shop_order')->where('aid',$aid)->where('id','in',$orderids)->update(['cyjj_shengyu'=>0]);
                                  Db::name('shop_order')->where('aid',$aid)->where('id',$myxiajiorder[0]['id'])->update(['cyjj_shengyu'=>$shengyu]);
                                  //往上找第一个等级id  奖励 元
                                  if($memberlevel['cyjj_shangdengji'] >0 && $memberlevel['cyjj_shangjiangli'] >0)
                                  {
                                       $shangid = self::getshangdengji($aid,$memberlevel['cyjj_shangdengji'],$v);
                                       if($shangid >0){
                                            self::addcommissionmy($aid,$shangid,$mid,$memberlevel['cyjj_shangjiangli'],'创业基金:往上找第一个等级id'.$memberlevel['cyjj_shangdengji'].',奖励 '.$memberlevel['cyjj_shangjiangli'].'佣金');
                                        //   self::addmoney($aid,$shangid,$memberlevel['cyjj_shangjiangli'],);
                                       }
                                  }
                              }
                          }
                        
                      }
               }
         }
     }
     
     /**
      * 获得最近的上一级
      * */
    public static function getshangdengji($aid,$cyjj_shangdengji,$mid)
    {
         $memberbenren = Db::name('member')->field('id,path')->where('aid',$aid)->where('id',$mid)->find();
         $pathArr = explode(',',$memberbenren['path']);
         $pathArr = array_reverse($pathArr);
         $returnid = 0;
         foreach($pathArr as $v)
         {
            $bbbmember = Db::name('member')->field('id,levelid')->where('aid',$aid)->where('id',$v)->find();
          
            if($bbbmember['levelid'] == $cyjj_shangdengji)
            {
                return $v;
            }
         }
         return $returnid;
    }
    
    /**
     * 获取最早推荐的两个人的团队业绩
     * */
    public static function getZaoTwo($aid,$mid)
    {
        $res = Db::name('member')->field('id,nickname,createtime')->where('aid',$aid)->where('pid',$mid)->order('createtime','asc')->limit(2)->select()->toArray();
        $returnArr =[];
        foreach($res as $k=>$v)
        {
            //分别获取两个用户的下级
            $myxiaji = self::getdownmids($aid,$v['id']);
            if (!empty($myxiaji)) {
                 $myxiajistr = $v['id'].','.implode(',',$myxiaji);
            }else{
                 $myxiajistr = (string)$v['id'];
            }
            //分别获取团队业绩
            $myxiajicount = Db::name('shop_order')->where('aid',$aid)->where('mid','in',$myxiajistr)->where('status','in','1,2,3')->sum('totalprice');
            if($myxiajicount)
            {
                 $returnArr[]=$myxiajicount;
            }else{
                 $returnArr[]=0;  
            }
           
        }
        if(count($returnArr) == 1)
        {
            return 0;
        }else{
            if($returnArr[0] >= $returnArr[1])
            {
                return $returnArr[1];
            }else{
              return $returnArr[0]; 
            } 
        }
    }
    
    /**
     * 获取最早推荐两个人的团队
     * */
    public static function getQianTuandui($aid,$mid)
    {
        $res = Db::name('member')->field('id,nickname,createtime')->where('aid',$aid)->where('pid',$mid)->order('createtime','asc')->limit(2)->select()->toArray();
        $return = [];
        foreach($res as $k=>$v)
        {
            // $return[]  = $v['id'];
            //分别获取两个用户的全部下级
            $myxiaji = self::getdownmids($aid,$v['id']);
            $return = array_merge($return,$myxiaji);
        }
        return $return;
    }
//加欠款
public static function addarrears($aid, $mid, $arrears, $remark, $frommid = 0)
{
    if ($arrears == 0) return;
    $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
    if (!$member) return ['status' => 0, 'msg' => t('会员') . '不存在'];

    // PHP 8.0+ 类型转换
    $after = floatval($member['arrears'] ?? 0) + floatval($arrears);
    Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['arrears' => $after]);

    $data = [];
    $data['aid'] = $aid;
    $data['mid'] = $mid;
    $data['arrears'] = $arrears;
    $data['after'] = $after;
    $data['createtime'] = time();
    $data['remark'] = $remark;
    if (getcustom('arrears_transfer')) {
        $data['from_mid'] = $frommid;
    }
    $arrearsLogId = intval(Db::name('member_arrearslog')->insertGetId($data));
    self::triggerUplv($aid, $mid, [
        'scene' => MemberUpgradeScene::USER_CENTER,
        'source_type' => 'member_arrears',
        'source_id' => strval($arrearsLogId),
        'request_trigger_key' => 'member_arrears:' . $arrearsLogId,
    ]);
    Wechat::updatemembercard($aid, $mid);

    $tmplcontent = [];
    $tmplcontent['first'] = '您的' . t('欠款') . '发生变动，变动金额：' . $arrears;
    $tmplcontent['remark'] = '点击进入查看~';
    $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
    $tmplcontent['keyword2'] = $remark;  //变动类型
    // PHP 8.0+ 类型转换：round() 参数必须是数字类型
    $tmplcontent['keyword3'] = (string)round(floatval($arrears), 2);  //变动金额
    $tmplcontent['keyword4'] = (string)round(floatval($after), 2);  //当前欠款
    $tmplcontentNew = [];
    $tmplcontentNew['thing2'] = str_replace(',', '', mb_substr($remark, 0, 5)); //消费项目
    $tmplcontentNew['amount3'] = round(floatval($arrears), 2); //消费金额
    $tmplcontentNew['amount4'] = round(floatval($after), 2); //卡内欠款
    $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
    $rs = \app\admin\common\Wechat::sendtmpl($aid, $mid, 'tmpl_arrearschange', $tmplcontent, m_url('pages/my/usercenter', $aid), $tmplcontentNew);
    //变动通知
    if (getcustom('sms_temp_arrears_use')) {
        if ($member['tel'] && $arrears < 0) {
            $rs = \app\admin\common\Sms::send($aid, $member['tel'], 'tmpl_arrears_use', ['arrears' => $arrears, 'real_arrears' => $arrears, 'sy_arrears' => $after]);
        }
    }
    return ['status' => 1, 'msg' => ''];
}

     //加余额
    public static function addmoney($aid,$mid,$money,$remark,$frommid=0,$paytype=''){
        // ========== 性能日志：开始 ==========
        $perf_start = microtime(true);
        \think\facade\Log::write("========== 充值性能日志开始 ========== AID:{$aid}, MID:{$mid}, 金额:{$money}");
        
        if($money==0) return ;
        
        // 步骤1：查询会员
        $step_start = microtime(true);
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("[步骤1] 查询会员信息 - 耗时: {$step_time}ms");
        
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        // 步骤2：更新余额
        $step_start = microtime(true);
        if(getcustom('w7moneyscore')) {
            $w7moneyscore = db('admin_set')->where(['aid'=>$aid])->value('w7moneyscore');
            if($w7moneyscore == 1){
                return self::addw7moneyscore($aid,$member,2,$money,$remark);
            }else{
                // 使用高精度计算，支持5位小数
                $after = bcadd($member['money'], $money, 5);
                // 检查余额是否足够（扣除操作时）
                if($money < 0 && bccomp($after, 0, 5) < 0){
                    \think\facade\Log::write("[步骤2] 余额不足 - 原余额:{$member['money']}, 扣除:{$money}, 计算后:{$after}");
                    return ['status'=>0,'msg'=>'余额不足'];
                }
                // 更新余额并检查返回值
                $updateResult = Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money'=>$after]);
                if($updateResult === false){
                    \think\facade\Log::write("[步骤2] 余额更新失败 - AID:{$aid}, MID:{$mid}, 金额:{$money}, 计算后余额:{$after}");
                    return ['status'=>0,'msg'=>'余额更新失败，请重试'];
                }
                // 验证余额是否真正更新成功（双重验证）
                $verifyMember = Db::name('member')->where('aid',$aid)->where('id',$mid)->field('money')->find();
                if($verifyMember && bccomp($verifyMember['money'], $after, 5) != 0){
                    \think\facade\Log::write("[步骤2] 余额验证失败 - 期望:{$after}, 实际:{$verifyMember['money']}");
                    return ['status'=>0,'msg'=>'余额更新验证失败，请重试'];
                }
            }
        } else {
            // 使用高精度计算，支持5位小数
            $after = bcadd($member['money'], $money, 5);
            // 检查余额是否足够（扣除操作时）
            if($money < 0 && bccomp($after, 0, 5) < 0){
                \think\facade\Log::write("[步骤2] 余额不足 - 原余额:{$member['money']}, 扣除:{$money}, 计算后:{$after}");
                return ['status'=>0,'msg'=>'余额不足'];
            }
            // 更新余额并检查返回值
            $updateResult = Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money'=>$after]);
            if($updateResult === false){
                \think\facade\Log::write("[步骤2] 余额更新失败 - AID:{$aid}, MID:{$mid}, 金额:{$money}, 计算后余额:{$after}");
                return ['status'=>0,'msg'=>'余额更新失败，请重试'];
            }
            // 验证余额是否真正更新成功（双重验证）
            $verifyMember = Db::name('member')->where('aid',$aid)->where('id',$mid)->field('money')->find();
            if($verifyMember && bccomp($verifyMember['money'], $after, 5) != 0){
                \think\facade\Log::write("[步骤2] 余额验证失败 - 期望:{$after}, 实际:{$verifyMember['money']}");
                return ['status'=>0,'msg'=>'余额更新验证失败，请重试'];
            }
        }
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("[步骤2] 更新余额 - 耗时: {$step_time}ms, 原余额:{$member['money']}, 变动:{$money}, 新余额:{$after}");

        // 步骤3：插入余额日志
        $step_start = microtime(true);
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        //$data['paytype'] = $paytype;
        if(getcustom('money_transfer') || getcustom('money_friend_transfer')) {
            $data['from_mid'] = $frommid;
        }
        $moneyLogId = intval(Db::name('member_moneylog')->insertGetId($data));
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("[步骤3] 插入余额日志 - 耗时: {$step_time}ms");
        
        // 步骤4：等级升级检查（性能瓶颈）
        $step_start = microtime(true);
        self::triggerUplv($aid, $mid, [
            'scene' => MemberUpgradeScene::USER_CENTER,
            'source_type' => 'member_money',
            'source_id' => strval($moneyLogId),
            'request_trigger_key' => 'member_money:' . $moneyLogId,
        ]);
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("[步骤4] ⚠️ 等级升级检查 - 耗时: {$step_time}ms ⚠️");
        
        // 步骤5：更新微信会员卡
        $step_start = microtime(true);
        Wechat::updatemembercard($aid,$mid);
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("[步骤5] 更新微信会员卡 - 耗时: {$step_time}ms");

        // 步骤6：发送微信模板消息
        $step_start = microtime(true);
        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('余额').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        // PHP 8.0+ 类型转换：round() 参数必须是数字类型
        $tmplcontent['keyword3'] = (string) round(floatval($money),2);  //变动金额
        $tmplcontent['keyword4'] = (string) round(floatval($after),2);  //当前余额
        $tmplcontentNew = [];
        $tmplcontentNew['thing2'] = str_replace(',','',mb_substr($remark,0,5));//消费项目
        $tmplcontentNew['amount3'] = round(floatval($money),2);//消费金额
        $tmplcontentNew['amount4'] = round(floatval($after),2);//卡内余额
        $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid),$tmplcontentNew);
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("[步骤6] 发送微信模板消息 - 耗时: {$step_time}ms");
        
        // 步骤7：发送短信通知
        $step_start = microtime(true);
        if(getcustom('sms_temp_money_use')){
            if($member['tel'] && $money < 0){
                $rs = \app\admin\common\Sms::send($aid,$member['tel'],'tmpl_money_use',['money'=>$money,'real_money'=>$money,'sy_money'=>$after]);
            }
        }
        $step_time = round((microtime(true) - $step_start) * 1000, 2);
        \think\facade\Log::write("[步骤7] 发送短信通知 - 耗时: {$step_time}ms");
        
        // ========== 性能日志：结束 ==========
        $total_time = round((microtime(true) - $perf_start) * 1000, 2);
        \think\facade\Log::write("========== 充值性能日志结束 - 总耗时: {$total_time}ms ==========\n");
        
        return ['status'=>1,'msg'=>''];
    }
    
    /**
     * 安全的余额扣除方法（带完整验证）
     * 用于关键业务场景，如提现、支付等
     * 
     * @param int $aid 应用ID
     * @param int $mid 会员ID
     * @param float $money 扣除金额（正数）
     * @param string $remark 备注
     * @param bool $useTransaction 是否使用事务（默认true）
     * @return array ['status'=>1/0, 'msg'=>'', 'data'=>[]]
     */
    public static function safeDeductMoney($aid, $mid, $money, $remark, $useTransaction = true){
        if($money <= 0){
            return ['status'=>0,'msg'=>'扣除金额必须大于0'];
        }
        
        if($useTransaction){
            Db::startTrans();
        }
        
        try {
            // 1. 锁定查询会员信息
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member){
                if($useTransaction) Db::rollback();
                return ['status'=>0,'msg'=>t('会员').'不存在'];
            }
            
            // 2. 检查余额是否足够
            $currentMoney = floatval($member['money']);
            if($currentMoney < $money){
                if($useTransaction) Db::rollback();
                \think\facade\Log::write("安全扣除余额失败-余额不足 - AID:{$aid}, MID:{$mid}, 当前余额:{$currentMoney}, 需扣除:{$money}");
                return ['status'=>0,'msg'=>'余额不足'];
            }
            
            // 3. 计算新余额
            $after = bcsub($currentMoney, $money, 5);
            
            // 4. 更新余额
            $updateResult = Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money'=>$after]);
            if($updateResult === false){
                if($useTransaction) Db::rollback();
                \think\facade\Log::write("安全扣除余额失败-更新失败 - AID:{$aid}, MID:{$mid}, 金额:{$money}");
                return ['status'=>0,'msg'=>'余额更新失败，请重试'];
            }
            
            // 5. 验证余额是否真正更新成功
            $verifyMember = Db::name('member')->where('aid',$aid)->where('id',$mid)->field('money')->find();
            if(!$verifyMember || bccomp($verifyMember['money'], $after, 5) != 0){
                if($useTransaction) Db::rollback();
                \think\facade\Log::write("安全扣除余额失败-验证失败 - AID:{$aid}, MID:{$mid}, 期望:{$after}, 实际:{$verifyMember['money']}");
                return ['status'=>0,'msg'=>'余额更新验证失败，请重试'];
            }
            
            // 6. 插入余额日志
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['money'] = -$money; // 负数表示扣除
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $logResult = Db::name('member_moneylog')->insert($data);
            if(!$logResult){
                if($useTransaction) Db::rollback();
                \think\facade\Log::write("安全扣除余额失败-日志插入失败 - AID:{$aid}, MID:{$mid}, 金额:{$money}");
                return ['status'=>0,'msg'=>'余额日志记录失败，请重试'];
            }
            
            if($useTransaction){
                Db::commit();
            }
            
            \think\facade\Log::write("安全扣除余额成功 - AID:{$aid}, MID:{$mid}, 原余额:{$currentMoney}, 扣除:{$money}, 新余额:{$after}");
            
            return [
                'status' => 1,
                'msg' => '扣除成功',
                'data' => [
                    'before' => $currentMoney,
                    'amount' => $money,
                    'after' => $after
                ]
            ];
            
        } catch (\Exception $e) {
            if($useTransaction){
                Db::rollback();
            }
            \think\facade\Log::write("安全扣除余额异常 - AID:{$aid}, MID:{$mid}, 金额:{$money}, 错误:".$e->getMessage());
            return ['status'=>0,'msg'=>'扣除失败：'.$e->getMessage()];
        }
    }
    
    /**
     * 余额数据一致性校验
     * 用于修复余额不一致问题，通过余额日志重新计算余额
     * 
     * @param int $aid 应用ID
     * @param int $mid 会员ID
     * @return array ['status'=>1/0, 'msg'=>'', 'data'=>['old_money'=>0, 'new_money'=>0, 'diff'=>0]]
     */
    public static function verifyAndFixMoney($aid, $mid){
        try {
            // 1. 获取当前余额
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
            if(!$member){
                return ['status'=>0,'msg'=>t('会员').'不存在'];
            }
            $currentMoney = floatval($member['money']);
            
            // 2. 从余额日志重新计算余额
            $logs = Db::name('member_moneylog')
                ->where('aid',$aid)
                ->where('mid',$mid)
                ->order('id asc')
                ->field('money')
                ->select()
                ->toArray();
            
            $calculatedMoney = '0';
            foreach($logs as $log){
                $calculatedMoney = bcadd($calculatedMoney, $log['money'], 5);
            }
            
            // 3. 比较差异
            $diff = bcsub($currentMoney, $calculatedMoney, 5);
            
            // 4. 如果差异超过0.01，修复余额
            if(abs(floatval($diff)) > 0.01){
                Db::startTrans();
                try {
                    // 更新余额
                    Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money'=>$calculatedMoney]);
                    
                    // 记录修复日志
                    $fixLog = [
                        'aid' => $aid,
                        'mid' => $mid,
                        'money' => $diff,
                        'after' => $calculatedMoney,
                        'createtime' => time(),
                        'remark' => '余额数据修复：原余额'.$currentMoney.'，计算余额'.$calculatedMoney.'，差异'.$diff
                    ];
                    Db::name('member_moneylog')->insert($fixLog);
                    
                    Db::commit();
                    
                    \think\facade\Log::write("余额修复成功 - AID:{$aid}, MID:{$mid}, 原余额:{$currentMoney}, 计算余额:{$calculatedMoney}, 差异:{$diff}");
                    
                    return [
                        'status' => 1,
                        'msg' => '余额已修复',
                        'data' => [
                            'old_money' => $currentMoney,
                            'new_money' => $calculatedMoney,
                            'diff' => $diff
                        ]
                    ];
                } catch (\Exception $e) {
                    Db::rollback();
                    return ['status'=>0,'msg'=>'余额修复失败：'.$e->getMessage()];
                }
            }
            
            return [
                'status' => 1,
                'msg' => '余额一致，无需修复',
                'data' => [
                    'old_money' => $currentMoney,
                    'new_money' => $calculatedMoney,
                    'diff' => $diff
                ]
            ];
            
        } catch (\Exception $e) {
            \think\facade\Log::write("余额校验异常 - AID:{$aid}, MID:{$mid}, 错误:".$e->getMessage());
            return ['status'=>0,'msg'=>'余额校验失败：'.$e->getMessage()];
        }
    }
    
    //加额度
    public static function addtuozhanedu($aid,$mid,$money,$remark,$orderid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['tuozhanedu'] ?? 0) + floatval($money);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['tuozhanedu'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_edulog')->insert($data);
        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('拓展额度').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        // PHP 8.0+ 类型转换：round() 参数必须是数字类型
        $tmplcontent['keyword3'] = (string) round(floatval($money),2);  //变动金额
        $tmplcontent['keyword4'] = (string) round(floatval($after),2);  //当前余额
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
        return ['status'=>1,'msg'=>''];
    }
    //加heijifen
    public static function addhei($aid,$mid,$money,$remark,$orderid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if(false){}else {
            // 使用高精度计算，支持5位小数
            $after = bcadd($member['heiscore'], $money, 5);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['heiscore'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_heiscorelog')->insert($data);
        // self::uplv($aid,$mid,$orderid);
        Wechat::updatemembercard($aid,$mid);

        
        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('现金券').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        // PHP 8.0+ 类型转换：round() 参数必须是数字类型
        $tmplcontent['keyword3'] = (string) round(floatval($money),2);  //变动金额
        $tmplcontent['keyword4'] = (string) round(floatval($after),2);  //当前余额
        $tmplcontentNew = [];
        $tmplcontentNew['thing2'] = '您的'.t('现金券').'发生变动，变动金额：'.$money;;//消费项目
        $tmplcontentNew['amount3'] = round(floatval($money),2);//消费金额
        $tmplcontentNew['amount4'] = round(floatval($after),2);//卡内余额
        $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid),$tmplcontentNew);
        
        return ['status'=>1,'msg'=>''];
    }
    
    //加消费币
    public static function addxiaofeib($aid,$mid,$money,$remark,$orderid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $after = bcadd($member['xiaofeib'], $money, 2);
        if($after < 0) return ['status'=>0,'msg'=>t('消费币').'不足'];
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['xiaofeib'=>$after]);
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_xiaofeiblog')->insert($data);
        Wechat::updatemembercard($aid,$mid);
        return ['status'=>1,'msg'=>''];
    }

    //加heijifen
    public static function addshengjiscore($aid,$mid,$money,$remark,$orderid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['shengjiscore'] ?? 0) + floatval($money);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['shengjiscore'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_shengjiscorelog')->insert($data);
        // self::uplv($aid,$mid,$orderid);
        Wechat::updatemembercard($aid,$mid);

        
        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('升级积分').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        // PHP 8.0+ 类型转换：round() 参数必须是数字类型
        $tmplcontent['keyword3'] = (string) round(floatval($money),2);  //变动金额
        $tmplcontent['keyword4'] = (string) round(floatval($after),2);  //当前余额
        $tmplcontentNew = [];
        $tmplcontentNew['thing2'] = '您的'.t('升级积分').'发生变动，变动金额：'.$money;;//消费项目
        $tmplcontentNew['amount3'] = round(floatval($money),2);//消费金额
        $tmplcontentNew['amount4'] = round(floatval($after),2);//卡内余额
        $tmplcontentNew['time6'] = date('Y-m-d H:i'); //变动时间
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid),$tmplcontentNew);
        
        return ['status'=>1,'msg'=>''];
    }
    
    //分红收益
    public static function addFenhongShouyi($aid, $mid, $fenhongShouyi, $remark, $orderid=0){
    if($fenhongShouyi == 0) return ['status' => 0, 'msg' => '变动金额不能为零'];
    
    // 加锁查询以保证数据一致性
    $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
    if(!$member) return ['status' => 0, 'msg' => '会员不存在'];
    
    // 更新分红收益
    // PHP 8.0+ 类型转换
    $after = floatval($member['zijifh'] ?? 0) + floatval($fenhongShouyi);
    Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['zijifh' => $after]);

    // 插入分红收益变动日志
    $data = [];
    $data['aid'] = $aid;
    $data['mid'] = $mid;
    $data['money'] = $fenhongShouyi;
    $data['after'] = $after;
    $data['createtime'] = time();
    $data['remark'] = $remark;
    Db::name('member_fenhongshouyilog')->insert($data); // 确保有对应的数据表和字段

    // 此处可添加任何额外的业务逻辑，比如更新会员等级等
    // self::uplv($aid, $mid, $orderid);

    // 更新会员的微信卡信息（如果有必要）
    // \app\admin\common\Wechat::updatemembercard($aid, $mid);

    // 发送微信模板消息通知用户（如果有必要）
    /*
    $tmplcontent = [];
    $tmplcontent['first'] = '您的分红收益发生变动，变动金额：'.$fenhongShouyi;
    $tmplcontent['remark'] = '点击进入查看~';
    $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
    $tmplcontent['keyword2'] = $remark; //变动原因
    // PHP 8.0+ 类型转换：round() 参数必须是数字类型
    $tmplcontent['keyword3'] = (string) round(floatval($fenhongShouyi),2); //变动金额
    $tmplcontent['keyword4'] = (string) round(floatval($after),2); //当前余额
    \app\admin\common\Wechat::sendtmpl($aid, $mid, 'tmpl_moneychange', $tmplcontent, m_url('pages/my/usercenter', $aid));
    */
    
    return ['status' => 1, 'msg' => '分红收益更新成功'];
}

    //加duihuanfen
    public static function addduihuanfen($aid,$mid,$money,$remark,$orderid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $after = bcadd((string)($member['duihuanfen'] ?? 0), (string)$money, 2);
        if($after < 0) return ['status'=>0,'msg'=>t('虚拟账号积分').'不足'];
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['duihuanfen'=>$after]);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_duihuanfenlog')->insert($data);
        // self::uplv($aid,$mid,$orderid);
        Wechat::updatemembercard($aid,$mid);

        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('虚拟账号积分').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        // PHP 8.0+ 类型转换：round() 参数必须是数字类型
        $tmplcontent['keyword3'] = (string) round(floatval($money),2);  //变动金额
        $tmplcontent['keyword4'] = (string) round(floatval($after),2);  //当前余额
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
        return ['status'=>1,'msg'=>''];
    }
  //加红包后台
    public static function addhuang($aid,$mid,$money,$remark,$orderid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if(false){}else {
            // 使用高精度计算，支持5位小数
            $after = bcadd($member['scorehuang'], $money, 5);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['scorehuang'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_scoreloghuang')->insert($data);
        // self::uplv($aid,$mid,$orderid);
        Wechat::updatemembercard($aid,$mid);

        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('红包').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
        $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
        return ['status'=>1,'msg'=>''];
    }
    
    
      //加股权后台
    public static function addguquan($aid,$mid,$money,$remark,$orderid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['guquan'] ?? 0) + floatval($money);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['guquan'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_guquanscorelog')->insert($data);
        // self::uplv($aid,$mid,$orderid);
        Wechat::updatemembercard($aid,$mid);

        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('红包').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
        $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
        return ['status'=>1,'msg'=>''];
    }
    
      //加贡献值后台
    public static function addgongxianzhi($aid,$mid,$money,$remark,$orderid=0){
        self::ensureGongxianzhiInstalledIfAvailable();
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if(false){}else {
            // 使用高精度计算，支持5位小数
            $after = bcadd($member['contribution_num'], $money, 5);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['contribution_num'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        if (self::tableExistsStatic('member_gongxianzhiscorelog')) {
            Db::name('member_gongxianzhiscorelog')->insert($data);
        } else {
            Log::warning('Member::addgongxianzhi 缺少 member_gongxianzhiscorelog 表，已跳过日志写入', ['aid' => $aid, 'mid' => $mid]);
        }
        // self::uplv($aid,$mid,$orderid);
        Wechat::updatemembercard($aid,$mid);

        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('贡献值').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
        $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
        return ['status'=>1,'msg'=>''];
    }

    /**
     * @return 加贡献值
     */
    public static function getAddContribution($aid,$mid,$money,$remark){
        self::ensureGongxianzhiInstalledIfAvailable();
        if (!self::tableExistsStatic('member_contribution_logs')) {
            Log::warning('Member::getAddContribution 缺少 member_contribution_logs 表，已跳过贡献值奖励处理', ['aid' => $aid, 'mid' => $mid]);
            return;
        }
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        //获取相应的等级 每消费多少元  赠送多少贡献值
        $member_level=Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->lock(true)->find();
        //需要计算的累计消费金额=(总消费-处理过的消费)
        //获取用户总消费
        $lj_nums=Db::name('payorder')->where('aid',$aid)->where('mid',$mid)->field('sum(money) as money')->find();
        //获取已经处理过的消费
        $contribution_dis=Db::name('member_contribution_logs')->where('aid',$aid)->where('mid',$mid)->order('lj_month desc')->find();
        //已处理过的
        $lj_monty=0;
        if($contribution_dis){
            $lj_monty=$contribution_dis['lj_month'];
        }
        $js_money=($lj_nums['money']-$lj_monty);
        $nums=0.00;
        if($member_level['gxz_xf_nums']>0 && $member_level['gxz_xf_zs']>0 && $js_money>=$member_level['gxz_xf_nums']){
            //计算需要添加的贡献值
            $nums=round(round($js_money/$member_level['gxz_xf_nums'],2)*$member_level['gxz_xf_zs'],2);
        }
        //修改用户的贡献值
        Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('contribution_num', $nums)->update();

        //给用户添加贡献值记录
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['add_nums'] = $nums;
        $data['xin_nums'] = $nums+$member['contribution_num'];
        if($nums>0){
            $data['lj_month'] = $lj_nums['money'];
        }else{
            $data['lj_month'] = $lj_monty;
        }
        $data['bc_month'] = $js_money;
        $data['createtime'] = date('Y-m-d H:i:s');
        $data['remark'] = $remark;
        Db::name('member_contribution_logs')->insert($data);

        //给上级添加贡献值  直推用户每累计消费_______元  送________贡献值
        //判断当前用户累计消费了多少
        //判断是否有上级
        if($member['pid']){
            //获取用户的累计消费值
            //判断总消费金额是否已经到了
            $f_nums=0.00;
            if($member_level['gxz_ljxf_nums']>0 && $member_level['gxz_ljxf_zs']>0 && $js_money>=$member_level['gxz_xf_nums']){
                //计算需要添加的贡献值
                $f_nums=round(round($js_money/$member_level['gxz_ljxf_nums'],2)*$member_level['gxz_ljxf_zs'],2);
            }
            //修改用户的贡献值
            Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->inc('contribution_num', $f_nums)->update();

            //给用户添加贡献值记录
            $f_data = [];
            $f_data['aid'] = $aid;
            $f_data['mid'] = $member['pid'];
            $f_data['add_nums'] = $f_nums;
            $f_data['xin_nums'] = $f_nums;
            $f_data['createtime'] = date('Y-m-d H:i:s');
            $f_data['remark'] = '直推用户奖励贡献值';
            Db::name('member_contribution_logs')->insert($f_data);

        }
    }

    /**
     * @return 消费添加金币积分
     *
     */
    public static function getAddjinbi($aid,$mid,$money,$ordernum,$remark){
         if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        //获取相应的等级 每消费多少元  赠送多少贡献值
        $member_level=Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->lock(true)->find();
        //需要计算的累计消费金额=(总消费-处理过的消费) - 废弃 
        //获取用户总消费
        // $lj_nums=Db::name('payorder')->where('aid',$aid)->where('mid',$mid)->field('sum(money) as money')->find();
        // //获取已经处理过的消费
        // $contribution_dis=Db::name('member_scoreloghuang')->where('aid',$aid)->where('mid',$mid)->order('lj_month desc')->find();
        // //已处理过的
        // $lj_monty=0;
        // if($contribution_dis){
        //     $lj_monty=$contribution_dis['lj_month'];
        // }
        // $js_money=($lj_nums['money']-$lj_monty);
        // $nums=0.00;
        // if($member_level['hjf_xf_nums']>0 && $member_level['hjf_xf_zs']>0 && $js_money>=$member_level['hjf_xf_nums']){
        //     //计算需要添加的红包
        //     $nums=round(round($js_money/$member_level['hjf_xf_nums'],2)*$member_level['hjf_xf_zs'],2);
        // }
          //计算需要添加的红包
          //获取订单的商品 
          $money=Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->where('is_yeji',1)->sum('real_totalprice'); 
                 
          $nums=0.00;
          if($member_level['jinbi_xf_nums']>0 && $member_level['jinbi_xf_zs']>0 && $money>=$member_level['jinbi_xf_nums']){
            //计算需要添加的红包
            //$b_nums=round($money/$member_level['hjf_xf_nums'],2);
            $b_nums=round($money/$member_level['jinbi_xf_nums'],6);
            $b_nums=sprintf("%.4f",substr(sprintf("%.5f",$b_nums),0,-1));
            if($b_nums>=1){
                 $nums=round($b_nums*$member_level['jinbi_xf_zs']);
            }
        }
        
        // var_dump($nums);

        //修改用户的积分
        Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('score', $nums)->update();

        //给用户添加贡献值记录
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $nums;
        $data['after'] = $nums+$member['guquan'];
        if($nums>0){
            $data['lj_month'] = $lj_nums['money'];
        }else{
            $data['lj_month'] = $lj_monty;
        }
        $data['bc_month'] = $js_money;
        $data['createtime'] = time();
        $data['type'] = 1;
        $data['remark'] = $remark;
        Db::name('member_scorelogjinbi')->insert($data);
    }


    /**
     * @return 消费添加股权
     *
     */
    public static function getAddguqian($aid,$mid,$money,$ordernum,$remark){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        //获取相应的等级 每消费多少元  赠送多少贡献值
        $member_level=Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->lock(true)->find();
        //需要计算的累计消费金额=(总消费-处理过的消费)
        //获取用户总消费
        // $lj_nums=Db::name('payorder')->where('aid',$aid)->where('mid',$mid)->field('sum(money) as money')->find();
        // //获取已经处理过的消费
        // $contribution_dis=Db::name('member_scorelogguquan')->where('aid',$aid)->where('mid',$mid)->order('lj_month desc')->find();
        // //已处理过的
        // $lj_monty=0;
        // if($contribution_dis){
        //     $lj_monty=$contribution_dis['lj_month'];
        // }
        
        // $js_money=($lj_nums['money']-$lj_monty);
        //获取订单的商品 
        $money=Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->where('is_yeji',1)->sum('real_totalprice'); 
        $nums=0.00;
        if($member_level['gq_xf_nums']>0 && $member_level['gq_xf_zs']>0 && $money>=$member_level['gq_xf_nums']){
            //计算需要添加的红包
            $b_nums=round($money/$member_level['gq_xf_nums'],6);
            $b_nums=sprintf("%.4f",substr(sprintf("%.5f",$b_nums),0,-1));
            if($b_nums>=1){
                 $nums=round($b_nums*$member_level['gq_xf_zs']);
            }
        }
        //修改用户的红包
        Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('guquan', $nums)->update();

        //给用户添加贡献值记录
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $nums;
        $data['after'] = $nums+$member['guquan'];
        if($nums>0){
            $data['lj_month'] = $lj_nums['money'];
        }else{
            $data['lj_month'] = $lj_monty;
        }
        $data['bc_month'] = $js_money;
        $data['createtime'] = time();
        $data['type'] = 1;
        $data['remark'] = $remark;
        Db::name('member_scorelogguquan')->insert($data);
    }




    /**
     * @return 消费添加红包
     *
     */
    public static function getAddhuangjifen($aid,$mid,$money,$ordernum,$remark){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        //获取相应的等级 每消费多少元  赠送多少贡献值
        $member_level=Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->lock(true)->find();
        //需要计算的累计消费金额=(总消费-处理过的消费) - 废弃 
        //获取用户总消费
        // $lj_nums=Db::name('payorder')->where('aid',$aid)->where('mid',$mid)->field('sum(money) as money')->find();
        // //获取已经处理过的消费
        // $contribution_dis=Db::name('member_scoreloghuang')->where('aid',$aid)->where('mid',$mid)->order('lj_month desc')->find();
        // //已处理过的
        // $lj_monty=0;
        // if($contribution_dis){
        //     $lj_monty=$contribution_dis['lj_month'];
        // }
        // $js_money=($lj_nums['money']-$lj_monty);
        // $nums=0.00;
        // if($member_level['hjf_xf_nums']>0 && $member_level['hjf_xf_zs']>0 && $js_money>=$member_level['hjf_xf_nums']){
        //     //计算需要添加的红包
        //     $nums=round(round($js_money/$member_level['hjf_xf_nums'],2)*$member_level['hjf_xf_zs'],2);
        // }
          //计算需要添加的红包
          //获取订单的商品 
          $money=Db::name('shop_order_goods')->where('aid',$aid)->where('ordernum',$ordernum)->where('is_yeji',1)->sum('real_totalprice'); 
                 
          $nums=0.00;
          if($member_level['hjf_xf_nums']>0 && $member_level['hjf_xf_zs']>0 && $money>=$member_level['hjf_xf_nums']){
            //计算需要添加的红包
            //$b_nums=round($money/$member_level['hjf_xf_nums'],2);
            $b_nums=round($money/$member_level['hjf_xf_nums'],6);
            $b_nums=sprintf("%.4f",substr(sprintf("%.5f",$b_nums),0,-1));
            if($b_nums>=1){
                 $nums=round($b_nums*$member_level['hjf_xf_zs']);
            }
        }
        // var_dump($nums);
        
        //修改用户的红包
        Db::name('member')->where('aid',$aid)->where('id',$mid)->inc('scorehuang', $nums)->update();

        //给用户添加贡献值记录
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $nums;
        $data['after'] = $nums+$member['scorehuang'];
        if($nums>0){
            $data['lj_month'] = $lj_nums['money'];
        }else{
            $data['lj_month'] = $lj_monty;
        }
        $data['bc_month'] = $js_money;
        $data['createtime'] = time();
        $data['type'] = 1;
        $data['remark'] = $remark;
        Db::name('member_scoreloghuang')->insert($data);
    }


    /**
     * @param $aid
     * @return 更新红包  - 不做判断 直接更新
     */
    public static function getUpdateshuangjifen($aid,$mid,$money,$remark){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        // PHP 8.0+ 类型转换
        $scorehuang_num = floatval($member['scorehuang'] ?? 0) + floatval($money);
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['scorehuang'=>$scorehuang_num]);

        //给用户添加贡献值记录
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $money;
        $data['after'] = $scorehuang_num;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_scoreloghuang')->insert($data);

    }



    /**
     * @param $aid
     * @return 更新贡献值  - 不做判断 直接更新
     */
    public static function getUpdateContribution($aid,$mid,$money,$remark){
        self::ensureGongxianzhiInstalledIfAvailable();
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        // PHP 8.0+ 类型转换
        $contribution_num = floatval($member['contribution_num'] ?? 0) + floatval($money);
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['contribution_num'=>$contribution_num]);

        //给用户添加贡献值记录
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['add_nums'] = $money;
        $data['xin_nums'] = $contribution_num;
        $data['createtime'] = date('Y-m-d H:i:s');
        $data['remark'] = $remark;
        if (self::tableExistsStatic('member_contribution_logs')) {
            Db::name('member_contribution_logs')->insert($data);
        } else {
            Log::warning('Member::getUpdateContribution 缺少 member_contribution_logs 表，已跳过日志写入', ['aid' => $aid, 'mid' => $mid]);
        }

    }
    
    //分享送贡献值
    public static function  getAddContributionFx($aid,$mid){
        self::ensureGongxianzhiInstalledIfAvailable();
        if (!self::tableExistsStatic('member_contribution_logs')) {
            Log::warning('Member::getAddContributionFx 缺少 member_contribution_logs 表，已跳过分享贡献值奖励处理', ['aid' => $aid, 'mid' => $mid]);
            return;
        }
        //分享______（几人）注册成为_______(等级id）送____贡献值
        //获取用户的下级是等级几的用户
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        //获取上级的详情
        $f_member=Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->lock(true)->find();
        $member_level=Db::name('member_level')->where('aid',$aid)->where('id',$f_member['levelid'])->lock(true)->find();
        if($member_level['gxz_fx_dj']){
            $gxz_fx_dj=explode(',',$member_level['gxz_fx_dj']);
            $member_nums = Db::name('member')->where('aid',$aid)->where('pid',$f_member['id'])->whereIn('levelid',$gxz_fx_dj)->count();
            $nums=0.00;
            //获取已经处理的分享用户数量
            $contribution_dis=Db::name('member_contribution_logs')->where('aid',$aid)->where('mid',$mid)->order('lj_rs_nums desc')->find();
            $lj_nums=0;
            if($contribution_dis){
                $lj_nums=$contribution_dis['lj_rs_nums'];
            }
            $js_nums=($member_nums-$lj_nums);
            if($js_nums>=$member_level['gxz_fx_nums']){
                //赠送贡献值
                $nums=$member_level['gxz_fx_zs'];
            }
            //修改用户的贡献值
            Db::name('member')->where('aid',$aid)->where('id',$f_member['id'])->inc('contribution_num', $nums)->update();
            //给用户添加贡献值记录
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $f_member['id'];
            $data['add_nums'] = $nums;
            $data['xin_nums'] = $nums;
            if($nums>0){
                $data['lj_rs_nums'] = $member_nums;
            }else{
                $data['lj_rs_nums'] = $lj_nums;
            }
            $data['bc_rs_nums'] = $js_nums;
            $data['createtime'] = date('Y-m-d H:i:s');
            $data['remark'] = '分享注册 赠送贡献值';
            Db::name('member_contribution_logs')->insert($data);
        }
    }
    //加自己分红
    public static function addzijifh($aid,$mid,$money,$remark)
    {
            if($money==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
    
            if(false){}else {
                // PHP 8.0+ 类型转换
                $after = floatval($member['zijifh'] ?? 0) + floatval($money);
                Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['zijifh'=>$after]);
            }
    
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['money'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_zijifhlog')->insert($data);
            // self::uplv($aid,$mid,$orderid);
            Wechat::updatemembercard($aid,$mid);
    
            $tmplcontent = [];
            $tmplcontent['first'] = '您的'.t('自己分红池').'发生变动，变动金额：'.$money;
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
            $tmplcontent['keyword2'] = $remark;  //变动类型
            $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
            $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
            $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
            return ['status'=>1,'msg'=>''];
        
    }
    //加分享分红池
    public static function addfxfhc($aid,$mid,$money,$remark)
    {
            if($money==0) return ;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
    
            if(false){}else {
                // PHP 8.0+ 类型转换
                $after = floatval($member['fxfhc'] ?? 0) + floatval($money);
                Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['fxfhc'=>$after]);
            }
    
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['money'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_fxfhclog')->insert($data);
            // self::uplv($aid,$mid,$orderid);
            Wechat::updatemembercard($aid,$mid);
    
            $tmplcontent = [];
            $tmplcontent['first'] = '您的'.t('分享分红池').'发生变动，变动金额：'.$money;
            $tmplcontent['remark'] = '点击进入查看~';
            $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
            $tmplcontent['keyword2'] = $remark;  //变动类型
            $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
            $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
            $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
            return ['status'=>1,'msg'=>''];
    }
    //收益池
    public static function addsyc($aid,$mid,$money,$remark,$q=0)
    {
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['syc'] ?? 0) + floatval($money);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['syc'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['q']=$q;
        Db::name('member_syclog')->insert($data);
        // self::uplv($aid,$mid,$orderid);
        Wechat::updatemembercard($aid,$mid,$remark);

        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('收益池').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
        $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
        return ['status'=>1,'msg'=>''];
    }
    //加排队返现积分
    public static function addscore3($aid,$mid,$bid,$score,$remark){
        if($score==0) return ;
        Db::startTrans();
        try {
            $score = $score;
            $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
            if(!$member) {
                Db::rollback();
                return ['status'=>0,'msg'=>t('会员').'不存在'];
            }
            if($score < 0 && bccomp((string)$member['score3'], bcmul((string)$score, '-1', 5), 5) < 0) {
                Db::rollback();
                return ['status'=>0,'msg'=>t('积分').'不足'];
            }
            // 使用高精度计算，支持5位小数
            $after = bcadd($member['score3'], $score, 5);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['score3'=>$after]);

            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['bid'] = $bid;
            $data['score'] = $score;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            // $data['type'] = 1;
            // $data['channel'] = $channel;
            $logResult = Db::name('member_score3log')->insert($data);
            if($logResult === false) {
                Db::rollback();
                return ['status'=>0,'msg'=>t('积分').'流水记录失败'];
            }

            Db::commit();
            return ['status'=>1,'msg'=>''];
        } catch (\Throwable $e) {
            Db::rollback();
            return ['status'=>0,'msg'=>$e->getMessage()];
        }
    }


    //加积分
    //@update 22-7-21 增加渠道
    public static function addscore($aid,$mid,$score,$remark,$channel=''){
        if($score==0) return ;
        $score = $score;
      
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $member['score'] = self::getscore($member);
        if($score < 0 && $member['score'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['score'] ?? 0) + floatval($score);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['score'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 1;
        $data['channel'] = $channel;
         
        Db::name('member_scorelog')->insert($data);
        Wechat::updatemembercard($aid,$mid,$remark);
        return ['status'=>1,'msg'=>''];
    }
    //加信用分
    //加绿积分
    public static function adjustCredit($aid,$mid,$score,$remark,$channel='')
    {
        if($score==0) return ;
        $score = $score;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        
        if($score < 0 && $member['credit_score'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['credit_score'] ?? 0) + floatval($score);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['credit_score'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['create_time'] = date('Y-m-d H:i:s', time()); // 修改这里，将时间戳转换为datetime格式
        $data['remark'] = $remark;
        $data['type'] = 1;
        
        Db::name('zhaopin_credit_log')->insert($data);
        Wechat::updatemembercard($aid,$mid,$remark);
        return ['status'=>1,'msg'=>''];
    }
    //加绿积分
    public static function addscorelv($aid,$mid,$score,$remark,$channel='')
    {
        if($score==0) return ;
        $score = $score;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $member['scorelv'] = self::getscorelv($member);
        if($score < 0 && $member['scorelv'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['scorelv'] ?? 0) + floatval($score);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['scorelv'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 1;
        $data['channel'] = $channel;
        Db::name('member_scoreloglv')->insert($data);
        Wechat::updatemembercard($aid,$mid,$remark);
        return ['status'=>1,'msg'=>''];
    }

            // 加减配资金额
            public static function updateAllocation($aid, $mid, $allocationAmount, $remark, $frommid = 0, $actionType = '增加') {
                if ($allocationAmount == 0) return;
            
                // 锁定用户信息
                $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
                if (!$member) return ['status' => 0, 'msg' => t('会员') . '不存在'];
            
                // 计算新的配资金额
                // PHP 8.0+ 类型转换
                $newAllocation = floatval($member['jishoupeizi'] ?? 0) + floatval($allocationAmount);
            
                // 检查配资金额不能为负（如果是扣减操作）
                if ($newAllocation < 0) {
                    return ['status' => 0, 'msg' => t('配资') . '金额不足'];
                }
            
                // 更新用户配资金额
                Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['jishoupeizi' => $newAllocation]);
            
                // 记录配资日志
                $data = [
                    'aid' => $aid,
                    'mid' => $mid,
                    'money' => $allocationAmount,
                    'after' => $newAllocation,
                    'createtime' => time(),
                    'remark' => $remark,
                ];
                Db::name('user_allocation_log')->insert($data);
            
                // 提示用户更新信息，微信通知
                $tmplcontent = [];
                $tmplcontent['first'] = '您的配资金额发生变动，变动金额：' . $allocationAmount;
                $tmplcontent['remark'] = '点击进入查看详情~';
                $tmplcontent['keyword1'] = date('Y-m-d H:i'); // 变动时间
                $tmplcontent['keyword2'] = $remark;           // 变动类型
                $tmplcontent['keyword3'] = (string) round($allocationAmount, 2);  // 变动金额
                $tmplcontent['keyword4'] = (string) round($newAllocation, 2);     // 当前配资余额
            
                $tmplcontentNew = [];
                $tmplcontentNew['thing2'] = str_replace(',', '', mb_substr($remark, 0, 5)); // 消费项目
                $tmplcontentNew['amount3'] = round($allocationAmount, 2);   // 消费金额
                $tmplcontentNew['amount4'] = round($newAllocation, 2);      // 卡内配资余额
                $tmplcontentNew['time6'] = date('Y-m-d H:i');               // 变动时间
            
                \app\admin\common\Wechat::sendtmpl($aid, $mid, 'tmpl_allocation_change', $tmplcontent, m_url('pages/my/usercenter', $aid), $tmplcontentNew);
            
                // 如果启用了短信提醒（配资减少时）
                if (getcustom('sms_temp_allocation_use') && $member['tel'] && $allocationAmount < 0) {
                    \app\admin\common\Sms::send($aid, $member['tel'], 'tmpl_allocation_use', [
                        'amount' => $allocationAmount,
                        'remaining' => $newAllocation
                    ]);
                }
            
                return ['status' => 1, 'msg' => '操作成功'];
            }



    
    public static function getscorelv($member)
    {
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        return $member['scorelv'];
    }
    
    public static function getscore3($member)
    {
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        return $member['score3'];
    }

    //加消费值
    public static function addscorexiaofeizhi($aid,$mid,$score,$remark,$channel='')
    {
        if($score==0) return ;
        $score = $score;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $member['scorexiaofeizhi'] = self::getscorexiaofeizhi($member);
        if($score < 0 && $member['scorexiaofeizhi'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['scorexiaofeizhi'] ?? 0) + floatval($score);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['scorexiaofeizhi'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 1;
        $data['channel'] = $channel;
        Db::name('member_scorelogxiaofeizhi')->insert($data);
        Wechat::updatemembercard($aid,$mid,$remark);
        return ['status'=>1,'msg'=>''];
    }
    
    public static function getscorexiaofeizhi($member)
    {
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        return $member['scorexiaofeizhi'];
    }
     //计算创业值
     public static function getscorebus($member)
    {
        if(!$member) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();
        return $member['bus_total'];
    }
    
    
    //计算显示现金券
        public static function getscoheijifen($member)
    {
        if(!$member) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();
        return $member['heiscore'];
    }
       //计算显示总损失金额
        public static function getsunshijine($member)
    {
        if(!$member) return '0';
        // $member = db('member')->where(['id'=>$member['id']])->find();
        // $member = Db::name('member_xingjilog')->where('mid',mid)->sum('money');
        $rdata['total_sunshi'] = Db::name('member_xingjilog')->where('mid',mid)->where('aid',1)->sum('money');
        //  $member = db('member')->where(['id'=>$member['id']])->find();
        return $rdata['total_sunshi'] ;
    }
     
    //计算显示贡献值
        public static function getgongxianzhi($member)
    {
        if(!$member) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();
        return $member['contribution_num'];
    }
    
        public static function getshouyichi($member)
    {
        if(!$member) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();
        return $member['syc'];
    }
    
        public static function getpeizi($member)
    {
        if(!$member) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();
        return $member['jishoupeizi'];
    }
    
        //计算显示贡献值
        public static function getguquan($member)
    {
        if(!$member) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();
        return $member['guquan'];
    }
    
    
    
    //家黄积
    public static function addscorehuang($aid,$mid,$score,$remark,$channel='')
    {
        if($score==0) return ;
        $score = $score;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $member['scorehuang'] = self::getscorehuang($member);
        if($score < 0 && $member['scorehuang'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        if(false){}else {
            // 使用高精度计算，支持5位小数
            $after = bcadd($member['scorehuang'], $score, 5);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['scorehuang'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 1;
        $data['channel'] = $channel;
        Db::name('member_scoreloghuang')->insert($data);
        Wechat::updatemembercard($aid,$mid,$remark);
        return ['status'=>1,'msg'=>''];
    }
    
    public static function getscorehuang($member)
    {
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        return $member['scorehuang'];
    }
    public static function getcreditscore($member)
    {
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        return $member['credit_score'];
    }
    
    public static function getjobcount($member)
    {
        if(!$member || !$member['id']) return '0';

        if(!self::tableExistsStatic('zhaopin_apply')) return 0;
        
        // 统计用户已入职的工作数量
        $jobcount = Db::name('zhaopin_apply')
            ->where([
                'mid' => $member['id'],
                'status' => 6  // 6表示已入职状态
            ])
            ->count();

        return $jobcount;
    }
    public static function getteamcount($member)
    {
        if(!$member || !$member['id']) return '0';
        
        // 初始化团队人数
        $teamnum = 0;
        
        // 获取所有等级>=2的等级ID
        $levelids = Db::name('member_level')
            ->where('aid', $member['aid'])
            ->where('id', '>=', 2)
            ->column('id');
        
        // 从当前会员开始向下遍历
        $topids = [$member['id']];
        while(true) {
            // 获取下一级成员
            $childs = Db::name('member')
                ->whereIn('pid', $topids)
                ->select()
                ->toArray();
            
            if(empty($childs)) {
                break;
            } else {
                $topids = [];
                foreach($childs as $item) {
                    $topids[] = $item['id'];
                    // 如果成员等级>=2,计入团队人数
                    if($item['levelid'] >= 2) {
                        $teamnum += 1;
                    }
                }
            }
        }
        
        return $teamnum;
    }
    
    public static function addscore2($aid,$mid,$score,$remark,$channel=''){
        if($score==0) return ;
        $score = intval($score);
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $member['score'] = self::getscore($member);
        if($score < 0 && $member['score2'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['score2'] ?? 0) + floatval($score);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['score2'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 1;
        $data['channel'] = $channel;
        Db::name('member_scorelog')->insert($data);
        Wechat::updatemembercard($aid,$mid,$remark);
        return ['status'=>1,'msg'=>''];
    }
    //静态加积分
    public static function addscoreA($aid,$mid,$score,$remark,$channel=''){
        if($score==0) return ;
        $score = $score;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $member['scoreA'] = self::getscoreA($member);
        if($score < 0 && $member['scoreA'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['scoreA'] ?? 0) + floatval($score);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['scoreA'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 1;
        $data['channel'] = $channel;
        Db::name('member_scoreloga')->insert($data);
        return ['status'=>1,'msg'=>''];
    }
        //动态加积分
    public static function addscoreB($aid,$mid,$score,$remark,$channel=''){
        if($score==0) return ;
        $score = $score;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        $member['scoreB'] = self::getscoreB($member);
        if($score < 0 && $member['scoreB'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['scoreB'] ?? 0) + floatval($score);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['scoreB'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 'B';
        $data['channel'] = $channel;
        Db::name('member_scorelogb')->insert($data);
        return ['status'=>1,'msg'=>''];
    }
    //加提现积分
    public static function addscore_withdraw($aid,$mid,$score,$remark){
        if($score==0) return ;
        $score = intval($score);
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if($score < 0 && $member['score_withdraw'] < $score*-1) return ['status'=>0,'msg'=>t('积分').'不足'];

        // PHP 8.0+ 类型转换
        $after = floatval($member['score_withdraw'] ?? 0) + floatval($score);
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['score_withdraw'=>$after]);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        $data['type'] = 2;
        Db::name('member_scorelog')->insert($data);
        return ['status'=>1,'msg'=>''];
    }
    //佣金增加微信通知
    public static function addcommission_wxnotify($type_str,$amount,$aid,$frommid,$proname=''){
        //self::addcommission_wxnotify($type_str,$amount,$aid,$formmid,$proname='');
        //添加佣金发放通知给用户
        //订阅消息
        $nickname = Db::name('member')->where('aid',$aid)->where('id',$frommid)->value('nickname');

        $tmplcontent = [];
        $tmplcontent['thing1'] = $type_str;//'分佣类型';
        $tmplcontent['amount2'] = $amount;
        $tmplcontent['thing3'] = $nickname;//'下线昵称';
        $tmplcontent['thing4'] = $proname;//'购买商品';
        $tmplcontent['time5'] = date('Y-m-d H:i');
                    
        $tmplcontentnew = [];
        $tmplcontentnew['thing1'] = $type_str;//'分佣类型';
        $tmplcontentnew['amount2'] = $amount;
        $tmplcontentnew['thing3'] = $nickname;//'下线昵称';
        $tmplcontentnew['thing4'] = $proname;//'购买商品';
        $tmplcontentnew['time5'] = date('Y-m-d H:i');
                    
        \app\admin\common\Wechat::sendwxtmpl($aid,$mid,'tmpl_tixiansuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
        
    }
    //添加佣金增加日志;
    public static function addcommissionmy($aid,$mid,$frommid,$commission,$remark,$addtotal=1,$fhtype='')
    {
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        // PHP 8.0+ 类型转换
        $after = floatval($member['commission'] ?? 0) + floatval($commission);
        $totalcommission = ($commission > 0 && $addtotal == 1)
            ? floatval($member['totalcommission'] ?? 0) + floatval($commission)
            : floatval($member['totalcommission'] ?? 0);
         $update_member = ['totalcommission'=>$totalcommission,'commission'=>$after];
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update($update_member);
       
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['frommid'] = $frommid;
        $data['commission'] = $commission;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_commissionlog')->insert($data);

    }
    //加创业值
    public static function addbusTotal($aid,$mid,$frommid,$commission,$remark,$addtotal=1,$fhtype='')
    {
        //   var_dump($mid);
        //   var_dump($frommid);
        //   exit();
          if($commission==0) return ;

          $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
          if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        //
          $m_bus_user = intval($member['bus_total']);
           Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['bus_total'=>$m_bus_user+intval($commission)]);
          
            $after = $m_bus_user+intval($commission);
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $mid;
            $data['frommid'] = $frommid;
            $data['busTotal'] = $commission;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_bustotallog')->insert($data);


            
            return ['status'=>1,'msg'=>''];
    }

    //收益抽佣奖励：享受下级_______(等级id)收益_______%(设置后，下级得到多少佣金，就会给对应的用户返收益的百分比）
    public static function addParentcommissionLogs($aid,$mid,$orderid,$ogid,$commission,$remark){
        //收益抽佣奖励：享受下级_______(等级id)收益_______%(设置后，下级得到多少佣金，就会给对应的用户返收益的百分比）
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        $f_member =  Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->lock(true)->find();
        if($f_member['id']){
            //判断是否有上级
            //获取用户相应等级的 收益抽佣奖励
            $f_member_level=Db::name('member_level')->where('aid',$aid)->where('id',$f_member['levelid'])->lock(true)->find();
            //判断下级的等级是否在上级的收益抽佣奖励范围内
            $f_member_level_arr=explode(',',$f_member_level['sy_xj_dj']);
            if(in_array($member['levelid'],$f_member_level_arr)){
                //获取数据进行处理
                $w_commission=0;
                if($commission>0){
                     $w_commission=$commission*($f_member_level['sy_xj_nums']/100);
                }
                $remark = \app\admin\common\RewardRemark::appendByKey(
                    $aid,
                    'income_parent',
                    $remark,
                    [
                        'from_mid' => $mid,
                        'target_mid' => $f_member['id'],
                        'commission' => round(floatval($w_commission), 2),
                        'source_commission' => round(floatval($commission), 2),
                        'order_id' => $orderid,
                        'ogid' => $ogid,
                    ]
                );
                if($orderid == 0)
                {
                    self::addcommission($aid,$f_member['id'],$mid,$w_commission,$remark);
                }else{
                    //添加分佣记录-不进行分佣 这里只是记录
                    Db::name('member_commission_record')->insert(
                        [
                            'aid'=>$aid,
                            'mid'=>$f_member['id'],
                            'frommid'=>$mid,
                            'orderid'=>$orderid,
                            'ogid'=>$ogid,
                            'type'=>'shop',
                            'commission'=>$w_commission,
                            'score'=>0,
                            'remark'=>$remark,
                            'createtime'=>time()
                        ]);
                }
            }

        }
    }
    //加冻结佣金
   public static function adddongjienum($aid,$mid,$score,$remark,$channel=''){
        if($score==0) return ;
        if(
            !self::columnsExistStatic('member', ['dongjienum'])
            || !self::tableExistsStatic('member_dongjienum')
            || !self::columnsExistStatic('member_dongjienum', ['score', 'after', 'createtime', 'remark'])
        ){
            return ['status'=>0,'msg'=>'联动插件未安装或表结构不完整'];
        }
        $score = $score;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        if($score < 0 && $member['dongjienum'] < $score*-1) return ['status'=>0,'msg'=>t('冻结佣金').'不足'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['dongjienum'] ?? 0) + floatval($score);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['dongjienum'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['score'] = $score;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_dongjienum')->insert($data);
        return ['status'=>1,'msg'=>''];
    }

    /**
     * 判断升级奖励的直属上级是否命中等级限制。
     * 未配置限制时保持历史行为；配置无法解析时不发放该笔奖励。
     */
    public static function isUpgradeParentCommissionAllowed(int $aid, int $parentMid, array $targetLevel): bool
    {
        $configured = trim(strval($targetLevel['up_give_parent_money_level_ids'] ?? ''));
        if ($configured === '' || $aid <= 0 || $parentMid <= 0) {
            return true;
        }
        $allowedLevelIds = [];
        $hasInvalidId = false;
        foreach (preg_split('/[,，]/u', $configured) ?: [] as $item) {
            $item = trim($item);
            if ($item === '' || $item === '0') {
                continue;
            }
            if (ctype_digit($item) && intval($item) > 0) {
                $allowedLevelIds[intval($item)] = true;
            } else {
                $hasInvalidId = true;
            }
        }
        if ($hasInvalidId) {
            return false;
        }
        if (empty($allowedLevelIds)) {
            return true;
        }
        try {
            $parentLevelId = intval(Db::name('member')
                ->where('aid', $aid)
                ->where('id', $parentMid)
                ->value('levelid'));
        } catch (\Throwable $e) {
            Log::warning('升级上级佣金等级限制检查失败，跳过该笔奖励', [
                'aid' => $aid,
                'parent_mid' => $parentMid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
        return isset($allowedLevelIds[$parentLevelId]);
    }

    public static function addcommission($aid,$mid,$frommid,$commission,$remark,$addtotal=1,$fhtype='',$qu=0){
        if($commission==0) return ;
        self::ensureGongxianzhiInstalledIfAvailable();
        
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        
        // 检查是否开启贡献值扣减功能 - 只有在增加佣金时才扣减贡献值
        if ($commission > 0) {
            $gongxianzhi_setting = null;
            if (self::tableExistsStatic('gongxianzhiset')) {
                $gongxianzhi_setting = Db::name('gongxianzhiset')->where('aid', $aid)->find();
            } else {
                Log::warning('Member::addcommission 缺少 gongxianzhiset 表，按未开启贡献值扣减处理', ['aid' => $aid, 'mid' => $mid]);
            }
            if ($gongxianzhi_setting && isset($gongxianzhi_setting['info'])) {
                $gongxianzhi_info = json_decode($gongxianzhi_setting['info'], true);
                
                // 如果开启了佣金扣减贡献值功能
                if (isset($gongxianzhi_info['deduct_contribution']) && $gongxianzhi_info['deduct_contribution'] == 1) {
                    
                    // 检查会员等级是否在限制范围内
                    $level_restrictions_enabled = false;
                    if (isset($gongxianzhi_info['level_restrictions']) && !empty($gongxianzhi_info['level_restrictions'])) {
                        $restricted_levels = explode(',', $gongxianzhi_info['level_restrictions']);
                        // 获取会员当前等级ID
                        $member_level_id = $member['levelid'];
                        // 检查当前会员等级是否在限制列表中
                        if (in_array($member_level_id, $restricted_levels)) {
                            $level_restrictions_enabled = true;
                            \think\facade\Log::write('用户ID:'.$mid.'等级ID:'.$member_level_id.'在贡献值扣减限制范围内');
                        } else {
                            \think\facade\Log::write('用户ID:'.$mid.'等级ID:'.$member_level_id.'不在贡献值扣减限制范围内，跳过扣减');
                        }
                    } else {
                        // 如果没有设置等级限制，默认对所有等级生效
                        $level_restrictions_enabled = true;
                        \think\facade\Log::write('用户ID:'.$mid.'未设置等级限制，对所有等级生效');
                    }
                    
                    // 只有在等级限制范围内的会员才进行贡献值扣减
                    if ($level_restrictions_enabled) {
                        // 获取扣减比例，默认100%全部扣减
                        $deduct_ratio = isset($gongxianzhi_info['deduct_ratio']) ? $gongxianzhi_info['deduct_ratio'] : 100;
                        $deduct_ratio = min(100, max(0, $deduct_ratio)); // 确保比例在0-100之间
                        
                        // 计算需要扣减的贡献值
                        $deduct_amount = round($commission * ($deduct_ratio / 100), 2);
                        
                        // 检查用户贡献值是否足够
                        if ($member['contribution_num'] >= $deduct_amount) {
                            // 扣减贡献值
                            $deduct_result = self::addgongxianzhi($aid, $mid, -$deduct_amount, '发放佣金扣减贡献值: '.$remark);
                        //    \think\facade\Log::write('用户ID:'.$mid.'发放佣金扣减贡献值，金额:'.$deduct_amount.'，原因:'.$remark, 'info');
                        } else {
                         //   \think\facade\Log::write('用户ID:'.$mid.'发放佣金需扣减贡献值，但贡献值不足，需扣:'.$deduct_amount.'，实际:'.$member['contribution_num'], 'warning');
                            
                            // 获取贡献值不足时的处理方式 0:拒绝发放 1:按比例发放 2:全额发放 3:按剩余贡献值发放
                            $insufficient_action = isset($gongxianzhi_info['insufficient_action']) ? $gongxianzhi_info['insufficient_action'] : 0;
                            
                            if ($insufficient_action == 0) {
                                // 拒绝发放佣金
                             //   \think\facade\Log::write('用户ID:'.$mid.'贡献值不足，拒绝发放佣金，金额:'.$commission, 'info');
                                return ['status'=>0, 'msg'=>'贡献值不足，无法发放佣金'];
                            } elseif ($insufficient_action == 1) {
                                // 按比例发放佣金
                                $ratio = isset($gongxianzhi_info['insufficient_ratio']) ? $gongxianzhi_info['insufficient_ratio'] : 50;
                                $ratio = min(100, max(0, $ratio)); // 确保比例在0-100之间
                                
                                $old_commission = $commission;
                                $commission = round($commission * ($ratio / 100), 2);
                                
                                // 如果有贡献值，扣除全部
                                if ($member['contribution_num'] > 0) {
                                    self::addgongxianzhi($aid, $mid, -$member['contribution_num'], '发放佣金扣减贡献值(不足): '.$remark);
                                }
                                
                              //  \think\facade\Log::write('用户ID:'.$mid.'贡献值不足，按比例发放佣金，原金额:'.$old_commission.'，调整后金额:'.$commission.'，比例:'.$ratio.'%', 'info');
                                
                                // 更新备注说明
                                $remark = $remark . '(贡献值不足，按'.$ratio.'%比例发放)';
                            } elseif ($insufficient_action == 3) {
                                // 按剩余贡献值发放佣金
                                $old_commission = $commission;
                                
                                // 计算按照贡献值能发放的佣金金额
                                $contribution_available = $member['contribution_num'];
                                
                                // 按照扣减比例计算需要的贡献值
                                $deduct_ratio = isset($gongxianzhi_info['deduct_ratio']) ? $gongxianzhi_info['deduct_ratio'] : 100;
                                $deduct_ratio = min(100, max(0, $deduct_ratio)); // 确保比例在0-100之间
                                
                                // 计算能发放的佣金比例
                                if ($deduct_amount > 0) {
                                    $available_ratio = ($contribution_available / $deduct_amount) * 100;
                                    $available_ratio = min(100, max(0, $available_ratio)); // 确保比例在0-100之间
                                } else {
                                    $available_ratio = 0; // 如果需要扣减的贡献值为0，则设置比例为0
                                }
                                
                                // 计算能发放的佣金金额
                                $commission = round($commission * ($available_ratio / 100), 2);
                                
                                // 确保佣金不为负数
                                $commission = max(0, $commission);
                                
                                // 扣除相应的贡献值
                                if ($contribution_available > 0) {
                                    // 直接扣除所有可用贡献值，而不是按比例计算
                                    self::addgongxianzhi($aid, $mid, -$contribution_available, '发放佣金扣减贡献值(按剩余): '.$remark);
                                }
                                
                              //  \think\facade\Log::write('用户ID:'.$mid.'按剩余贡献值发放佣金，原金额:'.$old_commission.'，调整后金额:'.$commission.'，贡献值:'.$contribution_available, 'info');
                                
                                // 更新备注说明
                                $remark = $remark . '(贡献值不足，按剩余贡献值发放)';
                            } else {
                                // 全额发放，不做任何处理
                             //   \think\facade\Log::write('用户ID:'.$mid.'贡献值不足，但设置为全额发放佣金，金额:'.$commission, 'info');
                            }
                        }
                    }
                }
            }
        }
        
        $set = Db::name('admin_set')->where('aid',$aid)->find();
        $memberlevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
        if($memberlevel['syc'] == 1)
        {
            if($member['syc'] >=  $commission )
            {
                $gwjf = 0;
                $yj = $commission;
                \app\admin\common\Member::addsyc($aid,$mid,'-'.$yj,'佣金到账扣除'.$yj);
            }else{
                 $syc_shouyi = json_decode($memberlevel['syc_shouyi'],1);
                 $gwjf = $commission *$syc_shouyi['gw']*0.01;
                 $yj = $commission*$syc_shouyi['yj']*0.01;
                 $commission = $yj;
              //   \app\admin\common\Member::heiscore($aid,$mid,'-'.$yj,'佣金到账扣除'.$yj);
                 self::addhei($aid, $mid, $gwjf, '佣金到账,收益池不足,到账金额'.$gwjf);
                 
                 //分佣到账通知
                $type_str = (strpos($remark,'团队') !== false)?'团队分红':'佣金到账';
                self::addcommission_wxnotify($type_str,$yj,$aid,$frommid,$remark);
                 
                 
            }
        }
//      
        //检测佣金金额是否能够抵扣创业值;
         //根据佣金更改创业值;(创业值-佣金值)
          //$m_bus_user = intval($member['bus_total']);
          ////但是后台不在意
          //if(strpos($remark,'后台')!=-1)
          //{
          //    if($m_bus_user-$commission >0)
    //        {
    //              Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['bus_total'=>$m_bus_user-$commission]);
    //        }else{
    //            return ['status'=>0,'msg'=>t('会员').'创业值不足,请充值创业值']; 
    //        }
          //}
              
        //根据佣金增加点数;
        if($commission > 0 && $set['commission2scorepercent'] > 0){
            $oldcommission = $commission;
            $commission = round($commission * (1-$set['commission2scorepercent']*0.01),2);
            $score = $oldcommission - $commission;
            self::addscore($aid,$mid,$score,$remark);
        }

        // PHP 8.0+ 类型转换
        if($commission > 0 && $addtotal==1){
            $totalcommission = floatval($member['totalcommission'] ?? 0) + floatval($commission);
        }else{
            $totalcommission = floatval($member['totalcommission'] ?? 0);
        }
        $liandongrs = self::getEnabledLiandongSet($aid, ['member_dongjienum'], [
            'liandong_set' => ['status', 'fcj_bili', 'fcj_levelids'],
            'member' => ['dongjienum', 'dong_status'],
            'member_dongjienum' => ['score', 'after', 'createtime', 'remark'],
        ]);
        if($commission > 0 && !empty($liandongrs) && $liandongrs['status'] == 1 && $liandongrs['fcj_bili'] != 0 && strpos($remark,'后台修改') === false && strpos($remark,'解冻') === false&& strpos($remark,'佣金提现') === false && strpos($remark,'后台解冻') === false && $member['dong_status'] !=1 && strpos($remark,'升级解冻') === false)
        {
            $fcj_levelids = explode(',',$liandongrs['fcj_levelids']);
            if(in_array('-1',$fcj_levelids) || in_array($member['levelid'],$fcj_levelids))
            {
                $dongjie_num = $commission*$liandongrs['fcj_bili']*0.01;
                $commission1= $commission;
                $commission = $commission - $dongjie_num;
                self::adddongjienum($aid,$mid,$dongjie_num,$remark.',冻结,冻结比例'.$liandongrs['fcj_bili'].'%','');
                $remark = $remark.',应到账'.$commission1.',现到账'.$commission.',冻结'.$dongjie_num.',冻结比例'.$liandongrs['fcj_bili'].'%';
            }
        }
        // PHP 8.0+ 类型转换
        $after = floatval($member['commission'] ?? 0) + floatval($commission);
        $update_member = ['totalcommission'=>$totalcommission,'commission'=>$after];
        if($fhtype == 'fenhong') {
            $update_member['total_fenhong_partner'] = floatval($member['total_fenhong_partner'] ?? 0) + floatval($commission);
            $update_member['total_fenhong'] = floatval($member['total_fenhong'] ?? 0) + floatval($commission);
        } elseif($fhtype == 'teamfenhong') {
            
            $update_member['total_fenhong_team'] = floatval($member['total_fenhong_team'] ?? 0) + floatval($commission);
            $update_member['total_fenhong'] = floatval($member['total_fenhong'] ?? 0) + floatval($commission);
        } elseif($fhtype == 'level_teamfenhong') {
            $update_member['total_fenhong_level_team'] = floatval($member['total_fenhong_level_team'] ?? 0) + floatval($commission);
            $update_member['total_fenhong'] = floatval($member['total_fenhong'] ?? 0) + floatval($commission);
        } elseif($fhtype == 'areafenhong') {
            $update_member['total_fenhong_area'] = floatval($member['total_fenhong_area'] ?? 0) + floatval($commission);
            $update_member['total_fenhong'] = floatval($member['total_fenhong'] ?? 0) + floatval($commission);
        }
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update($update_member);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['frommid'] = $frommid;
        $data['commission'] = $commission;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_commissionlog')->insert($data);
        //我得到佣金了,给我的上级上上级 上上级返佣
        if($qu == 0 && $commission>0 )
        {
            self::addfanyong(
                $aid,
                $mid,
                $frommid,
                $commission,
                \app\admin\common\RewardRemark::appendByKey(
                    $aid,
                    'income_fanyong',
                    '收益抽佣奖励',
                    [
                        'from_mid' => $frommid,
                        'target_mid' => $mid,
                        'commission' => round(floatval($commission), 2),
                        'source_remark' => $remark,
                    ]
                ),
                $remark
            );
        }
        // 团队分红-新加平级奖
        if(strpos($remark,"团队分红") !== false && strpos($remark,"团队分红-") === false && strpos($remark,"评级奖已处理") === false)
        {
           // \think\facade\Log::write('2025-01-03 22:55:53,565-INFO-[Member][addcommission_001] 检测到团队分红，调用统一平级奖处理方法: aid='.$aid.', mid='.$mid.', levelid='.$member['levelid'].', commission='.$commission, 'info');
            
            // 尝试从备注中提取订单时间信息（如果有的话）
            $order_time = 0;
            if (preg_match('/订单时间:(\d+)/', $remark, $matches)) {
                $order_time = intval($matches[1]);
            //    \think\facade\Log::write('2025-01-03 22:55:53,565-INFO-[Member][addcommission_002] 从备注中提取到订单时间: '.date('Y-m-d H:i:s', $order_time), 'info');
            }
            
            \app\admin\common\Fenhong::handleTeamPingjiFenhong($aid, $mid, $member['levelid'], $commission, '团队分红', $order_time);
        }

        return ['status'=>1,'msg'=>''];
    }

    /**
     * 九星奖励专用佣金增加方法
     * 简化版本，专门用于九星奖励发放，避免复杂的扣减逻辑
     * @param int $aid 应用ID
     * @param int $mid 会员ID
     * @param int $frommid 来源会员ID
     * @param float $commission 佣金金额
     * @param string $remark 备注
     * @return array
     */
    public static function addJiuxingCommission($aid, $mid, $frommid, $commission, $remark) {
        \think\facade\Log::write("=== 九星佣金发放开始 ===");
        \think\facade\Log::write("接收用户ID: " . $mid . ", 来源用户ID: " . $frommid . ", 佣金金额: " . $commission);
        \think\facade\Log::write("奖励备注: " . $remark);
        
        if($commission <= 0) {
            \think\facade\Log::write("佣金金额<=0，跳过发放");
            return ['status' => 1, 'msg' => ''];
        }
        
        // 获取会员信息
        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
        if(!$member) {
            \think\facade\Log::write("❌ 会员不存在，ID: " . $mid);
            return ['status' => 0, 'msg' => '会员不存在'];
        }
        
        \think\facade\Log::write("会员信息: 昵称=" . $member['nickname'] . ", 当前佣金=" . $member['commission']);
        
        // 计算佣金到账后的余额
        // PHP 8.0+ 类型转换
        $after = floatval($member['commission'] ?? 0) + floatval($commission);
        \think\facade\Log::write("佣金计算: 原余额=" . $member['commission'] . " + 奖励=" . $commission . " = 新余额=" . $after);
        
        // 更新会员佣金余额
        $update_member = ['commission' => $after];
        $updateResult = Db::name('member')->where('aid', $aid)->where('id', $mid)->update($update_member);
        \think\facade\Log::write("佣金余额更新结果: " . ($updateResult ? '成功' : '失败'));
        
        // 记录佣金日志
        $data = [
            'aid' => $aid,
            'mid' => $mid,
            'frommid' => $frommid,
            'commission' => $commission,
            'after' => $after,
            'createtime' => time(),
            'remark' => $remark
        ];
        $logResult = Db::name('member_commissionlog')->insert($data);
        \think\facade\Log::write("佣金日志记录结果: " . ($logResult ? '成功' : '失败'));
        
        // 发送微信通知
        try {
            self::addcommission_wxnotify('九星奖励', $commission, $aid, $frommid, $remark);
            \think\facade\Log::write("微信通知发送成功");
        } catch (\Exception $e) {
            // 通知失败不影响佣金发放
            \think\facade\Log::error('九星奖励微信通知失败: ' . $e->getMessage());
        }
        
        \think\facade\Log::write("✅ 九星奖励发放完成");
        return ['status' => 1, 'msg' => '九星奖励发放成功'];
    }

/**
 * 添加佣金(不扣除贡献值)
 * @param int $aid 应用ID
 * @param int $mid 会员ID
 * @param int $type 类型
 * @param float $money 金额
 * @param string $remark 备注
 */

 public static function addcommissionWithoutDeduct($aid,$mid,$frommid,$commission,$remark,$addtotal=1,$fhtype='',$qu=0){
    if($commission==0) return ;
    
    $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
    
    if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
    
    
    
    
    $set = Db::name('admin_set')->where('aid',$aid)->find();
    $memberlevel = Db::name('member_level')->where('aid',$aid)->where('id',$memberr['levelid'])->find();
    if($memberlevel['syc'] == 1)
    {
        if($member['syc'] >=  $commission )
        {
            $gwjf = 0;
            $yj = $commission;
            \app\admin\common\Member::addsyc($aid,$mid,'-'.$yj,'佣金到账扣除'.$yj);
        }else{
             $syc_shouyi = json_decode($memberlevel['syc_shouyi'],1);
             $gwjf = $commission *$syc_shouyi['gw']*0.01;
             $yj = $commission*$syc_shouyi['yj']*0.01;
             $commission = $yj;
          //   \app\admin\common\Member::heiscore($aid,$mid,'-'.$yj,'佣金到账扣除'.$yj);
             self::addhei($aid, $mid, $gwjf, '佣金到账,收益池不足,到账金额'.$gwjf);
             
             //分佣到账通知
            $type_str = (strpos($remark,'团队') !== false)?'团队分红':'佣金到账';
            self::addcommission_wxnotify($type_str,$yj,$aid,$formmid,$remark);
             
             
        }
    }
//      
    //检测佣金金额是否能够抵扣创业值;
     //根据佣金更改创业值;(创业值-佣金值)
      //$m_bus_user = intval($member['bus_total']);
      ////但是后台不在意
      //if(strpos($remark,'后台')!=-1)
      //{
      //    if($m_bus_user-$commission >0)
//        {
//              Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['bus_total'=>$m_bus_user-$commission]);
//        }else{
//            return ['status'=>0,'msg'=>t('会员').'创业值不足,请充值创业值']; 
//        }
      //}
          
    //根据佣金增加点数;
    if($commission > 0 && $set['commission2scorepercent'] > 0){
        $oldcommission = $commission;
        $commission = round($commission * (1-$set['commission2scorepercent']*0.01),2);
        $score = $oldcommission - $commission;
        self::addscore($aid,$mid,$score,$remark);
    }

    // PHP 8.0+ 类型转换
    if($commission > 0 && $addtotal==1){
        $totalcommission = floatval($member['totalcommission'] ?? 0) + floatval($commission);
    }else{
        $totalcommission = floatval($member['totalcommission'] ?? 0);
    }
    $liandongrs = self::getEnabledLiandongSet($aid, ['member_dongjienum'], [
        'liandong_set' => ['status', 'fcj_bili', 'fcj_levelids'],
        'member' => ['dongjienum', 'dong_status'],
        'member_dongjienum' => ['score', 'after', 'createtime', 'remark'],
    ]);
    if($commission > 0 && !empty($liandongrs) && $liandongrs['status'] == 1 && $liandongrs['fcj_bili'] != 0 && strpos($remark,'后台修改') === false && strpos($remark,'解冻') === false&& strpos($remark,'佣金提现') === false && strpos($remark,'后台解冻') === false && $member['dong_status'] !=1 && strpos($remark,'升级解冻') === false)
    {
        $fcj_levelids = explode(',',$liandongrs['fcj_levelids']);
        if(in_array('-1',$fcj_levelids) || in_array($member['levelid'],$fcj_levelids))
        {
            $dongjie_num = $commission*$liandongrs['fcj_bili']*0.01;
            $commission1= $commission;
            $commission = $commission - $dongjie_num;
            self::adddongjienum($aid,$mid,$dongjie_num,$remark.',冻结,冻结比例'.$liandongrs['fcj_bili'].'%','');
            $remark = $remark.',应到账'.$commission1.',现到账'.$commission.',冻结'.$dongjie_num.',冻结比例'.$liandongrs['fcj_bili'].'%';
        }
    }
    // PHP 8.0+ 类型转换
    $after = floatval($member['commission'] ?? 0) + floatval($commission);
    $update_member = ['totalcommission'=>$totalcommission,'commission'=>$after];
    if($fhtype == 'fenhong') {
        $update_member['total_fenhong_partner'] = floatval($member['total_fenhong_partner'] ?? 0) + floatval($commission);
        $update_member['total_fenhong'] = floatval($member['total_fenhong'] ?? 0) + floatval($commission);
    } elseif($fhtype == 'teamfenhong') {
        
        $update_member['total_fenhong_team'] = floatval($member['total_fenhong_team'] ?? 0) + floatval($commission);
        $update_member['total_fenhong'] = floatval($member['total_fenhong'] ?? 0) + floatval($commission);
    } elseif($fhtype == 'level_teamfenhong') {
        $update_member['total_fenhong_level_team'] = floatval($member['total_fenhong_level_team'] ?? 0) + floatval($commission);
        $update_member['total_fenhong'] = floatval($member['total_fenhong'] ?? 0) + floatval($commission);
    } elseif($fhtype == 'areafenhong') {
        $update_member['total_fenhong_area'] = floatval($member['total_fenhong_area'] ?? 0) + floatval($commission);
        $update_member['total_fenhong'] = floatval($member['total_fenhong'] ?? 0) + floatval($commission);
    }
    Db::name('member')->where('aid',$aid)->where('id',$mid)->update($update_member);

    $data = [];
    $data['aid'] = $aid;
    $data['mid'] = $mid;
    $data['frommid'] = $frommid;
    $data['commission'] = $commission;
    $data['after'] = $after;
    $data['createtime'] = time();
    $data['remark'] = $remark;
    Db::name('member_commissionlog')->insert($data);
    //我得到佣金了,给我的上级上上级 上上级返佣
    if($qu == 0 && $commission>0 )
    {
        self::addfanyong(
            $aid,
            $mid,
            $frommid,
            $commission,
            \app\admin\common\RewardRemark::appendByKey(
                $aid,
                'income_fanyong',
                '收益抽佣奖励',
                [
                    'from_mid' => $frommid,
                    'target_mid' => $mid,
                    'commission' => round(floatval($commission), 2),
                    'source_remark' => $remark,
                ]
            ),
            $remark
        );
    }
    // 团队分红-新加平级奖
         if(strpos($remark,"团队分红") !== false && strpos($remark,"团队分红-") === false && strpos($remark,"评级奖已处理") === false)
    {
        \think\facade\Log::write('2025-01-03 22:55:53,565-INFO-[Member][addcommissionWithoutDeduct_001] 检测到团队分红，调用统一平级奖处理方法: aid='.$aid.', mid='.$mid.', levelid='.$member['levelid'].', commission='.$commission, 'info');
        
        // 尝试从备注中提取订单时间信息（如果有的话）
        $order_time = 0;
        if (preg_match('/订单时间:(\d+)/', $remark, $matches)) {
            $order_time = intval($matches[1]);
        //    \think\facade\Log::write('2025-01-03 22:55:53,565-INFO-[Member][addcommissionWithoutDeduct_002] 从备注中提取到订单时间: '.date('Y-m-d H:i:s', $order_time), 'info');
        }
        
        \app\admin\common\Fenhong::handleTeamPingjiFenhong($aid, $mid, $member['levelid'], $commission, '团队分红', $order_time);
    }

    return ['status'=>1,'msg'=>''];
}



    /**
     * 分红平级奖
     * */
     public static function fafangpingji($aid,$mid,$levelid,$tuanduifenh)
     {

         
         $levelid = (string)$levelid;
         $curmember = Db::name('member')->field('id,path')->where('aid',$aid)->where('id',$mid)->find(); 
         $pidArr = explode(',',$curmember['path']);
         $pidArr = array_reverse($pidArr);
         $num = 1;
         

         foreach($pidArr as $v)
         {
             $valuemember = Db::name('member')->field('id,levelid')->where('aid',$aid)->where('id',$v)->find(); 
             $vlauelevel = Db::name('member_level')->field('id,pingjilevelid,pingjilevelidjiangli,pingjilevelid2,pingjilevelidjiangli2,pingjilevelid3,pingjilevelidjiangli3,pingjilevelidjiangli_num')
             ->where('aid',$aid)->where('id',$valuemember['levelid'])->find();
             //1.
             if(strpos($vlauelevel['pingjilevelid'],$levelid) !== false)
             {
                 if($tuanduifenh >0){
                     if($num ==1)
                     {
                         $jiangli = $vlauelevel['pingjilevelidjiangli']*$tuanduifenh*0.01;
                         $jiangli =  round($jiangli,2);
                     }else{
                         $jiangli = $tuanduifenh;
                          $jiangli =  round($jiangli,2);
                     }
                     if($vlauelevel['pingjilevelidjiangli_num'] > 0 && $num >= $vlauelevel['pingjilevelidjiangli_num'])
                     {
                         break;
                     }else{
                         self::addcommission($aid,$v,$mid,$jiangli,'团队分红-新加平级奖');
                         $num++;
                         $tuanduifenh = $jiangli;
                     }
                 }else{
                     break;
                 }
             }
             //2
             if(strpos($vlauelevel['pingjilevelid2'],$levelid) !== false)
             {
                 if($tuanduifenh >0){
                     if($num ==1)
                     {
                         $jiangli = $vlauelevel['pingjilevelidjiangli2']*$tuanduifenh*0.01;
                         $jiangli =  round($jiangli,2);
                     }else{
                         $jiangli = $tuanduifenh*0.1;
                          $jiangli =  round($jiangli,2);
                     }
                     self::addcommission($aid,$v,$mid,$jiangli,'团队分红-新加平级奖2');
                     $num++;
                     $tuanduifenh = $jiangli;
                 }else{
                     break;
                 }
             }
             //3.
             if(strpos($vlauelevel['pingjilevelid3'],$levelid) !== false)
             {
                 if($tuanduifenh >0){
                     if($num ==1)
                     {
                         $jiangli = $vlauelevel['pingjilevelidjiangli3']*$tuanduifenh*0.01;
                         $jiangli =  round($jiangli,2);
                     }else{
                         $jiangli = $tuanduifenh*0.1;
                          $jiangli =  round($jiangli,2);
                     }
                     self::addcommission($aid,$v,$mid,$jiangli,'团队分红-新加平级奖');
                     $num++;
                     $tuanduifenh = $jiangli;
                 }else{
                     break;
                 }
             }
             
         }
         

     }
     /**
      * 分红平级奖2
      * */
     public static function fafangpingji2($aid,$mid,$levelid,$tuanduifenh)
     {
          $levelid = (string)$levelid;
          $curmember = Db::name('member')->field('id,path')->where('aid',$aid)->where('id',$mid)->find(); 
          $pidArr = explode(',',$curmember['path']);
          $pidArr = array_reverse($pidArr);
          $num = 1;
          foreach($pidArr as $v)
          {
             $valuemember = Db::name('member')->field('id,levelid')->where('aid',$aid)->where('id',$v)->find(); 
             $vlauelevel = Db::name('member_level')->field('id,pingjilevelid2,pingjilevelidjiangli2')->where('aid',$aid)->where('id',$valuemember['levelid'])->find(); 
             if(strpos($vlauelevel['pingjilevelid2'],$levelid) !== false)
             {
                 if($tuanduifenh >0){
                     // 所有级别都使用配置的奖励比例
                     $jiangli = $vlauelevel['pingjilevelidjiangli2']*$tuanduifenh*0.01;
                     $jiangli = round($jiangli,2);
                     
                     self::addcommission($aid,$v,$mid,$jiangli,'团队分红-新加平级奖2');
                     $num++;
                     $tuanduifenh = $jiangli;
                 }else{
                     break;
                 }
             }
         }
          
     }
     
     /**
      * 分红平级奖3
      * */
      public static function fafangpingji3($aid,$mid,$levelid,$tuanduifenh)
     {
         $levelid = (string)$levelid;
         $curmember = Db::name('member')->field('id,path')->where('aid',$aid)->where('id',$mid)->find(); 
         $pidArr = explode(',',$curmember['path']);
         $pidArr = array_reverse($pidArr);
         $num = 1;
         foreach($pidArr as $v)
         {
             $valuemember = Db::name('member')->field('id',$v)->find(); 
             $vlauelevel = Db::name('member_level')->field('id,pingjilevelid3,pingjilevelidjiangli3')->where('aid',$aid)->where('id',$valuemember['levelid'])->find(); 
             if(strpos($vlauelevel['pingjilevelid3'],$levelid) !== false)
             {
                 if($tuanduifenh >0){
                     if($num ==1)
                     {
                         $jiangli = $vlauelevel['pingjilevelidjiangli3']*$tuanduifenh*0.01;
                         $jiangli =  round($jiangli,2);
                     }else{
                         $jiangli = $tuanduifenh*0.1;
                          $jiangli =  round($jiangli,2);
                     }
                     self::addcommission($aid,$v,$mid,$jiangli,'团队分红-新加平级奖');
                     $num++;
                     $tuanduifenh = $jiangli;
                 }else{
                     break;
                 }
             }
         }
     } 
    
    public static function addfanyong($aid,$mid,$frommid,$commission,$remark,$remark2)
    {
        //本人
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
        //一级
        $member1 = Db::name('member')->where('aid',$aid)->where('id',$member['pid'])->lock(true)->find();
        if(!empty($member1))
        {
            $memberlevel1 = Db::name('member_level')->where('aid',$aid)->where('id',$member1['levelid'])->lock(true)->find();
            if($memberlevel1['can_agent'] >0)
            {
                if($memberlevel1['commissionshouyi1'] >0)
                {
                    if($memberlevel1['commissiontype'] == 1)//固定金额
                    {
                        $jiangli = $memberlevel1['commissionshouyi1'];
                    }elseif($memberlevel1['commissiontype'] == 0)//百分比
                    {
                        $jiangli = $commission* $memberlevel1['commissionshouyi1']*0.01;
                    }
                    if($remark2 == '团队分红' || strpos($remark2,'团队') !== false)
                    {
                        if($memberlevel1['tuandui_chouyong'] == 1)
                        {
                            self::addcommission($aid,$member1['id'],$mid,$jiangli,$remark,1,'',1);
                        }
                    }else{
                        self::addcommission($aid,$member1['id'],$mid,$jiangli,$remark,1,'',1);
                    }
                }
                
            }
        }
        //二级
        $member2 = Db::name('member')->where('aid',$aid)->where('id',$member1['pid'])->lock(true)->find();
        if(!empty($member2))
        {
            $memberlevel2 = Db::name('member_level')->where('aid',$aid)->where('id',$member2['levelid'])->lock(true)->find();
            if($memberlevel2['can_agent'] >1)
            {
                 if($memberlevel2['commissionshouyi2'] >0)
                {
                    if($memberlevel2['commissiontype'] == 1)
                    {
                         $jiangli = $memberlevel2['commissionshouyi2'];
                    }elseif($memberlevel2['commissiontype'] == 0)
                    {
                        $jiangli = $commission* $memberlevel2['commissionshouyi2']*0.01;
                    }
                    if($remark2 == '团队分红' || strpos($remark2,'团队') !== false)
                    {
                        if($memberlevel2['tuandui_chouyong'] == 1)
                        {
                             self::addcommission($aid,$member2['id'],$mid,$jiangli,$remark,1,'',1);
                        }
                    }else{
                         self::addcommission($aid,$member2['id'],$mid,$jiangli,$remark,1,'',1);
                    }
                   
                }
            }
        }
       
        //三级
        $member3 = Db::name('member')->where('aid',$aid)->where('id',$member2['pid'])->lock(true)->find();
        if(!empty($member3))
        {
            $memberlevel3 = Db::name('member_level')->where('aid',$aid)->where('id',$member3['levelid'])->lock(true)->find();
            if($memberlevel3['can_agent'] >2)
            {
                if($memberlevel3['commissionshouyi3'] >0)
                {
                    if($memberlevel3['commissiontype'] == 1)
                    {
                         $jiangli = $memberlevel3['commissionshouyi3'];
                    }elseif($memberlevel3['commissiontype'] == 0)
                    {
                         $jiangli = $commission* $memberlevel3['commissionshouyi3']*0.01;
                    }
                    if($remark2 == '团队分红' || strpos($remark2,'团队') !== false)
                    {
                        if($memberlevel3['tuandui_chouyong'] == 1)
                        {
                              self::addcommission($aid,$member3['id'],$mid,$jiangli,$remark,1,'',1);
                        }
                    }else{
                          self::addcommission($aid,$member3['id'],$mid,$jiangli,$remark,1,'',1);
                    }
                   
                }
            }
        }
    }

    public static function addcommission2($aid,$mid,$frommid,$commission,$remark,$addtotal=1,$fhtype=''){
        if($commission==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        $set = Db::name('admin_set')->where('aid',$aid)->find();
//        if($commission > 0 && $set['commission2scorepercent'] > 0){
//            $oldcommission = $commission;
//            $commission = round($commission * (1-$set['commission2scorepercent']*0.01),2);
//            $score = $oldcommission - $commission;
//            self::addscore($aid,$mid,$score,$remark);
//        }
//
        $koucyz = 0;
        if($commission > 0 && $addtotal==1){
            if($member['bus_total'] >= $commission)
            {
                //扣除cyz 添加佣金
                // PHP 8.0+ 类型转换
                $totalcommission = floatval($member['totalcommission2'] ?? 0) + floatval($commission);
                $koucyz = $commission;
            }else{
                $totalcommission = $member['totalcommission2'] +$member['bus_total'];
                $koucyz = $member['bus_total'];
            }
                
        }else{
            $totalcommission = $member['totalcommission2'];
            $koucyz  = 0;
        }
        self::addbusTotal($aid,$mid,$mid,'-'.$koucyz,'到账分红扣除'.t('创业值'));
        // PHP 8.0+ 类型转换
        $after = floatval($member['commission2'] ?? 0) + floatval($commission);
        $update_member = ['totalcommission2'=>$totalcommission,'commission2'=>$after];
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update($update_member);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['frommid'] = $frommid;
        $data['commission'] = $commission;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_commissionlog')->insert($data);

        return ['status'=>1,'msg'=>''];
    }
    //获取余额
    public static function getmoney($member){
        if(!$member || !$member['id']) return '0.00';
        $member = db('member')->where(['id'=>$member['id']])->find();
        return $member['money'];
    }
    
   
    //获取积分
    public static function getscore($member){
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        return $member['score'];
    }
    //获取积分A
    public static function getscoreA($member){
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        return $member['scoreA'];
    }
     //获取积分B
    public static function getscoreB($member){
        if(!$member || !$member['id']) return '0';
        $member = db('member')->where(['id'=>$member['id']])->find();

        return $member['scoreB'];
    }


    public static function addw7moneyscore($aid,$member,$type,$money,$remark){
        $w7uniacid = db('admin_set')->where(['aid'=>$aid])->value('w7uniacid');
        if(empty($w7uniacid)) {
            return ['status'=>0,'msg'=>'w7uniacid empty'];
        }
        $fansinfo = Db::connect('w7')->table('ims_mc_mapping_fans')->where("uniacid='{$w7uniacid}' and (openid='{$member['mpopenid']}' or (unionid!='' && unionid is not null && unionid='{$member['unionid']}') or (openid!='' && openid is not null && openid='{$member['wxopenid']}'))")->find();
//        Log::write([
//            'file' => __FILE__,
//            'line' => __LINE__,
//            '$member' => $member,
//            '$fansinfo' => $fansinfo,
//            'sql' =>  Db::connect('w7')->table('ims_mc_mapping_fans')->getLastSql()
//        ]);
        $openid = $member['mpopenid'];
        if(!$openid) $openid = $member['unionid'];
        if(!$openid) $openid = $member['wxopenid'];
        if(!$fansinfo){
            $rec = array();
            $rec['acid'] = $w7uniacid;
            $rec['uniacid'] = $w7uniacid;
            $rec['openid'] = $openid;
            $rec['nickname'] = $member['nickname'];
            $rec['unionid'] = $member['unionid'];
            $rec['follow'] = $member['subscribe'] ? 1 : 0;
            $rec['followtime'] = $member['subscribe_time'] ? $member['subscribe_time'] : $member['createtime'];
            $rec['tag'] = base64_encode(serialize([
                'openid'=>$openid,
                'nickname'=>$member['nickname'],
                'sex'=>$member['sex'],
                'province'=>$member['province'],
                'city'=>$member['city'],
                'country'=>$member['country'],
                'unionid'=>$member['unionid'],
                'subscribe'=>$member['subscribe'],
                'subscribe_time'=>$member['subscribe_time'],
            ]));
            $member2 = array();
            $member2['uniacid'] = $w7uniacid;
            $member2['email'] = md5($openid).'@we7.cc';
            $member2['salt'] = random(8);
            $default_groupid = Db::connect('w7')->table('ims_mc_groups')->where(['uniacid'=>$w7uniacid,'isdefault'=>1])->value('groupid');
            $member2['groupid'] = $default_groupid;
            $member2['createtime'] = time();
            $member2['nickname'] = $member['nickname'];
            $member2['avatar'] = $member['headimg'];
            $member2['nationality'] = $member['country'];
            $member2['resideprovince'] = $member['province'];
            $member2['residecity'] = $member['city'];
            $config = include(ROOT_PATH.'config.php');
            $member2['password'] = md5($openid . $member2['salt'] . $config['authkey']);
            $rec['uid'] = Db::connect('w7')->table('ims_mc_members')->insertGetId($member2);
            Db::connect('w7')->table('ims_mc_mapping_fans')->insertGetId($rec);
        }
        $fansinfo = Db::connect('w7')->table('ims_mc_mapping_fans')->where("uniacid='{$w7uniacid}' and (openid='{$member['mpopenid']}' or (unionid!='' && unionid is not null && unionid='{$member['unionid']}') or (openid!='' && openid is not null && openid='{$member['wxopenid']}'))")->find();
        $uid = $fansinfo['uid'];
        $mcmember = Db::connect('w7')->table('ims_mc_members')->where(['uid'=>$uid])->find();
        if($uid == 0 || !$mcmember){
            $member2 = array();
            $member2['uniacid'] = $w7uniacid;
            $member2['email'] = md5($openid).'@we7.cc';
            $member2['salt'] = random(8);
            $default_groupid = Db::connect('w7')->table('ims_mc_groups')->where(['uniacid'=>$w7uniacid,'isdefault'=>1])->value('groupid');
            $member2['groupid'] = $default_groupid;
            $member2['createtime'] = time();
            $member2['nickname'] = $member['nickname'];
            $member2['avatar'] = $member['headimg'];
            $member2['nationality'] = $member['country'];
            $member2['resideprovince'] = $member['province'];
            $member2['residecity'] = $member['city'];
            $config = include(ROOT_PATH.'config.php');
            $member2['password'] = md5($openid . $member2['salt'] . $config['authkey']);
            $uid = Db::connect('w7')->table('ims_mc_members')->insertGetId($member2);
            Db::connect('w7')->table('ims_mc_mapping_fans')->where(['fanid'=>$fansinfo['fanid']])->update(['uid'=>$uid]);
            $mcmember = Db::connect('w7')->table('ims_mc_members')->where(['uid'=>$uid])->find();
        }
        $after = $mcmember['credit'.$type] + $money;
        Db::connect('w7')->table('ims_mc_members')->where(['uid'=>$uid])->update(['credit'.$type=>$after]);
        $data = array(
            'uid' => $uid,
            'credittype' => 'credit'.$type,
            'uniacid' => $w7uniacid,
            'num' => $money,
            'createtime' => time(),
            'operator' => '',
            'module' => 'ddwx_shop',
            'clerk_id' => '',
            'store_id' => '',
            'clerk_type' => 1,
            'remark' => $remark,
            'real_uniacid' => $uid
        );
        Db::connect('w7')->table('ims_mc_credits_record')->insert($data);

        if($type == 2){
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $member['id'];
            $data['money'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_moneylog')->insert($data);
            Db::name('member')->where(['aid'=>$aid,'id'=>$member['id']])->update(['money'=>$after]);
            Wechat::updatemembercard($aid,$member['id']);
        }else{
            $data = [];
            $data['aid'] = $aid;
            $data['mid'] = $member['id'];
            $data['score'] = $money;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            Db::name('member_scorelog')->insert($data);
            Db::name('member')->where(['aid'=>$aid,'id'=>$member['id']])->update(['score'=>$after]);
            Wechat::updatemembercard($aid,$member['id'],$remark);
        }

        return ['status'=>1,'msg'=>''];
    }
    //获取多少级以内的下级含脱离的（去掉缓存版本）
    public static function getdownmids($aid,$mid,$levelnum=0,$levelid=0){
        // ========== getdownmids 性能日志 ==========
        $func_start = microtime(true);
        
        $downmids = [];
        if($levelid == 0){
            $query_start = microtime(true);
            $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->select()->toArray();
            $query_time = round((microtime(true) - $query_start) * 1000, 2);
            $member_count = count($memberlist);
            \think\facade\Log::write("            [getdownmids] 查询下级会员 - 耗时: {$query_time}ms, 数量: {$member_count}");
        }else{
            $query_start = microtime(true);
            $levelid = str_replace('，',',',$levelid);
            $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',path)')->select()->toArray();
            $query_time = round((microtime(true) - $query_start) * 1000, 2);
            $member_count = count($memberlist);
            \think\facade\Log::write("            [getdownmids] 查询下级会员(按等级) - 耗时: {$query_time}ms, 数量: {$member_count}, 等级ID: {$levelid}");
            }
        foreach($memberlist as $member){
            if($levelnum == 0){
                $downmids[] = $member['id'];
            }else{
                $path = explode(',',$member['path']);
                $path = array_reverse($path);
                $key = array_search($mid,$path);
                if($key!==false && $key < $levelnum){
                    $downmids[] = $member['id'];
                }
            }
        }
        
        $func_time = round((microtime(true) - $func_start) * 1000, 2);
        $result_count = count($downmids);
        \think\facade\Log::write("            [getdownmids] 完成 - 总耗时: {$func_time}ms, 返回数量: {$result_count}");
        
        return $downmids;
    }
        //获取多少级以内的下级 筛选 不含脱离的
    public static function getdownmidsbu($aid,$mid,$levelnum=0,$levelid=0){
        $downmids = [];
//      var_dump($downmids);die;
        if($levelid == 0){
            $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->where('tuo1','<>',1)->select()->toArray();
        }else{
            $levelid = str_replace('，',',',$levelid);
            $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',path)')->where('tuo1','<>',1)->select()->toArray();
            }
        foreach($memberlist as $member){
            if($levelnum == 0){
                $downmids[] = $member['id'];
            }else{
                $path = explode(',',$member['path']);
                $path = array_reverse($path);
                $key = array_search($mid,$path);
                if($key!==false && $key < $levelnum){
                    $downmids[] = $member['id'];
                }
            }
        }
        return $downmids;
    }
    
    public static function getdownmids2($aid,$mid,$cyjj_qudengji,$levelnum=0,$levelid=0)
    {
        $downmids = [];
        if($levelid == 0){
            $memberlist = Db::name('member')->field('id,path,levelid')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->select()->toArray();
        }else{
            $levelid = str_replace('，',',',$levelid);
            $memberlist = Db::name('member')->field('id,path,levelid')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',path)')->select()->toArray();
        }
        foreach($memberlist as $member){
            if($member['levelid'] != $cyjj_qudengji){
                if($levelnum == 0){
                    $downmids[] = $member['id'];
                }else{
                    $path = explode(',',$member['path']);
                    $path = array_reverse($path);
                    $key = array_search($mid,$path);
                    if($key!==false && $key < $levelnum){
                        $downmids[] = $member['id'];
                    }
                }
          }
        }
        return $downmids;
    }

    public static function getdownlevelmids($aid,$mid,$levelnum=0,$levelid=0){
        $downmids = [];
        if($levelid == 0){
            $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('find_in_set('.$mid.',path)')->select()->toArray();
        }else{
            $levelid = str_replace('，',',',$levelid);
            $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('levelid','in',$levelid)->where('find_in_set('.$mid.',path)')->select()->toArray();
        }
        foreach($memberlist as $member){
            if($levelnum == 0){
                $downmids[] = $member['id'];
            }else{
                $path = explode(',',$member['path']);
                $path = array_reverse($path);
                $key = array_search($mid,$path);
                if($key!==false && $key < $levelnum){
                    $downmids[] = $member['id'];
                }
            }
        }
        return $downmids;
    }
    //获取多少级以内的下级 小区的(即除了人数最多的区的所有区)
    public static function getdownmids_xiao($aid,$mid,$levelnum=0,$levelid=0){
        $childList = Db::name('member')->field('id,path')->where('aid',$aid)->where('pid',$mid)->select()->toArray();

        $downmidsArr = [];
        foreach($childList as $cmember){
            $thisdownmids = self::getdownmids($aid,$cmember['id'],$levelnum,$levelid);
            if(!$thisdownmids){
                $thisdownmids = $cmember['id'];
            }else{
                $thisdownmids[] = $cmember['id'];
            }
            $downmidsArr[] = ['count'=>count($thisdownmids),'mids'=>$thisdownmids];
        }
        $counts = array_column($downmidsArr,'count');
        array_multisort($counts,SORT_DESC,$downmidsArr);

        $downmids = [];
        foreach($downmidsArr as $k=>$v){
            if($k > 0){
                $downmids = array_merge($downmids,$v['mids']);
            }
        }
        return $downmids;
    }

    //获取多少级以内的下级 去除业绩最高的一个
    public static function getdownmids_removemax($aid,$mid,$levelnum=0,$levelid=0){
        $childList = Db::name('member')->field('id,path')->where('aid',$aid)->where('pid',$mid)->select()->toArray();
        $downmidsArr = [];
//      var_dump($downmidsArr);die;
        foreach($childList as $cmember){
            $thisdownmids = self::getdownmids($aid,$cmember['id'],$levelnum,$levelid);
            if(!$thisdownmids){
                $thisdownmids = [$cmember['id']];
            }else{
                $thisdownmids[] = $cmember['id'];
            }
            //\think\facade\Log::write($thisdownmids);
            $fxordermoney = 0 + Db::name('shop_order_goods')->whereIn('status',MemberUpgradeOrderStatusPolicy::statuses(intval($aid)))->where('mid','in',$thisdownmids)->sum('totalprice');
            $downmidsArr[] = ['count'=>count($thisdownmids),'mids'=>$thisdownmids,'fxordermoney'=>$fxordermoney];
        }
        //\think\facade\Log::write($downmidsArr);
        $counts = array_column($downmidsArr,'fxordermoney');
        array_multisort($counts,SORT_DESC,$downmidsArr);
        //\think\facade\Log::write($downmidsArr);

        $downmids = [];
        foreach($downmidsArr as $k=>$v){
            if($k > 0){
                $downmids = array_merge($downmids,$v['mids']);
            }
        }
        return $downmids;
    }
    //获取团队的会员id集合 团队中有和他平级或超过他等级的就跳出
    private static $mids = [];

    /**
     * 优化版本：获取团队成员ID集合
     * 使用缓存和批量查询优化性能
     */
    public static function getteammids($aid,$mid,$deep,$levelids,$mids=[],$thisdeep=0){
        $start_time = microtime(true);
        $timestamp = date('Y-m-d H:i:s');

        // 详细记录输入参数
        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids] 输入参数 - aid: '.$aid.'，mid: '.$mid.'，deep: '.$deep.'，levelids: '.implode(',', $levelids), 'info');

        // 检查缓存
        $cache_key = "getteammids_{$aid}_{$mid}_{$deep}_" . md5(implode(',', $levelids));
        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids] 生成缓存键: '.$cache_key, 'info');

        // ✅ 启用缓存机制（缓存时间10分钟）
        $cached_result = \think\facade\Cache::get($cache_key);

        if ($cached_result !== false && $cached_result !== null) {
            $cache_time = round((microtime(true) - $start_time) * 1000, 2);
            \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids] ✅ 缓存命中！耗时: '.$cache_time.'ms，成员数量: '.count($cached_result), 'info');

            // 如果缓存结果为0且用户应该有团队成员，记录警告
            if (count($cached_result) == 0) {
                \think\facade\Log::write($timestamp.'-WARNING-[Member][getteammids] 缓存返回0个成员，可能是错误缓存，建议清除缓存重新计算', 'info');
            }

            return $cached_result;
        }

        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids] 缓存未命中，开始查询数据库', 'info');

        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids] 开始计算团队成员，aid: '.$aid.'，mid: '.$mid.'，deep: '.$deep.'，levelids: '.implode(',', $levelids), 'info');

        // 使用优化算法
        if ($deep > 50 || count($levelids) > 5) {
            // 大数据量使用基于path的查询
            $result = self::getteammids_optimized($aid, $mid, $deep, $levelids);
        } else {
            // 小数据量使用原始递归方法
            $result = self::getteammids_original($aid, $mid, $deep, $levelids, $mids, $thisdeep);
        }

        // ✅ 缓存结果（10分钟 = 600秒）
        \think\facade\Cache::set($cache_key, $result, 600);
        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids] 已缓存结果，缓存键: '.$cache_key.'，成员数量: '.count($result), 'info');

        $total_time = round((microtime(true) - $start_time) * 1000, 2);
        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids] 计算完成，总耗时: '.$total_time.'ms，成员数量: '.count($result), 'info');

        return $result;
    }

    /**
     * 优化版本：基于path字段的快速查询
     */
    private static function getteammids_optimized($aid, $mid, $deep, $levelids) {
        $start_time = microtime(true);
        $timestamp = date('Y-m-d H:i:s');

        // 使用path字段进行快速查询（优化版：使用 LIKE 替代 find_in_set）
        $levelids_str = implode(',', $levelids);
        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids_optimized_001] 开始优化查询（使用LIKE），aid: '.$aid.'，mid: '.$mid.'，deep: '.$deep.'，levelids: '.$levelids_str, 'info');

        // ✅ 使用 LIKE 查询替代 find_in_set，性能提升 60-75%
        $members = Db::name('member')
            ->field('id,path,levelid')  // 移除 nickname 字段，减少数据传输
            ->where('aid', $aid)
            ->whereIn('levelid', $levelids)  // 修复：使用 whereIn 传入数组而不是字符串
            ->where(function($query) use ($mid) {
                $query->whereOr([
                    ['path', 'like', "%,{$mid},%"],  // 中间节点
                    ['path', 'like', "{$mid},%"],    // 开始节点
                    ['path', 'like', "%,{$mid}"]     // 结束节点
                ]);
            })
            ->select()
            ->toArray();

        $query_time = round((microtime(true) - $start_time) * 1000, 2);
        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids_optimized_002] 数据库查询完成（LIKE），耗时: '.$query_time.'ms，查询到: '.count($members).'条记录，SQL: '.Db::getLastSql(), 'info');

        if (count($members) > 0) {
            \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids_optimized_003] 查询到 '.count($members).' 个成员，前3个ID: '.json_encode(array_column(array_slice($members, 0, 3), 'id')), 'info');
        }

        $result = [];
        $filtered_count = 0;
        foreach ($members as $member) {
            if ($deep > 0) {
                // 检查层级深度
                $path = explode(',', $member['path']);
                $path = array_reverse($path);
                $key = array_search($mid, $path);

                if ($key !== false && $key < $deep) {
                    $result[] = $member['id'];
                } else {
                    $filtered_count++;
                    if ($filtered_count <= 3) {
                        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids_optimized_004] 成员被层级过滤: ID='.$member['id'].'，path='.$member['path'].'，key='.$key.'，deep='.$deep, 'info');
                    }
                }
            } else {
                $result[] = $member['id'];
            }
        }

        $total_time = round((microtime(true) - $start_time) * 1000, 2);
        \think\facade\Log::write($timestamp.'-DEBUG-[Member][getteammids_optimized_005] 优化查询完成，总耗时: '.$total_time.'ms，最终结果: '.count($result).'个成员，被层级过滤: '.$filtered_count.'个', 'info');

        return $result;
    }

    /**
     * 原始递归方法（用于小数据量）
     * 修复静态变量污染问题
     */
    private static function getteammids_original($aid,$mid,$deep,$levelids,$mids=[],$thisdeep=0){
        // 使用传入的$mids参数而不是静态变量，避免多用户间的数据污染
        if($thisdeep == 0){
            $mids = []; // 初始化时清空
        }
        $thisdeep = $thisdeep+1;
        if($thisdeep > $deep) return $mids;

        $dowmids = Db::name('member')->where('aid',$aid)->where('pid',$mid)->whereIn('levelid',$levelids)->column('id');
        if($dowmids){
            foreach($dowmids as $downmid){
                if(!in_array($downmid,$mids)){
                    $mids[] = $downmid;
                    // 递归调用，传递$mids参数
                    $mids = self::getteammids_original($aid,$downmid,$deep,$levelids,$mids,$thisdeep);
                }
            }
        }
        return $mids;
    }

    public static function addHongbaoEverydayEdu($aid,$mid,$money,$remark,$ogid=0){
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        // PHP 8.0+ 类型转换
        $after = floatval($member['hongbao_everyday_edu'] ?? 0) + floatval($money);
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['hongbao_everyday_edu'=>$after]);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['ogid'] = $ogid;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_hbe_edu_record')->insert($data);
        return ['status'=>1,'msg'=>''];
    }

    public static function addHongbaoLog($aid,$mid,$money,$remark){

        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        // PHP 8.0+ 类型转换
        $afterTotal = floatval($member['hongbao_ereryday_total'] ?? 0) + floatval($money);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $afterTotal;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_hbe_log')->insert($data);
        return ['status'=>1,'msg'=>''];
    }

    //加余额宝
    public static function addyuebaomoney($aid,$mid,$money,$remark,$type=0){

        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        // PHP 8.0+ 类型转换
        $after = floatval($member['yuebao_money'] ?? 0) + floatval($money);
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['yuebao_money'=>$after]);

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['type']  = $type;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_yuebao_moneylog')->insert($data);

        return ['status'=>1,'msg'=>''];
    }
    //加元宝
    public static function addyuanbao($aid,$mid,$yuanbao,$remark){
        }
    //加其他余额
    public static function addOtherMoney($aid,$mid,$type,$money,$remark){
        }
        
     /**
 * 获取直推业绩
 */
public static function getZhituiYeji($aid, $mid)
{
    // 获取直推会员
    $memberList = Db::name('member')->field('id')->where('aid', $aid)->where('pid', $mid)->select()->toArray();
    if(empty($memberList)) {
        return 0;
    }
    
    $memberIds = array_column($memberList, 'id');
    $memberStr = implode(',', $memberIds);
    
    // 计算直推会员的订单业绩
    $where = [
        ['order.aid', '=', $aid],
        ['order.status', '>=', 1],
        ['order.status', '<>', 4]
    ];
    
    $yejiCount = Db::name('shop_order')
        ->alias('order')
        ->where($where)
        ->where('order.mid', 'in', $memberStr)
        ->where('good.is_yeji', '=', 1)
        ->join('shop_order_goods good', 'order.id=good.orderid')
        ->sum('good.totalprice');
        
    return $yejiCount ?: 0;
}

/**
 * 获取团队总业绩
 */
public static function getTuanduiYeji($aid, $mid)
{
    // 获取所有下级会员
    $memberAll = self::getdownmids($aid, $mid);
    if(empty($memberAll)) {
        return 0; 
    }
    
    $memberStr = implode(',', $memberAll);
    
    // 计算团队总业绩
    $where = [
        ['order.aid', '=', $aid],
        ['order.status', '>=', 1], 
        ['order.status', '<>', 4]
    ];
    
    $yejiCount = Db::name('shop_order')
        ->alias('order')
        ->where($where)
        ->where('order.mid', 'in', $memberStr)
        ->where('good.is_yeji', '=', 1)
        ->join('shop_order_goods good', 'order.id=good.orderid')
        ->sum('good.totalprice');
        
    return $yejiCount ?: 0;
}

/**
 * 获取我的团队业绩（去除一个最大区域业绩）
 * @param $aid
 * @param $mid
 * @return float|int
 */
public static function getmytuanduiyeji_removemax($aid, $mid)
{
    $yeji = 0;
    $member = Db::name('member')->where(['id' => $mid, 'aid' => $aid])->find();
    if (!$member) {
        return $yeji;
    }
    
    // 获取所有直推下级
    $list = Db::name('member')->where(['aid' => $aid, 'pid' => $mid])->select()->toArray();
    if (empty($list)) {
        return $yeji;
    }

    $areaYejis = []; // 各区域业绩
    
    // 计算每个直推下级的区域业绩
    foreach ($list as $v) {
        $downMids = self::getdownmids($aid, $v['id']);
        if (empty($downMids)) {
            $downMids = [$v['id']];
        } else {
            $downMids[] = $v['id'];
        }
        
        // 计算该区域的总业绩
        $where = [
            ['order.aid', '=', $aid],
        ];
        
        $areaYeji = Db::name('shop_order')
            ->alias('order')
            ->where($where)
            ->whereIn('order.status', MemberUpgradeOrderStatusPolicy::statuses(intval($aid)))
            ->where('order.mid', 'in', $downMids)
            ->where('good.is_yeji', '=', 1)
            ->join('shop_order_goods good', 'order.id=good.orderid')
            ->sum('good.totalprice');
        
        $areaYejis[] = $areaYeji ?: 0;
    }
    
    // 如果有区域业绩
    if (!empty($areaYejis)) {
        // 找出最大的区域业绩
        $maxYeji = max($areaYejis);
        
        // 计算总业绩
        $totalYeji = array_sum($areaYejis);
        
        // 总业绩减去最大区域业绩
        $yeji = $totalYeji - $maxYeji;
    }
    
    return $yeji;
}

    /**
     * A区业绩
     * */
     public static function gettanduiA($aid,$mid)
     {
        //首推第一人无限极业绩相加
        $memberone = Db::name('member')->field('id,nickname,levelid,createtime')->where('aid',$aid)->where('pid',$mid)->order('createtime','asc')->limit(1)->select()->toArray();
        if(!empty($memberone)){
            $memberAll = self::getdownmids($aid,$memberone[0]['id']);
            $memberAll[] = $memberone[0]['id'];
            $memberstr = implode(',',$memberAll);
            $set = Db::name('admin_set')->where('aid',$aid)->find();
            $where = [['order.aid','=',$aid]];
            $where[] = ['order.status','>=',1];
            $where[] = ['order.status','<>',4];
            $ordercount = Db::name('shop_order')->alias('order')->where($where)->where('order.mid','in',$memberstr)->where('good.is_yeji','=',1)->join('shop_order_goods good','order.id=good.orderid')->field('order.mid,order.id,good.id as ordergoodsid,good.jiandianjiangli,good.totalprice')->select()->toArray();
            $yejicount = 0;
            foreach($ordercount as $v)
            {
                $yejicount = $yejicount + $v['totalprice'];
            }
            return $yejicount;
        }else{
            return 0;
        }
     }
     /**
     * B区业绩
     * */
     public static function gettanduiB($aid,$mid)
     {
        //首推第二人无限极业绩相加
        $membertwo = Db::name('member')->field('id,nickname,levelid,createtime')->where('aid',$aid)->where('pid',$mid)->order('createtime','asc')->limit(1,1)->select()->toArray();
        if(!empty($membertwo)){
            $memberAll = self::getdownmids($aid,$membertwo[0]['id']);
            $memberAll[] = $membertwo[0]['id'];
            $memberstr = implode(',',$memberAll);
            $set = Db::name('admin_set')->where('aid',$aid)->find();
            $where = [['order.aid','=',$aid]];
            $where[] = ['order.status','>=',1];
            $where[] = ['order.status','<>',4];
            $ordercount = Db::name('shop_order')->alias('order')->where($where)->where('order.mid','in',$memberstr)->where('good.is_yeji','=',1)->join('shop_order_goods good','order.id=good.orderid')->field('order.mid,order.id,good.id as ordergoodsid,good.jiandianjiangli,good.totalprice')->select()->toArray();
            $yejicount = 0;
            foreach($ordercount as $v)
            {
                $yejicount = $yejicount + $v['totalprice'];
            }
            return $yejicount;
        }else{
            return 0;
        }
     }
     /**
     * C区业绩
     * */
     public static function gettanduiC($aid,$mid)
     {
         //除去前两人无限极业绩相加
         $memberc = Db::name('member')->field('id,nickname,levelid,createtime')->where('aid',$aid)->where('pid',$mid)->order('createtime','asc')->limit(2,9999999999)->select()->toArray();
        if(!empty($memberc)){
            $memberAll = [];
            foreach($memberc as $v)
            {
                 $memberAll[] = $v['id'];
                 $memberAll2 = self::getdownmids($aid,$v['id']);
                 $memberAll = array_merge($memberAll,$memberAll2);
            }
            $memberstr = implode(',',$memberAll);
            $set = Db::name('admin_set')->where('aid',$aid)->find();
            $where = [['order.aid','=',$aid]];
            $where[] = ['order.status','>=',1];
            $where[] = ['order.status','<>',4];
            $ordercount = Db::name('shop_order')->alias('order')->where($where)->where('order.mid','in',$memberstr)->where('good.is_yeji','=',1)->join('shop_order_goods good','order.id=good.orderid')->field('order.mid,order.id,good.id as ordergoodsid,good.jiandianjiangli,good.totalprice')->select()->toArray();
            $yejicount = 0;
            foreach($ordercount as $v)
            {
                $yejicount = $yejicount + $v['totalprice'];
            }
            return $yejicount;
        }else{
            return 0;
        }
     }

     
        
     /**
      * 得到签到分红
      * */
     public function getqianfh($aid,$mid,$signset)
     {
        $starttime = strtotime(date('Y-m-d 00:00:00',strtotime('-1 days')));
        $endtime = strtotime(date('Y-m-d 23:59:59',strtotime('-1 days')));
        //1.获取昨日订单的 30%
        $alltotalprice = Db::name('shop_order')->where('status','in','1,2,3')->where('aid',$aid)->where('paytime','>=',$starttime)->where('paytime','<=',$endtime)->sum('totalprice');
       
        $jiang = $alltotalprice*$signset['jiangfenh']*0.01;
        //2.计算自己的订单总额
         $mytotalprice = Db::name('shop_order')->where('status','in','1,2,3')->where('aid',$aid)->where('mid',$mid)->where('paytime','>=',$starttime)->where('paytime','<=',$endtime)->sum('totalprice');
        //3.计算自己的点数 - 检查是否开启分红点数功能
        $dianshu = 1; // 默认值，避免除零
        if(isset($signset['dianshu_enabled']) && $signset['dianshu_enabled'] == 1 && $signset['dianshu'] > 0){
            $dianshu = $signset['dianshu'];
        }
        $mydian = $mytotalprice/$dianshu;
        $zongdian = $alltotalprice/$dianshu;
        if($zongdian != 0){
            $yidian = $jiang/$zongdian;
            //4.计算分红
            $fh = $yidian*$mydian;
            $fh = round($fh,2);
            // 检查是否开启最大消耗创业值限制
            if(isset($signset['maxchuang_enabled']) && $signset['maxchuang_enabled'] == 1 && $signset['maxchuang'] > 0){
            if($fh > $signset['maxchuang'])
            {
                $fh = $signset['maxchuang'];
                }
            }
        }else{
             $fh = 0;
        }
        return $fh;
     }
     
     /**
      * 预计总分红数
      * */
      public function getqianfh2($aid,$signset)
     {
         $starttime = strtotime(date('Y-m-d 00:00:00',strtotime('-1 days')));
        $endtime = strtotime(date('Y-m-d 23:59:59',strtotime('-1 days')));
        //1.获取昨日订单的 30%
        $alltotalprice = Db::name('shop_order')->where('status','in','1,2,3')->where('aid',$aid)->where('paytime','>=',$starttime)->where('paytime','<=',$endtime)->sum('totalprice');
        $jiang = $alltotalprice*$signset['jiangfenh']*0.01;
        return  round($jiang,2);
     }
     /**
      * 预计总分红数
      * */
      public function getdanqianfh($aid,$signset)
     {
         $starttime = strtotime(date('Y-m-d 00:00:00',strtotime('-1 days')));
        $endtime = strtotime(date('Y-m-d 23:59:59',strtotime('-1 days')));
        //1.获取昨日订单的 30%
        $alltotalprice = Db::name('shop_order')->where('status','in','1,2,3')->where('aid',$aid)->where('paytime','>=',$starttime)->where('paytime','<=',$endtime)->sum('totalprice');
        $jiang = $alltotalprice*$signset['jiangfenh']*0.01;
        //获取会员的所有的分红点数
        $dis=Db::name('Member')->where('aid',aid)->field('sum(fenhong_num) as z_fenhong_num')->find();
        $z_fenhong_num=0;
        if($dis['z_fenhong_num']){
            $z_fenhong_num=$dis['z_fenhong_num'];
        }
        $jiang2 =  $jiang/$z_fenhong_num;
        return  round($jiang2,2);
     }
     
     
     public function getfhdian3($aid,$mid,$signset,$fenhong_num=0)
     {
         $starttime = strtotime(date('Y-m-d 00:00:00',strtotime('-1 days')));
        $endtime = strtotime(date('Y-m-d 23:59:59',strtotime('-1 days')));
        //1.获取昨日订单的 30%
        $alltotalprice = Db::name('shop_order')->where('status','in','1,2,3')->where('aid',$aid)->where('paytime','>=',$starttime)->where('paytime','<=',$endtime)->sum('totalprice');
        $jiang = $alltotalprice*$signset['jiangfenh']*0.01;
        //2.计算自己的订单总额
         $mytotalprice = Db::name('shop_order')->where('status','in','1,2,3')->where('aid',$aid)->where('mid',$mid)->where('paytime','>=',$starttime)->where('paytime','<=',$endtime)->sum('totalprice');
        //3.计算自己的点数
        $mydian = $mytotalprice/$signset['dianshu'];
        //4 加上设置的分红点数
        $mydian=$mydian+$fenhong_num;
        return round($mydian,2);
     }
     
    /**
     * @param $shop
     * @return 获取商品的红包抵现金额
     */
    public static function getShopProductScoredkmaxsethuang($aid,$shop){
        $datas=array(
            'types'=>0,  //是否抵现
            'nums'=>0,  //抵现金额
        );
        if($shop['scoredkmaxsethuang']){
            switch ($shop['scoredkmaxsethuang']){
                case 0:
                    //获取系统的
                    $set = Db::name('admin_set')->where('aid',$aid)->find();
                    if($set['score2moneyhuang']>0){
                        $datas['types']=1;
                        $datas['nums']=$set['scoredkmaxpercenthuang']."%";
                    }
                    break;
                case 1:
                    //获取百分比
                    $datas['types']=1;
                    $datas['nums']=$shop['scoredkmaxunithuang']."%";
                    break;
                case 2:
                    //获取抵现元
                    $datas['types']=1;
                    $datas['nums']=$shop['scoredkmaxunithuang']."元";
                    break;
            }
        }

        return $datas;

    }
     
     
    public function getLyjceshi($aid,$mid){
           $order = [
               'times'=>date('Y-m-d H:i:s')
            ];
            Db::name('member_lyj_logs')->insert($order);
    }
     
    public static function user_jiedianren($aid,$mid,$pid, &$slideReason = null)
    {
        $jiedianpid = $pid;
        $slideReason = null;
        $jifechiRow = self::tableExistsStatic('jifechi') ? Db::name('jifechi')->where('aid',$aid)->find() : null;
        $jiuxingsetinfo = $jifechiRow ? json_decode($jifechiRow['jiuxing'],1) : null;
        
        // 检查九星功能是否开启（未配置或未开启时，直接用推荐人做接点人）
        if(!$jiuxingsetinfo || $jiuxingsetinfo['jiuxing_status'] != 1) {
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['jiedianpid'=>$pid]);
            \think\facade\Log::write('[接点人] 九星未开启，直接用推荐人做接点人 新用户:'.$mid.' 接点人:'.$pid, 'info');
            $jiedianpid = self::applyHualuojinjieSlideHook($aid, $mid, $pid, $pid, $slideReason);
            self::applyMatrixRewardJoinHook($aid, $mid);
            return $jiedianpid;
        }

        // 检查是否启用新滑落系统
        $enableNewSlide = isset($jiuxingsetinfo['enable_new_slide']) ? $jiuxingsetinfo['enable_new_slide'] : 0;
        
        if($enableNewSlide == 1) {
            // 使用新滑落逻辑
            $jiedianpid = self::processNewSlideLogic($aid, $mid, $pid, $jiuxingsetinfo, $slideReason);
        } else {
            // 使用原有滑落逻辑
            $jiedianpid = self::processOriginalSlideLogic($aid, $mid, $pid, $jiuxingsetinfo, $slideReason);
        }

        // 检查返回的接点人是否有效
        if(empty($jiedianpid) || $jiedianpid <= 0) {
            $jiedianpid = $pid; // 降级处理：使用推荐人
            $slideReason = null; // 使用推荐人，不算滑落
        }

        // 更新用户的接点人
        Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['jiedianpid'=>$jiedianpid]);
        
        \think\facade\Log::write('[接点人滑落] 新用户:'.$mid.' 推荐人:'.$pid.' 接点人:'.$jiedianpid, 'info');
        
        // 添加联动人设置
        $liandongrs = self::getEnabledLiandongSet($aid, [], [
            'liandong_set' => ['status'],
            'member' => ['liandongren'],
        ]);
        if(!empty($liandongrs) && $liandongrs['status'] == 1) {
            // 联动2+1功能已启用，设置联动人为推荐人
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['liandongren'=>$pid]);
        }
        
        // 检查帮扶模式滑落逻辑
        self::checkBangfuHuala($aid, $pid, $mid);
        
        // 滑落晋级插件钩子：记录活动独立排位并检查晋级（插件未安装时自动跳过）。
        $jiedianpid = self::applyHualuojinjieSlideHook(
            $aid,
            $mid,
            $jiedianpid,
            $pid,
            $slideReason
        );

        self::applyMatrixRewardJoinHook($aid, $mid);

        return $jiedianpid;
    }

    private static function applyMatrixRewardJoinHook($aid, $mid)
    {
        if (!class_exists('\\app\\plugin\\matrixreward\\common\\MatrixRewardService')) {
            return;
        }
        try {
            \app\plugin\matrixreward\common\MatrixRewardService::onMemberJoined(intval($aid), intval($mid));
        } catch (\Throwable $e) {
            \think\facade\Log::error('[矩阵奖励] 入网钩子失败: ' . $e->getMessage());
        }
    }

    private static function applyHualuojinjieSlideHook(
        $aid,
        $mid,
        $jiedianpid,
        $pid,
        &$slideReason = null
    ) {
        if (!class_exists('\\app\\plugin\\hualuojinjie\\SlideHook')) {
            return $jiedianpid;
        }
        try {
            $pluginJiedianpid = \app\plugin\hualuojinjie\SlideHook::onPlaced(
                intval($aid),
                intval($mid),
                intval($jiedianpid),
                intval($pid),
                $slideReason
            );
            return max(0, intval($pluginJiedianpid));
        } catch (\Throwable $e) {
            \think\facade\Log::error('[滑落晋级] 钩子执行失败: ' . $e->getMessage());
            return $jiedianpid;
        }
    }

    /**
     * 会员等级成功变更后的插件扩展入口。
     */
    public static function dispatchLevelChangedHooks(
        int $aid,
        int $mid,
        int $beforeLevelId,
        int $afterLevelId
    ): void {
        if ($aid <= 0 || $mid <= 0 || $afterLevelId <= 0 || $beforeLevelId === $afterLevelId) {
            return;
        }
        try {
            if (class_exists('\\app\\plugin\\sweepstakes\\common\\SweepstakesTaskService')) {
                \app\plugin\sweepstakes\common\SweepstakesTaskService::grantForLevelUp(
                    $aid,
                    $mid,
                    $beforeLevelId,
                    $afterLevelId
                );
            }
        } catch (\Throwable $e) {
            Log::warning('幸运抽奖邀请升级任务处理失败: aid=' . $aid
                . ', mid=' . $mid . ', before=' . $beforeLevelId
                . ', after=' . $afterLevelId . ', error=' . $e->getMessage());
        }
        try {
            if (class_exists('\\app\\plugin\\levelfenhongpool\\common\\LevelFenhongPoolService')) {
                \app\plugin\levelfenhongpool\common\LevelFenhongPoolService::onLevelChanged(
                    $aid,
                    $mid,
                    $beforeLevelId,
                    $afterLevelId
                );
            }
        } catch (\Throwable $e) {
            Log::error('[等级分红池] 等级到达事件处理失败: aid=' . $aid
                . ', mid=' . $mid . ', before=' . $beforeLevelId
                . ', after=' . $afterLevelId . ', error=' . $e->getMessage());
        }
        try {
            if (class_exists('\\app\\plugin\\matrixreward\\common\\MatrixRewardService')) {
                \app\plugin\matrixreward\common\MatrixRewardService::onMemberLevelChanged(
                    $aid,
                    $mid,
                    $beforeLevelId,
                    $afterLevelId
                );
            }
        } catch (\Throwable $e) {
            Log::error('[矩阵奖励] 等级入网钩子失败: aid=' . $aid
                . ', mid=' . $mid . ', before=' . $beforeLevelId
                . ', after=' . $afterLevelId . ', error=' . $e->getMessage());
        }
        if ($beforeLevelId <= 0 || !class_exists('\\app\\plugin\\hualuojinjie\\SlideHook')) {
            return;
        }
        try {
            \app\plugin\hualuojinjie\SlideHook::onLevelChanged(
                $aid,
                $mid,
                $beforeLevelId,
                $afterLevelId
            );
        } catch (\Throwable $e) {
            Log::error('[滑落晋级] 等级变更入排钩子失败: aid=' . $aid
                . ', mid=' . $mid . ', before=' . $beforeLevelId
                . ', after=' . $afterLevelId . ', error=' . $e->getMessage());
        }
    }

    /**
     * 计算会员等级有效期结束时间。
     * 等级有效期沿用现有规则：从当天零点后的下一天开始计算，0 表示永久。
     */
    public static function getLevelEndTime(array $level, ?int $referenceTime = null): int
    {
        $validDays = intval($level['yxqdate'] ?? 0);
        if ($validDays <= 0) {
            return 0;
        }

        $referenceTime = $referenceTime ?: time();
        return strtotime(date('Y-m-d', $referenceTime)) + 86400 + (86400 * $validDays);
    }

    /**
     * 清理已过期的多分组等级记录。
     * 多分组等级直接通过 member_level_record 参与分销，不能只依赖定时任务删除。
     */
    public static function expireMemberLevelRecords(int $aid = 0, ?int $mid = null): int
    {
        // 多分组等级表属于可选扩展。先缓存表结构判断，避免老站点每次会员请求
        // 都抛出一次查询异常并写 warning 日志。
        if (!self::tableExistsStatic('member_level_record')) {
            return 0;
        }
        try {
            $query = Db::name('member_level_record')
                ->where('levelendtime', '>', 0)
                ->where('levelendtime', '<', time());
            if ($aid > 0) {
                $query->where('aid', $aid);
            }
            if ($mid !== null && $mid > 0) {
                $query->where('mid', $mid);
            }
            return intval($query->delete());
        } catch (\Throwable $e) {
            // 老站点可能未安装多分组等级表，不能影响主会员等级流程。
            Log::warning('清理多分组过期等级失败: aid=' . $aid . ', mid=' . intval($mid) . ', error=' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 统一写入会员等级及有效期，避免各业务入口只更新 levelid。
     * 传入 expectedLevelId 时会做乐观并发校验，等级已被其他流程改变则不覆盖。
     */
    public static function applyMemberLevel(
        int $aid,
        int $mid,
        array $targetLevel,
        ?int $expectedLevelId = null
    ): bool {
        $targetLevelId = intval($targetLevel['id'] ?? 0);
        if ($aid <= 0 || $mid <= 0 || $targetLevelId <= 0) {
            return false;
        }

        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
        if (!$member) {
            return false;
        }

        $beforeLevelId = intval($member['levelid'] ?? 0);
        if ($expectedLevelId !== null && $beforeLevelId !== intval($expectedLevelId)) {
            return false;
        }

        $query = Db::name('member')
            ->where('aid', $aid)
            ->where('id', $mid);
        if ($expectedLevelId !== null) {
            $query->where('levelid', intval($expectedLevelId));
        }

        $now = time();
        $updated = $query->update([
            'levelid' => $targetLevelId,
            'levelendtime' => self::getLevelEndTime($targetLevel, $now),
            'levelstarttime' => $now,
            'ylevelid' => $beforeLevelId,
        ]);
        if ($updated === 1) {
            self::dispatchLevelChangedHooks($aid, $mid, $beforeLevelId, $targetLevelId);
            return true;
        }
        return false;
    }

    /**
     * 处理单个会员的等级到期，供定时任务和会员请求入口共同调用。
     * 更新使用原等级和原到期时间作为条件，避免旧扫描结果覆盖并发产生的新等级。
     */
    public static function expireLevelIfNeeded(int $aid, int $mid): ?array
    {
        if ($aid <= 0 || $mid <= 0) {
            return null;
        }

        $lockName = 'member_level_expire_' . $aid . '_' . $mid;
        $lockAcquired = false;
        $member = null;
        try {
            $lockResult = Db::query("SELECT GET_LOCK('{$lockName}', 1) AS member_level_expire_lock");
            $lockAcquired = intval($lockResult[0]['member_level_expire_lock'] ?? 0) === 1;
            if (!$lockAcquired) {
                return Db::name('member')->where('aid', $aid)->where('id', $mid)->find() ?: null;
            }

            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
            $now = time();
            if (!$member) {
                return null;
            }
            $levelEndTime = intval($member['levelendtime'] ?? 0);
            if ($levelEndTime <= 0 || $levelEndTime >= $now) {
                return $member ?: null;
            }

            $defaultLevel = Db::name('member_level')
                ->where('aid', $aid)
                ->where('isdefault', 1)
                ->find();
            if (!$defaultLevel) {
                Log::warning('会员等级到期处理缺少默认等级: aid=' . $aid . ', mid=' . $mid);
                return $member;
            }

            $beforeLevelId = intval($member['levelid'] ?? 0);
            $targetLevel = $defaultLevel;
            $currentLevel = Db::name('member_level')
                ->where('aid', $aid)
                ->where('id', $beforeLevelId)
                ->find();

            if (function_exists('getcustom') && getcustom('next_level_set') && $currentLevel) {
                $nextLevelId = intval($currentLevel['next_level_id'] ?? 0);
                if ($nextLevelId > 0 && $nextLevelId !== intval($defaultLevel['id']) && $nextLevelId !== $beforeLevelId) {
                    $nextLevel = Db::name('member_level')
                        ->where('aid', $aid)
                        ->where('id', $nextLevelId)
                        ->find();
                    if ($nextLevel) {
                        $targetLevel = $nextLevel;
                    }
                }
            }

            $afterLevelId = intval($targetLevel['id'] ?? 0);
            if ($afterLevelId <= 0) {
                return $member;
            }

            $updated = Db::name('member')
                ->where('aid', $aid)
                ->where('id', $mid)
                ->where('levelid', $beforeLevelId)
                ->where('levelendtime', $levelEndTime)
                ->update([
                    'levelid' => $afterLevelId,
                    'levelendtime' => self::getLevelEndTime($targetLevel, $now),
                    'levelstarttime' => $now,
                    'ylevelid' => $beforeLevelId,
                ]);

            if ($updated === 1) {
                self::dispatchLevelChangedHooks($aid, $mid, $beforeLevelId, $afterLevelId);
            }

            return Db::name('member')->where('aid', $aid)->where('id', $mid)->find() ?: $member;
        } catch (\Throwable $e) {
            Log::warning('会员等级到期处理失败: aid=' . $aid . ', mid=' . $mid . ', error=' . $e->getMessage());
            return $member;
        } finally {
            if ($lockAcquired) {
                try {
                    Db::query("SELECT RELEASE_LOCK('{$lockName}')");
                } catch (\Throwable $e) {
                    Log::warning('会员等级到期锁释放失败: aid=' . $aid . ', mid=' . $mid . ', error=' . $e->getMessage());
                }
            }
        }
    }

    /**
     * 处理新滑落逻辑
     */
    private static function processNewSlideLogic($aid, $mid, $pid, $config, &$slideReason = null)
    {
        $hualuoMode = isset($config['hualuo_mode']) ? $config['hualuo_mode'] : 0;
        
        switch($hualuoMode) {
            case 0: // 关闭滑落
                return $pid;
                
            case 1: // 基于人数阈值
                $result = self::processNumberBasedSlide($aid, $pid, $config, $mid, $slideReason);
                return $result;
                
            case 2: // 基于业绩阈值
                $result = self::processPerformanceBasedSlide($aid, $pid, $config, $mid, $slideReason);
                return $result;
                
            case 3: // 混合模式
                $result = self::processMixedModeSlide($aid, $pid, $config, $mid, $slideReason);
                return $result;
                
            default:
                return $pid;
        }
    }

    /**
     * 处理原有滑落逻辑（保持兼容性）
     */
    private static function processOriginalSlideLogic($aid, $mid, $pid, $config, &$slideReason = null)
    {
        $sethualuocount = $config['hualuo'];
        
        if($sethualuocount <= 0) {
            return $pid;
        }

        // 获取推荐人的下级人数（这里是接点下级，不是直推下级）
        $membercount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$pid)->count();
        
        if($membercount >= $sethualuocount) {
            $hualuoStatus = $config['hualuo_status'];
            
            if($hualuoStatus == 0) {
                // 正常滑落
                $result = self::getjiedianpid($pid,$sethualuocount,$aid);
                if($result && $result != $pid) {
                    // 获取最终接点人的信息
                    $finalJiedian = Db::name('member')->where('aid', $aid)->where('id', $result)->field('id,nickname,tel')->find();
                    $finalJiedianName = $finalJiedian ? ($finalJiedian['nickname'] ?: ($finalJiedian['tel'] ?: 'ID:'.$result)) : 'ID:'.$result;
                    $slideReason = '正常滑落：推荐人接点下级已满'.$sethualuocount.'人，滑落到'.$finalJiedianName.'下面';
                }
                return $result ?: $pid;
            } elseif($hualuoStatus == 1) {
                // 公排制
                $result = self::getjiedianpidgongpaizhi($pid,$sethualuocount,$aid);
                if($result && $result != $pid) {
                    // 获取最终接点人的信息
                    $finalJiedian = Db::name('member')->where('aid', $aid)->where('id', $result)->field('id,nickname,tel')->find();
                    $finalJiedianName = $finalJiedian ? ($finalJiedian['nickname'] ?: ($finalJiedian['tel'] ?: 'ID:'.$result)) : 'ID:'.$result;
                    $slideReason = '公排制滑落：推荐人接点下级已满'.$sethualuocount.'人，按公排制滑落到'.$finalJiedianName.'下面';
                }
                return $result ?: $pid;
            } elseif($hualuoStatus == 2) {
                // 大小区滑落
                $areaType = isset($config['hualuo_area_type']) ? intval($config['hualuo_area_type']) : 0;
                $targetAreaType = ($areaType == 0) ? 'daqu' : 'xiaoqu';
                $areaTypeName = ($areaType == 0) ? '大区' : '小区';
                
                $areaReason = null;
                $targetPid = self::getSlideTargetArea($aid, $pid, $targetAreaType, $config, $areaReason);
                
                if($targetPid && $targetPid != $pid) {
                    // 获取滑落方式配置：0=填充空位，1=固定左边
                    $slideMethod = isset($config['hualuo_area_slide_method']) ? intval($config['hualuo_area_slide_method']) : 0;
                    
                    // 调试日志
                    \think\facade\Log::write('[大小区滑落] 推荐人ID='.$pid.' 目标区域ID='.$targetPid.' 滑落方式配置='.$slideMethod.' (0=填充空位,1=固定左边)', 'info');
                    
                    if($slideMethod == 1) {
                        // 固定左边：固定滑落到目标区域的左边（第一个直接接点下级），然后一直往左边滑落
                        // 获取目标区域的直接接点下级，按ID升序排序（左边）
                        $targetAreaChildren = Db::name('member')
                            ->where('aid', $aid)
                            ->where('jiedianpid', $targetPid)
                            ->field('id')
                            ->order('id', 'asc')
                            ->select()
                            ->toArray();
                        
                        if(empty($targetAreaChildren)) {
                            // 目标区域没有直接接点下级，直接接在目标区域下面
                            $result = $targetPid;
                        } else {
                            // 固定选择目标区域的第一个直接接点下级（左边），然后在这个左边节点下一直往左边滑落
                            $leftChildId = $targetAreaChildren[0]['id'];
                            $result = self::getjiedianpidLeft($leftChildId, $sethualuocount, $aid);
                            if(!$result) {
                                // 如果找不到，降级为直接接在左边节点下面
                                $result = $leftChildId;
                            }
                        }
                    } else {
                        // 填充空位：在目标区域内递归查找任意空位
                        $result = self::getjiedianpid($targetPid, $sethualuocount, $aid);
                    }
                    
                    if($result && $result != $pid) {
                        // 获取最终接点人的信息
                        $finalJiedian = Db::name('member')->where('aid', $aid)->where('id', $result)->field('id,nickname,tel')->find();
                        $finalJiedianName = $finalJiedian ? ($finalJiedian['nickname'] ?: ($finalJiedian['tel'] ?: 'ID:'.$result)) : 'ID:'.$result;
                        
                        // 获取目标区域接点人的信息
                        $targetJiedian = Db::name('member')->where('aid', $aid)->where('id', $targetPid)->field('id,nickname,tel')->find();
                        $targetJiedianName = $targetJiedian ? ($targetJiedian['nickname'] ?: ($targetJiedian['tel'] ?: 'ID:'.$targetPid)) : 'ID:'.$targetPid;
                        
                        // 构建滑落原因文本
                        $reasonText = '推荐人接点下级已满'.$sethualuocount.'人';
                        if($areaReason) {
                            $reasonText .= '，'.$areaReason;
                        } else {
                            // 如果areaReason为空，至少说明选择了目标区域
                            $reasonText .= '，选择'.$areaTypeName.'（'.$targetJiedianName.'）';
                        }
                        
                        if($slideMethod == 1) {
                            // 固定左边模式：固定滑落到目标区域的左边，然后一直往左边滑落
                            if($result == $targetPid) {
                                // 直接接在目标区域下面（说明目标区域没有直接接点下级）
                                $reasonText .= '，直接接在'.$targetJiedianName.'下面';
                            } else {
                                // 获取目标区域的直接接点下级（用于构建路径）
                                $targetAreaChildren = Db::name('member')
                                    ->where('aid', $aid)
                                    ->where('jiedianpid', $targetPid)
                                    ->field('id')
                                    ->order('id', 'asc')
                                    ->select()
                                    ->toArray();
                                
                                if(!empty($targetAreaChildren)) {
                                    $firstLeftId = $targetAreaChildren[0]['id'];
                                    
                                    // 构建从第一个左边节点到最终节点的路径
                                    $leftPath = self::getLeftSlidePath($aid, $firstLeftId, $result, $sethualuocount);
                                    
                                    if(count($leftPath) > 1 || $result != $firstLeftId) {
                                        // 有滑落路径，说明经过了多个左边节点
                                        $pathNames = [];
                                        // 先添加第一个左边节点
                                        $firstLeftInfo = Db::name('member')->where('aid', $aid)->where('id', $firstLeftId)->field('id,nickname,tel')->find();
                                        $firstLeftName = $firstLeftInfo ? ($firstLeftInfo['nickname'] ?: ($firstLeftInfo['tel'] ?: 'ID:'.$firstLeftId)) : 'ID:'.$firstLeftId;
                                        $pathNames[] = $firstLeftName;
                                        
                                        // 添加路径中的其他节点
                                        foreach($leftPath as $pathId) {
                                            if($pathId != $firstLeftId) {
                                                $pathMember = Db::name('member')->where('aid', $aid)->where('id', $pathId)->field('id,nickname,tel')->find();
                                                $pathName = $pathMember ? ($pathMember['nickname'] ?: ($pathMember['tel'] ?: 'ID:'.$pathId)) : 'ID:'.$pathId;
                                                $pathNames[] = $pathName;
                                            }
                                        }
                                        
                                        if($result != $firstLeftId && !in_array($result, $leftPath)) {
                                            // 如果最终节点不在路径中，添加它
                                            $finalInfo = Db::name('member')->where('aid', $aid)->where('id', $result)->field('id,nickname,tel')->find();
                                            $finalName = $finalInfo ? ($finalInfo['nickname'] ?: ($finalInfo['tel'] ?: 'ID:'.$result)) : 'ID:'.$result;
                                            $pathNames[] = $finalName;
                                        }
                                        
                                        $reasonText .= '，固定滑落到'.$targetJiedianName.'的左边，一直往左边滑落：'.implode(' -> ', $pathNames);
                                    } else {
                                        // 直接接在第一个左边节点下面
                                        $firstLeftInfo = Db::name('member')->where('aid', $aid)->where('id', $firstLeftId)->field('id,nickname,tel')->find();
                                        $firstLeftName = $firstLeftInfo ? ($firstLeftInfo['nickname'] ?: ($firstLeftInfo['tel'] ?: 'ID:'.$firstLeftId)) : 'ID:'.$firstLeftId;
                                        $reasonText .= '，固定滑落到'.$targetJiedianName.'的左边（'.$firstLeftName.'）下面';
                                    }
                                } else {
                                    // 理论上不会到这里，但为了安全
                                    $reasonText .= '，固定滑落到'.$targetJiedianName.'的左边';
                                }
                            }
                        } else {
                            // 填充空位模式
                            if($result == $targetPid) {
                                // 直接接在目标区域下面
                                $reasonText .= '，直接接在'.$targetJiedianName.'下面';
                            } else {
                                // 在目标区域内滑落
                                $targetAreaChildCount = Db::name('member')->where('aid', $aid)->where('jiedianpid', $targetPid)->count();
                                $reasonText .= '，但'.$targetJiedianName.'接点下级已满'.$targetAreaChildCount.'人，在该区域内继续滑落到'.$finalJiedianName.'下面';
                            }
                        }
                        $slideReason = '大小区滑落：'.$reasonText;
                        
                        // 调试日志
                        \think\facade\Log::write('[大小区滑落] 最终接点人ID='.$result.' 滑落原因='.$slideReason, 'info');
                    }
                    return $result ?: $pid;
                }
                // 如果找不到目标区域，降级为正常滑落
                $result = self::getjiedianpid($pid, $sethualuocount, $aid);
                if($result && $result != $pid) {
                    // 获取最终接点人的信息
                    $finalJiedian = Db::name('member')->where('aid', $aid)->where('id', $result)->field('id,nickname,tel')->find();
                    $finalJiedianName = $finalJiedian ? ($finalJiedian['nickname'] ?: ($finalJiedian['tel'] ?: 'ID:'.$result)) : 'ID:'.$result;
                    $slideReason = '大小区滑落-降级为正常滑落：推荐人接点下级已满'.$sethualuocount.'人，滑落到'.$finalJiedianName.'下面';
                }
                return $result ?: $pid;
            }
        }
        
        return $pid;
    }

    /**
     * 基于人数阈值的滑落处理
     */
    private static function processNumberBasedSlide($aid, $pid, $config, $newUserId = 0, &$slideReason = null)
    {
        $sethualuocount = $config['hualuo'];
        
        if($sethualuocount <= 0) {
            return $pid;
        }

        // 获取推荐人的现有接点人数（不包括新注册用户）
        $query = Db::name('member')->where('aid',$aid)->where('jiedianpid',$pid);
        if($newUserId > 0) {
            $query->where('id', '<>', $newUserId);
        }
        $existingDirectCount = $query->count();
        
        if($existingDirectCount >= $sethualuocount) {
            return $pid;
        } else {
            $result = self::processOriginalSlideLogic($aid, $newUserId, $pid, $config, $slideReason);
            return $result;
        }
    }

    /**
     * 基于业绩阈值的滑落处理
     */
    private static function processPerformanceBasedSlide($aid, $pid, $config, $newUserId = 0, &$slideReason = null)
    {
        $timestamp = date('Y-m-d H:i:s');
        $performanceThreshold = isset($config['hualuo_performance']) ? floatval($config['hualuo_performance']) : 560000;
        
        Log::write($timestamp.'-INFO-[Member][processPerformanceBasedSlide] 推荐人ID='.$pid.'，业绩阈值='.$performanceThreshold.'，新用户ID='.$newUserId);
        
        // 使用新的业绩检查方法：检查推荐人现有线路的业绩（不包括新注册用户）
        try {
            $isQualified = \app\common\PerformanceCacheService::checkExistingLinesPerformance($pid, $performanceThreshold, $aid, $newUserId);
            
            if($isQualified) {
                Log::write($timestamp.'-INFO-[Member][processPerformanceBasedSlide] 推荐人现有线路业绩都达标，新用户直接接在推荐人下面');
                return $pid;
            } else {
                Log::write($timestamp.'-INFO-[Member][processPerformanceBasedSlide] 推荐人现有线路业绩不达标，调用原有滑落逻辑');
                return self::processOriginalSlideLogic($aid, $newUserId, $pid, $config, $slideReason);
            }
        } catch (\Exception $e) {
            Log::write($timestamp.'-ERROR-[Member][processPerformanceBasedSlide] 业绩检查异常：'.$e->getMessage().'，降级使用实时计算');
            // 降级处理：使用实时计算
            $calculationType = $config['hualuo_performance_type'] ?? 'turnover'; // 获取计算类型
            return self::checkPerformanceRealtimeForExisting($aid, $pid, $performanceThreshold, $newUserId, $calculationType) ? $pid : self::processOriginalSlideLogic($aid, $newUserId, $pid, $config, $slideReason);
        }
    }

    /**
     * 混合模式滑落处理
     */
    private static function processMixedModeSlide($aid, $pid, $config, $newUserId = 0, &$slideReason = null)
    {
        // 检查人数条件（检查现有接点人数，不包括新注册用户）
        $numberQualified = false;
        $sethualuocount = $config['hualuo'];
        if($sethualuocount > 0) {
            $query = Db::name('member')->where('aid',$aid)->where('jiedianpid',$pid);
            if($newUserId > 0) {
                $query->where('id', '<>', $newUserId);
            }
            $existingDirectCount = $query->count();
            $numberQualified = ($existingDirectCount >= $sethualuocount);
        }
        
        // 检查业绩条件（检查现有接点线路业绩，不包括新注册用户）
        $performanceQualified = false;
        $performanceThreshold = isset($config['hualuo_performance']) ? floatval($config['hualuo_performance']) : 560000;
        try {
            $calculationType = $config['hualuo_performance_type'] ?? 'turnover'; // 获取计算类型
            $performanceQualified = \app\common\PerformanceCacheService::checkExistingLinesPerformance($pid, $performanceThreshold, $aid, $newUserId, $calculationType);
        } catch (\Exception $e) {
                            $calculationType = $config['hualuo_performance_type'] ?? 'turnover'; // 获取计算类型
                $performanceQualified = self::checkPerformanceRealtimeForExisting($aid, $pid, $performanceThreshold, $newUserId, $calculationType);
        }
        
        if($numberQualified && $performanceQualified) {
            return $pid;
        } else {
            return self::processOriginalSlideLogic($aid, $newUserId, $pid, $config, $slideReason);
        }
    }

    /**
     * 查找符合条件的九星接点人
     */
    private static function findQualifiedJiuxingParent($aid, $startPid, $config, $checkType)
    {
        $timestamp = date('Y-m-d H:i:s');
        $currentPid = $startPid;
        $maxDepth = 10; // 最多向上查找10层，避免无限循环
        $depth = 0;
        
        while($depth < $maxDepth) {
            // 获取当前用户的上级
            $parent = Db::name('member')->field('id,pid,levelid')->where('aid',$aid)->where('id',$currentPid)->find();
            if(!$parent || $parent['pid'] <= 0) {
                Log::write($timestamp.'-INFO-[Member][findQualifiedJiuxingParent] 已到达顶级用户，停止查找');
                break;
            }
            
            $currentPid = $parent['pid'];
            $depth++;
            
            // 检查是否为九星用户
            if($parent['levelid'] >= 1 && $parent['levelid'] <= 9) {
                Log::write($timestamp.'-INFO-[Member][findQualifiedJiuxingParent] 找到九星用户ID='.$currentPid.'，等级='.$parent['levelid'].'，开始检查条件');
                
                // 根据检查类型验证条件
                $qualified = false;
                switch($checkType) {
                    case 'number':
                        $directCount = Db::name('member')->where('aid',$aid)->where('pid',$currentPid)->count();
                        $qualified = ($directCount >= $config['hualuo']);
                        break;
                        
                    case 'performance':
                        $performanceThreshold = floatval($config['hualuo_performance']);
                        $calculationType = $config['hualuo_performance_type'] ?? 'turnover'; // 获取计算类型
                        try {
                            $qualified = \app\common\PerformanceCacheService::checkAllLinesPerformance($currentPid, $performanceThreshold, $aid, $calculationType);
                        } catch (\Exception $e) {
                            $qualified = self::checkPerformanceRealtime($aid, $currentPid, $performanceThreshold, $calculationType);
                        }
                        break;
                        
                    case 'mixed':
                        $directCount = Db::name('member')->where('aid',$aid)->where('pid',$currentPid)->count();
                        $numberOk = ($directCount >= $config['hualuo']);
                        
                        $performanceThreshold = floatval($config['hualuo_performance']);
                        $calculationType = $config['hualuo_performance_type'] ?? 'turnover'; // 获取计算类型
                        $performanceOk = false;
                        try {
                            $performanceOk = \app\common\PerformanceCacheService::checkAllLinesPerformance($currentPid, $performanceThreshold, $aid, $calculationType);
                        } catch (\Exception $e) {
                            $performanceOk = self::checkPerformanceRealtime($aid, $currentPid, $performanceThreshold, $calculationType);
                        }
                        
                        $qualified = ($numberOk && $performanceOk);
                        break;
                }
                
                if($qualified) {
                    Log::write($timestamp.'-INFO-[Member][findQualifiedJiuxingParent] 九星用户ID='.$currentPid.'条件达标，作为接点人');
                    return $currentPid;
                }
            }
        }
        
        Log::write($timestamp.'-INFO-[Member][findQualifiedJiuxingParent] 未找到符合条件的九星接点，返回原推荐人');
        return $startPid;
    }

    /**
     * 实时检查业绩（降级方案）
     * 参考ApiAgent.php中的业绩计算方法，保持统一性和准确性
     * @param int $aid 站点ID
     * @param int $mid 用户ID
     * @param float $threshold 业绩阈值
     * @return bool
     */
    private static function checkPerformanceRealtime($aid, $mid, $threshold, $calculationType = 'turnover')
    {
        try {
            // 获取用户的直推下级
            $directMembers = Db::name('member')
                ->where('aid', $aid)
                ->where('pid', $mid)
                ->field('id')
                ->select()
                ->toArray();
            
            if (empty($directMembers)) {
                Log::write('checkPerformanceRealtime用户ID='.$mid.'没有直推下级，无法形成线路');
                return false;
            }
            
            // 获取等级ID列表（与ApiAgent.php保持一致）
            $levelids = Db::name('member_level')->where('aid', $aid)->where('id', '>=', 2)->column('id');
            if (empty($levelids)) {
                $levelids = Db::name('member_level')->where('aid', $aid)->column('id');
            }
            
            // 检查每条线的团队业绩
            foreach ($directMembers as $member) {
                $lineMid = $member['id'];
                
                // 使用与ApiAgent.php相同的团队成员获取方法
                $teamMemberIds = self::getteammids($aid, $lineMid, 999, $levelids);
                $teamMemberIds[] = $lineMid; // 包含线头本人
                
                if (empty($teamMemberIds)) {
                    Log::write('checkPerformanceRealtime用户ID='.$mid.'线头ID='.$lineMid.'没有团队成员');
                    return false;
                }
                
                // 参考ApiAgent.php中的业绩计算方式
                // 1. 计算商品订单业绩
                $yejiwhere = [['status', 'in', '1,2,3']]; // 与ApiAgent.php保持一致
                
                if ($calculationType === 'profit') {
                    // 利润计算：先获取所有记录，然后在PHP中计算
                    $records = Db::name('shop_order_goods')
                        ->where('aid', $aid)
                        ->where('mid', 'in', $teamMemberIds)
                        ->where($yejiwhere)
                        ->select();
                    $teamyeji_shop = 0;
                    foreach ($records as $record) {
                        $profit = $record['real_totalprice'] - ($record['cost_price'] * $record['num']);
                        $teamyeji_shop += $profit;
                    }
                } else {
                    // 流水计算：使用 totalprice 字段（默认）
                    $teamyeji_shop = Db::name('shop_order_goods')
                        ->where('aid', $aid)
                        ->where('mid', 'in', $teamMemberIds)
                        ->where($yejiwhere)
                        ->sum('totalprice');
                }
                
                // 2. 计算会员升级订单业绩
                $levelup_where = [
                    ['aid', '=', $aid],
                    ['mid', 'in', $teamMemberIds],
                    ['status', 'in', '1,2'] // 会员升级订单状态
                ];
                $teamyeji_levelup = Db::name('member_levelup_order')
                    ->where($levelup_where)
                    ->sum('totalprice');
                
                // 该线的总业绩
                $linePerformance = floatval($teamyeji_shop) + floatval($teamyeji_levelup);
                
                // 如果有任何一条线不达标，返回false
                if ($linePerformance < $threshold) {
                    Log::write('checkPerformanceRealtime用户ID='.$mid.'线头ID='.$lineMid.'业绩='.$linePerformance.'(商品:'.$teamyeji_shop.'+升级:'.$teamyeji_levelup.')未达到阈值='.$threshold);
                    return false;
                }
                
                Log::write('checkPerformanceRealtime用户ID='.$mid.'线头ID='.$lineMid.'业绩='.$linePerformance.'达到阈值='.$threshold);
            }
            
            Log::write('checkPerformanceRealtime用户ID='.$mid.'所有'.count($directMembers).'条线路业绩都达到阈值='.$threshold);
            return true;
            
        } catch (\Exception $e) {
            Log::error('checkPerformanceRealtime异常：' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 实时检查现有线路业绩（降级方案，用于新用户注册时）
     * 参考ApiAgent.php中的业绩计算方法，保持统一性和准确性
     * @param int $aid 站点ID
     * @param int $mid 推荐人ID
     * @param float $threshold 业绩阈值
     * @param int $excludeNewUserId 排除的新用户ID
     * @return bool
     */
    private static function checkPerformanceRealtimeForExisting($aid, $mid, $threshold, $excludeNewUserId = 0, $calculationType = 'turnover')
    {
        try {
            // 获取推荐人的现有接点下级（排除新注册用户）
            $query = Db::name('member')
                ->where('aid', $aid)
                ->where('jiedianpid', $mid)
                ->field('id');
            
            if ($excludeNewUserId > 0) {
                $query->where('id', '<>', $excludeNewUserId);
            }
            
            $existingMembers = $query->select()->toArray();
            
            if (empty($existingMembers)) {
                return true;
            }
            
            // 获取等级ID列表（与ApiAgent.php保持一致）
            $levelids = Db::name('member_level')->where('aid', $aid)->where('id', '>=', 2)->column('id');
            if (empty($levelids)) {
                $levelids = Db::name('member_level')->where('aid', $aid)->column('id');
            }
            
            // 检查每条现有接点线路的团队业绩
            foreach ($existingMembers as $i => $member) {
                $lineMid = $member['id'];
                
                // 使用与ApiAgent.php相同的团队成员获取方法
                $teamMemberIds = self::getteammids($aid, $lineMid, 999, $levelids);
                $teamMemberIds[] = $lineMid; // 包含线头本人
                
                if (empty($teamMemberIds)) {
                    return false;
                }
                
                // 参考ApiAgent.php中的业绩计算方式
                // 1. 计算商品订单业绩
                $yejiwhere = [['status', 'in', '1,2,3']]; // 与ApiAgent.php保持一致
                if ($calculationType === 'profit') {
                    // 利润计算：先获取所有记录，然后在PHP中计算
                    $records = Db::name('shop_order_goods')
                        ->where('aid', $aid)
                        ->where('mid', 'in', $teamMemberIds)
                        ->where($yejiwhere)
                        ->select();
                    $teamyeji_shop = 0;
                    foreach ($records as $record) {
                        $profit = $record['real_totalprice'] - ($record['cost_price'] * $record['num']);
                        $teamyeji_shop += $profit;
                    }
                } else {
                    // 流水计算：使用 totalprice 字段（默认）
                    $teamyeji_shop = Db::name('shop_order_goods')
                        ->where('aid', $aid)
                        ->where('mid', 'in', $teamMemberIds)
                        ->where($yejiwhere)
                        ->sum('totalprice');
                }
                
                // 2. 计算会员升级订单业绩
                $levelup_where = [
                    ['aid', '=', $aid],
                    ['mid', 'in', $teamMemberIds],
                    ['status', 'in', '1,2'] // 会员升级订单状态
                ];
                $teamyeji_levelup = Db::name('member_levelup_order')
                    ->where($levelup_where)
                    ->sum('totalprice');
                
                // 该线的总业绩
                $linePerformance = floatval($teamyeji_shop) + floatval($teamyeji_levelup);
                
                // 如果有任何一条现有接点线路不达标，返回false
                if ($linePerformance < $threshold) {
                    return false;
                }
            }
            return true;
            
        } catch (\Exception $e) {
            Log::error('checkPerformanceRealtimeForExisting异常：' . $e->getMessage());
            return false;
        }
    }
    
    
    
  public static function checkBangfuHuala($aid, $pid, $mid) 
{
    $timestamp = date('Y-m-d H:i:s');
    Log::write($timestamp.'-INFO-[Member][checkBangfuHuala_001] 开始检查帮扶模式滑落，推荐人ID='.$pid.'，当前会员ID='.$mid);
    
    // 1. 检查是否开启帮扶模式
    $liandongSet = self::getEnabledLiandongSet($aid, ['liandong_tuoli_log'], [
        'liandong_set' => ['bangfu_status', 'huala_levelids', 'caifu_levelids'],
        'member' => ['tuo1', 'ypid'],
        'liandong_tuoli_log' => ['aid', 'mid', 'ytpid', 'newpid', 'createtime', 'type'],
    ]);
    if (empty($liandongSet) || $liandongSet['bangfu_status'] != 1) {
        return false;
    }
    
    // 2. 获取推荐人信息
    $parent = Db::name('member')->field('id,levelid')->where('aid', $aid)->where('id', $pid)->find();
    if (empty($parent)) {
        return false;
    }
    
    // 3. 检查推荐人是否属于滑落等级(218)
    $hualaLevelids = explode(',', $liandongSet['huala_levelids']);
    $parentLevelid = (string)$parent['levelid'];
    if (!in_array($parentLevelid, $hualaLevelids)) {
        return false;
    }
    
    // 4. 查找推荐人下级中的财富点位等级会员
    $caifuLevelids = explode(',', $liandongSet['caifu_levelids']);
    $downMembers = Db::name('member')->field('id,levelid')->where('aid', $aid)->where('pid', $pid)->select()->toArray();
    
    $caifuMid = 0;
    foreach ($downMembers as $downMember) {
        if ($downMember['id'] == $mid) {
            continue;
        }
        
        $downMemberLevelid = (string)$downMember['levelid'];
        if (in_array($downMemberLevelid, $caifuLevelids)) {
            $caifuMid = $downMember['id'];
            break;
        }
    }
    
    if ($caifuMid == 0) {
        return false;
    }
    
    // 5. 获取当前会员信息(用于更新路径)
    $member = Db::name('member')->field('id,path')->where('aid', $aid)->where('id', $mid)->find();
    
    // 6. 更新会员信息
    $oldPid = $pid;
    $updateData = [
        'pid' => $caifuMid,
        'ypid' => $oldPid,
        'tuo1' => 2
    ];
    
    $updateResult = Db::name('member')->where('aid', $aid)->where('id', $mid)->update($updateData);
    
    // 7. 记录滑落日志
    $logData = [
        'aid' => $aid,
        'mid' => $mid,
        'ytpid' => $oldPid,
        'newpid' => $caifuMid,
        'createtime' => time(),
        'type' => 2
    ];
    
    Db::name('liandong_tuoli_log')->insert($logData);
    
    // 8. 更新路径
    $oldPath = $member['path'];
    $caifuMemberInfo = Db::name('member')->field('path')->where('aid', $aid)->where('id', $caifuMid)->find();
    if ($caifuMemberInfo) {
        $newPath = $caifuMemberInfo['path'] . ',' . $caifuMid;
        Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['path' => $newPath]);
        
        // 更新所有下级的路径
        $downlineMembers = Db::name('member')->field('id,path')->where('aid', $aid)->where('path', 'like', $oldPath . ',' . $mid . '%')->select();
        foreach ($downlineMembers as $downlineMember) {
            $downlineOldPath = $downlineMember['path'];
            $downlineNewPath = str_replace($oldPath . ',' . $mid, $newPath . ',' . $mid, $downlineOldPath);
            Db::name('member')->where('aid', $aid)->where('id', $downlineMember['id'])->update(['path' => $downlineNewPath]);
        }
    }
    
   // Log::write($timestamp.'-INFO-[Member][checkBangfuHuala_002] 会员滑落成功: 会员ID='.$mid.' 原推荐人='.$oldPid.' 新推荐人='.$caifuMid);
    return true;
}


     public static function getjiedianpid($pid,$sethualuocount,$aid)
     {
        // 首先检查当前节点本身是否有空间
        $currentNodeChildCount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$pid)->count();
        if($currentNodeChildCount < $sethualuocount) {
            // 当前节点有空间，直接返回当前节点
            return $pid;
        }
        
        // 当前节点已满，查找下级中有空间的节点
        $memberlister = Db::name('member')->field('id')->where('aid',$aid)->where('jiedianpid',$pid)->select()->toArray();
        
        if(empty($memberlister)) {
            return null;
        }
        
        // 第一轮：查找有空间的直接下级
        foreach($memberlister as $v)
        {
            $membercount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$v['id'])->count(); 
            if($membercount < $sethualuocount)
            {
                 return $v['id'];
            }
        }
        
        // 第二轮：递归查找更深层的节点
        foreach($memberlister as $v)
        {
            $result = self::getjiedianpid($v['id'],$sethualuocount,$aid);
            if($result) {
                return $result;
        }
        }
        
        return null;
     }
     
     /**
      * 固定左边滑落：一直往左边（第一个直接接点下级）滑落
      * @param int $pid 当前节点ID
      * @param int $sethualuocount 滑落数量阈值
      * @param int $aid 站点ID
      * @return int|null 找到的接点人ID，如果找不到返回null
      */
    public static function getjiedianpidLeft($pid,$sethualuocount,$aid)
    {
       // 固定左边模式：沿着“最左边这条链”一直往下找，优先把人接到最底部的左边节点下面
       // 1. 先获取当前节点的直接接点下级，按ID升序排序（左边在前）
       $memberlister = Db::name('member')
           ->field('id')
           ->where('aid',$aid)
           ->where('jiedianpid',$pid)
           ->order('id', 'asc')
           ->select()
           ->toArray();
       
       if(!empty($memberlister)) {
           // 有直接接点下级：先沿着左边这条链一直往下递归
           $leftChildId = $memberlister[0]['id'];
           
           // 1.1 先去左孩子那条链的最底部找位置
           $deepTarget = self::getjiedianpidLeft($leftChildId, $sethualuocount, $aid);
           if($deepTarget) {
               return $deepTarget;
           }
           
           // 1.2 如果整条左链都无法安放（deepTarget 为 null），再看“当前节点自己”还有没有直推名额
           $currentNodeChildCount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$pid)->count();
           if($currentNodeChildCount < $sethualuocount) {
               return $pid;
           }
           
           // 左链以及当前节点都没有位置
           return null;
       } else {
           // 没有任何直接接点下级，当前节点就是这条左链的“最底部”
           $currentNodeChildCount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$pid)->count();
           if($currentNodeChildCount < $sethualuocount) {
               return $pid;
           }
           
           return null;
       }
    }
     
     /**
      * 获取左边滑落路径（用于备注显示）
      * @param int $aid 站点ID
      * @param int $startPid 起始节点ID
      * @param int $endPid 结束节点ID
      * @param int $sethualuocount 滑落数量阈值
      * @return array 滑落路径节点ID数组
      */
     private static function getLeftSlidePath($aid, $startPid, $endPid, $sethualuocount)
     {
        $path = [];
        $currentPid = $startPid;
        $maxDepth = 20; // 防止无限循环
        $depth = 0;
        
        while($currentPid != $endPid && $depth < $maxDepth) {
            // 检查当前节点是否有空间
            $currentNodeChildCount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$currentPid)->count();
            if($currentNodeChildCount < $sethualuocount) {
                // 当前节点有空间，应该在这里
                break;
            }
            
            // 获取当前节点的左边（第一个直接接点下级）
            $leftChild = Db::name('member')
                ->field('id')
                ->where('aid',$aid)
                ->where('jiedianpid',$currentPid)
                ->order('id', 'asc')
                ->find();
            
            if(!$leftChild) {
                break;
            }
            
            $path[] = $leftChild['id'];
            $currentPid = $leftChild['id'];
            $depth++;
        }
        
        // 如果结束节点不在路径中，添加它
        if($endPid != $startPid && !in_array($endPid, $path)) {
            $path[] = $endPid;
        }
        
        return $path;
     }
     
     public static function getjiedianpidgongpaizhi($pid,$sethualuocount,$aid)
     {
        //1.查看是我推荐的第几个人
        $count = Db::name('member')->where('aid',$aid)->where('pid',$pid)->count();
        $beishu = floor($count/$sethualuocount);
        $number = $count-$sethualuocount* $beishu;
        if($number == 0)
        {
            $number = floor($sethualuocount);
        }
        
        $memberlister = Db::name('member')->field('id')->where('aid',$aid)->where('jiedianpid',$pid)->select()->toArray();
        
        if(empty($memberlister) || !isset($memberlister[$number-1])) {
            return $pid;
        }
        
        $member = $memberlister[$number-1];
        
        $membercount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$member['id'])->count(); 
        
        if($membercount < $sethualuocount)
        {
             return $member['id'];
        }
        //获取无限下级第一个
        $jiedianpid = self::getjiedianpidone($member['id'],$sethualuocount,$aid);
        return $jiedianpid ?: $pid;
     }
     public static function  getjiedianpidone($pid,$sethualuocount,$aid)
     {
         // 首先检查当前节点本身是否有空间
         $currentNodeChildCount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$pid)->count();
         if($currentNodeChildCount < $sethualuocount) {
             return $pid;
         }
         
         $memberlister = Db::name('member')->field('id')->where('aid',$aid)->where('jiedianpid',$pid)->select()->first();
         
         if(empty($memberlister)) {
             return null;
         }
         
         $membercount = Db::name('member')->where('aid',$aid)->where('jiedianpid',$memberlister['id'])->count(); 
         
         if($membercount < $sethualuocount)
         {
             return $memberlister['id'];
         }else{
              return self::getjiedianpidone($memberlister['id'],$sethualuocount,$aid);
         }
     }
     
     public static function getjieidanpid($aid,$mid,$totalprice,$xingji,$orderid,$oldxingji)
     {
        $jiuxingsetinfo = self::tableExistsStatic('jifechi') ? Db::name('jifechi')->where('aid',$aid)->find() : null;
        $jiuxingsetinfo = !empty($jiuxingsetinfo['jiuxing']) ? json_decode($jiuxingsetinfo['jiuxing'],1) : [];
        if(($jiuxingsetinfo['jiuxing_status'] ?? 0) == 1)
        {
            if ($xingji >0) {
                if($xingji == 1)
                {
                     //如果下单人星级是1 那就直接给一级上级完全返
                    $xiadan_member =  Db::name('member')->field('id,jiedianpid')->where('aid',$aid)->where('id',$mid)->find(); 
                    $parentid = $xiadan_member['jiedianpid'];
                    if($parentid > 0)
                    {
                        //   && $parent_member['xingji'] >= $xingji)
                        $parent_member =  Db::name('member')->field('id,jiedianpid,xingji')->where('aid',$aid)->where('id',$parentid)->find(); 
                        if(!empty($parent_member)){
                            $jiangli = $totalprice*$jiuxingsetinfo['jiangli']*0.01;
                            if($parent_member['xingji'] >0){
                                //打钱进佣金
                                Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parentid,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>0,'type'=>'shop','commission'=>$jiangli,'remark'=>'九星奖励下一级消费'.$totalprice.'元,奖励'.$jiangli,'createtime'=>time()]);
                            }else{
                                $data=['aid'=>$aid,'mid'=>$parentid,'formid'=>$mid,'money'=>$jiangli,'afterxingji'=>$xingji,'xingji'=>$oldxingji,'createtime'=>time()];
                                Db::name('member_xingjilog')->insert($data);
                                
                            }
                        }
                    }
                }elseif($xingji >=2 && $xingji <=9)
                {
                    //如果下单人星级不是1
                    $iskou = 1;
                    $jiangli = $jiuxingsetinfo['xiaofeimoney']*$jiuxingsetinfo['jiangli']*0.01;
                    $xingcha = $xingji - $oldxingji;
                    if($xingcha != 1){
                        //多级返
                        if($oldxingji == 0)
                        {
                            $memberlist =  self::getdownjieidanparent($aid,$mid,1,[],$xingji);
                        }else{
                            $memberlist =  self::getdownjieidanparent($aid,$mid,1,[],$xingji,[],$oldxingji);
                        }
                        foreach($memberlist as $key=>$parentid)
                        {
                              $num = 0;
                              $jiangli = $jiuxingsetinfo['xiaofeimoney']*$jiuxingsetinfo['jiangli']*0.01;
                              $parent_member =  Db::name('member')->field('id,nickname,jiedianpid,xingji')->where('aid',$aid)->where('id',$parentid)->find(); 
                              if(!empty($parent_member))
                              {
                                  if($oldxingji !=0)
                                  {
                                     $num = $key+$oldxingji+1;
                                  }else
                                  {
                                      $num = $key+1;
                                  }
                                    if($parent_member['xingji'] >0 && $parent_member['xingji'] >= $num){
                                        
                                        $membercount = Db::name('member')->where('aid',$aid)->where('pid',$parentid)->where('xingji','>=',1)->count();
                                       // var_dump($membercount);
                                        if($num == 1)
                                        {
                                            
                                        }else{
                                            if($membercount < $jiuxingsetinfo['meizhitui'])
                                            {
                                                $jiangli = $jiangli*$jiuxingsetinfo['zhekou']*0.1;
                                            }
                                        }
                                        // var_dump($jiangli);
                                        Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parentid,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>0,'type'=>'shop','commission'=>$jiangli,'remark'=>'九星奖励会员消费'.$totalprice.'元,奖励'.$jiangli,'createtime'=>time()]);
                                    }else{
                                        $data=['aid'=>$aid,'mid'=>$parentid,'formid'=>$mid,'money'=>$jiangli,'afterxingji'=>$xingji,'xingji'=>$oldxingji,'createtime'=>time()];
                                        Db::name('member_xingjilog')->insert($data);
                                        
                                    }
                              }
                              
                        }
                    }else{
                        $memberlist =  self::getdownjieidanparent($aid,$mid,1,[],$xingji);
                        $key= $xingji -1;
                        if (isset($memberlist[$key])) {
                            //  && $parent_member['xingji'] >= $xingji
                            $parentid = $memberlist[$key];
                             $parent_member =  Db::name('member')->field('id,jiedianpid,xingji')->where('aid',$aid)->where('id',$parentid)->find(); 
                             if(!empty($parent_member)){
                                  if($parent_member['xingji'] >0 && $parent_member['xingji'] >= $xingji){
                                      $membercount = Db::name('member')->where('aid',$aid)->where('pid',$parentid)->where('xingji','>=',1)->count();

                                      if($membercount < $jiuxingsetinfo['meizhitui']){
                                          $jiangli = $jiangli*$jiuxingsetinfo['zhekou']*0.1;
                                      }
                                    Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parentid,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>0,'type'=>'shop','commission'=>$jiangli,'remark'=>'九星奖励会员消费'.$totalprice.'元,奖励'.$jiangli,'createtime'=>time()]);
                                  }else{
                                        $data=['aid'=>$aid,'mid'=>$parentid,'formid'=>$mid,'money'=>$jiangli,'afterxingji'=>$xingji,'xingji'=>$oldxingji,'createtime'=>time()];
                                        Db::name('member_xingjilog')->insert($data);
                                        
                                    }
                             }
                        }
                    }
                }
            }
        }
     }
     
    //  $xingji
     public static function getdownjieidanparent($aid,$mid,$deeplevel,$leveldata,$zuidacount=9,$parent=[],$oldxingji=0){
        $curmember =  Db::name('member')->field('id,jiedianpid')->where('aid',$aid)->where('id',$mid)->find(); 
        if($deeplevel > $zuidacount){
            return $parent;
        }else{
            $memberparent = Db::name('member')->field('id,nickname,headimg,pid,realname,tel,levelid')->where('aid',$aid)->where('id',$curmember['jiedianpid'])->find();
            if($oldxingji ==0){
               $parent[]=$memberparent['id'];
            }elseif($oldxingji > 0 && $deeplevel > $oldxingji){
                $parent[]=$memberparent['id'];
            }
            $deeplevel++;
            return self::getdownjieidanparent($aid,$memberparent['id'],$deeplevel,$leveldata,$zuidacount,$parent,$oldxingji);
        }
    }
    
    
     public static function getdownxiaji($mid,$deeplevel,$leveldata,$xingji,$aid,$str=''){
        if($deeplevel > $xingji) return [];
        $deeplevel++;
        $memberlist = Db::name('member')->field('id')->where('aid',$aid)->where('jiedianpid',$mid)->select()->toArray();
        foreach($memberlist as $k=>$member){
            $memberlist[$k]['children'] = self::getdownxiaji($member['id'],$deeplevel,$leveldata,$xingji,$aid,$str);
//          $memberlist[$k]['downcount'] = count($memberlist[$k]['children']);
//          $memberlist[$k]['name'] = $member['nickname'] . "\r\n" . $leveldata[$member['levelid']]['name'].'(ID:'.$member['id'].' 下级:'.$memberlist[$k]['downcount'].'人)';
        
        }
//      if($this->deeplevel < $deeplevel) $this->deeplevel = $deeplevel;
        return $memberlist;
    }
    
    public $deeplevel = 0;
    
    public static function getdownxiaji22($mid,$aid){
        $downcount = 0;
        $memberlist = self::getdownxiajicount22($mid,$aid,0);
        $memberlist = self::array_flatten($memberlist);
        return count($memberlist);
    }
    public static function array_flatten($array){
        $result=array();
        array_walk_recursive($array,function($value)use(&$result){
            if($value)
            {
                array_push($result,$value);
            }
            
            });
            return $result;
        }
    public static function getdownxiajicount22($mid,$aid,$deeplevel,$downcount = [])
    {
        $downcount = [];
        if($deeplevel > 9) return json([]);
        $deeplevel++;
        $memberlist = Db::name('member')->field('id')->where('aid',$aid)->where('jiedianpid',$mid)->select()->toArray();
        foreach($memberlist as $k=>$member){
            $children =  self::getdownxiajicount22($member['id'],$aid,$deeplevel,$downcount);
            $memberlist[$k]['children'] = $children;
//          $memberlist[$k]['downcount'] = count($children);
        }
        return $memberlist;
            
    }
    /**
     * 得到云库存是否开启
     * */
	 public static function getyunkucun($aid)
	 {
	     $returnsttaus = 0;
	    if(!self::tableExistsStatic('yunkucun_sysset')) {
	        return $returnsttaus;
	    }
	        $ykcrs = Db::name('yunkucun_sysset')->where('aid',$aid)->find();
	        if(!empty($ykcrs))
	        {
	            if($ykcrs['status'] == 1)
            {
                $returnsttaus = 1;
            }
        }
        return $returnsttaus;
     }
    /**
     * 判断是否是云库存商品
     * */
	 public static function isyunproduct($aid,$proid)
	 {
	    if(!self::tableExistsStatic('yunkucun_shop_product')) {
	        return false;
	    }
	        $ysp = Db::name('yunkucun_shop_product')->where('proid',$proid)->where('aid',$aid)->find();
	        if(!empty($ysp))
	        {
	            return true;
        }else{
            return false;
        }
     }
     /**
      * 判断我的上级云库存够不够我下单
      * */
	    public static function isshnagjiyunkuncun($aid,$proid,$ggid,$num,$pid,$kuncuntype=0)
	    {
	        if(!self::tableExistsStatic('yunkucun_users')) {
	            return ['status'=>1,'msg'=>'成功!'];
	        }
	        $numArr = Db::name('yunkucun_users')->where('aid',$aid)->where('mid',$pid)->where('proid',$proid)->where('ggid',$ggid)->find();
	        $product = Db::name('shop_product')->where('aid',$aid)->where('id',$proid)->find();
	        $guige = Db::name('shop_guige')->where('aid',$aid)->where('id',$ggid)->find();
        if($kuncuntype == 1)
        {
            if($product['kuncun_type'] == 1)
            {
                return ['status'=>1,'msg'=>'成功!'];
            }else{
              if(empty($numArr))
                {
                    return ['status'=>0,'msg'=>'商品:'.$product['name'].',规格:'.$guige['name'].',上级云库存不足,请联系代理补货后下单'];
                }else{
                    if($numArr['num'] < $num)
                    {
                         return ['status'=>0,'msg'=>'商品:'.$product['name'].',规格:'.$guige['name'].',上级云库存不足,请联系代理补货后下单'];
                    }else{
                         return ['status'=>1,'msg'=>'成功!'];
                    }
                }   
            }
        }else{
           if(empty($numArr))
            {
                return ['status'=>0,'msg'=>'商品:'.$product['name'].',规格:'.$guige['name'].',上级云库存不足,请联系代理补货后下单'];
            }else{
                if($numArr['num'] < $num)
                {
                     return ['status'=>0,'msg'=>'商品:'.$product['name'].',规格:'.$guige['name'].',上级云库存不足,请联系代理补货后下单'];
                }else{
                     return ['status'=>1,'msg'=>'成功!'];
                }
            } 
        }
    }
    //修改我的云库存
	    public static function yunkucunlog($aid,$mid,$proid,$ggid,$num,$ordergoods,$remark='')
	    {
	        if(
	            !self::tableExistsStatic('yunkucun_shop_product')
	            || !self::tableExistsStatic('yunkucun_users')
	            || !self::tableExistsStatic('yunkucun_memberlog')
	        ) {
	            return ['status'=>0,'msg'=>'操作失败'];
	        }
	        $ysp = Db::name('yunkucun_shop_product')->where('proid',$proid)->where('aid',$aid)->where('bid',$ordergoods['bid'])->find();
	        if(!empty($ysp)){
	            if($num==0) return ;
	            $numArr = Db::name('yunkucun_users')->where('aid',$aid)->where('mid',$mid)->where('proid',$proid)->where('ggid',$ggid)->find();
            $prodata = Db::name('shop_product')->where('id',$proid)->where('aid',$aid)->field('kuncun_type')->find();
            $kuncun_type = 0;
            if(!empty($prodata) && $prodata['kuncun_type'] ==1)
            {
                $kuncun_type = 1;
            }
            if(!empty($numArr))
            {
                if($num <  0)
                {
                    //减去5件 我就3件
                    if(abs($num) > $numArr['num'])
                    {
                        return ['status'=>0,'msg'=>'上级云库存不足'];
                    }else{
                       $after=$numArr['num']+$num;
                    }
                }else{
                    $after=$numArr['num']+$num;
                }
                Db::name('yunkucun_users')->where('aid',$aid)->where('id',$numArr['id'])->update(['num'=>$after,'kuncun_type'=>$kuncun_type]);
            }else{
                if($num <= 0)
                {
                    return ['status'=>0,'msg'=>'上级云库存不足'];
                }else{
                    $after = $num;
                    $insertgoods  =[
                        'aid'=>$ordergoods['aid'],
                        'bid'=>$ordergoods['bid'],
                        'mid'=>$mid,
                        'proid'=>$ordergoods['proid'],
                        'ggid'=>$ordergoods['ggid'],
                        'ggname'=>$ordergoods['ggname'],
                        'proname'=>$ordergoods['name'],
                        'pic'=>$ordergoods['pic'],
                        'procode'=>$ordergoods['procode'],
                        'num'=>$ordergoods['num'],
                        'updatetime'=>time(),
                        'kuncun_type'=>$kuncun_type,
                        ];
                     Db::name('yunkucun_users')->insert($insertgoods);
                }
            }
            $data = [];
            $data['aid'] = $aid;
            $data['bid'] = $ordergoods['bid'];
            $data['mid'] = $mid;
            $data['proid'] = $ordergoods['proid'];
            $data['proname'] = $ordergoods['name'];
            $data['ggid'] = $ordergoods['ggid'];
            $data['ggname'] = $ordergoods['ggname'];
            $data['num'] = $num;
            $data['after'] = $after;
            $data['createtime'] = time();
            $data['remark'] = $remark;
            $data['kuncun_type'] = $kuncun_type;
            Db::name('yunkucun_memberlog')->insert($data);
            return ['status'=>1,'msg'=>''];
        }else{
            return ['status'=>0,'msg'=>'操作失败'];
        }
    }
        
//          var_dump($nahuoArr);die;
//  }
    public static function shangjiyunkucunnahuoxunhuan($aid,$path,$buydata,$curmemberlevel,$mid,$nahuoArr = [],$kuncuntype=0)
    {
        if($curmemberlevel['yun_nahuo'] == '0'){
            $nahuoid = 0;
            return ['nahuoid'=>$nahuoid,'nahuoArr'=>$nahuoArr];
        }
        $yun_nahuoArr = $curmemberlevel['yun_nahuo'];
        $yun_nahuoArr = explode(',',$yun_nahuoArr);
     
         foreach($path as $v)
        {
          //  var_dump($v);
            $curmember = Db::name('member')->where('aid',$aid)->where('id',$v)->find();
            $curmemberlevel =  Db::name('member_level')->where('aid',$aid)->where('id',$curmember['levelid'])->find();
           
            if(in_array($curmember['levelid'],$yun_nahuoArr) !== false)
            {
                $nahuoArr[] = $v;
                $bool = self::isshnagjiyunkuncun3($aid,$buydata,$v,$kuncuntype);
                if($bool)
                {
                    $nahuoid = $v;
                    return ['nahuoid'=>$nahuoid,'nahuoArr'=>$nahuoArr];
                }else{
                    if($curmember['pid'] != 0)
                    {
                         $curmemberpath = explode(',',$curmember['path']);
                            $curmemberpath = array_reverse($curmemberpath);
                         return self::shangjiyunkucunnahuoxunhuan($aid,$curmemberpath,$buydata,$curmemberlevel,$curmember['id'],$nahuoArr);
                    }else{
                        $nahuoid = 0;
                        return ['nahuoid'=>0,'nahuoArr'=>$nahuoArr];
                    }
                }
            }
        }
         return ['nahuoid'=>0,'nahuoArr'=>$nahuoArr];
    }
    /**
     * 走货开启
     * */
     
     public static function isshnagjiyunkuncun2($aid,$path,$buydata,$curmemberlevel,$mid,$nahuoArr=[],$kuncuntype=0)
     {
        $nahuoid = 0;
        $pathArr = explode(',',$path); //7,8,9
        $pathArr = array_reverse($pathArr);
        if($curmemberlevel['yun_nahuo'] == '0'){
            $nahuoid = 0;
        }else{
           $nahuoArrzong = self::shangjiyunkucunnahuoxunhuan($aid,$pathArr,$buydata,$curmemberlevel,$mid, [],$kuncuntype);
        }
        $nahuoid = $nahuoArrzong['nahuoid'];
    //  var_dump($nahuoid);die;
        $nahuoArr = $nahuoArrzong['nahuoArr'];
       $moneyArr2 = self::isshnagjiyunkuncun4($nahuoid,$nahuoArr,$buydata,$aid,$mid,$kuncuntype);
       return ['nahuoid'=>$nahuoid,'nahuoArr'=>$moneyArr2];
     }
     
     /**
      * 走货开启3循环计算每人佣金
      * */
      public static function isshnagjiyunkuncun4($nahuoid,$nahuoArr,$buydata,$aid,$mid,$kuncuntype=0)
      {
           array_unshift($nahuoArr,$mid);
           $moneyArr = $moneyArr2 = [];
           foreach ($nahuoArr as $k=>$v)
           {
              $moneyArr[$k] = self::isshnagjiyunkuncun5($buydata,$v,$aid,$kuncuntype);
           }
           foreach($nahuoArr as $k=>$v)
           {
              $num = $k+1;
              if($nahuoid == $v)
              {
                  $moneyArr2[$nahuoArr[$k]] = $moneyArr[$k-1];
              }else{
                  $moneyArr2[$nahuoArr[$num]] = $moneyArr[$k] - $moneyArr[$num];
              }
            }
            return $moneyArr2;
          
      }
      /**
       * 走货开启4循环计算每人佣金2
       * */
       public static function isshnagjiyunkuncun5($buydata,$nahuoidv,$aid,$kuncuntype=0)
       {
             $count1 = 0;
             foreach ($buydata as $data)
             {
                if($data['prodata']){
                    $prodata = explode('-',$data['prodata']);
                }else{
                    return $this->json(['status'=>0,'msg'=>'产品数据错误']);
                }
                foreach($prodata as $key=>$pro)
                {
                    $sdata = explode(',',$pro); //32,52,2/测试规格56
                    $product  = Db::name('shop_product')->where('aid',$aid)->where('id',$sdata[0])->find();
                    $guige = Db::name('shop_guige')->where('aid',$aid)->where('id',$sdata[1])->find();
                    if(!empty($guige['lvprice_data']))
                    {
                         $nahuomember = Db::name('member')->where('aid',$aid)->where('id',$nahuoidv)->find();
                         $guigeArr = json_decode($guige['lvprice_data'],1);
                         if($kuncuntype == 1 )
                         {
                             if($product['kuncun_type'] == 1)
                            {
                                $count1 =  $count1+ $guigeArr[$nahuomember['levelid']] *$sdata[2];
                            }
                         }else{
                            $count1 =  $count1+ $guigeArr[$nahuomember['levelid']] *$sdata[2];
                         }
                    }else{
                        if($kuncuntype == 1 )
                        {
                            if($product['kuncun_type'] == 1)
                            {
                                $count1 =  $count1+ $guige['sell_price']*$sdata[2];
                            }
                            
                        }else{
                            $count1 =  $count1+ $guige['sell_price']*$sdata[2];
                        }
                    }
                }
             }
             return $count1;
       }
      
     /**
      * 走货开启2循环
      * */
     public static function isshnagjiyunkuncun3($aid,$buydata,$v,$kuncuntype=0)
     {
         //var_dump('判断人'.$v);
         
         $returnbool = false;
         foreach ($buydata as $data)
         {
            if($data['prodata']){
                $prodata = explode('-',$data['prodata']);
            }else{
                return $this->json(['status'=>0,'msg'=>'产品数据错误']);
            }
            foreach($prodata as $key=>$pro)
            {
                $sdata = explode(',',$pro); 
                $yunbool = \app\admin\common\Member::isshnagjiyunkuncun($aid,$sdata[0],$sdata[1],$sdata[2],$v,$kuncuntype);
                //var_dump('当前商品'.$sdata[0]);
                //var_dump($yunbool);
                $productgood = Db::name('shop_product')->where('id',$sdata[0])->find();
                if($productgood['kuncun_type'] != 1)
{
                if($yunbool['status'] == 1)
                {
                    $returnbool=true;
                }else{
                    $returnbool = false;
                    break;
                }
            }
            }
         }
         return $returnbool;
     }
     /**
      * 扣组合库存
      * */
      public static function kouzuhekuucn($product,$num,$aid)
      {
        if(!empty($product['dddata']))
        {
            $dddata_new = [];
            $dddata = json_decode($product['dddata'],1);
            foreach ($dddata as $kgoods=>$vgoods)
            {
                $num = $vgoods['num']*$num;
                Db::name('shop_guige')->where('aid',$aid)->where('id',$vgoods['ggid'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
                Db::name('shop_product')->where('aid',$aid)->where('id',$kgoods)->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
            }
        }
      }
      
      /**
       * 扣组合上级库存2
       * */
      public function kouzuhekuucn2($product,$num,$aid,$mid)
      {
        if(!empty($product['dddata']))
        {
            $dddata = json_decode($product['dddata'],1);
            foreach ($dddata as $kgoods=>$vgoods)
            {
                $productgood = Db::name('shop_product')->where('id',$kgoods)->find();
                $gg = Db::name('shop_guige')->where('id',$vgoods['ggid'])->find();
                $ordergoods2 =[
                    'aid'=>$aid,
                    'bid'=>0,
                    'mid'=>$mid,
                    'proid'=>$kgoods,
                    'ggid'=>$vgoods['ggid'],
                    'ggname'=>$gg['name'],
                    'name'=>$productgood['name'],
                    'pic'=>$productgood['pic'],
                    'procode'=>'',
                    'num'=>$vgoods['num']*$num
                    ];
                    $num1 = $vgoods['num']*$num;
                \app\admin\common\Member::yunkucunlog($aid,$mid,$kgoods,$vgoods['ggid'],'-'.$num1,$ordergoods2,'下级下单组合商品,扣除');
            }
        }
      }
      
      /**
       * 云库存给培养奖励
       * */
      public function givepeiyang($aid,$ordertotalprice,$mid,$orderid,$ylevelid,$b=0)
      {
           $curmember = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
           $order = Db::name('shop_order')->where('id',$orderid)->find();
           if($curmember['suo1'] == 0)
           {
               $suo1 = 0;
           }else{
               $suo1 = $curmember['suo1'];
           }
           if($curmember['suo2'] == 0)
           {
               $suo2 = 0;
           }else{
               $suo2 = $curmember['suo2'];
           }
           $curlevelid = $curmember['levelid'];
           $nowlv = Db::name('member_level')->where('aid',$aid)->where('id',$ylevelid)->find();
           $giveid1 = 0;
           $giveid2 = 0;
           $path = $curmember['path'];
           $pathArr = explode(',',$path);
           $pathArr = array_reverse($pathArr);
           foreach ($pathArr as $v)
           {
                $parentmember = Db::name('member')->where('aid',$aid)->where('id',$v)->find();
                if($suo1 == 0){
                    if($nowlv['peiyang_levelid1'] >0){
                        $jiangli_levelArr1= explode(',',$nowlv['peiyang_levelid1']);
                        if(in_array($parentmember['levelid'],$jiangli_levelArr1) !== false && $giveid1 == 0)
                        {
                            $giveid1 = $v;
                             if($giveid1 && $giveid2){
                                break;
                            }
                        }
                    }
                }
                if($suo2 == 0){
                    if($nowlv['peiyang_levelid2'] >0)
                    {
                         $jiangli_levelArr2= explode(',',$nowlv['peiyang_levelid2']);
                         if(in_array($parentmember['levelid'],$jiangli_levelArr2) !== false  && $giveid2 == 0)
                        {
                            $giveid2 = $v;
                             if($giveid1 && $giveid2){
                                break;
                            }
                        }
                    }
                }
           }
           if($suo1 !=0)
           {
                $jiangli1 = $ordertotalprice*$curmember['suo1jiang']*0.01;
                if($jiangli1 >0 )
                {
                   $str = '';
                   if($b == 1)
                   {
                       $str = '补货-';
                   }
                    Db::name('member_commission_record')->insert(['jl_type'=>1,'aid'=>$aid,'mid'=>$suo1,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>0,'type'=>'shop','commission'=>$jiangli1,'remark'=>'用户'.$mid.'-'.$str.'云库存培养奖励1,订单编号:'.$order['ordernum'],'createtime'=>time()]);
                }
           }elseif($giveid1 != 0){
               $jiangli1 = $ordertotalprice*$nowlv['peiyang_jiangli1']*0.01;
               if($giveid1 >0 && $jiangli1 >0 )
               {
                   $str = '';
                   if($b == 1)
                   {
                       $str = '补货-';
                   }
                    Db::name('member_commission_record')->insert(['jl_type'=>1,'aid'=>$aid,'mid'=>$giveid1,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>0,'type'=>'shop','commission'=>$jiangli1,'remark'=>'用户'.$mid.'-'.$str.'云库存培养奖励1,订单编号:'.$order['ordernum'],'createtime'=>time()]);
               }
           }
          if($suo2 !=0)
           {
                $jiangli2 = $ordertotalprice*$curmember['suo2jiang']*0.01;
                if( $jiangli2 >0 )
                {
                    $str = '';
                   if($b == 1)
                   {
                       $str = '补货-';
                   }
                  Db::name('member_commission_record')->insert(['jl_type'=>1,'aid'=>$aid,'mid'=>$suo2,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>0,'type'=>'shop','commission'=>$jiangli2,'remark'=>'用户'.$mid.'-'.$str.'云库存培养奖励2,订单编号:'.$order['ordernum'],'createtime'=>time()]);
                }
           }elseif($giveid2 != 0){
               $jiangli2 = $ordertotalprice*$nowlv['peiyang_jiangli2']*0.01;
               if($giveid2 >0 && $jiangli2 >0 )
               {
                   $str = '';
                   if($b == 1)
                   {
                       $str = '补货-';
                   }
                  Db::name('member_commission_record')->insert(['jl_type'=>1,'aid'=>$aid,'mid'=>$giveid2,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>0,'type'=>'shop','commission'=>$jiangli2,'remark'=>'用户'.$mid.'-'.$str.'云库存培养奖励2,订单编号:'.$order['ordernum'],'createtime'=>time()]);
               }
          }

      }
      
      public static function givepeiyang2($aid,$ordertotalprice,$mid,$orderid,$ylevelid,$b=0)
      {
           $giveid1 = 0;
           $giveid2 = 0;
           $jiangli1 = 0;
           $jiangli2 = 0;
           $curmember = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
           if($curmember['suo1'] == 0)
           {
               $suo1 = 0;
           }else{
               $suo1 = $curmember['suo1'];
           }
           if($curmember['suo2'] == 0)
           {
               $suo2 = 0;
           }else{
               $suo2 = $curmember['suo2'];
           }
           $curlevelid = $curmember['levelid'];
           $nowlv = Db::name('member_level')->where('aid',$aid)->where('id',$ylevelid)->find();
           $path = $curmember['path'];
           $pathArr = explode(',',$path);
           $pathArr = array_reverse($pathArr);
           foreach ($pathArr as $v)
           {
                $parentmember = Db::name('member')->where('aid',$aid)->where('id',$v)->find();
                 if($suo1 == 0){
                    if($nowlv['peiyang_levelid1'] >0){
                        $jiangli_levelArr1= explode(',',$nowlv['peiyang_levelid1']);
                        if(in_array($parentmember['levelid'],$jiangli_levelArr1) !== false && $giveid1 == 0)
                        {
                            $giveid1 = $v;
                            if($giveid1 && $giveid2){
                                break;
                            }
                            
                        }
                    }
                 }
                 if($suo2 == 0){
                    if($nowlv['peiyang_levelid2'] >0)
                    {
                         $jiangli_levelArr2= explode(',',$nowlv['peiyang_levelid2']);
                         if(in_array($parentmember['levelid'],$jiangli_levelArr2) !== false  && $giveid2 == 0)
                        {
                            $giveid2 = $v;
                           if($giveid1 && $giveid2){
                                break;
                            }
                        }
                    }
                  }
           }
           if($suo1 != 0){
               $jiangli1 = $ordertotalprice*$curmember['suo1jiang']*0.01;
           }elseif($giveid1 != 0){
               $jiangli1 = $ordertotalprice*$nowlv['peiyang_jiangli1']*0.01;
          }
          if($suo2 != 0){
                $jiangli2 = $ordertotalprice*$curmember['suo2jiang']*0.01;
          }elseif($giveid2 != 0){
               $jiangli2 = $ordertotalprice*$nowlv['peiyang_jiangli2']*0.01;
          }
           return $jiangli1+$jiangli2;
      }
      
//    获取最近上级等级允许分销的id

     public static function getparentid($aid,$mid)
     {
        $curmember = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
        $pathstr = $curmember['path'];
        $pathArr = explode(',',$pathstr);
        $pathArr = array_reverse($pathArr);
        foreach ($pathArr as $v)
        {
             $parentmember = Db::name('member')->where('aid',$aid)->where('id',$v)->find();
             $parentmember_level = Db::name('member_level')->where('aid',$aid)->where('id',$parentmember['levelid'])->find();
             if($parentmember_level['can_agent'] == 0)
             {
                 if($parentmember_level['fxzhuli'] ==0)
                 {
                     return [];
                 }
             }else{
                 return $parentmember;
             }
        }
     }
     
     /**
      * 直推奖抽佣
      * */
      public static function getzhituichouyong($aid,$mid,$yongjin,$orderid,$ogid,$ordernum)
      {
         //  \think\facade\Log::write('【直推奖抽佣】开始: aid='.$aid.', mid='.$mid.', yongjin='.$yongjin, 'info');
           $jaingli1 = 0;
           $jaingli2 = 0;
           $curmember = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
         //  \think\facade\Log::write('【直推奖抽佣】当前用户: '.json_encode($curmember, JSON_UNESCAPED_UNICODE), 'info');
           
           $curmember_level = Db::name('member_level')->where('aid',$aid)->where('id',$curmember['levelid'])->find();
        //   \think\facade\Log::write('【直推奖抽佣】当前用户等级: '.json_encode($curmember_level, JSON_UNESCAPED_UNICODE), 'info');
           
           $parentmember = Db::name('member')->where('aid',$aid)->where('id',$curmember['pid'])->find();
        //   \think\facade\Log::write('【直推奖抽佣】上级用户: '.json_encode($parentmember, JSON_UNESCAPED_UNICODE), 'info');
           
         //  \think\facade\Log::write('【直推奖抽佣】条件判断: zt_sy_yong1='.($curmember_level['zt_sy_yong1'] ?? '未设置').' > 0 && pid='.$curmember['pid'].' > 0', 'info');
           
        //   $parentmember_level = Db::name('member_level')->where('aid',$aid)->where('id',$parentmember['levelid'])->find();
           $zhituiBaseCommission = self::getZhituiCalcBase($aid, $orderid, $ogid, $yongjin);

           if($curmember_level['zt_sy_yong1'] >0 && $curmember['pid'] >0)
           {
          //     \think\facade\Log::write('【直推奖抽佣】条件1匹配: zt_sy_yong1='.$curmember_level['zt_sy_yong1'], 'info');
               if($curmember_level['zt_sy_levelid1'])
               {
                    $jiangli_levelArr1 = explode(',',$curmember_level['zt_sy_levelid1']);
             //       \think\facade\Log::write('【直推奖抽佣】等级限制: '.implode(',', $jiangli_levelArr1).' 上级等级: '.$parentmember['levelid'], 'info');
                    if(in_array($parentmember['levelid'],$jiangli_levelArr1))
                    {
                        //计算奖励
                        $jaingli1 = $zhituiBaseCommission * $curmember_level['zt_sy_yong1'] * 0.01;
                        //\think\facade\Log::write('【直推奖抽佣】等级匹配,计算奖励1: '.$jaingli1, 'info');
                    } else {
                       // \think\facade\Log::write('【直推奖抽佣】等级不匹配，不计算奖励', 'info');
                    }
               }else{
                   //计算奖励
                    $jaingli1 = $zhituiBaseCommission * $curmember_level['zt_sy_yong1'] * 0.01;
                    //\think\facade\Log::write('【直推奖抽佣】无等级限制,计算奖励1: '.$jaingli1, 'info');
               }
               if($orderid == 0)
               {
                    self::addcommission(
                        $aid,
                        $parentmember['id'],
                        $mid,
                        $jaingli1,
                        \app\admin\common\RewardRemark::appendByKey(
                            $aid,
                            'income_zhitui_1',
                            '下级用户:'.$mid.',得到直推佣金奖励抽佣1',
                            [
                                'from_mid' => $mid,
                                'target_mid' => $parentmember['id'],
                                'commission' => round(floatval($jaingli1), 2),
                                'order_sn' => $ordernum,
                            ]
                        )
                    );
                   // \think\facade\Log::write('【直推奖抽佣】添加佣金1完成', 'info');
               }else{
                   Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parentmember['id'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$jaingli1,'score'=>0,'remark'=>\app\admin\common\RewardRemark::appendByKey($aid,'income_zhitui_1','下级用户:'.$mid.',得到直推佣金奖励抽佣1,订单编号:'.$ordernum,['from_mid'=>$mid,'target_mid'=>$parentmember['id'],'commission'=>round(floatval($jaingli1), 2),'order_sn'=>$ordernum]),'createtime'=>time()]);
                   //\think\facade\Log::write('【直推奖抽佣】记录佣金1完成', 'info');
                }
           } else {
                if ($curmember_level['zt_sy_yong1'] <= 0) {
                   // \think\facade\Log::write('【直推奖抽佣】条件1不满足: zt_sy_yong1 不大于0', 'info');
                }
                if ($curmember['pid'] <= 0) {
                   // \think\facade\Log::write('【直推奖抽佣】条件1不满足: 用户无上级', 'info');
                }
           }
           
           if($curmember_level['zt_sy_yong2'] >0 && $curmember['pid'] >0)
           {
               if($curmember_level['zt_sy_levelid2'])
               {
                    $jiangli_levelArr2 = explode(',',$curmember_level['zt_sy_levelid2']);
                    if(in_array($parentmember['levelid'],$jiangli_levelArr2) )
                    {
                        //计算奖励
                        $jaingli2 = $zhituiBaseCommission * $curmember_level['zt_sy_yong2'] * 0.01;
                    }
               }else{
                   $jaingli2 = $zhituiBaseCommission * $curmember_level['zt_sy_yong2'] * 0.01;
               }
                if($orderid == 0)
               {
                    self::addcommission(
                        $aid,
                        $parentmember['id'],
                        $mid,
                        $jaingli2,
                        \app\admin\common\RewardRemark::appendByKey(
                            $aid,
                            'income_zhitui_2',
                            '下级用户:'.$mid.',得到直推佣金奖励抽佣2',
                            [
                                'from_mid' => $mid,
                                'target_mid' => $parentmember['id'],
                                'commission' => round(floatval($jaingli2), 2),
                                'order_sn' => $ordernum,
                            ]
                        )
                    );
               }else{
                   Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$parentmember['id'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$jaingli2,'score'=>0,'remark'=>\app\admin\common\RewardRemark::appendByKey($aid,'income_zhitui_2','下级用户:'.$mid.',得到直推佣金奖励抽佣2,订单编号:'.$ordernum,['from_mid'=>$mid,'target_mid'=>$parentmember['id'],'commission'=>round(floatval($jaingli2), 2),'order_sn'=>$ordernum]),'createtime'=>time()]);
               }
           }
           return true;
      }

      private static function getZhituiCalcBase($aid, $orderid, $ogid, $fallbackCommission)
      {
          $baseCommission = round(floatval($fallbackCommission), 2);
          if($baseCommission <= 0){
              return 0;
          }

          $adminSet = Db::name('admin_set')->where('aid', $aid)->field('zt_sy_calc_mode')->find();
          $calcMode = intval($adminSet['zt_sy_calc_mode'] ?? 0);
          if($calcMode !== 1 || intval($orderid) <= 0 || intval($ogid) <= 0){
              return $baseCommission;
          }

          $orderGoods = Db::name('shop_order_goods')
              ->where('aid', $aid)
              ->where('id', intval($ogid))
              ->field('id,totalprice,real_totalprice,business_total_money,manjian_money,coupon_money,hei_money')
              ->find();
          if(!$orderGoods){
              return $baseCommission;
          }

          $settlementBase = floatval($orderGoods['totalprice'] ?? 0)
              - floatval($orderGoods['manjian_money'] ?? 0)
              - floatval($orderGoods['coupon_money'] ?? 0)
              - floatval($orderGoods['hei_money'] ?? 0);
          if($settlementBase <= 0){
              $settlementBase = floatval($orderGoods['real_totalprice'] ?? 0);
          }

          $businessTotalMoney = floatval($orderGoods['business_total_money'] ?? 0);
          $platformFeeAmount = round($settlementBase - $businessTotalMoney, 2);
          if($platformFeeAmount > 0){
              return $platformFeeAmount;
          }

          return $baseCommission;
      }
      
    /**
   * 间推抽佣
   * */
     public static function getjiantuichouyong($aid,$mid,$yongjin,$orderid,$ogid,$ordernum)
     {
         //\think\facade\Log::write('【间推抽佣】开始: aid='.$aid.', mid='.$mid.', yongjin='.$yongjin, 'info');
         $jaingli1 = 0;
         $curmember = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
         $curmember_level = Db::name('member_level')->where('aid',$aid)->where('id',$curmember['levelid'])->find();
         
        // \think\facade\Log::write('【间推抽佣】用户等级配置: jt_sy_yong='.($curmember_level['jt_sy_yong'] ?? '未设置').', jt_sy_ceng='.($curmember_level['jt_sy_ceng'] ?? '未设置').', jt_sy_levelid='.($curmember_level['jt_sy_levelid'] ?? '未设置'), 'info');
         
         if($curmember_level['jt_sy_yong'] >0  && $curmember['pid'] >0)
         {
           //  \think\facade\Log::write('【间推抽佣】条件匹配: jt_sy_yong='.$curmember_level['jt_sy_yong'].' jt_sy_ceng='.$curmember_level['jt_sy_ceng'], 'info');
             $jiangliArr = [];
             $pathstr = $curmember['path'];
             $pathArr = explode(',',$pathstr);
             $pathArr = array_reverse($pathArr);
             unset($pathArr[0]);
             $countarr = count($pathArr);
            // \think\facade\Log::write('【间推抽佣】路径: '.$pathstr.', 上级数量: '.$countarr, 'info');
             
             if($curmember_level['jt_sy_ceng'] && $curmember_level['jt_sy_ceng']  < $countarr)
             {
                 $length = $countarr -$curmember_level['jt_sy_ceng'];
                 array_splice($pathArr,$curmember_level['jt_sy_ceng'],$length);
                // \think\facade\Log::write('【间推抽佣】应用层级限制,截取后上级数量: '.count($pathArr), 'info');
             }
             foreach($pathArr as $v)
             {
                 $parentmember = Db::name('member')->where('aid',$aid)->where('id',$v)->find();
               //  \think\facade\Log::write('【间推抽佣】处理上级: id='.$v.', levelid='.$parentmember['levelid'], 'info');
                 
                 if($curmember_level['jt_sy_levelid'])
                 {
                     $jiangli_levelArr1 = explode(',',$curmember_level['jt_sy_levelid']);
                     if(in_array($parentmember['levelid'],$jiangli_levelArr1))
                     {
                         //计算奖励
                          $jaingli1 = $yongjin * $curmember_level['jt_sy_yong'] * 0.01;
                         // \think\facade\Log::write('【间推抽佣】等级匹配,计算奖励: '.$jaingli1, 'info');
                     }
                 }else{
                     //计算奖励
                     $jaingli1 = $yongjin * $curmember_level['jt_sy_yong'] * 0.01;
                    // \think\facade\Log::write('【间推抽佣】无等级限制,计算奖励: '.$jaingli1, 'info');
                 }
                 if($jaingli1 >0)
                 {
                    if($orderid == 0)
                    {
                            self::addcommission(
                                $aid,
                                $v,
                                $mid,
                                $jaingli1,
                                \app\admin\common\RewardRemark::appendByKey(
                                    $aid,
                                    'income_jiantui',
                                    '下级用户:'.$mid.',得到佣金奖励,间接抽佣',
                                    [
                                        'from_mid' => $mid,
                                        'target_mid' => $v,
                                        'commission' => round(floatval($jaingli1), 2),
                                        'order_sn' => $ordernum,
                                    ]
                                )
                            );
                           // \think\facade\Log::write('【间推抽佣】添加佣金完成: 上级id='.$v.', 金额='.$jaingli1, 'info');
                    }else{
                              Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$v,'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$jaingli1,'score'=>0,'remark'=>\app\admin\common\RewardRemark::appendByKey($aid,'income_jiantui','用户:'.$mid.'得到佣金奖励,间接抽佣,订单编号:'.$ordernum,['from_mid'=>$mid,'target_mid'=>$v,'commission'=>round(floatval($jaingli1), 2),'order_sn'=>$ordernum]),'createtime'=>time()]);
                            //  \think\facade\Log::write('【间推抽佣】记录佣金完成: 上级id='.$v.', 金额='.$jaingli1, 'info');
                    }
                 }
             }
         }
        // \think\facade\Log::write('【间推抽佣】结束', 'info');
          return true;
     }
     public static function getshangji($aid,$mid,$num,$num1=1)
     {
       //  var_dump('第'.$num.'轮');var_dump($num1);
         $curmember =  Db::name('member')->where('aid',$aid)->where('id',$mid)->field('id,gongpaipid')->find();
         $parentid = $curmember['gongpaipid'];
         if($num1 < $num)
         {
             $num1 = $num1+1;
             $parentid = self::getshangji($aid,$curmember['gongpaipid'],$num,$num1);
             return $parentid;
         }else{
           //  var_dump(444);
             return $parentid;
         }
         
         
     }
     public static function setgongpai($aid,$mid)
     {
         $set = Db::name('admin_set')->where('aid',$aid)->find();
       //  var_dump(1);
         if($set['gongpaizhi_shi'] > 0 && $set['gongpaizhi_ren'] >0 && $set['gongpaizhi_xiaofei'] >0 && $set['gongpaizhi_ren_lun']>0)
         {
           //  var_dump(2);
             $jaingli = 0;
             $manzucount = 0;
             $curmember = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
             if($curmember['pid'] == 0)
             {
                 return;
             }
            //  var_dump('判断的上级'.$curmember['pid']);
             $parentmember = Db::name('member')->where('aid',$aid)->where('id',$curmember['pid'])->find();
            //  var_dump($parentmember['gongpainum']);
             if($parentmember['gongpainum'] == 1)
             {
                //  var_dump('进入大于第2轮的用户id'.$parentmember['id']);
                 return;
             }
             //第一轮 
             $allxiaji = Db::name('member')->where('aid',$aid)->where('pid',$curmember['pid'])->field('id,isxuni')->select()->toArray();//我上级的所有下级
           //  var_dump('我有多少下级'.count($allxiaji));die;
             foreach ($allxiaji as $v)
             {
                 if($manzucount >= $set['gongpaizhi_ren'])
                 {
                     break;
                 }else{
                     $xiajixiaofei = Db::name('shop_order')->where('aid',$aid)->where('mid',$v['id'])->where('status','in','1,2,3')->sum('totalprice');
                     if($xiajixiaofei >=  $set['gongpaizhi_xiaofei'])
                     {
                         $manzucount = $manzucount+1;
                     }
                   //  elseif($v['isxuni'] ==1)
                   //  {
                   //      $manzucount = $manzucount+1;
                   //  }
                 }
             }
           //  var_dump('满足的下级数'.$manzucount);
             if($manzucount >= $set['gongpaizhi_ren'])
             {
                 $gongpaizhi_lunshu = json_decode($set['gongpaizhi_lunshu'],1);
                 if($gongpaizhi_lunshu['gongpaizhi_lunshu'][1] ==1)
                 {
                     //判断消费 满足继续 不满足 返回
                     $gongpaizhi_jiangli_lunshu = json_decode($set['gongpaizhi_jiangli_lunshu'],1);
                     if($gongpaizhi_jiangli_lunshu[1]['xiaofei'] >0)
                     {
                        $mytotalprice = Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->sum('totalprice');
                        if($mytotalprice < $gongpaizhi_jiangli_lunshu[1]['xiaofei'])
                        {
                            return;
                        }
                     }
                     
                     
                 }
               //  var_dump('已达到设置人数');
               //  var_dump('要出去的用户id'.$curmember['pid']);
                 
               //出去 //设置公排状态
               $gongpaipid = self::getgongpaipid($aid,$curmember['pid'],$set);
               //var_dump('要出去用户的上级'.$gongpaipid);die;
               //修改公排上级
               Db::name('member')->where('aid', $aid)->where('id', $curmember['pid'])->update(['gongpaipid'=>$gongpaipid,'gongpainum'=>1,'gongpaitime'=>time()]);
               //用户进入第一轮,然后查看上级是否够了 升级第二轮
               if($gongpaipid > 0 ){
                    //第2轮
                    $gongpaipid2 = self::getdayu2gongpai($aid,$parentmember['id'],$set);
                    // var_dump($gongpaipid2);
                    if($gongpaipid2 > 0)
                    {
                        // //进入第三轮
                        $gongpaipid3 = self::getdayu2gongpai($aid,$gongpaipid2,$set,3);
                        if($gongpaipid3 >0)
                        {
                           $gongpaipid4 = self::getdayu2gongpai($aid,$gongpaipid3,$set,4); 
                           if($gongpaipid4 >0)
                           {
                               $gongpaipid5 = self::getdayu2gongpai($aid,$gongpaipid4,$set,5); 
                               if($gongpaipid5 >0)
                               {
                                   $gongpaipid6 = self::getdayu2gongpai($aid,$gongpaipid5,$set,6); 
                                   if($gongpaipid6 >0)
                                   {
                                       $gongpaipid7 = self::getdayu2gongpai($aid,$gongpaipid6,$set,7);
                                       if($gongpaipid7 >0)
                                       {
                                           $gongpaipid8 = self::getdayu2gongpai($aid,$gongpaipid6,$set,8);
                                       }
                                   }
                               }
                           }
                        }
                     }
               }
               
             }
         }
         return true;
     }
     
     public static function getdayu2gongpai($aid,$mid,$set,$lun=2)
     {
       //  var_dump('第几轮'.$lun);
       //  var_dump('判断用户id'.$mid);
         if($lun == 2)
         {
             $gongpaipid = 'gongpaipid2';
             $gongpainum = 'gongpainum2';
             $gongpaitime = 'gongpaitime2';
             $curmemberpid = 'gongpaipid';
         }elseif($lun == 3)
         {
             $gongpaipid = 'gongpaipid3';
             $gongpainum = 'gongpainum3';
             $gongpaitime = 'gongpaitime3';
             $curmemberpid = 'gongpaipid2';
         }elseif($lun == 4)
         {
             $gongpaipid = 'gongpaipid4';
             $gongpainum = 'gongpainum4';
             $gongpaitime = 'gongpaitime4';
              $curmemberpid = 'gongpaipid3';
         }elseif($lun == 5)
         {
             $gongpaipid = 'gongpaipid5';
             $gongpainum = 'gongpainum5';
             $gongpaitime = 'gongpaitime5';
             $curmemberpid = 'gongpaipid4';
         }elseif($lun == 6)
         {
             $gongpaipid = 'gongpaipid6';
             $gongpainum = 'gongpainum6';
             $gongpaitime = 'gongpaitime6';
              $curmemberpid = 'gongpaipid5';
         }elseif($lun == 7)
         {
             $gongpaipid = 'gongpaipid7';
             $gongpainum = 'gongpainum7';
             $gongpaitime = 'gongpaitime7';
             $curmemberpid = 'gongpaipid6';
         }elseif($lun == 8)
         {
             $gongpaipid = 'gongpaipid8';
             $gongpainum = 'gongpainum8';
             $gongpaitime = 'gongpaitime8';
             $curmemberpid = 'gongpaipid7';
         }
         
         //出来的用户
       //  $parentmember['id'] gongpaipid
        $curmember = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
       // var_dump('要升级的'.$curmember[$curmemberpid]);
        if($curmember[$curmemberpid] > 0)
        {
            $allxiajicount = 0;
            $allxiajicount = Db::name('member')->where('aid',$aid)->where($curmemberpid,$curmember[$curmemberpid])->count();
           // var_dump($allxiajicount);
            $allxiajiArr = Db::name('member')->where('aid',$aid)->where($curmemberpid,$curmember[$curmemberpid])->field('*')->select()->toArray();
            foreach($allxiajiArr as $v)
            {
                $allxiaji1 = 0;
                $allxiaji1 = Db::name('member')->where('aid',$aid)->where($curmemberpid,$v['id'])->count();
               // var_dump($allxiaji1);
                $allxiajicount = $allxiajicount+$allxiaji1;
            }
            $allxiaji = $allxiajicount;
           // var_dump($allxiaji);
           // var_dump($curmember['gongpainum']);die;
            
             $gongpaizhi_ren = $set['gongpaizhi_ren'];
           //  var_dump($allxiajicount);
            if($allxiaji >= $gongpaizhi_ren)
            {
                $gongpaizhi_lunshu = json_decode($set['gongpaizhi_lunshu'],1);
                 if($gongpaizhi_lunshu['gongpaizhi_lunshu'][$lun] ==1)
                 {
                     //判断消费 满足继续 不满足 返回
                     $gongpaizhi_jiangli_lunshu = json_decode($set['gongpaizhi_jiangli_lunshu'],1);
                     if($gongpaizhi_jiangli_lunshu[$lun]['xiaofei'] >0)
                     {
                        $mytotalprice = Db::name('shop_order')->where('aid',$aid)->where('mid',$mid)->where('status','in','1,2,3')->sum('totalprice');
                        if($mytotalprice < $gongpaizhi_jiangli_lunshu[$lun]['xiaofei'])
                        {
                            return 0;
                        }
                     }
                     
                     
                 }
               // var_dump(77777777);
                //大于 进入下一轮
               // 获取第2轮父级id
               $gongpaipid1 = self::getgongpaipid($aid,$curmember[$curmemberpid],$set,$lun);
               //var_dump('进入第2轮的父id'.$gongpaipid1);
               Db::name('member')->where('aid', $aid)->where('id', $curmember[$curmemberpid])->update([$gongpaipid=>$gongpaipid1,$gongpainum=>1,$gongpaitime=>time()]);
               //修改完成后
            //   获取当前轮数所有上级
                $parentArr = \app\admin\common\Member::getdownjieidanparent2($aid,$curmember[$curmemberpid],$gongpaipid);
                $set = Db::name('admin_set')->where('aid',$aid)->find();
                $gongpaizhi_jiangli_lunshu = json_decode($set['gongpaizhi_jiangli_lunshu'],1);
                foreach($parentArr as $k=>$v)
                {
                    if($v)
                    {
                       if($k == 0)
                       {
                           //直推钱
                           \app\admin\common\Member::addcommission($aid,$v,$curmember[$curmemberpid],$gongpaizhi_jiangli_lunshu[$lun]['zhi'],'用户id'.$curmember[$curmemberpid].',进入公排'.$lun.',直推拿钱'.$gongpaizhi_jiangli_lunshu[$lun]['zhi']);
                       }else{
                           //间推钱
                           \app\admin\common\Member::addcommission($aid,$v,$curmember[$curmemberpid],$gongpaizhi_jiangli_lunshu[$lun]['jian'],'用户id'.$curmember[$curmemberpid].',进入公排'.$lun.',间推拿钱'.$gongpaizhi_jiangli_lunshu[$lun]['jian']);
                       }
                    }
                }
               return $gongpaipid1;
            }else{
               return 0; 
            }
        }else{
            return 0;  
        }
     }
     public static function getdownjieidanparent2($aid,$mid,$gongpaipid,$deeplevel=1,$leveldata=[],$zuidacount=99,$parent=[]){
        $curmember =  Db::name('member')->field('id,'.$gongpaipid)->where('aid',$aid)->where('id',$mid)->find(); 
        if($deeplevel > $zuidacount){
            return $parent;
        }else{
            $memberparent = Db::name('member')->field('id,nickname,headimg,pid,realname,tel,levelid')->where('aid',$aid)->where('id',$curmember[$gongpaipid])->find();
            $parent[]=$memberparent['id'];
            $deeplevel++;
            return self::getdownjieidanparent2($aid,$memberparent['id'],$gongpaipid,$deeplevel,$leveldata,$zuidacount,$parent);
        }
    }
    
     public static function getgongpaipid($aid,$mid,$set,$lun=1)
     {
       //  var_dump('2第几轮'.$lun);
         if($lun == 1)
         {
             $gongpainum = 'gongpainum';
             $gongpaipid = 'gongpaipid';
             $gongpaitime = 'gongpaitime';
             
         }elseif($lun == 2)
         {
             $gongpainum = 'gongpainum2';
             $gongpaipid = 'gongpaipid2';
             $gongpaitime = 'gongpaitime2';
         }elseif($lun == 3)
         {
             $gongpainum = 'gongpainum3';
             $gongpaipid = 'gongpaipid3';
             $gongpaitime = 'gongpaitime3';
         }elseif($lun == 4)
         {
             $gongpainum = 'gongpainum4';
             $gongpaipid = 'gongpaipid4';
             $gongpaitime = 'gongpaitime4';
         }elseif($lun == 5)
         {
             $gongpainum = 'gongpainum5';
             $gongpaipid = 'gongpaipid5';
             $gongpaitime = 'gongpaitime5';
         }elseif($lun == 6)
         {
             $gongpainum = 'gongpainum6';
             $gongpaipid = 'gongpaipid6';
             $gongpaitime = 'gongpaitime6';
         }elseif($lun == 7)
         {
             $gongpainum = 'gongpainum7';
             $gongpaipid = 'gongpaipid7';
             $gongpaitime = 'gongpaitime7';
         }elseif($lun == 8)
         {
             $gongpaipid = 'gongpaipid8';
             $gongpainum = 'gongpainum8';
             $gongpaitime = 'gongpaitime8';
         }
         $returnpid = $dingjipid = 0;
         $curmember = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
         if($curmember['pid'] == 0)
         {
            $dingjipid = $mid;
         }else{
            $pathstr = $curmember['path'];
            $pathArr = explode(',',$pathstr);
            if($lun == 1)
            {
                $dingjipid = $pathArr[count($pathArr)-1];
                $s = 0;
                if($curmember['isxuni'] == 1 && $lun == 1)
                {
                    // $dingjipid = $curmember['pid'];//主账号
                    $xuniArr = Db::name('member')->where('aid',$aid)->where('pid',$curmember['pid'])->where('isxuni',1)->field('id')->order('id asc')->find();
                    if($xuniArr['id'] != $curmember['id'])
                    {
                        $s =1;
                        $dingjipid = $xuniArr['id'];
                    }else{
        
                        $s =1;
                        $rrr = Db::name('member')->where('aid',$aid)->where('id',$dingjipid)->field('id,pid,gongpainum')->order('id asc')->find();
                        if($rrr['gongpainum'] != 1)
                        {
                            $xuniArr = Db::name('member')->where('aid',$aid)->where('pid',$rrr['pid'])->where('isxuni',1)->field('id')->order('id asc')->find();
                            if(!empty($xuniArr))
                            {
                                $dingjipid = $xuniArr['id'];
                            }
                        }else{
                            $dingjipid = $rrr['pid'];
                        }
                    }
                }
            }else{
                 $dingjipid = $pathArr[0];
            }

         }
         if($s == 1)
         {
             $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where($gongpainum,1)->order($gongpaitime.' asc')->where('id',$dingjipid)->select()->toArray();
         }else{
             $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where($gongpainum,1)->where($gongpaipid,0)->order($gongpaitime.' asc')->where('id',$dingjipid)->select()->toArray();
         }
        //  var_dump($memberlist);die;
         if(empty($memberlist))
         {
             if($lun == 1){
                 $membersdingji = Db::name('member')->field('id,path')->where('aid',$aid)->where('id',$dingjipid)->find();
                 if(empty($membersdingji) ||  $membersdingji['path'] == '')
                 {
                     $memberlist =  [];
                 }else{
                    $membersdingjipathArr = explode(',',$membersdingji['path']);
                    $membersdingjipathArr = array_reverse($membersdingjipathArr);
                    if(!empty($membersdingjipathArr))
                    {
                        foreach ($membersdingjipathArr as $dingk =>$dingv)
                        {
                                $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where('id','<',$mid)->where($gongpainum,1)->order($gongpaitime.' asc')->where('find_in_set('.$dingv.',path)')->select()->toArray();
                                if(!empty($memberlist))
                                {
                                    break;
                                }
                        }
                    }
                 }
             }else{
                  $memberlist = Db::name('member')->field('id,path')->where('aid',$aid)->where($gongpainum,1)->order($gongpaitime.' asc')->where('find_in_set('.$dingjipid.',path)')->select()->toArray();
             }
         }
         if(!empty($memberlist))
         {
             $dingjipid = $memberlist[0]['id']; 
         }else{
              return  0;
         }
         $allcount = Db::name('member')->where('aid',$aid)->where($gongpaipid,$dingjipid)->count();//顶级下级人数
         var_dump('公排下级人数'.$allcount);
         $gongpaizhi_ren = $set['gongpaizhi_ren_lun'];
    //      if($lun == 0)
       // {
       //     $gongpaizhi_ren = $set['gongpaizhi_ren'];
       // }else{
       //     $gongpaizhi_ren = $set['gongpaizhi_ren_lun'];
       // }
       // var_dump($gongpaizhi_ren);die;
         if($allcount < $gongpaizhi_ren)
         {
             return  $dingjipid;
         }else{
             var_dump('顶级以满足,循环查找下面的');
            $allcountarray = Db::name('member')->where('aid',$aid)->where($gongpaipid,$dingjipid)->field('id')->select()->toArray();
            foreach($allcountarray as $v)
            {
                 $allcount1 = Db::name('member')->where('aid',$aid)->where($gongpaipid,$v['id'])->count();
                 var_dump('用户'.$v['id'].'公排人数'.$allcount1);
                 if($allcount1 < $gongpaizhi_ren)
                 {
                    $returnpid = $v['id'];
                    break;
                 }
            }
           // var_dump($returnpid);
            if($returnpid == 0)
            {
                 $returnpid = self::getxiajicount($aid,$allcountarray,$set,$lun);
            }
           // var_dump($returnpid);die;
            return $returnpid;
             
         }
     }
     
     public static function getxiajicount($aid,$allcountarray,$set,$lun=1)
     {
       //   var_dump('第'.$lun.'轮333');
         if($lun == 1)
         {
             $gongpainum = 'gongpainum';
             $gongpaipid = 'gongpaipid';
             $gongpaitime = 'gongpaitime';
             
         }elseif($lun == 2)
         {
             $gongpainum = 'gongpainum2';
             $gongpaipid = 'gongpaipid2';
             $gongpaitime = 'gongpaitime2';
         }elseif($lun == 3)
         {
             $gongpainum = 'gongpainum3';
             $gongpaipid = 'gongpaipid3';
             $gongpaitime = 'gongpaitime3';
         }elseif($lun == 4)
         {
             $gongpainum = 'gongpainum4';
             $gongpaipid = 'gongpaipid4';
             $gongpaitime = 'gongpaitime4';
         }elseif($lun == 5)
         {
             $gongpainum = 'gongpainum5';
             $gongpaipid = 'gongpaipid5';
             $gongpaitime = 'gongpaitime5';
         }elseif($lun == 6)
         {
             $gongpainum = 'gongpainum6';
             $gongpaipid = 'gongpaipid6';
             $gongpaitime = 'gongpaitime6';
         }elseif($lun == 7)
         {
             $gongpainum = 'gongpainum7';
             $gongpaipid = 'gongpaipid7';
             $gongpaitime = 'gongpaitime7';
         }elseif($lun == 8)
         {
             $gongpaipid = 'gongpaipid8';
             $gongpainum = 'gongpainum8';
             $gongpaitime = 'gongpaitime8';
         }
        $returnpid = 0;
        $gongpaizhi_ren = $set['gongpaizhi_ren_lun'];
       //  if($lun == 0)
       // {
       //     $gongpaizhi_ren = $set['gongpaizhi_ren'];
       // }else{
       //     $gongpaizhi_ren = $set['gongpaizhi_ren_lun'];
       // }
       $allcountarraymer = [];
        foreach($allcountarray as $v)
        {
           // var_dump('判断的用户id'.$v['id']);
             $allcountarray2 = Db::name('member')->where('aid',$aid)->where($gongpaipid,$v['id'])->order($gongpaitime.' asc')->field('id')->select()->toArray();
           //  var_dump($allcountarray2);
             $allcountarraymer = array_merge($allcountarraymer,$allcountarray2);
           //  var_dump($allcountarraymer);
             foreach ($allcountarray2 as $v2)
             {
               //  var_dump('判断用户2-'.$v2['id']);
                  $allcount2 = Db::name('member')->where('aid',$aid)->where($gongpaipid,$v2['id'])->count();
               //   var_dump('用户2数量'.$allcount2);
                  if($allcount2 < $gongpaizhi_ren)
                  {
                       $returnpid = $v2['id'];
                       break;
                  }
               //   var_dump($allcount2);
             }
             if($allcount2 < $gongpaizhi_ren)
              {
                   break;
              }
              
        }
        if($returnpid == 0)
        {
            $returnpid = self::getxiajicount($aid,$allcountarraymer,$set,$lun);
            if($returnpid > 0)
            {
                return $returnpid;
            }
        }else{
            return $returnpid;
        }
        
     }
     //切割时间
     public static function qiegetime($starttime,$arr=[])
     {
         $time = time(); 
         if($starttime)
         {
             $currentTime  = date('Y-m-d H:i:s',$starttime);
             $newTime = strtotime($currentTime . ' +5 days');
             $newTime2  = date('Y-m-d H:i:s',$newTime);
             $arr[]= $newTime2;
             if($newTime <= $time)
             {
                 //继续切割
                 return self::qiegetime($newTime,$arr);
             }else{
                 return $arr;
             }
         }else{
             return false;
         }
     }

     //给排队奖励
     public static function givepaiduijiang($aid,$mid,$orderid,$ogid,$ordernum,$qu = 0)
     {
         $defaultPoolId = \app\admin\common\PaiduiPoolService::ensureAidInitialized($aid);
         // 修复：排除商家补贴记录(is_merchant_subsidy=1)，只查询普通用户的排队记录
         $giveordergood = Db::name('paidui_list')
             ->where('aid', $aid)
             ->where('orderid', $orderid)
             ->where('ordergoodid', $ogid)
             ->where('qu', $qu)
             ->where(function($query) {
                 $query->where('is_merchant_subsidy', 0)
                       ->whereOr('is_merchant_subsidy', null);
             })
             ->find();

         if(!empty($giveordergood))
         {
            $sourcePoolId = intval($giveordergood['pool_id'] ?? 0);
            if($sourcePoolId <= 0) {
                $sourcePoolId = $defaultPoolId;
                Db::name('paidui_list')
                    ->where('aid', $aid)
                    ->where('id', $giveordergood['id'])
                    ->update(['pool_id' => $sourcePoolId]);
                $giveordergood['pool_id'] = $sourcePoolId;
            }

            $order_gooods= Db::name('shop_order_goods')->where('aid',$aid)->where('orderid', $giveordergood['orderid'])->where('id',$ogid)->find();
            $rewardBaseAmount = floatval($order_gooods['totalprice'] ?? 0);
            if($rewardBaseAmount <= 0 && intval($qu) === 1){
                $maidanOrderInfo = Db::name('maidan_order')->where('aid', $aid)->where('id', $orderid)->find();
                $rewardBaseAmount = floatval($maidanOrderInfo['totalprice'] ?? ($maidanOrderInfo['money'] ?? 0));
            }

             $member =  Db::name('member')->where('aid',$aid)->where('id',$giveordergood['mid'])->field('pid,path,levelid')->find();
             if($member['path'])
             {
                //加速
                 $zhi_pid = $member['pid'];
                 $jian_pid = 0;
                 $jian_pidArr = explode(',',$member['path']);
                 if(count($jian_pidArr) >=2)
                 {
                     $jian_pid = $jian_pidArr[count($jian_pidArr)-2];
                 }
                 if($zhi_pid > 0)
                 {
                     $zhi_pid_level =  Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->field('paidui_zhijiasu')->find();
                     if(!empty($zhi_pid_level) && $zhi_pid_level['paidui_zhijiasu'] >0 && $rewardBaseAmount > 0)
                     {
                         $jiang_zhi = $give_zhi = round($rewardBaseAmount*$zhi_pid_level['paidui_zhijiasu']*0.01,2);
                         $pai_list_zhi = Db::name('paidui_list')->where('aid',$aid)->where('mid',$zhi_pid)->where('paidui_jiang','>',0)
                         ->where('pool_id', $sourcePoolId)
                         ->where('status','<>',2)->where('status','<>',3);
                         $pai_list_zhi = PaiduiQueueOrderService::applyOrder($pai_list_zhi)->select()->toArray();
                         foreach($pai_list_zhi as $k=>$v)
                         {
                             if($give_zhi  >0){
                                
                                 $shiji_dedao_zhi = $v['shiji_dedao']+$give_zhi;
                                 if($shiji_dedao_zhi >=$v['paidui_jiang'] )
                                 {
                                     $shiji_dedao_zhi = $v['paidui_jiang'];
                                     $status =2;//完成
                                     $gaidong = $v['paidui_jiang']-$v['shiji_dedao'];
                                     $give_zhi = $give_zhi - $gaidong;
                                     $bool = self::addscore3($aid,$v['mid'],$v['bid'],$gaidong,'排队免单直推加速返现,订单已完成,队列id:'.$v['id'].',订单编号:'.$ordernum);
                                 }else{
                                     $shiji_dedao_zhi = $v['shiji_dedao']+$give_zhi;
                                     $status =1;//进行中
                                     $give_zhi = 0;
                                      $bool = self::addscore3($aid,$v['mid'],$v['bid'],$jiang_zhi,'排队免单直推加速返现,队列id'.$v['id'].',订单编号:'.$ordernum);
                                 }
                                 Db::name('paidui_list')->where('aid',$aid)->where('id',$v['id'])->update(['shiji_dedao'=>$shiji_dedao_zhi,'status'=>$status]);
                             }else{
                                 break;
                             }
                         }
                     }
                 }
                 if($jian_pid >0)
                 {
                     $jianmember =  Db::name('member')->where('aid',$aid)->where('id',$jian_pid)->field('pid,path,levelid')->find();
                     $jian_pid_level =  Db::name('member_level')->where('aid',$aid)->where('id',$jianmember['levelid'])->field('paidui_jianjiasu')->find();
                     if(!empty($jian_pid_level) && $jian_pid_level['paidui_jianjiasu'] >0 && $rewardBaseAmount > 0)
                     {
                         $jiang_jian = $give_jian = round($rewardBaseAmount*$jian_pid_level['paidui_jianjiasu']*0.01,2);
                         $pai_list_jian = Db::name('paidui_list')->where('aid',$aid)->where('mid',$jian_pid)->where('paidui_jiang','>',0)
                         ->where('pool_id', $sourcePoolId)
                         ->where('status','<>',2)->where('status','<>',3);
                         $pai_list_jian = PaiduiQueueOrderService::applyOrder($pai_list_jian)->select()->toArray();
                         foreach($pai_list_jian as $k=>$v)
                         {
                             if($give_jian  >0){
                                
                                 $shiji_dedao_jian = $v['shiji_dedao']+$give_jian;
                                 if($shiji_dedao_jian >=$v['paidui_jiang'] )
                                 {
                                     $shiji_dedao_jian = $v['paidui_jiang'];
                                     $status =2;//完成
                                     $gaidong = $v['paidui_jiang']-$v['shiji_dedao'];
                                     $give_jian = $give_jian - $gaidong;
                                     $bool = self::addscore3($aid,$v['mid'],$v['bid'],$gaidong,'排队免单间推加速返现,订单已完成,队列id:'.$v['id'].',订单编号:'.$ordernum);
                                 }else{
                                     $shiji_dedao_jian = $v['shiji_dedao']+$give_jian;
                                     $status =1;//进行中
                                     $give_jian = 0;
                                     $bool = self::addscore3($aid,$v['mid'],$v['bid'],$jiang_jian,'排队免单间推加速返现,队列id'.$v['id'].',订单编号:'.$ordernum);
                                 }
                                 Db::name('paidui_list')->where('aid',$aid)->where('id',$v['id'])->update(['shiji_dedao'=>$shiji_dedao_jian,'status'=>$status]);
                             }else{
                                 break;
                             }
                         }
                     }
                 }
             }
             
            $poolResult = \app\admin\common\PaiduiPoolService::distributeFromSourceRecord($giveordergood, $ordernum);
            if(($poolResult['status'] ?? 0) != 1){
                \think\facade\Log::warning('排队池分发失败，queue_id:'.$giveordergood['id'].'，msg:'.($poolResult['msg'] ?? '未知错误'));
            }

// 检查是否是复购行为（即同一用户之前已有订单）
$memberOrderCount = Db::name('shop_order')->where('aid', $aid)->where('mid', $mid)->where('status', 1)->count();
if ($memberOrderCount > 1) {
    // 获取用户当前等级的复购加速比例
    
    $memberInfo = Db::name('member')->where('aid', $aid)->where('id', $mid)->field('levelid')->find();
    $fugouLevel = Db::name('member_level')->where('aid', $aid)->where('id', $memberInfo['levelid'])->field('paidui_fugoujiasu')->find();
    
    if (!empty($fugouLevel) && $fugouLevel['paidui_fugoujiasu'] > 0) {
        // 查找用户自己正在排队中的记录
     
        $pai_list_fugou = Db::name('paidui_list')->where('aid', $aid)->where('mid', $mid)
                         ->where('pool_id', $sourcePoolId)
                         ->where('paidui_jiang', '>', 0)
                         ->where('status', '<>', 2)
                         ->where('status', '<>', 3);
        $pai_list_fugou = PaiduiQueueOrderService::applyOrder($pai_list_fugou)->select()->toArray();
           
        if (!empty($pai_list_fugou)) {
            // 获取订单信息计算加速金额
            $orderInfo = Db::name('shop_order')->where('aid', $aid)->where('id', $orderid)->find();
            if (!empty($orderInfo)) {
                $jiang_fugou = $give_fugou = round($orderInfo['totalprice'] * $fugouLevel['paidui_fugoujiasu'] * 0.01, 2);
                
                foreach ($pai_list_fugou as $k => $v) {
                    if ($give_fugou > 0) {
                        $shiji_dedao_fugou = $v['shiji_dedao'] + $give_fugou;
                        
                        if ($shiji_dedao_fugou >= $v['paidui_jiang']) {
                            $shiji_dedao_fugou = $v['paidui_jiang'];
                            $status = 2; // 完成
                            $gaidong = $v['paidui_jiang'] - $v['shiji_dedao'];
                            $give_fugou = $give_fugou - $gaidong;
                            $bool = self::addscore3($aid, $v['mid'], $v['bid'], $gaidong, '排队免单复购加速返现,订单已完成,队列id:' . $v['id'] . ',订单编号:' . $ordernum);
                        } else {
                            $shiji_dedao_fugou = $v['shiji_dedao'] + $give_fugou;
                            $status = 1; // 进行中
                            $bool = self::addscore3($aid, $v['mid'], $v['bid'], $give_fugou, '排队免单复购加速返现,队列id:' . $v['id'] . ',订单编号:' . $ordernum);
                            $give_fugou = 0;
                        }
                        
                        Db::name('paidui_list')->where('aid', $aid)->where('id', $v['id'])->update(['shiji_dedao' => $shiji_dedao_fugou, 'status' => $status]);
                    } else {
                        break;
                    }
                }
            }
        }
    }
}

return true;


     }
}
 
 
 
public static function givepaiduijiangModel($aid, $mid, $orderid, $ordernum, $setting)
{
    // 从数据库中获取订单信息
    $giveOrderInfo = Db::name('paidui_list')->where('aid', $aid)->where('id', $orderid)->find();
    if (!empty($giveOrderInfo)) {
        $bid = $giveOrderInfo['bid'];
        // 排队奖励总数，这里使用了paidui_jiang字段
        $commission = $giveOrderInfo['paidui_jiang'];
        //排队返现总数
        $paiduifanxianzongshu = $giveOrderInfo['paidui_give'];
        // 订单本人返现计算
        if ($setting['consumers_proportion'] != 0) {
            // 计算用户返现
            $cashback = bcmul($paiduifanxianzongshu, $setting['consumers_proportion'], 5);
            // 添加返现记录
            self::addscore3($aid, $mid, $bid, $cashback, '排队免单-分成模型1-返现20%,订单编号:' . $ordernum);
            // 计算剩余金额
            $remainderValue = bcsub($commission, $cashback, 5);
            // 更新数据库中用户的实际得到金额和剩余金额
            Db::name('paidui_list')
                ->where('aid', $aid)
                ->where('id', $orderid)
                ->update(['shiji_dedao' => $cashback, 'give_shengyu' => $remainderValue]);
        }

        $alreadyDivide = [];

        // 店铺分成计算
        if ($setting['shop_proportion'] != 0) {
            // 计算店铺分成金额
            $shopCommission = bcmul($paiduifanxianzongshu, $setting['shop_proportion'], 5);

            // 单店第一奖励金额（确认计算正确）
            $shopFirst = $setting['shop_first'] != 0 ? bcmul($shopCommission, $setting['shop_first'], 5) : 0;
           
            // 计算店铺剩余分成金额
            $shopRemainder = bcsub($shopCommission, $shopFirst, 5);  // 确保减去的是 shopFirst
 
            // 查询符合条件的店铺订单
            $queryShopList = Db::name('paidui_list')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('paidui_jiang', '>', 0)
                ->where('give_shengyu', '>', 0)
                ->whereNotIn('status', '2, 3');

            $queryShopList = PaiduiQueueOrderService::applyOrder($queryShopList);

            $shopList = $queryShopList->select()->toArray();

            $shopCount = count($shopList);

            // 判断是均分还是加权分成
            if (!empty($setting['shop_is_share'])) {
                // 均分模式
                $shopIsShare = true;
                $shopShare = bcdiv($shopRemainder, $shopCount, 5);
               
            } else {
                // 加权模式，根据总权重计算
                $weighting = $queryShopList->sum('paidui_jiang');
            }

            foreach ($shopList as $key => $value) {
                if (!$shopIsShare) {
                    // 计算加权比例
                    $tempProportion = bcdiv($value['paidui_jiang'], $weighting, 5);
                    $shopShare = bcmul($tempProportion, $shopRemainder, 5);
                }
                // 计算当前订单的额外金额
               
                $additionalAmount = ($key == 0) ? bcadd($shopFirst, $shopShare, 5) : $shopShare;
  //var_dump( $additionalAmount);die;
                if ($additionalAmount != 0) {
                    // 计算订单的实际得到金额
                    $tempValue = bcadd($value['shiji_dedao'], $additionalAmount, 5);
                    // var_dump(  $tempValue);die;
                    $status = $tempValue >= $value['paidui_jiang'] ? 2 : 1;
                    $actualValue = $status === 2 ? $value['paidui_jiang'] : $tempValue;
                    $changeValue = $status === 2 ? bcsub($value['paidui_jiang'], $value['shiji_dedao'], 5) : $additionalAmount;
                    $remainderValue = $status === 2 ? 0 : bcsub($value['give_shengyu'], $changeValue, 5);

                    // 记录日志信息
                    $logMessage = '排队免单返现-分成模型1-店铺-';
                    $logMessage .= $status === 2 ? ($key == 0 ? '顺位第一,订单已完成' : '阳光普照,订单已完成') : ($key == 0 ? '顺位第一' : '阳光普照');
                    $logMessage .= ',队列id:' . $value['id'] . ',订单编号:' . $ordernum;

                    // 添加分成记录
                    self::addscore3($aid, $value['mid'], $value['bid'], $changeValue, $logMessage);

                    // 更新数据库中店铺的实际得到金额、剩余金额和状态
                    Db::name('paidui_list')
                        ->where('aid', $aid)
                        ->where('id', $value['id'])
                        ->update(['shiji_dedao' => $actualValue, 'give_shengyu' => $remainderValue, 'status' => $status]);
                }

                // 记录已经分成的订单
                $alreadyDivide[] = $value['id'];
            }
        }

        // 读取多城市配置
        $admin_set = Db::name('admin_set')->where(['aid' => $aid])->field('area_on,area_set')->find();
        if (empty($admin_set) && empty($admin_set['area_on']) && empty($admin_set['area_set'])) {
            // 未开多城市，直接返回
            return true;
        }

        // 城市分成计算
        if ($setting['region_proportion'] != 0) {
            // 分区模式
            $area_set = json_decode($admin_set['area_set'], true);
            $areamode = $area_set['areamode'];

            $areaArray = [
                'province',
                'city',
                'district'
            ];
            $areaMode = $areaArray[$areamode];
            $areaModeId = $areaMode . '_id';

            // 获取当前订单的地区
            if (empty($giveOrderInfo['qu'])) {
                // 如果订单没有指定地区，通过商品信息获取地区
                $giveOrderArea = Db::name('shop_order_goods')
                    ->alias('g')
                    ->field('p.province_id, p.city_id, p.district_id, p.province, p.city, p.district')
                    ->join('shop_product p', 'p.id = g.proid')
                    ->where('g.aid', $aid)->where('g.orderid', $giveOrderInfo['orderid'])->where('g.id', $giveOrderInfo['ordergoodid'])->find();
            } else {
                // 通过商家信息获取地区
                $giveOrderArea = Db::name('business')->field('province_id, city_id, district_id, province, city, district')->where('aid', $aid)->where('id', $bid)->find();
            }
            $giveOrderAreaId = $giveOrderArea[$areaModeId];

            // 计算地区分成金额
            $regionCommission = bcmul($paiduifanxianzongshu, $setting['region_proportion'], 5);

            // 地区第一奖励金额
            $regionFirst = $setting['region_first'] != 0 ? bcmul($regionCommission, $setting['region_first'], 5) : 0;
            // 计算地区剩余分成金额
            $regionRemainder = bcsub($regionCommission, $regionFirst, 5);

            // 查询符合条件的订单
            $queryShopList = Db::name('paidui_list')
                ->where('aid', $aid)
                ->where('paidui_jiang', '>', 0)
                ->where('give_shengyu', '>', 0)
                ->whereNotIn('status', '2, 3');

            $queryShopList = PaiduiQueueOrderService::applyOrder($queryShopList);

            // 地区订单列表
            $regionOrderList = $queryShopList->select()->toArray();

            $commissionsList = [];
            $weighting = 0;
            if (!empty($regionOrderList)) {
                foreach ($regionOrderList as $key => $value) {
                    // 如果订单没有指定地区，通过商品信息获取地区
                    if (empty($value['qu'])) {
                        $orderArea = Db::name('shop_order_goods')
                            ->alias('g')
                            ->field('p.province_id, p.city_id, p.district_id, p.province, p.city, p.district')
                            ->join('shop_product p', 'p.id = g.proid')
                            ->where('g.aid', $aid)->where('g.orderid', $value['orderid'])->where('g.id', $value['ordergoodid'])->find();
                    }
                    // 通过买单信息获取地区
                    else {
                        $orderArea = Db::name('maidan_order')
                            ->alias('m')
                            ->field('b.province_id, b.city_id, b.district_id, b.province, b.city, b.district')
                            ->join('business b', 'b.id = m.bid')
                            ->where('m.aid', $aid)->where('m.id', $value['orderid'])->find();
                    }

                    // 如果地区匹配，加入分成列表
                    if ($giveOrderAreaId == $orderArea[$areaModeId]) {
                        $commissionsList[] = $value;
                        // 加权paidui_jiang
                        $weighting = bcadd($weighting, $value['paidui_jiang'], 5);
                    }
                }
            }

            if (!empty($commissionsList)) {
                // 判断是均分还是加权分成
                if (!empty($setting['region_is_share'])) {
                    // 均分模式
                    $regionIsShare = true;
                    $regionShare = bcdiv($regionRemainder, count($commissionsList), 5);
                }

                foreach ($commissionsList as $key => $value) {
                    if (!$regionIsShare) {
                        // 计算加权比例
                        $tempProportion = bcdiv($value['paidui_jiang'], $weighting, 5);
                        $regionShare = bcmul($tempProportion, $regionRemainder, 5);
                    }
                    // 计算当前订单的额外金额
                    $additionalAmount = ($key == 0) ? bcadd($regionFirst, $regionShare, 5) : $regionShare;

                    if ($additionalAmount != 0) {
                        // 计算订单的实际得到金额
                        $tempValue = bcadd($value['shiji_dedao'], $additionalAmount, 5);
                        $status = $tempValue >= $value['paidui_jiang'] ? 2 : 1;
                        $actualValue = $status === 2 ? $value['paidui_jiang'] : $tempValue;
                        $changeValue = $status === 2 ? bcsub($value['paidui_jiang'], $value['shiji_dedao'], 5) : $additionalAmount;
                        $remainderValue = $status === 2 ? 0 : bcsub($value['give_shengyu'], $changeValue, 5);

                        // 记录日志信息
                        $logMessage = '排队免单返现-分成模型1-地区-';
                        $logMessage .= $status === 2 ? ($key == 0 ? '顺位第一,订单已完成' : '阳光普照,订单已完成') : ($key == 0 ? '顺位第一' : '阳光普照');
                        $logMessage .= ',队列id:' . $value['id'] . ',订单编号:' . $ordernum;

                        // 添加分成记录
                        self::addscore3($aid, $value['mid'], $value['bid'], $changeValue, $logMessage);

                        // 更新数据库中订单的实际得到金额、剩余金额和状态
                        Db::name('paidui_list')
                            ->where('aid', $aid)
                            ->where('id', $value['id'])
                            ->update(['shiji_dedao' => $actualValue, 'give_shengyu' => $remainderValue, 'status' => $status]);
                    }
                }
            }
        }
    }
    return true;
}




     
     //脱离
     public static function liandongtuoli($aid,$mid)
     {
         $liandongrs = self::getEnabledLiandongSet($aid, ['liandong_tuoli_log'], [
             'liandong_set' => ['status'],
             'member' => ['tuo1', 'ytpid', 'ytpath', 'liandongren', 'yliandongren'],
             'member_level' => [
                 'tuoli_jiedianren',
                 'tuoli_member_count',
                 'tuoli_member_son_levelid',
                 'tuoli_member_parent_levelid',
                 'tuoli_shengji_prent1',
                 'tuoli_shengji_prent2',
                 'tuoli_jiedian_member_count',
                 'tuoli_jiedian_member_son_levelid',
                 'tuoli_jiedian_member_parent_levelid',
                 'tuoli_liandongjiang',
                 'tuoli_liandongjiangshang1',
                 'tuoli_liandongjiangshang2',
                 'tuoli_liandongjiangshang3',
             ],
             'liandong_tuoli_log' => ['aid', 'mid', 'ytpid', 'newpid', 'createtime', 'type'],
         ]);
         if(empty($liandongrs))
         {
             return;
         }elseif($liandongrs['status'] == 0)
         {
             return;
         }else{
             $member = Db::name('member')->field('id,path,levelid,pid,tuo1,ytpid,ytpath')->where('aid',$aid)->where('id',$mid)->find();
             $curlevel = Db::name('member_level')->where('aid',$aid)->where('id',$member['levelid'])->find();
           //   var_dump($curlevel['tuoli_jiedianren']);die;
            if($curlevel['tuoli_jiedianren'] == 0){
                 //下级脱离
                 if($curlevel['tuoli_member_count'] > 0)
                 {
                     $xiaji = Db::name('member')->field('id,path,levelid,pid,tuo1,ytpid,ytpath')->where('aid',$aid)->where('tuo1',0)->where('pid',$mid)->select()->toArray();
                     if(!empty($xiaji))
                     {
                         if($curlevel['tuoli_member_son_levelid'])
                         {
                            $xiajiArr = [];
                            $sonlevel = explode(',',$curlevel['tuoli_member_son_levelid']);
                            foreach($xiaji as $v)
                            {
                                if(in_array($v['levelid'],$sonlevel))
                                {
                                    $xiajiArr[] = $v;
                                }
                            }
                         }else{
                             $xiajiArr = $xiaji;
                         }
                         //符合等级的下级  $xiajiArr
                         $xiajiArr = array_slice($xiajiArr,0,$curlevel['tuoli_member_count']);
                         //获取上级
                         $xiajipid = 0;
                         $xiajipidArr = [];
                         if($curlevel['tuoli_member_parent_levelid'])
                         {
                             $parentlevel = explode(',',$curlevel['tuoli_member_parent_levelid']);
                             $parentArr = array_reverse(explode(',',$member['path']));
                             foreach($parentArr as  $v)
                             {
                                 $vmember = Db::name('member')->field('id,path,levelid,pid,tuo1,ytpid,ytpath')->where('aid',$aid)->where('id',$v)->find();
                                 if(in_array($vmember['levelid'],$parentlevel))
                                {
                                    $xiajipid = $v;
                                    $xiajipidArr = $vmember;
                                    break;
                                }
                             }
                         }else{
                             $vmember = Db::name('member')->field('aid',$aid)->where('id',$v)->find();
                             $xiajipid = $member['pid'];
                             $xiajipidArr = $vmember;
                         }
                         if($xiajipid)
                         {
                             if($xiajipidArr['path'])
                             {
                                 $path = $xiajipidArr['path'].','.$xiajipid;
                             }else{
                                 $path = $xiajipid;
                             }
                         }else{
                             $path = ''; 
                         }
                         foreach($xiajiArr as $v)
                         {
                             $update_data = [];
                             $update_data['pid'] =$xiajipid;
                             $update_data['path'] = $path;
                             $update_data['ytpid'] = $mid;
                             $update_data['ytpath'] = $v['path'];
                             $update_data['tuo1'] = 1;
                             $res = Db::name('member')->where('aid',$aid)->where('id',$v['id'])->update($update_data);
                                                         // 使用会员ID而不是整个path字符串
                            m::updatepath($v['id'], $path);
                             $insertdata=[];
                             $insertdata['aid'] = $aid;
                             $insertdata['mid'] = $v['id'];
                             $insertdata['ytpid'] = $mid;
                             $insertdata['newpid'] = $xiajipid;
                             $insertdata['createtime'] = time();
                             $insertdata['type'] = 0;
                             Db::name('liandong_tuoli_log')->insert($insertdata);
                         }
                         
                     }
                 }else{
                     return;
                 }
                 //我
                 if($curlevel['tuoli_shengji_prent1'] == 1)
                 {
                    //脱离上级//  升级后脱离上级 1是0否
                    $mypid = 0;//脱离上级 归于平台
                    $update_data = [];
                    $update_data['pid'] =0;
                    $update_data['path'] = '';
                    $update_data['ytpid'] = $member['pid'];
                    $update_data['ytpath'] = $member['path'];
                    $update_data['tuo1'] = 1;
                    $res = Db::name('member')->where('aid',$aid)->where('id',$member['id'])->update($update_data);
                                       // 传递会员ID和空path（脱离上级）
                   m::updatepath($member['id'], '');
                    $insertdata=[];
                    $insertdata['aid'] = $aid;
                    $insertdata['mid'] = $mid;
                    $insertdata['ytpid'] = $member['pid'];
                    $insertdata['newpid'] = $mypid;
                    $insertdata['createtime'] = time();
                    $insertdata['type'] = 0;
                    Db::name('liandong_tuoli_log')->insert($insertdata);
                 }
                 //回归上级
                 if($curlevel['tuoli_shengji_prent2'] == 1)
                 {
                     
                     //回到原来的推荐人下面
                     if($member['tuo1'] ==1)
                     {
                         $ytpidarr = Db::name('member')->where('aid',$aid)->where('id',$member['ytpid'])->find();
                         $pathArr = m::getpath($ytpidarr,[]);
                         $pathArr = array_reverse($pathArr);
                         $pathstr = implode(',',$pathArr);
                         $pathstr = $pathstr.','.$member['ytpid'];
                         $update_data = [];
                         $update_data['pid'] =$member['ytpid'];
                         $update_data['path'] = $pathstr;
                        //  $update_data['ytpid'] = 0;
                        //  $update_data['ytpath'] = '';
                         $update_data['tuo1'] = 2;
                         $res = Db::name('member')->where('aid',$aid)->where('id',$member['id'])->update($update_data);
                                                 // 传递会员ID和新的path（回归原推荐人）
                        m::updatepath($member['id'], $pathstr);
                         $insertdata=[];
                         $insertdata['aid'] = $aid;
                         $insertdata['mid'] = $mid;
                         $insertdata['ytpid'] = $member['pid'];
                         $insertdata['newpid'] = $member['ytpid'];
                         $insertdata['createtime'] = time();
                         $insertdata['type'] = 1;
                         Db::name('liandong_tuoli_log')->insert($insertdata);
                     }
                 }
                 return true;
            }else{
                //     
                if($curlevel['tuoli_jiedian_member_count'] >0)
                {
                    if($curlevel['tuoli_jiedian_member_son_levelid'])
                    {
                        $xiaji = Db::name('member')->field('id,path,levelid,liandongren,tuo1,ytpid,ytpath')->where('aid',$aid)->where('tuo1',0)->where('liandongren',$mid)
                        ->where('levelid','in',$curlevel['tuoli_jiedian_member_son_levelid'])->select()->toArray();
                    }else{
                         $xiaji = Db::name('member')->field('id,path,levelid,liandongren,tuo1,ytpid,ytpath')->where('aid',$aid)->where('tuo1',0)->where('liandongren',$mid)->select()->toArray();
                    }
                    if(!empty($xiaji)){
                        $xiajiArr = array_slice($xiaji,0,$curlevel['tuoli_jiedian_member_count']);
                        //获取上级
                        $parentArr = self::getdownliandongrenparent($aid,$mid,0);
                        $updatepid = 0;
                        foreach ($parentArr as $v)
                        {
                            if($v)
                            {
                                $member_parent =  Db::name('member')->field('id,path,levelid,liandongren,tuo1,ytpid,ytpath')->where('aid',$aid)->where('id',$v)->where('levelid','in',$curlevel['tuoli_jiedian_member_parent_levelid'])->find();
                                if(!empty($member_parent))
                                {
                                    $updatepid = $v;
                                    break;
                                }
                            }
                        }
                        if($updatepid >0)
                        {
                            $num = 0;
                            foreach($xiajiArr as $v)
                             {
                                 $num = $num+1;
                                 $update_data = [];
                                 $update_data['liandongren'] =$updatepid;
                                 $update_data['yliandongren'] =$mid;
                                 $update_data['tuo1'] = 1;
                                 $res = Db::name('member')->where('aid',$aid)->where('id',$v['id'])->update($update_data);
                                 $insertdata=[];
                                 $insertdata['aid'] = $aid;
                                 $insertdata['mid'] = $v['id'];
                                 $insertdata['ytpid'] = $mid;
                                 $insertdata['newpid'] = $updatepid;
                                 $insertdata['createtime'] = time();
                                 $insertdata['type'] = 3;
                                 Db::name('liandong_tuoli_log')->insert($insertdata);
                             }
                             if($curlevel['tuoli_liandongjiang'] >0){
                                 $tuoli_liandongjiang = $curlevel['tuoli_liandongjiang']*$num;
                                 self::addcommission($aid,$updatepid,0,$tuoli_liandongjiang,'联动奖');
                                 $member_parent = Db::name('member')->field('id,path,levelid,liandongren,tuo1,ytpid,ytpath')->where('aid',$aid)->where('id',$updatepid)->find();
                                 if($member_parent['path'])
                                 {
                                     $curlevel2 = Db::name('member_level')->where('aid',$aid)->where('id',$member_parent['levelid'])->find();
                                     $pathArr = array_reverse(explode(',',$member_parent['path']));
                                     if($curlevel2['tuoli_liandongjiangshang1'] >0 && isset($pathArr[0]))
                                     {
                                         $yiji = $tuoli_liandongjiang *$curlevel2['tuoli_liandongjiangshang1']*0.01;
                                         self::addcommission($aid,$pathArr[0],$updatepid,$yiji,'联动奖-抽佣');
                                     }
                                      if($curlevel2['tuoli_liandongjiangshang2'] >0 && isset($pathArr[1]))
                                     {
                                         $erji= $tuoli_liandongjiang *$curlevel2['tuoli_liandongjiangshang2'] *0.01;
                                         self::addcommission($aid,$pathArr[1],$updatepid,$erji,'联动奖-抽佣');
                                     }
                                      if($curlevel2['tuoli_liandongjiangshang3'] >0 && isset($pathArr[2]))
                                     {
                                         $sanji= $tuoli_liandongjiang *$curlevel2['tuoli_liandongjiangshang3']*0.01;
                                         self::addcommission($aid,$pathArr[2],$updatepid,$sanji,'联动奖-抽佣');
                                     }
                                 }
                             }
                        }
                    }
                }else{
                     return;
                }
                return true;
            }
         }
         return;
     }
      public static function getdownliandongrenparent($aid,$mid,$deeplevel,$parent=[],$oldxingji=0){
        $curmember =  Db::name('member')->field('id,liandongren')->where('aid',$aid)->where('id',$mid)->find(); 
        if($deeplevel > 99){
            return $parent;
        }elseif(empty($curmember) || empty($curmember['liandongren'])){
            return $parent;
        }else{
            $memberparent = Db::name('member')->field('id,nickname,headimg,pid,realname,tel,levelid')->where('aid',$aid)->where('id',$curmember['liandongren'])->find();
            if(empty($memberparent)){
                return $parent;
            }
            if($oldxingji ==0){
               $parent[]=$memberparent['id'];
            }elseif($oldxingji > 0 && $deeplevel > $oldxingji){
                $parent[]=$memberparent['id'];
            }
            $deeplevel++;
            return self::getdownliandongrenparent($aid,$memberparent['id'],$deeplevel,$parent,$oldxingji);
        }
    }
     
    //判断是否解冻
    public static function  jiedongpanduan($aid,$mid)
    {
       $liandongrs = self::getEnabledLiandongSet($aid, ['member_dongjienum'], [
           'liandong_set' => [
               'status',
               'fcj_levelids',
               'fcj_type',
               'fcj_dan_levelid',
               'fcj_quan_count',
               'fcj_quan_levelid',
               'fcj_quan_order',
               'fcj_quan_goodsid',
           ],
           'member' => ['tuo1', 'ytpid', 'ytpath', 'dongjienum', 'dong_status', 'dong_time'],
           'member_dongjienum' => ['score', 'after', 'createtime', 'remark'],
       ]);
       if(empty($liandongrs))
       {
             return;
        }elseif($liandongrs['status'] == 0)
        {
           return;
        }else{ 
            $member = Db::name('member')->field('id,path,levelid,pid,tuo1,ytpid,ytpath,dongjienum,dong_status')->where('aid',$aid)->where('id',$mid)->find();
            $fcj_levelids = explode(',',$liandongrs['fcj_levelids']);
            if($member['dong_status'] != 1){
                //判断自己升级解冻自己
                if(in_array('-1',$fcj_levelids) || in_array($member['levelid'],$fcj_levelids))
                {
                    
                }else{
                    //不参与冻结 解冻之前的 然后设置成解冻的
                    self::jiedong2($member,$aid,'本人升级解冻');
                }
            }
            if($liandongrs['fcj_type'] == 0)
            {
                 if($member['tuo1'] != 0){
                     $curmember = Db::name('member')->field('id,path,levelid,pid,tuo1,ytpid,ytpath,dongjienum,dong_status')->where('aid',$aid)->where('id',$member['ytpid'])->find();
                     if($curmember['dong_status'] != 1)
                     {
                         $tioajian = $liandongrs['fcj_dan_levelid'];
                         $tioajian = explode(',',$tioajian);
                         $manzu = 0;
                         $memberlist = Db::name('member')->field('id,levelid,ytpid')->where('aid',$aid)->where('ytpid',$member['ytpid'])->where('tuo1','<>',0)->select()->toArray();
                         $memberlistcount = count($memberlist);
                         if($memberlistcount >0)
                         {
                            foreach ($memberlist as $v)
                            {
                                 if(in_array($v['levelid'],$tioajian))
                                 {
                                     $manzu = $manzu+1;
                                 }
                            }
                            if($manzu >= $memberlistcount)
                            {
                                //滿足條件
                                self::jiedong2($curmember,$aid,'直推的脱离会员升级解冻');
                            }
                            
                         }
                     }
                     
                 }
            }elseif($liandongrs['fcj_type'] == 1)
            {
                $pathArr = explode(',',$member['path']);
                if(count($pathArr) >0 )
                {
                    $tioajian0 = $liandongrs['fcj_quan_count'];
                    $tioajian1 = $liandongrs['fcj_quan_levelid'];
                   // $tioajian1 = explode(',',$tioajian1);
                    foreach($pathArr as $v)
                    {
                        if($v){
                            $curmember = Db::name('member')->field('id,path,levelid,pid,tuo1,ytpid,ytpath,dongjienum,dong_status')->where('aid',$aid)->where('id',$v)->find();
                            if($curmember['dong_status'] != 1)
                            {
                                $memberlist = Db::name('member')->field('id,levelid,pid,path')->where('aid',$aid)->where('find_in_set('.$v.',path)')->where('levelid','in',$tioajian1)->select()->toArray();
                                $memberlistcount = count($memberlist);
                                if($memberlistcount >= $tioajian0)
                                {
                                    if($liandongrs['fcj_quan_order'] >0)
                                    {
                                        $quan_order_member = Db::name('member')->field('id')->where('aid',$aid)->where('find_in_set('.$v.',path)')->select()->toArray();
                                        $quan_order_member = array_column($quan_order_member,'id');
                                        $quan_order_member = implode(',',$quan_order_member);
                                        if($liandongrs['fcj_quan_goodsid']){
                                             $quan_order = Db::name('shop_order_goods')->where('aid',$aid)->where('proid','in',$liandongrs['fcj_quan_goodsid'])->where('status','in','1,2,3')->where('mid','in',$quan_order_member)->count();//fcj_quan_goodsid
                                        }else{
                                             $quan_order = Db::name('shop_order_goods')->where('aid',$aid)->where('status','in','1,2,3')->where('mid','in',$quan_order_member)->count();//fcj_quan_goodsid
                                        }
                                        ///获取我的直推下级
                                       
                                        if($quan_order >= $liandongrs['fcj_quan_order'])
                                        {
                                            self::jiedong2($curmember,$aid,'伞下会员升级且伞下全部会员订单总量满足解冻'); 
                                        }
                                        
                                    }else{
                                       self::jiedong2($curmember,$aid,'伞下会员升级解冻'); 
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    public static function jiedong2($info,$aid,$remark='')
    {
        if(
            empty($info['id'])
            || !self::columnsExistStatic('member', ['dongjienum', 'dong_status', 'dong_time'])
            || !self::tableExistsStatic('member_dongjienum')
            || !self::columnsExistStatic('member_dongjienum', ['score', 'after', 'createtime', 'remark'])
        ){
            return false;
        }
        if($info['dongjienum'] >0 ){
            \app\admin\common\Member::adddongjienum($aid,$info['id'],'-'.$info['dongjienum'],$remark);
            \app\admin\common\Member::addcommission($aid,$info['id'],0,$info['dongjienum'],$remark);
            Db::name('member')->where('aid',$aid)->where('id',$info['id'])->update(['dong_status'=>1,'dong_time'=>time()]);
            return true;
        }else{
            Db::name('member')->where('aid',$aid)->where('id',$info['id'])->update(['dong_status'=>1,'dong_time'=>time()]);
        }
         return true;
    }
    /**
     * 易货-拓展费增减
     * */
    public static function addtuozhanfei($aid,$mid,$money,$remark)
    {
        if($money==0) return ;
        $member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
        if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];

        if(false){}else {
            // PHP 8.0+ 类型转换
            $after = floatval($member['tuozhanfei'] ?? 0) + floatval($money);
            Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['tuozhanfei'=>$after]);
        }

        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['money'] = $money;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('member_tuozhanfeilog')->insert($data);

        $tmplcontent = [];
        $tmplcontent['first'] = '您的'.t('拓展费').'发生变动，变动金额：'.$money;
        $tmplcontent['remark'] = '点击进入查看~';
        $tmplcontent['keyword1'] = date('Y-m-d H:i'); //变动时间
        $tmplcontent['keyword2'] = $remark;  //变动类型
        $tmplcontent['keyword3'] = (string) round($money,2);  //变动金额
        $tmplcontent['keyword4'] = (string) round($after,2);  //当前余额
        $rs = \app\admin\common\Wechat::sendtmpl($aid,$mid,'tmpl_moneychange',$tmplcontent,m_url('pages/my/usercenter', $aid));
        return ['status'=>1,'msg'=>''];
    }
    
	 /**
	  * 生成订单
	  * */
	  public static function addorder($aid,$price,$mid,$tel)
	  {
	      if($price == 2400)
	      {
	          $goodsid = 199;
	          
	      }elseif($price == 9600)
	      {
	          $goodsid = 196;
	      }else{
	          return false;
	      }
	      $num = 1;
	      $product = Db::name('shop_product')->where('aid',$aid)->where('id',$goodsid)->find();
	      $guigeid = Db::name('shop_guige')->where('aid',$aid)->where('proid',$goodsid)->find();
	      $guigeid = $guigeid['id'];
	      $post=[
	          'linkman'=>'',
	          'tel'=>$tel,
	          'buydata'=>[
	              [
	                 'bid'=>$product['bid'],
                     'couponrid'=> "",
                     'cuxiaoid'=>0,
                     'formdata'=>[],
                     'freight_id'=> 2,
                     'freight_time'=> "",
                     'prodata'=> $goodsid.",".$guigeid.",".$num,
                     'storeid'=> 0,
                     'type11key'=> 0,
	                  ],
	              ],
	          ];
	         // var_dump($mid);die;
	      $Testh =   new Testh($aid);
	      $c = $Testh->createOrder($mid,$post);
	      return $c;
	      
	      
	  }
     
    /**
     * 增加可抵扣团队奖励余额
     * @param int $aid 应用ID
     * @param int $mid 会员ID
     * @param float $amount 增加金额
     * @param string $remark 备注
     * @param int $order_id 关联订单ID
     * @return array
     */
    public static function addTeamRewardBalance($aid, $mid, $amount, $remark, $order_id = 0) {
        if($amount == 0) return ['status' => 1, 'msg' => ''];
        
        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
        if(!$member) return ['status' => 0, 'msg' => t('会员').'不存在'];
        
        // 使用高精度计算
        $before = $member['team_reward_balance'] ?? 0;
        $after = bcadd($before, $amount, 2);
        
        // 更新会员余额
        Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['team_reward_balance' => $after]);
        
        // 记录日志
        $logData = [
            'aid' => $aid,
            'mid' => $mid,
            'type' => $amount > 0 ? 3 : 4, // 3=管理员充值，4=管理员扣减
            'amount' => abs($amount),
            'balance_before' => $before,
            'balance_after' => $after,
            'related_id' => $order_id,
            'related_type' => $order_id > 0 ? 'order' : '',
            'remark' => $remark,
            'createtime' => time()
        ];
        Db::name('team_reward_limit_log')->insert($logData);
        
        // 记录操作日志
        \app\admin\common\System::plog('可抵扣团队奖励余额变动：会员ID='.$mid.'，变动金额='.$amount.'，备注='.$remark);
        
        return ['status' => 1, 'msg' => ''];
    }
    
    /**
     * 扣减可抵扣团队奖励余额
     * @param int $aid 应用ID
     * @param int $mid 会员ID
     * @param float $amount 扣减金额
     * @param string $remark 备注
     * @param int $order_id 关联订单ID
     * @return array
     */
    public static function deductTeamRewardBalance($aid, $mid, $amount, $remark, $order_id = 0) {
        if($amount <= 0) return ['status' => 0, 'msg' => '扣减金额必须大于0'];
        
        $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->lock(true)->find();
        if(!$member) return ['status' => 0, 'msg' => t('会员').'不存在'];
        
        $before = $member['team_reward_balance'] ?? 0;
        if($before < $amount) {
            return ['status' => 0, 'msg' => '可抵扣团队奖励余额不足'];
        }
        
        // 使用高精度计算
        $after = bcsub($before, $amount, 2);
        
        // 更新会员余额
        Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['team_reward_balance' => $after]);
        
        // 记录日志
        $logData = [
            'aid' => $aid,
            'mid' => $mid,
            'type' => 1, // 1=团队分红扣减
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'related_id' => $order_id,
            'related_type' => $order_id > 0 ? 'order' : '',
            'remark' => $remark,
            'createtime' => time()
        ];
        Db::name('team_reward_limit_log')->insert($logData);
        
        // 记录操作日志
        \app\admin\common\System::plog('可抵扣团队奖励余额扣减：会员ID='.$mid.'，扣减金额='.$amount.'，备注='.$remark);
        
        return ['status' => 1, 'msg' => ''];
    }
    
    /**
     * 获取会员可抵扣团队奖励余额
     * @param int $aid 应用ID
     * @param int $mid 会员ID
     * @return float
     */
    public static function getTeamRewardBalance($aid, $mid) {
        $balance = Db::name('member')->where('aid', $aid)->where('id', $mid)->value('team_reward_balance');
        return $balance ?? 0;
    }

    /**
     * 新版九星升级奖励发放（支持分层配置）
     */
    public static function getjieidanpidNew($aid,$mid,$totalprice,$xingji,$orderid,$oldxingji,$jiuxingsetinfo)
    {
        \think\facade\Log::write("=== 九星奖励发放开始 ===");
        \think\facade\Log::write("应用ID: " . $aid . ", 用户ID: " . $mid . ", 订单金额: " . $totalprice);
        \think\facade\Log::write("新星级: " . $xingji . ", 旧星级: " . $oldxingji . ", 订单ID: " . $orderid);
        \think\facade\Log::write("九星功能状态: " . ($jiuxingsetinfo['jiuxing_status'] ?? '未设置'));
        
        if($jiuxingsetinfo['jiuxing_status'] == 1)
        {
            \think\facade\Log::write("九星功能已开启");
            if ($xingji > $oldxingji && $xingji > 0) {
                \think\facade\Log::write("检测到星级升级，从" . $oldxingji . "星升级到" . $xingji . "星");
                // 使用分层配置计算奖励
                $layerConfig = $jiuxingsetinfo['layer_config'] ?? [];
                
                // 检查是否启用固定奖励模式
                $rewardMode = $jiuxingsetinfo['reward_mode'] ?? 'percentage';
                $fixedRewardConfig = $jiuxingsetinfo['fixed_reward_config'] ?? [];
                
                // 为每个升级的星级发放奖励
                for($level = $oldxingji + 1; $level <= $xingji; $level++) {
                    $rewardRate = 10; // 默认奖励比例
                    $layerMoney = 1000; // 默认层级金额
                    $fixedReward = 0; // 固定奖励金额
                    
                    if(isset($layerConfig[$level])) {
                        $rewardRate = $layerConfig[$level]['jiangli'] ?? 10;
                        $layerMoney = $layerConfig[$level]['xiaofeimoney'] ?? 1000;
                    }
                    
                    // 获取固定奖励金额
                    if($rewardMode == 'fixed' && isset($fixedRewardConfig[$level])) {
                        $fixedReward = floatval($fixedRewardConfig[$level]);
                    }
                    
                    if($level == 1) {
                        // 1星奖励：基于订单金额或固定奖励
                        $xiadan_member = Db::name('member')->field('id,jiedianpid')->where('aid',$aid)->where('id',$mid)->find(); 
                        $parentid = $xiadan_member['jiedianpid'];
                        
                        // 日志调试
                        \think\facade\Log::write("=== 1星奖励调试开始 ===");
                        \think\facade\Log::write("下单用户ID: " . $mid);
                        \think\facade\Log::write("下单用户接点上级ID: " . $parentid);
                        \think\facade\Log::write("订单金额: " . $totalprice);
                        \think\facade\Log::write("奖励模式: " . $rewardMode);
                        
                        if($parentid > 0) {
                            $parent_member = Db::name('member')->field('id,jiedianpid,xingji,nickname')->where('aid',$aid)->where('id',$parentid)->find(); 
                            if(!empty($parent_member)){
                                \think\facade\Log::write("接点上级信息: ID=" . $parent_member['id'] . ", 昵称=" . $parent_member['nickname'] . ", 星级=" . $parent_member['xingji']);
                                
                                // 计算奖励金额
                                if($rewardMode == 'fixed' && $fixedReward > 0) {
                                    $jiangli = $fixedReward;
                                    \think\facade\Log::write("使用固定奖励: " . $jiangli . "元");
                                } else {
                                    $jiangli = $totalprice * $rewardRate * 0.01;
                                    \think\facade\Log::write("使用比例奖励: " . $jiangli . "元（" . $rewardRate . "%）");
                                }
                                
                                if($parent_member['xingji'] > 0){
                                    \think\facade\Log::write("✅ 上级星级>0，发放奖励");
                                    // 使用九星专用佣金增加方法
                                    if($rewardMode == 'fixed') {
                                        $remark = '九星'.$level.'星固定奖励'.$jiangli.'元';
                                    } else {
                                        $remark = '九星'.$level.'星奖励，下级消费'.$totalprice.'元，奖励'.$jiangli.'元（'.$rewardRate.'%）';
                                    }
                                    $result = self::addJiuxingCommission($aid, $parentid, $mid, $jiangli, $remark);
                                    \think\facade\Log::write("奖励发放结果: " . json_encode($result));
                                }else{
                                    \think\facade\Log::write("❌ 上级星级=0，记录损失日志");
                                    $data=[
                                        'aid'=>$aid,
                                        'mid'=>$parentid,
                                        'formid'=>$mid,
                                        'money'=>$jiangli,
                                        'afterxingji'=>$level,
                                        'xingji'=>$parent_member['xingji'],
                                        'createtime'=>time()
                                    ];
                                    Db::name('member_xingjilog')->insert($data);
                                }
                            } else {
                                \think\facade\Log::write("❌ 接点上级用户不存在");
                            }
                        } else {
                            \think\facade\Log::write("❌ 没有接点上级");
                        }
                    } else {
                        // 2-9星奖励：基于层级金额或固定奖励
                        if($rewardMode == 'fixed' && $fixedReward > 0) {
                            $jiangli = $fixedReward;
                        } else {
                            $jiangli = $layerMoney * $rewardRate * 0.01;
                        }
                        
                        $memberlist = self::getdownjieidanparent($aid,$mid,1,[],$level,[]);
                        
                        foreach($memberlist as $key=>$parentid) {
                            $parent_member = Db::name('member')->field('id,nickname,jiedianpid,xingji')->where('aid',$aid)->where('id',$parentid)->find(); 
                            if(!empty($parent_member)) {
                                if($parent_member['xingji'] >= $level) {
                                    // 有足够星级，发放奖励
                                    if($rewardMode == 'fixed') {
                                        $remark = '九星'.$level.'星升级固定奖励'.$jiangli.'元';
                                    } else {
                                        $remark = '九星'.$level.'星升级奖励，层级金额'.$layerMoney.'元，奖励'.$jiangli.'元（'.$rewardRate.'%）';
                                    }
                                    self::addJiuxingCommission($aid, $parentid, $mid, $jiangli, $remark);
                                    break; // 找到有效上级，停止向上查找
                                } else {
                                    // 星级不足，记录损失
                                    $data=[
                                        'aid'=>$aid,
                                        'mid'=>$parentid,
                                        'formid'=>$mid,
                                        'money'=>$jiangli,
                                        'afterxingji'=>$level,
                                        'xingji'=>$parent_member['xingji'],
                                        'createtime'=>time()
                                    ];
                                    Db::name('member_xingjilog')->insert($data);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 检查用户复购情况
     */
    public static function checkFugouRequirement($aid, $mid, $xingji, $jiuxingsetinfo)
    {
        if(!isset($jiuxingsetinfo['layer_config'][$xingji]['fugou_money'])) {
            return true; // 没有复购要求
        }
        
        $fugouMoney = $jiuxingsetinfo['layer_config'][$xingji]['fugou_money'];
        if($fugouMoney <= 0) {
            return true; // 复购金额为0，无要求
        }
        
        // 获取检查周期
        $checkPeriod = $jiuxingsetinfo['fugou_check_period'] ?? 'month';
        $startTime = 0;
        
        switch($checkPeriod) {
            case 'month':
                $startTime = strtotime(date('Y-m-01 00:00:00'));
                break;
            case 'quarter':
                $currentMonth = date('n');
                $quarterStartMonth = (ceil($currentMonth / 3) - 1) * 3 + 1;
                $startTime = strtotime(date('Y-' . $quarterStartMonth . '-01 00:00:00'));
                break;
            case 'year':
                $startTime = strtotime(date('Y-01-01 00:00:00'));
                break;
        }
        
        // 计算周期内的消费金额
        $upgradeBase = $jiuxingsetinfo['upgrade_base'] ?? 'order_amount';
        
        if($upgradeBase == 'order_amount') {
            // 按下单金额计算
            $periodConsume = Db::name('shop_order_goods')
                ->alias('og')
                ->join('shop_product p', 'og.proid = p.id')
                ->where('og.aid', $aid)
                ->where('og.mid', $mid)
                ->where('og.status', 'in', '1,2,3')
                ->where('og.createtime', '>=', $startTime)
                ->where('p.is_yeji', 1)
                ->sum('og.totalprice');
        } else {
            // 按成本或利润计算，需要逐条计算
            $orderGoods = Db::name('shop_order_goods')
                ->alias('og')
                ->join('shop_product p', 'og.proid = p.id')
                ->where('og.aid', $aid)
                ->where('og.mid', $mid)
                ->where('og.status', 'in', '1,2,3')
                ->where('og.createtime', '>=', $startTime)
                ->where('p.is_yeji', 1)
                ->field('og.price, og.num, og.totalprice, p.cost_price')
                ->select()->toArray();
                
            $periodConsume = 0;
            foreach($orderGoods as $item) {
                switch($upgradeBase) {
                    case 'cost':
                        $amount = ($item['cost_price'] ?? 0) * $item['num'];
                        break;
                    case 'profit':
                        $costPrice = $item['cost_price'] ?? 0;
                        $profit = ($item['price'] - $costPrice) * $item['num'];
                        $amount = max(0, $profit);
                        break;
                    default:
                        $amount = $item['totalprice'];
                        break;
                }
                $periodConsume += $amount;
            }
        }
        
        return $periodConsume >= $fugouMoney;
    }
    
    /**
     * 计算市场业绩（按流水）
     * @param int $aid 应用ID
     * @param int $mid 会员ID
     * @return float 市场业绩
     */
    public static function getMarketTurnover($aid, $mid)
    {
        // 获取该会员及其所有下级的订单总额
        $downmids = self::getdownmids($aid, $mid, 0, 0);
        $downmids[] = $mid; // 包含自己
        
        \think\facade\Log::write('计算市场流水 - 应用ID:'.$aid.' 会员ID:'.$mid.' 包含下级会员IDs:'.implode(',', $downmids));
        
        $totalTurnover = Db::name('shop_order_goods')
            ->alias('og')
            ->join('shop_order o', 'og.orderid = o.id')
            ->where('o.aid', $aid)
            ->where('og.mid', 'in', implode(',', $downmids))
            ->where('og.status', 'in', '1,2,3')
            ->sum('og.totalprice');
            
        \think\facade\Log::write('市场流水计算结果 - 会员ID:'.$mid.' 总流水:'.$totalTurnover.'分 ('.$totalTurnover/10000 .'万元)');
        
        return $totalTurnover ?: 0;
    }
    
    /**
     * 计算市场利润
     * @param int $aid 应用ID
     * @param int $mid 会员ID
     * @return float 市场利润
     */
    public static function getMarketProfit($aid, $mid)
    {
        // 获取该会员及其所有下级的订单利润
        $downmids = self::getdownmids($aid, $mid, 0, 0);
        $downmids[] = $mid; // 包含自己
        
        \think\facade\Log::write('计算市场利润 - 应用ID:'.$aid.' 会员ID:'.$mid.' 包含下级会员IDs:'.implode(',', $downmids));
        
        $totalProfit = Db::name('shop_order_goods')
            ->alias('og')
            ->join('shop_order o', 'og.orderid = o.id')
            ->join('shop_product p', 'og.proid = p.id')
            ->where('o.aid', $aid)
            ->where('og.mid', 'in', implode(',', $downmids))
            ->where('og.status', 'in', '1,2,3')
            ->field('SUM(og.totalprice - p.cost_price * og.num) as total_profit')
            ->find()['total_profit'] ?? 0;
            
        \think\facade\Log::write('市场利润计算结果 - 会员ID:'.$mid.' 总利润:'.$totalProfit.'分 ('.$totalProfit/10000 .'万元)');
        
        return $totalProfit ?: 0;
    }

    /**
     * 获取滑落目标区域
     * @param int $aid 站点ID
     * @param int $pid 推荐人ID
     * @param string $areaType 区域类型：daqu=大区，xiaoqu=小区
     * @param array $config 配置信息
     * @return int 目标区域的接点人ID，如果找不到则返回推荐人ID
     */
    private static function getSlideTargetArea($aid, $pid, $areaType, $config, &$areaReason = null)
    {
        try {
            // 获取推荐人的所有直接接点下级
            $directJiedianMembers = Db::name('member')
                ->where('aid', $aid)
                ->where('jiedianpid', $pid)
                ->field('id')
                ->select()
                ->toArray();
            
            if(empty($directJiedianMembers)) {
                // 没有接点下级，返回推荐人自己
                return $pid;
            }
            
            // 如果只有一个接点下级，大区和小区都是它
            if(count($directJiedianMembers) == 1) {
                $singleMember = Db::name('member')->where('aid', $aid)->where('id', $directJiedianMembers[0]['id'])->field('id,nickname,tel')->find();
                $singleMemberName = $singleMember ? ($singleMember['nickname'] ?: ($singleMember['tel'] ?: 'ID:'.$directJiedianMembers[0]['id'])) : 'ID:'.$directJiedianMembers[0]['id'];
                $areaTypeName = ($areaType == 'daqu') ? '大区' : '小区';
                $areaReason = '选择'.$areaTypeName.'：'.$singleMemberName.'（只有一个接点下级）';
                return $directJiedianMembers[0]['id'];
            }
            
            // 计算每个接点下级的区域业绩和“整条线下面”的节点人数
            $areaPerformances = [];
            $calculationType = $config['hualuo_performance_type'] ?? 'turnover';
            
            foreach($directJiedianMembers as $member) {
                $areaMid = $member['id'];
                
                // 获取该区域的团队业绩
                $areaPerformance = self::calculateAreaPerformance($aid, $areaMid, $calculationType);
                
                // 获取该区域“线下面所有节点”的数量（用于平衡分配）
                // 使用 path 字段，统计所有把当前节点当作路径中一环的成员数量（不含自己）
                $areaChildCount = Db::name('member')
                    ->where('aid', $aid)
                    ->where(function($query) use ($areaMid) {
                        $query->whereOr([
                            ['path', 'like', "%,{$areaMid},%"],  // 中间节点
                            ['path', 'like', "{$areaMid},%"],    // 开头
                            ['path', 'like', "%,{$areaMid}"]     // 结尾
                        ]);
                    })
                    ->count();
                
                $areaPerformances[] = [
                    'member_id' => $areaMid,
                    'performance' => $areaPerformance,
                    'child_count' => $areaChildCount
                ];
            }
            
            // 按业绩降序排序
            usort($areaPerformances, function($a, $b) {
                $performanceCompare = $b['performance'] <=> $a['performance'];
                if ($performanceCompare !== 0) {
                    return $performanceCompare;
                }
                // 业绩相同时，按接点下级数量升序排序（平衡分配）
                $childCountCompare = $a['child_count'] <=> $b['child_count'];
                if ($childCountCompare !== 0) {
                    return $childCountCompare;
                }
                // 业绩和下级数量都相同时，按member_id升序排序，确保排序结果稳定
                return $a['member_id'] <=> $b['member_id'];
            });
            
            if($areaType == 'daqu') {
                // 大区：业绩最高的区域
                // 如果业绩相同，选择接点下级数量最少的区域（平衡分配）
                $selectedArea = $areaPerformances[0];
                $maxPerformance = $selectedArea['performance'];
                
                // 获取选中区域的接点人信息
                $selectedMember = Db::name('member')->where('aid', $aid)->where('id', $selectedArea['member_id'])->field('id,nickname,tel')->find();
                $selectedMemberName = $selectedMember ? ($selectedMember['nickname'] ?: ($selectedMember['tel'] ?: 'ID:'.$selectedArea['member_id'])) : 'ID:'.$selectedArea['member_id'];
                
                // 检查是否有多个区域业绩相同
                $samePerformanceAreas = array_filter($areaPerformances, function($area) use ($maxPerformance) {
                    return $area['performance'] == $maxPerformance;
                });
                
                if(count($samePerformanceAreas) > 1) {
                    // 多个区域业绩相同，按接点下级数量平衡分配
                    if($maxPerformance > 0) {
                        $areaReason = '选择大区：'.$selectedMemberName.'（业绩相同均为'.number_format($maxPerformance/100, 2).'元，该区域接点下级数量最少'.$selectedArea['child_count'].'人，平衡分配）';
                    } else {
                        $areaReason = '选择大区：'.$selectedMemberName.'（所有区域业绩均为0元，该区域接点下级数量最少'.$selectedArea['child_count'].'人，平衡分配）';
                    }
                } else {
                    // 业绩最高的区域
                    if($maxPerformance > 0) {
                        $areaReason = '选择大区：'.$selectedMemberName.'（业绩最高'.number_format($maxPerformance/100, 2).'元）';
                    } else {
                        $areaReason = '选择大区：'.$selectedMemberName.'（所有区域业绩均为0元，该区域接点下级数量最少'.$selectedArea['child_count'].'人，平衡分配）';
                    }
                }
                
                return $selectedArea['member_id'];
            } elseif($areaType == 'xiaoqu') {
                // 小区：除大区外的其他区域，优先选择业绩最小的区域
                if(count($areaPerformances) > 1) {
                    // 返回业绩最小的区域（最后一个）
                    $selectedArea = $areaPerformances[count($areaPerformances) - 1];
                    $minPerformance = $selectedArea['performance'];
                    
                    // 获取选中区域的接点人信息
                    $selectedMember = Db::name('member')->where('aid', $aid)->where('id', $selectedArea['member_id'])->field('id,nickname,tel')->find();
                    $selectedMemberName = $selectedMember ? ($selectedMember['nickname'] ?: ($selectedMember['tel'] ?: 'ID:'.$selectedArea['member_id'])) : 'ID:'.$selectedArea['member_id'];
                    
                    // 检查是否有多个区域业绩相同
                    $samePerformanceAreas = array_filter($areaPerformances, function($area) use ($minPerformance) {
                        return $area['performance'] == $minPerformance;
                    });
                    
                    if(count($samePerformanceAreas) > 1) {
                        // 多个区域业绩相同，按接点下级数量平衡分配
                        if($minPerformance > 0) {
                            $areaReason = '选择小区：'.$selectedMemberName.'（业绩相同均为'.number_format($minPerformance/100, 2).'元，该区域接点下级数量最少'.$selectedArea['child_count'].'人，平衡分配）';
                        } else {
                            $areaReason = '选择小区：'.$selectedMemberName.'（所有区域业绩均为0元，该区域接点下级数量最少'.$selectedArea['child_count'].'人，平衡分配）';
                        }
                    } else {
                        // 业绩最小的区域
                        if($minPerformance > 0) {
                            $areaReason = '选择小区：'.$selectedMemberName.'（业绩最低'.number_format($minPerformance/100, 2).'元）';
                        } else {
                            $areaReason = '选择小区：'.$selectedMemberName.'（所有区域业绩均为0元，该区域接点下级数量最少'.$selectedArea['child_count'].'人，平衡分配）';
                        }
                    }
                    
                    return $selectedArea['member_id'];
                } else {
                    // 只有一个区域时，返回该区域
                    return $areaPerformances[0]['member_id'];
                }
            }
            
            return $pid;
            
        } catch (\Exception $e) {
            \think\facade\Log::write('getSlideTargetArea异常：' . $e->getMessage());
            return $pid;
        }
    }

    /**
     * 计算区域业绩（单个接点下级及其团队的业绩）
     * 注意：这里计算的是接点业绩（基于jiedianpid关系），不是推荐业绩（pid关系）
     * 算法与小市场业绩计算保持一致（ApiAgent::teamAgent方法）
     * @param int $aid 站点ID
     * @param int $areaMid 区域负责人ID（接点下级）
     * @param string $calculationType 计算类型：turnover=流水，profit=利润
     * @return float 区域业绩
     */
    private static function calculateAreaPerformance($aid, $areaMid, $calculationType = 'turnover')
    {
        try {
            // 获取该接点下级及其所有接点团队成员（基于jiedianpid关系）
            $area_team_members = \app\admin\common\MemberRelationService::getJiedianDownMembers($aid, $areaMid, 0, [], true);
            
            if (empty($area_team_members)) {
                return 0;
            }
            
            $area_performance = 0;
            
            // 根据计算类型计算商品订单业绩（与小市场业绩算法完全一致）
            if ($calculationType === 'profit') {
                // 按利润统计：计算该分支的商品订单利润（从订单商品表计算，只计算is_yeji=1的记录）
                // 使用 totalprice 字段，不是 real_totalprice
                $branch_shop_profit_sql = "SELECT SUM((totalprice - cost_price * num)) as amount FROM ".table_name('shop_order_goods')." WHERE aid=".$aid." AND mid IN (".implode(',', $area_team_members).") AND status IN (1,2,3) AND is_yeji = 1";
                $branch_shop_profit = Db::query($branch_shop_profit_sql);
                $area_performance += ($branch_shop_profit[0]['amount'] ?? 0);
            } else {
                // 按流水统计：计算该分支的商品订单业绩（需要关联订单商品表检查is_yeji字段）
                // 使用 og.totalprice 字段，不是 real_totalprice
                $branch_shop_sql = "SELECT SUM(og.totalprice) as amount FROM ".table_name('shop_order_goods')." og 
                    INNER JOIN ".table_name('shop_order')." o ON og.orderid = o.id 
                    WHERE og.aid=".$aid." AND o.mid IN (".implode(',', $area_team_members).") AND og.status IN (1,2,3) AND og.is_yeji = 1";
                $branch_shop = Db::query($branch_shop_sql);
                $area_performance += ($branch_shop[0]['amount'] ?? 0);
            }
            
            // 计算该分支的买单订单业绩（买单没有成本概念，按全额计算）
            // 与小市场业绩算法保持一致
            $branch_maidan_sql = "SELECT SUM(money) as amount FROM ".table_name('maidan_order')." WHERE aid=".$aid." AND mid IN (".implode(',', $area_team_members).") AND status IN (1,2)";
            $branch_maidan = Db::query($branch_maidan_sql);
            $area_performance += ($branch_maidan[0]['amount'] ?? 0);
            
            // 该区域的总业绩（接点业绩）
            return floatval($area_performance);
            
        } catch (\Exception $e) {
            \think\facade\Log::write('calculateAreaPerformance异常：' . $e->getMessage());
            return 0;
        }
    }
}
