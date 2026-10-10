<?php

namespace app\admin\common;

class PluginHexiaoRegistry
{
    /**
     * @return array<int, array{type:string,name:string,plugin:string,tables:array}>
     */
    public static function all(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = [];
        $pluginRoot = rtrim(app()->getRootPath(), '/\\') . '/app/plugin';
        if (!is_dir($pluginRoot)) {
            return $cached;
        }

        $dirs = glob($pluginRoot . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $file = $dir . '/hexiao.php';
            if (!is_file($file)) {
                continue;
            }
            $data = include $file;
            if (!is_array($data)) {
                continue;
            }
            $pluginName = basename($dir);
            foreach (($data['types'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $type = trim((string)($item['type'] ?? ''));
                $name = trim((string)($item['name'] ?? ''));
                if ($type === '' || $name === '') {
                    continue;
                }
                $tables = [];
                foreach (($item['tables'] ?? []) as $table) {
                    $table = trim(strval($table));
                    if ($table !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                        $tables[] = $table;
                    }
                }
                $cached[] = [
                    'type' => $type,
                    'name' => $name,
                    'plugin' => trim((string)($item['plugin'] ?? $pluginName)),
                    'tables' => $tables,
                ];
            }
        }

        return $cached;
    }

    /**
     * @return array<string, string>
     */
    public static function getTypeNameMap(): array
    {
        $map = [];
        foreach (self::all() as $item) {
            $map[$item['type']] = $item['name'];
        }
        return $map;
    }

    /**
     * @return array{theater_plugin_ready:int, hexiao_plugin_ready:array<string,int>}
     */
    public static function getReadyPayload(int $aid): array
    {
        $map = self::getReadyMap($aid);
        return [
            'theater_plugin_ready' => intval($map['theater'] ?? 0),
            'hexiao_plugin_ready' => $map,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function getReadyMap(int $aid): array
    {
        $map = [];
        foreach (self::all() as $item) {
            $ready = PluginBusinessAuth::isAidPluginReady($aid, $item['plugin'], $item['tables']) ? 1 : 0;
            $map[$item['type']] = $ready;
        }
        return $map;
    }

    /**
     * @param array<int|string, mixed> $permissions
     * @return array<int, string>
     */
    public static function sanitizeAuthData(int $aid, array $permissions): array
    {
        $ready = self::getReadyMap($aid);
        $pluginTypes = array_keys($ready);
        $result = [];
        foreach ($permissions as $permission) {
            $value = strval($permission);
            if ($value === '') {
                continue;
            }
            if (in_array($value, $pluginTypes, true) && empty($ready[$value])) {
                continue;
            }
            $result[] = $value;
        }
        return array_values(array_unique($result));
    }
}
