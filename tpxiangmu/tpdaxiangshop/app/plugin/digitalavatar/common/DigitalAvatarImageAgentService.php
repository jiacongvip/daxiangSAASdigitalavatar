<?php

namespace app\plugin\digitalavatar\common;

use think\facade\Log;

class DigitalAvatarImageAgentService
{
    public static function isLlmReady(int $aid): bool
    {
        return self::resolveLlm($aid) !== [];
    }

    public static function normalizeStage(string $stage): string
    {
        $stage = strtolower(trim($stage));
        return in_array($stage, ['final', 'hd', 'upscale', 'high'], true) ? 'final' : 'draft';
    }

    public static function preferDraft(int $aid, ?array $settings = null): bool
    {
        if (!is_array($settings)) {
            $settings = DigitalAvatarCreditService::ensureSettings($aid);
        }
        return intval($settings['image_agent_prefer_draft'] ?? 1) === 1;
    }

    public static function imageStageProfile(int $aid, string $stage, ?array $settings = null): array
    {
        if (!is_array($settings)) {
            $settings = DigitalAvatarCreditService::ensureSettings($aid);
        }
        $stage = self::normalizeStage($stage);
        $isFinal = $stage === 'final';
        $modelId = intval($settings[$isFinal ? 'image_agent_hd_model_id' : 'image_agent_draft_model_id'] ?? 0);
        $size = trim(strval($settings[$isFinal ? 'image_agent_hd_size' : 'image_agent_draft_size'] ?? ''));
        $quality = strtolower(trim(strval($settings[$isFinal ? 'image_agent_hd_quality' : 'image_agent_draft_quality'] ?? '')));
        $model = [];
        if ($modelId > 0) {
            try {
                $model = DigitalAvatarModelService::resolveById($aid, $modelId, 'image');
            } catch (\Throwable $e) {
                $model = [];
            }
        }
        if ($model === [] && !$isFinal) {
            try {
                $model = DigitalAvatarModelService::resolveForTask($aid, 'image', 'gpt-image-2');
            } catch (\Throwable $e) {
                try {
                    $model = DigitalAvatarModelService::resolveForTask($aid, 'image', '');
                } catch (\Throwable $ignored) {
                    $model = [];
                }
            }
        }
        if ($size === '') {
            $size = '1024x1024';
        }
        if (!in_array($quality, ['low', 'medium', 'high'], true)) {
            $quality = $isFinal ? 'high' : 'low';
        }
        return [
            'ready' => $model !== [] ? 1 : 0,
            'stage' => $stage,
            'model_id' => intval($model['id'] ?? 0),
            'model_code' => strval($model['model_code'] ?? ''),
            'provider_key' => strval($model['provider_key'] ?? ''),
            'title' => strval($model['title'] ?? ''),
            'size' => $size,
            'quality' => $quality,
        ];
    }

    public static function chat(int $aid, array $input): array
    {
        return DigitalAvatarImageAgentRuntime::run(
            $aid,
            intval($input['bid'] ?? 0),
            intval($input['member_id'] ?? ($input['mid'] ?? 0)),
            $input
        );
    }

    public static function upscale(int $aid, array $input): array
    {
        return DigitalAvatarImageAgentRuntime::upscale(
            $aid,
            intval($input['bid'] ?? 0),
            intval($input['member_id'] ?? ($input['mid'] ?? 0)),
            $input
        );
    }

    public static function complete(array $llm, array $messages, array $tools = []): array
    {
        $payload = [
            'model' => strval($llm['model']),
            'messages' => array_values($messages),
            'temperature' => 0.3,
            'max_tokens' => 2000,
            'enable_thinking' => false,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        $url = self::chatUrl(strval($llm['base_url']));
        try {
            $decoded = self::postJson($url, strval($llm['api_key']), $payload, intval($llm['timeout'] ?? 45));
        } catch (\Throwable $e) {
            if ($tools !== [] && self::isToolsUnsupported($e->getMessage())) {
                $fallbackMessages = $messages;
                array_unshift($fallbackMessages, [
                    'role' => 'system',
                    'content' => '当前接口不支持原生 tools。你必须只输出一个 JSON 对象：{"tool":"generate_image|edit_image|upscale_image|ask_user|inspect_gallery|final","arguments":{...},"reply":"给用户的短句"}。不要 Markdown。',
                ]);
                return self::complete($llm, $fallbackMessages, []);
            }
            throw $e;
        }
        $message = $decoded['choices'][0]['message'] ?? [];
        if (!is_array($message)) {
            $message = [];
        }
        $content = self::extractMessageContent($decoded);
        $message['content'] = $content;
        $toolCalls = [];
        foreach ((array) ($message['tool_calls'] ?? []) as $index => $call) {
            if (!is_array($call)) {
                continue;
            }
            $name = strval($call['function']['name'] ?? ($call['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $args = $call['function']['arguments'] ?? ($call['arguments'] ?? []);
            if (is_string($args)) {
                $decodedArgs = json_decode($args, true);
                $args = is_array($decodedArgs) ? $decodedArgs : [];
            }
            $toolCalls[] = [
                'id' => strval($call['id'] ?? ('call_' . $index . '_' . $name)),
                'name' => $name,
                'arguments' => is_array($args) ? $args : [],
                'function' => [
                    'name' => $name,
                    'arguments' => json_encode(is_array($args) ? $args : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ];
        }
        $message['tool_calls'] = $toolCalls;
        return $message;
    }

    private static function isToolsUnsupported(string $message): bool
    {
        $text = strtolower($message);
        return strpos($text, 'tool') !== false
            || strpos($text, 'function call') !== false
            || strpos($text, 'unknown field') !== false
            || strpos($text, 'not supported') !== false;
    }

    private static function heuristicDecision(string $message, array $history, bool $hasReference): array
    {
        $compact = preg_replace('/\s+/u', '', $message);
        $isGreeting = (bool) preg_match('/^(你好|在吗|嗨|hi|hello|谢谢|感谢)[!！。.?？]*$/iu', $compact);
        $isVague = (bool) preg_match('/^(帮我|给我|来|做|画|生成)?(一|1)?(张|个)?(图|图片|海报)?(吧|啊|呀)?$/u', $compact)
            || (bool) preg_match('/^(好看一点|优化一下|帮我想|随便来一张)$/u', $compact);
        if (($isGreeting || ($isVague && mb_strlen($compact) <= 8)) && $history === [] && !$hasReference) {
            return [
                'action' => 'reply',
                'reply' => '可以。你更想做哪一类？直接回我一句就行：商品主图、人物写真、场景海报，或把图丢上来。',
                'prompt' => '',
                'use_reference' => 0,
                'ratio' => '',
            ];
        }

        return [
            'action' => 'generate',
            'reply' => '我来按这个方向出图，生成后你可以说「背景再暗一点」「换成俯视」继续改。',
            'prompt' => self::visualPromptForGenerate($message, $hasReference),
            'use_reference' => $hasReference ? 1 : 0,
            'ratio' => '',
        ];
    }

    public static function visualPromptForGenerate(string $message, bool $hasReference, string $userPrompt = ''): string
    {
        $demand = trim($userPrompt);
        if ($demand === '' || self::looksLikeAgentDump($demand)) {
            $demand = self::extractUserDemand($message);
        }
        $demand = trim(preg_replace('/^图\d+\s*/u', '', $demand));
        $demand = trim(preg_replace('/[。.!！]+$/u', '', $demand));
        if ($demand === '') {
            $demand = $hasReference ? '按圈选位置继续修改' : trim($message);
        }
        if (!$hasReference) {
            return $demand !== '' ? $demand : '生成一张清晰的图片';
        }
        $parts = [
            '请基于第1张参考图做局部修改，保持人物、姿势、服装、场景和光影不变，不要另生成无关新图。',
            '修改要求：' . $demand . '。',
        ];
        if (preg_match('/涂鸦|笔迹|圈选|圈出|圈中/u', $message . $userPrompt . $demand)) {
            $parts[] = '彩色笔迹只表示要改的位置，只改圈中区域，不要把笔迹画进成品。';
        }
        return implode('', $parts);
    }

    private static function looksLikeAgentDump(string $text): bool
    {
        return (bool) preg_match('/用户需求：|图\d+\s*是第\s*\d+\s*张图|出图尺寸：|当前画布是/u', $text);
    }

    private static function extractUserDemand(string $message): string
    {
        if (preg_match('/用户需求[：:]\s*(.+)/u', $message, $match)) {
            $line = trim(preg_replace('/\s+/u', ' ', strval($match[1])));
            $line = trim(preg_replace('/\s*(出图尺寸|当前画布|图\d+\s*上的彩色).*/u', '', $line));
            if ($line !== '') {
                return $line;
            }
        }
        if (self::looksLikeAgentDump($message)) {
            return '';
        }
        return trim($message);
    }

    private static function buildMessages(string $message, array $history, array $referenceImages, string $lastImageUrl, string $modelName, bool $hasReference, array $skill = []): array
    {
        $systemLines = [
            '你是「Agent 作图」智能体，负责理解用户的作图意图，再决定是继续问一句，还是调用图片模型出图。',
            '只输出一个 JSON 对象，不要 Markdown，不要解释。字段：',
            '{"action":"reply|generate","reply":"给用户看的中文短回复，不超过120字","prompt":"给图片模型的完整提示词","use_reference":true,"ratio":"1:1"}',
            '规则：',
            '1. 用户已经说出主体、场景、风格或明确修改指令时，action 必须是 generate。prompt 要补全构图、光影、材质、镜头、风格和不要出现的内容，具体可执行，不要只复述原话。',
            '2. 只有首轮极度含糊（例如「帮我做张图」）才 action=reply，且只问一个关键问题，同时给 2-3 个可直接选的方向。',
            '3. 用户说改、换成、再来一张、更暗、去掉某物、在这张图上改时，action=generate，use_reference=true，prompt 写清楚保留什么、改什么，并明确这是基于已提供的底图做局部修改。',
            '4. 闲聊、问能力、说谢谢时 action=reply，不要出图。',
            '5. 已有用户指定的图或上一张作品时，默认 use_reference=true。',
            '6. 用户会用「图1」「图2」指代图库。只有一张时就是图1，不要主动提「参考图」，也不要假设一定有图2。prompt 里按用户实际提到的编号写清每张图的用途。',
            '7. ratio 仅在用户明确提到比例或画幅时填写，否则空字符串。',
            '8. reply 要像设计师助手，口语、干脆，不要自称 AI。生成完成后不要再教用户写「图1参考图2」。',
            '9. prompt 只能写给图片模型看的画面指令，禁止出现「用户需求」「出图尺寸」「当前画布是」「图1 是第 1 张图」这类系统说明。有底图时写清保留什么、只改什么；涂鸦圈选时写明只改圈中区域且不要把笔迹画进成品。',
            '10. 用户消息里会附带图片。你必须先看图再决策：辨认人物、服装、颜色、场景、笔迹圈选位置，再写 prompt。不要只根据文字猜画面。图1 默认是当前要改的底图；彩色笔迹只表示修改位置，不要把笔迹画进成品。',
            $modelName !== '' ? ('当前图片模型：' . $modelName) : '按通用图片模型写提示词。',
            $hasReference ? '当前会话已有用户指定的图或上一张作品。' : '当前没有用户指定的图。',
        ];
        $skillName = trim(strval($skill['name'] ?? ''));
        $skillContent = trim(strval($skill['content'] ?? ''));
        if ($skillName !== '' || $skillContent !== '') {
            $systemLines[] = '当前技能：' . ($skillName !== '' ? $skillName : '自定义技能');
            if ($skillContent !== '') {
                $systemLines[] = '技能要求与流程（优先遵守，但不能违反上面的 JSON 输出格式）：';
                $systemLines[] = $skillContent;
            }
        }
        $system = implode("\n", $systemLines);

        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $item) {
            $messages[] = [
                'role' => $item['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $item['text'],
            ];
        }

        $text = '用户需求：' . $message;
        $imageUrls = $referenceImages;
        if ($lastImageUrl !== '' && !in_array($lastImageUrl, $imageUrls, true)) {
            array_unshift($imageUrls, $lastImageUrl);
        }
        $imageUrls = array_slice($imageUrls, 0, 4);
        foreach ($imageUrls as $index => $url) {
            $text .= "\n请查看图" . ($index + 1) . '（已附在消息中的第 ' . ($index + 1) . ' 张图）。';
        }
        if ($imageUrls === []) {
            $messages[] = ['role' => 'user', 'content' => $text];
            return $messages;
        }

        $content = [['type' => 'text', 'text' => $text]];
        $detail = count($imageUrls) <= 2 ? 'high' : 'low';
        foreach ($imageUrls as $url) {
            $embedded = self::embedImageForVision($url);
            if ($embedded === '') {
                continue;
            }
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $embedded, 'detail' => $detail],
            ];
        }
        if (count($content) === 1) {
            $messages[] = ['role' => 'user', 'content' => $text];
            return $messages;
        }
        $messages[] = ['role' => 'user', 'content' => $content];
        return $messages;
    }

    private static function parseDecision(string $content, string $message, bool $hasReference): array
    {
        $payload = self::extractJson($content);
        $action = strtolower(trim(strval($payload['action'] ?? '')));
        if (!in_array($action, ['reply', 'generate'], true)) {
            $action = 'generate';
        }
        $prompt = trim(strval($payload['prompt'] ?? ''));
        if ($action === 'generate' && ($prompt === '' || self::looksLikeAgentDump($prompt))) {
            $prompt = self::visualPromptForGenerate($message, $hasReference);
        }
        $useReference = $payload['use_reference'] ?? $payload['useReference'] ?? $hasReference;
        if (is_string($useReference)) {
            $useReference = in_array(strtolower($useReference), ['1', 'true', 'yes'], true);
        }
        $ratio = trim(strval($payload['ratio'] ?? ''));
        if ($ratio !== '' && !preg_match('/^\d{1,2}:\d{1,2}$/', $ratio) && !preg_match('/^\d{3,4}x\d{3,4}$/i', $ratio)) {
            $ratio = '';
        }

        return [
            'action' => $action,
            'reply' => mb_substr(trim(strval($payload['reply'] ?? '')) ?: ($action === 'generate' ? '我来出图，生成后你可以直接说要改哪里。' : '再说细一点主体和场景，我就能帮你画。'), 0, 200),
            'prompt' => mb_substr($prompt, 0, 2000),
            'use_reference' => $useReference ? 1 : 0,
            'ratio' => $ratio,
        ];
    }

    public static function resolveLlm(int $aid): array
    {
        $settings = DigitalAvatarCreditService::ensureSettings($aid);
        if (intval($settings['image_agent_llm_enabled'] ?? 0) !== 1) {
            return [];
        }
        $baseUrl = rtrim(trim(strval($settings['image_agent_llm_base_url'] ?? '')), '/');
        $apiKey = trim(strval($settings['image_agent_llm_api_key'] ?? ''));
        $model = trim(strval($settings['image_agent_llm_model'] ?? ''));
        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            return [];
        }
        return [
            'driver' => 'plugin',
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'model' => $model,
            'timeout' => max(15, min(180, intval($settings['image_agent_llm_timeout'] ?? 45))),
        ];
    }

    private static function requestChat(array $llm, array $messages): string
    {
        $payload = [
            'model' => strval($llm['model']),
            'messages' => array_values($messages),
            'temperature' => 0.4,
            'max_tokens' => 1600,
            'enable_thinking' => false,
        ];
        $url = self::chatUrl(strval($llm['base_url']));
        $decoded = self::postJson($url, strval($llm['api_key']), $payload, intval($llm['timeout'] ?? 45));
        $content = self::extractMessageContent($decoded);
        if ($content === '') {
            throw new \RuntimeException('大模型未返回内容');
        }
        return $content;
    }

    private static function chatUrl(string $baseUrl): string
    {
        if (preg_match('#/chat/completions$#i', $baseUrl)) {
            return $baseUrl;
        }
        if (preg_match('#/v1$#i', $baseUrl)) {
            return $baseUrl . '/chat/completions';
        }
        return $baseUrl . '/v1/chat/completions';
    }

    private static function postJson(string $url, string $apiKey, array $payload, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => max(15, $timeout),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($response === false || $error !== '') {
            throw new \RuntimeException('大模型请求失败：' . ($error ?: '网络错误'));
        }
        $decoded = json_decode(strval($response), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('大模型返回不是 JSON');
        }
        if ($httpCode >= 400 || !empty($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? ($decoded['msg'] ?? ('HTTP ' . $httpCode));
            throw new \RuntimeException('大模型调用失败：' . $msg);
        }
        return $decoded;
    }

    private static function extractMessageContent(array $decoded): string
    {
        $message = $decoded['choices'][0]['message'] ?? [];
        $content = $message['content'] ?? '';
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $item) {
                if (is_string($item)) {
                    $parts[] = $item;
                } elseif (is_array($item)) {
                    $parts[] = strval($item['text'] ?? ($item['content'] ?? ''));
                }
            }
            $content = implode('', $parts);
        }
        $content = trim(strval($content));
        if ($content === '') {
            $content = trim(strval($message['reasoning_content'] ?? ''));
        }
        return $content;
    }

    private static function extractJson(string $content): array
    {
        $text = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $match)) {
            $text = trim($match[1]);
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        throw new \RuntimeException('智能体返回不是有效 JSON');
    }

    private static function normalizeHistory($history): array
    {
        if (!is_array($history)) {
            return [];
        }
        $items = [];
        foreach (array_slice($history, -12) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $role = strval($row['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim(strval($row['text'] ?? ($row['content'] ?? '')));
            if ($text === '') {
                continue;
            }
            $items[] = [
                'role' => $role,
                'text' => mb_substr($text, 0, 500),
            ];
        }
        return $items;
    }

    private static function normalizeUrls($value, int $limit): array
    {
        if (is_string($value) && $value !== '') {
            $value = [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        $urls = [];
        foreach ($value as $item) {
            $url = is_array($item) ? strval($item['url'] ?? ($item['img'] ?? '')) : strval($item);
            $url = self::firstUrl($url);
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
            if (count($urls) >= $limit) {
                break;
            }
        }
        return $urls;
    }

    public static function firstUrl($value): string
    {
        $url = trim(strval($value));
        if ($url === '') {
            return '';
        }
        if (stripos($url, 'data:image/') === 0) {
            return strlen($url) > 2500000 ? '' : $url;
        }
        if (strlen($url) > 2000) {
            return '';
        }
        if (stripos($url, 'website.php') !== false
            || stripos($url, 'playTaskMedia') !== false
            || stripos($url, 'playAssetMedia') !== false) {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }
        return $url;
    }

    public static function embedImageForVision(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (stripos($url, 'data:image/') === 0) {
            $binary = self::decodeDataUrl($url);
            $embedded = $binary !== '' ? self::binaryToJpegDataUrl($binary) : '';
            return $embedded !== '' ? $embedded : $url;
        }
        $binary = self::downloadImage($url);
        if ($binary === '') {
            return $url;
        }
        $embedded = self::binaryToJpegDataUrl($binary);
        return $embedded !== '' ? $embedded : $url;
    }

    private static function downloadImage(string $url): string
    {
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_MAXFILESIZE => 6 * 1024 * 1024,
        ]);
        $body = curl_exec($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        if ($httpCode >= 400 || !is_string($body) || $body === '' || strlen($body) > 6 * 1024 * 1024) {
            return '';
        }
        return $body;
    }

    private static function decodeDataUrl(string $url): string
    {
        if (!preg_match('#^data:image/[^;]+;base64,(.+)$#is', $url, $matches)) {
            return '';
        }
        $binary = base64_decode(strval($matches[1]), true);
        return is_string($binary) ? $binary : '';
    }

    private static function binaryToJpegDataUrl(string $binary): string
    {
        if ($binary === '' || !function_exists('imagecreatefromstring')) {
            return '';
        }
        $image = @imagecreatefromstring($binary);
        if (!$image) {
            return 'data:image/jpeg;base64,' . base64_encode($binary);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $maxSide = 1280;
        if ($width > 0 && $height > 0 && max($width, $height) > $maxSide) {
            $scale = $maxSide / max($width, $height);
            $nextWidth = max(1, intval($width * $scale));
            $nextHeight = max(1, intval($height * $scale));
            $resized = imagecreatetruecolor($nextWidth, $nextHeight);
            if ($resized) {
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $nextWidth, $nextHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }
        }
        ob_start();
        imagejpeg($image, null, 78);
        $jpeg = strval(ob_get_clean());
        imagedestroy($image);
        if ($jpeg === '') {
            return '';
        }
        return 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }
}
