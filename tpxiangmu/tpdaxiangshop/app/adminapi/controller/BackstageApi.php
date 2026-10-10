<?php
/**
 * Vue前端 - 后台统计 API
 * 参考：app/admin/controller/Backstage.php
 * 规范：统一返回 json(['status'=>1, 'msg'=>'', 'data'=>[]])
 */
namespace app\adminapi\controller;
use app\admin\common\PageVisitStatService;
use app\admin\common\PluginDashboardRegistry;
use app\admin\common\MemberOpenidBindService;
use app\admin\common\WithdrawFeeRewardService;
use app\admin\common\WithdrawMultiple;
use think\facade\Db;

class BackstageApi extends Common
{
    private const CPCNPAY_PLUGIN_NAME = 'cpcnpay';
    private const MENDIAN_GROUPBUY_PLUGIN_NAME = 'mendiangroupbuy';

    private function ensureAdminNoticeReadTable(): void
    {
        if ($this->tableExists('admin_notice_read')) {
            return;
        }

        $prefix = config('database.connections.mysql.prefix', '');
        Db::execute("
            CREATE TABLE IF NOT EXISTS `{$prefix}admin_notice_read` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `aid` int unsigned NOT NULL DEFAULT 0,
                `bid` int NOT NULL DEFAULT 0,
                `notice_id` int unsigned NOT NULL DEFAULT 0,
                `admin_uid` int unsigned NOT NULL DEFAULT 0,
                `isread` tinyint(1) NOT NULL DEFAULT 0,
                `readtime` int unsigned NOT NULL DEFAULT 0,
                `remind_count` int unsigned NOT NULL DEFAULT 0,
                `ti_time` int unsigned NOT NULL DEFAULT 0,
                `createtime` int unsigned NOT NULL DEFAULT 0,
                `updatetime` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_notice_admin` (`aid`,`notice_id`,`admin_uid`),
                KEY `idx_admin_status` (`aid`,`admin_uid`,`isread`),
                KEY `idx_notice` (`aid`,`notice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private function getCurrentAdminOrderReadSubQuery(): string
    {
        $this->ensureAdminNoticeReadTable();

        return Db::name('admin_notice_read')
            ->where('aid', aid)
            ->where('admin_uid', intval($this->uid))
            ->where('isread', 1)
            ->field('notice_id')
            ->buildSql();
    }

    private function hydrateCurrentAdminOrderReadState(array $list): array
    {
        if (empty($list)) {
            return $list;
        }

        $this->ensureAdminNoticeReadTable();
        $ids = array_values(array_filter(array_map(function ($item) {
            return intval($item['id'] ?? 0);
        }, $list)));
        if (empty($ids)) {
            return $list;
        }

        $readRows = Db::name('admin_notice_read')
            ->where('aid', aid)
            ->where('admin_uid', intval($this->uid))
            ->where('notice_id', 'in', $ids)
            ->select()
            ->toArray();

        $readMap = [];
        foreach ($readRows as $row) {
            $readMap[intval($row['notice_id'] ?? 0)] = $row;
        }

        foreach ($list as &$item) {
            $readInfo = $readMap[intval($item['id'] ?? 0)] ?? null;
            $item['isread'] = intval($readInfo['isread'] ?? 0);
            $item['readtime'] = intval($readInfo['readtime'] ?? 0);
            $item['remind_count'] = intval($readInfo['remind_count'] ?? 0);
            $item['user_ti_time'] = intval($readInfo['ti_time'] ?? 0);
        }
        unset($item);

        return $list;
    }

    private function markCurrentAdminOrderNoticesRead(array $ids): void
    {
        $this->ensureAdminNoticeReadTable();
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $notices = Db::name('admin_notice')
            ->where('aid', aid)
            ->where('type2', 'shop_order')
            ->where('id', 'in', $ids)
            ->field('id,bid')
            ->select()
            ->toArray();

        $now = time();
        foreach ($notices as $notice) {
            $noticeId = intval($notice['id'] ?? 0);
            if ($noticeId <= 0) {
                continue;
            }

            $where = [
                ['aid', '=', aid],
                ['notice_id', '=', $noticeId],
                ['admin_uid', '=', intval($this->uid)],
            ];
            $exists = Db::name('admin_notice_read')->where($where)->find();
            if ($exists) {
                Db::name('admin_notice_read')->where('id', intval($exists['id']))->update([
                    'isread' => 1,
                    'readtime' => $now,
                    'updatetime' => $now,
                ]);
                continue;
            }

            Db::name('admin_notice_read')->insert([
                'aid' => aid,
                'bid' => intval($notice['bid'] ?? 0),
                'notice_id' => $noticeId,
                'admin_uid' => intval($this->uid),
                'isread' => 1,
                'readtime' => $now,
                'remind_count' => 0,
                'ti_time' => 0,
                'createtime' => $now,
                'updatetime' => $now,
            ]);
        }
    }

    private function normalizeOrderReminderSound(string $sound, string $defaultSound = '/static/chat/13202.wav'): string
    {
        $sound = trim($sound);
        $legacy = [
            '',
            '/static/chat/default.mp3',
            '/static/chat/default.wav',
            '/static/chat/default.m4a',
        ];
        if ($sound === '' || in_array($sound, $legacy, true)) {
            return $defaultSound;
        }
        return $sound;
    }

    private function getOrderReminderSoundConfig(): array
    {
        $set = Db::name('admin_set')->where('aid', aid)->find();
        if (!$set) {
            \app\admin\common\System::initaccount(aid);
            $set = Db::name('admin_set')->where('aid', aid)->find();
        }

        $defaultSound = '/static/chat/13202.wav';
        $sound = $this->normalizeOrderReminderSound((string)($set['qiang_video'] ?? ''), $defaultSound);
        $strongSound = $this->normalizeOrderReminderSound((string)($set['qiangti_video'] ?? ''), $defaultSound);

        return [
            'set' => $set ?: [],
            'sound' => $sound !== '' ? $sound : $defaultSound,
            'strong_sound' => $strongSound !== '' ? $strongSound : $defaultSound,
            'qiang_status' => intval($set['qiang_status'] ?? 1),
            'jiange_ci' => intval($set['jiange_ci'] ?? 0),
            'jiange_min' => intval($set['jiange_min'] ?? 0),
        ];
    }

    private function formatOrderReminderNotice(?array $row): array
    {
        if (!$row) {
            return [];
        }

        $title = trim((string)($row['title'] ?? '订单提醒'));
        $desc = '';
        $content = $row['content'] ?? '';
        if (is_string($content) && $content !== '') {
            $parsed = json_decode($content, true);
            if (is_array($parsed)) {
                $title = trim((string)($parsed['keyword4'] ?? $parsed['keyword1'] ?? $parsed['first'] ?? $title));
                $desc = trim((string)($parsed['keyword2'] ?? $parsed['keyword3'] ?? $parsed['remark'] ?? ''));
            }
        }

        return [
            'id' => intval($row['id'] ?? 0),
            'title' => $title !== '' ? $title : '订单提醒',
            'desc' => $desc,
            'ordernum' => (string)($row['ordernum'] ?? ''),
            'createtime' => intval($row['createtime'] ?? 0),
        ];
    }

    private function getLatestUnreadOrderNotice(): array
    {
        $this->ensureAdminNoticeReadTable();
        $readSubQuery = $this->getCurrentAdminOrderReadSubQuery();
        $row = Db::name('admin_notice')
            ->where('aid', aid)
            ->where('type2', 'shop_order')
            ->whereRaw("id NOT IN {$readSubQuery}")
            ->order('id desc')
            ->find();

        return $this->formatOrderReminderNotice($row ?: null);
    }

    private function getDueOrderReminderRows(int $intervalMinutes, int $maxCount, bool $single = false): array
    {
        $this->ensureAdminNoticeReadTable();
        $prefix = config('database.connections.mysql.prefix', '');
        $uid = intval($this->uid);
        $intervalMinutes = max(1, intval($intervalMinutes));
        $maxCount = max(1, intval($maxCount));

        $query = Db::name('admin_notice')
            ->alias('notice')
            ->leftJoin("{$prefix}admin_notice_read notice_read", "notice_read.aid = notice.aid AND notice_read.notice_id = notice.id AND notice_read.admin_uid = {$uid}")
            ->where('notice.aid', aid)
            ->where('notice.type2', 'shop_order')
            ->whereRaw('IFNULL(notice_read.isread,0)=0')
            ->whereRaw('IFNULL(notice_read.remind_count,0) < ' . $maxCount)
            ->whereRaw("FROM_UNIXTIME(CASE WHEN IFNULL(notice_read.ti_time,0) > 0 THEN notice_read.ti_time ELSE notice.createtime END) <= NOW()-INTERVAL {$intervalMinutes} MINUTE")
            ->field('notice.*')
            ->order('notice.id desc');

        if ($single) {
            $row = $query->find();
            return $row ? [$row] : [];
        }

        return $query->select()->toArray();
    }

    private function touchCurrentAdminOrderReminderRows(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $this->ensureAdminNoticeReadTable();
        $now = time();
        foreach ($rows as $notice) {
            $noticeId = intval($notice['id'] ?? 0);
            if ($noticeId <= 0) {
                continue;
            }

            $where = [
                ['aid', '=', aid],
                ['notice_id', '=', $noticeId],
                ['admin_uid', '=', intval($this->uid)],
            ];
            $exists = Db::name('admin_notice_read')->where($where)->find();
            if ($exists) {
                Db::name('admin_notice_read')->where('id', intval($exists['id']))->update([
                    'isread' => 0,
                    'remind_count' => intval($exists['remind_count'] ?? 0) + 1,
                    'ti_time' => $now,
                    'updatetime' => $now,
                ]);
                continue;
            }

            Db::name('admin_notice_read')->insert([
                'aid' => aid,
                'bid' => intval($notice['bid'] ?? 0),
                'notice_id' => $noticeId,
                'admin_uid' => intval($this->uid),
                'isread' => 0,
                'readtime' => 0,
                'remind_count' => 1,
                'ti_time' => $now,
                'createtime' => $now,
                'updatetime' => $now,
            ]);
        }
    }

    private function getPluginInstallState(string $pluginName): array
    {
        $state = [
            'installed' => 0,
            'enabled' => 0,
        ];
        if ($pluginName === '') {
            return $state;
        }

        try {
            if ($this->tableExists('admin_plugin_version')) {
                $exists = Db::name('admin_plugin_version')
                    ->where('plugin_name', $pluginName)
                    ->find();
                if (!empty($exists)) {
                    $state['installed'] = 1;
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            if ($this->tableExists('admin_plugin_menu')) {
                $menuQuery = Db::name('admin_plugin_menu')
                    ->where('plugin_name', $pluginName);
                if ($this->tableColumnExists('admin_plugin_menu', 'aid')) {
                    $menuQuery->where('aid', aid);
                }

                $menuRows = $menuQuery->field('status')->select()->toArray();
                if (!empty($menuRows)) {
                    $state['installed'] = 1;
                    foreach ($menuRows as $row) {
                        if (intval($row['status'] ?? 0) === 1) {
                            $state['enabled'] = 1;
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return $state;
    }

    private function stripDeprecatedTextsetKeys($textset)
    {
        if (!is_array($textset)) {
            return [];
        }
        foreach ([
            '列表导航',
            '列表名称',
            '列表1',
            '列表2',
            '备注信息',
            '审核中',
            '已同意',
            '驳回',
            '退款订单',
            '取消',
            '通过',
            '审核通过,待退货',
            '申请退款',
            '退款中',
            '已退款',
            '退款申请已驳回',
            '查看退款',
        ] as $textKey) {
            unset($textset[$textKey]);
        }
        return $textset;
    }

    private function mergeDefaultTextset($textset)
    {
        $textset = $this->stripDeprecatedTextsetKeys($textset);
        $baseTextset = [
            '余额' => '余额',
            '积分' => '积分',
            '代币A' => '代币A',
            '积分3' => '积分3',
            '佣金' => '佣金',
            '优惠券' => '优惠券',
            '会员' => '会员',
            '一级' => '销售一组',
            '二级' => '销售二组',
        ];
        $extraTextset = [
            '团队分红' => '团队分红',
            '股东分红' => '股东分红',
            '区域代理分红' => '区域代理分红',
            '我的团队' => '我的团队',
            '分销订单' => '分销订单',
            '自定义表单' => '自定义表单',
            '推荐人' => '推荐人',
            '现金券' => '成就值',
            '奖励队列' => '排队中',
            '奖励记录' => '已完成',
            '贡献值' => '贡献值',
            '配资' => '配资',
            '消费币' => '消费币',
            '绿积分' => '绿积分',
            '黄积分' => '金元宝',
            '虚拟账号积分' => '虚拟账号积分',
            '创业值' => '创业值',
            '收益池' => '收益池',
            '让利' => '让利',
        ];
        $orderTextset = [
            '我的订单' => '我的订单',
            '查看全部' => '查看全部',
            '全部' => '全部',
            '待付款' => '待付款',
            '待发货' => '待发货',
            '待提货' => '待提货',
            '待收货' => '待收货',
            '已完成' => '已完成',
            '退款/售后' => '退款/售后',
        ];
        $avatarTextset = [
            'member_default_avatar' => PRE_URL . '/static/img/touxiang.png',
        ];
        $defaults = array_merge($baseTextset, $extraTextset, $orderTextset, $avatarTextset);
        if (!is_array($textset)) {
            $textset = [];
        }
        $normalizedTextset = [];
        foreach ($defaults as $key => $value) {
            $normalizedTextset[$key] = (isset($textset[$key]) && $textset[$key] !== '') ? $textset[$key] : $value;
        }
        foreach ($textset as $key => $value) {
            if (!array_key_exists($key, $normalizedTextset)) {
                $normalizedTextset[$key] = $value;
            }
        }
        return $normalizedTextset;
    }

    private function ensureCommissionWithdrawNeedPaycodeColumn()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'comwithdraw_need_paycode')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `comwithdraw_need_paycode` tinyint(1) NOT NULL DEFAULT 0 COMMENT '佣金提现是否需要上传收款码：0否 1是' AFTER `comwithdraw`");
            }
        } catch (\Throwable $e) {
        }
    }

    private function ensureAreaFenhongDifferentialColumn()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'areafenhong_differential')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `areafenhong_differential` tinyint(1) NOT NULL DEFAULT 0 COMMENT '区域代理级差分红：0否 1是'");
            }
        } catch (\Throwable $e) {
        }
    }

    private function ensureMemberRegisterNotelColumn()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'member_register_notel')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `member_register_notel` tinyint(1) NOT NULL DEFAULT 0 COMMENT '注册支持账号(无需手机号)：0否 1是'");
            }
        } catch (\Throwable $e) {
        }
    }

    private function ensureMemberAccountCancelColumn()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'member_account_cancel_enabled')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `member_account_cancel_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '会员注销账号入口：0关闭 1开启' AFTER `login_mast`");
            }
        } catch (\Throwable $e) {
        }
    }

    private function ensureMemberRegisterEnabledColumn()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'member_register_enabled')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `member_register_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '会员开放注册：0关闭 1开启' AFTER `login_mast`");
            }
        } catch (\Throwable $e) {
        }
    }

    private function ensureXiaofeibpayColumn()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'xiaofeibpay')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `xiaofeibpay` tinyint(1) NOT NULL DEFAULT 0 COMMENT '消费币支付总开关：0关闭 1开启' AFTER `heipay`");
            }
        } catch (\Throwable $e) {
        }
    }

    private function ensureAlipayEnabledColumn()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'alipay_enabled')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `alipay_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '支付宝支付总开关：0关闭 1开启' AFTER `wxpay_enabled`");
            }
        } catch (\Throwable $e) {
            // 保存时会返回明确的升级提示，读取设置仍按默认开启兼容旧库。
        }
    }

    private function ensureXiaofeibTransferColumns()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'xiaofeib_transfer')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `xiaofeib_transfer` tinyint(1) NOT NULL DEFAULT 0 COMMENT '消费币转账开关：0关闭 1开启' AFTER `xiaofeibpay`");
            }
        } catch (\Throwable $e) {
        }
        try {
            if (!$this->tableColumnExists('admin_set', 'xiaofeib_tansrate')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `xiaofeib_tansrate` decimal(10,4) NOT NULL DEFAULT 0 COMMENT '消费币转账手续费比例' AFTER `xiaofeib_transfer`");
            }
        } catch (\Throwable $e) {
        }
        try {
            if (!$this->tableColumnExists('admin_set', 'xiaofeib_transfer_need_paypwd')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `xiaofeib_transfer_need_paypwd` tinyint(1) NOT NULL DEFAULT 0 COMMENT '消费币转账需支付密码：0否 1是' AFTER `xiaofeib_tansrate`");
            }
        } catch (\Throwable $e) {
        }
        try {
            if (!$this->tableColumnExists('admin_set', 'xiaofeib_transfer_target_type')) {
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `xiaofeib_transfer_target_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '消费币转账识别方式：1手机号 2用户ID' AFTER `xiaofeib_transfer_need_paypwd`");
            }
        } catch (\Throwable $e) {
        }
    }

    private function ensureXianjinquanTransferTargetTypeColumn()
    {
        $tableName = config('database.connections.mysql.prefix', '') . 'admin_set';
        try {
            if (!$this->tableColumnExists('admin_set', 'xianjinquan_transfer_target_type')) {
                $afterSql = $this->tableColumnExists('admin_set', 'xianjinquan_transfer_need_paypwd') ? " AFTER `xianjinquan_transfer_need_paypwd`" : "";
                Db::execute("ALTER TABLE `{$tableName}` ADD COLUMN `xianjinquan_transfer_target_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '现金券转账识别方式：1手机号 2用户ID'{$afterSql}");
            }
        } catch (\Throwable $e) {
        }
    }

    private function normalizeDashboardScene(string $scene): string
    {
        $scene = preg_replace('/[^a-zA-Z0-9_\-]/', '', $scene);
        $scene = substr(trim(strval($scene)), 0, 64);
        return $scene !== '' ? $scene : 'stats';
    }

    private function getDashboardDataScopeFromInput(): string
    {
        $scope = input('post.data_scope/s', '');
        if ($scope === '') {
            $scope = input('post.dataScope/s', 'platform');
        }
        return $this->normalizeDashboardDataScope($scope);
    }

    private function normalizeDashboardDataScope($scope): string
    {
        $scope = strtolower(trim(strval($scope)));
        return in_array($scope, ['all', 'all_bids', 'all_business', 'platform_and_merchants'], true) ? 'all' : 'platform';
    }

    private function shouldUseAllBidData(string $dataScope): bool
    {
        return intval($this->bid) === 0 && $this->normalizeDashboardDataScope($dataScope) === 'all';
    }

    private function dashboardTable(string $table, string $dataScope)
    {
        return $this->applyDashboardBidScope(Db::name($table), $table, $dataScope);
    }

    private function applyDashboardBidScope($query, string $table, string $dataScope, string $alias = '')
    {
        if (!$this->tableColumnExists($table, 'bid')) {
            return $query;
        }
        if ($this->shouldUseAllBidData($dataScope)) {
            return $query;
        }

        $field = $alias !== '' ? $alias . '.bid' : 'bid';
        return $query->where($field, intval($this->bid));
    }

    private function ensureDashboardLayoutTable(): void
    {
        if ($this->tableExists('admin_dashboard_layout')) {
            return;
        }

        $prefix = config('database.connections.mysql.prefix', '');
        Db::execute("
            CREATE TABLE IF NOT EXISTS `{$prefix}admin_dashboard_layout` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `aid` int unsigned NOT NULL DEFAULT 0,
                `bid` int NOT NULL DEFAULT 0,
                `admin_uid` int unsigned NOT NULL DEFAULT 0,
                `scene` varchar(64) NOT NULL DEFAULT 'stats',
                `layout_json` longtext,
                `version` int unsigned NOT NULL DEFAULT 1,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `createtime` int unsigned NOT NULL DEFAULT 0,
                `updatetime` int unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_admin_scene` (`aid`,`bid`,`admin_uid`,`scene`),
                KEY `idx_aid_bid` (`aid`,`bid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台首页驾驶舱DIY布局';
        ");
    }

    public function getDashboardLayout()
    {
        $scene = $this->normalizeDashboardScene(input('post.scene/s', 'stats'));

        try {
            $this->ensureDashboardLayoutTable();
            $row = Db::name('admin_dashboard_layout')
                ->where('aid', aid)
                ->where('bid', bid)
                ->where('admin_uid', intval($this->uid))
                ->where('scene', $scene)
                ->where('status', 1)
                ->find();

            $layout = null;
            if (!empty($row['layout_json'])) {
                $decoded = json_decode(strval($row['layout_json']), true);
                if (is_array($decoded)) {
                    $layout = $decoded;
                }
            }

            return $this->success([
                'scene' => $scene,
                'layout' => $layout,
                'hasCustom' => $layout ? 1 : 0,
                'updatedAt' => intval($row['updatetime'] ?? 0),
            ], '获取成功');
        } catch (\Throwable $e) {
            return $this->error('获取驾驶舱布局失败：' . $e->getMessage());
        }
    }

    public function saveDashboardLayout()
    {
        $scene = $this->normalizeDashboardScene(input('post.scene/s', 'stats'));
        $layout = input('post.layout/a', []);
        if (!is_array($layout) || empty($layout)) {
            return $this->error('布局数据不能为空');
        }

        $encoded = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return $this->error('布局数据格式错误');
        }
        if (strlen($encoded) > 200000) {
            return $this->error('布局数据过大，请减少组件数量');
        }

        try {
            $this->ensureDashboardLayoutTable();
            $now = time();
            $data = [
                'layout_json' => $encoded,
                'version' => intval($layout['version'] ?? 1),
                'status' => 1,
                'admin_uid' => intval($this->uid),
                'updatetime' => $now,
            ];

            $row = Db::name('admin_dashboard_layout')
                ->where('aid', aid)
                ->where('bid', bid)
                ->where('admin_uid', intval($this->uid))
                ->where('scene', $scene)
                ->find();

            if ($row) {
                Db::name('admin_dashboard_layout')->where('id', intval($row['id']))->update($data);
            } else {
                $data['aid'] = aid;
                $data['bid'] = bid;
                $data['scene'] = $scene;
                $data['createtime'] = $now;
                Db::name('admin_dashboard_layout')->insert($data);
            }

            return $this->success([
                'scene' => $scene,
                'layout' => $layout,
                'hasCustom' => 1,
                'updatedAt' => $now,
            ], '保存成功');
        } catch (\Throwable $e) {
            return $this->error('保存驾驶舱布局失败：' . $e->getMessage());
        }
    }

    public function getPluginDashboardData()
    {
        $scene = $this->normalizeDashboardScene(input('post.scene/s', 'stats'));
        $dataScope = $this->getDashboardDataScopeFromInput();

        try {
            $registry = new PluginDashboardRegistry(intval($this->aid), intval($this->bid), $dataScope);
            return $this->success($registry->getDashboardData($scene), '获取成功');
        } catch (\Throwable $e) {
            return $this->error('获取插件驾驶舱数据失败：' . $e->getMessage());
        }
    }

    /**
     * 获取图表数据（30天趋势）
     * 参考：Backstage::welcome() 中的 getdata 部分
     * 
     * @return \think\response\Json
     */
    public function getChartData()
    {
        // 参数验证
        $type = input('post.type', 0, 'intval');
        if (!in_array($type, [1, 2, 3, 4, 5, 6, 7, 8])) {
            return $this->error('参数type错误，必须是1-8之间的数字');
        }
        
        try {
            $dataScope = $this->getDashboardDataScopeFromInput();
            $monthEnd = strtotime(date('Y-m-d', time()-86400));
            $monthStart = $monthEnd - 86400 * 29;
            
            $dataArr = array();
            $dateArr = array();
            $trafficTrend = [];
            if (in_array($type, [6, 7, 8], true)) {
                $metricMap = [
                    6 => 'pv',
                    7 => 'uv',
                    8 => 'vv',
                ];
                $trafficTrend = PageVisitStatService::getTrendData(
                    intval($this->aid),
                    intval($this->bid),
                    $metricMap[$type],
                    $monthStart,
                    30,
                    $this->shouldUseAllBidData($dataScope)
                );
            }
            
            for($i=0; $i<30; $i++){
                $thisDayStart = $monthStart + $i * 86400;
                $thisDayEnd = $monthStart + ($i+1) * 86400;
                $dateArr[] = date('m-d', $thisDayStart);
                
                if($type == 1){
                    // 客户总数（累计）
                    $dataArr[] = 0 + $this->dashboardTable('member', $dataScope)
                        ->where('aid', aid)
                        ->where('createtime', '<', $thisDayEnd)
                        ->count();
                        
                } elseif($type == 2){
                    // 新增客户数（每日）
                    $dataArr[] = 0 + $this->dashboardTable('member', $dataScope)
                        ->where('aid', aid)
                        ->where('createtime', '>=', $thisDayStart)
                        ->where('createtime', '<', $thisDayEnd)
                        ->count();
                        
                } elseif($type == 3){
                    // 收款金额
                    $dataArr[] = 0 + $this->dashboardTable('payorder', $dataScope)
                        ->where('aid', aid)
                        ->where('createtime', '>=', $thisDayStart)
                        ->where('createtime', '<', $thisDayEnd)
                        ->where('paytypeid', 'not in', '1,4')
                        ->where('status', 1)
                        ->sum('money');
                        
                } elseif($type == 4){
                    // 订单金额
                    $dataArr[] = 0 + $this->dashboardTable('shop_order', $dataScope)
                        ->where('aid', aid)
                        ->where('paytime', '>=', $thisDayStart)
                        ->where('paytime', '<', $thisDayEnd)
                        ->where('status', 'in', '1,2,3')
                        ->sum('totalprice');
                        
                } elseif($type == 5){
                    // 订单数
                    $dataArr[] = 0 + $this->dashboardTable('shop_order', $dataScope)
                        ->where('aid', aid)
                        ->where('paytime', '>=', $thisDayStart)
                        ->where('paytime', '<', $thisDayEnd)
                        ->where('status', 'in', '1,2,3')
                        ->count();
                } elseif(in_array($type, [6, 7, 8], true)){
                    $dataArr[] = intval($trafficTrend[$i] ?? 0);
                }
            }
            
            return $this->success([
                'dateArr' => $dateArr,
                'dataArr' => $dataArr
            ], '获取成功');
            
        } catch (\Exception $e) {
            return $this->error('获取图表数据失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取统计卡片数据
     * 参考：Backstage::welcome() 中的统计数据部分
     * 
     * @return \think\response\Json
     */
    public function getStats()
    {
        try {
            $dataScope = $this->getDashboardDataScopeFromInput();
            $lastDayStart = strtotime(date('Y-m-d', time()-86400));
            $lastDayEnd = $lastDayStart + 86400;
            $thisMonthStart = strtotime(date('Y-m-1'));
            $nowtime = time();
            
            // 客户数统计
            $memberCount = 0 + $this->dashboardTable('member', $dataScope)->where('aid', aid)->count();
            $memberThisDayCount = 0 + $this->dashboardTable('member', $dataScope)
                ->where('aid', aid)
                ->where('createtime', '>=', $lastDayEnd)
                ->count();
            $memberLastDayCount = 0 + $this->dashboardTable('member', $dataScope)
                ->where('aid', aid)
                ->where('createtime', '>=', $lastDayStart)
                ->where('createtime', '<', $lastDayEnd)
                ->count();
            $memberThisMonthCount = 0 + $this->dashboardTable('member', $dataScope)
                ->where('aid', aid)
                ->where('createtime', '>=', $thisMonthStart)
                ->where('createtime', '<', $nowtime)
                ->count();
            
            // 订单数统计
            $orderallCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('status', 'in', '1,2,3')
                ->count();
            $orderallThisDayCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('status', 'in', '1,2,3')
                ->where('paytime', '>=', $lastDayEnd)
                ->count();
            $orderallLastDayCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('status', 'in', '1,2,3')
                ->where('paytime', '>=', $lastDayStart)
                ->where('paytime', '<', $lastDayEnd)
                ->count();
            $orderallThisMonthCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('status', 'in', '1,2,3')
                ->where('paytime', '>=', $thisMonthStart)
                ->where('paytime', '<', $nowtime)
                ->count();
            
            // 订单金额统计
            $orderMoneyCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('status', 'in', '1,2,3')
                ->sum('totalprice');
            $orderMoneyThisDayCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('paytime', '>=', $lastDayEnd)
                ->where('status', 'in', '1,2,3')
                ->sum('totalprice');
            $orderMoneyLastDayCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('paytime', '>=', $lastDayStart)
                ->where('paytime', '<', $lastDayEnd)
                ->where('status', 'in', '1,2,3')
                ->sum('totalprice');
            $orderMoneyThisMonthCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('paytime', '>=', $thisMonthStart)
                ->where('paytime', '<', $nowtime)
                ->where('status', 'in', '1,2,3')
                ->sum('totalprice');
            
            // 商品数量统计
            $productCount = 0 + $this->dashboardTable('shop_product', $dataScope)
                ->where('aid', aid)
                ->count();
            $product1Count = 0 + $this->dashboardTable('shop_product', $dataScope)
                ->where('aid', aid)
                ->where('status', 1)
                ->count();
            $product0Count = 0 + $this->dashboardTable('shop_product', $dataScope)
                ->where('aid', aid)
                ->where('status', 0)
                ->count();
            
            // 退款金额统计
            $refundCount = 0 + $this->dashboardTable('shop_order', $dataScope)
                ->where('aid', aid)
                ->where('refund_status', 2)
                ->sum('refund_money');
            $refundThisDayCount = 0 + $this->dashboardTable('shop_refund_order', $dataScope)
                ->where('aid', aid)
                ->where('createtime', '>=', $lastDayEnd)
                ->where('refund_status', 2)
                ->sum('refund_money');
            $refundLastDayCount = 0 + $this->dashboardTable('shop_refund_order', $dataScope)
                ->where('aid', aid)
                ->where('createtime', '>=', $lastDayStart)
                ->where('createtime', '<', $lastDayEnd)
                ->where('refund_status', 2)
                ->sum('refund_money');
            $refundThisMonthCount = 0 + $this->dashboardTable('shop_refund_order', $dataScope)
                ->where('aid', aid)
                ->where('createtime', '>=', $thisMonthStart)
                ->where('createtime', '<', $nowtime)
                ->where('refund_status', 2)
                ->sum('refund_money');

            // 收款金额（仅平台后台显示）
            $payCount = 0; $payThisDayCount = 0; $payLastDayCount = 0; $payThisMonthCount = 0;
            if (bid == 0) {
                $payCount = 0 + $this->dashboardTable('payorder', $dataScope)
                    ->where('aid', aid)
                    ->where('paytypeid', 'not in', '1,4')
                    ->where('status', 1)
                    ->sum('money');
                $payThisDayCount = 0 + $this->dashboardTable('payorder', $dataScope)
                    ->where('aid', aid)
                    ->where('paytypeid', 'not in', '1,4')
                    ->where('status', 1)
                    ->where('paytime', '>=', $lastDayEnd)
                    ->sum('money');
                $payLastDayCount = 0 + $this->dashboardTable('payorder', $dataScope)
                    ->where('aid', aid)
                    ->where('paytypeid', 'not in', '1,4')
                    ->where('status', 1)
                    ->where('paytime', '>=', $lastDayStart)
                    ->where('paytime', '<', $lastDayEnd)
                    ->sum('money');
                $payThisMonthCount = 0 + $this->dashboardTable('payorder', $dataScope)
                    ->where('aid', aid)
                    ->where('paytypeid', 'not in', '1,4')
                    ->where('status', 1)
                    ->where('paytime', '>=', $thisMonthStart)
                    ->where('paytime', '<', $nowtime)
                    ->sum('money');
            }

            // 提现金额（仅平台后台显示，会员余额提现 + 佣金提现）
            $withdrawCount = 0; $withdrawThisDayCount = 0; $withdrawLastDayCount = 0; $withdrawThisMonthCount = 0;
            if (bid == 0) {
                $withdrawCount = 0 + Db::name('member_withdrawlog')->where('aid', aid)->where('status', 3)->sum('money')
                    + Db::name('member_commission_withdrawlog')->where('aid', aid)->where('status', 3)->sum('money');
                $withdrawThisDayCount = 0 + Db::name('member_withdrawlog')->where('aid', aid)->where('status', 3)->where('createtime', '>=', $lastDayEnd)->sum('money')
                    + Db::name('member_commission_withdrawlog')->where('aid', aid)->where('status', 3)->where('createtime', '>=', $lastDayEnd)->sum('money');
                $withdrawLastDayCount = 0 + Db::name('member_withdrawlog')->where('aid', aid)->where('status', 3)->where('createtime', '>=', $lastDayStart)->where('createtime', '<', $lastDayEnd)->sum('money')
                    + Db::name('member_commission_withdrawlog')->where('aid', aid)->where('status', 3)->where('createtime', '>=', $lastDayStart)->where('createtime', '<', $lastDayEnd)->sum('money');
                $withdrawThisMonthCount = 0 + Db::name('member_withdrawlog')->where('aid', aid)->where('status', 3)->where('createtime', '>=', $thisMonthStart)->where('createtime', '<', $nowtime)->sum('money')
                    + Db::name('member_commission_withdrawlog')->where('aid', aid)->where('status', 3)->where('createtime', '>=', $thisMonthStart)->where('createtime', '<', $nowtime)->sum('money');
            }

            // 会员资产（平台维度）
            $totalMemberBalance = number_format($this->dashboardTable('member', $dataScope)->where('aid', aid)->sum('money'), 2, '.', '');
            $totalMemberCommission = number_format($this->dashboardTable('member', $dataScope)->where('aid', aid)->sum('commission'), 2, '.', '');
            $totalMemberAllCommission = number_format($this->dashboardTable('member', $dataScope)->where('aid', aid)->sum('totalcommission'), 2, '.', '');

            // 商城总销售盒数（数量），参考 Backstage::welcome 统计逻辑
            // 修复：减去已退款数量，只统计实际销售数量
            $whereOg = [];
            $whereOg[] = ['og.aid', '=', aid];
            if (!$this->shouldUseAllBidData($dataScope)) {
                $whereOg[] = ['og.bid', '=', bid];
            }
            $whereOg[] = ['og.status', 'in', '1,2,3'];
            $total_stats = Db::name('shop_order_goods')->alias('og')
                ->join('shop_order', 'shop_order.id=og.orderid')
                ->fieldRaw('sum(og.num - IFNULL(og.refund_num, 0)) as total_num')
                ->where($whereOg)
                ->group('proid')
                ->select()->toArray();
            $totalSalesCount = 0;
            foreach ($total_stats as $item) {
                $totalSalesCount += $item['total_num'];
            }

            $trafficStats = PageVisitStatService::getSummary(
                intval($this->aid),
                intval($this->bid),
                $this->shouldUseAllBidData($dataScope)
            );
            
            $data = [
                // 客户
                'customerCount' => $memberCount,
                'todayCustomer' => $memberThisDayCount,
                'yesterdayCustomer' => $memberLastDayCount,
                'customerThisMonth' => $memberThisMonthCount,

                // 订单数
                'orderCount' => $orderallCount,
                'todayOrder' => $orderallThisDayCount,
                'yesterdayOrder' => $orderallLastDayCount,
                'orderThisMonth' => $orderallThisMonthCount,

                // 订单金额
                'orderAmount' => number_format($orderMoneyCount, 2, '.', ''),
                'todayAmount' => number_format($orderMoneyThisDayCount, 2, '.', ''),
                'yesterdayAmount' => number_format($orderMoneyLastDayCount, 2, '.', ''),
                'amountThisMonth' => number_format($orderMoneyThisMonthCount, 2, '.', ''),

                // 商品
                'productCount' => $productCount,
                'productOnline' => $product1Count,
                'productOffline' => $product0Count,

                // 收款金额（平台）
                'payCount' => number_format($payCount, 2, '.', ''),
                'payThisDayCount' => number_format($payThisDayCount, 2, '.', ''),
                'payLastDayCount' => number_format($payLastDayCount, 2, '.', ''),
                'payThisMonthCount' => number_format($payThisMonthCount, 2, '.', ''),

                // 提现金额（平台）
                'withdrawCount' => number_format($withdrawCount, 2, '.', ''),
                'withdrawThisDayCount' => number_format($withdrawThisDayCount, 2, '.', ''),
                'withdrawLastDayCount' => number_format($withdrawLastDayCount, 2, '.', ''),
                'withdrawThisMonthCount' => number_format($withdrawThisMonthCount, 2, '.', ''),

                // 退款金额
                'refundCount' => number_format($refundCount, 2, '.', ''),
                'refundAmount' => number_format($refundCount, 2, '.', ''), // 兼容旧命名
                'refundThisDayCount' => number_format($refundThisDayCount, 2, '.', ''),
                'refundLastDayCount' => number_format($refundLastDayCount, 2, '.', ''),
                'refundThisMonthCount' => number_format($refundThisMonthCount, 2, '.', ''),

                // 会员资产
                'totalMemberBalance' => $totalMemberBalance,
                'totalMemberCommission' => $totalMemberCommission,
                'totalMemberAllCommission' => $totalMemberAllCommission,

                // 销售盒数
                'totalSalesCount' => intval($totalSalesCount),
            ];
            $data = array_merge($data, $trafficStats);

            try {
                $pluginDashboard = (new PluginDashboardRegistry(intval($this->aid), intval($this->bid), $dataScope))->getDashboardData('stats');
                if (!empty($pluginDashboard['stats']) && is_array($pluginDashboard['stats'])) {
                    $data = array_merge($data, $pluginDashboard['stats']);
                }
            } catch (\Throwable $e) {
            }

            return $this->success($data, '获取成功');
            
        } catch (\Exception $e) {
            return $this->error('获取统计数据失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取完整的首页数据（统计+图表）
     * 
     * @return \think\response\Json
     */
    public function getHomeData()
    {
        try {
            // 直接查询所有数据，不调用其他方法
            $lastDayStart = strtotime(date('Y-m-d', time()-86400));
            $lastDayEnd = $lastDayStart + 86400;
            
            // 统计数据
            $stats = [
                'customerCount' => 0 + Db::name('member')->where('aid', aid)->count(),
                'todayCustomer' => 0 + Db::name('member')->where('aid', aid)->where('createtime', '>=', $lastDayEnd)->count(),
                'orderCount' => 0 + Db::name('shop_order')->where('aid', aid)->where('bid', bid)->where('status', 'in', '1,2,3')->count(),
                'todayOrder' => 0 + Db::name('shop_order')->where('aid', aid)->where('bid', bid)->where('status', 'in', '1,2,3')->where('paytime', '>=', $lastDayEnd)->count(),
                'orderAmount' => number_format(Db::name('shop_order')->where('aid', aid)->where('bid', bid)->where('status', 'in', '1,2,3')->sum('totalprice'), 2, '.', ''),
                'productCount' => 0 + Db::name('shop_product')->where('aid', aid)->where('bid', bid)->count(),
            ];
            
            // 图表数据（默认客户总数）
            $monthEnd = strtotime(date('Y-m-d', time()-86400));
            $monthStart = $monthEnd - 86400 * 29;
            $dateArr = [];
            $dataArr = [];
            
            for($i=0; $i<30; $i++){
                $thisDayStart = $monthStart + $i * 86400;
                $thisDayEnd = $monthStart + ($i+1) * 86400;
                $dateArr[] = date('m-d', $thisDayStart);
                $dataArr[] = 0 + Db::name('member')->where('aid', aid)->where('createtime', '<', $thisDayEnd)->count();
            }
            
            return $this->success([
                'stats' => $stats,
                'chart' => [
                    'dateArr' => $dateArr,
                    'dataArr' => $dataArr
                ]
            ], '获取成功');
            
        } catch (\Exception $e) {
            return $this->error('获取首页数据失败：' . $e->getMessage());
        }
    }

    // 获取系统设置（平台后台）
    public function getSyssetInfo()
    {
        if ($this->bid != 0) {
            return $this->error('仅平台后台可访问');
        }
        $this->ensureCommissionWithdrawNeedPaycodeColumn();
        $this->ensureAreaFenhongDifferentialColumn();
        $this->ensureMemberRegisterNotelColumn();
        $this->ensureMemberAccountCancelColumn();
        $this->ensureMemberRegisterEnabledColumn();
        $this->ensureAlipayEnabledColumn();
        $this->ensureXiaofeibpayColumn();
        $this->ensureXiaofeibTransferColumns();
        $this->ensureXianjinquanTransferTargetTypeColumn();
        MemberOpenidBindService::ensureSchema();
        WithdrawFeeRewardService::ensureAdminSetColumns();
        WithdrawMultiple::ensureAdminSetColumns();
        \app\admin\common\MapQuery::ensureH5MapKeyColumn();
        $config = include(ROOT_PATH . 'config.php');
        $authtoken = $config['authtoken'] ?? '';
        // 初始化
        $info = \think\facade\Db::name('admin_set')->where('aid', aid)->find();
        if (!$info) {
            \app\admin\common\System::initaccount(aid);
            $info = \think\facade\Db::name('admin_set')->where('aid', aid)->find();
        }
        $customIndexColumnsReady = $this->tableColumnExists('admin_set', 'custom_index_enabled')
            && $this->tableColumnExists('admin_set', 'custom_index_url');
        if (!isset($info['custom_index_enabled'])) {
            $info['custom_index_enabled'] = 0;
        }
        if (!isset($info['custom_index_url'])) {
            $info['custom_index_url'] = '';
        }
        if (!isset($info['comwithdraw_need_paycode'])) {
            $info['comwithdraw_need_paycode'] = 0;
        }
        if (!isset($info['areafenhong_differential'])) {
            $info['areafenhong_differential'] = 0;
        }
        if (!isset($info['login_popup_status'])) {
            $info['login_popup_status'] = 0;
        }
        if (!isset($info['login_popup_platforms'])) {
            $info['login_popup_platforms'] = '';
        }
        if (!isset($info['member_register_notel'])) {
            $info['member_register_notel'] = 0;
        }
        if (!isset($info['member_account_cancel_enabled'])) {
            $info['member_account_cancel_enabled'] = 1;
        }
        if (!isset($info['member_register_enabled'])) {
            $info['member_register_enabled'] = 1;
        }
        if (!isset($info['alipay_enabled'])) {
            $info['alipay_enabled'] = 1;
        }
        if (!isset($info['xiaofeibpay'])) {
            $info['xiaofeibpay'] = 0;
        }
        if (!isset($info['xiaofeib_transfer'])) {
            $info['xiaofeib_transfer'] = 0;
        }
        if (!isset($info['xiaofeib_tansrate'])) {
            $info['xiaofeib_tansrate'] = 0;
        }
        if (!isset($info['xiaofeib_transfer_need_paypwd'])) {
            $info['xiaofeib_transfer_need_paypwd'] = 0;
        }
        if (!isset($info['xiaofeib_transfer_target_type'])) {
            $info['xiaofeib_transfer_target_type'] = 1;
        }
        if (!isset($info['xianjinquan_transfer_target_type'])) {
            $info['xianjinquan_transfer_target_type'] = 1;
        }
        if (!isset($info['withdraw_fee_reward_enabled'])) {
            $info['withdraw_fee_reward_enabled'] = 0;
        }
        if (!isset($info['withdraw_fee_reward_asset'])) {
            $info['withdraw_fee_reward_asset'] = '';
        }
        if (!isset($info['withdraw_fee_reward_percent'])) {
            $info['withdraw_fee_reward_percent'] = 0;
        }
        if (!isset($info['withdraw_multiple'])) {
            $info['withdraw_multiple'] = 0;
        }
        if (!isset($info['comwithdraw_multiple'])) {
            $info['comwithdraw_multiple'] = 0;
        }
        if (!isset($info['money_transfer_multiple'])) {
            $info['money_transfer_multiple'] = 0;
        }
        if (!isset($info['commission2money_multiple'])) {
            $info['commission2money_multiple'] = 0;
        }
        if (!isset($info['wechat_openid_auto_bind_platforms'])) {
            $info['wechat_openid_auto_bind_platforms'] = 'mp,wx';
        }
        if (!isset($info['h5_qq_map_key'])) {
            $info['h5_qq_map_key'] = '';
        }

        // 平台支持
        $platform = \app\admin\common\Common::getplatform(aid);
        $allowedPlatforms = ['mp', 'wx', 'alipay', 'baidu', 'toutiao', 'qq', 'h5', 'app'];
        foreach ($platform as $pl) {
            if (in_array($pl, $allowedPlatforms) && isset($info['logintype_' . $pl])) {
                $info['logintype_' . $pl] = $info['logintype_' . $pl] !== '' ? explode(',', $info['logintype_' . $pl]) : [];
            }
        }
        // 展示时的数组字段
        if (isset($info['gettj'])) $info['gettj'] = $info['gettj'] !== '' ? explode(',', $info['gettj']) : [];
        if (isset($info['invoice_type'])) $info['invoice_type'] = $info['invoice_type'] !== '' ? explode(',', $info['invoice_type']) : [];
        if (isset($info['wxpay_gettj'])) $info['wxpay_gettj'] = $info['wxpay_gettj'] !== '' ? explode(',', $info['wxpay_gettj']) : [];
        if (isset($info['pay_transfer_gettj'])) $info['pay_transfer_gettj'] = $info['pay_transfer_gettj'] !== '' ? explode(',', $info['pay_transfer_gettj']) : [];
        // 辅助字段（原视图内用 explode）
        $gztsArr = isset($info['gzts']) ? array_filter(explode(',', (string)$info['gzts'])) : [];
        $ddbbArr = isset($info['ddbb']) ? array_filter(explode(',', (string)$info['ddbb'])) : [];

        // JSON 配置
        $textset = isset($info['textset']) ? json_decode($info['textset'], true) : null;
        $textset = $this->mergeDefaultTextset($textset);
        $info['cyz_qu'] = isset($info['cyz_qu']) ? json_decode($info['cyz_qu'], true) : null;
        $info['gongpaizhi_jiangli'] = isset($info['gongpaizhi_jiangli']) ? json_decode($info['gongpaizhi_jiangli'], true) : null;

        // 会员等级列表
        $default_cid = \think\facade\Db::name('member_level_category')->where('aid', aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $levellist = \think\facade\Db::name('member_level')->where('aid', aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();

        // 协议设置和远程存储
        $ainfo = \think\facade\Db::name('admin')->where('id', aid)->find();
        $xyinfo = \think\facade\Db::name('admin_set_xieyi')->where('aid', aid)->find();
        $rinfo = isset($ainfo['remote']) ? json_decode($ainfo['remote'], true) : null;
        $endtime_fmt = '';
        if (!empty($ainfo['endtime'])) {
            $endtime_fmt = date('Y-m-d', intval($ainfo['endtime']));
        }

        // 其他状态
        $jifenchi_status = 0;
        if ($this->hasEnabledPluginMenu('jifenchi') && $this->tableExists('jifechi')) {
            $jifenchi_status = \think\facade\Db::name('jifechi')->where('aid', aid)->value('jfc_status');
            if ($jifenchi_status === null) $jifenchi_status = 0;
        }

        $authDataRaw = $this->user['auth_data'] ?? [];
        $authAll = false;
        $authData = [];
        if ($authDataRaw === 'all') {
            $authAll = true;
        } elseif (is_array($authDataRaw)) {
            $authData = $authDataRaw;
        } elseif ($authDataRaw !== '' && $authDataRaw !== null) {
            $decoded = json_decode(strval($authDataRaw), true);
            if ($decoded === 'all') {
                $authAll = true;
            } elseif (is_array($decoded)) {
                $authData = $decoded;
            }
        }
        $partner_jiaquan = $authAll || in_array('partner_jiaquan', $authData, true);
        $partner_gongxian = $authAll || in_array('partner_gongxian', $authData, true);

        $cpcnPluginState = $this->getPluginInstallState(self::CPCNPAY_PLUGIN_NAME);
        $cpcnSetting = null;
        if (
            intval($cpcnPluginState['installed'] ?? 0) === 1
            && intval($cpcnPluginState['enabled'] ?? 0) === 1
            && $this->tableExists('cpcn_setting')
        ) {
            $cpcnSetting = \think\facade\Db::name('cpcn_setting')->where('aid', aid)->field('is_enabled,withdraw_payer_user_id')->find();
        }
        $cpcnPayEnabled = intval($cpcnSetting['is_enabled'] ?? 0) === 1 ? 1 : 0;
        $cpcnWithdrawPayerConfigured = !empty(trim($cpcnSetting['withdraw_payer_user_id'] ?? '')) ? 1 : 0;
        $mendianGroupbuyPluginState = $this->getPluginInstallState(self::MENDIAN_GROUPBUY_PLUGIN_NAME);

        // 绑定信息（与 welcome 页面一致返回）
        $mpinfo = [];
        $wxapp = [];
        if (in_array('wx', $platform)) {
            $wxapp = \app\admin\common\System::appinfo(aid, 'wx');
        }
        if (in_array('mp', $platform)) {
            $mpinfo = \app\admin\common\System::appinfo(aid, 'mp');
        }

        if (isset($info['desc'])) {
            $info['desc'] = \app\admin\common\System::plainText($info['desc']);
        }
        if (isset($info['maidian'])) {
            $info['maidian'] = \app\admin\common\System::plainText($info['maidian']);
        }

        // AI(NewAPI) 配置（客户维度 + 平台参考）
        $aiinfo = \app\admin\common\AiNewapi::getAidConfig(aid, false);
        $ai_platform = \app\admin\common\AiNewapi::getPlatformConfig(false);

        return $this->success([
            'info' => $info,
            'authtoken' => $authtoken,
            'textset' => $textset,
            'xyinfo' => $xyinfo,
            'rinfo' => $rinfo,
            'aiinfo' => $aiinfo,
            'ai_platform' => $ai_platform,
            'levellist' => $levellist,
            'jifenchi_status' => intval($jifenchi_status),
            'platform' => $platform,
            'gztsArr' => $gztsArr,
            'ddbbArr' => $ddbbArr,
            'isadmin' => intval($this->user['isadmin'] ?? 0),
            'mpinfo' => $mpinfo,
            'wxapp' => $wxapp,
            'endtime' => $endtime_fmt,
            'partner_jiaquan' => $partner_jiaquan ? 1 : 0,
            'partner_gongxian' => $partner_gongxian ? 1 : 0,
            'cpcn_plugin_installed' => intval($cpcnPluginState['installed'] ?? 0),
            'cpcn_plugin_enabled' => intval($cpcnPluginState['enabled'] ?? 0),
            'cpcn_pay_enabled' => $cpcnPayEnabled,
            'cpcn_withdraw_payer_configured' => $cpcnWithdrawPayerConfigured,
            'mendian_groupbuy_plugin_installed' => intval($mendianGroupbuyPluginState['installed'] ?? 0),
            'mendian_groupbuy_plugin_enabled' => intval($mendianGroupbuyPluginState['enabled'] ?? 0),
            'custom_index_columns_ready' => $customIndexColumnsReady ? 1 : 0,
        ], 'ok');
    }

    // 保存系统设置（平台后台）
    public function saveSysset()
    {
        if ($this->bid != 0) {
            return $this->error('仅平台后台可保存');
        }
        $this->ensureCommissionWithdrawNeedPaycodeColumn();
        $this->ensureAreaFenhongDifferentialColumn();
        $this->ensureMemberRegisterNotelColumn();
        $this->ensureMemberAccountCancelColumn();
        $this->ensureMemberRegisterEnabledColumn();
        $this->ensureAlipayEnabledColumn();
        $this->ensureXiaofeibpayColumn();
        $this->ensureXiaofeibTransferColumns();
        $this->ensureXianjinquanTransferTargetTypeColumn();
        MemberOpenidBindService::ensureSchema();
        WithdrawFeeRewardService::ensureAdminSetColumns();
        WithdrawMultiple::ensureAdminSetColumns();
        \app\admin\common\MapQuery::ensureH5MapKeyColumn();
        $info = input('post.info/a', []);
        $gzts = input('post.gzts/a', []);
        $ddbb = input('post.ddbb/a', []);
        $login_mast = input('post.login_mast/a', []);
        $textset = input('post.textset/a', []);
        $xyinfo = input('post.xyinfo/a', []);
        $rinfo = input('post.rinfo/a', []);
        $aiinfo = input('post.aiinfo/a', null);

        if (!is_array($info)) $info = [];
        if (isset($info['h5_qq_map_key'])) {
            $info['h5_qq_map_key'] = trim(strval($info['h5_qq_map_key']));
            if (!$this->tableColumnExists('admin_set', 'h5_qq_map_key')) {
                $attemptH5MapKey = $info['h5_qq_map_key'] !== '';
                unset($info['h5_qq_map_key']);
                if ($attemptH5MapKey) {
                    return $this->error('数据库缺少字段 h5_qq_map_key，请先执行升级SQL');
                }
            }
        }
        $mode = intval($info['mode'] ?? 0);
        if ($mode === 3) {
            $mendianGroupbuyPluginState = $this->getPluginInstallState(self::MENDIAN_GROUPBUY_PLUGIN_NAME);
            if (intval($mendianGroupbuyPluginState['installed'] ?? 0) !== 1) {
                return $this->error('请先安装门店社区团购插件，再启用门店模式');
            }
            if (intval($mendianGroupbuyPluginState['enabled'] ?? 0) !== 1) {
                return $this->error('请先启用门店社区团购插件，再启用门店模式');
            }
        }

        // implode 数组字段
        $info['gzts'] = implode(',', $gzts);
        $info['ddbb'] = implode(',', $ddbb);
        $info['login_mast'] = implode(',', $login_mast);

        // 中金提现相关开关
        $info['withdraw_cpcn_balance'] = !empty($info['withdraw_cpcn_balance']) ? 1 : 0;
        $info['withdraw_cpcn_commission'] = !empty($info['withdraw_cpcn_commission']) ? 1 : 0;
        $info['cpcn_withdraw_mode'] = isset($info['cpcn_withdraw_mode']) && in_array(intval($info['cpcn_withdraw_mode']), [0, 1, 2], true)
            ? intval($info['cpcn_withdraw_mode'])
            : 1;
        $cpcnPluginState = $this->getPluginInstallState(self::CPCNPAY_PLUGIN_NAME);
        if (
            intval($cpcnPluginState['installed'] ?? 0) !== 1
            || intval($cpcnPluginState['enabled'] ?? 0) !== 1
            || !$this->tableExists('cpcn_setting')
        ) {
            $info['withdraw_cpcn_balance'] = 0;
            $info['withdraw_cpcn_commission'] = 0;
            $info['cpcn_withdraw_mode'] = 1;
        }
        $rewardAsset = WithdrawFeeRewardService::normalizeAsset($info['withdraw_fee_reward_asset'] ?? '');
        $rewardPercent = WithdrawFeeRewardService::normalizePercent($info['withdraw_fee_reward_percent'] ?? 0);
        $info['withdraw_fee_reward_enabled'] = !empty($info['withdraw_fee_reward_enabled']) ? 1 : 0;
        $info['withdraw_fee_reward_asset'] = $rewardAsset;
        $info['withdraw_fee_reward_percent'] = $rewardPercent;
        if ($info['withdraw_fee_reward_enabled'] !== 1 || $rewardAsset === '' || $rewardPercent <= 0) {
            $info['withdraw_fee_reward_enabled'] = 0;
            $info['withdraw_fee_reward_asset'] = '';
            $info['withdraw_fee_reward_percent'] = 0;
        }
        $rewardColumnsReady = $this->tableColumnExists('admin_set', 'withdraw_fee_reward_enabled')
            && $this->tableColumnExists('admin_set', 'withdraw_fee_reward_asset')
            && $this->tableColumnExists('admin_set', 'withdraw_fee_reward_percent');
        if (!$rewardColumnsReady) {
            $attemptReward = intval($info['withdraw_fee_reward_enabled'] ?? 0) === 1
                || strval($info['withdraw_fee_reward_asset'] ?? '') !== ''
                || floatval($info['withdraw_fee_reward_percent'] ?? 0) > 0;
            unset($info['withdraw_fee_reward_enabled'], $info['withdraw_fee_reward_asset'], $info['withdraw_fee_reward_percent']);
            if ($attemptReward) {
                return $this->error('数据库缺少余额提现手续费奖励字段，请先执行升级SQL');
            }
        }
        $info['withdraw_multiple'] = WithdrawMultiple::normalize($info['withdraw_multiple'] ?? 0);
        $info['comwithdraw_multiple'] = WithdrawMultiple::normalize($info['comwithdraw_multiple'] ?? 0);
        $info['money_transfer_multiple'] = WithdrawMultiple::normalize($info['money_transfer_multiple'] ?? 0);
        $info['commission2money_multiple'] = WithdrawMultiple::normalize($info['commission2money_multiple'] ?? 0);
        if (isset($info['jifenzhuanzhangbeishu'])) {
            $info['jifenzhuanzhangbeishu'] = WithdrawMultiple::normalize($info['jifenzhuanzhangbeishu']);
        }
        if (!WithdrawMultiple::hasColumn('withdraw_multiple')) {
            $attemptWithdrawMultiple = $info['withdraw_multiple'] > 0;
            unset($info['withdraw_multiple']);
            if ($attemptWithdrawMultiple) {
                return $this->error('数据库缺少字段 withdraw_multiple，请先执行升级SQL');
            }
        }
        if (!WithdrawMultiple::hasColumn('comwithdraw_multiple')) {
            $attemptComwithdrawMultiple = $info['comwithdraw_multiple'] > 0;
            unset($info['comwithdraw_multiple']);
            if ($attemptComwithdrawMultiple) {
                return $this->error('数据库缺少字段 comwithdraw_multiple，请先执行升级SQL');
            }
        }
        if (!WithdrawMultiple::hasColumn('money_transfer_multiple')) {
            $attemptMoneyTransferMultiple = $info['money_transfer_multiple'] > 0;
            unset($info['money_transfer_multiple']);
            if ($attemptMoneyTransferMultiple) {
                return $this->error('数据库缺少字段 money_transfer_multiple，请先执行升级SQL');
            }
        }
        if (!WithdrawMultiple::hasColumn('commission2money_multiple')) {
            $attemptCommission2moneyMultiple = $info['commission2money_multiple'] > 0;
            unset($info['commission2money_multiple']);
            if ($attemptCommission2moneyMultiple) {
                return $this->error('数据库缺少字段 commission2money_multiple，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'comwithdraw_need_paycode')) {
            $info['comwithdraw_need_paycode'] = !empty($info['comwithdraw_need_paycode']) ? 1 : 0;
        } else {
            unset($info['comwithdraw_need_paycode']);
        }
        $info['custom_index_enabled'] = !empty($info['custom_index_enabled']) ? 1 : 0;
        $info['custom_index_url'] = isset($info['custom_index_url']) ? trim(strval($info['custom_index_url'])) : '';
        if ($info['custom_index_url'] !== '' && strpos($info['custom_index_url'], '/') !== 0) {
            $info['custom_index_url'] = '/' . ltrim($info['custom_index_url'], '/');
        }
        $customIndexColumnsReady = $this->tableColumnExists('admin_set', 'custom_index_enabled')
            && $this->tableColumnExists('admin_set', 'custom_index_url');
        if (!$customIndexColumnsReady) {
            $attemptCustomIndex = $info['custom_index_enabled'] === 1 || $info['custom_index_url'] !== '';
            unset($info['custom_index_enabled'], $info['custom_index_url']);
            if ($attemptCustomIndex) {
                return $this->error('数据库缺少字段 custom_index_enabled/custom_index_url，请先执行升级SQL');
            }
        } elseif (intval($info['mode'] ?? 0) === 0 && $info['custom_index_enabled'] === 1 && $info['custom_index_url'] === '') {
            return $this->error('请输入首页路径');
        }

        $allowedPlatforms = ['mp', 'wx', 'alipay', 'baidu', 'toutiao', 'qq', 'h5', 'app'];
        $info['login_popup_status'] = !empty($info['login_popup_status']) ? 1 : 0;
        $popupPlatforms = $info['login_popup_platforms'] ?? '';
        if (is_array($popupPlatforms)) {
            $popupPlatforms = array_values(array_intersect($allowedPlatforms, array_map('strval', $popupPlatforms)));
            $popupPlatforms = implode(',', $popupPlatforms);
        } else {
            $popupPlatforms = array_filter(explode(',', strval($popupPlatforms)));
            $popupPlatforms = array_values(array_intersect($allowedPlatforms, $popupPlatforms));
            $popupPlatforms = implode(',', $popupPlatforms);
        }
        $info['login_popup_platforms'] = $popupPlatforms;
        $loginPopupColumnsReady = $this->tableColumnExists('admin_set', 'login_popup_status')
            && $this->tableColumnExists('admin_set', 'login_popup_platforms');
        if (!$loginPopupColumnsReady) {
            $attemptLoginPopup = $info['login_popup_status'] === 1 || $info['login_popup_platforms'] !== '';
            unset($info['login_popup_status'], $info['login_popup_platforms']);
            if ($attemptLoginPopup) {
                return $this->error('数据库缺少字段 login_popup_status/login_popup_platforms，请先执行升级SQL');
            }
        }

        $info['wechat_openid_auto_bind_platforms'] = MemberOpenidBindService::normalizePlatforms(
            $info['wechat_openid_auto_bind_platforms'] ?? ''
        );
        if (!$this->tableColumnExists('admin_set', 'wechat_openid_auto_bind_platforms')) {
            $attemptOpenidAutoBind = $info['wechat_openid_auto_bind_platforms'] !== '';
            unset($info['wechat_openid_auto_bind_platforms']);
            if ($attemptOpenidAutoBind) {
                return $this->error('数据库缺少字段 wechat_openid_auto_bind_platforms，请先执行升级SQL');
            }
        }

        // 商城卖点字段：列不存在时跳过，避免写库报错
        if (isset($info['maidian']) && !$this->tableColumnExists('admin_set', 'maidian')) {
            unset($info['maidian']);
        }

        // 账号注册(无需手机号)开关：列不存在时跳过，避免写库报错
        if ($this->tableColumnExists('admin_set', 'member_register_notel')) {
            $info['member_register_notel'] = !empty($info['member_register_notel']) ? 1 : 0;
        } else {
            unset($info['member_register_notel']);
        }
        if ($this->tableColumnExists('admin_set', 'member_account_cancel_enabled')) {
            $info['member_account_cancel_enabled'] = !empty($info['member_account_cancel_enabled']) ? 1 : 0;
        } else {
            $attemptMemberAccountCancel = isset($info['member_account_cancel_enabled']) && intval($info['member_account_cancel_enabled']) !== 1;
            unset($info['member_account_cancel_enabled']);
            if ($attemptMemberAccountCancel) {
                return $this->error('数据库缺少字段 member_account_cancel_enabled，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'member_register_enabled')) {
            $info['member_register_enabled'] = !isset($info['member_register_enabled']) || !empty($info['member_register_enabled']) ? 1 : 0;
        } else {
            $attemptCloseRegister = isset($info['member_register_enabled']) && intval($info['member_register_enabled']) !== 1;
            unset($info['member_register_enabled']);
            if ($attemptCloseRegister) {
                return $this->error('数据库缺少字段 member_register_enabled，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'alipay_enabled')) {
            $info['alipay_enabled'] = !isset($info['alipay_enabled']) || !empty($info['alipay_enabled']) ? 1 : 0;
        } else {
            $attemptDisableAlipay = isset($info['alipay_enabled']) && intval($info['alipay_enabled']) !== 1;
            unset($info['alipay_enabled']);
            if ($attemptDisableAlipay) {
                return $this->error('数据库缺少字段 alipay_enabled，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'xiaofeibpay')) {
            $info['xiaofeibpay'] = !empty($info['xiaofeibpay']) ? 1 : 0;
        } else {
            $attemptXiaofeibpay = isset($info['xiaofeibpay']) && intval($info['xiaofeibpay']) !== 0;
            unset($info['xiaofeibpay']);
            if ($attemptXiaofeibpay) {
                return $this->error('数据库缺少字段 xiaofeibpay，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'xiaofeib_transfer')) {
            $info['xiaofeib_transfer'] = !empty($info['xiaofeib_transfer']) ? 1 : 0;
        } else {
            $attemptXiaofeibTransfer = isset($info['xiaofeib_transfer']) && intval($info['xiaofeib_transfer']) !== 0;
            unset($info['xiaofeib_transfer']);
            if ($attemptXiaofeibTransfer) {
                return $this->error('数据库缺少字段 xiaofeib_transfer，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'xiaofeib_tansrate')) {
            $info['xiaofeib_tansrate'] = isset($info['xiaofeib_tansrate']) ? max(0, floatval($info['xiaofeib_tansrate'])) : 0;
        } else {
            $attemptXiaofeibRate = isset($info['xiaofeib_tansrate']) && floatval($info['xiaofeib_tansrate']) > 0;
            unset($info['xiaofeib_tansrate']);
            if ($attemptXiaofeibRate) {
                return $this->error('数据库缺少字段 xiaofeib_tansrate，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'xiaofeib_transfer_need_paypwd')) {
            $info['xiaofeib_transfer_need_paypwd'] = !empty($info['xiaofeib_transfer_need_paypwd']) ? 1 : 0;
        } else {
            $attemptXiaofeibNeedPaypwd = isset($info['xiaofeib_transfer_need_paypwd']) && intval($info['xiaofeib_transfer_need_paypwd']) !== 0;
            unset($info['xiaofeib_transfer_need_paypwd']);
            if ($attemptXiaofeibNeedPaypwd) {
                return $this->error('数据库缺少字段 xiaofeib_transfer_need_paypwd，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'xiaofeib_transfer_target_type')) {
            $info['xiaofeib_transfer_target_type'] = (isset($info['xiaofeib_transfer_target_type']) && intval($info['xiaofeib_transfer_target_type']) === 2) ? 2 : 1;
        } else {
            $attemptXiaofeibTargetType = isset($info['xiaofeib_transfer_target_type']) && intval($info['xiaofeib_transfer_target_type']) === 2;
            unset($info['xiaofeib_transfer_target_type']);
            if ($attemptXiaofeibTargetType) {
                return $this->error('数据库缺少字段 xiaofeib_transfer_target_type，请先执行升级SQL');
            }
        }
        if ($this->tableColumnExists('admin_set', 'xianjinquan_transfer_target_type')) {
            $info['xianjinquan_transfer_target_type'] = (isset($info['xianjinquan_transfer_target_type']) && intval($info['xianjinquan_transfer_target_type']) === 2) ? 2 : 1;
        } else {
            $attemptXianjinquanTargetType = isset($info['xianjinquan_transfer_target_type']) && intval($info['xianjinquan_transfer_target_type']) === 2;
            unset($info['xianjinquan_transfer_target_type']);
            if ($attemptXianjinquanTargetType) {
                return $this->error('数据库缺少字段 xianjinquan_transfer_target_type，请先执行升级SQL');
            }
        }

        // 登录类型（按平台）
        $platform = \app\admin\common\Common::getplatform(aid);
        foreach ($platform as $pl) {
            if (in_array($pl, $allowedPlatforms) && isset($info['logintype_' . $pl]) && is_array($info['logintype_' . $pl])) {
                $info['logintype_' . $pl] = implode(',', $info['logintype_' . $pl]);
            }
        }

        // JSON 配置
        $textset = $this->mergeDefaultTextset($textset);
        $info['textset'] = jsonEncode($textset);
        if (isset($info['cyz_qu']) && is_array($info['cyz_qu'])) $info['cyz_qu'] = jsonEncode($info['cyz_qu']);
        if (isset($info['gongpaizhi_jiangli']) && is_array($info['gongpaizhi_jiangli'])) $info['gongpaizhi_jiangli'] = jsonEncode($info['gongpaizhi_jiangli']);
        if (isset($info['lunshu']) && is_array($info['lunshu'])) $info['lunshu'] = jsonEncode($info['lunshu']);

        // 默认值（IP配置等）
        if (!isset($info['ip_capture'])) $info['ip_capture'] = 0;
        if (!isset($info['ip_capture_mode'])) $info['ip_capture_mode'] = 1;
        if (!isset($info['ip_capture_interval'])) $info['ip_capture_interval'] = 24;
        if (!isset($info['ip_address_confirm'])) $info['ip_address_confirm'] = 0;
        if (!isset($info['ip_address_edit'])) $info['ip_address_edit'] = 0;
        if (!isset($info['ip_address_edit_times'])) $info['ip_address_edit_times'] = 0;

        // 进入条件、发票类型
        if (isset($info['gettj']) && is_array($info['gettj'])) $info['gettj'] = implode(',', $info['gettj']);
        if (isset($info['invoice_type']) && is_array($info['invoice_type'])) $info['invoice_type'] = implode(',', $info['invoice_type']);
        if (isset($info['wxpay_gettj']) && is_array($info['wxpay_gettj'])) $info['wxpay_gettj'] = implode(',', $info['wxpay_gettj']);
        if (isset($info['pay_transfer_gettj']) && is_array($info['pay_transfer_gettj'])) $info['pay_transfer_gettj'] = implode(',', $info['pay_transfer_gettj']);

        // 经纬度 -> 省市区
        if (!empty($info['latitude']) && !empty($info['longitude'])) {
            $address_component = \app\admin\common\Common::getAreaByLocation($info['longitude'], $info['latitude']);
            if ($address_component && ($address_component['status'] ?? 0) == 1) {
                $info['province'] = $address_component['province'] ?? '';
                $info['city'] = $address_component['city'] ?? '';
                $info['district'] = $address_component['district'] ?? '';
                $info['province_id'] = \think\facade\Db::name('area')->where('name', 'like', '%' . $info['province'] . '%')->value('id');
                $info['city_id'] = \think\facade\Db::name('area')->where('name', 'like', '%' . $info['city'] . '%')->value('id');
                $info['district_id'] = \think\facade\Db::name('area')->where('name', 'like', '%' . $info['district'] . '%')->value('id');
            }
        }

        if (isset($info['desc'])) {
            $info['desc'] = \app\admin\common\System::plainText($info['desc']);
        }
        if (isset($info['maidian'])) {
            $info['maidian'] = \app\admin\common\System::plainText($info['maidian']);
        }

        // 保存 admin_set（保留部分旧字段）
        $rs = \think\facade\Db::name('admin_set')->where('aid', aid)->find();
        if ($rs) {
            $existing = $rs;
            // 保留旧 JSON 配置（如未提交）
            if (!isset($info['cyz_qu']) && isset($existing['cyz_qu'])) $info['cyz_qu'] = $existing['cyz_qu'];
            if (!isset($info['gongpaizhi_jiangli']) && isset($existing['gongpaizhi_jiangli'])) $info['gongpaizhi_jiangli'] = $existing['gongpaizhi_jiangli'];
            \think\facade\Db::name('admin_set')->where('aid', aid)->update($info);
        } else {
            $info['aid'] = aid;
            \think\facade\Db::name('admin_set')->insert($info);
        }

        $shareBranding = \think\facade\Db::name('admin_set')
            ->where('aid', aid)
            ->field('name,logo,`desc`')
            ->find();
        \app\admin\common\H5ShareMetadata::sync(intval(aid), is_array($shareBranding) ? $shareBranding : []);

        // 协议设置
        if (!empty($xyinfo)) {
            $rs = \think\facade\Db::name('admin_set_xieyi')->where('aid', aid)->find();
            if ($rs) {
                \think\facade\Db::name('admin_set_xieyi')->where('aid', aid)->update($xyinfo);
            } else {
                $xyinfo['aid'] = aid;
                \think\facade\Db::name('admin_set_xieyi')->insert($xyinfo);
            }
        }

        // 远程存储设置
        if (!empty($rinfo)) {
            \think\facade\Db::name('admin')->where('id', aid)->update(['remote' => jsonEncode($rinfo)]);
        }

        // AI(NewAPI) 配置（客户维度）
        if (is_array($aiinfo)) {
            \app\admin\common\AiNewapi::saveAidConfigByTenant(aid, $aiinfo);
        }

        \think\facade\Cache::set('textset_'.aid, $textset);

        // 邀请码生成
        if (!empty($info['reg_invite_code']) && intval($info['reg_invite_code']) != 0 && intval($info['reg_invite_code_type'] ?? 0) == 1) {
            $memberlist = \think\facade\Db::name('member')->where('aid', aid)->where("yqcode='' or yqcode is null")->select()->toArray();
            foreach ($memberlist as $member) {
                $yqcode = \app\admin\model\Member::getyqcode(aid);
                \think\facade\Db::name('member')->where('id', $member['id'])->update(['yqcode' => $yqcode]);
            }
        }

        \app\admin\common\System::plog('系统设置');
        return $this->success([], '设置成功');
    }

    // 修改当前后台账号密码
    public function changePassword()
    {
        $oldPassword = input('post.old_password', null);
        if ($oldPassword === null) {
            $oldPassword = input('post.oldpwd', '');
        }
        $newPassword = input('post.password', null);
        if ($newPassword === null) {
            $newPassword = input('post.pwd', '');
        }
        $confirmPassword = input('post.confirm_password', null);
        if ($confirmPassword === null) {
            $confirmPassword = input('post.confirmPassword', '');
        }

        $oldPassword = is_scalar($oldPassword) ? strval($oldPassword) : '';
        $newPassword = is_scalar($newPassword) ? strval($newPassword) : '';
        $confirmPassword = is_scalar($confirmPassword) ? strval($confirmPassword) : '';

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return $this->error('请填写完整信息');
        }
        if (!preg_match('/^[\S]{6,20}$/', $newPassword)) {
            return $this->error('新密码必须为6-20位，且不能包含空格');
        }
        if ($newPassword !== $confirmPassword) {
            return $this->error('两次输入的新密码不一致');
        }
        if ($oldPassword === $newPassword) {
            return $this->error('新密码不能和原密码相同');
        }

        $uid = intval($this->uid);
        $adminUser = Db::name('admin_user')
            ->where('id', $uid)
            ->where('aid', aid)
            ->find();
        if (!$adminUser) {
            return $this->error('账号不存在');
        }
        if (($adminUser['pwd'] ?? '') !== md5($oldPassword)) {
            return $this->error('原密码输入错误');
        }

        Db::name('admin_user')
            ->where('id', $uid)
            ->where('aid', aid)
            ->update(['pwd' => md5($newPassword)]);
        \app\admin\common\System::plog('修改密码');

        return $this->success([], '修改成功');
    }

    // 操作日志 - 用户下拉
    public function getPlogUsers()
    {
        $isadmin = intval($this->user['isadmin'] ?? 0);
        $users = Db::name('admin_user')->where('aid', aid)->where('bid', bid)->column('un', 'id');
        $list = [];
        foreach ($users as $id => $un) {
            $list[] = ['id' => intval($id), 'un' => $un];
        }
        return $this->success(['users' => $list, 'isadmin' => $isadmin]);
    }

    // 操作日志 - 列表
    public function getPlogList()
    {
        $page = max(1, intval(input('post.page/d', 1)));
        $limit = max(1, intval(input('post.limit/d', 10)));
        $field = input('post.field/s', 'id');
        $order = strtolower(input('post.order/s', 'desc'));
        if (!in_array($order, ['asc', 'desc'])) $order = 'desc';
        $orderby = $field . ' ' . $order;

        $where = [];
        $where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];

        // 权限：非管理员仅查看自己的日志
        $isadmin = intval($this->user['isadmin'] ?? 0);
        $uid = intval(input('post.uid/d', 0));
        if ($isadmin > 0) {
            if ($uid > 0) $where[] = ['uid', '=', $uid];
        } else {
            $where[] = ['uid', '=', $this->uid];
        }
        $remark = input('post.remark/s', '');
        if ($remark !== '') $where[] = ['remark', 'like', '%' . $remark . '%'];

        $count = 0 + Db::name('plog')->where($where)->count();
        $data = Db::name('plog')->where($where)->page($page, $limit)->order($orderby)->select()->toArray();
        if (!empty($data)) {
            $userArr = Db::name('admin_user')->where('aid', aid)->where('bid', bid)->column('un', 'id');
            foreach ($data as &$v) {
                $v['un'] = $userArr[$v['uid']] ?? '';
            }
        }
        return $this->success(['total' => $count, 'list' => $data]);
    }

    // 操作日志 - 删除
    public function delPlog()
    {
        $ids = input('post.ids/a', []);
        if (empty($ids)) return $this->error('参数错误');
        Db::name('plog')->where('aid', aid)->where('bid', bid)->where('id', 'in', $ids)->delete();
        return $this->success([], '删除成功');
    }

    // 订单处理 - 列表
    public function getOrderNoticeList()
    {
        $this->ensureAdminNoticeReadTable();
        $page = max(1, intval(input('post.page/d', 1)));
        $limit = max(1, intval(input('post.limit/d', 10)));
        $status = strval(input('post.status', '2')); // 0全部,1已读,2未读(默认)
        $field = input('post.field/s', 'id');
        $order = strtolower(input('post.order/s', 'desc'));
        $fieldMap = [
            'id' => 'id',
            'ordernum' => 'ordernum',
            'title' => 'title',
            'createtime' => 'createtime',
        ];
        $field = $fieldMap[$field] ?? 'id';
        if (!in_array($order, ['asc', 'desc'])) $order = 'desc';
        $orderby = $field . ' ' . $order;

        $query = Db::name('admin_notice')
            ->where('aid', aid)
            ->where('type2', 'shop_order');

        $readSubQuery = $this->getCurrentAdminOrderReadSubQuery();

        if ($status == '1') {
            $query->whereRaw("id IN {$readSubQuery}");
        } elseif ($status != '0') {
            $query->whereRaw("id NOT IN {$readSubQuery}");
        }

        $count = 0 + (clone $query)->count();
        $list = (clone $query)->page($page, $limit)->order($orderby)->select()->toArray();
        $list = $this->hydrateCurrentAdminOrderReadState($list);
        return $this->success(['total' => $count, 'list' => $list], 'ok');
    }

    // 订单处理 - 未读统计
    public function getOrderNoticeUnreadSummary()
    {
        $this->ensureAdminNoticeReadTable();

        $query = Db::name('admin_notice')
            ->where('aid', aid)
            ->where('type2', 'shop_order');
        $readSubQuery = $this->getCurrentAdminOrderReadSubQuery();
        $query->whereRaw("id NOT IN {$readSubQuery}");

        $count = 0 + $query->count();
        return $this->success(['unread_count' => $count], 'ok');
    }

    public function getOrderReminder()
    {
        $config = $this->getOrderReminderSoundConfig();
        $latest = $this->getLatestUnreadOrderNotice();
        $due = [];
        if ($config['qiang_status'] === 1 && $config['jiange_ci'] > 0 && $config['jiange_min'] > 0) {
            $rows = $this->getDueOrderReminderRows($config['jiange_min'], $config['jiange_ci'], true);
            $due = $this->formatOrderReminderNotice(!empty($rows) ? $rows[0] : null);
        }

        $orderUnreadQuery = Db::name('admin_notice')
            ->where('aid', aid)
            ->where('type2', 'shop_order')
            ->whereRaw('id NOT IN ' . $this->getCurrentAdminOrderReadSubQuery());

        $systemUnread = 0 + Db::name('admin_notice')
            ->where('aid', aid)
            ->where('uid', uid)
            ->where('isread', 0)
            ->where('type2', '<>', 'shop_order')
            ->count();

        return $this->success([
            'sound' => $config['sound'],
            'strong_sound' => $config['strong_sound'],
            'qiang_status' => $config['qiang_status'],
            'latest' => $latest,
            'due' => $due,
            'order_unread_count' => 0 + $orderUnreadQuery->count(),
            'system_unread_count' => $systemUnread,
        ], 'ok');
    }

    public function ackOrderReminder()
    {
        $config = $this->getOrderReminderSoundConfig();
        if ($config['qiang_status'] === 1 && $config['jiange_ci'] > 0 && $config['jiange_min'] > 0) {
            $rows = $this->getDueOrderReminderRows($config['jiange_min'], $config['jiange_ci'], false);
            $this->touchCurrentAdminOrderReminderRows($rows);
        }
        return $this->success([], 'ok');
    }

    // 订单处理 - 标记已读
    public function readOrderNotices()
    {
        $ids = input('post.ids/a', []);
        if (empty($ids)) return $this->error('参数错误');
        $this->markCurrentAdminOrderNoticesRead($ids);
        return $this->success([], '已读成功');
    }

    // 获取提示音设置（平台后台）
    public function getSetorderInfo()
    {
        if ($this->bid != 0) {
            return $this->error('仅平台后台可访问');
        }
        $info = Db::name('admin_set')->where('aid', aid)->find();
        if (!$info) {
            \app\admin\common\System::initaccount(aid);
            $info = Db::name('admin_set')->where('aid', aid)->find();
        }
        // 仅返回相关字段
        $data = [
            'qiang_video' => $this->normalizeOrderReminderSound((string)($info['qiang_video'] ?? '')),
            'qiangti_video' => $this->normalizeOrderReminderSound((string)($info['qiangti_video'] ?? '')),
            'qiang_status' => intval($info['qiang_status'] ?? 1),
            'jiange_ci' => $info['jiange_ci'] ?? '',
            'jiange_min' => $info['jiange_min'] ?? '',
            'default_sound' => '/static/chat/13202.wav',
        ];
        return $this->success(['info' => $data], 'ok');
    }

    // 保存提示音设置（平台后台）
    public function saveSetorder()
    {
        if ($this->bid != 0) {
            return $this->error('仅平台后台可保存');
        }
        $info = input('post.info/a', []);
        if (!is_array($info)) $info = [];
        // legacy: unset duration fields
        unset($info['video_duration'], $info['video_duration2']);

        $rs = Db::name('admin_set')->where('aid', aid)->find();
        if ($rs) {
            Db::name('admin_set')->where('aid', aid)->update($info);
        } else {
            $info['aid'] = aid;
            Db::name('admin_set')->insert($info);
        }
        \app\admin\common\System::plog('系统设置');
        return $this->success([], '设置成功');
    }
}
