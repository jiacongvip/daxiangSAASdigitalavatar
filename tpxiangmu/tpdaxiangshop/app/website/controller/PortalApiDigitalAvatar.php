<?php

namespace app\website\controller;

use app\admin\controller\ApiCommon;
use app\plugin\digitalavatar\common\DigitalAvatarCheckinService;
use app\plugin\digitalavatar\common\DigitalAvatarInviteService;
use app\plugin\digitalavatar\common\DigitalAvatarCommerceService;
use app\plugin\digitalavatar\common\DigitalAvatarCreditService;
use app\plugin\digitalavatar\common\DigitalAvatarImageAgentRuntime;
use app\plugin\digitalavatar\common\DigitalAvatarInstaller;
use app\plugin\digitalavatar\common\DigitalAvatarPayService;
use app\plugin\digitalavatar\common\DigitalAvatarService;
use app\plugin\digitalavatar\common\DigitalAvatarSidebarService;
use app\plugin\digitalavatar\common\DigitalAvatarConversationService;
use app\plugin\digitalavatar\common\DigitalAvatarInspireService;
use app\plugin\digitalavatar\common\DigitalAvatarTaskService;
use app\plugin\digitalavatar\common\DigitalAvatarWxLoginService;
use think\facade\Db;
use think\facade\Session;

class PortalApiDigitalAvatar extends ApiCommon
{
    // admin_upload.platform is varchar(11) in existing installations.
    private const MEMBER_MEDIA_PLATFORM = 'davportal';

    public function initialize()
    {
        parent::initialize();
        DigitalAvatarInstaller::ensureInstalled();
        DigitalAvatarCreditService::ensureSettings($this->resolveAid());
    }

    public function getLoginConfig()
    {
        try {
            $aid = $this->resolveAid();
            if ($aid <= 0) {
                return json(['status' => 0, 'msg' => '请先传入商户ID']);
            }
            return json(['status' => 1, 'msg' => '获取成功', 'data' => $this->buildLoginConfig($aid)]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取登录配置失败：' . $e->getMessage()]);
        }
    }

    public function getLoginCaptcha()
    {
        try {
            $this->resolveAid();
            return json(['status' => 1, 'msg' => '获取成功', 'data' => $this->makeLoginCaptcha()]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取验证码失败：' . $e->getMessage()]);
        }
    }

    public function sendLoginSms()
    {
        try {
            $aid = $this->resolveAid();
            $config = $this->buildLoginConfig($aid);
            if (intval($config['needsms'] ?? 0) !== 1) {
                return json(['status' => 0, 'msg' => '当前商户未配置短信验证码']);
            }
            $payload = $this->portalPayload();
            $tel = trim(strval($payload['tel'] ?? input('post.tel/s', '')));
            if (!preg_match('/^1[3456789]\d{9}$/', $tel)) {
                return json(['status' => 0, 'msg' => '手机号格式错误']);
            }
            $scene = trim(strval($payload['scene'] ?? input('post.scene/s', '')));
            if ($scene === 'reset') {
                $member = Db::name('member')->where('aid', $aid)->where('tel', $tel)->find();
                if (!$member || trim(strval($member['tel'] ?? '')) === '') {
                    return json(['status' => 0, 'msg' => '如无法收到验证码或未绑定手机号码请联系管理员找回']);
                }
            }
            if (strval($config['login_style'] ?? '') === 'niwa') {
                $captchaCheck = $this->verifyLoginCaptcha(strval($payload['captcha'] ?? input('post.captcha/s', '')));
                if (intval($captchaCheck['status'] ?? 0) !== 1) {
                    return json($captchaCheck);
                }
            }
            $code = rand(100000, 999999);
            cache($this->sessionid . '_smscode', md5($tel . '-' . $code), 600);
            cache($this->sessionid . '_smscodetimes', 0);
            return json(\app\admin\common\Sms::send($aid, $tel, 'tmpl_smscode', ['code' => $code]));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '发送失败：' . $e->getMessage()]);
        }
    }

    public function submitMemberLogin()
    {
        try {
            $aid = $this->resolveAid();
            $config = $this->buildLoginConfig($aid);
            $payload = $this->portalPayload();
            $tel = trim(strval($payload['tel'] ?? input('post.tel/s', '')));
            $pwd = strval($payload['pwd'] ?? input('post.pwd/s', ''));
            if (!preg_match('/^1[3456789]\d{9}$/', $tel)) {
                return json(['status' => 0, 'msg' => '手机号格式错误']);
            }
            if ($pwd === '') {
                return json(['status' => 0, 'msg' => '请输入登录密码']);
            }
            if (strval($config['login_style'] ?? '') === 'niwa') {
                $captchaCheck = $this->verifyLoginCaptcha(strval($payload['captcha'] ?? input('post.captcha/s', '')));
                if (intval($captchaCheck['status'] ?? 0) !== 1) {
                    return json($captchaCheck);
                }
            }
            return json($this->loginByPassword($aid, $tel, $pwd, $this->rememberLoginDays()));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '登录失败：' . $e->getMessage()]);
        }
    }

    public function submitMemberSmsLogin()
    {
        try {
            $aid = $this->resolveAid();
            $config = $this->buildLoginConfig($aid);
            if (intval($config['needsms'] ?? 0) !== 1) {
                return json(['status' => 0, 'msg' => '当前商户未配置短信验证码']);
            }
            $payload = $this->portalPayload();
            $tel = trim(strval($payload['tel'] ?? input('post.tel/s', '')));
            $smscode = trim(strval($payload['smscode'] ?? input('post.smscode/s', '')));
            if (!preg_match('/^1[3456789]\d{9}$/', $tel)) {
                return json(['status' => 0, 'msg' => '手机号格式错误']);
            }
            $verify = $this->verifySmsCode($tel, $smscode);
            if (intval($verify['status'] ?? 0) !== 1) {
                return json($verify);
            }
            $member = Db::name('member')->where('aid', $aid)->where('tel', $tel)->find();
            if (!$member) {
                return json(['status' => 0, 'msg' => '该手机号尚未注册，请先注册账号']);
            }
            if (intval($member['checkst'] ?? 1) === 0) {
                return json(['status' => 0, 'msg' => '账号审核中']);
            }
            if (intval($member['checkst'] ?? 1) === 2) {
                return json(['status' => 0, 'msg' => '账号审核未通过']);
            }
            if (intval($member['is_blacklist'] ?? 0) === 1) {
                return json(['status' => 0, 'msg' => '该账号已被加入黑名单，无法登录使用']);
            }
            return json($this->finishMemberLogin($aid, $member, $this->rememberLoginDays()));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '登录失败：' . $e->getMessage()]);
        }
    }

    public function submitMemberRegister()
    {
        try {
            $aid = $this->resolveAid();
            $config = $this->buildLoginConfig($aid);
            $payload = $this->portalPayload();
            $tel = trim(strval($payload['tel'] ?? input('post.tel/s', '')));
            $pwd = strval($payload['pwd'] ?? input('post.pwd/s', ''));
            $confirmPassword = strval($payload['confirm_password'] ?? input('post.confirm_password/s', ''));
            $smscode = trim(strval($payload['smscode'] ?? input('post.smscode/s', '')));
            $inviteCode = trim(strval($payload['invite_code'] ?? input('post.invite_code/s', '')));
            $inviteMid = intval($payload['invite_mid'] ?? ($payload['inviteMid'] ?? input('post.invite_mid/d', 0)));
            $agreedProtocol = intval($payload['agreed_protocol'] ?? input('post.agreed_protocol/d', 0));

            if (!preg_match('/^1[3456789]\d{9}$/', $tel)) {
                return json(['status' => 0, 'msg' => '手机号格式错误']);
            }
            if (mb_strlen($pwd) < 6 || mb_strlen($pwd) > 20) {
                return json(['status' => 0, 'msg' => '请设置 6-20 位登录密码']);
            }
            if ($pwd !== $confirmPassword) {
                return json(['status' => 0, 'msg' => '两次输入的密码不一致']);
            }
            if (intval($config['xystatus'] ?? 0) === 1 && $agreedProtocol !== 1) {
                return json(['status' => 0, 'msg' => '请先勾选并同意会员协议']);
            }
            if (strval($config['login_style'] ?? '') === 'niwa' && intval($config['needsms'] ?? 0) !== 1) {
                $captchaCheck = $this->verifyLoginCaptcha(strval($this->portalPayload()['captcha'] ?? input('post.captcha/s', '')));
                if (intval($captchaCheck['status'] ?? 0) !== 1) {
                    return json($captchaCheck);
                }
            }
            if (intval($config['needsms'] ?? 0) === 1) {
                $verify = $this->verifySmsCode($tel, $smscode);
                if (intval($verify['status'] ?? 0) !== 1) {
                    return json($verify);
                }
            }

            $exists = Db::name('member')->where('aid', $aid)->where('tel', $tel)->find();
            if ($exists) {
                return json(['status' => 0, 'msg' => '该手机号已注册，请直接登录']);
            }

            $inviteMember = null;
            if ($inviteMid > 0 || $inviteCode !== '') {
                $inviteMember = DigitalAvatarInviteService::resolveInviter(
                    $aid,
                    $inviteMid,
                    $inviteCode,
                    intval($config['reg_invite_code_type'] ?? 0)
                );
            }
            if (intval($config['reg_invite_code'] ?? 0) === 2 && !$inviteMember) {
                return json(['status' => 0, 'msg' => '请填写正确的邀请信息']);
            }
            if ($inviteCode !== '' && !$inviteMember) {
                return json(['status' => 0, 'msg' => '邀请信息不正确']);
            }

            $now = time();
            $data = [
                'aid' => $aid,
                'tel' => $tel,
                'pwd' => md5($pwd),
                'nickname' => substr($tel, 0, 3) . '****' . substr($tel, -4),
                'sex' => 3,
                'headimg' => defined('PRE_URL') ? PRE_URL . '/static/img/touxiang.png' : '',
                'createtime' => $now,
                'last_visittime' => $now,
                'platform' => 'h5',
            ];
            if ($inviteMember) {
                $data['pid'] = intval($inviteMember['id'] ?? 0);
            }

            $mid = intval((new \app\admin\model\Member())->add($aid, $data));
            if ($mid <= 0) {
                return json(['status' => 0, 'msg' => '注册失败，请稍后再试']);
            }
            if (!empty($data['pid'])) {
                \app\admin\common\Common::user_tjscore($aid, $mid);
                try {
                    DigitalAvatarInviteService::onRegister($aid, $this->resolveBid(), $mid, intval($data['pid']));
                } catch (\Throwable $e) {
                }
            }
            \app\admin\common\Common::registerGive($aid, array_merge($data, ['id' => $mid]));
            \app\admin\common\Member::getAddContributionFx($aid, $mid);

            $member = Db::name('member')->where('aid', $aid)->where('id', $mid)->find();
            if (!$member) {
                return json(['status' => 0, 'msg' => '注册成功，但读取会员信息失败']);
            }
            cache($this->sessionid . '_smscode', null);
            cache($this->sessionid . '_smscodetimes', null);

            if (intval($member['checkst'] ?? 1) === 1) {
                $result = $this->finishMemberLogin($aid, $member, $this->rememberLoginDays());
                $result['msg'] = '注册成功，已自动进入 AI 数字人工作台';
                return json($result);
            }

            return json([
                'status' => 1,
                'msg' => '注册成功，请等待管理员审核',
                'data' => [
                    'mid' => $mid,
                    'auto_logged_in' => 0,
                    'member' => $this->formatMember($member),
                ],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '注册失败：' . $e->getMessage()]);
        }
    }

    public function resetMemberPassword()
    {
        try {
            $aid = $this->resolveAid();
            $config = $this->buildLoginConfig($aid);
            if (intval($config['needsms'] ?? 0) !== 1) {
                return json(['status' => 0, 'msg' => '如无法收到验证码或未绑定手机号码请联系管理员找回']);
            }
            $payload = $this->portalPayload();
            $tel = trim(strval($payload['tel'] ?? input('post.tel/s', '')));
            $pwd = strval($payload['pwd'] ?? input('post.pwd/s', ''));
            $confirm = strval($payload['confirm_password'] ?? input('post.confirm_password/s', ''));
            $smscode = trim(strval($payload['smscode'] ?? input('post.smscode/s', '')));
            if (!preg_match('/^1[3456789]\d{9}$/', $tel)) {
                return json(['status' => 0, 'msg' => '手机号格式错误']);
            }
            $member = Db::name('member')->where('aid', $aid)->where('tel', $tel)->find();
            if (!$member) {
                return json(['status' => 0, 'msg' => '如无法收到验证码或未绑定手机号码请联系管理员找回']);
            }
            if (mb_strlen($pwd) < 6 || mb_strlen($pwd) > 20) {
                return json(['status' => 0, 'msg' => '请设置 6-20 位登录密码']);
            }
            if ($pwd !== $confirm) {
                return json(['status' => 0, 'msg' => '两次输入的密码不一致']);
            }
            $verify = $this->verifySmsCode($tel, $smscode);
            if (intval($verify['status'] ?? 0) !== 1) {
                return json($verify);
            }
            Db::name('member')->where('aid', $aid)->where('id', intval($member['id']))->update(['pwd' => md5($pwd)]);
            return json(['status' => 1, 'msg' => '密码已重置，请使用新密码登录', 'data' => ['reset' => 1]]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '重置失败：' . $e->getMessage()]);
        }
    }

    public function createWxLoginQr()
    {
        try {
            return json(DigitalAvatarWxLoginService::createTicket($this->resolveAid()));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '生成二维码失败：' . $e->getMessage()]);
        }
    }

    public function pollWxLogin()
    {
        try {
            $aid = $this->resolveAid();
            $payload = $this->portalPayload();
            $token = trim(strval($payload['token'] ?? input('post.token/s', '')));
            $polled = DigitalAvatarWxLoginService::poll($aid, $token);
            if (intval($polled['status'] ?? 0) !== 1) {
                return json($polled);
            }
            $ticket = is_array($polled['ticket'] ?? null) ? $polled['ticket'] : null;
            unset($polled['ticket']);
            if ($ticket && intval($ticket['mid'] ?? 0) > 0) {
                $member = Db::name('member')->where('aid', $aid)->where('id', intval($ticket['mid']))->find();
                if (!$member) {
                    return json(['status' => 0, 'msg' => '会员不存在']);
                }
                cache('da_wxlogin_' . preg_replace('/[^a-f0-9]/', '', strtolower($token)), null);
                return json($this->finishMemberLogin($aid, $member, $this->rememberLoginDays()));
            }
            return json($polled);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '查询扫码状态失败：' . $e->getMessage()]);
        }
    }

    public function bindWxLoginPhone()
    {
        try {
            $aid = $this->resolveAid();
            $config = $this->buildLoginConfig($aid);
            if (intval($config['needsms'] ?? 0) !== 1) {
                return json(['status' => 0, 'msg' => '当前商户未配置短信验证码']);
            }
            $payload = $this->portalPayload();
            $tel = trim(strval($payload['tel'] ?? ''));
            $token = trim(strval($payload['token'] ?? ''));
            $smscode = trim(strval($payload['smscode'] ?? ''));
            $verify = $this->verifySmsCode($tel, $smscode);
            if (intval($verify['status'] ?? 0) !== 1) {
                return json($verify);
            }
            $bound = DigitalAvatarWxLoginService::bindPhone($aid, $token, $tel, [
                'invite_mid' => intval($payload['invite_mid'] ?? 0),
                'bid' => $this->resolveBid(),
            ]);
            if (intval($bound['status'] ?? 0) !== 1 || empty($bound['member'])) {
                return json($bound);
            }
            $member = $bound['member'];
            if (intval($member['checkst'] ?? 1) !== 1) {
                return json(['status' => 1, 'msg' => '绑定成功，请等待管理员审核', 'data' => ['auto_logged_in' => 0]]);
            }
            $result = $this->finishMemberLogin($aid, $member, $this->rememberLoginDays());
            $result['msg'] = strval($bound['msg'] ?? '登录成功');
            return json($result);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '绑定失败：' . $e->getMessage()]);
        }
    }

    public function logoutMemberSession()
    {
        try {
            $aid = $this->resolveAid();
            if ($this->sessionid !== '') {
                Db::name('session')->where('aid', $aid)->where('session_id', $this->sessionid)->delete();
                cache($this->sessionid . '_mid', null);
            }
            $this->mid = 0;
            $this->member = [];
            $this->sessionid = Session::getId();
            return json(['status' => 1, 'msg' => '已退出登录', 'data' => ['session_id' => $this->sessionid]]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '退出失败：' . $e->getMessage()]);
        }
    }

    public function bootstrap()
    {
        try {
            $aid = $this->resolveAid();
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'login_config' => $this->buildLoginConfig($aid),
                    'app' => DigitalAvatarService::getBootstrap($aid, $this->resolveBid(), intval($this->mid)),
                ],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '初始化失败：' . $e->getMessage()]);
        }
    }

    public function getSidebarMenu()
    {
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarSidebarService::portalConfig($this->resolveAid()),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取侧栏菜单失败：' . $e->getMessage()]);
        }
    }

    public function getWorkbench()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json(['status' => 1, 'msg' => '获取成功', 'data' => DigitalAvatarService::getWorkbench($this->resolveAid(), $this->resolveBid(), intval($this->mid))]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取工作台失败：' . $e->getMessage()]);
        }
    }

    public function getAvatars()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'public' => DigitalAvatarService::getPublicAvatars($this->resolveAid(), 1, 20, intval($this->mid))['list'],
                    'mine' => DigitalAvatarService::getMemberAvatars($this->resolveAid(), $this->resolveBid(), intval($this->mid)),
                ],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取数字人失败：' . $e->getMessage()]);
        }
    }

    public function getVoices()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'public' => DigitalAvatarService::getPublicVoices($this->resolveAid(), 1, 20, intval($this->mid))['list'],
                    'mine' => DigitalAvatarService::getMemberVoices($this->resolveAid(), $this->resolveBid(), intval($this->mid)),
                ],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取声音失败：' . $e->getMessage()]);
        }
    }

    public function uploadMemberMedia()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }

        $file = request()->file('file');
        if (!$file) {
            return json(['status' => 0, 'msg' => '请选择文件']);
        }

        $category = trim(strval(input('post.category/s', '')));
        $aid = $this->resolveAid();
        $bid = $this->resolveBid();
        $mid = intval($this->mid);

        try {
            $fileSize = 0;
            try {
                $fileSize = intval($file->getSize());
            } catch (\Throwable $e) {
                $tmpPath = $file->getPathname();
                if ($tmpPath && is_file($tmpPath)) {
                    $fileSize = intval(filesize($tmpPath));
                }
            }

            if ($fileSize > 0) {
                $storageCheck = $this->checkPortalStorageLimit($aid, $bid, $fileSize);
                if (intval($storageCheck['status'] ?? 0) !== 1) {
                    return json(['status' => 0, 'msg' => strval($storageCheck['msg'] ?? '存储空间不足')]);
                }
            }

            $allowedExtensions = $this->getPortalAllowedUploadExtensions($category);
            $extension = strtolower(strval($file->getOriginalExtension()));
            if ($extension === '') {
                $originalName = strtolower(strval($file->getOriginalName()));
                if (preg_match('/\.([a-z0-9]+)$/', $originalName, $matches)) {
                    $extension = strtolower(strval($matches[1]));
                }
            }
            if ($extension === '') {
                $mime = '';
                try {
                    $mime = strtolower(strval($file->getMime()));
                } catch (\Throwable $e) {
                    $mime = '';
                }
                if (str_contains($mime, 'webm')) {
                    $extension = 'webm';
                } elseif (str_contains($mime, 'wav')) {
                    $extension = 'wav';
                } elseif (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) {
                    $extension = 'mp3';
                } elseif (str_contains($mime, 'mp4') || str_contains($mime, 'm4a') || str_contains($mime, 'aac')) {
                    $extension = 'm4a';
                } elseif (str_contains($mime, 'ogg')) {
                    $extension = 'ogg';
                } elseif (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) {
                    $extension = 'jpg';
                } elseif (strpos($mime, 'png') !== false) {
                    $extension = 'png';
                } elseif (strpos($mime, 'webp') !== false) {
                    $extension = 'webp';
                } elseif (strpos($mime, 'gif') !== false) {
                    $extension = 'gif';
                }
            }
            if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
                $formatHint = 'MP3、WAV、M4A 或 WEBM';
                if ($category === 'video') {
                    $formatHint = 'MP4、MOV 或 WEBM';
                } elseif (in_array($category, ['image', 'cover'], true)) {
                    $formatHint = 'JPG、PNG 或 WEBP 图片';
                }
                return json(['status' => 0, 'msg' => '不支持的文件格式，请使用 ' . $formatHint]);
            }

            try {
                $savename = \think\facade\Filesystem::disk('upload')->putFile((string)$aid, $file);
                $filepath = 'upload/' . str_replace("\\", '/', $savename);
            } catch (\Throwable $diskException) {
                \think\facade\Log::write('PortalApiDigitalAvatar upload磁盘不可用，使用传统方式: ' . $diskException->getMessage());
                $uploadDir = 'upload/' . $aid . '/' . date('Ymd');
                $fullUploadDir = ROOT_PATH . $uploadDir;
                if (!is_dir($fullUploadDir)) {
                    mk_dir($fullUploadDir, 0755, true);
                }

                $fallbackExt = strtolower(strval($file->getOriginalExtension()));
                if ($fallbackExt !== '') {
                    $extension = $fallbackExt;
                }
                $filename = date('His') . rand(1000, 9999) . '.' . $extension;
                $filepath = $uploadDir . '/' . $filename;
                if (!$file->move($fullUploadDir, $filename)) {
                    throw new \RuntimeException('文件保存失败');
                }
            }

            $realFilePath = ROOT_PATH . $filepath;
            if ($extension === '') {
                $extension = strtolower(strval($file->getOriginalExtension()));
            }
            $name = strval($file->getOriginalName());
            if ($name === '' || !preg_match('/\.[a-z0-9]+$/i', $name)) {
                $name = 'record-' . date('YmdHis') . '.' . $extension;
            }
            $url = (defined('PRE_URL') ? PRE_URL : request()->domain()) . '/' . $filepath;
            $uploadedUrl = \app\admin\common\Pic::uploadoss($url, false, $realFilePath);
            if ($uploadedUrl && $uploadedUrl !== $url) {
                $url = $uploadedUrl;
            }

            $media = [
                'extension' => $extension,
                'name' => $name,
                'size' => $fileSize > 0
                    ? $fileSize
                    : (is_file($realFilePath) ? intval(filesize($realFilePath)) : 0),
                'url' => $url,
            ];
            $media['id'] = Db::name('admin_upload')->insertGetId([
                'aid' => $aid,
                'uid' => $mid,
                'name' => $name,
                'dir' => date('Ymd'),
                'url' => $url,
                'type' => $extension,
                'width' => 0,
                'height' => 0,
                'size' => intval($media['size']),
                'createtime' => time(),
                'gid' => 0,
                'platform' => self::MEMBER_MEDIA_PLATFORM,
                'merchant_id' => $bid,
            ]);

            $assetType = 'video';
            if ($category === 'audio') {
                $assetType = 'audio';
            } elseif (in_array($category, ['image', 'cover'], true) || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true)) {
                $assetType = 'image';
            }

            // 上传素材也进入数字人资产中心，确保会员后续能从真实资产列表继续使用。
            Db::name('digitalavatar_asset')->insert([
                'aid' => $aid,
                'bid' => max(0, $bid),
                'member_id' => $mid,
                'task_id' => 0,
                'asset_type' => $assetType,
                'name' => mb_substr($name, 0, 191),
                'url' => $url,
                'mime_type' => $extension !== '' ? 'application/' . $extension : '',
                'size' => intval($media['size']),
                'duration' => 0,
                'metadata_json' => json_encode(['source' => 'member_upload', 'upload_id' => intval($media['id'])], JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'create_time' => time(),
                'update_time' => time(),
                'delete_time' => 0,
            ]);

            return json(['status' => 1, 'msg' => '上传成功', 'data' => $media]);
        } catch (\think\exception\ValidateException $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '上传失败：' . $e->getMessage()]);
        }
    }

    public function createAvatarCloneTask()
    {
        return $this->createCloneTask('avatar_clone', '数字人克隆任务');
    }

    public function createVoiceCloneTask()
    {
        return $this->createCloneTask('voice_clone', '声音克隆任务');
    }

    public function previewAudio()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            $script = trim(strval($payload['text'] ?? ($payload['content'] ?? ($payload['prompt'] ?? ($payload['script'] ?? '')))));
            $payload['text'] = $script;
            $payload['content'] = $script;
            return json([
                'status' => 1,
                'msg' => '试听音频已生成',
                'data' => DigitalAvatarService::previewAudio(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    $payload
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function createTask()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            $data = DigitalAvatarTaskService::createTask(
                $this->resolveAid(),
                $this->resolveBid(),
                intval($this->mid),
                is_array($payload) ? $payload : []
            );
            $batchCreated = intval($data['batch_created'] ?? 0);
            $msg = $batchCreated > 1
                ? ('已提交 ' . $batchCreated . ' 个图片任务，将按单张分别生成和扣费')
                : '任务已进入队列';
            return json([
                'status' => 1,
                'msg' => $msg,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '创建任务失败：' . $e->getMessage()]);
        }
    }

    public function chatImageAgent()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            if (!is_array($payload)) {
                $payload = [];
            }
            $payload['bid'] = $this->resolveBid();
            $payload['member_id'] = intval($this->mid);
            return json([
                'status' => 1,
                'msg' => '理解完成',
                'data' => DigitalAvatarImageAgentRuntime::run(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    $payload
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function getImageAgentSession()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarImageAgentRuntime::session(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    intval(input('post.session_id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function listImageAgentSessions()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarImageAgentRuntime::listSessions(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    intval(input('post.page/d', 1)),
                    intval(input('post.limit/d', 40))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function upscaleImageAgent()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = input('post.');
            if (!is_array($payload)) {
                $payload = [];
            }
            $payload['bid'] = $this->resolveBid();
            $payload['member_id'] = intval($this->mid);
            return json([
                'status' => 1,
                'msg' => '已提交转高清',
                'data' => DigitalAvatarImageAgentRuntime::upscale(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    $payload
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function getTasks()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            DigitalAvatarConversationService::ensureSchema();
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarTaskService::getMemberList(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    max(1, intval($payload['page'] ?? 1)),
                    max(1, min(200, intval($payload['limit'] ?? 30))),
                    trim(strval($payload['keyword'] ?? '')),
                    trim(strval($payload['status'] ?? '')),
                    trim(strval($payload['task_type'] ?? '')),
                    trim(strval($payload['model_code'] ?? '')),
                    $this->taskListFilters($payload)
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取任务失败：' . $e->getMessage()]);
        }
    }

    public function listConversations()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $offset = intval(input('post.offset/d', -1));
            $limit = intval(input('post.limit/d', 8));
            if ($limit <= 0) {
                $limit = 8;
            }
            if ($offset < 0) {
                $page = max(1, intval(input('post.page/d', 1)));
                $offset = ($page - 1) * $limit;
            }
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarConversationService::listForMember(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    $offset,
                    $limit
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取对话失败：' . $e->getMessage()]);
        }
    }

    public function getConversation()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $beforeId = intval(input('post.before_id/d', 0));
            $afterId = intval(input('post.after_id/d', 0));
            $limit = intval(input('post.limit/d', 0));
            if ($limit <= 0) {
                if ($beforeId > 0) {
                    $limit = 5;
                } elseif ($afterId > 0) {
                    $limit = 20;
                } else {
                    $limit = 6;
                }
            }
            if ($limit > 20) {
                $limit = 20;
            }
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarConversationService::messages(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    intval(input('post.id/d', 0)),
                    $beforeId,
                    $limit,
                    $afterId
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function pinConversation()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已更新',
                'data' => DigitalAvatarConversationService::pin(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval(input('post.id/d', 0)),
                    intval(input('post.pinned/d', 1))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function renameConversation()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已重命名',
                'data' => DigitalAvatarConversationService::rename(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval(input('post.id/d', 0)),
                    strval(input('post.title/s', ''))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function deleteConversation()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已删除对话',
                'data' => DigitalAvatarConversationService::remove(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval(input('post.id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function exportTasks()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            $list = DigitalAvatarTaskService::exportMemberList(
                $this->resolveAid(),
                $this->resolveBid(),
                intval($this->mid),
                trim(strval($payload['keyword'] ?? '')),
                trim(strval($payload['status'] ?? '')),
                trim(strval($payload['task_type'] ?? '')),
                trim(strval($payload['model_code'] ?? '')),
                $this->taskListFilters($payload)
            );
            $filename = '任务中心_' . date('YmdHis') . '.xls';
            $asciiName = 'digitalavatar-tasks-' . date('YmdHis') . '.xls';
            return response("\xEF\xBB\xBF" . DigitalAvatarTaskService::buildTaskExcelBody($list), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
                'Cache-Control' => 'no-store',
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '导出任务失败：' . $e->getMessage()]);
        }
    }

    public function getTaskDetail()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            $taskId = intval($payload['id'] ?? input('post.id/d', 0));
            $recordKind = trim(strval($payload['record_kind'] ?? input('post.record_kind/s', '')));
            $aid = $this->resolveAid();
            $bid = $this->resolveBid();
            $mid = intval($this->mid);
            $task = DigitalAvatarTaskService::getDetail($aid, $taskId, $mid, $recordKind);
            $message = DigitalAvatarConversationService::formatMessage($task);
            $siblings = [$message];
            $conversationId = intval($task['conversation_id'] ?? 0);
            if ($conversationId > 0) {
                try {
                    $pack = DigitalAvatarConversationService::messages($aid, $bid, $mid, $conversationId);
                    if (!empty($pack['list']) && is_array($pack['list'])) {
                        $siblings = $pack['list'];
                    }
                } catch (\Throwable $e) {
                }
            }
            $task['message'] = $message;
            $task['siblings'] = $siblings;
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => $task,
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取任务详情失败：' . $e->getMessage()]);
        }
    }

    public function playTaskMedia()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $taskId = intval(input('id/d', 0));
            if ($taskId <= 0) {
                $taskId = intval(input('post.id/d', 0));
            }
            $task = DigitalAvatarTaskService::getDetail($this->resolveAid(), $taskId, intval($this->mid));
            $media = DigitalAvatarTaskService::playableMedia($task);
            $url = trim(strval($media['url'] ?? ''));
            $kind = strval($media['kind'] ?? '');
            if ($url === '') {
                return json(['status' => 0, 'msg' => '这个任务还没有可预览的结果']);
            }
            $hint = $kind !== '' ? $kind : strval($task['task_type'] ?? '');
            $this->streamRemoteMedia(
                $url,
                strval($task['title'] ?? 'digitalavatar'),
                intval(input('download/d', 0)) === 1,
                $hint
            );
            return null;
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '播放失败：' . $e->getMessage()]);
        }
    }

    public function playAssetMedia()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $assetId = intval(input('id/d', 0));
            if ($assetId <= 0) {
                $assetId = intval(input('post.id/d', 0));
            }
            $row = Db::name('digitalavatar_asset')
                ->where('aid', $this->resolveAid())
                ->where('member_id', intval($this->mid))
                ->where('id', $assetId)
                ->where('delete_time', 0)
                ->find();
            if (!$row) {
                return json(['status' => 0, 'msg' => '资产不存在']);
            }
            $url = trim(strval($row['url'] ?? ''));
            if ($url === '') {
                return json(['status' => 0, 'msg' => '这个文件还没有可预览的地址']);
            }
            $this->streamRemoteMedia(
                $url,
                strval($row['name'] ?? 'digitalavatar-asset'),
                intval(input('download/d', 0)) === 1,
                strval($row['asset_type'] ?? '')
            );
            return null;
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '播放失败：' . $e->getMessage()]);
        }
    }

    public function cancelTask()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '任务已取消',
                'data' => DigitalAvatarTaskService::cancelMemberTask($this->resolveAid(), intval($this->mid), intval(input('post.id/d', 0))),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '取消任务失败：' . $e->getMessage()]);
        }
    }

    public function getAssets()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarTaskService::getAssetList(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    max(1, intval(input('post.page/d', 1))),
                    max(1, min(200, intval(input('post.limit/d', 20)))),
                    trim(strval(input('post.asset_type/s', '')))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取资产失败：' . $e->getMessage()]);
        }
    }

    public function saveCoverImage()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $dataUrl = trim(strval(input('post.data_url', '')));
            if ($dataUrl === '') {
                throw new \RuntimeException('请上传封面图片');
            }
            if (!preg_match('#^data:image/(png|jpe?g|webp|gif);base64,#i', $dataUrl)) {
                throw new \RuntimeException('封面图片格式不支持');
            }
            $comma = strpos($dataUrl, ',');
            $binary = base64_decode(strval(substr($dataUrl, $comma + 1)), true);
            if ($binary === false || $binary === '') {
                throw new \RuntimeException('封面图片解析失败');
            }
            return json([
                'status' => 1,
                'msg' => '封面已保存',
                'data' => DigitalAvatarService::storeMemberImage(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    $binary,
                    trim(strval(input('post.name/s', 'cover.jpg'))) ?: 'cover.jpg'
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '保存封面失败：' . $e->getMessage()]);
        }
    }

    public function updateMemberAvatarCover()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '封面已更新',
                'data' => DigitalAvatarService::updateMemberAvatarCover(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    intval(input('post.id/d', 0)),
                    strval(input('post.cover_url/s', ''))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '更新封面失败：' . $e->getMessage()]);
        }
    }

    public function deleteMemberAvatar()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已删除',
                'data' => DigitalAvatarService::deleteMemberAvatar(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    is_array(input('post.')) ? input('post.') : []
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    public function deleteMemberVoice()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已删除',
                'data' => DigitalAvatarService::deleteMemberVoice(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    is_array(input('post.')) ? input('post.') : []
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    public function updateMemberVoice()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已修改',
                'data' => DigitalAvatarService::updateMemberVoice(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    is_array(input('post.')) ? input('post.') : []
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '修改失败：' . $e->getMessage()]);
        }
    }

    public function deleteMemberAsset()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已删除',
                'data' => DigitalAvatarTaskService::deleteMemberAsset(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    intval(input('post.id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    public function deleteMemberTask()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已删除',
                'data' => DigitalAvatarTaskService::deleteMemberTask(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($this->portalPayload()['id'] ?? input('post.id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '删除失败：' . $e->getMessage()]);
        }
    }

    public function clearMemberTasks()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已清空',
                'data' => DigitalAvatarTaskService::clearMemberTasks(
                    $this->resolveAid(),
                    intval($this->mid)
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '清空失败：' . $e->getMessage()]);
        }
    }

    public function getCreditSummary()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        return json([
            'status' => 1,
            'msg' => '获取成功',
            'data' => DigitalAvatarCreditService::getSummary($this->resolveAid(), $this->resolveBid(), intval($this->mid)),
        ]);
    }

    public function getCreditLogs()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        return json([
            'status' => 1,
            'msg' => '获取成功',
            'data' => DigitalAvatarCreditService::listLogs(
                $this->resolveAid(),
                $this->resolveBid(),
                intval($this->mid),
                max(1, intval(input('post.page/d', 1))),
                max(1, min(100, intval(input('post.limit/d', 20)))),
                $this->creditLogFilters()
            ),
        ]);
    }

    public function exportCreditLogs()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $list = DigitalAvatarCreditService::exportMemberLogs(
                $this->resolveAid(),
                $this->resolveBid(),
                intval($this->mid),
                $this->creditLogFilters()
            );
            $filename = '额度流水_' . date('YmdHis') . '.xls';
            $asciiName = 'digitalavatar-credits-' . date('YmdHis') . '.xls';
            return response("\xEF\xBB\xBF" . DigitalAvatarCreditService::buildCreditLogExcelBody($list), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
                'Cache-Control' => 'no-store',
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '导出流水失败：' . $e->getMessage()]);
        }
    }

    public function getPackages()
    {
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => ['list' => DigitalAvatarService::listPackages($this->resolveAid(), true)],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取套餐失败：' . $e->getMessage()]);
        }
    }

    public function createPackageOrder()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $aid = $this->resolveAid();
            $payload = $this->portalPayload();
            $packageId = intval($payload['package_id'] ?? input('post.package_id/d', 0));
            $result = DigitalAvatarService::createMemberPackageOrder(
                $aid,
                $this->resolveBid(),
                intval($this->mid),
                $packageId
            );
            $result['pay_options'] = DigitalAvatarPayService::getPayOptions($aid, intval($this->mid));
            $result['pay_mode'] = 'inplace';
            if (intval($result['need_pay'] ?? 0) === 1 && intval($result['payorderid'] ?? 0) > 0) {
                $tourl = rtrim(request()->domain(), '/') . '/portal/digitalavatar/#/profile?aid=' . $aid;
                $result['pay_url'] = m_url(
                    'shopPackage/pay/pay?id=' . intval($result['payorderid']) . '&tourl=' . urlencode($tourl),
                    $aid
                );
            } else {
                $result['pay_url'] = '';
            }
            return json([
                'status' => 1,
                'msg' => intval($result['need_pay'] ?? 0) === 1 ? '下单成功，请完成支付' : '购买成功',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '下单失败：' . $e->getMessage()]);
        }
    }

    public function getPackagePayOptions()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarPayService::getPayOptions($this->resolveAid(), intval($this->mid)),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取支付方式失败：' . $e->getMessage()]);
        }
    }

    public function payPackageOrder()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            $returnUrl = trim(strval($payload['return_url'] ?? ''));
            if ($returnUrl === '') {
                $aid = $this->resolveAid();
                $bid = $this->resolveBid();
                $returnUrl = rtrim(request()->domain(), '/') . '/portal/digitalavatar/?aid=' . $aid
                    . ($bid > 0 ? '&bid=' . $bid : '')
                    . '&paydone=1&order_id=' . intval($payload['order_id'] ?? 0);
            }
            $data = DigitalAvatarPayService::pay(
                $this->resolveAid(),
                $this->resolveBid(),
                intval($this->mid),
                intval($payload['order_id'] ?? $payload['package_order_id'] ?? 0),
                strval($payload['pay_type'] ?? $payload['paytype'] ?? ''),
                $returnUrl
            );
            $paid = intval($data['paid'] ?? 0) === 1;
            return json([
                'status' => 1,
                'msg' => strval($data['msg'] ?? ($paid ? '支付成功' : '下单成功')),
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function getPackageOrderStatus()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarPayService::getOrderStatus(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    intval($payload['order_id'] ?? $payload['package_order_id'] ?? input('post.order_id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function redeemCode()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '兑换成功',
                'data' => DigitalAvatarCommerceService::redeemMemberCode(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    strval(input('post.code/s', ''))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '兑换失败：' . $e->getMessage()]);
        }
    }

    public function saveMemberAsset()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已保存到资产库',
                'data' => DigitalAvatarTaskService::saveUploadedAsset(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    is_array($payload) ? $payload : []
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '保存资产失败：' . $e->getMessage()]);
        }
    }

    public function retryTask()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已重新提交任务',
                'data' => DigitalAvatarTaskService::retryMemberTask(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    intval(input('post.id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '重试失败：' . $e->getMessage()]);
        }
    }

    public function regenerateTask()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '已再次提交生成',
                'data' => DigitalAvatarTaskService::regenerateMemberTask(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    intval(input('post.id/d', 0)),
                    intval(input('post.conversation_id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '再次生成失败：' . $e->getMessage()]);
        }
    }

    public function starTask()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已更新收藏',
                'data' => DigitalAvatarTaskService::toggleStarMemberTask(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($payload['id'] ?? input('post.id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function listInspireCategories()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => ['list' => DigitalAvatarInspireService::listCategories($this->resolveAid(), true)],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取分类失败：' . $e->getMessage()]);
        }
    }

    public function listInspires()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarInspireService::portalFeed(
                    $this->resolveAid(),
                    intval($this->mid),
                    $payload
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取灵感失败：' . $e->getMessage()]);
        }
    }

    public function shareInspire()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已提交审核',
                'data' => DigitalAvatarInspireService::shareFromTask(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    $payload
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function toggleInspireLike()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已更新',
                'data' => DigitalAvatarInspireService::toggleLike(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($payload['id'] ?? 0)
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function listShareableInspireTasks()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => DigitalAvatarInspireService::recentShareableTasks(
                        $this->resolveAid(),
                        intval($this->mid)
                    ),
                    'categories' => DigitalAvatarInspireService::listCategories($this->resolveAid(), true),
                    'user_categories' => DigitalAvatarInspireService::listUserCategories(
                        $this->resolveAid(),
                        intval($this->mid)
                    ),
                ],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function saveInspireUserCategory()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已保存分类',
                'data' => DigitalAvatarInspireService::saveUserCategory(
                    $this->resolveAid(),
                    intval($this->mid),
                    $payload
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function deleteInspireUserCategory()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已删除分类',
                'data' => DigitalAvatarInspireService::deleteUserCategory(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($payload['id'] ?? input('post.id/d', 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function getInspireDetail()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarInspireService::getDetail(
                    $this->resolveAid(),
                    intval($payload['id'] ?? 0),
                    intval($this->mid),
                    false
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function listInspireComments()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarInspireService::listComments(
                    $this->resolveAid(),
                    intval($payload['id'] ?? 0),
                    intval($payload['page'] ?? 1),
                    intval($payload['limit'] ?? 10)
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function addInspireComment()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '评论成功',
                'data' => DigitalAvatarInspireService::addComment(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($payload['id'] ?? 0),
                    strval($payload['content'] ?? ''),
                    intval($payload['parent_id'] ?? 0)
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function toggleInspireFav()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已更新',
                'data' => DigitalAvatarInspireService::toggleFavorite(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($payload['id'] ?? 0)
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function toggleInspireFollow()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已更新',
                'data' => DigitalAvatarInspireService::toggleFollow(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($payload['member_id'] ?? ($payload['id'] ?? 0))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function recordInspireRemix()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '已记录',
                'data' => DigitalAvatarInspireService::recordRemix(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($payload['id'] ?? 0)
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function getInspireMemberHome()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarInspireService::memberHome(
                    $this->resolveAid(),
                    intval($this->mid),
                    intval($payload['member_id'] ?? ($payload['id'] ?? 0)),
                    $payload
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function updateMemberProfile()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $aid = $this->resolveAid();
            $nickname = trim(strval(input('post.nickname/s', '')));
            $headimg = trim(strval(input('post.headimg/s', '')));
            if ($nickname === '') {
                return json(['status' => 0, 'msg' => '请填写昵称']);
            }
            if (mb_strlen($nickname, 'UTF-8') > 20) {
                return json(['status' => 0, 'msg' => '昵称最多 20 个字']);
            }
            $data = ['nickname' => $nickname];
            if ($headimg !== '') {
                $data['headimg'] = mb_substr($headimg, 0, 500);
            }
            Db::name('member')->where('aid', $aid)->where('id', intval($this->mid))->update($data);
            $member = Db::name('member')->where('aid', $aid)->where('id', intval($this->mid))->find();
            return json([
                'status' => 1,
                'msg' => '资料已保存',
                'data' => ['member' => $this->formatMember($member ?: [])],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '保存资料失败：' . $e->getMessage()]);
        }
    }

    public function updateMemberPassword()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $aid = $this->resolveAid();
            $oldPassword = strval(input('post.old_password/s', ''));
            $newPassword = strval(input('post.new_password/s', ''));
            $confirmPassword = strval(input('post.confirm_password/s', ''));
            if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
                return json(['status' => 0, 'msg' => '请完整填写密码信息']);
            }
            if ($newPassword !== $confirmPassword) {
                return json(['status' => 0, 'msg' => '两次输入的新密码不一致']);
            }
            if (mb_strlen($newPassword) < 6 || mb_strlen($newPassword) > 20) {
                return json(['status' => 0, 'msg' => '新密码请设置为 6-20 位']);
            }
            $member = Db::name('member')->where('aid', $aid)->where('id', intval($this->mid))->find();
            if (!$member) {
                return json(['status' => 0, 'msg' => '会员信息不存在']);
            }
            if (trim(strval($member['pwd'] ?? '')) !== md5($oldPassword)) {
                return json(['status' => 0, 'msg' => '原密码不正确']);
            }
            Db::name('member')->where('aid', $aid)->where('id', intval($this->mid))->update(['pwd' => md5($newPassword)]);
            return json(['status' => 1, 'msg' => '密码已更新', 'data' => null]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '修改密码失败：' . $e->getMessage()]);
        }
    }

    public function getPackageOrders()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarCommerceService::getMemberPackageOrders(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    max(1, intval(input('post.page/d', 1))),
                    max(1, min(50, intval(input('post.limit/d', 20))))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取订单失败：' . $e->getMessage()]);
        }
    }

    public function getInvoices()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarCommerceService::getMemberInvoices(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    max(1, intval(input('post.page/d', 1))),
                    max(1, min(50, intval(input('post.limit/d', 20))))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取发票失败：' . $e->getMessage()]);
        }
    }

    public function applyInvoice()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json([
                'status' => 1,
                'msg' => '开票申请已提交',
                'data' => DigitalAvatarCommerceService::applyInvoice(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    is_array($payload) ? $payload : []
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function getCheckinInfo()
    {
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarCheckinService::memberInfo($this->resolveAid(), $this->resolveBid(), intval($this->mid)),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取签到信息失败：' . $e->getMessage()]);
        }
    }

    public function doCheckin()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '签到成功',
                'data' => DigitalAvatarCheckinService::checkin($this->resolveAid(), $this->resolveBid(), intval($this->mid)),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => $e->getMessage()]);
        }
    }

    public function getCheckinLogs()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarCheckinService::memberLogs(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    max(1, intval(input('post.page/d', 1))),
                    max(1, min(50, intval(input('post.limit/d', 20))))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取签到记录失败：' . $e->getMessage()]);
        }
    }

    public function getInviteInfo()
    {
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarInviteService::memberInfo($this->resolveAid(), $this->resolveBid(), intval($this->mid)),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取拉新信息失败：' . $e->getMessage()]);
        }
    }

    public function getInviteLogs()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => DigitalAvatarInviteService::memberLogs(
                    $this->resolveAid(),
                    $this->resolveBid(),
                    intval($this->mid),
                    max(1, intval(input('post.page/d', 1))),
                    max(1, min(50, intval(input('post.limit/d', 20))))
                ),
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取邀请记录失败：' . $e->getMessage()]);
        }
    }

    public function getWechatBind()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json(DigitalAvatarWxLoginService::bindStatus($this->resolveAid(), intval($this->mid)));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取微信号失败：' . $e->getMessage()]);
        }
    }

    public function createWechatBindQr()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            return json(DigitalAvatarWxLoginService::createBindTicket($this->resolveAid(), intval($this->mid)));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '生成绑定二维码失败：' . $e->getMessage()]);
        }
    }

    public function pollWechatBind()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $payload = $this->portalPayload();
            return json(DigitalAvatarWxLoginService::pollBind(
                $this->resolveAid(),
                intval($this->mid),
                strval($payload['token'] ?? '')
            ));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '查询绑定状态失败：' . $e->getMessage()]);
        }
    }

    public function sendWechatBindSms()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $aid = $this->resolveAid();
            $tel = $this->memberBindTel($aid);
            if ($tel === '' || !$this->memberSmsReady($aid)) {
                return json(['status' => 0, 'msg' => '如无法收到验证码或未绑定手机号码请联系管理员']);
            }
            $code = rand(100000, 999999);
            cache($this->sessionid . '_smscode', md5($tel . '-' . $code), 600);
            cache($this->sessionid . '_smscodetimes', 0);
            return json(\app\admin\common\Sms::send($aid, $tel, 'tmpl_smscode', ['code' => $code]));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '发送失败：' . $e->getMessage()]);
        }
    }

    public function unbindWechat()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $aid = $this->resolveAid();
            $checked = $this->verifyMemberBindSms($aid);
            if (intval($checked['status'] ?? 0) !== 1) {
                return json($checked);
            }
            return json(DigitalAvatarWxLoginService::clearBind($aid, intval($this->mid)));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '解绑失败：' . $e->getMessage()]);
        }
    }

    public function rebindWechat()
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }
        try {
            $aid = $this->resolveAid();
            $checked = $this->verifyMemberBindSms($aid);
            if (intval($checked['status'] ?? 0) !== 1) {
                return json($checked);
            }
            $cleared = DigitalAvatarWxLoginService::clearBind($aid, intval($this->mid));
            if (intval($cleared['status'] ?? 0) !== 1) {
                return json($cleared);
            }
            return json(DigitalAvatarWxLoginService::createBindTicket($aid, intval($this->mid)));
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '修改微信号失败：' . $e->getMessage()]);
        }
    }

    private function memberBindTel(int $aid): string
    {
        $tel = trim(strval(Db::name('member')->where('aid', $aid)->where('id', intval($this->mid))->value('tel')));
        return preg_match('/^1[3456789]\d{9}$/', $tel) ? $tel : '';
    }

    private function memberSmsReady(int $aid): bool
    {
        $smsset = Db::name('admin_set_sms')->where('aid', $aid)->find() ?: [];
        return intval($smsset['status'] ?? 0) === 1 && !empty($smsset['tmpl_smscode']) && intval($smsset['tmpl_smscode_st'] ?? 0) === 1;
    }

    private function verifyMemberBindSms(int $aid): array
    {
        $tel = $this->memberBindTel($aid);
        if ($tel === '' || !$this->memberSmsReady($aid)) {
            return ['status' => 0, 'msg' => '如无法收到验证码或未绑定手机号码请联系管理员'];
        }
        $payload = $this->portalPayload();
        return $this->verifySmsCode($tel, trim(strval($payload['smscode'] ?? '')));
    }

    private function portalPayload(): array
    {
        $payload = input('post.');
        if (!is_array($payload)) {
            $payload = [];
        }
        $raw = trim(strval(request()->getContent()));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }
        foreach (['text', 'content', 'prompt', 'script'] as $key) {
            $queryValue = trim(strval(input('param.' . $key . '/s', '')));
            if ($queryValue !== '' && trim(strval($payload[$key] ?? '')) === '') {
                $payload[$key] = $queryValue;
            }
        }
        return $payload;
    }

    private function createCloneTask(string $taskType, string $defaultTitle)
    {
        if (!$this->isMemberLoggedIn()) {
            return $this->loginRequiredResponse();
        }

        try {
            $payload = $this->portalPayload();
            $payload = is_array($payload) ? $payload : [];
            $payload['task_type'] = $taskType;
            if (trim(strval($payload['sample_url'] ?? '')) === '') {
                throw new \RuntimeException($taskType === 'avatar_clone' && DigitalAvatarService::isLocalImageAvatarClone(strval($payload['model_code'] ?? ''), $payload)
                    ? '请先上传形象图片'
                    : '请先上传样本文件');
            }
            if ($taskType === 'avatar_clone' && DigitalAvatarService::isLocalImageAvatarClone(strval($payload['model_code'] ?? ''), $payload)) {
                DigitalAvatarService::assertLocalImageAvatarCloneSample($payload);
            }
            if (trim(strval($payload['name'] ?? '')) === '') {
                throw new \RuntimeException('请填写资源名称');
            }
            $payload['title'] = trim(strval($payload['title'] ?? $payload['name'] ?? '')) ?: $defaultTitle;

            $task = DigitalAvatarTaskService::createTask(
                $this->resolveAid(),
                $this->resolveBid(),
                intval($this->mid),
                $payload
            );
            $completed = intval($task['status'] ?? 0) === 2;

            return json([
                'status' => 1,
                'msg' => $completed && $taskType === 'avatar_clone' ? 'XPro 图片形象已保存' : '克隆任务已进入队列',
                'data' => $task,
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '创建克隆任务失败：' . $e->getMessage()]);
        }
    }

    private function getPortalAllowedUploadExtensions(string $category): array
    {
        $videoTypes = ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', 'ogg', 'ogv', 'wmv', 'flv', '3gp', 'mpeg', 'mpg'];
        $audioTypes = ['mp3', 'wav', 'm4a', 'aac', 'flac', 'oga', 'ogg', 'opus', 'amr', 'wma', 'webm', 'weba'];
        $imageTypes = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
        if ($category === 'video') {
            return $videoTypes;
        }
        if ($category === 'audio') {
            return $audioTypes;
        }
        if (in_array($category, ['image', 'cover'], true)) {
            return $imageTypes;
        }
        return array_values(array_unique(array_merge($videoTypes, $audioTypes, $imageTypes)));
    }

    private function checkPortalStorageLimit(int $aid, int $bid, int $fileSize): array
    {
        $usedStorage = 0 + Db::name('admin_upload')
            ->where('aid', $aid)
            ->where('merchant_id', $bid > 0 ? $bid : 0)
            ->where('isdel', 0)
            ->sum('size');

        $storageLimit = $bid > 0
            ? Db::name('business')->where('aid', $aid)->where('id', $bid)->value('storage_limit')
            : Db::name('admin')->where('id', $aid)->value('storage_limit');
        if (!$storageLimit || intval($storageLimit) <= 0) {
            return ['status' => 1];
        }

        $storageLimitBytes = intval($storageLimit) * 1024 * 1024;
        if (($usedStorage + $fileSize) > $storageLimitBytes) {
            return [
                'status' => 0,
                'msg' => '存储空间不足，请清理文件或联系管理员扩容后再试',
            ];
        }
        return ['status' => 1];
    }

    private function resolveAid(): int
    {
        $aid = intval($this->aid ?? 0);
        if ($aid <= 0 && defined('aid')) {
            $aid = intval(aid);
        }
        if ($aid <= 0) {
            $aid = intval(input('param.aid/d', 0));
        }
        return $aid;
    }

    private function resolveBid(): int
    {
        if (defined('bid')) {
            return max(0, intval(bid));
        }
        return max(0, intval(input('param.bid/d', 0)));
    }

    private function isMemberLoggedIn(): bool
    {
        return intval($this->mid ?? 0) > 0;
    }

    private function taskListFilters(array $payload = []): array
    {
        if ($payload === []) {
            $payload = $this->portalPayload();
        }
        $startDate = trim(strval($payload['start_date'] ?? ''));
        $endDate = trim(strval($payload['end_date'] ?? ''));
        $startTime = intval($payload['start_time'] ?? 0);
        $endTime = intval($payload['end_time'] ?? 0);
        if ($startTime <= 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startTime = intval(strtotime($startDate . ' 00:00:00'));
        }
        if ($endTime <= 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endTime = intval(strtotime($endDate . ' 23:59:59'));
        }
        return [
            'task_id' => trim(strval($payload['task_id'] ?? '')),
            'model_task_type' => trim(strval($payload['model_task_type'] ?? '')),
            'start_time' => max(0, $startTime),
            'end_time' => max(0, $endTime),
        ];
    }

    private function creditLogFilters(): array
    {
        $payload = $this->portalPayload();
        $startDate = trim(strval($payload['start_date'] ?? ''));
        $endDate = trim(strval($payload['end_date'] ?? ''));
        $startTime = intval($payload['start_time'] ?? 0);
        $endTime = intval($payload['end_time'] ?? 0);
        if ($startTime <= 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startTime = intval(strtotime($startDate . ' 00:00:00'));
        }
        if ($endTime <= 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endTime = intval(strtotime($endDate . ' 23:59:59'));
        }
        $creditChange = $payload['credit_change'] ?? '';
        $changeAfter = $payload['change_after'] ?? '';
        return [
            'task_type' => trim(strval($payload['task_type'] ?? '')),
            'task_id' => trim(strval($payload['task_id'] ?? '')),
            'start_time' => max(0, $startTime),
            'end_time' => max(0, $endTime),
            'credit_change' => is_numeric($creditChange) ? intval($creditChange) : '',
            'change_after' => is_numeric($changeAfter) ? intval($changeAfter) : '',
        ];
    }

    private function loginRequiredResponse()
    {
        return json(['status' => 0, 'msg' => '请先登录会员账号']);
    }

    private function buildLoginConfig(int $aid): array
    {
        $sysset = Db::name('admin_set')->where('aid', $aid)->find();
        if (!$sysset) {
            throw new \RuntimeException('商户配置不存在');
        }
        $xieyi = Db::name('admin_set_xieyi')->where('aid', $aid)->find() ?: [];
        $smsset = Db::name('admin_set_sms')->where('aid', $aid)->find() ?: [];
        $loginTypes = array_filter(explode(',', strval($sysset['logintype_h5'] ?? '')));
        $settings = DigitalAvatarCreditService::ensureSettings($aid);
        $oem = DigitalAvatarCommerceService::formatOem($settings);
        $loginStyle = strval($oem['login_style'] ?? 'niwa');
        $inviteEnabled = 0;
        try {
            $inviteSet = DigitalAvatarInviteService::ensureSet($aid);
            $inviteEnabled = intval($inviteSet['enabled'] ?? 0) === 1 ? 1 : 0;
        } catch (\Throwable $e) {
        }
        return [
            'aid' => $aid,
            'name' => strval($sysset['name'] ?? ''),
            'logo' => strval($sysset['logo'] ?? ''),
            'notice_text' => strval($settings['notice_text'] ?? ''),
            'oem' => $oem,
            'login_style' => $loginStyle,
            'require_captcha' => $loginStyle === 'niwa' ? 1 : 0,
            'default_login_bg' => '/portal/digitalavatar/svg/login_bg.jpg',
            'xystatus' => intval($xieyi['status'] ?? 0),
            'xyname' => strval($xieyi['name'] ?? ''),
            'xycontent' => strval($xieyi['content'] ?? ''),
            'password_login_enabled' => in_array('1', $loginTypes, true) || $loginTypes === [],
            'needsms' => intval($smsset['status'] ?? 0) === 1 && !empty($smsset['tmpl_smscode']) && intval($smsset['tmpl_smscode_st'] ?? 0) === 1,
            'register_enabled' => true,
            'reg_invite_code' => intval($sysset['reg_invite_code'] ?? 0),
            'reg_invite_code_type' => intval($sysset['reg_invite_code_type'] ?? 0),
            'invite_enabled' => $inviteEnabled,
            'reg_check' => intval($sysset['reg_check'] ?? 0),
            'session_id' => strval($this->sessionid),
            'member' => $this->isMemberLoggedIn() ? $this->formatMember($this->member) : null,
        ];
    }

    private function loginByPassword(int $aid, string $tel, string $pwd, int $rememberDays = 0): array
    {
        $member = Db::name('member')->where('aid', $aid)->where('tel', $tel)->where('pwd', md5($pwd))->find();
        if (!$member) {
            return ['status' => 0, 'msg' => '手机号或密码输入错误'];
        }
        if (intval($member['checkst'] ?? 1) === 0) {
            return ['status' => 0, 'msg' => '账号审核中'];
        }
        if (intval($member['checkst'] ?? 1) === 2) {
            return ['status' => 0, 'msg' => '账号审核未通过'];
        }
        if (intval($member['is_blacklist'] ?? 0) === 1) {
            return ['status' => 0, 'msg' => '该账号已被加入黑名单，无法登录使用'];
        }
        return $this->finishMemberLogin($aid, $member, $rememberDays);
    }

    private function finishMemberLogin(int $aid, array $member, int $rememberDays = 0): array
    {
        $mid = intval($member['id'] ?? 0);
        if ($mid <= 0) {
            return ['status' => 0, 'msg' => '会员信息无效'];
        }
        $cacheTtl = 7 * 86400;
        $nologinDay = intval(Db::name('admin_set')->where('aid', $aid)->value('nologin_day'));
        if ($nologinDay > 0) {
            $cacheTtl = $nologinDay * 86400;
        }
        if ($rememberDays > 0) {
            $cacheTtl = max($cacheTtl, $rememberDays * 86400);
        }
        cache($this->sessionid . '_mid', $mid, $cacheTtl);

        $sessionData = [
            'mid' => $mid,
            'login_time' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'login_ip' => request()->ip(),
            'platform' => 'h5',
        ];
        $exists = Db::name('session')->where('aid', $aid)->where('session_id', $this->sessionid)->find();
        if ($exists) {
            Db::name('session')
                ->where('aid', $aid)
                ->where('session_id', $this->sessionid)
                ->update($sessionData);
        } else {
            Db::name('session')->insert(array_merge($sessionData, [
                'session_id' => $this->sessionid,
                'aid' => $aid,
            ]));
        }
        Db::name('member')->where('aid', $aid)->where('id', $mid)->update(['last_visittime' => time()]);
        $this->mid = $mid;
        $this->member = $member;
        return [
            'status' => 1,
            'msg' => '登录成功',
            'data' => [
                'mid' => $mid,
                'session_id' => strval($this->sessionid),
                'member' => $this->formatMember($member),
            ],
        ];
    }

    private function verifySmsCode(string $tel, string $smscode): array
    {
        if ($smscode === '' || md5($tel . '-' . $smscode) !== cache($this->sessionid . '_smscode') || intval(cache($this->sessionid . '_smscodetimes')) > 5) {
            cache($this->sessionid . '_smscodetimes', intval(cache($this->sessionid . '_smscodetimes')) + 1);
            return ['status' => 0, 'msg' => '短信验证码错误'];
        }
        cache($this->sessionid . '_smscode', null);
        cache($this->sessionid . '_smscodetimes', null);
        return ['status' => 1, 'msg' => '验证成功'];
    }

    private function rememberLoginDays(): int
    {
        $remember = intval(input('post.remember_me/d', 0));
        if ($remember !== 1) {
            $raw = $this->portalPayload();
            $remember = intval($raw['remember_me'] ?? ($raw['rememberMe'] ?? 0));
        }
        return $remember === 1 ? 30 : 0;
    }

    private function makeLoginCaptcha(): array
    {
        $code = str_pad(strval(random_int(0, 9999)), 4, '0', STR_PAD_LEFT);
        cache($this->sessionid . '_login_captcha', $code, 600);
        return [
            'code_num' => $code,
            'session_id' => strval($this->sessionid),
            'expire' => 600,
        ];
    }

    private function verifyLoginCaptcha(string $captcha): array
    {
        $captcha = trim($captcha);
        $cached = strval(cache($this->sessionid . '_login_captcha'));
        if ($captcha === '' || !preg_match('/^\d{4}$/', $captcha)) {
            return ['status' => 0, 'msg' => '请输入4位数字验证码'];
        }
        if ($cached === '' || $captcha !== $cached) {
            cache($this->sessionid . '_login_captcha', null);
            return ['status' => 0, 'msg' => '验证码输入错误，请重新输入'];
        }
        cache($this->sessionid . '_login_captcha', null);
        return ['status' => 1, 'msg' => '验证成功'];
    }

    private function resolveInviteMember(int $aid, string $inviteCode, int $inviteType): ?array
    {
        $query = Db::name('member')->where('aid', $aid);
        if ($inviteType === 1) {
            return $query->where('yqcode', $inviteCode)->find();
        }
        if ($inviteType === 2) {
            return $query->where('id', intval($inviteCode))->find();
        }
        return $query->where('tel', $inviteCode)->find();
    }

    private function formatMember(array $member): array
    {
        return [
            'id' => intval($member['id'] ?? 0),
            'nickname' => strval($member['nickname'] ?? ''),
            'tel' => strval($member['tel'] ?? ''),
            'headimg' => strval($member['headimg'] ?? ''),
        ];
    }

    private function mediaExtensionFromPath(string $value): string
    {
        $path = strval(parse_url($value, PHP_URL_PATH) ?: $value);
        $path = explode('?', $path)[0];
        if (preg_match('/\.(mp4|mov|m4v|webm|avi|mkv|mp3|wav|m4a|aac|ogg|flac|jpg|jpeg|png|webp|gif|bmp)$/i', $path, $matches)) {
            return strtolower(strval($matches[1]));
        }
        return '';
    }

    private function mimeFromMediaExtension(string $ext): string
    {
        $map = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'm4v' => 'video/x-m4v',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
        ];
        return strval($map[strtolower($ext)] ?? 'application/octet-stream');
    }

    private function mediaExtensionFromMime(string $mime): string
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));
        $map = [
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'video/x-m4v' => 'm4v',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/aac' => 'aac',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (isset($map[$mime])) {
            return $map[$mime];
        }
        if (strpos($mime, 'video/') === 0) {
            return 'mp4';
        }
        if (strpos($mime, 'audio/') === 0) {
            return 'mp3';
        }
        if (strpos($mime, 'image/') === 0) {
            return 'jpg';
        }
        return '';
    }

    private function downloadBaseName(string $filename): string
    {
        $safeName = preg_replace('/[^\w.\-\x{4e00}-\x{9fa5}]+/u', '_', $filename);
        if ($safeName === null || $safeName === '') {
            $safeName = 'digitalavatar';
        }
        $safeName = preg_replace('/\.(mp4|mov|m4v|webm|avi|mkv|mp3|wav|m4a|aac|ogg|flac|jpg|jpeg|png|webp|gif|bmp|php|html|htm)$/i', '', $safeName);
        $safeName = trim(strval($safeName), '._-');
        return $safeName !== '' ? $safeName : 'digitalavatar';
    }

    private function defaultMediaExtension(string $hint): string
    {
        $hint = strtolower(trim($hint));
        if (in_array($hint, ['audio', 'voice_clone', 'voice'], true) || strpos($hint, 'audio') !== false || strpos($hint, 'voice') !== false) {
            return 'mp3';
        }
        if ($hint === 'image' || strpos($hint, 'image') !== false) {
            return 'jpg';
        }
        return 'mp4';
    }

    private function streamRemoteMedia(string $url, string $filename, bool $asDownload = false, string $hint = ''): void
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException('文件地址无效');
        }
        $safeName = $this->downloadBaseName($filename);
        $ext = $this->mediaExtensionFromPath($url);
        $mime = $ext !== '' ? $this->mimeFromMediaExtension($ext) : 'application/octet-stream';
        $disposition = $asDownload ? 'attachment' : 'inline';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ignore_user_abort(true);
        set_time_limit(180);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('无法读取远程文件');
        }
        $range = strval($_SERVER['HTTP_RANGE'] ?? '');
        if ($range !== '' && !$asDownload) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Range: ' . $range]);
        }
        $headersSent = false;
        $statusCode = 0;
        $sendHeaders = function () use (&$headersSent, &$ext, &$mime, $safeName, $disposition, $hint) {
            if ($headersSent) {
                return;
            }
            $headersSent = true;
            if ($ext === '') {
                $ext = $this->mediaExtensionFromMime($mime);
            }
            if ($ext === '') {
                $ext = $this->defaultMediaExtension($hint);
                $mime = $this->mimeFromMediaExtension($ext);
            } elseif ($mime === 'application/octet-stream') {
                $mime = $this->mimeFromMediaExtension($ext);
            }
            $downloadName = $safeName . '.' . $ext;
            header('Content-Type: ' . $mime);
            header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
            header('Cache-Control: private, max-age=120');
            header('X-Content-Type-Options: nosniff');
        };
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$statusCode, &$mime, &$ext, $sendHeaders) {
            $len = strlen($headerLine);
            $header = trim($headerLine);
            if ($header === '') {
                if ($statusCode === 200 || $statusCode === 206) {
                    $sendHeaders();
                }
                return $len;
            }
            if (stripos($header, 'HTTP/') === 0) {
                if (preg_match('/\s(\d{3})\b/', $header, $matches)) {
                    $statusCode = intval($matches[1]);
                    if ($statusCode === 200 || $statusCode === 206) {
                        http_response_code($statusCode);
                    }
                }
                return $len;
            }
            if ($statusCode !== 200 && $statusCode !== 206) {
                return $len;
            }
            $lower = strtolower($header);
            if (strpos($lower, 'content-type:') === 0) {
                $remoteMime = trim(substr($header, strlen('Content-Type:')));
                $fromMime = $this->mediaExtensionFromMime($remoteMime);
                if ($fromMime !== '') {
                    $mime = strtolower(trim(explode(';', $remoteMime)[0]));
                    if ($ext === '') {
                        $ext = $fromMime;
                    }
                }
                return $len;
            }
            if (strpos($lower, 'content-length:') === 0
                || strpos($lower, 'content-range:') === 0
                || strpos($lower, 'accept-ranges:') === 0
            ) {
                $sendHeaders();
                header($header);
            }
            return $len;
        });
        curl_exec($ch);
        if (!$headersSent) {
            $sendHeaders();
        }
        curl_close($ch);
        exit;
    }
}
