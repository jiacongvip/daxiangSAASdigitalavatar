<?php

namespace app\admin\common;

use think\facade\Db;

class PluginBusinessAuth
{
    public static function isAidPluginReady(int $aid, string $pluginKey, array $requiredTables = []): bool
    {
        $pluginKey = trim($pluginKey);
        if (!self::isAidPluginEnabled($aid, $pluginKey)) {
            return false;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $pluginKey)) {
            return false;
        }

        $configFile = app()->getRootPath() . 'app/plugin/' . $pluginKey . '/plugin.json';
        if (
            !is_file($configFile)
            || !self::tableExists('admin_plugin_version')
            || !self::tableExists('admin_plugin_menu')
        ) {
            return false;
        }

        try {
            $hasInstallRecord = Db::name('admin_plugin_version')
                ->where('plugin_name', $pluginKey)
                ->count() > 0;
            if (!$hasInstallRecord) {
                return false;
            }

            $hasEnabledMenu = Db::name('admin_plugin_menu')
                ->where('aid', $aid)
                ->where('plugin_name', $pluginKey)
                ->where('status', 1)
                ->count() > 0;
        } catch (\Throwable $e) {
            return false;
        }

        if (!$hasEnabledMenu) {
            return false;
        }

        foreach ($requiredTables as $tableName) {
            $tableName = trim(strval($tableName));
            if ($tableName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName) || !self::tableExists($tableName)) {
                return false;
            }
        }

        return true;
    }

    public static function sanitizeHexiaoAuthData(int $aid, array $permissions): array
    {
        return PluginHexiaoRegistry::sanitizeAuthData($aid, $permissions);
    }

    public static function isAidPluginEnabled(int $aid, string $pluginKey): bool
    {
        $pluginKey = trim($pluginKey);
        if ($aid <= 0 || $pluginKey === '' || !self::tableExists('admin_plugin_license')) {
            return false;
        }

        try {
            $row = Db::name('admin_plugin_license')
                ->where('aid', $aid)
                ->where('plugin_name', $pluginKey)
                ->find();
        } catch (\Throwable $e) {
            return false;
        }

        return self::isActiveRow($row);
    }

    public static function isBusinessPluginEnabled(int $aid, int $bid, string $pluginKey): bool
    {
        if (!self::isAidPluginEnabled($aid, $pluginKey)) {
            return false;
        }

        return !empty(self::getBusinessAuth($aid, $bid, $pluginKey));
    }

    public static function hasBusinessPermission(int $aid, int $bid, string $pluginKey, string $permissionKey = ''): bool
    {
        $permissionKey = trim($permissionKey);
        $auth = self::getBusinessAuth($aid, $bid, $pluginKey);
        if (empty($auth)) {
            return false;
        }

        if ($permissionKey === '') {
            return true;
        }

        $permissions = $auth['permissions_list'] ?? [];
        return in_array('all', $permissions, true) || in_array($permissionKey, $permissions, true);
    }

    public static function getBusinessPermissions(int $aid, int $bid, string $pluginKey): array
    {
        $auth = self::getBusinessAuth($aid, $bid, $pluginKey);
        if (empty($auth)) {
            return [];
        }

        return $auth['permissions_list'] ?? [];
    }

    public static function getBusinessAuth(int $aid, int $bid, string $pluginKey): array
    {
        $pluginKey = trim($pluginKey);
        if ($aid <= 0 || $bid <= 0 || $pluginKey === '' || !self::tableExists('plugin_business_auth')) {
            return [];
        }

        if (!self::isAidPluginEnabled($aid, $pluginKey)) {
            return [];
        }

        try {
            $row = Db::name('plugin_business_auth')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('plugin_key', $pluginKey)
                ->find();
        } catch (\Throwable $e) {
            return [];
        }

        if (!self::isActiveRow($row)) {
            return [];
        }

        $row['permissions_list'] = self::decodePermissions($row['permissions'] ?? '');
        return $row;
    }

    public static function normalizePermissions(array $permissions): array
    {
        $result = [];
        foreach ($permissions as $key => $value) {
            if (is_int($key)) {
                $permission = trim(strval($value));
                if ($permission !== '') {
                    $result[] = $permission;
                }
                continue;
            }

            if ($value === true || intval($value) === 1) {
                $permission = trim(strval($key));
                if ($permission !== '') {
                    $result[] = $permission;
                }
            }
        }

        return array_values(array_unique($result));
    }

    public static function encodePermissions(array $permissions): string
    {
        return json_encode(self::normalizePermissions($permissions), JSON_UNESCAPED_UNICODE);
    }

    private static function decodePermissions($raw): array
    {
        if (is_array($raw)) {
            return self::normalizePermissions($raw);
        }

        $raw = trim(strval($raw));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return self::normalizePermissions($decoded);
        }

        if (strpos($raw, ',') !== false) {
            return self::normalizePermissions(array_map('trim', explode(',', $raw)));
        }

        return [$raw];
    }

    public static function businessCanUseArticle(int $aid, int $bid): bool
    {
        if ($aid <= 0 || $bid <= 0 || !self::isAidPluginEnabled($aid, 'article')) {
            return false;
        }

        if (self::isBusinessPluginEnabled($aid, $bid, 'article')) {
            return true;
        }

        if (!self::tableExists('admin_user')) {
            return false;
        }

        try {
            $admin = Db::name('admin_user')
                ->where('aid', $aid)
                ->where('bid', $bid)
                ->where('isadmin', '>', 0)
                ->order('id asc')
                ->field('auth_type,auth_data')
                ->find();
        } catch (\Throwable $e) {
            return false;
        }

        if (empty($admin)) {
            return false;
        }

        $authData = self::decodePermissions($admin['auth_data'] ?? '');
        if (in_array('all', $authData, true)) {
            return true;
        }

        foreach ($authData as $item) {
            if ($item === 'Article/*' || strpos($item, 'Article/') === 0) {
                return true;
            }
        }

        return false;
    }

    private static function isActiveRow($row): bool
    {
        if (empty($row) || intval($row['status'] ?? 0) !== 1) {
            return false;
        }

        $expireTime = intval($row['expire_time'] ?? 0);
        if ($expireTime > 0 && $expireTime < time()) {
            return false;
        }

        return true;
    }

    private static function tableExists(string $tableName): bool
    {
        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        try {
            $prefix = config('database.connections.mysql.prefix');
            $fullTableName = $prefix . $tableName;
            $result = Db::query("SHOW TABLES LIKE '{$fullTableName}'");
            $cache[$tableName] = !empty($result);
        } catch (\Throwable $e) {
            $cache[$tableName] = false;
        }

        return $cache[$tableName];
    }
}
