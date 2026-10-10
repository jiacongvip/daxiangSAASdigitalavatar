<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarService
{
    public static function getBootstrap(int $aid, int $bid, int $memberId = 0): array
    {
        $settings = DigitalAvatarCreditService::ensureSettings($aid);
        $activeAds = DigitalAvatarAdService::list($aid, true);
        $draftProfile = DigitalAvatarImageAgentService::imageStageProfile($aid, 'draft', $settings);
        $hdProfile = DigitalAvatarImageAgentService::imageStageProfile($aid, 'final', $settings);
        $data = [
            'product' => [
                'title' => strval($settings['product_title'] ?? 'AI数字人工作台'),
                'notice_text' => strval($settings['notice_text'] ?? ''),
                'enabled' => intval($settings['enabled'] ?? 1),
                'creative_video_enabled' => intval($settings['creative_video_enabled'] ?? 1),
                'avatar_enabled' => intval($settings['avatar_enabled'] ?? 1),
                'audio_enabled' => intval($settings['audio_enabled'] ?? 1),
                'image_enabled' => intval($settings['image_enabled'] ?? 1),
                'image_agent_enabled' => intval($settings['image_agent_enabled'] ?? 0),
                'image_agent_llm_ready' => DigitalAvatarImageAgentService::isLlmReady($aid) ? 1 : 0,
                'image_agent_prefer_draft' => DigitalAvatarImageAgentService::preferDraft($aid, $settings) ? 1 : 0,
                'image_agent_draft_ready' => intval($draftProfile['ready'] ?? 0),
                'image_agent_hd_ready' => intval($hdProfile['ready'] ?? 0),
                'image_agent_draft_size' => strval($draftProfile['size'] ?? '1024x1024'),
                'image_agent_draft_quality' => strval($draftProfile['quality'] ?? 'low'),
                'image_agent_hd_size' => strval($hdProfile['size'] ?? '1024x1024'),
                'image_agent_hd_quality' => strval($hdProfile['quality'] ?? 'high'),
            ],
            'oem' => DigitalAvatarCommerceService::formatOem($settings),
            'portal' => [
                'path' => strval($settings['portal_path'] ?? '/portal/digitalavatar/'),
                'route_mode' => 'hash',
            ],
            'models' => DigitalAvatarModelService::getPortalOptions($aid),
            'image_agent_skills' => DigitalAvatarImageSkillService::portalList($aid),
            'sidebar' => DigitalAvatarSidebarService::portalConfig($aid),
            'ads' => array_values(array_filter($activeAds, function ($ad) {
                return strval($ad['placement'] ?? 'carousel') === 'carousel';
            })),
            'side_ad' => self::firstSideAd($activeAds),
            'public_avatars' => self::getPublicAvatars($aid, 1, 20, $memberId)['list'],
            'public_voices' => self::getPublicVoices($aid, 1, 20, $memberId)['list'],
            'member' => null,
            'credit' => null,
            'checkin' => null,
            'invite' => null,
        ];
        try {
            $data['checkin'] = DigitalAvatarCheckinService::memberInfo($aid, $bid, $memberId);
        } catch (\Throwable $e) {
            $data['checkin'] = ['enabled' => 0];
        }
        try {
            $data['invite'] = DigitalAvatarInviteService::memberInfo($aid, $bid, $memberId);
        } catch (\Throwable $e) {
            $data['invite'] = ['enabled' => 0];
        }

        if ($memberId > 0) {
            $member = Db::name('member')
                ->where('aid', $aid)
                ->where('id', $memberId)
                ->field('id,nickname,tel,headimg')
                ->find();
            if ($member) {
                $data['member'] = [
                    'id' => intval($member['id']),
                    'nickname' => strval($member['nickname'] ?? ''),
                    'tel' => strval($member['tel'] ?? ''),
                    'headimg' => strval($member['headimg'] ?? ''),
                ];
                $data['credit'] = DigitalAvatarCreditService::getSummary($aid, $bid, $memberId);
            }
        }

        return $data;
    }

    public static function previewAudio(int $aid, int $bid, int $memberId, array $input): array
    {
        $settings = DigitalAvatarCreditService::ensureSettings($aid);
        if (intval($settings['enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('AI数字人功能暂未开放');
        }
        if (intval($settings['audio_enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('音频功能暂未开放');
        }
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }

        $text = trim(strval($input['text'] ?? ($input['content'] ?? ($input['prompt'] ?? ($input['script'] ?? '')))));
        if ($text === '') {
            throw new \RuntimeException('请先填写口播文案再试听');
        }
        $input['text'] = $text;
        $input['content'] = $text;

        $voice = self::resolveVoiceForSynthesis($aid, $bid, $memberId, $input);
        $resolvedVoiceId = trim(strval($voice['voice_id'] ?? ''));
        if ($resolvedVoiceId === '') {
            throw new \RuntimeException('请先选择音色');
        }
        $input['resolved_voice_id'] = $resolvedVoiceId;
        $input['selected_voice_id'] = $resolvedVoiceId;
        $audioModelCode = self::audioModelCodeForVoice($voice, trim(strval($input['model_code'] ?? 'SPro')));
        $input['resolved_voice_model_code'] = $audioModelCode;
        $input['model_code'] = $audioModelCode;

        $cost = DigitalAvatarCreditService::computeCost($aid, 'audio', $audioModelCode, $input);
        $creditCost = DigitalAvatarCreditService::creditAmount($cost['credit_cost'] ?? 0);
        if (intval($settings['charge_mode'] ?? 2) === 1) {
            $creditCost = 0;
        }
        if ($creditCost > 0) {
            $summary = DigitalAvatarCreditService::getSummary($aid, $bid, $memberId);
            if (intval($summary['has_access'] ?? 0) !== 1) {
                throw new \RuntimeException('当前额度不可用，请先充值或兑换');
            }
            if (DigitalAvatarCreditService::creditLt($summary['credit_available'] ?? 0, $creditCost)) {
                throw new \RuntimeException('创作点不足，请先购买套餐');
            }
        }

        @set_time_limit(90);
        $result = DigitalAvatarProviderService::synthesizeVoiceNow($aid, $bid, $memberId, $input);

        $audioUrl = trim(strval($result['audio_url'] ?? ($result['url'] ?? '')));
        $result['asset_id'] = 0;
        $result['task_id'] = 0;
        if ($audioUrl !== '') {
            try {
                $persisted = DigitalAvatarTaskService::persistPreviewAudio(
                    $aid,
                    $bid,
                    $memberId,
                    $input,
                    $result,
                    $creditCost,
                    is_array($cost) ? $cost : []
                );
                $asset = is_array($persisted['asset'] ?? null) ? $persisted['asset'] : [];
                $task = is_array($persisted['task'] ?? null) ? $persisted['task'] : [];
                $result['asset_id'] = intval($persisted['asset_id'] ?? ($asset['id'] ?? 0));
                $result['asset'] = $asset;
                $result['task_id'] = intval($persisted['task_id'] ?? ($task['id'] ?? 0));
                $result['task'] = $task;
            } catch (\Throwable $e) {
                \think\facade\Log::error('digitalavatar preview audio save failed: ' . $e->getMessage());
                // Fallback: keep legacy asset-only save so 历史音频 still works if task insert fails.
                try {
                    $voiceName = trim(strval($result['voice_name'] ?? ''));
                    $textSnippet = mb_substr($text, 0, 24);
                    $assetName = $voiceName !== '' ? ($voiceName . ' · 试听') : '试听音频';
                    if ($textSnippet !== '') {
                        $assetName = mb_substr($assetName . ' · ' . $textSnippet, 0, 191);
                    }
                    $asset = DigitalAvatarTaskService::saveAsset($aid, $memberId, [
                        'bid' => max(0, $bid),
                        'task_id' => 0,
                        'asset_type' => 'audio',
                        'name' => $assetName,
                        'url' => $audioUrl,
                        'duration' => floatval($result['duration'] ?? 0),
                        'metadata' => [
                            'source' => 'preview',
                            'task_type' => 'audio',
                            'voice_id' => strval($result['voice_id'] ?? ''),
                            'voice_name' => $voiceName,
                            'preview_text' => $text,
                            'model_code' => strval($result['model_code'] ?? ''),
                            'audio_id' => strval($result['audio_id'] ?? ''),
                        ],
                    ]);
                    $result['asset_id'] = intval($asset['id'] ?? 0);
                    $result['asset'] = $asset;
                } catch (\Throwable $assetError) {
                    $result['asset_id'] = 0;
                    $result['asset_save_error'] = $assetError->getMessage();
                }
                $result['task_id'] = 0;
                $result['task_save_error'] = $e->getMessage();
            }
        }

        if ($creditCost > 0) {
            $previewMode = DigitalAvatarCreditService::normalizeBillingMode(strval($cost['billing_mode'] ?? 'chars'));
            $previewQuantity = floatval($cost['quantity'] ?? 0);
            $sourceId = intval($result['task_id'] ?? 0);
            if ($sourceId <= 0) {
                $sourceId = intval($result['asset_id'] ?? 0);
            }
            DigitalAvatarCreditService::grant($aid, $bid, $memberId, -$creditCost, '试听配音', 0, [
                'source_type' => 'preview',
                'source_id' => $sourceId,
                'task_type' => 'preview',
                'change_type' => 'deduct',
                'frozen_amount' => 0,
                'model_code' => $audioModelCode,
                'platform_code' => strval($cost['platform_code'] ?? $audioModelCode),
                'api_model' => strval($cost['api_model'] ?? ''),
                'model' => strval($cost['api_model'] ?? ''),
                'billing_mode' => $previewMode,
                'billing_chars' => $previewMode === 'chars' && $previewQuantity > 0 ? strval(intval($previewQuantity)) : '',
                'billing_seconds' => in_array($previewMode, ['seconds', 'minutes'], true) && $previewQuantity > 0
                    ? strval($previewMode === 'minutes' ? ($previewQuantity * 60) : $previewQuantity)
                    : '',
                'quantity' => $previewQuantity,
                'preview_text' => $text,
                'task_id' => intval($result['task_id'] ?? 0),
            ]);
        }

        $result['credit_cost'] = $creditCost;
        $result['credit'] = DigitalAvatarCreditService::getSummary($aid, $bid, $memberId);
        return $result;
    }

    public static function getWorkbench(int $aid, int $bid, int $memberId): array
    {
        $taskQuery = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('deleted_time', 0);
        $assetQuery = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('delete_time', 0);

        return [
            'summary' => [
                'task_total' => intval((clone $taskQuery)->count()),
                'pending_task_total' => intval((clone $taskQuery)->where('status', 0)->count()),
                'running_task_total' => intval((clone $taskQuery)->where('status', 1)->count()),
                'success_task_total' => intval((clone $taskQuery)->where('status', 2)->count()),
                'failed_task_total' => intval((clone $taskQuery)->where('status', 3)->count()),
                'asset_total' => intval((clone $assetQuery)->count()),
                'avatar_total' => intval(Db::name('digitalavatar_member_avatar')->where('aid', $aid)->where('bid', max(0, $bid))->where('member_id', $memberId)->where('status', 1)->count()),
                'voice_total' => intval(Db::name('digitalavatar_member_voice')->where('aid', $aid)->where('bid', max(0, $bid))->where('member_id', $memberId)->where('status', 1)->count()),
            ],
            'credit' => DigitalAvatarCreditService::getSummary($aid, $bid, $memberId),
            'recent_tasks' => array_map(
                [DigitalAvatarTaskService::class, 'formatTask'],
                (clone $taskQuery)->order('id desc')->limit(8)->select()->toArray()
            ),
            'recent_assets' => (clone $assetQuery)->order('id desc')->limit(8)->select()->toArray(),
            'quick_actions' => [
                ['key' => 'avatar_video', 'title' => '生成数字人视频', 'route' => '#/videos'],
                ['key' => 'audio', 'title' => '合成一段语音', 'route' => '#/voices'],
                ['key' => 'image', 'title' => '创建一张图片', 'route' => '#/images'],
                ['key' => 'tasks', 'title' => '查看任务进度', 'route' => '#/tasks'],
            ],
            'image_agent_skills' => DigitalAvatarImageSkillService::portalList($aid),
        ];
    }

    public static function getPublicAvatars(int $aid, int $page = 1, int $limit = 20, int $memberId = 0): array
    {
        $query = Db::name('digitalavatar_public_avatar')->where('aid', $aid)->where('status', 1);
        self::applyPublicAvatarVisibility($query, $memberId);
        $result = self::paginateSimple(
            $query,
            $page,
            $limit,
            ['id', 'aid', 'name', 'cover_url', 'preview_url', 'provider_key', 'model_code', 'config_json', 'visible_member_ids', 'sort', 'status']
        );
        $result['list'] = array_values(array_filter(array_map(function ($row) use ($aid, $memberId) {
            if (is_array($row) && intval($row['aid'] ?? 0) <= 0) {
                $row['aid'] = $aid;
            }
            $formatted = self::formatPublicAvatar(is_array($row) ? $row : []);
            return self::isPublicAvatarVisibleToMember($formatted, $memberId) ? $formatted : null;
        }, $result['list'])));

        // 追爆插件维护的 NiwaAI 平台资源作为公共资源复用，避免两个插件各维护一套数字人。
        if ($page === 1) {
            $seenIds = [];
            foreach (Db::name('digitalavatar_public_avatar')->where('aid', $aid)->where('status', 1)->select()->toArray() as $owned) {
                $ownedFormatted = self::formatPublicAvatar(is_array($owned) ? $owned : []);
                $resourceId = trim(strval($ownedFormatted['resource_id'] ?? ($ownedFormatted['model_id'] ?? '')));
                if ($resourceId !== '') {
                    $seenIds[$resourceId] = true;
                }
            }
            foreach ($result['list'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $resourceId = trim(strval($item['resource_id'] ?? ($item['model_id'] ?? '')));
                if ($resourceId !== '') {
                    $seenIds[$resourceId] = true;
                }
            }
            foreach (self::getZhuibaoPlatformAvatars($aid) as $item) {
                if (!is_array($item) || !self::isPublicAvatarVisibleToMember($item, $memberId)) {
                    continue;
                }
                $resourceId = trim(strval($item['resource_id'] ?? ($item['model_id'] ?? '')));
                if ($resourceId !== '' && isset($seenIds[$resourceId])) {
                    continue;
                }
                if ($resourceId !== '') {
                    $seenIds[$resourceId] = true;
                }
                $result['list'][] = $item;
            }
            $result['total'] = count($result['list']);
        }
        return $result;
    }

    public static function getPublicVoices(int $aid, int $page = 1, int $limit = 20, int $memberId = 0): array
    {
        $query = Db::name('digitalavatar_public_voice')->where('aid', $aid)->where('status', 1);
        self::applyPublicAvatarVisibility($query, $memberId);
        $result = self::paginateSimple(
            $query,
            $page,
            $limit,
            ['id', 'aid', 'name', 'preview_url', 'provider_key', 'model_code', 'config_json', 'visible_member_ids', 'sort', 'status']
        );
        $result['list'] = array_values(array_filter(array_map(function ($row) use ($aid, $memberId) {
            if (is_array($row) && intval($row['aid'] ?? 0) <= 0) {
                $row['aid'] = $aid;
            }
            $formatted = self::formatPublicVoice(is_array($row) ? $row : []);
            return self::isPublicAvatarVisibleToMember($formatted, $memberId) ? $formatted : null;
        }, $result['list'])));

        if ($page === 1) {
            $seenIds = [];
            foreach (Db::name('digitalavatar_public_voice')->where('aid', $aid)->where('status', 1)->select()->toArray() as $owned) {
                $ownedFormatted = self::formatPublicVoice(is_array($owned) ? $owned : []);
                $resourceId = trim(strval($ownedFormatted['resource_id'] ?? ($ownedFormatted['voice_id'] ?? '')));
                if ($resourceId !== '') {
                    $seenIds[$resourceId] = true;
                }
            }
            foreach ($result['list'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $resourceId = trim(strval($item['resource_id'] ?? ($item['voice_id'] ?? '')));
                if ($resourceId !== '') {
                    $seenIds[$resourceId] = true;
                }
            }
            foreach (self::getZhuibaoPlatformVoices($aid) as $item) {
                if (!is_array($item) || !self::isPublicAvatarVisibleToMember($item, $memberId)) {
                    continue;
                }
                $resourceId = trim(strval($item['resource_id'] ?? ($item['voice_id'] ?? '')));
                if ($resourceId !== '' && isset($seenIds[$resourceId])) {
                    continue;
                }
                if ($resourceId !== '') {
                    $seenIds[$resourceId] = true;
                }
                $result['list'][] = $item;
            }
            $result['total'] = count($result['list']);
        }
        return $result;
    }

    public static function getMemberAvatars(int $aid, int $bid, int $memberId): array
    {
        $rows = Db::name('digitalavatar_member_avatar')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->order('id desc')
            ->select()
            ->toArray();
        $result = array_map([self::class, 'formatMemberAvatar'], $rows);
        try {
            if (class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
                $profiles = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getMemberAvatarProfiles(
                    $aid,
                    $memberId,
                    $bid,
                    false
                );
                $result = self::mergeZhuibaoMemberResources($result, $profiles, 'avatar', $aid);
            }
        } catch (\Throwable $e) {
            // 追爆表尚未安装时保留数字人插件自己的资源，不影响工作台使用。
        }
        return $result;
    }

    public static function getMemberVoices(int $aid, int $bid, int $memberId): array
    {
        $rows = Db::name('digitalavatar_member_voice')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->order('id desc')
            ->select()
            ->toArray();
        $result = array_map([self::class, 'formatMemberVoice'], $rows);
        try {
            if (class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
                $profiles = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getMemberVoiceProfiles(
                    $aid,
                    $memberId,
                    $bid,
                    false
                );
                $result = self::mergeZhuibaoMemberResources($result, $profiles, 'voice', $aid);
            }
        } catch (\Throwable $e) {
            // 追爆表尚未安装时保留数字人插件自己的资源，不影响工作台使用。
        }
        return $result;
    }

    /**
     * 根据前端提交的本地ID或厂商资源ID解析可用数字人。
     * 返回值只包含任务需要的业务字段，不把提供商密钥暴露给前端。
     */
    public static function resolveAvatarSelection(int $aid, int $bid, int $memberId, array $input): array
    {
        $selectedLocalId = trim(strval($input['selected_avatar_local_id'] ?? ''));
        $selectedModelId = trim(strval($input['selected_avatar_model_id'] ?? ($input['avatar_model_id'] ?? '')));
        $selectedId = intval($input['avatar_id'] ?? 0);
        $publicId = self::idFromPrefixedLocalId($selectedLocalId, 'digitalavatar_public_avatar_');
        $memberLocalId = self::idFromPrefixedLocalId($selectedLocalId, 'digitalavatar_member_avatar_');
        $zhuibaoLocalId = self::idFromPrefixedLocalId($selectedLocalId, 'member_avatar_');

        if ($publicId > 0) {
            $publicRow = Db::name('digitalavatar_public_avatar')
                ->where('aid', $aid)
                ->where('status', 1)
                ->where('id', $publicId)
                ->find();
            if ($publicRow) {
                $formatted = self::formatPublicAvatar($publicRow);
                if (self::isPublicAvatarVisibleToMember($formatted, $memberId) && self::avatarIsUsable($formatted)) {
                    return $formatted;
                }
            }
        }

        if ($memberLocalId > 0) {
            $row = self::findMemberAvatarRow($aid, $bid, $memberId, $memberLocalId);
            if ($row) {
                $formatted = self::formatMemberAvatar($row);
                if (self::avatarIsUsable($formatted)) {
                    return $formatted;
                }
            }
        }

        try {
            if (class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
                $profiles = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getMemberAvatarProfiles(
                    $aid,
                    $memberId,
                    $bid,
                    true
                );
                $matched = self::matchResourceProfile(
                    $profiles,
                    $selectedLocalId,
                    $selectedModelId,
                    $zhuibaoLocalId > 0 ? $zhuibaoLocalId : 0,
                    'model_id'
                );
                if (!empty($matched)) {
                    $formatted = self::formatZhuibaoAvatar($matched, strval($matched['owner_type'] ?? '') === 'member', $aid);
                    if (self::avatarVendorId($formatted) !== '' && self::isOfficialAvatarAllowedForMember($aid, $memberId, $formatted)) {
                        return $formatted;
                    }
                }
                if ($selectedLocalId === '' && $selectedModelId === '' && $selectedId <= 0) {
                    foreach ($profiles as $profile) {
                        if (!is_array($profile) || trim(strval($profile['model_id'] ?? '')) === '') {
                            continue;
                        }
                        $formatted = self::formatZhuibaoAvatar($profile, strval($profile['owner_type'] ?? '') === 'member', $aid);
                        if (self::isOfficialAvatarAllowedForMember($aid, $memberId, $formatted)) {
                            return $formatted;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // 追爆资源不可用时继续尝试数字人插件自己的资源。
        }

        if ($publicId <= 0 && $zhuibaoLocalId <= 0 && $selectedModelId !== '') {
            $publicRows = Db::name('digitalavatar_public_avatar')
                ->where('aid', $aid)
                ->where('status', 1)
                ->order('sort asc,id desc')
                ->select()
                ->toArray();
            foreach ($publicRows as $publicRow) {
                $formatted = self::formatPublicAvatar($publicRow);
                if ($formatted['model_id'] === $selectedModelId && self::isPublicAvatarVisibleToMember($formatted, $memberId)) {
                    return $formatted;
                }
            }
        }

        if ($publicId <= 0 && $zhuibaoLocalId <= 0 && $selectedId > 0) {
            $row = self::findMemberAvatarRow($aid, $bid, $memberId, $selectedId);
            if ($row) {
                $formatted = self::formatMemberAvatar($row);
                if (self::avatarIsUsable($formatted) && ($selectedModelId === '' || $formatted['model_id'] === $selectedModelId || self::isXproAvatar($formatted))) {
                    return $formatted;
                }
            }
        }

        $rows = Db::name('digitalavatar_member_avatar')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->order('id desc')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $formatted = self::formatMemberAvatar($row);
            if ($selectedModelId !== '' && $formatted['model_id'] === $selectedModelId) {
                return $formatted;
            }
        }
        $readyMember = self::pickReadyMemberAvatar($aid, $bid, $memberId);
        if ($readyMember) {
            return $readyMember;
        }
        return self::pickReadyPublicAvatar($aid, $memberId);
    }

    public static function resolveVoiceSelection(int $aid, int $bid, int $memberId, array $input): array
    {
        $selectedLocalId = trim(strval($input['selected_voice_local_id'] ?? ''));
        $selectedVoiceId = trim(strval($input['selected_voice_id'] ?? ($input['voice_resource_id'] ?? '')));
        $selectedId = intval($input['voice_id'] ?? 0);
        $publicId = self::idFromPrefixedLocalId($selectedLocalId, 'digitalavatar_public_voice_');
        $memberLocalId = self::idFromPrefixedLocalId($selectedLocalId, 'digitalavatar_member_voice_');
        $zhuibaoLocalId = self::idFromPrefixedLocalId($selectedLocalId, 'member_voice_');

        $publicQuery = Db::name('digitalavatar_public_voice')
            ->where('aid', $aid)
            ->where('status', 1);

        if ($publicId > 0) {
            $publicRow = (clone $publicQuery)->where('id', $publicId)->find();
            if ($publicRow) {
                $formatted = self::formatPublicVoice($publicRow);
                if (self::isOfficialVoiceAllowedForMember($aid, $memberId, $formatted)) {
                    return $formatted;
                }
            }
        }

        if ($memberLocalId > 0) {
            $row = self::findMemberVoiceRow($aid, $bid, $memberId, $memberLocalId);
            if ($row) {
                return self::formatMemberVoice($row);
            }
        }

        try {
            if (class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
                $profiles = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getMemberVoiceProfiles(
                    $aid,
                    $memberId,
                    $bid,
                    false
                );
                $matched = self::matchResourceProfile(
                    $profiles,
                    $selectedLocalId,
                    $selectedVoiceId,
                    $zhuibaoLocalId > 0 ? $zhuibaoLocalId : 0,
                    'voice_id'
                );
                if (!empty($matched)) {
                    $isMember = strval($matched['owner_type'] ?? '') === 'member';
                    $formatted = self::formatZhuibaoVoice($matched, $isMember, $aid);
                    if ($isMember || self::isOfficialVoiceAllowedForMember($aid, $memberId, $formatted)) {
                        return $formatted;
                    }
                }
            }
        } catch (\Throwable $e) {
            // 追爆资源不可用时继续尝试数字人插件自己的资源。
        }

        if ($memberLocalId <= 0 && $zhuibaoLocalId <= 0 && $selectedId > 0) {
            $publicRow = (clone $publicQuery)->where('id', $selectedId)->find();
            if ($publicRow) {
                $formatted = self::formatPublicVoice($publicRow);
                if (self::isOfficialVoiceAllowedForMember($aid, $memberId, $formatted)) {
                    return $formatted;
                }
            }
        }
        if ($memberLocalId <= 0 && $zhuibaoLocalId <= 0 && $selectedVoiceId !== '') {
            $publicRows = (clone $publicQuery)->order('sort asc,id desc')->select()->toArray();
            foreach ($publicRows as $publicRow) {
                $formatted = self::formatPublicVoice($publicRow);
                if ($formatted['voice_id'] === $selectedVoiceId && self::isOfficialVoiceAllowedForMember($aid, $memberId, $formatted)) {
                    return $formatted;
                }
            }
        }

        if ($publicId <= 0 && $zhuibaoLocalId <= 0 && $selectedId > 0) {
            $row = self::findMemberVoiceRow($aid, $bid, $memberId, $selectedId);
            if ($row) {
                $formatted = self::formatMemberVoice($row);
                if ($selectedVoiceId === '' || $formatted['voice_id'] === $selectedVoiceId) {
                    return $formatted;
                }
            }
        }

        $rows = Db::name('digitalavatar_member_voice')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->order('id desc')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $formatted = self::formatMemberVoice($row);
            if ($selectedVoiceId !== '' && $formatted['voice_id'] === $selectedVoiceId) {
                return $formatted;
            }
        }
        return [];
    }

    public static function resolveVoiceForSynthesis(int $aid, int $bid, int $memberId, array $input): array
    {
        $profile = self::resolveVoiceSelection($aid, $bid, $memberId, $input);
        $profile = is_array($profile) ? $profile : [];
        $profileVoiceId = trim(strval($profile['voice_id'] ?? ''));
        $localVoiceId = trim(strval($input['voice_id'] ?? ''));
        $memberLocalId = self::idFromPrefixedLocalId(
            trim(strval($input['selected_voice_local_id'] ?? '')),
            'digitalavatar_member_voice_'
        );
        $candidates = [];
        foreach (['resolved_voice_id', 'selected_voice_id', 'voice_resource_id'] as $key) {
            $value = trim(strval($input[$key] ?? ''));
            if ($value === '' || in_array($value, $candidates, true)) {
                continue;
            }
            $candidates[] = $value;
        }

        foreach ($candidates as $candidate) {
            if ($profileVoiceId !== '' && $candidate === $profileVoiceId) {
                return $profile;
            }
            // 前端会同时传本地表 id（如 4）和厂商 voice_id（如 4151）。
            // 不能把本地 id 当成厂商音色 ID 覆盖，否则会合成成别的声音。
            if ($candidate === $localVoiceId || ($memberLocalId > 0 && $candidate === strval($memberLocalId))) {
                continue;
            }
            $matched = self::findVoiceProfileByVendorId($aid, $bid, $memberId, $candidate);
            if (!empty($matched)) {
                return $matched;
            }
        }

        return $profile;
    }

    public static function audioModelCodeForVoice(array $profile, string $fallback = ''): string
    {
        $config = self::decodeJsonValue($profile['config_json'] ?? []);
        $providerResult = is_array($config['provider_result'] ?? null) ? $config['provider_result'] : [];
        $serviceName = strtolower(trim(strval(
            $profile['voice_service_name']
            ?? ($config['voice_service_name'] ?? ($providerResult['voice_service_name'] ?? ''))
        )));
        $serviceCode = strtolower(trim(strval(
            $profile['voice_service_code']
            ?? ($config['voice_service_code'] ?? ($providerResult['voice_service_code'] ?? ''))
        )));
        $modelCode = trim(strval($profile['model_code'] ?? ($config['model_code'] ?? '')));

        if (strpos($serviceName, 'nwmax') !== false || strpos($serviceName, 'aliminimax') !== false || $serviceCode === 'nwmax') {
            return 'NWMax';
        }
        if (strpos($serviceName, 'minimax') !== false || strpos($serviceName, 'hailuo') !== false || strpos($serviceName, '海螺') !== false
            || in_array($serviceCode, ['minimax', 'hailuo', 'max'], true) || strtoupper($modelCode) === 'MAX') {
            return 'Max';
        }
        if (strpos($serviceName, 'spro') !== false || strpos($serviceName, '方言') !== false || strpos($serviceName, 'cosyvoice') !== false || $serviceCode === 'spro') {
            return 'SPro';
        }
        if (strpos($serviceName, '专业') !== false || in_array($serviceCode, ['niwaai', 'pro'], true)) {
            return 'Pro';
        }
        if ($modelCode !== '') {
            $mapped = DigitalAvatarNiwaCatalog::resolve($modelCode, $config, ['task_type' => 'audio']);
            if (trim(strval($mapped['voice_service_code'] ?? '')) !== '') {
                return strval($mapped['voice_service_code']);
            }
            return $modelCode;
        }
        return trim($fallback) !== '' ? trim($fallback) : 'SPro';
    }

    public static function saveMemberAvatarFromTask(int $aid, int $bid, int $memberId, array $task, array $result): array
    {
        $taskId = intval($task['id'] ?? 0);
        if ($taskId <= 0 || $memberId <= 0) {
            throw new \RuntimeException('克隆任务信息无效');
        }

        $existing = Db::name('digitalavatar_member_avatar')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('config_json', 'like', '%"source_task_id":' . $taskId . '%')
            ->find();
        if ($existing) {
            return $existing;
        }

        $input = self::decodeJsonValue($task['input_json'] ?? []);
        $data = self::decodeJsonValue($result['data'] ?? []);
        $resourceId = self::firstValue($result, $data, [
            'profile_id',
            'model_id',
            'avatar_id',
            'resource_id',
            'remote_id',
        ]);
        $avatarUrl = self::firstValue($result, $data, [
            'avatar_url',
            'preview_url',
            'url',
        ]);
        $sampleUrl = trim(strval($input['sample_url'] ?? ''));
        $coverFromInput = trim(strval($input['cover_url'] ?? ''));
        $coverUrl = self::pickImageUrl([
            $coverFromInput,
            self::firstValue($result, $data, ['poster', 'thumbnail', 'thumb_url', 'cover_url']),
            $avatarUrl,
        ]);
        $previewUrl = self::pickVideoUrl([$sampleUrl, $avatarUrl]) ?: $sampleUrl;
        $modelCode = trim(strval($task['model_code'] ?? ($input['model_code'] ?? '')));
        $platformCode = trim(strval($input['platform_code'] ?? ($data['platform_code'] ?? '')));
        if ($platformCode === '' && class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoNiwaModelCatalog')) {
            $spec = \app\plugin\zhuibaoagent\common\ZhuibaoNiwaModelCatalog::resolve(
                $modelCode,
                is_array($input) ? $input : [],
                ['task_type' => 'avatar_clone']
            );
            $platformCode = trim(strval($spec['platform_code'] ?? ''));
        }
        $isLocalImageClone = self::isLocalImageAvatarClone($modelCode, $input)
            || !empty($result['local_only'])
            || !empty($data['local_only']);
        if ($isLocalImageClone) {
            $platformCode = 'XPro';
            $imageUrl = self::pickImageUrl([
                $coverFromInput,
                $sampleUrl,
                $avatarUrl,
                $result['cover_url'] ?? '',
                $result['url'] ?? '',
                $data['cover_url'] ?? '',
                $data['avatar_url'] ?? '',
            ]);
            if ($imageUrl === '') {
                throw new \RuntimeException('XPro 图片形象缺少可用图片地址');
            }
            $coverUrl = $imageUrl;
            $previewUrl = $imageUrl;
            $avatarUrl = $imageUrl;
            $resourceId = '';
        }
        $input['platform_code'] = $platformCode;
        $input['model_code'] = $modelCode;
        $config = [
            'source_task_id' => $taskId,
            'sample_url' => $sampleUrl,
            'cover_url' => $coverUrl,
            'cover_source' => ($coverFromInput !== '' || $isLocalImageClone) ? 'member' : 'auto',
            'model_id' => $resourceId,
            'model_code' => $modelCode,
            'platform_code' => $platformCode,
            'local_only' => $isLocalImageClone ? 1 : 0,
            'remote_id' => trim(strval($result['remote_id'] ?? '')),
            'provider_result' => $data,
        ];
        $now = time();
        $id = intval(Db::name('digitalavatar_member_avatar')->insertGetId([
            'aid' => $aid,
            'bid' => max(0, $bid),
            'member_id' => $memberId,
            'name' => mb_substr(
                trim(strval($input['name'] ?? ($input['avatar_name'] ?? '')))
                    ?: trim(strval($task['title'] ?? '我的数字人')),
                0,
                120
            ),
            'avatar_url' => trim(strval($avatarUrl)),
            'cover_url' => mb_strlen($coverUrl) <= 500 ? $coverUrl : '',
            'preview_url' => $previewUrl,
            'provider_key' => trim(strval($task['provider_key'] ?? '')),
            'model_code' => $modelCode,
            'status' => 1,
            'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'create_time' => $now,
            'update_time' => $now,
        ]));

        self::syncZhuibaoAvatarProfile(
            $aid,
            $bid,
            $memberId,
            $input,
            $resourceId,
            $data
        );

        return Db::name('digitalavatar_member_avatar')->where('id', $id)->find() ?: [];
    }

    public static function saveMemberVoiceFromTask(int $aid, int $bid, int $memberId, array $task, array $result): array
    {
        $taskId = intval($task['id'] ?? 0);
        if ($taskId <= 0 || $memberId <= 0) {
            throw new \RuntimeException('克隆任务信息无效');
        }

        $existing = Db::name('digitalavatar_member_voice')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('config_json', 'like', '%"source_task_id":' . $taskId . '%')
            ->find();
        if ($existing) {
            return $existing;
        }

        $input = self::decodeJsonValue($task['input_json'] ?? []);
        $data = self::decodeJsonValue($result['data'] ?? []);
        $resourceId = self::firstValue($result, $data, [
            'profile_id',
            'voice_id',
            'voice_model_id',
            'resource_id',
            'remote_id',
        ]);
        if ($resourceId === '') {
            $bizMsg = trim(strval($data['msg'] ?? ($result['msg'] ?? '')));
            throw new \RuntimeException($bizMsg !== '' ? $bizMsg : '拟娲未返回音色ID，本地不会保存无效克隆');
        }
        $previewUrl = self::firstValue($result, $data, [
            'preview_url',
            'sample_url',
            'audio_url',
            'url',
        ]);
        $sampleUrl = trim(strval($input['sample_url'] ?? ''));
        $requestedModelCode = trim(strval($task['model_code'] ?? ($input['model_code'] ?? '')));
        $channel = self::resolveVoiceCloneChannel($data, $requestedModelCode, $input);
        $modelCode = $channel['model_code'];
        $config = [
            'source_task_id' => $taskId,
            'sample_url' => $sampleUrl,
            'voice_id' => $resourceId,
            'model_code' => $modelCode,
            'requested_model_code' => $requestedModelCode,
            'voice_service_code' => $channel['voice_service_code'],
            'voice_service_name' => $channel['voice_service_name'],
            'target_model' => $channel['target_model'],
            'remote_id' => trim(strval($result['remote_id'] ?? '')),
            'provider_result' => $data,
            'language' => trim(strval($input['language'] ?? 'zh')) ?: 'zh',
            'instruction' => trim(strval($input['instruction'] ?? '')),
        ];
        $now = time();
        $id = intval(Db::name('digitalavatar_member_voice')->insertGetId([
            'aid' => $aid,
            'bid' => max(0, $bid),
            'member_id' => $memberId,
            'name' => mb_substr(
                trim(strval($input['name'] ?? ($input['voice_name'] ?? '')))
                    ?: trim(strval($task['title'] ?? '我的声音')),
                0,
                120
            ),
            'preview_url' => trim(strval($previewUrl)) ?: $sampleUrl,
            'provider_key' => trim(strval($task['provider_key'] ?? '')),
            'model_code' => $modelCode,
            'status' => 1,
            'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'create_time' => $now,
            'update_time' => $now,
        ]));

        self::syncZhuibaoVoiceProfile(
            $aid,
            $bid,
            $memberId,
            array_merge($input, [
                'model_code' => $modelCode,
                'voice_service_code' => $channel['voice_service_code'],
            ]),
            $resourceId,
            $data
        );

        return Db::name('digitalavatar_member_voice')->where('id', $id)->find() ?: [];
    }

    private static function syncZhuibaoAvatarProfile(
        int $aid,
        int $bid,
        int $memberId,
        array $input,
        string $modelId,
        array $providerResult
    ): void {
        if ($modelId === '' || !class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
            return;
        }

        try {
            $table = Db::name('zhuibao_member_avatar_profile');
            $existing = $table
                ->where('aid', $aid)
                ->where('uid', $memberId)
                ->where('model_id', $modelId)
                ->where('delete_time', 0)
                ->find();
            $data = [
                'aid' => $aid,
                'bid' => max(0, $bid),
                'uid' => $memberId,
                'name' => mb_substr(trim(strval($input['name'] ?? '我的数字人')) ?: '我的数字人', 0, 120),
                'sex' => intval($input['sex'] ?? $input['avatar_sex'] ?? 1),
                'platform_code' => trim(strval($providerResult['platform_code'] ?? ($input['platform_code'] ?? 'VPro'))) ?: 'VPro',
                'sample_url' => trim(strval($input['sample_url'] ?? '')),
                'model_id' => $modelId,
                'task_group_id' => intval($providerResult['task_group_id'] ?? 0),
                'status' => 1,
                'source_type' => 'digitalavatar',
                'remark' => '由 AI 数字人工作台创建',
                'update_time' => time(),
            ];
            if ($existing) {
                $table->where('id', intval($existing['id']))->update($data);
            } else {
                $data['create_time'] = time();
                $table->insert($data);
            }
        } catch (\Throwable $e) {
            // 兼容安装顺序：追爆资源表不可用时，新插件自己的资源仍然保留。
        }
    }

    private static function syncZhuibaoVoiceProfile(
        int $aid,
        int $bid,
        int $memberId,
        array $input,
        string $voiceId,
        array $providerResult
    ): void {
        if ($voiceId === '' || !class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
            return;
        }

        try {
            $table = Db::name('zhuibao_member_voice_profile');
            $existing = $table
                ->where('aid', $aid)
                ->where('uid', $memberId)
                ->where('voice_id', $voiceId)
                ->where('delete_time', 0)
                ->find();
            $data = [
                'aid' => $aid,
                'bid' => max(0, $bid),
                'uid' => $memberId,
                'name' => mb_substr(trim(strval($input['name'] ?? '我的声音')) ?: '我的声音', 0, 120),
                'sample_url' => trim(strval($input['sample_url'] ?? '')),
                'voice_id' => $voiceId,
                'sex' => intval($input['sex'] ?? 0),
                'language' => trim(strval($input['language'] ?? 'zh')) ?: 'zh',
                'instruction' => trim(strval($input['instruction'] ?? '请用自然平稳的语气说话。')) ?: '请用自然平稳的语气说话。',
                'status' => 1,
                'source_type' => 'digitalavatar',
                'remark' => '由 AI 数字人工作台创建',
                'update_time' => time(),
            ];
            if ($existing) {
                $table->where('id', intval($existing['id']))->update($data);
            } else {
                $data['create_time'] = time();
                $table->insert($data);
            }
        } catch (\Throwable $e) {
            // 兼容安装顺序：追爆资源表不可用时，新插件自己的资源仍然保留。
        }
    }

    public static function savePublicAvatar(int $aid, array $info): array
    {
        $name = trim(strval($info['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('请填写数字人名称');
        }
        $row = self::savePublicMedia($aid, $info, 'avatar', $name);
        $list = self::formatAdminPublicAvatarList($aid, [$row]);
        return $list[0] ?? $row;
    }

    public static function savePublicVoice(int $aid, array $info): array
    {
        $name = trim(strval($info['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('请填写声音名称');
        }
        $config = is_array($info['config'] ?? null) ? $info['config'] : [];
        if (trim(strval($info['voice_id'] ?? ($config['voice_id'] ?? ($config['resource_id'] ?? '')))) === '') {
            throw new \RuntimeException('请填写声音 ID');
        }
        $row = self::savePublicMedia($aid, $info, 'voice', $name);
        $list = self::formatAdminPublicAvatarList($aid, [$row]);
        return $list[0] ?? $row;
    }

    public static function parseVisibleMemberIds($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $text = trim(strval($value));
            if ($text === '') {
                return [0];
            }
            $decoded = json_decode($text, true);
            $items = is_array($decoded) ? $decoded : preg_split('/[,，、;\s|]+/u', $text);
        }

        $ids = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = $item['id'] ?? ($item['member_id'] ?? ($item['value'] ?? null));
            }
            if ($item === '' || $item === null || !is_numeric($item)) {
                continue;
            }
            $id = intval($item);
            if ($id < 0) {
                continue;
            }
            $ids[$id] = $id;
        }

        return $ids === [] ? [0] : array_values($ids);
    }

    public static function encodeVisibleMemberIds($value): string
    {
        return implode(',', self::parseVisibleMemberIds($value));
    }

    public static function isPublicAvatarVisibleToMember(array $row, int $memberId): bool
    {
        $ids = self::parseVisibleMemberIds($row['visible_member_ids'] ?? '');
        if (in_array(0, $ids, true)) {
            return true;
        }
        return $memberId > 0 && in_array($memberId, $ids, true);
    }

    private static function isOfficialAvatarAllowedForMember(int $aid, int $memberId, array $avatar): bool
    {
        if (strval($avatar['owner_type'] ?? '') === 'member') {
            return true;
        }
        $resourceId = trim(strval($avatar['resource_id'] ?? ($avatar['model_id'] ?? '')));
        if ($resourceId === '') {
            return self::isPublicAvatarVisibleToMember($avatar, $memberId);
        }
        foreach (Db::name('digitalavatar_public_avatar')->where('aid', $aid)->where('status', 1)->select()->toArray() as $row) {
            $formatted = self::formatPublicAvatar($row);
            $ownedId = trim(strval($formatted['resource_id'] ?? ($formatted['model_id'] ?? '')));
            if ($ownedId === $resourceId) {
                return self::isPublicAvatarVisibleToMember($formatted, $memberId);
            }
        }
        return self::isPublicAvatarVisibleToMember($avatar, $memberId);
    }

    private static function isOfficialVoiceAllowedForMember(int $aid, int $memberId, array $voice): bool
    {
        if (strval($voice['owner_type'] ?? '') === 'member') {
            return true;
        }
        $resourceId = trim(strval($voice['resource_id'] ?? ($voice['voice_id'] ?? '')));
        if ($resourceId === '') {
            return self::isPublicAvatarVisibleToMember($voice, $memberId);
        }
        foreach (Db::name('digitalavatar_public_voice')->where('aid', $aid)->where('status', 1)->select()->toArray() as $row) {
            $formatted = self::formatPublicVoice($row);
            $ownedId = trim(strval($formatted['resource_id'] ?? ($formatted['voice_id'] ?? '')));
            if ($ownedId === $resourceId) {
                return self::isPublicAvatarVisibleToMember($formatted, $memberId);
            }
        }
        return self::isPublicAvatarVisibleToMember($voice, $memberId);
    }

    public static function formatAdminPublicAvatarList(int $aid, array $list): array
    {
        $memberIds = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (self::parseVisibleMemberIds($row['visible_member_ids'] ?? '0') as $memberId) {
                if ($memberId > 0) {
                    $memberIds[$memberId] = $memberId;
                }
            }
        }

        $members = [];
        if (!empty($memberIds)) {
            foreach (Db::name('member')->where('aid', $aid)->whereIn('id', array_values($memberIds))->field('id,nickname,tel')->select()->toArray() as $member) {
                $members[intval($member['id'])] = $member;
            }
        }

        foreach ($list as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $ids = self::parseVisibleMemberIds($row['visible_member_ids'] ?? '0');
            $row['visible_member_ids'] = $ids;
            $row['visible_member_text'] = self::formatVisibleMemberText($ids, $members);
        }
        unset($row);

        return $list;
    }

    public static function searchMembers(int $aid, string $keyword = '', int $limit = 20): array
    {
        $query = Db::name('member')->where('aid', $aid);
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                if (ctype_digit($keyword)) {
                    $q->where('id', intval($keyword));
                }
                $like = '%' . $keyword . '%';
                $q->whereOr('nickname', 'like', $like)
                    ->whereOr('tel', 'like', $like)
                    ->whereOr('realname', 'like', $like);
            });
        }

        $rows = $query->field('id,nickname,tel,headimg,realname')->order('id desc')->limit(max(1, min(50, $limit)))->select()->toArray();
        $list = [[
            'id' => 0,
            'nickname' => '所有人可见',
            'tel' => '',
            'headimg' => '',
            'label' => '0 · 所有人可见',
        ]];
        foreach ($rows as $row) {
            $id = intval($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim(strval($row['nickname'] ?? ''));
            if ($name === '') {
                $name = trim(strval($row['realname'] ?? ''));
            }
            if ($name === '') {
                $name = '会员' . $id;
            }
            $tel = trim(strval($row['tel'] ?? ''));
            $list[] = [
                'id' => $id,
                'nickname' => $name,
                'tel' => $tel,
                'headimg' => strval($row['headimg'] ?? ''),
                'label' => $id . ' · ' . $name . ($tel !== '' ? ' · ' . $tel : ''),
            ];
        }
        return $list;
    }

    private static function applyPublicAvatarVisibility($query, int $memberId)
    {
        $memberId = max(0, $memberId);
        return $query->whereRaw(
            "(IFNULL(visible_member_ids,'') = '' OR FIND_IN_SET('0', visible_member_ids) OR FIND_IN_SET(?, visible_member_ids))",
            [strval($memberId)]
        );
    }

    private static function formatVisibleMemberText(array $ids, array $members = []): string
    {
        if (in_array(0, $ids, true)) {
            $extra = array_values(array_filter($ids, function ($id) {
                return intval($id) > 0;
            }));
            if ($extra === []) {
                return '所有人';
            }
            return '所有人；指定 ' . implode('、', array_map(function ($id) use ($members) {
                return self::formatVisibleMemberLabel(intval($id), $members);
            }, $extra));
        }
        if ($ids === []) {
            return '所有人';
        }
        return implode('、', array_map(function ($id) use ($members) {
            return self::formatVisibleMemberLabel(intval($id), $members);
        }, $ids));
    }

    private static function formatVisibleMemberLabel(int $id, array $members = []): string
    {
        $member = $members[$id] ?? [];
        $name = trim(strval($member['nickname'] ?? ''));
        if ($name === '') {
            return strval($id);
        }
        return $id . '（' . $name . '）';
    }

    public static function listPackages(int $aid, bool $activeOnly = false, $packageType = null, int $page = 0, int $limit = 50): array
    {
        $query = Db::name('digitalavatar_package')->where('aid', $aid);
        if ($activeOnly) {
            $query->where('status', 1);
        }
        $packageType = is_string($packageType) ? trim($packageType) : '';
        if (in_array($packageType, ['credit', 'boost'], true)) {
            $query->where('package_type', $packageType);
        }
        $query->order('sort asc,id asc');
        if ($page > 0) {
            $limit = max(1, min(200, $limit));
            $total = intval((clone $query)->count());
            $rows = $query->page($page, $limit)->select()->toArray();
            return [
                'list' => array_map([self::class, 'formatPackage'], $rows),
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        }
        return array_map([self::class, 'formatPackage'], $query->select()->toArray());
    }

    public static function formatPackage(array $row): array
    {
        $quota = DigitalAvatarLotService::normalizeQuotaMap($row['quota_json'] ?? []);
        $packageType = strval($row['package_type'] ?? 'credit') === 'boost' ? 'boost' : 'credit';
        $title = strval($row['title'] ?? '');
        $subtitle = trim(strval($row['subtitle'] ?? ''));
        $description = strval($row['description'] ?? '');
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'package_type' => $packageType,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'details' => self::packageDetailLines($row),
            'credit_amount' => DigitalAvatarCreditService::creditAmount($row['credit_amount'] ?? 0),
            'gift_credit' => DigitalAvatarCreditService::creditAmount($row['gift_credit'] ?? 0),
            'original_price' => round(floatval($row['original_price'] ?? 0), 2),
            'price' => round(floatval($row['price'] ?? 0), 2),
            'validity_days' => max(0, intval($row['validity_days'] ?? 0)),
            'max_concurrent_tasks' => max(0, min(100, intval($row['max_concurrent_tasks'] ?? 0))),
            'remark' => strval($row['remark'] ?? ''),
            'quota_json' => $quota,
            'sort' => intval($row['sort'] ?? 0),
            'status' => intval($row['status'] ?? 0),
            'is_recommend' => intval($row['is_recommend'] ?? 0),
            'create_time' => intval($row['create_time'] ?? 0),
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    public static function packageDetailLines(array $row): array
    {
        $lines = [];
        $packageType = strval($row['package_type'] ?? 'credit') === 'boost' ? 'boost' : 'credit';
        $creditAmount = DigitalAvatarCreditService::creditAmount($row['credit_amount'] ?? 0);
        if ($packageType !== 'boost' && $creditAmount > 0) {
            $lines[] = '会员算力 ' . $creditAmount . ' 点';
        }
        $raw = str_replace(["\r\n", "\r"], "\n", trim(strval($row['description'] ?? '')));
        if ($raw !== '') {
            foreach (explode("\n", $raw) as $line) {
                $line = trim(strval($line));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }
        $giftCredit = DigitalAvatarCreditService::creditAmount($row['gift_credit'] ?? 0);
        if ($giftCredit > 0) {
            $giftText = '赠送 ' . DigitalAvatarCreditService::formatCredit($giftCredit) . ' 创作点';
            $hasGift = false;
            foreach ($lines as $line) {
                if (mb_strpos($line, '赠送') !== false) {
                    $hasGift = true;
                    break;
                }
            }
            if (!$hasGift) {
                $lines[] = $giftText;
            }
        }
        $originalPrice = round(floatval($row['original_price'] ?? 0), 2);
        $salePrice = round(floatval($row['price'] ?? 0), 2);
        if ($originalPrice > $salePrice && $originalPrice > 0) {
            $originText = '原价 ¥' . number_format($originalPrice, 2, '.', '');
            $hasOrigin = false;
            foreach ($lines as $line) {
                if (mb_strpos($line, '原价') !== false) {
                    $hasOrigin = true;
                    break;
                }
            }
            if (!$hasOrigin) {
                $lines[] = $originText;
            }
        }
        $validityDays = max(0, intval($row['validity_days'] ?? 0));
        $validityText = $validityDays > 0 ? ('有效期 ' . $validityDays . ' 天') : '长期有效';
        $hasValidity = false;
        foreach ($lines as $line) {
            if (mb_strpos($line, '有效期') !== false || mb_strpos($line, '长期有效') !== false) {
                $hasValidity = true;
                break;
            }
        }
        if (!$hasValidity) {
            $lines[] = $validityText;
        }
        $concurrent = max(0, min(100, intval($row['max_concurrent_tasks'] ?? 0)));
        if ($packageType !== 'boost' && $concurrent > 0) {
            $lines[] = '任务并发 ' . $concurrent . ' 个';
        }
        $aid = intval($row['aid'] ?? 0);
        $quotas = DigitalAvatarLotService::dedupeQuotaMap(
            DigitalAvatarLotService::normalizeQuotaMap($row['quota_json'] ?? []),
            $aid
        );
        foreach ($quotas as $key => $amount) {
            $amount = max(0, intval($amount));
            if ($amount <= 0) {
                continue;
            }
            $meta = DigitalAvatarLotService::quotaMeta(strval($key), $aid);
            $lines[] = strval($meta['label'] ?? $key) . ' ' . $amount . strval($meta['unit'] ?? '');
        }
        $remark = trim(strval($row['remark'] ?? ''));
        if ($remark !== '') {
            $hasRemark = false;
            foreach ($lines as $line) {
                if ($line === $remark) {
                    $hasRemark = true;
                    break;
                }
            }
            if (!$hasRemark) {
                $lines[] = $remark;
            }
        }
        return array_values($lines);
    }

    public static function createMemberPackageOrder(int $aid, int $bid, int $memberId, int $packageId): array
    {
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        $package = Db::name('digitalavatar_package')
            ->where('aid', $aid)
            ->where('id', $packageId)
            ->where('status', 1)
            ->find();
        if (!$package) {
            throw new \RuntimeException('套餐不存在或已下架');
        }

        $pending = Db::name('digitalavatar_package_order')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('package_id', $packageId)
            ->where('status', 0)
            ->where('payorder_id', '>', 0)
            ->order('id desc')
            ->find();
        if ($pending) {
            return self::formatPackageOrder($pending, $package);
        }

        $packageType = strval($package['package_type'] ?? 'credit') === 'boost' ? 'boost' : 'credit';
        $price = round(floatval($package['price'] ?? 0), 2);
        $creditAmount = DigitalAvatarCreditService::creditAdd(
            $package['credit_amount'] ?? 0,
            $package['gift_credit'] ?? 0
        );
        $quotas = DigitalAvatarLotService::normalizeQuotaMap($package['quota_json'] ?? []);
        $quotaTotal = 0;
        foreach ($quotas as $amount) {
            $quotaTotal += max(0, intval($amount));
        }
        if ($packageType === 'boost') {
            if ($quotaTotal <= 0 && $creditAmount <= 0) {
                throw new \RuntimeException('加油包请至少填写一项模型额度');
            }
        } elseif ($creditAmount <= 0) {
            throw new \RuntimeException('套餐创作点数量不能为 0');
        }

        $now = time();
        $ordernum = 'DAP' . date('YmdHis') . str_pad(strval(mt_rand(0, 9999)), 4, '0', STR_PAD_LEFT);
        $orderId = intval(Db::name('digitalavatar_package_order')->insertGetId([
            'aid' => $aid,
            'bid' => max(0, $bid),
            'member_id' => $memberId,
            'package_id' => $packageId,
            'ordernum' => $ordernum,
            'payorder_id' => 0,
            'price' => number_format($price, 2, '.', ''),
            'credit_amount' => DigitalAvatarCreditService::creditStore($creditAmount),
            'status' => 0,
            'grant_status' => 0,
            'paid_time' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]));
        if ($orderId <= 0) {
            throw new \RuntimeException('套餐订单创建失败');
        }

        if ($price <= 0) {
            self::onPackageOrderPaid($orderId, '免费领取');
            $order = Db::name('digitalavatar_package_order')->where('id', $orderId)->find() ?: [];
            return self::formatPackageOrder($order, $package);
        }

        $payorderId = intval(\app\admin\model\Payorder::createorder(
            $aid,
            max(0, $bid),
            $memberId,
            'digitalavatar_package',
            $orderId,
            $ordernum,
            'AI数字人套餐-' . trim(strval($package['title'] ?? '')),
            $price
        ));
        if ($payorderId <= 0) {
            Db::name('digitalavatar_package_order')->where('id', $orderId)->update([
                'status' => 2,
                'update_time' => time(),
            ]);
            throw new \RuntimeException('统一支付单创建失败');
        }

        Db::name('digitalavatar_package_order')->where('id', $orderId)->update([
            'payorder_id' => $payorderId,
            'update_time' => time(),
        ]);
        $order = Db::name('digitalavatar_package_order')->where('id', $orderId)->find() ?: [];
        return self::formatPackageOrder($order, $package);
    }

    public static function onPackageOrderPaid(int $orderId, string $payType = '在线支付'): void
    {
        if ($orderId <= 0) {
            throw new \RuntimeException('套餐订单不存在');
        }

        Db::transaction(function () use ($orderId, $payType) {
            $order = Db::name('digitalavatar_package_order')->where('id', $orderId)->lock(true)->find();
            if (!$order) {
                throw new \RuntimeException('套餐订单不存在');
            }
            if (intval($order['grant_status'] ?? 0) === 1) {
                return;
            }

            $package = Db::name('digitalavatar_package')
                ->where('aid', intval($order['aid']))
                ->where('id', intval($order['package_id']))
                ->find();
            if (!$package) {
                throw new \RuntimeException('套餐不存在或已删除');
            }

            DigitalAvatarCreditService::grantPackage(
                intval($order['aid']),
                intval($order['bid']),
                intval($order['member_id']),
                DigitalAvatarCreditService::creditAmount($order['credit_amount'] ?? 0),
                intval($order['package_id']),
                intval($package['validity_days'] ?? 0),
                $orderId,
                '购买 AI数字人套餐 ' . trim(strval($order['ordernum'] ?? ''))
            );
            $quotas = DigitalAvatarLotService::normalizeQuotaMap($package['quota_json'] ?? []);
            $expireTime = 0;
            $days = intval($package['validity_days'] ?? 0);
            if ($days > 0) {
                $expireTime = strtotime('+' . $days . ' day') ?: 0;
            }
            DigitalAvatarLotService::grantQuotaLots(
                intval($order['aid']),
                intval($order['bid']),
                intval($order['member_id']),
                $quotas,
                $expireTime,
                'boost_pack',
                $orderId,
                '购买加油包 ' . trim(strval($order['ordernum'] ?? ''))
            );
            Db::name('digitalavatar_package_order')->where('id', $orderId)->update([
                'status' => 1,
                'paytype' => trim($payType) ?: '在线支付',
                'paytime' => intval($order['paytime'] ?? 0) > 0 ? intval($order['paytime']) : time(),
                'paid_time' => intval($order['paid_time'] ?? 0) > 0 ? intval($order['paid_time']) : time(),
                'grant_status' => 1,
                'update_time' => time(),
            ]);
            $fresh = Db::name('digitalavatar_package_order')->where('id', $orderId)->find();
            try {
                DigitalAvatarFinanceService::grantOrderCommission(is_array($fresh) ? $fresh : $order);
            } catch (\Throwable $e) {
            }
        });
    }

    public static function savePackage(int $aid, array $info): array
    {
        $id = intval($info['id'] ?? 0);
        $title = trim(strval($info['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('请填写套餐名称');
        }
        $packageType = strval($info['package_type'] ?? 'credit') === 'boost' ? 'boost' : 'credit';
        $quotas = DigitalAvatarLotService::dedupeQuotaMap(
            DigitalAvatarLotService::normalizeQuotaMap($info['quota_json'] ?? $info),
            $aid
        );
        $creditAmount = DigitalAvatarCreditService::creditAmount($info['credit_amount'] ?? 0);
        if ($packageType === 'credit' && $creditAmount <= 0) {
            throw new \RuntimeException('请填写会员算力');
        }
        if ($packageType === 'boost') {
            $sum = 0;
            foreach ($quotas as $amount) {
                $sum += max(0, intval($amount));
            }
            if ($sum <= 0) {
                throw new \RuntimeException('请至少填写一项模型额度');
            }
            $creditAmount = 0;
        }
        DigitalAvatarInstaller::ensurePackageConcurrentColumns();
        $data = [
            'aid' => $aid,
            'package_type' => $packageType,
            'title' => mb_substr($title, 0, 120),
            'subtitle' => mb_substr(trim(strval($info['subtitle'] ?? '')), 0, 200),
            'description' => mb_substr(trim(strval($info['description'] ?? '')), 0, 500),
            'credit_amount' => DigitalAvatarCreditService::creditStore($creditAmount),
            'gift_credit' => DigitalAvatarCreditService::creditStore($info['gift_credit'] ?? 0),
            'original_price' => max(0, floatval($info['original_price'] ?? 0)),
            'price' => max(0, floatval($info['price'] ?? 0)),
            'validity_days' => max(0, intval($info['validity_days'] ?? 0)),
            'max_concurrent_tasks' => $packageType === 'credit' ? max(0, min(100, intval($info['max_concurrent_tasks'] ?? 0))) : 0,
            'remark' => mb_substr(trim(strval($info['remark'] ?? '')), 0, 500),
            'feature_scope_json' => json_encode(is_array($info['feature_scope'] ?? null) ? $info['feature_scope'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'quota_json' => json_encode($quotas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sort' => intval($info['sort'] ?? 0),
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'is_recommend' => intval($info['is_recommend'] ?? 0) === 1 ? 1 : 0,
            'update_time' => time(),
        ];
        if ($id > 0) {
            $exists = Db::name('digitalavatar_package')->where('aid', $aid)->where('id', $id)->find();
            if (!$exists) {
                throw new \RuntimeException('套餐不存在');
            }
            Db::name('digitalavatar_package')->where('id', $id)->update($data);
        } else {
            $data['create_time'] = time();
            $id = intval(Db::name('digitalavatar_package')->insertGetId($data));
        }
        $row = Db::name('digitalavatar_package')->where('aid', $aid)->where('id', $id)->find() ?: [];
        return self::formatPackage($row);
    }

    public static function deletePackage(int $aid, int $id): void
    {
        if ($id <= 0) {
            throw new \RuntimeException('请传入套餐ID');
        }
        $row = Db::name('digitalavatar_package')->where('aid', $aid)->where('id', $id)->find();
        if (!$row) {
            throw new \RuntimeException('套餐不存在');
        }
        Db::name('digitalavatar_package')->where('id', $id)->delete();
    }

    public static function getModelOptions(int $aid): array
    {
        return DigitalAvatarModelService::getPortalOptions($aid);
    }

    private static function getZhuibaoPlatformAvatars(int $aid): array
    {
        try {
            if (!class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
                return [];
            }
            $provider = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getProviderConfigByKey($aid, 'avatar');
            if (intval($provider['status'] ?? 0) !== 1) {
                return [];
            }
            $profiles = is_array($provider['avatar_profiles'] ?? null) ? $provider['avatar_profiles'] : [];
            $result = [];
            foreach ($profiles as $profile) {
                if (is_array($profile) && trim(strval($profile['model_id'] ?? '')) !== '') {
                    $result[] = self::formatZhuibaoAvatar($profile, false, $aid);
                }
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function getZhuibaoPlatformVoices(int $aid): array
    {
        try {
            if (!class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
                return [];
            }
            $provider = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getProviderConfigByKey($aid, 'tts');
            if (intval($provider['status'] ?? 0) !== 1) {
                return [];
            }
            $profiles = is_array($provider['voice_profiles'] ?? null) ? $provider['voice_profiles'] : [];
            $serviceCode = trim(strval($provider['voice_service_code'] ?? ''));
            $providerModelCode = trim(strval($provider['model_code'] ?? ''));
            $result = [];
            foreach ($profiles as $profile) {
                if (is_array($profile) && trim(strval($profile['voice_id'] ?? '')) !== '') {
                    $profile['aid'] = $aid;
                    $profile['voice_service_code'] = trim(strval($profile['voice_service_code'] ?? $serviceCode));
                    $profile['model_code'] = trim(strval($profile['model_code'] ?? $providerModelCode));
                    $result[] = self::formatZhuibaoVoice($profile, false, $aid);
                }
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function formatPublicAvatar(array $row): array
    {
        $config = self::decodeJsonValue($row['config_json'] ?? '');
        $modelId = trim(strval($config['model_id'] ?? ($config['avatar_model_id'] ?? '')));
        return self::withAvatarCloneModelMeta($row, [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'local_id' => 'digitalavatar_public_avatar_' . intval($row['id'] ?? 0),
            'resource_id' => $modelId,
            'model_id' => $modelId,
            'name' => trim(strval($row['name'] ?? '')) ?: '平台数字人',
            'cover_url' => trim(strval($row['cover_url'] ?? '')),
            'preview_url' => trim(strval($row['preview_url'] ?? '')),
            'provider_key' => trim(strval($row['provider_key'] ?? '')),
            'model_code' => trim(strval($row['model_code'] ?? ($config['model_code'] ?? ''))),
            'platform_code' => trim(strval($config['platform_code'] ?? ($row['platform_code'] ?? ''))),
            'sort' => intval($row['sort'] ?? 0),
            'status' => intval($row['status'] ?? 1),
            'visible_member_ids' => self::parseVisibleMemberIds($row['visible_member_ids'] ?? ($config['visible_member_ids'] ?? '0')),
            'owner_type' => 'platform',
            'source_plugin' => 'digitalavatar',
            'ready' => $modelId !== '' ? 1 : 0,
        ]);
    }

    private static function formatPublicVoice(array $row): array
    {
        $config = self::decodeJsonValue($row['config_json'] ?? '');
        $voiceId = trim(strval($config['voice_id'] ?? ($config['resource_id'] ?? '')));
        $modelType = intval($config['model_type'] ?? 1) === 2 ? 2 : 1;
        return self::withVoiceCloneModelMeta($row, [
            'id' => intval($row['id'] ?? 0),
            'aid' => intval($row['aid'] ?? 0),
            'local_id' => 'digitalavatar_public_voice_' . intval($row['id'] ?? 0),
            'resource_id' => $voiceId,
            'voice_id' => $voiceId,
            'model_type' => $modelType,
            'name' => trim(strval($row['name'] ?? '')) ?: '平台声音',
            'preview_url' => trim(strval($row['preview_url'] ?? '')),
            'download_url' => trim(strval($row['preview_url'] ?? '')),
            'provider_key' => trim(strval($row['provider_key'] ?? '')),
            'model_code' => trim(strval($row['model_code'] ?? ($config['model_code'] ?? ''))),
            'voice_service_code' => trim(strval($config['voice_service_code'] ?? ($row['voice_service_code'] ?? ''))),
            'sort' => intval($row['sort'] ?? 0),
            'status' => intval($row['status'] ?? 1),
            'visible_member_ids' => self::parseVisibleMemberIds($row['visible_member_ids'] ?? ($config['visible_member_ids'] ?? '0')),
            'owner_type' => 'platform',
            'source_plugin' => 'digitalavatar',
            'ready' => $voiceId !== '' ? 1 : 0,
        ]);
    }

    private static function formatMemberAvatar(array $row): array
    {
        $config = self::decodeJsonValue($row['config_json'] ?? '');
        $modelId = trim(strval($config['model_id'] ?? ($config['avatar_model_id'] ?? '')));
        $sampleUrl = trim(strval($config['sample_url'] ?? ''));
        $coverUrl = self::pickImageUrl([
            $config['cover_url'] ?? '',
            $row['cover_url'] ?? '',
            $row['avatar_url'] ?? '',
        ]);
        $videoUrl = self::pickVideoUrl([
            $row['preview_url'] ?? '',
            $sampleUrl,
            $row['avatar_url'] ?? '',
        ]);
        $downloadUrl = $videoUrl !== '' ? $videoUrl : ($coverUrl !== '' ? $coverUrl : trim(strval($row['avatar_url'] ?? $sampleUrl)));
        return self::withAvatarCloneModelMeta($row, array_merge($row, [
            'local_id' => 'digitalavatar_member_avatar_' . intval($row['id'] ?? 0),
            'resource_id' => $modelId,
            'model_id' => $modelId,
            'sample_url' => $sampleUrl,
            'cover_url' => $coverUrl,
            'preview_url' => $videoUrl !== '' ? $videoUrl : ($coverUrl !== '' ? $coverUrl : trim(strval($row['preview_url'] ?? $sampleUrl))),
            'download_url' => $downloadUrl,
            'video_url' => $videoUrl,
            'model_code' => trim(strval($row['model_code'] ?? ($config['model_code'] ?? ''))),
            'platform_code' => trim(strval($config['platform_code'] ?? ($row['platform_code'] ?? ''))),
            'owner_type' => 'member',
            'source_plugin' => 'digitalavatar',
            'ready' => ($modelId !== '' || (self::isXproAvatar([
                'model_code' => trim(strval($row['model_code'] ?? ($config['model_code'] ?? ''))),
                'platform_code' => trim(strval($config['platform_code'] ?? ($row['platform_code'] ?? ''))),
                'cover_url' => $coverUrl,
            ]) && $coverUrl !== '')) ? 1 : 0,
        ]));
    }

    public static function updateMemberAvatarCover(int $aid, int $bid, int $memberId, int $id, string $coverUrl): array
    {
        $coverUrl = trim($coverUrl);
        if ($id <= 0 || $coverUrl === '') {
            throw new \RuntimeException('请选择数字人封面');
        }
        if (self::isVideoUrl($coverUrl)) {
            throw new \RuntimeException('封面请使用图片，不要使用视频');
        }
        $row = Db::name('digitalavatar_member_avatar')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('id', $id)
            ->find();
        if (!$row) {
            throw new \RuntimeException('数字人不存在');
        }
        $config = self::decodeJsonValue($row['config_json'] ?? '');
        $config['cover_url'] = $coverUrl;
        $config['cover_source'] = 'member';
        Db::name('digitalavatar_member_avatar')->where('id', $id)->update([
            'cover_url' => mb_strlen($coverUrl) <= 500 ? $coverUrl : '',
            'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'update_time' => time(),
        ]);
        return self::formatMemberAvatar(Db::name('digitalavatar_member_avatar')->where('id', $id)->find() ?: []);
    }

    public static function deleteMemberAvatar(int $aid, int $bid, int $memberId, array $input): array
    {
        $id = intval($input['id'] ?? 0);
        $localId = trim(strval($input['local_id'] ?? ''));
        $sourcePlugin = trim(strval($input['source_plugin'] ?? ''));
        $ownerType = trim(strval($input['owner_type'] ?? ''));
        if ($ownerType === 'platform') {
            throw new \RuntimeException('官方数字人不能删除');
        }
        if ($id <= 0 && $localId === '') {
            throw new \RuntimeException('请选择要删除的数字人');
        }

        $digitalavatarId = 0;
        if (preg_match('/^digitalavatar_member_avatar_(\d+)$/', $localId, $matches)) {
            $digitalavatarId = intval($matches[1]);
        } elseif ($sourcePlugin !== 'zhuibaoagent' && $id > 0) {
            $digitalavatarId = $id;
        }

        if ($digitalavatarId > 0 && $sourcePlugin !== 'zhuibaoagent') {
            $row = Db::name('digitalavatar_member_avatar')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->where('id', $digitalavatarId)
                ->where('status', 1)
                ->find();
            if ($row) {
                Db::name('digitalavatar_member_avatar')->where('id', $digitalavatarId)->update([
                    'status' => 0,
                    'update_time' => time(),
                ]);
                self::disableSyncedZhuibaoAvatar($aid, $memberId, $row);
                return [
                    'deleted' => 1,
                    'id' => $digitalavatarId,
                    'local_id' => 'digitalavatar_member_avatar_' . $digitalavatarId,
                    'source_plugin' => 'digitalavatar',
                ];
            }
            if ($sourcePlugin === 'digitalavatar' || strpos($localId, 'digitalavatar_member_avatar_') === 0) {
                throw new \RuntimeException('数字人不存在或无权删除');
            }
        }

        $zhuibaoId = 0;
        if (preg_match('/^member_avatar_(\d+)$/', $localId, $matches)) {
            $zhuibaoId = intval($matches[1]);
        } elseif ($sourcePlugin === 'zhuibaoagent' && $id > 0) {
            $zhuibaoId = $id;
        }
        if ($zhuibaoId > 0 && class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
            \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::deleteMemberAvatarProfile($aid, $memberId, $zhuibaoId);
            return [
                'deleted' => 1,
                'id' => $zhuibaoId,
                'local_id' => 'member_avatar_' . $zhuibaoId,
                'source_plugin' => 'zhuibaoagent',
            ];
        }

        throw new \RuntimeException('数字人不存在或无权删除');
    }

    public static function deleteMemberVoice(int $aid, int $bid, int $memberId, array $input): array
    {
        $id = intval($input['id'] ?? 0);
        $localId = trim(strval($input['local_id'] ?? ''));
        $sourcePlugin = trim(strval($input['source_plugin'] ?? ''));
        $ownerType = trim(strval($input['owner_type'] ?? ''));
        if ($ownerType === 'platform') {
            throw new \RuntimeException('官方音色不能删除');
        }
        if ($id <= 0 && $localId === '') {
            throw new \RuntimeException('请选择要删除的音色');
        }

        $digitalavatarId = 0;
        if (preg_match('/^digitalavatar_member_voice_(\d+)$/', $localId, $matches)) {
            $digitalavatarId = intval($matches[1]);
        } elseif ($sourcePlugin !== 'zhuibaoagent' && $id > 0) {
            $digitalavatarId = $id;
        }

        if ($digitalavatarId > 0 && $sourcePlugin !== 'zhuibaoagent') {
            $row = Db::name('digitalavatar_member_voice')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->where('id', $digitalavatarId)
                ->where('status', 1)
                ->find();
            if ($row) {
                Db::name('digitalavatar_member_voice')->where('id', $digitalavatarId)->update([
                    'status' => 0,
                    'update_time' => time(),
                ]);
                self::disableSyncedZhuibaoVoice($aid, $memberId, $row);
                return [
                    'deleted' => 1,
                    'id' => $digitalavatarId,
                    'local_id' => 'digitalavatar_member_voice_' . $digitalavatarId,
                    'source_plugin' => 'digitalavatar',
                ];
            }
            if ($sourcePlugin === 'digitalavatar' || strpos($localId, 'digitalavatar_member_voice_') === 0) {
                throw new \RuntimeException('音色不存在或无权删除');
            }
        }

        $zhuibaoId = 0;
        if (preg_match('/^member_voice_(\d+)$/', $localId, $matches)) {
            $zhuibaoId = intval($matches[1]);
        } elseif ($sourcePlugin === 'zhuibaoagent' && $id > 0) {
            $zhuibaoId = $id;
        }
        if ($zhuibaoId > 0 && class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
            \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::deleteMemberVoiceProfile($aid, $memberId, $zhuibaoId);
            return [
                'deleted' => 1,
                'id' => $zhuibaoId,
                'local_id' => 'member_voice_' . $zhuibaoId,
                'source_plugin' => 'zhuibaoagent',
            ];
        }

        throw new \RuntimeException('音色不存在或无权删除');
    }

    public static function updateMemberVoice(int $aid, int $bid, int $memberId, array $input): array
    {
        $name = mb_substr(trim(strval($input['name'] ?? ($input['title'] ?? ''))), 0, 120);
        if ($name === '') {
            throw new \RuntimeException('请填写音色名称');
        }

        $id = intval($input['id'] ?? 0);
        $localId = trim(strval($input['local_id'] ?? ''));
        $sourcePlugin = trim(strval($input['source_plugin'] ?? ''));
        $ownerType = trim(strval($input['owner_type'] ?? ''));
        if ($ownerType === 'platform') {
            throw new \RuntimeException('官方音色不能修改');
        }
        if ($id <= 0 && $localId === '') {
            throw new \RuntimeException('请选择要修改的音色');
        }

        $digitalavatarId = 0;
        if (preg_match('/^digitalavatar_member_voice_(\d+)$/', $localId, $matches)) {
            $digitalavatarId = intval($matches[1]);
        } elseif ($sourcePlugin !== 'zhuibaoagent' && $id > 0) {
            $digitalavatarId = $id;
        }

        if ($digitalavatarId > 0 && $sourcePlugin !== 'zhuibaoagent') {
            $row = Db::name('digitalavatar_member_voice')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->where('id', $digitalavatarId)
                ->where('status', 1)
                ->find();
            if ($row) {
                Db::name('digitalavatar_member_voice')->where('id', $digitalavatarId)->update([
                    'name' => $name,
                    'update_time' => time(),
                ]);
                self::renameSyncedZhuibaoVoice($aid, $memberId, $row, $name);
                return [
                    'id' => $digitalavatarId,
                    'name' => $name,
                    'local_id' => 'digitalavatar_member_voice_' . $digitalavatarId,
                    'source_plugin' => 'digitalavatar',
                ];
            }
            if ($sourcePlugin === 'digitalavatar' || strpos($localId, 'digitalavatar_member_voice_') === 0) {
                throw new \RuntimeException('音色不存在或无权修改');
            }
        }

        $zhuibaoId = 0;
        if (preg_match('/^member_voice_(\d+)$/', $localId, $matches)) {
            $zhuibaoId = intval($matches[1]);
        } elseif ($sourcePlugin === 'zhuibaoagent' && $id > 0) {
            $zhuibaoId = $id;
        }
        if ($zhuibaoId > 0) {
            try {
                $row = Db::name('zhuibao_member_voice_profile')
                    ->where('aid', $aid)
                    ->where('uid', $memberId)
                    ->where('id', $zhuibaoId)
                    ->where('delete_time', 0)
                    ->find();
                if ($row) {
                    Db::name('zhuibao_member_voice_profile')->where('id', $zhuibaoId)->update([
                        'name' => $name,
                        'update_time' => time(),
                    ]);
                    self::renameLinkedDigitalAvatarVoice($aid, $bid, $memberId, $row, $name);
                    return [
                        'id' => $zhuibaoId,
                        'name' => $name,
                        'local_id' => 'member_voice_' . $zhuibaoId,
                        'source_plugin' => 'zhuibaoagent',
                    ];
                }
            } catch (\Throwable $e) {
                // 追爆声音表不可用时，继续走统一错误提示。
            }
        }

        throw new \RuntimeException('音色不存在或无权修改');
    }

    public static function storeMemberImage(int $aid, int $bid, int $memberId, string $binary, string $fileName = 'cover.jpg', bool $saveAsset = true): array
    {
        if ($binary === '') {
            throw new \RuntimeException('图片内容为空');
        }
        if (strlen($binary) > 8 * 1024 * 1024) {
            throw new \RuntimeException('封面图片不能超过 8MB');
        }
        $imageInfo = @getimagesizefromstring($binary);
        if ($imageInfo === false) {
            throw new \RuntimeException('封面不是有效图片');
        }
        $mime = strtolower(trim(strval($imageInfo['mime'] ?? 'image/jpeg')));
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $ext = $extMap[$mime] ?? 'jpg';
        $dir = 'upload/' . $aid . '/' . date('Ymd');
        $fullDir = rtrim(strval(ROOT_PATH), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
        if (!is_dir($fullDir) && !mkdir($fullDir, 0755, true) && !is_dir($fullDir)) {
            throw new \RuntimeException('创建封面目录失败');
        }
        $savedName = date('His') . rand(1000, 9999) . '.' . $ext;
        $filepath = $dir . '/' . $savedName;
        $realPath = rtrim(strval(ROOT_PATH), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filepath);
        if (file_put_contents($realPath, $binary) === false) {
            throw new \RuntimeException('保存封面失败');
        }
        $url = (defined('PRE_URL') ? PRE_URL : request()->domain()) . '/' . $filepath;
        $uploadedUrl = \app\admin\common\Pic::uploadoss($url, false, $realPath);
        if ($uploadedUrl && $uploadedUrl !== $url) {
            $url = $uploadedUrl;
        }
        $size = is_file($realPath) ? intval(filesize($realPath)) : strlen($binary);
        $uploadId = intval(Db::name('admin_upload')->insertGetId([
            'aid' => $aid,
            'uid' => $memberId,
            'name' => mb_substr($fileName !== '' ? $fileName : ('cover.' . $ext), 0, 191),
            'dir' => date('Ymd'),
            'url' => $url,
            'type' => $ext,
            'width' => intval($imageInfo[0] ?? 0),
            'height' => intval($imageInfo[1] ?? 0),
            'size' => $size,
            'createtime' => time(),
            'gid' => 0,
            'platform' => 'davportal',
            'merchant_id' => max(0, $bid),
        ]));
        if ($saveAsset) {
            Db::name('digitalavatar_asset')->insert([
                'aid' => $aid,
                'bid' => max(0, $bid),
                'member_id' => $memberId,
                'task_id' => 0,
                'asset_type' => 'image',
                'name' => mb_substr($fileName !== '' ? $fileName : '数字人封面', 0, 191),
                'url' => $url,
                'mime_type' => $mime,
                'size' => $size,
                'duration' => 0,
                'metadata_json' => json_encode(['source' => 'avatar_cover', 'upload_id' => $uploadId], JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'create_time' => time(),
                'update_time' => time(),
                'delete_time' => 0,
            ]);
        }
        return [
            'id' => $uploadId,
            'url' => $url,
            'name' => $fileName,
            'extension' => $ext,
            'size' => $size,
        ];
    }

    public static function isVideoUrl(string $url): bool
    {
        return (bool)preg_match('/\.(mp4|mov|m4v|webm|avi|mkv|ogv|wmv|flv|3gp|mpeg|mpg)(?:[?#].*)?$/i', trim($url));
    }

    public static function isAudioUrl(string $url): bool
    {
        return (bool)preg_match('/\.(mp3|wav|m4a|aac|ogg|flac|wma|opus|amr|weba)(?:[?#].*)?$/i', trim($url));
    }

    public static function pickAudioUrl(array $candidates): string
    {
        foreach ($candidates as $url) {
            $url = trim(strval($url));
            if ($url !== '' && self::isAudioUrl($url)) {
                return $url;
            }
        }
        return '';
    }

    public static function isImageUrl(string $url): bool
    {
        $url = trim($url);
        if (stripos($url, 'data:image/') === 0) {
            return true;
        }
        return (bool)preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)(?:[?#].*)?$/i', $url);
    }

    public static function pickVideoUrl(array $candidates): string
    {
        foreach ($candidates as $url) {
            $url = trim(strval($url));
            if ($url !== '' && self::isVideoUrl($url)) {
                return $url;
            }
        }
        return '';
    }

    public static function pickImageUrl(array $candidates): string
    {
        foreach ($candidates as $url) {
            $url = trim(strval($url));
            if ($url !== '' && self::isImageUrl($url)) {
                return $url;
            }
        }
        foreach ($candidates as $url) {
            $url = trim(strval($url));
            if ($url !== '' && !self::isVideoUrl($url) && preg_match('#^https?://#i', $url)) {
                return $url;
            }
        }
        return '';
    }

    private static function formatMemberVoice(array $row): array
    {
        $config = self::decodeJsonValue($row['config_json'] ?? '');
        $voiceId = trim(strval($config['voice_id'] ?? ($config['resource_id'] ?? '')));
        $sampleUrl = trim(strval($row['preview_url'] ?? ''))
            ?: trim(strval($config['sample_url'] ?? ''))
            ?: trim(strval($row['sample_url'] ?? ''));
        $providerResult = is_array($config['provider_result'] ?? null) ? $config['provider_result'] : [];
        return self::withVoiceCloneModelMeta($row, array_merge($row, [
            'local_id' => 'digitalavatar_member_voice_' . intval($row['id'] ?? 0),
            'resource_id' => $voiceId,
            'voice_id' => $voiceId,
            'model_type' => 2,
            'owner_type' => 'member',
            'source_plugin' => 'digitalavatar',
            'sample_url' => $sampleUrl,
            'preview_url' => $sampleUrl,
            'download_url' => $sampleUrl,
            'model_code' => trim(strval($row['model_code'] ?? ($config['model_code'] ?? ''))),
            'voice_service_code' => trim(strval($config['voice_service_code'] ?? ($row['voice_service_code'] ?? ''))),
            'voice_service_name' => trim(strval($config['voice_service_name'] ?? ($providerResult['voice_service_name'] ?? ''))),
            'ready' => $voiceId !== '' ? 1 : 0,
        ]));
    }

    private static function avatarModelCodeFromPlatform(string $platformCode): string
    {
        $platform = strtolower(trim($platformCode));
        if ($platform === 'max') {
            return 'Max';
        }
        if ($platform === 'xpro') {
            return 'XPro';
        }
        if ($platform === 'pro') {
            return 'Pro';
        }
        return 'VPro';
    }

    public static function avatarVideoFamily(array $avatar): string
    {
        $hint = strtolower(trim(implode(' ', [
            strval($avatar['model_code'] ?? ''),
            strval($avatar['platform_code'] ?? ''),
            strval($avatar['clone_model_name'] ?? ''),
        ])));
        if (strpos($hint, 'xpro') !== false) {
            return 'xpro';
        }
        if (preg_match('/(^|[^a-z0-9])max([^a-z0-9]|$)/', $hint)) {
            return 'max';
        }
        if (strpos($hint, 'vpro') !== false) {
            return 'vpro';
        }
        if (preg_match('/(^|[^a-z0-9])pro([^a-z0-9]|$)/', $hint)) {
            return 'pro';
        }
        return 'vpro';
    }

    public static function allowedAvatarVideoCodes(array $avatar): array
    {
        $family = self::avatarVideoFamily($avatar);
        if ($family === 'max') {
            return ['Max', 'XPro'];
        }
        if ($family === 'xpro') {
            return ['XPro'];
        }
        return [];
    }

    public static function clampAvatarVideoModelCode(int $aid, array $avatar, string $requested): string
    {
        $requested = trim($requested);
        $allowed = self::allowedAvatarVideoCodes($avatar);
        $cloneCode = trim(strval($avatar['model_code'] ?? ($avatar['platform_code'] ?? '')));
        $available = [];
        try {
            foreach (DigitalAvatarModelService::getPortalOptions($aid) as $model) {
                if (strval($model['task_type'] ?? '') === 'avatar_video') {
                    $code = trim(strval($model['model_code'] ?? ''));
                    if ($code !== '') {
                        $available[] = $code;
                    }
                }
            }
        } catch (\Throwable $e) {
            $available = ['VPro', 'Pro', 'Max', 'XPro'];
        }
        $pool = $available;
        if ($allowed !== []) {
            $allowedMap = [];
            foreach ($allowed as $item) {
                $allowedMap[strtolower($item)] = $item;
            }
            $pool = [];
            foreach ($available as $code) {
                if (isset($allowedMap[strtolower($code)])) {
                    $pool[] = $code;
                }
            }
            if ($pool === []) {
                $pool = $allowed;
            }
        }
        if ($pool === []) {
            return $requested;
        }
        foreach ($pool as $code) {
            if ($requested !== '' && strcasecmp($code, $requested) === 0) {
                return $code;
            }
        }
        foreach ($pool as $code) {
            if ($cloneCode !== '' && strcasecmp($code, $cloneCode) === 0) {
                return $code;
            }
        }
        $family = self::avatarVideoFamily($avatar);
        foreach ($pool as $code) {
            if ($family !== '' && strcasecmp($code, $family) === 0) {
                return $code;
            }
        }
        return strval($pool[0]);
    }

    private static function lookupCloneModelTitle(int $aid, string $modelCode): string
    {
        static $cache = [];
        $modelCode = trim($modelCode);
        if ($aid <= 0 || $modelCode === '') {
            return '';
        }
        if (!isset($cache[$aid])) {
            try {
                $rows = Db::name('digitalavatar_model')
                    ->where('aid', $aid)
                    ->whereIn('task_type', ['avatar_clone', 'avatar_video', 'voice_clone', 'audio'])
                    ->column('title', 'model_code');
                $cache[$aid] = is_array($rows) ? $rows : [];
            } catch (\Throwable $e) {
                $cache[$aid] = [];
            }
        }
        return trim(strval($cache[$aid][$modelCode] ?? ''));
    }

    private static function cloneModelLabel(int $aid, string $modelCode, string $platformCode = '', array $config = []): string
    {
        $modelCode = trim($modelCode);
        $platformCode = trim($platformCode !== '' ? $platformCode : strval($config['platform_code'] ?? ''));
        if ($modelCode === '' || $modelCode === 'niwaai-avatar') {
            $modelCode = self::avatarModelCodeFromPlatform($platformCode);
        }

        $platformMap = [
            'VPro' => 'VPro 高仿真版',
            '3D' => '3D精品版',
            'niwa' => '拟娲基础数字人',
        ];
        if (isset($platformMap[$platformCode])) {
            return $platformMap[$platformCode];
        }

        $title = self::lookupCloneModelTitle($aid, $modelCode);
        if ($title !== '') {
            return $title;
        }

        if (class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoNiwaModelCatalog')) {
            $spec = \app\plugin\zhuibaoagent\common\ZhuibaoNiwaModelCatalog::resolve(
                $modelCode,
                $config,
                ['platform_code' => $platformCode, 'task_type' => 'avatar_clone']
            );
            $familyPlatform = trim(strval($spec['platform_code'] ?? ''));
            if (isset($platformMap[$familyPlatform])) {
                return $platformMap[$familyPlatform];
            }
            $label = trim(strval($spec['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return $modelCode !== '' ? $modelCode : $platformCode;
    }

    private static function withAvatarCloneModelMeta(array $source, array $result): array
    {
        $aid = intval($source['aid'] ?? ($result['aid'] ?? 0));
        $config = self::decodeJsonValue($source['config_json'] ?? ($result['config_json'] ?? []));
        $modelCode = trim(strval($result['model_code'] ?? ($source['model_code'] ?? ($config['model_code'] ?? ''))));
        $platformCode = trim(strval(
            $result['platform_code']
            ?? ($config['platform_code'] ?? ($source['platform_code'] ?? ''))
        ));
        if ($modelCode === '' || $modelCode === 'niwaai-avatar') {
            $modelCode = self::avatarModelCodeFromPlatform($platformCode);
        }
        $result['aid'] = $aid;
        $result['model_code'] = $modelCode;
        $result['platform_code'] = $platformCode;
        $result['clone_model_name'] = self::cloneModelLabel($aid, $modelCode, $platformCode, $config);
        return $result;
    }

    private static function voiceModelCodeFromService(string $serviceCode, bool $isClone = false): string
    {
        $service = strtolower(trim($serviceCode));
        if (in_array($service, ['minimax', 'hailuo', 'max'], true)) {
            return $isClone ? 'Max' : 'Max';
        }
        if (in_array($service, ['nwmax', 'aliminimax'], true)) {
            return 'NWMax';
        }
        if ($service === 'tts666') {
            return 'Pro';
        }
        if (in_array($service, ['niwaai', 'pro'], true)) {
            return 'Pro';
        }
        return 'SPro';
    }

    private static function resolveVoiceCloneChannel(array $data, string $requestedModelCode, array $input = []): array
    {
        $serviceName = trim(strval($data['voice_service_name'] ?? ''));
        $serviceCode = trim(strval(
            $data['voice_service_code']
            ?? ($input['voice_service_code'] ?? '')
        ));
        $mapped = self::voiceServiceCodeFromVendorName($serviceName);
        if ($mapped !== '') {
            $serviceCode = $mapped;
        }
        if ($serviceCode === '') {
            $serviceCode = trim(strval(DigitalAvatarModelService::defaultVoiceCloneConfig($requestedModelCode)['voice_service_code'] ?? ''));
        }
        $modelCode = self::voiceModelCodeFromService($serviceCode, true);
        if ($requestedModelCode !== '' && $serviceName === '') {
            $modelCode = $requestedModelCode;
        }
        return [
            'voice_service_code' => $serviceCode,
            'voice_service_name' => $serviceName,
            'model_code' => $modelCode,
            'target_model' => trim(strval($data['target_model'] ?? ($input['target_model'] ?? ''))),
        ];
    }

    private static function voiceServiceCodeFromVendorName(string $serviceName): string
    {
        $name = strtolower(trim($serviceName));
        if ($name === '') {
            return '';
        }
        if (strpos($name, 'spro') !== false || strpos($name, '方言') !== false || strpos($name, 'cosyvoice') !== false) {
            return 'SPro';
        }
        if (strpos($name, 'nwmax') !== false || strpos($name, 'aliminimax') !== false) {
            return 'NWMax';
        }
        if (strpos($name, 'max') !== false || strpos($name, 'minimax') !== false || strpos($name, 'hailuo') !== false || strpos($name, '海螺') !== false) {
            return 'Max';
        }
        if (strpos($name, 'tts666') !== false) {
            return 'Pro';
        }
        if (strpos($name, '专业') !== false || $name === 'niwaai' || strpos($name, '拟娲') !== false || $name === 'pro') {
            return 'Pro';
        }
        return '';
    }

    private static function voiceModelLabel(int $aid, string $modelCode, array $config = []): string
    {
        $modelCode = trim($modelCode);
        $serviceCode = trim(strval($config['voice_service_code'] ?? ''));
        if ($modelCode === '') {
            $modelCode = self::voiceModelCodeFromService($serviceCode, false);
        }

        $title = self::lookupCloneModelTitle($aid, $modelCode);
        if ($title !== '') {
            return $title;
        }

        if (class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoNiwaModelCatalog')) {
            $spec = \app\plugin\zhuibaoagent\common\ZhuibaoNiwaModelCatalog::resolve(
                $modelCode,
                $config,
                ['voice_service_code' => $serviceCode, 'task_type' => 'audio']
            );
            $label = trim(strval($spec['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return $modelCode !== '' ? $modelCode : $serviceCode;
    }

    private static function withVoiceCloneModelMeta(array $source, array $result): array
    {
        $aid = intval($source['aid'] ?? ($result['aid'] ?? 0));
        $config = self::decodeJsonValue($source['config_json'] ?? ($result['config_json'] ?? []));
        $serviceCode = trim(strval(
            $result['voice_service_code']
            ?? ($config['voice_service_code'] ?? ($source['voice_service_code'] ?? ''))
        ));
        $modelCode = trim(strval($result['model_code'] ?? ($source['model_code'] ?? ($config['model_code'] ?? ''))));
        $isClone = strval($result['owner_type'] ?? ($source['owner_type'] ?? '')) === 'member'
            || stripos($modelCode, 'clone') !== false;
        if ($modelCode === '') {
            $modelCode = self::voiceModelCodeFromService($serviceCode, $isClone);
        }
        if (!is_array($config)) {
            $config = [];
        }
        $config['voice_service_code'] = $serviceCode;
        $result['aid'] = $aid;
        $result['model_code'] = $modelCode;
        $result['voice_service_code'] = $serviceCode;
        $result['clone_model_name'] = self::voiceModelLabel($aid, $modelCode, $config);
        return $result;
    }

    private static function formatZhuibaoAvatar(array $profile, bool $isMember, int $aid = 0): array
    {
        $modelId = trim(strval($profile['model_id'] ?? ''));
        $aid = $aid > 0 ? $aid : intval($profile['aid'] ?? 0);
        $platformCode = trim(strval($profile['platform_code'] ?? 'VPro')) ?: 'VPro';
        $modelCode = trim(strval($profile['model_code'] ?? ''));
        return self::withAvatarCloneModelMeta($profile, [
            'id' => intval($profile['id'] ?? 0),
            'aid' => $aid,
            'local_id' => trim(strval($profile['local_id'] ?? '')),
            'resource_id' => $modelId,
            'model_id' => $modelId,
            'name' => trim(strval($profile['name'] ?? '')) ?: '平台数字人',
            'cover_url' => trim(strval($profile['sample_url'] ?? '')),
            'preview_url' => trim(strval($profile['sample_url'] ?? '')),
            'sample_url' => trim(strval($profile['sample_url'] ?? '')),
            'platform_code' => $platformCode,
            'provider_key' => 'niwaai',
            'model_code' => $modelCode,
            'status' => intval($profile['status'] ?? 1),
            'is_default' => intval($profile['is_default'] ?? 0),
            'visible_member_ids' => self::parseVisibleMemberIds($profile['visible_member_ids'] ?? ($isMember ? ($profile['uid'] ?? ($profile['member_id'] ?? 0)) : 0)),
            'owner_type' => $isMember ? 'member' : 'platform',
            'source_plugin' => 'zhuibaoagent',
            'ready' => $modelId !== '' ? 1 : 0,
        ]);
    }

    private static function formatZhuibaoVoice(array $profile, bool $isMember, int $aid = 0): array
    {
        $voiceId = trim(strval($profile['voice_id'] ?? ''));
        $aid = $aid > 0 ? $aid : intval($profile['aid'] ?? 0);
        $modelType = intval($profile['model_type'] ?? ($isMember ? 2 : 1)) === 2 ? 2 : 1;
        $serviceCode = trim(strval($profile['voice_service_code'] ?? ''));
        $modelCode = trim(strval($profile['model_code'] ?? ''));
        return self::withVoiceCloneModelMeta($profile, [
            'id' => intval($profile['id'] ?? 0),
            'aid' => $aid,
            'local_id' => trim(strval($profile['local_id'] ?? '')),
            'resource_id' => $voiceId,
            'voice_id' => $voiceId,
            'model_type' => $modelType,
            'name' => trim(strval($profile['name'] ?? '')) ?: '平台声音',
            'sample_url' => trim(strval($profile['sample_url'] ?? '')),
            'preview_url' => trim(strval($profile['sample_url'] ?? '')),
            'download_url' => trim(strval($profile['sample_url'] ?? '')),
            'sex' => intval($profile['sex'] ?? 0),
            'language' => trim(strval($profile['language'] ?? 'zh')) ?: 'zh',
            'instruction' => trim(strval($profile['instruction'] ?? '请用自然平稳的语气说话。')) ?: '请用自然平稳的语气说话。',
            'provider_key' => 'niwaai',
            'model_code' => $modelCode,
            'voice_service_code' => $serviceCode,
            'status' => intval($profile['status'] ?? 1),
            'is_default' => intval($profile['is_default'] ?? 0),
            'visible_member_ids' => self::parseVisibleMemberIds($profile['visible_member_ids'] ?? ($isMember ? ($profile['uid'] ?? ($profile['member_id'] ?? 0)) : 0)),
            'owner_type' => $isMember ? 'member' : 'platform',
            'source_plugin' => 'zhuibaoagent',
            'ready' => $voiceId !== '' ? 1 : 0,
        ]);
    }

    private static function mergeZhuibaoMemberResources(array $result, array $profiles, string $kind, int $aid = 0): array
    {
        $seenIds = [];
        foreach ($result as $item) {
            if (!is_array($item)) {
                continue;
            }
            $resourceId = trim(strval($item['resource_id'] ?? ''));
            if ($resourceId === '') {
                $resourceId = trim(strval($item[$kind === 'voice' ? 'voice_id' : 'model_id'] ?? ''));
            }
            if ($resourceId !== '') {
                $seenIds[$resourceId] = true;
            }
        }

        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }
            $resourceId = trim(strval($profile[$kind === 'voice' ? 'voice_id' : 'model_id'] ?? ''));
            if ($resourceId !== '' && isset($seenIds[$resourceId])) {
                continue;
            }
            if (trim(strval($profile['source_type'] ?? '')) === 'digitalavatar' && $resourceId === '') {
                continue;
            }
            if ($resourceId !== '') {
                $seenIds[$resourceId] = true;
            }
            $result[] = $kind === 'voice'
                ? self::formatZhuibaoVoice($profile, true, $aid)
                : self::formatZhuibaoAvatar($profile, true, $aid);
        }
        return $result;
    }

    private static function disableSyncedZhuibaoAvatar(int $aid, int $memberId, array $row): void
    {
        $config = self::decodeJsonValue($row['config_json'] ?? '');
        $modelId = trim(strval($config['model_id'] ?? ($config['avatar_model_id'] ?? '')));
        if ($modelId === '') {
            return;
        }
        try {
            Db::name('zhuibao_member_avatar_profile')
                ->where('aid', $aid)
                ->where('uid', $memberId)
                ->where('model_id', $modelId)
                ->where('source_type', 'digitalavatar')
                ->where('delete_time', 0)
                ->update([
                    'status' => 0,
                    'delete_time' => time(),
                    'update_time' => time(),
                ]);
        } catch (\Throwable $e) {
            // 追爆镜像表不存在时不影响数字人工作台自己的删除。
        }
    }

    private static function disableSyncedZhuibaoVoice(int $aid, int $memberId, array $row): void
    {
        $config = self::decodeJsonValue($row['config_json'] ?? '');
        $voiceId = trim(strval($config['voice_id'] ?? ($config['resource_id'] ?? '')));
        if ($voiceId === '') {
            return;
        }
        try {
            Db::name('zhuibao_member_voice_profile')
                ->where('aid', $aid)
                ->where('uid', $memberId)
                ->where('voice_id', $voiceId)
                ->where('source_type', 'digitalavatar')
                ->where('delete_time', 0)
                ->update([
                    'status' => 0,
                    'delete_time' => time(),
                    'update_time' => time(),
                ]);
        } catch (\Throwable $e) {
            // 追爆镜像表不存在时不影响数字人工作台自己的删除。
        }
    }

    private static function renameSyncedZhuibaoVoice(int $aid, int $memberId, array $row, string $name): void
    {
        $config = self::decodeJsonValue($row['config_json'] ?? '');
        $voiceId = trim(strval($config['voice_id'] ?? ($config['resource_id'] ?? '')));
        if ($voiceId === '') {
            return;
        }
        try {
            Db::name('zhuibao_member_voice_profile')
                ->where('aid', $aid)
                ->where('uid', $memberId)
                ->where('voice_id', $voiceId)
                ->where('source_type', 'digitalavatar')
                ->where('delete_time', 0)
                ->update([
                    'name' => $name,
                    'update_time' => time(),
                ]);
        } catch (\Throwable $e) {
            // 追爆镜像表不存在时不影响数字人工作台自己的改名。
        }
    }

    private static function renameLinkedDigitalAvatarVoice(int $aid, int $bid, int $memberId, array $zhuibaoRow, string $name): void
    {
        $voiceId = trim(strval($zhuibaoRow['voice_id'] ?? ''));
        if ($voiceId === '') {
            return;
        }
        try {
            $rows = Db::name('digitalavatar_member_voice')
                ->where('aid', $aid)
                ->where('bid', max(0, $bid))
                ->where('member_id', $memberId)
                ->where('status', 1)
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $config = self::decodeJsonValue($row['config_json'] ?? '');
                $linkedVoiceId = trim(strval($config['voice_id'] ?? ($config['resource_id'] ?? '')));
                if ($linkedVoiceId === '' || $linkedVoiceId !== $voiceId) {
                    continue;
                }
                Db::name('digitalavatar_member_voice')->where('id', intval($row['id'] ?? 0))->update([
                    'name' => $name,
                    'update_time' => time(),
                ]);
            }
        } catch (\Throwable $e) {
            // 数字人声音表不可用时不影响追爆侧改名。
        }
    }

    private static function findVoiceProfileByVendorId(int $aid, int $bid, int $memberId, string $vendorVoiceId): array
    {
        $vendorVoiceId = trim($vendorVoiceId);
        if ($vendorVoiceId === '') {
            return [];
        }

        $rows = Db::name('digitalavatar_member_voice')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->order('id desc')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $formatted = self::formatMemberVoice(is_array($row) ? $row : []);
            if (trim(strval($formatted['voice_id'] ?? '')) === $vendorVoiceId) {
                return $formatted;
            }
        }

        try {
            if (class_exists('app\\plugin\\zhuibaoagent\\common\\ZhuibaoProviderService')) {
                $profiles = \app\plugin\zhuibaoagent\common\ZhuibaoProviderService::getMemberVoiceProfiles(
                    $aid,
                    $memberId,
                    $bid,
                    false
                );
                $matched = self::matchResourceProfile($profiles, '', $vendorVoiceId, 0, 'voice_id');
                if (!empty($matched)) {
                    $isMember = strval($matched['owner_type'] ?? '') === 'member';
                    $formatted = self::formatZhuibaoVoice($matched, $isMember, $aid);
                    if ($isMember || self::isOfficialVoiceAllowedForMember($aid, $memberId, $formatted)) {
                        return $formatted;
                    }
                }
            }
        } catch (\Throwable $e) {
            // 追爆声音表不可用时只使用数字人插件自己的音色。
        }

        return [];
    }

    private static function idFromPrefixedLocalId(string $localId, string $prefix): int
    {
        if ($localId === '' || $prefix === '' || strpos($localId, $prefix) !== 0) {
            return 0;
        }
        return intval(substr($localId, strlen($prefix)));
    }

    private static function avatarVendorId(array $avatar): string
    {
        return trim(strval($avatar['model_id'] ?? ($avatar['resource_id'] ?? '')));
    }

    public static function isXproAvatar(array $avatar): bool
    {
        return self::avatarVideoFamily($avatar) === 'xpro';
    }

    public static function isLocalImageAvatarClone(string $modelCode, array $input = []): bool
    {
        $hint = strtolower(trim($modelCode . ' ' . strval($input['platform_code'] ?? '') . ' ' . strval($input['model_code'] ?? '')));
        if (strpos($hint, 'xpro') !== false) {
            return true;
        }
        $spec = DigitalAvatarNiwaCatalog::resolve($modelCode, $input, ['task_type' => 'avatar_clone']);
        return strval($spec['family'] ?? '') === 'xpro';
    }

    public static function localImageAvatarUrl(array $input): string
    {
        return self::pickImageUrl([
            $input['cover_url'] ?? '',
            $input['img_url'] ?? '',
            $input['sample_url'] ?? '',
            $input['avatar_url'] ?? '',
        ]);
    }

    public static function assertLocalImageAvatarCloneSample(array $input): string
    {
        $sample = trim(strval($input['sample_url'] ?? ''));
        if ($sample !== '' && self::isVideoUrl($sample)) {
            throw new \RuntimeException('XPro 图片形象只能上传图片格式文件');
        }
        $url = self::localImageAvatarUrl($input);
        if ($url === '') {
            throw new \RuntimeException('请上传 JPG、PNG、WEBP 等图片，XPro 形象不支持视频');
        }
        return $url;
    }

    public static function buildLocalImageAvatarCloneResult(array $input): array
    {
        $imageUrl = self::assertLocalImageAvatarCloneSample($input);
        return [
            'url' => $imageUrl,
            'cover_url' => $imageUrl,
            'avatar_url' => $imageUrl,
            'local_only' => 1,
            'data' => [
                'platform_code' => 'XPro',
                'cover_url' => $imageUrl,
                'avatar_url' => $imageUrl,
                'local_only' => 1,
            ],
        ];
    }

    public static function avatarIsUsable(array $avatar): bool
    {
        if ($avatar === []) {
            return false;
        }
        if (self::avatarVendorId($avatar) !== '') {
            return true;
        }
        if (!self::isXproAvatar($avatar)) {
            return false;
        }
        return self::pickImageUrl([
            $avatar['cover_url'] ?? '',
            $avatar['avatar_url'] ?? '',
            $avatar['img'] ?? '',
            $avatar['sample_url'] ?? '',
        ]) !== '';
    }

    private static function pickReadyMemberAvatar(int $aid, int $bid, int $memberId): array
    {
        $rows = Db::name('digitalavatar_member_avatar')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->order('id desc')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $formatted = self::formatMemberAvatar($row);
            if (self::avatarIsUsable($formatted)) {
                return $formatted;
            }
        }
        return [];
    }

    private static function pickReadyPublicAvatar(int $aid, int $memberId = 0): array
    {
        $query = Db::name('digitalavatar_public_avatar')
            ->where('aid', $aid)
            ->where('status', 1);
        self::applyPublicAvatarVisibility($query, $memberId);
        $rows = $query->order('sort asc,id desc')->select()->toArray();
        foreach ($rows as $row) {
            $formatted = self::formatPublicAvatar($row);
            if (self::isPublicAvatarVisibleToMember($formatted, $memberId) && self::avatarVendorId($formatted) !== '') {
                return $formatted;
            }
        }
        return [];
    }

    private static function findMemberAvatarRow(int $aid, int $bid, int $memberId, int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $row = Db::name('digitalavatar_member_avatar')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('id', $id)
            ->where('status', 1)
            ->find();
        return is_array($row) ? $row : [];
    }

    private static function findMemberVoiceRow(int $aid, int $bid, int $memberId, int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $row = Db::name('digitalavatar_member_voice')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('id', $id)
            ->where('status', 1)
            ->find();
        return is_array($row) ? $row : [];
    }

    private static function matchResourceProfile(array $profiles, string $localId, string $resourceId, int $id, string $resourceKey): array
    {
        if ($localId !== '') {
            foreach ($profiles as $profile) {
                if (!is_array($profile)) {
                    continue;
                }
                if (trim(strval($profile['local_id'] ?? '')) === $localId) {
                    return $profile;
                }
            }
        }
        if ($resourceId !== '') {
            foreach ($profiles as $profile) {
                if (!is_array($profile)) {
                    continue;
                }
                if (trim(strval($profile[$resourceKey] ?? '')) === $resourceId) {
                    return $profile;
                }
            }
        }
        if ($id > 0) {
            foreach ($profiles as $profile) {
                if (!is_array($profile)) {
                    continue;
                }
                if (intval($profile['id'] ?? 0) === $id) {
                    return $profile;
                }
            }
        }
        return [];
    }

    private static function firstSideAd(array $ads): ?array
    {
        foreach ($ads as $ad) {
            if (strval($ad['placement'] ?? '') === 'side') {
                return $ad;
            }
        }
        return null;
    }

    public static function getOverview(int $aid, int $bid = 0): array
    {
        $taskQuery = Db::name('digitalavatar_task')->where('aid', $aid)->where('deleted_time', 0);
        $creditQuery = Db::name('digitalavatar_member_credit')->where('aid', $aid);
        $checkinQuery = Db::name('digitalavatar_checkin_log')->where('aid', $aid);
        $inviteQuery = Db::name('digitalavatar_invite_log')->where('aid', $aid);
        if ($bid > 0) {
            $taskQuery->where('bid', $bid);
            $creditQuery->where('bid', $bid);
            $checkinQuery->where('bid', $bid);
            $inviteQuery->where('bid', $bid);
        }
        $todayCheckin = 0;
        $monthCheckin = 0;
        $todayInvite = 0;
        $monthInvite = 0;
        try {
            $todayCheckin = intval((clone $checkinQuery)->where('checkin_date', date('Y-m-d'))->count());
            $monthCheckin = intval((clone $checkinQuery)->where('checkin_date', '>=', date('Y-m-01'))->count());
        } catch (\Throwable $e) {
        }
        try {
            $todayInvite = intval((clone $inviteQuery)->where('invite_date', date('Y-m-d'))->count());
            $monthInvite = intval((clone $inviteQuery)->where('invite_date', '>=', date('Y-m-01'))->count());
        } catch (\Throwable $e) {
        }
        return [
            'task_total' => intval((clone $taskQuery)->count()),
            'pending_task_total' => intval((clone $taskQuery)->where('status', 0)->count()),
            'running_task_total' => intval((clone $taskQuery)->where('status', 1)->count()),
            'success_task_total' => intval((clone $taskQuery)->where('status', 2)->count()),
            'failed_task_total' => intval((clone $taskQuery)->where('status', 3)->count()),
            'member_total' => intval((clone $creditQuery)->count()),
            'credit_balance_total' => DigitalAvatarCreditService::creditAmount((clone $creditQuery)->sum('credit_balance')),
            'credit_used_total' => DigitalAvatarCreditService::creditAmount((clone $creditQuery)->sum('credit_used')),
            'package_total' => intval(Db::name('digitalavatar_package')->where('aid', $aid)->where('status', 1)->count()),
            'today_checkin_total' => $todayCheckin,
            'month_checkin_total' => $monthCheckin,
            'today_invite_total' => $todayInvite,
            'month_invite_total' => $monthInvite,
        ];
    }

    private static function savePublicMedia(int $aid, array $info, string $type, string $name): array
    {
        $id = intval($info['id'] ?? 0);
        $table = $type === 'avatar' ? 'digitalavatar_public_avatar' : 'digitalavatar_public_voice';
        $config = is_array($info['config'] ?? null) ? $info['config'] : [];
        if ($type === 'voice') {
            $voiceId = trim(strval($info['voice_id'] ?? ($config['voice_id'] ?? ($config['resource_id'] ?? ''))));
            if ($voiceId !== '') {
                $config['voice_id'] = $voiceId;
            }
            $config['model_type'] = intval($info['model_type'] ?? ($config['model_type'] ?? 1)) === 2 ? 2 : 1;
        }
        $data = [
            'aid' => $aid,
            'name' => mb_substr($name, 0, 120),
            'provider_key' => trim(strval($info['provider_key'] ?? '')),
            'model_code' => trim(strval($info['model_code'] ?? '')),
            'sort' => intval($info['sort'] ?? 0),
            'status' => intval($info['status'] ?? 1) === 1 ? 1 : 0,
            'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'update_time' => time(),
        ];
        if ($type === 'avatar') {
            $data['cover_url'] = trim(strval($info['cover_url'] ?? ''));
            $data['preview_url'] = trim(strval($info['preview_url'] ?? ''));
        } else {
            $data['preview_url'] = trim(strval($info['preview_url'] ?? ''));
        }
        $data['visible_member_ids'] = self::encodeVisibleMemberIds(
            $info['visible_member_ids'] ?? ($info['owner_member_ids'] ?? ($info['visible_member_id'] ?? '0'))
        );

        if ($id > 0) {
            $exists = Db::name($table)->where('aid', $aid)->where('id', $id)->find();
            if (!$exists) {
                throw new \RuntimeException('公共资源不存在');
            }
            Db::name($table)->where('id', $id)->update($data);
        } else {
            $data['create_time'] = time();
            $id = intval(Db::name($table)->insertGetId($data));
        }
        return Db::name($table)->where('aid', $aid)->where('id', $id)->find() ?: [];
    }

    private static function paginateSimple($query, int $page, int $limit, array $fields): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $total = intval((clone $query)->count());
        $list = $query->field($fields)->page($page, $limit)->order('sort asc,id desc')->select()->toArray();
        return ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    private static function decodeJsonValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function firstValue(array $result, array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim(strval($result[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
            $value = trim(strval($data[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
            if (is_array($data['data'] ?? null)) {
                $value = trim(strval($data['data'][$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    private static function formatPackageOrder(array $order, array $package): array
    {
        $payorderId = intval($order['payorder_id'] ?? 0);
        return [
            'order_id' => intval($order['id'] ?? 0),
            'ordernum' => strval($order['ordernum'] ?? ''),
            'package_id' => intval($order['package_id'] ?? 0),
            'package_title' => strval($package['title'] ?? ''),
            'price' => number_format(floatval($order['price'] ?? $package['price'] ?? 0), 2, '.', ''),
            'credit_amount' => DigitalAvatarCreditService::creditAmount($order['credit_amount'] ?? 0),
            'status' => intval($order['status'] ?? 0),
            'need_pay' => intval($order['status'] ?? 0) === 0 && $payorderId > 0 ? 1 : 0,
            'payorderid' => $payorderId,
        ];
    }
}
