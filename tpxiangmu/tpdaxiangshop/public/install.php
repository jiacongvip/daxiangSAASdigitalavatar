<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

const INSTALLER_VERSION = '0.3.3';
const INSTALLER_DEFAULT_AUTH_SERVER = 'http://127.0.0.1';
const INSTALLER_DEFAULT_PRODUCT_CODE = 'tpshop';
const INSTALLER_DEFAULT_PRODUCT_NAME = '天天善商城';
const INSTALLER_DEFAULT_PACKAGE_ID = '';
const INSTALLER_DEFAULT_PACKAGE_VERSION = '';
const INSTALLER_DEFAULT_CHANNEL = 'stable';
const INSTALLER_DEFAULT_PUBLIC_KEY = '';
const INSTALLER_DEFAULT_PACKAGE_SECRET = '';
const INSTALLER_MIN_PHP = '7.2.0';

function installer_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function installer_starts_with($haystack, $needle): bool
{
    return strncmp((string)$haystack, (string)$needle, strlen((string)$needle)) === 0;
}

function installer_ends_with($haystack, $needle): bool
{
    $needle = (string)$needle;
    if ($needle === '') {
        return true;
    }
    return substr((string)$haystack, -strlen($needle)) === $needle;
}

function installer_random_hex($bytes): string
{
    try {
        return bin2hex(random_bytes((int)$bytes));
    } catch (Throwable $e) {
        return substr(md5(uniqid('', true) . mt_rand()), 0, $bytes * 2);
    }
}

function installer_json(array $payload, int $httpStatus = 200): void
{
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function installer_success(array $data = [], string $msg = 'success'): void
{
    installer_json(['code' => 200, 'msg' => $msg, 'data' => $data]);
}

function installer_fail(string $msg, string $reason = 'install_failed', int $code = 500, array $data = []): void
{
    installer_json([
        'code' => $code,
        'msg' => $msg,
        'data' => array_merge(['reason' => $reason], $data),
    ], 200);
}

function installer_request_data(): array
{
    $payload = [];
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    if (!empty($_POST)) {
        $payload = array_merge($payload, $_POST);
    }
    return $payload;
}

function installer_get_state(): array
{
    $publicRoot = __DIR__;
    $targetRoot = basename($publicRoot) === 'public' ? dirname($publicRoot) : $publicRoot;
    $tmpRoot = $targetRoot . '/.install-tmp';

    return [
        'public_root' => $publicRoot,
        'target_root' => $targetRoot,
        'tmp_root' => $tmpRoot,
        'runtime_path' => $targetRoot . '/runtime',
        'env_path' => $targetRoot . '/.env',
        'lock_path' => $targetRoot . '/runtime/install.lock',
        'license_cache_path' => $targetRoot . '/runtime/license/license.cache',
        'package_config_path' => $targetRoot . '/config/package.php',
    ];
}

function installer_mkdir($dir): bool
{
    if (is_dir($dir)) {
        return is_writable($dir);
    }
    return @mkdir($dir, 0777, true) || is_dir($dir);
}

function installer_path_inside($root, $path): bool
{
    $root = rtrim(str_replace('\\', '/', (string)$root), '/') . '/';
    $path = str_replace('\\', '/', (string)$path);
    return strpos($path, $root) === 0;
}

function installer_resolve_local_package_path(array $state, $value): string
{
    $raw = trim(str_replace('\\', '/', (string)$value));
    if ($raw === '') {
        return '';
    }
    if (strpos($raw, "\0") !== false || preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)) {
        throw new RuntimeException('本地安装包路径不合法');
    }

    $targetRoot = realpath((string)$state['target_root']) ?: (string)$state['target_root'];
    $path = installer_starts_with($raw, '/') ? $raw : rtrim((string)$state['target_root'], '/\\') . '/' . ltrim($raw, '/');
    $realPath = realpath($path);
    if (!$realPath || !is_file($realPath)) {
        throw new RuntimeException('本地安装包不存在：' . $raw);
    }
    if (!installer_path_inside($targetRoot, $realPath)) {
        throw new RuntimeException('本地安装包必须放在项目目录内：' . $raw);
    }
    if (!preg_match('/\.zip$/i', $realPath)) {
        throw new RuntimeException('本地安装包必须是 zip 文件：' . $raw);
    }

    return $realPath;
}

function installer_write_file($path, $content): void
{
    $dir = dirname($path);
    if (!installer_mkdir($dir)) {
        throw new RuntimeException('目录不可写：' . $dir);
    }
    if (@file_put_contents($path, $content) === false) {
        throw new RuntimeException('写入文件失败：' . $path);
    }
}

function installer_parse_env_file($path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $env = [];
    foreach ($lines as $index => $line) {
        if ($index === 0) {
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
        }
        if (trim($line) === '' || preg_match('/^\s*[#;]/', $line)) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if ($value !== '' && strlen($value) >= 2) {
            $first = $value[0];
            $last = substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === '\'' && $last === '\'')) {
                $value = substr($value, 1, -1);
                $value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
            }
        }
        if ($key !== '') {
            $env[$key] = $value;
        }
    }

    return $env;
}

function installer_default_env(): array
{
    return [
        'APP_DEBUG' => 'false',
        'DEFAULT_TIMEZONE' => 'Asia/Shanghai',
        'DATABASE_TYPE' => 'mysql',
        'DATABASE_HOSTNAME' => '127.0.0.1',
        'DATABASE_DATABASE' => 'tpdaxiangshop',
        'DATABASE_USERNAME' => 'root',
        'DATABASE_PASSWORD' => '',
        'DATABASE_HOSTPORT' => '3306',
        'DATABASE_CHARSET' => 'utf8mb4',
        'DATABASE_DEBUG' => 'false',
        'DATABASE_PREFIX' => 'ddwx_',
        'REDIS_HOST' => '127.0.0.1',
        'REDIS_PORT' => '6379',
        'REDIS_PASSWORD' => '',
        'MONGODB_TYPE' => 'mongo',
        'MONGODB_HOSTNAME' => '127.0.0.1',
        'MONGODB_DATABASE' => '',
        'MONGODB_USERNAME' => '',
        'MONGODB_PASSWORD' => '',
        'MONGODB_HOSTPORT' => '27017',
        'MONGODB_CHARSET' => 'utf8',
        'MONGODB_DEBUG' => 'false',
        'AES_KEY' => installer_random_hex(8),
        'LANG_DEFAULT_LANG' => 'zh-cn',
        'JWT_SECRET' => installer_random_hex(16),
    ];
}

function installer_build_env_defaults(array $state): array
{
    $env = installer_default_env();
    foreach ([$state['target_root'] . '/DATABASE.env.example', $state['env_path']] as $path) {
        foreach (installer_parse_env_file($path) as $key => $value) {
            $env[$key] = $value;
        }
    }
    if (empty($env['AES_KEY'])) {
        $env['AES_KEY'] = installer_random_hex(8);
    }
    if (empty($env['JWT_SECRET'])) {
        $env['JWT_SECRET'] = installer_random_hex(16);
    }
    return $env;
}

function installer_normalize_prefix($prefix): string
{
    $prefix = trim((string)$prefix);
    if ($prefix === '') {
        return 'ddwx_';
    }
    if (substr($prefix, -1) !== '_') {
        $prefix .= '_';
    }
    return $prefix;
}

function installer_validate_identifier($value, $label): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$value)) {
        throw new RuntimeException($label . '只能包含字母、数字和下划线');
    }
}

function installer_create_pdo($host, $port, $username, $password, $database = ''): PDO
{
    $dsn = 'mysql:host=' . $host . ';port=' . intval($port) . ';charset=utf8mb4';
    if ($database !== '') {
        $dsn .= ';dbname=' . $database;
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
        $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
    }
    return new PDO($dsn, $username, $password, $options);
}

function installer_table_exists(PDO $pdo, $tableName): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function installer_database_has_core_tables(array $env): bool
{
    $host = trim((string)($env['DATABASE_HOSTNAME'] ?? ''));
    $database = trim((string)($env['DATABASE_DATABASE'] ?? ''));
    $username = trim((string)($env['DATABASE_USERNAME'] ?? ''));
    if ($host === '' || $database === '' || $username === '') {
        return false;
    }

    try {
        $pdo = installer_create_pdo(
            $host,
            (string)($env['DATABASE_HOSTPORT'] ?? '3306'),
            $username,
            (string)($env['DATABASE_PASSWORD'] ?? ''),
            $database
        );
        $prefix = installer_normalize_prefix((string)($env['DATABASE_PREFIX'] ?? 'ddwx_'));
        return installer_table_exists($pdo, $prefix . 'admin_user')
            && installer_table_exists($pdo, $prefix . 'admin_set')
            && installer_table_exists($pdo, $prefix . 'sysset');
    } catch (Throwable $e) {
        return false;
    }
}

function installer_existing_install_info(array $state, array $env): array
{
    if (is_file($state['lock_path'])) {
        $raw = @file_get_contents($state['lock_path']);
        $decoded = json_decode((string)$raw, true);
        return [
            'installed' => true,
            'source' => 'lock',
            'data' => is_array($decoded) ? $decoded : [],
            'message' => '已检测到安装锁文件，当前站点已经安装完成。',
        ];
    }

    if (is_file($state['env_path']) && installer_database_has_core_tables($env)) {
        return [
            'installed' => true,
            'source' => 'database',
            'data' => [],
            'message' => '已检测到 .env 和核心数据表，请不要重复安装。',
        ];
    }

    return ['installed' => false, 'source' => '', 'data' => [], 'message' => ''];
}

function installer_get_scheme(): string
{
    if (
        (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && $_SERVER['HTTP_X_CLIENT_SCHEME'] === 'https')
    ) {
        return 'https';
    }
    return 'http';
}

function installer_base_url(): string
{
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
    return installer_get_scheme() . '://' . $host;
}

function installer_detect_domain(): string
{
    $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? (string)$_SERVER['SERVER_NAME'] : '');
    $host = preg_replace('/:\d+$/', '', $host);
    return strtolower(trim((string)$host));
}

function installer_detect_machine_fingerprint(array $state): string
{
    $items = [
        php_uname('n'),
        installer_detect_domain(),
        realpath($state['target_root']) ?: $state['target_root'],
        isset($_SERVER['SERVER_ADDR']) ? (string)$_SERVER['SERVER_ADDR'] : '',
    ];
    return hash('sha256', implode('|', $items));
}

function installer_requirement_checks(array $state): array
{
    return [
        [
            'label' => 'PHP 版本 >= ' . INSTALLER_MIN_PHP,
            'ok' => version_compare(PHP_VERSION, INSTALLER_MIN_PHP, '>='),
            'detail' => PHP_VERSION,
        ],
        [
            'label' => 'PDO 扩展',
            'ok' => extension_loaded('pdo'),
            'detail' => extension_loaded('pdo') ? '已开启' : '未开启',
        ],
        [
            'label' => 'pdo_mysql 扩展',
            'ok' => extension_loaded('pdo_mysql'),
            'detail' => extension_loaded('pdo_mysql') ? '已开启' : '未开启',
        ],
        [
            'label' => '站点根目录可写',
            'ok' => installer_mkdir($state['target_root']) && is_writable($state['target_root']),
            'detail' => $state['target_root'],
        ],
        [
            'label' => '临时目录可写',
            'ok' => installer_mkdir($state['tmp_root']) && is_writable($state['tmp_root']),
            'detail' => $state['tmp_root'],
        ],
    ];
}

function installer_assert_requirements(array $state): void
{
    foreach (installer_requirement_checks($state) as $check) {
        if (!$check['ok']) {
            throw new RuntimeException('环境检测失败：' . $check['label'] . '（' . $check['detail'] . '）');
        }
    }
}

function installer_ksort_recursive(array &$data): void
{
    ksort($data);
    foreach ($data as &$value) {
        if (is_array($value)) {
            installer_ksort_recursive($value);
        }
    }
    unset($value);
}

function installer_canonical_json(array $data): string
{
    installer_ksort_recursive($data);
    return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function installer_build_signature(array $payload, $secret): string
{
    $secret = trim((string)$secret);
    if ($secret === '') {
        return '';
    }
    return base64_encode(hash_hmac('sha256', installer_canonical_json($payload), $secret, true));
}

function installer_license_endpoint($server, $action): string
{
    $server = rtrim((string)$server, '/');
    $action = trim((string)$action, '/');
    if ($server === '') {
        throw new RuntimeException('授权中心地址不能为空');
    }
    if (preg_match('/\.php(?:\?.*)?$/i', $server)) {
        return $server . (strpos($server, '?') === false ? '?' : '&') . 'action=' . rawurlencode($action);
    }
    return $server . '/licensecenter.php?action=' . rawurlencode($action);
}

function installer_http_post_json($url, array $payload, int $timeout = 20): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        throw new RuntimeException('授权请求编码失败');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
        ]);
        $responseBody = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($responseBody === false) {
            throw new RuntimeException('授权中心连接失败：' . $error);
        }
        return [
            'http_code' => $httpCode,
            'body' => (string)$responseBody,
            'json' => json_decode((string)$responseBody, true),
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => $timeout,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    if ($responseBody === false) {
        throw new RuntimeException('授权中心连接失败');
    }
    return [
        'http_code' => 0,
        'body' => (string)$responseBody,
        'json' => json_decode((string)$responseBody, true),
    ];
}

function installer_call_license_center(array $job, string $action, array $extra = [], int $timeout = 20): array
{
    $payload = [
        'license_key' => (string)$job['license_key'],
        'product_code' => (string)$job['product_code'],
        'package_id' => (string)$job['package_id'],
        'package_version' => (string)$job['package_version'],
        'domain' => installer_detect_domain(),
        'site_url' => installer_base_url(),
        'server_ip_detected' => isset($_SERVER['SERVER_ADDR']) ? (string)$_SERVER['SERVER_ADDR'] : '',
        'machine_fingerprint' => (string)$job['machine_fingerprint'],
        'php_version' => PHP_VERSION,
        'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? (string)$_SERVER['SERVER_SOFTWARE'] : '',
        'database_prefix' => (string)$job['db_prefix'],
        'timestamp' => time(),
        'nonce' => installer_random_hex(16),
    ];
    $payload = array_merge($payload, $extra);

    $signature = installer_build_signature($payload, (string)($job['package_secret'] ?? ''));
    if ($signature !== '') {
        $payload['signature'] = $signature;
    }

    $endpoint = installer_license_endpoint((string)$job['auth_server'], $action);
    $response = installer_http_post_json($endpoint, $payload, $timeout);
    $json = is_array($response['json']) ? $response['json'] : [];
    if (intval($json['code'] ?? 0) !== 200) {
        throw new RuntimeException((string)($json['msg'] ?? ('授权中心接口调用失败：' . $action)));
    }
    return is_array($json['data'] ?? null) ? $json['data'] : [];
}

function installer_decode_ticket($ticket): array
{
    $json = base64_decode((string)$ticket, true);
    if ($json === false || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function installer_verify_license_ticket($ticket, $signature, $publicKey): bool
{
    $publicKey = trim((string)$publicKey);
    if ($publicKey === '' || !function_exists('openssl_verify')) {
        return true;
    }
    $ticketJson = base64_decode((string)$ticket, true);
    $signatureRaw = base64_decode((string)$signature, true);
    if ($ticketJson === false || $signatureRaw === false) {
        return false;
    }
    return openssl_verify($ticketJson, $signatureRaw, $publicKey, OPENSSL_ALGO_SHA256) === 1;
}

function installer_mask_license_key($licenseKey): string
{
    $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$licenseKey));
    if ($normalized === '') {
        return '';
    }
    return substr($normalized, 0, 4) . '****' . substr($normalized, -4);
}

function installer_job_path(array $state, string $jobId): string
{
    if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
        throw new RuntimeException('安装任务无效');
    }
    return $state['tmp_root'] . '/job-' . $jobId . '.json';
}

function installer_save_job(array $state, array $job): void
{
    $job['updated_at'] = date('c');
    installer_write_file(
        installer_job_path($state, (string)$job['job_id']),
        json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

function installer_load_job(array $state, string $jobId): array
{
    $path = installer_job_path($state, $jobId);
    if (!is_file($path)) {
        throw new RuntimeException('安装任务不存在或已过期');
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('安装任务状态损坏');
    }
    $decoded['job_path'] = $path;
    return $decoded;
}

function installer_validate_form(array $form): array
{
    $errors = [];
    if (trim((string)($form['auth_server'] ?? '')) === '') {
        $errors[] = '授权中心地址不能为空';
    } elseif (!preg_match('#^https?://#i', trim((string)$form['auth_server']))) {
        $errors[] = '授权中心地址必须以 http:// 或 https:// 开头';
    }
    if (trim((string)($form['license_key'] ?? '')) === '') {
        $errors[] = '授权码不能为空';
    }
    if (trim((string)($form['site_name'] ?? '')) === '') {
        $errors[] = '站点名称不能为空';
    }
    if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', (string)($form['admin_username'] ?? ''))) {
        $errors[] = '管理员账号格式不正确，需为 4-20 位字母、数字或下划线';
    }
    if (!preg_match('/^[\S]{6,30}$/', (string)($form['admin_password'] ?? ''))) {
        $errors[] = '管理员密码格式不正确，需为 6-30 位且不能包含空格';
    }
    if ((string)($form['admin_password'] ?? '') !== (string)($form['admin_password_confirm'] ?? '')) {
        $errors[] = '两次输入的管理员密码不一致';
    }
    if (trim((string)($form['db_host'] ?? '')) === '' || trim((string)($form['db_user'] ?? '')) === '' || trim((string)($form['db_name'] ?? '')) === '') {
        $errors[] = '数据库连接信息不能为空';
    }
    if (!preg_match('/^\d{1,5}$/', (string)($form['db_port'] ?? '')) || intval($form['db_port']) <= 0 || intval($form['db_port']) > 65535) {
        $errors[] = '数据库端口不正确';
    }
    if (!preg_match('/^\d{1,5}$/', (string)($form['redis_port'] ?? '')) || intval($form['redis_port']) <= 0 || intval($form['redis_port']) > 65535) {
        $errors[] = 'Redis 端口不正确';
    }
    try {
        installer_validate_identifier((string)($form['db_name'] ?? ''), '数据库名');
        installer_validate_identifier(installer_normalize_prefix((string)($form['db_prefix'] ?? 'ddwx_')), '数据表前缀');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
    return $errors;
}

function installer_download_file($url, $dest, int $timeout = 1800): array
{
    $url = trim((string)$url);
    if ($url === '') {
        throw new RuntimeException('下载地址为空');
    }
    if (!installer_mkdir(dirname($dest))) {
        throw new RuntimeException('下载目录不可写：' . dirname($dest));
    }
    $tmp = $dest . '.downloading';
    $fp = fopen($tmp, 'wb');
    if (!$fp) {
        throw new RuntimeException('无法创建下载文件：' . $tmp);
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        fclose($fp);
        if (!$ok || ($httpCode >= 400 && $httpCode !== 0)) {
            @unlink($tmp);
            throw new RuntimeException('安装包下载失败：' . ($error ?: ('HTTP ' . $httpCode)));
        }
    } else {
        $source = @fopen($url, 'rb');
        if (!$source) {
            fclose($fp);
            @unlink($tmp);
            throw new RuntimeException('安装包下载失败，无法打开远程地址');
        }
        while (!feof($source)) {
            fwrite($fp, fread($source, 1024 * 256));
        }
        fclose($source);
        fclose($fp);
    }

    if (!@rename($tmp, $dest)) {
        @unlink($dest);
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new RuntimeException('保存安装包失败：' . $dest);
        }
    }

    $htmlError = installer_detect_html_file($dest);
    if ($htmlError !== '') {
        @unlink($dest);
        throw new RuntimeException('安装包下载失败：授权中心返回了 HTML 错误页：' . $htmlError);
    }

    return [
        'path' => $dest,
        'size' => is_file($dest) ? filesize($dest) : 0,
        'sha256' => is_file($dest) ? hash_file('sha256', $dest) : '',
    ];
}

function installer_detect_html_file($path): string
{
    if (!is_file($path)) {
        return '';
    }
    $head = (string)@file_get_contents($path, false, null, 0, 2048);
    $trimmed = ltrim($head);
    if (!preg_match('/^<(?:!doctype\s+html|html|head|body)\b/i', $trimmed)) {
        return '';
    }
    $text = trim((string)preg_replace('/\s+/', ' ', strip_tags($head)));
    return $text !== '' ? mb_substr($text, 0, 180, 'UTF-8') : '空 HTML 响应';
}

function installer_rrmdir($dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

function installer_normalize_zip_name($name): string
{
    $name = str_replace('\\', '/', (string)$name);
    $name = preg_replace('#/+#', '/', $name);
    $name = preg_replace('#^\./#', '', $name);
    $name = ltrim($name, '/');
    return trim((string)$name);
}

function installer_common_zip_root(array $names): string
{
    $root = '';
    foreach ($names as $name) {
        $name = trim($name, '/');
        if ($name === '' || strpos($name, '/') === false) {
            return '';
        }
        $segment = substr($name, 0, strpos($name, '/') + 1);
        if ($root === '') {
            $root = $segment;
        } elseif ($root !== $segment) {
            return '';
        }
    }
    return $root;
}

function installer_should_skip_extract_path(string $path, array $options): bool
{
    $lower = strtolower(trim($path, '/'));
    if ($lower === '') {
        return true;
    }
    if (strpos($lower, '../') !== false || installer_starts_with($lower, '../') || installer_ends_with($lower, '/..')) {
        return true;
    }
    if (!empty($options['skip_meta']) && in_array($lower, ['release-manifest.json', 'manifest.json', 'signature'], true)) {
        return true;
    }
    if (!empty($options['skip_app_runtime'])) {
        if ($lower === '.env' || installer_starts_with($lower, '.git/') || installer_starts_with($lower, '.install-tmp/')) {
            return true;
        }
        if (installer_starts_with($lower, 'runtime/') || $lower === 'runtime') {
            return true;
        }
        if ($lower === 'public/install.php' || $lower === 'install.php') {
            return true;
        }
        if (installer_ends_with($lower, '/.ds_store') || $lower === '.ds_store') {
            return true;
        }
    }
    return false;
}

function installer_extract_zip_to($zipFile, $targetRoot, array $options = []): array
{
    $options = array_merge([
        'strip_common_root' => true,
        'skip_app_runtime' => true,
        'skip_meta' => true,
    ], $options);

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new RuntimeException('安装包不是有效 zip 文件：' . $zipFile);
    }

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = installer_normalize_zip_name((string)($stat['name'] ?? ''));
        if ($name === '' || installer_ends_with($name, '/')) {
            continue;
        }
        $names[] = $name;
    }
    $commonRoot = !empty($options['strip_common_root']) ? installer_common_zip_root($names) : '';

    $written = 0;
    $skipped = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $rawName = (string)($stat['name'] ?? '');
        $name = installer_normalize_zip_name($rawName);
        if ($name === '') {
            $skipped++;
            continue;
        }
        if ($commonRoot !== '' && installer_starts_with($name, $commonRoot)) {
            $name = substr($name, strlen($commonRoot));
        }
        $name = installer_normalize_zip_name($name);
        if (installer_should_skip_extract_path($name, $options)) {
            $skipped++;
            continue;
        }

        $target = rtrim($targetRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        $targetDir = dirname($target);
        if (!installer_mkdir($targetDir)) {
            $zip->close();
            throw new RuntimeException('目录不可写：' . $targetDir);
        }

        if (installer_ends_with($rawName, '/')) {
            installer_mkdir($target);
            continue;
        }

        $source = $zip->getStream($rawName);
        if (!$source) {
            $zip->close();
            throw new RuntimeException('读取 zip 文件失败：' . $rawName);
        }
        $dest = fopen($target, 'wb');
        if (!$dest) {
            fclose($source);
            $zip->close();
            throw new RuntimeException('写入目标文件失败：' . $target);
        }
        while (!feof($source)) {
            fwrite($dest, fread($source, 1024 * 256));
        }
        fclose($source);
        fclose($dest);
        $written++;
    }

    $zip->close();
    return ['written' => $written, 'skipped' => $skipped, 'common_root' => $commonRoot];
}

function installer_find_file_by_suffix($root, array $suffixes): string
{
    if (!is_dir($root)) {
        return '';
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = substr($file->getPathname(), strlen(rtrim($root, '/\\')) + 1);
        $relative = str_replace('\\', '/', $relative);
        $lower = strtolower($relative);
        foreach ($suffixes as $suffix) {
            if (installer_ends_with($lower, strtolower((string)$suffix))) {
                return $file->getPathname();
            }
        }
    }
    return '';
}

function installer_resolve_install_sql_file(array $state): string
{
    $root = rtrim((string)$state['target_root'], '/\\');
    $candidates = [
        $root . '/database/install.sql',
        $root . '/database/install/tpdaxiangshop.sql',
        $root . '/database/install/tpshop.sql',
        $root . '/install.sql',
        $root . '/tpdaxiangshop.sql',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $installDir = $root . '/database/install';
    if (is_dir($installDir)) {
        $files = glob($installDir . '/*.sql');
        if (is_array($files) && !empty($files)) {
            sort($files, SORT_STRING);
            return (string)$files[0];
        }
    }

    return installer_find_file_by_suffix($root, [
        'database/install/tpdaxiangshop.sql',
        'database/install/tpshop.sql',
        'database/install.sql',
        'install.sql',
        'tpdaxiangshop.sql',
    ]);
}

function installer_read_json_file($path): array
{
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function installer_count_prefixed_tables(PDO $pdo, $databaseName, $prefix): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?');
    $stmt->execute([$databaseName, $prefix . '%']);
    return intval($stmt->fetchColumn());
}

function installer_should_skip_dump_statement(string $statement): bool
{
    $normalized = strtoupper(trim($statement));
    if ($normalized === '') {
        return true;
    }

    // mysqldump session backup/restore statements are optional during web installation.
    return preg_match('/^(?:\/\*!\d+\s+)?SET\s+(?:@OLD_TIME_ZONE\s*=|TIME_ZONE\s*=)/', $normalized) === 1
        || preg_match('/^SET\s+@OLD_[A-Z0-9_]+\s*=/', $normalized) === 1
        || preg_match('/^SET\s+(?:SQL_MODE|SQL_NOTES|CHARACTER_SET_CLIENT|CHARACTER_SET_RESULTS|COLLATION_CONNECTION)\s*=\s*@OLD_/', $normalized) === 1;
}

function installer_normalize_mysql_datetime_defaults(string $statement, int &$adjustments): string
{
    $head = strtoupper(ltrim($statement));
    if (!installer_starts_with($head, 'CREATE TABLE') && !installer_starts_with($head, 'ALTER TABLE')) {
        return $statement;
    }

    $lines = preg_split("/(\r\n|\n|\r)/", $statement);
    if (!is_array($lines)) {
        return $statement;
    }

    foreach ($lines as &$line) {
        if (preg_match('/^\s*`[^`]+`\s+(?:datetime|timestamp|date)\b/i', $line) !== 1) {
            continue;
        }
        if (
            preg_match('/\bDEFAULT\s+(?:CURRENT_TIMESTAMP(?:\(\d*\))?|[\'"]0000-00-00(?: 00:00:00)?[\'"]|[\'"]0[\'"]|0)\b/i', $line) !== 1
            && preg_match('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\d*\))?/i', $line) !== 1
        ) {
            continue;
        }

        $before = $line;
        $line = preg_replace('/\bNOT\s+NULL\b/i', 'NULL', $line, 1);
        $line = preg_replace('/\bDEFAULT\s+(?:CURRENT_TIMESTAMP(?:\(\d*\))?|[\'"]0000-00-00(?: 00:00:00)?[\'"]|[\'"]0[\'"]|0)\b/i', 'DEFAULT NULL', $line);
        $line = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\d*\))?/i', '', $line);
        if ($line !== $before) {
            $adjustments++;
        }
    }
    unset($line);

    return implode("\n", $lines);
}

function installer_import_sql(PDO $pdo, $sqlFile, $prefix): array
{
    $handle = fopen($sqlFile, 'rb');
    if (!$handle) {
        throw new RuntimeException('无法打开安装 SQL 文件：' . $sqlFile);
    }

    $buffer = '';
    $statementCount = 0;
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $escaped = false;
    $lineNumber = 0;
    $compatAdjustments = 0;

    try {
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            if ($lineNumber === 1) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }
            if ($prefix !== 'ddwx_') {
                $line = str_replace('ddwx_', $prefix, $line);
            }
            $trimmed = ltrim($line);
            $isLineComment = preg_match('/^--(?:\s|$)/', $trimmed) === 1 || installer_starts_with($trimmed, '#');
            if (
                !$inSingle &&
                !$inDouble &&
                !$inBacktick &&
                (
                    $isLineComment ||
                    (trim($buffer) === '' && installer_starts_with($trimmed, '/*'))
                )
            ) {
                continue;
            }

            $length = strlen($line);
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                if ($inSingle) {
                    $buffer .= $char;
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === '\'') {
                        $inSingle = false;
                    }
                    continue;
                }
                if ($inDouble) {
                    $buffer .= $char;
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === '"') {
                        $inDouble = false;
                    }
                    continue;
                }
                if ($inBacktick) {
                    $buffer .= $char;
                    if ($char === '`') {
                        $inBacktick = false;
                    }
                    continue;
                }
                if ($char === '\'') {
                    $inSingle = true;
                    $buffer .= $char;
                    continue;
                }
                if ($char === '"') {
                    $inDouble = true;
                    $buffer .= $char;
                    continue;
                }
                if ($char === '`') {
                    $inBacktick = true;
                    $buffer .= $char;
                    continue;
                }
                if ($char === ';') {
                    $statement = trim($buffer);
                    $buffer = '';
                    if (!installer_should_skip_dump_statement($statement)) {
                        $statement = installer_normalize_mysql_datetime_defaults($statement, $compatAdjustments);
                        $pdo->exec($statement);
                        $statementCount++;
                    }
                    continue;
                }
                $buffer .= $char;
            }
        }

        if ($inSingle || $inDouble || $inBacktick) {
            throw new RuntimeException('安装 SQL 存在未闭合的引号，结束于第 ' . $lineNumber . ' 行');
        }

        $tail = trim($buffer);
        if (!installer_should_skip_dump_statement($tail)) {
            $tail = installer_normalize_mysql_datetime_defaults($tail, $compatAdjustments);
            $pdo->exec($tail);
            $statementCount++;
        }
    } finally {
        fclose($handle);
    }

    return ['statements' => $statementCount, 'size' => filesize($sqlFile), 'compat_adjustments' => $compatAdjustments];
}

function installer_verify_default_menus(PDO $pdo, string $prefix): array
{
    $menuTableName = $prefix . 'admin_menu';
    if (!installer_table_exists($pdo, $menuTableName)) {
        throw new RuntimeException('默认菜单导入失败：admin_menu 表不存在');
    }

    $menuTable = str_replace('`', '``', $menuTableName);
    $requiredKeys = ['shop', 'member', 'finance', 'yingxiao', 'business', 'jiemian', 'pingtai', 'system'];
    $placeholders = implode(',', array_fill(0, count($requiredKeys), '?'));
    $stmt = $pdo->prepare("SELECT `key` FROM `{$menuTable}` WHERE `key` IN ({$placeholders}) AND `status` = 1");
    $stmt->execute($requiredKeys);
    $existingKeys = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $missingKeys = array_values(array_diff($requiredKeys, $existingKeys));
    if (!empty($missingKeys)) {
        throw new RuntimeException('默认菜单导入不完整，缺少：' . implode('、', $missingKeys));
    }

    $requiredMenuPaths = [
        'Money/membertransfer' => ['name' => '会员互转明细', 'parent_key' => 'finance'],
        'DesignerPagePopup/index' => ['name' => '页面弹窗', 'parent_key' => 'jiemian'],
    ];
    foreach ($requiredMenuPaths as $menuPath => $menuRule) {
        $stmt = $pdo->prepare(
            "SELECT child.`id`, child.`pid`, child.`level`, parent.`key` AS `parent_key` " .
            "FROM `{$menuTable}` child " .
            "LEFT JOIN `{$menuTable}` parent ON parent.`id` = child.`pid` " .
            "WHERE child.`path` = ? AND child.`status` = 1 AND child.`is_hidden` = 0 " .
            "ORDER BY child.`id` ASC"
        );
        $stmt->execute([$menuPath]);
        $menuRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $menuName = (string)$menuRule['name'];
        if (count($menuRows) !== 1) {
            throw new RuntimeException('默认菜单层级异常：' . $menuName . ' 应只有一条启用可见记录，实际为 ' . count($menuRows) . ' 条');
        }

        $menuRow = $menuRows[0];
        $expectedParentKey = (string)$menuRule['parent_key'];
        if ((string)($menuRow['parent_key'] ?? '') !== $expectedParentKey || intval($menuRow['level'] ?? 0) !== 2) {
            throw new RuntimeException('默认菜单层级异常：' . $menuName . ' 必须位于 ' . $expectedParentKey . ' 二级菜单下');
        }
    }

    $legacyMenuPaths = [
        'DesignerMenu/menu2' => '底部导航旧入口',
        'Member/addgongxianzhi' => '加贡献值旧入口',
        'YuyueOrder/commissionlog' => '预约佣金记录旧入口',
        'Backstage/dashboard' => '系统设置数据大屏旧入口',
    ];
    foreach ($legacyMenuPaths as $menuPath => $menuName) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$menuTable}` WHERE `path` = ? AND `status` = 1 AND `is_hidden` = 0");
        $stmt->execute([$menuPath]);
        if (intval($stmt->fetchColumn()) > 0) {
            throw new RuntimeException('默认菜单仍包含旧入口：' . $menuName);
        }
    }

    $configTableName = $prefix . 'admin_system_config';
    if (installer_table_exists($pdo, $configTableName)) {
        $configTable = str_replace('`', '``', $configTableName);
        $stmt = $pdo->query("SELECT `id` FROM `{$configTable}` WHERE `key` = 'menu_use_database' LIMIT 1");
        if ($stmt->fetchColumn()) {
            $pdo->exec("UPDATE `{$configTable}` SET `value` = '1' WHERE `key` = 'menu_use_database'");
        } else {
            $pdo->exec("INSERT INTO `{$configTable}` (`key`,`value`,`type`,`description`,`createtime`,`updatetime`) VALUES ('menu_use_database','1','switch','是否使用数据库菜单模式',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())");
        }
    }

    return [
        'menu_count' => intval($pdo->query("SELECT COUNT(*) FROM `{$menuTable}` WHERE `status` = 1")->fetchColumn()),
        'top_keys' => $existingKeys,
    ];
}

function installer_apply_site_config(PDO $pdo, $prefix, $siteName, $adminUsername, $adminPassword): void
{
    $passwordHash = md5((string)$adminPassword);
    $now = time();
    $userTable = $prefix . 'admin_user';

    if (installer_table_exists($pdo, $userTable)) {
        $adminUserIdStmt = $pdo->query('SELECT `id` FROM `' . str_replace('`', '``', $userTable) . '` WHERE `aid` = 1 AND `bid` = 0 ORDER BY `id` ASC LIMIT 1');
        $adminUserId = intval($adminUserIdStmt->fetchColumn());
        if ($adminUserId > 0) {
            $stmt = $pdo->prepare('UPDATE `' . str_replace('`', '``', $userTable) . '` SET `un` = ?, `pwd` = ?, `status` = 1 WHERE `id` = ?');
            $stmt->execute([$adminUsername, $passwordHash, $adminUserId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO `' . str_replace('`', '``', $userTable) . '` (`aid`,`bid`,`un`,`pwd`,`status`,`isadmin`,`createtime`,`logintime`,`ip`,`type`) VALUES (1,0,?,?,1,1,?,?,?,?)');
            $stmt->execute([$adminUsername, $passwordHash, $now, $now, '', 'business']);
        }
    }

    $adminSetTable = $prefix . 'admin_set';
    if (installer_table_exists($pdo, $adminSetTable)) {
        $stmt = $pdo->prepare('UPDATE `' . str_replace('`', '``', $adminSetTable) . '` SET `name` = ? WHERE `aid` = 1');
        $stmt->execute([$siteName]);
    }

    $syssetTable = $prefix . 'sysset';
    if (installer_table_exists($pdo, $syssetTable)) {
        $stmt = $pdo->prepare('SELECT `value` FROM `' . str_replace('`', '``', $syssetTable) . '` WHERE `name` = ? LIMIT 1');
        $stmt->execute(['webinfo']);
        $webinfo = $stmt->fetchColumn();
        $webinfoData = json_decode((string)$webinfo, true);
        if (!is_array($webinfoData)) {
            $webinfoData = [];
        }
        $webinfoData['webname'] = $siteName;
        $json = json_encode($webinfoData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($webinfo !== false) {
            $updateStmt = $pdo->prepare('UPDATE `' . str_replace('`', '``', $syssetTable) . '` SET `value` = ? WHERE `name` = ?');
            $updateStmt->execute([$json, 'webinfo']);
        } else {
            $insertStmt = $pdo->prepare('INSERT INTO `' . str_replace('`', '``', $syssetTable) . '` (`name`,`value`) VALUES (?, ?)');
            $insertStmt->execute(['webinfo', $json]);
        }
    }
}

function installer_format_env_value($value): string
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }
    if (preg_match('/^[A-Za-z0-9_:\\/.@-]+$/', $value)) {
        return $value;
    }
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function installer_write_env(array $state, array $env): void
{
    $order = [
        'APP_DEBUG',
        'DEFAULT_TIMEZONE',
        'DATABASE_TYPE',
        'DATABASE_HOSTNAME',
        'DATABASE_DATABASE',
        'DATABASE_USERNAME',
        'DATABASE_PASSWORD',
        'DATABASE_HOSTPORT',
        'DATABASE_CHARSET',
        'DATABASE_DEBUG',
        'DATABASE_PREFIX',
        'REDIS_HOST',
        'REDIS_PORT',
        'REDIS_PASSWORD',
        'MONGODB_TYPE',
        'MONGODB_HOSTNAME',
        'MONGODB_DATABASE',
        'MONGODB_USERNAME',
        'MONGODB_PASSWORD',
        'MONGODB_HOSTPORT',
        'MONGODB_CHARSET',
        'MONGODB_DEBUG',
        'AES_KEY',
        'LANG_DEFAULT_LANG',
        'JWT_SECRET',
    ];

    $lines = ['# installer generated', '# do not run install.php again after installation', ''];
    $written = [];
    foreach ($order as $key) {
        $lines[] = $key . '=' . installer_format_env_value($env[$key] ?? '');
        $written[$key] = true;
        if (in_array($key, ['DEFAULT_TIMEZONE', 'DATABASE_PREFIX', 'REDIS_PASSWORD', 'MONGODB_DEBUG', 'JWT_SECRET'], true)) {
            $lines[] = '';
        }
    }
    foreach ($env as $key => $value) {
        if (!isset($written[$key])) {
            $lines[] = $key . '=' . installer_format_env_value($value);
        }
    }
    installer_write_file($state['env_path'], preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) . "\n");
}

function installer_write_package_config(array $state, array $job): void
{
    $config = [
        'license_required' => true,
        'product_code' => (string)$job['product_code'],
        'product_name' => (string)$job['product_name'],
        'package_id' => (string)$job['package_id'],
        'package_version' => (string)($job['installed_version'] ?? $job['package_version']),
        'build_id' => (string)($job['build_id'] ?? ''),
        'channel' => (string)$job['channel'],
        'license_server' => (string)$job['auth_server'],
        'public_key' => (string)($job['public_key'] ?? ''),
        'installed_at' => date('c'),
    ];
    installer_write_file($state['package_config_path'], "<?php\nreturn " . var_export($config, true) . ";\n");
}

function installer_write_license_cache(array $state, array $data): void
{
    installer_write_file(
        $state['license_cache_path'],
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

function installer_write_lock(array $state, array $data): void
{
    installer_write_file(
        $state['lock_path'],
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

function installer_action_start(array $state, array $data): void
{
    $envDefaults = installer_build_env_defaults($state);
    $installedInfo = installer_existing_install_info($state, $envDefaults);
    if (!empty($installedInfo['installed'])) {
        throw new RuntimeException($installedInfo['message']);
    }
    installer_assert_requirements($state);

    $form = array_merge([
        'auth_server' => INSTALLER_DEFAULT_AUTH_SERVER,
        'product_code' => INSTALLER_DEFAULT_PRODUCT_CODE,
        'product_name' => INSTALLER_DEFAULT_PRODUCT_NAME,
        'package_id' => INSTALLER_DEFAULT_PACKAGE_ID,
        'package_version' => INSTALLER_DEFAULT_PACKAGE_VERSION,
        'channel' => INSTALLER_DEFAULT_CHANNEL,
        'public_key' => INSTALLER_DEFAULT_PUBLIC_KEY,
        'package_secret' => INSTALLER_DEFAULT_PACKAGE_SECRET,
        'license_key' => '',
        'site_name' => '商城系统',
        'admin_username' => 'admin',
        'admin_password' => '',
        'admin_password_confirm' => '',
        'db_host' => $envDefaults['DATABASE_HOSTNAME'],
        'db_port' => $envDefaults['DATABASE_HOSTPORT'],
        'db_name' => $envDefaults['DATABASE_DATABASE'],
        'db_user' => $envDefaults['DATABASE_USERNAME'],
        'db_password' => $envDefaults['DATABASE_PASSWORD'],
        'db_prefix' => $envDefaults['DATABASE_PREFIX'],
        'redis_host' => $envDefaults['REDIS_HOST'],
        'redis_port' => $envDefaults['REDIS_PORT'],
        'redis_password' => $envDefaults['REDIS_PASSWORD'],
    ], $data);

    $errors = installer_validate_form($form);
    if ($errors) {
        throw new RuntimeException(implode('；', $errors));
    }

    $prefix = installer_normalize_prefix((string)$form['db_prefix']);
    $jobId = installer_random_hex(16);
    $jobDir = $state['tmp_root'] . '/' . $jobId;
    if (!installer_mkdir($jobDir)) {
        throw new RuntimeException('无法创建安装临时目录：' . $jobDir);
    }

    $job = [
        'job_id' => $jobId,
        'installer_version' => INSTALLER_VERSION,
        'created_at' => date('c'),
        'auth_server' => rtrim((string)$form['auth_server'], '/'),
        'product_code' => trim((string)$form['product_code']),
        'product_name' => trim((string)$form['product_name']),
        'package_id' => trim((string)$form['package_id']),
        'package_version' => trim((string)$form['package_version']),
        'installed_version' => trim((string)$form['package_version']),
        'build_id' => trim((string)$form['package_id']),
        'channel' => trim((string)$form['channel']) ?: 'stable',
        'public_key' => trim((string)$form['public_key']),
        'package_secret' => trim((string)$form['package_secret']),
        'license_key' => trim((string)$form['license_key']),
        'license_key_mask' => installer_mask_license_key((string)$form['license_key']),
        'site_name' => trim((string)$form['site_name']),
        'admin_username' => trim((string)$form['admin_username']),
        'admin_password' => (string)$form['admin_password'],
        'db_host' => trim((string)$form['db_host']),
        'db_port' => trim((string)$form['db_port']),
        'db_name' => trim((string)$form['db_name']),
        'db_user' => trim((string)$form['db_user']),
        'db_password' => (string)$form['db_password'],
        'db_prefix' => $prefix,
        'redis_host' => trim((string)$form['redis_host']),
        'redis_port' => trim((string)$form['redis_port']),
        'redis_password' => (string)$form['redis_password'],
        'machine_fingerprint' => installer_detect_machine_fingerprint($state),
        'job_dir' => $jobDir,
        'sql_file' => '',
        'manifest' => [],
    ];
    installer_save_job($state, $job);

    installer_success([
        'job_id' => $jobId,
        'message' => '安装任务已创建',
        'license_key_mask' => $job['license_key_mask'],
    ]);
}

function installer_action_verify(array $state, array $data): void
{
    $job = installer_load_job($state, (string)($data['job_id'] ?? ''));
    $verify = installer_call_license_center($job, 'verify-install', ['package_type' => 'install']);
    if (!installer_verify_license_ticket($verify['ticket'] ?? '', $verify['signature'] ?? '', $job['public_key'] ?? '')) {
        throw new RuntimeException('授权中心返回票据验签失败');
    }
    $verify['ticket_data'] = installer_decode_ticket($verify['ticket'] ?? '');
    $job['license_verify'] = $verify;
    installer_save_job($state, $job);
    installer_success([
        'request_ip' => (string)($verify['request_ip'] ?? ''),
        'license_id' => intval($verify['license_id'] ?? 0),
        'license_type' => (string)($verify['license_type'] ?? ''),
    ], '授权校验通过');
}

function installer_action_release(array $state, array $data): void
{
    $job = installer_load_job($state, (string)($data['job_id'] ?? ''));
    $releaseData = installer_call_license_center($job, 'latest-release', [
        'package_type' => 'install',
        'channel' => (string)$job['channel'],
    ]);
    $release = is_array($releaseData['release'] ?? null) ? $releaseData['release'] : [];
    if (empty($release['id'])) {
        throw new RuntimeException('授权中心未返回可安装发布包');
    }
    $job['release'] = $release;
    $job['installed_version'] = (string)($release['version'] ?? '');
    $job['build_id'] = (string)($release['build_id'] ?? '');
    installer_save_job($state, $job);
    installer_success([
        'release_id' => intval($release['id']),
        'version' => (string)($release['version'] ?? ''),
        'build_id' => (string)($release['build_id'] ?? ''),
        'file_size_text' => (string)($release['file_size_text'] ?? ''),
    ], '已获取安装包版本');
}

function installer_action_ticket(array $state, array $data): void
{
    $job = installer_load_job($state, (string)($data['job_id'] ?? ''));
    $releaseId = intval($job['release']['id'] ?? 0);
    if ($releaseId <= 0) {
        throw new RuntimeException('请先获取发布版本');
    }
    $ticket = installer_call_license_center($job, 'download-ticket', [
        'package_type' => 'install',
        'channel' => (string)$job['channel'],
        'release_id' => $releaseId,
    ]);
    if (empty($ticket['download_url'])) {
        throw new RuntimeException('授权中心未返回下载地址');
    }
    $job['download_ticket'] = $ticket;
    installer_save_job($state, $job);
    installer_success([
        'expires_at_text' => (string)($ticket['expires_at_text'] ?? ''),
    ], '下载凭证已生成');
}

function installer_action_download(array $state, array $data): void
{
    $job = installer_load_job($state, (string)($data['job_id'] ?? ''));
    $localPackagePath = trim((string)($job['local_package_path'] ?? ''));
    if ($localPackagePath !== '') {
        if (!is_file($localPackagePath)) {
            throw new RuntimeException('本地安装包不存在，请重新上传：' . $localPackagePath);
        }
        $download = [
            'path' => $localPackagePath,
            'size' => filesize($localPackagePath),
            'sha256' => hash_file('sha256', $localPackagePath),
            'source' => 'local',
        ];
        $job['download_path'] = $localPackagePath;
    } else {
        $downloadUrl = (string)($job['download_ticket']['download_url'] ?? '');
        $download = installer_download_file($downloadUrl, (string)$job['download_path']);
        $download['source'] = 'remote';
    }
    $expectedSha = trim((string)($job['download_ticket']['release']['file_sha256'] ?? ($job['release']['file_sha256'] ?? '')));
    if ($expectedSha !== '' && !hash_equals(strtolower($expectedSha), strtolower((string)$download['sha256']))) {
        $expectedFilesSha = trim((string)($job['download_ticket']['release']['manifest']['files_sha256'] ?? ($job['release']['manifest']['files_sha256'] ?? '')));
        if ($localPackagePath !== '' && $expectedFilesSha !== '' && hash_equals(strtolower($expectedFilesSha), strtolower((string)$download['sha256']))) {
            throw new RuntimeException('本地安装包填成了内层 files.zip。请上传外层发布包 ZIP，例如 tpdaxiangshop-install-版本-构建号.zip；files.zip 只是代码包，缺少安装清单和数据库 SQL。');
        }
        $expectedSize = intval($job['download_ticket']['release']['file_size'] ?? ($job['release']['file_size'] ?? 0));
        $actualSize = intval($download['size'] ?? 0);
        throw new RuntimeException('安装包 SHA256 校验失败：授权中心期望 SHA256=' . $expectedSha . ($expectedSize > 0 ? '，大小=' . $expectedSize . '字节' : '') . '；当前文件 SHA256=' . (string)$download['sha256'] . ($actualSize > 0 ? '，大小=' . $actualSize . '字节' : '') . '。请上传授权中心发布记录对应的外层 ZIP，或在授权中心重新导入当前 ZIP 对应的 release-summary.json。');
    }
    $job['download'] = $download;
    installer_save_job($state, $job);
    installer_success([
        'size' => intval($download['size']),
        'sha256' => (string)$download['sha256'],
        'source' => (string)($download['source'] ?? ''),
    ], $localPackagePath !== '' ? '已使用本地安装包' : '安装包下载完成');
}

function installer_action_extract(array $state, array $data): void
{
    $job = installer_load_job($state, (string)($data['job_id'] ?? ''));
    $downloadPath = (string)($job['download_path'] ?? '');
    if (!is_file($downloadPath)) {
        throw new RuntimeException('安装包不存在，请重新下载');
    }

    installer_rrmdir((string)$job['package_dir']);
    installer_mkdir((string)$job['package_dir']);
    installer_extract_zip_to($downloadPath, (string)$job['package_dir'], [
        'strip_common_root' => false,
        'skip_app_runtime' => false,
        'skip_meta' => false,
    ]);

    $manifestPath = installer_find_file_by_suffix((string)$job['package_dir'], ['release-manifest.json', 'manifest.json']);
    $manifest = installer_read_json_file($manifestPath);
    if (!empty($manifest['min_php']) && version_compare(PHP_VERSION, (string)$manifest['min_php'], '<')) {
        throw new RuntimeException('当前 PHP 版本低于安装包要求：' . $manifest['min_php']);
    }

    $filesZip = installer_find_file_by_suffix((string)$job['package_dir'], ['files.zip', 'source.zip', 'code.zip']);
    if ($filesZip !== '') {
        $expectedFilesSha = trim((string)($manifest['files_sha256'] ?? ''));
        if ($expectedFilesSha !== '' && !hash_equals(strtolower($expectedFilesSha), strtolower((string)hash_file('sha256', $filesZip)))) {
            throw new RuntimeException('代码包 files.zip SHA256 校验失败');
        }
        $extract = installer_extract_zip_to($filesZip, $state['target_root'], [
            'strip_common_root' => true,
            'skip_app_runtime' => true,
            'skip_meta' => true,
        ]);
        $sqlFile = installer_find_file_by_suffix((string)$job['package_dir'], ['database/install.sql', 'install.sql', 'tpdaxiangshop.sql']);
    } else {
        $extract = installer_extract_zip_to($downloadPath, $state['target_root'], [
            'strip_common_root' => true,
            'skip_app_runtime' => true,
            'skip_meta' => true,
        ]);
        $sqlFile = installer_find_file_by_suffix($state['target_root'], ['database/install.sql', 'database/install/tpdaxiangshop.sql', 'install.sql', 'tpdaxiangshop.sql']);
        if ($sqlFile === '') {
            $sqlFile = installer_find_file_by_suffix((string)$job['package_dir'], ['database/install.sql', 'install.sql', 'tpdaxiangshop.sql']);
        }
    }

    if ($sqlFile === '') {
        throw new RuntimeException('安装包中未找到安装 SQL，请确认包含 database/install.sql');
    }

    $job['manifest'] = $manifest;
    $job['manifest_path'] = $manifestPath;
    $job['sql_file'] = $sqlFile;
    $job['extract'] = $extract;
    installer_save_job($state, $job);
    installer_success([
        'written' => intval($extract['written'] ?? 0),
        'skipped' => intval($extract['skipped'] ?? 0),
        'sql_file' => $sqlFile,
    ], '代码解压完成');
}

function installer_action_database(array $state, array $data): void
{
    $job = installer_load_job($state, (string)($data['job_id'] ?? ''));
    $sqlFile = (string)($job['sql_file'] ?? '');
    if (!is_file($sqlFile)) {
        $sqlFile = installer_resolve_install_sql_file($state);
    }
    if (!is_file($sqlFile)) {
        throw new RuntimeException('已解压代码中未找到安装 SQL，请确认项目目录包含 database/install/tpdaxiangshop.sql 或 database/install.sql');
    }

    installer_validate_identifier((string)$job['db_name'], '数据库名');
    installer_validate_identifier((string)$job['db_prefix'], '数据表前缀');

    $serverPdo = installer_create_pdo($job['db_host'], $job['db_port'], $job['db_user'], $job['db_password']);
    $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', (string)$job['db_name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

    $pdo = installer_create_pdo($job['db_host'], $job['db_port'], $job['db_user'], $job['db_password'], $job['db_name']);
    $existingTableCount = installer_count_prefixed_tables($pdo, (string)$job['db_name'], (string)$job['db_prefix']);
    if ($existingTableCount > 0) {
        throw new RuntimeException('目标数据库中已存在 ' . $existingTableCount . ' 张 `' . $job['db_prefix'] . '` 前缀表，请换空库或清空后重试');
    }

    $import = installer_import_sql($pdo, $sqlFile, (string)$job['db_prefix']);
    installer_apply_site_config($pdo, (string)$job['db_prefix'], (string)$job['site_name'], (string)$job['admin_username'], (string)$job['admin_password']);
    // install.sql 已经按完整 SQL 顺序导入菜单。不要再逐行重复执行 INSERT，
    // 否则带有 SET @pid 的子菜单会失去父级上下文并产生重复菜单。
    $menuRowsImported = 0;
    $menuImport = installer_verify_default_menus($pdo, (string)$job['db_prefix']);
    $job['sql_file'] = $sqlFile;
    $job['database_import'] = $import;
    $job['menu_import'] = $menuImport;
    $job['menu_rows_imported'] = $menuRowsImported;
    installer_save_job($state, $job);
    installer_success([
        'statements' => intval($import['statements'] ?? 0),
        'size' => intval($import['size'] ?? 0),
        'menu_count' => intval($menuImport['menu_count'] ?? 0),
        'menu_rows_imported' => $menuRowsImported,
    ], '数据库导入完成');
}

function installer_action_config(array $state, array $data): void
{
    $job = installer_load_job($state, (string)($data['job_id'] ?? ''));
    $env = installer_build_env_defaults($state);
    $env['APP_DEBUG'] = 'false';
    $env['DATABASE_TYPE'] = 'mysql';
    $env['DATABASE_HOSTNAME'] = (string)$job['db_host'];
    $env['DATABASE_DATABASE'] = (string)$job['db_name'];
    $env['DATABASE_USERNAME'] = (string)$job['db_user'];
    $env['DATABASE_PASSWORD'] = (string)$job['db_password'];
    $env['DATABASE_HOSTPORT'] = (string)$job['db_port'];
    $env['DATABASE_CHARSET'] = 'utf8mb4';
    $env['DATABASE_DEBUG'] = 'false';
    $env['DATABASE_PREFIX'] = (string)$job['db_prefix'];
    $env['REDIS_HOST'] = (string)$job['redis_host'];
    $env['REDIS_PORT'] = (string)$job['redis_port'];
    $env['REDIS_PASSWORD'] = (string)$job['redis_password'];
    if (empty($env['AES_KEY'])) {
        $env['AES_KEY'] = installer_random_hex(8);
    }
    if (empty($env['JWT_SECRET'])) {
        $env['JWT_SECRET'] = installer_random_hex(16);
    }
    installer_write_env($state, $env);
    installer_write_package_config($state, $job);

    $baseUrl = installer_base_url();
    $lockData = [
        'installed_at' => date('c'),
        'installer_version' => INSTALLER_VERSION,
        'site_name' => (string)$job['site_name'],
        'admin_username' => (string)$job['admin_username'],
        'base_url' => $baseUrl,
        'database' => [
            'host' => (string)$job['db_host'],
            'port' => intval($job['db_port']),
            'name' => (string)$job['db_name'],
            'prefix' => (string)$job['db_prefix'],
        ],
        'release' => [
            'product_code' => (string)$job['product_code'],
            'package_id' => (string)$job['package_id'],
            'version' => (string)($job['installed_version'] ?? ''),
            'build_id' => (string)($job['build_id'] ?? ''),
            'channel' => (string)$job['channel'],
            'manifest' => is_array($job['manifest'] ?? null) ? $job['manifest'] : [],
        ],
        'license' => [
            'license_key_mask' => (string)$job['license_key_mask'],
            'license_id' => intval($job['license_verify']['license_id'] ?? 0),
            'request_ip' => (string)($job['license_verify']['request_ip'] ?? ''),
            'license_type' => (string)($job['license_verify']['license_type'] ?? ''),
        ],
    ];
    $installLockHash = hash('sha256', json_encode($lockData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $lockData['install_lock_hash'] = $installLockHash;
    installer_write_lock($state, $lockData);

    $licenseCache = [
        'product_code' => (string)$job['product_code'],
        'product_name' => (string)$job['product_name'],
        'package_id' => (string)$job['package_id'],
        'package_version' => (string)($job['installed_version'] ?? ''),
        'build_id' => (string)($job['build_id'] ?? ''),
        'license_server' => (string)$job['auth_server'],
        'license_key' => (string)$job['license_key'],
        'license_key_mask' => (string)$job['license_key_mask'],
        'verified_at' => date('c'),
        'verify' => is_array($job['license_verify'] ?? null) ? $job['license_verify'] : [],
        'release' => is_array($job['release'] ?? null) ? $job['release'] : [],
    ];
    installer_write_license_cache($state, $licenseCache);

    $job['install_lock_hash'] = $installLockHash;
    $job['lock_data'] = $lockData;
    $job['license_cache'] = $licenseCache;
    installer_save_job($state, $job);
    installer_success([
        'lock_path' => $state['lock_path'],
        'license_cache_path' => $state['license_cache_path'],
    ], '配置文件已写入');
}

function installer_action_confirm(array $state, array $data): void
{
    $job = installer_load_job($state, (string)($data['job_id'] ?? ''));
    $installToken = (string)($job['license_verify']['install_token'] ?? '');
    $warning = '';
    if ($installToken !== '') {
        try {
            $confirm = installer_call_license_center($job, 'confirm-install', [
                'install_token' => $installToken,
                'install_lock_hash' => (string)($job['install_lock_hash'] ?? ''),
                'package_version' => (string)($job['installed_version'] ?? $job['package_version']),
            ]);
            if (!installer_verify_license_ticket($confirm['ticket'] ?? '', $confirm['signature'] ?? '', $job['public_key'] ?? '')) {
                throw new RuntimeException('授权确认票据验签失败');
            }
            $confirm['ticket_data'] = installer_decode_ticket($confirm['ticket'] ?? '');
            $licenseCache = is_array($job['license_cache'] ?? null) ? $job['license_cache'] : [];
            $licenseCache['confirmed_at'] = date('c');
            $licenseCache['confirm'] = $confirm;
            installer_write_license_cache($state, $licenseCache);
        } catch (Throwable $e) {
            $warning = '安装已完成，但授权中心确认失败：' . $e->getMessage();
        }
    }

    if (!empty($job['job_path'])) {
        @unlink((string)$job['job_path']);
    }

    installer_success([
        'base_url' => installer_base_url(),
        'admin_url' => installer_base_url() . '/admin/',
        'legacy_admin_url' => installer_base_url() . '/admin.php/Login/index',
        'warning' => $warning,
    ], $warning === '' ? '安装完成' : $warning);
}

function installer_handle_action(string $action): void
{
    $state = installer_get_state();
    $data = installer_request_data();
    try {
        switch ($action) {
            case 'start':
                installer_action_start($state, $data);
                break;
            case 'verify':
                installer_action_verify($state, $data);
                break;
            case 'release':
                installer_action_release($state, $data);
                break;
            case 'ticket':
                installer_action_ticket($state, $data);
                break;
            case 'download':
                installer_action_download($state, $data);
                break;
            case 'extract':
                installer_action_extract($state, $data);
                break;
            case 'database':
                installer_action_database($state, $data);
                break;
            case 'config':
                installer_action_config($state, $data);
                break;
            case 'confirm':
                installer_action_confirm($state, $data);
                break;
            default:
                installer_fail('未知安装动作', 'unknown_action', 404);
        }
    } catch (Throwable $e) {
        installer_fail($e->getMessage(), 'step_failed', 500);
    }
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
if ($action !== '') {
    installer_handle_action($action);
    exit;
}

$state = installer_get_state();
$envDefaults = installer_build_env_defaults($state);
$requirements = installer_requirement_checks($state);
$requirementsOk = true;
foreach ($requirements as $check) {
    if (empty($check['ok'])) {
        $requirementsOk = false;
        break;
    }
}
$realInstalledInfo = installer_existing_install_info($state, $envDefaults);
$previewMode = isset($_GET['preview']) && (string)$_GET['preview'] === '1';
$installedInfo = $previewMode ? ['installed' => false, 'source' => '', 'data' => [], 'message' => ''] : $realInstalledInfo;
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>系统在线安装器</title>
  <style>
    *{box-sizing:border-box}
    body{margin:0;background:#f6f7f9;color:#1f2937;font:14px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif}
    .page{max-width:1080px;margin:30px auto;padding:0 18px}
    .panel{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:22px;margin-bottom:16px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
    .top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
    h1,h2{margin:0 0 14px}
    h1{font-size:26px;line-height:1.25}
    h2{font-size:17px}
    .muted{color:#6b7280}
    .badge{display:inline-flex;align-items:center;border-radius:999px;background:#eef2ff;color:#3730a3;padding:4px 10px;font-size:12px;font-weight:600}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .field label{display:block;margin:0 0 6px;font-weight:600;color:#374151}
    .field input,.field textarea{width:100%;border:1px solid #d1d5db;border-radius:6px;padding:10px 11px;font:inherit;background:#fff}
    .field textarea{min-height:82px;resize:vertical}
    .field small{display:block;margin-top:5px;color:#6b7280}
    .check-list,.log-list{margin:0;padding-left:18px}
    .check-ok{color:#166534}
    .check-fail{color:#991b1b}
    .alert{border-radius:8px;padding:12px 14px;margin-bottom:14px}
    .alert-warn{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412}
    .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
    .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    .progress{height:12px;background:#e5e7eb;border-radius:999px;overflow:hidden}
    .bar{height:100%;width:0;background:#2563eb;transition:width .25s ease}
    .step-row{display:flex;justify-content:space-between;gap:12px;margin:10px 0 8px}
    .wizard{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}
    .wizard-item{position:relative;border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:13px 14px;color:#6b7280}
    .wizard-item strong{display:block;color:#374151;font-size:14px}
    .wizard-item span{font-size:12px}
    .wizard-item.active{border-color:#111827;background:#111827;color:#d1d5db}
    .wizard-item.active strong{color:#fff}
    .wizard-item.done{border-color:#bbf7d0;background:#f0fdf4;color:#166534}
    .wizard-item.done strong{color:#166534}
    .form-step{display:none}
    .form-step.active{display:block}
    .step-heading{margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #eef0f3}
    .step-heading h2{margin-bottom:3px}
    .step-actions{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-top:22px;padding-top:18px;border-top:1px solid #eef0f3}
    .step-actions-right{display:flex;gap:10px;margin-left:auto}
    .review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px}
    .review-item{border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;padding:12px 14px}
    .review-item span{display:block;color:#6b7280;font-size:12px}
    .review-item strong{display:block;margin-top:2px;word-break:break-all}
    .actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:20px}
    button,.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:6px;background:#111827;color:#fff;padding:11px 18px;font-weight:700;text-decoration:none;cursor:pointer}
    button[disabled]{opacity:.6;cursor:not-allowed}
    .btn-secondary{background:#fff;color:#111827;border:1px solid #d1d5db}
    details{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-top:16px}
    summary{cursor:pointer;font-weight:700}
    code{background:#f3f4f6;border-radius:5px;padding:2px 6px}
    .hidden{display:none}
    @media (max-width:760px){
      .grid,.grid3{grid-template-columns:1fr}
      .wizard{grid-template-columns:repeat(2,minmax(0,1fr))}
      .review-grid{grid-template-columns:1fr}
      .top{display:block}
      .page{margin:18px auto}
    }
    :root{--brand:#e84b34;--brand-dark:#c93322;--ink:#191919;--soft:#77716b;--line:#e9e2da;--canvas:#f5f0ea}
    body{min-height:100vh;background:radial-gradient(circle at 8% 4%,rgba(232,75,52,.10),transparent 27%),radial-gradient(circle at 94% 10%,rgba(218,164,94,.13),transparent 24%),var(--canvas);color:var(--ink);font-family:"Avenir Next","PingFang SC","Microsoft YaHei",sans-serif}
    body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.28;background-image:linear-gradient(rgba(62,50,38,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(62,50,38,.035) 1px,transparent 1px);background-size:28px 28px}
    .page{position:relative;max-width:1040px;margin:34px auto 56px}
    .panel{border-color:var(--line);border-radius:18px;padding:26px;background:rgba(255,255,255,.96);box-shadow:0 18px 50px rgba(66,49,32,.07)}
    .top{position:relative;overflow:hidden;align-items:center;min-height:158px;padding:34px 36px;border:0;color:#fff;background:linear-gradient(125deg,#1c1a18 0%,#29231f 72%,#4a2b24 100%);box-shadow:0 24px 60px rgba(37,27,21,.20)}
    .top:after{content:"";position:absolute;width:235px;height:235px;right:-68px;top:-105px;border:42px solid rgba(232,75,52,.30);border-radius:50%}
    .brand-row{position:relative;z-index:1;display:flex;align-items:center;gap:16px}
    .brand-mark{display:grid;place-items:center;width:54px;height:54px;flex:0 0 auto;border-radius:16px;background:var(--brand);box-shadow:0 12px 28px rgba(232,75,52,.32);font-size:25px;font-weight:900}
    h1{font-size:30px;letter-spacing:.02em}.top .muted{max-width:680px;color:rgba(255,255,255,.66)}
    .muted{color:var(--soft)}
    .badge{position:relative;z-index:1;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.08);color:#fff;padding:7px 13px;backdrop-filter:blur(8px)}
    .grid,.grid3{gap:18px}.field{margin-bottom:4px}.field label{margin-bottom:7px;color:#393532;font-weight:700}
    .field input,.field textarea{border-color:#dcd5ce;border-radius:11px;padding:12px 13px;background:#fdfcfb;outline:none;transition:.18s ease}
    .field input:hover,.field textarea:hover{border-color:#bdb3aa;background:#fff}.field input:focus,.field textarea:focus{border-color:var(--brand);background:#fff;box-shadow:0 0 0 4px rgba(232,75,52,.10)}
    .field small{color:#918b85}.check-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:0;list-style:none}.check-list li{padding:10px 12px;border:1px solid #eee8e1;border-radius:10px;background:#faf8f5}
    .wizard{gap:12px;margin-bottom:18px}.wizard-item{min-height:76px;padding:14px 15px 13px 50px;border-color:var(--line);border-radius:14px;background:rgba(255,255,255,.88);box-shadow:0 9px 26px rgba(66,49,32,.04)}
    .wizard-item:before{content:attr(data-step-nav);position:absolute;left:14px;top:16px;display:grid;place-items:center;width:27px;height:27px;border-radius:9px;background:#eee8e1;color:#756e67;font-weight:800}
    .wizard-item.active{border-color:var(--brand);background:#fff;color:#7b665f;box-shadow:0 12px 32px rgba(232,75,52,.12)}.wizard-item.active:before{background:var(--brand);color:#fff}.wizard-item.active strong{color:var(--brand-dark)}
    .wizard-item.done{border-color:#c9e4cf;background:#f5fbf6}.wizard-item.done:before{content:"✓";background:#1f9d55;color:#fff}
    .form-step.active{animation:stepIn .24s ease both}@keyframes stepIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
    .step-heading{margin-bottom:24px;padding-bottom:16px;border-color:#eee8e1}.step-heading h2{font-size:21px}.step-actions{margin-top:26px;padding-top:20px;border-color:#eee8e1}
    .review-item{border-color:#ebe5df;border-radius:12px;background:#faf8f5;padding:14px 15px}
    button,.btn{min-height:43px;border-radius:11px;background:var(--brand);padding:11px 20px;box-shadow:0 8px 20px rgba(232,75,52,.18);transition:.16s ease}button:hover,.btn:hover{background:var(--brand-dark);transform:translateY(-1px)}
    .btn-secondary{background:#fff;color:#4b4540;border-color:#d8d1ca;box-shadow:none}.btn-secondary:hover{background:#f8f5f1;color:#191919;box-shadow:none}
    details{border-color:#e6dfd8;border-radius:12px;padding:14px 16px;background:#fbf9f7}.progress{height:9px;background:#eee8e1}.bar{border-radius:999px;background:linear-gradient(90deg,var(--brand),#f28a4b)}
    code{background:#eee8e1;color:#5e554e}
    @media (max-width:760px){.check-list{grid-template-columns:1fr}.page{padding:0 12px}.panel{padding:20px;border-radius:15px}.top{padding:28px 24px}.top .badge{margin-top:20px}.brand-mark{width:46px;height:46px;border-radius:14px}h1{font-size:25px}.step-actions-right{align-items:stretch}}

    :root{--install-navy:#111b2b;--install-gold:#d8b76a;--install-blue:#0ea5d5;--install-bg:#f1f3f7;--install-text:#111827;--install-muted:#8a94a6;--install-line:#e4e8ef}
    body{background:var(--install-bg);color:var(--install-text);font-family:"PingFang SC","Microsoft YaHei",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    body:before{display:none}
    .page{max-width:980px;margin:0 auto 72px;padding:0 24px}
    .panel{border:1px solid var(--install-line);border-radius:12px;background:#fff;box-shadow:0 12px 30px rgba(17,27,43,.08)}
    .top{width:100vw;margin-left:calc(50% - 50vw);min-height:72px;padding:0 48px;border-radius:0;background:var(--install-navy);box-shadow:none;display:flex;align-items:center;justify-content:space-between;color:#fff}
    .top:after{display:none}
    .brand-row{gap:14px}
    .brand-mark{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#f4d889,#bd9451);color:#f7e4ae;box-shadow:0 10px 24px rgba(216,183,106,.22);font-size:20px}
    .top h1{margin:0;font-size:20px;line-height:1;font-weight:800;letter-spacing:0;color:#fff}
    .top .muted{display:none}
    .top .badge{border:0;background:transparent;color:#667286;padding:0;font-weight:600}
    .installer-shell{display:flex;flex-direction:column;padding-top:48px}
    .wizard{position:relative;display:flex;justify-content:center;gap:0;margin:0 auto 36px;max-width:900px}
    .wizard:before{content:"";position:absolute;left:70px;right:70px;top:19px;height:1px;background:#dfe4ec}
    .wizard-item{position:relative;z-index:1;min-height:74px;width:138px;padding:0;border:0;background:transparent;box-shadow:none;text-align:center;color:var(--install-muted)}
    .wizard-item:before{content:attr(data-step-nav);position:static;display:grid;place-items:center;width:38px;height:38px;margin:0 auto 8px;border-radius:50%;background:#e6eaf1;color:#9aa4b5;font-weight:800;font-size:15px;box-shadow:none}
    .wizard-item strong{display:block;margin:0;font-size:13px;font-weight:500;color:#8a94a6}
    .wizard-item span{display:none}
    .wizard-item.active,.wizard-item.done{border:0;background:transparent;box-shadow:none;color:var(--install-text)}
    .wizard-item.active:before{background:var(--install-blue);color:#fff;box-shadow:0 0 0 6px rgba(14,165,213,.14)}
    .wizard-item.done:before{content:"✓";background:#24b47e;color:#fff}
    .wizard-item.active strong{color:#263040;font-weight:700}
    .wizard-item.done strong{color:#5d687a}
    .wizard{order:1}
    #installForm{order:2}
    #progressPanel{order:3}
    .installer-shell > .panel:not(#installForm):not(#progressPanel){max-width:820px;margin:0 auto 24px}
    #installForm.panel{max-width:820px;margin:0 auto;padding:44px 44px 40px}
    .step-heading{margin-bottom:28px;padding-bottom:0;border:0}
    .step-heading h2{margin:0 0 4px;font-size:24px;line-height:1.3;color:#101828}
    .step-heading .muted{font-size:14px;color:#7b8798}
    .license-agreement{height:360px;overflow:auto;border:1px solid var(--install-line);border-radius:8px;background:#fbfcfe;padding:30px 34px;color:#445066;line-height:2}
    .license-agreement h3{margin:0 0 16px;font-size:15px;color:#1f2937}
    .license-agreement h4{margin:24px 0 8px;font-size:15px;color:#1f2937}
    .agree-row{display:flex;align-items:center;gap:9px;margin-top:22px;color:#1f2937}
    .agree-row input{width:16px;height:16px}
    .env-target{margin-top:16px}
    .env-result{margin:18px 0 0}
    .field input,.field textarea{border-color:#d9dee8;border-radius:7px;background:#fff}
    .field input:focus,.field textarea:focus{border-color:var(--install-blue);box-shadow:0 0 0 3px rgba(14,165,213,.12)}
    .check-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:18px}
    .check-list li{border-color:#e6eaf1;background:#fbfcfe}
    .step-actions{margin-top:32px;padding-top:20px;border-top:0}
    button,.btn{min-height:42px;border-radius:6px;background:var(--install-blue);box-shadow:none;padding:10px 26px}
    button:hover,.btn:hover{background:#0284b8;transform:none}
    button[disabled]{background:#c7ced9;color:#fff}
    .btn-secondary{background:#fff;color:#344054;border-color:#d0d5dd}
    .btn-secondary:hover{background:#f8fafc}
    .review-item{border-color:#e6eaf1;border-radius:8px;background:#fbfcfe}
    #progressPanel.panel{max-width:820px;margin:18px auto 0}
    .progress{background:#e6eaf1}.bar{background:var(--install-blue)}
    .alert{border-radius:8px}
    @media (max-width:760px){
      .top{padding:0 18px}.page{padding:0 14px}.installer-shell{padding-top:28px}
      .wizard{overflow-x:auto;justify-content:flex-start;padding-bottom:4px}.wizard:before{display:none}.wizard-item{min-width:96px;width:96px}
      #installForm.panel{padding:28px 20px}.license-agreement{height:300px;padding:20px}
      .check-list,.grid,.grid3,.review-grid{grid-template-columns:1fr}
      .step-actions{display:block}.step-actions-right{margin-top:12px}
    }

    :root{--setup-bg:#f8fafc;--setup-card:#fff;--setup-text:#0f172a;--setup-muted:#64748b;--setup-soft:#f8fafc;--setup-line:#e2e8f0;--setup-blue:#2563eb;--setup-blue-dark:#1d4ed8;--setup-green:#22c55e;--setup-red:#ef4444}
    body{min-height:100vh;background:var(--setup-bg);color:var(--setup-text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}
    .page{max-width:720px;margin:0 auto;padding:48px 16px 64px}
    .top{display:none}
    .installer-shell{display:flex;flex-direction:column;padding-top:0}
    .panel{border:1px solid #f1f5f9;border-radius:24px;background:var(--setup-card);box-shadow:0 1px 2px rgba(15,23,42,.04)}
    .installer-shell > .panel:not(#installForm):not(#progressPanel){max-width:none;width:100%;margin:0 0 24px}
    #installForm.panel{width:100%;max-width:none;margin:0;padding:40px 48px}
    .wizard{--progress:0%;position:relative;display:flex;justify-content:space-between;gap:0;width:100%;max-width:none;margin:0 0 56px;padding:0 28px}
    .wizard:before{content:"";position:absolute;left:44px;right:44px;top:14px;height:4px;border-radius:999px;background:linear-gradient(90deg,var(--setup-blue) 0 var(--progress),rgba(226,232,240,.72) var(--progress) 100%);transition:background .5s ease}
    .wizard-item{position:relative;z-index:1;width:auto;min-height:58px;padding:0;border:0;background:transparent;box-shadow:none;text-align:center;color:#94a3b8}
    .wizard-item:before{content:attr(data-step-nav);position:static;display:grid;place-items:center;width:32px;height:32px;margin:0 auto 10px;border:2px solid var(--setup-line);border-radius:50%;background:#fff;color:#94a3b8;font-size:13px;font-weight:800;box-shadow:0 1px 2px rgba(15,23,42,.06)}
    .wizard-item strong{display:block;margin:0;font-size:13px;line-height:1;font-weight:600;color:#94a3b8;white-space:nowrap}
    .wizard-item span{display:none}
    .wizard-item.active,.wizard-item.done{border:0;background:transparent;box-shadow:none}
    .wizard-item.active:before,.wizard-item.done:before{border-color:var(--setup-blue);background:var(--setup-blue);color:#fff;box-shadow:0 1px 2px rgba(15,23,42,.08)}
    .wizard-item.done:before{content:"✓";font-size:14px}
    .wizard-item.active strong{color:#1d4ed8;font-weight:800}
    .wizard-item.done strong{color:#475569}
    .form-step.active{display:block;animation:slideUp .4s cubic-bezier(.16,1,.3,1)}
    @keyframes slideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .step-heading{margin:0 0 40px;padding:0;border:0;text-align:center}
    .step-heading h2{margin:0 0 12px;font-size:24px;line-height:1.25;font-weight:800;letter-spacing:-.02em;color:#0f172a}
    .step-heading .muted{font-size:14px;color:#64748b}
    .grid,.grid3{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
    .form-step[data-step="3"] .grid{grid-template-columns:1fr}
    .field{margin:0}
    .field label{margin:0 0 12px;font-size:15px;font-weight:600;color:#1e293b}
    .field input,.field textarea{width:100%;border:1px solid var(--setup-line);border-radius:12px;background:var(--setup-soft);padding:13px 16px;font-size:15px;color:#0f172a;outline:none;transition:.18s ease}
    .field input:hover,.field textarea:hover{border-color:#cbd5e1;background:#fff}
    .field input:focus,.field textarea:focus{border-color:var(--setup-blue);background:#fff;box-shadow:0 0 0 1px var(--setup-blue)}
    .field small{display:flex;gap:6px;margin-top:10px;font-size:13px;color:#64748b}
    .field small:before{content:"•";color:#94a3b8}
    .license-agreement{height:256px;margin-bottom:0;overflow:auto;border:1px solid var(--setup-line);border-radius:16px;background:var(--setup-soft);padding:24px;color:#475569;font-size:14px;line-height:1.8}
    .license-agreement::-webkit-scrollbar{width:6px}.license-agreement::-webkit-scrollbar-track{background:transparent}.license-agreement::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:20px}
    .license-agreement h3{margin:0 0 20px;text-align:center;font-size:16px;color:#0f172a}
    .license-agreement h4{margin:20px 0 8px;font-size:15px;color:#0f172a}
    .agree-row{display:flex;align-items:center;gap:9px;margin-top:18px;font-size:14px;color:#1e293b}
    .agree-row input{width:16px;height:16px;accent-color:var(--setup-blue)}
    .check-list{display:flex;flex-direction:column;gap:12px;margin:0 0 24px;padding:0;list-style:none}
    .check-list li{display:flex;align-items:center;justify-content:space-between;gap:16px;border:1px solid #f1f5f9;border-radius:16px;background:var(--setup-soft);padding:16px;font-size:15px;font-weight:600;color:#334155}
    .check-list li .muted{font-size:14px;font-weight:500;color:#0f172a}
    .check-list li:after{content:"✓";display:grid;place-items:center;flex:0 0 auto;width:20px;height:20px;border-radius:50%;background:var(--setup-green);color:#fff;font-size:13px;font-weight:900}
    .check-list li.check-fail:after{content:"!";background:var(--setup-red)}
    .env-target{margin:0 0 14px;font-size:13px;color:#64748b}
    .env-result{margin:18px 0 0}
    code{border-radius:6px;background:#f1f5f9;color:#334155;padding:2px 6px}
    .alert{border-radius:14px;padding:13px 16px;margin-bottom:14px;font-size:14px}
    .alert-success{border-color:#bbf7d0;background:#f0fdf4;color:#166534}
    .alert-error{border-color:#fecaca;background:#fef2f2;color:#991b1b}
    .alert-warn{border-color:#fed7aa;background:#fff7ed;color:#9a3412}
    .step-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:40px;padding:16px 0 0;border:0}
    .step-actions-right{display:flex;align-items:center;justify-content:flex-end;gap:12px;margin-left:auto}
    button,.btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border:0;border-radius:12px;background:var(--setup-blue);box-shadow:none;padding:10px 32px;color:#fff;font-size:15px;font-weight:600;text-decoration:none;transition:.18s ease;cursor:pointer}
    button:hover,.btn:hover{background:var(--setup-blue-dark);transform:none}
    button[disabled]{background:#cbd5e1;color:#fff;cursor:not-allowed;opacity:1}
    .btn-secondary{border:1px solid var(--setup-line);background:#fff;color:#334155;padding:10px 24px}
    .btn-secondary:hover{background:#f8fafc;color:#0f172a}
    .review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:8px}
    .review-item{border:1px solid #f1f5f9;border-radius:16px;background:var(--setup-soft);padding:16px}
    .review-item span{display:block;margin-bottom:4px;color:#64748b;font-size:13px}
    .review-item strong{display:block;color:#0f172a;word-break:break-all}
    .form-step[data-step="5"] > h2{margin:0 0 18px;font-size:16px;color:#0f172a}
    .form-step[data-step="5"] > h2[style]{margin-top:28px!important}
    #progressPanel.panel{width:100%;max-width:none;margin:24px 0 0;padding:28px}
    .progress{height:10px;border-radius:999px;background:#e2e8f0}
    .bar{height:100%;border-radius:999px;background:var(--setup-blue)}
    .log-list{margin:14px 0 0;padding-left:18px;color:#475569}
    @media (max-width:760px){
      .page{padding:32px 16px 48px}
      .wizard{overflow:visible;margin-bottom:42px;padding:0 4px}
      .wizard:before{left:20px;right:20px}
      .wizard-item strong{display:none}
      #installForm.panel{padding:30px 22px;border-radius:22px}
      .grid,.grid3,.review-grid{grid-template-columns:1fr}
      .step-heading{margin-bottom:30px}
      .step-actions{display:flex;align-items:stretch}
      .step-actions > .muted{display:none}
      .step-actions-right{margin-top:0}
      button,.btn{width:100%}
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="panel top">
      <div class="brand-row">
        <div class="brand-mark">善</div>
        <div>
          <h1>系统在线安装器</h1>
          <div class="muted">先完成环境检测，再进行授权校验、数据库初始化与系统配置。</div>
        </div>
      </div>
      <div class="badge">Installer <?php echo installer_h(INSTALLER_VERSION); ?></div>
    </div>
    <div class="installer-shell">

    <?php if ($previewMode && !empty($realInstalledInfo['installed'])): ?>
      <div class="panel">
        <div class="alert alert-warn">
          <strong>预览模式</strong><br>
          当前环境已经安装，页面仅用于查看安装器界面；真正开始安装仍会被后端保护拦截。
        </div>
      </div>
    <?php endif; ?>

    <?php if ($installedInfo['installed']): ?>
      <div class="panel">
        <div class="alert alert-warn">
          <strong>当前站点已安装</strong><br>
          <?php echo installer_h($installedInfo['message']); ?>
        </div>
        <?php if (!empty($installedInfo['data'])): ?>
          <div class="muted">安装时间：<?php echo installer_h($installedInfo['data']['installed_at'] ?? ''); ?></div>
          <div class="muted">版本：<?php echo installer_h($installedInfo['data']['release']['version'] ?? ''); ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!$installedInfo['installed']): ?>
      <div class="wizard" id="wizardNav">
        <div class="wizard-item active" data-step-nav="1"><strong>环境检测</strong><span>服务器环境</span></div>
        <div class="wizard-item" data-step-nav="2"><strong>使用协议</strong><span>阅读并同意协议</span></div>
        <div class="wizard-item" data-step-nav="3"><strong>授权验证</strong><span>授权码与授权中心</span></div>
        <div class="wizard-item" data-step-nav="4"><strong>站点信息</strong><span>站点与管理员账号</span></div>
        <div class="wizard-item" data-step-nav="5"><strong>服务配置</strong><span>数据库与 Redis</span></div>
        <div class="wizard-item" data-step-nav="6"><strong>完成安装</strong><span>核对配置并执行</span></div>
      </div>
      <form id="installForm" class="panel">
        <section class="form-step active" data-step="1">
        <div class="step-heading"><h2>环境检测</h2><div class="muted">安装前先确认 PHP 扩展、目录权限和目标路径，检测失败时不能继续。</div></div>
        <ul class="check-list">
          <?php foreach ($requirements as $check): ?>
            <li class="<?php echo $check['ok'] ? 'check-ok' : 'check-fail'; ?>">
              <?php echo installer_h($check['label']); ?>：
              <?php echo $check['ok'] ? '通过' : '失败'; ?>
              <span class="muted"><?php echo installer_h($check['detail']); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="muted env-target">目标安装根目录：<code><?php echo installer_h($state['target_root']); ?></code></p>
        <?php if ($requirementsOk): ?>
          <div class="alert alert-success env-result">环境检测通过，可以继续安装。</div>
        <?php else: ?>
          <div class="alert alert-error env-result">环境检测未通过，请先修复失败项，然后刷新本页面重新检测。</div>
        <?php endif; ?>
        <div class="step-actions"><span class="muted">下一步阅读使用协议</span><div class="step-actions-right"><button type="button" data-next="2" <?php echo $requirementsOk ? '' : 'disabled'; ?>>下一步</button></div></div>
        </section>

        <section class="form-step" data-step="2">
        <div class="step-heading"><h2>使用协议</h2><div class="muted">请仔细阅读以下协议，同意后方可继续安装。</div></div>
        <div class="license-agreement">
          <h3>天天善商城系统使用协议</h3>
          <p>欢迎使用天天善商城系统。安装和使用本系统前，请确认您已获得合法授权，并承诺在授权范围内使用本系统。</p>
          <h4>一、授权范围</h4>
          <p>1. 本系统为商业授权软件，授权码仅限绑定的客户、服务器 IP 及授权范围内使用。</p>
          <p>2. 未经授权，不得将本系统源代码、安装包、数据库结构或核心文件转售、转授权、公开传播或共享给第三方。</p>
          <p>3. 授权中心会在安装及运行期间校验授权码状态和服务器授权信息。</p>
          <h4>二、使用限制</h4>
          <p>1. 不得删除、绕过或破坏系统授权校验逻辑。</p>
          <p>2. 不得利用本系统从事违法违规业务，因客户业务内容产生的责任由客户自行承担。</p>
          <p>3. 系统插件、增值模块、前端包及更新服务以实际授权范围为准。</p>
          <h4>三、安装确认</h4>
          <p>继续安装即表示您已阅读、理解并同意本协议全部内容。</p>
        </div>
        <label class="agree-row">
          <input id="agreementAccepted" name="agreement_accepted" type="checkbox" value="1" required>
          <span>我已阅读并同意《天天善商城系统使用协议》</span>
        </label>

        <div class="step-actions"><button class="btn-secondary" type="button" data-prev="1">上一步</button><div class="step-actions-right"><button id="agreementNextBtn" type="button" data-next="3" disabled>同意并继续</button></div></div>
        </section>

        <section class="form-step" data-step="3">
        <div class="step-heading"><h2>授权信息</h2><div class="muted">填写客户授权码和授权中心地址。</div></div>
        <div class="grid">
          <div class="field">
            <label for="license_key">授权码</label>
            <input id="license_key" name="license_key" autocomplete="off" required>
            <small>授权中心只校验授权码状态和当前服务器公网 IP。</small>
          </div>
          <div class="field">
            <label for="auth_server">授权中心地址</label>
            <input id="auth_server" name="auth_server" value="<?php echo installer_h(INSTALLER_DEFAULT_AUTH_SERVER); ?>" required>
            <small>正式交付时改成你的授权服务器域名。</small>
          </div>
        </div>

        <div class="step-actions"><button class="btn-secondary" type="button" data-prev="2">上一步</button><div class="step-actions-right"><button type="button" data-next="4">下一步</button></div></div>
        </section>

        <section class="form-step" data-step="4">
        <div class="step-heading"><h2>站点与管理员</h2><div class="muted">设置安装完成后的站点名称和后台登录账号。</div></div>
        <div class="grid">
          <div class="field">
            <label for="site_name">站点名称</label>
            <input id="site_name" name="site_name" value="商城系统" required>
          </div>
          <div class="field">
            <label for="admin_username">管理员账号</label>
            <input id="admin_username" name="admin_username" value="admin" required>
          </div>
        </div>
        <div class="grid">
          <div class="field">
            <label for="admin_password">管理员密码</label>
            <input id="admin_password" name="admin_password" type="password" required>
          </div>
          <div class="field">
            <label for="admin_password_confirm">确认密码</label>
            <input id="admin_password_confirm" name="admin_password_confirm" type="password" required>
          </div>
        </div>

        <div class="step-actions"><button class="btn-secondary" type="button" data-prev="3">上一步</button><div class="step-actions-right"><button type="button" data-next="5">下一步</button></div></div>
        </section>

        <section class="form-step" data-step="5">
        <div class="step-heading"><h2>数据库与 Redis</h2><div class="muted">数据库必须允许当前服务器连接；Redis 可使用本机默认配置。</div></div>
        <h2>数据库配置</h2>
        <div class="grid3">
          <div class="field">
            <label for="db_host">数据库主机</label>
            <input id="db_host" name="db_host" value="<?php echo installer_h($envDefaults['DATABASE_HOSTNAME']); ?>" required>
          </div>
          <div class="field">
            <label for="db_port">数据库端口</label>
            <input id="db_port" name="db_port" value="<?php echo installer_h($envDefaults['DATABASE_HOSTPORT']); ?>" required>
          </div>
          <div class="field">
            <label for="db_name">数据库名</label>
            <input id="db_name" name="db_name" value="<?php echo installer_h($envDefaults['DATABASE_DATABASE']); ?>" required>
          </div>
        </div>
        <div class="grid">
          <div class="field">
            <label for="db_user">数据库账号</label>
            <input id="db_user" name="db_user" value="<?php echo installer_h($envDefaults['DATABASE_USERNAME']); ?>" required>
          </div>
          <div class="field">
            <label for="db_password">数据库密码</label>
            <input id="db_password" name="db_password" type="password" value="<?php echo installer_h($envDefaults['DATABASE_PASSWORD']); ?>">
          </div>
        </div>
        <div class="grid">
          <div class="field">
            <label for="db_prefix">数据表前缀</label>
            <input id="db_prefix" name="db_prefix" value="<?php echo installer_h($envDefaults['DATABASE_PREFIX']); ?>" required>
          </div>
        </div>

        <h2 style="margin-top:22px">Redis 配置</h2>
        <div class="grid3">
          <div class="field">
            <label for="redis_host">Redis 主机</label>
            <input id="redis_host" name="redis_host" value="<?php echo installer_h($envDefaults['REDIS_HOST']); ?>" required>
          </div>
          <div class="field">
            <label for="redis_port">Redis 端口</label>
            <input id="redis_port" name="redis_port" value="<?php echo installer_h($envDefaults['REDIS_PORT']); ?>" required>
          </div>
          <div class="field">
            <label for="redis_password">Redis 密码</label>
            <input id="redis_password" name="redis_password" type="password" value="<?php echo installer_h($envDefaults['REDIS_PASSWORD']); ?>">
          </div>
        </div>

        <div class="step-actions"><button class="btn-secondary" type="button" data-prev="4">上一步</button><div class="step-actions-right"><button type="button" data-next="6">下一步</button></div></div>
        </section>

        <section class="form-step" data-step="6">
        <div class="step-heading"><h2>确认并安装</h2><div class="muted">确认以下关键配置，点击开始安装后将创建数据库、导入 SQL 并写入系统配置。</div></div>
        <div class="review-grid">
          <div class="review-item"><span>授权码</span><strong id="review_license_key">-</strong></div>
          <div class="review-item"><span>站点名称</span><strong id="review_site_name">-</strong></div>
          <div class="review-item"><span>管理员账号</span><strong id="review_admin_username">-</strong></div>
          <div class="review-item"><span>数据库</span><strong id="review_database">-</strong></div>
          <div class="review-item"><span>数据表前缀</span><strong id="review_db_prefix">-</strong></div>
          <div class="review-item"><span>Redis</span><strong id="review_redis">-</strong></div>
        </div>
        <input type="hidden" name="product_code" value="<?php echo installer_h(INSTALLER_DEFAULT_PRODUCT_CODE); ?>">
        <input type="hidden" name="product_name" value="<?php echo installer_h(INSTALLER_DEFAULT_PRODUCT_NAME); ?>">
        <input type="hidden" name="package_id" value="<?php echo installer_h(INSTALLER_DEFAULT_PACKAGE_ID); ?>">
        <input type="hidden" name="package_version" value="<?php echo installer_h(INSTALLER_DEFAULT_PACKAGE_VERSION); ?>">
        <input type="hidden" name="channel" value="<?php echo installer_h(INSTALLER_DEFAULT_CHANNEL); ?>">
        <input type="hidden" name="public_key" value="<?php echo installer_h(INSTALLER_DEFAULT_PUBLIC_KEY); ?>">
        <input type="hidden" name="package_secret" value="<?php echo installer_h(INSTALLER_DEFAULT_PACKAGE_SECRET); ?>">

        <div class="step-actions">
          <button class="btn-secondary" type="button" data-prev="5">上一步</button>
          <div class="step-actions-right">
            <span class="muted">安装过程中不要关闭页面。</span>
            <button id="startBtn" type="submit">开始安装</button>
          </div>
        </div>
        </section>
      </form>

      <div id="progressPanel" class="panel hidden">
        <h2>安装进度</h2>
        <div class="step-row">
          <strong id="stepTitle">准备安装</strong>
          <span id="percentText">0%</span>
        </div>
        <div class="progress"><div id="bar" class="bar"></div></div>
        <ul id="logList" class="log-list" style="margin-top:14px"></ul>
        <div id="resultBox" class="hidden" style="margin-top:16px"></div>
      </div>
    <?php endif; ?>
    </div>
  </div>

  <script>
    const form = document.getElementById('installForm');
    const progressPanel = document.getElementById('progressPanel');
    const startBtn = document.getElementById('startBtn');
    const stepTitle = document.getElementById('stepTitle');
    const percentText = document.getElementById('percentText');
    const bar = document.getElementById('bar');
    const logList = document.getElementById('logList');
    const resultBox = document.getElementById('resultBox');
    const formSteps = Array.from(document.querySelectorAll('.form-step'));
    const wizardItems = Array.from(document.querySelectorAll('[data-step-nav]'));
    const agreementAccepted = document.getElementById('agreementAccepted');
    const agreementNextBtn = document.getElementById('agreementNextBtn');
    const requirementsOk = <?php echo $requirementsOk ? 'true' : 'false'; ?>;
    let currentFormStep = 1;

    function updateWizardProgress() {
      const wizard = document.getElementById('wizardNav');
      if (!wizard || wizardItems.length <= 1) return;
      const progress = ((currentFormStep - 1) / (wizardItems.length - 1)) * 100;
      wizard.style.setProperty('--progress', progress.toFixed(2) + '%');
    }

    function showFormStep(step) {
      currentFormStep = Number(step);
      formSteps.forEach((item) => item.classList.toggle('active', Number(item.dataset.step) === currentFormStep));
      wizardItems.forEach((item) => {
        const itemStep = Number(item.dataset.stepNav);
        item.classList.toggle('active', itemStep === currentFormStep);
        item.classList.toggle('done', itemStep < currentFormStep);
      });
      updateWizardProgress();
      if (currentFormStep === 6) updateReview();
      window.scrollTo({top: document.getElementById('wizardNav').offsetTop - 16, behavior: 'smooth'});
    }

    function validateStep(step) {
      const section = document.querySelector('.form-step[data-step="' + step + '"]');
      if (!section) return false;
      if (Number(step) === 1 && !requirementsOk) {
        alert('环境检测未通过，请先修复失败项，然后刷新本页面重新检测。');
        return false;
      }
      const fields = Array.from(section.querySelectorAll('input[required], textarea[required]'));
      for (const field of fields) {
        if (!field.reportValidity()) return false;
      }
      if (Number(step) === 2 && agreementAccepted && !agreementAccepted.checked) {
        agreementAccepted.reportValidity();
        return false;
      }
      if (Number(step) === 4 && form.admin_password.value !== form.admin_password_confirm.value) {
        form.admin_password_confirm.setCustomValidity('两次输入的管理员密码不一致');
        form.admin_password_confirm.reportValidity();
        return false;
      }
      form.admin_password_confirm.setCustomValidity('');
      return true;
    }

    function maskLicense(value) {
      value = String(value || '');
      if (value.length <= 8) return value || '-';
      return value.slice(0, 4) + '****' + value.slice(-4);
    }

    function updateReview() {
      document.getElementById('review_license_key').textContent = maskLicense(form.license_key.value);
      document.getElementById('review_site_name').textContent = form.site_name.value || '-';
      document.getElementById('review_admin_username').textContent = form.admin_username.value || '-';
      document.getElementById('review_database').textContent = (form.db_host.value || '-') + ':' + (form.db_port.value || '-') + ' / ' + (form.db_name.value || '-');
      document.getElementById('review_db_prefix').textContent = form.db_prefix.value || '-';
      document.getElementById('review_redis').textContent = (form.redis_host.value || '-') + ':' + (form.redis_port.value || '-');
    }

    document.querySelectorAll('[data-next]').forEach((button) => {
      button.addEventListener('click', () => {
        if (validateStep(currentFormStep)) showFormStep(button.dataset.next);
      });
    });
    document.querySelectorAll('[data-prev]').forEach((button) => {
      button.addEventListener('click', () => showFormStep(button.dataset.prev));
    });
    if (agreementAccepted && agreementNextBtn) {
      agreementAccepted.addEventListener('change', () => {
        agreementNextBtn.disabled = !agreementAccepted.checked;
      });
    }
    updateWizardProgress();

    function formPayload() {
      const data = {};
      new FormData(form).forEach((value, key) => {
        data[key] = value;
      });
      return data;
    }

    function addLog(text, type) {
      const li = document.createElement('li');
      li.textContent = text;
      if (type === 'error') li.className = 'check-fail';
      if (type === 'ok') li.className = 'check-ok';
      logList.appendChild(li);
    }

    function setProgress(title, percent) {
      stepTitle.textContent = title;
      percentText.textContent = percent + '%';
      bar.style.width = percent + '%';
    }

    async function callStep(action, payload) {
      const res = await fetch('?action=' + encodeURIComponent(action), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload || {})
      });
      const text = await res.text();
      let json = null;
      try {
        json = JSON.parse(text);
      } catch (err) {
        const brief = text.replace(/\s+/g, ' ').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 240);
        throw new Error('服务端返回了非 JSON 内容' + (res.status ? '（HTTP ' + res.status + '）' : '') + '：' + (brief || text.slice(0, 120)));
      }
      if (!json || Number(json.code) !== 200) {
        throw new Error((json && json.msg) ? json.msg : '请求失败');
      }
      return json;
    }

    if (form) {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        progressPanel.classList.remove('hidden');
        resultBox.className = 'hidden';
        resultBox.innerHTML = '';
        logList.innerHTML = '';
        startBtn.disabled = true;

        const steps = [
          ['verify', '校验授权码 / IP', 25],
          ['database', '创建数据库并导入 SQL', 65],
          ['config', '写入配置和授权缓存', 88],
          ['confirm', '确认安装结果', 100]
        ];

        try {
          setProgress('创建安装任务', 5);
          addLog('创建安装任务');
          const start = await callStep('start', formPayload());
          const jobId = start.data.job_id;
          addLog(start.msg, 'ok');

          let finalData = {};
          for (const step of steps) {
            setProgress(step[1], step[2]);
            addLog(step[1]);
            const res = await callStep(step[0], {job_id: jobId});
            finalData = res.data || {};
            addLog(res.msg || (step[1] + '完成'), 'ok');
          }

          setProgress('安装完成', 100);
          const warning = finalData.warning ? '<div class="alert alert-warn">' + finalData.warning + '</div>' : '';
          resultBox.className = '';
          resultBox.innerHTML = warning + '<div class="alert alert-success"><strong>安装完成</strong><br>可以进入后台登录。</div>' +
            '<div class="actions">' +
            '<a class="btn" href="' + finalData.admin_url + '" target="_blank" rel="noopener">打开 Vue 管理后台</a>' +
            '<a class="btn btn-secondary" href="' + finalData.legacy_admin_url + '" target="_blank" rel="noopener">打开旧版后台入口</a>' +
            '</div>';
        } catch (err) {
          addLog(err.message || String(err), 'error');
          resultBox.className = '';
          resultBox.innerHTML = '<div class="alert alert-error"><strong>安装失败</strong><br>' + (err.message || String(err)) + '</div>';
          startBtn.disabled = false;
        }
      });
    }
  </script>
</body>
</html>
