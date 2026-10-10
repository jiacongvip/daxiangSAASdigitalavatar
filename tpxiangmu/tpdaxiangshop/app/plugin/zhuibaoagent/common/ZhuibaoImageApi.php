<?php

namespace app\plugin\zhuibaoagent\common;

use think\facade\Log;

class ZhuibaoImageApi
{
    public static function generate(array $providerConfig, string $prompt, array $options = []): array
    {
        $resolved = self::resolveConfig($providerConfig);
        if (intval($resolved['status'] ?? 0) !== 1) {
            return $resolved;
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['status' => 0, 'msg' => 'prompt 不能为空'];
        }

        $isArk = self::isArkImageEndpoint($resolved['base_url'], $resolved['model_name']);
        $payload = [
            'model' => strval($resolved['model_name']),
            'prompt' => $prompt,
            'size' => self::normalizeRequestedSize(
                strval($options['size'] ?? ($resolved['image_size'] ?? '1024x1536')),
                strval($resolved['image_size'] ?? '1024x1536')
            ),
        ];
        if (!$isArk) {
            $payload['n'] = max(1, intval($options['n'] ?? 1));
        }

        if (!empty($options['quality'])) {
            $payload['quality'] = trim(strval($options['quality']));
        }
        $apiStyle = self::normalizeApiStyleOption($options['style'] ?? '');
        if ($apiStyle !== '') {
            $payload['style'] = $apiStyle;
        }
        if (!empty($options['background'])) {
            $payload['background'] = trim(strval($options['background']));
        }
        if (!empty($options['response_format'])) {
            $payload['response_format'] = trim(strval($options['response_format']));
        }
        if ($isArk) {
            if (empty($payload['response_format'])) {
                $payload['response_format'] = 'url';
            }
            $payload['stream'] = intval($options['stream'] ?? 0) === 1;
            if (array_key_exists('watermark', $options)) {
                $payload['watermark'] = intval($options['watermark']) === 1;
            } else {
                $payload['watermark'] = true;
            }
            if (!empty($options['sequential_image_generation'])) {
                $payload['sequential_image_generation'] = trim(strval($options['sequential_image_generation']));
            }
        }

        $sourceReferenceImages = self::normalizeReferenceImages(
            $options['reference_images'] ?? ($options['image'] ?? []),
            max(0, intval($options['max_reference_images'] ?? 6))
        );
        $referencePayloadMode = self::normalizeReferenceImagePayloadMode($options['reference_image_payload_mode'] ?? 'auto');
        $referenceImages = self::buildReferenceImagePayloads(
            $sourceReferenceImages,
            max(0, intval($options['max_reference_images'] ?? 6)),
            $referencePayloadMode,
            $options['heartbeat_callback'] ?? null
        );
        $requiredReferenceCount = max(0, intval($options['require_reference_count'] ?? 0));
        if ($requiredReferenceCount > 0 && count($referenceImages) < $requiredReferenceCount) {
            return [
                'status' => 0,
                'msg' => '参考图未能完整读取，请检查图片是否存在或可访问',
                'submitted_reference_count' => count($referenceImages),
            ];
        }

        if ($resolved['provider_type'] === 'openai' && !empty($referenceImages)) {
            $referenceDelivery = self::buildReferenceDeliveryMeta($referenceImages, 'openai_images_edits');
            Log::info('zhuibao image api dispatch url=' . strval($resolved['edits_url']) . ' model=' . strval($resolved['model_name']) . ' transport=openai_images_edits', [
                'transport_mode' => 'openai_images_edits',
                'provider_type' => 'openai',
                'model_name' => strval($resolved['model_name']),
                'base_url' => strval($resolved['base_url']),
                'edits_url' => strval($resolved['edits_url']),
                'reference_image_count' => count($referenceImages),
                'reference_payload_mode' => $referencePayloadMode,
                'prompt_length' => self::textLength($prompt),
                'reference_delivery' => $referenceDelivery,
            ]);

            $result = self::mergeReferenceDeliveryMeta(
                self::requestOpenAiImageEdit($resolved, $prompt, $referenceImages, $options),
                $referenceDelivery
            );
            if (intval($result['status'] ?? 0) === 1) {
                return $result;
            }

            $temporaryFailureRetryCount = max(0, intval($options['temporary_failure_retry_count'] ?? 2));
            $temporaryFailureRetrySleepMs = max(0, intval($options['temporary_failure_retry_sleep_ms'] ?? 1200));
            for ($attempt = 1; $attempt <= $temporaryFailureRetryCount; $attempt++) {
                if (!self::shouldRetryTemporaryProviderFailure(
                    strval($result['msg'] ?? ''),
                    intval($result['http_code'] ?? 0)
                )) {
                    break;
                }

                $sleepMs = self::buildTemporaryFailureRetrySleepMs($temporaryFailureRetrySleepMs, $attempt);
                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
                Log::info('zhuibao image api temporary failure retry', [
                    'attempt' => $attempt,
                    'max_attempts' => $temporaryFailureRetryCount,
                    'transport_mode' => 'openai_images_edits',
                    'model_name' => strval($resolved['model_name']),
                    'request_url' => strval($resolved['edits_url']),
                    'sleep_ms' => $sleepMs,
                    'http_code' => intval($result['http_code'] ?? 0),
                    'last_error_message' => strval($result['msg'] ?? ''),
                    'reference_delivery' => $referenceDelivery,
                ]);

                $result = self::mergeReferenceDeliveryMeta(
                    self::requestOpenAiImageEdit($resolved, $prompt, $referenceImages, $options),
                    $referenceDelivery
                );
                if (intval($result['status'] ?? 0) === 1) {
                    $result['temporary_failure_retried'] = $attempt;
                    return $result;
                }
            }

            return $result;
        }

        $useChatCompletions = !empty($referenceImages)
            && self::shouldUseChatCompletionsForReferenceImages($resolved['base_url'], $resolved['model_name']);
        $referenceTransportMode = $useChatCompletions ? 'chat_completions_with_reference' : 'images_generations';
        $referenceDelivery = self::buildReferenceDeliveryMeta($referenceImages, $referenceTransportMode);

        Log::info('zhuibao image api dispatch url=' . strval($resolved['images_url']) . ' model=' . strval($resolved['model_name']) . ' transport=' . $referenceTransportMode, [
            'transport_mode' => $referenceTransportMode,
            'model_name' => strval($resolved['model_name']),
            'base_url' => strval($resolved['base_url']),
            'images_url' => strval($resolved['images_url']),
            'chat_url' => strval($resolved['chat_url']),
            'reference_image_count' => count($referenceImages),
            'reference_payload_mode' => $referencePayloadMode,
            'prompt_length' => self::textLength($prompt),
            'payload_summary' => self::summarizePayload($payload),
            'reference_delivery' => $referenceDelivery,
        ]);
        Log::info('zhuibao image api dispatch refs: ' . self::formatReferenceDeliveryMeta($referenceDelivery));

        if ($useChatCompletions) {
            return self::mergeReferenceDeliveryMeta(
                self::requestChatCompletionsImageGeneration($resolved, $prompt, $referenceImages, $options),
                $referenceDelivery
            );
        }
        if (!empty($referenceImages)) {
            // OpenAI-compatible vendors may accept reference images via "image": ["data:image/..."].
            $payload['image'] = $referenceImages;
        }

        $result = self::mergeReferenceDeliveryMeta(
            self::requestImageGeneration($resolved, $payload, $options),
            $referenceDelivery
        );
        if (intval($result['status'] ?? 0) === 1) {
            return $result;
        }

        if (
            !$useChatCompletions
            && !empty($payload['image'])
            && intval($options['retry_with_alternate_reference_payload'] ?? 1) === 1
            && self::shouldRetryWithoutReferenceImages(strval($result['msg'] ?? ''))
        ) {
                $alternateReferenceImages = self::buildAlternativeReferenceImagePayloads(
                    $sourceReferenceImages,
                    $payload['image'],
                    max(0, intval($options['max_reference_images'] ?? 6)),
                    $options['heartbeat_callback'] ?? null
            );
            if (!empty($alternateReferenceImages)) {
                $alternatePayload = $payload;
                $alternatePayload['image'] = $alternateReferenceImages;
                $alternateReferenceDelivery = self::buildReferenceDeliveryMeta(
                    $alternateReferenceImages,
                    $referenceTransportMode
                );
                Log::info('zhuibao image api retry with alternate reference payload', [
                    'model_name' => strval($resolved['model_name']),
                    'request_url' => strval($resolved['images_url']),
                    'error_message' => strval($result['msg'] ?? ''),
                    'from_reference_delivery' => $referenceDelivery,
                    'to_reference_delivery' => $alternateReferenceDelivery,
                ]);
                Log::info(
                    'zhuibao image api retry with alternate reference payload: from '
                    . self::formatReferenceDeliveryMeta($referenceDelivery)
                    . ' to '
                    . self::formatReferenceDeliveryMeta($alternateReferenceDelivery)
                    . '; error=' . self::limitText(strval($result['msg'] ?? ''), 200)
                );

                $alternateResult = self::mergeReferenceDeliveryMeta(
                    self::requestImageGeneration($resolved, $alternatePayload, $options),
                    $alternateReferenceDelivery
                );
                if (intval($alternateResult['status'] ?? 0) === 1) {
                    return $alternateResult;
                }

                $payload = $alternatePayload;
                $referenceDelivery = $alternateReferenceDelivery;
                $result = $alternateResult;
            }
        }

        if (
            !empty($payload['image'])
            && intval($options['retry_without_reference_images'] ?? 1) === 1
            && self::shouldRetryWithoutReferenceImages(strval($result['msg'] ?? ''))
        ) {
            Log::info('zhuibao image api retry without reference images', [
                'model_name' => strval($resolved['model_name']),
                'request_url' => strval($resolved['images_url']),
                'reference_image_count' => count($payload['image']),
                'error_message' => strval($result['msg'] ?? ''),
            ]);
            Log::info(
                'zhuibao image api retry without reference images: '
                . self::formatReferenceDeliveryMeta($referenceDelivery)
                . '; error=' . self::limitText(strval($result['msg'] ?? ''), 200)
            );
            unset($payload['image']);
            $retryReferenceDelivery = self::buildReferenceDeliveryMeta([], 'images_generations');
            $retryResult = self::mergeReferenceDeliveryMeta(
                self::requestImageGeneration($resolved, $payload, $options),
                $retryReferenceDelivery
            );
            if (intval($retryResult['status'] ?? 0) === 1) {
                $retryResult['reference_images_degraded'] = 1;
                $retryResult['reference_images_degraded_reason'] = strval($result['msg'] ?? '');
                return $retryResult;
            }
        }

        $temporaryFailureRetryCount = max(0, intval($options['temporary_failure_retry_count'] ?? 2));
        $temporaryFailureRetrySleepMs = max(0, intval($options['temporary_failure_retry_sleep_ms'] ?? 1200));
        if (
            $temporaryFailureRetryCount > 0
            && self::shouldRetryTemporaryProviderFailure(
                strval($result['msg'] ?? ''),
                intval($result['http_code'] ?? 0)
            )
        ) {
            for ($attempt = 1; $attempt <= $temporaryFailureRetryCount; $attempt++) {
                $sleepMs = self::buildTemporaryFailureRetrySleepMs($temporaryFailureRetrySleepMs, $attempt);
                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }

                Log::info('zhuibao image api temporary failure retry', [
                    'attempt' => $attempt,
                    'max_attempts' => $temporaryFailureRetryCount,
                    'transport_mode' => $useChatCompletions ? 'chat_completions_with_reference' : 'images_generations',
                    'model_name' => strval($resolved['model_name']),
                    'request_url' => $useChatCompletions ? strval($resolved['chat_url']) : strval($resolved['images_url']),
                    'sleep_ms' => $sleepMs,
                    'last_error_message' => strval($result['msg'] ?? ''),
                    'reference_delivery' => $referenceDelivery,
                ]);
                Log::info(
                    'zhuibao image api temporary failure retry: attempt '
                    . $attempt . '/' . $temporaryFailureRetryCount
                    . '; sleep_ms=' . $sleepMs
                    . '; transport=' . ($useChatCompletions ? 'chat_completions_with_reference' : 'images_generations')
                    . '; refs=' . self::formatReferenceDeliveryMeta($referenceDelivery)
                    . '; error=' . self::limitText(strval($result['msg'] ?? ''), 200)
                );

                $retryAttemptResult = $useChatCompletions
                    ? self::mergeReferenceDeliveryMeta(
                        self::requestChatCompletionsImageGeneration($resolved, $prompt, $referenceImages, $options),
                        $referenceDelivery
                    )
                    : self::mergeReferenceDeliveryMeta(
                        self::requestImageGeneration($resolved, $payload, $options),
                        $referenceDelivery
                    );
                if (intval($retryAttemptResult['status'] ?? 0) === 1) {
                    $retryAttemptResult['temporary_failure_retried'] = $attempt;
                    return $retryAttemptResult;
                }

                $result = $retryAttemptResult;
                if (!self::shouldRetryTemporaryProviderFailure(
                    strval($result['msg'] ?? ''),
                    intval($result['http_code'] ?? 0)
                )) {
                    break;
                }
            }
        }

        return $result;
    }

    private static function resolveConfig(array $providerConfig): array
    {
        $providerType = self::normalizeProviderType($providerConfig['provider_type'] ?? 'auto');
        $baseUrl = trim(strval($providerConfig['base_url'] ?? ''));
        $apiKey = trim(strval($providerConfig['api_key'] ?? ''));
        $modelName = trim(strval($providerConfig['model_name'] ?? ''));
        $extraJson = self::decodeJsonField($providerConfig['extra_json'] ?? []);
        $imageSize = trim(strval($providerConfig['image_size'] ?? ($extraJson['image_size'] ?? '1024x1536'))) ?: '1024x1536';

        if ($baseUrl === '') {
            return ['status' => 0, 'msg' => '请先填写请求地址'];
        }
        if ($apiKey === '') {
            return ['status' => 0, 'msg' => '请先填写 API Key'];
        }
        if ($modelName === '') {
            return ['status' => 0, 'msg' => '请先填写模型名称'];
        }

        return [
            'status' => 1,
            'provider_type' => $providerType,
            'base_url' => rtrim($baseUrl, '/'),
            'images_url' => self::buildImagesUrl($baseUrl),
            'edits_url' => self::buildEditsUrl($baseUrl),
            'chat_url' => self::buildChatUrl($baseUrl),
            'api_key' => $apiKey,
            'model_name' => $modelName,
            'image_size' => $imageSize,
            'provider_name' => trim(strval($providerConfig['provider_name'] ?? '图片生成服务')) ?: '图片生成服务',
        ];
    }

    private static function normalizeProviderType($providerType): string
    {
        $providerType = strtolower(trim(strval($providerType)));
        if (in_array($providerType, ['openai', 'volcengine'], true)) {
            return $providerType;
        }

        return 'auto';
    }

    private static function buildImagesUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return '';
        }

        if (preg_match('#/images/generations$#i', $baseUrl)) {
            return $baseUrl;
        }
        if (preg_match('#/api/v3$#i', $baseUrl)) {
            return $baseUrl . '/images/generations';
        }
        if (preg_match('#/v1$#i', $baseUrl)) {
            return $baseUrl . '/images/generations';
        }
        if (preg_match('#/api/v3/#i', $baseUrl)) {
            return $baseUrl;
        }

        if (preg_match('#ark\.cn-|volces\.com#i', $baseUrl)) {
            return $baseUrl . '/api/v3/images/generations';
        }

        return $baseUrl . '/v1/images/generations';
    }

    private static function buildChatUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return '';
        }

        if (preg_match('#/chat/completions$#i', $baseUrl)) {
            return $baseUrl;
        }
        if (preg_match('#/v1$#i', $baseUrl)) {
            return $baseUrl . '/chat/completions';
        }

        return $baseUrl . '/v1/chat/completions';
    }

    private static function buildEditsUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return '';
        }
        if (preg_match('#/images/edits$#i', $baseUrl)) {
            return $baseUrl;
        }
        if (preg_match('#/images/generations$#i', $baseUrl)) {
            return preg_replace('#/images/generations$#i', '/images/edits', $baseUrl);
        }
        if (preg_match('#/v1$#i', $baseUrl)) {
            return $baseUrl . '/images/edits';
        }

        return $baseUrl . '/v1/images/edits';
    }

    private static function normalizeApiStyleOption($style): string
    {
        $style = trim(strval($style));
        if ($style === '') {
            return '';
        }

        $normalized = strtolower($style);
        $supportedStyles = [
            'vivid' => 'vivid',
            'natural' => 'natural',
        ];
        if (isset($supportedStyles[$normalized])) {
            return $supportedStyles[$normalized];
        }

        $localizedStyles = [
            '生动' => 'vivid',
            '鲜艳' => 'vivid',
            '自然' => 'natural',
            '自然写实' => 'natural',
        ];

        return $localizedStyles[$style] ?? '';
    }

    public static function getStableSupportedSizes(): array
    {
        return [
            '1024x1024',
            '1024x1536',
            '1536x1024',
        ];
    }

    public static function normalizeRequestedSize(string $size, string $default = '1024x1536'): string
    {
        $default = strtolower(trim($default)) ?: '1024x1536';
        $size = strtolower(trim($size));
        if ($size === '') {
            return $default;
        }
        if (preg_match('/^\d{2,5}\s*[x*]\s*\d{2,5}$/', $size) !== 1) {
            return $default;
        }

        $normalized = preg_replace('/\s*[x*]\s*/', 'x', $size) ?: $default;
        if (in_array($normalized, self::getStableSupportedSizes(), true)) {
            return $normalized;
        }

        $parts = explode('x', $normalized);
        $width = intval($parts[0] ?? 0);
        $height = intval($parts[1] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return $default;
        }

        $ratio = $width / $height;
        if ($ratio >= 0.9 && $ratio <= 1.1) {
            return '1024x1024';
        }

        return $ratio > 1 ? '1536x1024' : '1024x1536';
    }

    private static function requestOpenAiImageEdit(
        array $resolved,
        string $prompt,
        array $referenceImages,
        array $options
    ): array {
        $fields = [
            'model' => strval($resolved['model_name']),
            'prompt' => $prompt,
            'size' => self::normalizeRequestedSize(
                strval($options['size'] ?? ($resolved['image_size'] ?? '1024x1536')),
                strval($resolved['image_size'] ?? '1024x1536')
            ),
            'n' => strval(max(1, intval($options['n'] ?? 1))),
        ];
        if (!empty($options['quality'])) {
            $fields['quality'] = trim(strval($options['quality']));
        }
        if (!empty($options['background'])) {
            $fields['background'] = trim(strval($options['background']));
        }
        if (!empty($options['input_fidelity']) && stripos(strval($resolved['model_name']), 'gpt-image-2') !== 0) {
            $fields['input_fidelity'] = trim(strval($options['input_fidelity']));
        }

        $uploads = [];
        foreach ($referenceImages as $index => $referenceImage) {
            $upload = self::buildOpenAiReferenceUpload(
                strval($referenceImage),
                intval($index),
                $options['heartbeat_callback'] ?? null
            );
            if (empty($upload['binary'])) {
                return [
                    'status' => 0,
                    'msg' => 'OpenAI 参考图读取失败，请检查商品图和房间照片是否可访问',
                    'submitted_reference_count' => count($uploads),
                ];
            }
            $uploads[] = $upload;
        }

        $boundary = '------------------------' . bin2hex(random_bytes(12));
        $body = self::buildOpenAiEditMultipartBody($fields, $uploads, $boundary);
        $timeout = max(1, intval($options['timeout'] ?? 240));
        $connectTimeout = max(1, intval($options['connect_timeout'] ?? 20));

        Log::info('zhuibao image api request start url=' . strval($resolved['edits_url']) . ' model=' . strval($resolved['model_name']) . ' transport=openai_images_edits', [
            'transport_mode' => 'openai_images_edits',
            'request_url' => strval($resolved['edits_url']),
            'model_name' => strval($resolved['model_name']),
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'reference_image_count' => count($uploads),
            'multipart_size' => strlen($body),
        ]);

        $ch = curl_init($resolved['edits_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . strval($resolved['api_key']),
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Accept: application/json',
            'User-Agent: OpenAI-PHP/1.0 (Aiscene Image Client)',
            'Expect:',
            'Content-Length: ' . strlen($body),
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        self::configureCurlHeartbeat($ch, $options['heartbeat_callback'] ?? null);

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $contentType = trim(strval(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: ''));
        $effectiveUrl = trim(strval(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: ($resolved['edits_url'] ?? '')));
        curl_close($ch);

        return self::parseOpenAiImageResponse(
            $resolved,
            strval($responseBody),
            $errno,
            $error,
            $httpCode,
            $contentType,
            $effectiveUrl,
            'openai_images_edits'
        );
    }

    private static function buildOpenAiReferenceUpload(string $referenceImage, int $index, $heartbeatCallback = null): array
    {
        $referenceImage = trim($referenceImage);
        $binary = '';
        $mime = '';
        if (preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#is', $referenceImage, $matches) === 1) {
            $mime = strtolower(trim(strval($matches[1] ?? '')));
            $decoded = base64_decode(preg_replace('/\s+/', '', strval($matches[2] ?? '')), true);
            $binary = $decoded === false ? '' : $decoded;
        } else {
            $binary = self::safeDownloadBinary($referenceImage, 120, $heartbeatCallback);
        }

        if ($binary === '') {
            return [];
        }
        if ($mime === '' && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = strtolower(trim(strval(finfo_buffer($finfo, $binary) ?: '')));
                finfo_close($finfo);
            }
        }
        if (strpos($mime, 'image/') !== 0) {
            $mime = 'image/png';
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        return [
            'binary' => $binary,
            'mime' => $mime,
            'filename' => 'reference_' . ($index + 1) . '.' . ($extensions[$mime] ?? 'png'),
        ];
    }

    private static function buildOpenAiEditMultipartBody(array $fields, array $uploads, string $boundary): string
    {
        $lineEnd = "\r\n";
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . $lineEnd;
            $body .= 'Content-Disposition: form-data; name="' . $name . '"' . $lineEnd . $lineEnd;
            $body .= strval($value) . $lineEnd;
        }
        foreach ($uploads as $upload) {
            $body .= '--' . $boundary . $lineEnd;
            $body .= 'Content-Disposition: form-data; name="image[]"; filename="' . strval($upload['filename']) . '"' . $lineEnd;
            $body .= 'Content-Type: ' . strval($upload['mime']) . $lineEnd . $lineEnd;
            $body .= strval($upload['binary']) . $lineEnd;
        }
        $body .= '--' . $boundary . '--' . $lineEnd;

        return $body;
    }

    private static function parseOpenAiImageResponse(
        array $resolved,
        string $body,
        int $errno,
        string $error,
        int $httpCode,
        string $contentType,
        string $effectiveUrl,
        string $transportMode
    ): array {
        if ($errno) {
            $errorMessage = self::normalizeProviderErrorMessage(
                $resolved,
                'OpenAI 请求失败：' . $error,
                $httpCode,
                $contentType
            );
            Log::info('zhuibao image api response', [
                'transport_mode' => $transportMode,
                'request_url' => $effectiveUrl,
                'status' => 0,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'curl_errno' => $errno,
                'curl_error' => $error,
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => $errorMessage !== '' ? $errorMessage : ('OpenAI 请求失败：' . $error),
                'http_code' => $httpCode,
                'provider_content_type' => $contentType,
                'provider_response_preview' => self::sanitizeProviderResponsePreview($body),
                'curl_errno' => $errno,
                'raw' => $body,
            ];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            $preview = trim(strval(substr($body, 0, 240)));
            $rawMessage = 'OpenAI 生图接口返回非 JSON'
                . ($httpCode > 0 ? '（HTTP ' . $httpCode . '）' : '')
                . ($contentType !== '' ? '（' . $contentType . '）' : '')
                . ($preview !== '' ? '：' . $preview : '');
            $errorMessage = self::normalizeProviderErrorMessage($resolved, $rawMessage, $httpCode, $contentType);
            Log::info('zhuibao image api response', [
                'transport_mode' => $transportMode,
                'request_url' => $effectiveUrl,
                'status' => 0,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'message' => 'OpenAI 生图接口返回非 JSON',
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => $errorMessage !== '' ? $errorMessage : $rawMessage,
                'http_code' => $httpCode,
                'provider_content_type' => $contentType,
                'provider_response_preview' => self::sanitizeProviderResponsePreview($body),
                'raw' => $body,
            ];
        }
        if ($httpCode >= 400 || !empty($json['error'])) {
            $errorInfo = is_array($json['error'] ?? null) ? $json['error'] : [];
            $errorMessage = trim(strval($errorInfo['message'] ?? ($json['message'] ?? '请求失败')));
            $errorMessage = self::normalizeProviderErrorMessage($resolved, $errorMessage, $httpCode, $contentType);
            Log::info('zhuibao image api response url=' . $effectiveUrl . ' http=' . $httpCode . ' status=0 msg=' . mb_substr($errorMessage, 0, 180) . ' transport=' . $transportMode, [
                'transport_mode' => $transportMode,
                'request_url' => $effectiveUrl,
                'status' => 0,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'message' => $errorMessage !== '' ? $errorMessage : 'OpenAI 生图失败',
                'error_code' => strval($errorInfo['code'] ?? ''),
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => $errorMessage !== '' ? $errorMessage : 'OpenAI 生图失败',
                'http_code' => $httpCode,
                'request_url' => $effectiveUrl,
                'provider_content_type' => $contentType,
                'provider_response_preview' => self::sanitizeProviderResponsePreview($body),
                'raw' => $body,
                'data' => $json,
            ];
        }

        $items = is_array($json['data'] ?? null) ? $json['data'] : [];
        $firstItem = isset($items[0]) && is_array($items[0]) ? $items[0] : [];
        $imageUrl = trim(strval($firstItem['url'] ?? ''));
        $base64 = trim(strval($firstItem['b64_json'] ?? ''));
        $binary = $base64 !== '' ? base64_decode($base64, true) : '';
        $binary = $binary === false ? '' : strval($binary);
        if ($imageUrl === '' && $binary === '') {
            return ['status' => 0, 'msg' => 'OpenAI 生图接口未返回图片', 'http_code' => $httpCode, 'raw' => $body, 'data' => $json];
        }

        Log::info('zhuibao image api response', [
            'transport_mode' => $transportMode,
            'request_url' => $effectiveUrl,
            'status' => 1,
            'http_code' => $httpCode,
            'has_image_url' => $imageUrl !== '' ? 1 : 0,
            'has_image_binary' => $binary !== '' ? 1 : 0,
        ]);

        return [
            'status' => 1,
            'msg' => 'success',
            'http_code' => $httpCode,
            'image_url' => $imageUrl,
            'image_binary' => $binary,
            'revised_prompt' => trim(strval($firstItem['revised_prompt'] ?? '')),
            'data' => $json,
            'raw' => $body,
        ];
    }

    private static function requestImageGeneration(array $resolved, array $payload, array $options): array
    {
        $timeout = max(1, intval($options['timeout'] ?? 240));
        $connectTimeout = max(1, intval($options['connect_timeout'] ?? 20));

        Log::info('zhuibao image api request start url=' . strval($resolved['images_url']) . ' model=' . strval($resolved['model_name']) . ' transport=images_generations', [
            'transport_mode' => 'images_generations',
            'request_url' => strval($resolved['images_url']),
            'model_name' => strval($resolved['model_name']),
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'payload_summary' => self::summarizePayload($payload),
        ]);

        $ch = curl_init($resolved['images_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . strval($resolved['api_key']),
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: OpenAI-PHP/1.0 (Aiscene Image Client)',
            'Expect:',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        self::configureCurlHeartbeat($ch, $options['heartbeat_callback'] ?? null);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $contentType = trim(strval(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: ''));
        $effectiveUrl = trim(strval(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: ($resolved['images_url'] ?? '')));
        curl_close($ch);

        if ($errno) {
            $errorMessage = self::normalizeProviderErrorMessage(
                $resolved,
                '请求失败：' . $error,
                $httpCode,
                $contentType
            );
            Log::info('zhuibao image api response', [
                'transport_mode' => 'images_generations',
                'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['images_url']),
                'status' => 0,
                'http_code' => $httpCode,
                'curl_errno' => $errno,
                'curl_error' => $error,
                'body_preview' => self::limitText(strval($body), 500),
            ]);
            return ['status' => 0, 'msg' => $errorMessage !== '' ? $errorMessage : ('请求失败：' . $error), 'raw' => strval($body)];
        }

        $body = strval($body);
        $json = json_decode($body, true);
        if (!is_array($json)) {
            $preview = trim(strval(substr($body, 0, 240)));
            $rawMessage = '接口返回非 JSON'
                . ($contentType !== '' ? '（' . $contentType . '）' : '')
                . ($effectiveUrl !== '' ? '，请求地址：' . $effectiveUrl : '')
                . ($preview !== '' ? '：' . $preview : '');
            $errorMessage = self::normalizeProviderErrorMessage($resolved, $rawMessage, $httpCode, $contentType);
            Log::info('zhuibao image api response', [
                'transport_mode' => 'images_generations',
                'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['images_url']),
                'status' => 0,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'message' => '接口返回非 JSON',
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => $errorMessage !== '' ? $errorMessage : $rawMessage,
                'http_code' => $httpCode,
                'raw' => $body,
            ];
        }

        if ($httpCode >= 400 || !empty($json['error'])) {
            $errorInfo = is_array($json['error'] ?? null) ? $json['error'] : [];
            $errorMsg = trim(strval($errorInfo['message'] ?? ($json['message'] ?? '请求失败')));
            $errorMsg = self::normalizeProviderErrorMessage($resolved, $errorMsg, $httpCode, $contentType);
            Log::info('zhuibao image api response', [
                'transport_mode' => 'images_generations',
                'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['images_url']),
                'status' => 0,
                'http_code' => $httpCode,
                'message' => $errorMsg !== '' ? $errorMsg : '请求失败',
                'error_code' => strval($errorInfo['code'] ?? ''),
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => $errorMsg !== '' ? $errorMsg : '请求失败',
                'http_code' => $httpCode,
                'raw' => $body,
                'data' => $json,
            ];
        }

        $items = is_array($json['data'] ?? null) ? $json['data'] : [];
        $firstItem = isset($items[0]) && is_array($items[0]) ? $items[0] : [];
        if (empty($firstItem)) {
            return [
                'status' => 0,
                'msg' => '图片接口未返回有效数据',
                'http_code' => $httpCode,
                'raw' => $body,
                'data' => $json,
            ];
        }

        $imageUrl = trim(strval($firstItem['url'] ?? ($firstItem['image_url'] ?? '')));
        $base64 = trim(strval($firstItem['b64_json'] ?? ($firstItem['base64'] ?? ($firstItem['image_base64'] ?? ''))));
        $binary = '';
        if ($base64 !== '') {
            $decoded = base64_decode($base64, true);
            if ($decoded !== false) {
                $binary = $decoded;
            }
        }

        if ($imageUrl === '' && $binary === '') {
            Log::info('zhuibao image api response', [
                'transport_mode' => 'images_generations',
                'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['images_url']),
                'status' => 0,
                'http_code' => $httpCode,
                'message' => '图片接口未返回 url 或 base64 图片内容',
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => '图片接口未返回 url 或 base64 图片内容',
                'http_code' => $httpCode,
                'raw' => $body,
                'data' => $json,
            ];
        }

        Log::info('zhuibao image api response', [
            'transport_mode' => 'images_generations',
            'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['images_url']),
            'status' => 1,
            'http_code' => $httpCode,
            'has_image_url' => $imageUrl !== '' ? 1 : 0,
            'has_image_binary' => $binary !== '' ? 1 : 0,
            'image_url' => $imageUrl,
            'revised_prompt' => self::limitText(trim(strval($firstItem['revised_prompt'] ?? '')), 500),
        ]);

        return [
            'status' => 1,
            'msg' => 'success',
            'http_code' => $httpCode,
            'image_url' => $imageUrl,
            'image_binary' => $binary,
            'revised_prompt' => trim(strval($firstItem['revised_prompt'] ?? '')),
            'data' => $json,
            'raw' => $body,
        ];
    }

    private static function requestChatCompletionsImageGeneration(
        array $resolved,
        string $prompt,
        array $referenceImages,
        array $options
    ): array {
        $content = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ];
        foreach ($referenceImages as $uri) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $uri],
            ];
        }

        $payload = [
            'model' => strval($resolved['model_name']),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'stream' => false,
            'size' => self::normalizeRequestedSize(
                strval($options['size'] ?? ($resolved['image_size'] ?? '1024x1536')),
                strval($resolved['image_size'] ?? '1024x1536')
            ),
        ];
        if (!empty($options['quality'])) {
            $payload['quality'] = trim(strval($options['quality']));
        }
        if (!empty($options['aspect_ratio'])) {
            $payload['aspect_ratio'] = trim(strval($options['aspect_ratio']));
        }
        if (!empty($options['image_size'])) {
            $payload['image_size'] = trim(strval($options['image_size']));
        }

        $timeout = max(1, intval($options['timeout'] ?? 240));
        $connectTimeout = max(1, intval($options['connect_timeout'] ?? 20));

        Log::info('zhuibao image api request start', [
            'transport_mode' => 'chat_completions_with_reference',
            'request_url' => strval($resolved['chat_url']),
            'model_name' => strval($resolved['model_name']),
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'reference_image_count' => count($referenceImages),
            'payload_summary' => self::summarizePayload($payload),
        ]);

        $ch = curl_init($resolved['chat_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . strval($resolved['api_key']),
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: OpenAI-PHP/1.0 (Aiscene Image Client)',
            'Expect:',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        self::configureCurlHeartbeat($ch, $options['heartbeat_callback'] ?? null);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $contentType = trim(strval(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: ''));
        $effectiveUrl = trim(strval(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: ($resolved['chat_url'] ?? '')));
        curl_close($ch);

        if ($errno) {
            $errorMessage = self::normalizeProviderErrorMessage(
                $resolved,
                '请求失败：' . $error,
                $httpCode,
                $contentType
            );
            Log::info('zhuibao image api response', [
                'transport_mode' => 'chat_completions_with_reference',
                'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['chat_url']),
                'status' => 0,
                'http_code' => $httpCode,
                'curl_errno' => $errno,
                'curl_error' => $error,
                'body_preview' => self::limitText(strval($body), 500),
            ]);
            return ['status' => 0, 'msg' => $errorMessage !== '' ? $errorMessage : ('请求失败：' . $error), 'raw' => strval($body)];
        }

        $body = strval($body);
        $json = json_decode($body, true);
        if (!is_array($json)) {
            $preview = trim(strval(substr($body, 0, 240)));
            $rawMessage = '接口返回非 JSON'
                . ($contentType !== '' ? '（' . $contentType . '）' : '')
                . ($effectiveUrl !== '' ? '，请求地址：' . $effectiveUrl : '')
                . ($preview !== '' ? '：' . $preview : '');
            $errorMessage = self::normalizeProviderErrorMessage($resolved, $rawMessage, $httpCode, $contentType);
            Log::info('zhuibao image api response', [
                'transport_mode' => 'chat_completions_with_reference',
                'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['chat_url']),
                'status' => 0,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'message' => '接口返回非 JSON',
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => $errorMessage !== '' ? $errorMessage : $rawMessage,
                'http_code' => $httpCode,
                'raw' => $body,
            ];
        }

        if ($httpCode >= 400 || !empty($json['error'])) {
            $errorInfo = is_array($json['error'] ?? null) ? $json['error'] : [];
            $errorMsg = trim(strval($errorInfo['message'] ?? ($json['message'] ?? '请求失败')));
            $errorMsg = self::normalizeProviderErrorMessage($resolved, $errorMsg, $httpCode, $contentType);
            Log::info('zhuibao image api response', [
                'transport_mode' => 'chat_completions_with_reference',
                'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['chat_url']),
                'status' => 0,
                'http_code' => $httpCode,
                'message' => $errorMsg !== '' ? $errorMsg : '请求失败',
                'error_code' => strval($errorInfo['code'] ?? ''),
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => $errorMsg !== '' ? $errorMsg : '请求失败',
                'http_code' => $httpCode,
                'raw' => $body,
                'data' => $json,
            ];
        }

        $choices = is_array($json['choices'] ?? null) ? $json['choices'] : [];
        $firstChoice = isset($choices[0]) && is_array($choices[0]) ? $choices[0] : [];
        $message = is_array($firstChoice['message'] ?? null) ? $firstChoice['message'] : [];
        $contentText = self::extractAssistantContentText($message['content'] ?? '');
        $imageUrl = self::extractImageUrlFromAssistantContent($contentText);
        $binary = self::decodeImageDataUrl($imageUrl);
        if ($binary !== '') {
            $imageUrl = '';
        }

        if ($imageUrl === '' && $binary === '') {
            Log::info('zhuibao image api response', [
                'transport_mode' => 'chat_completions_with_reference',
                'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['chat_url']),
                'status' => 0,
                'http_code' => $httpCode,
                'message' => '对话图片接口未返回图片地址',
                'assistant_content_preview' => self::limitText($contentText, 500),
                'body_preview' => self::limitText($body, 500),
            ]);
            return [
                'status' => 0,
                'msg' => '对话图片接口未返回图片地址',
                'http_code' => $httpCode,
                'raw' => $body,
                'data' => $json,
            ];
        }

        Log::info('zhuibao image api response', [
            'transport_mode' => 'chat_completions_with_reference',
            'request_url' => $effectiveUrl !== '' ? $effectiveUrl : strval($resolved['chat_url']),
            'status' => 1,
            'http_code' => $httpCode,
            'has_image_url' => $imageUrl !== '' ? 1 : 0,
            'has_image_binary' => $binary !== '' ? 1 : 0,
            'image_url' => $imageUrl,
            'assistant_content_preview' => self::limitText($contentText, 500),
        ]);

        return [
            'status' => 1,
            'msg' => 'success',
            'http_code' => $httpCode,
            'image_url' => $imageUrl,
            'image_binary' => $binary,
            'revised_prompt' => '',
            'data' => $json,
            'raw' => $body,
        ];
    }

    private static function shouldRetryWithoutReferenceImages(string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        $lower = strtolower($message);
        $keywords = [
            'unrecognized',
            'request argument',
            'unknown',
            'unsupported',
            'not support',
            'invalid parameter',
            'not allowed',
            'bad gateway',
            'error code: 502',
            'failed to fetch',
            'timeout',
        ];
        foreach ($keywords as $keyword) {
            if (strpos($lower, $keyword) !== false) {
                return true;
            }
        }

        if (preg_match('/error code:\s*(502|503|504|520|521|522|523|524)/i', $message)) {
            return true;
        }

        if (strpos($lower, 'image') === false) {
            return (strpos($message, '网关异常') !== false)
                || (strpos($message, '上游图片服务') !== false)
                || (strpos($message, '超时') !== false)
                || (strpos($message, '连接异常') !== false);
        }

        return (strpos($message, '不支持') !== false)
            || (strpos($message, '未知') !== false)
            || (strpos($message, '网关') !== false)
            || (strpos($message, '超时') !== false)
            || (strpos($message, '连接超时') !== false);
    }

    private static function shouldRetryTemporaryProviderFailure(string $message, int $httpCode = 0): bool
    {
        $message = trim($message);
        if ($message === '' && $httpCode <= 0) {
            return false;
        }

        $lower = strtolower($message);
        $nonRetryableKeywords = [
            'api key 无效',
            'invalid api key',
            'incorrect api key',
            '余额不足',
            'insufficient balance',
            'quota exceeded',
            'rate limit',
            '模型不可用',
            'invalid model',
            'does not exist',
        ];
        foreach ($nonRetryableKeywords as $keyword) {
            if (strpos($lower, strtolower($keyword)) !== false) {
                return false;
            }
        }

        if ($httpCode === 429 || in_array($httpCode, [500, 502, 503, 504, 520, 521, 522, 523, 524], true)) {
            return true;
        }

        $retryableKeywords = [
            'bad gateway',
            'gateway timeout',
            'failed to fetch',
            'timed out',
            'timeout',
            'connection reset',
            'connection refused',
            'unexpected eof',
            '上游图片服务暂时异常',
            '上游图片服务返回异常响应',
            '网关异常',
            '连接异常',
            '连接超时',
            '请求超时',
            '服务暂时异常',
            '返回异常响应',
            '接口返回非 json',
            '频率已达上限',
        ];
        foreach ($retryableKeywords as $keyword) {
            if (strpos($lower, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return preg_match('/(?:error code|http[_\s-]*code)[:=\s]*(502|503|504|520|521|522|523|524)\b/i', $message) === 1;
    }

    private static function buildTemporaryFailureRetrySleepMs(int $baseSleepMs, int $attempt): int
    {
        $baseSleepMs = max(0, $baseSleepMs);
        $attempt = max(1, $attempt);
        if ($baseSleepMs === 0) {
            return 0;
        }

        return min(8000, $baseSleepMs * $attempt);
    }

    public static function normalizeProviderErrorMessage(
        array $providerConfig,
        string $message,
        int $httpCode = 0,
        string $contentType = ''
    ): string {
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        $providerName = trim(strval($providerConfig['provider_name'] ?? ''));
        if ($providerName === '') {
            $providerName = '图片生成服务';
        }

        $normalized = strtolower($message);
        $gatewayCodes = [502, 503, 504, 520, 521, 522, 523, 524];
        $matchedGatewayCode = 0;
        if ($httpCode > 0 && in_array($httpCode, $gatewayCodes, true)) {
            $matchedGatewayCode = $httpCode;
        } elseif (preg_match('/error code:\s*(502|503|504|520|521|522|523|524)/i', $message, $matches)) {
            $matchedGatewayCode = intval($matches[1] ?? 0);
        }

        if (
            strpos($normalized, 'no available compatible accounts') !== false
            || strpos($normalized, 'no available compatible account') !== false
            || strpos($normalized, 'no available image accounts') !== false
            || strpos($normalized, 'no available image account') !== false
        ) {
            return $providerName . '当前没有可用的生图账号，请到上游中转服务检查图片模型绑定、账号池状态或稍后重试';
        }

        if ($matchedGatewayCode > 0) {
            return '上游图片服务暂时异常（' . $matchedGatewayCode . '），请稍后重试';
        }

        if (
            strpos($normalized, 'insufficient balance') !== false
            || strpos($normalized, 'insufficient_balance') !== false
            || strpos($normalized, 'balance not enough') !== false
            || strpos($message, '余额不足') !== false
        ) {
            return $providerName . '余额不足，请先充值后重试';
        }

        if (
            strpos($normalized, 'invalid api key') !== false
            || strpos($normalized, 'incorrect api key') !== false
            || strpos($normalized, 'api key is invalid') !== false
            || strpos($normalized, 'unauthorized') !== false
            || strpos($normalized, 'authentication') !== false
        ) {
            return $providerName . '的 API Key 无效或已失效，请检查配置';
        }

        if (
            strpos($normalized, 'quota exceeded') !== false
            || strpos($normalized, 'rate limit') !== false
            || $httpCode === 429
        ) {
            return $providerName . '当前额度或频率已达上限，请稍后重试';
        }

        if (
            strpos($normalized, 'model') !== false
            && (
                strpos($normalized, 'not found') !== false
                || strpos($normalized, 'does not exist') !== false
                || strpos($normalized, 'invalid model') !== false
            )
        ) {
            return $providerName . '模型不可用，请检查模型名称是否正确';
        }

        if (
            strpos($message, '接口返回非 JSON') !== false
            || (strpos($normalized, 'text/plain') !== false)
            || ($contentType !== '' && stripos($contentType, 'text/plain') !== false)
        ) {
            return '上游图片服务返回异常响应，请稍后重试';
        }

        return $message;
    }

    private static function isArkImageEndpoint(string $baseUrl, string $modelName): bool
    {
        $baseUrl = trim($baseUrl);
        $modelName = trim($modelName);
        if ($baseUrl !== '' && preg_match('#ark\.cn-|volces\.com|/api/v3(?:/|$)#i', $baseUrl)) {
            return true;
        }

        return $modelName !== '' && preg_match('/doubao|seedream/i', $modelName);
    }

    private static function shouldUseChatCompletionsForReferenceImages(string $baseUrl, string $modelName): bool
    {
        $baseUrl = strtolower(trim($baseUrl));
        $modelName = strtolower(trim($modelName));
        if ($modelName === '') {
            return false;
        }

        if (strpos($baseUrl, 'flow2api') !== false) {
            return true;
        }

        return preg_match('/gemini-(?:2\.5|3\.[01])-(?:flash|pro)-image|imagen-4\.0-generate-preview/i', $modelName) === 1;
    }

    private static function buildReferenceDeliveryMeta(array $referenceImages, string $transportMode): array
    {
        $hosts = [];
        $dataUrlCount = 0;
        $urlCount = 0;

        foreach ($referenceImages as $item) {
            $item = trim(strval($item));
            if ($item === '') {
                continue;
            }

            if (stripos($item, 'data:image/') === 0) {
                $dataUrlCount++;
                continue;
            }

            $urlCount++;
            $host = strtolower(trim(strval(parse_url($item, PHP_URL_HOST) ?: '')));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        return [
            'reference_transport_mode' => trim($transportMode),
            'submitted_reference_count' => count($referenceImages),
            'submitted_reference_data_url_count' => $dataUrlCount,
            'submitted_reference_url_count' => $urlCount,
            'submitted_reference_hosts' => array_values(array_unique($hosts)),
        ];
    }

    private static function mergeReferenceDeliveryMeta(array $result, array $referenceDelivery): array
    {
        foreach ($referenceDelivery as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    private static function formatReferenceDeliveryMeta(array $referenceDelivery): string
    {
        $parts = [
            'transport=' . trim(strval($referenceDelivery['reference_transport_mode'] ?? '')),
            'submitted_refs=' . intval($referenceDelivery['submitted_reference_count'] ?? 0),
            'data_urls=' . intval($referenceDelivery['submitted_reference_data_url_count'] ?? 0),
            'url_refs=' . intval($referenceDelivery['submitted_reference_url_count'] ?? 0),
        ];

        $hosts = $referenceDelivery['submitted_reference_hosts'] ?? [];
        if (is_array($hosts) && !empty($hosts)) {
            $parts[] = 'hosts=' . implode(',', array_map('strval', $hosts));
        }

        return implode('; ', $parts);
    }

    private static function normalizeReferenceImages($referenceImages, int $maxCount): array
    {
        if (!is_array($referenceImages) || empty($referenceImages) || $maxCount === 0) {
            return [];
        }

        $normalized = [];
        foreach ($referenceImages as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '') {
                $normalized[] = $item;
            }
        }

        $normalized = array_values(array_unique($normalized));
        if ($maxCount > 0 && count($normalized) > $maxCount) {
            $normalized = array_slice($normalized, 0, $maxCount);
        }

        return $normalized;
    }

    private static function buildReferenceImagePayloads(
        array $referenceImages,
        int $maxCount,
        string $mode = 'auto',
        $heartbeatCallback = null
    ): array
    {
        if (empty($referenceImages) || $maxCount === 0) {
            return [];
        }

        if (!in_array($mode, ['auto', 'url', 'data_url'], true)) {
            $mode = 'auto';
        }

        if ($maxCount > 0 && count($referenceImages) > $maxCount) {
            $referenceImages = array_slice($referenceImages, 0, $maxCount);
        }

        $result = [];
        foreach ($referenceImages as $item) {
            $item = trim(strval($item));
            if ($item === '') {
                continue;
            }
            if (stripos($item, 'data:image/') === 0) {
                $result[] = $item;
                continue;
            }

            if ($mode === 'data_url') {
                $dataUrl = self::safeBuildDataUrlFromUrl($item, 120, $heartbeatCallback);
                if ($dataUrl !== '') {
                    $result[] = $dataUrl;
                }
                continue;
            }

            if ($mode !== 'data_url' && self::shouldKeepReferenceImageUrl($item)) {
                $result[] = $item;
                continue;
            }

            $dataUrl = self::safeBuildDataUrlFromUrl($item, 120, $heartbeatCallback);
            $result[] = $dataUrl !== '' ? $dataUrl : $item;
        }

        return array_values(array_unique(array_filter($result, function ($item) {
            return trim(strval($item)) !== '';
        })));
    }

    private static function normalizeReferenceImagePayloadMode($mode): string
    {
        $mode = strtolower(trim(strval($mode)));
        return in_array($mode, ['auto', 'url', 'data_url'], true) ? $mode : 'auto';
    }

    private static function buildAlternativeReferenceImagePayloads(
        array $sourceReferenceImages,
        array $currentReferenceImages,
        int $maxCount,
        $heartbeatCallback = null
    ): array {
        if (empty($sourceReferenceImages) || empty($currentReferenceImages) || $maxCount === 0) {
            return [];
        }

        $hasCurrentRawUrl = false;
        foreach ($currentReferenceImages as $item) {
            if (self::isHttpUrl($item) && stripos(trim(strval($item)), 'data:image/') !== 0) {
                $hasCurrentRawUrl = true;
                break;
            }
        }

        if ($hasCurrentRawUrl) {
            $alternate = self::buildReferenceImagePayloads(
                $sourceReferenceImages,
                $maxCount,
                'data_url',
                $heartbeatCallback
            );
            return $alternate !== $currentReferenceImages ? $alternate : [];
        }

        $hasPublicRemoteUrl = false;
        foreach ($sourceReferenceImages as $item) {
            if (self::shouldKeepReferenceImageUrl($item)) {
                $hasPublicRemoteUrl = true;
                break;
            }
        }
        if (!$hasPublicRemoteUrl) {
            return [];
        }

        $alternate = self::buildReferenceImagePayloads(
            $sourceReferenceImages,
            $maxCount,
            'url',
            $heartbeatCallback
        );
        return $alternate !== $currentReferenceImages ? $alternate : [];
    }

    private static function shouldKeepReferenceImageUrl(string $url): bool
    {
        $url = trim($url);
        if (!self::isHttpUrl($url)) {
            return false;
        }

        $host = strtolower(trim(strval(parse_url($url, PHP_URL_HOST) ?: '')));
        if ($host === '') {
            return false;
        }
        if (strpos($host, '.') === false && filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return !self::isLocalOrPrivateHost($host);
    }

    private static function isHttpUrl(string $value): bool
    {
        return preg_match('#^https?://#i', trim($value)) === 1;
    }

    private static function isLocalOrPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === '' || $host === 'localhost' || $host === '::1') {
            return true;
        }
        if (substr($host, -6) === '.local') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (strpos($host, '10.') === 0 || strpos($host, '127.') === 0 || strpos($host, '192.168.') === 0) {
                return true;
            }
            if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host) === 1) {
                return true;
            }

            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return preg_match('/^(::1|fc|fd|fe80:)/i', $host) === 1;
        }

        return false;
    }

    private static function safeBuildDataUrlFromUrl(string $url, int $timeout, $heartbeatCallback = null): string
    {
        $binary = self::safeDownloadBinary($url, $timeout, $heartbeatCallback);
        return self::buildDataUrlFromBinary($binary);
    }

    private static function safeDownloadBinary(string $url, int $timeout, $heartbeatCallback = null): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $localFile = self::resolveLocalUploadFilePath($url);
        if ($localFile !== '') {
            self::invokeHeartbeat($heartbeatCallback);
            $binary = @file_get_contents($localFile);
            return $binary !== false ? strval($binary) : '';
        }

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, max(1, $timeout));
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            self::configureCurlHeartbeat($ch, $heartbeatCallback);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            curl_close($ch);

            if ($errno || $httpCode >= 400 || $body === false) {
                return '';
            }

            return strval($body);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function configureCurlHeartbeat($ch, $heartbeatCallback): void
    {
        if (!is_callable($heartbeatCallback)) {
            return;
        }

        $lastHeartbeatAt = 0;
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        $progressHandler = static function () use ($heartbeatCallback, &$lastHeartbeatAt): int {
            $now = time();
            if ($lastHeartbeatAt === 0 || $now - $lastHeartbeatAt >= 10) {
                $lastHeartbeatAt = $now;
                self::invokeHeartbeat($heartbeatCallback);
            }
            return 0;
        };
        if (defined('CURLOPT_XFERINFOFUNCTION')) {
            curl_setopt($ch, constant('CURLOPT_XFERINFOFUNCTION'), $progressHandler);
        } else {
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $progressHandler);
        }
    }

    private static function invokeHeartbeat($heartbeatCallback): void
    {
        if (!is_callable($heartbeatCallback)) {
            return;
        }
        try {
            $heartbeatCallback();
        } catch (\Throwable $e) {
        }
    }

    private static function sanitizeProviderResponsePreview(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8');
        $text = trim(strval(preg_replace('/\s+/u', ' ', $text)));
        return self::limitText($text, 180);
    }

    private static function resolveLocalUploadFilePath(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $rootPath = rtrim(root_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $candidates = [];

        $parsedPath = strval(parse_url($url, PHP_URL_PATH) ?: '');
        if ($parsedPath !== '') {
            $candidates[] = $rootPath . ltrim($parsedPath, '/');
            $uploadPos = strpos($parsedPath, '/upload/');
            if ($uploadPos !== false) {
                $relative = ltrim(substr($parsedPath, $uploadPos + 1), '/');
                $candidates[] = $rootPath . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $candidates[] = $rootPath . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            }
        }

        $uploadPos = strpos($url, 'upload/');
        if ($uploadPos !== false) {
            $relative = ltrim(substr($url, $uploadPos), '/');
            $candidates[] = $rootPath . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $candidates[] = $rootPath . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            $relative = ltrim(preg_replace('#^public/#', '', $url), '/');
            $candidates[] = $rootPath . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $candidates[] = $rootPath . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function buildDataUrlFromBinary(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $mime = 'image/png';
        $imageInfo = @getimagesizefromstring($binary);
        if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
            $mime = strtolower(trim(strval($imageInfo['mime'])));
        }
        if ($mime === '') {
            $mime = 'image/png';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    private static function extractAssistantContentText($content): string
    {
        if (is_string($content)) {
            return trim($content);
        }
        if (!is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $item) {
            if (is_string($item)) {
                $parts[] = trim($item);
                continue;
            }
            if (!is_array($item)) {
                continue;
            }

            $text = trim(strval($item['text'] ?? ($item['content'] ?? '')));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode("\n", array_filter($parts, function ($item) {
            return $item !== '';
        })));
    }

    private static function extractImageUrlFromAssistantContent(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }
        if (stripos($content, 'data:image/') === 0) {
            return $content;
        }

        if (preg_match('/!\[[^\]]*]\(([^)]+)\)/', $content, $matches)) {
            return trim(strval($matches[1]), " \t\n\r\0\x0B'\"");
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
            return trim(strval($matches[1]));
        }
        if (preg_match('/https?:\/\/\S+/i', $content, $matches)) {
            return rtrim(trim(strval($matches[0])), " \t\n\r\0\x0B'\"),.>");
        }

        return '';
    }

    private static function decodeImageDataUrl(string $uri): string
    {
        if (!preg_match('#^data:image/[^;]+;base64,(.+)$#is', trim($uri), $matches)) {
            return '';
        }

        $decoded = base64_decode(strval($matches[1]), true);
        return $decoded !== false ? $decoded : '';
    }

    private static function summarizePayload(array $payload): array
    {
        $summary = [];
        foreach ([
            'model',
            'size',
            'n',
            'quality',
            'style',
            'background',
            'response_format',
            'stream',
            'watermark',
            'sequential_image_generation',
            'aspect_ratio',
            'image_size',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                $summary[$key] = $payload[$key];
            }
        }

        if (array_key_exists('prompt', $payload)) {
            $summary['prompt_length'] = self::textLength(strval($payload['prompt']));
            $summary['prompt_preview'] = self::limitText(strval($payload['prompt']), 240);
        }

        if (!empty($payload['image']) && is_array($payload['image'])) {
            $summary['image_count'] = count($payload['image']);
        }

        if (!empty($payload['messages']) && is_array($payload['messages'])) {
            $summary['messages'] = self::summarizeMessages($payload['messages']);
        }

        return $summary;
    }

    private static function summarizeMessages(array $messages): array
    {
        $summary = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $item = [
                'role' => strval($message['role'] ?? ''),
            ];
            $content = $message['content'] ?? '';

            if (is_string($content)) {
                $item['content_type'] = 'text';
                $item['text_length'] = self::textLength($content);
                $item['text_preview'] = self::limitText($content, 240);
            } elseif (is_array($content)) {
                $textParts = [];
                $imageCount = 0;
                foreach ($content as $contentItem) {
                    if (!is_array($contentItem)) {
                        continue;
                    }
                    $type = strval($contentItem['type'] ?? '');
                    if ($type === 'image_url') {
                        $imageCount++;
                        continue;
                    }
                    if ($type === 'text') {
                        $text = trim(strval($contentItem['text'] ?? ''));
                        if ($text !== '') {
                            $textParts[] = $text;
                        }
                    }
                }
                $mergedText = trim(implode("\n", $textParts));
                $item['content_type'] = 'mixed';
                $item['image_count'] = $imageCount;
                $item['text_length'] = self::textLength($mergedText);
                $item['text_preview'] = self::limitText($mergedText, 240);
            } else {
                $item['content_type'] = gettype($content);
            }

            $summary[] = $item;
        }

        return $summary;
    }

    private static function textLength(string $text): int
    {
        return function_exists('mb_strlen') ? intval(mb_strlen($text, 'UTF-8')) : strlen($text);
    }

    private static function limitText(string $text, int $length = 240): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $length) {
                return $text;
            }

            return mb_substr($text, 0, $length, 'UTF-8') . '...';
        }

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    private static function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }
}
