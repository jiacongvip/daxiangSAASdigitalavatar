<?php

namespace app\plugin\zhuibaoagent\common;

use think\facade\Db;

class ZhuibaoProviderService
{
    public const PROVIDER_MODE_CHANNEL = 0;
    public const PROVIDER_MODE_SITE = 1;
    public const PROVIDER_MODE_DISABLED = 2;

    private const DEFAULT_VENDOR_BASE_URL = 'https://vapi.niwaai.com';
    private const DEFAULT_VENDOR_APP_ID = '0464349396';
    private const DEFAULT_VENDOR_APP_SECRET = 'ihptdkt1xhayn80h09dqb138bxsd5w8r';
    private const DEFAULT_VENDOR_VOICE_ID = '1637';
    private const DEFAULT_VENDOR_AVATAR_MODEL_ID = '25010';
    private const DEFAULT_TTS_TEST_AUDIO_ID = '30022';
    private const DEFAULT_AVATAR_TEST_ID = '24374';
    private const DEFAULT_EDITOR_BASE_URL = 'https://ice.cn-shanghai.aliyuncs.com';
    private const DEFAULT_EDITOR_REGION_ID = 'cn-shanghai';
    private const DEFAULT_EDITOR_ENDPOINT = 'ice.cn-shanghai.aliyuncs.com';
    private const CHANNEL_MANAGED_PROVIDER_KEYS = ['avatar'];

    public static function getProviderConfigs(int $aid): array
    {
        $siteProviders = self::getSiteProviderConfigs($aid);
        $siteMap = [];
        foreach ($siteProviders as $provider) {
            $siteMap[strval($provider['provider_key'] ?? '')] = $provider;
        }

        $legacyOutlineTemplate = '';
        if (!empty($siteMap['llm'])) {
            $legacyLlmExtra = self::decodeJsonField($siteMap['llm']['extra_json'] ?? []);
            $legacyOutlineTemplate = str_replace(
                "\r",
                '',
                strval($legacyLlmExtra['outline_prompt_template'] ?? '')
            );
        }

        $result = [];
        foreach (self::defaultProviders() as $default) {
            $providerKey = strval($default['provider_key'] ?? '');
            $provider = $siteMap[$providerKey] ?? self::decorateProviderConfig($default);
            $resolved = self::resolveEffectiveProviderForAid($aid, $provider);
            if ($providerKey === 'image' && trim(strval($resolved['outline_prompt_template'] ?? '')) === '' && $legacyOutlineTemplate !== '') {
                $resolved['outline_prompt_template'] = $legacyOutlineTemplate;
                $resolved['outline_prompt_template_default'] = ZhuibaoGraphicService::getDefaultOutlinePromptTemplate();
                $resolvedExtra = self::decodeJsonField($resolved['extra_json'] ?? []);
                if (trim(strval($resolvedExtra['outline_prompt_template'] ?? '')) === '') {
                    $resolvedExtra['outline_prompt_template'] = $legacyOutlineTemplate;
                    $resolved['extra_json'] = $resolvedExtra;
                }
            }
            $result[] = $resolved;
        }

        return array_values($result);
    }

    public static function getUsageStats(int $aid): array
    {
        return ZhuibaoUsageStatService::getStepUsageSummary(
            $aid,
            0,
            ZhuibaoTaskService::getStepDefinitions()
        );
    }

    public static function saveProviderConfig(int $aid, array $info): array
    {
        return self::saveAidProviderConfig($aid, $info);
    }

    public static function saveAidProviderConfig(int $aid, array $info): array
    {
        $providerKey = trim(strval($info['provider_key'] ?? ''));
        if ($providerKey === '') {
            throw new \RuntimeException('provider_key 不能为空');
        }

        $allowedKeys = array_column(self::defaultProviders(), 'provider_key');
        if (!in_array($providerKey, $allowedKeys, true)) {
            throw new \RuntimeException('不支持的 provider_key：' . $providerKey);
        }

        $normalized = self::normalizeProviderInfo($info);
        $data = [
            'aid' => $aid,
            'provider_key' => $providerKey,
            'provider_name' => trim(strval($normalized['provider_name'] ?? '')),
            'api_key' => trim(strval($normalized['api_key'] ?? '')),
            'base_url' => trim(strval($normalized['base_url'] ?? '')),
            'model_name' => trim(strval($normalized['model_name'] ?? '')),
            'extra_json' => json_encode(self::decodeJsonField($normalized['extra_json'] ?? []), JSON_UNESCAPED_UNICODE),
            'status' => intval($normalized['status'] ?? 1) === 1 ? 1 : 0,
            'update_time' => time(),
        ];

        $existing = Db::name('zhuibao_provider_config')
            ->where('aid', $aid)
            ->where('provider_key', $providerKey)
            ->find();

        if ($existing) {
            Db::name('zhuibao_provider_config')->where('id', intval($existing['id']))->update($data);
        } else {
            $data['create_time'] = time();
            Db::name('zhuibao_provider_config')->insert($data);
        }

        return self::findProviderByKey($aid, $providerKey);
    }

    public static function saveAidProviderConfigByTenant(int $aid, array $info): array
    {
        $providerKey = trim(strval($info['provider_key'] ?? ''));
        self::assertAidProviderEditableByTenant($aid, $providerKey);
        return self::saveAidProviderConfig($aid, $info);
    }

    public static function ensureAidProviderEditableByTenant(int $aid, string $providerKey): void
    {
        self::assertAidProviderEditableByTenant($aid, $providerKey);
    }

    public static function saveChannelProviderConfig(int $rootUid, array $info): array
    {
        $providerKey = trim(strval($info['provider_key'] ?? ''));
        if (!self::isChannelManagedProviderKey($providerKey)) {
            throw new \RuntimeException('当前只支持代理维护数字人接口配置');
        }

        $allowedKeys = array_column(self::defaultProviders(), 'provider_key');
        if (!in_array($providerKey, $allowedKeys, true)) {
            throw new \RuntimeException('不支持的 provider_key：' . $providerKey);
        }

        $normalized = self::normalizeProviderInfo($info);
        $data = [
            'root_uid' => max(0, intval($rootUid)),
            'provider_key' => $providerKey,
            'provider_name' => trim(strval($normalized['provider_name'] ?? '')),
            'api_key' => trim(strval($normalized['api_key'] ?? '')),
            'base_url' => trim(strval($normalized['base_url'] ?? '')),
            'model_name' => trim(strval($normalized['model_name'] ?? '')),
            'extra_json' => json_encode(self::decodeJsonField($normalized['extra_json'] ?? []), JSON_UNESCAPED_UNICODE),
            'status' => intval($normalized['status'] ?? 1) === 1 ? 1 : 0,
            'update_time' => time(),
        ];

        $existing = Db::name('zhuibao_channel_provider_config')
            ->where('root_uid', max(0, intval($rootUid)))
            ->where('provider_key', $providerKey)
            ->find();

        if ($existing) {
            Db::name('zhuibao_channel_provider_config')->where('id', intval($existing['id']))->update($data);
        } else {
            $data['create_time'] = time();
            Db::name('zhuibao_channel_provider_config')->insert($data);
        }

        return self::getChannelProviderConfigByKey($rootUid, $providerKey);
    }

    public static function testProviderConfig(array $info): array
    {
        $normalized = self::normalizeProviderInfo($info);
        $providerKey = trim(strval($normalized['provider_key'] ?? ''));
        if ($providerKey === '') {
            return ['status' => 0, 'msg' => '请先选择服务类型'];
        }

        $baseUrl = trim(strval($normalized['base_url'] ?? ''));
        $appId = trim(strval($normalized['app_id'] ?? ''));
        $appSecret = trim(strval($normalized['app_secret'] ?? ''));
        $apiKey = trim(strval($normalized['api_key'] ?? ''));

        if (in_array($providerKey, ['avatar', 'tts'], true)) {
            if ($baseUrl === '') {
                return ['status' => 0, 'msg' => '请先填写请求地址'];
            }
            if ($appId === '') {
                return ['status' => 0, 'msg' => '请先填写 AppID'];
            }
            if ($appSecret === '') {
                return ['status' => 0, 'msg' => '请先填写密钥'];
            }

            if ($providerKey === 'tts') {
                $queryResult = ZhuibaoAvatarVoiceApi::queryVoiceMergeStatus(
                    $normalized,
                    self::DEFAULT_TTS_TEST_AUDIO_ID
                );
            } else {
                $queryResult = ZhuibaoAvatarVoiceApi::queryAvatarVideoStatus(
                    $normalized,
                    self::DEFAULT_AVATAR_TEST_ID
                );
            }

            if (intval($queryResult['status'] ?? 0) !== 1) {
                return [
                    'status' => 0,
                    'msg' => '连通性测试失败：' . strval($queryResult['msg'] ?? '未知错误'),
                    'data' => [
                        'http_code' => intval($queryResult['http_code'] ?? 0),
                        'provider_key' => $providerKey,
                    ],
                ];
            }

            return [
                'status' => 1,
                'msg' => $providerKey === 'tts' ? '配音接口连通成功，已通过真实任务状态查询' : '数字人接口连通成功，已通过真实任务状态查询',
                'data' => [
                    'provider_key' => $providerKey,
                    'base_url' => $baseUrl,
                    'sample_result' => $queryResult['data'] ?? [],
                ],
            ];
        }

        if ($providerKey === 'llm') {
            if ($baseUrl === '') {
                return ['status' => 0, 'msg' => '请先填写请求地址'];
            }
            if ($apiKey === '') {
                return ['status' => 0, 'msg' => '请先填写 API Key'];
            }
            if (trim(strval($normalized['model_name'] ?? '')) === '') {
                return ['status' => 0, 'msg' => '请先填写模型名称'];
            }

            $testResult = ZhuibaoOpenAiApi::testConnection($normalized);
            if (intval($testResult['status'] ?? 0) !== 1) {
                return [
                    'status' => 0,
                    'msg' => '连通性测试失败：' . strval($testResult['msg'] ?? '未知错误'),
                    'data' => [
                        'http_code' => intval($testResult['http_code'] ?? 0),
                        'provider_key' => $providerKey,
                    ],
                ];
            }

            return [
                'status' => 1,
                'msg' => '大模型接口连通成功，已通过 OpenAI 兼容对话测试',
                'data' => [
                    'provider_key' => $providerKey,
                    'base_url' => $baseUrl,
                    'model_name' => trim(strval($normalized['model_name'] ?? '')),
                    'sample_result' => trim(strval($testResult['content'] ?? '')),
                ],
            ];
        }

        if ($providerKey === 'image') {
            if ($baseUrl === '') {
                return ['status' => 0, 'msg' => '请先填写请求地址'];
            }
            if ($apiKey === '') {
                return ['status' => 0, 'msg' => '请先填写 API Key'];
            }
            if (trim(strval($normalized['model_name'] ?? '')) === '') {
                return ['status' => 0, 'msg' => '请先填写模型名称'];
            }

            return [
                'status' => 1,
                'msg' => '图片生成配置格式校验通过。建议到图文做图工作台做一次真实出图验证。',
                'data' => [
                    'provider_key' => $providerKey,
                    'base_url' => $baseUrl,
                    'model_name' => trim(strval($normalized['model_name'] ?? '')),
                    'image_size' => trim(strval($normalized['extra_json']['image_size'] ?? '1024x1536')) ?: '1024x1536',
                ],
            ];
        }

        if ($providerKey === 'editor') {
            if ($baseUrl === '') {
                return ['status' => 0, 'msg' => '请先填写请求地址'];
            }
            if ($appId === '') {
                return ['status' => 0, 'msg' => '请先填写 AccessKey ID'];
            }
            if ($appSecret === '') {
                return ['status' => 0, 'msg' => '请先填写 AccessKey Secret'];
            }
            if (trim(strval($normalized['editor_region_id'] ?? '')) === '') {
                return ['status' => 0, 'msg' => '请先填写 RegionId'];
            }

            return [
                'status' => 1,
                'msg' => '智能剪辑配置校验通过。拿到阿里云 IMS 模板和输出存储后，即可接入第 5 步真实剪辑。',
                'data' => [
                    'provider_key' => $providerKey,
                    'base_url' => $baseUrl,
                    'region_id' => trim(strval($normalized['editor_region_id'] ?? '')),
                    'template_id' => trim(strval($normalized['editor_template_id'] ?? '')),
                    'project_id' => trim(strval($normalized['editor_project_id'] ?? '')),
                    'pipeline_id' => trim(strval($normalized['editor_pipeline_id'] ?? '')),
                ],
            ];
        }

        if ($providerKey === 'runway') {
            if (!ZhuibaoAgentService::isRunningHubFlowInstalled()) {
                return ['status' => 0, 'msg' => '请先安装 RunningHub(ComfyUI云) 工作流市场插件'];
            }

            return [
                'status' => 1,
                'msg' => 'RunningHub 工作流插件已安装。具体 apiKey、域名和工作流参数请在 RunningHub 插件里维护。',
                'data' => [
                    'provider_key' => $providerKey,
                    'source_plugin' => 'runninghubflow',
                ],
            ];
        }

        if ($apiKey === '') {
            return ['status' => 0, 'msg' => '请先填写 API Key'];
        }

        return [
            'status' => 1,
            'msg' => '配置格式校验通过。第一版先做参数校验，真实连通性检测可在下一步接入。',
        ];
    }

    public static function cloneVoiceProfile(array $info): array
    {
        $provider = self::normalizeProviderInfo($info);
        $provider = self::applyExplicitVoiceCloneOverrides($provider, $info);
        self::assertVendorProvider($provider, 'tts', '配音/TTS');

        $sampleUrl = trim(strval($provider['voice_sample_url'] ?? ''));
        if ($sampleUrl === '') {
            throw new \RuntimeException('请先上传或填写声音样本地址');
        }
        self::assertPublicMediaUrl($sampleUrl, '声音样本地址');

        $spec = ZhuibaoNiwaModelCatalog::resolve(
            trim(strval($provider['model_code'] ?? ($info['model_code'] ?? ''))),
            $provider,
            $info
        );
        if (ZhuibaoNiwaModelCatalog::cloneApi($spec) === 'none' || empty($spec['supports_clone'])) {
            throw new \RuntimeException('当前声音模型不支持克隆');
        }

        $payload = ZhuibaoNiwaModelCatalog::buildClonePayload($spec, [
            'sample_url' => $sampleUrl,
            'name' => trim(strval($provider['voice_clone_prefix'] ?? '')) ?: ('我的声音-' . date('His')),
            'sex' => intval($provider['voice_clone_sex'] ?? 1),
            'language' => trim(strval($provider['voice_language'] ?? 'zh')) ?: 'zh',
            'instruction' => trim(strval($info['instruction'] ?? ($provider['default_instruction'] ?? ''))),
            'description' => trim(strval($info['description'] ?? '')),
            'clone_text' => trim(strval($info['clone_text'] ?? ($info['text'] ?? ''))),
            'minimax_model' => trim(strval($info['minimax_model'] ?? '')),
            'voice_service_code' => trim(strval($provider['voice_service_code'] ?? '')),
            'target_model' => trim(strval($provider['target_model'] ?? '')),
        ]);

        $result = ZhuibaoAvatarVoiceApi::cloneVoice($provider, $payload, ZhuibaoNiwaModelCatalog::cloneApi($spec));
        if (intval($result['status'] ?? 0) !== 1) {
            throw new \RuntimeException(strval($result['msg'] ?? '未知错误'));
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $voiceId = $data['voice_id'] ?? '';
        if ($voiceId === '' || $voiceId === null) {
            throw new \RuntimeException('接口未返回声音ID');
        }

        return [
            'voice_id' => $voiceId,
            'voice_name' => strval($data['voice_name'] ?? ($payload['prefix'] ?? ($payload['voice_name'] ?? ''))),
            'clone_status' => intval($data['clone_status'] ?? 0),
            'voice_service_name' => strval($data['voice_service_name'] ?? ''),
            'create_time' => strval($data['create_time'] ?? ''),
        ];
    }

    public static function cloneAvatarProfile(array $info): array
    {
        $provider = self::normalizeProviderInfo($info);
        $provider = self::applyExplicitAvatarCloneOverrides($provider, $info);
        self::assertVendorProvider($provider, 'avatar', '数字人');

        $sampleUrl = trim(strval($provider['avatar_sample_video_url'] ?? ''));
        if ($sampleUrl === '') {
            throw new \RuntimeException('请先上传或填写数字人样本视频地址');
        }
        self::assertPublicMediaUrl($sampleUrl, '数字人样本视频地址');

        $avatarName = trim(strval($provider['avatar_name'] ?? '')) ?: ('我的数字人-' . date('His'));
        $sex = intval($provider['avatar_sex'] ?? 1);

        $result = ZhuibaoAvatarVoiceApi::cloneAvatar($provider, [
            'sex' => $sex,
            'name' => $avatarName,
            'platform_code' => trim(strval($provider['platform_code'] ?? 'VPro')),
            'video_oss_url' => $sampleUrl,
        ]);
        if (intval($result['status'] ?? 0) !== 1) {
            throw new \RuntimeException(strval($result['msg'] ?? '未知错误'));
        }

        $modelId = $result['data'] ?? '';
        if ($modelId === '' || $modelId === null) {
            throw new \RuntimeException('接口未返回数字人ID');
        }

        return [
            'model_id' => $modelId,
            'avatar_name' => $avatarName,
            'platform_code' => trim(strval($provider['platform_code'] ?? 'VPro')),
            'sex' => $sex,
        ];
    }

    public static function getMemberAvatarProfiles(int $aid, int $uid, int $bid = 0, bool $includePlatform = true): array
    {
        $memberProfiles = self::getMemberAvatarProfilesOnly($aid, $uid, $bid);
        if (!$includePlatform) {
            return $memberProfiles;
        }

        $provider = self::getProviderConfigByKey($aid, 'avatar');
        $platformProfiles = self::buildPlatformAvatarProfilesForMember($provider, !empty($memberProfiles));

        return array_values(array_merge($memberProfiles, $platformProfiles));
    }

    public static function getMemberVoiceProfiles(int $aid, int $uid, int $bid = 0, bool $includePlatform = true): array
    {
        $memberProfiles = self::getMemberVoiceProfilesOnly($aid, $uid, $bid);
        if (!$includePlatform) {
            return $memberProfiles;
        }

        $provider = self::getProviderConfigByKey($aid, 'tts');
        $platformProfiles = self::buildPlatformVoiceProfilesForMember($provider, !empty($memberProfiles));

        return array_values(array_merge($memberProfiles, $platformProfiles));
    }

    public static function decorateAvatarProviderForMember(int $aid, int $uid, array $provider, int $bid = 0): array
    {
        if (trim(strval($provider['provider_key'] ?? '')) !== 'avatar') {
            return $provider;
        }

        $memberProfiles = self::getMemberAvatarProfilesOnly($aid, $uid, $bid);
        $platformProfiles = self::buildPlatformAvatarProfilesForMember($provider, !empty($memberProfiles));
        $mergedProfiles = array_values(array_merge($memberProfiles, $platformProfiles));

        $provider['avatar_profiles_member'] = $memberProfiles;
        $provider['avatar_profiles_platform'] = $platformProfiles;
        $provider['avatar_profiles'] = $mergedProfiles;
        $provider['avatar_profile_source_split'] = 1;

        $defaultAvatarProfile = self::pickDefaultProfile($mergedProfiles, 'model_id', '');
        if (!empty($defaultAvatarProfile)) {
            $provider['default_model_id'] = trim(strval($defaultAvatarProfile['model_id'] ?? ($provider['default_model_id'] ?? '')));
            $provider['avatar_sample_video_url'] = trim(strval($defaultAvatarProfile['sample_url'] ?? ($provider['avatar_sample_video_url'] ?? '')));
            $provider['avatar_name'] = trim(strval($defaultAvatarProfile['name'] ?? ($provider['avatar_name'] ?? '')));
            $provider['avatar_sex'] = intval($defaultAvatarProfile['sex'] ?? ($provider['avatar_sex'] ?? 1));
        }

        return $provider;
    }

    public static function decorateTtsProviderForMember(int $aid, int $uid, array $provider, int $bid = 0): array
    {
        if (trim(strval($provider['provider_key'] ?? '')) !== 'tts') {
            return $provider;
        }

        $memberProfiles = self::getMemberVoiceProfilesOnly($aid, $uid, $bid);
        $platformProfiles = self::buildPlatformVoiceProfilesForMember($provider, !empty($memberProfiles));
        $mergedProfiles = array_values(array_merge($memberProfiles, $platformProfiles));

        $provider['voice_profiles_member'] = $memberProfiles;
        $provider['voice_profiles_platform'] = $platformProfiles;
        $provider['voice_profiles'] = $mergedProfiles;
        $provider['voice_profile_source_split'] = 1;

        $defaultVoiceProfile = self::pickDefaultProfile($mergedProfiles, 'voice_id', '');
        if (!empty($defaultVoiceProfile)) {
            $provider['default_voice_id'] = trim(strval($defaultVoiceProfile['voice_id'] ?? ($provider['default_voice_id'] ?? '')));
            $provider['default_instruction'] = trim(strval($defaultVoiceProfile['instruction'] ?? ($provider['default_instruction'] ?? '')));
            $provider['voice_sample_url'] = trim(strval($defaultVoiceProfile['sample_url'] ?? ($provider['voice_sample_url'] ?? '')));
            $provider['voice_clone_prefix'] = trim(strval($defaultVoiceProfile['name'] ?? ($provider['voice_clone_prefix'] ?? '')));
            $provider['voice_clone_sex'] = intval($defaultVoiceProfile['sex'] ?? ($provider['voice_clone_sex'] ?? 0));
            $provider['voice_language'] = trim(strval($defaultVoiceProfile['language'] ?? ($provider['voice_language'] ?? 'zh'))) ?: 'zh';
        }

        return $provider;
    }

    public static function saveMemberAvatarProfile(int $aid, int $uid, int $bid, array $info): array
    {
        if ($uid <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }

        $id = intval($info['id'] ?? 0);
        $name = trim(strval($info['name'] ?? ''));
        $sampleUrl = trim(strval($info['sample_url'] ?? ''));
        $platformCode = trim(strval($info['platform_code'] ?? 'VPro')) ?: 'VPro';
        $sex = intval($info['sex'] ?? 1);
        $isDefault = intval($info['is_default'] ?? 0) === 1 ? 1 : 0;
        $remark = trim(strval($info['remark'] ?? ''));
        $taskGroupId = intval($info['task_group_id'] ?? 0);

        if ($name === '') {
            throw new \RuntimeException('请填写数字人名称');
        }
        if ($sampleUrl === '') {
            throw new \RuntimeException('请先选择数字人样本视频');
        }
        self::assertPublicMediaUrl($sampleUrl, '数字人样本视频地址');

        $now = time();
        $existing = [];
        if ($id > 0) {
            $existing = Db::name('zhuibao_member_avatar_profile')
                ->where('aid', $aid)
                ->where('uid', $uid)
                ->where('id', $id)
                ->where('delete_time', 0)
                ->find();
            if (!$existing) {
                throw new \RuntimeException('数字人不存在或无权编辑');
            }
        }

        $modelId = trim(strval($existing['model_id'] ?? ''));
        $originalSampleUrl = trim(strval($existing['sample_url'] ?? ''));
        $originalSex = intval($existing['sex'] ?? 1);
        $originalPlatformCode = trim(strval($existing['platform_code'] ?? 'VPro'));
        $sampleChanged = $existing && (
            $originalSampleUrl !== $sampleUrl ||
            $originalSex !== $sex ||
            $originalPlatformCode !== $platformCode
        );
        if ($sampleChanged) {
            $modelId = '';
        }

        $data = [
            'aid' => $aid,
            'bid' => max(0, $bid),
            'uid' => $uid,
            'name' => $name,
            'sex' => $sex,
            'platform_code' => $platformCode,
            'sample_url' => $sampleUrl,
            'model_id' => $modelId,
            'task_group_id' => $taskGroupId > 0 ? $taskGroupId : intval($existing['task_group_id'] ?? 0),
            'is_default' => $isDefault,
            'status' => 1,
            'source_type' => 'member',
            'remark' => $remark,
            'update_time' => $now,
        ];

        if (!empty($existing)) {
            Db::name('zhuibao_member_avatar_profile')
                ->where('id', intval($existing['id']))
                ->update($data);
            $recordId = intval($existing['id']);
        } else {
            $data['create_time'] = $now;
            $recordId = intval(Db::name('zhuibao_member_avatar_profile')->insertGetId($data));
        }

        if ($isDefault === 1) {
            self::syncMemberAvatarDefaultState($aid, $uid, $recordId);
        } else {
            self::ensureMemberAvatarDefaultExists($aid, $uid, $recordId);
        }

        return self::getMemberAvatarProfileById($aid, $uid, $recordId);
    }

    public static function saveMemberVoiceProfile(int $aid, int $uid, int $bid, array $info): array
    {
        if ($uid <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }

        $id = intval($info['id'] ?? 0);
        $name = trim(strval($info['name'] ?? ''));
        $sampleUrl = trim(strval($info['sample_url'] ?? ''));
        $sex = intval($info['sex'] ?? 0);
        $language = trim(strval($info['language'] ?? 'zh')) ?: 'zh';
        $instruction = trim(strval($info['instruction'] ?? '请用自然平稳的语气说话。')) ?: '请用自然平稳的语气说话。';
        $isDefault = intval($info['is_default'] ?? 0) === 1 ? 1 : 0;
        $remark = trim(strval($info['remark'] ?? ''));

        if ($name === '') {
            throw new \RuntimeException('请填写声音名称');
        }
        if ($sampleUrl === '') {
            throw new \RuntimeException('请先上传声音样本');
        }
        self::assertPublicMediaUrl($sampleUrl, '声音样本地址');

        $now = time();
        $existing = [];
        if ($id > 0) {
            $existing = Db::name('zhuibao_member_voice_profile')
                ->where('aid', $aid)
                ->where('uid', $uid)
                ->where('id', $id)
                ->where('delete_time', 0)
                ->find();
            if (!$existing) {
                throw new \RuntimeException('声音不存在或无权编辑');
            }
        }

        $voiceId = trim(strval($existing['voice_id'] ?? ''));
        $sampleChanged = $existing && trim(strval($existing['sample_url'] ?? '')) !== $sampleUrl;
        if ($sampleChanged) {
            $voiceId = '';
        }

        $data = [
            'aid' => $aid,
            'bid' => max(0, $bid),
            'uid' => $uid,
            'name' => $name,
            'sample_url' => $sampleUrl,
            'voice_id' => $voiceId,
            'sex' => $sex,
            'language' => $language,
            'instruction' => $instruction,
            'is_default' => $isDefault,
            'status' => 1,
            'source_type' => 'member',
            'remark' => $remark,
            'update_time' => $now,
        ];

        if (!empty($existing)) {
            Db::name('zhuibao_member_voice_profile')
                ->where('id', intval($existing['id']))
                ->update($data);
            $recordId = intval($existing['id']);
        } else {
            $data['create_time'] = $now;
            $recordId = intval(Db::name('zhuibao_member_voice_profile')->insertGetId($data));
        }

        if ($isDefault === 1) {
            self::syncMemberVoiceDefaultState($aid, $uid, $recordId);
        } else {
            self::ensureMemberVoiceDefaultExists($aid, $uid, $recordId);
        }

        return self::getMemberVoiceProfileById($aid, $uid, $recordId);
    }

    public static function deleteMemberAvatarProfile(int $aid, int $uid, int $id): void
    {
        if ($uid <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        if ($id <= 0) {
            throw new \RuntimeException('请传入数字人ID');
        }

        $row = Db::name('zhuibao_member_avatar_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('id', $id)
            ->where('delete_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('数字人不存在或无权删除');
        }

        Db::name('zhuibao_member_avatar_profile')
            ->where('id', $id)
            ->update([
                'status' => 0,
                'delete_time' => time(),
                'update_time' => time(),
            ]);

        self::ensureMemberAvatarDefaultExists($aid, $uid, 0);
    }

    public static function deleteMemberVoiceProfile(int $aid, int $uid, int $id): void
    {
        if ($uid <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        if ($id <= 0) {
            throw new \RuntimeException('请传入声音ID');
        }

        $row = Db::name('zhuibao_member_voice_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('id', $id)
            ->where('delete_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('声音不存在或无权删除');
        }

        Db::name('zhuibao_member_voice_profile')
            ->where('id', $id)
            ->update([
                'status' => 0,
                'delete_time' => time(),
                'update_time' => time(),
            ]);

        self::ensureMemberVoiceDefaultExists($aid, $uid, 0);
    }

    public static function cloneMemberAvatarProfile(int $aid, int $uid, int $bid, int $id): array
    {
        $row = Db::name('zhuibao_member_avatar_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('id', $id)
            ->where('delete_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('数字人不存在或无权操作');
        }

        $provider = self::getProviderConfigByKey($aid, 'avatar');
        $cloneResult = self::cloneAvatarProfile(array_merge($provider, [
            'avatar_name' => trim(strval($row['name'] ?? '')),
            'avatar_sex' => intval($row['sex'] ?? 1),
            'platform_code' => trim(strval($row['platform_code'] ?? 'VPro')) ?: 'VPro',
            'avatar_sample_video_url' => trim(strval($row['sample_url'] ?? '')),
        ]));

        Db::name('zhuibao_member_avatar_profile')
            ->where('id', $id)
            ->update([
                'bid' => max(0, $bid),
                'model_id' => trim(strval($cloneResult['model_id'] ?? '')),
                'platform_code' => trim(strval($cloneResult['platform_code'] ?? ($row['platform_code'] ?? 'VPro'))) ?: 'VPro',
                'sex' => intval($cloneResult['sex'] ?? ($row['sex'] ?? 1)),
                'update_time' => time(),
            ]);

        return self::getMemberAvatarProfileById($aid, $uid, $id);
    }

    public static function cloneMemberVoiceProfile(int $aid, int $uid, int $bid, int $id): array
    {
        $row = Db::name('zhuibao_member_voice_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('id', $id)
            ->where('delete_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('声音不存在或无权操作');
        }

        $provider = self::getProviderConfigByKey($aid, 'tts');
        $cloneResult = self::cloneVoiceProfile(array_merge($provider, [
            'voice_sample_url' => trim(strval($row['sample_url'] ?? '')),
            'voice_clone_prefix' => trim(strval($row['name'] ?? '')),
            'voice_clone_sex' => intval($row['sex'] ?? 0),
            'voice_language' => trim(strval($row['language'] ?? 'zh')) ?: 'zh',
            'default_instruction' => trim(strval($row['instruction'] ?? '请用自然平稳的语气说话。')) ?: '请用自然平稳的语气说话。',
        ]));

        Db::name('zhuibao_member_voice_profile')
            ->where('id', $id)
            ->update([
                'bid' => max(0, $bid),
                'voice_id' => trim(strval($cloneResult['voice_id'] ?? '')),
                'name' => trim(strval($cloneResult['voice_name'] ?? ($row['name'] ?? ''))) ?: trim(strval($row['name'] ?? '')),
                'update_time' => time(),
            ]);

        return self::getMemberVoiceProfileById($aid, $uid, $id);
    }

    public static function getPublishAccounts(int $aid, int $page, int $limit, string $platform = '', string $keyword = ''): array
    {
        $where = [['aid', '=', $aid]];
        if ($platform !== '') {
            $where[] = ['platform', '=', $platform];
        }
        if ($keyword !== '') {
            $where[] = ['account_name|open_id', 'like', '%' . $keyword . '%'];
        }

        $count = Db::name('zhuibao_publish_account')->where($where)->count();
        $list = Db::name('zhuibao_publish_account')
            ->where($where)
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $row['extra_json'] = self::decodeJsonField($row['extra_json'] ?? '');
            $row['status_text'] = intval($row['status'] ?? 0) === 1 ? '启用' : '停用';
        }
        unset($row);

        return ['list' => $list, 'total' => intval($count)];
    }

    public static function savePublishAccount(int $aid, int $bid, array $info): array
    {
        $platform = trim(strval($info['platform'] ?? ''));
        $accountName = trim(strval($info['account_name'] ?? ''));
        if ($platform === '') {
            throw new \RuntimeException('platform 不能为空');
        }
        if ($accountName === '') {
            throw new \RuntimeException('账号名称不能为空');
        }

        $data = [
            'aid' => $aid,
            'bid' => max(0, $bid),
            'platform' => $platform,
            'account_name' => $accountName,
            'open_id' => trim(strval($info['open_id'] ?? '')),
            'access_token' => trim(strval($info['access_token'] ?? '')),
            'refresh_token' => trim(strval($info['refresh_token'] ?? '')),
            'expires_at' => intval($info['expires_at'] ?? 0),
            'extra_json' => json_encode(is_array($info['extra_json'] ?? null) ? $info['extra_json'] : [], JSON_UNESCAPED_UNICODE),
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'update_time' => time(),
        ];

        $id = intval($info['id'] ?? 0);
        if ($id > 0) {
            Db::name('zhuibao_publish_account')->where('aid', $aid)->where('id', $id)->update($data);
        } else {
            $data['create_time'] = time();
            $id = intval(Db::name('zhuibao_publish_account')->insertGetId($data));
        }

        $row = Db::name('zhuibao_publish_account')->where('aid', $aid)->where('id', $id)->find();
        $row['extra_json'] = self::decodeJsonField($row['extra_json'] ?? '');
        return is_array($row) ? $row : [];
    }

    public static function getTemplates(int $aid, int $page, int $limit, string $type = '', string $keyword = ''): array
    {
        $where = [['aid', '=', $aid]];
        if ($type !== '') {
            $where[] = ['type', '=', $type];
        }
        if ($keyword !== '') {
            $where[] = ['name', 'like', '%' . $keyword . '%'];
        }

        $count = Db::name('zhuibao_template')->where($where)->count();
        $list = Db::name('zhuibao_template')
            ->where($where)
            ->order('sort desc,id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $row = self::decorateTemplateRow($row);
        }
        unset($row);

        return ['list' => $list, 'total' => intval($count)];
    }

    public static function getEditorTemplates(int $aid, bool $onlyEnabled = true): array
    {
        $query = Db::name('zhuibao_template')
            ->where('aid', $aid)
            ->where('type', 'edit');

        if ($onlyEnabled) {
            $query->where('status', 1);
        }

        $list = $query
            ->order('sort desc,id desc')
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $row = self::decorateTemplateRow($row);
        }
        unset($row);

        return $list;
    }

    public static function resolveEditorTemplate(int $aid, int $selectedTemplateId = 0): array
    {
        if ($selectedTemplateId > 0) {
            $row = Db::name('zhuibao_template')
                ->where('aid', $aid)
                ->where('id', $selectedTemplateId)
                ->where('type', 'edit')
                ->find();

            if (is_array($row)) {
                $template = self::decorateTemplateRow($row);
                if (intval($template['status'] ?? 0) === 1) {
                    return $template;
                }
            }
        }

        $list = self::getEditorTemplates($aid, true);
        foreach ($list as $item) {
            if (intval($item['is_default'] ?? 0) === 1) {
                return $item;
            }
        }

        return isset($list[0]) && is_array($list[0]) ? $list[0] : [];
    }

    public static function saveTemplate(int $aid, array $info): array
    {
        $name = trim(strval($info['name'] ?? ''));
        $type = trim(strval($info['type'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('模板名称不能为空');
        }
        if ($type === '') {
            throw new \RuntimeException('模板类型不能为空');
        }

        $id = intval($info['id'] ?? 0);
        $existingRow = [];
        if ($id > 0) {
            $existingRow = Db::name('zhuibao_template')->where('aid', $aid)->where('id', $id)->find();
            if (!is_array($existingRow) || empty($existingRow)) {
                throw new \RuntimeException('模板不存在或已删除');
            }
        }

        $contentJson = self::buildTemplateContentJson(
            $aid,
            $type,
            $info,
            self::decodeJsonField($existingRow['content_json'] ?? [])
        );

        $data = [
            'aid' => $aid,
            'type' => $type,
            'name' => $name,
            'content_json' => json_encode($contentJson, JSON_UNESCAPED_UNICODE),
            'sort' => intval($info['sort'] ?? 0),
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'update_time' => time(),
        ];

        if ($id > 0) {
            Db::name('zhuibao_template')->where('aid', $aid)->where('id', $id)->update($data);
        } else {
            $data['create_time'] = time();
            $id = intval(Db::name('zhuibao_template')->insertGetId($data));
        }

        if ($type === 'edit' && intval($contentJson['is_default'] ?? 0) === 1) {
            self::clearEditorTemplateDefault($aid, $id);
        }

        $row = Db::name('zhuibao_template')->where('aid', $aid)->where('id', $id)->find();
        return is_array($row) ? self::decorateTemplateRow($row) : [];
    }

    public static function deleteTemplate(int $aid, int $id): bool
    {
        if ($id <= 0) {
            throw new \RuntimeException('请传入模板ID');
        }

        $deleted = Db::name('zhuibao_template')
            ->where('aid', $aid)
            ->where('id', $id)
            ->delete();

        if (!$deleted) {
            throw new \RuntimeException('模板不存在或已删除');
        }

        return true;
    }

    public static function defaultProviders(): array
    {
        return [
            [
                'provider_key' => 'llm',
                'provider_name' => '大模型',
                'api_key' => '',
                'base_url' => '',
                'model_name' => '',
                'status' => 0,
                'extra_json' => ['usage' => 'OpenAI 兼容接口，用于文案改写、标题与话题生成，可直接接 DeepSeek'],
            ],
            [
                'provider_key' => 'image',
                'provider_name' => '图文做图',
                'api_key' => '',
                'base_url' => '',
                'model_name' => '',
                'status' => 0,
                'extra_json' => [
                    'usage' => '支持 OpenAI 兼容图片接口，也兼容火山 Ark。推荐 Ark 填法：请求地址可填 https://ark.cn-beijing.volces.com 或 https://ark.cn-beijing.volces.com/api/v3，模型如 doubao-seedream-5-0-260128。',
                    'image_size' => '1024x1536',
                    'cover_reference_enabled' => 1,
                    'temporary_failure_retry_count' => 2,
                    'temporary_failure_retry_sleep_ms' => 1200,
                    'outline_prompt_template' => '',
                    'image_prompt_template' => '',
                ],
            ],
            [
                'provider_key' => 'tts',
                'provider_name' => '配音/TTS(SPro)',
                'api_key' => '',
                'base_url' => self::DEFAULT_VENDOR_BASE_URL,
                'model_name' => 'cosyvoice-v3.5-flash',
                'status' => 1,
                'extra_json' => [
                    'usage' => '音频生成',
                    'app_id' => self::DEFAULT_VENDOR_APP_ID,
                    'app_secret' => self::DEFAULT_VENDOR_APP_SECRET,
                    'voice_service_code' => 'SPro',
                    'target_model' => 'cosyvoice-v3.5-flash',
                    'default_voice_id' => self::DEFAULT_VENDOR_VOICE_ID,
                    'default_instruction' => '请用自然平稳的语气说话。',
                    'voice_sample_url' => '',
                    'voice_clone_prefix' => '默认声音',
                    'voice_clone_sex' => 0,
                    'voice_language' => 'zh',
                    'voice_profiles' => [
                        [
                            'local_id' => 'voice_default',
                            'name' => '默认声音',
                            'voice_id' => self::DEFAULT_VENDOR_VOICE_ID,
                            'sample_url' => '',
                            'sex' => 0,
                            'language' => 'zh',
                            'instruction' => '请用自然平稳的语气说话。',
                            'is_default' => 1,
                        ],
                    ],
                ],
            ],
            [
                'provider_key' => 'avatar',
                'provider_name' => '数字人',
                'api_key' => '',
                'base_url' => self::DEFAULT_VENDOR_BASE_URL,
                'model_name' => 'VPro',
                'status' => 1,
                'extra_json' => [
                    'usage' => '数字人视频生成',
                    'app_id' => self::DEFAULT_VENDOR_APP_ID,
                    'app_secret' => self::DEFAULT_VENDOR_APP_SECRET,
                    'platform_code' => 'VPro',
                    'default_model_id' => self::DEFAULT_VENDOR_AVATAR_MODEL_ID,
                    'avatar_estimated_minutes_per_video_minute' => 3,
                    'avatar_sample_video_url' => '',
                    'avatar_name' => '默认数字人',
                    'avatar_sex' => 1,
                    'avatar_profiles' => [
                        [
                            'local_id' => 'avatar_default',
                            'name' => '默认数字人',
                            'sex' => 1,
                            'platform_code' => 'VPro',
                            'sample_url' => '',
                            'model_id' => self::DEFAULT_VENDOR_AVATAR_MODEL_ID,
                            'is_default' => 1,
                        ],
                    ],
                ],
            ],
            [
                'provider_key' => 'editor',
                'provider_name' => '智能剪辑',
                'api_key' => '',
                'base_url' => self::DEFAULT_EDITOR_BASE_URL,
                'model_name' => 'aliyun_ims',
                'status' => 0,
                'extra_json' => [
                    'usage' => '阿里云 IMS 智能剪辑，用于第5步一键剪辑与模板成片',
                    'app_id' => '',
                    'app_secret' => '',
                    'editor_region_id' => self::DEFAULT_EDITOR_REGION_ID,
                    'editor_endpoint' => self::DEFAULT_EDITOR_ENDPOINT,
                    'editor_template_id' => '',
                    'editor_project_id' => '',
                    'editor_pipeline_id' => '',
                    'editor_notify_url' => '',
                    'editor_output_bucket' => '',
                    'editor_output_dir' => 'zhuibao/editor',
                    'editor_aspect_ratio' => '9:16',
                    'editor_subtitle_enabled' => 1,
                    'editor_default_bgm_url' => '',
                ],
            ],
            [
                'provider_key' => 'runway',
                'provider_name' => 'RunningHub',
                'api_key' => '',
                'base_url' => '',
                'model_name' => '',
                'status' => 0,
                'extra_json' => ['usage' => 'RunningHub(ComfyUI云) 工作流市场能力。apiKey、域名和工作流参数在 RunningHub 插件中维护，这里仅作为智能体绑定能力标识。'],
            ],
        ];
    }

    public static function getSiteProviderConfigs(int $aid): array
    {
        return self::loadProviderConfigsByScope('aid', $aid);
    }

    public static function getChannelProviderConfigs(int $rootUid): array
    {
        return self::loadProviderConfigsByScope('root_uid', max(0, intval($rootUid)));
    }

    public static function getChannelProviderConfigByKey(int $rootUid, string $providerKey): array
    {
        return self::findProviderByKeyFromRows(self::getChannelProviderConfigs($rootUid), $providerKey);
    }

    public static function getAidProviderMode(int $aid, string $providerKey): int
    {
        if (!self::isChannelManagedProviderKey($providerKey)) {
            return self::PROVIDER_MODE_SITE;
        }

        $row = Db::name('zhuibao_provider_mode')
            ->where('aid', intval($aid))
            ->where('provider_key', trim(strval($providerKey)))
            ->find();

        if (!$row) {
            return self::PROVIDER_MODE_SITE;
        }

        return self::normalizeProviderMode($row['mode'] ?? self::PROVIDER_MODE_SITE);
    }

    public static function saveAidProviderMode(int $aid, string $providerKey, int $mode): int
    {
        if (!self::isChannelManagedProviderKey($providerKey)) {
            throw new \RuntimeException('当前服务类型不支持来源切换');
        }

        $mode = self::normalizeProviderMode($mode);
        $now = time();
        $existing = Db::name('zhuibao_provider_mode')
            ->where('aid', intval($aid))
            ->where('provider_key', trim(strval($providerKey)))
            ->find();

        $data = [
            'aid' => intval($aid),
            'provider_key' => trim(strval($providerKey)),
            'mode' => $mode,
            'update_time' => $now,
        ];

        if ($existing) {
            Db::name('zhuibao_provider_mode')->where('id', intval($existing['id']))->update($data);
        } else {
            $data['create_time'] = $now;
            Db::name('zhuibao_provider_mode')->insert($data);
        }

        return $mode;
    }

    public static function getAidProviderModeSummary(int $aid, string $providerKey): array
    {
        $providerKey = trim(strval($providerKey));
        $mode = self::getAidProviderMode($aid, $providerKey);
        $rootUid = self::getOwnerRootUidByAid($aid);
        $storedChannelConfig = self::hasStoredChannelProviderConfig($rootUid, $providerKey);

        return [
            'provider_key' => $providerKey,
            'mode' => $mode,
            'mode_text' => self::providerModeText($mode),
            'root_uid' => $rootUid,
            'site_can_edit' => $mode === self::PROVIDER_MODE_SITE ? 1 : 0,
            'channel_has_custom_config' => $storedChannelConfig ? 1 : 0,
        ];
    }

    public static function getProviderConfigByKey(int $aid, string $providerKey): array
    {
        return self::findProviderByKey($aid, $providerKey);
    }

    private static function findProviderByKey(int $aid, string $providerKey): array
    {
        $rows = self::getProviderConfigs($aid);
        foreach ($rows as $row) {
            if (strval($row['provider_key'] ?? '') === $providerKey) {
                return $row;
            }
        }
        return [];
    }

    private static function findProviderByKeyFromRows(array $rows, string $providerKey): array
    {
        $providerKey = trim(strval($providerKey));
        foreach ($rows as $row) {
            if (strval($row['provider_key'] ?? '') === $providerKey) {
                return $row;
            }
        }
        return [];
    }

    private static function loadProviderConfigsByScope(string $scopeField, int $scopeValue): array
    {
        $table = $scopeField === 'root_uid' ? 'zhuibao_channel_provider_config' : 'zhuibao_provider_config';
        $rows = Db::name($table)
            ->where($scopeField, intval($scopeValue))
            ->order('id asc')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $row['extra_json'] = self::decodeJsonField($row['extra_json'] ?? '');
            $row['provider_name'] = strval($row['provider_name'] ?? '');
            $map[strval($row['provider_key'] ?? '')] = $row;
        }

        $result = [];
        foreach (self::defaultProviders() as $default) {
            $providerKey = strval($default['provider_key']);
            $row = $map[$providerKey] ?? [];
            $merged = array_merge($default, $row, [
                'provider_key' => $providerKey,
                'extra_json' => array_replace_recursive(
                    self::decodeJsonField($default['extra_json'] ?? []),
                    self::decodeJsonField($row['extra_json'] ?? [])
                ),
            ]);
            $result[] = self::decorateProviderConfig($merged);
        }

        return array_values($result);
    }

    private static function resolveEffectiveProviderForAid(int $aid, array $provider): array
    {
        $providerKey = trim(strval($provider['provider_key'] ?? ''));
        if (!self::isChannelManagedProviderKey($providerKey)) {
            $provider['provider_mode'] = self::PROVIDER_MODE_SITE;
            $provider['provider_mode_text'] = self::providerModeText(self::PROVIDER_MODE_SITE);
            $provider['provider_scope'] = 'site';
            $provider['provider_scope_text'] = '站点自有';
            $provider['site_can_edit'] = 1;
            $provider['source_root_uid'] = 0;
            $provider['channel_has_custom_config'] = 0;
            $provider['provider_lock_reason'] = '';
            return $provider;
        }

        $mode = self::getAidProviderMode($aid, $providerKey);
        $rootUid = self::getOwnerRootUidByAid($aid);
        $channelProvider = self::getChannelProviderConfigByKey($rootUid, $providerKey);
        $effectiveProvider = $provider;
        $scope = 'site';
        $lockReason = '';

        if ($mode === self::PROVIDER_MODE_CHANNEL) {
            $effectiveProvider = !empty($channelProvider) ? $channelProvider : $provider;
            $scope = 'channel';
            $lockReason = '当前由代理统一提供数字人接口，站点不可修改。';
        } elseif ($mode === self::PROVIDER_MODE_DISABLED) {
            $effectiveProvider = $provider;
            $effectiveProvider['status'] = 0;
            $scope = 'disabled';
            $lockReason = '当前站点已设置为不使用数字人接口。';
        }

        $effectiveProvider['provider_mode'] = $mode;
        $effectiveProvider['provider_mode_text'] = self::providerModeText($mode);
        $effectiveProvider['provider_scope'] = $scope;
        $effectiveProvider['provider_scope_text'] = self::providerScopeText($scope);
        $effectiveProvider['site_can_edit'] = $mode === self::PROVIDER_MODE_SITE ? 1 : 0;
        $effectiveProvider['source_root_uid'] = $rootUid;
        $effectiveProvider['channel_has_custom_config'] = self::hasStoredChannelProviderConfig($rootUid, $providerKey) ? 1 : 0;
        $effectiveProvider['provider_lock_reason'] = $lockReason;

        return $effectiveProvider;
    }

    private static function assertAidProviderEditableByTenant(int $aid, string $providerKey): void
    {
        $providerKey = trim(strval($providerKey));
        if (!self::isChannelManagedProviderKey($providerKey)) {
            return;
        }

        $mode = self::getAidProviderMode($aid, $providerKey);
        if ($mode !== self::PROVIDER_MODE_SITE) {
            throw new \RuntimeException('当前站点未启用自有数字人接口，请到控制台切换为“站点自有”后再编辑');
        }
    }

    private static function isChannelManagedProviderKey(string $providerKey): bool
    {
        return in_array(trim(strval($providerKey)), self::CHANNEL_MANAGED_PROVIDER_KEYS, true);
    }

    private static function normalizeProviderMode($mode): int
    {
        $mode = intval($mode);
        if (!in_array($mode, [self::PROVIDER_MODE_CHANNEL, self::PROVIDER_MODE_SITE, self::PROVIDER_MODE_DISABLED], true)) {
            return self::PROVIDER_MODE_SITE;
        }
        return $mode;
    }

    private static function providerModeText(int $mode): string
    {
        if ($mode === self::PROVIDER_MODE_CHANNEL) {
            return '使用代理配置';
        }
        if ($mode === self::PROVIDER_MODE_DISABLED) {
            return '不使用';
        }
        return '使用站点自己的配置';
    }

    private static function providerScopeText(string $scope): string
    {
        if ($scope === 'channel') {
            return '代理继承';
        }
        if ($scope === 'disabled') {
            return '不使用';
        }
        return '站点自有';
    }

    private static function getOwnerRootUidByAid(int $aid): int
    {
        if ($aid <= 0) {
            return 0;
        }
        return max(0, intval(Db::name('admin')->where('id', intval($aid))->value('owner_root_uid')));
    }

    private static function hasStoredChannelProviderConfig(int $rootUid, string $providerKey): bool
    {
        if (!self::isChannelManagedProviderKey($providerKey)) {
            return false;
        }

        return intval(Db::name('zhuibao_channel_provider_config')
            ->where('root_uid', max(0, intval($rootUid)))
            ->where('provider_key', trim(strval($providerKey)))
            ->count()) > 0;
    }

    private static function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function decorateProviderConfig(array $provider): array
    {
        $extra = self::decodeJsonField($provider['extra_json'] ?? []);
        $providerKey = trim(strval($provider['provider_key'] ?? ''));

        $provider['extra_json'] = $extra;
        $provider['base_url'] = self::resolveProviderBaseUrl(
            $providerKey,
            trim(strval($provider['base_url'] ?? ($extra['base_url'] ?? '')))
        );
        $provider['app_id'] = trim(strval($provider['app_id'] ?? ($extra['app_id'] ?? '')));
        $provider['app_secret'] = trim(strval($provider['app_secret'] ?? ($extra['app_secret'] ?? '')));
        $provider['voice_service_code'] = trim(strval($provider['voice_service_code'] ?? ($extra['voice_service_code'] ?? '')));
        $provider['target_model'] = trim(strval($provider['target_model'] ?? ($extra['target_model'] ?? '')));
        $provider['platform_code'] = trim(strval($provider['platform_code'] ?? ($extra['platform_code'] ?? '')));
        $provider['voice_profiles'] = self::normalizeVoiceProfiles($provider, $extra);
        $defaultVoiceProfile = self::pickDefaultProfile(
            $provider['voice_profiles'],
            'voice_id',
            trim(strval($provider['default_voice_id'] ?? ($extra['default_voice_id'] ?? '')))
        );
        $provider['default_voice_id'] = trim(strval($defaultVoiceProfile['voice_id'] ?? ($provider['default_voice_id'] ?? ($extra['default_voice_id'] ?? ''))));
        $provider['default_instruction'] = trim(strval($defaultVoiceProfile['instruction'] ?? ($provider['default_instruction'] ?? ($extra['default_instruction'] ?? ''))));
        $provider['voice_sample_url'] = trim(strval($defaultVoiceProfile['sample_url'] ?? ($provider['voice_sample_url'] ?? ($extra['voice_sample_url'] ?? ''))));
        $provider['voice_clone_prefix'] = trim(strval($defaultVoiceProfile['name'] ?? ($provider['voice_clone_prefix'] ?? ($extra['voice_clone_prefix'] ?? ''))));
        $provider['voice_clone_sex'] = intval($defaultVoiceProfile['sex'] ?? ($provider['voice_clone_sex'] ?? ($extra['voice_clone_sex'] ?? 0)));
        $provider['voice_language'] = trim(strval($defaultVoiceProfile['language'] ?? ($provider['voice_language'] ?? ($extra['voice_language'] ?? 'zh'))));
        $provider['avatar_profiles'] = self::normalizeAvatarProfiles($provider, $extra);
        $defaultAvatarProfile = self::pickDefaultProfile(
            $provider['avatar_profiles'],
            'model_id',
            trim(strval($provider['default_model_id'] ?? ($extra['default_model_id'] ?? '')))
        );
        $provider['default_model_id'] = trim(strval($defaultAvatarProfile['model_id'] ?? ($provider['default_model_id'] ?? ($extra['default_model_id'] ?? ''))));
        $provider['avatar_sample_video_url'] = trim(strval($defaultAvatarProfile['sample_url'] ?? ($provider['avatar_sample_video_url'] ?? ($extra['avatar_sample_video_url'] ?? ''))));
        $provider['avatar_name'] = trim(strval($defaultAvatarProfile['name'] ?? ($provider['avatar_name'] ?? ($extra['avatar_name'] ?? ''))));
        $provider['avatar_sex'] = intval($defaultAvatarProfile['sex'] ?? ($provider['avatar_sex'] ?? ($extra['avatar_sex'] ?? 1)));
        $provider['avatar_estimated_minutes_per_video_minute'] = self::normalizeAvatarEstimatedMinutesPerVideoMinute(
            $provider['avatar_estimated_minutes_per_video_minute'] ?? ($extra['avatar_estimated_minutes_per_video_minute'] ?? 3)
        );
        $provider['editor_region_id'] = trim(strval($provider['editor_region_id'] ?? ($extra['editor_region_id'] ?? '')));
        $provider['editor_endpoint'] = trim(strval($provider['editor_endpoint'] ?? ($extra['editor_endpoint'] ?? '')));
        $provider['editor_template_id'] = trim(strval($provider['editor_template_id'] ?? ($extra['editor_template_id'] ?? '')));
        $provider['editor_project_id'] = trim(strval($provider['editor_project_id'] ?? ($extra['editor_project_id'] ?? '')));
        $provider['editor_pipeline_id'] = trim(strval($provider['editor_pipeline_id'] ?? ($extra['editor_pipeline_id'] ?? '')));
        $provider['editor_notify_url'] = trim(strval($provider['editor_notify_url'] ?? ($extra['editor_notify_url'] ?? '')));
        $provider['editor_output_bucket'] = trim(strval($provider['editor_output_bucket'] ?? ($extra['editor_output_bucket'] ?? '')));
        $provider['editor_output_dir'] = trim(strval($provider['editor_output_dir'] ?? ($extra['editor_output_dir'] ?? '')));
        $provider['editor_aspect_ratio'] = trim(strval($provider['editor_aspect_ratio'] ?? ($extra['editor_aspect_ratio'] ?? '9:16'))) ?: '9:16';
        $provider['editor_subtitle_enabled'] = intval($provider['editor_subtitle_enabled'] ?? ($extra['editor_subtitle_enabled'] ?? 1)) === 1 ? 1 : 0;
        $provider['editor_default_bgm_url'] = trim(strval($provider['editor_default_bgm_url'] ?? ($extra['editor_default_bgm_url'] ?? '')));
        $provider['outline_prompt_template_default'] = ZhuibaoGraphicService::getDefaultOutlinePromptTemplate();
        $provider['outline_prompt_template'] = str_replace(
            "\r",
            '',
            strval($provider['outline_prompt_template'] ?? ($extra['outline_prompt_template'] ?? ''))
        );
        $provider['cover_reference_enabled'] = intval($provider['cover_reference_enabled'] ?? ($extra['cover_reference_enabled'] ?? 1)) === 1 ? 1 : 0;
        $provider['temporary_failure_retry_count'] = max(
            0,
            intval($provider['temporary_failure_retry_count'] ?? ($extra['temporary_failure_retry_count'] ?? 2))
        );
        $provider['temporary_failure_retry_sleep_ms'] = max(
            0,
            intval($provider['temporary_failure_retry_sleep_ms'] ?? ($extra['temporary_failure_retry_sleep_ms'] ?? 1200))
        );
        if ($provider['provider_key'] === 'image' && trim($provider['outline_prompt_template']) === '') {
            $provider['outline_prompt_template'] = $provider['outline_prompt_template_default'];
        }
        $provider['image_prompt_template_default'] = ZhuibaoGraphicService::getDefaultImagePromptTemplate(false);
        $provider['image_prompt_template'] = str_replace(
            "\r",
            '',
            strval($provider['image_prompt_template'] ?? ($extra['image_prompt_template'] ?? ''))
        );
        if ($provider['provider_key'] === 'image' && trim($provider['image_prompt_template']) === '') {
            $provider['image_prompt_template'] = $provider['image_prompt_template_default'];
        }

        return $provider;
    }

    private static function normalizeProviderInfo(array $info): array
    {
        $defaults = self::defaultProviderMap();
        $providerKey = trim(strval($info['provider_key'] ?? ''));
        $default = $defaults[$providerKey] ?? ['extra_json' => []];
        $extraJson = self::buildProviderExtraJson($providerKey, $info, $default);
        $modelName = trim(strval($info['model_name'] ?? ''));

        if ($providerKey === 'tts' && $modelName === '') {
            $modelName = trim(strval($info['target_model'] ?? ($extraJson['target_model'] ?? '')));
        }

        $normalized = array_merge($default, $info, [
            'provider_key' => $providerKey,
            'provider_name' => trim(strval($info['provider_name'] ?? ($default['provider_name'] ?? ''))),
            'api_key' => trim(strval($info['api_key'] ?? '')),
            'base_url' => self::resolveProviderBaseUrl($providerKey, trim(strval($info['base_url'] ?? ''))),
            'model_name' => $modelName,
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'extra_json' => $extraJson,
        ]);

        return self::decorateProviderConfig($normalized);
    }

    private static function buildProviderExtraJson(string $providerKey, array $info, array $default): array
    {
        $defaultExtra = self::decodeJsonField($default['extra_json'] ?? []);
        $inputExtra = self::decodeJsonField($info['extra_json'] ?? []);
        $extra = array_replace_recursive($defaultExtra, $inputExtra);

        if ($providerKey === 'tts') {
            $extra['app_id'] = trim(strval($info['app_id'] ?? ($extra['app_id'] ?? '')));
            $extra['app_secret'] = trim(strval($info['app_secret'] ?? ($extra['app_secret'] ?? '')));
            $extra['voice_service_code'] = trim(strval($info['voice_service_code'] ?? ($extra['voice_service_code'] ?? 'SPro')));
            $extra['target_model'] = trim(strval($info['target_model'] ?? ($extra['target_model'] ?? 'cosyvoice-v3.5-flash')));
            $extra['default_voice_id'] = trim(strval($info['default_voice_id'] ?? ($extra['default_voice_id'] ?? '')));
            $extra['default_instruction'] = trim(strval($info['default_instruction'] ?? ($extra['default_instruction'] ?? '请用自然平稳的语气说话。')));
            $extra['voice_sample_url'] = trim(strval($info['voice_sample_url'] ?? ($extra['voice_sample_url'] ?? '')));
            $extra['voice_clone_prefix'] = trim(strval($info['voice_clone_prefix'] ?? ($extra['voice_clone_prefix'] ?? '我的声音')));
            $extra['voice_clone_sex'] = intval($info['voice_clone_sex'] ?? ($extra['voice_clone_sex'] ?? 0));
            $extra['voice_language'] = trim(strval($info['voice_language'] ?? ($extra['voice_language'] ?? 'zh')));
            $extra['voice_profiles'] = self::normalizeVoiceProfiles(array_merge($extra, $info));
            $defaultVoiceProfile = self::pickDefaultProfile(
                $extra['voice_profiles'],
                'voice_id',
                trim(strval($extra['default_voice_id'] ?? ''))
            );
            $extra['default_voice_id'] = trim(strval($defaultVoiceProfile['voice_id'] ?? ($extra['default_voice_id'] ?? '')));
            $extra['default_instruction'] = trim(strval($defaultVoiceProfile['instruction'] ?? ($extra['default_instruction'] ?? '请用自然平稳的语气说话。')));
            $extra['voice_sample_url'] = trim(strval($defaultVoiceProfile['sample_url'] ?? ($extra['voice_sample_url'] ?? '')));
            $extra['voice_clone_prefix'] = trim(strval($defaultVoiceProfile['name'] ?? ($extra['voice_clone_prefix'] ?? '我的声音')));
            $extra['voice_clone_sex'] = intval($defaultVoiceProfile['sex'] ?? ($extra['voice_clone_sex'] ?? 0));
            $extra['voice_language'] = trim(strval($defaultVoiceProfile['language'] ?? ($extra['voice_language'] ?? 'zh')));
        }

        if ($providerKey === 'avatar') {
            $extra['app_id'] = trim(strval($info['app_id'] ?? ($extra['app_id'] ?? '')));
            $extra['app_secret'] = trim(strval($info['app_secret'] ?? ($extra['app_secret'] ?? '')));
            $extra['platform_code'] = trim(strval($info['platform_code'] ?? ($extra['platform_code'] ?? 'VPro')));
            $extra['default_model_id'] = trim(strval($info['default_model_id'] ?? ($extra['default_model_id'] ?? '')));
            $extra['avatar_estimated_minutes_per_video_minute'] = self::normalizeAvatarEstimatedMinutesPerVideoMinute(
                $info['avatar_estimated_minutes_per_video_minute'] ?? ($extra['avatar_estimated_minutes_per_video_minute'] ?? 3)
            );
            $extra['avatar_sample_video_url'] = trim(strval($info['avatar_sample_video_url'] ?? ($extra['avatar_sample_video_url'] ?? '')));
            $extra['avatar_name'] = trim(strval($info['avatar_name'] ?? ($extra['avatar_name'] ?? '我的数字人')));
            $extra['avatar_sex'] = intval($info['avatar_sex'] ?? ($extra['avatar_sex'] ?? 1));
            $extra['avatar_profiles'] = self::normalizeAvatarProfiles(array_merge($extra, $info));
            $defaultAvatarProfile = self::pickDefaultProfile(
                $extra['avatar_profiles'],
                'model_id',
                trim(strval($extra['default_model_id'] ?? ''))
            );
            $extra['default_model_id'] = trim(strval($defaultAvatarProfile['model_id'] ?? ($extra['default_model_id'] ?? '')));
            $extra['avatar_sample_video_url'] = trim(strval($defaultAvatarProfile['sample_url'] ?? ($extra['avatar_sample_video_url'] ?? '')));
            $extra['avatar_name'] = trim(strval($defaultAvatarProfile['name'] ?? ($extra['avatar_name'] ?? '我的数字人')));
            $extra['avatar_sex'] = intval($defaultAvatarProfile['sex'] ?? ($extra['avatar_sex'] ?? 1));
        }

        if ($providerKey === 'editor') {
            $extra['app_id'] = trim(strval($info['app_id'] ?? ($extra['app_id'] ?? '')));
            $extra['app_secret'] = trim(strval($info['app_secret'] ?? ($extra['app_secret'] ?? '')));
            $extra['editor_region_id'] = trim(strval($info['editor_region_id'] ?? ($extra['editor_region_id'] ?? self::DEFAULT_EDITOR_REGION_ID)));
            $extra['editor_endpoint'] = trim(strval($info['editor_endpoint'] ?? ($extra['editor_endpoint'] ?? self::DEFAULT_EDITOR_ENDPOINT)));
            $extra['editor_template_id'] = trim(strval($info['editor_template_id'] ?? ($extra['editor_template_id'] ?? '')));
            $extra['editor_project_id'] = trim(strval($info['editor_project_id'] ?? ($extra['editor_project_id'] ?? '')));
            $extra['editor_pipeline_id'] = trim(strval($info['editor_pipeline_id'] ?? ($extra['editor_pipeline_id'] ?? '')));
            $extra['editor_notify_url'] = trim(strval($info['editor_notify_url'] ?? ($extra['editor_notify_url'] ?? '')));
            $extra['editor_output_bucket'] = trim(strval($info['editor_output_bucket'] ?? ($extra['editor_output_bucket'] ?? '')));
            $extra['editor_output_dir'] = trim(strval($info['editor_output_dir'] ?? ($extra['editor_output_dir'] ?? 'zhuibao/editor')));
            $extra['editor_aspect_ratio'] = trim(strval($info['editor_aspect_ratio'] ?? ($extra['editor_aspect_ratio'] ?? '9:16'))) ?: '9:16';
            $extra['editor_subtitle_enabled'] = intval($info['editor_subtitle_enabled'] ?? ($extra['editor_subtitle_enabled'] ?? 1)) === 1 ? 1 : 0;
            $extra['editor_default_bgm_url'] = trim(strval($info['editor_default_bgm_url'] ?? ($extra['editor_default_bgm_url'] ?? '')));
        }

        if ($providerKey === 'image') {
            $extra['cover_reference_enabled'] = intval($info['cover_reference_enabled'] ?? ($extra['cover_reference_enabled'] ?? 1)) === 1 ? 1 : 0;
            $extra['temporary_failure_retry_count'] = max(
                0,
                intval($info['temporary_failure_retry_count'] ?? ($extra['temporary_failure_retry_count'] ?? 2))
            );
            $extra['temporary_failure_retry_sleep_ms'] = max(
                0,
                intval($info['temporary_failure_retry_sleep_ms'] ?? ($extra['temporary_failure_retry_sleep_ms'] ?? 1200))
            );
            $extra['outline_prompt_template'] = str_replace(
                "\r",
                '',
                strval($info['outline_prompt_template'] ?? ($extra['outline_prompt_template'] ?? ''))
            );
            $extra['image_prompt_template'] = str_replace(
                "\r",
                '',
                strval($info['image_prompt_template'] ?? ($extra['image_prompt_template'] ?? ''))
            );
        }

        return $extra;
    }

    private static function resolveProviderBaseUrl(string $providerKey, string $baseUrl): string
    {
        if ($baseUrl !== '') {
            $baseUrl = rtrim($baseUrl, '/');
            if ($providerKey === 'image') {
                $normalizedHost = strtolower(trim(strval(parse_url($baseUrl, PHP_URL_HOST) ?: '')));
                if ($normalizedHost === 'www.aiwanwu.cc') {
                    $path = trim(strval(parse_url($baseUrl, PHP_URL_PATH) ?: ''));
                    if ($path === '/v1' || $path === '/v1/') {
                        return 'https://2api.aiwanwu.cc/v1';
                    }
                    if ($path === '') {
                        return 'https://2api.aiwanwu.cc';
                    }
                }
            }

            return $baseUrl;
        }

        if (in_array($providerKey, ['tts', 'avatar'], true)) {
            return self::DEFAULT_VENDOR_BASE_URL;
        }

        if ($providerKey === 'editor') {
            return self::DEFAULT_EDITOR_BASE_URL;
        }

        return '';
    }

    private static function defaultProviderMap(): array
    {
        $map = [];
        foreach (self::defaultProviders() as $provider) {
            $map[strval($provider['provider_key'] ?? '')] = $provider;
        }

        return $map;
    }

    private static function decorateTemplateRow(array $row): array
    {
        $type = trim(strval($row['type'] ?? ''));
        $content = self::decodeJsonField($row['content_json'] ?? '');

        $row['content_json'] = $content;
        $row['status_text'] = intval($row['status'] ?? 0) === 1 ? '启用' : '停用';
        $row['type_text'] = self::resolveTemplateTypeText($type);

        if ($type === 'edit') {
            $row['cover_url'] = trim(strval($content['cover_url'] ?? ''));
            $row['preview_video_url'] = trim(strval($content['preview_video_url'] ?? ''));
            $row['description'] = trim(strval($content['description'] ?? ''));
            $row['editor_template_id'] = trim(strval($content['editor_template_id'] ?? ''));
            $row['subtitle_template_id_classic'] = trim(strval($content['subtitle_template_id_classic'] ?? ''));
            $row['subtitle_template_id_bold'] = trim(strval($content['subtitle_template_id_bold'] ?? ''));
            $row['subtitle_template_id_minimal'] = trim(strval($content['subtitle_template_id_minimal'] ?? ''));
            $row['subtitle_template_id_highlight'] = trim(strval($content['subtitle_template_id_highlight'] ?? ''));
            $row['aspect_ratio'] = trim(strval($content['aspect_ratio'] ?? '9:16')) ?: '9:16';
            $row['subtitle_enabled'] = intval($content['subtitle_enabled'] ?? 1) === 1 ? 1 : 0;
            $row['subtitle_style'] = self::normalizeTemplateSubtitleStyle($content['subtitle_style'] ?? 'classic');
            $row['title_enabled'] = intval($content['title_enabled'] ?? 0) === 1 ? 1 : 0;
            $row['subheading_enabled'] = intval($content['subheading_enabled'] ?? 0) === 1 ? 1 : 0;
            $row['persona_enabled'] = intval($content['persona_enabled'] ?? 0) === 1 ? 1 : 0;
            $row['bgm_mode'] = self::normalizeTemplateBgmMode($content['bgm_mode'] ?? 'none');
            $row['bgm_url'] = trim(strval($content['bgm_url'] ?? ''));
            $row['bgm_volume'] = self::normalizeTemplateBgmVolume($content['bgm_volume'] ?? 60);
            $row['is_default'] = intval($content['is_default'] ?? 0) === 1 ? 1 : 0;
            $row['is_default_text'] = intval($row['is_default']) === 1 ? '默认模板' : '普通模板';

            $titleConfig = self::resolveTemplateTitleConfig($content);
            foreach ($titleConfig as $configKey => $configValue) {
                $row[$configKey] = $configValue;
            }

            $subtitleConfig = self::resolveTemplateSubtitleConfig($row['subtitle_style'], $content);
            foreach ($subtitleConfig as $configKey => $configValue) {
                $row[$configKey] = $configValue;
            }

            $personaConfig = self::resolveTemplatePersonaConfig($content);
            foreach ($personaConfig as $configKey => $configValue) {
                $row[$configKey] = $configValue;
            }
        }

        return $row;
    }

    private static function resolveTemplateTypeText(string $type): string
    {
        $map = [
            'persona' => '人设模板',
            'voice' => '配音模板',
            'edit' => '剪辑模板',
            'cover' => '封面模板',
        ];

        return $map[$type] ?? ($type !== '' ? $type : '未知模板');
    }

    private static function buildTemplateContentJson(int $aid, string $type, array $info, array $existingContent = []): array
    {
        $content = array_replace_recursive(
            $existingContent,
            self::decodeJsonField($info['content_json'] ?? [])
        );

        if ($type !== 'edit') {
            return $content;
        }

        $content['cover_url'] = trim(strval($info['cover_url'] ?? ($content['cover_url'] ?? '')));
        $content['preview_video_url'] = trim(strval($info['preview_video_url'] ?? ($content['preview_video_url'] ?? '')));
        $content['description'] = trim(strval($info['description'] ?? ($content['description'] ?? '')));
        $content['editor_template_id'] = trim(strval($info['editor_template_id'] ?? ($content['editor_template_id'] ?? '')));
        $content['subtitle_template_id_classic'] = trim(strval($info['subtitle_template_id_classic'] ?? ($content['subtitle_template_id_classic'] ?? '')));
        $content['subtitle_template_id_bold'] = trim(strval($info['subtitle_template_id_bold'] ?? ($content['subtitle_template_id_bold'] ?? '')));
        $content['subtitle_template_id_minimal'] = trim(strval($info['subtitle_template_id_minimal'] ?? ($content['subtitle_template_id_minimal'] ?? '')));
        $content['subtitle_template_id_highlight'] = trim(strval($info['subtitle_template_id_highlight'] ?? ($content['subtitle_template_id_highlight'] ?? '')));
        $content['aspect_ratio'] = trim(strval($info['aspect_ratio'] ?? ($content['aspect_ratio'] ?? '9:16'))) ?: '9:16';
        $content['subtitle_enabled'] = intval($info['subtitle_enabled'] ?? ($content['subtitle_enabled'] ?? 1)) === 1 ? 1 : 0;
        $content['subtitle_style'] = self::normalizeTemplateSubtitleStyle($info['subtitle_style'] ?? ($content['subtitle_style'] ?? 'classic'));
        $content['title_enabled'] = intval($info['title_enabled'] ?? ($content['title_enabled'] ?? 0)) === 1 ? 1 : 0;
        $content['subheading_enabled'] = intval($info['subheading_enabled'] ?? ($content['subheading_enabled'] ?? 0)) === 1 ? 1 : 0;
        $content['persona_enabled'] = intval($info['persona_enabled'] ?? ($content['persona_enabled'] ?? 0)) === 1 ? 1 : 0;
        $content['bgm_mode'] = self::normalizeTemplateBgmMode($info['bgm_mode'] ?? ($content['bgm_mode'] ?? 'none'));
        $content['bgm_url'] = trim(strval($info['bgm_url'] ?? ($content['bgm_url'] ?? '')));
        $content['bgm_volume'] = self::normalizeTemplateBgmVolume($info['bgm_volume'] ?? ($content['bgm_volume'] ?? 60));
        $content['is_default'] = intval($info['is_default'] ?? ($content['is_default'] ?? 0)) === 1 ? 1 : 0;

        $titleConfig = self::resolveTemplateTitleConfig(array_merge($content, $info));
        foreach ($titleConfig as $configKey => $configValue) {
            $content[$configKey] = $configValue;
        }

        $subtitleConfig = self::resolveTemplateSubtitleConfig($content['subtitle_style'], array_merge($content, $info));
        foreach ($subtitleConfig as $configKey => $configValue) {
            $content[$configKey] = $configValue;
        }

        $personaConfig = self::resolveTemplatePersonaConfig(array_merge($content, $info));
        foreach ($personaConfig as $configKey => $configValue) {
            $content[$configKey] = $configValue;
        }

        if ($content['is_default'] !== 1 && empty(self::getEditorTemplates($aid, false))) {
            $content['is_default'] = 1;
        }

        return $content;
    }

    private static function clearEditorTemplateDefault(int $aid, int $exceptId): void
    {
        $rows = Db::name('zhuibao_template')
            ->where('aid', $aid)
            ->where('type', 'edit')
            ->where('id', '<>', $exceptId)
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            $content = self::decodeJsonField($row['content_json'] ?? '');
            if (intval($content['is_default'] ?? 0) !== 1) {
                continue;
            }

            $content['is_default'] = 0;
            Db::name('zhuibao_template')
                ->where('aid', $aid)
                ->where('id', intval($row['id'] ?? 0))
                ->update([
                    'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE),
                    'update_time' => time(),
                ]);
        }
    }

    private static function normalizeTemplateBgmMode($value): string
    {
        $mode = trim(strtolower(strval($value)));
        if (in_array($mode, ['none', 'default', 'custom'], true)) {
            return $mode;
        }

        return 'none';
    }

    private static function normalizeTemplateBgmVolume($value): int
    {
        $volume = intval($value);
        if ($volume < 0 || $volume > 100) {
            return 60;
        }

        return $volume;
    }

    private static function normalizeTemplateSubtitleStyle($value): string
    {
        $style = trim(strtolower(strval($value)));
        if (in_array($style, ['classic', 'bold', 'minimal', 'highlight', 'viral'], true)) {
            return $style;
        }

        return 'classic';
    }

    public static function resolveTemplateTitleConfig(array $config = []): array
    {
        return [
            'title_font' => self::normalizeTemplateSubtitleFont($config['title_font'] ?? 'Alibaba PuHuiTi 2.0 95 ExtraBold'),
            'title_font_size' => self::normalizeTemplateSubtitleInt($config['title_font_size'] ?? 68, 18, 120, 68),
            'title_font_color' => self::normalizeTemplateSubtitleColor($config['title_font_color'] ?? '#FFFFFF', '#FFFFFF'),
            'title_outline_color' => self::normalizeTemplateSubtitleColor($config['title_outline_color'] ?? '#000000', '#000000'),
            'title_outline_width' => self::normalizeTemplateSubtitleInt($config['title_outline_width'] ?? 2, 0, 10, 2),
            'title_bg_color' => self::normalizeTemplateSubtitleColor($config['title_bg_color'] ?? '', '', true),
            'title_alignment' => self::normalizeTemplateSubtitleAlignment($config['title_alignment'] ?? 'TopCenter'),
            'title_x' => self::normalizeTemplateSubtitleFloat($config['title_x'] ?? 0, -1.0, 1.0, 0),
            'title_y' => self::normalizeTemplateSubtitleFloat($config['title_y'] ?? 0.08, 0.05, 0.95, 0.08),
            'title_text_width' => self::normalizeTemplateSubtitleFloat($config['title_text_width'] ?? 0.82, 0.3, 0.95, 0.82),
            'title_bold' => intval($config['title_bold'] ?? 1) === 1 ? 1 : 0,
        ];
    }

    public static function resolveTemplateSubtitleConfig(string $style = 'classic', array $config = []): array
    {
        $style = self::normalizeTemplateSubtitleStyle($style);
        $presets = [
            'classic' => [
                'subtitle_font' => 'Alibaba PuHuiTi 2.0 95 ExtraBold',
                'subtitle_font_size' => 52,
                'subtitle_font_color' => '#FFFFFF',
                'subtitle_outline_color' => '#000000',
                'subtitle_outline_width' => 3,
                'subtitle_bg_color' => '',
                'subtitle_alignment' => 'BottomCenter',
                'subtitle_x' => 0,
                'subtitle_y' => 0.82,
                'subtitle_text_width' => 0.82,
                'subtitle_bold' => 1,
                'subtitle_effect_color_style' => '',
                'subtitle_bubble_style_id' => '',
                'subtitle_bubble_width' => 0,
                'subtitle_bubble_height' => 0,
                'subtitle_motion_in_effect' => '',
                'subtitle_motion_out_effect' => '',
            ],
            'bold' => [
                'subtitle_font' => 'Alibaba PuHuiTi 2.0 115 Black',
                'subtitle_font_size' => 58,
                'subtitle_font_color' => '#FFE066',
                'subtitle_outline_color' => '#111111',
                'subtitle_outline_width' => 4,
                'subtitle_bg_color' => '',
                'subtitle_alignment' => 'BottomCenter',
                'subtitle_x' => 0,
                'subtitle_y' => 0.82,
                'subtitle_text_width' => 0.82,
                'subtitle_bold' => 1,
                'subtitle_effect_color_style' => '',
                'subtitle_bubble_style_id' => '',
                'subtitle_bubble_width' => 0,
                'subtitle_bubble_height' => 0,
                'subtitle_motion_in_effect' => '',
                'subtitle_motion_out_effect' => '',
            ],
            'minimal' => [
                'subtitle_font' => 'Alibaba PuHuiTi 2.0 65 Medium',
                'subtitle_font_size' => 46,
                'subtitle_font_color' => '#F9FAFB',
                'subtitle_outline_color' => '#00000066',
                'subtitle_outline_width' => 1,
                'subtitle_bg_color' => '#00000055',
                'subtitle_alignment' => 'BottomCenter',
                'subtitle_x' => 0,
                'subtitle_y' => 0.84,
                'subtitle_text_width' => 0.82,
                'subtitle_bold' => 0,
                'subtitle_effect_color_style' => '',
                'subtitle_bubble_style_id' => '',
                'subtitle_bubble_width' => 0,
                'subtitle_bubble_height' => 0,
                'subtitle_motion_in_effect' => '',
                'subtitle_motion_out_effect' => '',
            ],
            'highlight' => [
                'subtitle_font' => 'Alibaba PuHuiTi 2.0 95 ExtraBold',
                'subtitle_font_size' => 54,
                'subtitle_font_color' => '#111827',
                'subtitle_outline_color' => '#000000',
                'subtitle_outline_width' => 0,
                'subtitle_bg_color' => '#FDE68A',
                'subtitle_alignment' => 'BottomCenter',
                'subtitle_x' => 0,
                'subtitle_y' => 0.82,
                'subtitle_text_width' => 0.82,
                'subtitle_bold' => 1,
                'subtitle_effect_color_style' => '',
                'subtitle_bubble_style_id' => '',
                'subtitle_bubble_width' => 0,
                'subtitle_bubble_height' => 0,
                'subtitle_motion_in_effect' => '',
                'subtitle_motion_out_effect' => '',
            ],
            'viral' => [
                'subtitle_font' => 'Alibaba PuHuiTi 2.0 115 Black',
                'subtitle_font_size' => 60,
                'subtitle_font_color' => '#FFFFFF',
                'subtitle_outline_color' => '#111111',
                'subtitle_outline_width' => 4,
                'subtitle_bg_color' => '',
                'subtitle_alignment' => 'Center',
                'subtitle_x' => -0.08,
                'subtitle_y' => 0.74,
                'subtitle_text_width' => 0.76,
                'subtitle_bold' => 1,
                'subtitle_effect_color_style' => '',
                'subtitle_bubble_style_id' => '',
                'subtitle_bubble_width' => 0,
                'subtitle_bubble_height' => 0,
                'subtitle_motion_in_effect' => '',
                'subtitle_motion_out_effect' => '',
            ],
        ];

        $preset = $presets[$style] ?? $presets['classic'];

        return [
            'subtitle_font' => self::normalizeTemplateSubtitleFont($config['subtitle_font'] ?? ($preset['subtitle_font'] ?? '')),
            'subtitle_font_size' => self::normalizeTemplateSubtitleInt($config['subtitle_font_size'] ?? ($preset['subtitle_font_size'] ?? 52), 18, 120, 52),
            'subtitle_font_color' => self::normalizeTemplateSubtitleColor($config['subtitle_font_color'] ?? ($preset['subtitle_font_color'] ?? '#FFFFFF'), '#FFFFFF'),
            'subtitle_outline_color' => self::normalizeTemplateSubtitleColor($config['subtitle_outline_color'] ?? ($preset['subtitle_outline_color'] ?? '#000000'), '#000000'),
            'subtitle_outline_width' => self::normalizeTemplateSubtitleInt($config['subtitle_outline_width'] ?? ($preset['subtitle_outline_width'] ?? 3), 0, 10, 3),
            'subtitle_bg_color' => self::normalizeTemplateSubtitleColor($config['subtitle_bg_color'] ?? ($preset['subtitle_bg_color'] ?? ''), '', true),
            'subtitle_alignment' => self::normalizeTemplateSubtitleAlignment($config['subtitle_alignment'] ?? ($preset['subtitle_alignment'] ?? 'BottomCenter')),
            'subtitle_x' => self::normalizeTemplateSubtitleFloat($config['subtitle_x'] ?? ($preset['subtitle_x'] ?? 0), -1.0, 1.0, 0),
            'subtitle_y' => self::normalizeTemplateSubtitleFloat($config['subtitle_y'] ?? ($preset['subtitle_y'] ?? 0.82), 0.05, 0.95, 0.82),
            'subtitle_text_width' => self::normalizeTemplateSubtitleFloat($config['subtitle_text_width'] ?? ($preset['subtitle_text_width'] ?? 0.82), 0.3, 0.95, 0.82),
            'subtitle_bold' => intval($config['subtitle_bold'] ?? ($preset['subtitle_bold'] ?? 0)) === 1 ? 1 : 0,
            'subtitle_effect_color_style' => self::normalizeTemplateSubtitleString($config['subtitle_effect_color_style'] ?? ($preset['subtitle_effect_color_style'] ?? '')),
            'subtitle_bubble_style_id' => self::normalizeTemplateSubtitleString($config['subtitle_bubble_style_id'] ?? ($preset['subtitle_bubble_style_id'] ?? '')),
            'subtitle_bubble_width' => self::normalizeTemplateSubtitleInt($config['subtitle_bubble_width'] ?? ($preset['subtitle_bubble_width'] ?? 0), 0, 1000, 0),
            'subtitle_bubble_height' => self::normalizeTemplateSubtitleInt($config['subtitle_bubble_height'] ?? ($preset['subtitle_bubble_height'] ?? 0), 0, 1000, 0),
            'subtitle_motion_in_effect' => self::normalizeTemplateSubtitleString($config['subtitle_motion_in_effect'] ?? ($preset['subtitle_motion_in_effect'] ?? '')),
            'subtitle_motion_out_effect' => self::normalizeTemplateSubtitleString($config['subtitle_motion_out_effect'] ?? ($preset['subtitle_motion_out_effect'] ?? '')),
        ];
    }

    public static function resolveTemplatePersonaConfig(array $config = []): array
    {
        return [
            'persona_mode' => trim(strtolower(strval($config['persona_mode'] ?? 'fixed'))) === 'ai' ? 'ai' : 'fixed',
            'persona_prompt' => self::normalizeTemplateSubtitleString($config['persona_prompt'] ?? ''),
            'persona_text' => self::normalizeTemplateSubtitleString($config['persona_text'] ?? '数码测评') ?: '数码测评',
            'persona_font' => self::normalizeTemplateSubtitleFont($config['persona_font'] ?? 'Alibaba PuHuiTi 2.0 95 ExtraBold'),
            'persona_font_size' => self::normalizeTemplateSubtitleInt($config['persona_font_size'] ?? 34, 18, 88, 34),
            'persona_font_color' => self::normalizeTemplateSubtitleColor($config['persona_font_color'] ?? '#FFFFFF', '#FFFFFF'),
            'persona_bg_color' => self::normalizeTemplateSubtitleColor($config['persona_bg_color'] ?? '#111827CC', '#111827CC', true),
            'persona_alignment' => self::normalizeTemplateSubtitleAlignment($config['persona_alignment'] ?? 'TopCenter'),
            'persona_x' => self::normalizeTemplateSubtitleFloat($config['persona_x'] ?? -0.24, -0.45, 0.45, -0.24),
            'persona_y' => self::normalizeTemplateSubtitleFloat($config['persona_y'] ?? 0.14, 0.05, 0.95, 0.14),
            'persona_text_width' => self::normalizeTemplateSubtitleFloat($config['persona_text_width'] ?? 0.3, 0.18, 0.6, 0.3),
            'persona_bold' => intval($config['persona_bold'] ?? 1) === 1 ? 1 : 0,
        ];
    }

    private static function normalizeTemplateSubtitleFont($value): string
    {
        $font = trim(strval($value));
        $allowed = [
            'Alibaba PuHuiTi 2.0 95 ExtraBold',
            'Alibaba PuHuiTi 2.0 115 Black',
            'Alibaba PuHuiTi 2.0 65 Medium',
            'Alibaba PuHuiTi 2.0 55 Regular',
        ];

        if (in_array($font, $allowed, true)) {
            return $font;
        }

        return 'Alibaba PuHuiTi 2.0 95 ExtraBold';
    }

    private static function normalizeTemplateSubtitleAlignment($value): string
    {
        $alignment = trim(strval($value));
        $allowed = ['TopCenter', 'Center', 'BottomCenter'];
        if (in_array($alignment, $allowed, true)) {
            return $alignment;
        }

        return 'BottomCenter';
    }

    private static function normalizeTemplateSubtitleInt($value, int $min, int $max, int $default): int
    {
        $number = intval($value);
        if ($number < $min || $number > $max) {
            return $default;
        }

        return $number;
    }

    private static function normalizeTemplateSubtitleFloat($value, float $min, float $max, float $default): float
    {
        $number = floatval($value);
        if ($number < $min || $number > $max) {
            return $default;
        }

        return round($number, 3);
    }

    private static function normalizeTemplateSubtitleColor($value, string $default = '', bool $allowEmpty = false): string
    {
        $color = strtoupper(trim(strval($value)));
        if ($allowEmpty && $color === '') {
            return '';
        }

        if (preg_match('/^#([0-9A-F]{3}|[0-9A-F]{4}|[0-9A-F]{6}|[0-9A-F]{8})$/', $color)) {
            return $color;
        }

        return $default;
    }

    private static function normalizeTemplateSubtitleString($value): string
    {
        return trim(strval($value));
    }

    private static function pickDefaultProfile(array $profiles, string $idKey, string $fallbackId = ''): array
    {
        foreach ($profiles as $profile) {
            if (intval($profile['is_default'] ?? 0) === 1) {
                return is_array($profile) ? $profile : [];
            }
        }

        if ($fallbackId !== '') {
            foreach ($profiles as $profile) {
                if (trim(strval($profile[$idKey] ?? '')) === $fallbackId) {
                    return is_array($profile) ? $profile : [];
                }
            }
        }

        return isset($profiles[0]) && is_array($profiles[0]) ? $profiles[0] : [];
    }

    private static function normalizeVoiceProfiles(array $provider, array $extra = []): array
    {
        $profiles = is_array($provider['voice_profiles'] ?? null)
            ? $provider['voice_profiles']
            : self::decodeJsonField($extra['voice_profiles'] ?? ($provider['voice_profiles'] ?? []));

        $normalized = [];
        foreach ($profiles as $index => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $normalized[] = [
                'local_id' => trim(strval($profile['local_id'] ?? '')) ?: ('voice_' . ($index + 1)),
                'name' => trim(strval($profile['name'] ?? ($profile['voice_name'] ?? ''))) ?: '我的声音',
                'voice_id' => trim(strval($profile['voice_id'] ?? '')),
                'sample_url' => trim(strval($profile['sample_url'] ?? '')),
                'sex' => intval($profile['sex'] ?? 0),
                'language' => trim(strval($profile['language'] ?? 'zh')) ?: 'zh',
                'instruction' => trim(strval($profile['instruction'] ?? '请用自然平稳的语气说话。')) ?: '请用自然平稳的语气说话。',
                'is_default' => intval($profile['is_default'] ?? 0) === 1 ? 1 : 0,
            ];
        }

        if (empty($normalized)) {
            $legacyVoiceId = trim(strval($provider['default_voice_id'] ?? ($extra['default_voice_id'] ?? '')));
            $legacySampleUrl = trim(strval($provider['voice_sample_url'] ?? ($extra['voice_sample_url'] ?? '')));
            $legacyName = trim(strval($provider['voice_clone_prefix'] ?? ($extra['voice_clone_prefix'] ?? '')));
            $legacyInstruction = trim(strval($provider['default_instruction'] ?? ($extra['default_instruction'] ?? '')));
            if ($legacyVoiceId !== '' || $legacySampleUrl !== '' || $legacyName !== '' || $legacyInstruction !== '') {
                $normalized[] = [
                    'local_id' => 'voice_legacy',
                    'name' => $legacyName !== '' ? $legacyName : '我的声音',
                    'voice_id' => $legacyVoiceId,
                    'sample_url' => $legacySampleUrl,
                    'sex' => intval($provider['voice_clone_sex'] ?? ($extra['voice_clone_sex'] ?? 0)),
                    'language' => trim(strval($provider['voice_language'] ?? ($extra['voice_language'] ?? 'zh'))) ?: 'zh',
                    'instruction' => $legacyInstruction !== '' ? $legacyInstruction : '请用自然平稳的语气说话。',
                    'is_default' => 1,
                ];
            }
        }

        $default = self::pickDefaultProfile(
            $normalized,
            'voice_id',
            trim(strval($provider['default_voice_id'] ?? ($extra['default_voice_id'] ?? '')))
        );
        $defaultLocalId = trim(strval($default['local_id'] ?? ''));

        foreach ($normalized as &$profile) {
            $profile['is_default'] = $defaultLocalId !== '' && trim(strval($profile['local_id'] ?? '')) === $defaultLocalId ? 1 : 0;
        }
        unset($profile);

        return array_values($normalized);
    }

    private static function normalizeAvatarProfiles(array $provider, array $extra = []): array
    {
        $profiles = is_array($provider['avatar_profiles'] ?? null)
            ? $provider['avatar_profiles']
            : self::decodeJsonField($extra['avatar_profiles'] ?? ($provider['avatar_profiles'] ?? []));

        $normalized = [];
        foreach ($profiles as $index => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $normalized[] = [
                'local_id' => trim(strval($profile['local_id'] ?? '')) ?: ('avatar_' . ($index + 1)),
                'name' => trim(strval($profile['name'] ?? '')) ?: '我的数字人',
                'sex' => intval($profile['sex'] ?? 1),
                'platform_code' => trim(strval($profile['platform_code'] ?? 'VPro')) ?: 'VPro',
                'sample_url' => trim(strval($profile['sample_url'] ?? '')),
                'model_id' => trim(strval($profile['model_id'] ?? '')),
                'task_group_id' => intval($profile['task_group_id'] ?? 0),
                'is_default' => intval($profile['is_default'] ?? 0) === 1 ? 1 : 0,
            ];
        }

        if (empty($normalized)) {
            $legacyModelId = trim(strval($provider['default_model_id'] ?? ($extra['default_model_id'] ?? '')));
            $legacySampleUrl = trim(strval($provider['avatar_sample_video_url'] ?? ($extra['avatar_sample_video_url'] ?? '')));
            $legacyName = trim(strval($provider['avatar_name'] ?? ($extra['avatar_name'] ?? '')));
            if ($legacyModelId !== '' || $legacySampleUrl !== '' || $legacyName !== '') {
                $normalized[] = [
                    'local_id' => 'avatar_legacy',
                    'name' => $legacyName !== '' ? $legacyName : '我的数字人',
                    'sex' => intval($provider['avatar_sex'] ?? ($extra['avatar_sex'] ?? 1)),
                    'platform_code' => trim(strval($provider['platform_code'] ?? ($extra['platform_code'] ?? 'VPro'))) ?: 'VPro',
                    'sample_url' => $legacySampleUrl,
                    'model_id' => $legacyModelId,
                    'task_group_id' => 0,
                    'is_default' => 1,
                ];
            }
        }

        $default = self::pickDefaultProfile(
            $normalized,
            'model_id',
            trim(strval($provider['default_model_id'] ?? ($extra['default_model_id'] ?? '')))
        );
        $defaultLocalId = trim(strval($default['local_id'] ?? ''));

        foreach ($normalized as &$profile) {
            $profile['is_default'] = $defaultLocalId !== '' && trim(strval($profile['local_id'] ?? '')) === $defaultLocalId ? 1 : 0;
        }
        unset($profile);

        return array_values($normalized);
    }

    private static function getMemberAvatarProfilesOnly(int $aid, int $uid, int $bid = 0): array
    {
        self::ensureMemberAvatarDefaultExists($aid, $uid, 0);

        $query = Db::name('zhuibao_member_avatar_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('status', 1)
            ->where('delete_time', 0);

        if ($bid > 0) {
            $query->where(function ($subQuery) use ($bid) {
                $subQuery->where('bid', 0)->whereOr('bid', $bid);
            });
        }

        $rows = $query
            ->order('is_default desc, sort asc, id asc')
            ->select()
            ->toArray();

        return array_values(array_map(static function ($row) {
            return ZhuibaoProviderService::formatMemberAvatarProfileRow(is_array($row) ? $row : []);
        }, $rows));
    }

    private static function buildPlatformAvatarProfilesForMember(array $provider, bool $memberProfilesExist): array
    {
        $profiles = is_array($provider['avatar_profiles'] ?? null) ? $provider['avatar_profiles'] : [];
        $normalized = [];

        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $normalized[] = [
                'id' => 0,
                'local_id' => trim(strval($profile['local_id'] ?? '')),
                'name' => trim(strval($profile['name'] ?? '')) ?: '平台数字人',
                'sex' => intval($profile['sex'] ?? 1),
                'platform_code' => trim(strval($profile['platform_code'] ?? 'VPro')) ?: 'VPro',
                'sample_url' => trim(strval($profile['sample_url'] ?? '')),
                'model_id' => trim(strval($profile['model_id'] ?? '')),
                'task_group_id' => intval($profile['task_group_id'] ?? 0),
                'is_default' => $memberProfilesExist ? 0 : (intval($profile['is_default'] ?? 0) === 1 ? 1 : 0),
                'status' => 1,
                'editable' => 0,
                'owner_type' => 'platform',
                'owner_type_text' => '平台',
                'source_type' => 'platform',
                'remark' => '',
            ];
        }

        return array_values($normalized);
    }

    private static function formatMemberAvatarProfileRow(array $row): array
    {
        $id = intval($row['id'] ?? 0);
        return [
            'id' => $id,
            'local_id' => 'member_avatar_' . $id,
            'name' => trim(strval($row['name'] ?? '')) ?: '我的数字人',
            'sex' => intval($row['sex'] ?? 1),
            'platform_code' => trim(strval($row['platform_code'] ?? 'VPro')) ?: 'VPro',
            'sample_url' => trim(strval($row['sample_url'] ?? '')),
            'model_id' => trim(strval($row['model_id'] ?? '')),
            'task_group_id' => intval($row['task_group_id'] ?? 0),
            'is_default' => intval($row['is_default'] ?? 0) === 1 ? 1 : 0,
            'status' => intval($row['status'] ?? 1),
            'editable' => 1,
            'owner_type' => 'member',
            'owner_type_text' => '我的',
            'source_type' => trim(strval($row['source_type'] ?? 'member')) ?: 'member',
            'remark' => trim(strval($row['remark'] ?? '')),
        ];
    }

    private static function getMemberAvatarProfileById(int $aid, int $uid, int $id): array
    {
        $row = Db::name('zhuibao_member_avatar_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('id', $id)
            ->where('delete_time', 0)
            ->find();

        if (!$row) {
            throw new \RuntimeException('数字人不存在或无权访问');
        }

        return self::formatMemberAvatarProfileRow($row);
    }

    private static function syncMemberAvatarDefaultState(int $aid, int $uid, int $defaultId): void
    {
        if ($defaultId <= 0) {
            return;
        }

        Db::name('zhuibao_member_avatar_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('delete_time', 0)
            ->update([
                'is_default' => 0,
                'update_time' => time(),
            ]);

        Db::name('zhuibao_member_avatar_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('id', $defaultId)
            ->where('delete_time', 0)
            ->update([
                'is_default' => 1,
                'status' => 1,
                'update_time' => time(),
            ]);
    }

    private static function ensureMemberAvatarDefaultExists(int $aid, int $uid, int $preferredId = 0): void
    {
        $rows = Db::name('zhuibao_member_avatar_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('status', 1)
            ->where('delete_time', 0)
            ->order('is_default desc, sort asc, id asc')
            ->select()
            ->toArray();

        if (empty($rows)) {
            return;
        }

        $defaultId = 0;
        foreach ($rows as $row) {
            if (intval($row['is_default'] ?? 0) === 1) {
                $defaultId = intval($row['id'] ?? 0);
                break;
            }
        }

        if ($defaultId > 0) {
            return;
        }

        $defaultId = $preferredId > 0 ? $preferredId : intval($rows[0]['id'] ?? 0);
        if ($defaultId <= 0) {
            return;
        }

        self::syncMemberAvatarDefaultState($aid, $uid, $defaultId);
    }

    private static function getMemberVoiceProfilesOnly(int $aid, int $uid, int $bid = 0): array
    {
        self::ensureMemberVoiceDefaultExists($aid, $uid, 0);

        $query = Db::name('zhuibao_member_voice_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('status', 1)
            ->where('delete_time', 0);

        if ($bid > 0) {
            $query->where(function ($subQuery) use ($bid) {
                $subQuery->where('bid', 0)->whereOr('bid', $bid);
            });
        }

        $rows = $query
            ->order('is_default desc, sort asc, id asc')
            ->select()
            ->toArray();

        return array_values(array_map(static function ($row) {
            return ZhuibaoProviderService::formatMemberVoiceProfileRow(is_array($row) ? $row : []);
        }, $rows));
    }

    private static function buildPlatformVoiceProfilesForMember(array $provider, bool $memberProfilesExist): array
    {
        $profiles = is_array($provider['voice_profiles'] ?? null) ? $provider['voice_profiles'] : [];
        $normalized = [];

        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $normalized[] = [
                'id' => 0,
                'local_id' => trim(strval($profile['local_id'] ?? '')),
                'name' => trim(strval($profile['name'] ?? '')) ?: '平台声音',
                'voice_id' => trim(strval($profile['voice_id'] ?? '')),
                'sample_url' => trim(strval($profile['sample_url'] ?? '')),
                'sex' => intval($profile['sex'] ?? 0),
                'language' => trim(strval($profile['language'] ?? 'zh')) ?: 'zh',
                'instruction' => trim(strval($profile['instruction'] ?? '请用自然平稳的语气说话。')) ?: '请用自然平稳的语气说话。',
                'is_default' => $memberProfilesExist ? 0 : (intval($profile['is_default'] ?? 0) === 1 ? 1 : 0),
                'status' => 1,
                'editable' => 0,
                'owner_type' => 'platform',
                'owner_type_text' => '平台',
                'source_type' => 'platform',
                'remark' => '',
            ];
        }

        return array_values($normalized);
    }

    private static function formatMemberVoiceProfileRow(array $row): array
    {
        $id = intval($row['id'] ?? 0);
        return [
            'id' => $id,
            'local_id' => 'member_voice_' . $id,
            'name' => trim(strval($row['name'] ?? '')) ?: '我的声音',
            'voice_id' => trim(strval($row['voice_id'] ?? '')),
            'sample_url' => trim(strval($row['sample_url'] ?? '')),
            'sex' => intval($row['sex'] ?? 0),
            'language' => trim(strval($row['language'] ?? 'zh')) ?: 'zh',
            'instruction' => trim(strval($row['instruction'] ?? '请用自然平稳的语气说话。')) ?: '请用自然平稳的语气说话。',
            'is_default' => intval($row['is_default'] ?? 0) === 1 ? 1 : 0,
            'status' => intval($row['status'] ?? 1),
            'editable' => 1,
            'owner_type' => 'member',
            'owner_type_text' => '我的',
            'source_type' => trim(strval($row['source_type'] ?? 'member')) ?: 'member',
            'remark' => trim(strval($row['remark'] ?? '')),
        ];
    }

    private static function getMemberVoiceProfileById(int $aid, int $uid, int $id): array
    {
        $row = Db::name('zhuibao_member_voice_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('id', $id)
            ->where('delete_time', 0)
            ->find();

        if (!$row) {
            throw new \RuntimeException('声音不存在或无权访问');
        }

        return self::formatMemberVoiceProfileRow($row);
    }

    private static function syncMemberVoiceDefaultState(int $aid, int $uid, int $defaultId): void
    {
        if ($defaultId <= 0) {
            return;
        }

        Db::name('zhuibao_member_voice_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('delete_time', 0)
            ->update([
                'is_default' => 0,
                'update_time' => time(),
            ]);

        Db::name('zhuibao_member_voice_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('id', $defaultId)
            ->where('delete_time', 0)
            ->update([
                'is_default' => 1,
                'status' => 1,
                'update_time' => time(),
            ]);
    }

    private static function ensureMemberVoiceDefaultExists(int $aid, int $uid, int $preferredId = 0): void
    {
        $rows = Db::name('zhuibao_member_voice_profile')
            ->where('aid', $aid)
            ->where('uid', $uid)
            ->where('status', 1)
            ->where('delete_time', 0)
            ->order('is_default desc, sort asc, id asc')
            ->select()
            ->toArray();

        if (empty($rows)) {
            return;
        }

        $defaultId = 0;
        foreach ($rows as $row) {
            if (intval($row['is_default'] ?? 0) === 1) {
                $defaultId = intval($row['id'] ?? 0);
                break;
            }
        }

        if ($defaultId > 0) {
            return;
        }

        $defaultId = $preferredId > 0 ? $preferredId : intval($rows[0]['id'] ?? 0);
        if ($defaultId <= 0) {
            return;
        }

        self::syncMemberVoiceDefaultState($aid, $uid, $defaultId);
    }

    private static function applyExplicitVoiceCloneOverrides(array $provider, array $info): array
    {
        $sampleUrl = trim(strval($info['voice_sample_url'] ?? ''));
        if ($sampleUrl !== '') {
            $provider['voice_sample_url'] = $sampleUrl;
        }

        $clonePrefix = trim(strval($info['voice_clone_prefix'] ?? ''));
        if ($clonePrefix !== '') {
            $provider['voice_clone_prefix'] = $clonePrefix;
        }

        if (array_key_exists('voice_clone_sex', $info)) {
            $provider['voice_clone_sex'] = intval($info['voice_clone_sex']);
        }

        $language = trim(strval($info['voice_language'] ?? ''));
        if ($language !== '') {
            $provider['voice_language'] = $language;
        }

        $serviceCode = trim(strval($info['voice_service_code'] ?? ''));
        if ($serviceCode !== '') {
            $provider['voice_service_code'] = $serviceCode;
        }

        $targetModel = trim(strval($info['target_model'] ?? ''));
        if ($targetModel !== '') {
            $provider['target_model'] = $targetModel;
        }

        $modelCode = trim(strval($info['model_code'] ?? ''));
        if ($modelCode !== '') {
            $provider['model_code'] = $modelCode;
        }

        return $provider;
    }

    private static function applyExplicitAvatarCloneOverrides(array $provider, array $info): array
    {
        $sampleUrl = trim(strval($info['avatar_sample_video_url'] ?? ''));
        if ($sampleUrl !== '') {
            $provider['avatar_sample_video_url'] = $sampleUrl;
        }

        $avatarName = trim(strval($info['avatar_name'] ?? ''));
        if ($avatarName !== '') {
            $provider['avatar_name'] = $avatarName;
        }

        if (array_key_exists('avatar_sex', $info)) {
            $provider['avatar_sex'] = intval($info['avatar_sex']);
        }

        $platformCode = trim(strval($info['platform_code'] ?? ''));
        if ($platformCode !== '') {
            $provider['platform_code'] = $platformCode;
        }

        return $provider;
    }

    private static function assertVendorProvider(array $provider, string $providerKey, string $providerName): void
    {
        if (trim(strval($provider['provider_key'] ?? '')) !== $providerKey) {
            throw new \RuntimeException($providerName . '配置类型错误');
        }
        if (trim(strval($provider['base_url'] ?? '')) === '') {
            throw new \RuntimeException('请先填写' . $providerName . '请求地址');
        }
        if (trim(strval($provider['app_id'] ?? '')) === '') {
            throw new \RuntimeException('请先填写' . $providerName . 'AppID');
        }
        if (trim(strval($provider['app_secret'] ?? '')) === '') {
            throw new \RuntimeException('请先填写' . $providerName . '密钥');
        }
    }

    private static function assertPublicMediaUrl(string $url, string $fieldName): void
    {
        if (!preg_match('/^https?:\/\//i', $url)) {
            throw new \RuntimeException($fieldName . '必须是 http/https 开头的完整地址');
        }

        $host = strtolower(strval(parse_url($url, PHP_URL_HOST)));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            throw new \RuntimeException($fieldName . '必须是外网可访问地址，不能使用 localhost');
        }
    }

    private static function normalizeLanguageHints($value): array
    {
        if (is_array($value)) {
            $list = array_values(array_filter(array_map('strval', $value)));
            return empty($list) ? ['zh'] : $list;
        }

        $text = trim(strval($value));
        if ($text === '') {
            return ['zh'];
        }

        $parts = preg_split('/[\s,，]+/', $text);
        $parts = array_values(array_filter(array_map('strval', is_array($parts) ? $parts : [])));
        return empty($parts) ? ['zh'] : $parts;
    }

    private static function normalizeAvatarEstimatedMinutesPerVideoMinute($value): float
    {
        $number = round(floatval($value), 2);
        if ($number < 0.2) {
            return 0.2;
        }
        if ($number > 20) {
            return 20.0;
        }

        return $number;
    }
}
