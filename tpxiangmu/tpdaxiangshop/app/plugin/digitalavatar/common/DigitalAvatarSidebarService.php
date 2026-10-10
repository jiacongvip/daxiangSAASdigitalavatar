<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarSidebarService
{
    public static function adminConfig(int $aid): array
    {
        $config = self::load($aid);
        return [
            'chats_enabled' => intval($config['chats_enabled'] ?? 1) === 1 ? 1 : 0,
            'items' => array_values($config['items'] ?? []),
            'targets' => self::targetOptions(),
        ];
    }

    public static function portalConfig(int $aid): array
    {
        $config = self::load($aid);
        $chatsEnabled = intval($config['chats_enabled'] ?? 1) === 1 ? 1 : 0;
        $items = [];
        foreach ($config['items'] as $item) {
            if (intval($item['enabled'] ?? 0) !== 1) {
                continue;
            }
            if (strval($item['target'] ?? '') === 'chats' && $chatsEnabled !== 1) {
                continue;
            }
            $items[] = $item;
        }
        return [
            'chats_enabled' => $chatsEnabled,
            'items' => array_values($items),
        ];
    }

    public static function saveItem(int $aid, array $info): array
    {
        $config = self::load($aid);
        $item = self::normalizeItem($info, false);
        if ($item['name'] === '') {
            throw new \RuntimeException('请填写菜单名称');
        }
        if ($item['target'] === 'link' && $item['link_url'] === '') {
            throw new \RuntimeException('自定义链接请填写跳转地址');
        }

        $items = $config['items'];
        $found = false;
        foreach ($items as $index => $row) {
            if (strval($row['id']) === strval($item['id'])) {
                if ($item['sort'] <= 0) {
                    $item['sort'] = intval($row['sort'] ?? (($index + 1) * 10));
                }
                $items[$index] = $item;
                $found = true;
                break;
            }
        }
        if (!$found) {
            if ($item['id'] === '' || preg_match('/^(workspace|tasks|community|chats)$/', $item['id'])) {
                $item['id'] = 'm' . str_replace('.', '', uniqid('', true));
            }
            $item['builtin'] = 0;
            if ($item['sort'] <= 0) {
                $max = 0;
                foreach ($items as $row) {
                    $max = max($max, intval($row['sort'] ?? 0));
                }
                $item['sort'] = $max + 10;
            }
            $items[] = $item;
        }

        $items = self::uniqueChats($items);
        $config['items'] = $items;
        if ($item['target'] === 'chats') {
            $config['chats_enabled'] = intval($item['enabled']) === 1 ? 1 : 0;
        }
        self::store($aid, $config);
        return self::adminConfig($aid);
    }

    public static function deleteItem(int $aid, string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new \RuntimeException('请传入菜单ID');
        }
        $config = self::load($aid);
        $next = [];
        $removed = null;
        foreach ($config['items'] as $item) {
            if (strval($item['id']) === $id) {
                $removed = $item;
                continue;
            }
            $next[] = $item;
        }
        if ($removed === null) {
            throw new \RuntimeException('菜单不存在');
        }
        $config['items'] = $next;
        if (strval($removed['target'] ?? '') === 'chats') {
            $config['chats_enabled'] = 0;
        }
        self::store($aid, $config);
        return self::adminConfig($aid);
    }

    public static function sortItems(int $aid, array $ids): array
    {
        $config = self::load($aid);
        $map = [];
        foreach ($config['items'] as $item) {
            $map[strval($item['id'])] = $item;
        }
        $sorted = [];
        $used = [];
        $sort = 10;
        foreach ($ids as $id) {
            $key = strval($id);
            if ($key === '' || !isset($map[$key]) || isset($used[$key])) {
                continue;
            }
            $item = $map[$key];
            $item['sort'] = $sort;
            $sorted[] = $item;
            $used[$key] = 1;
            $sort += 10;
        }
        foreach ($config['items'] as $item) {
            $key = strval($item['id']);
            if (isset($used[$key])) {
                continue;
            }
            $item['sort'] = $sort;
            $sorted[] = $item;
            $sort += 10;
        }
        $config['items'] = $sorted;
        self::store($aid, $config);
        return self::adminConfig($aid);
    }

    public static function saveChatsEnabled(int $aid, int $enabled): array
    {
        $config = self::load($aid);
        $on = $enabled === 1 ? 1 : 0;
        $config['chats_enabled'] = $on;
        foreach ($config['items'] as &$item) {
            if (strval($item['target'] ?? '') === 'chats') {
                $item['enabled'] = $on;
            }
        }
        unset($item);
        self::store($aid, $config);
        return self::adminConfig($aid);
    }

    public static function restoreDefaults(int $aid): array
    {
        self::store($aid, self::defaultConfig());
        return self::adminConfig($aid);
    }

    private static function settingTable(): string
    {
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        return str_replace('`', '', $prefix . 'digitalavatar_setting');
    }

    private static function load(int $aid): array
    {
        DigitalAvatarInstaller::ensureSidebarSupport();
        DigitalAvatarCreditService::ensureSettings($aid);
        $raw = '';
        try {
            $rows = Db::query('SELECT `sidebar_json` FROM `' . self::settingTable() . '` WHERE `aid` = ? LIMIT 1', [$aid]);
            $raw = trim(strval($rows[0]['sidebar_json'] ?? ''));
        } catch (\Throwable $e) {
            $raw = '';
        }
        if ($raw === '') {
            $config = self::defaultConfig();
            try {
                self::store($aid, $config);
            } catch (\Throwable $e) {
            }
            return $config;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::defaultConfig();
        }
        return self::normalizeConfig($decoded);
    }

    private static function store(int $aid, array $config): void
    {
        DigitalAvatarInstaller::ensureSidebarSupport();
        $normalized = self::normalizeConfig($config);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            $json = json_encode(self::defaultConfig(), JSON_UNESCAPED_UNICODE);
        }
        if (!is_string($json) || $json === '') {
            throw new \RuntimeException('菜单配置编码失败');
        }
        Db::execute(
            'UPDATE `' . self::settingTable() . '` SET `sidebar_json` = ?, `update_time` = ? WHERE `aid` = ?',
            [$json, time(), $aid]
        );
    }

    private static function normalizeConfig(array $config): array
    {
        $items = [];
        $source = $config['items'] ?? $config['list'] ?? [];
        if (!is_array($source)) {
            $source = [];
        }
        foreach ($source as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = self::normalizeItem($row, true);
            if ($item['id'] === '' || $item['name'] === '') {
                continue;
            }
            $items[] = $item;
        }
        usort($items, function ($a, $b) {
            $sort = intval($a['sort'] ?? 0) <=> intval($b['sort'] ?? 0);
            if ($sort !== 0) {
                return $sort;
            }
            return strcmp(strval($a['id'] ?? ''), strval($b['id'] ?? ''));
        });
        $items = self::uniqueChats($items);
        $chatsEnabled = array_key_exists('chats_enabled', $config)
            ? (intval($config['chats_enabled']) === 1 ? 1 : 0)
            : 1;
        foreach ($items as $item) {
            if (strval($item['target'] ?? '') === 'chats') {
                $chatsEnabled = intval($item['enabled']) === 1 ? 1 : 0;
            }
        }
        return [
            'chats_enabled' => $chatsEnabled,
            'items' => array_values($items),
        ];
    }

    private static function normalizeItem(array $info, bool $keepEmptyName): array
    {
        $id = trim(strval($info['id'] ?? ''));
        $target = self::normalizeTarget(strval($info['target'] ?? 'workspace'));
        $name = mb_substr(trim(strval($info['name'] ?? '')), 0, 20);
        if ($name === '' && !$keepEmptyName) {
            $name = self::targetLabel($target);
        }
        $builtin = intval($info['builtin'] ?? 0) === 1 || in_array($id, ['workspace', 'tasks', 'community', 'chats'], true) ? 1 : 0;
        return [
            'id' => mb_substr($id, 0, 40),
            'name' => $name,
            'target' => $target,
            'link_url' => mb_substr(trim(strval($info['link_url'] ?? '')), 0, 500),
            'icon' => self::sanitizeSvg(strval($info['icon'] ?? '')),
            'icon_on' => self::sanitizeSvg(strval($info['icon_on'] ?? '')),
            'enabled' => intval($info['enabled'] ?? 1) === 1 ? 1 : 0,
            'sort' => max(0, intval($info['sort'] ?? 0)),
            'builtin' => $builtin,
        ];
    }

    private static function uniqueChats(array $items): array
    {
        $seen = false;
        $next = [];
        foreach ($items as $item) {
            if (strval($item['target'] ?? '') !== 'chats') {
                $next[] = $item;
                continue;
            }
            if ($seen) {
                continue;
            }
            $seen = true;
            $next[] = $item;
        }
        return $next;
    }

    public static function sanitizeSvg(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (stripos($raw, '<svg') === false) {
            return '';
        }
        $raw = str_ireplace(['javascript:', 'data:'], '', $raw);
        if (strlen($raw) > 12000) {
            $raw = substr($raw, 0, 12000);
        }
        return $raw;
    }

    public static function defaultConfig(): array
    {
        $icons = self::defaultIcons();
        return [
            'chats_enabled' => 1,
            'items' => [
                self::builtinItem('workspace', '创作', 'workspace', 10, $icons['workspace']),
                self::builtinItem('tasks', '任务', 'tasks', 20, $icons['tasks']),
                self::builtinItem('community', '社区', 'community', 30, $icons['community']),
                self::builtinItem('chats', '对话记录', 'chats', 40, $icons['chats']),
            ],
        ];
    }

    private static function builtinItem(string $id, string $name, string $target, int $sort, string $icon): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'target' => $target,
            'link_url' => '',
            'icon' => $icon,
            'icon_on' => $icon,
            'enabled' => 1,
            'sort' => $sort,
            'builtin' => 1,
        ];
    }

    public static function targetOptions(): array
    {
        return [
            ['value' => 'workspace', 'label' => '创作 / 工作台'],
            ['value' => 'tasks', 'label' => '任务中心'],
            ['value' => 'community', 'label' => '灵感社区'],
            ['value' => 'chats', 'label' => '对话记录列表'],
            ['value' => 'assets', 'label' => '资产管理'],
            ['value' => 'avatars', 'label' => '数字人'],
            ['value' => 'voices', 'label' => '音色'],
            ['value' => 'videos', 'label' => '创意视频'],
            ['value' => 'images', 'label' => '图片'],
            ['value' => 'link', 'label' => '自定义链接'],
        ];
    }

    private static function normalizeTarget(string $target): string
    {
        $target = trim($target);
        $ok = ['workspace', 'tasks', 'community', 'chats', 'assets', 'avatars', 'voices', 'videos', 'images', 'link'];
        return in_array($target, $ok, true) ? $target : 'workspace';
    }

    private static function targetLabel(string $target): string
    {
        foreach (self::targetOptions() as $row) {
            if ($row['value'] === $target) {
                return strval($row['label']);
            }
        }
        return '菜单';
    }

    public static function defaultIcons(): array
    {
        return [
            'workspace' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.2 13.5 8h4.8L15.2 11l1.5 4.8L12 13.6 7.3 15.8 8.8 11 5.7 8h4.8L12 3.2Z" fill="currentColor"/><path d="M5 18.5h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            'tasks' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12M4.5 6h.01M4.5 12h.01M4.5 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            'community' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 15H14M14.817 10.9948L13.846 9.54484L14.88 8.31284C15.158 7.98937 15.3254 7.58548 15.3576 7.16016C15.3898 6.73483 15.2851 6.31036 15.059 5.94871C14.8328 5.58707 14.4969 5.30721 14.1004 5.14999C13.7039 4.99278 13.2675 4.96644 12.855 5.07484L11.035 5.43884L9.91 3.88484C9.68842 3.55559 9.3739 3.2997 9.00645 3.14974C8.639 2.99978 8.23525 2.96253 7.84656 3.04273C7.45788 3.12293 7.10185 3.31696 6.82377 3.60012C6.54569 3.88328 6.35815 4.24277 6.285 4.63284L6.141 6.54984L4.416 6.97584C4.02305 7.09612 3.67669 7.33453 3.42403 7.65863C3.17138 7.98273 3.02469 8.3768 3.00393 8.78722C2.98317 9.19764 3.08934 9.6045 3.308 9.95244C3.52666 10.3004 3.84719 10.5725 4.226 10.7318L4.883 11.0018M18.822 10.9951L21.082 5.61507C21.1336 5.49215 21.1601 5.36014 21.1599 5.22683C21.1598 5.09351 21.133 4.96158 21.0811 4.83878C21.0292 4.71598 20.9532 4.60479 20.8577 4.51177C20.7623 4.41874 20.6491 4.34574 20.525 4.29707L16.954 2.90007C16.713 2.80594 16.4449 2.80888 16.206 2.90827C15.9672 3.00767 15.7761 3.19577 15.673 3.43307L14.749 5.55507M4 12.0059C3.99921 11.8746 4.0243 11.7444 4.07382 11.6227C4.12335 11.5011 4.19635 11.3904 4.28865 11.297C4.38095 11.2036 4.49075 11.1293 4.61177 11.0783C4.7328 11.0273 4.86268 11.0007 4.994 10.9999H19C19.2652 10.9999 19.5196 11.1052 19.7071 11.2928C19.8946 11.4803 20 11.7347 20 11.9999V18.9999C20 19.5303 19.7893 20.039 19.4142 20.4141C19.0391 20.7892 18.5304 20.9999 18 20.9999H6C5.46957 20.9999 4.96086 20.7892 4.58579 20.4141C4.21072 20.039 4 19.5303 4 18.9999V12.0059Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
            'chats' => '<svg width="20" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M17.5 3A4.5 4.5 0 0 1 22 7.5v7a4.5 4.5 0 0 1-4.5 4.5h-5.028a1 1 0 0 0-.542.16l-4.152 2.68A1 1 0 0 1 6.236 21v-2.009A4.5 4.5 0 0 1 2 14.5v-7A4.5 4.5 0 0 1 6.5 3h11Zm-11 2A2.5 2.5 0 0 0 4 7.5v7A2.5 2.5 0 0 0 6.5 17h.736a1 1 0 0 1 1 1v1.163l2.609-1.683a3 3 0 0 1 1.627-.48H17.5a2.5 2.5 0 0 0 2.5-2.5v-7A2.5 2.5 0 0 0 17.5 5h-11Z" fill="currentColor"></path></svg>',
            'assets' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H9l1.2 1.6A1 1 0 0 0 11 7h6.5A2.5 2.5 0 0 1 20 9.5v7A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
            'avatars' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            'voices' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5a3 3 0 0 1 3 3v5a3 3 0 1 1-6 0v-5a3 3 0 0 1 3-3Zm7 8.5a1 1 0 1 1 2 0 9 9 0 0 1-8 8.94V22a1 1 0 1 1-2 0v-1.06A9 9 0 0 1 3 12a1 1 0 1 1 2 0 7 7 0 1 0 14 0Z" fill="currentColor"/></svg>',
            'videos' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15.5 7.2v9.6a2.7 2.7 0 0 1-2.7 2.7H6.7A2.7 2.7 0 0 1 4 16.8V7.2A2.7 2.7 0 0 1 6.7 4.5h6.1a2.7 2.7 0 0 1 2.7 2.7Zm2.1.55 3.08-1.68A1.2 1.2 0 0 1 22.5 7.1v9.8a1.2 1.2 0 0 1-1.82 1.03L17.6 16.25V7.75Z" fill="currentColor"/></svg>',
            'images' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4h14a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Zm3.2 4.2a1.6 1.6 0 1 0 0-3.2 1.6 1.6 0 0 0 0 3.2ZM4.4 16.3l3.9-3.9a1.2 1.2 0 0 1 1.7 0l3.4 3.4 2.1-2.1a1.2 1.2 0 0 1 1.7 0l2.4 2.4V17a1 1 0 0 1-1 1H5.2a.8.8 0 0 1-.8-.7Z" fill="currentColor"/></svg>',
            'link' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 5.93M14 11a5 5 0 0 0-7.07 0L5.52 12.41a5 5 0 0 0 7.07 7.07L14 18.07" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        ];
    }
}
