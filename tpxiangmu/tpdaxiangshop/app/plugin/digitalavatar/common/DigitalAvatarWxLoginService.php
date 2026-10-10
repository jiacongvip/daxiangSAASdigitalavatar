<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarWxLoginService
{
    private const TTL = 300;
    private const PREFIX = 'dalogin_';

    public static function scenePrefix(): string
    {
        return self::PREFIX;
    }

    public static function createTicket(int $aid): array
    {
        if ($aid <= 0) {
            return ['status' => 0, 'msg' => '请先传入商户ID'];
        }
        $token = bin2hex(random_bytes(8));
        $scene = self::PREFIX . $token;
        $accessToken = \app\admin\common\Wechat::access_token($aid, 'mp');
        if (!$accessToken) {
            return ['status' => 0, 'msg' => '公众号未配置或授权失败，暂时无法扫码登录'];
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $accessToken;
        $payload = [
            'expire_seconds' => self::TTL,
            'action_name' => 'QR_STR_SCENE',
            'action_info' => ['scene' => ['scene_str' => $scene]],
        ];
        $rs = json_decode(request_post($url, jsonEncode($payload)), true);
        if (!is_array($rs) || empty($rs['url'])) {
            $msg = \app\admin\common\Wechat::geterror(is_array($rs) ? $rs : []);
            return ['status' => 0, 'msg' => $msg !== '' ? $msg : '生成登录二维码失败'];
        }
        $qr = function_exists('createqrcode') ? strval(createqrcode($rs['url'])) : '';
        cache(self::cacheKey($token), [
            'aid' => $aid,
            'token' => $token,
            'state' => 'pending',
            'openid' => '',
            'unionid' => '',
            'nickname' => '',
            'headimg' => '',
            'mid' => 0,
            'expire_at' => time() + self::TTL,
        ], self::TTL);
        return [
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'token' => $token,
                'qr_url' => $qr,
                'expire' => self::TTL,
            ],
        ];
    }

    public static function onOfficialScan(int $aid, string $openid, string $eventKey): void
    {
        $raw = str_replace('qrscene_', '', trim($eventKey));
        if (strpos($raw, self::BIND_PREFIX) === 0) {
            self::onBindScan($aid, $openid, $raw);
            return;
        }
        self::onLoginScan($aid, $openid, $eventKey);
    }

    private static function onLoginScan(int $aid, string $openid, string $eventKey): void
    {
        $openid = trim($openid);
        $token = self::tokenFromEvent($eventKey);
        if ($aid <= 0 || $openid === '' || $token === '') {
            return;
        }
        $ticket = self::ticket($token);
        if (!$ticket || intval($ticket['aid'] ?? 0) !== $aid) {
            return;
        }
        $profile = self::fetchFans($aid, $openid);
        $member = Db::name('member')->where('aid', $aid)->where('mpopenid', $openid)->find();
        $ticket['openid'] = $openid;
        $ticket['unionid'] = strval($profile['unionid'] ?? '');
        $ticket['nickname'] = strval($profile['nickname'] ?? '');
        $ticket['headimg'] = strval($profile['headimgurl'] ?? '');
        $tel = trim(strval($member['tel'] ?? ''));
        if ($member && preg_match('/^1[3456789]\d{9}$/', $tel)) {
            $ticket['state'] = 'ready';
            $ticket['mid'] = intval($member['id'] ?? 0);
        } else {
            $ticket['state'] = 'need_bind';
            $ticket['mid'] = intval($member['id'] ?? 0);
        }
        cache(self::cacheKey($token), $ticket, max(30, intval($ticket['expire_at'] ?? 0) - time()));
    }

    public static function poll(int $aid, string $token): array
    {
        $ticket = self::requireTicket($aid, $token);
        if (isset($ticket['status']) && intval($ticket['status']) === 0) {
            return $ticket;
        }
        $state = strval($ticket['state'] ?? 'pending');
        return [
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'state' => $state,
                'nickname' => strval($ticket['nickname'] ?? ''),
                'headimg' => strval($ticket['headimg'] ?? ''),
            ],
            'ticket' => $state === 'ready' ? $ticket : null,
        ];
    }

    public static function bindPhone(int $aid, string $token, string $tel, array $extra = []): array
    {
        $ticket = self::requireTicket($aid, $token);
        if (isset($ticket['status']) && intval($ticket['status']) === 0) {
            return $ticket;
        }
        if (strval($ticket['state'] ?? '') !== 'need_bind' || trim(strval($ticket['openid'] ?? '')) === '') {
            return ['status' => 0, 'msg' => '请先使用微信扫码关注公众号'];
        }
        if (!preg_match('/^1[3456789]\d{9}$/', $tel)) {
            return ['status' => 0, 'msg' => '手机号格式错误'];
        }
        $openid = trim(strval($ticket['openid']));
        $phoneMember = Db::name('member')->where('aid', $aid)->where('tel', $tel)->find();
        $openidMember = Db::name('member')->where('aid', $aid)->where('mpopenid', $openid)->find();
        if ($phoneMember) {
            $bound = trim(strval($phoneMember['mpopenid'] ?? ''));
            if ($bound !== '' && $bound !== $openid) {
                return ['status' => 0, 'msg' => '该手机号码已存在并绑定了其他微信，请换其他手机号码绑定或者用老账号登陆'];
            }
            if (intval($phoneMember['is_blacklist'] ?? 0) === 1) {
                return ['status' => 0, 'msg' => '该账号已被加入黑名单，无法登录使用'];
            }
            if (intval($phoneMember['checkst'] ?? 1) === 0) {
                return ['status' => 0, 'msg' => '账号审核中'];
            }
            if (intval($phoneMember['checkst'] ?? 1) === 2) {
                return ['status' => 0, 'msg' => '账号审核未通过'];
            }
            $update = ['mpopenid' => $openid];
            if (trim(strval($phoneMember['unionid'] ?? '')) === '' && trim(strval($ticket['unionid'] ?? '')) !== '') {
                $update['unionid'] = strval($ticket['unionid']);
            }
            if (trim(strval($phoneMember['headimg'] ?? '')) === '' && trim(strval($ticket['headimg'] ?? '')) !== '') {
                $update['headimg'] = strval($ticket['headimg']);
            }
            Db::name('member')->where('aid', $aid)->where('id', intval($phoneMember['id']))->update($update);
            if ($openidMember && intval($openidMember['id']) !== intval($phoneMember['id'])) {
                Db::name('member')->where('aid', $aid)->where('id', intval($openidMember['id']))->update(['mpopenid' => '']);
            }
            $member = Db::name('member')->where('aid', $aid)->where('id', intval($phoneMember['id']))->find();
        } else {
            $member = self::createMember($aid, $tel, $ticket, $openidMember, $extra);
            if (isset($member['status']) && intval($member['status']) === 0) {
                return $member;
            }
        }
        cache(self::cacheKey($token), null);
        return ['status' => 1, 'msg' => '绑定成功', 'member' => $member];
    }

    private static function createMember(int $aid, string $tel, array $ticket, $openidMember, array $extra)
    {
        $now = time();
        $nickname = trim(strval($ticket['nickname'] ?? ''));
        if ($nickname === '' || $nickname === '关注用户') {
            $nickname = substr($tel, 0, 3) . '****' . substr($tel, -4);
        }
        $headimg = trim(strval($ticket['headimg'] ?? ''));
        if ($headimg === '') {
            $headimg = defined('PRE_URL') ? PRE_URL . '/static/img/touxiang.png' : '';
        }
        $pwd = bin2hex(random_bytes(6));
        if ($openidMember && trim(strval($openidMember['tel'] ?? '')) === '') {
            Db::name('member')->where('aid', $aid)->where('id', intval($openidMember['id']))->update([
                'tel' => $tel,
                'pwd' => md5($pwd),
                'nickname' => $nickname,
                'headimg' => $headimg !== '' ? $headimg : strval($openidMember['headimg'] ?? ''),
                'unionid' => trim(strval($ticket['unionid'] ?? '')) ?: strval($openidMember['unionid'] ?? ''),
                'mpopenid' => strval($ticket['openid']),
                'last_visittime' => $now,
            ]);
            return Db::name('member')->where('aid', $aid)->where('id', intval($openidMember['id']))->find();
        }
        $data = [
            'aid' => $aid,
            'tel' => $tel,
            'pwd' => md5($pwd),
            'nickname' => $nickname,
            'sex' => 3,
            'headimg' => $headimg,
            'mpopenid' => strval($ticket['openid']),
            'unionid' => strval($ticket['unionid'] ?? ''),
            'subscribe' => 1,
            'createtime' => $now,
            'last_visittime' => $now,
            'platform' => 'mp',
        ];
        $inviteMid = intval($extra['invite_mid'] ?? 0);
        if ($inviteMid > 0) {
            $inviter = Db::name('member')->where('aid', $aid)->where('id', $inviteMid)->find();
            if ($inviter) {
                $data['pid'] = $inviteMid;
            }
        }
        $mid = intval((new \app\admin\model\Member())->add($aid, $data));
        if ($mid <= 0) {
            return ['status' => 0, 'msg' => '创建账号失败，请稍后再试'];
        }
        if (!empty($data['pid'])) {
            \app\admin\common\Common::user_tjscore($aid, $mid);
            try {
                DigitalAvatarInviteService::onRegister($aid, intval($extra['bid'] ?? 0), $mid, intval($data['pid']));
            } catch (\Throwable $e) {
            }
        }
        \app\admin\common\Common::registerGive($aid, array_merge($data, ['id' => $mid]));
        \app\admin\common\Member::getAddContributionFx($aid, $mid);
        return Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
    }

    private static function fetchFans(int $aid, string $openid): array
    {
        $token = \app\admin\common\Wechat::access_token($aid, 'mp');
        if (!$token) {
            return [];
        }
        $rs = curl_get('https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $token . '&openid=' . rawurlencode($openid) . '&lang=zh_CN');
        $json = json_decode(strval($rs), true);
        return is_array($json) ? $json : [];
    }

    private static function requireTicket(int $aid, string $token): array
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        $ticket = self::ticket($token);
        if (!$ticket || intval($ticket['aid'] ?? 0) !== $aid) {
            return ['status' => 0, 'msg' => '二维码已失效，请重新获取'];
        }
        return $ticket;
    }

    private static function ticket(string $token): array
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        if ($token === '') {
            return [];
        }
        $row = cache(self::cacheKey($token));
        return is_array($row) ? $row : [];
    }

    private static function tokenFromEvent(string $eventKey): string
    {
        $eventKey = str_replace('qrscene_', '', trim($eventKey));
        if (strpos($eventKey, self::PREFIX) !== 0) {
            return '';
        }
        return preg_replace('/[^a-f0-9]/', '', strtolower(substr($eventKey, strlen(self::PREFIX))));
    }

    public const BIND_CONFLICT = '绑定失败：该微信号已经绑定其他账号，请用其他微信号绑定';

    private const BIND_PREFIX = 'dabind_';

    public static function bindStatus(int $aid, int $mid): array
    {
        self::ensureWxTable();
        $member = self::memberRow($aid, $mid);
        if (!$member) {
            return ['status' => 0, 'msg' => '会员不存在'];
        }
        $openid = trim(strval($member['mpopenid'] ?? ''));
        $nickname = '';
        if ($openid !== '') {
            $nickname = self::storedWxName($aid, $mid, $openid);
        }
        $tel = trim(strval($member['tel'] ?? ''));
        return [
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'bound' => $openid !== '' ? 1 : 0,
                'nickname' => $nickname,
                'tel_masked' => self::maskTel($tel),
                'has_tel' => preg_match('/^1[3456789]\d{9}$/', $tel) ? 1 : 0,
            ],
        ];
    }

    public static function createBindTicket(int $aid, int $mid): array
    {
        self::ensureWxTable();
        $member = self::memberRow($aid, $mid);
        if (!$member) {
            return ['status' => 0, 'msg' => '会员不存在'];
        }
        if (trim(strval($member['mpopenid'] ?? '')) !== '') {
            return ['status' => 0, 'msg' => '当前账号已绑定微信号，请先验证手机号后再修改'];
        }
        if ($aid <= 0) {
            return ['status' => 0, 'msg' => '请先传入商户ID'];
        }
        $token = bin2hex(random_bytes(8));
        $scene = self::BIND_PREFIX . $token;
        $accessToken = \app\admin\common\Wechat::access_token($aid, 'mp');
        if (!$accessToken) {
            return ['status' => 0, 'msg' => '公众号未配置或授权失败，暂时无法绑定'];
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $accessToken;
        $payload = [
            'expire_seconds' => self::TTL,
            'action_name' => 'QR_STR_SCENE',
            'action_info' => ['scene' => ['scene_str' => $scene]],
        ];
        $rs = json_decode(request_post($url, jsonEncode($payload)), true);
        if (!is_array($rs) || empty($rs['url'])) {
            $msg = \app\admin\common\Wechat::geterror(is_array($rs) ? $rs : []);
            return ['status' => 0, 'msg' => $msg !== '' ? $msg : '生成绑定二维码失败'];
        }
        $qr = function_exists('createqrcode') ? strval(createqrcode($rs['url'])) : '';
        cache(self::bindCacheKey($token), [
            'aid' => $aid,
            'mid' => $mid,
            'token' => $token,
            'state' => 'pending',
            'openid' => '',
            'nickname' => '',
            'message' => '',
            'expire_at' => time() + self::TTL,
        ], self::TTL);
        return [
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'token' => $token,
                'qr_url' => $qr,
                'expire' => self::TTL,
            ],
        ];
    }

    public static function pollBind(int $aid, int $mid, string $token): array
    {
        $ticket = self::requireBindTicket($aid, $mid, $token);
        if (isset($ticket['status']) && intval($ticket['status']) === 0) {
            return $ticket;
        }
        return [
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'state' => strval($ticket['state'] ?? 'pending'),
                'nickname' => strval($ticket['nickname'] ?? ''),
                'message' => strval($ticket['message'] ?? ''),
            ],
        ];
    }

    public static function clearBind(int $aid, int $mid): array
    {
        self::ensureWxTable();
        $member = self::memberRow($aid, $mid);
        if (!$member) {
            return ['status' => 0, 'msg' => '会员不存在'];
        }
        Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['mpopenid' => '']);
        Db::name('digitalavatar_member_wx')->where('aid', $aid)->where('member_id', $mid)->delete();
        return self::bindStatus($aid, $mid);
    }

    private static function onBindScan(int $aid, string $openid, string $eventKey): void
    {
        try {
            self::ensureWxTable();
            $openid = trim($openid);
            $token = preg_replace('/[^a-f0-9]/', '', strtolower(substr($eventKey, strlen(self::BIND_PREFIX))));
            if ($aid <= 0 || $openid === '' || $token === '') {
                return;
            }
            $ticket = cache(self::bindCacheKey($token));
            if (!is_array($ticket) || intval($ticket['aid'] ?? 0) !== $aid) {
                return;
            }
            $mid = intval($ticket['mid'] ?? 0);
            if ($mid <= 0) {
                return;
            }
            $profile = self::fetchFans($aid, $openid);
            $nickname = trim(strval($profile['nickname'] ?? ''));
            if ($nickname === '' || $nickname === '关注用户') {
                $nickname = '微信用户';
            }
            $owner = Db::name('member')->where('aid', $aid)->where('mpopenid', $openid)->find();
            if ($owner && intval($owner['id'] ?? 0) !== $mid) {
                $ticket['state'] = 'conflict';
                $ticket['openid'] = $openid;
                $ticket['nickname'] = '';
                $ticket['message'] = self::BIND_CONFLICT;
            } else {
                Db::name('member')->where('aid', $aid)->where('id', $mid)->update([
                    'mpopenid' => $openid,
                    'subscribe' => 1,
                ]);
                self::saveWxProfile($aid, $mid, $openid, $nickname, strval($profile['headimgurl'] ?? ''));
                $ticket['state'] = 'bound';
                $ticket['openid'] = $openid;
                $ticket['nickname'] = $nickname;
                $ticket['message'] = '';
            }
            cache(self::bindCacheKey($token), $ticket, max(30, intval($ticket['expire_at'] ?? 0) - time()));
        } catch (\Throwable $e) {
        }
    }

    private static function memberRow(int $aid, int $mid)
    {
        if ($aid <= 0 || $mid <= 0) {
            return null;
        }
        $row = Db::name('member')->where('aid', $aid)->where('id', $mid)->field('id,tel,mpopenid')->find();
        return $row ?: null;
    }

    private static function storedWxName(int $aid, int $mid, string $openid): string
    {
        $row = Db::name('digitalavatar_member_wx')->where('aid', $aid)->where('member_id', $mid)->find();
        $savedOpenid = trim(strval($row['mpopenid'] ?? ''));
        $savedName = trim(strval($row['nickname'] ?? ''));
        if ($row && $savedOpenid === $openid && $savedName !== '') {
            return $savedName;
        }
        $profile = self::fetchFans($aid, $openid);
        $nickname = trim(strval($profile['nickname'] ?? ''));
        if ($nickname === '' || $nickname === '关注用户') {
            $nickname = '微信用户';
        }
        self::saveWxProfile($aid, $mid, $openid, $nickname, strval($profile['headimgurl'] ?? ''));
        return $nickname;
    }

    private static function saveWxProfile(int $aid, int $mid, string $openid, string $nickname, string $headimg): void
    {
        $now = time();
        $data = [
            'mpopenid' => $openid,
            'nickname' => $nickname,
            'headimg' => $headimg,
            'update_time' => $now,
        ];
        $exists = Db::name('digitalavatar_member_wx')->where('aid', $aid)->where('member_id', $mid)->find();
        if ($exists) {
            Db::name('digitalavatar_member_wx')->where('aid', $aid)->where('member_id', $mid)->update($data);
            return;
        }
        $data['aid'] = $aid;
        $data['member_id'] = $mid;
        Db::name('digitalavatar_member_wx')->insert($data);
    }

    private static function maskTel(string $tel): string
    {
        if (!preg_match('/^1[3456789]\d{9}$/', $tel)) {
            return '';
        }
        return substr($tel, 0, 3) . '****' . substr($tel, -4);
    }

    private static function requireBindTicket(int $aid, int $mid, string $token): array
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        $ticket = $token === '' ? [] : cache(self::bindCacheKey($token));
        if (!is_array($ticket) || intval($ticket['aid'] ?? 0) !== $aid || intval($ticket['mid'] ?? 0) !== $mid) {
            return ['status' => 0, 'msg' => '二维码已失效，请重新获取'];
        }
        return $ticket;
    }

    private static function bindCacheKey(string $token): string
    {
        return 'da_wxbind_' . preg_replace('/[^a-f0-9]/', '', strtolower($token));
    }

    private static function ensureWxTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_member_wx');
        try {
            $exists = Db::query("SHOW TABLES LIKE '{$table}'");
            if ($exists) {
                return;
            }
            Db::execute(
                "CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `aid` int(11) NOT NULL DEFAULT '0',
                  `member_id` int(11) NOT NULL DEFAULT '0',
                  `mpopenid` varchar(64) NOT NULL DEFAULT '',
                  `nickname` varchar(120) NOT NULL DEFAULT '',
                  `headimg` varchar(500) NOT NULL DEFAULT '',
                  `update_time` int(11) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_aid_member` (`aid`,`member_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人会员微信号'"
            );
        } catch (\Throwable $e) {
        }
    }

    private static function cacheKey(string $token): string
    {
        return 'da_wxlogin_' . $token;
    }
}
