<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Db;
use think\facade\Log;

class DigitalAvatarImageAgentRuntime
{
    private const MAX_STEPS = 8;
    private const MAX_COUNT = 8;
    private const UI_LIMIT = 40;
    private const MSG_LIMIT = 20;

    public static function session(int $aid, int $bid, int $memberId, int $sessionId = 0): array
    {
        self::assertEnabled($aid);
        if ($sessionId <= 0) {
            return [
                'session_id' => 0,
                'status' => 'idle',
                'messages' => [],
                'question' => null,
                'task_ids' => [],
                'title' => '',
            ];
        }
        $row = Db::name('digitalavatar_agent_session')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $sessionId)
            ->find();
        if (!is_array($row)) {
            throw new \RuntimeException('对话不存在');
        }
        return self::formatSession($row);
    }

    public static function listSessions(int $aid, int $bid, int $memberId, int $page = 1, int $limit = 40): array
    {
        self::assertEnabled($aid);
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));
        $baseWhere = function ($query) use ($aid, $memberId) {
            $query->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('title', '<>', '');
        };
        $total = intval(Db::name('digitalavatar_agent_session')->where($baseWhere)->count());
        $rows = Db::name('digitalavatar_agent_session')
            ->where($baseWhere)
            ->order('update_time desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        $taskIds = [];
        foreach ($rows as $row) {
            foreach (self::sessionTaskIds($row) as $id) {
                $taskIds[$id] = $id;
            }
        }
        $covers = [];
        if ($taskIds !== []) {
            $tasks = Db::name('digitalavatar_task')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('id', 'in', array_values($taskIds))
                ->where('deleted_time', 0)
                ->select()
                ->toArray();
            foreach ($tasks as $task) {
                $url = self::rowImageUrl($task);
                if ($url !== '') {
                    $covers[intval($task['id'])] = $url;
                }
            }
        }
        $list = [];
        foreach ($rows as $row) {
            $cover = '';
            $ids = self::sessionTaskIds($row);
            for ($i = count($ids) - 1; $i >= 0; $i--) {
                if (!empty($covers[$ids[$i]])) {
                    $cover = $covers[$ids[$i]];
                    break;
                }
            }
            $list[] = [
                'session_id' => intval($row['id'] ?? 0),
                'title' => strval($row['title'] ?: '未命名对话'),
                'status' => strval($row['status'] ?? 'active'),
                'cover' => $cover,
                'task_ids' => $ids,
                'update_time' => intval($row['update_time'] ?? 0),
                'create_time' => intval($row['create_time'] ?? 0),
            ];
        }
        return [
            'list' => $list,
            'total' => $total,
        ];
    }

    public static function run(int $aid, int $bid, int $memberId, array $input): array
    {
        self::assertEnabled($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }

        $userText = trim(strval($input['message'] ?? ($input['prompt'] ?? '')));
        $answer = self::normalizeAnswer($input['answer'] ?? null);
        $gallery = self::normalizeGallery($input['gallery'] ?? []);
        $skill = DigitalAvatarImageSkillService::find($aid, intval($input['skill_id'] ?? 0), true);
        $session = self::loadSession($aid, $bid, $memberId, intval($input['session_id'] ?? 0));
        $waiting = self::decodeJson($session['waiting_json'] ?? '');
        $messages = self::decodeJson($session['messages_json'] ?? '');
        $uiLog = self::decodeJson($session['ui_json'] ?? '');
        if (!is_array($messages)) {
            $messages = [];
        }
        if (!is_array($uiLog)) {
            $uiLog = [];
        }

        $preferDraft = DigitalAvatarImageAgentService::preferDraft($aid);
        $imageStage = DigitalAvatarImageAgentService::normalizeStage(strval($input['image_stage'] ?? ($preferDraft ? 'draft' : 'final')));
        $ctx = [
            'aid' => $aid,
            'bid' => $bid,
            'member_id' => $memberId,
            'gallery' => $gallery,
            'current_image_url' => DigitalAvatarImageAgentService::firstUrl($input['current_image_url'] ?? ''),
            'size' => self::normalizeSize(strval($input['size'] ?? '')),
            'quality' => self::normalizeQuality(strval($input['quality'] ?? '')),
            'ratio' => trim(strval($input['ratio'] ?? '')),
            'has_doodle' => intval($input['has_doodle'] ?? 0) === 1,
            'skill' => $skill,
            'task_ids' => [],
            'question' => null,
            'events' => [],
            'image_stage' => $imageStage,
            'prefer_draft' => $preferDraft ? 1 : 0,
            'conversation_id' => intval($input['conversation_id'] ?? 0),
            'conversation_mode' => strtolower(trim(strval($input['conversation_mode'] ?? ''))),
        ];

        if (strval($session['status'] ?? '') === 'waiting_user' && $answer !== null && $waiting !== []) {
            $callId = strval($waiting['tool_call_id'] ?? 'ask_user');
            $toolContent = json_encode([
                'ok' => 1,
                'selected' => $answer['selected'],
                'text' => $answer['text'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $replaced = false;
            for ($i = count($messages) - 1; $i >= 0; $i--) {
                if (strval($messages[$i]['role'] ?? '') !== 'tool') {
                    continue;
                }
                if (strval($messages[$i]['tool_call_id'] ?? '') !== $callId) {
                    continue;
                }
                $messages[$i]['content'] = $toolContent;
                $replaced = true;
                break;
            }
            if (!$replaced) {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
                    'content' => $toolContent,
                ];
            }
            if ($userText === '') {
                $userText = $answer['text'] !== '' ? $answer['text'] : ('用户选择：' . implode('、', $answer['selected']));
            }
            $session['waiting_json'] = '';
            $session['status'] = 'active';
        }

        if ($userText === '') {
            throw new \RuntimeException('请先描述你想要的画面');
        }

        $messages[] = ['role' => 'user', 'content' => $userText];
        $ctx['focus_figure'] = self::figureFromMessages($messages, $ctx);
        $uiLog[] = [
            'id' => 'user-' . time() . '-' . substr(md5($userText), 0, 6),
            'role' => 'user',
            'text' => $userText,
            'refs' => array_map(static function ($item) {
                return [
                    'id' => strval($item['id'] ?? ''),
                    'name' => strval($item['label'] ?? ('图' . intval($item['no'] ?? 0))),
                    'url' => strval($item['url'] ?? ''),
                ];
            }, array_slice($gallery, 0, 8)),
        ];

        $llm = DigitalAvatarImageAgentService::resolveLlm($aid);
        if ($llm === []) {
            $assistant = self::heuristicTurn($userText, $ctx);
            $messages[] = ['role' => 'assistant', 'content' => strval($assistant['text'] ?? '')];
        } else {
            $assistant = self::loop($llm, $messages, $ctx);
        }

        $status = $ctx['question'] ? 'waiting_user' : 'active';
        $uiLog[] = [
            'id' => 'bot-' . time() . '-' . substr(md5(strval($assistant['text'] ?? '')), 0, 6),
            'role' => 'assistant',
            'text' => strval($assistant['text'] ?? ''),
            'task_ids' => $ctx['task_ids'],
            'tools' => $ctx['events'],
            'question' => $ctx['question'],
            'status' => $ctx['task_ids'] !== [] ? 'generating' : ($ctx['question'] ? 'waiting' : ''),
        ];

        $save = [
            'bid' => $bid,
            'skill_id' => intval($skill['id'] ?? 0),
            'status' => $status,
            'title' => trim(strval($session['title'] ?? '')) !== ''
                ? strval($session['title'])
                : mb_substr($userText, 0, 40),
            'waiting_json' => $ctx['question'] ? json_encode(array_merge($ctx['question'], [
                'tool_call_id' => strval($ctx['question']['tool_call_id'] ?? 'ask_user'),
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            'messages_json' => json_encode(array_slice($messages, -self::MSG_LIMIT), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ui_json' => json_encode(array_slice($uiLog, -self::UI_LIMIT), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_task_ids' => implode(',', $ctx['task_ids']),
            'update_time' => time(),
        ];
        Db::name('digitalavatar_agent_session')->where('id', intval($session['id']))->update($save);
        $conversationId = intval($ctx['conversation_id'] ?? 0);
        try {
            $conversationId = DigitalAvatarConversationService::syncSession(
                $aid,
                $bid,
                $memberId,
                array_merge($session, $save, ['id' => intval($session['id'])]),
                is_array($ctx['task_ids'] ?? null) ? $ctx['task_ids'] : [],
                intval($ctx['conversation_id'] ?? 0),
                strval($ctx['conversation_mode'] ?? '')
            );
        } catch (\Throwable $e) {
        }

        $reply = self::sanitizeQueuedReply(trim(strval($assistant['text'] ?? '')), count($ctx['task_ids']));
        if ($reply === '') {
            $reply = $ctx['question']['question'] ?? '再说细一点，我就能继续。';
        }

        return [
            'session_id' => intval($session['id']),
            'conversation_id' => intval($conversationId ?? 0),
            'status' => $status,
            'reply' => mb_substr($reply, 0, 400),
            'task_ids' => $ctx['task_ids'],
            'question' => $ctx['question'],
            'events' => $ctx['events'],
            'messages' => array_slice($uiLog, -self::UI_LIMIT),
            'llm_used' => $llm === [] ? 0 : 1,
            'llm_ready' => DigitalAvatarImageAgentService::isLlmReady($aid) ? 1 : 0,
            'skill_id' => intval($skill['id'] ?? 0),
            'skill_name' => strval($skill['name'] ?? ''),
            'action' => $ctx['task_ids'] !== [] ? 'generate' : 'reply',
            'prompt' => '',
            'use_reference' => $gallery !== [] ? 1 : 0,
        ];
    }

    public static function upscale(int $aid, int $bid, int $memberId, array $input): array
    {
        self::assertEnabled($aid);
        if ($memberId <= 0) {
            throw new \RuntimeException('请先登录会员账号');
        }
        $taskId = intval($input['task_id'] ?? ($input['id'] ?? 0));
        if ($taskId <= 0) {
            throw new \RuntimeException('请选择要转高清的图片');
        }
        $row = Db::name('digitalavatar_task')
            ->where('aid', $aid)
            ->where('member_id', $memberId)
            ->where('id', $taskId)
            ->where('deleted_time', 0)
            ->find();
        if (!$row) {
            throw new \RuntimeException('任务不存在或无权转高清');
        }
        if (intval($row['status'] ?? 0) !== 2) {
            throw new \RuntimeException('请等这张图完成后再转高清');
        }
        $url = self::rowImageUrl($row);
        if ($url === '') {
            throw new \RuntimeException('这张图还没有可转高清的文件');
        }
        $parentInput = self::decodeJson($row['input_json'] ?? '');
        if (DigitalAvatarImageAgentService::normalizeStage(strval($parentInput['image_stage'] ?? '')) === 'final') {
            throw new \RuntimeException('这张已经是高清成品');
        }

        $session = self::loadSession($aid, $bid, $memberId, intval($input['session_id'] ?? 0));
        $messages = self::decodeJson($session['messages_json'] ?? '');
        $uiLog = self::decodeJson($session['ui_json'] ?? '');
        if (!is_array($messages)) {
            $messages = [];
        }
        if (!is_array($uiLog)) {
            $uiLog = [];
        }
        $ctx = [
            'aid' => $aid,
            'bid' => $bid,
            'member_id' => $memberId,
            'gallery' => [['no' => 1, 'id' => 'task-' . $taskId, 'label' => '图1', 'url' => $url]],
            'current_image_url' => $url,
            'size' => '',
            'quality' => '',
            'ratio' => '',
            'has_doodle' => 0,
            'skill' => [],
            'task_ids' => [],
            'question' => null,
            'events' => [],
            'image_stage' => 'final',
            'prefer_draft' => 0,
        ];
        $queued = self::queueImages('upscale_image', [
            'prompt' => self::upscalePrompt(),
            'figure' => 1,
            'parent_task_id' => $taskId,
            'stage' => 'final',
            'reference_images' => [$url],
        ], $ctx);
        if (intval($queued['ok'] ?? 0) !== 1) {
            throw new \RuntimeException(strval($queued['error'] ?? '转高清提交失败'));
        }
        $reply = '已提交转高清，完成后会替换到对话里。';
        $messages[] = ['role' => 'user', 'content' => '把这张转高清'];
        $messages[] = ['role' => 'assistant', 'content' => $reply];
        $uiLog[] = [
            'id' => 'user-upscale-' . $taskId,
            'role' => 'user',
            'text' => '把这张转高清',
        ];
        $uiLog[] = [
            'id' => 'bot-upscale-' . time() . '-' . $taskId,
            'role' => 'assistant',
            'text' => $reply,
            'task_ids' => $ctx['task_ids'],
            'tools' => $ctx['events'],
            'status' => 'generating',
        ];
        Db::name('digitalavatar_agent_session')->where('id', intval($session['id']))->update([
            'status' => 'active',
            'waiting_json' => '',
            'messages_json' => json_encode(array_slice($messages, -self::MSG_LIMIT), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ui_json' => json_encode(array_slice($uiLog, -self::UI_LIMIT), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_task_ids' => implode(',', $ctx['task_ids']),
            'update_time' => time(),
        ]);
        return [
            'session_id' => intval($session['id']),
            'status' => 'active',
            'reply' => $reply,
            'task_ids' => $ctx['task_ids'],
            'question' => null,
            'events' => $ctx['events'],
            'messages' => array_slice($uiLog, -self::UI_LIMIT),
            'action' => 'generate',
            'parent_task_id' => $taskId,
        ];
    }

    private static function loop(array $llm, array &$messages, array &$ctx): array
    {
        $finalText = '';
        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            $packed = self::packMessages($messages, $ctx);
            try {
                $assistant = DigitalAvatarImageAgentService::complete($llm, $packed, self::toolSchemas());
            } catch (\Throwable $e) {
                Log::warning('digitalavatar agent llm: ' . $e->getMessage());
                $ctx['events'][] = [
                    'name' => 'llm',
                    'title' => '理解失败，已按规则继续',
                    'status' => 'failed',
                    'detail' => mb_substr($e->getMessage(), 0, 160),
                ];
                return self::heuristicTurn(self::lastUserText($messages), $ctx);
            }

            $toolCalls = is_array($assistant['tool_calls'] ?? null) ? $assistant['tool_calls'] : [];
            $text = trim(strval($assistant['content'] ?? ''));
            if ($toolCalls === []) {
                $parsed = self::parseContentTools($text);
                if ($parsed['tool_calls'] !== []) {
                    $toolCalls = $parsed['tool_calls'];
                    $text = $parsed['text'];
                }
            }

            if ($toolCalls === []) {
                $finalText = $text;
                $messages[] = ['role' => 'assistant', 'content' => $finalText];
                break;
            }

            $storeCalls = [];
            foreach ($toolCalls as $call) {
                $storeCalls[] = [
                    'id' => strval($call['id'] ?? ''),
                    'type' => 'function',
                    'function' => [
                        'name' => strval($call['name'] ?? ($call['function']['name'] ?? '')),
                        'arguments' => is_string($call['arguments'] ?? null)
                            ? $call['arguments']
                            : json_encode($call['arguments'] ?? ($call['function']['arguments'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ];
            }
            $messages[] = [
                'role' => 'assistant',
                'content' => $text !== '' ? $text : null,
                'tool_calls' => $storeCalls,
            ];
            if ($text !== '') {
                $finalText = $text;
            }

            $stop = false;
            foreach ($toolCalls as $call) {
                $name = strval($call['name'] ?? ($call['function']['name'] ?? ''));
                $callId = strval($call['id'] ?? ('call_' . substr(md5($name . microtime(true)), 0, 10)));
                $arguments = $call['arguments'] ?? ($call['function']['arguments'] ?? []);
                if (is_string($arguments)) {
                    $decoded = json_decode($arguments, true);
                    $arguments = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($arguments)) {
                    $arguments = [];
                }
                $result = self::executeTool($name, $arguments, $ctx, $callId);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $callId,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
                if ($name === 'ask_user') {
                    $stop = true;
                }
            }
            if ($stop) {
                break;
            }
        }

        if ($finalText === '' && $ctx['question']) {
            $finalText = strval($ctx['question']['question'] ?? '');
        }
        if ($finalText === '' && $ctx['task_ids'] !== []) {
            $finalText = '已经提交出图，完成后会出现在图库。';
        }
        if ($finalText === '') {
            $finalText = '我先按这个方向处理。';
        }
        return ['text' => self::sanitizeQueuedReply($finalText, count($ctx['task_ids']))];
    }

    private static function heuristicTurn(string $text, array &$ctx): array
    {
        $compact = preg_replace('/\s+/u', '', $text);
        $isGreeting = (bool) preg_match('/^(你好|在吗|嗨|hi|hello|谢谢|感谢)[!！。.?？]*$/iu', strval($compact));
        if ($isGreeting && $ctx['gallery'] === []) {
            $ctx['events'][] = ['name' => 'ask_user', 'title' => '需要补充信息', 'status' => 'done'];
            $ctx['question'] = [
                'tool_call_id' => 'ask_user_hello',
                'question' => '可以。你更想做哪一类？',
                'options' => ['商品主图', '人物写真', '场景海报', '把现有图拆成多张'],
                'allow_multiple' => 0,
            ];
            return ['text' => $ctx['question']['question']];
        }

        $wantsUpscale = (bool) preg_match('/转高清|升清|超分|放大成高清|upscale/u', $text);
        if ($wantsUpscale) {
            $queued = self::executeTool('upscale_image', [
                'prompt' => self::upscalePrompt(),
                'figure' => self::defaultFigure($ctx),
                'parent_task_id' => 0,
                'stage' => 'final',
            ], $ctx, 'auto_upscale_image');
            $reply = intval($queued['ok'] ?? 0) === 1
                ? '已提交转高清，完成后会出现在对话里。'
                : strval($queued['error'] ?? '转高清失败');
            return ['text' => $reply];
        }
        $count = 1;
        if (preg_match('/(\d+)\s*张/u', $text, $match)) {
            $count = max(1, min(self::MAX_COUNT, intval($match[1])));
        }
        $wantsSplit = (bool) preg_match('/拆|分成|拆分|分别.*(张|图)/u', $text);
        if ($wantsSplit && $count < 2) {
            $count = 4;
        }
        $background = preg_match('/透明/u', $text) ? 'transparent' : '';
        $size = $ctx['size'];
        if (preg_match('/(\d{3,4})\s*[xX×*]\s*(\d{3,4})/u', $text, $match)) {
            $size = intval($match[1]) . 'x' . intval($match[2]);
        }
        $wantsEdit = (bool) preg_match('/改成|换成|改一下|局部|涂鸦|圈选/u', $text);
        $wantsFinal = (bool) preg_match('/直接高清|直接出成品|不要草稿/u', $text);
        $tool = ($wantsEdit && !$wantsSplit) ? 'edit_image' : 'generate_image';
        $prompt = $wantsSplit
            ? ('把参考图拆成 ' . $count . ' 张互不重叠的独立成品图。每张只保留其中一个完整主体或视角，正方形构图，周围留白，不要拼图、不要网格、不要多件衣服排在同一张里。' . ($background === 'transparent' ? '透明背景。' : '') . '用户原话：' . $text)
            : $text;
        $args = [
            'prompt' => $prompt,
            'count' => $tool === 'edit_image' ? 1 : $count,
            'size' => $size,
            'quality' => $ctx['quality'],
            'background' => $background,
            'figure' => $wantsSplit || $wantsEdit || $ctx['gallery'] !== [] ? self::defaultFigure($ctx) : 0,
            'stage' => $wantsFinal ? 'final' : strval($ctx['image_stage'] ?? 'draft'),
        ];
        self::executeTool($tool, $args, $ctx, 'auto_' . $tool);
        $reply = $wantsSplit
            ? ('按你的要求拆成 ' . $count . ' 张' . ($background === 'transparent' ? '透明底' : '') . ($size !== '' ? $size : '') . '，生成后会出现在图库。')
            : ($tool === 'edit_image' ? '按你圈的位置改，生成后可以直接说还要调哪里。' : '我来按这个方向出图。');
        return ['text' => $reply];
    }

    private static function executeTool(string $name, array $args, array &$ctx, string $callId): array
    {
        $name = trim($name);
        if ($name === 'inspect_gallery') {
            $list = self::galleryBrief($ctx);
            $ctx['events'][] = ['name' => $name, 'title' => '查看图库', 'status' => 'done', 'detail' => count($list) . ' 张'];
            return ['ok' => 1, 'gallery' => $list];
        }
        if ($name === 'ask_user') {
            $question = trim(strval($args['question'] ?? $args['text'] ?? ''));
            $options = [];
            foreach ((array) ($args['options'] ?? []) as $item) {
                $label = trim(strval($item));
                if ($label !== '' && !in_array($label, $options, true)) {
                    $options[] = mb_substr($label, 0, 40);
                }
                if (count($options) >= 6) {
                    break;
                }
            }
            if ($question === '') {
                $question = '还差一个关键信息，请选一个方向。';
            }
            $ctx['question'] = [
                'tool_call_id' => $callId,
                'question' => mb_substr($question, 0, 200),
                'options' => $options,
                'allow_multiple' => !empty($args['allow_multiple']) ? 1 : 0,
            ];
            $ctx['events'][] = ['name' => $name, 'title' => '向你确认', 'status' => 'done', 'detail' => $question];
            return ['ok' => 1, 'waiting' => 1];
        }
        if ($name === 'edit_image' || $name === 'generate_image' || $name === 'upscale_image') {
            return self::queueImages($name, $args, $ctx);
        }
        $ctx['events'][] = ['name' => $name, 'title' => '未知工具', 'status' => 'failed'];
        return ['ok' => 0, 'error' => '不支持的工具：' . $name];
    }

    private static function queueImages(string $tool, array $args, array &$ctx): array
    {
        $isUpscale = $tool === 'upscale_image';
        $prompt = trim(strval($args['prompt'] ?? $args['text'] ?? ''));
        if ($prompt === '') {
            $prompt = $isUpscale ? self::upscalePrompt() : '';
        }
        if ($prompt === '') {
            return ['ok' => 0, 'error' => '缺少出图描述'];
        }
        $count = ($tool === 'edit_image' || $isUpscale) ? 1 : max(1, min(self::MAX_COUNT, intval($args['count'] ?? ($args['n'] ?? 1))));
        $stage = $isUpscale
            ? 'final'
            : DigitalAvatarImageAgentService::normalizeStage(strval($args['stage'] ?? ($ctx['image_stage'] ?? 'draft')));
        $profile = DigitalAvatarImageAgentService::imageStageProfile(intval($ctx['aid']), $stage);
        if (intval($profile['ready'] ?? 0) !== 1) {
            $error = $stage === 'final' ? '请先在系统设置绑定高清模型' : '请先在系统设置绑定草稿模型';
            $ctx['events'][] = ['name' => $tool, 'title' => $isUpscale ? '转高清' : '出图', 'status' => 'failed', 'detail' => $error];
            return ['ok' => 0, 'error' => $error];
        }
        $size = self::normalizeSize(strval($args['size'] ?? ''));
        if ($size === '') {
            $size = self::normalizeSize(strval($profile['size'] ?? ($ctx['size'] ?? '')));
        }
        $quality = self::normalizeQuality(strval($args['quality'] ?? ''));
        $profileQuality = self::normalizeQuality(strval($profile['quality'] ?? ''));
        if ($stage === 'draft') {
            $quality = $profileQuality !== '' ? $profileQuality : 'low';
        } elseif ($quality === '') {
            $quality = $profileQuality !== '' ? $profileQuality : 'high';
        }
        $background = self::normalizeBackground(strval($args['background'] ?? ''));
        $figure = intval($args['figure'] ?? ($args['image'] ?? 0));
        if ($figure <= 0 && preg_match('/图\s*(\d+)/u', strval($args['image'] ?? $args['ref'] ?? ''), $match)) {
            $figure = intval($match[1]);
        }
        $needRef = $tool === 'edit_image' || $isUpscale || $ctx['has_doodle'];
        $refs = self::resolveRefs($ctx, $args, $figure, $needRef);
        if ($tool === 'edit_image' && $refs === []) {
            return ['ok' => 0, 'error' => '改图需要指定图库中的底图'];
        }
        if ($isUpscale && $refs === []) {
            return ['ok' => 0, 'error' => '转高清需要一张已完成的底图'];
        }
        if (stripos($prompt, '透明') !== false || $background === 'transparent') {
            $background = 'transparent';
        }

        $ids = [];
        $errors = [];
        $parentTaskId = intval($args['parent_task_id'] ?? 0);
        if ($parentTaskId <= 0 && $figure > 0) {
            $parentTaskId = self::figureTaskId($ctx, $figure);
        }
        for ($index = 1; $index <= $count; $index++) {
            $piecePrompt = $prompt;
            if ($count > 1) {
                $piecePrompt .= "\n这是第{$index}/{$count}张，只输出这一张独立成品，不要把多张拼在同一画面里。";
            }
            if ($background === 'transparent' && stripos($piecePrompt, '透明') === false) {
                $piecePrompt .= ' 透明背景，不要白底、不要灰底、不要阴影铺满画布。';
            }
            if ($size !== '' && stripos($piecePrompt, $size) === false) {
                $piecePrompt .= ' 输出尺寸 ' . $size . '。';
            }
            try {
                $created = DigitalAvatarTaskService::createTask(intval($ctx['aid']), intval($ctx['bid']), intval($ctx['member_id']), [
                    'task_type' => 'image',
                    'source' => 'image_agent',
                    'agent_tool' => $tool,
                    'agent_prompt_locked' => 1,
                    'title' => mb_substr(($isUpscale ? '转高清 ' : ($stage === 'draft' ? '草稿 ' : '')) . $prompt, 0, 40),
                    'prompt' => $piecePrompt,
                    'user_prompt' => $prompt,
                    'skill_id' => intval($ctx['skill']['id'] ?? 0),
                    'image_model' => strval($profile['model_code']),
                    'model_code' => strval($profile['model_code']),
                    'image_stage' => $stage,
                    'parent_task_id' => $parentTaskId,
                    'reference_images' => $refs,
                    'images' => $refs,
                    'gen_count' => 1,
                    'count' => 1,
                    'size' => $size,
                    'quality' => $quality,
                    'ratio' => strval($ctx['ratio'] ?? ''),
                    'background' => $background,
                    'conversation_id' => intval($ctx['conversation_id'] ?? 0),
                    'conversation_mode' => strval($ctx['conversation_mode'] ?? ''),
                    'request_id' => bin2hex(random_bytes(8)) . $index,
                ]);
                $taskId = intval($created['id'] ?? 0);
                $cid = intval($created['conversation_id'] ?? 0);
                if ($cid > 0) {
                    $ctx['conversation_id'] = $cid;
                }
                if ($taskId > 0) {
                    $ids[] = $taskId;
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                break;
            }
        }

        foreach ($ids as $taskId) {
            if (!in_array($taskId, $ctx['task_ids'], true)) {
                $ctx['task_ids'][] = $taskId;
            }
        }
        $ok = $ids !== [];
        $title = $isUpscale ? '转高清' : ($tool === 'edit_image' ? '改图' : (($stage === 'draft' ? '草稿 ×' : '出图 ×') . max(1, count($ids))));
        $ctx['events'][] = [
            'name' => $tool,
            'title' => $title,
            'status' => $ok ? 'done' : 'failed',
            'detail' => $ok
                ? (implode('、', array_map(static function ($id) {
                    return '#' . $id;
                }, $ids)) . ($stage === 'draft' ? ' · 草稿' : ($isUpscale ? ' · 高清' : '')) . ($background === 'transparent' ? ' · 透明底' : '') . ($size !== '' ? ' · ' . $size : ''))
                : implode('；', $errors),
        ];
        return [
            'ok' => $ok ? 1 : 0,
            'task_ids' => $ids,
            'count' => count($ids),
            'size' => $size,
            'background' => $background,
            'error' => $errors !== [] ? implode('；', $errors) : '',
        ];
    }

    private static function resolveRefs(array $ctx, array $args, int $figure, bool $preferCurrent): array
    {
        $urls = [];
        foreach ((array) ($args['reference_images'] ?? ($args['images'] ?? [])) as $item) {
            $url = DigitalAvatarImageAgentService::firstUrl($item);
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }
        if ($figure > 0) {
            foreach ($ctx['gallery'] as $item) {
                if (intval($item['no'] ?? 0) === $figure) {
                    $url = DigitalAvatarImageAgentService::firstUrl($item['url'] ?? '');
                    if ($url !== '') {
                        array_unshift($urls, $url);
                    }
                    break;
                }
            }
        }
        if ($preferCurrent || $urls === []) {
            $current = DigitalAvatarImageAgentService::firstUrl($ctx['current_image_url'] ?? '');
            if ($current !== '') {
                array_unshift($urls, $current);
            } elseif ($ctx['gallery'] !== []) {
                $url = DigitalAvatarImageAgentService::firstUrl($ctx['gallery'][0]['url'] ?? '');
                if ($url !== '') {
                    array_unshift($urls, $url);
                }
            }
        }
        $unique = [];
        foreach ($urls as $url) {
            if ($url !== '' && !in_array($url, $unique, true)) {
                $unique[] = $url;
            }
        }
        return array_slice($unique, 0, 4);
    }

    private static function figureFromText(string $text): int
    {
        if (preg_match('/图\s*(\d+)/u', $text, $match)) {
            return intval($match[1]);
        }
        return 0;
    }

    private static function figureFromMessages(array $messages, array $ctx): int
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (strval($messages[$i]['role'] ?? '') !== 'user') {
                continue;
            }
            $figure = self::figureFromText(strval($messages[$i]['content'] ?? ''));
            if ($figure > 0) {
                return $figure;
            }
        }
        return self::defaultFigure($ctx);
    }

    private static function defaultFigure(array $ctx): int
    {
        $focus = intval($ctx['focus_figure'] ?? 0);
        if ($focus > 0) {
            foreach ($ctx['gallery'] as $item) {
                if (intval($item['no'] ?? 0) === $focus) {
                    return $focus;
                }
            }
        }
        if ($ctx['gallery'] === []) {
            return 0;
        }
        $current = DigitalAvatarImageAgentService::firstUrl($ctx['current_image_url'] ?? '');
        foreach ($ctx['gallery'] as $item) {
            if ($current !== '' && DigitalAvatarImageAgentService::firstUrl($item['url'] ?? '') === $current) {
                return intval($item['no'] ?? 0);
            }
        }
        return intval($ctx['gallery'][0]['no'] ?? 0);
    }

    private static function sanitizeQueuedReply(string $text, int $taskCount): string
    {
        $trimmed = trim($text);
        if ($taskCount <= 0) {
            return $trimmed;
        }
        if ($trimmed === '' || preg_match('/画好了|已经画好|生成完成|已经完成/u', $trimmed)) {
            return $taskCount > 1
                ? ('已提交 ' . $taskCount . ' 张到队列，正在一张张生成。全部完成后会出现在对话和图库。')
                : '已提交出图任务，正在生成。';
        }
        return $trimmed;
    }

    private static function repairToolThread(array $messages): array
    {
        $out = [];
        $pending = [];
        foreach ($messages as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = strval($item['role'] ?? '');
            if ($role === 'assistant' && !empty($item['tool_calls']) && is_array($item['tool_calls'])) {
                $pending = [];
                foreach ($item['tool_calls'] as $call) {
                    $id = strval($call['id'] ?? '');
                    if ($id !== '') {
                        $pending[$id] = true;
                    }
                }
                $out[] = $item;
                continue;
            }
            if ($role === 'tool') {
                $id = strval($item['tool_call_id'] ?? '');
                if ($id === '' || empty($pending[$id])) {
                    continue;
                }
                unset($pending[$id]);
                $out[] = $item;
                continue;
            }
            $pending = [];
            $out[] = $item;
        }
        return $out;
    }

    private static function packMessages(array $messages, array $ctx): array
    {
        $system = [
            '你是商业级「Agent 作图」智能体。你只负责理解意图并调用工具，不要假装已经画好。',
            '可用工具：inspect_gallery、generate_image、edit_image、upscale_image、ask_user。',
            '规则：',
            '1. 先看用户附带的图片再决策。用户说图1/图10 时，用对应编号。',
            '2. 明确要出图、改图、拆成多张时调用 generate_image 或 edit_image，不要只口头说「画好了」。默认 stage=draft 出草稿。用户说「直接高清」才 stage=final。',
            '3. 「拆成 N 张 / 分别出 N 张」必须 generate_image，count=N。每张应是独立成品，不要在一张图里拼宫格。用户要透明底就 background=transparent；要 1000x1000 就 size=1000x1000。',
            '4. 涂鸦、圈选、改衣服颜色、局部替换用 edit_image，count=1，并指定 figure。改完仍是草稿。',
            '5. 用户说转高清、升清、超分时只调用 upscale_image，指定 figure。不要重新 generate。没绑定高清模型时如实告诉用户。出完草稿后禁止自动转高清，必须等用户确认。一批多张也不要自动全部升清。',
            '6. 缺关键信息（拆图是裁切还是多视角、主体不明确）时用 ask_user，只问一个问题，给 2-4 个选项。闲聊、谢谢也用短回复或 ask_user，不要出图。',
            '7. 可以连续调用多个工具。出图工具只负责提交任务，返回 task_ids 即成功，不要等待像素级完成。提交后禁止说「画好了」，只能说已提交、正在生成。',
            '8. 给用户的话要短、口语、像设计师助手。不要输出 JSON 给用户看。',
        ];
        $skillName = trim(strval($ctx['skill']['name'] ?? ''));
        $skillContent = trim(strval($ctx['skill']['content'] ?? ''));
        if ($skillName !== '' || $skillContent !== '') {
            $system[] = '当前技能：' . ($skillName !== '' ? $skillName : '自定义');
            if ($skillContent !== '') {
                $system[] = '技能要求（在不违反工具调用的前提下优先遵守）：' . $skillContent;
            }
        }
        $system[] = '当前图库：' . self::galleryText($ctx);
        $system[] = '当前默认出图档位：' . (strval($ctx['image_stage'] ?? 'draft') === 'final' ? '高清成品' : '草稿确认') . '。草稿用低成本模型确认构图，转高清走后台单独绑定的高清提供商。';
        $system[] = '界面默认尺寸 ' . ($ctx['size'] ?: '1024x1024') . '，清晰度 ' . ($ctx['quality'] ?: '按档位') . '。用户另有指定时以用户为准。';
        if ($ctx['has_doodle']) {
            $system[] = '用户在当前画布上画了圈选笔迹，改图时只改圈中区域，不要把笔迹画进成品。';
        }

        $packed = [['role' => 'system', 'content' => implode("\n", $system)]];
        foreach (self::repairToolThread(array_slice($messages, -self::MSG_LIMIT)) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = strval($item['role'] ?? '');
            if ($role === 'tool') {
                $packed[] = [
                    'role' => 'tool',
                    'tool_call_id' => strval($item['tool_call_id'] ?? ''),
                    'content' => strval($item['content'] ?? ''),
                ];
                continue;
            }
            if ($role === 'assistant' && !empty($item['tool_calls'])) {
                $packed[] = [
                    'role' => 'assistant',
                    'content' => $item['content'] ?? '',
                    'tool_calls' => $item['tool_calls'],
                ];
                continue;
            }
            if ($role === 'user' || $role === 'assistant') {
                $packed[] = [
                    'role' => $role,
                    'content' => is_string($item['content'] ?? null) ? $item['content'] : json_encode($item['content'] ?? '', JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        $vision = self::visionUser($ctx);
        if ($vision !== null) {
            array_splice($packed, 1, 0, [$vision]);
        }
        return $packed;
    }

    private static function visionUser(array $ctx): ?array
    {
        $urls = [];
        $focus = intval($ctx['focus_figure'] ?? 0);
        if ($focus > 0) {
            foreach ($ctx['gallery'] as $item) {
                if (intval($item['no'] ?? 0) !== $focus) {
                    continue;
                }
                $url = DigitalAvatarImageAgentService::firstUrl($item['url'] ?? '');
                if ($url !== '') {
                    $urls[] = $url;
                }
                break;
            }
        }
        $current = DigitalAvatarImageAgentService::firstUrl($ctx['current_image_url'] ?? '');
        if ($current !== '' && !in_array($current, $urls, true)) {
            array_unshift($urls, $current);
        }
        foreach ($ctx['gallery'] as $item) {
            $url = DigitalAvatarImageAgentService::firstUrl($item['url'] ?? '');
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
            if (count($urls) >= 4) {
                break;
            }
        }
        $urls = array_slice($urls, 0, 4);
        if ($urls === []) {
            return null;
        }
        $content = [[
            'type' => 'text',
            'text' => '请先查看这些图，再决定调用哪个工具。' . self::galleryText($ctx),
        ]];
        $detail = count($urls) <= 2 ? 'high' : 'low';
        foreach ($urls as $url) {
            $embedded = DigitalAvatarImageAgentService::embedImageForVision($url);
            if ($embedded === '') {
                continue;
            }
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $embedded, 'detail' => $detail],
            ];
        }
        if (count($content) === 1) {
            return null;
        }
        return ['role' => 'user', 'content' => $content];
    }

    private static function toolSchemas(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'inspect_gallery',
                    'description' => '查看当前图库有哪些图、编号和地址。出图前如不确定图10是哪张，先调用。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_image',
                    'description' => '提交图片生成任务。用于新图、拆成多张独立图、多视角、透明底商品图。默认 stage=draft 出草稿。用户明确要求直接高清时才 stage=final。count 为要生成的张数。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'prompt' => ['type' => 'string', 'description' => '给图片模型的完整画面指令，不要写系统说明'],
                            'count' => ['type' => 'integer', 'description' => '生成张数，1-8。球衣套装拆图必须每次 count=1，连续提交 6 次。'],
                            'size' => ['type' => 'string', 'description' => '如 2000x2000 或 1024x1024。球衣套装拆图用 2000x2000'],
                            'quality' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'background' => ['type' => 'string', 'enum' => ['transparent', 'opaque', '']],
                            'figure' => ['type' => 'integer', 'description' => '参考图编号，如图10就填10'],
                            'stage' => ['type' => 'string', 'enum' => ['draft', 'final'], 'description' => 'draft=草稿确认，final=直接高清。默认 draft'],
                            'reference_images' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['prompt'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'edit_image',
                    'description' => '基于已有底图做局部修改，例如改颜色、圈选区域、换局部元素。一次只改一张。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'prompt' => ['type' => 'string'],
                            'figure' => ['type' => 'integer'],
                            'size' => ['type' => 'string'],
                            'quality' => ['type' => 'string'],
                            'background' => ['type' => 'string'],
                        ],
                        'required' => ['prompt'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'upscale_image',
                    'description' => '把已确认的草稿转到高清。必须指定 figure。走后台绑定的高清提供商，不要重新出图。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'figure' => ['type' => 'integer', 'description' => '要转高清的图号'],
                            'prompt' => ['type' => 'string', 'description' => '可选，默认保持构图只提高清晰度'],
                        ],
                        'required' => ['figure'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'ask_user',
                    'description' => '关键信息不足时向用户提问并给出可点选项。提问后停止本轮出图。',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'allow_multiple' => ['type' => 'boolean'],
                        ],
                        'required' => ['question'],
                    ],
                ],
            ],
        ];
    }

    private static function parseContentTools(string $text): array
    {
        $toolCalls = [];
        $remain = trim($text);
        if ($remain === '') {
            return ['tool_calls' => [], 'text' => ''];
        }
        $json = null;
        if (preg_match('/\{[\s\S]*\}/', $remain, $match)) {
            $json = json_decode($match[0], true);
        }
        if (!is_array($json)) {
            return ['tool_calls' => [], 'text' => $remain];
        }
        if (!empty($json['tool_calls']) && is_array($json['tool_calls'])) {
            foreach ($json['tool_calls'] as $index => $call) {
                $toolCalls[] = self::normalizeCall($call, $index);
            }
            return ['tool_calls' => array_values(array_filter($toolCalls)), 'text' => strval($json['text'] ?? ($json['reply'] ?? ''))];
        }
        $name = strval($json['tool'] ?? ($json['name'] ?? ($json['action'] ?? '')));
        if ($name === 'generate' || $name === 'generate_image') {
            $name = 'generate_image';
        } elseif ($name === 'edit' || $name === 'edit_image') {
            $name = 'edit_image';
        } elseif ($name === 'upscale' || $name === 'upscale_image') {
            $name = 'upscale_image';
        } elseif ($name === 'reply' || $name === 'final' || $name === 'message') {
            return ['tool_calls' => [], 'text' => strval($json['arguments']['text'] ?? ($json['reply'] ?? $json['text'] ?? ''))];
        } elseif ($name === 'ask' || $name === 'ask_user') {
            $name = 'ask_user';
        }
        if (in_array($name, ['generate_image', 'edit_image', 'upscale_image', 'ask_user', 'inspect_gallery'], true)) {
            $args = $json['arguments'] ?? $json;
            unset($args['tool'], $args['name'], $args['action'], $args['arguments']);
            if ($name === 'generate_image' && empty($args['prompt'])) {
                $args['prompt'] = strval($json['prompt'] ?? '');
            }
            $toolCalls[] = [
                'id' => 'parsed_' . $name,
                'name' => $name,
                'arguments' => $args,
            ];
            return ['tool_calls' => $toolCalls, 'text' => strval($json['reply'] ?? ($json['text'] ?? ''))];
        }
        return ['tool_calls' => [], 'text' => $remain];
    }

    private static function normalizeCall($call, int $index): ?array
    {
        if (!is_array($call)) {
            return null;
        }
        $name = strval($call['name'] ?? ($call['function']['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $args = $call['arguments'] ?? ($call['function']['arguments'] ?? []);
        if (is_string($args)) {
            $decoded = json_decode($args, true);
            $args = is_array($decoded) ? $decoded : [];
        }
        return [
            'id' => strval($call['id'] ?? ('call_' . $index . '_' . $name)),
            'name' => $name,
            'arguments' => is_array($args) ? $args : [],
        ];
    }

    private static function galleryBrief(array $ctx): array
    {
        $list = [];
        foreach ($ctx['gallery'] as $item) {
            $list[] = [
                'no' => intval($item['no'] ?? 0),
                'id' => strval($item['id'] ?? ''),
                'label' => strval($item['label'] ?? ''),
                'url' => strval($item['url'] ?? ''),
            ];
        }
        return $list;
    }

    private static function galleryText(array $ctx): string
    {
        if ($ctx['gallery'] === []) {
            return '空';
        }
        $parts = [];
        foreach ($ctx['gallery'] as $item) {
            $parts[] = '图' . intval($item['no'] ?? 0) . ' ' . strval($item['label'] ?? '') . ' ' . strval($item['url'] ?? '');
        }
        $current = DigitalAvatarImageAgentService::firstUrl($ctx['current_image_url'] ?? '');
        if ($current !== '') {
            $parts[] = '当前画布：' . $current;
        }
        return implode('；', $parts);
    }

    private static function lastUserText(array $messages): string
    {
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if (strval($messages[$index]['role'] ?? '') === 'user') {
                $content = $messages[$index]['content'] ?? '';
                return is_string($content) ? $content : '';
            }
        }
        return '';
    }

    private static function assertEnabled(int $aid): void
    {
        $settings = DigitalAvatarCreditService::ensureSettings($aid);
        if (intval($settings['enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('AI数字人功能暂未开放');
        }
        if (intval($settings['image_enabled'] ?? 1) !== 1) {
            throw new \RuntimeException('图片创作功能暂未开放');
        }
        if (intval($settings['image_agent_enabled'] ?? 0) !== 1) {
            throw new \RuntimeException('Agent 作图暂未开放');
        }
    }

    private static function loadSession(int $aid, int $bid, int $memberId, int $sessionId): array
    {
        $row = null;
        if ($sessionId > 0) {
            $row = Db::name('digitalavatar_agent_session')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->where('id', $sessionId)
                ->find();
        }
        if ($row) {
            if (intval($row['bid'] ?? 0) !== $bid) {
                Db::name('digitalavatar_agent_session')->where('id', intval($row['id']))->update([
                    'bid' => $bid,
                    'update_time' => time(),
                ]);
                $row['bid'] = $bid;
            }
            return $row;
        }
        $now = time();
        $id = Db::name('digitalavatar_agent_session')->insertGetId([
            'aid' => $aid,
            'bid' => $bid,
            'member_id' => $memberId,
            'status' => 'active',
            'title' => '',
            'waiting_json' => '',
            'messages_json' => '[]',
            'ui_json' => '[]',
            'last_task_ids' => '',
            'create_time' => $now,
            'update_time' => $now,
        ]);
        $row = Db::name('digitalavatar_agent_session')->where('id', intval($id))->find();
        return is_array($row) ? $row : ['id' => intval($id), 'status' => 'active'];
    }

    private static function findSession(int $aid, int $memberId): ?array
    {
        try {
            $row = Db::name('digitalavatar_agent_session')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->order('id desc')
                ->find();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            DigitalAvatarInstaller::ensureInstalled(true);
            $row = Db::name('digitalavatar_agent_session')
                ->where('aid', $aid)
                ->where('member_id', $memberId)
                ->order('id desc')
                ->find();
            return is_array($row) ? $row : null;
        }
    }

    public static function formatSession(array $row): array
    {
        $waiting = self::decodeJson($row['waiting_json'] ?? '');
        $taskIds = [];
        foreach (explode(',', strval($row['last_task_ids'] ?? '')) as $id) {
            $id = intval($id);
            if ($id > 0) {
                $taskIds[] = $id;
            }
        }
        return [
            'session_id' => intval($row['id'] ?? 0),
            'status' => strval($row['status'] ?? 'active'),
            'messages' => self::decodeJson($row['ui_json'] ?? ''),
            'question' => $waiting !== [] ? $waiting : null,
            'task_ids' => $taskIds,
            'title' => strval($row['title'] ?? ''),
            'update_time' => intval($row['update_time'] ?? 0),
        ];
    }

    private static function sessionTaskIds(array $row): array
    {
        $ids = [];
        foreach (explode(',', strval($row['last_task_ids'] ?? '')) as $id) {
            $id = intval($id);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ui = self::decodeJson($row['ui_json'] ?? '');
        if (is_array($ui)) {
            foreach ($ui as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $list = $item['task_ids'] ?? [];
                if (!is_array($list) && intval($item['task_id'] ?? ($item['taskId'] ?? 0)) > 0) {
                    $list = [intval($item['task_id'] ?? ($item['taskId'] ?? 0))];
                }
                if (!is_array($list)) {
                    continue;
                }
                foreach ($list as $id) {
                    $id = intval($id);
                    if ($id > 0) {
                        $ids[$id] = $id;
                    }
                }
            }
        }
        return array_values($ids);
    }

    private static function normalizeGallery($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        $index = 1;
        foreach ($value as $row) {
            if (is_string($row)) {
                $url = DigitalAvatarImageAgentService::firstUrl($row);
                if ($url === '') {
                    continue;
                }
                $items[] = ['no' => $index, 'id' => 'g-' . $index, 'label' => '图' . $index, 'url' => $url];
                $index++;
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $url = DigitalAvatarImageAgentService::firstUrl($row['url'] ?? ($row['originUrl'] ?? ''));
            if ($url === '') {
                continue;
            }
            $no = intval($row['no'] ?? $index);
            $items[] = [
                'no' => $no > 0 ? $no : $index,
                'id' => strval($row['id'] ?? ('g-' . $no)),
                'label' => strval($row['label'] ?? ($row['name'] ?? ('图' . $no))),
                'url' => $url,
            ];
            $index++;
            if (count($items) >= 12) {
                break;
            }
        }
        return $items;
    }

    private static function normalizeAnswer($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $text = trim($value);
            return $text === '' ? null : ['selected' => [$text], 'text' => $text];
        }
        if (!is_array($value)) {
            return null;
        }
        $selected = [];
        foreach ((array) ($value['selected'] ?? ($value['options'] ?? [])) as $item) {
            $label = trim(strval($item));
            if ($label !== '') {
                $selected[] = $label;
            }
        }
        $text = trim(strval($value['text'] ?? ''));
        if ($text === '' && $selected !== []) {
            $text = implode('、', $selected);
        }
        if ($text === '' && $selected === []) {
            return null;
        }
        return ['selected' => $selected, 'text' => $text];
    }

    private static function normalizeSize(string $size): string
    {
        $size = strtolower(trim(str_replace(['×', '*', 'X'], 'x', $size)));
        if (preg_match('/^\d{3,5}x\d{3,5}$/', $size)) {
            return $size;
        }
        $map = [
            '1:1' => '1024x1024',
            '16:9' => '1792x1024',
            '9:16' => '1024x1792',
        ];
        return $map[$size] ?? $size;
    }

    private static function normalizeQuality(string $quality): string
    {
        $quality = strtolower(trim($quality));
        return in_array($quality, ['low', 'medium', 'high'], true) ? $quality : '';
    }

    private static function upscalePrompt(): string
    {
        return '保持构图、主体、比例、背景和文字完全不变，只提高清晰度、细节和分辨率，做成高清成品。不要重新设计，不要改衣服，不要增删物体。';
    }

    private static function rowImageUrl(array $row): string
    {
        $result = self::decodeJson($row['result_json'] ?? '');
        $resultData = is_array($result['data'] ?? null) ? $result['data'] : [];
        $candidates = [
            $result['image_url'] ?? '',
            $result['url'] ?? '',
            $result['cover_url'] ?? '',
            $resultData['image_url'] ?? '',
            $resultData['url'] ?? '',
            $row['cover_url'] ?? '',
        ];
        if (is_array($result['assets'] ?? null)) {
            foreach ($result['assets'] as $asset) {
                $candidates[] = is_array($asset) ? ($asset['url'] ?? '') : $asset;
            }
        }
        foreach ($candidates as $item) {
            $url = DigitalAvatarImageAgentService::firstUrl($item);
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }

    private static function figureTaskId(array $ctx, int $figure): int
    {
        if ($figure <= 0) {
            return 0;
        }
        foreach ($ctx['gallery'] as $item) {
            if (intval($item['no'] ?? 0) !== $figure) {
                continue;
            }
            $taskId = intval($item['task_id'] ?? 0);
            if ($taskId > 0) {
                return $taskId;
            }
            $id = trim(strval($item['id'] ?? ''));
            if (preg_match('/^task-(\d+)$/', $id, $match)) {
                return intval($match[1]);
            }
            if (preg_match('/^\d+$/', $id)) {
                return intval($id);
            }
        }
        return 0;
    }

    private static function normalizeBackground(string $background): string
    {
        $background = strtolower(trim($background));
        if (in_array($background, ['transparent', 'transparent_background', 'alpha'], true)) {
            return 'transparent';
        }
        if (in_array($background, ['opaque', 'white', 'solid'], true)) {
            return 'opaque';
        }
        return '';
    }

    private static function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $text = trim(strval($value));
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }
}
