<?php

namespace app\admin\common;

use think\facade\Db;

class H5ShareMetadata
{
    private const START_MARKER = '<!-- shop-share-meta:start -->';
    private const END_MARKER = '<!-- shop-share-meta:end -->';

    public static function sync(int $aid, array $branding = []): bool
    {
        if ($aid <= 0) {
            return false;
        }

        try {
            if (!array_key_exists('name', $branding)
                || !array_key_exists('logo', $branding)
                || !array_key_exists('desc', $branding)) {
                $stored = Db::name('admin_set')
                    ->where('aid', $aid)
                    ->field('name,logo,`desc`')
                    ->find();
                if (is_array($stored)) {
                    $branding = array_merge($stored, $branding);
                }
            }

            $branding['base_url'] = $branding['base_url'] ?? self::resolveBaseUrl();
            foreach (self::entryPaths($aid) as $entryPath) {
                if (!is_file($entryPath) || !is_readable($entryPath)) {
                    continue;
                }

                $html = file_get_contents($entryPath);
                if (!is_string($html) || $html === '') {
                    continue;
                }

                $updated = self::inject($html, $branding);
                if ($updated === $html) {
                    return true;
                }

                return file_put_contents($entryPath, $updated, LOCK_EX) !== false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    public static function inject(string $html, array $branding): string
    {
        $name = trim((string)($branding['name'] ?? ''));
        $description = \app\admin\common\System::plainText($branding['desc'] ?? '');
        $logo = self::normalizeImageUrl(
            (string)($branding['logo'] ?? ''),
            (string)($branding['base_url'] ?? '')
        );

        $html = preg_replace(
            '/' . preg_quote(self::START_MARKER, '/') . '.*?' . preg_quote(self::END_MARKER, '/') . '/s',
            '',
            $html,
            1
        ) ?? $html;

        if ($name === '' && $description === '' && $logo === '') {
            return $html;
        }

        $titleFound = false;
        if ($name !== '') {
            $safeTitle = self::escape($name);
            $html = preg_replace_callback('/<title\b[^>]*>.*?<\/title>/is', function () use ($safeTitle, &$titleFound) {
                $titleFound = true;
                return '<title>' . $safeTitle . '</title>';
            }, $html, 1) ?? $html;
        }

        $meta = [self::START_MARKER];
        if ($name !== '') {
            $safeName = self::escape($name);
            if (!$titleFound) {
                $meta[] = '<title>' . $safeName . '</title>';
            }
            $meta[] = '<meta property="og:title" content="' . $safeName . '">';
        }
        if ($description !== '') {
            $safeDescription = self::escape($description);
            $meta[] = '<meta name="description" content="' . $safeDescription . '">';
            $meta[] = '<meta property="og:description" content="' . $safeDescription . '">';
        }
        if ($logo !== '') {
            $safeLogo = self::escape($logo);
            $meta[] = '<link rel="icon" href="' . $safeLogo . '">';
            $meta[] = '<link rel="shortcut icon" href="' . $safeLogo . '">';
            $meta[] = '<link rel="apple-touch-icon" href="' . $safeLogo . '">';
            $meta[] = '<meta property="og:image" content="' . $safeLogo . '">';
            $meta[] = '<meta itemprop="image" content="' . $safeLogo . '">';
        }
        $meta[] = '<meta property="og:type" content="website">';
        $meta[] = self::END_MARKER;
        $block = implode('', $meta);

        return preg_replace_callback('/<head\b[^>]*>/i', function ($matches) use ($block) {
            return $matches[0] . $block;
        }, $html, 1) ?? $html;
    }

    private static function entryPaths(int $aid): array
    {
        $paths = [];
        $projectRoot = rtrim(dirname(__DIR__, 3), '/\\');
        if ($projectRoot !== '') {
            $paths[] = $projectRoot . '/public/h5/' . $aid . '.html';
        }
        if (function_exists('root_path')) {
            $rootPath = rtrim((string)root_path(), '/\\');
            if ($rootPath !== '') {
                $paths[] = $rootPath . '/public/h5/' . $aid . '.html';
            }
        }
        if (defined('ROOT_PATH')) {
            $legacyRoot = rtrim((string)ROOT_PATH, '/\\');
            if ($legacyRoot !== '') {
                $paths[] = $legacyRoot . '/h5/' . $aid . '.html';
            }
        }
        return array_values(array_unique($paths));
    }

    private static function normalizeImageUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '' || stripos($url, 'data:') === 0) {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return '';
        }
        if (strpos($url, '//') === 0) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $url;
        }

        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return $url;
        }
        return $baseUrl . '/' . ltrim($url, '/');
    }

    private static function resolveBaseUrl(): string
    {
        if (defined('PRE_URL') && trim((string)PRE_URL) !== '') {
            return rtrim((string)PRE_URL, '/');
        }
        try {
            return rtrim((string)request()->domain(), '/');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
