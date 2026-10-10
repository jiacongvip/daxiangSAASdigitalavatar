<?php
/**
 * Vue前端 - 登录接口
 * 参考：app/admin/controller/Login.php
 * 规范：统一返回 json(['status'=>1, 'msg'=>'', 'data'=>[]])
 */
namespace app\adminapi\controller;
use app\BaseController;
use think\facade\Db;

class Login extends BaseController
{
    private function buildAdminHashUrl(string $path = 'login'): string
    {
        $baseFile = str_replace('\\', '/', (string) request()->baseFile());
        $dir = str_replace('\\', '/', dirname($baseFile));
        $publicBasePath = ($dir === '.' || $dir === '/' || $dir === '\\') ? '' : '/' . trim($dir, '/');
        return $publicBasePath . '/admin/#/' . ltrim($path, '/');
    }

    /**
     * 登录页品牌信息
     * 复用旧后台登录页使用的 webinfo 配置，供未登录状态展示品牌名称、Logo、背景图等。
     * @return \think\response\Json
     */
    public function getBranding()
    {
        try {
            $webinfoRaw = Db::name('sysset')->where('name', 'webinfo')->value('value');
            $webinfo = json_decode((string)$webinfoRaw, true);
            if (!is_array($webinfo)) {
                $webinfo = [];
            }

            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => [
                    'webname' => trim((string)($webinfo['webname'] ?? '')),
                    'logo' => trim((string)($webinfo['logo'] ?? '')),
                    'ico' => trim((string)($webinfo['ico'] ?? '')),
                    'adminloginbg' => trim((string)($webinfo['adminloginbg'] ?? '')),
                    'company' => trim((string)($webinfo['company'] ?? '')),
                    'copyright' => trim((string)($webinfo['copyright'] ?? '')),
                ],
            ]);
        } catch (\Throwable $e) {
            return json(['status' => 0, 'msg' => '获取品牌信息失败']);
        }
    }

    /**
     * 用户登录
     * @return \think\response\Json
     */
    public function index()
    {
        $username = trim(input('post.username'));
        $password = trim(input('post.password'));
        $captcha = trim(input('post.captcha', ''));

        // 参数验证
        if($username == '' || $password == ''){
            return json(['status'=>0, 'msg'=>'用户名和密码不能为空']);
        }

        try {
            // 查询用户
            $rs = Db::name('admin_user')
                ->where('un', $username)
                ->where('pwd', md5($password))
                ->find();

            if(!$rs){
                return json(['status'=>2, 'msg'=>'用户名或密码错误']);
            }

            if($rs['status'] != 1){
                return json(['status'=>0, 'msg'=>'该账号已禁用']);
            }

            // 检查商家/团长状态
            if ($rs['bid'] > 0) {
                // 查询 business 表
                $binfo = Db::name('business')->where('id', $rs['bid'])->find();

                if ($binfo && $binfo['status'] != 1) {
                    return json(['status'=>0, 'msg'=>'该商家尚未审核通过']);
                }

                // 如果 business 表查询为空，再查询 tuanzhang 表
                if (!$binfo) {
                    $tinfo = Db::name('tuanzhang')->where('id', $rs['bid'])->find();

                    if ($tinfo && $tinfo['status'] != 1) {
                        return json(['status'=>0, 'msg'=>'该团长尚未审核通过']);
                    } elseif (!$tinfo) {
                        return json(['status'=>0, 'msg'=>'未找到相关的商家或团长信息']);
                    }
                }
            }

            // 更新登录信息
            Db::name('admin_user')
                ->where('un', $username)
                ->where('pwd', md5($password))
                ->update(['ip'=>request()->ip(), 'logintime'=>time()]);

            $this->clearOriginConsoleSession();
            $this->writeLoginSession($rs);

            // 记录登录日志
            Db::name('admin_loginlog')->insert([
                'aid' => $rs['aid'],
                'uid' => $rs['id'],
                'logintime' => time(),
                'loginip' => request()->ip(),
                'logintype' => '账号登录'
            ]);

            // 记住密码
            if(input('post.remember')){
                cookie('remember', 1, 30*86400);
                cookie('username', $username, 30*86400);
                cookie('password', md5(md5($password)), 30*86400);
            } else {
                cookie('remember', null);
                cookie('username', null);
                cookie('password', null);
            }

            // 返回成功
            return json([
                'status' => 1,
                'msg' => '登录成功',
                'data' => $this->buildLoginData($rs)
            ]);

        } catch (\Exception $e) {
            return json(['status'=>0, 'msg'=>'登录失败：'.$e->getMessage()]);
        }
    }

    /**
     * 使用控制台登录令牌登录到后台
     * 路径示例：/admin.php/adminapi/Login/loginByToken?token=xxxx
     *
     * 控制台「登录后台」按钮会先在 ConsoleUser::loginAs 中生成临时令牌，
     * 然后跳转到本方法完成一键登录，最后再跳转到 Vben 新后台入口。
     */
    public function loginByToken()
    {
        $token = trim(input('get.token', ''));
        if ($token === '') {
            // 没有 token
            if (request()->isAjax() || request()->isJson()) {
                return json(['status' => 0, 'msg' => '缺少登录令牌']);
            }
            return redirect($this->buildAdminHashUrl('login'));
        }

        $sessionKey = 'TEMP_LOGIN_TOKEN_' . $token;
        $info = session($sessionKey);

        // 令牌不存在或格式错误
        if (!$info || !is_array($info)) {
            if (request()->isAjax() || request()->isJson()) {
                return json(['status' => 0, 'msg' => '登录令牌无效或已使用']);
            }
            return redirect($this->buildAdminHashUrl('login'));
        }

        // 校验过期时间
        $expire = isset($info['expire']) ? intval($info['expire']) : 0;
        if ($expire > 0 && time() > $expire) {
            // 过期后清除令牌
            session($sessionKey, null);
            if (request()->isAjax() || request()->isJson()) {
                return json(['status' => 0, 'msg' => '登录令牌已过期，请重新操作']);
            }
            return redirect($this->buildAdminHashUrl('login'));
        }

        $uid = isset($info['uid']) ? intval($info['uid']) : 0;
        if ($uid <= 0) {
            session($sessionKey, null);
            if (request()->isAjax() || request()->isJson()) {
                return json(['status' => 0, 'msg' => '登录令牌数据错误']);
            }
            return redirect($this->buildAdminHashUrl('login'));
        }

        // 查询管理员用户
        $user = Db::name('admin_user')->where('id', $uid)->find();
        if (!$user || $user['status'] != 1) {
            session($sessionKey, null);
            if (request()->isAjax() || request()->isJson()) {
                return json(['status' => 0, 'msg' => '用户不存在或已禁用']);
            }
            return redirect($this->buildAdminHashUrl('login'));
        }

        $this->rememberOriginConsoleSession();
        $this->writeLoginSession($user, true);

        // 记录登录日志
        try {
            Db::name('admin_loginlog')->insert([
                'aid'       => $user['aid'],
                'uid'       => $user['id'],
                'logintime' => time(),
                'loginip'   => request()->ip(),
                'logintype' => '控制台一键登录',
            ]);
        } catch (\Exception $e) {
            // 日志失败不影响登录流程
        }

        // 一次性令牌，用完即销毁
        session($sessionKey, null);

        // 如果是 AJAX 请求，返回 JSON，由前端自行跳转到 Vue 后台首页
        if (request()->isAjax() || request()->isJson()) {
            return json([
                'status' => 1,
                'msg'    => '登录成功',
                'data'   => $this->buildLoginData($user),
            ]);
        }

        // 非 AJAX 请求，直接重定向到 Vue 后台首页（hash 路由）
        $targetPath = in_array(intval(session('CONSOLE_ROLE')), [3, 4], true) ? 'console' : 'stats';
        return redirect($this->buildAdminHashUrl($targetPath));
    }


    /**
     * 获取用户信息
     * @return \think\response\Json
     */
    public function getUserInfo()
    {
        try {
            // 检查登录状态
            if (!session('ADMIN_LOGIN')) {
                return json(['status'=>0, 'msg'=>'未登录']);
            }

            $uid = session('ADMIN_UID');

            // 查询用户信息
            $userInfo = Db::name('admin_user')
                ->where('id', $uid)
                ->field('id,un,aid,bid,isadmin,type,mid,console_role,root_uid,channel_auth_data')
                ->find();

            if (!$userInfo) {
                return json(['status'=>0, 'msg'=>'用户不存在']);
            }

            // 返回用户信息（符合 Vben UserInfo 格式）
            return json([
                'status' => 1,
                'msg' => '获取成功',
                'data' => $this->buildLoginData($userInfo)
            ]);

        } catch (\Exception $e) {
            return json(['status'=>0, 'msg'=>'获取失败：'.$e->getMessage()]);
        }
    }

    /**
     * 从站点后台恢复到原控制台账号
     */
    public function restoreConsole()
    {
        if (!session('ADMIN_LOGIN')) {
            return json(['status' => 0, 'msg' => '未登录']);
        }

        $originUser = $this->getOriginConsoleUser();
        if (!$originUser) {
            $uid = intval(session('ADMIN_UID'));
            $current = $uid > 0 ? Db::name('admin_user')->where('id', $uid)->find() : null;
            if ($current && $this->isConsoleRole(\app\admin\common\ConsoleAuth::resolveRoleFromUser($current))) {
                return json([
                    'status' => 1,
                    'msg' => '已在控制台',
                    'data' => $this->buildLoginData($current),
                ]);
            }
            return json(['status' => 0, 'msg' => '当前不能返回控制台']);
        }

        if (intval($originUser['status'] ?? 0) !== 1) {
            return json(['status' => 0, 'msg' => '原控制台账号已禁用']);
        }

        $this->clearOriginConsoleSession();
        $this->writeLoginSession($originUser);

        return json([
            'status' => 1,
            'msg' => '已返回控制台',
            'data' => $this->buildLoginData($originUser),
        ]);
    }

    private function isConsoleRole(int $role): bool
    {
        return in_array($role, [
            \app\admin\common\ConsoleAuth::ROLE_SUPER,
            \app\admin\common\ConsoleAuth::ROLE_CHANNEL_ROOT,
            \app\admin\common\ConsoleAuth::ROLE_CHANNEL_SUB,
        ], true);
    }

    private function clearOriginConsoleSession(): void
    {
        session('ORIGIN_CONSOLE_UID', null);
        session('ORIGIN_CONSOLE_ROLE', null);
        session('ORIGIN_CONSOLE_ROOT_UID', null);
    }

    private function rememberOriginConsoleSession(): void
    {
        if (intval(session('ORIGIN_CONSOLE_UID')) > 0) {
            return;
        }

        $uid = intval(session('CONSOLE_UID') ?: 0);
        if ($uid <= 0) {
            $uid = intval(session('BST_ID') ?: 0);
        }
        if ($uid <= 0) {
            $uid = intval(session('ADMIN_UID') ?: 0);
        }
        if ($uid <= 0) {
            return;
        }

        $user = Db::name('admin_user')->where('id', $uid)->find();
        if (!$user) {
            return;
        }
        $role = \app\admin\common\ConsoleAuth::resolveRoleFromUser($user);
        if (!$this->isConsoleRole($role)) {
            return;
        }

        session('ORIGIN_CONSOLE_UID', intval($user['id']));
        session('ORIGIN_CONSOLE_ROLE', $role);
        session('ORIGIN_CONSOLE_ROOT_UID', \app\admin\common\ConsoleAuth::resolveRootUidFromUser($user));
    }

    private function getOriginConsoleUser(): array
    {
        $uid = intval(session('ORIGIN_CONSOLE_UID') ?: 0);
        if ($uid <= 0) {
            return [];
        }
        $user = Db::name('admin_user')->where('id', $uid)->find();
        if (!$user) {
            return [];
        }
        $role = \app\admin\common\ConsoleAuth::resolveRoleFromUser($user);
        if (!$this->isConsoleRole($role)) {
            return [];
        }
        return $user;
    }

    private function writeLoginSession(array $user, bool $keepOrigin = false): void
    {
        $userId = intval($user['id'] ?? 0);
        $consoleRole = \app\admin\common\ConsoleAuth::resolveRoleFromUser($user);
        $consoleRootUid = \app\admin\common\ConsoleAuth::resolveRootUidFromUser($user);
        $isConsole = $this->isConsoleRole($consoleRole);

        session('ADMIN_LOGIN', 1);
        session('ADMIN_UID', $userId);
        session('ADMIN_AID', intval($user['aid'] ?? 0));
        session('ADMIN_BID', intval($user['bid'] ?? 0));
        session('ADMIN_NAME', strval($user['un'] ?? ''));
        session('IS_ADMIN', intval($user['isadmin'] ?? 0));
        if ($isConsole) {
            session('CONSOLE_UID', $userId);
            session('CONSOLE_ROLE', $consoleRole);
            session('CONSOLE_ROOT_UID', $consoleRootUid > 0 ? $consoleRootUid : null);
            session('BST_ID', $consoleRole === \app\admin\common\ConsoleAuth::ROLE_SUPER ? $userId : null);
        } else {
            $this->clearConsoleIdentitySession();
        }
        if (!$keepOrigin) {
            $this->clearOriginConsoleSession();
        }
    }

    private function clearConsoleIdentitySession(): void
    {
        session('CONSOLE_UID', null);
        session('CONSOLE_ROLE', null);
        session('CONSOLE_ROOT_UID', null);
        session('BST_ID', null);
        try {
            \think\facade\Session::delete('CONSOLE_UID');
            \think\facade\Session::delete('CONSOLE_ROLE');
            \think\facade\Session::delete('CONSOLE_ROOT_UID');
            \think\facade\Session::delete('BST_ID');
        } catch (\Throwable $e) {
        }
    }

    private function buildLoginData(array $user): array
    {
        $consoleRole = \app\admin\common\ConsoleAuth::resolveRoleFromUser($user);
        $consoleRootUid = \app\admin\common\ConsoleAuth::resolveRootUidFromUser($user);
        $channelPermissions = \app\admin\common\ConsoleAuth::getChannelAuthData($user);
        $permissionMap = [];
        foreach (\app\admin\common\ConsoleAuth::getChannelPermissionKeys() as $key => $title) {
            $permissionMap[$key] = \app\admin\common\ConsoleAuth::hasChannelPermission($key, $user);
        }

        $isConsole = $this->isConsoleRole($consoleRole);
        $canReturnConsole = !empty($this->getOriginConsoleUser());

        return array_merge([
            'uid' => (int)($user['id'] ?? 0),
            'username' => (string)($user['un'] ?? ''),
            'aid' => (int)($user['aid'] ?? 0),
            'bid' => (int)($user['bid'] ?? 0),
            'isadmin' => (int)($user['isadmin'] ?? 0),
            'console_role' => (int)$consoleRole,
            'console_root_uid' => (int)$consoleRootUid,
            'is_console' => $isConsole,
            'can_return_console' => $canReturnConsole,
            'channel_permissions' => array_values($channelPermissions),
            'channel_permission_map' => $permissionMap,
            'userId' => (string)($user['id'] ?? 0),
            'realName' => (string)($user['un'] ?? ''),
            'roles' => intval($user['isadmin'] ?? 0) == 1 ? ['admin'] : ['user'],
            'homePath' => '/dashboard/analytics',
        ], $this->buildStorageSummary($user), $this->buildMobilePreviewData($user));
    }

    private function buildStorageSummary(array $user): array
    {
        $aid = (int)($user['aid'] ?? 0);
        $bid = (int)($user['bid'] ?? 0);

        if ($aid <= 0) {
            return [
                'storage_used' => 0,
                'storage_used_format' => '0 Bytes',
                'storage_remaining_format' => '不限制',
                'storage_limit_format' => '不限制',
                'storage_has_limit' => false,
            ];
        }

        if ($bid > 0) {
            $storageUsed = 0 + Db::name('admin_upload')
                ->where('aid', $aid)
                ->where('merchant_id', $bid)
                ->where('isdel', 0)
                ->sum('size');

            $userType = (string)($user['type'] ?? '');
            if ($userType === 'tuanzhang') {
                $storageLimit = Db::name('tuanzhang')
                    ->where('aid', $aid)
                    ->where('id', $bid)
                    ->value('storage_limit');
            } else {
                $storageLimit = Db::name('business')
                    ->where('aid', $aid)
                    ->where('id', $bid)
                    ->value('storage_limit');
            }
        } else {
            $storageUsed = 0 + Db::name('admin_upload')
                ->where('aid', $aid)
                ->where('isdel', 0)
                ->sum('size');

            $storageLimit = Db::name('admin')
                ->where('id', $aid)
                ->value('storage_limit');
        }

        $storageLimit = (int)$storageLimit;
        $storageHasLimit = $storageLimit > 0;
        $storageRemainingFormat = '不限制';
        $storageLimitFormat = '不限制';

        if ($storageHasLimit) {
            $storageLimitBytes = $storageLimit * 1024 * 1024;
            $storageRemaining = max(0, $storageLimitBytes - $storageUsed);
            $storageRemainingFormat = $this->formatBytes($storageRemaining);
            $storageLimitFormat = $storageLimit . 'MB';
        }

        return [
            'storage_used' => (int)$storageUsed,
            'storage_used_format' => $this->formatBytes($storageUsed),
            'storage_remaining_format' => $storageRemainingFormat,
            'storage_limit_format' => $storageLimitFormat,
            'storage_has_limit' => $storageHasLimit,
        ];
    }

    private function formatBytes($bytes): string
    {
        $bytes = (float)$bytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return (int)$bytes . ' Bytes';
    }

    private function buildMobilePreviewData(array $user): array
    {
        $aid = (int)($user['aid'] ?? 0);
        $uid = (int)($user['id'] ?? 0);
        $mid = (int)($user['mid'] ?? 0);

        if ($aid <= 0 || $uid <= 0 || $mid <= 0) {
            return [
                'mobile_preview_enabled' => false,
                'mobile_preview_url' => '',
                'mobile_preview_socket_token' => '',
                'mobile_preview_socket_mid' => 0,
                'mobile_preview_sound_url' => '',
            ];
        }

        $socketToken = (string)Db::name('member')->where('id', $mid)->value('random_str');
        if ($socketToken === '') {
            return [
                'mobile_preview_enabled' => false,
                'mobile_preview_url' => '',
                'mobile_preview_socket_token' => '',
                'mobile_preview_socket_mid' => 0,
                'mobile_preview_sound_url' => '',
            ];
        }

        $set = Db::name('admin_set')->where('aid', $aid)->find();
        $soundUrl = (string)($set['qiang_video'] ?? '');
        if ($soundUrl === '') {
            $soundUrl = '/static/chat/13202.wav';
        }

        $mobilePreviewUrl = m_url('admin/index/index', $aid);
        $mobilePreviewUrl .= (strpos($mobilePreviewUrl, '?') === false ? '?' : '&') . 'uid=' . $uid;

        return [
            'mobile_preview_enabled' => true,
            'mobile_preview_url' => $mobilePreviewUrl,
            'mobile_preview_socket_token' => $socketToken,
            'mobile_preview_socket_mid' => $mid,
            'mobile_preview_sound_url' => $soundUrl,
        ];
    }

    /**
     * 用户登出
     * @return \think\response\Json
     */
    public function logout()
    {
        try {
            session('ADMIN_LOGIN', null);
            session('ADMIN_UID', null);
            session('ADMIN_AID', null);
            session('ADMIN_BID', null);
            session('ADMIN_NAME', null);
            session('IS_ADMIN', null);
            session('BST_ID', null);
            session('CONSOLE_UID', null);
            session('CONSOLE_ROLE', null);
            session('CONSOLE_ROOT_UID', null);
            session('ORIGIN_CONSOLE_UID', null);
            session('ORIGIN_CONSOLE_ROLE', null);
            session('ORIGIN_CONSOLE_ROOT_UID', null);
            session('RESTAURANT_CASHIER_LOGIN', null);

            return json(['status'=>1, 'msg'=>'退出成功']);
        } catch (\Exception $e) {
            return json(['status'=>0, 'msg'=>'退出失败：'.$e->getMessage()]);
        }
    }
}
