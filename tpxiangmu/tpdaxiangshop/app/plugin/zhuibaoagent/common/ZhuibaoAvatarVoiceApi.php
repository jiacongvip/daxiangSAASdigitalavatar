<?php

namespace app\plugin\zhuibaoagent\common;

/**
 * 数字人开发者 API 网关
 *
 * 约定：
 * - 一个厂商/一套签名体系，尽量收口在一个文件里
 * - 后续这个厂商新增接口，直接补 ENDPOINTS 和对应薄封装方法
 * - 业务层只关心传 providerConfig + payload，不关心签名和 HTTP 细节
 */
class ZhuibaoAvatarVoiceApi
{
    private const VIDEO_TEXT_PATH = '/openapi/video/videoText';
    private const AVATAR_CLONE_PATH = '/openapi/video/clone';
    private const AVATAR_MERGE_PATH = '/openapi/video/merge';
    private const AVATAR_STATUS_PATH = '/openapi/video/cloneStatus/';
    private const VOICE_CLONE_PATH = '/openapi/figure/proVoiceClone';
    private const VOICE_CLONE_BASIC_PATH = '/openapi/figure/voiceClone';
    private const VOICE_CLONE_MINIMAX_PATH = '/openapi/figure/miniMaxVoiceClone';
    private const VOICE_CLONE_MAX_PATH = '/openapi/figure/maxVoiceClone';
    private const VOICE_CLONE_NWMAX_PATH = '/openapi/figure/NWMaxClone';
    private const VOICE_MERGE_PATH = '/openapi/figure/proVoiceMerge';
    private const VOICE_MERGE_BASIC_PATH = '/openapi/figure/audioMerge';
    private const VOICE_MERGE_MINIMAX_PATH = '/openapi/figure/miniMaxAudioMerge';
    private const VOICE_MERGE_MAX_PATH = '/openapi/figure/maxAudioMerge';
    private const VOICE_MERGE_NWMAX_PATH = '/openapi/figure/NWMaxMerge';
    private const VOICE_STATUS_PATH = '/openapi/figure/audioMergeStatus/';
    private const AVATAR_MAX_MERGE_PATH = '/openapi/video/submitVideo';
    private const AVATAR_XPRO_MERGE_PATH = '/openapi/video/submitImageHuman';
    private const IMAGE_SUBMIT_PATH = '/openapi/image/submitImage';
    private const IMAGE_STATUS_PATH = '/openapi/image/getGenImageRes/';
    private const ENDPOINTS = [
        'video.text' => ['path' => self::VIDEO_TEXT_PATH, 'method' => 'POST'],
        'avatar.clone' => ['path' => self::AVATAR_CLONE_PATH, 'method' => 'POST'],
        'avatar.merge' => ['path' => self::AVATAR_MERGE_PATH, 'method' => 'POST'],
        'avatar.status' => ['path' => self::AVATAR_STATUS_PATH, 'method' => 'GET'],
        'avatar.max' => ['path' => self::AVATAR_MAX_MERGE_PATH, 'method' => 'POST'],
        'avatar.xpro' => ['path' => self::AVATAR_XPRO_MERGE_PATH, 'method' => 'POST'],
        'image.submit' => ['path' => self::IMAGE_SUBMIT_PATH, 'method' => 'POST'],
        'image.status' => ['path' => self::IMAGE_STATUS_PATH, 'method' => 'GET'],
        'status.imagea' => ['path' => self::IMAGE_STATUS_PATH, 'method' => 'GET'],
        'voice.clone' => ['path' => self::VOICE_CLONE_PATH, 'method' => 'POST'],
        'voice.clone.basic' => ['path' => self::VOICE_CLONE_BASIC_PATH, 'method' => 'POST'],
        'voice.clone.minimax' => ['path' => self::VOICE_CLONE_MINIMAX_PATH, 'method' => 'POST'],
        'voice.clone.max' => ['path' => self::VOICE_CLONE_MAX_PATH, 'method' => 'POST'],
        'voice.clone.nwmax' => ['path' => self::VOICE_CLONE_NWMAX_PATH, 'method' => 'POST'],
        'voice.merge' => ['path' => self::VOICE_MERGE_PATH, 'method' => 'POST'],
        'voice.merge.basic' => ['path' => self::VOICE_MERGE_BASIC_PATH, 'method' => 'POST'],
        'voice.merge.minimax' => ['path' => self::VOICE_MERGE_MINIMAX_PATH, 'method' => 'POST'],
        'voice.merge.max' => ['path' => self::VOICE_MERGE_MAX_PATH, 'method' => 'POST'],
        'voice.merge.nwmax' => ['path' => self::VOICE_MERGE_NWMAX_PATH, 'method' => 'POST'],
        'voice.status' => ['path' => self::VOICE_STATUS_PATH, 'method' => 'GET'],
    ];

    public static function buildSign(string $appId, string $appSecret, int $timestamp): string
    {
        $content = 'appId=' . trim($appId) . '&timestamp=' . $timestamp;
        return hash_hmac('sha256', $content, trim($appSecret));
    }

    public static function call(array $providerConfig, string $endpointKey, array $payload = [], array $options = []): array
    {
        $overrides = is_array($providerConfig['endpoint_overrides'] ?? null) ? $providerConfig['endpoint_overrides'] : [];
        $overridePath = trim(strval($overrides[$endpointKey] ?? ''));
        $endpoint = self::ENDPOINTS[$endpointKey] ?? null;
        if (!$endpoint) {
            if ($overridePath === '') {
                return ['status' => 0, 'msg' => '未定义的接口：' . $endpointKey];
            }
            $endpoint = [
                'path' => $overridePath,
                'method' => strpos($endpointKey, 'status.') === 0 ? 'GET' : 'POST',
            ];
        }

        $path = strval($endpoint['path'] ?? '');
        $method = strtoupper(strval($endpoint['method'] ?? 'POST'));
        if ($overridePath !== '') {
            $path = $overridePath;
        }

        if (!empty($options['path_suffix'])) {
            $suffix = ltrim(strval($options['path_suffix']), '/');
            if ($suffix !== '') {
                $path = rtrim($path, '/') . '/' . $suffix;
            }
        }

        return self::requestJson($providerConfig, $path, $payload, $method, $options);
    }

    public static function cloneAvatar(array $providerConfig, array $payload): array
    {
        return self::call($providerConfig, 'avatar.clone', $payload);
    }

    public static function mergeAvatarVideo(array $providerConfig, array $payload): array
    {
        return self::call($providerConfig, 'avatar.merge', $payload);
    }

    public static function queryAvatarVideoStatus(array $providerConfig, string $searchId, string $family = ''): array
    {
        $searchId = trim($searchId);
        if ($searchId === '') {
            return ['status' => 0, 'msg' => 'searchId 不能为空'];
        }

        return self::call($providerConfig, self::statusEndpointKey($providerConfig, $family, 'avatar.status'), [], [
            'path_suffix' => '/' . rawurlencode($searchId),
        ]);
    }

    public static function extractVideoText(array $providerConfig, array $payload): array
    {
        return self::call($providerConfig, 'video.text', $payload, [
            'timeout' => 90,
            'connect_timeout' => 15,
        ]);
    }

    public static function cloneVoice(array $providerConfig, array $payload, string $api = 'pro'): array
    {
        return self::call($providerConfig, self::voiceCloneEndpoint($api), $payload);
    }

    public static function mergeVoice(array $providerConfig, array $payload, string $api = 'pro'): array
    {
        return self::call($providerConfig, self::voiceMergeEndpoint($api), $payload, [
            'timeout' => 0,
            'connect_timeout' => 15,
        ]);
    }

    public static function queryVoiceMergeStatus(array $providerConfig, $audioId, string $family = ''): array
    {
        $audioId = trim(strval($audioId));
        if ($audioId === '') {
            return ['status' => 0, 'msg' => 'audioId 不能为空'];
        }

        return self::call($providerConfig, self::statusEndpointKey($providerConfig, $family, 'voice.status'), [], [
            'path_suffix' => '/' . rawurlencode($audioId),
        ]);
    }

    public static function queryImageStatus(array $providerConfig, $searchId, string $family = 'imagea'): array
    {
        $searchId = trim(strval($searchId));
        if ($searchId === '') {
            return ['status' => 0, 'msg' => 'searchId 不能为空'];
        }

        return self::call($providerConfig, self::statusEndpointKey($providerConfig, $family, 'image.status'), [], [
            'path_suffix' => '/' . rawurlencode($searchId),
        ]);
    }

    public static function buildChannelSearchId(string $appId, int $uid = 0, $extra = ''): string
    {
        $appId = preg_replace('/\D+/', '', trim($appId));
        $uidPart = str_pad(substr(preg_replace('/\D+/', '', strval($uid)), -8), 8, '0', STR_PAD_LEFT);
        $ms = (int) floor(microtime(true) * 1000);
        $extraDigits = preg_replace('/\D+/', '', strval($extra));
        $mix = ($ms + intval(substr(strval($extraDigits), -6))) % 1000000;
        $uniq = str_pad(strval($mix), 6, '0', STR_PAD_LEFT)
            . str_pad(strval(random_int(0, 9999)), 4, '0', STR_PAD_LEFT);
        return $appId . $uidPart . $uniq;
    }

    public static function endpointKeys(): array
    {
        return array_keys(self::ENDPOINTS);
    }

    private static function statusEndpointKey(array $providerConfig, string $family, string $fallbackKey): string
    {
        $family = trim($family);
        if ($family !== '') {
            $familyKey = 'status.' . $family;
            $overrides = is_array($providerConfig['endpoint_overrides'] ?? null) ? $providerConfig['endpoint_overrides'] : [];
            if (trim(strval($overrides[$familyKey] ?? '')) !== '') {
                return $familyKey;
            }
        }
        return $fallbackKey;
    }

    private static function voiceCloneEndpoint(string $api): string
    {
        $map = [
            'spro' => 'voice.clone',
            'pro' => 'voice.clone',
            'basic' => 'voice.clone.basic',
            'max' => 'voice.clone.max',
            'nwmax' => 'voice.clone.nwmax',
            'minimax' => 'voice.clone.max',
        ];
        $api = strtolower(trim($api));
        if ($api === 'none' || $api === '') {
            return 'voice.clone';
        }
        return $map[$api] ?? 'voice.clone';
    }

    private static function voiceMergeEndpoint(string $api): string
    {
        $map = [
            'spro' => 'voice.merge',
            'pro' => 'voice.merge',
            'basic' => 'voice.merge.basic',
            'max' => 'voice.merge.max',
            'nwmax' => 'voice.merge.nwmax',
            'minimax' => 'voice.merge.max',
        ];
        $api = strtolower(trim($api));
        return $map[$api] ?? 'voice.merge';
    }

    private static function requestJson(array $providerConfig, string $path, array $payload, string $method = 'POST', array $options = []): array
    {
        $resolved = self::resolveConfig($providerConfig);
        if (intval($resolved['status']) !== 1) {
            return $resolved;
        }

        $baseUrl = strval($resolved['base_url']);
        $appId = strval($resolved['app_id']);
        $appSecret = strval($resolved['app_secret']);
        $timestamp = time();
        if (preg_match('#^https?://#i', $path)) {
            $url = $path;
        } else {
            $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        }
        $headers = [
            'appId: ' . $appId,
            'timestamp: ' . $timestamp,
            'sign: ' . self::buildSign($appId, $appSecret, $timestamp),
            'Content-Type: application/json',
        ];

        if ($method === 'GET' && !empty($payload)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($payload);
        }

        $timeout = array_key_exists('timeout', $options) ? intval($options['timeout']) : 30;
        $connectTimeout = array_key_exists('connect_timeout', $options) ? intval($options['connect_timeout']) : 10;
        $body = '';
        $errno = 0;
        $error = '';
        $httpCode = 0;
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout <= 0 ? 0 : max(1, $timeout));
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout <= 0 ? 0 : max(1, $connectTimeout));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

            if ($method !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            curl_close($ch);

            if (!$errno) {
                break;
            }

            if ($attempt >= $maxAttempts || !self::shouldRetryCurlError($errno, $error)) {
                break;
            }

            usleep(300000);
        }

        $requestMeta = [
            'method' => strtoupper($method),
            'url' => $url,
            'path' => $path,
            'headers' => [
                'appId' => $appId,
                'timestamp' => strval($timestamp),
                'Content-Type' => 'application/json',
            ],
            'body' => strtoupper($method) === 'GET' ? [] : $payload,
        ];

        if ($errno) {
            return [
                'status' => 0,
                'msg' => $error !== '' ? $error : '请求失败',
                'raw' => strval($body),
                'http_code' => $httpCode,
                'request' => $requestMeta,
            ];
        }

        $body = strval($body);
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return [
                'status' => 0,
                'msg' => $body !== '' ? $body : '接口返回非 JSON',
                'http_code' => $httpCode,
                'raw' => $body,
                'request' => $requestMeta,
            ];
        }

        $result = self::normalizeResponse($json, $httpCode, $body);
        $result['request'] = $requestMeta;
        return $result;
    }

    private static function shouldRetryCurlError(int $errno, string $error): bool
    {
        if (in_array($errno, [7, 28, 35, 52, 56], true)) {
            return true;
        }

        $error = strtolower(trim($error));
        if ($error === '') {
            return false;
        }

        foreach ([
            'ssl_error_syscall',
            'connection reset by peer',
            'empty reply from server',
            'unexpected eof',
            'operation timed out',
        ] as $keyword) {
            if (strpos($error, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeResponse(array $json, int $httpCode, string $raw): array
    {
        $code = $json['code'] ?? $json['status'] ?? null;
        $msg = strval($json['msg'] ?? $json['message'] ?? $json['error_msg'] ?? '请求成功');
        $data = $json['data'] ?? [];

        $successCodes = [1, '1', 200, '200'];
        $status = in_array($code, $successCodes, true) ? 1 : 0;
        if ($status !== 1 && in_array($code, [0, '0'], true)) {
            $isPositiveMsg = preg_match('/(success|成功)/iu', $msg) === 1;
            $hasErrorMsg = preg_match('/(失败|错误|异常|不存在|找不到|无效|error|fail)/iu', $msg) === 1;
            $status = ($isPositiveMsg && !$hasErrorMsg) ? 1 : 0;
        }

        if ($status !== 1 && $msg === '') {
            $msg = '接口返回失败';
        }

        return [
            'status' => $status,
            'msg' => $msg,
            'code' => $code,
            'http_code' => $httpCode,
            'data' => $data,
            'raw' => $raw,
        ];
    }

    private static function resolveConfig(array $providerConfig): array
    {
        $extra = is_array($providerConfig['extra_json'] ?? null) ? $providerConfig['extra_json'] : [];
        $baseUrl = trim(strval($providerConfig['base_url'] ?? ($extra['base_url'] ?? '')));
        $appId = trim(strval($providerConfig['app_id'] ?? ($extra['app_id'] ?? '')));
        $appSecret = trim(strval($providerConfig['app_secret'] ?? ($extra['app_secret'] ?? '')));

        if ($baseUrl === '') {
            return ['status' => 0, 'msg' => '请先填写请求地址'];
        }
        if ($appId === '') {
            return ['status' => 0, 'msg' => '请先填写 AppID'];
        }
        if ($appSecret === '') {
            return ['status' => 0, 'msg' => '请先填写密钥'];
        }

        return [
            'status' => 1,
            'base_url' => rtrim($baseUrl, '/'),
            'app_id' => $appId,
            'app_secret' => $appSecret,
        ];
    }
}
