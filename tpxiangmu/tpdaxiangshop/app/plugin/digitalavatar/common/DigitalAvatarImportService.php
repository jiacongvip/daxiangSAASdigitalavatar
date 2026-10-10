<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarImportService
{
    public static function getImportPreview(int $aid): array
    {
        $sources = self::loadZhuibaoSources($aid);
        $providerKey = self::resolveProviderKey($aid);
        $avatarProfiles = $sources['avatar_profiles'];
        $voiceProfiles = $sources['voice_profiles'];
        $existingAvatars = self::indexPublicAvatars($aid);
        $existingVoices = self::indexPublicVoices($aid);
        $missingModels = self::missingDefaultModels($aid, $providerKey);

        $avatarNew = 0;
        $avatarUpdate = 0;
        foreach ($avatarProfiles as $profile) {
            $modelId = trim(strval($profile['model_id'] ?? ''));
            if ($modelId === '') {
                continue;
            }
            if (isset($existingAvatars[$modelId])) {
                $avatarUpdate++;
            } else {
                $avatarNew++;
            }
        }

        $voiceNew = 0;
        $voiceUpdate = 0;
        foreach ($voiceProfiles as $profile) {
            $voiceId = trim(strval($profile['voice_id'] ?? ''));
            if ($voiceId === '') {
                continue;
            }
            if (isset($existingVoices[$voiceId])) {
                $voiceUpdate++;
            } else {
                $voiceNew++;
            }
        }

        return [
            'provider_key' => $providerKey,
            'zhuibao_avatar_ready' => intval($sources['avatar_ready']),
            'zhuibao_tts_ready' => intval($sources['tts_ready']),
            'avatar_total' => count($avatarProfiles),
            'avatar_new' => $avatarNew,
            'avatar_update' => $avatarUpdate,
            'voice_total' => count($voiceProfiles),
            'voice_new' => $voiceNew,
            'voice_update' => $voiceUpdate,
            'model_missing' => count($missingModels),
            'model_items' => $missingModels,
            'can_import' => ($avatarNew + $avatarUpdate + $voiceNew + $voiceUpdate + count($missingModels)) > 0,
        ];
    }

    public static function importConfiguredData(int $aid): array
    {
        if ($aid <= 0) {
            throw new \RuntimeException('站点参数错误');
        }
        if (!class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
            throw new \RuntimeException('请先安装并配置追爆插件');
        }

        $sources = self::loadZhuibaoSources($aid);
        if (intval($sources['avatar_ready']) !== 1 && intval($sources['tts_ready']) !== 1) {
            throw new \RuntimeException('请先在追爆插件中配置并启用数字人 / 配音接口');
        }

        return Db::transaction(function () use ($aid, $sources) {
            $providerKey = self::resolveProviderKey($aid);
            $avatarStats = self::importAvatarProfiles($aid, $providerKey, $sources['avatar_profiles']);
            $voiceStats = self::importVoiceProfiles($aid, $providerKey, $sources['voice_profiles'], $sources['tts_model_code']);
            $modelStats = self::ensureDefaultModels($aid, $providerKey);

            return [
                'provider_key' => $providerKey,
                'avatar_inserted' => intval($avatarStats['inserted']),
                'avatar_updated' => intval($avatarStats['updated']),
                'avatar_skipped' => intval($avatarStats['skipped']),
                'voice_inserted' => intval($voiceStats['inserted']),
                'voice_updated' => intval($voiceStats['updated']),
                'voice_skipped' => intval($voiceStats['skipped']),
                'model_inserted' => intval($modelStats['inserted']),
                'model_skipped' => intval($modelStats['skipped']),
            ];
        });
    }

    private static function loadZhuibaoSources(int $aid): array
    {
        $avatarProvider = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getProviderConfigByKey($aid, 'avatar');
        $ttsProvider = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getProviderConfigByKey($aid, 'tts');

        $avatarProfiles = [];
        if (intval($avatarProvider['status'] ?? 0) === 1) {
            $profiles = is_array($avatarProvider['avatar_profiles'] ?? null) ? $avatarProvider['avatar_profiles'] : [];
            foreach ($profiles as $profile) {
                if (!is_array($profile) || trim(strval($profile['model_id'] ?? '')) === '') {
                    continue;
                }
                $avatarProfiles[] = $profile;
            }
        }

        $voiceProfiles = [];
        $ttsModelCode = 'SPro';
        if (intval($ttsProvider['status'] ?? 0) === 1) {
            $profiles = is_array($ttsProvider['voice_profiles'] ?? null) ? $ttsProvider['voice_profiles'] : [];
            foreach ($profiles as $profile) {
                if (!is_array($profile) || trim(strval($profile['voice_id'] ?? '')) === '') {
                    continue;
                }
                $voiceProfiles[] = $profile;
            }
            $serviceCode = trim(strval($ttsProvider['voice_service_code'] ?? ''));
            if ($serviceCode !== '') {
                $spec = DigitalAvatarNiwaCatalog::resolve($serviceCode, [
                    'voice_service_code' => $serviceCode,
                ], ['task_type' => 'audio']);
                $ttsModelCode = trim(strval($spec['voice_service_code'] ?? 'SPro')) ?: 'SPro';
            }
        }

        return [
            'avatar_ready' => intval($avatarProvider['status'] ?? 0) === 1 ? 1 : 0,
            'tts_ready' => intval($ttsProvider['status'] ?? 0) === 1 ? 1 : 0,
            'avatar_profiles' => $avatarProfiles,
            'voice_profiles' => $voiceProfiles,
            'tts_model_code' => $ttsModelCode,
        ];
    }

    private static function resolveProviderKey(int $aid): string
    {
        $rows = Db::name('digitalavatar_provider_config')
            ->where('aid', $aid)
            ->where('status', 1)
            ->order('id asc')
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            $extra = self::decodeJson($row['extra_json'] ?? '');
            $protocol = strtolower(trim(strval($extra['provider_protocol'] ?? '')));
            if ($protocol === 'niwaai') {
                return trim(strval($row['provider_key'] ?? ''));
            }
        }

        foreach ($rows as $row) {
            $providerKey = trim(strval($row['provider_key'] ?? ''));
            if (in_array($providerKey, ['niwaai', 'zhuibao', 'deepsekk', 'deepseek', 'avatar', 'tts'], true)) {
                return $providerKey;
            }
        }

        if (!empty($rows[0]['provider_key'])) {
            return trim(strval($rows[0]['provider_key']));
        }

        throw new \RuntimeException('请先在 AI 数字人工作台配置并启用 NiwaAI 提供商');
    }

    private static function importAvatarProfiles(int $aid, string $providerKey, array $profiles): array
    {
        $existing = self::indexPublicAvatars($aid);
        $videoModelCode = self::resolveTaskModelCode($aid, 'avatar_video', 'VPro');
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $sort = 0;

        foreach ($profiles as $profile) {
            $modelId = trim(strval($profile['model_id'] ?? ''));
            if ($modelId === '') {
                $skipped++;
                continue;
            }

            $name = mb_substr(trim(strval($profile['name'] ?? '')) ?: '平台数字人', 0, 120);
            $previewUrl = trim(strval($profile['sample_url'] ?? ''));
            $config = [
                'model_id' => $modelId,
                'platform_code' => trim(strval($profile['platform_code'] ?? 'VPro')) ?: 'VPro',
                'sex' => intval($profile['sex'] ?? 1),
                'source_local_id' => trim(strval($profile['local_id'] ?? '')),
                'is_default' => intval($profile['is_default'] ?? 0) === 1 ? 1 : 0,
            ];
            $data = [
                'aid' => $aid,
                'name' => $name,
                'cover_url' => $previewUrl,
                'preview_url' => $previewUrl,
                'provider_key' => $providerKey,
                'model_code' => $videoModelCode,
                'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sort' => intval($profile['is_default'] ?? 0) === 1 ? 0 : (++$sort),
                'status' => 1,
                'update_time' => time(),
            ];

            if (isset($existing[$modelId])) {
                unset($data['visible_member_ids']);
                Db::name('digitalavatar_public_avatar')
                    ->where('aid', $aid)
                    ->where('id', intval($existing[$modelId]['id']))
                    ->update($data);
                $updated++;
                continue;
            }

            $data['create_time'] = time();
            $data['visible_member_ids'] = '0';
            Db::name('digitalavatar_public_avatar')->insert($data);
            $inserted++;
        }

        return compact('inserted', 'updated', 'skipped');
    }

    private static function importVoiceProfiles(int $aid, string $providerKey, array $profiles, string $ttsModelCode): array
    {
        $existing = self::indexPublicVoices($aid);
        $audioModelCode = self::resolveTaskModelCode($aid, 'audio', $ttsModelCode);
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $sort = 0;

        foreach ($profiles as $profile) {
            $voiceId = trim(strval($profile['voice_id'] ?? ''));
            if ($voiceId === '') {
                $skipped++;
                continue;
            }

            $name = mb_substr(trim(strval($profile['name'] ?? '')) ?: '平台声音', 0, 120);
            $previewUrl = trim(strval($profile['sample_url'] ?? ''));
            $config = [
                'voice_id' => $voiceId,
                'sex' => intval($profile['sex'] ?? 0),
                'language' => trim(strval($profile['language'] ?? 'zh')) ?: 'zh',
                'instruction' => trim(strval($profile['instruction'] ?? '请用自然平稳的语气说话。')) ?: '请用自然平稳的语气说话。',
                'voice_service_code' => $audioModelCode,
                'source_local_id' => trim(strval($profile['local_id'] ?? '')),
                'is_default' => intval($profile['is_default'] ?? 0) === 1 ? 1 : 0,
                'model_type' => 1,
            ];
            $data = [
                'aid' => $aid,
                'name' => $name,
                'preview_url' => $previewUrl,
                'provider_key' => $providerKey,
                'model_code' => $audioModelCode,
                'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sort' => intval($profile['is_default'] ?? 0) === 1 ? 0 : (++$sort),
                'status' => 1,
                'update_time' => time(),
            ];

            if (isset($existing[$voiceId])) {
                unset($data['visible_member_ids']);
                Db::name('digitalavatar_public_voice')
                    ->where('aid', $aid)
                    ->where('id', intval($existing[$voiceId]['id']))
                    ->update($data);
                $updated++;
                continue;
            }

            $data['create_time'] = time();
            $data['visible_member_ids'] = '0';
            Db::name('digitalavatar_public_voice')->insert($data);
            $inserted++;
        }

        return compact('inserted', 'updated', 'skipped');
    }

    private static function ensureDefaultModels(int $aid, string $providerKey): array
    {
        $missing = self::missingDefaultModels($aid, $providerKey);
        DigitalAvatarModelService::syncOfficialNiwaModels($aid);
        $stillMissing = self::missingDefaultModels($aid, $providerKey);
        return [
            'inserted' => max(0, count($missing) - count($stillMissing)),
            'skipped' => count(DigitalAvatarNiwaCatalog::seedTemplates($providerKey)) - count($missing),
        ];
    }

    private static function missingDefaultModels(int $aid, string $providerKey): array
    {
        $missing = [];
        foreach (DigitalAvatarNiwaCatalog::seedTemplates($providerKey) as $template) {
            $exists = Db::name('digitalavatar_model')
                ->where('aid', $aid)
                ->where('task_type', strval($template['task_type']))
                ->where('model_code', strval($template['model_code']))
                ->find();
            if (!$exists) {
                $missing[] = [
                    'task_type' => strval($template['task_type']),
                    'model_code' => strval($template['model_code']),
                    'title' => strval($template['title']),
                ];
            }
        }
        return $missing;
    }

    private static function resolveTaskModelCode(int $aid, string $taskType, string $fallback): string
    {
        $model = Db::name('digitalavatar_model')
            ->where('aid', $aid)
            ->where('task_type', $taskType)
            ->where('status', 1)
            ->order('is_default desc,sort asc,id asc')
            ->find();
        if ($model && trim(strval($model['model_code'] ?? '')) !== '') {
            return trim(strval($model['model_code']));
        }
        return $fallback;
    }

    private static function indexPublicAvatars(int $aid): array
    {
        $result = [];
        $rows = Db::name('digitalavatar_public_avatar')->where('aid', $aid)->select()->toArray();
        foreach ($rows as $row) {
            $config = self::decodeJson($row['config_json'] ?? '');
            $modelId = trim(strval($config['model_id'] ?? ''));
            if ($modelId !== '') {
                $result[$modelId] = $row;
            }
        }
        return $result;
    }

    private static function indexPublicVoices(int $aid): array
    {
        $result = [];
        $rows = Db::name('digitalavatar_public_voice')->where('aid', $aid)->select()->toArray();
        foreach ($rows as $row) {
            $config = self::decodeJson($row['config_json'] ?? '');
            $voiceId = trim(strval($config['voice_id'] ?? ''));
            if ($voiceId !== '') {
                $result[$voiceId] = $row;
            }
        }
        return $result;
    }

    private static function normalizeModelCode(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._:-]+/', '-', $value) ?: '';
        if ($value === '' || !preg_match('/^[a-z0-9][a-z0-9._:-]{0,119}$/', $value)) {
            return $fallback;
        }
        return $value;
    }

    private static function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }
}
