<?php

namespace app\common;

use think\facade\Db;

class SystemUpdateService
{
    const JOB_VERSION = '1.0';

    public static function getStatus(): array
    {
        $package = self::packageConfig();
        $cache = self::readJson(self::paths()['license_cache']);
        $latestJob = self::latestJob();

        return self::ok([
            'enabled' => empty($package['license_required']) ? 0 : 1,
            'package' => self::publicPackageInfo($package),
            'license' => self::publicLicenseInfo($cache),
            'latest_job' => $latestJob ?: null,
            'server_time' => date('Y-m-d H:i:s'),
            'writable' => self::checkWritable(),
        ]);
    }

    public static function checkUpdate(): array
    {
        $ready = self::ensureReady();
        if (!$ready['status']) {
            return $ready;
        }

        $payload = self::licensePayload($ready['package'], $ready['cache'], [
            'package_type' => 'upgrade',
            'channel' => strval($ready['package']['channel'] ?? 'stable'),
            'current_version' => strval($ready['package']['package_version'] ?? ''),
        ]);
        $json = self::postJson(self::endpoint(self::licenseServer($ready['package'], $ready['cache']), 'latest-release'), $payload);
        if (intval($json['code'] ?? 0) !== 200) {
            return self::fail(strval($json['msg'] ?? '检查更新失败'), strval($json['data']['reason'] ?? 'check_update_failed'), $json['data'] ?? []);
        }

        $release = is_array($json['data']['release'] ?? null) ? $json['data']['release'] : [];
        $currentVersion = strval($ready['package']['package_version'] ?? '');
        $targetVersion = strval($release['to_version'] ?? ($release['version'] ?? ''));
        $hasUpdate = self::hasUpdate($currentVersion, $targetVersion) ? 1 : 0;

        return self::ok([
            'current' => self::publicPackageInfo($ready['package']),
            'release' => $release,
            'license' => is_array($json['data']['license'] ?? null) ? $json['data']['license'] : [],
            'has_update' => $hasUpdate,
            'checked_at' => date('Y-m-d H:i:s'),
        ], $hasUpdate ? '发现可用升级包' : '当前已是最新版本');
    }

    public static function startUpdate(array $options = []): array
    {
        $running = self::runningJob();
        if ($running) {
            return self::fail('已有升级任务正在执行，请先等待完成或查看进度', 'update_job_running', ['job' => $running]);
        }

        $checked = self::checkUpdate();
        if (!$checked['status']) {
            return $checked;
        }

        $release = is_array($checked['data']['release'] ?? null) ? $checked['data']['release'] : [];
        if (empty($release['id'])) {
            return self::fail('授权中心未返回可升级版本', 'release_missing');
        }
        if (empty($checked['data']['has_update']) && empty($options['force'])) {
            return self::fail('当前版本无需升级', 'update_not_required', $checked['data']);
        }

        $jobId = self::createJobId();
        $paths = self::paths($jobId);
        self::ensureDir($paths['job_dir']);
        self::ensureDir($paths['download_dir']);
        self::ensureDir($paths['backup_dir']);

        $job = [
            'job_version' => self::JOB_VERSION,
            'job_id' => $jobId,
            'status' => 'queued',
            'percent' => 0,
            'message' => '升级任务已创建',
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'started_at' => '',
            'finished_at' => '',
            'package' => $checked['data']['current'],
            'release' => $release,
            'license' => is_array($checked['data']['license'] ?? null) ? $checked['data']['license'] : [],
            'paths' => [
                'download' => $paths['download_zip'],
                'extract_dir' => $paths['extract_dir'],
                'backup_dir' => $paths['backup_dir'],
            ],
            'steps' => self::initialSteps(),
            'log' => [],
            'result' => [],
        ];
        self::appendLog($job, '升级任务已创建，目标版本：' . self::releaseVersion($release));
        self::saveJob($job);
        self::writeJson(self::paths()['latest_job'], ['job_id' => $jobId, 'updated_at' => date('c')]);

        return self::ok(['job' => self::publicJob($job)], '升级任务已创建');
    }

    public static function getProgress(string $jobId = ''): array
    {
        $job = self::loadJob($jobId);
        if (!$job) {
            return self::fail('升级任务不存在', 'job_not_found');
        }
        return self::ok(['job' => self::publicJob($job)]);
    }

    public static function runStep(string $jobId): array
    {
        $job = self::loadJob($jobId);
        if (!$job) {
            return self::fail('升级任务不存在', 'job_not_found');
        }
        if (in_array(strval($job['status'] ?? ''), ['completed', 'failed'], true)) {
            return self::ok(['job' => self::publicJob($job)], '升级任务已结束');
        }

        $stepKey = self::nextStepKey($job);
        if ($stepKey === '') {
            self::completeJob($job);
            self::saveJob($job);
            return self::ok(['job' => self::publicJob($job)], '升级完成');
        }

        self::startStep($job, $stepKey);
        try {
            switch ($stepKey) {
                case 'ticket':
                    self::stepTicket($job);
                    break;
                case 'download':
                    self::stepDownload($job);
                    break;
                case 'verify':
                    self::stepVerify($job);
                    break;
                case 'extract':
                    self::stepExtract($job);
                    break;
                case 'apply_files':
                    self::stepApplyFiles($job);
                    break;
                case 'database':
                    self::stepDatabase($job);
                    break;
                case 'finalize':
                    self::stepFinalize($job);
                    break;
                default:
                    throw new \RuntimeException('未知升级步骤：' . $stepKey);
            }
            self::finishStep($job, $stepKey);
            if (self::nextStepKey($job) === '') {
                self::completeJob($job);
            }
            self::saveJob($job);
            return self::ok(['job' => self::publicJob($job)], '步骤执行完成');
        } catch (\Throwable $e) {
            self::failJob($job, $e->getMessage(), $stepKey);
            self::saveJob($job);
            return self::fail($e->getMessage(), 'update_step_failed', ['job' => self::publicJob($job)]);
        }
    }

    private static function stepTicket(array &$job): void
    {
        $ready = self::ensureReady();
        if (!$ready['status']) {
            throw new \RuntimeException($ready['msg']);
        }

        $releaseId = intval($job['release']['id'] ?? 0);
        if ($releaseId <= 0) {
            throw new \RuntimeException('缺少发布版本 ID');
        }

        $payload = self::licensePayload($ready['package'], $ready['cache'], [
            'package_type' => 'upgrade',
            'release_id' => $releaseId,
            'channel' => strval($job['release']['channel'] ?? ($ready['package']['channel'] ?? 'stable')),
            'current_version' => strval($ready['package']['package_version'] ?? ''),
        ]);
        $json = self::postJson(self::endpoint(self::licenseServer($ready['package'], $ready['cache']), 'download-ticket'), $payload);
        if (intval($json['code'] ?? 0) !== 200) {
            throw new \RuntimeException(strval($json['msg'] ?? '获取下载凭证失败'));
        }
        $ticket = is_array($json['data'] ?? null) ? $json['data'] : [];
        if (trim(strval($ticket['download_url'] ?? '')) === '') {
            throw new \RuntimeException('授权中心未返回下载地址');
        }

        $job['download_ticket'] = $ticket;
        self::appendLog($job, '下载凭证已生成，有效期：' . strval($ticket['expires_at_text'] ?? ''));
    }

    private static function stepDownload(array &$job): void
    {
        $downloadUrl = trim(strval($job['download_ticket']['download_url'] ?? ''));
        if ($downloadUrl === '') {
            throw new \RuntimeException('缺少下载地址，请先获取下载凭证');
        }

        $downloadPath = strval($job['paths']['download'] ?? '');
        if ($downloadPath === '') {
            throw new \RuntimeException('缺少本地下载路径');
        }
        self::ensureDir(dirname($downloadPath));

        $download = self::downloadFile($downloadUrl, $downloadPath);
        $expectedSha = trim(strval($job['download_ticket']['release']['file_sha256'] ?? ($job['release']['file_sha256'] ?? '')));
        if ($expectedSha !== '' && !hash_equals(strtolower($expectedSha), strtolower(strval($download['sha256'])))) {
            throw new \RuntimeException('升级包 SHA256 校验失败');
        }

        $job['download'] = $download;
        self::appendLog($job, '升级包下载完成，大小：' . self::formatBytes(intval($download['size'] ?? 0)));
    }

    private static function stepVerify(array &$job): void
    {
        $downloadPath = strval($job['paths']['download'] ?? '');
        if (!is_file($downloadPath)) {
            throw new \RuntimeException('升级包不存在，请重新下载');
        }

        $zip = new \ZipArchive();
        if ($zip->open($downloadPath) !== true) {
            throw new \RuntimeException('升级包不是有效 zip 文件');
        }
        $hasManifest = $zip->locateName('RELEASE-MANIFEST.json', \ZipArchive::FL_NOCASE) !== false;
        $hasFilesZip = $zip->locateName('files.zip', \ZipArchive::FL_NOCASE) !== false;
        $zip->close();

        $job['verify'] = [
            'sha256' => hash_file('sha256', $downloadPath),
            'has_manifest' => $hasManifest ? 1 : 0,
            'has_files_zip' => $hasFilesZip ? 1 : 0,
        ];
        self::appendLog($job, '升级包结构校验通过');
    }

    private static function stepExtract(array &$job): void
    {
        $downloadPath = strval($job['paths']['download'] ?? '');
        $extractDir = strval($job['paths']['extract_dir'] ?? '');
        if (!is_file($downloadPath)) {
            throw new \RuntimeException('升级包不存在，请重新下载');
        }
        if ($extractDir === '') {
            throw new \RuntimeException('缺少解压目录');
        }

        self::removeDir($extractDir);
        self::ensureDir($extractDir);
        self::extractZip($downloadPath, $extractDir);

        $manifestPath = self::findFileBySuffix($extractDir, ['release-manifest.json', 'manifest.json']);
        $manifest = self::readJson($manifestPath);
        if ($manifest) {
            $package = self::packageConfig();
            if (!empty($manifest['product_code']) && strcasecmp(strval($manifest['product_code']), strval($package['product_code'] ?? '')) !== 0) {
                throw new \RuntimeException('升级包产品标识不匹配');
            }
            if (!empty($manifest['package_type']) && strval($manifest['package_type']) !== 'upgrade') {
                throw new \RuntimeException('当前发布包不是升级包');
            }
            if (!empty($manifest['min_php']) && version_compare(PHP_VERSION, strval($manifest['min_php']), '<')) {
                throw new \RuntimeException('当前 PHP 版本低于升级包要求：' . $manifest['min_php']);
            }
        }

        $filesZip = self::findFileBySuffix($extractDir, ['files.zip', 'source.zip', 'code.zip']);
        $sqlFile = self::findFileBySuffix($extractDir, ['database/install.sql', 'database/upgrade.sql', 'upgrade.sql', 'install.sql']);

        if ($filesZip === '') {
            $filesZip = $downloadPath;
        }
        if (!is_file($filesZip)) {
            throw new \RuntimeException('升级包中未找到代码压缩包');
        }
        if ($manifest) {
            $expectedFilesSha = trim(strval($manifest['files_sha256'] ?? ''));
            if ($expectedFilesSha !== '' && !hash_equals(strtolower($expectedFilesSha), strtolower(hash_file('sha256', $filesZip)))) {
                throw new \RuntimeException('代码包 files.zip SHA256 校验失败');
            }
            $expectedSqlSha = trim(strval($manifest['sql_sha256'] ?? ''));
            if ($expectedSqlSha !== '' && $sqlFile !== '' && !hash_equals(strtolower($expectedSqlSha), strtolower(hash_file('sha256', $sqlFile)))) {
                throw new \RuntimeException('数据库升级 SQL SHA256 校验失败');
            }
        }

        $job['manifest'] = $manifest;
        $job['manifest_path'] = $manifestPath;
        $job['files_zip'] = $filesZip;
        $job['sql_file'] = $sqlFile;
        self::appendLog($job, '升级包已解压，代码包：' . basename($filesZip));
    }

    private static function stepApplyFiles(array &$job): void
    {
        $filesZip = strval($job['files_zip'] ?? '');
        if (!is_file($filesZip)) {
            throw new \RuntimeException('代码包不存在，请重新解压');
        }
        $backupDir = strval($job['paths']['backup_dir'] ?? '');
        if ($backupDir === '') {
            throw new \RuntimeException('缺少备份目录');
        }
        self::ensureDir($backupDir);

        $stats = self::applyFilesZip($filesZip, self::root(), $backupDir);
        $job['file_apply'] = $stats;
        self::appendLog($job, '文件更新完成：写入 ' . intval($stats['written']) . ' 个，备份 ' . intval($stats['backed_up']) . ' 个');
    }

    private static function stepDatabase(array &$job): void
    {
        $sqlFile = strval($job['sql_file'] ?? '');
        if ($sqlFile === '' || !is_file($sqlFile)) {
            $job['database'] = ['skipped' => 1, 'message' => '升级包未包含 SQL'];
            self::appendLog($job, '升级包未包含 SQL，跳过数据库升级');
            return;
        }

        $prefix = strval(config('database.connections.mysql.prefix', 'ddwx_'));
        $stats = self::executeSqlFile($sqlFile, $prefix);
        $job['database'] = $stats;
        self::appendLog($job, '数据库升级完成，执行语句：' . intval($stats['statements']));
    }

    private static function stepFinalize(array &$job): void
    {
        $package = self::packageConfig();
        $release = is_array($job['release'] ?? null) ? $job['release'] : [];
        $manifest = is_array($job['manifest'] ?? null) ? $job['manifest'] : [];
        $version = self::releaseVersion($release);
        if ($version === '') {
            $version = strval($manifest['to_version'] ?? ($manifest['version'] ?? ''));
        }
        if ($version === '') {
            throw new \RuntimeException('缺少升级后版本号');
        }

        $package['license_required'] = true;
        $package['package_version'] = $version;
        $package['build_id'] = strval($release['build_id'] ?? ($manifest['build_id'] ?? ($package['build_id'] ?? '')));
        $package['channel'] = strval($release['channel'] ?? ($manifest['channel'] ?? ($package['channel'] ?? 'stable')));
        $package['updated_at'] = date('c');
        self::writePackageConfig($package);

        $paths = self::paths();
        $cache = self::readJson($paths['license_cache']);
        if ($cache) {
            $cache['package_version'] = $version;
            $cache['build_id'] = strval($package['build_id'] ?? '');
            $cache['updated_at'] = date('c');
            $cache['last_update'] = [
                'job_id' => strval($job['job_id'] ?? ''),
                'release' => $release,
                'updated_at' => date('c'),
            ];
            self::writeJson($paths['license_cache'], $cache);
        }

        $job['result'] = [
            'version' => $version,
            'build_id' => strval($package['build_id'] ?? ''),
            'backup_dir' => strval($job['paths']['backup_dir'] ?? ''),
        ];
        self::appendLog($job, '版本信息已写入：' . $version);
    }

    private static function ensureReady(): array
    {
        $package = self::packageConfig();
        if (empty($package['license_required'])) {
            return self::fail('当前系统未启用授权包配置，不能在线升级', 'license_package_disabled');
        }
        $paths = self::paths();
        if (!is_file($paths['license_cache'])) {
            return self::fail('本地授权缓存不存在，请先通过 install.php 完成安装', 'license_cache_missing');
        }
        $cache = self::readJson($paths['license_cache']);
        if (!$cache) {
            return self::fail('本地授权缓存损坏，请重新安装或联系服务商', 'license_cache_invalid');
        }
        if (self::licenseServer($package, $cache) === '') {
            return self::fail('缺少授权中心地址，不能在线升级', 'license_server_missing');
        }
        if (trim(strval($cache['license_key'] ?? '')) === '') {
            return self::fail('缺少本地授权码，不能在线升级', 'license_key_missing');
        }

        return ['status' => 1, 'package' => $package, 'cache' => $cache];
    }

    private static function packageConfig(): array
    {
        $path = self::root() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'package.php';
        $defaults = [
            'license_required' => false,
            'product_code' => '',
            'product_name' => '',
            'package_id' => '',
            'package_version' => '',
            'build_id' => '',
            'channel' => 'stable',
            'license_server' => '',
        ];
        if (!is_file($path)) {
            return $defaults;
        }
        $loaded = include $path;
        return is_array($loaded) ? array_merge($defaults, $loaded) : $defaults;
    }

    private static function writePackageConfig(array $config): void
    {
        $path = self::root() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'package.php';
        self::ensureDir(dirname($path));
        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($path, $content) === false) {
            throw new \RuntimeException('写入版本配置失败：' . $path);
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private static function licensePayload(array $package, array $cache, array $extra): array
    {
        return array_merge([
            'license_key' => strval($cache['license_key'] ?? ''),
            'product_code' => strval($package['product_code'] ?? ($cache['product_code'] ?? '')),
            'package_id' => strval($package['package_id'] ?? ($cache['package_id'] ?? '')),
            'package_version' => strval($package['package_version'] ?? ($cache['package_version'] ?? '')),
            'domain' => self::domain(),
            'site_url' => self::baseUrl(),
            'server_ip_detected' => strval($_SERVER['SERVER_ADDR'] ?? ''),
            'machine_fingerprint' => self::machineFingerprint(),
            'php_version' => PHP_VERSION,
            'server_software' => strval($_SERVER['SERVER_SOFTWARE'] ?? ''),
            'database_prefix' => strval(config('database.connections.mysql.prefix', '')),
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
        ], $extra);
    }

    private static function endpoint(string $server, string $action): string
    {
        $server = rtrim($server, '/');
        if (preg_match('/\.php(?:\?.*)?$/i', $server)) {
            return $server . (strpos($server, '?') === false ? '?' : '&') . 'action=' . rawurlencode($action);
        }
        return $server . '/licensecenter.php?action=' . rawurlencode($action);
    }

    private static function postJson(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                throw new \RuntimeException($error ?: '授权中心连接失败');
            }
            $json = json_decode(strval($response), true);
            if (!is_array($json)) {
                throw new \RuntimeException('授权中心返回格式异常');
            }
            return $json;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 30,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('授权中心连接失败');
        }
        $json = json_decode(strval($response), true);
        if (!is_array($json)) {
            throw new \RuntimeException('授权中心返回格式异常');
        }
        return $json;
    }

    private static function downloadFile(string $url, string $target): array
    {
        @unlink($target);
        if (function_exists('curl_init')) {
            $fp = fopen($target, 'wb');
            if (!$fp) {
                throw new \RuntimeException('无法创建下载文件：' . $target);
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 600,
                CURLOPT_FAILONERROR => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $ok = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            curl_close($ch);
            fclose($fp);
            if (!$ok || $httpCode >= 400) {
                @unlink($target);
                throw new \RuntimeException($error ?: '升级包下载失败，HTTP ' . $httpCode);
            }
        } else {
            $context = stream_context_create(['http' => ['timeout' => 600]]);
            $data = @file_get_contents($url, false, $context);
            if ($data === false) {
                throw new \RuntimeException('升级包下载失败');
            }
            if (@file_put_contents($target, $data) === false) {
                throw new \RuntimeException('写入升级包失败：' . $target);
            }
        }

        if (!is_file($target) || filesize($target) <= 0) {
            throw new \RuntimeException('升级包下载结果为空');
        }
        return [
            'path' => $target,
            'size' => filesize($target),
            'sha256' => hash_file('sha256', $target),
            'downloaded_at' => date('c'),
        ];
    }

    private static function extractZip(string $zipPath, string $targetDir): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('无法打开 zip：' . $zipPath);
        }

        $written = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = strval($stat['name'] ?? '');
            if (!self::isSafeRelativePath($name)) {
                $zip->close();
                throw new \RuntimeException('zip 中包含非法路径：' . $name);
            }
            $target = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (substr($name, -1) === '/') {
                self::ensureDir($target);
                continue;
            }
            self::ensureDir(dirname($target));
            $source = $zip->getStream($name);
            if (!$source) {
                $zip->close();
                throw new \RuntimeException('读取 zip 文件失败：' . $name);
            }
            $dest = fopen($target, 'wb');
            if (!$dest) {
                fclose($source);
                $zip->close();
                throw new \RuntimeException('写入解压文件失败：' . $target);
            }
            stream_copy_to_stream($source, $dest);
            fclose($source);
            fclose($dest);
            $written++;
        }
        $zip->close();

        return ['written' => $written];
    }

    private static function applyFilesZip(string $zipPath, string $targetRoot, string $backupDir): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('无法打开代码包：' . $zipPath);
        }

        $written = 0;
        $backedUp = 0;
        $skipped = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', strval($stat['name'] ?? ''));
            if (!self::isSafeRelativePath($name)) {
                $zip->close();
                throw new \RuntimeException('代码包中包含非法路径：' . $name);
            }
            if (self::shouldSkipApplyPath($name)) {
                $skipped++;
                continue;
            }
            $target = rtrim($targetRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (substr($name, -1) === '/') {
                self::ensureDir($target);
                continue;
            }
            self::ensureDir(dirname($target));
            if (is_file($target)) {
                $backupPath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                self::ensureDir(dirname($backupPath));
                if (!@copy($target, $backupPath)) {
                    $zip->close();
                    throw new \RuntimeException('备份文件失败：' . $name);
                }
                $backedUp++;
            }

            $source = $zip->getStream($name);
            if (!$source) {
                $zip->close();
                throw new \RuntimeException('读取代码包文件失败：' . $name);
            }
            $dest = fopen($target, 'wb');
            if (!$dest) {
                fclose($source);
                $zip->close();
                throw new \RuntimeException('写入目标文件失败：' . $target);
            }
            stream_copy_to_stream($source, $dest);
            fclose($source);
            fclose($dest);
            $written++;
        }
        $zip->close();

        return ['written' => $written, 'backed_up' => $backedUp, 'skipped' => $skipped];
    }

    private static function executeSqlFile(string $sqlFile, string $prefix): array
    {
        $handle = fopen($sqlFile, 'rb');
        if (!$handle) {
            throw new \RuntimeException('无法打开升级 SQL：' . $sqlFile);
        }

        $buffer = '';
        $count = 0;
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $escaped = false;
        $lineNumber = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if ($lineNumber === 1) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
                }
                $line = str_replace('__PREFIX__', $prefix, $line);
                if ($prefix !== 'ddwx_') {
                    $line = str_replace('ddwx_', $prefix, $line);
                }
                $trimmed = ltrim($line);
                if (
                    !$inSingle &&
                    !$inDouble &&
                    !$inBacktick &&
                    trim($buffer) === '' &&
                    (
                        strpos($trimmed, '-- ') === 0 ||
                        strpos($trimmed, '#') === 0 ||
                        (strpos($trimmed, '/*') === 0 && strpos($trimmed, '/*!') !== 0)
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
                        if ($statement !== '') {
                            self::assertUpgradeSqlSafe($statement);
                            Db::execute($statement);
                            $count++;
                        }
                        continue;
                    }
                    $buffer .= $char;
                }
            }

            $tail = trim($buffer);
            if ($tail !== '') {
                self::assertUpgradeSqlSafe($tail);
                Db::execute($tail);
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return [
            'statements' => $count,
            'size' => filesize($sqlFile),
            'executed_at' => date('c'),
        ];
    }

    private static function assertUpgradeSqlSafe(string $statement): void
    {
        $normalized = preg_replace('/\s+/', ' ', trim($statement));
        if ($normalized === '') {
            return;
        }
        if (preg_match('/^(DROP\s+DATABASE|DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $normalized)) {
            throw new \RuntimeException('升级 SQL 包含高风险语句，已拒绝执行：' . mb_substr($normalized, 0, 80, 'utf-8'));
        }
    }

    private static function initialSteps(): array
    {
        $items = [
            ['key' => 'ticket', 'title' => '获取下载凭证'],
            ['key' => 'download', 'title' => '下载安装包'],
            ['key' => 'verify', 'title' => '校验安装包'],
            ['key' => 'extract', 'title' => '解压升级包'],
            ['key' => 'apply_files', 'title' => '备份并更新文件'],
            ['key' => 'database', 'title' => '执行数据库升级'],
            ['key' => 'finalize', 'title' => '写入版本信息'],
        ];
        foreach ($items as &$item) {
            $item['status'] = 'pending';
            $item['message'] = '';
            $item['started_at'] = '';
            $item['finished_at'] = '';
        }
        unset($item);
        return $items;
    }

    private static function nextStepKey(array $job): string
    {
        foreach ($job['steps'] ?? [] as $step) {
            if (in_array(strval($step['status'] ?? 'pending'), ['pending', 'running'], true)) {
                return strval($step['key'] ?? '');
            }
        }
        return '';
    }

    private static function startStep(array &$job, string $stepKey): void
    {
        $job['status'] = 'running';
        if (empty($job['started_at'])) {
            $job['started_at'] = date('c');
        }
        foreach ($job['steps'] as &$step) {
            if (strval($step['key'] ?? '') === $stepKey) {
                $step['status'] = 'running';
                $step['started_at'] = $step['started_at'] ?: date('c');
                $step['message'] = '执行中';
                $job['message'] = strval($step['title'] ?? '') . '中';
                self::appendLog($job, strval($step['title'] ?? $stepKey) . '中');
                break;
            }
        }
        unset($step);
        self::updatePercent($job);
        self::saveJob($job);
    }

    private static function finishStep(array &$job, string $stepKey): void
    {
        foreach ($job['steps'] as &$step) {
            if (strval($step['key'] ?? '') === $stepKey) {
                $step['status'] = 'completed';
                $step['finished_at'] = date('c');
                $step['message'] = '完成';
                break;
            }
        }
        unset($step);
        $job['message'] = '步骤执行完成';
        self::updatePercent($job);
    }

    private static function completeJob(array &$job): void
    {
        $job['status'] = 'completed';
        $job['percent'] = 100;
        $job['message'] = '升级完成';
        $job['finished_at'] = date('c');
        $job['updated_at'] = date('c');
        self::appendLog($job, '升级完成');
    }

    private static function failJob(array &$job, string $message, string $stepKey): void
    {
        $job['status'] = 'failed';
        $job['message'] = $message;
        $job['failed_step'] = $stepKey;
        $job['finished_at'] = date('c');
        foreach ($job['steps'] as &$step) {
            if (strval($step['key'] ?? '') === $stepKey) {
                $step['status'] = 'failed';
                $step['message'] = $message;
                $step['finished_at'] = date('c');
                break;
            }
        }
        unset($step);
        self::appendLog($job, $message, 'error');
        self::updatePercent($job);
    }

    private static function updatePercent(array &$job): void
    {
        $steps = $job['steps'] ?? [];
        $total = max(1, count($steps));
        $done = 0;
        foreach ($steps as $step) {
            if (strval($step['status'] ?? '') === 'completed') {
                $done++;
            }
        }
        $job['percent'] = intval(floor($done / $total * 100));
        $job['updated_at'] = date('c');
    }

    private static function appendLog(array &$job, string $message, string $level = 'info'): void
    {
        $log = is_array($job['log'] ?? null) ? $job['log'] : [];
        $log[] = [
            'time' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
        ];
        if (count($log) > 200) {
            $log = array_slice($log, -200);
        }
        $job['log'] = $log;
    }

    private static function loadJob(string $jobId = ''): array
    {
        $jobId = trim($jobId);
        if ($jobId === '') {
            $latest = self::readJson(self::paths()['latest_job']);
            $jobId = strval($latest['job_id'] ?? '');
        }
        if ($jobId === '') {
            return [];
        }
        $path = self::paths($jobId)['job_file'];
        return self::readJson($path);
    }

    private static function saveJob(array $job): void
    {
        $jobId = strval($job['job_id'] ?? '');
        if ($jobId === '') {
            throw new \RuntimeException('缺少升级任务 ID');
        }
        $job['updated_at'] = date('c');
        self::writeJson(self::paths($jobId)['job_file'], $job);
    }

    private static function latestJob(): array
    {
        $job = self::loadJob('');
        return $job ? self::publicJob($job) : [];
    }

    private static function runningJob(): array
    {
        $job = self::latestJob();
        if (!$job) {
            return [];
        }
        if (in_array(strval($job['status'] ?? ''), ['queued', 'running'], true)) {
            return $job;
        }
        return [];
    }

    private static function publicJob(array $job): array
    {
        $copy = $job;
        if (!empty($copy['download_ticket'])) {
            $copy['download_ticket'] = [
                'expires_at' => intval($copy['download_ticket']['expires_at'] ?? 0),
                'expires_at_text' => strval($copy['download_ticket']['expires_at_text'] ?? ''),
            ];
        }
        return $copy;
    }

    private static function publicPackageInfo(array $package): array
    {
        return [
            'license_required' => empty($package['license_required']) ? 0 : 1,
            'product_code' => strval($package['product_code'] ?? ''),
            'product_name' => strval($package['product_name'] ?? ''),
            'package_id' => strval($package['package_id'] ?? ''),
            'package_version' => strval($package['package_version'] ?? ''),
            'build_id' => strval($package['build_id'] ?? ''),
            'channel' => strval($package['channel'] ?? 'stable'),
            'license_server' => self::maskUrl(strval($package['license_server'] ?? '')),
        ];
    }

    private static function publicLicenseInfo(array $cache): array
    {
        return [
            'license_key_mask' => strval($cache['license_key_mask'] ?? ''),
            'verified_at' => strval($cache['verified_at'] ?? ''),
            'confirmed_at' => strval($cache['confirmed_at'] ?? ''),
            'package_version' => strval($cache['package_version'] ?? ''),
            'build_id' => strval($cache['build_id'] ?? ''),
        ];
    }

    private static function checkWritable(): array
    {
        $paths = [
            self::root() . DIRECTORY_SEPARATOR . 'runtime',
            self::root() . DIRECTORY_SEPARATOR . 'config',
        ];
        $items = [];
        foreach ($paths as $path) {
            $items[] = [
                'path' => $path,
                'writable' => is_dir($path) ? (is_writable($path) ? 1 : 0) : (is_writable(dirname($path)) ? 1 : 0),
            ];
        }
        return $items;
    }

    private static function paths(string $jobId = ''): array
    {
        $root = self::root();
        $runtime = $root . DIRECTORY_SEPARATOR . 'runtime';
        $update = $runtime . DIRECTORY_SEPARATOR . 'update';
        $paths = [
            'license_cache' => $runtime . DIRECTORY_SEPARATOR . 'license' . DIRECTORY_SEPARATOR . 'license.cache',
            'latest_job' => $update . DIRECTORY_SEPARATOR . 'latest.json',
            'update_root' => $update,
        ];
        if ($jobId !== '') {
            $jobRoot = $update . DIRECTORY_SEPARATOR . 'jobs' . DIRECTORY_SEPARATOR . $jobId;
            $paths = array_merge($paths, [
                'job_dir' => $jobRoot,
                'job_file' => $jobRoot . DIRECTORY_SEPARATOR . 'job.json',
                'download_dir' => $jobRoot . DIRECTORY_SEPARATOR . 'download',
                'download_zip' => $jobRoot . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR . 'release.zip',
                'extract_dir' => $jobRoot . DIRECTORY_SEPARATOR . 'extract',
                'backup_dir' => $update . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $jobId,
            ]);
        }
        return $paths;
    }

    private static function createJobId(): string
    {
        return 'upd_' . date('YmdHis') . '_' . substr(hash('sha256', uniqid('', true) . random_int(1000, 9999)), 0, 8);
    }

    private static function hasUpdate(string $currentVersion, string $targetVersion): bool
    {
        $currentVersion = trim($currentVersion);
        $targetVersion = trim($targetVersion);
        if ($targetVersion === '') {
            return false;
        }
        if ($currentVersion === '') {
            return true;
        }
        if ($currentVersion === $targetVersion) {
            return false;
        }
        if (preg_match('/^\d+(?:\.\d+)+/', $currentVersion) && preg_match('/^\d+(?:\.\d+)+/', $targetVersion)) {
            return version_compare($targetVersion, $currentVersion, '>');
        }
        return true;
    }

    private static function releaseVersion(array $release): string
    {
        return strval($release['to_version'] ?? ($release['version'] ?? ''));
    }

    private static function licenseServer(array $package, array $cache): string
    {
        return rtrim(strval($cache['license_server'] ?? ($package['license_server'] ?? '')), '/');
    }

    private static function root(): string
    {
        return rtrim(root_path(), '/\\');
    }

    private static function readJson(string $path): array
    {
        if ($path === '' || !is_file($path)) {
            return [];
        }
        $decoded = json_decode(strval(@file_get_contents($path)), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function writeJson(string $path, array $data): void
    {
        self::ensureDir(dirname($path));
        if (@file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL) === false) {
            throw new \RuntimeException('写入文件失败：' . $path);
        }
    }

    private static function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('创建目录失败：' . $dir);
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    private static function findFileBySuffix(string $root, array $suffixes): string
    {
        if (!is_dir($root)) {
            return '';
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($root, '/\\')) + 1));
            $lower = strtolower($relative);
            foreach ($suffixes as $suffix) {
                if (substr($lower, -strlen(strtolower($suffix))) === strtolower($suffix)) {
                    return $file->getPathname();
                }
            }
        }
        return '';
    }

    private static function isSafeRelativePath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || $path[0] === '/' || strpos($path, "\0") !== false) {
            return false;
        }
        if (preg_match('/^[A-Za-z]:\//', $path)) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }
        return true;
    }

    private static function shouldSkipApplyPath(string $path): bool
    {
        $lower = strtolower(trim(str_replace('\\', '/', $path), '/'));
        $skipExact = [
            '.env',
            'release-manifest.json',
            'signature',
            'install.sql',
            'upgrade.sql',
            'database/install.sql',
            'database/upgrade.sql',
            'runtime/install.lock',
            'runtime/license/license.cache',
            'public/install.php',
            'public/licensecenter.php',
        ];
        if (in_array($lower, $skipExact, true)) {
            return true;
        }
        $skipPrefixes = [
            'runtime/',
            'upload/',
            'uploads/',
            'public/upload/',
            'public/uploads/',
            'public/runtime/',
        ];
        foreach ($skipPrefixes as $prefix) {
            if (strpos($lower, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function domain(): string
    {
        $host = strval($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        return strtolower(trim(strval($host)));
    }

    private static function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strval($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = strval($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        return ($https ? 'https' : 'http') . '://' . $host;
    }

    private static function machineFingerprint(): string
    {
        return hash('sha256', implode('|', [
            php_uname('n'),
            self::domain(),
            realpath(root_path()) ?: root_path(),
            strval($_SERVER['SERVER_ADDR'] ?? ''),
        ]));
    }

    private static function maskUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        return preg_replace('#(https?://[^/]+).*#i', '$1', $url);
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = floatval($bytes);
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }
        return round($value, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
    }

    private static function ok(array $data = [], string $msg = 'success'): array
    {
        return ['status' => 1, 'msg' => $msg, 'data' => $data];
    }

    private static function fail(string $msg, string $reason, array $data = []): array
    {
        return ['status' => 0, 'msg' => $msg, 'reason' => $reason, 'data' => $data];
    }
}
