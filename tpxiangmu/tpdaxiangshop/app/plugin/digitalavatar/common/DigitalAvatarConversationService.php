<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;
use think\facade\Log;

class DigitalAvatarConversationService
{
    private static $schemaReady = false;
    private static $avatarRefCache = [];

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        self::$schemaReady = true;
        $prefix = strval(config('database.connections.mysql.prefix', ''));
        $table = str_replace('`', '', $prefix . 'digitalavatar_conversation');
        if ($table === '') {
            return;
        }
        try {
            $exists = Db::query("SHOW TABLES LIKE '" . str_replace("'", '', $table) . "'");
            if (!$exists) {
                Db::execute(
                    "CREATE TABLE IF NOT EXISTS `" . $table . "` (
                      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                      `aid` int(11) NOT NULL DEFAULT '0',
                      `bid` int(11) NOT NULL DEFAULT '0',
                      `member_id` int(11) NOT NULL DEFAULT '0',
                      `title` varchar(120) NOT NULL DEFAULT '',
                      `conv_type` varchar(40) NOT NULL DEFAULT '',
                      `pinned` tinyint(1) NOT NULL DEFAULT '0',
                      `pin_time` int(11) NOT NULL DEFAULT '0',
                      `last_time` int(11) NOT NULL DEFAULT '0',
                      `agent_session_id` int(11) NOT NULL DEFAULT '0',
                      `deleted_time` int(11) NOT NULL DEFAULT '0',
                      `create_time` int(11) NOT NULL DEFAULT '0',
                      `update_time` int(11) NOT NULL DEFAULT '0',
                      PRIMARY KEY (`id`),
                      KEY `idx_member_last` (`aid`,`member_id`,`deleted_time`,`pinned`,`last_time`),
                      KEY `idx_agent_session` (`aid`,`agent_session_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC COMMENT='AI数字人对话记录'"
                );
            }
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exists') === false) {
                Log::error('digitalavatar conversation table: ' . $e->getMessage());
            }
        }
        DigitalAvatarInstaller::ensureConversationTaskColumn();
        DigitalAvatarInstaller::ensureConversationDefaultColumn();
        DigitalAvatarInstaller::ensureTaskStarredColumn();
    }

    public static function listForMember(int $aid, int $bid, int $memberId, int $offset = 0, int $limit = 8): array
    {
        self::ensureSchema();
        if ($aid <= 0 || $memberId <= 0) {
            return ['list' => [], 'total' => 0, 'offset' => 0, 'limit' => $limit, 'has_more' => 0];
        }
        self::backfillIfNeeded($aid, $bid, $memberId);
        $offset = max(0, $offset);
        $limit = max(1, min(20, $limit));
        $total = intval(
            Db::name('digitalavatar_conversation')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('deleted_time', 0)
                ->count()
        );
        $rows = Db::name('digitalavatar_conversation')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('deleted_time', 0)
            ->order('pinned desc, pin_time desc, last_time desc, id desc')
            ->limit($offset, $limit)
            ->select()
            ->toArray();
        $ids = [];
        foreach ($rows as $row) {
            $id = intval($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $firstTypes = self::firstTaskTypes($aid, $ids);
        $list = [];
        foreach ($rows as $row) {
            $item = self::formatRow($row);
            $cid = intval($row['id'] ?? 0);
            if ($cid > 0 && !empty($firstTypes[$cid])) {
                $item['conv_type'] = $firstTypes[$cid];
            }
            $list[] = $item;
        }
        $loaded = $offset + count($list);
        return [
            'list' => $list,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => $loaded < $total ? 1 : 0,
        ];
    }

    public static function messages(int $aid, int $bid, int $memberId, int $conversationId, int $beforeId = 0, int $limit = 0, int $afterId = 0): array
    {
        self::ensureSchema();
        $row = self::owned($aid, $memberId, $conversationId);
        $total = intval(
            Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('conversation_id', $conversationId)
                ->where('deleted_time', 0)
                ->count()
        );
        $paginate = $limit > 0 || $beforeId > 0 || $afterId > 0;
        if (!$paginate) {
            $tasks = Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('conversation_id', $conversationId)
                ->where('deleted_time', 0)
                ->order('create_time asc, id asc')
                ->select()
                ->toArray();
            $items = [];
            foreach ($tasks as $task) {
                $items[] = self::formatMessage(DigitalAvatarTaskService::formatTask($task));
            }
            return [
                'conversation' => self::formatRow($row),
                'list' => $items,
                'total' => $total,
                'limit' => $total,
                'has_more' => 0,
            ];
        }
        $limit = max(1, min(20, $limit > 0 ? $limit : ($beforeId > 0 ? 5 : 6)));
        $query = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('conversation_id', $conversationId)
            ->where('deleted_time', 0);
        $hasMore = 0;
        if ($afterId > 0) {
            $cursor = Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('conversation_id', $conversationId)
                ->where('id', $afterId)
                ->find();
            if (!$cursor) {
                return [
                    'conversation' => self::formatRow($row),
                    'list' => [],
                    'total' => $total,
                    'limit' => $limit,
                    'has_more' => 0,
                ];
            }
            $ct = intval($cursor['create_time'] ?? 0);
            $cid = intval($cursor['id']);
            $query->whereRaw('(create_time > ? OR (create_time = ? AND id > ?))', [$ct, $ct, $cid]);
            $tasks = $query->order('create_time asc, id asc')->limit($limit)->select()->toArray();
        } else {
            if ($beforeId > 0) {
                $cursor = Db::name('digitalavatar_task')
                    ->where('aid', $aid)
                    ->where('member_id', $memberId)
                    ->where('conversation_id', $conversationId)
                    ->where('id', $beforeId)
                    ->find();
                if ($cursor) {
                    $ct = intval($cursor['create_time'] ?? 0);
                    $cid = intval($cursor['id']);
                    $query->whereRaw('(create_time < ? OR (create_time = ? AND id < ?))', [$ct, $ct, $cid]);
                }
            }
            $tasks = $query->order('create_time desc, id desc')->limit($limit + 1)->select()->toArray();
            $hasMore = count($tasks) > $limit ? 1 : 0;
            if ($hasMore) {
                $tasks = array_slice($tasks, 0, $limit);
            }
            $tasks = array_reverse($tasks);
        }
        $items = [];
        foreach ($tasks as $task) {
            $items[] = self::formatMessage(DigitalAvatarTaskService::formatTask($task));
        }
        return [
            'conversation' => self::formatRow($row),
            'list' => $items,
            'total' => $total,
            'limit' => $limit,
            'has_more' => $hasMore,
        ];
    }

    public static function pin(int $aid, int $memberId, int $conversationId, int $pinned = 1): array
    {
        $row = self::owned($aid, $memberId, $conversationId);
        $on = $pinned ? 1 : 0;
        $now = time();
        Db::name('digitalavatar_conversation')->where('id', intval($row['id']))->update([
            'pinned' => $on,
            'pin_time' => $on ? $now : 0,
            'update_time' => $now,
        ]);
        return self::listForMember($aid, intval($row['bid'] ?? 0), $memberId);
    }

    public static function rename(int $aid, int $memberId, int $conversationId, string $title): array
    {
        $row = self::owned($aid, $memberId, $conversationId);
        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException('请填写对话标题');
        }
        $title = mb_substr($title, 0, 40);
        Db::name('digitalavatar_conversation')->where('id', intval($row['id']))->update([
            'title' => $title,
            'update_time' => time(),
        ]);
        return self::formatRow(array_merge($row, ['title' => $title]));
    }

    public static function remove(int $aid, int $memberId, int $conversationId): array
    {
        $row = self::owned($aid, $memberId, $conversationId);
        if (intval($row['is_default'] ?? 0) === 1) {
            throw new \RuntimeException('默认对话记录不能删除');
        }
        Db::name('digitalavatar_conversation')->where('id', intval($row['id']))->update([
            'deleted_time' => time(),
            'pinned' => 0,
            'update_time' => time(),
        ]);
        return ['id' => intval($row['id'])];
    }

    public static function ensureDefaultConversation(int $aid, int $bid, int $memberId): int
    {
        self::ensureSchema();
        if ($aid <= 0 || $memberId <= 0) {
            return 0;
        }
        $row = Db::name('digitalavatar_conversation')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('is_default', 1)
            ->where('deleted_time', 0)
            ->order('id asc')
            ->find();
        if ($row) {
            return intval($row['id'] ?? 0);
        }
        $deleted = Db::name('digitalavatar_conversation')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('is_default', 1)
            ->order('id asc')
            ->find();
        if ($deleted) {
            $now = time();
            $title = trim(strval($deleted['title'] ?? ''));
            Db::name('digitalavatar_conversation')->where('id', intval($deleted['id']))->update([
                'deleted_time' => 0,
                'pinned' => 0,
                'title' => $title !== '' ? $title : '默认对话记录',
                'last_time' => $now,
                'update_time' => $now,
            ]);
            return intval($deleted['id'] ?? 0);
        }
        return self::createConversation($aid, $bid, $memberId, '默认对话记录', '', 0, 1);
    }

    public static function bindTask(int $aid, int $bid, int $memberId, int $taskId, int $conversationId, string $title = '', string $taskType = '', string $mode = ''): int
    {
        self::ensureSchema();
        if ($taskId <= 0 || $memberId <= 0) {
            return 0;
        }
        $now = time();
        $taskRow = [];
        try {
            $taskRow = Db::name('digitalavatar_task')->where('id', $taskId)->where('aid', $aid)->find() ?: [];
        } catch (\Throwable $e) {
            $taskRow = [];
        }
        $autoTitle = self::autoTitleFromTask($taskRow);
        $mode = strtolower(trim($mode));
        if ($conversationId > 0) {
            try {
                self::owned($aid, $memberId, $conversationId);
            } catch (\Throwable $e) {
                $conversationId = 0;
            }
        }
        if ($conversationId <= 0) {
            if ($mode === 'new') {
                $conversationId = self::createConversation($aid, $bid, $memberId, $autoTitle, $taskType, 0, 0);
            } else {
                $conversationId = self::ensureDefaultConversation($aid, $bid, $memberId);
            }
        }
        if ($conversationId <= 0) {
            return 0;
        }
        try {
            Db::name('digitalavatar_task')->where('id', $taskId)->where('aid', $aid)->update([
                'conversation_id' => $conversationId,
            ]);
        } catch (\Throwable $e) {
            self::ensureSchema();
            Db::name('digitalavatar_task')->where('id', $taskId)->where('aid', $aid)->update([
                'conversation_id' => $conversationId,
            ]);
        }
        $save = [
            'last_time' => $now,
            'update_time' => $now,
        ];
        $convRow = [];
        try {
            $convRow = Db::name('digitalavatar_conversation')->where('id', $conversationId)->find() ?: [];
        } catch (\Throwable $e) {
            $convRow = [];
        }
        $isDefault = intval($convRow['is_default'] ?? 0) === 1;
        $currentType = strval($convRow['conv_type'] ?? '');
        if ($taskType !== '' && $currentType === '') {
            $save['conv_type'] = $taskType;
        }
        if (!$isDefault && trim(strval($convRow['title'] ?? '')) === '') {
            $save['title'] = mb_substr($autoTitle !== '' ? $autoTitle : $title, 0, 40);
        }
        Db::name('digitalavatar_conversation')->where('id', $conversationId)->update($save);
        return $conversationId;
    }

    public static function syncSession(int $aid, int $bid, int $memberId, array $session, array $taskIds, int $conversationId = 0, string $mode = ''): int
    {
        self::ensureSchema();
        $sessionId = intval($session['id'] ?? 0);
        if ($sessionId <= 0 || $memberId <= 0) {
            return 0;
        }
        $title = trim(strval($session['title'] ?? ''));
        if ($title === '') {
            $title = '图片对话';
        }
        $mode = strtolower(trim($mode));
        $taskIds = array_values(array_filter(array_map('intval', $taskIds)));
        $row = null;
        if ($conversationId > 0) {
            try {
                $row = self::owned($aid, $memberId, $conversationId);
            } catch (\Throwable $e) {
                $row = null;
                $conversationId = 0;
            }
        }
        if (!$row && $mode === 'new') {
            $row = Db::name('digitalavatar_conversation')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('agent_session_id', $sessionId)
                ->where('deleted_time', 0)
                ->find();
        }
        if (!$row && $conversationId <= 0 && $taskIds === []) {
            return 0;
        }
        if (!$row && $conversationId <= 0) {
            if ($mode === 'new') {
                $conversationId = self::createConversation($aid, $bid, $memberId, $title, 'image', $sessionId, 0);
            } else {
                $conversationId = self::ensureDefaultConversation($aid, $bid, $memberId);
            }
        } elseif ($row) {
            $conversationId = intval($row['id']);
            $save = [
                'last_time' => time(),
                'update_time' => time(),
            ];
            if (intval($row['is_default'] ?? 0) !== 1) {
                $save['title'] = mb_substr($title, 0, 40);
                $save['conv_type'] = 'image';
            }
            if (intval($row['agent_session_id'] ?? 0) <= 0 && $sessionId > 0) {
                $save['agent_session_id'] = $sessionId;
            }
            Db::name('digitalavatar_conversation')->where('id', $conversationId)->update($save);
        }
        foreach ($taskIds as $id) {
            if ($id > 0) {
                self::bindTask($aid, $bid, $memberId, $id, $conversationId, $title, 'image', $mode);
            }
        }
        return $conversationId;
    }

    public static function formatMessage(array $task): array
    {
        $input = is_array($task['input'] ?? null) ? $task['input'] : [];
        $result = is_array($task['result'] ?? null) ? $task['result'] : [];
        $text = trim(strval($input['text'] ?? ($input['content'] ?? ($input['prompt'] ?? ($input['script'] ?? ($task['title'] ?? ''))))));
        if ($text === '' && is_array($input['history_audio'] ?? null)) {
            $text = trim(strval($input['history_audio']['name'] ?? ($input['history_audio']['title'] ?? '')));
        }
        if ($text === '') {
            $text = trim(strval($input['history_audio_name'] ?? ($input['audio_name'] ?? ($input['voice_name'] ?? ''))));
        }
        $taskType = strval($task['task_type'] ?? '');
        $createTime = intval($task['create_time'] ?? 0);
        $metric = '';
        if (in_array($taskType, ['avatar_video', 'video', 'audio'], true)) {
            $metric = strval($task['billing_quantity_text'] ?? '');
            if ($metric === '' || $metric === '--') {
                $seconds = floatval($input['audio_seconds'] ?? ($input['audio_duration'] ?? ($input['duration'] ?? 0)));
                $metric = $seconds > 0 ? (rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.') . '秒') : '';
            }
        } elseif ($taskType === 'image') {
            $count = intval($input['count'] ?? ($input['n'] ?? 0));
            if ($count <= 0 && !empty($task['cover_url'])) {
                $count = 1;
            }
            $metric = $count > 0 ? ($count . '张') : '';
        }
        $promptText = self::pickPromptText($input, $text !== '' ? $text : strval($task['title'] ?? ''));
        return [
            'id' => intval($task['id'] ?? 0),
            'task_type' => $taskType,
            'task_name' => strval($task['task_name'] ?? ''),
            'record_kind' => strval($task['record_kind'] ?? 'task') ?: 'task',
            'title' => strval($task['title'] ?? ''),
            'status' => intval($task['status'] ?? 0),
            'status_text' => strval($task['status_text'] ?? ''),
            'create_time' => $createTime,
            'date_key' => $createTime > 0 ? date('Y-m-d', $createTime) : '',
            'date_label' => $createTime > 0 ? (intval(date('n', $createTime)) . '月' . intval(date('j', $createTime)) . '日') : '',
            'refs' => self::collectRefs($task, $input),
            'summary' => self::clipChars($text !== '' ? $text : strval($task['title'] ?? ''), 15),
            'summary_full' => $text,
            'prompt_text' => $promptText,
            'model_name' => strval($task['model_name'] ?? ($task['model_code'] ?? '')),
            'model_code' => strval($task['model_code'] ?? ''),
            'metric_text' => $metric,
            'cover_url' => strval($task['cover_url'] ?? ''),
            'video_url' => strval($task['video_url'] ?? ''),
            'audio_url' => strval($task['audio_url'] ?? ''),
            'download_url' => strval($task['download_url'] ?? ''),
            'credit_cost' => $task['credit_cost'] ?? 0,
            'charge_text' => strval($task['charge_text'] ?? ''),
            'billing_quantity_text' => strval($task['billing_quantity_text'] ?? ''),
            'create_time_text' => strval($task['create_time_text'] ?? ''),
            'started_time_text' => strval($task['started_time_text'] ?? ''),
            'finished_time_text' => strval($task['finished_time_text'] ?? ''),
            'duration_text' => strval($task['duration_text'] ?? ''),
            'starred' => intval($task['starred'] ?? 0),
            'conversation_id' => intval($task['conversation_id'] ?? 0),
            'fields' => self::messageFields($task, $input, $result, $metric),
            'error_msg' => strval($task['error_msg'] ?? ''),
            'input' => $input,
            'result' => $result,
        ];
    }

    public static function clipDisplayTitle(string $title): string
    {
        $title = trim($title);
        $width = 0;
        $out = '';
        $len = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        for ($i = 0; $i < $len; $i++) {
            $ch = function_exists('mb_substr') ? mb_substr($title, $i, 1, 'UTF-8') : substr($title, $i, 1);
            $w = (strlen($ch) > 1 || ord($ch) > 127) ? 2 : 1;
            if ($width + $w > 10) {
                return $out . '...';
            }
            $out .= $ch;
            $width += $w;
        }
        return $out;
    }

    private static function clipChars(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return '';
        }
        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($len <= $max) {
            return $text;
        }
        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
        return $cut . '...';
    }

    private static function untitledDateTitle(int $ts = 0): string
    {
        if ($ts <= 0) {
            $ts = time();
        }
        return '未命名' . intval(date('n', $ts)) . '月' . intval(date('j', $ts)) . '日';
    }

    private static function autoTitleFromTask(array $task): string
    {
        $input = [];
        if (is_array($task['input'] ?? null)) {
            $input = $task['input'];
        } else {
            $raw = strval($task['input_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $input = $decoded;
                }
            }
        }
        $text = self::pickPromptText($input, '');
        $text = trim(preg_replace('/\s+/u', '', $text));
        if ($text === '') {
            $maybe = trim(preg_replace('/\s+/u', '', strval($task['title'] ?? '')));
            $generic = [
                '未命名对话' => 1,
                '数字人视频' => 1,
                '创意视频' => 1,
                '创作图片' => 1,
                '图片对话' => 1,
                '语音克隆' => 1,
                '数字人克隆' => 1,
                '语音合成' => 1,
                '视频生成' => 1,
                '图片生成' => 1,
            ];
            if ($maybe !== '' && empty($generic[$maybe])) {
                $text = $maybe;
            }
        }
        if ($text !== '') {
            if (function_exists('mb_substr')) {
                return mb_substr($text, 0, 6, 'UTF-8');
            }
            return substr($text, 0, 6);
        }
        return self::untitledDateTitle(intval($task['create_time'] ?? 0));
    }

    private static function pickPromptText(array $input, string $fallback): string
    {
        $prompt = trim(strval($input['prompt'] ?? ($input['positive_prompt'] ?? ($input['image_prompt'] ?? ''))));
        if ($prompt !== '') {
            return $prompt;
        }
        $copy = trim(strval($input['script'] ?? ($input['text'] ?? ($input['content'] ?? ''))));
        if ($copy !== '') {
            return $copy;
        }
        $audio = trim(strval($input['audio_text'] ?? ($input['tts_text'] ?? '')));
        if ($audio !== '') {
            return $audio;
        }
        return trim($fallback);
    }

    private static function hasFieldValue($value): bool
    {
        if ($value === null) {
            return false;
        }
        $text = trim(strval($value));
        return $text !== '' && $text !== '--' && strcasecmp($text, 'null') !== 0;
    }

    private static function messageFields(array $task, array $input, array $result, string $metric): array
    {
        $resultData = is_array($result['data'] ?? null) ? $result['data'] : [];
        $modelVersion = '';
        foreach (['api_model_alias', 'api_model', 'xpro_model', 'vpro_model', 'max_avatar_model', 'model', 'minimax_model'] as $versionKey) {
            $candidate = trim(strval($input[$versionKey] ?? ''));
            if (self::hasFieldValue($candidate)) {
                $modelVersion = $candidate;
                break;
            }
        }
        $ratio = trim(strval($input['ratio'] ?? ($input['aspect_ratio'] ?? ($result['ratio'] ?? ($resultData['ratio'] ?? '')))));
        $width = intval($result['width'] ?? ($resultData['width'] ?? ($input['width'] ?? 0)));
        $height = intval($result['height'] ?? ($resultData['height'] ?? ($input['height'] ?? 0)));
        $rawSize = trim(strval($input['size'] ?? ($result['size'] ?? ($resultData['size'] ?? ''))));
        if ($width > 0 && $height > 0) {
            $size = $width . 'x' . $height;
        } elseif (self::hasFieldValue($rawSize) && !preg_match('/^\d+:\d+$/', $rawSize)) {
            $size = $rawSize;
        } else {
            $size = $ratio !== '' ? $ratio : $rawSize;
        }
        $resolution = trim(strval($input['resolution'] ?? ($result['resolution'] ?? ($resultData['resolution'] ?? ''))));
        $style = trim(strval($input['style'] ?? ($result['style'] ?? ($resultData['style'] ?? ''))));
        $taskType = strval($task['task_type'] ?? '');
        if ($taskType === 'image') {
            $metricLabel = '图片张数';
        } elseif (in_array($taskType, ['audio', 'voice_clone'], true)) {
            $metricLabel = (mb_strpos(strval($metric), '字') !== false) ? '字数' : '音频时长';
        } else {
            $metricLabel = '视频时长';
        }
        $finishedLabel = intval($task['status'] ?? 0) === 3 ? '失败时间' : '完成时间';
        $pairs = [
            ['模型名称', $task['model_name'] ?? ''],
            ['模型版本', $modelVersion],
            ['风格', $style],
            ['尺寸', $size],
            ['分辨率', $resolution],
            [$metricLabel, $metric !== '' ? $metric : ($task['billing_quantity_text'] ?? '')],
            ['消耗额度', $task['charge_text'] ?? ''],
            ['计费数量', $task['billing_quantity_text'] ?? ''],
            ['任务类型', $task['task_name'] ?? ''],
            ['任务ID', intval($task['id'] ?? 0) > 0 ? strval($task['id']) : ''],
            ['创建时间', $task['create_time_text'] ?? ''],
            ['生成时间', $task['started_time_text'] ?? ''],
            [$finishedLabel, $task['finished_time_text'] ?? ''],
            ['耗时', $task['duration_text'] ?? ''],
        ];
        $out = [];
        $seen = [];
        foreach ($pairs as $pair) {
            $label = strval($pair[0]);
            $value = trim(strval($pair[1]));
            if (!self::hasFieldValue($value)) {
                continue;
            }
            if ($label === '计费数量' && $metric !== '' && $value === $metric) {
                continue;
            }
            $key = $label . ':' . $value;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = 1;
            $out[] = ['label' => $label, 'value' => $value];
        }
        return $out;
    }

    private static function pickRefUrl($item): string
    {
        if (is_string($item) || is_numeric($item)) {
            return trim(strval($item));
        }
        if (!is_array($item)) {
            return '';
        }
        foreach (['url', 'cover_url', 'coverUrl', 'img_url', 'imgUrl', 'avatar_url', 'image_url', 'image', 'img', 'cover', 'src', 'preview_url', 'audio_url', 'video_url'] as $key) {
            $value = trim(strval($item[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private static function avatarRefFromTask(array $task, array $input): array
    {
        $aid = intval($task['aid'] ?? 0);
        $bid = intval($task['bid'] ?? 0);
        $mid = intval($task['member_id'] ?? 0);
        if ($aid <= 0 || $mid <= 0) {
            return [];
        }
        $key = $aid . ':' . $mid . ':' . strval($input['selected_avatar_local_id'] ?? '') . ':' . strval($input['avatar_id'] ?? '') . ':' . strval($input['selected_avatar_model_id'] ?? '');
        if (!array_key_exists($key, self::$avatarRefCache)) {
            try {
                self::$avatarRefCache[$key] = DigitalAvatarService::resolveAvatarSelection($aid, $bid, $mid, $input);
            } catch (\Throwable $e) {
                self::$avatarRefCache[$key] = [];
            }
        }
        return is_array(self::$avatarRefCache[$key]) ? self::$avatarRefCache[$key] : [];
    }

    private static function collectRefs(array $task, array $input): array
    {
        $refs = [];
        $push = function ($kind, $url, $name = '') use (&$refs) {
            $url = trim(strval($url));
            if ($url === '' || !preg_match('#^(https?:)?//|^/|^blob:#i', $url)) {
                return;
            }
            foreach ($refs as $item) {
                if ($item['url'] === $url) {
                    return;
                }
            }
            $refs[] = [
                'kind' => $kind,
                'url' => $url,
                'name' => strval($name),
            ];
        };
        $kindOf = function ($url, $fallback = 'image') {
            $url = strtolower(strval($url));
            if (preg_match('/\.(mp4|mov|webm|mkv|m4v)(\?|$)/i', $url)) {
                return 'video';
            }
            if (preg_match('/\.(mp3|wav|m4a|aac|ogg|flac)(\?|$)/i', $url)) {
                return 'audio';
            }
            return $fallback;
        };
        $pushBag = function ($item, $fallbackKind, $name) use ($push, $kindOf) {
            if (is_string($item) || is_numeric($item)) {
                $url = trim(strval($item));
                $push($kindOf($url, $fallbackKind), $url, $name);
                return;
            }
            if (!is_array($item)) {
                return;
            }
            $url = self::pickRefUrl($item);
            if ($url !== '') {
                $push($kindOf($url, strval($item['kind'] ?? $fallbackKind)), $url, strval($item['name'] ?? $name));
            }
            $push('audio', $item['audio_url'] ?? '', strval($item['name'] ?? '参考音频'));
            $push('video', $item['video_url'] ?? '', strval($item['name'] ?? '参考视频'));
        };
        $push('image', $input['cover_url'] ?? ($input['coverUrl'] ?? ''), '形象');
        $push('image', $input['img_url'] ?? ($input['imgUrl'] ?? ''), '形象');
        $push('image', $input['img'] ?? '', '灵感图');
        $push('image', $input['avatar_cover'] ?? '', '形象');
        $push('image', $input['first_frame'] ?? '', '首帧');
        $push('image', $input['last_frame'] ?? '', '尾帧');
        if (is_array($input['avatar'] ?? null)) {
            $pushBag($input['avatar'], 'image', strval($input['avatar']['name'] ?? '形象'));
        }
        if (is_array($input['inspire'] ?? null)) {
            $pushBag($input['inspire'], 'image', '灵感图');
        }
        if (is_array($input['reference'] ?? null)) {
            $pushBag($input['reference'], 'image', '参考');
        }
        $push('audio', $input['audio_url'] ?? '', '音频');
        $push('audio', $input['history_audio_url'] ?? '', '历史音频');
        if (is_array($input['history_audio'] ?? null)) {
            $pushBag($input['history_audio'], 'audio', strval($input['history_audio']['name'] ?? '历史音频'));
        }
        $sample = trim(strval($input['sample_url'] ?? ''));
        if ($sample !== '') {
            $push($kindOf($sample, 'audio'), $sample, '参考');
        }
        foreach (['reference_images', 'gallery', 'refs', 'images', 'references'] as $key) {
            if (!is_array($input[$key] ?? null)) {
                continue;
            }
            foreach ($input[$key] as $item) {
                $pushBag($item, 'image', '参考图');
            }
        }
        $hasVisual = false;
        foreach ($refs as $item) {
            if (in_array($item['kind'], ['image', 'video'], true)) {
                $hasVisual = true;
                break;
            }
        }
        if (!$hasVisual) {
            $avatar = self::avatarRefFromTask($task, $input);
            $localId = trim(strval($input['selected_avatar_local_id'] ?? ''));
            $modelId = trim(strval($input['selected_avatar_model_id'] ?? ''));
            $avatarId = trim(strval($input['avatar_id'] ?? ''));
            $matched = $avatar && (
                ($localId !== '' && strval($avatar['local_id'] ?? '') === $localId)
                || ($modelId !== '' && strval($avatar['model_id'] ?? '') === $modelId)
                || ($avatarId !== '' && strval($avatar['id'] ?? '') === $avatarId)
            );
            if ($matched) {
                $name = strval($avatar['name'] ?? '形象');
                $cover = self::pickRefUrl($avatar);
                if ($cover !== '') {
                    $push($kindOf($cover, 'image'), $cover, $name);
                } elseif (trim(strval($avatar['preview_url'] ?? '')) !== '') {
                    $push($kindOf($avatar['preview_url'], 'video'), $avatar['preview_url'], $name);
                }
            }
        }
        return array_slice($refs, 0, 16);
    }

    private static function firstTaskTypes(int $aid, array $conversationIds): array
    {
        $ids = [];
        foreach ($conversationIds as $id) {
            $id = intval($id);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            return [];
        }
        try {
            $rows = Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('conversation_id', 'in', $ids)
                ->where('deleted_time', 0)
                ->field('id,conversation_id,task_type')
                ->order('id asc')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            $cid = intval($row['conversation_id'] ?? 0);
            if ($cid > 0 && !isset($map[$cid])) {
                $map[$cid] = strval($row['task_type'] ?? '');
            }
        }
        return $map;
    }

    private static function formatRow(array $row): array
    {
        $isDefault = intval($row['is_default'] ?? 0) === 1;
        $title = trim(strval($row['title'] ?? ''));
        if ($title === '') {
            $title = $isDefault ? '默认对话记录' : '未命名对话';
        }
        return [
            'id' => intval($row['id'] ?? 0),
            'title' => $title,
            'title_short' => self::clipDisplayTitle($title),
            'conv_type' => strval($row['conv_type'] ?? ''),
            'pinned' => intval($row['pinned'] ?? 0),
            'is_default' => $isDefault ? 1 : 0,
            'last_time' => intval($row['last_time'] ?? 0),
            'agent_session_id' => intval($row['agent_session_id'] ?? 0),
        ];
    }

    private static function owned(int $aid, int $memberId, int $conversationId): array
    {
        self::ensureSchema();
        if ($conversationId <= 0) {
            throw new \RuntimeException('对话不存在');
        }
        $row = Db::name('digitalavatar_conversation')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $conversationId)
            ->where('deleted_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('对话不存在');
        }
        return $row;
    }

    private static function createConversation(int $aid, int $bid, int $memberId, string $title, string $type, int $sessionId, int $isDefault = 0): int
    {
        $now = time();
        $isDefault = $isDefault ? 1 : 0;
        $title = trim($title);
        if ($title === '') {
            $title = $isDefault ? '默认对话记录' : self::untitledDateTitle(time());
        }
        return intval(Db::name('digitalavatar_conversation')->insertGetId([
            'aid' => $aid,
            'bid' => max(0, $bid),
            'member_id' => $memberId,
            'title' => mb_substr($title, 0, 40),
            'conv_type' => $type,
            'pinned' => 0,
            'is_default' => $isDefault,
            'pin_time' => 0,
            'last_time' => $now,
            'agent_session_id' => max(0, $sessionId),
            'deleted_time' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]));
    }

    private static function backfillIfNeeded(int $aid, int $bid, int $memberId): void
    {
        $count = intval(Db::name('digitalavatar_conversation')->where('aid', $aid)->where('member_id', $memberId)->count());
        if ($count > 0) {
            return;
        }
        try {
            $sessions = Db::name('digitalavatar_agent_session')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('title', '<>', '')
                ->order('update_time desc')
                ->limit(40)
                ->select()
                ->toArray();
            $usedTask = [];
            foreach ($sessions as $session) {
                $ids = [];
                foreach (explode(',', strval($session['last_task_ids'] ?? '')) as $id) {
                    $id = intval($id);
                    if ($id > 0) {
                        $ids[] = $id;
                        $usedTask[$id] = 1;
                    }
                }
                self::syncSession($aid, $bid, $memberId, $session, $ids);
            }
            $tasks = Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('deleted_time', 0)
                ->order('id desc')
                ->limit(60)
                ->select()
                ->toArray();
            foreach ($tasks as $task) {
                $id = intval($task['id'] ?? 0);
                if ($id <= 0 || isset($usedTask[$id]) || intval($task['conversation_id'] ?? 0) > 0) {
                    continue;
                }
                self::bindTask(
                    $aid,
                    $bid,
                    $memberId,
                    $id,
                    0,
                    strval($task['title'] ?? ''),
                    strval($task['task_type'] ?? '')
                );
            }
        } catch (\Throwable $e) {
            Log::error('digitalavatar conversation backfill: ' . $e->getMessage());
        }
    }
}
