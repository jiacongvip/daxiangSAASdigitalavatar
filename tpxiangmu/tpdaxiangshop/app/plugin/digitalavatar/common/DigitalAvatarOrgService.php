<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarOrgService
{
    public static function permissionModules(): array
    {
        return [
            ['key' => 'workbench', 'group' => '基础', 'name' => '工作台'],
            ['key' => 'settings', 'group' => '基础', 'name' => '系统设置'],
            ['key' => 'ads', 'group' => '基础', 'name' => '首页广告'],
            ['key' => 'portal-entry', 'group' => '基础', 'name' => '用户端入口'],
            ['key' => 'image-agent-skills', 'group' => '模型与资源', 'name' => 'Agent作图技能'],
            ['key' => 'providers', 'group' => '模型与资源', 'name' => '提供商配置'],
            ['key' => 'models', 'group' => '模型与资源', 'name' => '模型管理'],
            ['key' => 'charge-rules', 'group' => '模型与资源', 'name' => '每日额度'],
            ['key' => 'public-avatars', 'group' => '模型与资源', 'name' => '公共数字人'],
            ['key' => 'public-voices', 'group' => '模型与资源', 'name' => '公共声音'],
            ['key' => 'packages', 'group' => '会员运营', 'name' => '会员套餐'],
            ['key' => 'boost-packs', 'group' => '会员运营', 'name' => '加油包充值'],
            ['key' => 'redeem-codes', 'group' => '会员运营', 'name' => '兑换码'],
            ['key' => 'member-credits', 'group' => '会员运营', 'name' => '会员额度'],
            ['key' => 'tasks', 'group' => '会员运营', 'name' => '任务管理'],
            ['key' => 'credit-logs', 'group' => '会员运营', 'name' => '额度流水'],
            ['key' => 'invoices', 'group' => '会员运营', 'name' => '发票管理'],
            ['key' => 'checkin-set', 'group' => '会员运营', 'name' => '签到设置'],
            ['key' => 'checkin-logs', 'group' => '会员运营', 'name' => '签到记录'],
            ['key' => 'invite-set', 'group' => '会员运营', 'name' => '拉新设置'],
            ['key' => 'invite-logs', 'group' => '会员运营', 'name' => '拉新记录'],
            ['key' => 'oem', 'group' => '会员运营', 'name' => 'OEM / 商家代理'],
            ['key' => 'package-orders', 'group' => '财务管理', 'name' => '订单管理'],
            ['key' => 'withdraws', 'group' => '财务管理', 'name' => '提现管理'],
            ['key' => 'commission-levels', 'group' => '财务管理', 'name' => '团队长分佣等级'],
            ['key' => 'admin-roles', 'group' => '管理员管理', 'name' => '管理员角色'],
            ['key' => 'admins', 'group' => '管理员管理', 'name' => '管理员管理'],
            ['key' => 'agents', 'group' => '代理管理', 'name' => '代理列表'],
        ];
    }

    public static function listRoles(int $aid, int $page = 1, int $limit = 50, string $keyword = '', string $status = ''): array
    {
        $query = Db::name('digitalavatar_admin_role')->where('aid', $aid);
        if ($keyword !== '') {
            $query->whereLike('name|remark', '%' . $keyword . '%');
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('status', intval($status));
        }
        $total = intval((clone $query)->count());
        $list = $query->page(max(1, $page), max(1, min(200, $limit)))->order('sort asc,id desc')->select()->toArray();
        foreach ($list as &$item) {
            $item = self::formatRole($item);
        }
        unset($item);
        return [
            'list' => $list,
            'total' => $total,
            'page' => max(1, $page),
            'limit' => max(1, min(200, $limit)),
            'modules' => self::permissionModules(),
        ];
    }

    public static function saveRole(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $name = trim(strval($info['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('请填写角色名称');
        }
        $dup = Db::name('digitalavatar_admin_role')->where('aid', $aid)->where('name', $name);
        if ($id > 0) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new \RuntimeException('角色名称已存在');
        }
        $now = time();
        $data = [
            'aid' => $aid,
            'name' => mb_substr($name, 0, 120),
            'permissions' => json_encode(self::normalizePermissions($info['permissions'] ?? []), JSON_UNESCAPED_UNICODE),
            'remark' => mb_substr(trim(strval($info['remark'] ?? '')), 0, 255),
            'status' => intval($info['status'] ?? 1) === 0 ? 0 : 1,
            'sort' => max(0, intval($info['sort'] ?? 0)),
            'update_time' => $now,
        ];
        if ($id > 0) {
            $exists = Db::name('digitalavatar_admin_role')->where('aid', $aid)->where('id', $id)->find();
            if (!$exists) {
                throw new \RuntimeException('角色不存在');
            }
            Db::name('digitalavatar_admin_role')->where('id', $id)->update($data);
        } else {
            $data['create_time'] = $now;
            $id = intval(Db::name('digitalavatar_admin_role')->insertGetId($data));
        }
        $row = Db::name('digitalavatar_admin_role')->where('id', $id)->find();
        return self::formatRole($row ?: $data);
    }

    public static function deleteRole(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择角色');
        }
        $used = intval(Db::name('digitalavatar_admin_user')->where('aid', $aid)->where('role_id', $id)->count());
        if ($used > 0) {
            throw new \RuntimeException('该角色下还有管理员，无法删除');
        }
        $affected = Db::name('digitalavatar_admin_role')->where('aid', $aid)->where('id', $id)->delete();
        if ($affected <= 0) {
            throw new \RuntimeException('角色不存在');
        }
    }

    public static function listAdmins(int $aid, int $page = 1, int $limit = 50, string $keyword = '', string $status = '', int $roleId = 0): array
    {
        $query = Db::name('digitalavatar_admin_user')
            ->alias('u')
            ->leftJoin('digitalavatar_admin_role r', 'r.aid = u.aid and r.id = u.role_id')
            ->where('u.aid', $aid);
        if ($keyword !== '') {
            $query->whereLike('u.username|u.remark', '%' . $keyword . '%');
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('u.status', intval($status));
        }
        if ($roleId > 0) {
            $query->where('u.role_id', $roleId);
        }
        $total = intval((clone $query)->count());
        $list = $query
            ->field('u.*,r.name as role_name')
            ->page(max(1, $page), max(1, min(200, $limit)))
            ->order('u.id desc')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item = self::formatAdmin($item);
        }
        unset($item);
        $roles = Db::name('digitalavatar_admin_role')->where('aid', $aid)->where('status', 1)->order('sort asc,id desc')->select()->toArray();
        $roleOptions = [];
        foreach ($roles as $role) {
            $roleOptions[] = [
                'id' => intval($role['id'] ?? 0),
                'name' => strval($role['name'] ?? ''),
            ];
        }
        return [
            'list' => $list,
            'total' => $total,
            'page' => max(1, $page),
            'limit' => max(1, min(200, $limit)),
            'roles' => $roleOptions,
        ];
    }

    public static function saveAdmin(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $username = trim(strval($info['username'] ?? ''));
        if ($username === '') {
            throw new \RuntimeException('请填写用户名');
        }
        if (!preg_match('/^[A-Za-z0-9_\-]{2,60}$/', $username)) {
            throw new \RuntimeException('用户名仅支持 2-60 位字母、数字、下划线和短横线');
        }
        $dup = Db::name('digitalavatar_admin_user')->where('aid', $aid)->where('username', $username);
        if ($id > 0) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new \RuntimeException('用户名已存在');
        }
        $roleId = intval($info['role_id'] ?? 0);
        if ($roleId > 0) {
            $role = Db::name('digitalavatar_admin_role')->where('aid', $aid)->where('id', $roleId)->find();
            if (!$role) {
                throw new \RuntimeException('角色分组不存在');
            }
        }
        $password = strval($info['password'] ?? '');
        $now = time();
        $data = [
            'aid' => $aid,
            'username' => $username,
            'role_id' => $roleId,
            'remark' => mb_substr(trim(strval($info['remark'] ?? '')), 0, 255),
            'status' => intval($info['status'] ?? 1) === 0 ? 0 : 1,
            'update_time' => $now,
        ];
        if ($id > 0) {
            $exists = Db::name('digitalavatar_admin_user')->where('aid', $aid)->where('id', $id)->find();
            if (!$exists) {
                throw new \RuntimeException('管理员不存在');
            }
            if ($password !== '') {
                $data['password'] = self::hashPassword($password);
            }
            Db::name('digitalavatar_admin_user')->where('id', $id)->update($data);
        } else {
            if ($password === '') {
                throw new \RuntimeException('请填写登录密码');
            }
            $data['password'] = self::hashPassword($password);
            $data['create_time'] = $now;
            $id = intval(Db::name('digitalavatar_admin_user')->insertGetId($data));
        }
        $row = Db::name('digitalavatar_admin_user')
            ->alias('u')
            ->leftJoin('digitalavatar_admin_role r', 'r.aid = u.aid and r.id = u.role_id')
            ->where('u.id', $id)
            ->field('u.*,r.name as role_name')
            ->find();
        return self::formatAdmin($row ?: $data);
    }

    public static function setAdminStatus(int $aid, int $id, int $status): array
    {
        $row = Db::name('digitalavatar_admin_user')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('管理员不存在');
        }
        $status = $status === 1 ? 1 : 0;
        Db::name('digitalavatar_admin_user')->where('id', $id)->update(['status' => $status, 'update_time' => time()]);
        $row['status'] = $status;
        return self::formatAdmin($row);
    }

    public static function deleteAdmin(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择管理员');
        }
        $affected = Db::name('digitalavatar_admin_user')->where('aid', $aid)->where('id', $id)->delete();
        if ($affected <= 0) {
            throw new \RuntimeException('管理员不存在');
        }
    }

    public static function listAgents(int $aid, int $page = 1, int $limit = 100, string $keyword = '', string $status = ''): array
    {
        $query = Db::name('digitalavatar_agent')->where('aid', $aid);
        if ($keyword !== '') {
            $query->whereLike('name|mobile|remark', '%' . $keyword . '%');
        }
        if ($status !== '' && is_numeric($status)) {
            $query->where('status', intval($status));
        }
        $total = intval((clone $query)->count());
        $list = $query->page(max(1, $page), max(1, min(200, $limit)))->order('id desc')->select()->toArray();
        foreach ($list as &$item) {
            $item = self::formatAgent($item);
        }
        unset($item);
        return [
            'list' => $list,
            'total' => $total,
            'page' => max(1, $page),
            'limit' => max(1, min(200, $limit)),
        ];
    }

    public static function getAgentDetail(int $aid, int $id): array
    {
        $row = Db::name('digitalavatar_agent')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('代理不存在');
        }
        return self::formatAgent($row);
    }

    public static function saveAgent(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $name = trim(strval($info['name'] ?? ''));
        $mobile = trim(strval($info['mobile'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('请填写代理名称');
        }
        if ($mobile === '') {
            throw new \RuntimeException('请填写登陆手机号');
        }
        if (!preg_match('/^1\d{10}$/', $mobile)) {
            throw new \RuntimeException('登陆手机号格式不正确');
        }
        $dup = Db::name('digitalavatar_agent')->where('aid', $aid)->where('mobile', $mobile);
        if ($id > 0) {
            $dup->where('id', '<>', $id);
        }
        if ($dup->find()) {
            throw new \RuntimeException('该手机号已存在');
        }
        $password = strval($info['password'] ?? '');
        $expireTime = self::parseExpireTime($info);
        $now = time();
        $data = [
            'aid' => $aid,
            'name' => mb_substr($name, 0, 120),
            'mobile' => $mobile,
            'money' => round(max(0, floatval($info['money'] ?? 0)), 2),
            'expire_time' => $expireTime,
            'remark' => mb_substr(trim(strval($info['remark'] ?? '')), 0, 255),
            'status' => intval($info['status'] ?? 1) === 0 ? 0 : 1,
            'update_time' => $now,
        ];
        if ($id > 0) {
            $exists = Db::name('digitalavatar_agent')->where('aid', $aid)->where('id', $id)->find();
            if (!$exists) {
                throw new \RuntimeException('代理不存在');
            }
            if ($password !== '') {
                $data['password'] = self::hashPassword($password);
            }
            Db::name('digitalavatar_agent')->where('id', $id)->update($data);
        } else {
            if ($password === '') {
                throw new \RuntimeException('请填写登陆密码');
            }
            $data['password'] = self::hashPassword($password);
            $data['create_time'] = $now;
            $id = intval(Db::name('digitalavatar_agent')->insertGetId($data));
        }
        $row = Db::name('digitalavatar_agent')->where('id', $id)->find();
        return self::formatAgent($row ?: $data);
    }

    public static function setAgentStatus(int $aid, int $id, int $status): array
    {
        $row = Db::name('digitalavatar_agent')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('代理不存在');
        }
        $status = $status === 1 ? 1 : 0;
        Db::name('digitalavatar_agent')->where('id', $id)->update(['status' => $status, 'update_time' => time()]);
        $row['status'] = $status;
        return self::formatAgent($row);
    }

    public static function deleteAgent(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请选择代理');
        }
        $affected = Db::name('digitalavatar_agent')->where('aid', $aid)->where('id', $id)->delete();
        if ($affected <= 0) {
            throw new \RuntimeException('代理不存在');
        }
    }

    private static function hashPassword(string $password): string
    {
        $password = trim($password);
        if (strlen($password) < 6) {
            throw new \RuntimeException('密码至少 6 位');
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private static function normalizePermissions($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $allowed = [];
        foreach (self::permissionModules() as $module) {
            $allowed[$module['key']] = true;
        }
        $keys = [];
        foreach ($raw as $item) {
            $key = trim(strval($item));
            if ($key !== '' && isset($allowed[$key])) {
                $keys[] = $key;
            }
        }
        return array_values(array_unique($keys));
    }

    private static function parseExpireTime(array $info): int
    {
        if (isset($info['expire_time']) && is_numeric($info['expire_time']) && intval($info['expire_time']) > 100000) {
            return intval($info['expire_time']);
        }
        $date = trim(strval($info['expire_date'] ?? ($info['expire_time'] ?? '')));
        if ($date === '' || $date === '0') {
            return 0;
        }
        $ts = strtotime($date . (strpos($date, ' ') !== false ? '' : ' 23:59:59'));
        return $ts ? intval($ts) : 0;
    }

    private static function formatRole($row): array
    {
        $row = is_array($row) ? $row : [];
        return [
            'id' => intval($row['id'] ?? 0),
            'name' => strval($row['name'] ?? ''),
            'permissions' => self::normalizePermissions($row['permissions'] ?? []),
            'permissions_text' => implode('、', self::permissionNames($row['permissions'] ?? [])),
            'remark' => strval($row['remark'] ?? ''),
            'status' => intval($row['status'] ?? 0) === 1 ? 1 : 0,
            'sort' => intval($row['sort'] ?? 0),
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
        ];
    }

    private static function permissionNames($raw): array
    {
        $keys = self::normalizePermissions($raw);
        $map = [];
        foreach (self::permissionModules() as $module) {
            $map[$module['key']] = $module['name'];
        }
        $names = [];
        foreach ($keys as $key) {
            $names[] = $map[$key] ?? $key;
        }
        return $names;
    }

    private static function formatAdmin(array $row): array
    {
        $status = intval($row['status'] ?? 0) === 1 ? 1 : 0;
        return [
            'id' => intval($row['id'] ?? 0),
            'username' => strval($row['username'] ?? ''),
            'role_id' => intval($row['role_id'] ?? 0),
            'role_name' => strval($row['role_name'] ?? '') ?: '未分组',
            'remark' => strval($row['remark'] ?? ''),
            'status' => $status,
            'status_text' => $status === 1 ? '正常' : '禁止',
            'last_login_time' => intval($row['last_login_time'] ?? 0),
            'last_login_time_text' => intval($row['last_login_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['last_login_time'])) : '',
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
        ];
    }

    private static function formatAgent(array $row): array
    {
        $status = intval($row['status'] ?? 0) === 1 ? 1 : 0;
        $expireTime = intval($row['expire_time'] ?? 0);
        $expired = $expireTime > 0 && $expireTime < time();
        return [
            'id' => intval($row['id'] ?? 0),
            'name' => strval($row['name'] ?? ''),
            'mobile' => strval($row['mobile'] ?? ''),
            'money' => number_format(floatval($row['money'] ?? 0), 2, '.', ''),
            'expire_time' => $expireTime,
            'expire_date' => $expireTime > 0 ? date('Y-m-d', $expireTime) : '',
            'expire_time_text' => $expireTime > 0 ? date('Y-m-d', $expireTime) : '长期有效',
            'expired' => $expired ? 1 : 0,
            'remark' => strval($row['remark'] ?? ''),
            'status' => $status,
            'status_text' => $status === 1 ? ($expired ? '已过期' : '正常') : '禁止',
            'create_time' => intval($row['create_time'] ?? 0),
            'create_time_text' => intval($row['create_time'] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($row['create_time'])) : '',
        ];
    }
}
