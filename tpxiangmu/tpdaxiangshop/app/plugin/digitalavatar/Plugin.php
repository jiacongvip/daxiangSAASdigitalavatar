<?php

namespace app\plugin\digitalavatar;

use app\admin\common\PluginMenu;
use app\plugin\digitalavatar\common\DigitalAvatarInstaller;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class Plugin
{
    private const PLUGIN_NAME = 'digitalavatar';
    private const USE_VUE = 1;

    public function info()
    {
        return [
            'name' => self::PLUGIN_NAME,
            'title' => 'AI数字人工作台',
            'version' => '0.7.8',
        ];
    }

    public function install()
    {
        try {
            DigitalAvatarInstaller::ensureInstalled(true);
            $this->syncPluginState($this->info()['version']);
            return ['status' => 1, 'msg' => '安装成功'];
        } catch (\Throwable $e) {
            Log::error('AI数字人插件安装失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '安装失败：' . $e->getMessage()];
        }
    }

    public function uninstall()
    {
        try {
            foreach ($this->getAllAids() as $aid) {
                Db::name('admin_plugin_menu')
                    ->where('aid', intval($aid))
                    ->where('plugin_name', self::PLUGIN_NAME)
                    ->delete();
            }
            Cache::clear();
            return ['status' => 1, 'msg' => '卸载成功，业务数据已保留'];
        } catch (\Throwable $e) {
            Log::error('AI数字人插件卸载失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '卸载失败：' . $e->getMessage()];
        }
    }

    public function enable()
    {
        try {
            DigitalAvatarInstaller::ensureInstalled(true);
            $this->syncPluginState($this->info()['version']);
            return ['status' => 1, 'msg' => '启用成功'];
        } catch (\Throwable $e) {
            Log::error('AI数字人插件启用失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '启用失败：' . $e->getMessage()];
        }
    }

    public function disable()
    {
        Cache::clear();
        return ['status' => 1, 'msg' => '禁用成功'];
    }

    public function upgrade($fromVersion, $toVersion)
    {
        try {
            DigitalAvatarInstaller::ensureInstalled(true);
            $this->syncPluginState($toVersion ?: $this->info()['version']);
            return ['status' => 1, 'msg' => "升级成功！版本：{$fromVersion} → {$toVersion}"];
        } catch (\Throwable $e) {
            Log::error('AI数字人插件升级失败: ' . $e->getMessage());
            return ['status' => 0, 'msg' => '升级失败：' . $e->getMessage()];
        }
    }

    private function syncPluginState(string $version): void
    {
        foreach ($this->getAllAids() as $aid) {
            try {
                PluginMenu::registerFromConfig(self::PLUGIN_NAME, intval($aid));
            } catch (\Throwable $e) {
                Log::error('AI数字人插件注册菜单失败(aid=' . intval($aid) . '): ' . $e->getMessage());
            }
        }

        if ($this->tableExists('admin_plugin_version')) {
            $now = time();
            $exists = Db::name('admin_plugin_version')
                ->where('plugin_name', self::PLUGIN_NAME)
                ->find();
            if ($exists) {
                Db::name('admin_plugin_version')
                    ->where('plugin_name', self::PLUGIN_NAME)
                    ->update([
                        'version' => $version,
                        'use_vue' => self::USE_VUE,
                        'updatetime' => $now,
                    ]);
            } else {
                Db::name('admin_plugin_version')->insert([
                    'plugin_name' => self::PLUGIN_NAME,
                    'version' => $version,
                    'use_vue' => self::USE_VUE,
                    'createtime' => $now,
                    'updatetime' => $now,
                ]);
            }
        }

        Cache::clear();
    }

    private function getAllAids(): array
    {
        $aids = [];
        foreach (['admin_set', 'admin_user', 'admin_plugin_menu'] as $table) {
            try {
                if ($this->tableExists($table)) {
                    $aids = array_merge($aids, Db::name($table)->where('aid', '>=', 0)->column('aid'));
                }
            } catch (\Throwable $e) {
            }
        }
        if (defined('aid')) {
            $aids[] = intval(aid);
        }
        $aids[] = 0;

        return array_values(array_unique(array_filter(array_map('intval', $aids), function ($aid) {
            return $aid >= 0;
        })));
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $prefix = config('database.connections.mysql.prefix', '');
            $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            return $tableName !== '' && !empty(Db::query("SHOW TABLES LIKE '{$prefix}{$tableName}'"));
        } catch (\Throwable $e) {
            return false;
        }
    }
}
