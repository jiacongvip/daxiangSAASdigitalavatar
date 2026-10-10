<?php

namespace app\plugin\zhuibaoagent\common;

use think\facade\Db;
use think\facade\Log;

class ZhuibaoMediaLimitService
{
    private const MAX_DURATION_SEC = 86400;
    private const MAX_TEXT_LENGTH = 100000;
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'm4a', 'aac', 'flac', 'oga', 'ogg', 'opus', 'amr', 'wma', 'weba'];
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', 'ogg', 'ogv', 'wmv', 'flv', '3gp', 'mpeg', 'mpg'];

    public static function normalizeLimitConfig($limits = [], array $config = []): array
    {
        $raw = self::decodeJson($limits);
        if (!is_array($raw)) {
            $raw = [];
        }

        $audioMaxDurationSec = self::firstInt([
            $raw['audio_max_duration_sec'] ?? null,
            $raw['audio_limit_seconds'] ?? null,
            $raw['max_audio_duration_sec'] ?? null,
            $raw['max_duration_sec'] ?? null,
            $config['audio_max_duration_sec'] ?? null,
            $config['audio_limit_seconds'] ?? null,
        ]);
        $textMaxLength = self::firstInt([
            $raw['text_max_length'] ?? null,
            $raw['max_text_length'] ?? null,
            $config['text_max_length'] ?? null,
            $config['max_text_length'] ?? null,
        ]);

        return [
            'audio_max_duration_sec' => min(self::MAX_DURATION_SEC, max(0, $audioMaxDurationSec)),
            'text_max_length' => min(self::MAX_TEXT_LENGTH, max(0, $textMaxLength)),
        ];
    }

    public static function getAgentLimitConfig(int $aid, string $agentKey): array
    {
        $agentKey = trim($agentKey);
        if ($aid <= 0 || $agentKey === '') {
            return self::normalizeLimitConfig();
        }

        $agent = Db::name('zhuibao_agent')
            ->where('aid', $aid)
            ->where('agent_key', $agentKey)
            ->find();
        if (!$agent) {
            return self::normalizeLimitConfig();
        }

        $config = self::decodeJson($agent['config_json'] ?? []);
        $config = is_array($config) ? $config : [];
        return self::normalizeLimitConfig($config['limits'] ?? [], $config);
    }

    public static function validateAgentPayload(int $aid, string $agentKey, array &$payload, array $labels = []): array
    {
        $limits = self::getAgentLimitConfig($aid, $agentKey);
        self::resetPayloadAudioDurationFields($payload);
        self::validateTextPayload($limits, $payload, $labels);
        $duration = self::populatePayloadAudioDuration($payload, intval($limits['audio_max_duration_sec'] ?? 0));
        if ($duration > 0) {
            $payload['audio_duration'] = round($duration, 2);
        }

        return $limits;
    }

    private static function resetPayloadAudioDurationFields(array &$payload): void
    {
        foreach (['audio_duration', 'reference_audio_url_duration', 'audio_url_duration', 'uploaded_audio_url_duration', 'voice_url_duration'] as $key) {
            unset($payload[$key]);
        }
    }

    public static function validateTextPayload(array $limits, array $payload, array $labels = []): void
    {
        $maxLength = max(0, intval($limits['text_max_length'] ?? 0));
        if ($maxLength <= 0) {
            return;
        }

        $defaultLabels = [
            'source_url' => '参考内容',
            'remark' => '任务说明',
            'title' => '任务标题',
            'extract_text' => '提取文案',
            'rewrite_text' => '改写文案',
            'topic' => '图文主题',
            'extra_requirement' => '补充要求',
            'publish_copy' => '发布文案',
            'copywriting' => '文案内容',
            'smart_edit_title' => '智能剪辑标题',
            'smart_edit_subheading' => '智能剪辑副标题',
        ];
        $fieldLabels = array_merge($defaultLabels, $labels);

        foreach ($fieldLabels as $key => $label) {
            if (!array_key_exists($key, $payload) || is_array($payload[$key])) {
                continue;
            }
            $value = trim(strval($payload[$key]));
            if ($value === '' || self::shouldSkipTextLimit($key, $value)) {
                continue;
            }
            $length = self::textLength($value);
            if ($length > $maxLength) {
                throw new \RuntimeException($label . '最多 ' . $maxLength . ' 字，当前 ' . $length . ' 字');
            }
        }
    }

    public static function populatePayloadAudioDuration(array &$payload, int $maxDurationSec = 0): float
    {
        $total = 0.0;
        foreach (['reference_audio_url', 'audio_url', 'uploaded_audio_url', 'voice_url'] as $key) {
            $url = trim(strval($payload[$key] ?? ''));
            if ($url === '') {
                continue;
            }
            $duration = self::resolveMediaDurationFromUrl($url);
            self::assertOptionalMediaDurationWithinLimit($duration, $maxDurationSec, '音频文件');
            if ($duration > 0) {
                $payload[$key . '_duration'] = round($duration, 2);
                $total += $duration;
            }
        }

        return max(0.0, $total);
    }

    public static function assertOptionalMediaDurationWithinLimit(float $duration, int $maxDurationSec, string $label = '文件'): void
    {
        $maxDurationSec = max(0, $maxDurationSec);
        if ($maxDurationSec <= 0) {
            return;
        }
        if ($duration <= 0) {
            throw new \RuntimeException('无法读取' . $label . '时长，请确认文件格式受支持且文件可以正常播放');
        }
        if ($duration > ($maxDurationSec + 0.2)) {
            throw new \RuntimeException($label . '时长 ' . self::formatSeconds($duration) . '，超过限制 ' . self::formatSeconds($maxDurationSec));
        }
    }

    public static function assertLocalMediaDurationWithinLimit(string $filePath, int $maxDurationSec, string $label = '文件'): float
    {
        $duration = self::probeMediaDuration($filePath);
        $duration = $duration !== null ? max(0.0, floatval($duration)) : 0.0;
        self::assertOptionalMediaDurationWithinLimit($duration, $maxDurationSec, $label);
        return $duration;
    }

    public static function resolveMediaDurationFromUrl(string $url): float
    {
        $url = trim($url);
        if ($url === '') {
            return 0.0;
        }

        $localPath = self::resolveLocalMediaPath($url);
        if ($localPath !== '') {
            $duration = self::probeMediaDuration($localPath);
            return $duration !== null ? max(0.0, floatval($duration)) : 0.0;
        }

        $binary = self::downloadBinary($url);
        if ($binary === '') {
            return 0.0;
        }

        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'mp3';
        $tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'zhuibao_media_' . substr(md5($url . microtime(true)), 0, 12) . '.' . strtolower($ext);
        if (file_put_contents($tmp, $binary) === false) {
            return 0.0;
        }

        try {
            $duration = self::probeMediaDuration($tmp);
            return $duration !== null ? max(0.0, floatval($duration)) : 0.0;
        } finally {
            @unlink($tmp);
        }
    }

    public static function isAudioExtension(string $extension): bool
    {
        return in_array(strtolower(trim($extension)), self::AUDIO_EXTENSIONS, true);
    }

    public static function isVideoExtension(string $extension): bool
    {
        return in_array(strtolower(trim($extension)), self::VIDEO_EXTENSIONS, true);
    }

    public static function formatSeconds(float $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            $remain = round($seconds - $minutes * 60, 1);
            return $minutes . '分' . ($remain > 0 ? $remain . '秒' : '');
        }
        return round($seconds, 1) . '秒';
    }

    public static function textLength(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return intval(mb_strlen($text, 'UTF-8'));
        }
        return strlen($text);
    }

    private static function probeMediaDuration(string $filePath): ?float
    {
        if (!is_file($filePath)) {
            return null;
        }

        $duration = self::probeMediaDurationWithGetId3($filePath);
        if ($duration !== null) {
            return $duration;
        }

        $ffprobe = self::findExecutable('ffprobe');
        if ($ffprobe !== '') {
            $cmd = escapeshellarg($ffprobe)
                . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
                . escapeshellarg($filePath) . ' 2>/dev/null';
            $output = [];
            $code = 0;
            @exec($cmd, $output, $code);
            if ($code === 0 && !empty($output)) {
                $duration = floatval(trim(strval($output[0])));
                if ($duration > 0) {
                    return $duration;
                }
            }
        }

        $ffmpeg = self::findExecutable('ffmpeg');
        if ($ffmpeg !== '') {
            $cmd = escapeshellarg($ffmpeg) . ' -i ' . escapeshellarg($filePath) . ' 2>&1';
            $output = [];
            @exec($cmd, $output);
            $text = implode("\n", $output);
            if (preg_match('/Duration:\s*(\d{2}):(\d{2}):(\d{2}(?:\.\d+)?)/', $text, $matches)) {
                return intval($matches[1]) * 3600 + intval($matches[2]) * 60 + floatval($matches[3]);
            }
        }

        return null;
    }

    private static function probeMediaDurationWithGetId3(string $filePath): ?float
    {
        if (!class_exists('getID3')) {
            return null;
        }

        try {
            $analyzer = new \getID3();
            $info = $analyzer->analyze($filePath);
            $duration = floatval($info['playtime_seconds'] ?? 0);
            return $duration > 0 ? $duration : null;
        } catch (\Throwable $e) {
            Log::warning('getID3 读取媒体时长失败：' . $e->getMessage());
            return null;
        }
    }

    private static function resolveLocalMediaPath(string $url): string
    {
        $path = trim(parse_url($url, PHP_URL_PATH) ?: $url);
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return '';
        }
        if (strpos($path, 'public/') === 0) {
            $path = substr($path, 7);
        }

        $candidates = [];
        if (defined('ROOT_PATH')) {
            $candidates[] = rtrim(strval(ROOT_PATH), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
        try {
            $root = rtrim(app()->getRootPath(), DIRECTORY_SEPARATOR);
            $candidates[] = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $candidates[] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        } catch (\Throwable $e) {
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function downloadBinary(string $url): string
    {
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }
        $ch = curl_init($url);
        if (!$ch) {
            return '';
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($errno || $httpCode >= 400) {
            return '';
        }
        return strval($body);
    }

    private static function findExecutable(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_-]/i', '', $name);
        if ($name === '') {
            return '';
        }
        foreach (['/usr/local/bin', '/usr/bin', '/bin', '/opt/homebrew/bin'] as $dir) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_executable($path)) {
                return $path;
            }
        }
        $output = [];
        $code = 0;
        @exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $output, $code);
        if ($code === 0 && !empty($output)) {
            $path = trim(strval($output[0]));
            return is_executable($path) ? $path : '';
        }
        return '';
    }

    private static function shouldSkipTextLimit(string $key, string $value): bool
    {
        if ($key !== 'source_url') {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private static function firstInt(array $values): int
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            return intval($value);
        }
        return 0;
    }

    private static function decodeJson($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }
}
