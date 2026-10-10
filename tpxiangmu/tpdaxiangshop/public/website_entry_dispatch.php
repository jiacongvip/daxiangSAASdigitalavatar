<?php

namespace think;

function codexNormalizeHost(string $host): string
{
    $host = trim($host);
    if ($host === '') {
        return '';
    }

    if (strpos($host, '://') === false) {
        $host = 'http://' . $host;
    }

    $parsed = parse_url($host);
    $normalized = strtolower(trim((string)($parsed['host'] ?? '')));
    return preg_replace('/:\d+$/', '', $normalized) ?: '';
}

function codexReadEnvValue(array $env, string $key, string $default = ''): string
{
    if (array_key_exists($key, $env)) {
        return trim((string)$env[$key]);
    }

    $sectionKey = str_replace('_', '.', strtolower($key));
    if (array_key_exists($sectionKey, $env)) {
        return trim((string)$env[$sectionKey]);
    }

    return $default;
}

function codexFindWebsiteAidByHost(string $host): int
{
    $host = codexNormalizeHost($host);
    if ($host === '') {
        return 0;
    }

    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) {
        return 0;
    }

    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (!is_array($env)) {
        return 0;
    }

    $databaseType = strtolower(codexReadEnvValue($env, 'DATABASE_TYPE', 'mysql'));
    if ($databaseType !== 'mysql') {
        return 0;
    }

    $hostname = codexReadEnvValue($env, 'DATABASE_HOSTNAME', '127.0.0.1');
    $database = codexReadEnvValue($env, 'DATABASE_DATABASE');
    $username = codexReadEnvValue($env, 'DATABASE_USERNAME');
    $password = (string)($env['DATABASE_PASSWORD'] ?? '');
    $hostport = intval(codexReadEnvValue($env, 'DATABASE_HOSTPORT', '3306'));
    $charset = codexReadEnvValue($env, 'DATABASE_CHARSET', 'utf8mb4') ?: 'utf8mb4';
    $prefix = preg_replace('/[^a-zA-Z0-9_]+/', '', codexReadEnvValue($env, 'DATABASE_PREFIX', 'ddwx_'));

    if ($hostname === '' || $database === '' || $username === '') {
        return 0;
    }

    try {
        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $hostname, $hostport, $database, $charset),
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        $stmt = $pdo->prepare(sprintf(
            'SELECT `aid` FROM `%swebsite_site_route` WHERE `custom_domain` = :domain LIMIT 1',
            $prefix
        ));
        $stmt->execute(['domain' => $host]);

        return intval($stmt->fetchColumn() ?: 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

function codexCurrentRequestPath(): string
{
    $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    return $path === '' ? '/' : $path;
}

function codexCurrentRouteParam(): string
{
    return trim((string)($_GET['s'] ?? ''), '/');
}

function codexIsWebsiteRoutePath(string $path): bool
{
    return preg_match('#^/(site/\d+|s/[a-z0-9-]+(?:/|$)|website(?:/|$)|sitemap(?:\.xml)?$|robots(?:\.txt)?$)#i', $path) === 1;
}

function codexIsWebsiteRouteParam(string $route): bool
{
    return preg_match('#^(site/\d+|s/[a-z0-9-]+(?:/|$)|website(?:/|$)|sitemap(?:\.xml)?$|robots(?:\.txt)?$)#i', $route) === 1;
}

function codexIsExplicitAdminPath(string $path): bool
{
    return preg_match('#^/(admin(?:api)?\.php|business\.php|Backstage(?:/|$)|admin(?:/|$)|console(?:/|$))#i', $path) === 1;
}

function codexIsExplicitAdminRouteParam(string $route): bool
{
    return preg_match('#^(admin(?:api)?/|business/|Backstage(?:/|$)|login(?:/|$)|console(?:/|$))#i', $route) === 1;
}

function codexRequestHostMatchesWebsite(): bool
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    return codexFindWebsiteAidByHost($host) > 0;
}

function codexResolvePublicAppName(): string
{
    $path = codexCurrentRequestPath();
    $route = codexCurrentRouteParam();

    if (codexIsExplicitAdminPath($path) || codexIsExplicitAdminRouteParam($route)) {
        return 'admin';
    }

    if (codexIsWebsiteRoutePath($path) || codexIsWebsiteRouteParam($route)) {
        return 'website';
    }

    return codexRequestHostMatchesWebsite() ? 'website' : 'admin';
}

function codexResolveAdminEntryAppName(): string
{
    $path = codexCurrentRequestPath();
    $route = codexCurrentRouteParam();

    if (codexIsExplicitAdminRouteParam($route)) {
        return 'admin';
    }

    if (codexIsWebsiteRouteParam($route)) {
        return 'website';
    }

    if (codexIsExplicitAdminPath($path)) {
        return 'admin';
    }

    if (codexIsWebsiteRoutePath($path)) {
        return 'website';
    }

    return codexRequestHostMatchesWebsite() ? 'website' : 'admin';
}

function codexFindDigitalAvatarAidByHost(string $host): int
{
    $host = codexNormalizeHost($host);
    if ($host === '') {
        return 0;
    }

    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) {
        return 0;
    }

    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (!is_array($env)) {
        return 0;
    }

    $databaseType = strtolower(codexReadEnvValue($env, 'DATABASE_TYPE', 'mysql'));
    if ($databaseType !== 'mysql') {
        return 0;
    }

    $hostname = codexReadEnvValue($env, 'DATABASE_HOSTNAME', '127.0.0.1');
    $database = codexReadEnvValue($env, 'DATABASE_DATABASE');
    $username = codexReadEnvValue($env, 'DATABASE_USERNAME');
    $password = (string)($env['DATABASE_PASSWORD'] ?? '');
    $hostport = intval(codexReadEnvValue($env, 'DATABASE_HOSTPORT', '3306'));
    $charset = codexReadEnvValue($env, 'DATABASE_CHARSET', 'utf8mb4') ?: 'utf8mb4';
    $prefix = preg_replace('/[^a-zA-Z0-9_]+/', '', codexReadEnvValue($env, 'DATABASE_PREFIX', 'ddwx_'));

    if ($hostname === '' || $database === '' || $username === '') {
        return 0;
    }

    try {
        $pdo = new \PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $hostname, $hostport, $database, $charset),
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        $stmt = $pdo->prepare(sprintf(
            'SELECT `aid` FROM `%sdigitalavatar_setting` WHERE `portal_domain` = :domain LIMIT 1',
            $prefix
        ));
        $stmt->execute(['domain' => $host]);

        return intval($stmt->fetchColumn() ?: 0);
    } catch (\Throwable $e) {
        return 0;
    }
}

function codexServeDigitalAvatarPortal(): void
{
    $wantsHost = isset($_GET['da_portal_host']);
    if (!$wantsHost) {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return;
        }
        $route = trim(codexCurrentRouteParam(), '/');
        if ($route !== '' && $route !== 'index') {
            return;
        }
        $path = rtrim(codexCurrentRequestPath(), '/');
        if ($path === '') {
            $path = '/';
        }
        if (!in_array($path, ['/', '/index.php', '/index.html'], true)) {
            return;
        }
    }

    $hostAid = codexFindDigitalAvatarAidByHost((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($wantsHost) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['status' => 1, 'aid' => $hostAid], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($hostAid <= 0) {
        return;
    }

    $file = __DIR__ . '/portal/digitalavatar/index.html';
    if (!is_file($file)) {
        return;
    }

    $html = file_get_contents($file);
    if (!is_string($html) || $html === '') {
        return;
    }

    $hostAid = intval($hostAid);
    $inject = '<script>(function(){try{var k="digitalavatar:portal-auth",p={};try{p=JSON.parse(localStorage.getItem(k)||"{}")||{}}catch(e){}if(Number(p.aid)!==' . $hostAid . '){p.sessionId="";}p.aid=' . $hostAid . ';if(p.bid==null)p.bid=0;localStorage.setItem(k,JSON.stringify(p));}catch(e){}})();</script>';
    $count = 0;
    $html = preg_replace('/<head>/i', '<head>' . $inject, $html, 1, $count);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $html;
    exit;
}
