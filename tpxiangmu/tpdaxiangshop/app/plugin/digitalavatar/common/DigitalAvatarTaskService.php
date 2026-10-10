<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;

class DigitalAvatarTaskService
{
    private static $taskModelNameCache = [];
    private static $submitDepth = 0;

    private const TASK_TYPES = [
        'avatar_video' => '数字人视频',
        'voice_clone' => '语音克隆',
        'avatar_clone' => '数字人克隆',
        'audio' => '语音合成',
        'video' => '视频生成',
        'image' => '图片生成',
    ];

    public static function createTask(int $aid, int $bid, int $memberId, array $input): array
    {
        $settings = DigitalAvatarCreditService::ensureSettings($aid);
        if (intval($settings['enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('AI数字人功能暂未开放');
        }
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }

        $taskType = trim(strval($input['task_type'] ?? ''));
        if (!isset(self::TASK_TYPES[$taskType])) {
            throw new \RuntimeException('任务类型不支持');
        }

        if ($taskType === 'video' && intval($settings['creative_video_enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('创意视频功能暂未开放');
        }
        if (in_array($taskType, ['avatar_video', 'avatar_clone'], true) && intval($settings['avatar_enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('数字人功能暂未开放');
        }
        if (in_array($taskType, ['audio', 'voice_clone'], true) && intval($settings['audio_enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('音频功能暂未开放');
        }
        if ($taskType === 'image' && intval($settings['image_enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('图片创作功能暂未开放');
        }

        if ($taskType === 'image') {
            $source = trim(strval($input['source'] ?? ''));
            $isBatchItem = intval($input['_image_batch_item'] ?? 0) === 1;
            if (!$isBatchItem && $source !== 'image_agent') {
                $batch = max(1, min(5, intval($input['submit_count'] ?? ($input['count'] ?? ($input['gen_count'] ?? ($input['n'] ?? 1))))));
                if ($batch > 1) {
                    return self::createImageBatchTasks($aid, $bid, $memberId, $input, $batch);
                }
            }
            $input['count'] = 1;
            $input['gen_count'] = 1;
            $input['n'] = 1;
            unset($input['_image_batch_item']);
        }

        $requestId = trim(strval($input['request_id'] ?? ''));
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(16));
        }
        if (strlen($requestId) > 80) {
            throw new \RuntimeException('request_id 长度不能超过 80');
        }

        $existing = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('request_id', $requestId)
            ->where('deleted_time', 0)
            ->find();
        if ($existing) {
            return self::formatTaskWithQueue($existing);
        }

        $requestedModelCode = trim(strval($input['model_code'] ?? ''));
        if ($taskType === 'image' && trim(strval($input['source'] ?? '')) === 'image_agent') {
            $stage = DigitalAvatarImageAgentService::normalizeStage(strval($input['image_stage'] ?? 'draft'));
            $profile = DigitalAvatarImageAgentService::imageStageProfile($aid, $stage);
            if (intval($profile['ready'] ?? 0) !== 1 || trim(strval($profile['model_code'] ?? '')) === '') {
                throw new \RuntimeException($stage === 'final' ? '请先在系统设置绑定高清模型' : '请先在系统设置绑定草稿模型');
            }
            $input['image_stage'] = $stage;
            $input['image_model'] = strval($profile['model_code']);
            $requestedModelCode = strval($profile['model_code']);
            try {
                $configuredModel = intval($profile['model_id'] ?? 0) > 0
                    ? DigitalAvatarModelService::resolveById($aid, intval($profile['model_id']), 'image')
                    : DigitalAvatarModelService::resolveForTask($aid, $taskType, $requestedModelCode);
            } catch (\Throwable $e) {
                throw new \RuntimeException($stage === 'final' ? '高清模型不可用：' . $e->getMessage() : '草稿模型不可用：' . $e->getMessage());
            }
        } else {
            $configuredModel = DigitalAvatarModelService::resolveForTask($aid, $taskType, $requestedModelCode);
        }
        $providerModel = [
            'provider_key' => $configuredModel['provider_key'],
            'model_code' => $configuredModel['model_code'],
            'provider_name' => $configuredModel['provider_name'],
        ];
        $inputPayload = self::normalizePayload($input);
        if ($taskType === 'avatar_clone' && DigitalAvatarService::isLocalImageAvatarClone(strval($providerModel['model_code'] ?? ''), $inputPayload)) {
            $imageUrl = DigitalAvatarService::assertLocalImageAvatarCloneSample($inputPayload);
            $inputPayload['platform_code'] = 'XPro';
            $inputPayload['sample_url'] = $imageUrl;
            $inputPayload['cover_url'] = $imageUrl;
            $inputPayload['img_url'] = $imageUrl;
            $inputPayload['local_clone'] = 1;
        }
        if (in_array($taskType, ['audio', 'avatar_video'], true)) {
            $voice = DigitalAvatarService::resolveVoiceForSynthesis($aid, $bid, $memberId, $inputPayload);
            $resolvedVoiceId = trim(strval($voice['voice_id'] ?? ''));
            if ($resolvedVoiceId !== '') {
                $inputPayload['resolved_voice_id'] = $resolvedVoiceId;
                $inputPayload['resolved_voice_name'] = trim(strval($voice['name'] ?? ''));
                $inputPayload['resolved_voice_model_code'] = DigitalAvatarService::audioModelCodeForVoice($voice, '');
                $inputPayload['selected_voice_id'] = $resolvedVoiceId;
                $inputPayload['voice_id'] = $resolvedVoiceId;
            }
            $avatar = DigitalAvatarService::resolveAvatarSelection($aid, $bid, $memberId, $inputPayload);
            $resolvedModelId = trim(strval($avatar['model_id'] ?? ''));
            if ($resolvedModelId !== '') {
                $inputPayload['resolved_avatar_model_id'] = $resolvedModelId;
                $inputPayload['resolved_avatar_name'] = trim(strval($avatar['name'] ?? ''));
                $inputPayload['selected_avatar_model_id'] = $resolvedModelId;
            } elseif ($taskType === 'avatar_video' && !DigitalAvatarService::avatarIsUsable(is_array($avatar) ? $avatar : [])) {
                throw new \RuntimeException('所选数字人尚未克隆成功（缺少拟娲形象ID）。请选择「默认数字人」或克隆成功的形象后再生成。');
            }
            if ($taskType === 'avatar_video' && $avatar) {
                $clamped = DigitalAvatarService::clampAvatarVideoModelCode(
                    $aid,
                    $avatar,
                    strval($providerModel['model_code'] ?? '')
                );
                if ($clamped !== '' && strcasecmp($clamped, strval($providerModel['model_code'] ?? '')) !== 0) {
                    $configuredModel = DigitalAvatarModelService::resolveForTask($aid, $taskType, $clamped);
                    $providerModel = [
                        'provider_key' => $configuredModel['provider_key'],
                        'model_code' => $configuredModel['model_code'],
                        'provider_name' => $configuredModel['provider_name'],
                    ];
                }
                $inputPayload['model_code'] = strval($providerModel['model_code'] ?? '');
                $inputPayload['avatar_clone_model_code'] = strval($avatar['model_code'] ?? '');
                $inputPayload['avatar_platform_code'] = strval($avatar['platform_code'] ?? '');
                if (DigitalAvatarService::isXproAvatar($avatar)) {
                    $imageUrl = DigitalAvatarService::pickImageUrl([
                        $avatar['cover_url'] ?? '',
                        $avatar['avatar_url'] ?? '',
                        $avatar['img'] ?? '',
                        $avatar['sample_url'] ?? '',
                    ]);
                    if ($imageUrl !== '') {
                        $inputPayload['img_url'] = $imageUrl;
                        $inputPayload['cover_url'] = $imageUrl;
                    }
                }
            }
        }
        if ($taskType === 'avatar_video') {
            $audioSeconds = DigitalAvatarCreditService::resolveAudioSeconds($aid, $bid, $memberId, $inputPayload);
            if ($audioSeconds > 0) {
                $inputPayload['audio_seconds'] = $audioSeconds;
                $inputPayload['audio_duration'] = $audioSeconds;
            }
        }
        $inputPayload = DigitalAvatarCreditService::bindSelectedApiModel(
            $aid,
            $taskType,
            strval($providerModel['model_code'] ?? ''),
            $inputPayload,
            is_array($configuredModel['config'] ?? null) ? $configuredModel['config'] : []
        );
        $cost = DigitalAvatarCreditService::computeCost(
            $aid,
            $taskType,
            $providerModel['model_code'],
            $inputPayload
        );
        if (intval($settings['charge_mode'] ?? 2) === 1) {
            $cost['credit_cost'] = 0;
        }
        $chargeMode = DigitalAvatarCreditService::normalizeBillingMode(strval($cost['billing_mode'] ?? 'fixed'));
        if (
            $taskType === 'avatar_video'
            && in_array($chargeMode, ['seconds', 'minutes'], true)
            && floatval($inputPayload['audio_seconds'] ?? 0) <= 0
            && intval($inputPayload['skip_tts'] ?? 0) === 1
        ) {
            throw new \RuntimeException('无法获取所选音频时长，请重新选择音频后再生成');
        }
        $inputPayload['billing_quantity'] = floatval($cost['quantity'] ?? 0);
        $inputPayload['billing_mode'] = $chargeMode;
        if (trim(strval($inputPayload['platform_code'] ?? '')) === '' && trim(strval($cost['platform_code'] ?? '')) !== '') {
            $inputPayload['platform_code'] = strval($cost['platform_code']);
        }
        if (trim(strval($inputPayload['api_model'] ?? '')) === '' && trim(strval($cost['api_model'] ?? '')) !== '') {
            $inputPayload['api_model'] = strval($cost['api_model']);
        }

        $now = time();
        $taskId = intval(Db::name('digitalavatar_task')->insertGetId([
            'aid' => $aid,
            'bid' => max(0, $bid),
            'member_id' => $memberId,
            'request_id' => $requestId,
            'task_type' => $taskType,
            'provider_key' => $providerModel['provider_key'],
            'model_code' => $providerModel['model_code'],
            'title' => mb_substr(trim(strval($input['title'] ?? '')) ?: self::TASK_TYPES[$taskType], 0, 191),
            'status' => 0,
            'progress' => 0,
            'credit_cost' => DigitalAvatarCreditService::creditStore($cost['credit_cost'] ?? 0),
            'credit_status' => 0,
            'credit_source' => '',
            'quota_units' => 0,
            'input_json' => self::encodeJson($inputPayload),
            'result_json' => '',
            'error_msg' => '',
            'create_time' => $now,
            'update_time' => $now,
            'started_time' => 0,
            'finished_time' => 0,
            'deleted_time' => 0,
        ]));
        if ($taskId <= 0) {
            throw new \RuntimeException('任务创建失败');
        }
        try {
            DigitalAvatarConversationService::bindTask(
                $aid,
                $bid,
                $memberId,
                $taskId,
                intval($input['conversation_id'] ?? ($inputPayload['conversation_id'] ?? 0)),
                mb_substr(trim(strval($input['title'] ?? '')) ?: self::TASK_TYPES[$taskType], 0, 191),
                $taskType,
                strval($input['conversation_mode'] ?? ($inputPayload['conversation_mode'] ?? ''))
            );
        } catch (\Throwable $e) {
        }

        $creditCost = DigitalAvatarCreditService::creditAmount($cost['credit_cost'] ?? 0);
        $quotaUnits = max(0, intval($cost['quota_units'] ?? 0));
        $dailyReserved = 0;
        $memberFrozen = false;
        $lotReserve = [];

        try {
            $taskRow = Db::name('digitalavatar_task')->where('id', $taskId)->find() ?: [];
            if ($creditCost > 0 || $quotaUnits > 0) {
                $dailyReserved = DigitalAvatarCreditService::reserveDailyQuota(
                    $aid,
                    $bid,
                    $memberId,
                    $providerModel['model_code'],
                    $quotaUnits
                );
                $remainUnits = max(0, $quotaUnits - $dailyReserved);
                $mode = DigitalAvatarCreditService::normalizeBillingMode(strval($cost['billing_mode'] ?? 'fixed'));
                $origQty = max(0, intval(ceil(floatval($cost['quantity'] ?? $quotaUnits))));
                if ($mode === 'fixed') {
                    $origQty = $quotaUnits > 0 ? $quotaUnits : 1;
                }
                $remainQty = $quotaUnits > 0
                    ? (int)round($origQty * $remainUnits / $quotaUnits)
                    : $origQty;
                if ($dailyReserved > 0 && $remainUnits <= 0 && $remainQty <= 0) {
                    $inputPayload['daily_quota_units'] = $dailyReserved;
                    $inputPayload['charge_breakdown'] = [
                        'daily' => $dailyReserved,
                        'reward_quota' => 0,
                        'boost_quota' => 0,
                        'credit' => 0,
                    ];
                    Db::name('digitalavatar_task')->where('id', $taskId)->update([
                        'credit_source' => 'daily_quota',
                        'quota_units' => $dailyReserved,
                        'credit_cost' => 0,
                        'credit_status' => 1,
                        'input_json' => self::encodeJson($inputPayload),
                        'update_time' => time(),
                    ]);
                } else {
                    $cost['remaining_quantity'] = $remainQty;
                    $cost['skip_base'] = $dailyReserved > 0;
                    $lotReserve = DigitalAvatarLotService::reserveForTask($aid, $bid, $memberId, $taskRow, $cost);
                    $creditCost = DigitalAvatarCreditService::creditAmount($lotReserve['credit_cost'] ?? $creditCost);
                    $quotaTaken = intval($lotReserve['quota_taken'] ?? 0);
                    $inputPayload['lot_consume'] = $lotReserve['quota_lots'] ?? [];
                    $inputPayload['quota_key'] = strval($lotReserve['quota_key'] ?? '');
                    $inputPayload['quota_taken'] = $quotaTaken;
                    $inputPayload['daily_quota_units'] = $dailyReserved;
                    $inputPayload['api_model'] = strval($cost['api_model'] ?? '');
                    $inputPayload['charge_breakdown'] = [
                        'daily' => $dailyReserved,
                        'reward_quota' => intval($lotReserve['reward_quota_taken'] ?? 0),
                        'boost_quota' => intval($lotReserve['boost_quota_taken'] ?? 0),
                        'credit' => $creditCost,
                    ];
                    $creditSource = strval($lotReserve['credit_source'] ?? 'member_credit');
                    if ($dailyReserved > 0 && ($creditCost > 0 || $quotaTaken > 0)) {
                        $creditSource = 'mixed';
                    } elseif ($dailyReserved > 0) {
                        $creditSource = 'daily_quota';
                    }
                    if ($creditCost > 0) {
                        DigitalAvatarCreditService::freeze(
                            $aid,
                            $bid,
                            $memberId,
                            $creditCost,
                            'task',
                            $taskId,
                            'AI任务排队冻结额度'
                        );
                        $memberFrozen = true;
                    }
                    Db::name('digitalavatar_task')->where('id', $taskId)->update([
                        'credit_cost' => DigitalAvatarCreditService::creditStore($creditCost),
                        'credit_source' => $creditSource,
                        'quota_units' => $dailyReserved + $quotaTaken,
                        'credit_status' => 1,
                        'input_json' => self::encodeJson($inputPayload),
                        'update_time' => time(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            if (!empty($lotReserve['quota_lots'])) {
                $taskRow = Db::name('digitalavatar_task')->where('id', $taskId)->find() ?: [];
                $decoded = self::decodeJson($taskRow['input_json'] ?? '');
                $decoded['lot_consume'] = $lotReserve['quota_lots'];
                DigitalAvatarLotService::refundTaskLots(['input_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
            if ($memberFrozen) {
                Db::name('digitalavatar_task')->where('id', $taskId)->update([
                    'credit_source' => 'member_credit',
                    'credit_status' => 1,
                    'update_time' => time(),
                ]);
                DigitalAvatarCreditService::refund($aid, $taskId);
            }
            if ($dailyReserved > 0) {
                DigitalAvatarCreditService::settleDailyQuota($aid, $taskId, false, $dailyReserved);
                $dailyReserved = 0;
            }
            Db::name('digitalavatar_task')->where('id', $taskId)->delete();
            throw $e;
        }

        $task = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if ($taskType === 'avatar_clone' && DigitalAvatarService::isLocalImageAvatarClone(strval($providerModel['model_code'] ?? ''), $inputPayload)) {
            try {
                $result = DigitalAvatarService::buildLocalImageAvatarCloneResult($inputPayload);
                DigitalAvatarService::saveMemberAvatarFromTask($aid, $bid, $memberId, is_array($task) ? $task : [], $result);
                return self::completeTask($aid, $taskId, $result);
            } catch (\Throwable $e) {
                self::failTask($aid, $taskId, $e->getMessage());
                throw $e;
            }
        }
        try {
            $deferToWorker = DigitalAvatarProviderService::shouldDeferImageExecution($aid, [
                'task_type' => $taskType,
                'provider_key' => strval($providerModel['provider_key'] ?? ''),
            ], $inputPayload);
            if (!$deferToWorker) {
                self::submitQueuedForMember($aid, $bid, $memberId, 1);
            }
        } catch (\Throwable $e) {
        }
        $task = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        return self::formatTaskWithQueue($task ?: []);
    }

    private static function createImageBatchTasks(int $aid, int $bid, int $memberId, array $input, int $batch): array
    {
        $batch = max(1, min(5, $batch));
        $baseRequestId = trim(strval($input['request_id'] ?? ''));
        if ($baseRequestId === '') {
            $baseRequestId = bin2hex(random_bytes(8));
        }
        $batchId = substr($baseRequestId, 0, 64);
        $baseTitle = mb_substr(trim(strval($input['title'] ?? '')) ?: '图片生成', 0, 160);
        $created = [];
        $failed = null;
        for ($index = 1; $index <= $batch; $index++) {
            $item = $input;
            $item['_image_batch_item'] = 1;
            $item['count'] = 1;
            $item['gen_count'] = 1;
            $item['n'] = 1;
            $item['request_id'] = substr($batchId . '-' . $index, 0, 80);
            $item['title'] = $baseTitle . ' (' . $index . '/' . $batch . ')';
            $item['image_batch_id'] = $batchId;
            $item['image_batch_index'] = $index;
            $item['image_batch_total'] = $batch;
            try {
                $created[] = self::createTask($aid, $bid, $memberId, $item);
                if (intval($item['conversation_id'] ?? 0) <= 0) {
                    $newId = intval($created[count($created) - 1]['conversation_id'] ?? 0);
                    if ($newId > 0) {
                        $input['conversation_id'] = $newId;
                    }
                }
            } catch (\Throwable $e) {
                $failed = $e;
                break;
            }
        }
        if ($created === []) {
            throw $failed ?: new \RuntimeException('图片任务创建失败');
        }
        $last = $created[count($created) - 1];
        $last['batch_id'] = $batchId;
        $last['batch_count'] = $batch;
        $last['batch_created'] = count($created);
        $last['batch_ids'] = array_values(array_filter(array_map(static function ($row) {
            return intval($row['id'] ?? 0);
        }, $created)));
        $last['batch_tasks'] = $created;
        if ($failed) {
            throw new \RuntimeException('已创建 ' . count($created) . '/' . $batch . ' 个图片任务，后续失败：' . $failed->getMessage());
        }
        return $last;
    }

    /**
     * 由统一 async:work 调用，处理数据库中的数字人任务。
     *
     * 任务表是最终队列，Redis 只作为可选的加速通知，不依赖 Redis 才能执行。
     */
    public static function processQueue(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $now = time();
        try {
            DigitalAvatarLotService::expireLots();
        } catch (\Throwable $e) {
        }
        $handled = 0;
        $success = 0;
        $failed = 0;
        $pending = 0;
        $skipped = 0;
        $items = [];

        $runningRows = Db::name('digitalavatar_task')
            ->where('deleted_time', 0)
            ->where('status', 1)
            ->order('update_time asc,id asc')
            ->limit(max($limit * 4, 20))
            ->select()
            ->toArray();

        foreach ($runningRows as $row) {
            if ($handled >= $limit) {
                break;
            }
            $resultJson = self::decodeJson($row['result_json'] ?? '');
            if (intval($resultJson['next_poll_time'] ?? 0) > $now) {
                $skipped++;
                continue;
            }
            if (!self::claimRunningTask($row, $now)) {
                $skipped++;
                continue;
            }
            $outcome = self::executeClaimedTask($row);
            $handled++;
            $success += intval($outcome['success'] ?? 0);
            $failed += intval($outcome['failed'] ?? 0);
            $pending += intval($outcome['pending'] ?? 0);
            $items[] = $outcome['item'] ?? [];
            if (!empty($outcome['success']) || !empty($outcome['failed'])) {
                try {
                    self::submitQueuedForMember(
                        intval($row['aid'] ?? 0),
                        intval($row['bid'] ?? 0),
                        intval($row['member_id'] ?? 0),
                        1
                    );
                } catch (\Throwable $e) {
                }
            }
        }

        $startLimit = max($limit, 20);
        $newStarts = 0;
        $pendingMembers = [];
        try {
            $pendingMembers = Db::name('digitalavatar_task')
                ->where('deleted_time', 0)
                ->whereIn('status', [0, 5])
                ->field('aid,bid,member_id,min(create_time) as first_time')
                ->group('aid,bid,member_id')
                ->order('first_time asc')
                ->limit(50)
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            $pendingMembers = [];
        }
        if ($pendingMembers === []) {
            $pendingRows = Db::name('digitalavatar_task')
                ->where('deleted_time', 0)
                ->whereIn('status', [0, 5])
                ->order('create_time asc,id asc')
                ->limit(200)
                ->select()
                ->toArray();
            $seen = [];
            foreach ($pendingRows as $row) {
                $memberKey = intval($row['aid'] ?? 0) . ':' . intval($row['bid'] ?? 0) . ':' . intval($row['member_id'] ?? 0);
                if (isset($seen[$memberKey])) {
                    continue;
                }
                $seen[$memberKey] = 1;
                $pendingMembers[] = $row;
            }
        }
        foreach ($pendingMembers as $member) {
            if ($newStarts >= $startLimit) {
                break;
            }
            $started = self::submitQueuedForMember(
                intval($member['aid'] ?? 0),
                intval($member['bid'] ?? 0),
                intval($member['member_id'] ?? 0),
                $startLimit - $newStarts
            );
            if ($started <= 0) {
                $skipped++;
                continue;
            }
            $newStarts += $started;
            $handled += $started;
        }

        return [
            'total' => $handled,
            'success' => $success,
            'failed' => $failed,
            'pending' => $pending,
            'skipped' => $skipped,
            'items' => $items,
        ];
    }

    public static function getMemberList(int $aid, int $bid, int $memberId, int $page, int $limit, string $keyword = '', string $status = '', string $taskType = '', string $modelCode = '', array $extra = []): array
    {
        if (self::isHistoryAudioQuery($taskType, $extra)) {
            return self::getHistoryAudioMemberList($aid, $bid, $memberId, $page, $limit, $keyword, $status, $modelCode, $extra);
        }
        try {
            self::refreshMemberRunningTasks($aid, $bid, $memberId, 8);
        } catch (\Throwable $e) {
        }
        if (self::shouldIncludePreviewAudio($taskType, $extra)) {
            return self::getMergedMemberList($aid, $bid, $memberId, $page, $limit, $keyword, $status, $taskType, $modelCode, $extra);
        }
        $query = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('deleted_time', 0);
        self::applyFilters($query, $keyword, $status, $taskType, $modelCode, $extra);
        return self::paginate($query, $page, $limit);
    }

    public static function getAdminList(int $aid, int $page, int $limit, string $keyword = '', string $status = '', string $deleted = ''): array
    {
        $query = Db::name('digitalavatar_task')->where('aid', $aid);
        if ($deleted === '1') {
            $query->where('deleted_time', '>', 0);
        } elseif ($deleted !== 'all') {
            $query->where('deleted_time', 0);
        }
        self::applyFilters($query, $keyword, $status);
        $result = self::paginate($query, $page, $limit);

        $memberIds = array_values(array_unique(array_filter(array_map('intval', array_column($result['list'], 'member_id')))));
        $members = [];
        if (!empty($memberIds)) {
            foreach (Db::name('member')->where('aid', $aid)->whereIn('id', $memberIds)->field('id,nickname,tel,headimg')->select()->toArray() as $member) {
                $members[intval($member['id'])] = $member;
            }
        }
        foreach ($result['list'] as &$task) {
            $member = $members[intval($task['member_id'] ?? 0)] ?? [];
            $task['member'] = [
                'id' => intval($member['id'] ?? 0),
                'nickname' => strval($member['nickname'] ?? ''),
                'tel' => strval($member['tel'] ?? ''),
                'headimg' => strval($member['headimg'] ?? ''),
            ];
        }
        unset($task);

        return $result;
    }

    public static function getDetail(int $aid, int $taskId, int $memberId = 0, string $recordKind = ''): array
    {
        $recordKind = strtolower(trim($recordKind));
        if ($recordKind === 'asset') {
            $asset = self::findMemberAudioAsset($aid, $taskId, $memberId);
            if (!$asset) {
                throw new \RuntimeException('任务不存在或无权查看');
            }
            $formatted = self::formatHistoryAudioAsTask(self::formatAsset($asset));
            $formatted['assets'] = [$asset];
            return $formatted;
        }
        $query = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId);
        if ($memberId > 0) {
            $query->where('member_id', $memberId);
        }
        $row = $query->find();
        if (!$row) {
            $asset = self::findMemberAudioAsset($aid, $taskId, $memberId);
            if ($asset) {
                $formatted = self::formatHistoryAudioAsTask(self::formatAsset($asset));
                $formatted['assets'] = [$asset];
                return $formatted;
            }
            throw new \RuntimeException('任务不存在或无权查看');
        }
        if (intval($row['status'] ?? 0) === 1) {
            try {
                self::refreshRunningTaskRow($row, 1);
                $fresh = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
                if ($fresh) {
                    $row = $fresh;
                }
            } catch (\Throwable $e) {
            }
        }

        $result = self::formatTaskWithQueue($row);
        $assets = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('task_id', $taskId)
            ->where('delete_time', 0)
            ->order('id desc')
            ->select()
            ->toArray();
        $result['assets'] = $assets;
        return $result;
    }

    public static function startTask(int $aid, int $taskId): array
    {
        $row = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在');
        }
        if (intval($row['status'] ?? 0) === 2) {
            return self::formatTask($row);
        }
        Db::name('digitalavatar_task')->where('id', $taskId)->update([
            'status' => 1,
            'progress' => max(1, intval($row['progress'] ?? 0)),
            'started_time' => intval($row['started_time'] ?? 0) ?: time(),
            'update_time' => time(),
        ]);
        return self::formatTask(Db::name('digitalavatar_task')->where('id', $taskId)->find() ?: []);
    }

    public static function completeTask(int $aid, int $taskId, array $result = []): array
    {
        $row = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在');
        }
        if (intval($row['status'] ?? 0) === 2) {
            return self::formatTask($row);
        }

        Db::name('digitalavatar_task')->where('id', $taskId)->update([
            'result_json' => self::encodeJson($result),
            'error_msg' => '',
            'update_time' => time(),
        ]);

        if (intval($row['credit_status'] ?? 0) === 1) {
            self::settleCreatedTaskBilling($aid, $taskId, true);
        }

        Db::name('digitalavatar_task')->where('id', $taskId)->update([
            'status' => 2,
            'progress' => 100,
            'error_msg' => '',
            'finished_time' => time(),
            'update_time' => time(),
        ]);
        $fresh = Db::name('digitalavatar_task')->where('id', $taskId)->find() ?: [];
        try {
            self::submitQueuedForMember(intval($fresh['aid'] ?? $aid), intval($fresh['bid'] ?? 0), intval($fresh['member_id'] ?? 0), 1);
        } catch (\Throwable $e) {
        }
        return self::formatTask($fresh);
    }

    public static function failTask(int $aid, int $taskId, string $errorMessage, array $result = []): array
    {
        $row = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在');
        }
        if (in_array(intval($row['status'] ?? 0), [2, 4], true)) {
            return self::formatTask($row);
        }

        if (intval($row['credit_status'] ?? 0) === 1) {
            self::settleCreatedTaskBilling($aid, $taskId, false);
        }
        $existingResult = self::decodeJson($row['result_json'] ?? '');
        $mergedResult = $result !== [] ? array_merge($existingResult, $result) : $existingResult;
        $update = [
            'status' => 3,
            'progress' => min(99, max(0, intval($row['progress'] ?? 0))),
            'error_msg' => mb_substr(trim($errorMessage) ?: '任务执行失败', 0, 2000),
            'finished_time' => time(),
            'update_time' => time(),
        ];
        if ($mergedResult !== []) {
            $update['result_json'] = self::encodeJson($mergedResult);
        }
        Db::name('digitalavatar_task')->where('id', $taskId)->update($update);
        $fresh = Db::name('digitalavatar_task')->where('id', $taskId)->find() ?: [];
        try {
            self::submitQueuedForMember(intval($fresh['aid'] ?? $aid), intval($fresh['bid'] ?? 0), intval($fresh['member_id'] ?? 0), 1);
        } catch (\Throwable $e) {
        }

        return self::formatTask($fresh);
    }

    public static function cancelMemberTask(int $aid, int $memberId, int $taskId): array
    {
        $row = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $taskId)
            ->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在或无权操作');
        }
        if (in_array(intval($row['status'] ?? 0), [2, 3, 4], true)) {
            return self::formatTask($row);
        }

        Db::name('digitalavatar_task')->where('id', $taskId)->update([
            'status' => 4,
            'error_msg' => '用户取消任务',
            'finished_time' => time(),
            'update_time' => time(),
        ]);
        if (intval($row['credit_status'] ?? 0) === 1) {
            self::settleCreatedTaskBilling($aid, $taskId, false);
        }
        try {
            self::submitQueuedForMember($aid, intval($row['bid'] ?? 0), $memberId, 1);
        } catch (\Throwable $e) {
        }
        return self::formatTaskWithQueue(Db::name('digitalavatar_task')->where('id', $taskId)->find() ?: []);
    }

    public static function deleteMemberTask(int $aid, int $memberId, int $taskId): array
    {
        $row = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $taskId)
            ->where('deleted_time', 0)
            ->find();
        if (!$row) {
            $asset = self::findMemberAudioAsset($aid, $taskId, $memberId);
            if ($asset) {
                return self::deleteMemberAsset($aid, 0, $memberId, $taskId);
            }
            throw new \RuntimeException('任务不存在或无权删除');
        }
        if (!in_array(intval($row['status'] ?? 0), [2, 3, 4], true)) {
            self::cancelMemberTask($aid, $memberId, $taskId);
        }
        Db::name('digitalavatar_task')->where('id', $taskId)->update([
            'deleted_time' => time(),
            'update_time' => time(),
        ]);
        return ['deleted' => 1, 'id' => $taskId];
    }

    public static function deleteMemberAsset(int $aid, int $bid, int $memberId, int $assetId): array
    {
        if ($assetId <= 0) {
            throw new \RuntimeException('请选择要删除的资产');
        }
        $query = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $assetId)
            ->where('delete_time', 0);
        if ($bid > 0) {
            $query->whereIn('bid', [0, max(0, $bid)]);
        }
        $row = $query->find();
        if (!$row) {
            throw new \RuntimeException('资产不存在或无权删除');
        }
        Db::name('digitalavatar_asset')->where('id', $assetId)->update([
            'status' => 0,
            'delete_time' => time(),
            'update_time' => time(),
        ]);
        return ['deleted' => 1, 'id' => $assetId];
    }

    public static function clearMemberTasks(int $aid, int $memberId): array
    {
        $ids = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('deleted_time', 0)
            ->column('id');
        $deleted = 0;
        foreach ($ids as $id) {
            try {
                self::deleteMemberTask($aid, $memberId, intval($id));
                $deleted++;
            } catch (\Throwable $e) {
                // Keep clearing remaining records even if one task fails.
            }
        }
        return ['deleted' => $deleted];
    }

    public static function saveAsset(int $aid, int $memberId, array $info): array
    {
        $taskId = intval($info['task_id'] ?? 0);
        $task = [];
        if ($taskId > 0) {
            $task = Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('id', $taskId)
                ->where('member_id', $memberId)
                ->find();
            if (!$task) {
                throw new \RuntimeException('任务不存在或无权保存资产');
            }
        }
        $url = trim(strval($info['url'] ?? ''));
        if ($url === '') {
            throw new \RuntimeException('资产地址不能为空');
        }
        $now = time();
        $assetType = trim(strval($info['asset_type'] ?? 'video')) ?: 'video';
        $existing = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('asset_type', $assetType)
            ->where('url', $url)
            ->where('delete_time', 0)
            ->order('id desc')
            ->find();
        if ($existing) {
            $update = [];
            $name = mb_substr(trim(strval($info['name'] ?? '')) ?: strval($existing['name'] ?? '未命名资产'), 0, 191);
            if ($name !== '' && $name !== strval($existing['name'] ?? '')) {
                $update['name'] = $name;
            }
            if (!empty($info['metadata'])) {
                $update['metadata_json'] = self::encodeJson($info['metadata']);
            }
            $duration = max(0, floatval($info['duration'] ?? 0));
            if ($duration > 0) {
                $update['duration'] = $duration;
            }
            if ($update !== []) {
                $update['update_time'] = $now;
                Db::name('digitalavatar_asset')->where('id', intval($existing['id']))->update($update);
                $existing = array_merge($existing, $update);
            }
            return self::formatAsset($existing);
        }
        $id = intval(Db::name('digitalavatar_asset')->insertGetId([
            'aid' => $aid,
            'bid' => intval($info['bid'] ?? ($task['bid'] ?? 0)),
            'member_id' => $memberId,
            'task_id' => $taskId,
            'asset_type' => $assetType,
            'name' => mb_substr(trim(strval($info['name'] ?? '')) ?: strval($task['title'] ?? '未命名资产'), 0, 191),
            'url' => mb_substr($url, 0, 2000),
            'mime_type' => trim(strval($info['mime_type'] ?? '')),
            'size' => max(0, intval($info['size'] ?? 0)),
            'duration' => max(0, floatval($info['duration'] ?? 0)),
            'metadata_json' => self::encodeJson($info['metadata'] ?? []),
            'status' => 1,
            'create_time' => $now,
            'update_time' => $now,
            'delete_time' => 0,
        ]));
        $row = Db::name('digitalavatar_asset')->where('id', $id)->find();
        return self::formatAsset(is_array($row) ? $row : []);
    }

    public static function saveUploadedAsset(int $aid, int $bid, int $memberId, array $info): array
    {
        $info['bid'] = max(0, $bid);
        $info['task_id'] = 0;
        $assetType = trim(strval($info['asset_type'] ?? ''));
        if ($assetType === '') {
            $url = strtolower(trim(strval($info['url'] ?? '')));
            if (preg_match('/\.(mp3|wav|m4a|aac|ogg|flac|wma)(?:[?#].*)?$/', $url)) {
                $assetType = 'audio';
            } elseif (preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)(?:[?#].*)?$/', $url)) {
                $assetType = 'image';
            } else {
                $assetType = 'video';
            }
            $info['asset_type'] = $assetType;
        }
        return self::saveAsset($aid, $memberId, $info);
    }

    /**
     * Persist a synchronous 试听 result as a completed audio task + asset,
     * so it shows in 任务中心 / 对话记录 like other synthesis jobs.
     */
    public static function persistPreviewAudio(
        int $aid,
        int $bid,
        int $memberId,
        array $input,
        array $synthResult,
        $creditCost = 0,
        array $costMeta = []
    ): array {
        $audioUrl = trim(strval($synthResult['audio_url'] ?? ($synthResult['url'] ?? '')));
        if ($audioUrl === '') {
            throw new \RuntimeException('试听音频地址为空，无法保存任务');
        }
        $text = trim(strval($input['text'] ?? ($input['content'] ?? ($input['prompt'] ?? ($input['script'] ?? '')))));
        $voiceName = trim(strval($synthResult['voice_name'] ?? ($input['resolved_voice_name'] ?? '')));
        $modelCode = trim(strval($synthResult['model_code'] ?? ($input['model_code'] ?? ($input['resolved_voice_model_code'] ?? ''))));
        $duration = max(0, floatval($synthResult['duration'] ?? 0));
        $audioId = trim(strval($synthResult['audio_id'] ?? ''));

        $titleBase = $voiceName !== '' ? ($voiceName . ' · 试听') : '试听音频';
        $textSnippet = $text !== '' ? mb_substr($text, 0, 24) : '';
        $title = $textSnippet !== '' ? mb_substr($titleBase . ' · ' . $textSnippet, 0, 191) : mb_substr($titleBase, 0, 191);

        $providerKey = '';
        try {
            $configured = DigitalAvatarModelService::resolveForTask($aid, 'audio', $modelCode);
            $providerKey = strval($configured['provider_key'] ?? '');
            if ($modelCode === '') {
                $modelCode = strval($configured['model_code'] ?? '');
            }
        } catch (\Throwable $e) {
            $providerKey = 'tts';
        }

        $inputPayload = self::normalizePayload(array_merge($input, [
            'source' => 'preview',
            'task_type' => 'audio',
            'preview_text' => $text,
            'text' => $text,
            'content' => $text,
            'prompt' => $text,
            'script' => $text,
            'model_code' => $modelCode,
        ]));
        if (!empty($costMeta)) {
            foreach (['billing_mode', 'platform_code', 'api_model', 'quantity'] as $key) {
                if (array_key_exists($key, $costMeta) && !isset($inputPayload[$key])) {
                    $inputPayload[$key] = $costMeta[$key];
                }
            }
            if (isset($costMeta['quantity'])) {
                $inputPayload['billing_quantity'] = floatval($costMeta['quantity']);
            }
        }

        $resultPayload = [
            'audio_url' => $audioUrl,
            'url' => $audioUrl,
            'audio_id' => $audioId,
            'duration' => $duration,
            'voice_id' => strval($synthResult['voice_id'] ?? ($input['resolved_voice_id'] ?? '')),
            'voice_name' => $voiceName,
            'model_code' => $modelCode,
            'source' => 'preview',
            'preview_text' => $text,
        ];

        $creditCost = DigitalAvatarCreditService::creditAmount($creditCost);
        $now = time();
        $requestId = trim(strval($input['request_id'] ?? ''));
        if ($requestId === '') {
            $requestId = 'preview-' . bin2hex(random_bytes(12));
        }
        $requestId = mb_substr($requestId, 0, 80);

        $taskId = intval(Db::name('digitalavatar_task')->insertGetId([
            'aid' => $aid,
            'bid' => max(0, $bid),
            'member_id' => $memberId,
            'conversation_id' => max(0, intval($input['conversation_id'] ?? ($inputPayload['conversation_id'] ?? 0))),
            'request_id' => $requestId,
            'task_type' => 'audio',
            'provider_key' => $providerKey,
            'model_code' => $modelCode,
            'title' => $title,
            'status' => 2,
            'progress' => 100,
            'credit_cost' => DigitalAvatarCreditService::creditStore($creditCost),
            'credit_status' => $creditCost > 0 ? 2 : 0,
            'credit_source' => $creditCost > 0 ? 'member_credit' : '',
            'quota_units' => 0,
            'input_json' => self::encodeJson($inputPayload),
            'result_json' => self::encodeJson($resultPayload),
            'error_msg' => '',
            'create_time' => $now,
            'update_time' => $now,
            'started_time' => $now,
            'finished_time' => $now,
            'deleted_time' => 0,
        ]));
        if ($taskId <= 0) {
            throw new \RuntimeException('试听任务保存失败');
        }

        try {
            DigitalAvatarConversationService::bindTask(
                $aid,
                $bid,
                $memberId,
                $taskId,
                intval($input['conversation_id'] ?? ($inputPayload['conversation_id'] ?? 0)),
                $title,
                'audio',
                strval($input['conversation_mode'] ?? ($inputPayload['conversation_mode'] ?? ''))
            );
        } catch (\Throwable $e) {
        }

        $asset = self::saveAsset($aid, $memberId, [
            'bid' => max(0, $bid),
            'task_id' => $taskId,
            'asset_type' => 'audio',
            'name' => $title,
            'url' => $audioUrl,
            'duration' => $duration,
            'metadata' => [
                'source' => 'preview',
                'task_type' => 'audio',
                'voice_id' => strval($synthResult['voice_id'] ?? ($input['resolved_voice_id'] ?? '')),
                'voice_name' => $voiceName,
                'preview_text' => $text,
                'model_code' => $modelCode,
                'audio_id' => $audioId,
            ],
        ]);

        $row = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        $task = self::formatTask(is_array($row) ? $row : []);
        return [
            'task' => $task,
            'asset' => $asset,
            'task_id' => $taskId,
            'asset_id' => intval($asset['id'] ?? 0),
        ];
    }

    public static function retryMemberTask(int $aid, int $bid, int $memberId, int $taskId): array
    {
        $row = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $taskId)
            ->where('deleted_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在或无权重试');
        }
        $status = intval($row['status'] ?? 0);
        if (!in_array($status, [3, 4], true)) {
            throw new \RuntimeException('只有失败或已取消的任务可以重试');
        }
        $input = self::sanitizeCloneInput(self::decodeJson($row['input_json'] ?? ''), intval($row['id'] ?? 0));
        $input['task_type'] = strval($row['task_type'] ?? '');
        $input['title'] = strval($row['title'] ?? '');
        $input['model_code'] = strval($row['model_code'] ?? ($input['model_code'] ?? ''));
        $input['request_id'] = 'retry-' . $taskId . '-' . bin2hex(random_bytes(8));
        $input['conversation_id'] = intval($row['conversation_id'] ?? ($input['conversation_id'] ?? 0));
        return self::createTask($aid, $bid, $memberId, $input);
    }

    public static function regenerateMemberTask(int $aid, int $bid, int $memberId, int $taskId, int $conversationId = 0): array
    {
        $row = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $taskId)
            ->where('deleted_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在');
        }
        $input = self::sanitizeCloneInput(self::decodeJson($row['input_json'] ?? ''), intval($row['id'] ?? 0));
        $input['task_type'] = strval($row['task_type'] ?? '');
        $input['title'] = strval($row['title'] ?? '');
        $input['model_code'] = strval($row['model_code'] ?? ($input['model_code'] ?? ''));
        $conv = $conversationId > 0 ? $conversationId : intval($row['conversation_id'] ?? 0);
        $input['conversation_id'] = $conv;
        $input['request_id'] = 'regen-' . $taskId . '-' . bin2hex(random_bytes(8));
        return self::createTask($aid, $bid, $memberId, $input);
    }

    public static function toggleStarMemberTask(int $aid, int $memberId, int $taskId): array
    {
        DigitalAvatarInstaller::ensureTaskStarredColumn();
        $row = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $taskId)
            ->where('deleted_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在');
        }
        $on = intval($row['starred'] ?? 0) ? 0 : 1;
        Db::name('digitalavatar_task')->where('id', $taskId)->update([
            'starred' => $on,
            'update_time' => time(),
        ]);
        return [
            'id' => $taskId,
            'starred' => $on,
        ];
    }

    public static function cancelAdminTask(int $aid, int $taskId): array
    {
        $row = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在');
        }
        return self::cancelMemberTask($aid, intval($row['member_id'] ?? 0), $taskId);
    }

    public static function retryAdminTask(int $aid, int $taskId): array
    {
        $row = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在');
        }
        return self::retryMemberTask(
            $aid,
            intval($row['bid'] ?? 0),
            intval($row['member_id'] ?? 0),
            $taskId
        );
    }

    public static function getAssetList(int $aid, int $bid, int $memberId, int $page, int $limit, string $assetType = ''): array
    {
        $query = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('delete_time', 0);
        if ($bid > 0) {
            $query->whereIn('bid', [0, max(0, $bid)]);
        }
        if ($assetType !== '') {
            $query->where('asset_type', $assetType);
        }
        $count = intval((clone $query)->count());
        $list = $query->page(max(1, $page), max(1, min(200, $limit)))
            ->order('id desc')
            ->select()
            ->toArray();
        $taskIds = array_values(array_unique(array_filter(array_map('intval', array_column($list, 'task_id')))));
        $taskTypes = [];
        if ($taskIds !== []) {
            $taskTypes = Db::name('digitalavatar_task')->whereIn('id', $taskIds)->column('task_type', 'id');
        }
        foreach ($list as &$item) {
            $item = self::formatAsset($item, $taskTypes);
        }
        unset($item);
        return ['list' => $list, 'total' => $count, 'page' => $page, 'limit' => $limit];
    }

    public static function formatAsset(array $item, array $taskTypes = []): array
    {
        $meta = [];
        if (!empty($item['metadata_json'])) {
            $decoded = json_decode(strval($item['metadata_json']), true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (is_array($item['metadata'] ?? null)) {
            $meta = array_merge($meta, $item['metadata']);
        }
        $url = trim(strval($item['url'] ?? ''));
        $cover = trim(strval($meta['cover_url'] ?? ($item['cover_url'] ?? '')));
        if ($cover === '' && strval($item['asset_type'] ?? '') === 'image') {
            $cover = $url;
        }
        $taskId = intval($item['task_id'] ?? 0);
        $taskType = strval($taskTypes[$taskId] ?? ($meta['task_type'] ?? ($item['task_type'] ?? '')));
        $source = trim(strval($item['source'] ?? ($meta['source'] ?? '')));
        if ($source === '') {
            if ($taskId <= 0) {
                $source = 'member_upload';
            } elseif ($taskType === 'video') {
                $source = 'creative_video';
            } elseif ($taskType !== '') {
                $source = $taskType;
            }
        }
        $item['cover_url'] = $cover;
        $item['download_url'] = $url;
        $item['video_url'] = DigitalAvatarService::isVideoUrl($url) ? $url : '';
        $item['metadata'] = $meta;
        $item['source'] = $source;
        $item['task_type'] = $taskType;
        $item['preview_text'] = strval($meta['preview_text'] ?? ($meta['text'] ?? ''));
        return $item;
    }

    public static function playableMedia(array $task): array
    {
        $taskType = strval($task['task_type'] ?? '');
        $input = is_array($task['input'] ?? null) ? $task['input'] : [];
        $result = is_array($task['result'] ?? null) ? $task['result'] : [];
        $sample = trim(strval($input['sample_url'] ?? ($input['audio_url'] ?? '')));
        $audio = trim(strval($task['audio_url'] ?? ($result['audio_url'] ?? '')));
        $video = trim(strval($task['video_url'] ?? ($result['video_url'] ?? '')));
        $download = trim(strval($task['download_url'] ?? ($result['download_url'] ?? ($result['url'] ?? ''))));
        $cover = trim(strval($task['cover_url'] ?? ($result['cover_url'] ?? ($result['image_url'] ?? ''))));

        $pickKind = function (string $url, string $fallback) {
            if ($url === '') {
                return $fallback;
            }
            if (DigitalAvatarService::isAudioUrl($url)) {
                return 'audio';
            }
            if (DigitalAvatarService::isVideoUrl($url)) {
                return 'video';
            }
            if (DigitalAvatarService::isImageUrl($url)) {
                return 'image';
            }
            return $fallback;
        };

        if ($taskType === 'image') {
            $url = DigitalAvatarService::pickImageUrl([$cover, $download, $result['image_url'] ?? '', $result['url'] ?? '']);
            return ['url' => $url, 'kind' => 'image'];
        }

        if (in_array($taskType, ['voice_clone', 'audio'], true)) {
            $audioUrl = DigitalAvatarService::pickAudioUrl([$audio, $sample, $download, $result['preview_url'] ?? '']);
            if ($audioUrl === '') {
                foreach ([$audio, $sample, $download, $result['preview_url'] ?? ''] as $candidate) {
                    $candidate = trim(strval($candidate));
                    if ($candidate !== '' && !DigitalAvatarService::isImageUrl($candidate) && !DigitalAvatarService::isVideoUrl($candidate)) {
                        $audioUrl = $candidate;
                        break;
                    }
                }
            }
            if ($audioUrl !== '') {
                return ['url' => $audioUrl, 'kind' => $pickKind($audioUrl, 'audio')];
            }
            $videoUrl = DigitalAvatarService::pickVideoUrl([$video, $sample, $download]);
            if ($videoUrl === '' && DigitalAvatarService::isVideoUrl($sample)) {
                $videoUrl = $sample;
            }
            if ($videoUrl !== '') {
                return ['url' => $videoUrl, 'kind' => 'video'];
            }
            if ($sample !== '') {
                return ['url' => $sample, 'kind' => $pickKind($sample, 'audio')];
            }
            return ['url' => '', 'kind' => 'audio'];
        }

        $videoUrl = DigitalAvatarService::pickVideoUrl([$video, $sample, $download, $result['preview_url'] ?? '']);
        if ($videoUrl === '') {
            foreach ([$video, $download, $sample] as $candidate) {
                $candidate = trim(strval($candidate));
                if ($candidate !== '' && !DigitalAvatarService::isImageUrl($candidate)) {
                    $videoUrl = $candidate;
                    break;
                }
            }
        }
        if ($videoUrl !== '') {
            return ['url' => $videoUrl, 'kind' => $pickKind($videoUrl, 'video')];
        }
        if ($audio !== '') {
            return ['url' => $audio, 'kind' => $pickKind($audio, 'audio')];
        }
        return ['url' => $cover, 'kind' => $pickKind($cover, 'image')];
    }

    public static function formatTask(array $row): array
    {
        $status = intval($row['status'] ?? 0);
        $statusMap = [
            0 => '排队中',
            1 => '运行中',
            2 => '已完成',
            3 => '失败',
            4 => '已取消',
            5 => '待重试',
        ];
        $input = self::decodeJson($row['input_json'] ?? '');
        $result = self::decodeJson($row['result_json'] ?? '');
        $resultData = is_array($result['data'] ?? null) ? $result['data'] : [];
        $providerData = is_array($result['provider_response']['data'] ?? null) ? $result['provider_response']['data'] : [];
        $taskType = strval($row['task_type'] ?? '');
        $assetUrls = [];
        if (is_array($result['assets'] ?? null)) {
            foreach ($result['assets'] as $asset) {
                if (is_array($asset)) {
                    $assetUrls[] = $asset['url'] ?? '';
                }
            }
        }
        $generatedImageUrl = '';
        if ($taskType === 'image') {
            $generatedImageUrl = DigitalAvatarService::pickImageUrl(array_merge([
                $result['image_url'] ?? '',
                $result['url'] ?? '',
                $result['cover_url'] ?? '',
                $resultData['image_url'] ?? '',
                $resultData['url'] ?? '',
                $resultData['cover_url'] ?? '',
                $providerData['image_url'] ?? '',
                $providerData['url'] ?? '',
                $providerData['cover_url'] ?? '',
            ], $assetUrls));
        }
        $coverUrl = DigitalAvatarService::pickImageUrl([
            $generatedImageUrl,
            $result['cover_url'] ?? '',
            $resultData['cover_url'] ?? '',
            $providerData['cover_url'] ?? '',
            $taskType === 'image' ? '' : ($input['cover_url'] ?? ''),
            $result['poster'] ?? '',
            $result['thumbnail'] ?? '',
            $result['thumb_url'] ?? '',
        ]);
        if ($taskType === 'image' && $generatedImageUrl === '' && $status !== 2) {
            $coverUrl = DigitalAvatarService::pickImageUrl([
                $coverUrl,
                $input['cover_url'] ?? '',
            ]);
        }
        $videoCandidates = array_merge([
            $result['video_url'] ?? '',
            $result['url'] ?? '',
            $result['download_url'] ?? '',
            $result['preview_url'] ?? '',
            $resultData['video_url'] ?? '',
            $resultData['url'] ?? '',
            $providerData['video_url'] ?? '',
            $providerData['url'] ?? '',
        ], $assetUrls);
        if (in_array($taskType, ['avatar_clone', 'avatar_video', 'video'], true)) {
            $videoCandidates[] = $input['sample_url'] ?? '';
        }
        if ($taskType === 'voice_clone' && DigitalAvatarService::isVideoUrl(strval($input['sample_url'] ?? ''))) {
            $videoCandidates[] = $input['sample_url'] ?? '';
        }
        $videoUrl = DigitalAvatarService::pickVideoUrl($videoCandidates);
        $audioCandidates = array_merge([
            $result['audio_url'] ?? '',
            $resultData['audio_url'] ?? '',
            $result['preview_url'] ?? '',
            $resultData['preview_url'] ?? '',
            $providerData['audio_url'] ?? '',
        ], $assetUrls);
        if (in_array($taskType, ['voice_clone', 'audio'], true)) {
            $audioCandidates[] = $input['sample_url'] ?? '';
            $audioCandidates[] = $input['audio_url'] ?? '';
        }
        $audioUrl = DigitalAvatarService::pickAudioUrl($audioCandidates);
        if ($audioUrl === '' && in_array($taskType, ['voice_clone', 'audio'], true)) {
            foreach ($audioCandidates as $candidate) {
                $candidate = trim(strval($candidate));
                if ($candidate === '' || DigitalAvatarService::isImageUrl($candidate) || DigitalAvatarService::isVideoUrl($candidate)) {
                    continue;
                }
                if (preg_match('#^https?://#i', $candidate)) {
                    $audioUrl = $candidate;
                    break;
                }
            }
        }
        if ($coverUrl === '') {
            $fallback = trim(strval($result['cover_url'] ?? ($resultData['cover_url'] ?? ($result['url'] ?? ''))));
            if ($fallback !== '' && !DigitalAvatarService::isVideoUrl($fallback)) {
                $coverUrl = $fallback;
            }
        }
        $result['cover_url'] = $coverUrl;
        if ($taskType === 'image' && $generatedImageUrl !== '') {
            $coverUrl = $generatedImageUrl;
            $result['cover_url'] = $generatedImageUrl;
            $result['image_url'] = $generatedImageUrl;
            $result['url'] = $generatedImageUrl;
        } elseif ($videoUrl !== '') {
            $result['video_url'] = $videoUrl;
            $result['url'] = $videoUrl;
        } elseif ($audioUrl !== '') {
            $result['url'] = $audioUrl;
        }
        if ($taskType === 'image') {
            $downloadUrl = $generatedImageUrl !== '' ? $generatedImageUrl : '';
        } elseif (in_array($taskType, ['voice_clone', 'audio'], true)) {
            $downloadUrl = $audioUrl !== '' ? $audioUrl : $videoUrl;
        } else {
            $downloadUrl = $videoUrl !== '' ? $videoUrl : ($audioUrl !== '' ? $audioUrl : $coverUrl);
        }
        $creditStatus = intval($row['credit_status'] ?? 0);
        $creditStatusMap = [
            0 => '无需扣点',
            1 => '已冻结',
            2 => '已结算',
            3 => '已退回',
        ];
        $createTime = intval($row['create_time'] ?? 0);
        $startedTime = intval($row['started_time'] ?? 0);
        $finishedTime = intval($row['finished_time'] ?? 0);
        $aid = intval($row['aid'] ?? 0);
        $modelCode = trim(strval($row['model_code'] ?? ''));
        $modelName = self::resolveTaskModelName($aid, $taskType, $modelCode, $input);
        $apiTaskId = self::extractApiTaskId($result, $input, intval($row['id'] ?? 0));
        $billing = self::billingQuantityInfo($taskType, $input, $result);
        $creditCost = DigitalAvatarCreditService::creditAmount($row['credit_cost'] ?? 0);
        $quotaUnits = max(0, intval($row['quota_units'] ?? 0));
        $creditSource = strval($row['credit_source'] ?? '');
        $durationSeconds = ($startedTime > 0 && $finishedTime >= $startedTime) ? ($finishedTime - $startedTime) : 0;
        return [
            'id' => intval($row['id'] ?? 0),
            'aid' => $aid,
            'bid' => intval($row['bid'] ?? 0),
            'member_id' => intval($row['member_id'] ?? 0),
            'conversation_id' => intval($row['conversation_id'] ?? 0),
            'request_id' => strval($row['request_id'] ?? ''),
            'api_task_id' => $apiTaskId,
            'task_type' => $taskType,
            'task_name' => (strval($input['source'] ?? '') === 'preview')
                ? '试听音频'
                : strval(self::TASK_TYPES[$taskType] ?? $taskType),
            'record_kind' => 'task',
            'provider_key' => strval($row['provider_key'] ?? ''),
            'model_code' => $modelCode,
            'model_name' => $modelName,
            'title' => strval($row['title'] ?? ''),
            'status' => $status,
            'status_text' => $statusMap[$status] ?? '未知状态',
            'queue_position' => intval($row['queue_position'] ?? 0),
            'progress' => max(0, min(100, intval($row['progress'] ?? 0))),
            'credit_cost' => $creditCost,
            'quota_units' => $quotaUnits,
            'credit_status' => $creditStatus,
            'credit_status_text' => strval($creditStatusMap[$creditStatus] ?? '未知'),
            'credit_source' => $creditSource,
            'charge_text' => self::formatChargeText($creditSource, $creditCost, $quotaUnits, $creditStatus),
            'billing_quantity' => $billing['value'],
            'billing_quantity_text' => $billing['text'],
            'input' => $input,
            'result' => $result,
            'provider_calls' => is_array($result['provider_calls'] ?? null) ? $result['provider_calls'] : [],
            'provider_request' => is_array($result['provider_request'] ?? null) ? $result['provider_request'] : (is_array($result['provider_response']['request'] ?? null) ? $result['provider_response']['request'] : null),
            'error_msg' => strval($row['error_msg'] ?? ''),
            'create_time' => $createTime,
            'create_time_text' => self::formatTimestamp($createTime),
            'update_time' => intval($row['update_time'] ?? 0),
            'started_time' => $startedTime,
            'started_time_text' => self::formatTimestamp($startedTime),
            'finished_time' => $finishedTime,
            'finished_time_text' => self::formatTimestamp($finishedTime),
            'duration_seconds' => $durationSeconds,
            'duration_text' => ($startedTime > 0 && $finishedTime >= $startedTime) ? ($durationSeconds . '秒') : '--',
            'cover_url' => $coverUrl,
            'download_url' => $downloadUrl,
            'video_url' => $videoUrl,
            'audio_url' => $audioUrl,
            'starred' => intval($row['starred'] ?? 0),
            'deleted_time' => intval($row['deleted_time'] ?? 0),
        ];
    }

    private static function formatTaskWithQueue(array $row): array
    {
        $formatted = self::attachQueueMeta([self::formatTask($row)]);
        return $formatted[0] ?? self::formatTask($row);
    }

    private static function paginate($query, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $total = intval((clone $query)->count());
        $rows = $query->page($page, $limit)->order('id desc')->select()->toArray();
        $list = array_map([self::class, 'formatTask'], $rows);
        return [
            'list' => self::attachQueueMeta($list),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private static function attachQueueMeta(array $list): array
    {
        $groups = [];
        foreach ($list as $item) {
            if (!in_array(intval($item['status'] ?? 0), [0, 5], true)) {
                continue;
            }
            $aid = intval($item['aid'] ?? 0);
            $memberId = intval($item['member_id'] ?? 0);
            if ($aid <= 0 || $memberId <= 0) {
                continue;
            }
            $groups[$aid . ':' . $memberId] = ['aid' => $aid, 'member_id' => $memberId];
        }
        $posMaps = [];
        foreach ($groups as $key => $group) {
            $ids = Db::name('digitalavatar_task')
                ->where('aid', intval($group['aid']))
                ->where('member_id', intval($group['member_id']))
                ->where('deleted_time', 0)
                ->whereIn('status', [0, 5])
                ->order('create_time asc,id asc')
                ->column('id');
            $position = 1;
            foreach ($ids as $id) {
                $posMaps[$key][intval($id)] = $position;
                $position++;
            }
        }
        foreach ($list as &$item) {
            $key = intval($item['aid'] ?? 0) . ':' . intval($item['member_id'] ?? 0);
            $position = intval($posMaps[$key][intval($item['id'] ?? 0)] ?? 0);
            $item['queue_position'] = $position;
            if ($position > 0 && in_array(intval($item['status'] ?? 0), [0, 5], true)) {
                $prefix = intval($item['status'] ?? 0) === 5 ? '待重试' : '排队中';
                $item['status_text'] = $prefix . '（第' . $position . '位）';
            }
        }
        unset($item);
        return $list;
    }

    private static function isHistoryAudioQuery(string $taskType, array $extra = []): bool
    {
        $types = self::normalizeTaskTypes($taskType, $extra);
        return $types === ['audio'];
    }

    private static function shouldIncludePreviewAudio(string $taskType, array $extra = []): bool
    {
        return self::normalizeTaskTypes($taskType, $extra) === [];
    }

    private static function normalizeTaskTypes(string $taskType, array $extra = []): array
    {
        $types = array_values(array_filter(array_map('trim', explode(',', $taskType))));
        $modelTaskType = trim(strval($extra['model_task_type'] ?? ''));
        if ($types === [] && $modelTaskType !== '') {
            $types = [$modelTaskType];
        }
        return $types;
    }

    private static function findMemberAudioAsset(int $aid, int $assetId, int $memberId = 0): array
    {
        if ($assetId <= 0) {
            return [];
        }
        $query = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('id', $assetId)
            ->where('asset_type', 'audio')
            ->where('delete_time', 0);
        if ($memberId > 0) {
            $query->where('member_id', $memberId);
        }
        $row = $query->find();
        return is_array($row) ? $row : [];
    }

    private static function previewAudioQuery(int $aid, int $bid, int $memberId, string $keyword, string $status, string $modelCode, array $extra)
    {
        $query = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('asset_type', 'audio')
            ->where('delete_time', 0)
            ->where('task_id', 0) // task-linked 试听 already appears via digitalavatar_task
            ->where('url', '<>', '')
            ->where(function ($sub) {
                $sub->whereLike('metadata_json', '%"source":"preview"%')
                    ->whereOr('metadata_json', 'like', '%"source": "preview"%')
                    ->whereOr('name', 'like', '%试听%');
            });
        if ($bid > 0) {
            $query->whereIn('bid', [0, max(0, $bid)]);
        }
        self::applyHistoryAudioFilters($query, $keyword, $status, $modelCode, $extra);
        return $query;
    }

    private static function getMergedMemberList(
        int $aid,
        int $bid,
        int $memberId,
        int $page,
        int $limit,
        string $keyword = '',
        string $status = '',
        string $taskType = '',
        string $modelCode = '',
        array $extra = []
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $taskQuery = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('deleted_time', 0);
        self::applyFilters($taskQuery, $keyword, $status, $taskType, $modelCode, $extra);
        $assetQuery = self::previewAudioQuery($aid, $bid, $memberId, $keyword, $status, $modelCode, $extra);
        $taskCount = intval((clone $taskQuery)->count());
        $assetCount = intval((clone $assetQuery)->count());
        $total = $taskCount + $assetCount;
        $offset = ($page - 1) * $limit;
        $need = $offset + $limit;
        $taskRows = $taskCount > 0
            ? (clone $taskQuery)->order('create_time desc,id desc')->limit($need)->select()->toArray()
            : [];
        $assetRows = $assetCount > 0
            ? (clone $assetQuery)->order('create_time desc,id desc')->limit($need)->select()->toArray()
            : [];
        $merged = [];
        foreach ($taskRows as $row) {
            $merged[] = [
                'kind' => 'task',
                'create_time' => intval($row['create_time'] ?? 0),
                'id' => intval($row['id'] ?? 0),
                'row' => $row,
            ];
        }
        foreach ($assetRows as $row) {
            $merged[] = [
                'kind' => 'asset',
                'create_time' => intval($row['create_time'] ?? 0),
                'id' => intval($row['id'] ?? 0),
                'row' => $row,
            ];
        }
        usort($merged, function ($a, $b) {
            $dt = intval($b['create_time'] ?? 0) - intval($a['create_time'] ?? 0);
            if ($dt !== 0) {
                return $dt > 0 ? 1 : -1;
            }
            if (($a['kind'] ?? '') !== ($b['kind'] ?? '')) {
                return ($a['kind'] ?? '') === 'task' ? -1 : 1;
            }
            return intval($b['id'] ?? 0) - intval($a['id'] ?? 0);
        });
        $slice = array_slice($merged, $offset, $limit);
        $prepared = [];
        $taskItems = [];
        foreach ($slice as $item) {
            if (($item['kind'] ?? '') === 'asset') {
                $prepared[] = [
                    'kind' => 'asset',
                    'task' => self::formatHistoryAudioAsTask(self::formatAsset($item['row'] ?? [])),
                ];
            } else {
                $formatted = self::formatTask($item['row'] ?? []);
                $prepared[] = ['kind' => 'task', 'task' => $formatted];
                $taskItems[] = $formatted;
            }
        }
        $queued = self::attachQueueMeta($taskItems);
        $qi = 0;
        $list = [];
        foreach ($prepared as $item) {
            if (($item['kind'] ?? '') === 'task') {
                $list[] = $queued[$qi++] ?? $item['task'];
            } else {
                $list[] = $item['task'];
            }
        }
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private static function getHistoryAudioMemberList(
        int $aid,
        int $bid,
        int $memberId,
        int $page,
        int $limit,
        string $keyword = '',
        string $status = '',
        string $modelCode = '',
        array $extra = []
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $query = Db::name('digitalavatar_asset')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('asset_type', 'audio')
            ->where('delete_time', 0)
            ->where('url', '<>', '');
        if ($bid > 0) {
            $query->whereIn('bid', [0, max(0, $bid)]);
        }
        self::applyHistoryAudioFilters($query, $keyword, $status, $modelCode, $extra);
        $total = intval((clone $query)->count());
        $rows = $query->page($page, $limit)->order('create_time desc,id desc')->select()->toArray();
        $taskIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'task_id')))));
        $taskTypes = [];
        if ($taskIds !== []) {
            $taskTypes = Db::name('digitalavatar_task')->whereIn('id', $taskIds)->column('task_type', 'id');
        }
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::formatHistoryAudioAsTask(self::formatAsset($row, $taskTypes));
        }
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private static function applyHistoryAudioFilters($query, string $keyword, string $status, string $modelCode, array $extra): void
    {
        $statuses = array_values(array_filter(array_map('trim', explode(',', $status)), function ($item) {
            return $item !== '' && is_numeric($item);
        }));
        if ($statuses !== []) {
            $allowCompleted = false;
            foreach ($statuses as $item) {
                if (intval($item) === 2) {
                    $allowCompleted = true;
                    break;
                }
            }
            if (!$allowCompleted) {
                $query->where('id', 0);
                return;
            }
        }
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($sub) use ($like) {
                $sub->whereLike('name|url|metadata_json', $like);
            });
        }
        $modelCode = trim($modelCode);
        if ($modelCode !== '') {
            $query->whereLike('metadata_json', '%' . $modelCode . '%');
        }
        $taskId = trim(strval($extra['task_id'] ?? ''));
        if ($taskId !== '') {
            $query->where(function ($sub) use ($taskId) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $taskId) . '%';
                $sub->whereLike('name|url|metadata_json', $like);
                if (ctype_digit($taskId)) {
                    $id = intval($taskId);
                    $sub->whereOr('id', $id)->whereOr('task_id', $id);
                }
            });
        }
        $startTime = intval($extra['start_time'] ?? 0);
        if ($startTime > 0) {
            $query->where('create_time', '>=', $startTime);
        }
        $endTime = intval($extra['end_time'] ?? 0);
        if ($endTime > 0) {
            $query->where('create_time', '<=', $endTime);
        }
    }

    private static function formatHistoryAudioAsTask(array $asset): array
    {
        $meta = is_array($asset['metadata'] ?? null) ? $asset['metadata'] : [];
        $url = trim(strval($asset['url'] ?? ($asset['download_url'] ?? '')));
        $source = trim(strval($asset['source'] ?? ($meta['source'] ?? '')));
        $taskType = trim(strval($asset['task_type'] ?? ($meta['task_type'] ?? 'audio'))) ?: 'audio';
        if ($source === 'member_upload') {
            $taskName = '上传音频';
        } elseif ($source === 'preview') {
            $taskName = '试听音频';
        } else {
            $taskName = '合成音频';
        }
        $title = trim(strval($asset['name'] ?? ''));
        if ($title === '') {
            $title = $taskName;
        }
        $createTime = intval($asset['create_time'] ?? 0);
        $durationSeconds = max(0, intval(round(floatval($asset['duration'] ?? ($meta['duration'] ?? 0)))));
        $modelCode = trim(strval($meta['model_code'] ?? ($asset['model_code'] ?? '')));
        $apiTaskId = trim(strval($meta['audio_id'] ?? ''));
        if ($apiTaskId === '') {
            $apiTaskId = 'audio-' . intval($asset['id'] ?? 0);
        }
        $result = [
            'url' => $url,
            'audio_url' => $url,
            'duration' => $durationSeconds,
            'source' => $source,
        ];
        return [
            'id' => intval($asset['id'] ?? 0),
            'aid' => intval($asset['aid'] ?? 0),
            'bid' => intval($asset['bid'] ?? 0),
            'member_id' => intval($asset['member_id'] ?? 0),
            'conversation_id' => 0,
            'request_id' => '',
            'api_task_id' => $apiTaskId,
            'task_type' => 'audio',
            'task_name' => $taskName,
            'record_kind' => 'asset',
            'provider_key' => '',
            'model_code' => $modelCode,
            'model_name' => $modelCode,
            'title' => $title,
            'status' => 2,
            'status_text' => '已完成',
            'queue_position' => 0,
            'progress' => 100,
            'credit_cost' => 0,
            'quota_units' => 0,
            'credit_status' => 0,
            'credit_status_text' => '无需扣点',
            'credit_source' => '',
            'charge_text' => '--',
            'billing_quantity' => $durationSeconds,
            'billing_quantity_text' => $durationSeconds > 0 ? ($durationSeconds . '秒') : '--',
            'input' => [
                'source' => $source,
                'task_type' => $taskType,
                'preview_text' => strval($asset['preview_text'] ?? ($meta['preview_text'] ?? '')),
                'text' => strval($asset['preview_text'] ?? ($meta['preview_text'] ?? '')),
                'content' => strval($asset['preview_text'] ?? ($meta['preview_text'] ?? '')),
            ],
            'result' => $result,
            'error_msg' => '',
            'create_time' => $createTime,
            'create_time_text' => self::formatTimestamp($createTime),
            'update_time' => intval($asset['update_time'] ?? 0),
            'started_time' => $createTime,
            'started_time_text' => self::formatTimestamp($createTime),
            'finished_time' => $createTime,
            'finished_time_text' => self::formatTimestamp($createTime),
            'duration_seconds' => $durationSeconds,
            'duration_text' => $durationSeconds > 0 ? self::formatAudioClock($durationSeconds) : '--',
            'cover_url' => strval($asset['cover_url'] ?? ''),
            'download_url' => $url,
            'video_url' => '',
            'audio_url' => $url,
            'deleted_time' => 0,
        ];
    }

    private static function formatAudioClock(int $seconds): string
    {
        $seconds = max(0, $seconds);
        return intval(floor($seconds / 60)) . ':' . str_pad(strval($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    private static function applyFilters($query, string $keyword, string $status, string $taskType = '', string $modelCode = '', array $extra = []): void
    {
        if ($keyword !== '') {
            $query->whereLike('title|task_type|provider_key|model_code|request_id', '%' . $keyword . '%');
        }
        $statuses = array_values(array_filter(array_map('trim', explode(',', $status)), function ($item) {
            return $item !== '' && is_numeric($item);
        }));
        if (count($statuses) === 1) {
            $query->where('status', intval($statuses[0]));
        } elseif (count($statuses) > 1) {
            $query->whereIn('status', array_map('intval', $statuses));
        }
        $types = array_values(array_filter(array_map('trim', explode(',', $taskType))));
        $modelTaskType = trim(strval($extra['model_task_type'] ?? ''));
        if ($types === [] && $modelTaskType !== '') {
            $types = [$modelTaskType];
        }
        if (count($types) === 1) {
            $query->where('task_type', $types[0]);
        } elseif (count($types) > 1) {
            $query->whereIn('task_type', $types);
        }
        $modelCode = trim($modelCode);
        if ($modelCode !== '') {
            $query->where('model_code', $modelCode);
        }
        $taskId = trim(strval($extra['task_id'] ?? ''));
        if ($taskId !== '') {
            $query->where(function ($q) use ($taskId) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $taskId) . '%';
                $q->where('request_id', $taskId)
                    ->whereOr('result_json', 'like', $like);
                if (ctype_digit($taskId)) {
                    $q->whereOr('id', intval($taskId));
                }
            });
        }
        $startTime = intval($extra['start_time'] ?? 0);
        if ($startTime > 0) {
            $query->where('create_time', '>=', $startTime);
        }
        $endTime = intval($extra['end_time'] ?? 0);
        if ($endTime > 0) {
            $query->where('create_time', '<=', $endTime);
        }
    }

    public static function exportMemberList(int $aid, int $bid, int $memberId, string $keyword = '', string $status = '', string $taskType = '', string $modelCode = '', array $extra = []): array
    {
        if (self::isHistoryAudioQuery($taskType, $extra)) {
            $result = self::getHistoryAudioMemberList($aid, $bid, $memberId, 1, 2000, $keyword, $status, $modelCode, $extra);
            return is_array($result['list'] ?? null) ? $result['list'] : [];
        }
        if (self::shouldIncludePreviewAudio($taskType, $extra)) {
            $result = self::getMergedMemberList($aid, $bid, $memberId, 1, 2000, $keyword, $status, $taskType, $modelCode, $extra);
            return is_array($result['list'] ?? null) ? $result['list'] : [];
        }
        $query = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('bid', max(0, $bid))
            ->where('member_id', $memberId)
            ->where('deleted_time', 0);
        self::applyFilters($query, $keyword, $status, $taskType, $modelCode, $extra);
        $rows = $query->order('id desc')->limit(2000)->select()->toArray();
        return self::attachQueueMeta(array_map([self::class, 'formatTask'], $rows));
    }

    public static function buildTaskExcelBody(array $list): string
    {
        $headers = [
            '本地ID',
            '任务ID',
            '任务类型',
            '任务模型',
            '模型编码',
            '任务标题',
            '任务状态',
            '创建时间',
            '提交API时间',
            '成功/失败时间',
            '耗时(秒)',
            '扣费',
            '计费数量',
            '失败原因',
        ];
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body><table border="1">';
        $html .= '<tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $html .= '</tr>';
        foreach ($list as $item) {
            $cells = [
                strval($item['id'] ?? ''),
                strval($item['api_task_id'] ?? ''),
                strval($item['task_name'] ?? ''),
                strval($item['model_name'] ?? ''),
                strval($item['model_code'] ?? ''),
                strval($item['title'] ?? ''),
                strval($item['status_text'] ?? ''),
                strval($item['create_time_text'] ?? ''),
                strval($item['started_time_text'] ?? ''),
                strval($item['finished_time_text'] ?? ''),
                strval($item['duration_seconds'] ?? ''),
                strval($item['charge_text'] ?? ''),
                strval($item['billing_quantity_text'] ?? ''),
                strval($item['error_msg'] ?? ''),
            ];
            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<td>' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';
        return $html;
    }

    private static function resolveTaskModelName(int $aid, string $taskType, string $modelCode, array $input): string
    {
        $modelCode = trim($modelCode);
        $taskType = trim($taskType);
        if ($aid > 0 && $taskType !== '' && $modelCode !== '') {
            $cacheKey = $aid . ':' . $taskType . ':' . $modelCode;
            if (!array_key_exists($cacheKey, self::$taskModelNameCache)) {
                self::$taskModelNameCache[$cacheKey] = trim(strval(Db::name('digitalavatar_model')
                    ->where('aid', $aid)
                    ->where('task_type', $taskType)
                    ->where('model_code', $modelCode)
                    ->value('title')));
            }
            if (self::$taskModelNameCache[$cacheKey] !== '') {
                return self::$taskModelNameCache[$cacheKey];
            }
        }
        $fallback = trim(strval($input['model_name'] ?? ($input['model_title'] ?? '')));
        if ($fallback !== '') {
            return $fallback;
        }
        return $modelCode;
    }

    private static function extractApiTaskId(array $result, array $input, int $localId): string
    {
        $bags = [$result];
        if (is_array($result['data'] ?? null)) {
            $bags[] = $result['data'];
        }
        if (is_array($result['provider_response'] ?? null)) {
            $bags[] = $result['provider_response'];
            if (is_array($result['provider_response']['data'] ?? null)) {
                $bags[] = $result['provider_response']['data'];
            }
        }
        if (is_array($result['provider_calls'] ?? null)) {
            foreach ($result['provider_calls'] as $call) {
                if (!is_array($call)) {
                    continue;
                }
                $bags[] = $call;
                if (is_array($call['response'] ?? null)) {
                    $bags[] = $call['response'];
                }
                if (is_array($call['data'] ?? null)) {
                    $bags[] = $call['data'];
                }
            }
        }
        $keys = ['remote_id', 'audio_id', 'video_id', 'task_id', 'profile_id', 'model_id', 'voice_id'];
        foreach ($bags as $bag) {
            foreach ($keys as $key) {
                $value = trim(strval($bag[$key] ?? ''));
                if ($value === '' || $value === '0') {
                    continue;
                }
                if (is_numeric($value) && intval($value) === $localId) {
                    continue;
                }
                return $value;
            }
        }
        return '';
    }

    private static function billingQuantityInfo(string $taskType, array $input, array $result): array
    {
        $stored = floatval($input['billing_quantity'] ?? 0);
        $storedMode = strval($input['billing_mode'] ?? '');
        $seconds = floatval($input['audio_seconds'] ?? ($input['audio_duration'] ?? ($input['duration'] ?? ($result['duration'] ?? ($result['audio_seconds'] ?? 0)))));
        if ($seconds <= 0 && $stored > 0 && in_array($taskType, ['avatar_video', 'video'], true)) {
            $seconds = $storedMode === 'minutes' ? ($stored * 60) : $stored;
        }
        $count = intval($input['count'] ?? ($input['n'] ?? ($result['count'] ?? ($result['image_count'] ?? 0))));
        if ($count <= 0 && $stored > 0 && $taskType === 'image') {
            $count = intval($stored);
        }
        $text = trim(strval($input['text'] ?? ($input['content'] ?? ($input['prompt'] ?? ($input['script'] ?? '')))));
        $chars = $text === '' ? 0 : (function_exists('mb_strlen') ? intval(mb_strlen($text, 'UTF-8')) : strlen($text));
        if ($chars <= 0 && $stored > 0 && $taskType === 'audio') {
            $chars = intval($stored);
        }

        if (in_array($taskType, ['avatar_video', 'video'], true)) {
            $secondsText = $seconds > 0 ? (rtrim(rtrim(number_format($seconds, 2, '.', ''), '0'), '.') . '秒') : '--';
            return ['value' => $seconds, 'text' => $secondsText];
        }
        if ($taskType === 'image') {
            if ($count <= 0) {
                $urls = [];
                if (is_array($result['image_urls'] ?? null)) {
                    $urls = $result['image_urls'];
                } elseif (is_array($result['images'] ?? null)) {
                    $urls = $result['images'];
                } elseif (is_array($result['data'] ?? null) && is_array($result['data']['image_urls'] ?? null)) {
                    $urls = $result['data']['image_urls'];
                }
                if ($urls) {
                    $count = count($urls);
                }
            }
            if ($count <= 0) {
                $count = 1;
            }
            return ['value' => $count, 'text' => $count . '张'];
        }
        if ($taskType === 'audio') {
            return ['value' => $chars, 'text' => ($chars > 0 ? ($chars . '字') : '--')];
        }
        if ($chars > 0) {
            return ['value' => $chars, 'text' => $chars . '字'];
        }
        if ($seconds > 0) {
            return ['value' => $seconds, 'text' => rtrim(rtrim(number_format($seconds, 2, '.', ''), '0'), '.') . '秒'];
        }
        if ($count > 0) {
            return ['value' => $count, 'text' => $count . '张'];
        }
        return ['value' => 0, 'text' => '--'];
    }

    private static function formatChargeText(string $source, $creditCost, int $quotaUnits, int $creditStatus): string
    {
        $creditCost = DigitalAvatarCreditService::creditAmount($creditCost);
        $creditText = DigitalAvatarCreditService::formatCredit($creditCost);
        if ($source === 'daily_quota') {
            $amount = $quotaUnits > 0 ? $quotaUnits : $creditCost;
            $text = $amount > 0 ? ('每日赠送 ' . $amount) : '未扣费';
        } elseif ($source === 'model_quota') {
            $text = $quotaUnits > 0 ? ('模型额度 ' . $quotaUnits) : '未扣费';
        } elseif ($source === 'mixed') {
            $parts = [];
            if ($quotaUnits > 0) {
                $parts[] = '额度 ' . $quotaUnits;
            }
            if ($creditCost > 0) {
                $parts[] = '算力 ' . $creditText . '点';
            }
            $text = $parts !== [] ? implode(' + ', $parts) : '未扣费';
        } elseif ($creditCost > 0) {
            $text = '算力 ' . $creditText . '点';
        } else {
            $text = '未扣费';
        }
        if ($creditStatus === 1) {
            $text .= '（已冻结）';
        } elseif ($creditStatus === 3) {
            $text .= '（已退回）';
        }
        return $text;
    }

    private static function formatTimestamp(int $time): string
    {
        return $time > 0 ? date('Y-m-d H:i:s', $time) : '--';
    }

    private static function sanitizeCloneInput($raw, int $oldTaskId = 0): array
    {
        $input = is_array($raw) ? $raw : [];
        $drop = [
            'id', 'task_id', 'api_task_id', 'remote_id', 'audio_id', 'video_id', 'image_id',
            'job_id', 'jobId', 'provider_task_id', 'provider_job_id', 'execution_id',
            'submit_id', 'queue_id', 'third_id', 'third_task_id', 'provider_calls',
            'provider_response', 'result', 'result_json', 'error_msg', 'lot_consume',
            'quota_taken', 'quota_key', 'daily_quota_units', 'charge_breakdown',
            'next_poll_time', 'progress', 'status', 'credit_cost', 'credit_status',
            'credit_source', 'started_time', 'finished_time', 'create_time', 'update_time',
            '_image_batch_item',
        ];
        foreach ($drop as $key) {
            unset($input[$key]);
        }
        foreach (['avatar', 'voice', 'history_audio', 'reference', 'inspire'] as $bagKey) {
            if (!is_array($input[$bagKey] ?? null)) {
                continue;
            }
            unset(
                $input[$bagKey]['task_id'],
                $input[$bagKey]['remote_id'],
                $input[$bagKey]['api_task_id'],
                $input[$bagKey]['job_id'],
                $input[$bagKey]['audio_id'],
                $input[$bagKey]['video_id']
            );
        }
        if ($oldTaskId > 0 && intval($input['conversation_id'] ?? 0) === $oldTaskId) {
            unset($input['conversation_id']);
        }
        return $input;
    }

    private static function normalizePayload(array $input): array
    {
        $payload = $input;
        unset($payload['request_id'], $payload['provider_key'], $payload['model_code']);
        return self::limitDepth($payload);
    }

    private static function limitDepth($value, int $depth = 0)
    {
        if ($depth > 5) {
            return null;
        }
        if (is_array($value)) {
            $result = [];
            foreach (array_slice($value, 0, 100, true) as $key => $item) {
                $result[is_int($key) ? $key : mb_substr(strval($key), 0, 80)] = self::limitDepth($item, $depth + 1);
            }
            return $result;
        }
        if (is_string($value)) {
            return mb_substr($value, 0, 20000);
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        return strval($value);
    }

    private static function settleCreatedTaskBilling(int $aid, int $taskId, bool $success): void
    {
        $row = Db::name('digitalavatar_task')->where('aid', $aid)->where('id', $taskId)->find();
        if (!$row || intval($row['credit_status'] ?? 0) !== 1) {
            return;
        }
        DigitalAvatarCreditService::settleDailyQuota($aid, $taskId, $success);
        $creditCost = DigitalAvatarCreditService::creditAmount($row['credit_cost'] ?? 0);
        if ($success) {
            if ($creditCost > 0) {
                DigitalAvatarCreditService::confirm($aid, $taskId);
            } else {
                Db::name('digitalavatar_task')->where('id', $taskId)->update([
                    'credit_status' => 2,
                    'update_time' => time(),
                ]);
            }
            return;
        }
        if ($creditCost > 0) {
            DigitalAvatarCreditService::refund($aid, $taskId);
            return;
        }
        Db::name('digitalavatar_task')->where('id', $taskId)->update([
            'credit_status' => 3,
            'update_time' => time(),
        ]);
    }

    private static function setTaskQuotaUnits(int $taskId, int $units): void
    {
        Db::name('digitalavatar_task')->where('id', $taskId)->update([
            'credit_source' => 'daily_quota',
            'quota_units' => max(0, $units),
            'update_time' => time(),
        ]);
    }

    private static function encodeJson($value): string
    {
        return json_encode(is_array($value) ? $value : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function decodeJson($value): array
    {
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function refreshMemberRunningTasks(int $aid, int $bid, int $memberId, int $limit = 8): int
    {
        if ($aid <= 0 || $memberId <= 0) {
            return 0;
        }
        $query = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('deleted_time', 0)
            ->where('status', 1);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        $rows = $query->order('update_time asc,id asc')->limit(max(1, min(20, $limit)))->select()->toArray();
        $handled = 0;
        foreach ($rows as $row) {
            if (self::refreshRunningTaskRow($row, 1)) {
                $handled++;
            }
        }
        return $handled;
    }

    private static function refreshRunningTaskRow(array $row, int $minIdleSeconds = 3): bool
    {
        if (intval($row['status'] ?? 0) !== 1 || intval($row['id'] ?? 0) <= 0) {
            return false;
        }
        if (!self::claimRunningTask($row, time(), $minIdleSeconds)) {
            return false;
        }
        self::executeClaimedTask($row);
        return true;
    }

    private static function claimRunningTask(array $row, int $now, int $minIdleSeconds = 3): bool
    {
        $updated = Db::name('digitalavatar_task')
            ->where('id', intval($row['id'] ?? 0))
            ->where('status', 1)
            ->where('update_time', '<=', $now - max(0, $minIdleSeconds))
            ->update([
                'update_time' => $now,
            ]);
        return intval($updated) > 0;
    }

    private static function claimPendingTask(array $row, int $now): bool
    {
        $updated = Db::name('digitalavatar_task')
            ->where('id', intval($row['id'] ?? 0))
            ->whereIn('status', [0, 5])
            ->update([
                'status' => 1,
                'progress' => max(1, intval($row['progress'] ?? 0)),
                'started_time' => intval($row['started_time'] ?? 0) ?: $now,
                'update_time' => $now,
            ]);
        return intval($updated) > 0;
    }

    private static function countRunningApiTasks(int $aid, int $memberId, int $bid = 0): int
    {
        if ($aid <= 0 || $memberId <= 0) {
            return 0;
        }
        $query = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('deleted_time', 0)
            ->where('status', 1);
        if ($bid > 0) {
            $query->where('bid', $bid);
        }
        return intval($query->count());
    }

    private static function submitQueuedForMember(int $aid, int $bid, int $memberId, int $maxStart = 1): int
    {
        if ($aid <= 0 || $memberId <= 0) {
            return 0;
        }
        if (self::$submitDepth >= 2) {
            return 0;
        }
        $maxStart = max(1, min(20, $maxStart));
        $started = 0;
        self::$submitDepth++;
        try {
        while ($started < $maxStart) {
            $query = Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('deleted_time', 0)
                ->whereIn('status', [0, 5]);
            if ($bid > 0) {
                $query->where('bid', $bid);
            }
            $row = $query->order('create_time asc,id asc')->find();
            if (!$row) {
                break;
            }
            if (!self::tryClaimPendingTask($row)) {
                $freshStatus = intval(Db::name('digitalavatar_task')->where('id', intval($row['id'] ?? 0))->value('status'));
                if (in_array($freshStatus, [0, 5], true)) {
                    break;
                }
                continue;
            }
            $fresh = Db::name('digitalavatar_task')->where('id', intval($row['id'] ?? 0))->find() ?: $row;
            self::executeClaimedTask($fresh);
            $started++;
        }
        } finally {
            self::$submitDepth--;
        }
        return $started;
    }

    private static function tryClaimPendingTask(array $row): bool
    {
        $taskId = intval($row['id'] ?? 0);
        $aid = intval($row['aid'] ?? 0);
        $bid = intval($row['bid'] ?? 0);
        $memberId = intval($row['member_id'] ?? 0);
        if ($taskId <= 0 || $aid <= 0 || $memberId <= 0) {
            return false;
        }
        $claimed = false;
        Db::transaction(function () use ($row, $taskId, $aid, $bid, $memberId, &$claimed) {
            DigitalAvatarCreditService::lockMemberAccount($aid, $bid, $memberId);
            $fresh = Db::name('digitalavatar_task')->where('id', $taskId)->lock(true)->find();
            if (!$fresh || !in_array(intval($fresh['status'] ?? 0), [0, 5], true) || intval($fresh['deleted_time'] ?? 0) > 0) {
                return;
            }
            if (!self::canStartTask($fresh)) {
                return;
            }
            $claimed = self::claimPendingTask($fresh, time());
        });
        return $claimed;
    }

    private static function canStartTask(array $row): bool
    {
        $aid = intval($row['aid'] ?? 0);
        $bid = intval($row['bid'] ?? 0);
        $memberId = intval($row['member_id'] ?? 0);
        if ($aid <= 0 || $memberId <= 0) {
            return false;
        }
        $maxConcurrent = DigitalAvatarCreditService::memberConcurrentLimit($aid, $bid, $memberId);
        $running = self::countRunningApiTasks($aid, $memberId, $bid);
        return $running < $maxConcurrent;
    }

    private static function isImageProviderQuotaError(string $message): bool
    {
        return (bool) preg_match('/预扣费|剩余额度|额度不足|insufficient.?quota|billing/i', $message);
    }

    private static function failQueuedAgentSiblingsOnQuota(array $row, string $message): void
    {
        if (!self::isImageProviderQuotaError($message)) {
            return;
        }
        $aid = intval($row['aid'] ?? 0);
        $memberId = intval($row['member_id'] ?? 0);
        $taskId = intval($row['id'] ?? 0);
        $createTime = intval($row['create_time'] ?? 0);
        if ($aid <= 0 || $memberId <= 0 || $createTime <= 0) {
            return;
        }
        $siblings = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('deleted_time', 0)
            ->where('task_type', 'image')
            ->whereIn('status', [0, 5])
            ->where('id', '<>', $taskId)
            ->where('create_time', '>=', $createTime - 2)
            ->where('create_time', '<=', $createTime + 8)
            ->select()
            ->toArray();
        foreach ($siblings as $sibling) {
            $input = self::decodeJson($sibling['input_json'] ?? '');
            if (strval($input['source'] ?? '') !== 'image_agent') {
                continue;
            }
            try {
                self::failTask($aid, intval($sibling['id'] ?? 0), $message);
            } catch (\Throwable $e) {
            }
        }
    }

    private static function executeClaimedTask(array $row): array
    {
        $taskId = intval($row['id'] ?? 0);
        $aid = intval($row['aid'] ?? 0);
        $item = [
            'task_id' => $taskId,
            'aid' => $aid,
            'status' => 'failed',
        ];

        try {
            $execution = DigitalAvatarProviderService::executeTask($aid, $row);
            $executionStatus = trim(strval($execution['status'] ?? 'failed'));
            if ($executionStatus === 'success') {
                $result = is_array($execution['result'] ?? null) ? $execution['result'] : [];
                self::saveResultAssets($row, $result);
                self::completeTask($aid, $taskId, $result);
                $item['status'] = 'success';
                $item['message'] = trim(strval($execution['message'] ?? '任务完成'));
                return ['success' => 1, 'item' => $item];
            }

            if ($executionStatus === 'pending' || $executionStatus === 'skipped') {
                $result = is_array($execution['result'] ?? null) ? $execution['result'] : [];
                if ($executionStatus === 'skipped') {
                    $item['status'] = 'skipped';
                    $item['message'] = trim(strval($execution['message'] ?? '等待下一次轮询'));
                    return ['pending' => 1, 'item' => $item];
                }
                Db::name('digitalavatar_task')
                    ->where('id', $taskId)
                    ->where('status', 1)
                    ->update([
                        'progress' => max(1, min(99, intval($execution['progress'] ?? $result['progress'] ?? 1))),
                        'result_json' => self::encodeJson($result),
                        'error_msg' => '',
                        'update_time' => time(),
                    ]);
                $item['status'] = 'pending';
                $item['message'] = trim(strval($execution['message'] ?? '任务处理中'));
                $item['remote_id'] = trim(strval($execution['remote_id'] ?? $result['remote_id'] ?? ''));
                return ['pending' => 1, 'item' => $item];
            }

            $message = trim(strval($execution['message'] ?? $execution['error'] ?? '提供商执行失败'));
            $failedResult = is_array($execution['result'] ?? null) ? $execution['result'] : [];
            self::failTask($aid, $taskId, $message, $failedResult);
            self::failQueuedAgentSiblingsOnQuota($row, $message);
            $item['message'] = $message;
            return ['failed' => 1, 'item' => $item];
        } catch (\Throwable $e) {
            try {
                self::failTask($aid, $taskId, $e->getMessage());
            } catch (\Throwable $settleException) {
                $item['settle_error'] = $settleException->getMessage();
            }
            self::failQueuedAgentSiblingsOnQuota($row, $e->getMessage());
            $item['message'] = $e->getMessage();
            return ['failed' => 1, 'item' => $item];
        }
    }

    private static function saveResultAssets(array $task, array $result): void
    {
        $taskType = strval($task['task_type'] ?? '');
        if ($taskType === 'avatar_clone') {
            DigitalAvatarService::saveMemberAvatarFromTask(
                intval($task['aid'] ?? 0),
                intval($task['bid'] ?? 0),
                intval($task['member_id'] ?? 0),
                $task,
                $result
            );
        } elseif ($taskType === 'voice_clone') {
            DigitalAvatarService::saveMemberVoiceFromTask(
                intval($task['aid'] ?? 0),
                intval($task['bid'] ?? 0),
                intval($task['member_id'] ?? 0),
                $task,
                $result
            );
        }

        $assets = [];
        if (trim(strval($result['url'] ?? '')) !== '') {
            $assets[] = [
                'url' => $result['url'],
                'asset_type' => self::assetTypeForTask($taskType),
                'name' => strval($task['title'] ?? ''),
                'metadata' => ['source' => 'provider'],
            ];
        }
        if (is_array($result['assets'] ?? null)) {
            foreach ($result['assets'] as $asset) {
                if (is_array($asset) && trim(strval($asset['url'] ?? '')) !== '') {
                    $assets[] = $asset;
                }
            }
        }

        foreach ($assets as $asset) {
            $exists = Db::name('digitalavatar_asset')
                ->where('aid', intval($task['aid'] ?? 0))
                ->where('task_id', intval($task['id'] ?? 0))
                ->where('url', trim(strval($asset['url'] ?? '')))
                ->where('delete_time', 0)
                ->find();
            if ($exists) {
                continue;
            }
            self::saveAsset(intval($task['aid'] ?? 0), intval($task['member_id'] ?? 0), [
                'task_id' => intval($task['id'] ?? 0),
                'asset_type' => trim(strval($asset['asset_type'] ?? self::assetTypeForTask($taskType))),
                'name' => trim(strval($asset['name'] ?? $task['title'] ?? '')),
                'url' => trim(strval($asset['url'] ?? '')),
                'mime_type' => trim(strval($asset['mime_type'] ?? '')),
                'size' => intval($asset['size'] ?? 0),
                'duration' => floatval($asset['duration'] ?? 0),
                'metadata' => array_merge(
                    is_array($asset['metadata'] ?? null) ? $asset['metadata'] : [],
                    [
                        'task_type' => $taskType,
                        'source' => $taskType === 'video' ? 'creative_video' : ($taskType !== '' ? $taskType : 'provider'),
                    ]
                ),
            ]);
        }
    }

    private static function assetTypeForTask(string $taskType): string
    {
        if ($taskType === 'audio' || $taskType === 'voice_clone') {
            return 'audio';
        }
        if ($taskType === 'image') {
            return 'image';
        }
        return 'video';
    }
}
