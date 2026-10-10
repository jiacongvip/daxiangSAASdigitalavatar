<?php

namespace app\plugin\zhuibaoagent\common;

/**
 * 拟娲数字人 / 配音产品线目录。
 *
 * 官方文档里同一套 OpenAPI 按产品线拆参数：
 * - 语速/音调/音量只对 Pro 极速版生效
 * - language 只对 3D 精品版生效
 * - model 1/2 只对 VPro 高仿真版生效
 * - 海螺 MiniMax 走独立克隆/合成接口
 */
class ZhuibaoNiwaModelCatalog
{
    public static function families(): array
    {
        return [
            'spro' => self::sproFamily(),
            'professional' => self::professionalFamily(),
            'tts666' => self::tts666Family(),
            '3d' => self::threeDFamily(),
            'minimax' => self::minimaxFamily(),
            'vpro' => self::vproFamily(),
            'niwa' => self::niwaAvatarFamily(),
        ];
    }

    public static function seedTemplates(string $providerKey): array
    {
        $providerKey = trim($providerKey);
        if ($providerKey === '') {
            return [];
        }

        return [
            [
                'task_type' => 'audio',
                'title' => '拟娲专业',
                'model_code' => 'niwaai-professional',
                'provider_key' => $providerKey,
                'description' => '拟娲专业配音，不支持语速/音调/音量调节',
                'credit_cost' => 1,
                'is_default' => 0,
                'status' => 1,
                'sort' => 12,
                'config' => self::configOf('professional'),
            ],
            [
                'task_type' => 'audio',
                'title' => '基础公模 tts666',
                'model_code' => 'niwaai-tts666',
                'provider_key' => $providerKey,
                'description' => '官方公模音色（中英日韩粤语等），不支持克隆和语速调节',
                'credit_cost' => 1,
                'is_default' => 0,
                'status' => 1,
                'sort' => 13,
                'config' => self::configOf('tts666'),
            ],
            [
                'task_type' => 'audio',
                'title' => '3D精品版',
                'model_code' => 'niwaai-3d',
                'provider_key' => $providerKey,
                'description' => '3D精品配音，可选择语言识别码',
                'credit_cost' => 1,
                'is_default' => 0,
                'status' => 1,
                'sort' => 14,
                'config' => self::configOf('3d'),
            ],
            [
                'task_type' => 'audio',
                'title' => '海螺 MiniMax',
                'model_code' => 'niwaai-minimax',
                'provider_key' => $providerKey,
                'description' => '海螺声音合成，可选择引擎型号',
                'credit_cost' => 1,
                'is_default' => 0,
                'status' => 1,
                'sort' => 15,
                'config' => self::configOf('minimax'),
            ],
            [
                'task_type' => 'voice_clone',
                'title' => '拟娲专业克隆',
                'model_code' => 'niwaai-professional-clone',
                'provider_key' => $providerKey,
                'description' => '拟娲专业声音克隆，可填性别和描述',
                'credit_cost' => 1,
                'is_default' => 0,
                'status' => 1,
                'sort' => 12,
                'config' => self::configOf('professional'),
            ],
            [
                'task_type' => 'voice_clone',
                'title' => '海螺 MiniMax 克隆',
                'model_code' => 'niwaai-minimax-clone',
                'provider_key' => $providerKey,
                'description' => '海螺声音克隆，需选择引擎型号并提供试听文本',
                'credit_cost' => 1,
                'is_default' => 0,
                'status' => 1,
                'sort' => 13,
                'config' => self::configOf('minimax'),
            ],
            [
                'task_type' => 'avatar_clone',
                'title' => '3D精品数字人克隆',
                'model_code' => 'niwaai-3d-clone',
                'provider_key' => $providerKey,
                'description' => '3D精品形象克隆',
                'credit_cost' => 1,
                'is_default' => 0,
                'status' => 1,
                'sort' => 12,
                'config' => self::configOf('3d'),
            ],
            [
                'task_type' => 'avatar_clone',
                'title' => '拟娲基础数字人克隆',
                'model_code' => 'niwaai-basic-clone',
                'provider_key' => $providerKey,
                'description' => '基础形象克隆（platform_code=niwa）',
                'credit_cost' => 1,
                'is_default' => 0,
                'status' => 1,
                'sort' => 13,
                'config' => self::configOf('niwa'),
            ],
        ];
    }

    public static function resolve(string $modelCode = '', array $config = [], array $input = []): array
    {
        $familyFromCode = self::detectFamily($modelCode, [], []);
        $family = self::detectFamily($modelCode, $config, $input);
        $spec = self::families()[$family] ?? self::sproFamily();
        $inputService = trim(strval($input['voice_service_code'] ?? ''));
        $configService = trim(strval($config['voice_service_code'] ?? ''));
        if ($inputService !== '') {
            $spec['voice_service_code'] = $inputService;
        } elseif ($configService !== '' && ($modelCode === '' || $familyFromCode === 'spro')) {
            $spec['voice_service_code'] = $configService;
        }
        if (trim(strval($input['target_model'] ?? ($config['target_model'] ?? ''))) !== '') {
            $spec['target_model'] = trim(strval($input['target_model'] ?? ($config['target_model'] ?? '')));
        }
        if (trim(strval($input['platform_code'] ?? ($config['platform_code'] ?? ''))) !== '') {
            $spec['platform_code'] = trim(strval($input['platform_code'] ?? ($config['platform_code'] ?? '')));
        }
        $spec['family'] = $family;
        $spec['model_code'] = trim($modelCode);
        return $spec;
    }

    public static function portalCapabilities(array $modelRow): array
    {
        $spec = self::resolve(
            strval($modelRow['model_code'] ?? ''),
            is_array($modelRow['config'] ?? null) ? $modelRow['config'] : [],
            ['task_type' => strval($modelRow['task_type'] ?? '')]
        );
        return [
            'family' => strval($spec['family'] ?? ''),
            'params' => array_values($spec['params'] ?? []),
            'clone_params' => array_values($spec['clone_params'] ?? []),
            'language_options' => array_values($spec['language_options'] ?? []),
            'minimax_model_options' => array_values($spec['minimax_model_options'] ?? []),
            'vpro_model_options' => array_values($spec['vpro_model_options'] ?? []),
            'supports_clone' => !empty($spec['supports_clone']),
            'voice_service_code' => strval($spec['voice_service_code'] ?? ''),
            'platform_code' => strval($spec['platform_code'] ?? ''),
            'target_model' => strval($spec['target_model'] ?? ''),
        ];
    }

    public static function buildAudioPayload(array $spec, array $profile, array $input, string $voiceId, string $text): array
    {
        $family = strval($spec['family'] ?? 'spro');
        $modelType = intval($profile['model_type'] ?? 0);
        $modelType = $modelType === 2 ? 2 : ($modelType === 1 ? 1 : intval($input['voice_model_type'] ?? ($input['model_type'] ?? 1)));
        $modelType = $modelType === 2 ? 2 : 1;
        $voiceValue = ctype_digit($voiceId) ? intval($voiceId) : $voiceId;

        if ($family === 'minimax') {
            return [
                'voice_id' => $voiceValue,
                'model_type' => $modelType,
                'model' => trim(strval($input['minimax_model'] ?? ($spec['default_minimax_model'] ?? 'speech-02-hd'))) ?: 'speech-02-hd',
                'text' => $text,
            ];
        }

        if ($family === 'spro') {
            $language = trim(strval($input['language'] ?? ($profile['language'] ?? 'zh'))) ?: 'zh';
            $instruction = trim(strval($input['instruction'] ?? ($profile['instruction'] ?? '请用自然平稳的语气说话。'))) ?: '请用自然平稳的语气说话。';
            $speechRate = self::clampRatio(floatval($input['speech_rate'] ?? ($input['speed'] ?? 1.0)), 0.5, 3.0, 1.0);
            $pitchRate = self::clampRatio(floatval($input['pitch_rate'] ?? ($input['pitch'] ?? 1.0)), 0.5, 3.0, 1.0);
            return [
                'voice_id' => $voiceValue,
                'model_type' => $modelType,
                'text' => $text,
                'format' => trim(strval($input['format'] ?? 'WAV_16000HZ_MONO_16BIT')) ?: 'WAV_16000HZ_MONO_16BIT',
                'volume' => self::toPercentVolume($input['volume'] ?? 60),
                'speech_rate' => $speechRate,
                'pitch_rate' => $pitchRate,
                'bit_rate' => max(1, intval($input['bit_rate'] ?? 32)),
                'enable_word_timestamp' => false,
                'seed' => 0,
                'language_hints' => [$language],
                'instruction' => $instruction,
                'enable_aigc_tag' => false,
                'aigc_propagator' => '',
                'aigc_propagate_id' => '',
                'hot_fix' => '',
                'enable_markdown_filter' => false,
            ];
        }

        $payload = [
            'voice_id' => $voiceValue,
            'content' => $text,
            'model_type' => strval($modelType),
        ];
        if ($family === '3d') {
            $payload['language'] = trim(strval($input['language'] ?? 'zh-CN')) ?: 'zh-CN';
        }
        if ($family === 'vpro') {
            $vproModel = intval($input['vpro_model'] ?? ($input['model'] ?? 1));
            $payload['model'] = ($vproModel === 2) ? '2' : '1';
        }
        return $payload;
    }

    public static function buildClonePayload(array $spec, array $input): array
    {
        $family = strval($spec['family'] ?? 'spro');
        $sampleUrl = trim(strval($input['sample_url'] ?? ($input['voice_sample_url'] ?? ($input['url'] ?? ''))));
        $name = trim(strval($input['name'] ?? ($input['voice_name'] ?? ($input['prefix'] ?? '')))) ?: ('我的声音-' . date('His'));
        $sex = intval($input['sex'] ?? ($input['voice_clone_sex'] ?? 1));
        if ($sex !== 2) {
            $sex = 1;
        }

        if ($family === 'minimax') {
            $promptText = trim(strval($input['clone_text'] ?? ($input['text'] ?? ''))) ?: '您好，很高兴为您提供配音服务。';
            return [
                'voice_service_code' => strval($spec['voice_service_code'] ?? 'miniMax'),
                'voice_url' => $sampleUrl,
                'sex' => $sex,
                'voice_name' => $name,
                'prompt_audio_url' => $sampleUrl,
                'text' => $promptText,
                'model' => trim(strval($input['minimax_model'] ?? ($spec['default_minimax_model'] ?? 'speech-02-hd'))) ?: 'speech-02-hd',
            ];
        }

        if ($family === 'professional' || $family === 'tts666') {
            return [
                'voice_service_code' => strval($spec['voice_service_code'] ?? 'niwaai'),
                'voice_name' => $name,
                'voice_url' => $sampleUrl,
                'description' => trim(strval($input['description'] ?? ($input['instruction'] ?? ''))) ?: $name,
                'sex' => $sex,
            ];
        }

        $language = trim(strval($input['language'] ?? ($input['voice_language'] ?? 'zh'))) ?: 'zh';
        return [
            'voice_service_code' => strval($spec['voice_service_code'] ?? 'SPro'),
            'url' => $sampleUrl,
            'sex' => $sex,
            'prefix' => $name,
            'target_model' => strval($spec['target_model'] ?? 'cosyvoice-v3.5-flash'),
            'language_hints' => [$language],
            'max_prompt_audio_length' => 25,
            'enable_preprocess' => true,
        ];
    }

    public static function mergeApi(array $spec): string
    {
        return strval($spec['merge_api'] ?? 'pro') ?: 'pro';
    }

    public static function cloneApi(array $spec): string
    {
        return strval($spec['clone_api'] ?? 'pro') ?: 'pro';
    }

    private static function detectFamily(string $modelCode, array $config, array $input): string
    {
        $service = strtolower(trim(strval($input['voice_service_code'] ?? ($config['voice_service_code'] ?? ''))));
        $platform = strtolower(trim(strval($input['platform_code'] ?? ($config['platform_code'] ?? ''))));
        $familyHint = strtolower(trim(strval($input['family'] ?? ($config['family'] ?? ''))));
        $code = strtolower(trim($modelCode . ' ' . $familyHint));

        if (strpos($code, 'minimax') !== false || strpos($code, 'hailuo') !== false) {
            return 'minimax';
        }
        if (strpos($code, 'tts666') !== false) {
            return 'tts666';
        }
        if (strpos($code, '3d') !== false) {
            return '3d';
        }
        if (strpos($code, 'professional') !== false) {
            return 'professional';
        }
        if (strpos($code, 'basic-clone') !== false || strpos($code, 'niwa-avatar') !== false) {
            return 'niwa';
        }
        if (strpos($code, 'vpro') !== false || strpos($code, 'avatar') !== false) {
            return 'vpro';
        }
        if (strpos($code, 'spro') !== false || strpos($code, 'cosyvoice') !== false || strpos($code, 'niwaai-tts') !== false || strpos($code, 'voice-clone') !== false) {
            return 'spro';
        }

        if (in_array($service, ['minimax', 'hailuo'], true)) {
            return 'minimax';
        }
        if ($service === 'tts666') {
            return 'tts666';
        }
        if ($service === 'niwaai') {
            return 'professional';
        }
        if ($platform === '3d') {
            return '3d';
        }
        if ($platform === 'niwa') {
            return 'niwa';
        }
        if ($platform === 'vpro') {
            return 'vpro';
        }
        return 'spro';
    }

    private static function configOf(string $family): array
    {
        $spec = self::families()[$family] ?? self::sproFamily();
        return [
            'family' => $family,
            'voice_service_code' => strval($spec['voice_service_code'] ?? ''),
            'target_model' => strval($spec['target_model'] ?? ''),
            'platform_code' => strval($spec['platform_code'] ?? ''),
            'clone_api' => strval($spec['clone_api'] ?? ''),
            'merge_api' => strval($spec['merge_api'] ?? ''),
        ];
    }

    private static function sproFamily(): array
    {
        return [
            'family' => 'spro',
            'label' => 'Pro 极速版',
            'voice_service_code' => 'SPro',
            'target_model' => 'cosyvoice-v3.5-flash',
            'platform_code' => '',
            'clone_api' => 'pro',
            'merge_api' => 'pro',
            'supports_clone' => true,
            'params' => ['speed', 'pitch', 'volume', 'instruction', 'language'],
            'clone_params' => ['language', 'instruction', 'sex'],
            'language_options' => [
                ['value' => 'zh', 'label' => '中文'],
                ['value' => 'en', 'label' => '英语'],
                ['value' => 'ja', 'label' => '日语'],
                ['value' => 'ko', 'label' => '韩语'],
            ],
        ];
    }

    private static function professionalFamily(): array
    {
        return [
            'family' => 'professional',
            'label' => '拟娲专业',
            'voice_service_code' => 'niwaai',
            'target_model' => '',
            'platform_code' => '',
            'clone_api' => 'basic',
            'merge_api' => 'basic',
            'supports_clone' => true,
            'params' => [],
            'clone_params' => ['sex', 'description'],
        ];
    }

    private static function tts666Family(): array
    {
        return [
            'family' => 'tts666',
            'label' => '基础公模 tts666',
            'voice_service_code' => 'tts666',
            'target_model' => '',
            'platform_code' => '',
            'clone_api' => 'none',
            'merge_api' => 'basic',
            'supports_clone' => false,
            'params' => [],
            'clone_params' => [],
        ];
    }

    private static function threeDFamily(): array
    {
        return [
            'family' => '3d',
            'label' => '3D精品版',
            'voice_service_code' => 'niwaai',
            'target_model' => '',
            'platform_code' => '3D',
            'clone_api' => 'basic',
            'merge_api' => 'basic',
            'supports_clone' => true,
            'params' => ['language'],
            'clone_params' => ['sex'],
            'language_options' => [
                ['value' => 'zh-CN', 'label' => '中文（普通话、简体）'],
                ['value' => 'zh-HK', 'label' => '中文（粤语、繁体）'],
                ['value' => 'zh-TW', 'label' => '中文（台湾普通话，繁体）'],
                ['value' => 'nan-CN', 'label' => '中文（闽南话）'],
                ['value' => 'en-US', 'label' => '英语（美国）'],
                ['value' => 'en-GB', 'label' => '英语（英国）'],
                ['value' => 'ja-JP', 'label' => '日语'],
                ['value' => 'ko-KR', 'label' => '韩语'],
            ],
        ];
    }

    private static function minimaxFamily(): array
    {
        $models = [
            ['value' => 'speech-2.6-hd', 'label' => 'speech-2.6-hd'],
            ['value' => 'speech-2.6-turbo', 'label' => 'speech-2.6-turbo'],
            ['value' => 'speech-02-hd', 'label' => 'speech-02-hd'],
            ['value' => 'speech-02-turbo', 'label' => 'speech-02-turbo'],
        ];
        return [
            'family' => 'minimax',
            'label' => '海螺 MiniMax',
            'voice_service_code' => 'miniMax',
            'target_model' => '',
            'platform_code' => '',
            'clone_api' => 'minimax',
            'merge_api' => 'minimax',
            'supports_clone' => true,
            'default_minimax_model' => 'speech-02-hd',
            'params' => ['minimax_model'],
            'clone_params' => ['sex', 'minimax_model', 'clone_text'],
            'minimax_model_options' => $models,
        ];
    }

    private static function vproFamily(): array
    {
        return [
            'family' => 'vpro',
            'label' => 'VPro 高仿真版',
            'voice_service_code' => '',
            'target_model' => '',
            'platform_code' => 'VPro',
            'clone_api' => 'none',
            'merge_api' => 'pro',
            'supports_clone' => true,
            'params' => ['vpro_model'],
            'clone_params' => ['sex'],
            'vpro_model_options' => [
                ['value' => '1', 'label' => 'Model 1'],
                ['value' => '2', 'label' => 'Model 2'],
            ],
        ];
    }

    private static function niwaAvatarFamily(): array
    {
        return [
            'family' => 'niwa',
            'label' => '拟娲基础数字人',
            'voice_service_code' => '',
            'target_model' => '',
            'platform_code' => 'niwa',
            'clone_api' => 'none',
            'merge_api' => 'basic',
            'supports_clone' => true,
            'params' => [],
            'clone_params' => ['sex'],
        ];
    }

    private static function clampRatio(float $value, float $min, float $max, float $fallback): float
    {
        if ($value <= 0) {
            return $fallback;
        }
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }

    private static function toPercentVolume($value): int
    {
        $number = floatval($value);
        if ($number <= 0) {
            return 60;
        }
        if ($number <= 3) {
            return max(1, min(100, intval(round($number * 50))));
        }
        return max(1, min(100, intval(round($number))));
    }
}
