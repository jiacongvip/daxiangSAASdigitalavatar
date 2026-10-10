<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarCommerceService
{
    public static function getRedeemCodeList(
        int $aid,
        int $bid = 0,
        int $page = 1,
        int $limit = 20,
        string $keyword = '',
        string $status = '',
        string $batchNo = ''
    ): array {
        $query = Db::name('digitalavatar_redeem_code')
            ->alias('c')
            ->leftJoin('digitalavatar_package p', 'p.aid = c.aid and p.id = c.package_id')
            ->leftJoin('member m', 'm.aid = c.aid and m.id = c.used_member_id')
            ->where('c.aid', $aid);
        if ($bid > 0) {
            $query->where('c.bid', $bid);
        }
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($subQuery) use ($like) {
                $subQuery
                    ->where('c.code', 'like', $like)
                    ->whereOr('c.batch_no', 'like', $like)
                    ->whereOr('c.remark', 'like', $like)
                    ->whereOr('p.title', 'like', $like)
                    ->whereOr('m.nickname', 'like', $like)
                    ->whereOr('m.tel', 'like', $like);
            });
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('c.status', intval($status));
        }
        if ($batchNo !== '') {
            $query->where('c.batch_no', $batchNo);
        }

        $total = intval((clone $query)->count());
        $list = $query
            ->field('c.*,p.title as package_title,m.nickname as used_member_name,m.tel as used_member_tel')
            ->page(max(1, $page), max(1, min(100, $limit)))
            ->order('c.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatRedeemCode($item);
        }
        unset($item);

        return [
            'list' => $list,
            'total' => $total,
            'page' => max(1, $page),
            'limit' => max(1, min(100, $limit)),
            'package_options' => array_values(array_filter(
                DigitalAvatarService::listPackages($aid, false),
                function ($row) {
                    return strval($row['package_type'] ?? 'credit') !== 'boost';
                }
            )),
        ];
    }

    public static function generateRedeemCodes(int $aid, int $bid, int $operatorId, array $info): array
    {
        $count = max(1, min(200, intval($info['count'] ?? 1)));
        $customCode = self::normalizeCode(strval($info['code'] ?? ''));
        if ($customCode !== '' && $count > 1) {
            throw new \RuntimeException('指定兑换码时一次只能生成 1 张');
        }

        $packageId = intval($info['package_id'] ?? 0);
        $creditAmount = DigitalAvatarCreditService::creditAmount($info['credit_amount'] ?? 0);
        $package = [];
        $validityDays = max(0, intval($info['expire_days'] ?? ($info['validity_days'] ?? 0)));
        if ($packageId > 0) {
            $package = Db::name('digitalavatar_package')->where('aid', $aid)->where('id', $packageId)->find();
            if (!$package) {
                throw new \RuntimeException('关联套餐不存在');
            }
            if ($creditAmount <= 0) {
                $creditAmount = DigitalAvatarCreditService::creditAdd($package['credit_amount'] ?? 0, $package['gift_credit'] ?? 0);
            }
            if ($validityDays <= 0) {
                $validityDays = max(0, intval($package['validity_days'] ?? 0));
            }
        }
        if ($creditAmount <= 0) {
            throw new \RuntimeException('请选择套餐或填写创作点数量');
        }

        $expireTime = 0;
        if ($validityDays > 0) {
            $expireTime = time() + $validityDays * 86400;
        } elseif (!empty($info['expire_time'])) {
            $expireTime = is_numeric($info['expire_time'])
                ? intval($info['expire_time'])
                : intval(strtotime(strval($info['expire_time'])));
            $expireTime = max(0, $expireTime);
        }

        $batchNo = trim(strval($info['batch_no'] ?? ''));
        if ($batchNo === '') {
            $batchNo = 'B' . date('YmdHis') . str_pad(strval(mt_rand(0, 999)), 3, '0', STR_PAD_LEFT);
        }
        $remark = mb_substr(trim(strval($info['remark'] ?? '')), 0, 200);
        $now = time();
        $list = [];

        Db::startTrans();
        try {
            for ($i = 0; $i < $count; $i++) {
                $code = ($customCode !== '' && $i === 0) ? $customCode : self::makeUniqueCode($aid);
                $id = intval(Db::name('digitalavatar_redeem_code')->insertGetId([
                    'aid' => $aid,
                    'bid' => max(0, $bid),
                    'package_id' => $packageId,
                    'batch_no' => mb_substr($batchNo, 0, 40),
                    'code' => $code,
                    'credit_amount' => DigitalAvatarCreditService::creditStore($creditAmount),
                    'status' => 0,
                    'used_member_id' => 0,
                    'used_time' => 0,
                    'expire_time' => $expireTime,
                    'remark' => $remark,
                    'operator_id' => max(0, $operatorId),
                    'create_time' => $now,
                    'update_time' => $now,
                ]));
                if ($id <= 0) {
                    throw new \RuntimeException('兑换码生成失败');
                }
                $row = Db::name('digitalavatar_redeem_code')->where('id', $id)->find();
                $row['package_title'] = strval($package['title'] ?? '');
                $list[] = self::formatRedeemCode($row ?: []);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            if (stripos($e->getMessage(), 'duplicate') !== false) {
                throw new \RuntimeException($customCode !== '' ? '兑换码已存在' : '兑换码生成冲突，请重试');
            }
            throw $e;
        }

        return [
            'batch_no' => $batchNo,
            'count' => count($list),
            'list' => $list,
        ];
    }

    public static function voidRedeemCode(int $aid, int $bid, int $id): array
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择兑换码');
        }
        $query = Db::name('digitalavatar_redeem_code')->where('aid', $aid)->where('id', $id);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        $row = $query->find();
        if (!$row) {
            throw new \RuntimeException('兑换码不存在');
        }
        if (intval($row['status'] ?? 0) === 1) {
            throw new \RuntimeException('兑换码已使用，不能作废');
        }
        if (intval($row['status'] ?? 0) === 2) {
            return self::formatRedeemCode($row);
        }
        Db::name('digitalavatar_redeem_code')->where('id', $id)->update([
            'status' => 2,
            'update_time' => time(),
        ]);
        $row['status'] = 2;
        return self::formatRedeemCode($row);
    }

    public static function redeemMemberCode(int $aid, int $bid, int $memberId, string $rawCode): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        $code = self::normalizeCode($rawCode);
        if ($code === '') {
            throw new \RuntimeException('请输入兑换码');
        }

        $now = time();
        $result = [];
        Db::startTrans();
        try {
            $row = Db::name('digitalavatar_redeem_code')
                ->where('aid', $aid)
                ->where('code', $code)
                ->lock(true)
                ->find();
            if (!$row) {
                throw new \RuntimeException('兑换码不存在');
            }
            $status = intval($row['status'] ?? 0);
            if ($status === 1) {
                throw new \RuntimeException('兑换码已被使用');
            }
            if ($status === 2) {
                throw new \RuntimeException('兑换码已作废');
            }
            $expireTime = intval($row['expire_time'] ?? 0);
            if ($expireTime > 0 && $expireTime < $now) {
                throw new \RuntimeException('兑换码已过期');
            }

            $packageId = intval($row['package_id'] ?? 0);
            $package = [];
            $creditAmount = DigitalAvatarCreditService::creditAmount($row['credit_amount'] ?? 0);
            $validityDays = 0;
            if ($packageId > 0) {
                $package = Db::name('digitalavatar_package')
                    ->where('aid', $aid)
                    ->where('id', $packageId)
                    ->find();
                if (!$package) {
                    throw new \RuntimeException('兑换码关联套餐不存在或已删除');
                }
                if ($creditAmount <= 0) {
                    $creditAmount = DigitalAvatarCreditService::creditAdd($package['credit_amount'] ?? 0, $package['gift_credit'] ?? 0);
                }
                $validityDays = max(0, intval($package['validity_days'] ?? 0));
            }
            if ($creditAmount <= 0) {
                throw new \RuntimeException('兑换码创作点数量无效');
            }

            $logExists = Db::name('digitalavatar_redeem_log')
                ->where('aid', $aid)
                ->where('code_id', intval($row['id']))
                ->where('member_id', $memberId)
                ->find();
            if ($logExists) {
                throw new \RuntimeException('该兑换码当前账号已兑换过');
            }

            $remark = '兑换码充值：' . $code;
            if ($packageId > 0) {
                DigitalAvatarCreditService::grantPackage(
                    $aid,
                    max(0, $bid),
                    $memberId,
                    $creditAmount,
                    $packageId,
                    $validityDays,
                    intval($row['id']),
                    $remark
                );
            } else {
                DigitalAvatarCreditService::grant(
                    $aid,
                    max(0, $bid),
                    $memberId,
                    $creditAmount,
                    $remark,
                    0,
                    [
                        'source_type' => 'redeem',
                        'source_id' => intval($row['id']),
                    ]
                );
            }

            Db::name('digitalavatar_redeem_code')->where('id', intval($row['id']))->update([
                'status' => 1,
                'used_member_id' => $memberId,
                'used_time' => $now,
                'update_time' => $now,
            ]);
            Db::name('digitalavatar_redeem_log')->insert([
                'aid' => $aid,
                'code_id' => intval($row['id']),
                'member_id' => $memberId,
                'credit_amount' => DigitalAvatarCreditService::creditStore($creditAmount),
                'create_time' => $now,
            ]);

            Db::commit();
            $result = [
                'code' => $code,
                'credit_amount' => $creditAmount,
                'package_title' => strval($package['title'] ?? ''),
                'credit' => DigitalAvatarCreditService::getSummary($aid, max(0, $bid), $memberId),
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return $result;
    }

    public static function getAdminPackageOrderList(
        int $aid,
        int $bid = 0,
        int $page = 1,
        int $limit = 100,
        string $keyword = '',
        string $status = '',
        string $grantStatus = '',
        string $startDate = '',
        string $endDate = '',
        string $packageType = '',
        string $payType = ''
    ): array {
        $limit = max(1, min(200, $limit));
        $query = self::buildPackageOrderQuery($aid, $bid, $keyword, $status, $grantStatus, $startDate, $endDate, $packageType, $payType);
        $statsQuery = self::buildPackageOrderQuery($aid, $bid, $keyword, '', '', $startDate, $endDate, $packageType, $payType);
        $paidQuery = (clone $statsQuery)->where('o.status', 1);
        $stats = [
            'total_order_count' => intval((clone $statsQuery)->count()),
            'paid_order_count' => intval((clone $paidQuery)->count()),
            'unpaid_order_count' => intval((clone $statsQuery)->where('o.status', 0)->count()),
            'canceled_order_count' => intval((clone $statsQuery)->where('o.status', 2)->count()),
            'paid_amount' => number_format(floatval((clone $paidQuery)->sum('o.price')), 2, '.', ''),
            'granted_credit' => DigitalAvatarCreditService::creditAmount((clone $statsQuery)->where('o.grant_status', 1)->sum('o.credit_amount')),
        ];

        $total = intval((clone $query)->count());
        $list = $query
            ->field('o.*,p.title as package_title,p.package_type,p.gift_credit,p.validity_days,m.nickname,m.tel,m.headimg,m.realname,pm.nickname as parent_nickname,pm.tel as parent_tel,pm.realname as parent_realname,po.paynum as unified_paynum,po.paytype as unified_paytype,po.paytypeid as unified_paytypeid,po.platform as unified_platform')
            ->page(max(1, $page), $limit)
            ->order('o.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatAdminPackageOrder($item);
        }
        unset($item);
        self::fillOrderInvoiceMeta($aid, $list);

        return [
            'list' => $list,
            'total' => $total,
            'page' => max(1, $page),
            'limit' => $limit,
            'stats' => $stats,
        ];
    }

    public static function getAdminPackageOrderDetail(int $aid, int $bid, int $id): array
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择订单');
        }
        $query = Db::name('digitalavatar_package_order')
            ->alias('o')
            ->leftJoin('digitalavatar_package p', 'p.aid = o.aid and p.id = o.package_id')
            ->leftJoin('member m', 'm.aid = o.aid and m.id = o.member_id')
            ->leftJoin('member pm', 'pm.aid = o.aid and pm.id = o.parent_mid')
            ->leftJoin('payorder po', 'po.aid = o.aid and po.id = o.payorder_id')
            ->where('o.aid', $aid)
            ->where('o.id', $id);
        if ($bid > 0) {
            $query->where('o.bid', $bid);
        }
        $row = $query
            ->field('o.*,p.title as package_title,p.package_type,p.gift_credit,p.validity_days,m.nickname,m.tel,m.headimg,m.realname,pm.nickname as parent_nickname,pm.tel as parent_tel,pm.realname as parent_realname,po.paynum as unified_paynum,po.paytype as unified_paytype,po.paytypeid as unified_paytypeid,po.platform as unified_platform')
            ->find();
        if (!$row) {
            throw new \RuntimeException('订单不存在');
        }
        return self::formatAdminPackageOrder($row);
    }

    public static function settlePackageOrder(int $aid, int $bid, int $id): array
    {
        $detail = self::getAdminPackageOrderDetail($aid, $bid, $id);
        DigitalAvatarService::onPackageOrderPaid(intval($detail['id']), strval($detail['paytype'] ?: '后台开通'));
        return self::getAdminPackageOrderDetail($aid, $bid, $id);
    }

    public static function getMemberPackageOrders(int $aid, int $bid, int $memberId, int $page = 1, int $limit = 20): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        $query = Db::name('digitalavatar_package_order')
            ->alias('o')
            ->leftJoin('digitalavatar_package p', 'p.aid = o.aid and p.id = o.package_id')
            ->leftJoin('payorder po', 'po.aid = o.aid and po.id = o.payorder_id')
            ->where('o.aid', $aid)
            ->where('o.bid', max(0, $bid))
            ->where('o.member_id', $memberId);
        $total = intval((clone $query)->count());
        $list = $query
            ->field('o.*,p.title as package_title,po.paynum as unified_paynum,po.paytype as unified_paytype,po.paytypeid as unified_paytypeid,po.platform as unified_platform')
            ->page(max(1, $page), max(1, min(50, $limit)))
            ->order('o.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatAdminPackageOrder($item);
        }
        unset($item);
        self::fillOrderInvoiceMeta($aid, $list);

        return [
            'list' => $list,
            'total' => $total,
            'page' => max(1, $page),
            'limit' => max(1, min(50, $limit)),
        ];
    }

    public static function formatOem(array $settings): array
    {
        $color = trim(strval($settings['oem_color'] ?? ''));
        if ($color !== '' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            $color = '';
        }
        $loginStyle = strtolower(trim(strval($settings['login_style'] ?? ''))) === 'classic' ? 'classic' : 'niwa';
        $loginBg = trim(strval($settings['oem_login_bg'] ?? ''));
        if ($loginBg === '' && $loginStyle === 'niwa') {
            $loginBg = '/portal/digitalavatar/svg/login_bg.jpg';
        }
        return [
            'title' => strval($settings['product_title'] ?? 'AI数字人工作台'),
            'logo' => trim(strval($settings['oem_logo'] ?? '')),
            'login_bg' => $loginBg,
            'login_style' => $loginStyle,
            'color' => $color,
            'subtitle' => trim(strval($settings['oem_subtitle'] ?? '')),
            'contact_wechat' => trim(strval($settings['oem_contact_wechat'] ?? '')),
            'contact_tel' => trim(strval($settings['oem_contact_tel'] ?? '')),
            'agent_enabled' => intval($settings['agent_enabled'] ?? 0) === 1 ? 1 : 0,
            'agent_can_grant' => intval($settings['agent_can_grant'] ?? 0) === 1 ? 1 : 0,
            'agent_can_redeem' => intval($settings['agent_can_redeem'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    private static function buildPackageOrderQuery(
        int $aid,
        int $bid,
        string $keyword,
        string $status,
        string $grantStatus,
        string $startDate,
        string $endDate,
        string $packageType = '',
        string $payType = ''
    ) {
        $query = Db::name('digitalavatar_package_order')
            ->alias('o')
            ->leftJoin('digitalavatar_package p', 'p.aid = o.aid and p.id = o.package_id')
            ->leftJoin('member m', 'm.aid = o.aid and m.id = o.member_id')
            ->leftJoin('member pm', 'pm.aid = o.aid and pm.id = o.parent_mid')
            ->leftJoin('payorder po', 'po.aid = o.aid and po.id = o.payorder_id')
            ->where('o.aid', $aid);
        if ($bid > 0) {
            $query->where('o.bid', $bid);
        }
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($subQuery) use ($like) {
                $subQuery
                    ->where('o.ordernum', 'like', $like)
                    ->whereOr('o.paynum', 'like', $like)
                    ->whereOr('p.title', 'like', $like)
                    ->whereOr('m.nickname', 'like', $like)
                    ->whereOr('m.tel', 'like', $like)
                    ->whereOr('m.id', 'like', $like)
                    ->whereOr('pm.nickname', 'like', $like)
                    ->whereOr('pm.tel', 'like', $like);
            });
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('o.status', intval($status));
        }
        if ($grantStatus === '0' || $grantStatus === '1') {
            $query->where('o.grant_status', intval($grantStatus));
        }
        $startTime = $startDate !== '' ? strtotime($startDate . ' 00:00:00') : false;
        if ($startTime !== false) {
            $query->where('o.create_time', '>=', intval($startTime));
        }
        $endTime = $endDate !== '' ? strtotime($endDate . ' 23:59:59') : false;
        if ($endTime !== false) {
            $query->where('o.create_time', '<=', intval($endTime));
        }
        $packageType = trim($packageType);
        if ($packageType === 'credit' || $packageType === 'boost') {
            $query->where('p.package_type', $packageType);
        }
        $payType = trim($payType);
        if ($payType !== '') {
            self::applyPayKindFilter($query, $payType);
        }
        return $query;
    }

    private static function applyPayKindFilter($query, string $kind): void
    {
        $kind = strtolower($kind);
        $words = [];
        $payTypeId = 0;
        if ($kind === 'wxpay') {
            $words = ['微信', 'wxpay', 'wechat', 'weixin'];
            $payTypeId = 2;
        } elseif ($kind === 'alipay') {
            $words = ['支付宝', 'alipay'];
            $payTypeId = 3;
        } elseif ($kind === 'money') {
            $words = ['余额', 'money'];
            $payTypeId = 1;
        } elseif ($kind === 'admin') {
            $words = ['后台', '补结算', '开通'];
        } elseif ($kind === 'redeem') {
            $words = ['兑换', 'redeem'];
        } else {
            $words = [$kind];
        }
        $query->where(function ($sub) use ($words, $payTypeId) {
            foreach ($words as $index => $word) {
                if ($index === 0) {
                    $sub->whereLike('o.paytype', '%' . $word . '%');
                } else {
                    $sub->whereOrLike('o.paytype', '%' . $word . '%');
                }
                $sub->whereOrLike('po.paytype', '%' . $word . '%');
            }
            if ($payTypeId > 0) {
                $sub->whereOr('o.paytypeid', $payTypeId)->whereOr('po.paytypeid', $payTypeId);
            }
        });
    }

    private static function formatRedeemCode(array $row): array
    {
        $status = intval($row['status'] ?? 0);
        $expireTime = intval($row['expire_time'] ?? 0);
        $usedTime = intval($row['used_time'] ?? 0);
        $statusText = '未使用';
        if ($status === 1) {
            $statusText = '已使用';
        } elseif ($status === 2) {
            $statusText = '已作废';
        } elseif ($status === 0 && $expireTime > 0 && $expireTime < time()) {
            $statusText = '已过期';
        }
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'bid' => intval($row['bid'] ?? 0),
            'package_id' => intval($row['package_id'] ?? 0),
            'package_title' => strval($row['package_title'] ?? ''),
            'batch_no' => strval($row['batch_no'] ?? ''),
            'code' => strval($row['code'] ?? ''),
            'credit_amount' => DigitalAvatarCreditService::creditAmount($row['credit_amount'] ?? 0),
            'status' => $status,
            'status_text' => $statusText,
            'used_member_id' => intval($row['used_member_id'] ?? 0),
            'used_member_name' => strval($row['used_member_name'] ?? ''),
            'used_member_tel' => strval($row['used_member_tel'] ?? ''),
            'used_time' => $usedTime,
            'used_time_text' => $usedTime > 0 ? date('Y-m-d H:i:s', $usedTime) : '',
            'expire_time' => $expireTime,
            'expire_time_text' => $expireTime > 0 ? date('Y-m-d H:i:s', $expireTime) : '长期有效',
            'remark' => strval($row['remark'] ?? ''),
            'operator_id' => intval($row['operator_id'] ?? 0),
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
        ];
    }

    private static function formatAdminPackageOrder(array $row): array
    {
        $status = intval($row['status'] ?? 0);
        $statusMap = [0 => '待支付', 1 => '已支付', 2 => '已取消'];
        $payMeta = self::payTypeMeta($row);
        $paytype = strval($payMeta['paytype'] ?? '');
        $paynum = trim(strval($row['paynum'] ?? '')) ?: trim(strval($row['unified_paynum'] ?? ''));
        $platform = trim(strval($row['platform'] ?? '')) ?: trim(strval($row['unified_platform'] ?? ''));
        $paytime = intval($row['paytime'] ?? 0) ?: intval($row['paid_time'] ?? 0);
        $createTime = intval($row['create_time'] ?? 0);
        $memberId = intval($row['member_id'] ?? 0);
        $nickname = trim(strval($row['nickname'] ?? ''));
        $realname = trim(strval($row['realname'] ?? ''));
        $packageType = strval($row['package_type'] ?? 'credit') === 'boost' ? 'boost' : 'credit';
        $parentId = intval($row['parent_mid'] ?? 0);
        $parentNickname = trim(strval($row['parent_nickname'] ?? ''));
        $parentRealname = trim(strval($row['parent_realname'] ?? ''));
        $parentName = '';
        if ($parentId > 0) {
            $parentName = $parentNickname !== '' ? $parentNickname : ($parentRealname !== '' ? $parentRealname : ('会员' . $parentId));
        }
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'bid' => intval($row['bid'] ?? 0),
            'member_id' => $memberId,
            'member_name' => $nickname !== '' ? $nickname : ($realname !== '' ? $realname : ('会员' . $memberId)),
            'tel' => strval($row['tel'] ?? ''),
            'headimg' => strval($row['headimg'] ?? ''),
            'package_id' => intval($row['package_id'] ?? 0),
            'package_title' => strval($row['package_title'] ?? ''),
            'package_type' => $packageType,
            'package_type_text' => $packageType === 'boost' ? '加油包' : '会员套餐',
            'ordernum' => strval($row['ordernum'] ?? ''),
            'payorder_id' => intval($row['payorder_id'] ?? 0),
            'price' => number_format(floatval($row['price'] ?? 0), 2, '.', ''),
            'credit_amount' => DigitalAvatarCreditService::creditAmount($row['credit_amount'] ?? 0),
            'gift_credit' => DigitalAvatarCreditService::creditAmount($row['gift_credit'] ?? 0),
            'validity_days' => intval($row['validity_days'] ?? 0),
            'validity_text' => intval($row['validity_days'] ?? 0) > 0 ? intval($row['validity_days']) . '天' : '长期有效',
            'status' => $status,
            'status_text' => $statusMap[$status] ?? '未知',
            'grant_status' => intval($row['grant_status'] ?? 0) === 1 ? 1 : 0,
            'grant_status_text' => intval($row['grant_status'] ?? 0) === 1 ? '已发放' : '未发放',
            'paytype' => $paytype,
            'paytype_text' => strval($payMeta['paytype_text'] ?? ''),
            'pay_type_kind' => strval($payMeta['kind'] ?? ''),
            'paynum' => $paynum,
            'platform' => $platform,
            'platform_text' => self::platformText($platform),
            'paytime' => $paytime,
            'paytime_text' => $paytime > 0 ? date('Y-m-d H:i:s', $paytime) : '',
            'create_time' => $createTime,
            'create_time_text' => $createTime > 0 ? date('Y-m-d H:i:s', $createTime) : '',
            'parent_mid' => $parentId,
            'parent_name' => $parentName,
            'parent_tel' => strval($row['parent_tel'] ?? ''),
            'commission_amount' => number_format(floatval($row['commission_amount'] ?? 0), 2, '.', ''),
            'commission_rate' => number_format(floatval($row['commission_rate'] ?? 0), 2, '.', ''),
            'commission_level_id' => intval($row['commission_level_id'] ?? 0),
            'can_invoice' => self::orderCanInvoice($row) ? 1 : 0,
            'invoice_disabled_text' => self::invoiceDisabledText($row, -1),
            'invoice_id' => intval($row['invoice_id'] ?? 0),
            'invoice_status' => intval($row['invoice_status'] ?? -1),
            'invoice_status_text' => self::invoiceStatusText(isset($row['invoice_status']) ? intval($row['invoice_status']) : -1),
        ];
    }

    private static function fillOrderInvoiceMeta(int $aid, array &$list): void
    {
        $ids = [];
        foreach ($list as $item) {
            $id = intval($item['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $map = [];
        if ($ids) {
            try {
                DigitalAvatarInstaller::ensureInvoiceReapplyColumn();
                $rows = Db::name('digitalavatar_invoice')->where('aid', $aid)->whereIn('order_id', $ids)->select()->toArray();
                foreach ($rows as $row) {
                    $map[intval($row['order_id'] ?? 0)] = $row;
                }
            } catch (\Throwable $e) {
                $map = [];
            }
        }
        foreach ($list as &$item) {
            $inv = $map[intval($item['id'] ?? 0)] ?? null;
            $invoiceStatus = $inv ? intval($inv['status'] ?? 0) : -1;
            $allowReapply = $inv && intval($inv['allow_reapply'] ?? 0) === 1 ? 1 : 0;
            $rejectReason = $invoiceStatus === 2 ? trim(strval($inv['admin_remark'] ?? '')) : '';
            $item['invoice_id'] = $inv ? intval($inv['id'] ?? 0) : 0;
            $item['invoice_status'] = $invoiceStatus;
            $item['invoice_status_text'] = self::invoiceStatusText($invoiceStatus);
            $item['invoice_allow_reapply'] = $allowReapply;
            $item['invoice_reject_reason'] = $rejectReason;
            $item['admin_remark'] = $inv ? strval($inv['admin_remark'] ?? '') : strval($item['admin_remark'] ?? '');
            if ($invoiceStatus === 0 || $invoiceStatus === 1) {
                $item['can_invoice'] = 0;
            } elseif ($invoiceStatus === 2) {
                $item['can_invoice'] = ($allowReapply === 1 && self::orderCanInvoice($item)) ? 1 : 0;
            }
            $item['invoice_disabled_text'] = self::invoiceDisabledText($item, $invoiceStatus);
        }
        unset($item);
    }

    public static function payTypeMeta(array $row): array
    {
        $raw = trim(strval($row['paytype'] ?? ''));
        if ($raw === '') {
            $raw = trim(strval($row['unified_paytype'] ?? ''));
        }
        $payTypeId = intval($row['paytypeid'] ?? ($row['unified_paytypeid'] ?? 0));
        $kind = 'unknown';
        $text = $raw;
        $lower = strtolower($raw);
        if (preg_match('/微信|wxpay|wechat|weixin/i', $lower) || $payTypeId === 2) {
            $kind = 'wxpay';
            $text = '微信支付';
        } elseif (preg_match('/支付宝|alipay/i', $lower) || $payTypeId === 3) {
            $kind = 'alipay';
            $text = '支付宝支付';
        } elseif (preg_match('/余额|money|moneypay/i', $lower) || $payTypeId === 1) {
            $kind = 'money';
            $text = '余额支付';
        } elseif (preg_match('/后台|补结算|开通|admin|grant/i', $lower)) {
            $kind = 'admin';
            $text = '后台开通';
        } elseif (preg_match('/兑换|redeem|code|card/i', $lower)) {
            $kind = 'redeem';
            $text = '兑换码';
        } elseif (preg_match('/免费/i', $lower)) {
            $kind = 'free';
            $text = '免费领取';
        } elseif ($raw !== '') {
            $text = $raw;
        } else {
            $text = '--';
        }
        return [
            'kind' => $kind,
            'paytype' => $raw !== '' ? $raw : $text,
            'paytype_text' => $text,
        ];
    }

    public static function invoiceDisabledText(array $row, $invoiceStatus = -1): string
    {
        $invoiceStatus = intval($invoiceStatus);
        if ($invoiceStatus === 0) {
            return '待审核';
        }
        if ($invoiceStatus === 1) {
            return '已开票';
        }
        if ($invoiceStatus === 2) {
            $reason = trim(strval($row['invoice_reject_reason'] ?? ($row['admin_remark'] ?? '')));
            if (intval($row['invoice_allow_reapply'] ?? ($row['allow_reapply'] ?? 0)) === 1) {
                return $reason !== '' ? ('已驳回：' . $reason) : '已驳回，可再次申请';
            }
            return $reason !== '' ? ('已驳回：' . $reason . '（不可再次申请）') : '已驳回，不可再次申请';
        }
        if (intval($row['can_invoice'] ?? 0) === 1 || self::orderCanInvoice($row)) {
            return '';
        }
        if (intval($row['status'] ?? 0) !== 1) {
            return strval($row['invoice_status_text'] ?? '未申请');
        }
        $kind = strval(self::payTypeMeta($row)['kind'] ?? '');
        if ($kind === 'money') {
            return '余额支付不可开票';
        }
        if ($kind === 'admin') {
            return '后台开通不可开票';
        }
        if ($kind === 'redeem') {
            return '兑换码不可开票';
        }
        if ($kind === 'free') {
            return '免费领取不可开票';
        }
        return '不可开票';
    }

    public static function orderCanInvoice(array $row): bool
    {
        if (intval($row['status'] ?? 0) !== 1) {
            return false;
        }
        if (round(floatval($row['price'] ?? 0), 2) <= 0) {
            return false;
        }
        $kind = strval(self::payTypeMeta($row)['kind'] ?? '');
        return in_array($kind, ['wxpay', 'alipay'], true);
    }

    public static function invoiceStatusText($status): string
    {
        $map = [
            0 => '待审核',
            1 => '已开票',
            2 => '已驳回',
        ];
        $status = intval($status);
        return strval($map[$status] ?? '未申请');
    }

    public static function getMemberInvoices(int $aid, int $bid, int $memberId, int $page = 1, int $limit = 20): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        DigitalAvatarInstaller::ensureInvoiceReapplyColumn();
        $query = Db::name('digitalavatar_invoice')
            ->alias('i')
            ->leftJoin('digitalavatar_package_order o', 'o.id = i.order_id')
            ->leftJoin('digitalavatar_package p', 'p.aid = i.aid and p.id = o.package_id')
            ->where('i.aid', $aid)
            ->where('i.member_id', $memberId);
        if ($bid > 0) {
            $query->where('i.bid', max(0, $bid));
        }
        $total = intval((clone $query)->count());
        $list = $query
            ->field('i.*,o.ordernum as order_ordernum,o.price as order_price,o.paytype,o.payorder_id,p.title as package_title')
            ->page(max(1, $page), max(1, min(50, $limit)))
            ->order('i.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatInvoice($item);
        }
        unset($item);
        return ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    public static function applyInvoice(int $aid, int $bid, int $memberId, array $input): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        $orderId = intval($input['order_id'] ?? 0);
        if ($orderId <= 0) {
            throw new \RuntimeException('请选择要开票的订单');
        }
        $order = Db::name('digitalavatar_package_order')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $orderId)
            ->find();
        if (!$order) {
            throw new \RuntimeException('订单不存在');
        }
        if ($bid > 0 && intval($order['bid'] ?? 0) !== $bid) {
            throw new \RuntimeException('订单不存在');
        }
        $payorderId = intval($order['payorder_id'] ?? 0);
        if ($payorderId > 0) {
            $payorder = Db::name('payorder')->where('id', $payorderId)->find();
            if ($payorder) {
                $order['unified_paytype'] = strval($payorder['paytype'] ?? '');
                $order['unified_paytypeid'] = intval($payorder['paytypeid'] ?? 0);
            }
        }
        if (!self::orderCanInvoice($order)) {
            throw new \RuntimeException('仅微信支付和支付宝支付成功的订单可以申请开票，余额支付和后台开通不可开票');
        }
        $nameType = intval($input['name_type'] ?? 1) === 2 ? 2 : 1;
        $type = intval($input['type'] ?? 1) === 2 ? 2 : 1;
        $invoiceName = trim(strval($input['invoice_name'] ?? ''));
        if ($invoiceName === '') {
            throw new \RuntimeException('请填写发票抬头');
        }
        $taxNo = trim(strval($input['tax_no'] ?? ''));
        if ($nameType === 2 && $taxNo === '') {
            throw new \RuntimeException('企业开票请填写税号');
        }
        $mobile = trim(strval($input['mobile'] ?? ''));
        $email = trim(strval($input['email'] ?? ''));
        if ($mobile === '' && $email === '') {
            throw new \RuntimeException('请至少填写手机号或邮箱，方便接收发票');
        }
        DigitalAvatarInstaller::ensureInvoiceReapplyColumn();
        $now = time();
        $existing = Db::name('digitalavatar_invoice')->where('aid', $aid)->where('order_id', $orderId)->find();
        if ($existing && intval($existing['status'] ?? 0) === 1) {
            throw new \RuntimeException('该订单已开票，不能重复申请');
        }
        if ($existing && intval($existing['status'] ?? 0) === 0) {
            throw new \RuntimeException('该订单已提交开票申请，请等待审核');
        }
        if ($existing && intval($existing['status'] ?? 0) === 2 && intval($existing['allow_reapply'] ?? 0) !== 1) {
            $reason = trim(strval($existing['admin_remark'] ?? ''));
            throw new \RuntimeException($reason !== '' ? ('该开票申请已被驳回：' . $reason . '，不可再次申请') : '该开票申请已被驳回，不可再次申请');
        }
        $data = [
            'aid' => $aid,
            'bid' => intval($order['bid'] ?? 0),
            'member_id' => $memberId,
            'order_id' => $orderId,
            'ordernum' => strval($order['ordernum'] ?? ''),
            'amount' => number_format(floatval($order['price'] ?? 0), 2, '.', ''),
            'type' => $type,
            'name_type' => $nameType,
            'invoice_name' => mb_substr($invoiceName, 0, 191),
            'tax_no' => mb_substr($taxNo, 0, 60),
            'address' => mb_substr(trim(strval($input['address'] ?? '')), 0, 255),
            'tel' => mb_substr(trim(strval($input['tel'] ?? '')), 0, 50),
            'bank_name' => mb_substr(trim(strval($input['bank_name'] ?? '')), 0, 120),
            'bank_account' => mb_substr(trim(strval($input['bank_account'] ?? '')), 0, 80),
            'mobile' => mb_substr($mobile, 0, 30),
            'email' => mb_substr($email, 0, 120),
            'status' => 0,
            'admin_remark' => '',
            'allow_reapply' => 0,
            'operator_id' => 0,
            'update_time' => $now,
        ];
        if ($existing) {
            Db::name('digitalavatar_invoice')->where('id', intval($existing['id']))->update($data);
            $id = intval($existing['id']);
        } else {
            $data['create_time'] = $now;
            $id = intval(Db::name('digitalavatar_invoice')->insertGetId($data));
        }
        $row = Db::name('digitalavatar_invoice')->where('id', $id)->find() ?: [];
        $row['package_title'] = strval(Db::name('digitalavatar_package')->where('id', intval($order['package_id'] ?? 0))->value('title'));
        return self::formatInvoice($row);
    }

    public static function getAdminInvoiceList(int $aid, int $bid, int $page, int $limit, string $keyword = '', string $status = ''): array
    {
        DigitalAvatarInstaller::ensureInvoiceReapplyColumn();
        $query = Db::name('digitalavatar_invoice')
            ->alias('i')
            ->leftJoin('digitalavatar_package_order o', 'o.id = i.order_id')
            ->leftJoin('digitalavatar_package p', 'p.aid = i.aid and p.id = o.package_id')
            ->leftJoin('member m', 'm.aid = i.aid and m.id = i.member_id')
            ->where('i.aid', $aid);
        if ($bid > 0) {
            $query->where('i.bid', $bid);
        }
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($q) use ($like) {
                $q->where('i.ordernum', 'like', $like)
                    ->whereOr('i.invoice_name', 'like', $like)
                    ->whereOr('m.nickname', 'like', $like)
                    ->whereOr('m.tel', 'like', $like)
                    ->whereOr('m.id', 'like', $like);
            });
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('i.status', intval($status));
        }
        $total = intval((clone $query)->count());
        $list = $query
            ->field('i.*,o.price as order_price,o.paytype,o.payorder_id,p.title as package_title,m.nickname,m.tel')
            ->page(max(1, $page), max(1, min(100, $limit)))
            ->order('i.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatInvoice($item);
        }
        unset($item);
        return ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    public static function auditInvoice(int $aid, int $bid, int $id, int $status, string $adminRemark, int $operatorId = 0, int $allowReapply = 0): array
    {
        DigitalAvatarInstaller::ensureInvoiceReapplyColumn();
        if ($id <= 0) {
            throw new \RuntimeException('请选择发票申请');
        }
        $status = $status === 2 ? 2 : 1;
        $adminRemark = trim($adminRemark);
        if ($status === 2 && $adminRemark === '') {
            throw new \RuntimeException('请填写驳回原因');
        }
        $query = Db::name('digitalavatar_invoice')->where('aid', $aid)->where('id', $id);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        $row = $query->find();
        if (!$row) {
            throw new \RuntimeException('发票申请不存在');
        }
        if (intval($row['status'] ?? 0) === 1) {
            throw new \RuntimeException('该申请已开票');
        }
        Db::name('digitalavatar_invoice')->where('id', $id)->update([
            'status' => $status,
            'admin_remark' => mb_substr($adminRemark, 0, 500),
            'allow_reapply' => $status === 2 && $allowReapply === 1 ? 1 : 0,
            'operator_id' => $operatorId,
            'update_time' => time(),
        ]);
        $fresh = Db::name('digitalavatar_invoice')->where('id', $id)->find() ?: [];
        return self::formatInvoice($fresh);
    }

    private static function formatInvoice(array $row): array
    {
        DigitalAvatarInstaller::ensureInvoiceReapplyColumn();
        $status = intval($row['status'] ?? 0);
        $createTime = intval($row['create_time'] ?? 0);
        $updateTime = intval($row['update_time'] ?? 0);
        $allowReapply = $status === 2 && intval($row['allow_reapply'] ?? 0) === 1 ? 1 : 0;
        return [
            'id' => intval($row['id'] ?? 0),
            'order_id' => intval($row['order_id'] ?? 0),
            'ordernum' => strval($row['ordernum'] ?? ($row['order_ordernum'] ?? '')),
            'package_title' => strval($row['package_title'] ?? ''),
            'member_id' => intval($row['member_id'] ?? 0),
            'member_name' => strval($row['nickname'] ?? ''),
            'tel' => strval($row['tel'] ?? ''),
            'amount' => number_format(floatval($row['amount'] ?? ($row['order_price'] ?? 0)), 2, '.', ''),
            'type' => intval($row['type'] ?? 1),
            'type_text' => intval($row['type'] ?? 1) === 2 ? '增值税发票' : '普通发票',
            'name_type' => intval($row['name_type'] ?? 1),
            'name_type_text' => intval($row['name_type'] ?? 1) === 2 ? '企业' : '个人',
            'invoice_name' => strval($row['invoice_name'] ?? ''),
            'tax_no' => strval($row['tax_no'] ?? ''),
            'address' => strval($row['address'] ?? ''),
            'bank_name' => strval($row['bank_name'] ?? ''),
            'bank_account' => strval($row['bank_account'] ?? ''),
            'mobile' => strval($row['mobile'] ?? ''),
            'email' => strval($row['email'] ?? ''),
            'status' => $status,
            'status_text' => self::invoiceStatusText($status),
            'admin_remark' => strval($row['admin_remark'] ?? ''),
            'reject_reason' => $status === 2 ? strval($row['admin_remark'] ?? '') : '',
            'allow_reapply' => $allowReapply,
            'can_reapply' => $allowReapply,
            'create_time' => $createTime,
            'create_time_text' => $createTime > 0 ? date('Y-m-d H:i:s', $createTime) : '',
            'update_time' => $updateTime,
            'update_time_text' => $updateTime > 0 ? date('Y-m-d H:i:s', $updateTime) : '',
        ];
    }

    private static function platformText(string $platform): string
    {
        $map = [
            'wx' => '微信小程序',
            'mp' => '微信公众号',
            'h5' => 'H5',
            'app' => 'App',
            'alipay' => '支付宝',
        ];
        return $map[$platform] ?? ($platform !== '' ? $platform : '-');
    }

    private static function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code);
        return strval($code);
    }

    private static function makeUniqueCode(int $aid): string
    {
        for ($i = 0; $i < 12; $i++) {
            $code = 'DA' . strtoupper(bin2hex(random_bytes(5)));
            $exists = Db::name('digitalavatar_redeem_code')->where('aid', $aid)->where('code', $code)->find();
            if (!$exists) {
                return $code;
            }
        }
        throw new \RuntimeException('兑换码生成冲突，请重试');
    }
}
