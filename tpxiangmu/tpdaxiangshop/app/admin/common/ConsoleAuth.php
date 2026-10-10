<?php

namespace app\admin\common;

use think\facade\Db;

class ConsoleAuth
{
    const ROLE_NONE = 0;
    const ROLE_SUPER = 2;
    const ROLE_CHANNEL_ROOT = 3;
    const ROLE_CHANNEL_SUB = 4;

    public static function getChannelPermissionKeys(): array
    {
        return [
            'platform.manage' => '平台管理',
            'plugin.manage' => '插件授权',
            'subuser.manage' => '子管理员管理',
        ];
    }

    public static function getConsoleUid(): int
    {
        $uid = intval(session('CONSOLE_UID'));
        if ($uid > 0) {
            return $uid;
        }
        return intval(session('BST_ID'));
    }

    public static function getConsoleUser(): array
    {
        $uid = self::getConsoleUid();
        if ($uid <= 0) {
            return [];
        }
        $user = Db::name('admin_user')->where('id', $uid)->find();
        return $user ?: [];
    }

    public static function getConsoleRole(array $user = []): int
    {
        if (!empty($user)) {
            return self::resolveRoleFromUser($user);
        }

        $role = intval(session('CONSOLE_ROLE'));
        if ($role > 0) {
            return $role;
        }
        if (intval(session('BST_ID')) > 0) {
            return self::ROLE_SUPER;
        }
        return self::ROLE_NONE;
    }

    public static function getConsoleRootUid(array $user = []): int
    {
        if (!empty($user)) {
            return self::resolveRootUidFromUser($user);
        }

        $rootUid = intval(session('CONSOLE_ROOT_UID'));
        if ($rootUid > 0) {
            return $rootUid;
        }

        return 0;
    }

    public static function resolveRoleFromUser(array $user): int
    {
        $role = intval($user['console_role'] ?? 0);
        if ($role > 0) {
            return $role;
        }
        if (intval($user['isadmin'] ?? 0) === 2) {
            return self::ROLE_SUPER;
        }
        return self::ROLE_NONE;
    }

    public static function resolveRootUidFromUser(array $user): int
    {
        $role = self::resolveRoleFromUser($user);
        if ($role !== self::ROLE_CHANNEL_ROOT && $role !== self::ROLE_CHANNEL_SUB) {
            return 0;
        }

        $rootUid = intval($user['root_uid'] ?? 0);
        if ($rootUid > 0) {
            return $rootUid;
        }
        if ($role === self::ROLE_CHANNEL_ROOT) {
            return intval($user['id'] ?? 0);
        }
        return intval($user['parent_uid'] ?? 0);
    }

    public static function isConsole(array $user = []): bool
    {
        return in_array(self::getConsoleRole($user), [self::ROLE_SUPER, self::ROLE_CHANNEL_ROOT, self::ROLE_CHANNEL_SUB], true);
    }

    public static function isSuperConsole(array $user = []): bool
    {
        return self::getConsoleRole($user) === self::ROLE_SUPER;
    }

    public static function isChannelConsole(array $user = []): bool
    {
        $role = self::getConsoleRole($user);
        return $role === self::ROLE_CHANNEL_ROOT || $role === self::ROLE_CHANNEL_SUB;
    }

    public static function getChannelAuthData(array $user = []): array
    {
        if (empty($user)) {
            $user = self::getConsoleUser();
        }
        if (empty($user)) {
            return [];
        }

        $raw = $user['channel_auth_data'] ?? '';
        if ($raw === '' || $raw === null) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $permissions = [];
        foreach ($decoded as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && trim($value) !== '') {
                    $permissions[] = trim($value);
                }
                continue;
            }
            if ($value === true || intval($value) === 1) {
                $permissions[] = trim(strval($key));
            }
        }

        return array_values(array_unique(array_filter($permissions)));
    }

    public static function hasChannelPermission(string $permissionKey, array $user = []): bool
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '') {
            return false;
        }

        $role = self::getConsoleRole($user);
        if ($role === self::ROLE_SUPER || $role === self::ROLE_CHANNEL_ROOT) {
            return true;
        }
        if ($role !== self::ROLE_CHANNEL_SUB) {
            return false;
        }

        $permissions = self::getChannelAuthData($user);
        return in_array('all', $permissions, true) || in_array($permissionKey, $permissions, true);
    }

    public static function normalizeChannelPermissions(array $permissions): array
    {
        $allowKeys = array_keys(self::getChannelPermissionKeys());
        $result = [];
        foreach ($permissions as $permission) {
            $permission = trim(strval($permission));
            if ($permission !== '' && in_array($permission, $allowKeys, true)) {
                $result[] = $permission;
            }
        }
        return array_values(array_unique($result));
    }
}
