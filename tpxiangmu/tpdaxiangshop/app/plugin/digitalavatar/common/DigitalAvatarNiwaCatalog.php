<?php

namespace app\plugin\digitalavatar\common;

/**
 * 按拟娲开放平台大模型识别码组织数字人插件的声音/数字人/图片产品线。
 */
class DigitalAvatarNiwaCatalog
{
    public static function families(): array
    {
        return [
            'spro' => self::sproFamily(),
            'pro_voice' => self::proVoiceFamily(),
            'max_voice' => self::maxVoiceFamily(),
            'nwmax' => self::nwmaxFamily(),
            'vpro' => self::vproFamily(),
            'pro_avatar' => self::proAvatarFamily(),
            'max_avatar' => self::maxAvatarFamily(),
            'xpro' => self::xproFamily(),
            'imagea' => self::imageAFamily(),
        ];
    }

    public static function seedTemplates(string $providerKey): array
    {
        $providerKey = trim($providerKey);
        if ($providerKey === '') {
            return [];
        }
        $rows = [];
        foreach ([
            ['voice_clone', 'SPro 声音克隆', 'SPro', 'spro', '拟娲 SPro 声音大模型克隆，支持方言', 1, 10],
            ['voice_clone', 'Pro 极速版克隆', 'Pro', 'pro_voice', '拟娲 Pro 极速版常用语种声音克隆', 0, 20],
            ['voice_clone', 'Max 声音克隆', 'Max', 'max_voice', '拟娲 Max 声音大模型克隆', 0, 30],
            ['voice_clone', 'NWMax 声音克隆', 'NWMax', 'nwmax', '拟娲 NWMax 声音大模型克隆', 0, 40],
            ['audio', 'SPro 声音合成', 'SPro', 'spro', '拟娲 SPro 声音合成，按文案字数计费', 1, 10],
            ['audio', 'Pro 极速版合成', 'Pro', 'pro_voice', '拟娲 Pro 极速版声音合成', 0, 20],
            ['audio', 'Max 声音合成', 'Max', 'max_voice', '拟娲 Max 声音合成', 0, 30],
            ['audio', 'NWMax 声音合成', 'NWMax', 'nwmax', '拟娲 NWMax 声音合成', 0, 40],
            ['avatar_clone', 'VPro 高清数字人克隆', 'VPro', 'vpro', 'VPro 高清版，最高可上传 500M 形象素材', 1, 10],
            ['avatar_clone', 'Pro 商业数字人克隆', 'Pro', 'pro_avatar', 'Pro 商业版，最高可上传 100M 形象素材', 0, 20],
            ['avatar_clone', 'Max 极速数字人克隆', 'Max', 'max_avatar', 'Max 极速版形象克隆，克隆后可用于 Max 口播视频', 0, 30],
            ['avatar_clone', 'XPro 图片数字人', 'XPro', 'xpro', '上传人物图片即可保存形象，不调用第三方克隆', 0, 35],
            ['avatar_video', 'VPro 高清数字人', 'VPro', 'vpro', '先克隆形象再合成口播视频', 1, 10],
            ['avatar_video', 'Pro 商业数字人', 'Pro', 'pro_avatar', '先克隆形象再合成口播视频', 0, 20],
            ['avatar_video', 'Max 极速数字人', 'Max', 'max_avatar', '无需克隆形象，按音频时长计费', 0, 30],
            ['avatar_video', 'XPro 图片数字人', 'XPro', 'xpro', '使用已保存的 XPro 图片形象生成口播视频', 0, 40],
            ['image', 'imageA 图片生成', 'imageA', 'imagea', '拟娲 imageA 图片生成，model=gpt-image-2', 1, 10],
        ] as $item) {
            $rows[] = [
                'task_type' => $item[0],
                'title' => $item[1],
                'model_code' => $item[2],
                'provider_key' => $providerKey,
                'description' => $item[4],
                'credit_cost' => ($item[0] === 'avatar_clone' && strcasecmp($item[2], 'XPro') === 0) ? 0 : 1,
                'is_default' => $item[5],
                'status' => 1,
                'sort' => $item[6],
                'config' => self::configOf($item[3]),
            ];
        }
        return $rows;
    }

    public static function resolve(string $modelCode = '', array $config = [], array $input = []): array
    {
        $family = self::detectFamily($modelCode, $config, $input);
        $spec = self::families()[$family] ?? self::sproFamily();
        $inputService = trim(strval($input['voice_service_code'] ?? ''));
        $configService = trim(strval($config['voice_service_code'] ?? ''));
        if ($inputService !== '' && self::familyOfService($inputService) === $family) {
            $spec['voice_service_code'] = $inputService;
        } elseif ($configService !== '' && self::familyOfService($configService) === $family) {
            $spec['voice_service_code'] = $configService;
        }
        foreach (['target_model', 'platform_code', 'model'] as $field) {
            $value = trim(strval($input[$field] ?? ($config[$field] ?? '')));
            if ($value === '') {
                continue;
            }
            if ($field === 'model' && $family === 'imagea' && !preg_match('/gpt-image|imagea/i', $value)) {
                continue;
            }
            $spec[$field] = $value;
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
        $paramFields = array_values($spec['param_fields'] ?? []);
        $requiredParams = array_values($spec['required_params'] ?? []);
        $optionalParams = array_values($spec['optional_params'] ?? []);
        if ($paramFields === [] && !empty($spec['params'])) {
            $requiredParams = array_values($spec['params']);
        }
        return [
            'family' => strval($spec['family'] ?? ''),
            'params' => array_values($spec['params'] ?? []),
            'required_params' => $requiredParams,
            'optional_params' => $optionalParams,
            'param_fields' => $paramFields,
            'clone_params' => array_values($spec['clone_params'] ?? []),
            'clone_param_fields' => array_values($spec['clone_param_fields'] ?? []),
            'language_options' => array_values($spec['language_options'] ?? []),
            'minimax_model_options' => array_values($spec['minimax_model_options'] ?? []),
            'vpro_model_options' => array_values($spec['vpro_model_options'] ?? []),
            'supports_clone' => !empty($spec['supports_clone']),
            'needs_avatar_clone' => !empty($spec['needs_avatar_clone']),
            'sample_kind' => strval($spec['sample_kind'] ?? ((strval($spec['family'] ?? '') === 'xpro') ? 'image' : 'video')),
            'local_clone' => !empty($spec['local_clone']) || strval($spec['family'] ?? '') === 'xpro' ? 1 : 0,
            'voice_service_code' => strval($spec['voice_service_code'] ?? ''),
            'platform_code' => strval($spec['platform_code'] ?? ''),
            'target_model' => strval($spec['target_model'] ?? ''),
            'model' => strval($spec['model'] ?? ''),
        ];
    }

    public static function cloneApi(array $spec): string
    {
        return strval($spec['clone_api'] ?? 'spro') ?: 'spro';
    }

    public static function mergeApi(array $spec): string
    {
        return strval($spec['merge_api'] ?? 'spro') ?: 'spro';
    }

    public static function buildClonePayload(array $spec, array $input): array
    {
        $family = strval($spec['family'] ?? 'spro');
        $sampleUrl = trim(strval($input['sample_url'] ?? ($input['voice_sample_url'] ?? ($input['url'] ?? ''))));
        $name = trim(strval($input['name'] ?? ($input['voice_name'] ?? ($input['prefix'] ?? '')))) ?: ('我的声音-' . date('His'));
        $sex = intval($input['sex'] ?? ($input['voice_clone_sex'] ?? 1));
        $sex = $sex === 2 ? 2 : 1;
        $code = strval($spec['voice_service_code'] ?? '');

        if ($family === 'max_voice') {
            $promptText = trim(strval($input['clone_text'] ?? ($input['text'] ?? ''))) ?: '您好，很高兴为您提供配音服务。';
            return [
                'voice_service_code' => $code ?: 'Max',
                'voice_url' => $sampleUrl,
                'sex' => $sex,
                'voice_name' => $name,
                'prompt_audio_url' => $sampleUrl,
                'text' => $promptText,
                'model' => trim(strval($input['minimax_model'] ?? ($spec['default_minimax_model'] ?? 'speech-2.8-hd'))) ?: 'speech-2.8-hd',
            ];
        }

        if ($family === 'nwmax') {
            $promptText = trim(strval($input['clone_text'] ?? ($input['text'] ?? ''))) ?: '您好，很高兴为您提供配音服务。';
            $languageBoost = trim(strval($input['language_boost'] ?? ''));
            $cloneInput = [
                'audio_url' => $sampleUrl,
                'clone_prompt' => [
                    'prompt_audio' => $sampleUrl,
                    'prompt_text' => $promptText,
                ],
                'text' => $promptText,
                'need_noise_reduction' => self::toBoolFlag($input['need_noise_reduction'] ?? 0),
                'need_volume_normalization' => self::toBoolFlag($input['need_volume_normalization'] ?? 0),
                'aigc_watermark' => self::toBoolFlag($input['aigc_watermark'] ?? 0),
            ];
            if ($languageBoost !== '') {
                $cloneInput['language_boost'] = $languageBoost;
            }
            return [
                'voice_service_code' => $code ?: 'NWMax',
                'voice_name' => $name,
                'sex' => $sex,
                'model' => trim(strval($input['minimax_model'] ?? ($spec['model'] ?? ''))),
                'input' => $cloneInput,
            ];
        }

        if ($family === 'pro_voice') {
            return [
                'voice_service_code' => $code ?: 'Pro',
                'voice_name' => $name,
                'voice_url' => $sampleUrl,
                'description' => trim(strval($input['description'] ?? ($input['instruction'] ?? ''))) ?: $name,
                'sex' => $sex,
            ];
        }

        $language = trim(strval($input['language'] ?? ($input['voice_language'] ?? 'zh'))) ?: 'zh';
        return [
            'voice_service_code' => $code ?: 'SPro',
            'url' => $sampleUrl,
            'sex' => $sex,
            'prefix' => $name,
            'target_model' => strval($spec['target_model'] ?? 'cosyvoice-v3.5-flash'),
            'language_hints' => [$language],
            'max_prompt_audio_length' => 25,
            'enable_preprocess' => true,
        ];
    }

    public static function buildAudioPayload(array $spec, array $profile, array $input, string $voiceId, string $text): array
    {
        $family = strval($spec['family'] ?? 'spro');
        $modelType = intval($profile['model_type'] ?? 0);
        $modelType = $modelType === 2 ? 2 : ($modelType === 1 ? 1 : intval($input['voice_model_type'] ?? ($input['model_type'] ?? 1)));
        $modelType = $modelType === 2 ? 2 : 1;
        $voiceValue = ctype_digit($voiceId) ? intval($voiceId) : $voiceId;

        if ($family === 'max_voice') {
            return self::buildMaxAudioPayload($spec, $input, $voiceValue, $modelType, $text);
        }

        if ($family === 'nwmax') {
            return self::buildNwmaxAudioPayload($spec, $input, $voiceId, $modelType, $text);
        }

        if ($family === 'pro_voice') {
            return [
                'voice_id' => $voiceValue,
                'content' => $text,
                'model_type' => strval($modelType),
                'speed_ratio' => self::clampRatio(floatval($input['speech_rate'] ?? ($input['speed'] ?? 1.0)), 0.5, 2.0, 1.0),
                'pitch_ratio' => self::clampRatio(floatval($input['pitch_rate'] ?? ($input['pitch'] ?? 1.0)), 0.5, 2.0, 1.0),
                'volume_ratio' => self::clampRatio(floatval($input['volume'] ?? 1.0), 0.1, 2.0, 1.0),
            ];
        }

        $language = trim(strval($input['language'] ?? ($profile['language'] ?? 'zh'))) ?: 'zh';
        $instruction = trim(strval($input['instruction'] ?? ($profile['instruction'] ?? '请用自然平稳的语气说话。'))) ?: '请用自然平稳的语气说话。';
        $format = trim(strval($input['format'] ?? 'WAV_16000HZ_MONO_16BIT')) ?: 'WAV_16000HZ_MONO_16BIT';
        return [
            'voice_id' => $voiceValue,
            'model_type' => $modelType,
            'text' => $text,
            'format' => $format,
            'volume' => self::toPercentVolume($input['volume'] ?? 60),
            'speech_rate' => self::clampRatio(floatval($input['speech_rate'] ?? ($input['speed'] ?? 1.0)), 0.5, 2.0, 1.0),
            'pitch_rate' => self::clampRatio(floatval($input['pitch_rate'] ?? ($input['pitch'] ?? 1.0)), 0.5, 2.0, 1.0),
            'bit_rate' => max(1, intval($input['bit_rate'] ?? 32)),
            'enable_word_timestamp' => self::toBoolFlag($input['enable_word_timestamp'] ?? false),
            'seed' => max(0, intval($input['seed'] ?? 0)),
            'language_hints' => [$language],
            'instruction' => $instruction,
            'enable_aigc_tag' => self::toBoolFlag($input['enable_aigc_tag'] ?? false),
            'aigc_propagator' => trim(strval($input['aigc_propagator'] ?? '')),
            'aigc_propagate_id' => trim(strval($input['aigc_propagate_id'] ?? '')),
            'hot_fix' => trim(strval($input['hot_fix'] ?? '')),
            'enable_markdown_filter' => self::toBoolFlag($input['enable_markdown_filter'] ?? false),
        ];
    }

    private static function buildMaxAudioPayload(array $spec, array $input, $voiceValue, int $modelType, string $text): array
    {
        $outputFormat = strtolower(trim(strval($input['output_format'] ?? 'url'))) ?: 'url';
        if (!in_array($outputFormat, ['url', 'hex'], true)) {
            $outputFormat = 'url';
        }
        $emotion = trim(strval($input['emotion'] ?? ''));
        $voiceSetting = [
            'vol' => self::clampRatio(floatval($input['volume'] ?? 1.0), 0.1, 10.0, 1.0),
            'speed' => self::clampRatio(floatval($input['speech_rate'] ?? ($input['speed'] ?? 1.0)), 0.5, 2.0, 1.0),
            'pitch' => self::clampInt($input['nwmax_pitch'] ?? 0, -12, 12, 0),
        ];
        if ($emotion !== '') {
            $voiceSetting['emotion'] = $emotion;
        }
        if (self::toBoolFlag($input['text_normalization'] ?? 0)) {
            $voiceSetting['text_normalization'] = true;
        }
        if (self::toBoolFlag($input['latex_read'] ?? 0)) {
            $voiceSetting['latex_read'] = true;
        }

        $payload = [
            'voice_id' => $voiceValue,
            'model_type' => $modelType,
            'text' => $text,
            'model' => trim(strval($input['minimax_model'] ?? ($spec['default_minimax_model'] ?? 'speech-2.8-hd'))) ?: 'speech-2.8-hd',
            'voice_setting' => $voiceSetting,
            'output_format' => $outputFormat,
        ];

        $audioSetting = self::buildMaxAudioSetting($input, true);
        if ($audioSetting !== []) {
            $payload['audio_setting'] = $audioSetting;
        }
        $tones = self::parseToneList($input['pronunciation_tone'] ?? ($input['pronunciation_dict'] ?? []));
        if ($tones !== []) {
            $payload['pronunciation_dict'] = ['tone' => $tones];
        }
        $languageBoost = trim(strval($input['language_boost'] ?? ''));
        if ($languageBoost !== '') {
            $payload['language_boost'] = $languageBoost;
        }
        $voiceModify = self::buildVoiceModify($input, 'sound_effects');
        if ($voiceModify !== []) {
            $payload['voice_modify'] = $voiceModify;
        }
        $weights = self::parseTimbreWeights($input['timbre_weights'] ?? [], false);
        if ($weights !== []) {
            $payload['timbre_weights'] = $weights;
        }
        if (self::toBoolFlag($input['subtitle_enable'] ?? ($input['subtitle_enabl'] ?? 0))) {
            $payload['subtitle_enabl'] = true;
        }
        if (self::toBoolFlag($input['aigc_watermark'] ?? 0)) {
            $payload['aigc_watermark'] = true;
        }
        return $payload;
    }

    private static function buildNwmaxAudioPayload(array $spec, array $input, string $voiceId, int $modelType, string $text): array
    {
        $emotion = trim(strval($input['emotion'] ?? ''));
        $voiceSetting = [
            'voice_id' => strval($voiceId),
            'voice_model_type' => $modelType,
            'speed' => self::clampRatio(floatval($input['speech_rate'] ?? ($input['speed'] ?? 1.0)), 0.5, 2.0, 1.0),
            'vol' => self::clampRatio(floatval($input['volume'] ?? 1.0), 0.1, 10.0, 1.0),
            'pitch' => self::clampInt($input['nwmax_pitch'] ?? 0, -12, 12, 0),
        ];
        if ($emotion !== '') {
            $voiceSetting['emotion'] = $emotion;
        }
        $inner = [
            'text' => $text,
            'voice_setting' => $voiceSetting,
        ];
        $audioSetting = self::buildMaxAudioSetting($input, false);
        if ($audioSetting !== []) {
            $inner['audio_setting'] = $audioSetting;
        }
        $tones = self::parseToneList($input['pronunciation_tone'] ?? ($input['pronunciation_dict'] ?? []));
        if ($tones !== []) {
            $inner['pronunciation_dict'] = ['tone' => $tones];
        }
        $languageBoost = trim(strval($input['language_boost'] ?? ''));
        if ($languageBoost !== '') {
            $inner['language_boost'] = $languageBoost;
        }
        $voiceModify = self::buildVoiceModify($input, 'sound_effect');
        if ($voiceModify !== []) {
            $inner['voice_modify'] = $voiceModify;
        }
        $weights = self::parseTimbreWeights($input['timbre_weights'] ?? [], true);
        if ($weights !== []) {
            $inner['timbre_weights'] = $weights;
        }
        if (self::toBoolFlag($input['subtitle_enable'] ?? 0)) {
            $inner['subtitle_enable'] = true;
        }
        if (self::toBoolFlag($input['aigc_watermark'] ?? 0)) {
            $inner['aigc_watermark'] = true;
        }
        return [
            'model' => trim(strval($input['minimax_model'] ?? ($spec['model'] ?? 'MiniMax/speech-2.8-hd'))) ?: 'MiniMax/speech-2.8-hd',
            'input' => $inner,
        ];
    }

    public static function buildImagePayload(array $spec, array $input): array
    {
        $prompt = trim(strval($input['prompt'] ?? ($input['text'] ?? ($input['content'] ?? ''))));
        $ratio = trim(strval($input['ratio'] ?? '1:1')) ?: '1:1';
        $size = self::normalizeImageSize($ratio, trim(strval($input['size'] ?? '')));
        $count = 1;
        $images = self::collectPublicImageUrls($input);
        $isAgent = trim(strval($input['source'] ?? '')) === 'image_agent';
        $promptLocked = intval($input['agent_prompt_locked'] ?? 0) === 1;
        if ($isAgent && !$promptLocked) {
            $prompt = DigitalAvatarImageAgentService::visualPromptForGenerate(
                $prompt,
                $images !== [],
                trim(strval($input['user_prompt'] ?? ''))
            );
        }
        $style = trim(strval($input['style'] ?? ''));
        if ($isAgent && $images !== []) {
            $style = '';
        }
        $fullPrompt = $prompt;
        if ($style !== '' && $prompt !== '' && mb_strpos($prompt, $style) === false) {
            $fullPrompt = $prompt . '，风格：' . $style;
        }
        $payload = [
            'platform_code' => trim(strval($input['platform_code'] ?? ($spec['platform_code'] ?? 'imageA'))) ?: 'imageA',
            'model' => self::resolveImageModel($spec, $input),
            'prompt' => $fullPrompt,
            'size' => $size,
            'gen_count' => $count,
        ];
        $quality = self::normalizeImageQuality($input['quality'] ?? '');
        if ($quality !== '') {
            $payload['quality'] = $quality;
        }
        $name = trim(strval($input['name'] ?? ($input['title'] ?? '')));
        if ($name !== '') {
            $payload['name'] = mb_substr($name, 0, 80);
        }
        $channelSearchId = trim(strval($input['channel_search_id'] ?? ''));
        if ($channelSearchId !== '') {
            $payload['channel_search_id'] = $channelSearchId;
        }
        if ($images !== []) {
            $payload['images'] = $images;
        }
        return $payload;
    }

    public static function configOf(string $family): array
    {
        $spec = self::families()[$family] ?? self::sproFamily();
        $row = self::endpointsForFamily($family);
        return [
            'family' => $family,
            'voice_service_code' => strval($spec['voice_service_code'] ?? ''),
            'target_model' => strval($spec['target_model'] ?? ''),
            'platform_code' => strval($spec['platform_code'] ?? ''),
            'model' => strval($spec['model'] ?? ''),
            'clone_api' => strval($spec['clone_api'] ?? ''),
            'merge_api' => strval($spec['merge_api'] ?? ''),
            'needs_avatar_clone' => !empty($spec['needs_avatar_clone']) ? 1 : 0,
            'sample_kind' => strval($spec['sample_kind'] ?? ''),
            'local_clone' => !empty($spec['local_clone']) ? 1 : 0,
            'clone_endpoint' => strval($row['clone'] ?? ''),
            'submit_endpoint' => strval($row['submit'] ?? ''),
            'status_endpoint' => strval($row['status'] ?? ''),
        ];
    }

    public static function endpointsForFamily(string $family): array
    {
        $family = trim($family);
        if ($family === '') {
            return [];
        }
        foreach (self::endpointRows() as $row) {
            if (strval($row['key'] ?? '') === $family) {
                return $row;
            }
        }
        return [];
    }

    public static function applyModelPathOverrides(array $provider, array $modelConfig, array $spec = []): array
    {
        $family = trim(strval($modelConfig['family'] ?? ($spec['family'] ?? '')));
        $row = self::endpointsForFamily($family);
        $overrides = is_array($provider['endpoint_overrides'] ?? null) ? $provider['endpoint_overrides'] : [];

        $clone = trim(strval($modelConfig['clone_endpoint'] ?? ''));
        $submit = trim(strval($modelConfig['submit_endpoint'] ?? ''));
        $status = trim(strval($modelConfig['status_endpoint'] ?? ''));
        if ($clone === '') {
            $clone = trim(strval($row['clone'] ?? ''));
        }
        if ($submit === '') {
            $submit = trim(strval($row['submit'] ?? ''));
        }
        if ($status === '') {
            $status = trim(strval($row['status'] ?? ''));
        }

        $cloneKey = trim(strval($row['clone_key'] ?? ''));
        $submitKey = trim(strval($row['submit_key'] ?? ''));
        $statusKey = trim(strval($row['status_key'] ?? ''));
        if ($cloneKey === '' && $clone !== '') {
            $cloneKey = self::fallbackEndpointKey($family, strval($spec['clone_api'] ?? ''), 'clone');
        }
        if ($submitKey === '' && $submit !== '') {
            $submitKey = self::fallbackEndpointKey($family, strval($spec['merge_api'] ?? ''), 'submit');
        }
        if ($statusKey === '' && $status !== '' && $family !== '') {
            $statusKey = 'status.' . $family;
        }

        if ($clone !== '' && $cloneKey !== '') {
            $overrides[$cloneKey] = $clone;
        }
        if ($submit !== '' && $submitKey !== '') {
            $overrides[$submitKey] = $submit;
        }
        if ($status !== '' && $statusKey !== '') {
            $overrides[$statusKey] = $status;
        }

        $provider['endpoint_overrides'] = $overrides;
        return $provider;
    }

    private static function fallbackEndpointKey(string $family, string $specKey, string $kind): string
    {
        $specKey = trim($specKey);
        if ($specKey !== '') {
            return $specKey;
        }
        if (in_array($family, ['vpro', 'pro_avatar', 'max_avatar', 'xpro'], true) || strpos($family, 'avatar') !== false) {
            if ($kind === 'clone') {
                return 'avatar.clone';
            }
            if ($family === 'max_avatar') {
                return 'avatar.max';
            }
            if ($family === 'xpro') {
                return 'avatar.xpro';
            }
            return 'avatar.merge';
        }
        if ($family === 'imagea' || strpos($family, 'image') !== false) {
            return 'image.submit';
        }
        if ($kind === 'clone') {
            return 'voice.clone';
        }
        return 'voice.merge';
    }

    private static function detectFamily(string $modelCode, array $config, array $input): string
    {
        $configFamily = trim(strval($input['family'] ?? ($config['family'] ?? '')));
        if ($configFamily !== '' && isset(self::families()[$configFamily])) {
            return $configFamily;
        }

        $hint = strtolower(trim(implode(' ', [
            $modelCode,
            strval($input['family'] ?? ($config['family'] ?? '')),
            strval($input['voice_service_code'] ?? ($config['voice_service_code'] ?? '')),
            strval($input['platform_code'] ?? ($config['platform_code'] ?? '')),
            strval($input['task_type'] ?? ''),
        ])));
        $taskType = strtolower(trim(strval($input['task_type'] ?? ($config['task_type'] ?? ''))));

        if (strpos($hint, 'imagea') !== false || strpos($hint, 'gpt-image') !== false) {
            return 'imagea';
        }
        if (strpos($hint, 'xpro') !== false) {
            return 'xpro';
        }
        if (strpos($hint, 'nwmax') !== false || strpos($hint, 'aliminimax') !== false) {
            return 'nwmax';
        }
        if (
            strpos($hint, 'max') !== false
            && (
                strpos($hint, 'avatar') !== false
                || $taskType === 'avatar_video'
                || $taskType === 'avatar_clone'
            )
        ) {
            return 'max_avatar';
        }
        if (strpos($hint, 'max') !== false || strpos($hint, 'minimax') !== false || strpos($hint, 'hailuo') !== false || strpos($hint, '海螺') !== false) {
            return 'max_voice';
        }
        if (strpos($hint, 'vpro') !== false) {
            return 'vpro';
        }
        if ($taskType === 'avatar_clone' || $taskType === 'avatar_video') {
            if (trim($modelCode) === 'Pro' || strpos($hint, 'pro_avatar') !== false || strpos($hint, 'basic-clone') !== false) {
                return 'pro_avatar';
            }
        }
        if (strpos($hint, 'spro') !== false || strpos($hint, 'cosyvoice') !== false || strpos($hint, 'voice-clone') !== false || strpos($hint, 'niwaai-tts') !== false) {
            return 'spro';
        }
        if (trim($modelCode) === 'Pro' || strpos($hint, 'professional') !== false || strpos($hint, 'niwaai') !== false) {
            return ($taskType === 'avatar_clone' || $taskType === 'avatar_video') ? 'pro_avatar' : 'pro_voice';
        }
        return 'spro';
    }

    private static function familyOfService(string $service): string
    {
        $service = strtolower(trim($service));
        if ($service === 'spro') {
            return 'spro';
        }
        if ($service === 'nwmax' || $service === 'aliminimax') {
            return 'nwmax';
        }
        if ($service === 'max' || $service === 'minimax') {
            return 'max_voice';
        }
        if ($service === 'pro' || $service === 'niwaai') {
            return 'pro_voice';
        }
        return '';
    }

    private static function sproFamily(): array
    {
        $languageOptions = [
            ['value' => 'zh', 'label' => '中文'],
            ['value' => 'en', 'label' => '英语'],
            ['value' => 'ja', 'label' => '日语'],
            ['value' => 'ko', 'label' => '韩语'],
        ];
        $fields = [
            self::sliderField('speed', '语速', true, 0.5, 2, 0.1, 1, '对应接口字段 speech_rate'),
            self::sliderField('pitch', '音调', true, 0.5, 2, 0.1, 1, '对应接口字段 pitch_rate'),
            self::sliderField('volume', '音量', true, 0.1, 2, 0.1, 1, '提交时会转换为 0-100 音量'),
            self::selectField('language', '语言', true, $languageOptions, 'zh', '对应接口字段 language_hints'),
            self::textareaField('instruction', '语气指令', true, '请用自然平稳的语气说话。', '指导音色说话风格，对应接口字段 instruction'),
            self::selectField('format', '音频格式', false, self::sproFormatOptions(), 'WAV_16000HZ_MONO_16BIT'),
            self::selectField('bit_rate', '码率', false, self::sproBitRateOptions(), '32', '对应接口字段 bit_rate'),
            self::numberField('seed', '随机种子', false, 0, 999999, 1, 0, '0 表示由接口自动分配'),
            self::switchField('enable_word_timestamp', '字级时间戳', false, 0, '对应接口字段 enable_word_timestamp'),
            self::switchField('enable_aigc_tag', 'AIGC 标记', false, 0, '对应接口字段 enable_aigc_tag'),
            self::textField('aigc_propagator', 'AIGC 传播方', false, '', '对应接口字段 aigc_propagator'),
            self::textField('aigc_propagate_id', 'AIGC 传播ID', false, '', '对应接口字段 aigc_propagate_id'),
            self::textField('hot_fix', '热修复参数', false, '', '对应接口字段 hot_fix'),
            self::switchField('enable_markdown_filter', 'Markdown 过滤', false, 0, '对应接口字段 enable_markdown_filter'),
        ];
        $cloneFields = [
            self::selectField('language', '语言', true, $languageOptions, 'zh'),
            self::textareaField('instruction', '语气指令', false, '请用自然平稳的语气说话。'),
            self::selectField('sex', '性别', true, self::sexOptions(), '1'),
        ];
        return self::withParamFields([
            'family' => 'spro',
            'label' => 'SPro 声音大模型',
            'voice_service_code' => 'SPro',
            'target_model' => 'cosyvoice-v3.5-flash',
            'platform_code' => '',
            'clone_api' => 'spro',
            'merge_api' => 'spro',
            'supports_clone' => true,
            'language_options' => $languageOptions,
        ], $fields, $cloneFields);
    }

    private static function proVoiceFamily(): array
    {
        $fields = [
            self::sliderField('speed', '语速', true, 0.5, 2, 0.1, 1, '对应接口字段 speed_ratio'),
            self::sliderField('pitch', '音调', true, 0.5, 2, 0.1, 1, '对应接口字段 pitch_ratio'),
            self::sliderField('volume', '音量', true, 0.1, 2, 0.1, 1, '对应接口字段 volume_ratio'),
        ];
        $cloneFields = [
            self::selectField('sex', '性别', true, self::sexOptions(), '1'),
            self::textareaField('description', '音色描述', false, '', '对应接口字段 description'),
        ];
        return self::withParamFields([
            'family' => 'pro_voice',
            'label' => 'Pro 极速版声音',
            'voice_service_code' => 'Pro',
            'target_model' => '',
            'platform_code' => '',
            'clone_api' => 'basic',
            'merge_api' => 'basic',
            'supports_clone' => true,
        ], $fields, $cloneFields);
    }

    private static function maxVoiceFamily(): array
    {
        $models = self::maxSpeechModelOptions('');
        $fields = [
            self::selectField('minimax_model', '合成模型', true, $models, 'speech-2.8-hd', '对应接口字段 model'),
            self::sliderField('speed', '语速', false, 0.5, 2, 0.1, 1, '对应 voice_setting.speed，范围 0.5-2，默认 1.0'),
            self::sliderField('volume', '音量', false, 0.1, 10, 0.1, 1, '对应 voice_setting.vol，范围 (0,10]，默认 1'),
            self::sliderField('nwmax_pitch', '语调', false, -12, 12, 1, 0, '对应 voice_setting.pitch，范围 -12 到 12，默认 0'),
            self::selectField('emotion', '情绪', false, self::maxEmotionOptions(), 'calm', '对应 voice_setting.emotion，一般可让模型按文案自动匹配'),
            self::switchField('text_normalization', '文本规范化', false, 0, '对应 voice_setting.text_normalization，提升中英数字朗读，可能略增延迟'),
            self::switchField('latex_read', '朗读公式', false, 0, '对应 voice_setting.latex_read，是否朗读 latex 公式'),
            self::selectField('sample_rate', '采样率', false, self::maxSampleRateOptions(), '32000', '对应 audio_setting.sample_rate'),
            self::selectField('bitrate', '比特率', false, self::maxBitrateOptions(), '128000', '对应 audio_setting.bitrate，仅 mp3 生效'),
            self::selectField('audio_format', '音频格式', false, self::maxAudioFormatOptions(), 'mp3', '对应 audio_setting.format'),
            self::selectField('channel', '声道', false, self::maxChannelOptions(), '1', '对应 audio_setting.channel，1 单声道 / 2 双声道'),
            self::switchField('force_cbr', '恒定比特率', false, 0, '对应 audio_setting.force_cbr'),
            self::textareaField('pronunciation_tone', '发音替换', false, '', '对应 pronunciation_dict.tone，每行一条：原文/替换，如 处理/(chu3)(li3)', 2000),
            self::selectField('language_boost', '语言增强', false, self::maxLanguageOptions(), 'Chinese', '对应 language_boost，speech-01/02 暂不支持波斯语、菲律宾语、泰米尔语'),
            self::sliderField('modify_pitch', '音高调整', false, -100, 100, 1, 0, '对应 voice_modify.pitch，-100 更低沉，100 更明亮'),
            self::sliderField('modify_intensity', '强度调整', false, -100, 100, 1, 0, '对应 voice_modify.intensity，-100 更刚劲，100 更轻柔'),
            self::sliderField('modify_timbre', '音色调整', false, -100, 100, 1, 0, '对应 voice_modify.timbre，-100 更浑厚，100 更清脆'),
            self::selectField('sound_effects', '音效', false, self::maxSoundEffectOptions(), '', '对应 voice_modify.sound_effects，单次只能选一种'),
            self::textareaField('timbre_weights', '混合音色', false, '', '对应 timbre_weights，每行 voice_id,weight，最多 4 种，权重 1-100', 2000),
            self::selectField('output_format', '返回格式', false, [
                ['value' => 'url', 'label' => 'URL'],
                ['value' => 'hex', 'label' => 'HEX'],
            ], 'url', '对应 output_format，非流式可用，url 有效期 24 小时'),
            self::switchField('subtitle_enable', '开启字幕', false, 0, '对应 subtitle_enabl，仅非流式且指定模型有效'),
            self::switchField('aigc_watermark', 'AIGC 标识', false, 0, '对应 aigc_watermark，在音频末尾添加节奏标识'),
        ];
        $cloneFields = [
            self::selectField('sex', '性别', true, self::sexOptions(), '1', '对应接口字段 sex，1=男 2=女'),
            self::selectField('minimax_model', '克隆模型', true, $models, 'speech-2.8-hd', '对应接口字段 model'),
            self::textareaField('clone_text', '复刻文本', false, '您好，很高兴为您提供配音服务。', '对应接口字段 text', 1000),
        ];
        return self::withParamFields([
            'family' => 'max_voice',
            'label' => 'Max 声音大模型',
            'voice_service_code' => 'Max',
            'target_model' => '',
            'platform_code' => '',
            'clone_api' => 'max',
            'merge_api' => 'max',
            'supports_clone' => true,
            'default_minimax_model' => 'speech-2.8-hd',
            'minimax_model_options' => $models,
            'language_options' => self::maxLanguageOptions(),
        ], $fields, $cloneFields);
    }

    private static function nwmaxFamily(): array
    {
        $models = self::maxSpeechModelOptions('MiniMax/');
        $fields = [
            self::selectField('minimax_model', '合成模型', true, $models, 'MiniMax/speech-2.8-hd', '对应接口字段 model，不传则用默认模型'),
            self::sliderField('speed', '语速', false, 0.5, 2, 0.1, 1, '对应 voice_setting.speed，范围 0.5-2.0'),
            self::sliderField('volume', '音量', false, 0.1, 10, 0.1, 1, '对应 voice_setting.vol，范围 (0,10]'),
            self::sliderField('nwmax_pitch', '语调', false, -12, 12, 1, 0, '对应 voice_setting.pitch，范围 -12 到 12'),
            self::selectField('emotion', '情绪', false, self::emotionOptions(), 'calm', '对应 voice_setting.emotion，一般无需手动指定'),
            self::selectField('sample_rate', '采样率', false, self::maxSampleRateOptions(), '32000', '对应 audio_setting.sample_rate'),
            self::selectField('bitrate', '比特率', false, self::maxBitrateOptions(), '128000', '对应 audio_setting.bitrate'),
            self::selectField('channel', '声道', false, self::maxChannelOptions(), '1', '对应 audio_setting.channel'),
            self::textareaField('pronunciation_tone', '发音替换', false, '', '对应 pronunciation_dict.tone，每行一条：原文/替换', 2000),
            self::selectField('language_boost', '语言增强', false, self::maxLanguageOptions(), 'Chinese', '对应 language_boost'),
            self::sliderField('modify_pitch', '音高调整', false, -100, 100, 1, 0, '对应 voice_modify.pitch'),
            self::sliderField('modify_intensity', '强度调整', false, -100, 100, 1, 0, '对应 voice_modify.intensity'),
            self::sliderField('modify_timbre', '音色调整', false, -100, 100, 1, 0, '对应 voice_modify.timbre'),
            self::selectField('sound_effects', '音效', false, self::maxSoundEffectOptions(), '', '对应 voice_modify.sound_effect'),
            self::textareaField('timbre_weights', '混合音色', false, '', '对应 timbre_weights，每行 voice_id,weight，最多 4 种', 2000),
            self::switchField('subtitle_enable', '开启字幕', false, 0, '对应 subtitle_enable'),
            self::switchField('aigc_watermark', 'AIGC 标识', false, 0, '对应 aigc_watermark'),
        ];
        $cloneFields = [
            self::selectField('sex', '性别', true, self::sexOptions(), '1'),
            self::textareaField('clone_text', '复刻文本', true, '您好，很高兴为您提供配音服务。', '对应 input.text，限 1000 字', 1000),
            self::selectField('language_boost', '语言增强', false, self::maxLanguageOptions(), 'Chinese', '对应 input.language_boost'),
            self::selectField('minimax_model', '克隆模型', false, $models, 'MiniMax/speech-2.8-hd', '对应 model，不传为默认模型'),
            self::switchField('need_noise_reduction', '降噪', false, 0, '对应 input.need_noise_reduction'),
            self::switchField('need_volume_normalization', '音量归一化', false, 0, '对应 input.need_volume_normalization'),
            self::switchField('aigc_watermark', 'AIGC 标识', false, 0, '对应 input.aigc_watermark'),
        ];
        return self::withParamFields([
            'family' => 'nwmax',
            'label' => 'NWMax 声音大模型',
            'voice_service_code' => 'NWMax',
            'target_model' => '',
            'platform_code' => '',
            'model' => 'MiniMax/speech-2.8-hd',
            'clone_api' => 'nwmax',
            'merge_api' => 'nwmax',
            'supports_clone' => true,
            'minimax_model_options' => $models,
            'language_options' => self::maxLanguageOptions(),
        ], $fields, $cloneFields);
    }

    private static function vproFamily(): array
    {
        return [
            'family' => 'vpro',
            'label' => 'VPro 高清数字人',
            'voice_service_code' => '',
            'platform_code' => 'VPro',
            'clone_api' => 'avatar',
            'merge_api' => 'avatar',
            'supports_clone' => true,
            'needs_avatar_clone' => true,
            'params' => [],
            'clone_params' => ['sex'],
        ];
    }

    private static function proAvatarFamily(): array
    {
        return [
            'family' => 'pro_avatar',
            'label' => 'Pro 商业数字人',
            'voice_service_code' => '',
            'platform_code' => 'Pro',
            'clone_api' => 'avatar',
            'merge_api' => 'avatar',
            'supports_clone' => true,
            'needs_avatar_clone' => true,
            'params' => [],
            'clone_params' => ['sex'],
        ];
    }

    private static function maxAvatarFamily(): array
    {
        return [
            'family' => 'max_avatar',
            'label' => 'Max 极速数字人',
            'voice_service_code' => '',
            'platform_code' => 'Max',
            'model' => 'Max1.0',
            'clone_api' => 'avatar',
            'merge_api' => 'max_avatar',
            'supports_clone' => true,
            'needs_avatar_clone' => false,
            'params' => ['max_avatar_model'],
            'clone_params' => ['sex'],
        ];
    }

    private static function xproFamily(): array
    {
        return [
            'family' => 'xpro',
            'label' => 'XPro 图片数字人',
            'voice_service_code' => '',
            'platform_code' => 'XPro',
            'model' => 'XPro1.0',
            'clone_api' => 'none',
            'merge_api' => 'xpro',
            'supports_clone' => true,
            'needs_avatar_clone' => false,
            'local_clone' => true,
            'sample_kind' => 'image',
            'params' => ['xpro_model'],
            'clone_params' => [],
        ];
    }

    private static function imageAFamily(): array
    {
        $fields = [
            self::imageListField('reference_images', '参考图片', false, '对应接口字段 images，可上传多张参考图'),
            self::selectField('ratio', '图片比例', true, self::imageRatioOptions(), '1:1', '对应接口字段 size，支持 1:1 / 3:2 / 2:3 / auto 或宽x高'),
            self::selectField('count', '生成张数', true, self::imageCountOptions(), '1', '张数表示同时提交多少个独立任务，每个任务向接口只传 gen_count=1，最高 5 张'),
            self::selectField('style', '风格', false, self::imageStyleOptions(), '摄影写实', '会写入 prompt 风格说明'),
            self::selectField('quality', '生成画质', false, self::imageQualityOptions(), 'high', '对应接口字段 quality：low / medium / high'),
        ];
        return self::withParamFields([
            'family' => 'imagea',
            'label' => 'imageA 图片生成',
            'voice_service_code' => '',
            'platform_code' => 'imageA',
            'model' => 'gpt-image-2',
            'clone_api' => 'none',
            'merge_api' => 'imagea',
            'supports_clone' => false,
        ], $fields);
    }

    public static function mergeEndpointConfig($saved): array
    {
        $saved = is_array($saved) ? $saved : [];
        $result = [];
        foreach (self::endpointRows() as $row) {
            $key = strval($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $item = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $status = trim(strval($item['status'] ?? ''));
            if ($status === '') {
                $group = strval($row['group'] ?? '');
                if ($group === 'voice') {
                    $status = trim(strval($saved['voice_status']['status'] ?? ($row['status'] ?? '')));
                } elseif ($group === 'avatar') {
                    $status = trim(strval($saved['avatar_status']['status'] ?? ($row['status'] ?? '')));
                } else {
                    $status = trim(strval($row['status'] ?? ''));
                }
            }
            $result[$key] = [
                'clone' => trim(strval($item['clone'] ?? ($row['clone'] ?? ''))),
                'submit' => trim(strval($item['submit'] ?? ($row['submit'] ?? ''))),
                'status' => $status,
            ];
        }
        return $result;
    }

    public static function buildEndpointOverrides(array $extra): array
    {
        $saved = self::mergeEndpointConfig($extra['model_endpoints'] ?? []);
        $overrides = [];
        foreach (self::endpointRows() as $row) {
            $key = strval($row['key'] ?? '');
            $item = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            foreach (['clone' => 'clone_key', 'submit' => 'submit_key', 'status' => 'status_key'] as $field => $mapKey) {
                $endpointKey = trim(strval($row[$mapKey] ?? ''));
                $path = trim(strval($item[$field] ?? ''));
                if ($endpointKey === '' || $path === '') {
                    continue;
                }
                $overrides[$endpointKey] = $path;
            }
        }
        return $overrides;
    }

    public static function endpointRows(): array
    {
        $voiceStatus = '/openapi/figure/audioMergeStatus/';
        $avatarStatus = '/openapi/video/cloneStatus/';
        return [
            [
                'key' => 'spro',
                'label' => 'SPro 声音',
                'hint' => '声音克隆 / 合成 / 查询',
                'group' => 'voice',
                'clone' => '/openapi/figure/proVoiceClone',
                'submit' => '/openapi/figure/proVoiceMerge',
                'status' => $voiceStatus,
                'clone_key' => 'voice.clone',
                'submit_key' => 'voice.merge',
                'status_key' => 'status.spro',
                'has_clone' => 1,
                'has_submit' => 1,
                'has_status' => 1,
            ],
            [
                'key' => 'pro_voice',
                'label' => 'Pro 声音',
                'hint' => '极速版克隆 / 合成 / 查询',
                'group' => 'voice',
                'clone' => '/openapi/figure/voiceClone',
                'submit' => '/openapi/figure/audioMerge',
                'status' => $voiceStatus,
                'clone_key' => 'voice.clone.basic',
                'submit_key' => 'voice.merge.basic',
                'status_key' => 'status.pro_voice',
                'has_clone' => 1,
                'has_submit' => 1,
                'has_status' => 1,
            ],
            [
                'key' => 'max_voice',
                'label' => 'Max 声音',
                'hint' => 'Max 声音克隆 / 合成 / 查询',
                'group' => 'voice',
                'clone' => '/openapi/figure/maxVoiceClone',
                'submit' => '/openapi/figure/maxAudioMerge',
                'status' => $voiceStatus,
                'clone_key' => 'voice.clone.max',
                'submit_key' => 'voice.merge.max',
                'status_key' => 'status.max_voice',
                'has_clone' => 1,
                'has_submit' => 1,
                'has_status' => 1,
            ],
            [
                'key' => 'nwmax',
                'label' => 'NWMax 声音',
                'hint' => 'NWMax 声音克隆 / 合成 / 查询',
                'group' => 'voice',
                'clone' => '/openapi/figure/NWMaxClone',
                'submit' => '/openapi/figure/NWMaxMerge',
                'status' => $voiceStatus,
                'clone_key' => 'voice.clone.nwmax',
                'submit_key' => 'voice.merge.nwmax',
                'status_key' => 'status.nwmax',
                'has_clone' => 1,
                'has_submit' => 1,
                'has_status' => 1,
            ],
            [
                'key' => 'vpro',
                'label' => 'VPro 数字人',
                'hint' => '高清形象克隆 / 口播合成 / 查询',
                'group' => 'avatar',
                'clone' => '/openapi/video/clone',
                'submit' => '/openapi/video/merge',
                'status' => $avatarStatus,
                'clone_key' => 'avatar.clone',
                'submit_key' => 'avatar.merge',
                'status_key' => 'status.vpro',
                'has_clone' => 1,
                'has_submit' => 1,
                'has_status' => 1,
            ],
            [
                'key' => 'pro_avatar',
                'label' => 'Pro 数字人',
                'hint' => '商业版形象克隆 / 口播合成 / 查询',
                'group' => 'avatar',
                'clone' => '/openapi/video/clone',
                'submit' => '/openapi/video/merge',
                'status' => $avatarStatus,
                'clone_key' => 'avatar.clone',
                'submit_key' => 'avatar.merge',
                'status_key' => 'status.pro_avatar',
                'has_clone' => 1,
                'has_submit' => 1,
                'has_status' => 1,
            ],
            [
                'key' => 'max_avatar',
                'label' => 'Max 数字人',
                'hint' => 'Max 形象克隆 / 极速口播合成 / 查询',
                'group' => 'avatar',
                'clone' => '/openapi/video/clone',
                'submit' => '/openapi/video/submitVideo',
                'status' => $avatarStatus,
                'clone_key' => 'avatar.clone',
                'submit_key' => 'avatar.max',
                'status_key' => 'status.max_avatar',
                'has_clone' => 1,
                'has_submit' => 1,
                'has_status' => 1,
            ],
            [
                'key' => 'xpro',
                'label' => 'XPro 数字人',
                'hint' => '图片数字人，无需克隆形象',
                'group' => 'avatar',
                'clone' => '',
                'submit' => '/openapi/video/submitImageHuman',
                'status' => $avatarStatus,
                'clone_key' => '',
                'submit_key' => 'avatar.xpro',
                'status_key' => 'status.xpro',
                'has_clone' => 0,
                'has_submit' => 1,
                'has_status' => 1,
            ],
            [
                'key' => 'imagea',
                'label' => 'imageA 图片',
                'hint' => '图片生成 / 查询',
                'group' => 'image',
                'clone' => '',
                'submit' => '/openapi/image/submitImage',
                'status' => '/openapi/image/getGenImageRes/',
                'clone_key' => '',
                'submit_key' => 'image.submit',
                'status_key' => 'status.imagea',
                'has_clone' => 0,
                'has_submit' => 1,
                'has_status' => 1,
            ],
        ];
    }

    private static function maxLanguageOptions(): array
    {
        return [
            ['value' => '', 'label' => '不指定'],
            ['value' => 'auto', 'label' => '自动识别'],
            ['value' => 'Chinese', 'label' => '中文'],
            ['value' => 'Chinese,Yue', 'label' => '粤语'],
            ['value' => 'English', 'label' => '英语'],
            ['value' => 'Spanish', 'label' => '西班牙语'],
            ['value' => 'French', 'label' => '法语'],
            ['value' => 'Russian', 'label' => '俄语'],
            ['value' => 'German', 'label' => '德语'],
            ['value' => 'Portuguese', 'label' => '葡萄牙语'],
            ['value' => 'Arabic', 'label' => '阿拉伯语'],
            ['value' => 'Italian', 'label' => '意大利语'],
            ['value' => 'Japanese', 'label' => '日语'],
            ['value' => 'Korean', 'label' => '韩语'],
            ['value' => 'Indonesian', 'label' => '印尼语'],
            ['value' => 'Vietnamese', 'label' => '越南语'],
            ['value' => 'Turkish', 'label' => '土耳其语'],
            ['value' => 'Dutch', 'label' => '荷兰语'],
            ['value' => 'Ukrainian', 'label' => '乌克兰语'],
            ['value' => 'Thai', 'label' => '泰语'],
            ['value' => 'Polish', 'label' => '波兰语'],
            ['value' => 'Romanian', 'label' => '罗马尼亚语'],
            ['value' => 'Greek', 'label' => '希腊语'],
            ['value' => 'Czech', 'label' => '捷克语'],
            ['value' => 'Finnish', 'label' => '芬兰语'],
            ['value' => 'Hindi', 'label' => '印地语'],
            ['value' => 'Bulgarian', 'label' => '保加利亚语'],
            ['value' => 'Danish', 'label' => '丹麦语'],
            ['value' => 'Hebrew', 'label' => '希伯来语'],
            ['value' => 'Malay', 'label' => '马来语'],
            ['value' => 'Persian', 'label' => '波斯语'],
            ['value' => 'Slovak', 'label' => '斯洛伐克语'],
            ['value' => 'Swedish', 'label' => '瑞典语'],
            ['value' => 'Croatian', 'label' => '克罗地亚语'],
            ['value' => 'Filipino', 'label' => '菲律宾语'],
            ['value' => 'Hungarian', 'label' => '匈牙利语'],
            ['value' => 'Norwegian', 'label' => '挪威语'],
            ['value' => 'Slovenian', 'label' => '斯洛文尼亚语'],
            ['value' => 'Catalan', 'label' => '加泰罗尼亚语'],
            ['value' => 'Nynorsk', 'label' => '尼诺斯克语'],
            ['value' => 'Tamil', 'label' => '泰米尔语'],
            ['value' => 'Afrikaans', 'label' => '阿非利卡语'],
        ];
    }

    private static function maxSpeechModelOptions(string $prefix = ''): array
    {
        $names = [
            'speech-2.8-hd',
            'speech-2.8-turbo',
            'speech-2.6-hd',
            'speech-2.6-turbo',
            'speech-02-hd',
            'speech-02-turbo',
            'speech-01-hd',
            'speech-01-turbo',
        ];
        $rows = [];
        foreach ($names as $name) {
            $value = $prefix . $name;
            $rows[] = ['value' => $value, 'label' => $value];
        }
        return $rows;
    }

    private static function maxSampleRateOptions(): array
    {
        $rows = [];
        foreach ([8000, 16000, 22050, 24000, 32000, 44100] as $value) {
            $rows[] = ['value' => strval($value), 'label' => strval($value) . ' Hz'];
        }
        return $rows;
    }

    private static function maxBitrateOptions(): array
    {
        $rows = [];
        foreach ([32000, 64000, 128000, 256000] as $value) {
            $rows[] = ['value' => strval($value), 'label' => strval(intval($value / 1000)) . ' kbps'];
        }
        return $rows;
    }

    private static function maxChannelOptions(): array
    {
        return [
            ['value' => '1', 'label' => '单声道'],
            ['value' => '2', 'label' => '双声道'],
        ];
    }

    private static function maxAudioFormatOptions(): array
    {
        return [
            ['value' => 'mp3', 'label' => 'mp3'],
            ['value' => 'wav', 'label' => 'wav'],
            ['value' => 'flac', 'label' => 'flac'],
            ['value' => 'pcm', 'label' => 'pcm'],
        ];
    }

    private static function maxSoundEffectOptions(): array
    {
        return [
            ['value' => '', 'label' => '无'],
            ['value' => 'spacious_echo', 'label' => '空旷回音'],
            ['value' => 'auditorium_echo', 'label' => '礼堂广播'],
            ['value' => 'lofi_telephone', 'label' => '电话失真'],
            ['value' => 'robotic', 'label' => '电音'],
        ];
    }

    private static function imageRatioOptions(): array
    {
        return [
            ['value' => '1:1', 'label' => '1:1 通用正方'],
            ['value' => '16:9', 'label' => '16:9 电脑壁纸'],
            ['value' => '9:16', 'label' => '9:16 手机壁纸'],
            ['value' => '3:2', 'label' => '3:2 摄影横幅'],
            ['value' => '2:3', 'label' => '2:3 标准竖版'],
            ['value' => '4:3', 'label' => '4:3 老式屏幕'],
        ];
    }

    private static function imageCountOptions(): array
    {
        return [
            ['value' => '1', 'label' => '1 张'],
            ['value' => '2', 'label' => '2 张'],
            ['value' => '3', 'label' => '3 张'],
            ['value' => '4', 'label' => '4 张'],
            ['value' => '5', 'label' => '5 张'],
        ];
    }

    private static function imageStyleOptions(): array
    {
        return [
            ['value' => '摄影写实', 'label' => '摄影写实'],
            ['value' => '二次元动漫', 'label' => '二次元动漫'],
            ['value' => '3D 渲染', 'label' => '3D 渲染'],
            ['value' => '油画艺术', 'label' => '油画艺术'],
            ['value' => '赛博朋克', 'label' => '赛博朋克'],
            ['value' => '水彩手绘', 'label' => '水彩手绘'],
        ];
    }

    private static function imageQualityOptions(): array
    {
        return [
            ['value' => 'high', 'label' => '高质量'],
            ['value' => 'medium', 'label' => '标准'],
            ['value' => 'low', 'label' => '流畅'],
        ];
    }

    private static function collectPublicImageUrls(array $input): array
    {
        $images = [];
        $rawImages = $input['images'] ?? ($input['reference_images'] ?? ($input['image'] ?? []));
        if (is_string($rawImages) && trim($rawImages) !== '') {
            $rawImages = [trim($rawImages)];
        }
        if (!is_array($rawImages)) {
            return [];
        }
        foreach ($rawImages as $item) {
            $url = trim(strval(is_array($item) ? ($item['url'] ?? ($item['img'] ?? '')) : $item));
            if ($url === '') {
                continue;
            }
            if (stripos($url, 'website.php') !== false
                || stripos($url, 'playTaskMedia') !== false
                || stripos($url, 'playAssetMedia') !== false
                || stripos($url, 'data:image/') === 0) {
                continue;
            }
            if (!preg_match('#^https?://#i', $url)) {
                continue;
            }
            $images[] = $url;
        }
        return array_values(array_unique($images));
    }

    private static function resolveImageModel(array $spec, array $input): string
    {
        foreach (['image_model', 'model'] as $field) {
            $value = trim(strval($input[$field] ?? ''));
            if ($value !== '' && preg_match('/gpt-image|imagea/i', $value)) {
                return $value;
            }
        }
        $specModel = trim(strval($spec['model'] ?? ''));
        if ($specModel !== '' && preg_match('/gpt-image|imagea/i', $specModel)) {
            return $specModel;
        }
        return 'gpt-image-2';
    }

    private static function normalizeImageQuality($value): string
    {
        $value = strtolower(trim(strval($value)));
        $map = [
            'high' => 'high',
            'hd' => 'high',
            'medium' => 'medium',
            'standard' => 'medium',
            'low' => 'low',
        ];
        return isset($map[$value]) ? $map[$value] : '';
    }

    private static function normalizeImageSize(string $ratio, string $size): string
    {
        $size = trim($size);
        $ratio = trim($ratio) ?: '1:1';
        if (preg_match('/^\d+x\d+$/i', $size)) {
            return strtolower($size);
        }
        $candidate = $size !== '' ? $size : $ratio;
        $candidate = strtolower($candidate);
        if (in_array($candidate, ['1:1', '3:2', '2:3', 'auto'], true)) {
            return $candidate;
        }
        return self::imageSizeFromRatio($candidate);
    }

    private static function imageSizeFromRatio(string $ratio): string
    {
        $map = [
            '1:1' => '1024x1024',
            '16:9' => '1792x1024',
            '9:16' => '1024x1792',
            '3:2' => '3:2',
            '2:3' => '2:3',
            '4:3' => '1792x1024',
            'auto' => 'auto',
            '1024x1024' => '1024x1024',
            '1792x1024' => '1792x1024',
            '1024x1792' => '1024x1792',
            '1536x1024' => '1792x1024',
            '1024x1536' => '1024x1792',
        ];
        return isset($map[$ratio]) ? $map[$ratio] : '1024x1024';
    }

    private static function sproFormatOptions(): array
    {
        return [
            ['value' => 'WAV_16000HZ_MONO_16BIT', 'label' => 'WAV 16kHz 单声道'],
            ['value' => 'WAV_24000HZ_MONO_16BIT', 'label' => 'WAV 24kHz 单声道'],
            ['value' => 'MP3_22050HZ_MONO_64KBPS', 'label' => 'MP3 22.05kHz 64kbps'],
            ['value' => 'PCM_16000HZ_MONO_16BIT', 'label' => 'PCM 16kHz 单声道'],
        ];
    }

    private static function sproBitRateOptions(): array
    {
        return [
            ['value' => '16', 'label' => '16 kbps'],
            ['value' => '32', 'label' => '32 kbps'],
            ['value' => '64', 'label' => '64 kbps'],
            ['value' => '128', 'label' => '128 kbps'],
            ['value' => '256', 'label' => '256 kbps'],
        ];
    }

    private static function emotionOptions(): array
    {
        return [
            ['value' => 'calm', 'label' => '中性'],
            ['value' => 'happy', 'label' => '高兴'],
            ['value' => 'sad', 'label' => '悲伤'],
            ['value' => 'angry', 'label' => '愤怒'],
            ['value' => 'fearful', 'label' => '害怕'],
            ['value' => 'disgusted', 'label' => '厌恶'],
            ['value' => 'surprised', 'label' => '惊讶'],
            ['value' => 'whisper', 'label' => '低语'],
        ];
    }

    private static function maxEmotionOptions(): array
    {
        $rows = self::emotionOptions();
        $rows[] = ['value' => 'fluent', 'label' => '生动'];
        return $rows;
    }

    private static function sexOptions(): array
    {
        return [
            ['value' => '1', 'label' => '男'],
            ['value' => '2', 'label' => '女'],
        ];
    }

    private static function withParamFields(array $family, array $fields, array $cloneFields = []): array
    {
        $family['param_fields'] = array_values($fields);
        $family['params'] = self::fieldKeys($fields);
        $family['required_params'] = self::fieldKeys($fields, true);
        $family['optional_params'] = self::fieldKeys($fields, false);
        if ($cloneFields !== []) {
            $family['clone_param_fields'] = array_values($cloneFields);
            $family['clone_params'] = self::fieldKeys($cloneFields);
        }
        return $family;
    }

    private static function fieldKeys(array $fields, $required = null): array
    {
        $keys = [];
        foreach ($fields as $field) {
            $isRequired = !empty($field['required']);
            if ($required === true && !$isRequired) {
                continue;
            }
            if ($required === false && $isRequired) {
                continue;
            }
            $key = trim(strval($field['key'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        return array_values($keys);
    }

    private static function sliderField(string $key, string $label, bool $required, float $min, float $max, float $step, $default, string $hint = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required ? 1 : 0,
            'type' => 'slider',
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'default' => $default,
            'hint' => $hint,
            'options' => [],
        ];
    }

    private static function selectField(string $key, string $label, bool $required, array $options, $default = '', string $hint = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required ? 1 : 0,
            'type' => 'select',
            'min' => 0,
            'max' => 0,
            'step' => 0,
            'default' => $default,
            'hint' => $hint,
            'options' => array_values($options),
        ];
    }

    private static function textareaField(string $key, string $label, bool $required, string $default = '', string $hint = '', $maxLen = 0): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required ? 1 : 0,
            'type' => 'textarea',
            'min' => 0,
            'max' => intval($maxLen),
            'step' => 0,
            'default' => $default,
            'hint' => $hint,
            'options' => [],
        ];
    }

    private static function textField(string $key, string $label, bool $required, string $default = '', string $hint = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required ? 1 : 0,
            'type' => 'text',
            'min' => 0,
            'max' => 0,
            'step' => 0,
            'default' => $default,
            'hint' => $hint,
            'options' => [],
        ];
    }

    private static function numberField(string $key, string $label, bool $required, float $min, float $max, float $step, $default, string $hint = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required ? 1 : 0,
            'type' => 'number',
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'default' => $default,
            'hint' => $hint,
            'options' => [],
        ];
    }

    private static function imageListField(string $key, string $label, bool $required, string $hint = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required ? 1 : 0,
            'type' => 'images',
            'min' => 0,
            'max' => 4,
            'step' => 0,
            'default' => '',
            'hint' => $hint,
            'options' => [],
        ];
    }

    private static function switchField(string $key, string $label, bool $required, $default = 0, string $hint = ''): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required ? 1 : 0,
            'type' => 'switch',
            'min' => 0,
            'max' => 1,
            'step' => 1,
            'default' => intval($default) ? 1 : 0,
            'hint' => $hint,
            'options' => [],
        ];
    }

    private static function buildMaxAudioSetting(array $input, $withFormat = false): array
    {
        $sampleRate = intval($input['sample_rate'] ?? 0);
        $bitrate = intval($input['bitrate'] ?? ($input['bit_rate'] ?? 0));
        if ($bitrate > 0 && $bitrate < 1000) {
            $bitrate = $bitrate * 1000;
        }
        $channel = intval($input['channel'] ?? 0);
        $format = strtolower(trim(strval($input['audio_format'] ?? '')));
        $forceCbr = self::toBoolFlag($input['force_cbr'] ?? 0);
        $allowedRates = [8000, 16000, 22050, 24000, 32000, 44100];
        $allowedBitrates = [32000, 64000, 128000, 256000];
        $setting = [];
        if (in_array($sampleRate, $allowedRates, true) && $sampleRate !== 32000) {
            $setting['sample_rate'] = $sampleRate;
        } elseif (in_array($sampleRate, $allowedRates, true)) {
            $setting['sample_rate'] = $sampleRate;
        }
        if (in_array($bitrate, $allowedBitrates, true)) {
            $setting['bitrate'] = $bitrate;
        }
        if (in_array($channel, [1, 2], true)) {
            $setting['channel'] = $channel;
        }
        if ($withFormat && in_array($format, ['mp3', 'pcm', 'flac', 'wav'], true)) {
            $setting['format'] = $format;
        }
        if ($withFormat && $forceCbr) {
            $setting['force_cbr'] = true;
        }
        if ($setting === []) {
            return [
                'sample_rate' => 32000,
                'bitrate' => 128000,
                'channel' => 1,
            ];
        }
        if (!isset($setting['sample_rate'])) {
            $setting['sample_rate'] = 32000;
        }
        if (!isset($setting['bitrate'])) {
            $setting['bitrate'] = 128000;
        }
        if (!isset($setting['channel'])) {
            $setting['channel'] = 1;
        }
        return $setting;
    }

    private static function buildVoiceModify(array $input, string $effectKey): array
    {
        $pitch = self::clampInt($input['modify_pitch'] ?? 0, -100, 100, 0);
        $intensity = self::clampInt($input['modify_intensity'] ?? 0, -100, 100, 0);
        $timbre = self::clampInt($input['modify_timbre'] ?? 0, -100, 100, 0);
        $effect = trim(strval($input['sound_effects'] ?? ($input['sound_effect'] ?? '')));
        $allowed = ['spacious_echo', 'auditorium_echo', 'lofi_telephone', 'robotic'];
        $modify = [];
        if ($pitch !== 0) {
            $modify['pitch'] = $pitch;
        }
        if ($intensity !== 0) {
            $modify['intensity'] = $intensity;
        }
        if ($timbre !== 0) {
            $modify['timbre'] = $timbre;
        }
        if (in_array($effect, $allowed, true)) {
            $modify[$effectKey] = $effect;
        }
        return $modify;
    }

    private static function parseToneList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (is_array($value)) {
            if (isset($value['tone']) && is_array($value['tone'])) {
                $value = $value['tone'];
            }
            $rows = [];
            foreach ($value as $item) {
                $line = trim(strval(is_array($item) ? ($item['tone'] ?? ($item['text'] ?? '')) : $item));
                if ($line !== '') {
                    $rows[] = $line;
                }
            }
            return array_values(array_unique($rows));
        }
        $text = trim(strval($value));
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/\r\n|\n|\r/', $text);
        $rows = [];
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $line = trim(strval($part));
                if ($line !== '') {
                    $rows[] = $line;
                }
            }
        }
        return array_values(array_unique($rows));
    }

    private static function parseTimbreWeights($value, $withModelType = false): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        $items = [];
        if (is_array($value)) {
            $items = $value;
        } else {
            $text = trim(strval($value));
            if ($text === '') {
                return [];
            }
            $lines = preg_split('/\r\n|\n|\r/', $text);
            $items = is_array($lines) ? $lines : [];
        }
        $rows = [];
        foreach ($items as $item) {
            $voiceId = '';
            $weight = 0;
            $modelType = 2;
            if (is_array($item)) {
                $voiceId = trim(strval($item['voice_id'] ?? ($item['id'] ?? '')));
                $weight = intval($item['weight'] ?? 0);
                $modelType = intval($item['voice_model_type'] ?? ($item['model_type'] ?? 2));
            } else {
                $line = trim(strval($item));
                if ($line === '') {
                    continue;
                }
                $parts = preg_split('/[,:|\s]+/', $line);
                $voiceId = trim(strval($parts[0] ?? ''));
                $weight = intval($parts[1] ?? 0);
                if (isset($parts[2])) {
                    $modelType = intval($parts[2]);
                }
            }
            if ($voiceId === '' || $weight < 1) {
                continue;
            }
            $row = [
                'voice_id' => $voiceId,
                'weight' => max(1, min(100, $weight)),
            ];
            if ($withModelType) {
                $row['voice_model_type'] = $modelType === 1 ? 1 : 2;
            }
            $rows[] = $row;
            if (count($rows) >= 4) {
                break;
            }
        }
        return $rows;
    }

    private static function clampInt($value, int $min, int $max, int $fallback): int
    {
        if ($value === '' || $value === null) {
            return $fallback;
        }
        if (!is_numeric($value)) {
            return $fallback;
        }
        $number = intval($value);
        return max($min, min($max, $number));
    }

    private static function toBoolFlag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return intval($value) === 1;
        }
        $text = strtolower(trim(strval($value)));
        return in_array($text, ['1', 'true', 'yes', 'on'], true);
    }

    private static function clampRatio(float $value, float $min, float $max, float $fallback): float
    {
        if ($value <= 0) {
            return $fallback;
        }
        return max($min, min($max, $value));
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
